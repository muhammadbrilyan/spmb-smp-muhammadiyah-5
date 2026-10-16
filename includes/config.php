<?php
/**
 * Konfigurasi aplikasi (PHP Native).
 * Data utama: MySQL db_spmb_mualimku (database/db_spmb_mualimku.sql).
 * Fallback: includes/dummy.php dipakai otomatis bila database tidak tersedia.
 */

if (session_status() === PHP_SESSION_NONE) {
    /* --- Session cookie hardening --- */
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,   /* true hanya di HTTPS */
        'httponly' => true,       /* JS tidak bisa baca cookie */
        'samesite' => 'Lax',      /* cegah CSRF via cross-site POST */
    ]);
    session_start();
}

/* --- Security headers (dipanggil sebelum output apa pun) --- */
if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header_remove('X-Powered-By');
}

/* --- Error display: ON hanya di development (localhost) --- */
$__host = $_SERVER['HTTP_HOST'] ?? '';
$__dev  = (strpos($__host, 'localhost') === 0)
       || (strpos($__host, '127.0.0.1') === 0)
       || (strpos($__host, '::1') === 0);
error_reporting(E_ALL);
ini_set('display_errors', $__dev ? '1' : '0');
ini_set('log_errors', '1');

define('APP_NAME', 'SMP Muhammadiyah 5 Kota Tangerang');
define('APP_SHORT', 'SMP Muhammadiyah 5');
define('APP_TAGLINE', 'SIMS & SPMB Portal');
define('APP_TAHUN', 'T.A. 2025/2026');

/** Deteksi base URL otomatis agar aman dipanggil di sub-folder XAMPP (http://localhost/mualimku). */
$__root = str_replace('\\', '/', dirname(__DIR__));
$__doc  = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/\\'));
$__base = '';
if ($__doc !== '' && strpos($__root, $__doc) === 0) {
    $__base = substr($__root, strlen($__doc));
}
define('BASE_URL', rtrim($__base, '/'));

/* ---------------------------------------------------------------
 * Loader .env sederhana — cari di (1) satu tingkat di atas root
 * proyek (disarankan produksi: .env & storage DI LUAR web root),
 * lalu (2) root proyek (lokal/dev). Nilai menimpa default di bawah.
 * Jangan commit .env ke git.
 * --------------------------------------------------------------- */
$__envFile = null;
foreach ([
    dirname(__DIR__, 2) . '/.env',   /* di luar web root — dipakai bila ada */
    dirname(__DIR__) . '/.env',      /* root proyek — fallback lokal */
] as $__e) {
    if (is_file($__e) && is_readable($__e)) {
        $__envFile = $__e;
        break;
    }
}
if ($__envFile !== null) {
    foreach (file($__envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $__baris) {
        $__baris = trim($__baris);
        if ($__baris === '' || $__baris[0] === '#' || strpos($__baris, '=') === false) {
            continue;
        }
        list($__k, $__v) = explode('=', $__baris, 2);
        $__k = trim($__k);
        $__v = trim($__v);
        /* Hapus tanda kutip ganda/satu bila ada. */
        if (strlen($__v) >= 2 && ($__v[0] === '"' || $__v[0] === "'") && $__v[0] === $__v[strlen($__v) - 1]) {
            $__v = substr($__v, 1, -1);
        }
        if ($__k !== '') {
            $_ENV[$__k] = $__v;
            putenv($__k . '=' . $__v);
        }
    }
}

/* Ambil nilai dari .env bila ada, selain itu pakai default. */
function env_val($key, $default = '')
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null || $v === '') ? $default : $v;
}

/* ---------------------------------------------------------------
 * Folder penyimpanan berkas pribadi (unggahan) & cadangan.
 * Default: <root>/storage (lokal). Produksi disarankan memindahkan
 * folder ini ke luar public_html lalu menyetel STORAGE_DIR absolut
 * di .env (Fase 12 C6). Jalur RELATIF yang tersimpan di DB
 * ("storage/dokumen/...") TIDAK berubah — gate & query tetap pakai
 * prefix itu; hanya resolusi fisiknya yang lewat fungsi ini.
 * --------------------------------------------------------------- */
$__storage = (string) env_val('STORAGE_DIR', '');
if ($__storage === '') {
    $__storage = dirname(__DIR__) . '/storage';
} elseif ($__storage[0] !== '/' && !preg_match('#^[A-Za-z]:[\\\\/]#', $__storage)) {
    /* Nilai relatif di .env -> relatif terhadap root proyek. */
    $__storage = dirname(__DIR__) . '/' . $__storage;
}
define('STORAGE_DIR', rtrim(str_replace('\\', '/', $__storage), '/'));

/** Path absolut ke subfolder storage ('' = folder storage itu sendiri). */
function storage_path($sub = '')
{
    $sub = trim(str_replace('\\', '/', (string) $sub), '/');
    return $sub === '' ? STORAGE_DIR : STORAGE_DIR . '/' . $sub;
}

/* ---------------------------------------------------------------
 * Koneksi MySQL — user terbatas (spmb_app) via .env, fallback root
 * untuk instalasi lokal XAMPP default.
 * Impor skema: mysql -u root < database/db_spmb_mualimku.sql
 * --------------------------------------------------------------- */
define('DB_HOST', env_val('DB_HOST', '127.0.0.1'));
define('DB_PORT', (int) env_val('DB_PORT', '3306'));
define('DB_NAME', env_val('DB_NAME', 'db_spmb_mualimku'));
define('DB_USER', env_val('DB_USER', 'root'));
define('DB_PASS', env_val('DB_PASS', ''));

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/dummy.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/cms.php';
require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/fonnte.php';
require_once __DIR__ . '/notifikasi.php';
require_once __DIR__ . '/midtrans.php';
require_once __DIR__ . '/auth.php';

/* Fase 11 A5: pembersihan acak ±1% request (tanpa menggagalkan request). */
rate_limit_bersihkan();
