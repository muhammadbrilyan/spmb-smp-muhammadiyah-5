<?php
/**
 * Koneksi database MySQL via PDO (singleton).
 *
 * Berkas ini dipanggil dari includes/config.php SETELAH konstanta
 * DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS didefinisikan.
 *
 * Gaya "tahan-gagal": bila MySQL berhenti / kredensial salah, db()
 * mengembalikan null dan aplikasi jatuh ke data dummy di
 * includes/dummy.php — antarmuka tetap terbuka tanpa error fatal.
 */

function db()
{
    static $pdo = null;
    static $sudah_dicoba = false;

    if ($sudah_dicoba) {
        return $pdo;
    }
    $sudah_dicoba = true;

    if (!defined('DB_NAME') || DB_NAME === '') {
        return null;
    }

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (Throwable $e) {
        $pdo = null;
    }

    return $pdo;
}

/** true bila koneksi database tersedia. */
function db_aktif()
{
    return db() !== null;
}
