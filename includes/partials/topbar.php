<?php
/**
 * Topbar dashboard.
 * Variabel: $breadcrumbs (array label => href|null), $pageActions (HTML opsional)
 */
$breadcrumbs = $breadcrumbs ?? ['Portal Akademik' => null, 'SPMB' => null, 'Operasional Operator SPMB' => null];
$pageActions = $pageActions ?? '';
?>
<header class="no-print sticky top-0 z-30 bg-white/95 backdrop-blur border-b border-line lg:pl-[264px]">
  <div class="px-4 sm:px-6 lg:px-8 h-16 flex items-center gap-3">

    <button class="lg:hidden icon-box border border-line text-ink-700 shrink-0" data-sidebar-open aria-label="Buka menu">
      <?= icon('menu') ?>
    </button>

    <!-- Lipat/bentangkan sidebar (desktop); status disimpan di localStorage -->
    <button type="button" class="hidden lg:grid icon-box border border-line text-ink-700 shrink-0"
            data-sidebar-toggle aria-label="Lipat atau bentangkan menu" title="Lipat atau bentangkan menu">
      <span class="ms" id="ikon-sidebar">left_panel_close</span>
    </button>

    <!-- Breadcrumb -->
    <?php
    /* Halaman memakai dua konvensi: daftar ['A','B'] atau peta ['A' => href|null].
     * Pada peta, LABEL-nya adalah kunci; nilai hanya dipakai sebagai tautan bila
     * benar-benar URL (sebelumnya nilai null dicetak sebagai label sehingga semua
     * breadcrumb halaman berpeta tampil kosong). */
    $crumbs = [];
    foreach ($breadcrumbs as $key => $val) {
        $label = trim((string) (is_int($key) ? $val : $key));
        if ($label === '') {
            continue;
        }
        $href  = (is_string($val) && (str_starts_with($val, 'http') || str_starts_with($val, '/'))) ? $val : null;
        $crumbs[] = ['label' => $label, 'href' => $href];
    }
    $crumbLast = count($crumbs) - 1;
    ?>
    <nav class="hidden md:flex items-center gap-1.5 text-[13px] text-ink-500 min-w-0">
      <?php foreach ($crumbs as $i => $c): ?>
        <?php if ($i > 0): ?><span class="ms text-[16px] text-ink-400">chevron_right</span><?php endif; ?>
        <?php if ($c['href'] !== null && $i !== $crumbLast): ?>
          <a href="<?= e($c['href']) ?>" class="truncate hover:text-ink-900"><?= e($c['label']) ?></a>
        <?php else: ?>
          <span class="<?= $i === $crumbLast ? 'text-ink-900 font-semibold' : '' ?> truncate"><?= e($c['label']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>

    <div class="flex-1"></div>

    <!-- Chips status -->
    <?php
    /* [PERBAIKAN] Chip semula hardcode "WA Gateway Online" + "99.4% Terkirim"
     * — selalu hijau berapa pun keadaan sebenarnya (waktu itu token masih
     * kosong, kini device pun terputus). Kini hanya menyatakan hal yang bisa
     * diverifikasi lokal: token terkonfigurasi + tingkat sukses dari wa_log. */
    $waSiap  = fonnte_token() !== null;
    $waTotal = (int) (q_col("SELECT COUNT(*) FROM wa_log WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')") ?? 0);
    $waOk    = (int) (q_col("SELECT COUNT(*) FROM wa_log WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND status = 'TERKIRIM'") ?? 0);
    $waPct   = $waTotal > 0 ? round($waOk / $waTotal * 100) : null;
    ?>
    <div class="hidden sm:flex items-center gap-2">
      <span class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-canvas border border-line text-[13px] text-ink-700">
        <span class="ms text-[17px] text-primary">calendar_month</span>
        <strong class="font-semibold"><?= e(APP_TAHUN) ?></strong>
        <span class="text-ink-400">Genap</span>
      </span>

      <?php if ($waSiap): ?>
        <span class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-[#ECFDF5] border border-[#A7F3D0] text-[13px] text-[#065F46]">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          <strong class="font-semibold">Gateway WA dikonfigurasi</strong>
          <?php if ($waPct !== null): ?>
            <span class="hidden xl:inline text-[#065F46]/70"><?= number_format($waPct) ?>% terkirim bulan ini</span>
          <?php else: ?>
            <span class="hidden xl:inline text-[#065F46]/70">belum ada pesan</span>
          <?php endif; ?>
        </span>
      <?php else: ?>
        <span class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-[#FFFBEB] border border-[#FDE68A] text-[13px] text-[#92400E]">
          <span class="w-2 h-2 rounded-full bg-amber-500"></span>
          <strong class="font-semibold">Gateway WA belum diatur</strong>
        </span>
      <?php endif; ?>
    </div>

    <!-- Aksi halaman -->
    <div class="flex items-center gap-2"><?= $pageActions ?></div>

    <!-- Ikon -->
    <!-- Tombol dark mode / light mode — klik untuk ganti tema, disimpan di localStorage -->
    <button onclick="toggleTema()" class="icon-box border border-line text-ink-500 hover:text-primary" aria-label="Ganti tema gelap/terang" title="Ganti tema gelap/terang">
      <span class="ms" id="ikon-tema">dark_mode</span>
    </button>
    <?php
    /* [PERBAIKAN] Kedua ikon semula bertag data-demo sehingga klik = nihil,
     * dan titik merah notifikasi hardcode (selalu merah walau tidak ada pesan).
     * Kini menjadi tautan nyata; inilah yang menggantikan menu "Notifikasi" &
     * "Panduan" saat dihapus dari sidebar. */
    $roleTop   = user_role();
    $bolehNotf = in_array($roleTop, ['operator', 'bendahara', 'kepsek', 'admin'], true);
    /* Siswa memakai notifikasi per penerima; staf memakai papan operator. */
    if ($roleTop === 'siswa') {
        $pidNotifTop = isset($siswa['id']) ? (string) $siswa['id'] : '';
        if ($pidNotifTop === '') {
            $pidNotifTop = (string) (q_col(
                'SELECT id FROM pendaftar WHERE user_id = ? LIMIT 1',
                [(string) (current_user()['id'] ?? '')]
            ) ?? '');
        }
        $jmlNotf = $pidNotifTop !== '' ? notif_siswa_belum_dibaca($pidNotifTop) : 0;
    } else {
        $jmlNotf = $bolehNotf ? notif_belum_dibaca() : 0;
    }
    $keNotf = $roleTop === 'siswa'
        ? url('app/siswa/notifikasi.php')
        : ($bolehNotf ? url('app/operator/notifikasi.php') : url('app/siswa/notifikasi.php'));
    $keBantuan = $roleTop === 'siswa'
        ? url('app/spmb/index.php')
        : url('app/operator/panduan.php');
    ?>
    <a href="<?= e($keNotf) ?>" class="relative icon-box border border-line text-ink-500 hover:text-primary" aria-label="Notifikasi<?= $jmlNotf > 0 ? ' (' . (int) $jmlNotf . ' belum dibaca)' : '' ?>" title="<?= $jmlNotf > 0 ? (int) $jmlNotf . ' notifikasi belum dibaca' : 'Notifikasi' ?>">
      <?= icon('notifications') ?>
      <?php /* [PERBAIKAN] Badge selalu dirender (walau tersembunyi) dan memakai
              penanda .notif-badge-count — dipakai bersama oleh badge sidebar di
              halaman bendahara/kepsek. Sebelumnya id="notif-badge" ada di
              sidebar dan polling ikut pindah ke sini saat menu Notifikasi
              dihapus dari sidebar operator. */ ?>
      <span class="notif-badge-count absolute -top-1.5 -right-1.5 min-w-[17px] h-[17px] px-1 rounded-full text-[10px] font-bold grid place-items-center ring-2 ring-white <?= $jmlNotf > 0 ? 'bg-red-600 text-white' : 'hidden' ?>"><?= $jmlNotf > 0 ? ($jmlNotf > 99 ? '99+' : (int) $jmlNotf) : '' ?></span>
    </a>
    <a href="<?= e($keBantuan) ?>" class="hidden sm:grid icon-box border border-line text-ink-500 hover:text-primary" aria-label="Bantuan & Panduan" title="Bantuan & Panduan">
      <?= icon('help') ?>
    </a>

    <?php if ($bolehNotf || $roleTop === 'siswa'): ?>
    <script>
    /* Polling ringan jumlah notifikasi belum dibaca — tiap 60 detik, tanpa library.
     * Menyasar SEMUA elemen .notif-badge-count (topbar + sidebar bendahara/kepsek)
     * sehingga tidak ada lagi badge yang membeku di angka lama. */
    (function () {
      var endpoint = <?= json_encode(url('app/api/notif-count.php')) ?>;
      function perbarui() {
        fetch(endpoint, { credentials: 'same-origin' })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (j) {
            if (!j || !j.ok) return;
            var ada = j.jumlah > 0;
            var teks = j.jumlah > 99 ? '99+' : String(j.jumlah);
            Array.prototype.forEach.call(document.querySelectorAll('.notif-badge-count'), function (el) {
              if (ada) {
                el.textContent = teks;
                el.classList.remove('hidden', 'badge-blue', 'badge-gray');
                el.classList.add('bg-red-600', 'text-white');
              } else {
                el.textContent = '';
                el.classList.add('hidden');
                el.classList.remove('bg-red-600', 'text-white');
              }
            });
          })
          .catch(function () { /* jaringan mati — abaikan */ });
      }
      setInterval(perbarui, 60000);
    })();
    </script>
    <?php endif; ?>

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
