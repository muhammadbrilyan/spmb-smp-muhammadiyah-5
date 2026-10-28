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

/* [PERBAIKAN] $notifBadge tidak lagi dihitung di sini — dulu ia dipakai oleh
 * badge menu "Notifikasi" di sidebar, dan menu itu kini sudah dihapus untuk
 * operator (aksesnya lewat ikon notifikasi di topbar). Perhitungannya dipindah
 * ke bawah, tepat setelah $navGrup terbentuk, sehingga HANYA dijalankan bila
 * role itu benar-benar punya menu notifikasi (bendahara/kepsek).
 * Polling pembaruan jumlahnya juga pindah ke topbar. */

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
    /* [PERBAIKAN] Sidebar semula 18 menu sehingga terkesan padat. Empat menu
     * dihapus karena AKSESNYA TETAP ADA di tempat lain (tidak ada halaman yang
     * jadi tak terjangkau):
     *   1. Notifikasi          -> ikon notifikasi di topbar (dengan badge jumlah)
     *   2. Panduan & SOP       -> ikon bantuan "?" di topbar
     *   3. Pendaftaran Manual  -> tombol CTA tepat di atas daftar menu ini
     *   4. Ganti Password      -> link di bagian bawah sidebar
     * Hasil: 18 -> 14 menu dalam 4 grup; kartu profil & link bantuan di footer
     * ikut dihapus agar sidebar lebih ringkas (keduanya sudah ada di topbar). */
    $navGrup = [
        [
            'judul' => 'Operasional SPMB',
            'menu'  => [
                $menuItem('dashboard', 'dashboard', 'Dashboard Operasional', url('app/operator/index.php')),
                $menuItem('pendaftar', 'group', 'Data Pendaftar', url('app/operator/pendaftar.php')),
                $menuItem('berkas', 'folder_shared', 'Verifikasi Berkas', url('app/operator/berkas.php')),
                $menuItem('verifikasi-data', 'fact_check', 'Verifikasi Data Siswa', url('app/operator/verifikasi-data.php')),
            ],
        ],
        [
            'judul' => 'Seleksi & Penerimaan',
            'menu'  => [
                $menuItem('jadwal', 'event', 'Jadwal Ujian', url('app/operator/jadwal.php')),
                $menuItem('peserta', 'event_seat', 'Peserta Ujian', url('app/operator/peserta-ujian.php')),
                $menuItem('seleksi', 'assignment', 'Data & Status Tes', url('app/operator/seleksi.php')),
                $menuItem('pengumuman', 'campaign', 'Pengumuman Hasil', url('app/operator/pengumuman.php')),
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
                $menuItem('pengaturan', 'settings', 'Pengaturan Sistem', url('app/operator/pengaturan.php')),
                $menuItem('petugas', 'admin_panel_settings', 'Petugas & Hak Akses', url('app/operator/petugas.php')),
                $menuItem('jenis-dokumen', 'description', 'Jenis Dokumen', url('app/operator/jenis-dokumen.php')),
                $menuItem('retensi', 'inventory_2', 'Retensi Data', url('app/operator/retensi.php')),
                $menuItem('kesiapan', 'task_alt', 'Kesiapan Rilis', url('app/operator/kesiapan.php')),
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

/* [PERBAIKAN] Hitung jumlah notifikasi BELUM DIBACA hanya bila role itu punya
 * menu Notifikasi di sidebar (bendahara/kepsek/admin). Untuk operator, menu
 * itu sudah dihapus — aksesnya lewat ikon notifikasi di topbar, jadi query ini
 * tidak perlu dijalankan di sini. */
$notifBadge = 0;
foreach ($navGrup as $gNav) {
    foreach ($gNav['menu'] as $mNav) {
        if ($mNav['key'] === 'notifikasi') {
            $notifBadge = notif_belum_dibaca();
            break 2;
        }
    }
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
    <?php foreach ($navGrup as $gi => $grup):
      /* [PERBAIKAN] Grup dibuat bisa dilipat agar sidebar tidak terlihat
       * menumpuk. Dua aturan agar tidak pernah membingungkan:
       *   - grup yang BERISI menu aktif SELALU terbuka (pengguna tahu dirinya
       *     ada di mana), dan
       *   - grup pertama terbuka sebagai pintu masuk.
       * Grup lain mulai tertutup; preferensi lipatan disimpan di localStorage. */
      $adaAktifGrup = false;
      foreach ($grup['menu'] as $mg) {
          if ($mg['key'] === $activeMenu) {
              $adaAktifGrup = true;
              break;
          }
      }
      $bukaAwal = $adaAktifGrup || $gi === 0;
      ?>
      <details class="nav-grup" data-grup="<?= (int) $gi ?>"<?= $bukaAwal ? ' open' : '' ?>>
        <summary>
          <span><?= e($grup['judul']) ?></span>
          <span class="ms text-[16px] nav-grup-tanda" aria-hidden="true">expand_more</span>
        </summary>
        <div class="space-y-1 mt-1">
          <?php foreach ($grup['menu'] as $m): $isActive = $m['key'] === $activeMenu; ?>
            <a href="<?= e($m['href']) ?>"
               class="nav-link <?= $isActive ? 'nav-link-active' : '' ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
              <span class="ms text-[20px] <?= $isActive ? 'text-primary' : 'text-ink-400' ?>"><?= e($m['icon']) ?></span>
              <span class="flex-1"><?= e($m['label']) ?></span>
              <?php if ($m['key'] === 'notifikasi'): ?>
                <?php /* Penanda .notif-badge-count dipakai bersama oleh polling di
                        topbar — elemennya tetap dirender walau tersembunyi, agar
                        pembaruan jumlah selalu menemukannya. */ ?>
                <span class="notif-badge-count badge h-[19px] min-w-[19px] px-1.5 justify-center text-[10px] <?= $notifBadge > 0 ? 'bg-red-600 text-white' : (($isActive ? 'badge-blue' : 'badge-gray') . ' hidden') ?>"><?= $notifBadge > 0 ? ($notifBadge > 99 ? '99+' : (int) $notifBadge) : '' ?></span>
              <?php elseif ($m['badge']): ?>
                <span class="badge h-[19px] px-2 text-[10px] <?= $isActive ? 'badge-blue' : 'badge-gray' ?>"><?= e($m['badge']) ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </nav>

  <!-- [PERBAIKAN] Blok "Bantuan & Panduan" dihapus — sudah digantikan ikon "?"
       di topbar (ke kanan atas) yang untuk semua peran sudah menunjuk ke
       halaman yang benar: siswa ke Info SPMB, staf ke Panduan & SOP. -->

  <!-- Aksi akun -->
  <!-- [PERBAIKAN] Kartu profil (avatar + nama + jabatan) dihapus — informasi
       yang sama sudah tampil di topbar, dan kartu itu hanya memakan tinggi
       sidebar. Yang tersisa: dua aksi yang benar-benar dipakai. -->
  <div class="px-4 pb-4 pt-3 border-t border-line">
    <a href="<?= url('app/staf/ganti-password.php') ?>" class="nav-link">
      <span class="ms text-[20px] text-ink-400">lock</span> Ganti Password
    </a>
    <form method="post" action="<?= url('app/auth/logout.php') ?>">
      <?= csrf_field() ?>
      <button type="submit" class="nav-link w-full text-red-600 hover:text-red-700 hover:bg-red-50">
        <span class="ms text-[20px]">logout</span> Keluar
      </button>
    </form>
  </div>
</aside>

<div id="sidebar-overlay" class="fixed inset-0 bg-ink-900/40 z-40 hidden lg:hidden"></div>

<script>
/* Simpan & pulihkan status lipatan grup sidebar (per grup, per pengguna).
 * Grup yang berisi menu aktif SELALU terbuka — tidak ikut disimpan, agar
 * pengguna tak pernah kehilangan petunjuk "saya ada di halaman mana". */
(function () {
  var grup = document.querySelectorAll('.nav-grup');
  if (!grup.length) return;
  Array.prototype.forEach.call(grup, function (d) {
    var adaAktif = d.querySelector('.nav-link-active');
    if (adaAktif) { d.open = true; return; }

    var kunci = 'nav-grup-' + (d.getAttribute('data-grup') || '0');
    try {
      var s = localStorage.getItem(kunci);
      if (s !== null) d.open = (s === '1');
    } catch (e) { /* storage diblokir — pakai nilai default */ }

    d.addEventListener('toggle', function () {
      try { localStorage.setItem(kunci, d.open ? '1' : '0'); } catch (e) {}
    });
  });
})();
</script>

