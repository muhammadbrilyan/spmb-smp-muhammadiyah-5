<?php
/**
 * SIDEBAR PORTAL — menu dipisah per peran.
 *
 * - Siswa  : portal ringkas (Dashboard, Data Siswa, Keuangan, PPDB & Seleksi,
 *            Akademik, Pengumuman, Pengaturan Akun).
 * - Operator: operasional SPMB (pendaftar, verifikasi, jadwal, tes, seleksi,
 *            penempatan, pengumuman, pengaturan).
 * - Bendahara/Kepsek: menu monitoring yang sudah ada tetap dipertahankan.
 *
 * Variabel dari halaman pemanggil: $activeMenu (menu aktif), $badgeRole (opsional).
 */
$activeMenu = $activeMenu ?? 'dashboard';
$role       = user_role();
$badgeRole  = $badgeRole ?? strtoupper((string) $role);

$boleh_notif = in_array($role, ['operator', 'bendahara', 'kepsek', 'admin'], true);
$notifBadge  = $boleh_notif ? notif_belum_dibaca() : 0;

/** Helper satu baris menu. */
$menuItem = function ($key, $icon, $label, $href, $badge = '') {
    return ['key' => $key, 'icon' => $icon, 'label' => $label, 'href' => $href, 'badge' => $badge];
};

/* ==================================================================
 * DEFINISI MENU PER PERAN
 * ================================================================== */
$navGrup = [];

if ($role === 'siswa') {
    $navGrup = [
        [
            'judul' => 'Portal Saya',
            'menu'  => [
                $menuItem('dashboard', 'dashboard', 'Dashboard', url('app/siswa/index.php')),
                $menuItem('siswa', 'badge', 'Data Siswa', url('app/siswa/data-siswa.php')),
                $menuItem('keuangan', 'account_balance_wallet', 'Keuangan', url('app/siswa/keuangan.php')),
                $menuItem('ppdb', 'how_to_reg', 'PPDB & Seleksi', url('app/siswa/ppdb.php')),
                $menuItem('akademik', 'menu_book', 'Akademik', url('app/siswa/akademik.php')),
                $menuItem('pengumuman', 'campaign', 'Pengumuman', url('app/siswa/pengumuman.php')),
            ],
        ],
        [
            'judul' => 'Akun',
            'menu'  => [
                $menuItem('akun', 'manage_accounts', 'Pengaturan Akun', url('app/siswa/akun.php')),
            ],
        ],
    ];
} elseif ($role === 'operator') {
    $navGrup = [
        [
            'judul' => 'Operasional SPMB',
            'menu'  => [
                $menuItem('dashboard', 'dashboard', 'Dashboard Operasional', url('app/operator/index.php')),
                $menuItem('pendaftar', 'group', 'Data Pendaftar', url('app/operator/pendaftar.php')),
                $menuItem('berkas', 'folder_shared', 'Verifikasi Kelengkapan Formulir', url('app/operator/berkas.php')),
                $menuItem('verifikasi-data', 'fact_check', 'Verifikasi Data Calon Siswa', url('app/operator/verifikasi-data.php')),
                $menuItem('notifikasi', 'notifications', 'Notifikasi', url('app/operator/notifikasi.php')),
            ],
        ],
        [
            'judul' => 'Seleksi & Penerimaan',
            'menu'  => [
                $menuItem('jadwal', 'event', 'Jadwal Ujian', url('app/operator/jadwal.php')),
                $menuItem('peserta', 'event_seat', 'Peserta Ujian', url('app/operator/peserta-ujian.php')),
                $menuItem('seleksi', 'assignment', 'Data & Status Tes', url('app/operator/seleksi.php')),
                $menuItem('pengumuman', 'campaign', 'Pengumuman & Status Penerimaan', url('app/operator/pengumuman.php')),
            ],
        ],
        [
            'judul' => 'Pasca Penerimaan',
            'menu'  => [
                $menuItem('penempatan', 'move_to_inbox', 'Penempatan Kelas', url('app/operator/penempatan.php')),
                $menuItem('kelas', 'class', 'Manajemen Kelas', url('app/operator/kelas.php')),
            ],
        ],
        [
            'judul' => 'Pengaturan Sistem',
            'menu'  => [
                $menuItem('pendaftaran', 'person_add', 'Pendaftaran Manual', url('app/operator/pendaftar-baru.php')),
                $menuItem('jenis-dokumen', 'description', 'Jenis Dokumen', url('app/operator/jenis-dokumen.php')),
                $menuItem('pengaturan', 'settings', 'Pengaturan Sistem', url('app/operator/pengaturan.php')),
                $menuItem('retensi', 'inventory_2', 'Retensi Data', url('app/operator/retensi.php')),
                $menuItem('kesiapan', 'task_alt', 'Kesiapan Rilis', url('app/operator/kesiapan.php')),
                $menuItem('panduan', 'support_agent', 'Panduan & SOP', url('app/operator/panduan.php')),
                $menuItem('ganti-pass', 'lock', 'Ganti Password', url('app/staf/ganti-password.php')),
            ],
        ],
    ];
} elseif ($role === 'bendahara') {
    $navGrup = [
        [
            'judul' => 'Keuangan SPMB',
            'menu'  => [
                $menuItem('keuangan', 'dashboard', 'Dashboard & Verifikasi Pembayaran', url('app/bendahara/index.php')),
                $menuItem('notifikasi', 'notifications', 'Notifikasi', url('app/operator/notifikasi.php')),
                $menuItem('panduan', 'support_agent', 'Panduan & SOP', url('app/operator/panduan.php')),
                $menuItem('ganti-pass', 'lock', 'Ganti Password', url('app/staf/ganti-password.php')),
            ],
        ],
    ];
} elseif ($role === 'kepsek') {
    $navGrup = [
        [
            'judul' => 'Monitoring',
            'menu'  => [
                $menuItem('dashboard', 'dashboard', 'Dashboard Kepala Sekolah', url('app/kepsek/index.php')),
                $menuItem('notifikasi', 'notifications', 'Notifikasi', url('app/operator/notifikasi.php')),
                $menuItem('panduan', 'support_agent', 'Panduan & SOP', url('app/operator/panduan.php')),
                $menuItem('ganti-pass', 'lock', 'Ganti Password', url('app/staf/ganti-password.php')),
            ],
        ],
    ];
} else {
    $navGrup = [[
        'judul' => 'Portal',
        'menu'  => [
            $menuItem('dashboard', 'dashboard', 'Dashboard', role_home($role)),
        ],
    ]];
}
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

  <?php if ($role === 'operator'): ?>
    <div class="px-4 pt-4">
      <a href="<?= url('app/operator/pendaftar-baru.php') ?>" class="btn btn-primary btn-block">
        <?= icon('add_circle') ?> Pendaftaran Manual
      </a>
    </div>
  <?php elseif ($role === 'siswa'): ?>
    <div class="px-4 pt-4">
      <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline btn-block" target="_blank" rel="noopener">
        <?= icon('language') ?> Info SPMB
      </a>
    </div>
  <?php endif; ?>

  <!-- Navigasi berkelompok -->
  <nav class="flex-1 overflow-y-auto scroll-thin px-4 py-4 space-y-4">
    <?php foreach ($navGrup as $grup): ?>
      <div>
        <p class="px-2 pb-1.5 text-[10px] font-semibold uppercase tracking-wider text-ink-400"><?= e($grup['judul']) ?></p>
        <div class="space-y-1">
          <?php foreach ($grup['menu'] as $m): $isActive = $m['key'] === $activeMenu; ?>
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
        </div>
      </div>
    <?php endforeach; ?>
  </nav>

  <!-- Bantuan -->
  <div class="px-4 pb-3">
    <?php if ($role === 'siswa'): ?>
      <a href="<?= url('app/spmb/index.php') ?>" class="nav-link" target="_blank" rel="noopener">
        <span class="ms text-[20px] text-ink-400">help</span> Bantuan &amp; Info SPMB
      </a>
    <?php else: ?>
      <a href="<?= url('app/operator/panduan.php') ?>" class="nav-link">
        <span class="ms text-[20px] text-ink-400">help</span> Bantuan &amp; Panduan
      </a>
    <?php endif; ?>
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
