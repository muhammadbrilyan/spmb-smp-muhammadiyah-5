<?php
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'bendahara', 'kepsek', 'admin']);
require_once __DIR__ . '/../../includes/queries/core.php';

$galat = [];
$user  = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        $galat[] = 'Formulir kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.';
    } else {
    $lama   = $_POST['password_lama'] ?? '';
    $baru   = $_POST['password_baru'] ?? '';
    $ulangi = $_POST['password_ulangi'] ?? '';

    if ($lama === '') {
        $galat[] = 'Password lama wajib diisi.';
    }
    if (strlen($baru) < 8) {
        $galat[] = 'Password baru minimal 8 karakter.';
    }
    if ($baru !== $ulangi) {
        $galat[] = 'Konfirmasi password baru tidak sama.';
    }

    if (!$galat) {
        $akun = q_all('SELECT id, password_hash FROM users WHERE id = ?', [$user['id']]);
        $akun = $akun[0] ?? null;
        if (!$akun || !password_verify($lama, $akun['password_hash'])) {
            $galat[] = 'Password lama salah.';
        } elseif (password_verify($baru, $akun['password_hash'])) {
            $galat[] = 'Password baru tidak boleh sama dengan password lama.';
        } else {
            $ok = q_exec('UPDATE users SET password_hash = ?, wajib_ganti_password = 0 WHERE id = ?',
                [password_hash($baru, PASSWORD_DEFAULT), $akun['id']]);
            if ($ok) {
                flash('sukses', 'Password berhasil diganti.');
                header('Location: ganti-password.php');
                exit;
            }
            $galat[] = 'Gagal menyimpan password baru.';
        }
    }
    }
    if ($galat) {
        flash('error', implode(' ', $galat));
    }
}

require_once __DIR__ . '/../../includes/partials/head.php';
require __DIR__ . '/../../includes/partials/sidebar.php';
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
?>
<div class="lg:pl-[264px]">
<main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-xl mx-auto">
  <h1 class="text-2xl font-bold mb-1">Ganti Password</h1>
  <p class="text-sm text-slate-500 mb-6">Masuk sebagai <strong><?= htmlspecialchars($user['nama_lengkap'] ?? $user['username']) ?></strong> (<?= htmlspecialchars($user['role']) ?>).</p>

  <?php if ($flash): ?>
    <div class="mb-4 rounded-xl border px-4 py-3 text-sm <?= $flash['tipe'] === 'sukses' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800' ?>">
      <?= e($flash['pesan']) ?>
    </div>
  <?php endif; ?>

  <form method="post" class="bg-white dark:bg-slate-800 rounded-xl shadow p-6 space-y-4" autocomplete="off">
    <?= csrf_field() ?>

    <div>
      <label class="block text-sm font-medium mb-1">Password Lama</label>
      <input type="password" name="password_lama" required minlength="1"
             class="w-full border rounded-lg px-3 py-2 dark:bg-slate-900 dark:border-slate-700">
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Password Baru</label>
      <input type="password" name="password_baru" required minlength="8"
             class="w-full border rounded-lg px-3 py-2 dark:bg-slate-900 dark:border-slate-700">
      <p class="text-xs text-slate-500 mt-1">Minimal 8 karakter.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Ulangi Password Baru</label>
      <input type="password" name="password_ulangi" required minlength="8"
             class="w-full border rounded-lg px-3 py-2 dark:bg-slate-900 dark:border-slate-700">
    </div>

    <button type="submit"
            class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg font-medium">
      Simpan Password Baru
    </button>
  </form>
</main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<?php if ($flash): ?>
<script>window.APP_FLASH = <?= json_encode(['tipe' => $flash['tipe'], 'pesan' => $flash['pesan']], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
