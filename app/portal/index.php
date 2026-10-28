<?php
require_once __DIR__ . '/../../includes/config.php';

require_login();

$me   = current_user();
$flash = flash();

/* Hak akses per role — merujuk matriks RBAC pada PRD. */
$HAK_AKSES = [
    'siswa'     => ['Biodata lengkap', 'Unggah berkas (maks. 2MB)', 'Status verifikasi bayar', 'Jadwal tes & hasil seleksi', 'Penempatan kelas'],
    'operator'  => ['Akses penuh CMS', 'Pengaturan branding & biaya', 'Manajemen kelas/kuota', 'Generate akun & kirim WA', 'Penjadwalan tes & input seleksi'],
    'bendahara' => ['Verifikasi bukti bayar formulir', 'Penetapan status ' . label_biaya_pendaftaran(), 'Skema angsuran (Lunas / Angsuran 1 & 2)'],
    'kepsek'    => ['Dashboard analitik (read-only)', 'Keterisian kuota rombel', 'Rekapitulasi finansial'],
];

$role      = $me['role'];
$daftarHak = $HAK_AKSES[$role] ?? ['Akses dasar portal'];

/* Role yang sudah punya halaman dashboard tersendiri. */
$tujuanRole    = role_home($role);
$punyaDashboard = $tujuanRole !== url('app/portal/index.php');
$labelDashboard = [
    'operator' => 'Dashboard Operator',
    'admin'    => 'Dashboard Operator',
    'bendahara'=> 'Dashboard Bendahara',
    'kepsek'   => 'Dashboard Kepala Sekolah',
    'siswa'    => 'Portal Saya',
][$role] ?? 'Portal Saya';

$title   = 'Portal ' . ($META_ROLE[$role]['label'] ?? 'Siswa');
$desc    = 'Halaman portal ' . APP_NAME . '.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
?>

<div class="min-h-screen flex flex-col">

  <!-- Header ringkas -->
  <header class="bg-white border-b border-line">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
      <a href="<?= url('app/spmb/index.php') ?>" class="flex items-center gap-3 min-w-0">
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo" class="w-9 h-9 shrink-0">
        <span class="min-w-0 leading-tight">
          <span class="block font-bold text-ink-900 text-[15px] truncate"><?= e(APP_SHORT) ?></span>
          <span class="block text-[11px] text-ink-500"><?= e(APP_TAGLINE) ?></span>
        </span>
      </a>

      <div class="flex items-center gap-2">
        <?php if ($punyaDashboard): ?>
          <a href="<?= e($tujuanRole) ?>" class="btn btn-primary btn-sm"><?= icon('dashboard', 'text-[16px]') ?> <?= e($labelDashboard) ?></a>
        <?php endif; ?>
        <form method="post" action="<?= url('app/auth/logout.php') ?>" class="inline">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline btn-sm"><?= icon('logout', 'text-[16px]') ?> Keluar</button>
        </form>
      </div>
    </div>
  </header>

  <main class="flex-1 flex items-center justify-center px-4 sm:px-6 py-12">
    <div class="w-full max-w-2xl">

      <?php if ($flash): ?>
        <div class="mb-5 flex items-start gap-2.5 rounded-xl border px-4 py-3 text-sm animate-fade
          <?= $flash['tipe'] === 'error' ? 'border-[#FECACA] bg-[#FEF2F2] text-[#991B1B]' : 'border-[#A7F3D0] bg-[#ECFDF5] text-[#065F46]' ?>" role="alert">
          <span class="ms text-[18px] mt-0.5"><?= $flash['tipe'] === 'error' ? 'error' : 'check_circle' ?></span>
          <span><?= e($flash['pesan']) ?></span>
        </div>
      <?php endif; ?>

      <div class="card overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-primary-light to-white border-b border-line flex items-center gap-4">
          <span class="w-12 h-12 rounded-xl bg-primary text-white grid place-items-center font-bold text-base shrink-0"><?= e($me['inisial']) ?></span>
          <div class="min-w-0">
            <p class="text-[11px] uppercase tracking-wider text-ink-500 font-semibold">Masuk sebagai <?= e($META_ROLE[$role]['label'] ?? $role) ?></p>
            <h1 class="text-lg font-bold text-ink-900 truncate"><?= e($me['nama']) ?></h1>
            <p class="text-xs text-ink-500"><?= e($me['jabatan']) ?> · <span class="font-mono">@<?= e($me['username']) ?></span></p>
          </div>
        </div>

        <div class="p-6">
          <?php if ($punyaDashboard): ?>
            <div class="flex items-start gap-3 rounded-xl bg-[#ECFDF5] border border-[#A7F3D0] p-4">
              <span class="icon-box bg-[#0E9F6E] text-white shrink-0"><?= icon('dashboard') ?></span>
              <div>
                <p class="font-semibold text-[#065F46] text-sm"><?= e($labelDashboard) ?> sudah tersedia</p>
                <p class="text-[13px] text-[#065F46]/80 mt-0.5 leading-relaxed">
                  Hak akses <strong><?= e($META_ROLE[$role]['label'] ?? $role) ?></strong> aktif pada sesi ini —
                  silakan buka dashboard untuk melanjutkan pekerjaan Anda.
                </p>
              </div>
            </div>
          <?php else: ?>
            <div class="flex items-start gap-3 rounded-xl bg-canvas border border-line p-4">
              <span class="icon-box bg-white border border-line text-ink-500 shrink-0"><?= icon('construction') ?></span>
              <div>
                <p class="font-semibold text-ink-900 text-sm">Halaman untuk role ini sedang dalam pengembangan</p>
                <p class="text-[13px] text-ink-500 mt-0.5 leading-relaxed">
                  Sesi dan hak akses Anda sudah aktif. Tampilan dashboard untuk role
                  <strong><?= e($META_ROLE[$role]['label'] ?? $role) ?></strong> akan menyusul pada tahap berikutnya —
                  dashboard <strong>Operator</strong> dan <strong>Bendahara</strong> sudah dapat dicoba.
                </p>
              </div>
            </div>
          <?php endif; ?>

          <div class="mt-6">
            <p class="text-[11px] uppercase tracking-wider text-ink-400 font-semibold">Hak akses Anda (matriks RBAC PRD)</p>
            <ul class="mt-3 grid sm:grid-cols-2 gap-2">
              <?php foreach ($daftarHak as $hak): ?>
                <li class="flex items-start gap-2 rounded-lg border border-line px-3 py-2.5 text-sm text-ink-700">
                  <span class="ms text-[17px] text-[#0E9F6E] mt-0.5">check_circle</span>
                  <span><?= e($hak) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>

          <div class="mt-6 flex flex-wrap gap-3">
            <?php if ($punyaDashboard): ?>
              <a href="<?= e($tujuanRole) ?>" class="btn btn-primary"><?= icon('arrow_forward') ?> Buka <?= e($labelDashboard) ?></a>
            <?php endif; ?>
            <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline"><?= icon('language') ?> Kembali ke SPMB</a>
            <form method="post" action="<?= url('app/auth/logout.php') ?>" class="inline">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-ghost"><?= icon('logout') ?> Keluar</button>
            </form>
          </div>
        </div>
      </div>

      <p class="mt-5 text-center text-xs text-ink-400">
        &copy; <?= date('Y') ?> <?= e($PENGATURAN['nama_sekolah']) ?>
      </p>
    </div>
  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[60] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
