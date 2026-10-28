<?php
/**
 * PENGUMUMAN — portal siswa (Fase 13).
 *
 * Halaman ini hanya menampilkan informasi yang benar-benar berasal dari
 * data sistem: status seleksi/pengumuman, jadwal tes, status pembayaran,
 * status verifikasi data, tindak lanjut koreksi, dan kontak panitia.
 * Tidak ada pengumuman contoh/dummy.
 */
$title = 'Pengumuman';
$desc  = 'Informasi resmi SPMB: pengumuman hasil seleksi, jadwal tes, status pembayaran, dan kontak panitia.';
require __DIR__ . '/_bootstrap.php';

$flash = flash();

/* ==================================================================
 * SUSUN DAFTAR INFORMASI (hanya dari data nyata)
 * ================================================================== */
$info = [];

if ($pengumumanAktif) {
    $info[] = [
        'ikon'    => 'campaign',
        'tone'    => 'green',
        'judul'   => 'Hasil seleksi resmi telah diumumkan',
        'pesan'   => 'Hasil seleksi SPMB ' . ($PENGATURAN['tahun_ajaran'] ?? '') . ' sudah dapat dilihat. Buka menu PPDB & Seleksi untuk detailnya.',
        'tanggal' => $pengumuman['dijadwalkan_pada'] ?? null,
        'tautan'  => url('app/siswa/ppdb.php?tab=hasil'),
        'label'   => 'Lihat Hasil',
    ];
} elseif (!empty($pengumuman['dijadwalkan_pada'])) {
    $info[] = [
        'ikon'    => 'schedule',
        'tone'    => 'blue',
        'judul'   => 'Jadwal pengumuman hasil seleksi',
        'pesan'   => 'Hasil seleksi dijadwalkan diumumkan pada ' . bulan($pengumuman['dijadwalkan_pada']) . '.',
        'tanggal' => $pengumuman['dijadwalkan_pada'],
        'tautan'  => url('app/siswa/ppdb.php?tab=hasil'),
        'label'   => 'Buka PPDB',
    ];
} elseif (!empty($PENGATURAN['tanggal_pengumuman'])) {
    $info[] = [
        'ikon'    => 'event_note',
        'tone'    => 'gray',
        'judul'   => 'Perkiraan tanggal pengumuman',
        'pesan'   => 'Panitia menargetkan pengumuman hasil seleksi pada ' . bulan($PENGATURAN['tanggal_pengumuman']) . '. Tanggal resmi akan diinformasikan kembali.',
        'tanggal' => $PENGATURAN['tanggal_pengumuman'],
        'tautan'  => url('app/siswa/ppdb.php'),
        'label'   => 'Lihat Status',
    ];
}

if (!empty($PENGATURAN['tanggal_buka_ppdb']) || !empty($PENGATURAN['tanggal_tutup_ppdb'])) {
    $buka  = !empty($PENGATURAN['tanggal_buka_ppdb']) ? bulan($PENGATURAN['tanggal_buka_ppdb']) : '—';
    $tutup = !empty($PENGATURAN['tanggal_tutup_ppdb']) ? bulan($PENGATURAN['tanggal_tutup_ppdb']) : '—';
    $info[] = [
        'ikon'    => 'date_range',
        'tone'    => 'blue',
        'judul'   => 'Masa pendaftaran ' . ($PENGATURAN['gelombang'] ?? 'SPMB'),
        'pesan'   => 'Pendaftaran dibuka ' . $buka . ' sampai ' . $tutup . '.',
        'tanggal' => $PENGATURAN['tanggal_tutup_ppdb'] ?? null,
        'tautan'  => url('app/spmb/index.php'),
        'label'   => 'Info SPMB',
    ];
}

if ($kartuUjian) {
    $info[] = [
        'ikon'    => 'event',
        'tone'    => 'blue',
        'judul'   => 'Jadwal tes: ' . $kartuUjian['nama_kegiatan'],
        'pesan'   => bulan($kartuUjian['tanggal']) . ', '
            . substr((string) $kartuUjian['jam_mulai'], 0, 5) . '–' . substr((string) $kartuUjian['jam_selesai'], 0, 5) . ' WIB · '
            . ($kartuUjian['ruangan'] ?: ($kartuUjian['lokasi'] ?: 'Lokasi sekolah')),
        'tanggal' => $kartuUjian['tanggal'],
        'tautan'  => url('app/siswa/ppdb.php?tab=jadwal'),
        'label'   => 'Kartu Peserta',
    ];
}

if ($siswa) {
    if (($siswa['status_verifikasi'] ?? '') === 'MENUNGGU_VERIFIKASI') {
        $info[] = [
            'ikon'    => 'fact_check',
            'tone'    => 'amber',
            'judul'   => 'Data sedang menunggu verifikasi panitia',
            'pesan'   => 'Data yang Anda ajukan sedang diperiksa operator. Pantau catatan panitia pada menu Data Siswa.',
            'tanggal' => $siswa['updated_at'] ?? null,
            'tautan'  => url('app/siswa/data-siswa.php'),
            'label'   => 'Buka Data Saya',
        ];
    } elseif (($siswa['status_verifikasi'] ?? '') === 'DITOLAK') {
        $info[] = [
            'ikon'    => 'error',
            'tone'    => 'red',
            'judul'   => 'Data perlu diperbaiki',
            'pesan'   => 'Panitia meminta perbaikan data. ' . ($siswa['catatan_verifikasi_data'] ?: 'Periksa catatan pada menu Data Siswa.'),
            'tanggal' => $siswa['updated_at'] ?? null,
            'tautan'  => url('app/siswa/data-siswa.php?tab=data-diri'),
            'label'   => 'Perbaiki Data',
        ];
    }
}

foreach ([[$bayarFormulir, 'Biaya formulir'], [$bayarUangMasuk, label_biaya_pendaftaran()]] as $pasangan) {
    $b    = $pasangan[0];
    $nama = $pasangan[1];
    if ($b['rejected'] > 0) {
        $info[] = [
            'ikon'    => 'error',
            'tone'    => 'red',
            'judul'   => 'Pembayaran ' . $nama . ' ditolak',
            'pesan'   => 'Periksa catatan bendahara pada menu Keuangan, lalu unggah ulang bukti pembayaran yang benar.',
            'tanggal' => null,
            'tautan'  => url('app/siswa/keuangan.php?tab=riwayat'),
            'label'   => 'Lihat Riwayat',
        ];
    } elseif ($b['pending_bukti'] > 0) {
        $info[] = [
            'ikon'    => 'hourglass_top',
            'tone'    => 'amber',
            'judul'   => 'Pembayaran ' . $nama . ' menunggu verifikasi',
            'pesan'   => 'Bukti pembayaran sudah diterima. Status lunas baru berlaku setelah diperiksa bendahara.',
            'tanggal' => null,
            'tautan'  => url('app/siswa/keuangan.php'),
            'label'   => 'Buka Keuangan',
        ];
    }
}

/* Tindak lanjut pengajuan koreksi data (jika ada). */
foreach (array_slice($koreksi, 0, 3) as $k) {
    $kl     = koreksi_status_label($k['status']);
    $bidang = $koreksiBidang[$k['bidang']] ?? $k['bidang'];
    $pesan  = $k['catatan_operator'] ?: 'Bagian: ' . $bidang . '.';
    if (empty($k['catatan_operator']) && $k['status'] === 'MENUNGGU') {
        $pesan = 'Bagian: ' . $bidang . '. Pengajuan sedang menunggu tinjauan panitia.';
    }
    $info[] = [
        'ikon'    => 'edit_note',
        'tone'    => $kl[1],
        'judul'   => 'Pengajuan koreksi: ' . $kl[0],
        'pesan'   => $pesan,
        'tanggal' => $k['reviewed_at'] ?: $k['created_at'],
        'tautan'  => url('app/siswa/data-siswa.php?tab=data-diri'),
        'label'   => 'Lihat Pengajuan',
    ];
}

/* Informasi umum dari ticker CMS (hanya bila diisi). */
$ticker = trim((string) ($PENGATURAN['ticker_header'] ?? ''));
if ($ticker !== '') {
    $info[] = [
        'ikon'    => 'info',
        'tone'    => 'gray',
        'judul'   => 'Informasi SPMB ' . ($PENGATURAN['gelombang'] ?? ''),
        'pesan'   => $ticker,
        'tanggal' => null,
        'tautan'  => '',
        'label'   => '',
    ];
}

$toneRingkas = [
    'green' => ['#ECFDF5', '#0E9F6E'],
    'blue'  => ['#EFF6FF', '#2563EB'],
    'amber' => ['#FFFBEB', '#B45309'],
    'red'   => ['#FEF2F2', '#991B1B'],
    'gray'  => ['#F8FAFC', '#475569'],
];

$waPanitia = preg_replace('/\D+/', '', (string) ($PENGATURAN['kontak_wa_panitia'] ?? ''));
if ($waPanitia !== '' && strpos($waPanitia, '0') === 0) {
    $waPanitia = '62' . substr($waPanitia, 1);
}

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'pengumuman';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Siswa' => url('app/siswa/index.php'), 'Pengumuman' => null];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Papan Informasi</span>
          <span class="badge badge-gray"><?= e($PENGATURAN['gelombang'] ?? 'SPMB') ?> · <?= e($PENGATURAN['tahun_ajaran'] ?? '') ?></span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Pengumuman</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Informasi resmi panitia yang berkaitan dengan pendaftaran Anda. Semua isi di halaman ini berasal dari status dan pengaturan sistem.
        </p>
      </div>
      <a href="<?= url('app/siswa/index.php') ?>" class="btn btn-outline shrink-0"><?= icon('arrow_back') ?> Dashboard</a>
    </div>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">

      <!-- Daftar informasi -->
      <section class="lg:col-span-2 space-y-3">
        <?php if (!$info): ?>
          <div class="card p-8 text-center">
            <span class="icon-box bg-canvas text-ink-400 mx-auto"><?= icon('campaign', 'text-[26px]') ?></span>
            <h2 class="mt-3 font-bold text-ink-900">Belum ada pengumuman</h2>
            <p class="mt-1 text-sm text-ink-500">Panitia belum menerbitkan informasi baru. Halaman ini akan diperbarui otomatis saat ada status baru.</p>
            <a href="<?= url('app/siswa/ppdb.php') ?>" class="btn btn-outline btn-sm mt-4">Lihat Status PPDB</a>
          </div>
        <?php endif; ?>

        <?php foreach ($info as $n): ?>
          <?php $warna = $toneRingkas[$n['tone']] ?? $toneRingkas['gray']; ?>
          <article class="card p-5 sm:p-6">
            <div class="flex items-start gap-4">
              <span class="icon-box shrink-0" style="background:<?= e($warna[0]) ?>;color:<?= e($warna[1]) ?>">
                <?= icon($n['ikon'], 'text-[21px]') ?>
              </span>
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                  <h2 class="font-bold text-ink-900"><?= e($n['judul']) ?></h2>
                  <?php if (!empty($n['tanggal'])): ?>
                    <span class="text-[11px] text-ink-400 whitespace-nowrap"><?= e(bulan($n['tanggal'])) ?></span>
                  <?php endif; ?>
                </div>
                <p class="mt-1.5 text-[13px] text-ink-600 whitespace-pre-line"><?= e($n['pesan']) ?></p>
                <?php if (!empty($n['tautan'])): ?>
                  <a href="<?= e($n['tautan']) ?>" class="mt-3 inline-flex items-center gap-1 text-[13px] font-semibold text-primary hover:underline">
                    <?= e($n['label']) ?> <?= icon('arrow_forward', 'text-[16px]') ?>
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <!-- Kontak & status -->
      <div class="space-y-5">
        <section class="card p-5">
          <h2 class="font-bold text-ink-900 text-sm">Status Anda</h2>
          <?php if ($siswa): ?>
            <dl class="mt-3 space-y-2.5 text-[13px]">
              <div class="flex items-start justify-between gap-3">
                <dt class="text-ink-500">No. pendaftaran</dt>
                <dd class="font-mono font-semibold text-ink-900 text-right"><?= e($siswa['no_pendaftaran']) ?></dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-ink-500">Tahap</dt>
                <dd class="text-right"><span class="badge badge-<?= e($tahap['persen'] >= 100 ? 'green' : 'amber') ?>"><?= e($tahap['label']) ?></span></dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-ink-500">Verifikasi data</dt>
                <dd class="text-right"><span class="badge badge-<?= e($verifikasi[1]) ?>"><?= e($verifikasi[0]) ?></span></dd>
              </div>
              <div class="flex items-start justify-between gap-3">
                <dt class="text-ink-500">Hasil seleksi</dt>
                <dd class="text-right">
                  <?php if ($pengumumanAktif): $sel = $labelSeleksi[$siswa['status_seleksi']] ?? [$siswa['status_seleksi'], 'gray']; ?>
                    <span class="badge badge-<?= e($sel[1]) ?>"><?= e($sel[0]) ?></span>
                  <?php else: ?>
                    <span class="badge badge-gray">Belum Diumumkan</span>
                  <?php endif; ?>
                </dd>
              </div>
            </dl>
            <a href="<?= url('app/siswa/ppdb.php') ?>" class="btn btn-outline btn-block btn-sm mt-4">Buka PPDB &amp; Seleksi</a>
          <?php else: ?>
            <p class="mt-2 text-[13px] text-ink-500">Data pendaftaran belum terhubung ke akun ini. Hubungi panitia SPMB.</p>
          <?php endif; ?>
        </section>

        <section class="card p-5">
          <h2 class="font-bold text-ink-900 text-sm">Kontak Panitia</h2>
          <dl class="mt-3 space-y-2.5 text-[13px]">
            <div>
              <dt class="text-ink-400">Sekolah</dt>
              <dd class="font-semibold text-ink-900"><?= e($PENGATURAN['nama_sekolah'] ?? APP_SHORT) ?></dd>
            </div>
            <?php if (!empty($PENGATURAN['kontak_wa_panitia'])): ?>
              <div>
                <dt class="text-ink-400">WhatsApp Panitia</dt>
                <dd class="font-semibold text-ink-900"><?= e($PENGATURAN['kontak_wa_panitia']) ?></dd>
              </div>
            <?php endif; ?>
            <?php if (!empty($PENGATURAN['email_sekolah'])): ?>
              <div>
                <dt class="text-ink-400">Email</dt>
                <dd class="font-semibold text-ink-900 break-all"><?= e($PENGATURAN['email_sekolah']) ?></dd>
              </div>
            <?php endif; ?>
            <?php if (!empty($PENGATURAN['alamat_sekolah'])): ?>
              <div>
                <dt class="text-ink-400">Alamat</dt>
                <dd class="text-ink-700"><?= e($PENGATURAN['alamat_sekolah']) ?></dd>
              </div>
            <?php endif; ?>
          </dl>
          <?php if ($waPanitia !== ''): ?>
            <a href="https://wa.me/<?= e($waPanitia) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-block btn-sm mt-4">
              <?= icon('chat') ?> Hubungi Panitia
            </a>
          <?php endif; ?>
        </section>

        <section class="card p-5 bg-canvas">
          <p class="text-[12px] text-ink-500">
            Pengumuman resmi juga disampaikan melalui WhatsApp orang tua / wali yang terdaftar. Pastikan nomor pada menu
            <a class="font-semibold text-primary hover:underline" href="<?= url('app/siswa/data-siswa.php?tab=data-diri') ?>">Data Siswa</a> selalu aktif.
          </p>
        </section>
      </div>
    </div>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
