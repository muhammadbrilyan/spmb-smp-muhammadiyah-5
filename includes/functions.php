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

/**
 * Bangun path asset (css/js/img).
 * Versi dari filemtime ikut ditempel supaya browser mengambil berkas baru
 * begitu CSS/JS berubah — tanpa itu pengguna lama melihat tampilan/perilaku
 * lama (dan harus hard reload) setiap kali aset diperbarui.
 */
function asset($path)
{
    $path = ltrim((string) $path, '/');
    $file = dirname(__DIR__) . '/' . $path;
    $ver  = is_file($file) ? (int) @filemtime($file) : 0;
    return url($path) . ($ver ? '?v=' . $ver : '');
}

/** Format rupiah: 200000 -> Rp 200.000 */
function rupiah($angka, $prefix = 'Rp ')
{
    return $prefix . number_format((float) $angka, 0, ',', '.');
}

/**
 * Nama biaya besar yang dulu disebut "uang pangkal gedung" / "uang masuk"
 * (kolom DB & konstanta internal UANG_MASUK tidak ikut berubah). Operator
 * bisa mengganti namanya di Pengaturan Sistem → Struktur Tarif.
 */
function label_biaya_pendaftaran()
{
    $l = trim((string) ($GLOBALS['PENGATURAN']['label_uang_pangkal'] ?? ''));
    return $l !== '' ? $l : 'Biaya Pendaftaran';
}

/**
 * Status pendaftaran publik dari saklar CMS (Persiapan Tahun Ajaran).
 * Tanggal buka/tutup hanya informasi jadwal — bukan penentu status.
 * Default terbuka agar instalasi lama tanpa kolom tetap bisa mendaftar.
 */
function pendaftaran_terbuka()
{
    return (int) ($GLOBALS['PENGATURAN']['pendaftaran_dibuka'] ?? 1) === 1;
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

/**
 * Logo merek media sosial (SVG sebaris, mengikuti warna teks induk).
 * Material Symbols tidak punya lambang Instagram/TikTok/YouTube, jadi
 * footer publik memakai bentuk asli masing-masing tautan.
 */
function logo_merek($nama, $ukuran = 18)
{
    $path = [
        'instagram' => 'M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793 0 1.44.645 1.44 1.439z',
        'tiktok'    => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
        'youtube'   => 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z',
    ][strtolower((string) $nama)] ?? null;
    if ($path === null) {
        return '';
    }
    $u = max(8, (int) $ukuran);
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $u . '" height="' . $u
        . '" fill="currentColor" aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>';
}

/** true bila string berupa hex 6 digit (#RRGGBB). */
function warna_valid($hex)
{
    return is_string($hex) && preg_match('/^#[0-9a-f]{6}$/i', $hex) === 1;
}

/**
 * Normalisasi tautan yang diketik operator (media sosial / website).
 *
 * Operator cukup menempelkan alamat, misalnya "instagram.com/sekolah" atau
 * "https://tiktok.com/@sekolah". Tanpa skema otomatis ditambah https:// agar
 * tautan di footer langsung bisa diklik.
 *
 * @param  string $raw inputsan mentah
 * @return string|null URL bersih, atau null bila kosong
 * @throws InvalidArgumentException bila bukan URL http(s) yang sah
 */
function tautan_normal($raw)
{
    $tautan = preg_replace('/\s+/', '', trim((string) $raw));
    if ($tautan === '') {
        return null;
    }
    if (!preg_match('#^https?://#i', $tautan)) {
        $tautan = 'https://' . ltrim($tautan, '/');
    }
    if (strlen($tautan) > 255) {
        throw new InvalidArgumentException('Tautan maksimal 255 karakter.');
    }
    if (filter_var($tautan, FILTER_VALIDATE_URL) === false || !preg_match('~^https?://[^/?#]+\.[a-z]{2,}~i', $tautan)) {
        throw new InvalidArgumentException('Tautan harus berbentuk alamat situs, contoh: instagram.com/namasekolah');
    }
    return $tautan;
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
    return klien_ip();
}

/**
 * IP klien SEJATI di belakang proxy tepercaya (Fase 12 C3).
 *
 * .env:
 *   TRUSTED_PROXIES      = daftar CIDR/IP dipisah kanga (kosong = TIDAK ADA
 *                          proxy tepercaya -> semua header DIABAIKAN).
 *   TRUSTED_PROXY_HEADER = 'X-Forwarded-For' atau 'CF-Connecting-IP'
 *                          (kosong = 'X-Forwarded-For').
 *
 * Daftar CIDR Cloudflare: salin dari HALAMAN RESMI Cloudflare ke .env —
 * sengaja TIDAK di-hardcode di sini agar tidak basi.
 *
 * Algoritma: bila REMOTE_ADDR bukan anggota TRUSTED_PROXIES -> kembalikan
 * REMOTE_ADDR dan abaikan semua header. Bila anggota: X-Forwarded-For
 * ditelusuri kanan->kiri (lewati alamat yang termasuk proxy tepercaya,
 * ambil yang pertama bukan proxy); CF-Connecting-IP dipakai nilainya.
 * Hasil divalidasi FILTER_VALIDATE_IP; tidak valid -> REMOTE_ADDR.
 * Parameter opsional dipakai tools/uji-klien-ip.php.
 */
function klien_ip(?array $server = null, $proxiesCsv = null, $headerName = null)
{
    $server = $server ?? $_SERVER;
    $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));
    if ($remote === '' || filter_var($remote, FILTER_VALIDATE_IP) === false) {
        return '0.0.0.0';
    }

    $csv = $proxiesCsv !== null ? (string) $proxiesCsv : (string) env_val('TRUSTED_PROXIES');
    $daftar = array_values(array_filter(array_map('trim', explode(',', $csv))));
    if ($daftar === [] || !klien_ip_dalam_daftar($remote, $daftar)) {
        return $remote; /* bukan proxy tepercaya -> abaikan semua header */
    }

    $header = strtoupper($headerName !== null ? (string) $headerName
        : (string) (env_val('TRUSTED_PROXY_HEADER') ?: 'X-Forwarded-For'));

    if ($header === 'CF-CONNECTING-IP') {
        $v = trim((string) ($server['HTTP_CF_CONNECTING_IP'] ?? ''));
        return filter_var($v, FILTER_VALIDATE_IP) !== false ? $v : $remote;
    }

    /* X-Forwarded-For: telusuri kanan -> kiri. */
    $xff = array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '')));
    for ($i = count($xff) - 1; $i >= 0; $i--) {
        $k = $xff[$i];
        if (filter_var($k, FILTER_VALIDATE_IP) === false) {
            continue; /* nilai sampah -> lewati */
        }
        if (!klien_ip_dalam_daftar($k, $daftar)) {
            return $k;
        }
    }
    return $remote;
}

/** Apakah $ip cocok dengan salah satu entri CIDR/IP (IPv4 & IPv6)? */
function klien_ip_dalam_daftar($ip, array $daftar)
{
    $ipBin = @inet_pton($ip);
    if ($ipBin === false) {
        return false;
    }
    foreach ($daftar as $c) {
        if (strpos($c, '/') === false) {
            if ($ip === $c) {
                return true;
            }
            continue;
        }
        $bagian = explode('/', $c, 2);
        if (count($bagian) !== 2) {
            continue;
        }
        list($subnet, $bits) = $bagian;
        $bits = (int) $bits;
        $subnetBin = @inet_pton($subnet);
        if ($subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            continue; /* v4 vs v6 / CIDR rusak */
        }
        $maks = strlen($ipBin) * 8;
        if ($bits < 0 || $bits > $maks) {
            continue;
        }
        if ($bits === 0) {
            return true;
        }
        $bytePenuh = intdiv($bits, 8);
        $sisa = $bits % 8;
        $ok = true;
        for ($j = 0; $j < $bytePenuh; $j++) {
            if ($ipBin[$j] !== $subnetBin[$j]) {
                $ok = false;
                break;
            }
        }
        if ($ok && $sisa > 0) {
            $mask = chr((0xFF << (8 - $sisa)) & 0xFF);
            if (($ipBin[$bytePenuh] & $mask) !== ($subnetBin[$bytePenuh] & $mask)) {
                $ok = false;
            }
        }
        if ($ok) {
            return true;
        }
    }
    return false;
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

/**
 * Fase 11 A5: bersihkan baris rate_limit lebih tua dari 2 hari.
 * Dipanggil secara acak ±1% request (sama pola dengan pembersihan
 * login_attempts) — LIMIT 1000 agar hemat, tidak pernah menggagalkan request.
 */
function rate_limit_bersihkan()
{
    try {
        if (random_int(1, 100) !== 1) {
            return; /* hanya ±1% request */
        }
        $db = db();
        if ($db === null) {
            return;
        }
        $db->exec('DELETE FROM rate_limit WHERE created_at < (NOW() - INTERVAL 2 DAY) LIMIT 1000');
    } catch (Throwable $e) {
        /* abaikan — pembersihan tidak boleh menggagalkan request */
    }
}

/**
 * Urutan resmi tahap pendaftar (model status — Fase 0..8).
 * Index lebih besar = lebih maju dalam alur SPMB.
 */
function tahap_urutan()
{
    return [
        'MENUNGGU_PEMBAYARAN', 'MENUNGGU_VERIFIKASI_BAYAR', 'BAYAR_TERVERIFIKASI',
        'AKUN_TERKIRIM', 'MELENGKAPI_BERKAS', 'BERKAS_DISUBMIT', 'BERKAS_REVISI',
        'BERKAS_TERVERIFIKASI', 'JADWAL_DITETAPKAN', 'NILAI_TERINPUT', 'DIUMUMKAN',
    ];
}

/** Index tahap dalam urutan resmi; -1 bila tidak dikenal. */
function tahap_index($tahap)
{
    $i = array_search((string) $tahap, tahap_urutan(), true);
    return $i === false ? -1 : (int) $i;
}

/** true bila $tahap sudah minimal $minimal (mengikuti urutan resmi). */
function tahap_minimal($tahap, $minimal)
{
    $a = tahap_index($tahap);
    $b = tahap_index($minimal);
    if ($a < 0 || $b < 0) {
        return false;
    }
    return $a >= $b;
}

/**
 * Label ramah pengguna untuk tahap pendaftar (dipakai halaman operator).
 * @return array{label:string,tone:string}
 */
function label_tahap($tahap)
{
    $peta = [
        'MENUNGGU_PEMBAYARAN'       => ['Menunggu Pembayaran', 'amber'],
        'MENUNGGU_VERIFIKASI_BAYAR' => ['Verifikasi Pembayaran', 'amber'],
        'BAYAR_TERVERIFIKASI'       => ['Pembayaran Terverifikasi', 'blue'],
        'AKUN_TERKIRIM'             => ['Akun Terkirim', 'blue'],
        'MELENGKAPI_BERKAS'         => ['Melengkapi Berkas', 'amber'],
        'BERKAS_DISUBMIT'           => ['Berkas Dikirim', 'blue'],
        'BERKAS_REVISI'             => ['Revisi Berkas', 'red'],
        'BERKAS_TERVERIFIKASI'      => ['Berkas Terverifikasi', 'green'],
        'JADWAL_DITETAPKAN'         => ['Jadwal Ditentukan', 'blue'],
        'NILAI_TERINPUT'            => ['Nilai Terinput', 'blue'],
        'DIUMUMKAN'                 => ['Hasil Diumumkan', 'green'],
    ];
    $d = $peta[$tahap] ?? [$tahap !== '' ? $tahap : 'Belum Ada Tahap', 'gray'];
    return ['label' => $d[0], 'tone' => $d[1]];
}

/**
 * Render teks Syarat & Ketentuan polos menjadi HTML terstruktur yang nyaman
 * dibaca (judul terpusat + daftar pasal bernomor kartu). Aman: semua teks
 * di-escape dulu. Dipakai modal S&K di halaman registrasi publik.
 */
function syarat_ke_html($teks)
{
    $teks = trim((string) $teks);
    if ($teks === '') {
        return '';
    }
    $baris  = preg_split('/\R/u', $teks) ?: [];
    $judul  = '';
    $sub    = [];
    $pasal  = [];
    $kini   = null;

    foreach ($baris as $b) {
        $b = trim($b);
        if ($b === '') {
            continue;
        }
        /* Baris "1." / "1)" = awal pasal baru. */
        if (preg_match('/^(\d+)\s*[.)]\s*(.+)$/u', $b, $m)) {
            $kini = (int) $m[1];
            $pasal[$kini] = [$m[2]];
            continue;
        }
        if ($kini === null) {
            if ($judul === '') {
                $judul = $b;
            } else {
                $sub[] = $b;
            }
        } else {
            $pasal[$kini][] = $b;   /* lanjutan isi pasal */
        }
    }

    $h  = '<div class="sk-doc">';
    if ($judul !== '') {
        $h .= '<p class="sk-judul">' . e($judul) . '</p>';
    }
    foreach ($sub as $s) {
        $h .= '<p class="sk-sub">' . e($s) . '</p>';
    }
    if ($pasal) {
        ksort($pasal);
        $h .= '<ol class="sk-ol">';
        foreach ($pasal as $no => $isi) {
            $h .= '<li><span class="sk-badge">' . e((string) $no) . '</span>'
                . '<div class="sk-teks">' . e(implode(' ', $isi)) . '</div></li>';
        }
        $h .= '</ol>';
    } else {
        /* Tanpa nomor pasal — tampilkan sebagai paragraf biasa. */
        $h .= '<p class="sk-teks">' . e(implode(' ', $sub)) . '</p>';
    }
    $h .= '</div>';
    return $h;
}
