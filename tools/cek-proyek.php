<?php
/**
 * Fase 11 B2 — Pemeriksaan kesiapan proyek (8 pemeriksaan).
 *
 * Output: [PASS] / [WARN] / [GAGAL] per pemeriksaan + ringkasan.
 * Exit code: 0 = tanpa GAGAL, 1 = ada minimal satu GAGAL.
 *
 * CLI-ONLY. Pakai:
 *   php tools/cek-proyek.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** Ubah nilai php.ini seperti "8M" menjadi byte. */
function cek_size_to_bytes($val)
{
    $val = trim((string) $val);
    if ($val === '') {
        return 0;
    }
    $last = strtolower($val[strlen($val) - 1]);
    $num = (float) $val;
    switch ($last) {
        case 'g': return (int) ($num * 1073741824);
        case 'm': return (int) ($num * 1048576);
        case 'k': return (int) ($num * 1024);
        default:  return (int) $num;
    }
}

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';   /* koneksi db(), q_all, q_col */

$hasil = [];   /* [status, judul, detail] */

function catat($status, $judul, $detail = '')
{
    global $hasil;
    $hasil[] = [$status, $judul, $detail];
}

/* ---------------------------------------------------------------
 * 1. Versi PHP
 * ------------------------------------------------------------- */
if (version_compare(PHP_VERSION, '8.1.0', '>=')) {
    catat('PASS', 'Versi PHP', PHP_VERSION);
} else {
    catat('GAGAL', 'Versi PHP', PHP_VERSION . ' — aplikasi membutuhkan PHP >= 8.1');
}

/* ---------------------------------------------------------------
 * 2. Ekstensi PHP wajib
 * ------------------------------------------------------------- */
$wajib = [
    'pdo_mysql' => 'koneksi database (PDO)',
    'mbstring'  => 'teks multibahasa',
    'curl'      => 'WhatsApp Fonnte & gateway Midtrans',
    'fileinfo'  => 'deteksi MIME unggahan',
    'gd'        => 'kompresi gambar QRIS/logo',
    'zip'       => 'paket rilis & cadangan',
    'json'      => 'respons API',
    'session'   => 'login & CSRF',
];
$hilang = [];
foreach ($wajib as $ek => $guna) {
    if (!extension_loaded($ek)) {
        $hilang[] = $ek . ' (' . $guna . ')';
    }
}
if (!$hilang) {
    catat('PASS', 'Ekstensi PHP wajib', implode(', ', array_keys($wajib)));
} else {
    catat('GAGAL', 'Ekstensi PHP wajib', 'belum aktif: ' . implode('; ', $hilang));
}

/* ---------------------------------------------------------------
 * 3. Berkas konfigurasi (.env / APP_URL)
 * ------------------------------------------------------------- */
$envAda = is_file($root . '/.env');
$envContoh = is_file($root . '/.env.example');
$appUrl = '';
if ($envContoh) {
    /* Baca APP_URL langsung dari .env (tanpa memuat config). */
    if ($envAda) {
        foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $baris) {
            $baris = trim($baris);
            if ($baris === '' || $baris[0] === '#') {
                continue;
            }
            if (strpos($baris, 'APP_URL=') === 0) {
                $appUrl = trim(substr($baris, 8));
            }
        }
    }
}
if (!$envContoh) {
    catat('GAGAL', 'Berkas konfigurasi', '.env.example tidak ditemukan');
} elseif (!$envAda) {
    catat('WARN', 'Berkas konfigurasi', '.env belum ada — salin dari .env.example di server produksi (lihat docs/DEPLOY.md)');
} elseif ($appUrl === '') {
    catat('WARN', 'Berkas konfigurasi', 'APP_URL belum diisi — tautan WhatsApp memakai host otomatis');
} else {
    catat('PASS', 'Berkas konfigurasi', 'APP_URL=' . $appUrl);
}

/* ---------------------------------------------------------------
 * 4. Koneksi DB + tabel inti
 * ------------------------------------------------------------- */
$tabelInti = ['users', 'pendaftar', 'orang_tua', 'pembayaran', 'pengaturan_sekolah', 'rate_limit'];
$db = null;
try {
    $db = db();
} catch (Throwable $e) {
    $db = null;
}
if ($db === null) {
    catat('GAGAL', 'Koneksi database', 'tidak bisa terhubung — periksa DB_* di .env / kredensial MySQL');
} else {
    $adaTabel = q_all(
        'SELECT table_name AS t FROM information_schema.tables
          WHERE table_schema = DATABASE()'
    );
    $set = [];
    foreach ($adaTabel as $r) {
        $set[strtolower($r['t'])] = true;
    }
    $kurang = [];
    foreach ($tabelInti as $t) {
        if (!isset($set[$t])) {
            $kurang[] = $t;
        }
    }
    if ($kurang) {
        catat('GAGAL', 'Koneksi database', 'tabel belum ada: ' . implode(', ', $kurang) . ' — impor database/db_spmb_mualimku.sql');
    } else {
        catat('PASS', 'Koneksi database', count($set) . ' tabel, tabel inti lengkap');
    }
}

/* ---------------------------------------------------------------
 * 5. Skema migrasi terbaru (sudah dijalankan?)
 * ------------------------------------------------------------- */
if ($db !== null) {
    $kolom = [];
    try {
        $q = q_all(
            "SELECT CONCAT(table_name, '.', column_name) AS k
               FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND ((table_name = 'pendaftar' AND column_name = 'pay_token_hash')
                  OR (table_name = 'users' AND column_name = 'wajib_ganti_password')
                  OR (table_name = 'rate_limit' AND column_name = 'aksi')
                  OR (table_name = 'pengaturan_sekolah' AND column_name = 'teks_beasiswa'))"
        );
        foreach ($q as $r) {
            $kolom[$r['k']] = true;
        }
    } catch (Throwable $e) {
        $kolom = [];
    }
    $kurang = [];
    foreach (['pendaftar.pay_token_hash', 'users.wajib_ganti_password', 'rate_limit.aksi',
        'pengaturan_sekolah.teks_beasiswa'] as $k) {
        if (!isset($kolom[$k])) {
            $kurang[] = $k;
        }
    }
    if ($kurang) {
        catat('GAGAL', 'Skema migrasi terbaru', 'kolom belum ada: ' . implode(', ', $kurang) . ' — jalankan database/migrations/ berurutan');
    } else {
        catat('PASS', 'Skema migrasi terbaru', 'pay_token_hash, wajib_ganti_password, rate_limit.aksi, teks_beasiswa ada');
    }
}

/* ---------------------------------------------------------------
 * 6. Izin tulis folder data
 * ------------------------------------------------------------- */
$folderData = ['dokumen', 'backup'];
$masalah = [];
foreach ($folderData as $f) {
    $p = storage_path($f);
    $label = 'storage/' . $f;
    if (!is_dir($p)) {
        $masalah[] = $label . ' belum ada (akan dibuat oleh tool cadangan)';
    } elseif (!is_writable($p)) {
        $masalah[] = $label . ' TIDAK bisa ditulis web server';
    }
}
$gagalIzin = (bool) array_filter($masalah, function ($m) {
    return strpos($m, 'TIDAK bisa ditulis') !== false;
});
if ($gagalIzin) {
    catat('GAGAL', 'Izin folder data', implode('; ', $masalah));
} elseif ($masalah) {
    catat('WARN', 'Izin folder data', implode('; ', $masalah));
} else {
    catat('PASS', 'Izin folder data', 'storage/dokumen & storage/backup (' . STORAGE_DIR . ') bisa ditulis');
}

/* ---------------------------------------------------------------
 * 7. php.ini produksi
 * ------------------------------------------------------------- */
$de = ini_get('display_errors');
$deOn = ($de !== '' && $de !== '0' && strtolower((string) $de) !== 'off');
$ufs = ini_get('upload_max_filesize');
$ufsByte = cek_size_to_bytes($ufs);
$masalah = [];
if ($deOn) {
    $masalah[] = 'display_errors=On (matikan di produksi)';
}
if ($ufsByte !== false && $ufsByte < 2 * 1024 * 1024) {
    $masalah[] = 'upload_max_filesize=' . $ufs . ' (minimal 2M)';
}
if ($masalah) {
    catat('WARN', 'php.ini', implode('; ', $masalah));
} else {
    catat('PASS', 'php.ini', 'display_errors=Off, upload_max_filesize=' . $ufs);
}

/* ---------------------------------------------------------------
 * 8. Akun & password tersimpan
 * ------------------------------------------------------------- */
if ($db !== null) {
    $totalUser = (int) (q_col('SELECT COUNT(*) FROM users') ?? 0);
    $kunci = (int) (q_col("SELECT COUNT(*) FROM users WHERE password_hash = '!LOCKED'") ?? 0);
    $lemah = (int) (q_col(
        'SELECT COUNT(*) FROM users
          WHERE password_hash IS NOT NULL
            AND password_hash <> "!LOCKED"
            AND password_hash NOT LIKE "$2y$%"
            AND password_hash NOT LIKE "$argon2%"'
    ) ?? 0);
    if ($lemah > 0) {
        catat('GAGAL', 'Akun & password', $lemah . ' akun memakai hash non-bcrypt — reset lewat php tools/set-password.php');
    } elseif ($kunci > 0) {
        catat('WARN', 'Akun & password', $totalUser . ' akun; ' . $kunci . ' masih terkunci (!LOCKED) — jalankan tools/set-password.php sebelum login');
    } else {
        catat('PASS', 'Akun & password', $totalUser . ' akun, semua hash bcrypt');
    }
}

/* ---------------------------------------------------------------
 * Laporan
 * ------------------------------------------------------------- */
$gagal = 0;
$warn  = 0;
fwrite(STDOUT, "PEMERIKSAAN PROYEK SPMB/CMS — " . date('Y-m-d H:i:s') . " (Asia/Jakarta)\n");
fwrite(STDOUT, str_repeat('-', 72) . "\n");
foreach ($hasil as $i => $h) {
    printf("%d. [%s] %s%s\n", $i + 1, $h[0], $h[1], $h[2] !== '' ? ': ' . $h[2] : '');
    if ($h[0] === 'GAGAL') {
        $gagal++;
    } elseif ($h[0] === 'WARN') {
        $warn++;
    }
}
fwrite(STDOUT, str_repeat('-', 72) . "\n");
fwrite(STDOUT, 'Ringkasan: ' . count($hasil) . ' pemeriksaan — '
    . (count($hasil) - $gagal - $warn) . ' PASS, ' . $warn . ' WARN, ' . $gagal . " GAGAL\n");
if ($gagal > 0) {
    fwrite(STDOUT, "Status: BELUM SIAP — perbaiki item GAGAL di atas.\n");
    exit(1);
}
fwrite(STDOUT, $warn > 0 ? "Status: SIAP dengan catatan WARN.\n" : "Status: SIAP.\n");
exit(0);
