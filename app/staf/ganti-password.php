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
    /* Fase 11 A2: rate limit 5 percobaan / 15 menit — per IP dan per user. */
    if (!rate_limit_cek('ganti_password', 5, 15)
        || !rate_limit_cek('ganti_password_user', 5, 15, (string) $user['id'])) {
        $galat[] = 'Terlalu banyak percobaan ganti password. Coba lagi beberapa menit lagi.';
    } else {
    rate_limit_catat('ganti_password');
    rate_limit_catat('ganti_password_user', (string) $user['id']);

    $lama   = $_POST['password_lama'] ?? '';
    $baru   = $_POST['password_baru'] ?? '';
    $ulangi = $_POST['password_ulangi'] ?? '';

    if ($lama === '') {
        $galat[] = 'Password lama wajib diisi.';
    }
    if (strlen($baru) < 10) {
        $galat[] = 'Password baru minimal 10 karakter.';
    }
    if ($baru !== '' && $baru === (string) ($user['username'] ?? '')) {
        $galat[] = 'Password baru tidak boleh sama dengan username.';
    }
    if ($baru !== $ulangi) {
        $galat[] = 'Konfirmasi password baru tidak sama.';
    }

    if (!$galat) {
        $akun = q_all('SELECT id, username, password_hash FROM users WHERE id = ?', [$user['id']]);
        $akun = $akun[0] ?? null;
        if (!$akun || !password_verify($lama, $akun['password_hash'])) {
            $galat[] = 'Password lama salah.';
        } elseif (password_verify($baru, $akun['password_hash'])) {
            $galat[] = 'Password baru tidak boleh sama dengan password lama.';
        } else {
            $ok = q_exec('UPDATE users SET password_hash = ?, wajib_ganti_password = 0 WHERE id = ?',
                [password_hash($baru, PASSWORD_DEFAULT), $akun['id']]);
            if ($ok) {
                /* Fase 11 A2: regenerasi ID sesi setelah hak berubah. */
                session_regenerate_id(true);
                flash('sukses', 'Password berhasil diganti.');
                header('Location: ganti-password.php');
                exit;
            }
            $galat[] = 'Gagal menyimpan password baru.';
        }
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
      <div class="relative">
        <input type="password" name="password_lama" required minlength="1"
               class="w-full border rounded-lg px-3 py-2 pr-11 dark:bg-slate-900 dark:border-slate-700">
        <button type="button" data-pass-toggle class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-500 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Lihat password" title="Lihat / sembunyikan password"><span class="ms text-[20px]">visibility</span></button>
      </div>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Password Baru</label>
      <div class="relative">
        <input type="password" name="password_baru" required minlength="10"
               class="w-full border rounded-lg px-3 py-2 pr-11 dark:bg-slate-900 dark:border-slate-700">
        <button type="button" data-pass-toggle class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-500 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Lihat password" title="Lihat / sembunyikan password"><span class="ms text-[20px]">visibility</span></button>
      </div>
      <p class="text-xs text-slate-500 mt-1">Minimal 10 karakter, tidak boleh sama dengan username.</p>
    </div>

    <div>
      <label class="block text-sm font-medium mb-1">Ulangi Password Baru</label>
      <div class="relative">
        <input type="password" name="password_ulangi" required minlength="10"
               class="w-full border rounded-lg px-3 py-2 pr-11 dark:bg-slate-900 dark:border-slate-700">
        <button type="button" data-pass-toggle class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 rounded-md text-slate-500 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Lihat password" title="Lihat / sembunyikan password"><span class="ms text-[20px]">visibility</span></button>
      </div>
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
<script>
  /* Toggle lihat/sembunyi password. */
  document.querySelectorAll('[data-pass-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var inp = btn.parentElement.querySelector('input');
      var shown = inp.type === 'text';
      inp.type = shown ? 'password' : 'text';
      btn.querySelector('.ms').textContent = shown ? 'visibility' : 'visibility_off';
      btn.setAttribute('aria-label', shown ? 'Lihat password' : 'Sembunyikan password');
    });
  });
</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
