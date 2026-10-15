<?php
/**
 * SIDEBAR DASHBOARD — tampil di semua halaman portal (dashboard, operator, dll).
 *
 * YANG SERING DIUBAH:
 *   - $menu  → daftar menu sidebar. Tambah/hapus baris untuk ubah menu.
 *     Format: ['key'=>..., 'icon'=>..., 'label'=>TAMPILAN, 'href'=>HALAMAN, 'badge'=>TEKS_KECIL]
 *   - $boleh_xxx → aturan role mana yang boleh lihat menu tertentu.
 *
 * Variabel dari halaman pemanggil: $activeMenu (menu aktif), $badgeRole (opsional)
 */
$activeMenu = $activeMenu ?? 'dashboard';
$role       = user_role();
$badgeRole  = $badgeRole ?? strtoupper((string) $role);

/* Aturan hak akses per menu — ubah di sini jika ingin ganti role yang boleh akses. */
$boleh_ppdb      = in_array($role, ['operator', 'admin'], true);
$boleh_keuangan  = in_array($role, ['bendahara', 'operator'], true);
$boleh_pengaturan = in_array($role, ['operator', 'admin'], true);
$boleh_siswa     = $role === 'siswa';
$boleh_kelas     = $boleh_ppdb;
$boleh_staf      = in_array($role, ['operator', 'bendahara', 'kepsek', 'admin'], true);
$boleh_notif     = in_array($role, ['operator', 'bendahara', 'kepsek', 'admin'], true);

/* Jumlah notifikasi belum dibaca (Fase 3) — hanya untuk staf. */
$notifBadge = $boleh_notif ? notif_belum_dibaca() : 0;

/* DAFTAR MENU SIDEBAR — tambah/hapus baris di sini untuk ubah menu. */
$menu = [
    ['key' => 'dashboard',  'icon' => 'dashboard',        'label' => 'Dashboard',          'href' => role_home($role), 'badge' => ''],
    ['key' => 'ppdb',       'icon' => 'how_to_reg',       'label' => 'SPMB',                'href' => $boleh_ppdb ? url('app/operator/index.php') : '#', 'badge' => $boleh_ppdb ? $badgeRole : ''],
    ['key' => 'notifikasi', 'icon' => 'notifications',    'label' => 'Notifikasi',          'href' => $boleh_notif ? url('app/operator/notifikasi.php') : '#', 'badge' => ''],
    ['key' => 'berkas',     'icon' => 'folder_shared',    'label' => 'Verifikasi Berkas',   'href' => $boleh_ppdb ? url('app/operator/berkas.php') : '#', 'badge' => $boleh_ppdb ? 'F5' : ''],
    ['key' => 'peserta',    'icon' => 'event_seat',       'label' => 'Peserta Ujian',      'href' => $boleh_ppdb ? url('app/operator/peserta-ujian.php') : '#', 'badge' => $boleh_ppdb ? 'F6' : ''],
    ['key' => 'siswa',      'icon' => 'groups',           'label' => 'Data Siswa',         'href' => $boleh_siswa ? url('app/siswa/index.php') : '#', 'badge' => $boleh_siswa ? 'SAYA' : ''],
    ['key' => 'kelas',      'icon' => 'class',            'label' => 'Manajemen Kelas',    'href' => $boleh_kelas ? url('app/operator/kelas.php') : '#', 'badge' => ''],
    ['key' => 'seleksi',    'icon' => 'assignment',       'label' => 'Input Seleksi',      'href' => $boleh_kelas ? url('app/operator/seleksi.php') : '#', 'badge' => ''],
    ['key' => 'penempatan', 'icon' => 'move_to_inbox',    'label' => 'Penempatan Kelas',   'href' => $boleh_kelas ? url('app/operator/penempatan.php') : '#', 'badge' => $boleh_kelas ? 'AKHIR' : ''],
    ['key' => 'pengumuman', 'icon' => 'campaign',         'label' => 'Pengumuman Hasil',   'href' => $boleh_kelas ? url('app/operator/pengumuman.php') : '#', 'badge' => $boleh_kelas ? 'F8' : ''],
    ['key' => 'pendaftaran','icon' => 'person_add',       'label' => 'Pendaftaran Manual', 'href' => $boleh_ppdb ? url('app/operator/pendaftar-baru.php') : '#', 'badge' => $boleh_ppdb ? 'BARU' : ''],
    ['key' => 'akademik',   'icon' => 'menu_book',        'label' => 'Akademik',           'href' => '#',        'badge' => ''],
    ['key' => 'keuangan',   'icon' => 'account_balance_wallet', 'label' => 'Keuangan & SPP', 'href' => $boleh_keuangan ? url('app/bendahara/index.php') : '#', 'badge' => $role === 'bendahara' ? 'BENDAHARA' : ''],
    ['key' => 'pengaturan', 'icon' => 'settings',         'label' => 'Pengaturan Sistem',  'href' => $boleh_pengaturan ? url('app/operator/pengaturan.php') : '#', 'badge' => $boleh_pengaturan ? 'CMS' : ''],
    ['key' => 'panduan',    'icon' => 'support_agent',    'label' => 'Panduan & SOP',      'href' => url('app/operator/panduan.php'), 'badge' => 'SOP'],
    ['key' => 'ganti-pass', 'icon' => 'lock',             'label' => 'Ganti Password',     'href' => $boleh_staf ? url('app/staf/ganti-password.php') : '#', 'badge' => ''],
];
?>
<aside id="sidebar"
       class="fixed inset-y-0 left-0 z-50 w-[264px] bg-white border-r border-line flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200">

  <!-- Brand -->
  <div class="px-5 py-5 flex items-center justify-between border-b border-line">
    <a href="<?= url('app/spmb/index.php') ?>" class="flex items-center gap-3 min-w-0">
      <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo" class="w-10 h-10 shrink-0">
      <span class="min-w-0 leading-tight">
        <span class="block font-bold text-ink-900 text-[15px] truncate"><?= e(APP_SHORT) ?></span>
        <span class="block text-[11px] text-ink-500"><?= e(APP_TAGLINE) ?></span>
      </span>
    </a>
    <button class="lg:hidden icon-box border border-line text-ink-500" data-sidebar-close aria-label="Tutup menu"><?= icon('close') ?></button>
  </div>

  <div class="px-4 pt-4">
    <?php if ($boleh_ppdb): ?>
      <a href="<?= url('app/operator/pendaftar-baru.php') ?>" class="btn btn-primary btn-block">
        <?= icon('add_circle') ?> Pendaftaran Manual
      </a>
    <?php else: ?>
      <button class="btn btn-primary btn-block" data-demo="Form pendaftaran manual hanya untuk operator/admin.">
        <?= icon('add_circle') ?> Pendaftaran Manual
      </button>
    <?php endif; ?>
  </div>

  <!-- Navigasi -->
  <nav class="flex-1 overflow-y-auto scroll-thin px-4 py-4 space-y-1">
    <?php foreach ($menu as $m): $isActive = $m['key'] === $activeMenu; ?>
      <a href="<?= e($m['href']) ?>"
         class="nav-link <?= $isActive ? 'nav-link-active' : '' ?>"
         <?= $isActive ? 'aria-current="page"' : '' ?>>
        <span class="ms text-[20px] <?= $isActive ? 'text-primary' : 'text-ink-400' ?>"><?= e($m['icon']) ?></span>
        <span class="flex-1"><?= e($m['label']) ?></span>
        <?php if ($m['key'] === 'notifikasi' && $notifBadge > 0): ?>
          <span id="notif-badge" class="badge h-[19px] min-w-[19px] px-1.5 justify-center text-[10px] bg-red-600 text-white"><?= $notifBadge > 99 ? '99+' : (int) $notifBadge ?></span>
        <?php elseif ($m['key'] === 'notifikasi'): ?>
          <span id="notif-badge" class="badge h-[19px] min-w-[19px] px-1.5 justify-center text-[10px] <?= $isActive ? 'badge-blue' : 'badge-gray' ?> hidden"></span>
        <?php elseif ($m['badge']): ?>
          <span class="badge h-[19px] px-2 text-[10px] <?= $isActive ? 'badge-blue' : 'badge-gray' ?>"><?= e($m['badge']) ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <!-- Bantuan -->
  <div class="px-4 pb-3">
    <a href="<?= url('app/operator/panduan.php') ?>" class="nav-link">
      <span class="ms text-[20px] text-ink-400">help</span> Bantuan &amp; Panduan
    </a>
  </div>

  <!-- Profil user -->
  <div class="px-4 pb-4 pt-3 border-t border-line">
    <div class="flex items-center gap-3 rounded-xl bg-canvas border border-line px-3 py-2.5">
      <span class="w-9 h-9 rounded-lg bg-primary text-white grid place-items-center text-xs font-bold shrink-0"><?= e($USER_LOGIN['inisial']) ?></span>
      <div class="min-w-0 flex-1 leading-tight">
        <div class="text-[13px] font-semibold text-ink-900 truncate"><?= e($USER_LOGIN['nama']) ?></div>
        <div class="text-[11px] text-ink-500"><?= e($USER_LOGIN['jabatan']) ?></div>
      </div>
      <form method="post" action="<?= url('app/auth/logout.php') ?>" class="shrink-0">
        <?= csrf_field() ?>
        <button type="submit" class="text-ink-400 hover:text-red-600" title="Keluar"><?= icon('logout', 'text-[19px]') ?></button>
      </form>
    </div>
    <form method="post" action="<?= url('app/auth/logout.php') ?>">
      <?= csrf_field() ?>
      <button type="submit" class="mt-2 flex items-center gap-2 px-1 text-sm text-red-600 hover:underline">
        <?= icon('logout', 'text-[18px]') ?> Keluar
      </button>
    </form>
  </div>
</aside>

<div id="sidebar-overlay" class="fixed inset-0 bg-ink-900/40 z-40 hidden lg:hidden"></div>

<?php if ($boleh_notif): ?>
<script>
/* Polling ringan jumlah notifikasi belum dibaca — tiap 60 detik, tanpa library. */
(function () {
  var endpoint = <?= json_encode(url('app/api/notif-count.php')) ?>;
  function perbarui() {
    fetch(endpoint, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        if (!j || !j.ok) return;
        var el = document.getElementById('notif-badge');
        if (!el) return;
        if (j.jumlah > 0) {
          el.textContent = j.jumlah > 99 ? '99+' : String(j.jumlah);
          el.classList.remove('hidden', 'badge-blue', 'badge-gray');
          el.classList.add('bg-red-600', 'text-white');
        } else {
          el.classList.add('hidden');
          el.classList.remove('bg-red-600', 'text-white');
        }
      })
      .catch(function () { /* jaringan mati — abaikan */ });
  }
  setInterval(perbarui, 60000);
})();
</script>
<?php endif; ?>
