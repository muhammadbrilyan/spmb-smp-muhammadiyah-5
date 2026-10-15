<?php
/**
 * PARTIAL <head> — dipanggil di semua halaman via require head.php.
 * Menghasilkan tag <head> + CSS + meta tags.
 *
 * YANG SERING DIUBAH:
 *   - $title, $desc  → dari halaman pemanggil (bukan di sini)
 *   - $warnaPrima    → warna tema dari CMS (Pengaturan → Warna Tema)
 *   - favicon        → logo sekolah dari assets/img/uploads/logo-sekolah.png
 *   - Dark mode      → toggle di topbar, disimpan di localStorage
 *
 * Variabel dari halaman pemanggil: $title, $desc, $bodyClass
 */
$title   = $title   ?? 'Beranda';
$desc    = $desc    ?? 'Sistem Informasi SPMB & CMS SMP Muhammadiyah 5 Kota Tangerang.';
$bodyClass = $bodyClass ?? 'bg-canvas text-ink-700 antialiased';

/* Warna tema dari CMS (Pengaturan → Warna Tema). Ubah di CMS, bukan di sini. */
$warnaPrima    = warna_valid($PENGATURAN['warna_utama'] ?? '') ? $PENGATURAN['warna_utama'] : '#0056B3';
$warnaDark     = warna_campur($warnaPrima, '#000000', 0.18);
$warnaDarker   = warna_campur($warnaPrima, '#000000', 0.30);
$warnaLight    = warna_campur($warnaPrima, '#FFFFFF', 0.90);
/* Teks kontras otomatis: putih untuk latar gelap, gelap untuk latar terang. */
$teksKontras   = warna_kontras($warnaPrima);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="<?= e($PENGATURAN['warna_utama']) ?>">
<title><?= e($title) ?> · <?= e(APP_SHORT) ?></title>
<link rel="icon" href="<?= asset('assets/img/uploads/logo-sekolah.png') ?>" type="image/png">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block" rel="stylesheet">

<!-- Tailwind CSS lokal (tanpa CDN) — warna utama via CSS variable agar bisa diganti dari CMS -->
<link rel="stylesheet" href="<?= asset('assets/css/tailwind.css') ?>">
<script>
/* Dark mode: baca preferensi dari localStorage SEBELUM render agar tidak flash. */
(function () {
  var t = localStorage.getItem('tema');
  if (t === 'gelap') document.documentElement.classList.add('gelap');
})();
</script>
<style>
  :root {
    --color-primary: <?= e($warnaPrima) ?>;
    --color-primary-dark: <?= e($warnaDark) ?>;
    --color-primary-darker: <?= e($warnaDarker) ?>;
    --color-primary-light: <?= e($warnaLight) ?>;
    --color-primary-contrast: <?= e($teksKontras) ?>;
  }
  /* Override warna primary dari Tailwind agar mengikuti CMS */
  .bg-primary { background-color: var(--color-primary) !important; }
  .bg-primary-dark { background-color: var(--color-primary-dark) !important; }
  .bg-primary-darker { background-color: var(--color-primary-darker) !important; }
  .bg-primary-light { background-color: var(--color-primary-light) !important; }
  .text-primary { color: var(--color-primary) !important; }
  .border-primary { border-color: var(--color-primary) !important; }
  .ring-primary { --tw-ring-color: var(--color-primary) !important; }
  .hover\:bg-primary:hover { background-color: var(--color-primary) !important; }
  .hover\:bg-primary-dark:hover { background-color: var(--color-primary-dark) !important; }
  .active\:bg-primary-darker:active { background-color: var(--color-primary-darker) !important; }
  .focus\:border-primary:focus { border-color: var(--color-primary) !important; }
  .focus\:ring-primary:focus { --tw-ring-color: var(--color-primary) !important; }
  .bg-primary\/15 { background-color: color-mix(in srgb, var(--color-primary) 15%, transparent) !important; }
  .focus\:ring-primary\/15:focus { --tw-ring-color: color-mix(in srgb, var(--color-primary) 15%, transparent) !important; }
  /* Teks di atas background primary otomatis kontras */
  .text-on-primary { color: var(--color-primary-contrast) !important; }
  .btn-primary, .btn-primary * { color: var(--color-primary-contrast); }

  /* ===== DARK MODE ===== */
  html.gelap {
    color-scheme: dark;
  }
  html.gelap body {
    background-color: #0F172A !important;
    color: #E2E8F0 !important;
  }
  html.gelap .bg-white { background-color: #1E293B !important; }
  html.gelap .bg-canvas { background-color: #0F172A !important; }
  html.gelap .text-ink-900,
  html.gelap .text-ink-700 { color: #F1F5F9 !important; }
  html.gelap .text-ink-500,
  html.gelap .text-ink-400 { color: #94A3B8 !important; }
  html.gelap .border-line,
  html.gelap .border-line-mid { border-color: #334155 !important; }
  html.gelap .bg-canvas,
  html.gelap .bg-gray-50 { background-color: #1E293B !important; }
  html.gelap .card { background-color: #1E293B !important; border-color: #334155 !important; }
  html.gelap .input,
  html.gelap .select,
  html.gelap .textarea {
    background-color: #0F172A !important;
    border-color: #475569 !important;
    color: #F1F5F9 !important;
  }
  html.gelap .input::placeholder,
  html.gelap .textarea::placeholder { color: #64748B !important; }
  html.gelap table { color: #E2E8F0 !important; }
  html.gelap thead { background-color: #1E293B !important; }
  html.gelap tbody tr { border-color: #334155 !important; }
  html.gelap tbody tr:hover { background-color: #1E293B !important; }
  html.gelap .badge-gray,
  html.gelap .badge { background-color: #1E293B !important; border-color: #475569 !important; color: #94A3B8 !important; }
  html.gelap aside,
  html.gelap #sidebar { background-color: #1E293B !important; border-color: #334155 !important; }
  html.gelap header { background-color: rgba(15,23,42,.95) !important; border-color: #334155 !important; }
  html.gelap footer { background-color: #1E293B !important; border-color: #334155 !important; }
  html.gelap .hover\:bg-canvas:hover { background-color: #334155 !important; }
  html.gelap .hover\:bg-gray-100:hover { background-color: #334155 !important; }
  html.gelap .bg-ink-900 { background-color: #020617 !important; }
  html.gelap .ticker-wrap { background-color: #020617 !important; }
  /* Tombol dark mode */
  html.gelap .icon-box { border-color: #475569 !important; color: #CBD5E1 !important; }
</style>

<link rel="stylesheet" href="<?= asset('assets/css/app.css') ?>">
</head>
<body class="<?= e($bodyClass) ?>">
