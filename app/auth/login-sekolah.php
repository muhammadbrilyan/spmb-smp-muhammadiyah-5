<?php
/* ==================================================================
 * LOGIN STAFF (Operator, Bendahara, Kepala Sekolah)
 * Login siswa/ortu ada di app/auth/login.php (terpisah).
 *
 * YANG SERING DIUBAH:
 *   - Teks judul/deskripsi halaman → bagian HTML setelah blok PHP
 *   - Redirect setelah login       → otomatis via role_home() di includes/auth.php
 * ================================================================== */
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
            /* Tolak login siswa di halaman ini */
            if (user_role() === 'siswa') {
                logout();
                $error = 'Akun siswa tidak bisa login di halaman ini. Silakan gunakan portal siswa.';
            } else {
                header('Location: ' . ($next !== '' ? $next : role_home(user_role())));
                exit;
            }
        } else {
            $error = $hasil['pesan'];
        }
    }
}

$title = 'Login Pihak Sekolah';
$desc  = 'Masuk ke dashboard operator, bendahara, atau kepala sekolah SMP Muhammadiyah 5 Kota Tangerang.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';

/* Hanya role staf yang ditampilkan di halaman ini. */
$ROLE_SEKOLAH = ['operator', 'bendahara', 'kepsek'];
$ikon_role = ['operator' => 'settings_suggest', 'bendahara' => 'payments', 'kepsek' => 'workspace_premium'];
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
          <span class="block text-xs text-white/70">Portal Pihak Sekolah</span>
        </span>
      </a>
    </div>

    <div class="relative max-w-md">
      <span class="badge badge-green bg-white/10! text-white! border-white/25!"><?= dot('green') ?> Akses Internal</span>
      <h1 class="mt-5 text-3xl xl:text-[34px] font-extrabold text-white leading-tight tracking-tight">
        Dashboard Pengelola SPMB &amp; SIMS
      </h1>
      <p class="mt-4 text-white/75 leading-relaxed">
        Kelola pendaftaran, verifikasi pembayaran, input seleksi, penempatan kelas, dan pantau statistik SPMB — semuanya dari satu dashboard.
      </p>

      <ul class="mt-8 space-y-3 text-sm text-white/85">
        <li class="flex items-center gap-3"><span class="icon-box bg-white/10 text-white"><?= icon('settings_suggest') ?></span> Operator — kelola penuh seluruh tahapan SPMB</li>
        <li class="flex items-center gap-3"><span class="icon-box bg-white/10 text-white"><?= icon('payments') ?></span> Bendahara — verifikasi pembayaran &amp; rekap keuangan</li>
        <li class="flex items-center gap-3"><span class="icon-box bg-white/10 text-white"><?= icon('workspace_premium') ?></span> Kepala Sekolah — monitoring &amp; persetujuan akhir</li>
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
          <span class="block text-xs text-ink-500">Portal Pihak Sekolah</span>
        </span>
      </div>

      <a href="<?= url('app/spmb/index.php') ?>" class="inline-flex items-center gap-1.5 text-sm text-ink-500 hover:text-primary transition mb-6">
        <?= icon('arrow_back', 'text-[17px]') ?> Kembali ke halaman SPMB
      </a>

      <div class="flex items-center justify-between gap-3">
        <div>
          <h2 class="text-2xl font-bold text-ink-900 tracking-tight">Login Pihak Sekolah</h2>
          <p class="mt-1 text-sm text-ink-500">Khusus operator, bendahara, dan kepala sekolah.</p>
        </div>
        <span class="badge badge-blue hidden sm:inline-flex"><?= dot('green') ?> Internal</span>
      </div>

      <!-- Pilihan peran staf -->
      <div class="mt-6">
        <span class="label">Masuk sebagai</span>
        <div class="grid grid-cols-3 gap-2" id="role-picker">
          <?php foreach ($ROLE_SEKOLAH as $i => $role):
              if (!isset($AKUN_PER_ROLE[$role])) { continue; }
              $akun = $AKUN_PER_ROLE[$role];
          ?>
            <button type="button"
              class="role-chip flex flex-col items-center gap-1.5 rounded-xl border px-2 py-3 transition text-center
              <?= $i === 0 ? 'border-primary bg-primary-light text-primary' : 'border-line bg-white text-ink-700 hover:border-line-mid' ?>"
              data-demo-fill
              data-label="<?= e($akun['label']) ?>"
              data-user="<?= e($akun['username']) ?>"
              <?php if ($devMode && is_string($akun['password'] ?? null) && $akun['password'] !== ''): ?>data-pass="<?= e($akun['password']) ?>"<?php endif; ?>>
              <span class="ms text-[20px]"><?= e($ikon_role[$role] ?? 'person') ?></span>
              <span class="text-xs font-semibold"><?= e($akun['label']) ?></span>
            </button>
          <?php endforeach; ?>
        </div>
        <p class="hint">Klik peran untuk mengisi otomatis kredensial demo (hanya di localhost).</p>
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

      <form class="mt-5 space-y-4" method="post" action="<?= url('app/auth/login-sekolah.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <div>
          <label class="label" for="username">Username</label>
          <div class="relative">
            <span class="ms absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-ink-400">badge</span>
            <input id="username" name="username" class="input pl-10" type="text" autocomplete="username"
                   placeholder="Masukkan username staf" required autofocus>
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
          Masuk ke Dashboard <?= icon('arrow_forward') ?>
        </button>
      </form>

      <?php if ($devMode): ?>
      <!-- Kredensial demo — hanya tampil di localhost/dev -->
      <div class="mt-6 card p-4">
        <div class="flex items-center gap-2 text-xs font-semibold text-ink-500 uppercase tracking-wider">
          <?= icon('key', 'text-[15px] text-primary') ?> Akun Demo Staf
          <span class="badge h-5 px-2 text-[9px] <?= db_aktif() ? 'badge-green' : 'badge-amber' ?>">
            <?= db_aktif() ? e(DB_NAME) . '.users' : 'mode dummy' ?>
          </span>
        </div>
        <ul class="mt-3 space-y-2 text-xs">
          <?php foreach ($ROLE_SEKOLAH as $role):
              if (!isset($AKUN_PER_ROLE[$role])) { continue; }
              $akun = $AKUN_PER_ROLE[$role];
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
          <?php endforeach; ?>
        </ul>
        <p class="mt-3 text-[11px] text-ink-400 leading-relaxed">
          Login diverifikasi terhadap tabel <span class="font-mono">users</span> (bcrypt). Password tidak disimpan di kode — reset lewat
          <span class="font-mono">php tools/set-password.php &lt;username&gt;</span>.
        </p>
      </div>
      <?php endif; ?>

      <p class="mt-6 text-center text-xs text-ink-400">
        Siswa? Login di <a href="<?= url('app/auth/login.php') ?>" class="text-primary hover:underline font-medium">Portal Siswa</a>
      </p>

    </div>
  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[60] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
