<?php
/**
 * Fase 12 F.2 — Set password akun staf LEWAT BROWSER (hosting tanpa SSH).
 *
 * Keamanan berlapis:
 *   1. Hanya aktif bila penanda storage/.izinkan-set-password ADA (404 bila tidak).
 *   2. CSRF token per sesi.
 *   3. Rate limit 5 percobaan/jam per IP.
 *   4. HANYA akun berpassword_hash '!LOCKED' yang bisa dipilih — bila semua
 *      staf sudah punya password valid, tool menolak semua aksi.
 *   5. Setelah sukses, penanda otomatis DIHAPUS dan halaman meminta Anda
 *      menghapus berkas tools/set-password-web.php dari server.
 *
 * Pakai: buat file kosong storage/.izinkan-set-password via File Manager
 * cPanel, buka https://domain-anda/tools/set-password-web.php, selesaikan,
 * lalu hapus penanda + berkas tool ini.
 */
require_once __DIR__ . '/../includes/config.php';

$penanda = storage_path('.izinkan-set-password');
if (!is_file($penanda)) {
    http_response_code(404);
    exit('Not Found');
}

$pesan  = null;
$sukses = false;
$akun   = [];

try {
    $akun = q_all(
        "SELECT username, role, nama_lengkap FROM users
          WHERE password_hash = '!LOCKED'
            AND role IN ('operator','admin','bendahara','kepsek')
          ORDER BY role, username"
    ) ?: [];
} catch (Throwable $e) {
    $pesan = 'Database tidak tersedia.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        $pesan = 'Formulir kedaluwarsa — muat ulang halaman lalu coba lagi.';
    } elseif (!rate_limit_cek('set_pass_web', 5, 60)) {
        $pesan = 'Terlalu banyak percobaan (maks 5/jam per IP). Coba lagi nanti.';
    } elseif (!$akun) {
        $pesan = 'Tidak ada akun staf terkunci — semua akun sudah memiliki password. '
            . 'Gunakan alat CLI set-password.php (bila ada akses) atau hubungi administrator database.';
    } else {
        $username = (string) ($_POST['username'] ?? '');
        $baru     = (string) ($_POST['password_baru'] ?? '');
        $ulangi   = (string) ($_POST['password_ulangi'] ?? '');

        $dipilih = null;
        foreach ($akun as $a) {
            if ($a['username'] === $username) {
                $dipilih = $a;
                break;
            }
        }

        if ($dipilih === null) {
            $pesan = 'Pilihan akun tidak valid — hanya akun terkunci yang bisa diatur.';
        } elseif (strlen($baru) < 10) {
            $pesan = 'Password minimal 10 karakter.';
        } elseif ($baru === $username) {
            $pesan = 'Password tidak boleh sama dengan username.';
        } elseif ($baru !== $ulangi) {
            $pesan = 'Konfirmasi password tidak sama.';
        } else {
            rate_limit_catat('set_pass_web');
            $hash = password_hash($baru, PASSWORD_DEFAULT);
            $ok   = q_exec(
                'UPDATE users SET password_hash = ?, wajib_ganti_password = 1 WHERE username = ? AND password_hash = \'!LOCKED\'',
                [$hash, $username]
            );
            if ($ok) {
                @unlink($penanda);
                $sukses = true;
            } else {
                $pesan = 'Gagal menyimpan — coba lagi atau periksa koneksi database.';
            }
        }
    }
}

$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Set Password Staf — SPMB</title>
<style>
  body { font-family: system-ui, sans-serif; background: #f1f5f9; display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 1.5rem; }
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.75rem; width: 100%; max-width: 440px; box-shadow: 0 8px 24px rgba(15,23,42,.06); }
  h1 { font-size: 1.15rem; margin: 0 0 .35rem; color: #0f172a; }
  p.hint { font-size: .8rem; color: #64748b; margin: 0 0 1rem; line-height: 1.5; }
  label { display: block; font-size: .8rem; font-weight: 600; color: #334155; margin: .75rem 0 .25rem; }
  select, input { width: 100%; box-sizing: border-box; padding: .55rem .7rem; border: 1px solid #cbd5e1; border-radius: 10px; font-size: .9rem; }
  button { margin-top: 1.1rem; width: 100%; padding: .65rem; border: 0; border-radius: 10px; background: #b91c1c; color: #fff; font-weight: 700; font-size: .9rem; cursor: pointer; }
  button:hover { background: #991b1b; }
  .err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: .6rem .8rem; font-size: .82rem; }
  .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 10px; padding: .6rem .8rem; font-size: .82rem; line-height: 1.6; }
  .warn { font-size: .75rem; color: #b45309; margin-top: .9rem; line-height: 1.5; }
</style>
</head>
<body>
<div class="card">
  <h1>Set Password Akun Staf</h1>
  <p class="hint">Alat darurat instalasi (Fase 12) — hanya untuk akun yang masih
  <strong>terkunci</strong>. Setelah berhasil, penanda <code>.izinkan-set-password</code>
  otomatis terhapus; <strong>segera hapus berkas ini</strong> dari server.</p>

  <?php if ($sukses): ?>
    <div class="ok">
      Password tersimpan. Akun ditandai <em>wajib ganti password</em> saat login pertama.<br>
      <strong>Langkah wajib berikutnya:</strong> hapus berkas
      <code>tools/set-password-web.php</code> dari server via File Manager.
    </div>
  <?php else: ?>
    <?php if ($pesan): ?><div class="err"><?= e($pesan) ?></div><?php endif; ?>
    <?php if (!$akun): ?>
      <div class="err">Tidak ada akun staf terkunci di database — tidak ada yang bisa diatur.</div>
    <?php else: ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <label for="username">Akun</label>
        <select id="username" name="username" required>
          <?php foreach ($akun as $a): ?>
            <option value="<?= e($a['username']) ?>"><?= e($a['username']) ?> — <?= e($a['role']) ?> (<?= e($a['nama_lengkap'] ?? '-') ?>)</option>
          <?php endforeach; ?>
        </select>
        <label for="password_baru">Password baru (min 10 karakter)</label>
        <input id="password_baru" name="password_baru" type="password" minlength="10" required>
        <label for="password_ulangi">Ulangi password baru</label>
        <input id="password_ulangi" name="password_ulangi" type="password" minlength="10" required>
        <button type="submit">Simpan password</button>
      </form>
      <p class="warn">Halaman ini bersifat publik selama penanda ada — gunakan hanya
      saat instalasi, jangan dibiarkan aktif di produksi.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
