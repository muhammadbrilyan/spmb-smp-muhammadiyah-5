<?php
/**
 * Fase 12 F.2 / Fase 13 butir 1 — Set password akun staf LEWAT BROWSER
 * (hosting tanpa SSH).
 *
 * Keamanan berlapis (Fase 13):
 *   1. Hanya aktif bila penanda storage/.izinkan-set-password ADA (404 bila tidak).
 *   2. Isi penanda = KODE ACAK (min 8 karakter) yang harus diketik di form
 *      sebelum daftar akun terlihat — pengunjung anonim TIDAK bisa melihat
 *      daftar akun terkunci apalagi menyetel password tanpa kode ini.
 *   3. CSRF token per sesi; otorisasi kode disimpan di sesi privat.
 *   4. Rate limit 5 percobaan/jam per IP — DIHITUNG SETIAP percobaan
 *      (kode maupun password, sukses maupun gagal).
 *   5. HANYA akun berpassword_hash '!LOCKED' yang bisa dipilih.
 *   6. Setelah sukses: penanda DIHAPUS dan berkas ini menghapus DIRINYA
 *      SENDIRI (bila izin mengizinkan — kalau tidak, halaman meminta
 *      penghapusan manual via File Manager).
 *
 * Pakai: buat file storage/.izinkan-set-password berisi KODE ACAK (mis.
 * hasil password generator, min 8 karakter) via File Manager cPanel, buka
 * https://domain-anda/tools/set-password-web.php, ketik kode, selesaikan.
 */
require_once __DIR__ . '/../includes/config.php';

$penanda = storage_path('.izinkan-set-password');
if (!is_file($penanda)) {
    http_response_code(404);
    exit('Not Found');
}
$kodeFile = trim((string) file_get_contents($penanda));

$authorized = !empty($_SESSION['spw_authorized']);
$pesan      = null;
$sukses     = false;
$toolHapus  = false;
$akun       = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Setiap percobaan dihitung — termasuk yang gagal. */
    rate_limit_catat('set_pass_web');

    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        $pesan = 'Formulir kedaluwarsa — muat ulang halaman lalu coba lagi.';
    } elseif (!rate_limit_cek('set_pass_web', 5, 60)) {
        $pesan = 'Terlalu banyak percobaan (maks 5/jam per IP). Coba lagi nanti.';
    } else {
        $langkah = (string) ($_POST['langkah'] ?? '');

        if ($langkah === 'kode') {
            /* Tahap 1: verifikasi kode instalasi dari isi penanda. */
            $kode = (string) ($_POST['kode_instalasi'] ?? '');
            if ($kodeFile === '' || strlen($kodeFile) < 8) {
                $pesan = 'Penanda .izinkan-set-password harus berisi kode acak minimal 8 karakter. '
                    . 'Perbaiki isi file penanda via File Manager lalu muat ulang.';
            } elseif (!hash_equals($kodeFile, $kode)) {
                $pesan = 'Kode instalasi salah.';
            } else {
                $_SESSION['spw_authorized'] = true;
                $authorized = true;
            }
        } elseif ($langkah === 'simpan') {
            /* Tahap 2: set password (hanya setelah kode tervalidasi). */
            if (!$authorized) {
                $pesan = 'Sesi belum terverifikasi — ketik kode instalasi terlebih dahulu.';
            } else {
                try {
                    $akun = q_all(
                        "SELECT username, role, nama_lengkap FROM users
                          WHERE password_hash = '!LOCKED'
                            AND role IN ('operator','admin','bendahara','kepsek')
                          ORDER BY role, username"
                    ) ?: [];
                } catch (Throwable $e) {
                    $akun = [];
                }

                if (!$akun) {
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
                        $hash = password_hash($baru, PASSWORD_DEFAULT);
                        $ok   = q_exec(
                            'UPDATE users SET password_hash = ?, wajib_ganti_password = 1 WHERE username = ? AND password_hash = \'!LOCKED\'',
                            [$hash, $username]
                        );
                        if ($ok) {
                            @unlink($penanda);
                            $toolHapus = @unlink(__FILE__);   /* hapus diri sendiri */
                            unset($_SESSION['spw_authorized']);
                            $sukses = true;
                        } else {
                            $pesan = 'Gagal menyimpan — coba lagi atau periksa koneksi database.';
                        }
                    }
                }
            }
        } else {
            $pesan = 'Langkah tidak dikenal.';
        }
    }
}

/* Kunci daftar akun di belakang verifikasi kode. */
if (!$authorized && !$sukses) {
    $akun = [];
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
  .err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: .6rem .8rem; font-size: .82rem; margin-bottom: .75rem; }
  .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 10px; padding: .6rem .8rem; font-size: .82rem; line-height: 1.6; }
  .warn { font-size: .75rem; color: #b45309; margin-top: .9rem; line-height: 1.5; }
</style>
</head>
<body>
<div class="card">
  <h1>Set Password Akun Staf</h1>

  <?php if ($sukses): ?>
    <div class="ok">
      Password tersimpan. Akun ditandai <em>wajib ganti password</em> saat login pertama.<br>
      Penanda instalasi sudah dihapus<?php if ($toolHapus): ?>, begitu juga berkas alat ini<?php else: ?> —
      <strong>berkas alat ini belum bisa dihapus otomatis, hapus
      <code>tools/set-password-web.php</code> via File Manager SEKARANG</strong><?php endif; ?>.
    </div>
  <?php else: ?>
    <?php if ($pesan): ?><div class="err"><?= e($pesan) ?></div><?php endif; ?>

    <?php if (!$authorized): ?>
      <p class="hint">Alat darurat instalasi. Masukkan <strong>KODE INSTALASI</strong> yang
      tertulis di dalam file penanda <code>storage/.izinkan-set-password</code>
      (Anda yang membuatnya via File Manager). Tanpa kode ini tidak ada informasi
      akun yang ditampilkan.</p>
      <form method="post" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="langkah" value="kode">
        <label for="kode_instalasi">Kode instalasi</label>
        <input id="kode_instalasi" name="kode_instalasi" type="password" required
               minlength="8" placeholder="ketik isi file penanda" autofocus>
        <button type="submit">Verifikasi kode</button>
      </form>
      <p class="warn">Maks 5 percobaan per jam per IP. Selama file penanda &amp; alat ini ada
      di server, jangan publikasikan domain sebelum keduanya dihapus.</p>
    <?php else: ?>
      <p class="hint">Kode terverifikasi. Pilih akun <strong>terkunci</strong> yang akan
      diatur — setelah berhasil, penanda &amp; berkas alat ini terhapus otomatis.</p>
      <?php if (!$akun): ?>
        <div class="err">Tidak ada akun staf terkunci di database — tidak ada yang bisa diatur.</div>
        <form method="post"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="langkah" value="kode"><button type="submit">Muat ulang</button></form>
      <?php else: ?>
        <form method="post" autocomplete="off">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="langkah" value="simpan">
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
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
