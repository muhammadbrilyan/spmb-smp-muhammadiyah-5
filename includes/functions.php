<?php
/** Helper global untuk seluruh view. */

function e($str)
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

/** Bangun URL absolut-berbasis root aplikasi. */
function url($path = '')
{
    return BASE_URL . '/' . ltrim((string) $path, '/');
}

/**
 * Bangun URL ABSOLUT (https://domain/...) untuk tautan di luar web
 * (WhatsApp, email). Fase 10 — D1:
 *   1. env APP_URL bila diatur (disarankan untuk production).
 *   2. Auto-detect scheme + host dari request berjalan.
 *   3. Fallback: url() relatif (mis. di CLI tanpa host).
 */
function url_abs($path = '')
{
    $base = rtrim((string) env_val('APP_URL', ''), '/');
    if ($base === '') {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return url($path);
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https' : 'http') . '://' . $host . BASE_URL;
    }
    return $base . '/' . ltrim((string) $path, '/');
}

/** Bangun path asset (css/js/img). */
function asset($path)
{
    return url($path);
}

/** Format rupiah: 200000 -> Rp 200.000 */
function rupiah($angka, $prefix = 'Rp ')
{
    return $prefix . number_format((float) $angka, 0, ',', '.');
}

/** Nama bulan panjang Bahasa Indonesia. */
function bulan($tanggal)
{
    $bulan = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $ts = strtotime($tanggal);
    return date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

/** Nama bulan pendek Bahasa Indonesia: 2025-10-01 -> "1 Okt". */
function bulan_pendek($tanggal)
{
    $pendek = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $ts = strtotime($tanggal);
    return date('j', $ts) . ' ' . $pendek[(int) date('n', $ts)];
}

/** Inisial untuk avatar, mis. "Aisyah Nur Salsabila" -> "AS" */
function inisial($nama)
{
    $bagi = preg_split('/\s+/', trim((string) $nama));
    $a = $bagi[0][0] ?? '';
    $b = count($bagi) > 1 ? $bagi[1][0] : '';
    return strtoupper($a . $b);
}

/**
 * Badge status berbasis token warna dari DESIGN.md.
 * @param string $label teks badge
 * @param string $tone  green|amber|red|blue|gray
 */
function badge($label, $tone = 'gray')
{
    $map = [
        'green' => 'bg-[#ECFDF5] text-[#065F46] border-[#A7F3D0]',
        'amber' => 'bg-[#FFFBEB] text-[#92400E] border-[#FDE68A]',
        'red'   => 'bg-[#FEF2F2] text-[#991B1B] border-[#FECACA]',
        'blue'  => 'bg-[#EFF6FF] text-[#1E40AF] border-[#BFDBFE]',
        'gray'  => 'bg-canvas text-ink-500 border-line',
    ];
    $cls = $map[$tone] ?? $map['gray'];
    return '<span class="badge ' . $cls . '">' . e($label) . '</span>';
}

/** Titik status berwarna di dalam badge. */
function dot($tone = 'green')
{
    $map = ['green' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500', 'blue' => 'bg-blue-500', 'gray' => 'bg-slate-400'];
    return '<span class="w-1.5 h-1.5 rounded-full ' . ($map[$tone] ?? $map['gray']) . ' shrink-0"></span>';
}

/** Ikon Material Symbols. */
function icon($nama, $kelas = 'text-[18px]')
{
    return '<span class="ms ' . $kelas . '" aria-hidden="true">' . e($nama) . '</span>';
}

/** true bila string berupa hex 6 digit (#RRGGBB). */
function warna_valid($hex)
{
    return is_string($hex) && preg_match('/^#[0-9a-f]{6}$/i', $hex) === 1;
}

/**
 * Campur warna hex dengan warna target.
 * @param string $hex    warna dasar (#RRGGBB)
 * @param string $target '#000000' (gelapkan) atau '#ffffff' (terangkan)
 * @param float  $rasio  0..1 porsi warna target
 */
function warna_campur($hex, $target, $rasio)
{
    if (!warna_valid($hex) || !warna_valid($target)) {
        return $hex;
    }
    $rasio = max(0.0, min(1.0, (float) $rasio));
    $a = [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    $b = [hexdec(substr($target, 1, 2)), hexdec(substr($target, 3, 2)), hexdec(substr($target, 5, 2))];
    $c = [];
    for ($i = 0; $i < 3; $i++) {
        $c[$i] = (int) round($a[$i] * (1 - $rasio) + $b[$i] * $rasio);
    }
    return sprintf('#%02X%02X%02X', $c[0], $c[1], $c[2]);
}

/**
 * Hitung luminance relatif sebuah warna hex (WCAG 2.1).
 * @return float 0.0 (hitam) .. 1.0 (putih)
 */
function warna_luminance($hex)
{
    if (!warna_valid($hex)) {
        return 0.5;
    }
    $r = hexdec(substr($hex, 1, 2)) / 255;
    $g = hexdec(substr($hex, 3, 2)) / 255;
    $b = hexdec(substr($hex, 5, 2)) / 255;
    $konversi = function ($c) {
        return $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * $konversi($r) + 0.7152 * $konversi($g) + 0.0722 * $konversi($b);
}

/**
 * Pilih warna teks yang kontras di atas latar warna tertentu.
 * Mengembalikan '#FFFFFF' atau '#1F2937' (abu gelap) — mana yang kontrasnya lebih tinggi.
 */
function warna_kontras($hex)
{
    $lum = warna_luminance($hex);
    /* luminance > 0.4 → latar terang → teks gelap. Sebaliknya → teks putih. */
    return $lum > 0.4 ? '#1F2937' : '#FFFFFF';
}

/**
 * IDEMPOTENCY KEY — identitas unik per "niat" pembayaran/registrasi.
 *
 * Client mengirim key yang sama saat retry (double-click / jaringan lambat).
 * Server menyimpan key di kolom UNIQUE; request kedua dengan key yang sama
 * tidak membuat efek baru — hanya mengembalikan hasil yang sudah ada.
 *
 * Format diterima: hex 32 karakter (UUID tanpa strip juga boleh).
 */
function idem_key_valid($key)
{
    $key = strtolower(trim((string) $key));
    $key = str_replace('-', '', $key);
    if (strlen($key) !== 32 || !ctype_xdigit($key)) {
        return null;
    }
    return $key;
}

/** Buat idempotency key baru (32 hex char). */
function idem_key_baru()
{
    return bin2hex(random_bytes(16));
}

/**
 * Cek apakah aksi tertentu masih boleh dilakukan oleh IP saat ini.
 *
 * Gagal-aman: bila koneksi DB error atau tabel rate_limit tidak ada,
 * fungsi mengembalikan false (tolak) untuk aksi sensitif — jangan lolos diam-diam.
 *
 * @param string   $aksi   Kode aksi, mis. 'registrasi', 'cek_status', 'bayar_token'
 * @param int      $maks   Jumlah maksimum percobaan
 * @param int      $menit  Jendela waktu dalam menit
 * @param ?string  $kunci  Kunci rate opsional selain IP (mis. no_pendaftaran).
 *                         Bila diisi, identitas rate menjadi kunci tsb sehingga
 *                         batas berlaku LINTAS IP (anti brute-force per objek).
 * @return bool  true = masih boleh, false = sudah melewati batas
 */
function rate_limit_cek($aksi, $maks = 5, $menit = 60, $kunci = null)
{
    try {
        $db = db();
        if ($db === null) {
            return false;
        }
        $ip = rate_limit_identitas($kunci);
        $stmt = $db->prepare(
            'SELECT COUNT(*) FROM rate_limit
             WHERE ip_address = ? AND aksi = ? AND created_at > (NOW() - INTERVAL ? MINUTE)'
        );
        $stmt->execute([$ip, $aksi, (int) $menit]);
        $jumlah = (int) $stmt->fetchColumn();
        return $jumlah < (int) $maks;
    } catch (Throwable $e) {
        error_log('rate_limit_cek gagal: ' . $e->getMessage());
        return false;
    }
}

/**
 * Identitas rate untuk satu percobaan.
 * Default: IP klien. Bila $kunci diisi (mis. no_pendaftaran), kunci tsb
 * dipakai — menggantikan IP — agar batas berlaku global/lintas IP.
 * Prefiks 'k:' mencegah bentrok dengan alamat IP asli.
 * (Kolom rate_limit.ip_address VARCHAR(45) dipakai menyimpan identitas.)
 */
function rate_limit_identitas($kunci = null)
{
    if (is_string($kunci)) {
        $kunci = trim($kunci);
        if ($kunci !== '') {
            return 'k:' . substr($kunci, 0, 42);
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Catat satu percobaan aksi yang dibatasi rate_limit.
 * Dipanggil setiap kali user mencoba aksi (sukses atau gagal).
 *
 * Gagal-aman: bila DB error, catat ke error_log agar tidak hilang diam-diam.
 */
function rate_limit_catat($aksi, $kunci = null)
{
    try {
        $db = db();
        if ($db === null) {
            error_log('rate_limit_catat: DB null untuk aksi=' . $aksi);
            return;
        }
        $ip = rate_limit_identitas($kunci);
        $stmt = $db->prepare(
            'INSERT INTO rate_limit (ip_address, aksi, created_at) VALUES (?, ?, NOW())'
        );
        $stmt->execute([$ip, $aksi]);
    } catch (Throwable $e) {
        error_log('rate_limit_catat gagal: ' . $e->getMessage());
    }
}
