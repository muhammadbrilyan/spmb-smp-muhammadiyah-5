<?php
require_once __DIR__ . '/../../includes/config.php';

/* Sudah masuk? → langsung ke halaman role masing-masing. */
if (is_logged_in()) {
    header('Location: ' . role_home(user_role()));
    exit;
}

$next  = safe_next($_GET['next'] ?? '');
$error = '';
$devMode = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') === 0)
        || (strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') === 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        $error = 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $next     = safe_next($_POST['next'] ?? '');

        $hasil = attempt_login($username, $password);
        if ($hasil['ok']) {
            /* Tolak login staf sekolah di halaman siswa */
            if (user_role() !== 'siswa') {
                logout();
                $error = 'Akun staf sekolah tidak bisa login di halaman ini. Silakan gunakan Login Pihak Sekolah.';
            } else {
                header('Location: ' . ($next !== '' ? $next : role_home(user_role())));
                exit;
            }
        } else {
            $error = $hasil['pesan'];
        }
    }
}

$title = 'Login Portal Siswa';
$desc  = 'Masuk ke portal siswa SPMB SMP Muhammadiyah 5 Kota Tangerang.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
?>

<div class="min-h-screen lg:grid lg:grid-cols-2">

  <!-- Panel kiri: visual sekolah — banner bisa diganti operator di CMS Pengaturan → Aset Visual -->
  <aside class="relative hidden lg:flex flex-col justify-between p-10 xl:p-14 overflow-hidden">
    <?php $loginBanner = $PENGATURAN['login_banner_url'] ?? ''; ?>
    <?php if (!empty($loginBanner)): ?>
      <img src="<?= e($loginBanner) ?>" alt="Foto Sekolah" class="absolute inset-0 w-full h-full object-cover">
    <?php else: ?>
      <img src="<?= asset('assets/img/kampus.png') ?>" alt="Kampus SMP Muhammadiyah 5" class="absolute inset-0 w-full h-full object-cover">
    <?php endif; ?>
    <div class="absolute inset-0 bg-gradient-to-br from-ink-900/90 via-ink-900/75 to-primary/70"></div>
    <div class="absolute -bottom-32 -left-24 w-96 h-96 rounded-full bg-accent/20 blur-3xl"></div>

    <div class="relative">
      <a href="<?= url('app/spmb/index.php') ?>" class="inline-flex items-center gap-3">
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo" class="w-11 h-11 ring-2 ring-white/30 rounded-full">
        <span class="leading-tight">
          <span class="block font-bold text-white text-base"><?= e(APP_SHORT) ?></span>
          <span class="block text-xs text-white/70">Portal Siswa</span>
        </span>
      </a>
    </div>

    <div class="relative max-w-md">
      <span class="badge badge-green bg-white/10! text-white! border-white/25!"><?= dot('green') ?> SPMB <?= e($PENGATURAN['tahun_ajaran']) ?></span>
      <h1 class="mt-5 text-3xl xl:text-[34px] font-extrabold text-white leading-tight tracking-tight">
        Pantau proses SPMB Anda di sini.
      </h1>
      <p class="mt-4 text-white/75 leading-relaxed">
        Lihat status verifikasi berkas, jadwal tes, hasil seleksi, hingga penempatan kelas — semuanya dalam satu akun siswa.
      </p>

      <ul class="mt-8 space-y-3 text-sm text-white/85">
        <li class="flex items-center gap-3"><span class="icon-box bg-white/10 text-white"><?= icon('upload_file') ?></span> Unggah berkas persyaratan &amp; bukti transfer</li>
        <li class="flex items-center gap-3"><span class="icon-box bg-white/10 text-white"><?= icon('payment') ?></span> Bayar biaya formulir online (VA / QRIS / e-wallet)</li>
        <li class="flex items-center gap-3"><span class="icon-box bg-white/10 text-white"><?= icon('school') ?></span> Lihat hasil seleksi &amp; penempatan kelas</li>
      </ul>
    </div>

    <div class="relative flex items-center gap-4 text-xs text-white/60">
      <span>&copy; <?= date('Y') ?> <?= e(APP_SHORT) ?></span>
      <span class="w-1 h-1 rounded-full bg-white/40"></span>
      <span>Terakreditasi A · Dikdasmen PDM Kota Tangerang</span>
    </div>
  </aside>

  <!-- Panel kanan: form login -->
  <main class="flex items-center justify-center px-5 py-10 sm:px-10 lg:py-14">
    <div class="w-full max-w-md">

      <div class="lg:hidden flex items-center gap-3 mb-8">
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo" class="w-10 h-10">
        <span class="leading-tight">
          <span class="block font-bold text-ink-900"><?= e(APP_SHORT) ?></span>
          <span class="block text-xs text-ink-500">Portal Siswa</span>
        </span>
      </div>

      <a href="<?= url('app/spmb/index.php') ?>" class="inline-flex items-center gap-1.5 text-sm text-ink-500 hover:text-primary transition mb-6">
        <?= icon('arrow_back', 'text-[17px]') ?> Kembali ke halaman SPMB
      </a>

      <div class="flex items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-bold text-ink-900 tracking-tight">Login Portal Siswa</h2>
          <p class="mt-1 text-sm text-ink-500">Gunakan NISN &amp; password yang diberikan panitia.</p>
        </div>
        <span class="badge badge-green hidden sm:inline-flex"><?= dot('green') ?> Server Aktif</span>
      </div>

      <?php if ($error): ?>
        <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3 text-sm text-[#991B1B] animate-fade" role="alert">
          <span class="ms text-[18px] mt-0.5">error</span>
          <span><?= e($error) ?></span>
        </div>
      <?php elseif ($next !== ''): ?>
        <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3 text-sm text-[#1E40AF] animate-fade" role="status">
          <span class="ms text-[18px] mt-0.5">info</span>
          <span>Silakan login terlebih dahulu — Anda akan diarahkan kembali ke halaman yang diminta.</span>
        </div>
      <?php endif; ?>

      <form class="mt-5 space-y-4" method="post" action="<?= url('app/auth/login.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div>
          <label class="label" for="username">NISN / Username</label>
          <div class="relative">
            <span class="ms absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-ink-400">school</span>
            <input id="username" name="username" class="input pl-10" type="text" autocomplete="username"
                   placeholder="Masukkan NISN atau username" required autofocus>
          </div>
        </div>

        <div>
          <label class="label" for="password">Password</label>
          <div class="relative">
            <span class="ms absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-ink-400">lock</span>
            <input id="password" name="password" class="input pl-10 pr-11" type="password" autocomplete="current-password"
                   placeholder="••••••••" required>
            <button type="button" id="pw-toggle"
                    class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 grid place-items-center rounded-md text-ink-400 hover:text-primary hover:bg-line-soft"
                    aria-label="Tampilkan password">
              <span class="ms text-[18px]">visibility</span>
            </button>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block shadow-drop">
          Masuk ke Portal Siswa <?= icon('arrow_forward') ?>
        </button>
      </form>

      <?php if ($devMode): ?>
      <!-- Kredensial demo — hanya tampil di localhost/dev -->
      <div class="mt-6 card p-4">
        <div class="flex items-center gap-2 text-xs font-semibold text-ink-500 uppercase tracking-wider">
          <?= icon('key', 'text-[15px] text-primary') ?> Akun Demo Siswa
          <span class="badge h-5 px-2 text-[9px] <?= db_aktif() ? 'badge-green' : 'badge-amber' ?>">
            <?= db_aktif() ? e(DB_NAME) . '.users' : 'mode dummy' ?>
          </span>
        </div>
        <ul class="mt-3 space-y-2 text-xs">
          <?php if (isset($AKUN_PER_ROLE['siswa'])):
              $akun = $AKUN_PER_ROLE['siswa'];
          ?>
            <li class="flex items-center justify-between gap-3 rounded-lg bg-canvas border border-line px-3 py-2">
              <span class="font-medium text-ink-700"><?= e($akun['label']) ?></span>
              <span class="font-mono text-ink-500 truncate"><?= e($akun['username']) ?> / <?php
                if (is_string($akun['password'] ?? null) && $akun['password'] !== '') {
                    echo e($akun['password']);
                } else {
                    echo '&#8226;&#8226;&#8226;';
                }
              ?></span>
            </li>
          <?php endif; ?>
        </ul>
        <p class="mt-3 text-[11px] text-ink-400 leading-relaxed">
          Login diverifikasi terhadap tabel <span class="font-mono">users</span> (bcrypt). Password tidak disimpan di kode — lihat
          <span class="font-mono">tools/set-password.php</span> untuk reset.
        </p>
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[60] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
