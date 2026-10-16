<?php
/**
 * Fase 11 B3 — Cadangan database (mysqldump -> gzip).
 *
 * Output : storage/backup/db/spmb-<YYYYMMDD-HHMMSS>.sql.gz
 * Rotasi : file lebih tua dari 14 hari otomatis dihapus (max 14 cadangan aktif).
 * Password DB dikirim lewat env MYSQL_PWD (TIDAK lewat argumen proses —
 * agar tidak muncul di process list) dan TIDAK pernah dicetak.
 *
 * CLI-ONLY. Pakai:
 *   php tools/backup-db.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

function gagal_backup($pesan)
{
    fwrite(STDERR, 'GAGAL: ' . $pesan . "\n");
    exit(1);
}

/* Cari mysqldump: env MYSQLDUMP_PATH -> XAMPP -> PATH -> umum. */
function cari_mysqldump()
{
    $kandidat = [];
    $envPath = getenv('MYSQLDUMP_PATH');
    if ($envPath) {
        $kandidat[] = $envPath;
    }
    $bin = dirname(PHP_BINARY);           /* C:/xampp/php */
    $kandidat[] = $bin . '/../mysql/bin/mysqldump.exe';
    $kandidat[] = $bin . '/mysqldump.exe';
    $kandidat[] = '/usr/bin/mysqldump';
    $kandidat[] = '/usr/local/bin/mysqldump';
    foreach ($kandidat as $k) {
        if ($k !== '' && is_file($k)) {
            return $k;
        }
    }
    $cari = (DIRECTORY_SEPARATOR === '\\') ? 'where mysqldump' : 'command -v mysqldump';
    $out = @shell_exec($cari . ' 2>NUL');
    $out = $out ? trim($out) : '';
    if ($out !== '' && is_file($out)) {
        return $out;
    }
    return null;
}

/* ------------------------------------------------------------- */
$host = env_val('DB_HOST') ?: '127.0.0.1';
$port = env_val('DB_PORT') ?: '3306';
$user = env_val('DB_USER') ?: 'root';
$pass = (string) env_val('DB_PASS');
$name = env_val('DB_NAME') ?: 'db_spmb_mualimku';

$dump = cari_mysqldump();
if ($dump === null) {
    gagal_backup('mysqldump tidak ditemukan. Setel env MYSQLDUMP_PATH atau gunakan instalasi XAMPP/MySQL lengkap.');
}

$dir = dirname(__DIR__) . '/storage/backup/db';
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    gagal_backup('Gagal membuat folder storage/backup/db.');
}

$ts   = date('Ymd-His');
$tmp  = $dir . '/.spmb-' . $ts . '.sql';
$gzip = $dir . '/spmb-' . $ts . '.sql.gz';

$cmd = '"' . $dump . '"'
    . ' --host=' . escapeshellarg($host)
    . ' --port=' . escapeshellarg((string) $port)
    . ' --user=' . escapeshellarg($user)
    . ' --single-transaction --routines --triggers'
    . ' --result-file=' . escapeshellarg($tmp)
    . ' ' . escapeshellarg($name);

/* MYSQL_PWD lewat environment — tidak tampil di process list.
 * Bawa SELURUH environment proses saat ini (getenv() tanpa argumen),
 * agar variabel sistem (SystemRoot, TEMP, dll.) tidak hilang —
 * tanpa itu winsock/winresolv bisa gagal membuat socket. */
$env = array_merge((array) getenv(), [
    'MYSQL_PWD' => $pass,
]);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
$mulai = microtime(true);
$proc = proc_open($cmd, $descriptors, $pipes, null, $env);
if (!is_resource($proc)) {
    gagal_backup('Gagal menjalankan proses mysqldump.');
}
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$kode = proc_close($proc);

if ($kode !== 0 || !is_file($tmp) || filesize($tmp) === 0) {
    @unlink($tmp);
    $pesan = trim((string) $stderr);
    /* stderr mysqldump tidak memuat password (hanya user/host) — aman dicetak. */
    gagal_backup('mysqldump keluar dengan kode ' . $kode . ($pesan !== '' ? ': ' . $pesan : ''));
}

/* Kompres. */
$isi = file_get_contents($tmp);
if ($isi === false) {
    @unlink($tmp);
    gagal_backup('Gagal membaca hasil dump sementara.');
}
$gz = gzencode($isi, 6);
unset($isi);
if ($gz === false || file_put_contents($gzip, $gz) === false) {
    @unlink($tmp);
    @unlink($gzip);
    gagal_backup('Gagal mengompres hasil dump.');
}
unset($gz);
@unlink($tmp);
if (function_exists('chmod')) {
    @chmod($gzip, 0600);
}
$durasi = round(microtime(true) - $mulai, 1);

/* Rotasi: simpan 14 hari terakhir. */
$hapus = 0;
foreach (glob($dir . '/spmb-*.sql.gz') ?: [] as $f) {
    if (@filemtime($f) < time() - 14 * 86400) {
        @unlink($f);
        $hapus++;
    }
}
$sisa = count(glob($dir . '/spmb-*.sql.gz') ?: []);

fwrite(STDOUT, "CADANGAN DATABASE BERHASIL\n"
    . '  File    : storage/backup/db/' . basename($gzip) . "\n"
    . '  Ukuran  : ' . round(filesize($gzip) / 1024, 1) . " KB (gzip)\n"
    . '  Durasi  : ' . $durasi . " detik\n"
    . '  Rotasi  : ' . $sisa . ' cadangan tersimpan (retensi 14 hari, ' . $hapus . " dihapus)\n"
    . "Catatan: file ini MEMUAT data aplikasi — jangan dibagikan; folder storage/ diblokir dari web (.htaccess).\n");
exit(0);
