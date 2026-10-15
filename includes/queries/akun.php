<?php
/**
 * Modul queries: akun — di-include dari includes/queries.php.
 */

function akun_username_unik($no_pendaftaran)
{
    $digits = preg_replace('/\D+/', '', (string) $no_pendaftaran);
    $basis  = 'siswa' . substr($digits, -4);
    $u = $basis;
    $n = 2;
    while ((int) (q_col('SELECT COUNT(*) FROM users WHERE username = ?', [$u]) ?? 1) > 0) {
        $u = $basis . $n;
        $n++;
    }
    return $u;
}

/**
 * Buat akun siswa (users + link pendaftar). return ['ok','username','password'].
 * Password hanya dikembalikan sekali (bcrypt yang disimpan).
 */

function akun_buat($pendaftar_id)
{
    $p = pendaftar_detail_akun($pendaftar_id);
    if (!$p) {
        return ['ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.'];
    }
    if ($p['user_id']) {
        return ['ok' => false, 'pesan' => 'Akun sudah dibuat sebelumnya (username: ' . $p['username'] . ').'];
    }
    if ($p['status_verifikasi'] === 'DITOLAK') {
        return ['ok' => false, 'pesan' => 'Pendaftar berstatus DITOLAK — akun tidak dibuat.'];
    }

    $username = akun_username_unik($p['no_pendaftaran']);
    $password = password_acak();
    $userId   = 'usr-' . bin2hex(random_bytes(5));

    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    try {
        $db->beginTransaction();
        $db->prepare('INSERT INTO users (id, username, password_hash, role, nama_lengkap, jabatan, is_active)
                      VALUES (?, ?, ?, ?, ?, ?, 1)')
            ->execute([$userId, $username, password_hash($password, PASSWORD_DEFAULT), 'siswa', $p['nama_siswa'], 'Calon Siswa SPMB']);
        $db->prepare("UPDATE pendaftar SET user_id = ?, status_akun = 'AKTIF' WHERE id = ?")
           ->execute([$userId, $pendaftar_id]);
        $db->commit();
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal menyimpan akun (kemungkinan bentrok).'];
    }

    return ['ok' => true, 'username' => $username, 'password' => $password, 'nama' => $p['nama_siswa'], 'reg' => $p['no_pendaftaran'], 'wa' => $p['no_whatsapp'] ?? '', 'pendaftar_id' => $pendaftar_id];
}

/** Reset password akun siswa (untuk "Kirim Ulang"). return password baru sekali. */

function akun_reset_password($user_id)
{
    $password = password_acak();
    $ok = q_exec('UPDATE users SET password_hash = ? WHERE id = ? AND role = ?', [password_hash($password, PASSWORD_DEFAULT), $user_id, 'siswa']);
    return $ok ? ['ok' => true, 'password' => $password] : ['ok' => false, 'pesan' => 'Gagal mereset password.'];
}

/** Tandai kredensial terkirim. */

function akun_tandai_terkirim($pendaftar_id)
{
    return q_exec("UPDATE pendaftar SET kred_sent_at = NOW() WHERE id = ?", [$pendaftar_id]);
}

/** Tandai status akun GAGAL (opsional). */

function akun_tandai_gagal($pendaftar_id)
{
    return q_exec("UPDATE pendaftar SET status_akun = 'GAGAL' WHERE id = ?", [$pendaftar_id]);
}

/* ==================================================================
 * 11. INPUT NILAI SELEKSI — daftar untuk formulir
 * ================================================================== */

/**
 * Seluruh pendaftar (kecuali DITOLAK) beserta nilai seleksi yang sudah ada.
 */
