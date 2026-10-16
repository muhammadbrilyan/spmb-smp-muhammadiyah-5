<?php
/**
 * Fase 11 B3 / Fase 12 C2 — Cadangan database.
 *
 * Jalur A (bila tersedia): mysqldump + proc_open (cepat).
 * Jalur B (otomatis bila proc_open/mysqldump dinonaktifkan — lazim di
 * shared hosting): dump PDO murni (lihat tools/backup-db-lib.php),
 * streaming ke gzip, hemat memori, tanpa exec/shell apa pun.
 *
 * Output : storage/backup/db/spmb-<YYYYMMDD-HHMMSS>.sql.gz
 * Rotasi : file lebih tua dari 14 hari otomatis dihapus.
 * Password DB dikirim lewat env MYSQL_PWD (jalur A — TIDAK lewat argumen
 * proses) dan TIDAK pernah dicetak di kedua jalur.
 *
 * CLI-ONLY. Pakai:
 *   php tools/backup-db.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/backup-db-lib.php';

/* ------------------------------------------------------------- */
$host = env_val('DB_HOST') ?: '127.0.0.1';
$port = env_val('DB_PORT') ?: '3306';
$user = env_val('DB_USER') ?: 'root';
$pass = (string) env_val('DB_PASS');
$name = env_val('DB_NAME') ?: 'db_spmb_mualimku';

$info = ['host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass, 'name' => $name];

$dir = storage_path('backup/db');
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    backup_gagal('Gagal membuat folder storage/backup/db.');
}

$ts   = date('Ymd-His');
$tmp  = $dir . '/.spmb-' . $ts . '.sql';
$gzip = $dir . '/spmb-' . $ts . '.sql.gz';
$mulai = microtime(true);

if (backup_bisa_mysqldump()) {
    /* ---------------- Jalur A: mysqldump ---------------- */
    $dump = backup_cari_mysqldump();
    $cmd = '"' . $dump . '"'
        . ' --host=' . escapeshellarg($host)
        . ' --port=' . escapeshellarg((string) $port)
        . ' --user=' . escapeshellarg($user)
        . ' --single-transaction --routines --triggers'
        . ' --result-file=' . escapeshellarg($tmp)
        . ' ' . escapeshellarg($name);

    /* MYSQL_PWD lewat environment — tidak tampil di process list.
     * Bawa SELURUH environment proses saat ini (getenv() tanpa argumen),
     * agar variabel sistem (SystemRoot, TEMP, dll.) tidak hilang. */
    $env = array_merge((array) getenv(), [
        'MYSQL_PWD' => $pass,
    ]);
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
    if (!is_resource($proc)) {
        backup_gagal('Gagal menjalankan proses mysqldump.');
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
        backup_gagal('mysqldump keluar dengan kode ' . $kode . ($pesan !== '' ? ': ' . $pesan : ''));
    }

    $isi = file_get_contents($tmp);
    if ($isi === false) {
        @unlink($tmp);
        backup_gagal('Gagal membaca hasil dump sementara.');
    }
    $gz = gzencode($isi, 6);
    unset($isi);
    if ($gz === false || file_put_contents($gzip, $gz) === false) {
        @unlink($tmp);
        @unlink($gzip);
        backup_gagal('Gagal mengompres hasil dump.');
    }
    unset($gz);
    @unlink($tmp);
    $metode = 'mysqldump + proc_open';
} else {
    /* ---------------- Jalur B: dump PDO murni ---------------- */
    backup_dump_pdo($info, $gzip);
    $metode = 'dump PDO murni (tanpa exec/proc_open)';
}

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
    . '  Metode  : ' . $metode . "\n"
    . '  Ukuran  : ' . round(filesize($gzip) / 1024, 1) . " KB (gzip)\n"
    . '  Durasi  : ' . $durasi . " detik\n"
    . '  Rotasi  : ' . $sisa . ' cadangan tersimpan (retensi 14 hari, ' . $hapus . " dihapus)\n"
    . "Catatan: file ini MEMUAT data aplikasi — jangan dibagikan; folder storage/ diblokir dari web (.htaccess).\n");
exit(0);
