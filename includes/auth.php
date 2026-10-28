<?php
/**
 * HAK AKSES / RBAC — terhubung MySQL `db_spmb_mualimku.tabel users`.
 *
 * Urutan sumber kredensial:
 *   1. tabel `users` (bcrypt / password_verify) — sumber utama
 *   2. fallback includes/dummy.php (akun demo TANPA password / fail-closed)
 *      dipakai otomatis bila database tidak dapat dihubungi.
 *
 * Saat kolom role dipakai: ENUM('siswa','bendahara','operator','kepsek')
 * sesuai PRD. Halaman dijaga require_role() di tiap berkas app/.
 */

/** Cari akun berdasarkan username. null = tidak ditemukan. */
function akun_by_username($username)
{
    global $AKUN_DEMO, $META_ROLE;

    $username = trim((string) $username);
    if ($username === '') {
        return null;
    }

    /* 1. Sumber utama: tabel users */
    $db = db();
    if ($db !== null) {
        try {
            $stmt = $db->prepare(
                'SELECT id, username, password_hash, role, nama_lengkap, jabatan
                   FROM users
                  WHERE username = ? AND is_active = 1'
            );
            $stmt->execute([$username]);
            $row = $stmt->fetch();
            if ($row) {
                $meta  = $META_ROLE[$row['role']] ?? [];
                $nama  = $row['nama_lengkap'] !== '' && $row['nama_lengkap'] !== null
                    ? $row['nama_lengkap']
                    : ($meta['nama'] ?? $row['username']);
                $jab   = $row['jabatan'] !== '' && $row['jabatan'] !== null
                    ? $row['jabatan']
                    : ($meta['jabatan'] ?? '');
                return [
                    'id'            => $row['id'],
                    'username'      => $row['username'],
                    'password_hash' => $row['password_hash'],
                    'role'          => $row['role'],
                    'nama'          => $nama,
                    'jabatan'       => $jab,
                    'sumber'        => DB_NAME . '.users',
                ];
            }
            /* Username tidak ada di DB: tidak jatuh ke dummy agar akun
               yang sengaja dihapus/dinonaktifkan tetap ditolak. */
            return null;
        } catch (Throwable $e) {
            /* koneksi/query gagal -> lanjut ke dummy di bawah */
        }
    }

    /* 2. Fallback: akun demo dummy (tanpa password — lihat includes/dummy.php) */
    foreach ($AKUN_DEMO as $a) {
        if ($a['username'] === $username) {
            return $a;
        }
    }
    return null;
}

/** Bandingkan password. Mendukung bcrypt (DB) maupun teks polos (dummy). */
function password_cocok($plain, $akun)
{
    $plain = (string) $plain;

    if (is_string($akun['password_hash'] ?? null) && $akun['password_hash'] !== '') {
        return password_verify($plain, $akun['password_hash']);
    }
    return is_string($akun['password'] ?? null) && hash_equals($akun['password'], $plain);
}

/** Proses login. true = sukses, false = gagal. */
function attempt_login($username, $password)
{
    /* --- Rate limiting: max 5 percobaan gagal per 15 menit per IP ---
     * Disimpan di tabel login_attempts (bukan session) agar tidak bisa
     * dilewati penyerang dengan membuang cookie. */
    $ip = klien_ip();
    $db = db();

    $sisaDetik = 0;
    if ($db !== null) {
        try {
            /* Bersihkan baris luar jendela 15 menit sesekali. */
            $db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 15 MINUTE)');

            $stmt = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)');
            $stmt->execute([$ip]);
            $percobaan = (int) $stmt->fetchColumn();

            if ($percobaan >= 5) {
                $stmt2 = $db->prepare('SELECT TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) FROM login_attempts WHERE ip_address = ?');
                $stmt2->execute([$ip]);
                $lewat = (int) ($stmt2->fetchColumn() ?: 0);
                $sisaDetik = max(1, 900 - $lewat);
                return ['ok' => false, 'pesan' => 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($sisaDetik / 60) . ' menit.'];
            }
        } catch (Throwable $e) {
            /* Bila tabel belum ada, lanjut tanpa rate limit agar login tetap jalan. */
        }
    }

    $akun = akun_by_username(trim((string) $username));
    $cocok = $akun && password_cocok($password, $akun);

    if (!$cocok) {
        if ($db !== null) {
            try {
                $ins = $db->prepare('INSERT INTO login_attempts (ip_address, username, attempted_at) VALUES (?, ?, NOW())');
                $ins->execute([$ip, substr(trim((string) $username), 0, 100)]);
            } catch (Throwable $e) {
                /* abaikan bila gagal mencatat */
            }
        }
        return ['ok' => false, 'pesan' => 'Username atau password salah. Silakan periksa kembali kredensial Anda.'];
    }

    /* Sukses: hapus catatan gagal IP ini. */
    if ($db !== null) {
        try {
            $del = $db->prepare('DELETE FROM login_attempts WHERE ip_address = ?');
            $del->execute([$ip]);
        } catch (Throwable $e) {
            /* abaikan */
        }
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => $akun['id'] ?? null,
        'username' => $akun['username'],
        'role'     => $akun['role'],
        'nama'     => $akun['nama'],
        'jabatan'  => $akun['jabatan'] ?? '',
        'inisial'  => inisial($akun['nama']),
    ];
    return ['ok' => true, 'pesan' => 'Login berhasil.'];
}

/** Data user pada sesi aktif, atau null. */
function current_user()
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in()
{
    return isset($_SESSION['user']);
}

function user_role()
{
    return $_SESSION['user']['role'] ?? null;
}

function logout()
{
    /* Hapus semua data session */
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** Halaman default untuk tiap role. */
function role_home($role)
{
    switch ($role) {
        case 'operator':
        case 'admin':
            return url('app/operator/index.php');
        case 'bendahara':
            return url('app/bendahara/index.php');
        case 'kepsek':
            return url('app/kepsek/index.php');
        case 'siswa':
            return url('app/siswa/index.php');
        default:
            return url('app/portal/index.php');
    }
}

/** Validasi tujuan `next` (cegah open redirect). */
function safe_next($next)
{
    if (!is_string($next) || $next === '') {
        return '';
    }
    $prefix = BASE_URL . '/';
    if (strpos($next, $prefix) !== 0) {
        return '';
    }
    if (strpos($next, '//') !== false || strpos($next, '\\') !== false || strpos($next, '..') !== false) {
        return '';
    }
    return $next;
}

/** Wajib login — selain itu redirect ke halaman login yang sesuai. */
function require_login()
{
    if (is_logged_in()) {
        return;
    }
    $next = $_SERVER['REQUEST_URI'] ?? '';

    /* Tebak role dari path URL untuk arahkan ke login yang tepat. */
    $uri = strtolower($next);
    $tujuanLogin = 'app/auth/login.php'; /* default: portal siswa */
    if (strpos($uri, '/operator/') !== false
        || strpos($uri, '/bendahara/') !== false
        || strpos($uri, '/kepsek/') !== false
        || strpos($uri, '/auth/login-sekolah') !== false) {
        /* Alamat pendek pihak sekolah (folder admin/ di root proyek). */
        $tujuanLogin = 'admin/';
    }

    header('Location: ' . url($tujuanLogin) . ($next !== '' ? '?next=' . urlencode($next) : ''));
    exit;
}

/** Wajib login + role tertentu. Role lain dialihkan ke portal netral. */
function require_role($roles)
{
    require_login();
    $roles = (array) $roles;
    if (in_array(user_role(), $roles, true)) {
        wajib_ganti_password_cek();
        return;
    }
    flash('error', 'Maaf, akun Anda tidak memiliki hak akses ke halaman tersebut.');
    header('Location: ' . url('app/portal/index.php'));
    exit;
}

/**
 * [Fase 10] Cek paksa ganti password.
 * Akun yang di-reset lewat tools/set-password.php ditandai
 * users.wajib_ganti_password = 1 — hanya halaman Ganti Password
 * yang boleh dibuka sampai pengguna sukses mengganti password.
 * Khusus role staf (portal siswa tidak memiliki alur ini).
 */
function wajib_ganti_password_cek()
{
    if (user_role() === null || user_role() === 'siswa') {
        return;
    }
    $me = current_user();
    $meId = $me['id'] ?? '';
    if ($meId === '') {
        return;
    }

    /* Kecualikan halaman ganti-password itu sendiri agar tidak loop. */
    $script = strtolower(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
    if (strpos($script, '/app/staf/ganti-password.php') !== false) {
        return;
    }

    $flag = q_col('SELECT wajib_ganti_password FROM users WHERE id = ?', [$meId]);
    if ((int) $flag !== 1) {
        return;
    }

    flash('error', 'Anda wajib mengganti password terlebih dahulu.');
    header('Location: ' . url('app/staf/ganti-password.php'));
    exit;
}

/**
 * Flash message satu kali.
 * flash('error', 'pesan')  -> simpan
 * flash()                  -> ambil & hapus
 */
function flash($tipe = null, $pesan = null)
{
    if ($pesan !== null) {
        $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Token CSRF (sekali per sesi). */
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

/** Hidden input untuk ditaruh di dalam <form method="post">. */
function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/** Cek token CSRF dari POST. */
function csrf_cek($token)
{
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
