<?php
/**
 * NOTIFIKASI & PENGUMUMAN — portal siswa (Tahap 2).
 *
 * SATU halaman untuk semua pemberitahuan siswa: pengumuman panitia yang
 * sudah terbit, lalu notifikasi per penerima (tabel notifikasi_siswa) —
 * hasil verifikasi berkas & data, keputusan pembayaran, hasil seleksi.
 * Dulu pengumuman punya menu & halaman sendiri; kini digabung agar siswa
 * tidak bingung memilih antara "Notifikasi" dan "Pengumuman".
 */
$title = 'Notifikasi & Pengumuman';
$desc  = 'Pemberitahuan status pendaftaran, pembayaran, berkas, seleksi, dan pengumuman panitia.';
require __DIR__ . '/_bootstrap.php';

$keHalaman = url('app/siswa/notifikasi.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        header('Location: ' . $keHalaman);
        exit;
    }

    $aksi = (string) ($_POST['aksi'] ?? '');
    if (!$siswa) {
        flash('error', 'Akun ini belum terhubung ke data pendaftaran.');
        header('Location: ' . $keHalaman);
        exit;
    }

    if ($aksi === 'tandai_semua') {
        notif_siswa_tandai_dibaca($siswa['id']);
        flash('sukses', 'Semua notifikasi ditandai sudah dibaca.');
        header('Location: ' . $keHalaman);
        exit;
    }

    if ($aksi === 'tandai_dibaca') {
        notif_siswa_tandai_dibaca($siswa['id'], (string) ($_POST['id'] ?? ''));
        flash('sukses', 'Notifikasi ditandai sudah dibaca.');
        header('Location: ' . $keHalaman);
        exit;
    }

    if ($aksi === 'buka') {
        /* Tautan diambil dari baris milik siswa ini — bukan dari input POST. */
        $baris = q_all(
            'SELECT tautan FROM notifikasi_siswa WHERE id = ? AND pendaftar_id = ? LIMIT 1',
            [(string) ($_POST['id'] ?? ''), (string) $siswa['id']]
        );
        $tautan = $baris ? (string) ($baris[0]['tautan'] ?? '') : '';
        notif_siswa_tandai_dibaca($siswa['id'], (string) ($_POST['id'] ?? ''));
        header('Location: ' . ($tautan !== '' ? $tautan : $keHalaman));
        exit;
    }

    flash('error', 'Aksi tidak dikenali.');
    header('Location: ' . $keHalaman);
    exit;
}

$flash = flash();

/* Pengumuman panitia yang sudah terbit (isi lengkap, terbaru & penting dulu).
 * Notifikasi turunannya tidak ditampilkan lagi di feed agar tidak dobel —
 * barisnya ditandai dibaca karena isinya sudah tampil di bagian atas. */
$pengumumanTerbit = pengumuman_umum_terbit(20);

$daftar = [];
if ($siswa) {
    if ($pengumumanTerbit) {
        notif_siswa_tandai_dibaca_tipe($siswa['id'], 'PENGUMUMAN_UMUM');
    }
    foreach (notif_siswa_daftar($siswa['id'], 200) as $n) {
        if ($n['tipe'] === 'PENGUMUMAN_UMUM') {
            continue;
        }
        $daftar[] = $n;
    }
}
$jumlah = $siswa ? notif_siswa_belum_dibaca($siswa['id']) : 0;

/* Informasi pendaftaran yang bukan notifikasi (status saat ini, bukan peristiwa). */
$info = [];

if ($pengumumanAktif) {
    $info[] = [
        'ikon'    => 'campaign',
        'tone'    => 'green',
        'judul'   => 'Hasil seleksi resmi telah diumumkan',
        'pesan'   => 'Hasil seleksi SPMB ' . ($PENGATURAN['tahun_ajaran'] ?? '') . ' sudah dapat dilihat.',
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
        'pesan'   => 'Panitia menargetkan pengumuman hasil seleksi pada ' . bulan($PENGATURAN['tanggal_pengumuman']) . '.',
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
$activeMenu = 'notifikasi';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Siswa' => url('app/siswa/index.php'), 'Notifikasi & Pengumuman' => null];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue"><?= dot('blue') ?> <?= (int) $jumlah ?> belum dibaca</span>
          <span class="badge badge-gray"><?= e($PENGATURAN['gelombang'] ?? 'SPMB') ?> · <?= e($PENGATURAN['tahun_ajaran'] ?? '') ?></span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Notifikasi &amp; Pengumuman</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Semua pemberitahuan resmi untuk Anda dalam satu tempat: pengumuman panitia, verifikasi data &amp; berkas,
          keputusan pembayaran, dan hasil seleksi.
        </p>
      </div>
      <div class="flex gap-2.5 shrink-0">
        <?php if ($jumlah > 0): ?>
          <form method="post" action="<?= $keHalaman ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tandai_semua">
            <button type="submit" class="btn btn-outline"><?= icon('done_all') ?> Tandai Semua Dibaca</button>
          </form>
        <?php endif; ?>
        <a href="<?= url('app/siswa/index.php') ?>" class="btn btn-outline"><?= icon('arrow_back') ?> Dashboard</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">

      <div class="lg:col-span-2 space-y-6">

        <?php if (!$siswa): ?>
          <div class="card p-8 text-center">
            <span class="icon-box bg-canvas text-ink-400 mx-auto"><?= icon('link_off', 'text-[26px]') ?></span>
            <h2 class="mt-3 font-bold text-ink-900">Akun belum terhubung</h2>
            <p class="mt-1 text-sm text-ink-500">Data pendaftaran belum terhubung ke akun ini. Hubungi panitia SPMB.</p>
          </div>
        <?php endif; ?>

        <!-- Pengumuman panitia (terbit) -->
        <?php if ($pengumumanTerbit): ?>
          <section>
            <div class="flex items-center justify-between gap-3">
              <h2 class="font-bold text-ink-900">Pengumuman Panitia</h2>
              <span class="badge badge-gray"><?= count($pengumumanTerbit) ?></span>
            </div>
            <div class="mt-3 space-y-3">
              <?php foreach ($pengumumanTerbit as $p):
                $tone = !empty($p['penting']) ? $toneRingkas['amber'] : $toneRingkas['blue']; ?>
                <article class="card p-5 sm:p-6" id="pgn-<?= e($p['id']) ?>">
                  <div class="flex items-start gap-4">
                    <span class="icon-box shrink-0" style="background:<?= e($tone[0]) ?>;color:<?= e($tone[1]) ?>">
                      <?= icon('campaign', 'text-[21px]') ?>
                    </span>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-start justify-between gap-3 flex-wrap">
                        <h3 class="font-bold text-ink-900"><?= e($p['judul']) ?></h3>
                        <div class="flex items-center gap-2">
                          <?php if (!empty($p['penting'])): ?>
                            <span class="badge badge-amber">Penting</span>
                          <?php endif; ?>
                          <?php if (!empty($p['terbit_at'])): ?>
                            <span class="text-[11px] text-ink-400 whitespace-nowrap"><?= e(bulan($p['terbit_at'])) ?></span>
                          <?php endif; ?>
                        </div>
                      </div>
                      <p class="mt-1.5 text-[13px] text-ink-600 whitespace-pre-line"><?= e($p['isi']) ?></p>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- Notifikasi peristiwa -->
        <section>
          <div class="flex items-center justify-between gap-3">
            <h2 class="font-bold text-ink-900">Notifikasi</h2>
            <span class="badge badge-gray"><?= count($daftar) ?> total</span>
          </div>

          <div class="mt-3 space-y-3">
            <?php if ($siswa && !$daftar && !$pengumumanTerbit): ?>
              <div class="card p-8 text-center">
                <span class="icon-box bg-canvas text-ink-400 mx-auto"><?= icon('notifications_off', 'text-[26px]') ?></span>
                <h3 class="mt-3 font-bold text-ink-900">Belum ada notifikasi</h3>
                <p class="mt-1 text-sm text-ink-500">Pemberitahuan akan muncul di sini setiap kali ada pembaruan status pendaftaran Anda.</p>
              </div>
            <?php elseif ($siswa && !$daftar): ?>
              <div class="card p-6 text-center">
                <p class="text-sm text-ink-500">Belum ada notifikasi baru. Pengumuman panitia tampil di bagian atas halaman ini.</p>
              </div>
            <?php endif; ?>

            <?php foreach ($daftar as $n):
              $lbl    = notif_siswa_label($n['tipe']);
              $sudah  = $n['dibaca_at'] !== null;
              $punyaTautan = trim((string) ($n['tautan'] ?? '')) !== '';
            ?>
              <article class="card p-4 sm:p-5 <?= $sudah ? 'opacity-70' : '' ?>">
                <div class="flex flex-col sm:flex-row sm:items-start gap-3 sm:gap-4">
                  <span class="icon-box shrink-0 <?= $sudah ? 'bg-canvas text-ink-400' : 'bg-[#EFF6FF] text-[#2563EB]' ?>">
                    <?= icon($lbl[2], 'text-[20px]') ?>
                  </span>
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="badge badge-<?= e($lbl[1]) ?>"><?= $sudah ? dot('gray') : dot('blue') ?> <?= e($lbl[0]) ?></span>
                      <?php if (!$sudah): ?>
                        <span class="badge badge-blue">Baru</span>
                      <?php endif; ?>
                      <span class="text-[12px] text-ink-400"><?= e(date('d M Y H:i', strtotime($n['created_at']))) ?></span>
                    </div>
                    <h3 class="mt-2 font-bold text-ink-900 <?= $sudah ? 'text-[14px]' : 'text-[15px]' ?>"><?= e($n['judul']) ?></h3>
                    <p class="mt-1 text-[13px] text-ink-600 whitespace-pre-line"><?= e($n['pesan']) ?></p>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    <?php if ($punyaTautan): ?>
                      <form method="post" action="<?= $keHalaman ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="buka">
                        <input type="hidden" name="id" value="<?= e($n['id']) ?>">
                        <button type="submit" class="btn btn-outline btn-sm"><?= icon('open_in_new') ?> Buka</button>
                      </form>
                    <?php endif; ?>
                    <?php if (!$sudah): ?>
                      <form method="post" action="<?= $keHalaman ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="tandai_dibaca">
                        <input type="hidden" name="id" value="<?= e($n['id']) ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" title="Tandai sudah dibaca"><?= icon('done') ?></button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- Informasi pendaftaran (status saat ini, bukan peristiwa) -->
        <?php if ($info): ?>
          <section>
            <h2 class="font-bold text-ink-900">Informasi Pendaftaran</h2>
            <div class="mt-3 space-y-3">
              <?php foreach ($info as $n): $warna = $toneRingkas[$n['tone']] ?? $toneRingkas['gray']; ?>
                <article class="card p-4 sm:p-5">
                  <div class="flex items-start gap-3.5">
                    <span class="icon-box shrink-0" style="background:<?= e($warna[0]) ?>;color:<?= e($warna[1]) ?>">
                      <?= icon($n['ikon'], 'text-[19px]') ?>
                    </span>
                    <div class="min-w-0 flex-1">
                      <div class="flex items-start justify-between gap-3 flex-wrap">
                        <h3 class="font-semibold text-ink-900 text-[14px]"><?= e($n['judul']) ?></h3>
                        <?php if (!empty($n['tanggal'])): ?>
                          <span class="text-[11px] text-ink-400 whitespace-nowrap"><?= e(bulan($n['tanggal'])) ?></span>
                        <?php endif; ?>
                      </div>
                      <p class="mt-1 text-[13px] text-ink-600 whitespace-pre-line"><?= e($n['pesan']) ?></p>
                      <?php if (!empty($n['tautan'])): ?>
                        <a href="<?= e($n['tautan']) ?>" class="mt-2 inline-flex items-center gap-1 text-[13px] font-semibold text-primary hover:underline">
                          <?= e($n['label']) ?> <?= icon('arrow_forward', 'text-[16px]') ?>
                        </a>
                      <?php endif; ?>
                    </div>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

      </div>

      <!-- Status & kontak -->
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
            <a class="font-semibold text-primary hover:underline" href="<?= url('app/siswa/data-siswa.php') ?>">Data Siswa</a> selalu aktif.
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
