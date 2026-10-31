<?php
/* ==================================================================
 * LANDING PAGE (BERANDA PUBLIK)
 * Halaman pertama yang dilihat calon siswa/orang tua.
 *
 * YANG SERING DIUBAH:
 *   - $nav           → menu navigasi atas (label & anchor)
 *   - Ticker         → teks berjalan di paling atas
 *   - Hero section   → judul, deskripsi, tombol CTA, statistik
 *   - Section alur   → langkah pendaftaran
 *   - Section biaya  → rincian biaya
 *   - Section jadwal → jadwal gelombang (otomatis dari CMS)
 *   - Section FAQ    → tanya jawab
 *   - Footer         → via includes/partials/footer-public.php
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';

$title = 'SPMB ' . $PENGATURAN['tahun_ajaran'];
$desc  = 'Sistem Penerimaan Murid Baru (SPMB) SMP Muhammadiyah 5 Kota Tangerang — pendaftaran online, pembayaran formulir, tes CBT & BTQ, hingga pengumuman hasil seleksi.';

require_once __DIR__ . '/../../includes/partials/head.php';

/* [PERBAIKAN] Statistik hero kini dihitung dari database. Semula angka karangan
 * dari dummy.php ("345+ pendaftar", "98% lolos", "15+ prestasi"). */
$stDaftar = (int) (q_col('SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NULL') ?? 0);
$stKuota  = (int) (q_col('SELECT COALESCE(SUM(kuota),0) FROM kelas WHERE is_aktif = 1') ?? 0);
$stTerisi = (int) (q_col('SELECT COUNT(*) FROM penempatan_kelas') ?? 0);
$stRombel = (int) (q_col('SELECT COUNT(*) FROM kelas WHERE is_aktif = 1') ?? 0);
$STATISTIK = [
    ['angka' => number_format($stDaftar, 0, ',', '.'),                'label' => 'Pendaftar saat ini', 'icon' => 'groups',       'tone' => 'blue'],
    ['angka' => number_format(max(0, $stKuota - $stTerisi), 0, ',', '.'), 'label' => 'Kursi masih tersedia', 'icon' => 'event_seat',   'tone' => 'green'],
    ['angka' => number_format($stRombel, 0, ',', '.'),                'label' => 'Rombel kelas VII',   'icon' => 'meeting_room', 'tone' => 'amber'],
];

/* MENU NAVIGASI ATAS — tambah/hapus baris untuk ubah menu.
 * '#beranda' = anchor section di halaman ini sendiri. */
$nav = [
    '#beranda'   => 'Beranda',
    '#alur'      => 'Syarat & Alur',
    '#biaya'     => 'Rincian Biaya',
    '#jadwal'    => 'Jadwal & Agenda',
    '#faq'       => 'FAQ',
];
?>

<!-- ============================ TICKER ============================ -->
<div class="bg-ink-900 text-white text-xs ticker-wrap overflow-hidden">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center gap-3">
    <span class="badge badge-green shrink-0 hidden sm:inline-flex"><?= dot('green') ?> Live Update</span>
    <div class="overflow-hidden flex-1">
      <div class="ticker-track whitespace-nowrap">
        <span class="px-6 text-slate-300"><?= e($PENGATURAN['ticker']) ?></span>
        <span class="px-6 text-slate-300"><?= e($PENGATURAN['ticker']) ?></span>
      </div>
    </div>
  </div>
</div>

<!-- ============================ HEADER ============================ -->
<header id="beranda" class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-line">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="h-16 flex items-center justify-between gap-4">
      <a href="<?= url('app/spmb/index.php') ?>" class="flex items-center gap-3 shrink-0">
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo <?= e(APP_SHORT) ?>" style="width:40px;height:40px;object-fit:contain;" class="w-10 h-10 object-contain">
        <span class="hidden sm:block leading-tight">
          <span class="block font-bold text-ink-900 text-[15px]"><?= e(APP_SHORT) ?></span>
          <span class="block text-[11px] text-ink-500">Kota Tangerang · Berakhlak & Berprestasi</span>
        </span>
      </a>

      <nav class="hidden lg:flex items-center gap-0.5">
        <?php foreach ($nav as $anchor => $label): ?>
          <a href="<?= e($anchor) ?>" class="px-2.5 py-2 rounded-lg text-[13.5px] font-medium text-ink-700 whitespace-nowrap hover:bg-line-soft hover:text-primary transition"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="flex items-center gap-2">
        <a href="<?= url('app/spmb/cek-status.php') ?>" class="hidden md:inline-flex btn btn-ghost text-sm whitespace-nowrap"><?= icon('search') ?> Cek Status</a>
        <a href="<?= url('app/auth/login.php') ?>" class="hidden md:inline-flex btn btn-ghost text-sm whitespace-nowrap"><?= icon('school') ?> Login Siswa</a>
        <details class="relative hidden md:hidden">
          <summary class="btn btn-ghost text-sm list-none cursor-pointer whitespace-nowrap"><?= icon('login') ?> Masuk</summary>
          <div class="absolute right-0 mt-2 w-56 card p-2 z-50 shadow-drop">
            <a href="<?= url('app/auth/login.php') ?>" class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm text-ink-700 hover:bg-canvas"><?= icon('school') ?> Login Siswa</a>
          </div>
        </details>
        <a href="<?= url('app/auth/login.php') ?>" class="md:hidden btn btn-ghost text-sm px-3" aria-label="Login Siswa"><?= icon('login') ?></a>
        <!-- Tombol dark mode / light mode -->
        <button onclick="toggleTema()" class="icon-box border border-line text-ink-700" aria-label="Ganti tema gelap/terang" title="Ganti tema gelap/terang">
          <span class="ms" id="ikon-tema">dark_mode</span>
        </button>
        <button class="lg:hidden icon-box border border-line text-ink-700" data-sidebar-open aria-label="Buka menu"><?= icon('menu') ?></button>
      </div>
    </div>
  </div>
</header>

<!-- Menu mobile -->
<div id="sidebar-overlay" class="fixed inset-0 bg-ink-900/40 z-40 hidden lg:hidden"></div>
<div id="sidebar" class="fixed inset-y-0 left-0 w-72 bg-white z-50 border-r border-line p-5 -translate-x-full transition-transform duration-200 lg:hidden">
  <div class="flex items-center justify-between mb-6">
    <span class="font-bold text-ink-900">Menu Navigasi</span>
    <button class="icon-box border border-line" data-sidebar-close aria-label="Tutup menu"><?= icon('close') ?></button>
  </div>
  <nav class="space-y-1">
    <?php foreach ($nav as $anchor => $label): ?>
      <a href="<?= e($anchor) ?>" class="nav-link" data-sidebar-close><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="mt-6 pt-6 border-t border-line space-y-2">
    <a href="<?= url('app/auth/login.php') ?>" class="btn btn-outline btn-block"><?= icon('school') ?> Login Siswa</a>
  </div>
</div>

<main>
<!-- ============================ HERO ============================ -->
<?php $bannerHero = $PENGATURAN['hero_banner_url'] ?? ''; ?>
<?php if ($bannerHero): ?>
<!-- CSS khusus banner hero — ubah border-radius di sini untuk atur besar curve.
     Nilai saat ini: 2% dari lebar layar (terlihat halus, tidak berlebihan). -->
<style>
  .hero-banner { margin: 0 12px; border-radius: 14px; }
  @media (min-width: 640px)  { .hero-banner { margin: 0 20px; border-radius: 20px; } }
  @media (min-width: 1024px) { .hero-banner { margin: 0 32px; border-radius: 28px; } }
</style>
<?php endif; ?>
<section class="relative overflow-hidden <?= $bannerHero ? 'bg-ink-900 hero-banner' : 'bg-gradient-to-b from-primary-light/70 via-white to-white' ?>">
  <?php if (!empty($bannerHero)): ?>
    <!-- Banner foto sekolah sebagai background hero — inline style sebagai fallback bila CSS gagal -->
    <img src="<?= e($bannerHero) ?>" alt="Foto sekolah"
         style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;"
         class="absolute inset-0 w-full h-full object-cover object-center" loading="eager">
    <div style="position:absolute;inset:0;background:linear-gradient(to bottom,rgba(15,23,42,.75),rgba(15,23,42,.55),rgba(15,23,42,.75));"
         class="absolute inset-0 bg-gradient-to-b from-ink-900/75 via-ink-900/55 to-ink-900/75"></div>
  <?php else: ?>
    <div class="absolute -top-24 -right-24 w-96 h-96 rounded-full bg-accent/10 blur-3xl"></div>
    <div class="absolute top-32 -left-32 w-96 h-96 rounded-full bg-primary/10 blur-3xl"></div>
  <?php endif; ?>

  <!-- Padding hero — ubah nilai ini untuk atur tinggi/rendahnya banner.
       pt = padding atas, pb = padding bawah. sm/lg = breakpoint layar. -->
  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-9 pb-[52px] lg:pt-[60px] lg:pb-[76px]">
    <div class="grid lg:grid-cols-12 gap-10 items-start">

      <!-- Kolom kiri -->
      <div class="lg:col-span-7">
        <div class="flex flex-wrap items-center gap-2">
          <span class="badge badge-blue <?= $bannerHero ? 'bg-white/15 border-white/25 text-white backdrop-blur-sm' : '' ?>"><?= icon('school', 'text-[13px]') ?> SPMB · <?= e($PENGATURAN['tahun_ajaran']) ?></span>
          <span class="badge badge-green <?= $bannerHero ? 'bg-white/15 border-white/25 text-white backdrop-blur-sm' : '' ?>"><?= dot('green') ?> Sesi Live Tahun Berjalan</span>
        </div>

        <h1 class="mt-5 text-3xl sm:text-4xl lg:text-[42px] font-extrabold leading-[1.12] tracking-tight <?= $bannerHero ? 'text-white' : 'text-ink-900' ?>">
          Sistem Penerimaan Murid Baru (SPMB)<br class="hidden sm:block">
          <span class="<?= $bannerHero ? 'text-white/90' : 'text-primary' ?>"><?= e($PENGATURAN['nama_sekolah']) ?></span>
        </h1>

        <p class="mt-4 text-base sm:text-lg leading-relaxed max-w-2xl <?= $bannerHero ? 'text-white/80' : 'text-ink-500' ?>">
          Membentuk generasi muda yang unggul dalam ilmu pengetahuan, berkarakter Islam, dan
          berdaya saing global di wilayah Kota Tangerang.
        </p>

        <div class="mt-6 flex flex-wrap gap-2.5">
          <span class="inline-flex items-center gap-2 h-9 px-3.5 rounded-full border text-sm shadow-card <?= $bannerHero ? 'bg-white/15 border-white/25 text-white backdrop-blur-sm' : 'bg-white border-line text-ink-700' ?>">
            <?= icon('event', 'text-[17px]') ?> <strong><?= e($PENGATURAN['gelombang']) ?></strong>
            <?php if (!empty($PENGATURAN['tanggal_buka_ppdb']) && !empty($PENGATURAN['tanggal_tutup_ppdb'])): ?>
              <span class="<?= $bannerHero ? 'text-white/50' : 'text-ink-400' ?>">&middot;</span>
              <span class="<?= $bannerHero ? 'text-white/70' : 'text-ink-500' ?>"><?= e(bulan_pendek($PENGATURAN['tanggal_buka_ppdb'])) ?> &ndash; <?= e(bulan_pendek($PENGATURAN['tanggal_tutup_ppdb'])) ?> <?= e(date('Y', strtotime($PENGATURAN['tanggal_tutup_ppdb']))) ?></span>
            <?php endif; ?>
          </span>
          <span class="inline-flex items-center gap-2 h-9 px-3.5 rounded-full border text-sm shadow-card <?= $bannerHero ? 'bg-white/15 border-white/25 text-white backdrop-blur-sm' : 'bg-white border-line text-ink-700' ?>">
            <?= icon('group', 'text-[17px]') ?> Kuota Siswa <strong><?= (int) $PENGATURAN['kuota_total'] ?></strong> (Sesi Berjalan)
          </span>
        </div>

        <div class="mt-7 flex flex-wrap gap-3">
          <a href="#registrasi" class="btn btn-primary btn-lg shadow-drop"><?= icon('arrow_forward') ?> Daftar Sekarang</a>
        </div>

        <!-- statistik -->
        <div class="mt-9 grid grid-cols-3 gap-3 sm:gap-4 max-w-xl">
          <?php foreach ($STATISTIK as $s): ?>
            <div class="card p-4 <?= $bannerHero ? 'bg-white/10 border-white/20 backdrop-blur-sm' : '' ?>">
              <div class="flex items-center justify-between">
                <span class="text-2xl font-extrabold <?= $bannerHero ? 'text-white' : 'text-ink-900' ?>"><?= e($s['angka']) ?></span>
                <span class="icon-box <?= $bannerHero ? 'bg-white/20 text-white' : 'bg-primary-light text-primary' ?>"><?= icon($s['icon']) ?></span>
              </div>
              <p class="mt-1 text-[11px] sm:text-xs leading-snug <?= $bannerHero ? 'text-white/70' : 'text-ink-500' ?>"><?= e($s['label']) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Kolom kanan: pendaftaran cepat -->
      <div class="lg:col-span-5 w-full" id="registrasi">
        <div class="card p-5 sm:p-6 shadow-drop lg:mt-4">
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="icon-box bg-primary-light text-primary w-10 h-10"><?= icon('how_to_reg', 'text-[20px]') ?></span>
              <div>
                <h2 class="font-bold text-ink-900 text-lg leading-tight">Pendaftaran Cepat</h2>
                <p class="text-xs text-ink-500">Isi data awal untuk mendapatkan kode registrasi.</p>
              </div>
            </div>
            <span class="badge badge-green shrink-0"><?= dot('green') ?> Online 24 Jam</span>
          </div>

          <form class="mt-5 space-y-4" method="post" action="<?= url('app/auth/registrasi.php') ?>">
            <?= csrf_field() ?>
            <!-- Honeypot: jangan diisi — bot biasanya mengisi semua field -->
            <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
              <label for="qc-website">Website</label>
              <input id="qc-website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <div>
              <label class="label" for="qc-nama">Nama Lengkap Siswa</label>
              <input id="qc-nama" name="nama" class="input" type="text" placeholder="Contoh: Muhammad Farhan Al-Ghifari" required>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="label" for="qc-wa">WhatsApp Orang Tua</label>
                <input id="qc-wa" name="wa" class="input" type="tel" placeholder="081234567890" required>
              </div>
              <div>
                <label class="label" for="qc-jalur">Pilih Jalur</label>
                <select id="qc-jalur" name="jalur" class="select w-full">
                  <?php foreach (jalur_aktif() as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div>
              <label class="label" for="qc-asal">Asal Sekolah (SD/MI)</label>
              <input id="qc-asal" name="asal" class="input" type="text" placeholder="Contoh: SD Muhammadiyah 3 Tangerang" required>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block shadow-drop">
              Lanjutkan &amp; Dapatkan Nomor Pendaftaran <?= icon('arrow_forward') ?>
            </button>

            <p class="flex items-start gap-2 text-xs text-ink-400 leading-relaxed">
              <?= icon('lock', 'text-[15px] mt-0.5') ?>
              Nomor pendaftaran dibuat otomatis (REG-<?= date('Y') ?>-XXXX). Dengan menekan tombol ini, Anda akan diminta menyetujui Syarat &amp; Ketentuan pendaftaran terlebih dahulu.
            </p>
          </form>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ============================ ALUR 4 LANGKAH ============================ -->
<section id="alur" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
  <div class="text-center max-w-2xl mx-auto">
    <span class="badge badge-green mx-auto"><?= icon('verified', 'text-[13px]') ?> Proses Mudah &amp; Terstandar</span>
    <h2 class="mt-4 text-2xl sm:text-3xl font-bold tracking-tight text-ink-900">Alur 4 Langkah Mudah SPMB</h2>
    <p class="mt-3 text-ink-500">Proses seleksi penerimaan siswa baru dirancang praktis, transparan, dan dapat dipantau langsung melalui portal (SPMB).</p>
  </div>

  <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
    <?php foreach ($LANGKAH as $i => $l): ?>
      <article class="card p-5 flex flex-col hover:border-primary/40 hover:shadow-drop transition">
        <div class="flex items-start justify-between">
          <span class="text-3xl font-extrabold text-line-mid"><?= e($l['no']) ?></span>
          <span class="icon-box <?= $i % 2 ? 'bg-[#ECFDF5] text-[#0E9F6E]' : 'bg-primary-light text-primary' ?>"><?= icon($l['icon'], 'text-[20px]') ?></span>
        </div>
        <h3 class="mt-4 font-bold text-ink-900"><?= e($l['judul']) ?></h3>
        <p class="mt-2 text-sm text-ink-500 leading-relaxed flex-1"><?= e($l['isi']) ?></p>
        <div class="mt-4 pt-4 border-t border-line-soft flex items-center gap-2 text-xs text-ink-500">
          <?= icon($l['meta_icon'], 'text-[15px] text-ink-400') ?> <?= e($l['meta']) ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============================ BIAYA & JADWAL ============================ -->
<section id="biaya" class="bg-white border-y border-line">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-5">
      <div>
        <span class="badge badge-blue"><?= icon('receipt_long', 'text-[13px]') ?> Transparan Finansial &amp; Operasional</span>
        <h2 class="mt-4 text-2xl sm:text-3xl font-bold tracking-tight text-ink-900">Rincian Biaya &amp; Jadwal Seleksi</h2>
        <p class="mt-2 text-ink-500 max-w-2xl">Biaya pendidikan transparan tanpa pengecualian tersembunyi, dengan ketentuan cicilan untuk peserta yang memenuhi kriteria tertentu.</p>
      </div>
      <span class="badge badge-green self-start"><?= dot('green') ?> Selalu Sinkron dengan CMS</span>
    </div>

    <div class="mt-10 grid gap-6 lg:grid-cols-3">

      <!-- Kartu 1: Biaya formulir -->
      <div class="card p-6 flex flex-col">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Bank Aktif</span>
          <span class="badge badge-gray">Saldo Rekening</span>
        </div>
        <h3 class="mt-4 font-bold text-ink-900 text-lg">Biaya Formulir</h3>
        <div class="mt-5 rounded-xl bg-canvas border border-line p-5">
          <div class="text-xs text-ink-500">Nominal Tagihan Formulir</div>
          <div class="mt-1 text-3xl font-extrabold text-primary"><?= rupiah($PENGATURAN['biaya_formulir']) ?></div>
          <div class="mt-4 border-t border-line pt-3 flex items-center justify-between gap-2">
            <div class="text-sm">
              <div class="text-ink-500 text-xs">Transfer Bank / BSI Mobile (810)</div>
              <div class="font-semibold text-ink-900 font-mono tracking-wide"><?= e($PENGATURAN['no_rekening']) ?></div>
              <div class="text-xs text-ink-500">a.n. <?= e($PENGATURAN['atas_nama_rekening']) ?></div>
            </div>
            <button type="button" class="btn btn-sm btn-outline" data-copy="<?= e($PENGATURAN['no_rekening']) ?>">
              <?= icon('content_copy', 'text-[15px]') ?> Salin
            </button>
          </div>
        </div>
        <p class="mt-4 text-xs text-ink-500 leading-relaxed flex-1">
          Transfer sesuai nominal agar sistem dapat memverifikasi otomatis. Unggah bukti transfer melalui akun siswa setelah registrasi awal.
        </p>
      </div>

      <!-- Kartu 2: Rincian biaya pendaftaran -->
      <?php /* Program beasiswa hanya tampil bila operator mengisi teksnya di
              * Pengaturan Sistem → Struktur Tarif (kolom teks_beasiswa). */ ?>
      <?php $beasiswa = trim((string) ($PENGATURAN['teks_beasiswa'] ?? '')); ?>
      <div class="card p-6 flex flex-col relative ring-2 ring-accent/40">
        <?php if ($beasiswa !== ''): ?>
          <span class="absolute -top-3 left-6 badge badge-green bg-[#0E9F6E]! text-white! border-[#0E9F6E]!">Beasiswa Tersedia</span>
        <?php endif; ?>
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Investasi Pendidikan</span>
          <span class="badge badge-amber"><?= icon('schedule', 'text-[13px]') ?> T.A. 2025/2026</span>
        </div>
        <h3 class="mt-4 font-bold text-ink-900 text-lg">Rincian SOP &amp; <?= e(label_biaya_pendaftaran()) ?></h3>

        <div class="mt-4 space-y-3">
          <div class="rounded-xl border border-line p-4">
            <div class="flex items-center justify-between">
              <!-- Label bisa diganti operator di CMS Pengaturan → Struktur Tarif -->
              <span class="text-sm font-medium text-ink-700"><?= e(label_biaya_pendaftaran()) ?></span>
              <button class="text-ink-400 hover:text-primary" title="Estimasi dari CMS (pengaturan_sekolah)"><?= icon('info', 'text-[17px]') ?></button>
            </div>
            <div class="mt-1 font-bold text-ink-900"><?= rupiah($PENGATURAN['estimasi_uang_masuk']) ?></div>
            <div class="text-[11px] text-ink-400">Estimasi <?= e(label_biaya_pendaftaran()) ?> sesuai pengaturan CMS.</div>
          </div>
          <div class="rounded-xl border border-line p-4">
            <div class="flex items-center justify-between">
              <!-- Label bisa diganti operator di CMS Pengaturan → Struktur Tarif -->
              <span class="text-sm font-medium text-ink-700"><?= e($PENGATURAN['label_spp'] ?? 'SPP Bulanan') ?></span>
              <span class="badge badge-blue">Bulanan</span>
            </div>
            <div class="mt-1 font-bold text-ink-900"><?= rupiah($PENGATURAN['biaya_spp']) ?></div>
            <div class="text-[11px] text-ink-400">Sesuai kebijakan yayasan &amp; pengaturan CMS.</div>
          </div>
          <?php if ($beasiswa !== ''): ?>
            <div class="rounded-xl bg-[#ECFDF5] border border-[#A7F3D0] p-4">
              <div class="flex items-center gap-2 text-[#065F46] font-semibold text-sm">
                <?= icon('volunteer_activism', 'text-[18px]') ?> Program Beasiswa
              </div>
              <p class="mt-1 text-[11px] leading-relaxed text-[#065F46]/90"><?= nl2br(e($beasiswa)) ?></p>
            </div>
          <?php endif; ?>
        </div>

        <a href="#faq" class="btn btn-primary btn-block mt-5">Ajukan Skema Angsuran</a>
      </div>

      <!-- Kartu 3: Agenda -->
      <div class="card p-6 flex flex-col" id="jadwal">
        <div class="flex items-center justify-between">
          <span class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Agenda Seleksi</span>
          <span class="badge badge-blue"><?= icon('calendar_month', 'text-[13px]') ?> Serentak</span>
        </div>
        <h3 class="mt-4 font-bold text-ink-900 text-lg">Jadwal Tes Seleksi &amp; Verifikasi</h3>

        <ul class="mt-4 space-y-3">
          <?php foreach ($JADWAL_TES as $j): ?>
            <li class="flex gap-3 rounded-xl border border-line p-3.5">
              <span class="icon-box bg-canvas text-ink-500"><?= icon('event', 'text-[19px]') ?></span>
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                  <p class="font-semibold text-ink-900 text-sm truncate"><?= e($j['nama']) ?></p>
                  <span class="badge badge-<?= e($j['tone']) ?> shrink-0"><?= e($j['mode']) ?></span>
                </div>
                <p class="mt-1 text-xs text-ink-500"><?= bulan($j['tgl']) ?> &middot; <?= e($j['jam']) ?></p>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>

        <div class="mt-4 rounded-xl bg-primary-light border border-primary/15 p-4">
          <div class="text-[11px] uppercase tracking-wider text-ink-500 font-semibold">Periode Pendaftaran <?= e($PENGATURAN['gelombang']) ?></div>
          <?php if (!empty($PENGATURAN['tanggal_buka_ppdb']) && !empty($PENGATURAN['tanggal_tutup_ppdb'])): ?>
            <div class="mt-1 font-bold text-ink-900">
              <?= e(bulan($PENGATURAN['tanggal_buka_ppdb'])) ?> &ndash; <?= e(bulan($PENGATURAN['tanggal_tutup_ppdb'])) ?>
            </div>
            <?php if (!empty($PENGATURAN['tanggal_pengumuman'])): ?>
              <div class="mt-1 text-[11px] text-ink-500">
                Pengumuman hasil seleksi: <strong class="text-ink-700"><?= e(bulan($PENGATURAN['tanggal_pengumuman'])) ?></strong>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <div class="mt-1 font-bold text-ink-900">Setiap Hari Selasa &amp; Jumat pekan</div>
          <?php endif; ?>
        </div>

        <?php
        /* Konfirmasi jadwal lewat WhatsApp panitia — tautan nyata bila nomor
         * panitia sudah diisi di Pengaturan. */
        $waPanitia = preg_replace('/\D+/', '', (string) ($PENGATURAN['kontak_wa_panitia'] ?? ''));
        if ($waPanitia !== '' && strpos($waPanitia, '0') === 0) {
            $waPanitia = '62' . substr($waPanitia, 1);
        }
        $pesanWaJadwal = rawurlencode('Assalamu\'alaikum, saya ingin konfirmasi jadwal tes seleksi SPMB '
            . $PENGATURAN['nama_sekolah'] . ' ' . $PENGATURAN['gelombang'] . '.');
        ?>
        <?php if ($waPanitia !== ''): ?>
          <a href="https://wa.me/<?= e($waPanitia) ?>?text=<?= e($pesanWaJadwal) ?>" target="_blank" rel="noopener"
             class="btn btn-outline btn-block mt-5">
            <?= icon('forum') ?> Konfirmasi Jadwal via WhatsApp
          </a>
        <?php else: ?>
          <span class="btn btn-outline btn-block mt-5 opacity-60 cursor-not-allowed" title="Nomor WhatsApp panitia belum diatur">
            <?= icon('forum') ?> Konfirmasi Jadwal via WhatsApp
          </span>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

<!-- ============================ FAQ ============================ -->
<section id="faq" class="bg-white border-t border-line">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
    <div class="text-center">
      <span class="badge badge-gray mx-auto"><?= icon('help', 'text-[13px]') ?> Pusat Bantuan</span>
      <h2 class="mt-4 text-2xl sm:text-3xl font-bold tracking-tight text-ink-900">Tanya Jawab Seputar SPMB (FAQ)</h2>
      <p class="mt-3 text-ink-500">Informasi penting seputar pendaftaran, tes seleksi, dan kebijakan sekolah.</p>
    </div>

    <div class="mt-9 space-y-3">
      <?php foreach ($FAQ as $i => $f): ?>
        <details class="card-flat group <?= $i === 0 ? 'ring-1 ring-primary/20' : '' ?>" <?= $i === 0 ? 'open' : '' ?>>
          <summary class="flex items-center justify-between gap-4 px-5 py-4 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
            <span class="font-semibold text-ink-900 text-[15px]"><?= e($f['q']) ?></span>
            <span class="icon-box bg-canvas text-ink-500 group-open:bg-primary group-open:text-white transition"><?= icon('add') ?></span>
          </summary>
          <div class="px-5 pb-5 -mt-1 text-sm text-ink-500 leading-relaxed"><?= e($f['a']) ?></div>
        </details>
      <?php endforeach; ?>
    </div>

    <div class="mt-8 card p-5 flex flex-col sm:flex-row items-center gap-4 justify-between">
      <div class="flex items-center gap-3">
        <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E] w-10 h-10"><?= icon('support_agent', 'text-[20px]') ?></span>
        <div>
          <p class="font-semibold text-ink-900 text-sm">Belum menemukan jawaban?</p>
          <p class="text-xs text-ink-500">Hubungi panitia SPMB — Senin–Jumat, 08.00–15.00 WIB.</p>
        </div>
      </div>
      <a href="<?= url('app/auth/login.php') ?>" class="btn btn-primary"><?= icon('school') ?> Login Siswa</a>
    </div>
  </div>
</section>
</main>

<?php require_once __DIR__ . '/../../includes/partials/footer-public.php'; ?>

<div id="toast-host" class="fixed bottom-5 right-5 z-[60] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
/* Fungsi toggle dark/light mode — dipanggil dari tombol di header landing. */
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
</body>
</html>
