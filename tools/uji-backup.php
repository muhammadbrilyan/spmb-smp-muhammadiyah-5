<?php
/**
 * Fase 12 C2 — Uji cadangan: dump PDO murni -> impor ke database sementara
 * -> bandingkan jumlah baris tiap tabel -> hapus database sementara.
 *
 * HANYA untuk lingkungan pengembangan (membuat & menghapus database).
 * Butuh user DB dengan hak CREATE DATABASE (mis. root). Bila .env memakai
 * user aplikasi terbatas, setel environment UJI_BACKUP_USER / UJI_BACKUP_PASS
 * (nilai ini diutamakan di atas .env), contoh Windows:
 *   set UJI_BACKUP_USER=root&& set UJI_BACKUP_PASS=&& php tools/uji-backup.php
 * CLI-ONLY. Pakai:
 *   php tools/uji-backup.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/backup-db-lib.php';

function uji_gagal($pesan)
{
    fwrite(STDERR, 'GAGAL: ' . $pesan . "\n");
    exit(1);
}

/* Override lewat environment — diutamakan di atas .env (user uji butuh
 * hak CREATE DATABASE yang tidak dimiliki user aplikasi).
 * Windows tidak mendukung env kosong: UJI_BACKUP_PASS yang tidak di-set
 * dianggap string kosong (root XAMPP tanpa password). */
$host = getenv('UJI_BACKUP_HOST') ?: (env_val('DB_HOST') ?: '127.0.0.1');
$port = getenv('UJI_BACKUP_PORT') ?: (env_val('DB_PORT') ?: '3306');
$name = env_val('DB_NAME') ?: 'db_spmb_mualimku';
$ujiUser = getenv('UJI_BACKUP_USER');
if ($ujiUser !== false && $ujiUser !== '') {
    $user = $ujiUser;
    $passDb = getenv('UJI_BACKUP_PASS');
    $pass = ($passDb === false) ? '' : $passDb;
} else {
    $user = env_val('DB_USER') ?: 'root';
    $pass = (string) env_val('DB_PASS');
}
$info = ['host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass, 'name' => $name];

$dbUji   = $name . '_uji_backup';
$gzUji   = sys_get_temp_dir() . '/spmb-uji-backup-' . date('Ymd-His') . '.sql.gz';

fwrite(STDOUT, "UJI CADANGAN (dump PDO -> database sementara)\n");
fwrite(STDOUT, "  Sumber : {$name}\n  Tujuan : {$dbUji} (sementara)\n");

/* 1) Dump PDO murni (jalur shared hosting). */
$jumlahTabel = backup_dump_pdo($info, $gzUji);
if (!is_file($gzUji) || filesize($gzUji) === 0) {
    uji_gagal('Dump tidak menghasilkan file.');
}
fwrite(STDOUT, "  Dump   : {$jumlahTabel} tabel, " . round(filesize($gzUji) / 1024, 1) . " KB (gzip)\n");

try {
    $pdoRoot = backup_pdo($info, false);
    $pdoRoot->exec("DROP DATABASE IF EXISTS `$dbUji`");
    $pdoRoot->exec("CREATE DATABASE `$dbUji` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdoUji = backup_pdo(['host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass, 'name' => $dbUji]);

    /* 2) Impor per baris (format dump kita: satu statement per baris). */
    $gz = gzopen($gzUji, 'rb');
    if ($gz === false) {
        uji_gagal('Gagal membuka dump uji.');
    }
    $impor = 0;
    while (($barisAsli = gzgets($gz)) !== false) {
        $baris = rtrim($barisAsli, "\r\n");
        if (trim($baris) === '') {
            continue;
        }
        $pdoUji->exec($baris);
        $impor++;
    }
    gzclose($gz);
    fwrite(STDOUT, "  Impor  : {$impor} statement dieksekusi\n");

    /* 3) Bandingkan jumlah baris per tabel (kualifikasi nama DB —
     *    $pdoRoot sengaja tanpa dbname). */
    $selisih = 0;
    foreach ($pdoRoot->query("SHOW TABLES FROM `$name`")->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $a = (int) $pdoRoot->query("SELECT COUNT(*) FROM `$name`.`$t`")->fetchColumn();
        $b = (int) $pdoUji->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        $tanda = ($a === $b) ? 'OK' : 'SELISIH';
        if ($a !== $b) {
            $selisih++;
        }
        fwrite(STDOUT, '  ' . str_pad($t, 28) . " {$a} -> {$b}  {$tanda}\n");
    }

    /* 4) Bersihkan. */
    $pdoUji = null;
    $pdoRoot->exec("DROP DATABASE IF EXISTS `$dbUji`");
    @unlink($gzUji);

    if ($selisih > 0) {
        uji_gagal("UJI CADANGAN GAGAL — {$selisih} tabel berbeda jumlah barisnya.");
    }
    fwrite(STDOUT, "UJI CADANGAN LULUS — semua tabel identik jumlah barisnya; database sementara dihapus.\n");
    exit(0);
} catch (PDOException $e) {
    @unlink($gzUji);
    try {
        $pdoRoot->exec("DROP DATABASE IF EXISTS `$dbUji`");
    } catch (Throwable $t) {
        /* abaikan */
    }
    uji_gagal('Kesalahan database saat uji: ' . $e->getMessage());
}
