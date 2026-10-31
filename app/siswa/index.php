<?php
$title = 'Dashboard Siswa';
$desc  = 'Ringkasan status pendaftaran, kelengkapan data, pembayaran, jadwal tes, dan informasi akademik.';
require __DIR__ . '/_bootstrap.php';

$kabar = $siswa ? siswa_kabar_terbaru(
    $siswa, $pembayaran, $kartuUjian, $pengumuman, $penempatan, $dokumen,
    $bayarFormulir, $bayarUangMasuk
) : [];

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'dashboard';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Siswa' => null, 'Dashboard' => null];
$pageActions = '<a href="' . url('app/siswa/data-siswa.php') . '" class="btn btn-outline hidden md:inline-flex">' . icon('badge') . ' Data Saya</a>';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Akun Siswa</span>
          <?php if ($siswa): ?>
            <span class="badge badge-<?= e($verifikasi[1]) ?>"><?= e($verifikasi[0]) ?></span>
            <?php $akun = $labelAkun[$siswa['status_akun']] ?? [$siswa['status_akun'], 'gray']; ?>
            <span class="badge badge-<?= e($akun[1]) ?>">Akun <?= e($akun[0]) ?></span>
          <?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">
          Halo, <?= e($siswa['nama_siswa'] ?? $me['nama']) ?>
        </h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Ringkasan <?= e($PENGATURAN['gelombang'] ?? 'SPMB') ?> <?= e($PENGATURAN['tahun_ajaran'] ?? '') ?>
          <?php if ($siswa): ?>
            · No. pendaftaran <span class="font-mono font-semibold text-ink-700"><?= e($siswa['no_pendaftaran']) ?></span>
          <?php endif; ?>
        </p>
      </div>
      <div class="flex items-center gap-2.5 shrink-0">
        <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline" target="_blank" rel="noopener">
          <?= icon('language') ?> Info SPMB
        </a>
      </div>
    </div>

    <?php if (!$siswa): ?>
      <div class="mt-6 rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] p-5 text-sm text-[#92400E]">
        <p class="font-semibold">Data pendaftaran belum ditemukan untuk akun ini.</p>
        <p class="mt-1">Hubungi panitia SPMB untuk menghubungkan akun dengan data pendaftaran Anda.</p>
      </div>
    <?php else: ?>

      <!-- Kartu ringkasan utama -->
      <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Status Pendaftaran</p>
              <p class="mt-2 text-lg font-extrabold text-ink-900 leading-tight"><?= e($tahap['label']) ?></p>
            </div>
            <span class="icon-box bg-primary-light text-primary"><?= icon('how_to_reg', 'text-[20px]') ?></span>
          </div>
          <div class="mt-3">
            <div class="h-1.5 rounded-full bg-line-soft overflow-hidden">
              <div class="h-full rounded-full bg-primary" style="width:<?= (int) $tahap['persen'] ?>%"></div>
            </div>
            <p class="mt-2 text-[12px] text-ink-500"><?= (int) $tahap['persen'] ?>% alur pendaftaran</p>
          </div>
        </article>

        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Kelengkapan Data</p>
              <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $kelengkapan['persen'] ?><span class="text-base font-semibold text-ink-400">%</span></p>
            </div>
            <span class="icon-box bg-[#EFF6FF] text-[#2563EB]"><?= icon('fact_check', 'text-[20px]') ?></span>
          </div>
          <div class="mt-3 pt-3 border-t border-line-soft flex items-center justify-between gap-2 text-xs">
            <span class="text-ink-500"><?= (int) $kelengkapan['terisi'] ?>/<?= (int) $kelengkapan['total'] ?> butir wajib</span>
            <a href="<?= url('app/siswa/data-siswa.php') ?>" class="font-semibold text-primary hover:underline">Periksa</a>
          </div>
        </article>

        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Pembayaran Formulir</p>
              <p class="mt-2 text-xl font-extrabold text-ink-900"><?= rupiah($bayarFormulir['verified']) ?></p>
            </div>
            <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E]"><?= icon('payments', 'text-[20px]') ?></span>
          </div>
          <div class="mt-3 pt-3 border-t border-line-soft text-xs text-ink-500">
            Status: <strong class="text-ink-900"><?= e($bayarFormulir['label']) ?></strong>
          </div>
        </article>

        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Jadwal / Hasil</p>
              <?php if ($kartuUjian): ?>
                <p class="mt-2 text-lg font-extrabold text-ink-900 leading-tight"><?= e($kartuUjian['kode']) ?></p>
                <p class="text-[12px] text-ink-500 mt-0.5"><?= e(bulan($kartuUjian['tanggal'])) ?></p>
              <?php elseif ($pengumumanAktif): ?>
                <?php $sel = $labelSeleksi[$siswa['status_seleksi']] ?? [$siswa['status_seleksi'], 'gray']; ?>
                <p class="mt-2 text-lg font-extrabold text-ink-900 leading-tight"><?= e($sel[0]) ?></p>
                <p class="text-[12px] text-ink-500 mt-0.5">Hasil seleksi resmi</p>
              <?php else: ?>
                <p class="mt-2 text-lg font-extrabold text-ink-900 leading-tight">Belum Ada</p>
                <p class="text-[12px] text-ink-500 mt-0.5">Menunggu panitia</p>
              <?php endif; ?>
            </div>
            <span class="icon-box bg-[#FFF7ED] text-[#EA580C]"><?= icon('event', 'text-[20px]') ?></span>
          </div>
          <div class="mt-3 pt-3 border-t border-line-soft text-xs">
            <a href="<?= url('app/siswa/ppdb.php') ?>" class="font-semibold text-primary hover:underline">Buka PPDB &amp; Seleksi</a>
          </div>
        </article>
      </section>

      <!-- Langkah berikutnya -->
      <?php if ($langkahBerikut): ?>
        <section class="mt-6 card p-5 sm:p-6 border-l-4 border-l-primary">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3 min-w-0">
              <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon($langkahBerikut['ikon'], 'text-[22px]') ?></span>
              <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-primary">Langkah berikutnya</p>
                <h2 class="mt-1 font-bold text-ink-900"><?= e($langkahBerikut['judul']) ?></h2>
                <p class="mt-1 text-[13px] text-ink-500"><?= e($langkahBerikut['pesan']) ?></p>
              </div>
            </div>
            <a href="<?= e($langkahBerikut['tautan']) ?>" class="btn btn-primary shrink-0"><?= e($langkahBerikut['label']) ?> <?= icon('arrow_forward') ?></a>
          </div>
        </section>
      <?php endif; ?>

      <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <!-- Kabar terbaru -->
        <section class="card lg:col-span-2 p-5 sm:p-6">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="font-bold text-ink-900">Kabar Terbaru</h2>
              <p class="mt-1 text-[13px] text-ink-500">Diambil dari status pendaftaran, pembayaran, dan jadwal Anda.</p>
            </div>
            <a href="<?= url('app/siswa/notifikasi.php') ?>" class="btn btn-sm btn-outline shrink-0">Semua</a>
          </div>

          <div class="mt-4 space-y-3">
            <?php foreach ($kabar as $k): ?>
              <a href="<?= e($k['tautan']) ?>" class="block rounded-2xl border border-line p-4 hover:bg-canvas transition">
                <div class="flex items-start gap-3">
                  <span class="icon-box bg-canvas text-primary shrink-0"><?= icon($k['ikon'], 'text-[19px]') ?></span>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                      <p class="font-semibold text-sm text-ink-900"><?= e($k['judul']) ?></p>
                      <?php if (!empty($k['tanggal'])): ?>
                        <span class="text-[11px] text-ink-400"><?= e(bulan($k['tanggal'])) ?></span>
                      <?php endif; ?>
                    </div>
                    <p class="mt-1 text-[13px] text-ink-500"><?= e($k['pesan']) ?></p>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
            <?php if (!$kabar): ?>
              <p class="text-sm text-ink-400">Belum ada kabar baru dari panitia.</p>
            <?php endif; ?>
          </div>
        </section>

        <div class="space-y-6">
          <!-- Ringkasan pembayaran -->
          <section class="card p-5 sm:p-6">
            <h2 class="font-bold text-ink-900">Ringkasan Pembayaran</h2>
            <div class="mt-4 space-y-3">
              <?php
              $itemBayar = [['Biaya formulir', $bayarFormulir]];
              if ($siswaDiterima || (float) $bayarUangMasuk['verified'] > 0 || (float) $bayarUangMasuk['pending'] > 0) {
                  $itemBayar[] = [label_biaya_pendaftaran(), $bayarUangMasuk];
              }
              foreach ($itemBayar as $item): ?>
                <?php $b = $item[1]; ?>
                <div class="rounded-xl border border-line p-3">
                  <div class="flex items-start justify-between gap-2">
                    <p class="text-[13px] font-semibold text-ink-900"><?= e($item[0]) ?></p>
                    <span class="badge badge-<?= e($b['tone']) ?>"><?= e($b['label']) ?></span>
                  </div>
                  <dl class="mt-2 grid grid-cols-2 gap-2 text-[12px]">
                    <div><dt class="text-ink-400">Tagihan</dt><dd class="font-semibold text-ink-900"><?= rupiah($b['tagihan']) ?></dd></div>
                    <div><dt class="text-ink-400">Sisa</dt><dd class="font-semibold text-ink-900"><?= rupiah($b['sisa']) ?></dd></div>
                  </dl>
                </div>
              <?php endforeach; ?>
            </div>
            <a href="<?= url('app/siswa/keuangan.php') ?>" class="btn btn-outline btn-block mt-4"><?= icon('account_balance_wallet') ?> Buka Keuangan</a>
          </section>

          <!-- Ringkasan kelas / akun -->
          <section class="card p-5 sm:p-6">
            <h2 class="font-bold text-ink-900"><?= $siswaAktif ? 'Kelas Saya' : 'Akun & Kontak' ?></h2>
            <?php if ($siswaAktif): ?>
              <?php if ($penempatan): ?>
                <p class="mt-3 text-sm text-primary font-semibold">Anda ditempatkan di</p>
                <p class="mt-1 text-lg font-extrabold text-ink-900"><?= e($penempatan['kode']) ?> — <?= e($penempatan['nama_kelas']) ?></p>
                <p class="mt-1 text-[13px] text-ink-500">Wali kelas: <?= e($penempatan['wali_kelas'] ?: '—') ?></p>
                <p class="mt-1 text-[13px] text-ink-500">Tahun ajaran: <?= e($PENGATURAN['tahun_ajaran'] ?? '—') ?></p>
              <?php else: ?>
                <p class="mt-3 text-sm text-ink-500">Anda sudah dinyatakan lulus. Penempatan kelas belum ditetapkan panitia.</p>
              <?php endif; ?>
              <a href="<?= url('app/siswa/akademik.php') ?>" class="btn btn-outline btn-block mt-4"><?= icon('school') ?> Lihat Akademik</a>
            <?php else: ?>
              <dl class="mt-3 space-y-2 text-[13px]">
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Username</dt><dd class="font-mono font-semibold text-ink-900"><?= e($me['username']) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">No. pendaftaran</dt><dd class="font-mono font-semibold text-ink-900"><?= e($siswa['no_pendaftaran']) ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">WA orang tua</dt><dd class="font-semibold text-ink-900"><?= e($siswa['no_whatsapp'] ?: '—') ?></dd></div>
              </dl>
              <a href="<?= url('app/siswa/akun.php') ?>" class="btn btn-outline btn-block mt-4"><?= icon('manage_accounts') ?> Pengaturan Akun</a>
            <?php endif; ?>
          </section>
        </div>
      </div>

    <?php endif; ?>
  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode(flash(), JSON_UNESCAPED_UNICODE) ?>;
</script>
</body>
</html>
