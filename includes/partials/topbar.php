<?php
/**
 * Topbar dashboard.
 * Variabel: $breadcrumbs (array label => href|null), $pageActions (HTML opsional)
 */
$breadcrumbs = $breadcrumbs ?? ['Portal Akademik' => null, 'SPMB' => null, 'Operasional Operator SPMB' => null];
$pageActions = $pageActions ?? '';
?>
<header class="sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-line">
  <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center gap-3">

    <button class="lg:hidden icon-box border border-line text-ink-700 shrink-0" data-sidebar-open aria-label="Buka menu">
      <?= icon('menu') ?>
    </button>

    <!-- Breadcrumb -->
    <nav class="hidden md:flex items-center gap-1.5 text-[13px] text-ink-500 min-w-0">
      <?php $last = array_key_last($breadcrumbs); ?>
      <?php foreach ($breadcrumbs as $i => $label): ?>
        <?php if ($i !== 0): ?><span class="ms text-[16px] text-ink-400">chevron_right</span><?php endif; ?>
        <span class="<?= $i === $last ? 'text-ink-900 font-semibold' : '' ?> truncate"><?= e($label) ?></span>
      <?php endforeach; ?>
    </nav>

    <div class="flex-1"></div>

    <!-- Chips status -->
    <div class="hidden sm:flex items-center gap-2">
      <span class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-canvas border border-line text-[13px] text-ink-700">
        <span class="ms text-[17px] text-primary">calendar_month</span>
        <strong class="font-semibold"><?= e(APP_TAHUN) ?></strong>
        <span class="text-ink-400">Genap</span>
      </span>

      <span class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-[#ECFDF5] border border-[#A7F3D0] text-[13px] text-[#065F46]">
        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
        <strong class="font-semibold">WA Gateway Online</strong>
        <span class="hidden xl:inline text-[#065F46]/70">99.4% Terkirim</span>
      </span>
    </div>

    <!-- Aksi halaman -->
    <div class="flex items-center gap-2"><?= $pageActions ?></div>

    <!-- Ikon -->
    <!-- Tombol dark mode / light mode — klik untuk ganti tema, disimpan di localStorage -->
    <button onclick="toggleTema()" class="icon-box border border-line text-ink-500 hover:text-primary" aria-label="Ganti tema gelap/terang" title="Ganti tema gelap/terang">
      <span class="ms" id="ikon-tema">dark_mode</span>
    </button>
    <button class="relative icon-box border border-line text-ink-500 hover:text-primary" data-demo="Tahap UI — notifikasi belum tersedia." aria-label="Notifikasi">
      <?= icon('notifications') ?>
      <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-red-500 ring-2 ring-white"></span>
    </button>
    <button class="hidden sm:grid icon-box border border-line text-ink-500 hover:text-primary" data-demo="Tahap UI — pusat bantuan belum tersedia." aria-label="Bantuan">
      <?= icon('help') ?>
    </button>

    <div class="hidden xl:flex items-center gap-2.5 pl-3 border-l border-line">
      <span class="w-9 h-9 rounded-lg bg-primary text-white grid place-items-center text-xs font-bold"><?= e($USER_LOGIN['inisial']) ?></span>
      <span class="leading-tight">
        <span class="block text-[13px] font-semibold text-ink-900"><?= e($USER_LOGIN['nama']) ?></span>
        <span class="block text-[11px] text-ink-500"><?= e($USER_LOGIN['jabatan']) ?></span>
      </span>
    </div>
  </div>
</header>
<script>
/* Fungsi toggle dark/light mode — dipanggil dari tombol di topbar & landing. */
function toggleTema() {
  var html = document.documentElement;
  html.classList.toggle('gelap');
  var gelap = html.classList.contains('gelap');
  localStorage.setItem('tema', gelap ? 'gelap' : 'terang');
  var ikon = document.getElementById('ikon-tema');
  if (ikon) ikon.textContent = gelap ? 'light_mode' : 'dark_mode';
}
/* Sinkronkan ikon saat halaman dimuat. */
(function () {
  var ikon = document.getElementById('ikon-tema');
  if (ikon && document.documentElement.classList.contains('gelap')) {
    ikon.textContent = 'light_mode';
  }
})();
</script>
