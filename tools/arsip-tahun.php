<?php
/**
 * Arsip massal pendaftar satu tahun — persiapan tahun ajaran baru.
 *
 * Tahun dibaca dari nomor pendaftaran REG-YYYY-NNNN (tabel pendaftar tidak
 * menyimpan kolom tahun). Pengarsipan bersifat SOFT: baris pendaftar,
 * pembayaran, dokumen, dan riwayat seleksi tetap utuh; pendaftar keluar dari
 * antrean/statistik/cek-status/tautan bayar dan akun portal siswanya
 * dinonaktifkan. Pemulihan per pendaftar tetap lewat Data Pendaftar → Arsip.
 *
 * Default DRY-RUN. Eksekusi nyata memerlukan --terapkan dan otomatis membuat
 * cadangan database lebih dulu (dump PDO portabel, tanpa mysqldump).
 *
 * CLI-ONLY. Pakai:
 *   php tools/arsip-tahun.php                          ringkasan per tahun
 *   php tools/arsip-tahun.php --tahun=2026             simulasi
 *   php tools/arsip-tahun.php --tahun=2026 --terapkan  eksekusi + cadangan
 *   php tools/arsip-tahun.php --tahun=2026 --terapkan --oleh=operator
 *   php tools/arsip-tahun.php --tahun=2026 --terapkan --tanpa-cadangan
 *
 * Opsi lain: --alasan="..." (min 5 karakter), --bantuan.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/backup-db-lib.php';

$opsi = [
    'tahun'         => '',
    'terapkan'      => false,
    'tanpa_cadangan' => false,
    'alasan'        => '',
    'oleh'          => '',
    'bantuan'       => false,
];

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--terapkan') {
        $opsi['terapkan'] = true;
    } elseif ($arg === '--tanpa-cadangan') {
        $opsi['tanpa_cadangan'] = true;
    } elseif ($arg === '--bantuan' || $arg === '--help' || $arg === '-h') {
        $opsi['bantuan'] = true;
    } elseif (strpos($arg, '--tahun=') === 0) {
        $opsi['tahun'] = trim(substr($arg, 8));
    } elseif (strpos($arg, '--alasan=') === 0) {
        $opsi['alasan'] = trim(substr($arg, 9));
    } elseif (strpos($arg, '--oleh=') === 0) {
        $opsi['oleh'] = trim(substr($arg, 7));
    } else {
        fwrite(STDERR, "Argumen tidak dikenal: {$arg}\nPakai --bantuan untuk cara pakai.\n");
        exit(1);
    }
}

if ($opsi['bantuan']) {
    fwrite(STDOUT,
        "ARSIP PENDAFTAR PER TAHUN — SPMB\n\n"
        . "  php tools/arsip-tahun.php\n"
        . "      Ringkasan jumlah pendaftar aktif per tahun (dari nomor REG).\n\n"
        . "  php tools/arsip-tahun.php --tahun=2026\n"
        . "      Simulasi: tampilkan kandidat, tidak mengubah data.\n\n"
        . "  php tools/arsip-tahun.php --tahun=2026 --terapkan\n"
        . "      Eksekusi. Cadangan database dibuat otomatis lebih dulu.\n"
        . "      Tambahan: --oleh=<username operator/bendahara>\n"
        . "                --alasan=\"alasan pengarsipan\" (min 5 karakter)\n"
        . "                --tanpa-cadangan (tidak disarankan)\n\n"
        . "Pengarsipan TIDAK menghapus baris/pembayaran/dokumen. Pulihkan satu per\n"
        . "satu lewat Data Pendaftar → filter Arsip → Pulihkan.\n");
    exit(0);
}

if (db() === null) {
    fwrite(STDERR, "GAGAL: koneksi database tidak tersedia (periksa .env).\n");
    exit(1);
}

/* Ringkasan bila tahun tidak dipilih. */
if ($opsi['tahun'] === '') {
    $ringkas = pendaftar_arsip_tahun_ringkasan();
    fwrite(STDOUT, "ARSIP PENDAFTAR PER TAHUN — " . date('Y-m-d H:i:s') . " WIB\n");
    if (!$ringkas) {
        fwrite(STDOUT, "Tidak ada pendaftar aktif (semua sudah terarsip atau tabel kosong).\n");
        exit(0);
    }
    fwrite(STDOUT, "Pendaftar aktif per tahun:\n");
    foreach ($ringkas as $r) {
        fwrite(STDOUT, '  ' . $r['tahun'] . ' — ' . $r['jumlah'] . " pendaftar\n");
    }
    fwrite(STDOUT, "\nPilih tahun untuk simulasi: php tools/arsip-tahun.php --tahun=YYYY\n");
    exit(0);
}

if (!preg_match('/^\d{4}$/', $opsi['tahun'])
    || (int) $opsi['tahun'] < 2000 || (int) $opsi['tahun'] > (int) date('Y')) {
    fwrite(STDERR, "GAGAL: --tahun harus 4 angka antara 2000 dan " . date('Y') . ".\n");
    exit(1);
}

$alasan = $opsi['alasan'] !== ''
    ? $opsi['alasan']
    : 'Arsip otomatis tahun ' . $opsi['tahun'] . ' (pergantian tahun ajaran)';
if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 255) {
    fwrite(STDERR, "GAGAL: --alasan harus 5-255 karakter.\n");
    exit(1);
}

$oleh = null;
if ($opsi['oleh'] !== '') {
    $baris = q_all(
        "SELECT id FROM users WHERE username = ? AND role IN ('operator','bendahara') AND is_active = 1 LIMIT 1",
        [$opsi['oleh']]
    );
    if (!$baris) {
        fwrite(STDERR, "GAGAL: akun '{$opsi['oleh']}' tidak ditemukan / bukan staf aktif.\n");
        exit(1);
    }
    $oleh = $baris[0]['id'];
}

$kandidat = pendaftar_arsip_tahun_kandidat($opsi['tahun']);

fwrite(STDOUT, "ARSIP PENDAFTAR TAHUN {$opsi['tahun']} — " . date('Y-m-d H:i:s') . " WIB\n");
fwrite(STDOUT, 'Mode    : ' . ($opsi['terapkan'] ? 'EKSEKUSI NYATA' : 'DRY-RUN (tambah --terapkan utk eksekusi)') . "\n");
fwrite(STDOUT, 'Alasan  : ' . $alasan . "\n");
fwrite(STDOUT, 'Oleh    : ' . ($opsi['oleh'] !== '' ? $opsi['oleh'] : '—') . "\n");
fwrite(STDOUT, 'Kandidat: ' . count($kandidat) . " pendaftar\n\n");

if (!$kandidat) {
    fwrite(STDOUT, "Tidak ada pendaftar aktif pada tahun tersebut — tidak ada yang dikerjakan.\n");
    exit(0);
}

if ((int) $opsi['tahun'] === (int) date('Y')) {
    fwrite(STDOUT, "PERINGATAN: tahun {$opsi['tahun']} sama dengan tahun kalender berjalan — pastikan SPMB tahun ini benar-benar selesai.\n\n");
}

$tampil = array_slice($kandidat, 0, 50);
foreach ($tampil as $k) {
    fwrite(STDOUT, '  ' . $k['no_pendaftaran'] . ' — ' . $k['nama_siswa']
        . ' (daftar ' . substr((string) $k['created_at'], 0, 10) . ")\n");
}
if (count($kandidat) > count($tampil)) {
    fwrite(STDOUT, '  ... dan ' . (count($kandidat) - count($tampil)) . " lainnya\n");
}

if (!$opsi['terapkan']) {
    fwrite(STDOUT, "\nDRY-RUN selesai. Untuk mengeksekusi ulangi dengan --terapkan.\n");
    exit(0);
}

/* Cadangan otomatis sebelum menulis. */
if (!$opsi['tanpa_cadangan']) {
    $dir = storage_path('backup/db');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        fwrite(STDERR, "GAGAL: tidak bisa membuat folder storage/backup/db.\n");
        exit(1);
    }
    $info = [
        'host' => env_val('DB_HOST') ?: '127.0.0.1',
        'port' => env_val('DB_PORT') ?: '3306',
        'user' => env_val('DB_USER') ?: 'root',
        'pass' => (string) env_val('DB_PASS'),
        'name' => env_val('DB_NAME') ?: 'db_spmb_mualimku',
    ];
    $gzip = $dir . '/spmb-sebelum-arsip-' . $opsi['tahun'] . '-' . date('Ymd-His') . '.sql.gz';
    backup_dump_pdo($info, $gzip);
    if (function_exists('chmod')) {
        @chmod($gzip, 0600);
    }
    fwrite(STDOUT, "\nCadangan: storage/backup/db/" . basename($gzip)
        . ' (' . round(filesize($gzip) / 1024, 1) . " KB)\n");
} else {
    fwrite(STDOUT, "\nPERINGATAN: --tanpa-cadangan dipakai — tidak ada cadangan database baru.\n");
}

$hasil = pendaftar_arsip_tahun($opsi['tahun'], $alasan, $oleh);
if (!$hasil['ok']) {
    fwrite(STDERR, 'GAGAL: ' . $hasil['pesan'] . "\n");
    exit(1);
}

fwrite(STDOUT, 'SELESAI: ' . $hasil['jumlah'] . ' pendaftar diarsipkan, '
    . $hasil['akun'] . " akun portal dinonaktifkan.\n"
    . "Pemulihan per pendaftar: Data Pendaftar → filter Arsip → Pulihkan.\n");
exit(0);
