<?php
/**
 * LAPISAN QUERY — Akun petugas (operator, bendahara, kepsek).
 *
 * Hanya menyentuh baris users dengan role <> 'siswa'; akun siswa dibuat lewat
 * alur generate akun pendaftar di includes/queries/akun.php.
 *
 * Invariant: harus selalu ada minimal satu akun operator yang aktif. Tanpa itu
 * tidak ada lagi yang bisa memulihkan akun lain dari dalam aplikasi, dan
 * pemulihan hanya tersisa lewat akses database/CLI hosting.
 */

/** Role yang bisa dikelola halaman Petugas (siswa sengaja di luar). */
function petugas_role_katalog()
{
    $meta = $GLOBALS['META_ROLE'] ?? [];
    $out  = [];
    foreach (['operator', 'bendahara', 'kepsek'] as $r) {
        $out[$r] = $meta[$r]['label'] ?? ucfirst($r);
    }
    return $out;
}

/** Semua akun petugas, berurutan role lalu username. */
function petugas_semua()
{
    return q_all(
        "SELECT id, username, role, nama_lengkap, jabatan, is_active, wajib_ganti_password, created_at,
                (password_hash = '!LOCKED') AS terkunci
           FROM users
          WHERE role <> 'siswa'
          ORDER BY FIELD(role, 'operator', 'bendahara', 'kepsek'), username"
    ) ?: [];
}

/** Satu akun petugas (null = tidak ada / akun siswa / galat). */
function petugas_detail($id)
{
    $baris = q_all(
        "SELECT id, username, role, nama_lengkap, jabatan, is_active, wajib_ganti_password,
                (password_hash = '!LOCKED') AS terkunci
           FROM users
          WHERE id = ? AND role <> 'siswa'",
        [(string) $id]
    );
    return $baris ? $baris[0] : null;
}

/** null = username layak dipakai; selain itu pesan galat untuk operator. */
function petugas_username_valid($username, $kecualiId = '')
{
    $u = trim((string) $username);
    if ($u === '') {
        return 'Username wajib diisi.';
    }
    if (!preg_match('/^[a-z0-9._-]{4,30}$/', $u)) {
        return 'Username harus 4-30 karakter: huruf kecil, angka, titik, garis bawah, atau strip.';
    }
    $pakai = (int) (q_col('SELECT COUNT(*) FROM users WHERE username = ? AND id <> ?', [$u, (string) $kecualiId]) ?? 1);
    if ($pakai > 0) {
        return 'Username "' . $u . '" sudah dipakai akun lain.';
    }
    return null;
}

/** null = password sementara layak dipakai; selain itu pesan galat. */
function petugas_password_valid($password, $username)
{
    $p   = (string) $password;
    $pan = trim((string) $username);
    if (strlen($p) < 10) {
        return 'Password sementara minimal 10 karakter.';
    }
    /* bcrypt mengabaikan byte ke-72 dan seterusnya — tolak eksplisit agar
     * operator tidak mengira password panjangnya tersimpan utuh. */
    if (strlen($p) > 72) {
        return 'Password maksimal 72 karakter.';
    }
    if ($pan !== '' && strcasecmp($p, $pan) === 0) {
        return 'Password tidak boleh sama dengan username.';
    }
    return null;
}

/** Nama & jabatan tampilan (kolom users.nama_lengkap / users.jabatan). */
function petugas_teks_valid($nama, $jabatan)
{
    if (trim((string) $nama) === '') {
        return 'Nama petugas wajib diisi.';
    }
    if (mb_strlen(trim((string) $nama)) > 100) {
        return 'Nama petugas maksimal 100 karakter.';
    }
    if (mb_strlen(trim((string) $jabatan)) > 100) {
        return 'Jabatan maksimal 100 karakter.';
    }
    return null;
}

/** Buat akun petugas baru. Password sementara wajib diganti saat login pertama. */
function petugas_buat($d)
{
    $nama     = trim((string) ($d['nama'] ?? ''));
    $jabatan  = trim((string) ($d['jabatan'] ?? ''));
    $username = trim((string) ($d['username'] ?? ''));
    $role     = (string) ($d['role'] ?? '');
    $password = (string) ($d['password'] ?? '');

    $galat = petugas_teks_valid($nama, $jabatan);
    if ($galat === null && !array_key_exists($role, petugas_role_katalog())) {
        $galat = 'Role tidak dikenal.';
    }
    if ($galat === null) {
        $galat = petugas_username_valid($username);
    }
    if ($galat === null) {
        $galat = petugas_password_valid($password, $username);
    }
    if ($galat !== null) {
        return ['ok' => false, 'pesan' => $galat];
    }

    $ok = q_exec(
        'INSERT INTO users (id, username, password_hash, role, nama_lengkap, jabatan, is_active, wajib_ganti_password)
         VALUES (?, ?, ?, ?, ?, ?, 1, 1)',
        [
            'usr-' . bin2hex(random_bytes(5)),
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $nama,
            $jabatan !== '' ? $jabatan : petugas_role_katalog()[$role],
        ]
    );
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal menyimpan akun (kemungkinan bentrok username atau database tidak aktif).'];
    }
    return [
        'ok'      => true,
        'pesan'   => 'Petugas "' . $username . '" (' . petugas_role_katalog()[$role] . ') dibuat. '
            . 'Yang bersangkutan wajib mengganti password pada login pertama.',
        'username' => $username,
    ];
}

/** Ubah nama, jabatan, dan (opsional) username seorang petugas. */
function petugas_ubah_identitas($id, $nama, $jabatan, $username = null)
{
    $akun = petugas_detail($id);
    if (!$akun) {
        return ['ok' => false, 'pesan' => 'Akun petugas tidak ditemukan.'];
    }

    $galat = petugas_teks_valid($nama, $jabatan);
    $baru  = $username === null ? $akun['username'] : trim((string) $username);
    if ($galat === null && $baru !== $akun['username']) {
        $galat = petugas_username_valid($baru, $akun['id']);
    }
    if ($galat !== null) {
        return ['ok' => false, 'pesan' => $galat];
    }

    $ok = q_exec(
        'UPDATE users SET nama_lengkap = ?, jabatan = ?, username = ? WHERE id = ? AND role <> \'siswa\'',
        [trim((string) $nama), trim((string) $jabatan), $baru, $akun['id']]
    );
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal menyimpan perubahan.'];
    }
    return [
        'ok'        => true,
        'pesan'     => 'Data petugas diperbarui'
            . ($baru !== $akun['username'] ? ' — username sekarang "' . $baru . '".' : '.'),
        'username'  => $baru,
        'dahulu'    => $akun['username'],
    ];
}

/** Setel password sementara + paksa ganti pada login berikutnya. */
function petugas_set_password($id, $password)
{
    $akun = petugas_detail($id);
    if (!$akun) {
        return ['ok' => false, 'pesan' => 'Akun petugas tidak ditemukan.'];
    }
    $galat = petugas_password_valid($password, $akun['username']);
    if ($galat !== null) {
        return ['ok' => false, 'pesan' => $galat];
    }
    $ok = q_exec(
        "UPDATE users SET password_hash = ?, wajib_ganti_password = 1
          WHERE id = ? AND role <> 'siswa'",
        [password_hash($password, PASSWORD_DEFAULT), $akun['id']]
    );
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal menyimpan password.'];
    }
    return [
        'ok'    => true,
        'pesan' => 'Password "' . $akun['username'] . '" disetel. Akun wajib ganti password pada login berikutnya.',
    ];
}

/** Aktif/nonaktifkan akun — nonaktif adalah "keluar dari sistem", bukan hapus. */
function petugas_set_aktif($id, $aktif)
{
    $akun = petugas_detail($id);
    if (!$akun) {
        return ['ok' => false, 'pesan' => 'Akun petugas tidak ditemukan.'];
    }
    $aktif = (int) $aktif;

    if ($aktif === 0 && $akun['role'] === 'operator' && (int) $akun['is_active'] === 1) {
        $operatorAktif = (int) (q_col(
            "SELECT COUNT(*) FROM users WHERE role = 'operator' AND is_active = 1 AND id <> ?",
            [$akun['id']]
        ) ?? 0);
        if ($operatorAktif < 1) {
            return ['ok' => false, 'pesan' => 'Akun ini satu-satunya operator aktif. Buat atau aktifkan operator lain lebih dulu.'];
        }
    }

    $ok = q_exec("UPDATE users SET is_active = ? WHERE id = ? AND role <> 'siswa'", [$aktif, $akun['id']]);
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal mengubah status akun.'];
    }
    return [
        'ok'    => true,
        'pesan' => $aktif === 1
            ? 'Akun "' . $akun['username'] . '" diaktifkan kembali.'
            : 'Akun "' . $akun['username'] . '" dinonaktifkan — tidak bisa login, datanya tetap tersimpan.',
    ];
}
