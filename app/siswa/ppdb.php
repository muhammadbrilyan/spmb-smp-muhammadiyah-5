<?php
/**
 * PPDB & SELEKSI — portal siswa (Fase 13).
 *
 * Ringkasan formulir, kelengkapan/pengajuan, jadwal tes & lokasi, ujian
 * daring, serta status tes. Hasil seleksi TIDAK lagi berada di sini — ia
 * menjadi menu tersendiri di app/siswa/hasil.php (lihat catatan redirect).
 */
$title = 'PPDB & Seleksi';
$desc  = 'Formulir pendaftaran, kelengkapan berkas, jadwal tes, dan status seleksi.';
require __DIR__ . '/_bootstrap.php';

/* Tautan lama (pesan WA & bookmark) menunjuk ke ?tab=hasil. Dibawa ke halaman
 * Hasil Seleksi yang baru agar tidak ada yang mati. */
if ((string) ($_GET['tab'] ?? '') === 'hasil') {
    header('Location: ' . url('app/siswa/hasil.php'));
    exit;
}

$tabs = [
    'formulir' => ['Formulir & Kelengkapan', 'how_to_reg'],
    'jadwal'   => ['Jadwal Tes', 'event'],
];
$tab = (string) ($_GET['tab'] ?? 'formulir');
if (!isset($tabs[$tab])) {
    $tab = 'formulir';
}

$flash = flash();

$labelMode = [
    'OFFLINE'    => ['Tes Offline di Sekolah', 'Datang langsung sesuai lokasi dan ruang yang tertera.'],
    'DARING'     => ['Tes Daring', 'Ujian dikerjakan online melalui tautan resmi panitia.'],
    'VIDEO_CALL' => ['Tes Video Call', 'Ujian dilaksanakan melalui panggilan video sesuai jadwal.'],
];
$tautanVideo = '';
if (!empty($jadwalSiswa['tautan_video'])) {
    $kandidat = trim((string) $jadwalSiswa['tautan_video']);
    if (preg_match('#^https?://#i', $kandidat) === 1) {
        $tautanVideo = $kandidat;
    }
}

/* Agenda = SELURUH jadwal berstatus Terjadwal, fungsi yang sama dipakai tombol
 * "Umumkan Semua Jadwal" operator — sehingga tabel pada kartu ini dan pesan
 * WhatsApp wali siswa tidak pernah berbeda isi. Bila jadwal siswa sendiri
 * belum berstatus Terjadwal, kartunya tetap ditampilkan sebagai satu baris. */
$labelMetode = ['OFFLINE' => 'Offline', 'DARING' => 'Daring', 'VIDEO_CALL' => 'Video Call'];
$agendaTes   = $siswa ? jadwal_terjadwal() : [];
$agendaBaris = $agendaTes ?: ($kartuUjian ? [$kartuUjian] : []);

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'ppdb';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Siswa' => url('app/siswa/index.php'), 'PPDB & Seleksi' => null];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue"><?= e($PENGATURAN['gelombang'] ?? 'SPMB') ?></span>
          <span class="badge badge-gray"><?= e($PENGATURAN['tahun_ajaran'] ?? '') ?></span>
          <?php if ($siswa): ?><span class="badge badge-<?= e($tahap['persen'] >= 100 ? 'green' : 'amber') ?>"><?= e($tahap['label']) ?></span><?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">PPDB &amp; Seleksi</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Pantau formulir, kelengkapan berkas, dan jadwal tes Anda. Hasil seleksi dibuka sendiri melalui menu
          <a href="<?= url('app/siswa/hasil.php') ?>" class="font-semibold text-primary hover:underline">Hasil Seleksi</a>.
        </p>
      </div>
      <a href="<?= url('app/siswa/index.php') ?>" class="btn btn-outline shrink-0"><?= icon('arrow_back') ?> Dashboard</a>
    </div>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <?php if (!$siswa): ?>
      <div class="mt-6 rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] p-5 text-sm text-[#92400E]">
        <p class="font-semibold">Data pendaftaran belum ditemukan untuk akun ini.</p>
        <p class="mt-1">Hubungi panitia SPMB untuk menghubungkan akun dengan data pendaftaran Anda.</p>
      </div>
    <?php else: ?>

      <!-- Progres tahap -->
      <section class="mt-6 card p-5 sm:p-6">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
          <div>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-primary">Tahap saat ini</p>
            <h2 class="mt-1 text-lg font-extrabold text-ink-900"><?= e($tahap['label']) ?></h2>
            <p class="mt-1 text-[13px] text-ink-500"><?= e($tahap['desc']) ?></p>
          </div>
          <span class="text-sm font-bold text-ink-900 shrink-0"><?= (int) $tahap['persen'] ?>%</span>
        </div>
        <div class="mt-3 h-2 rounded-full bg-line-soft overflow-hidden">
          <div class="h-full rounded-full <?= $tahap['persen'] >= 100 ? 'bg-emerald-500' : 'bg-primary' ?>" style="width:<?= (int) $tahap['persen'] ?>%"></div>
        </div>
      </section>

      <nav class="mt-5 card p-2 flex gap-1.5 overflow-x-auto scroll-thin" aria-label="Bagian PPDB">
        <?php foreach ($tabs as $key => $meta): $aktif = $key === $tab; ?>
          <a href="<?= url('app/siswa/ppdb.php?tab=' . $key) ?>"
             class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[13px] font-semibold whitespace-nowrap transition
                    <?= $aktif ? 'bg-primary-light text-primary' : 'text-ink-500 hover:bg-canvas' ?>"
             <?= $aktif ? 'aria-current="page"' : '' ?>>
            <?= icon($meta[1], 'text-[18px]') ?> <?= e($meta[0]) ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <?php if ($tab === 'formulir'): ?>
        <div class="mt-5 grid gap-5 lg:grid-cols-3">
          <section class="card lg:col-span-2 p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <div>
                <h2 class="font-bold text-ink-900">Formulir Pendaftaran</h2>
                <p class="mt-1 text-[13px] text-ink-500">No. pendaftaran <span class="font-mono font-semibold text-ink-700"><?= e($siswa['no_pendaftaran']) ?></span></p>
              </div>
              <span class="badge badge-<?= e($verifikasi[1]) ?>"><?= e($verifikasi[0]) ?></span>
            </div>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
              <div><dt class="label">Nama Calon Siswa</dt><dd class="text-sm font-semibold text-ink-900"><?= e($siswa['nama_siswa']) ?></dd></div>
              <div><dt class="label">Asal Sekolah</dt><dd class="text-sm text-ink-900"><?= e($siswa['asal_sekolah'] ?: '—') ?></dd></div>
              <div><dt class="label">Jalur Pendaftaran</dt><dd class="text-sm text-ink-900"><?= e($siswa['jalur'] ?: 'Reguler') ?></dd></div>
              <div><dt class="label">Tanggal Daftar</dt><dd class="text-sm text-ink-900"><?= e($siswa['created_at'] ? bulan($siswa['created_at']) : '—') ?></dd></div>
              <div><dt class="label">Status Akun</dt>
                <dd class="mt-0.5"><?php $akun = $labelAkun[$siswa['status_akun']] ?? [$siswa['status_akun'], 'gray']; ?><?= badge($akun[0], $akun[1]) ?></dd>
              </div>
              <div><dt class="label">Domisili</dt><dd class="text-sm text-ink-900"><?= !empty($siswa['is_luar_daerah']) ? 'Luar Daerah' : 'Dalam Daerah' ?></dd></div>
            </dl>

            <?php if (!empty($siswa['catatan_verifikasi_data'])): ?>
              <div class="mt-4 rounded-xl bg-[#FEF2F2] border border-red-200 px-4 py-3 text-[13px] text-[#991B1B]">
                <strong>Catatan panitia:</strong> <?= e($siswa['catatan_verifikasi_data']) ?>
              </div>
            <?php endif; ?>

            <div class="mt-5 flex flex-wrap gap-2.5 border-t border-line-soft pt-4">
              <a href="<?= url('app/siswa/data-siswa.php') ?>" class="btn btn-outline btn-sm"><?= icon('badge') ?> Periksa Data</a>
              <a href="<?= url('app/siswa/data-siswa.php') ?>" class="btn btn-outline btn-sm"><?= icon('folder_shared') ?> Berkas Persyaratan</a>
            </div>
          </section>

          <div class="space-y-5">
            <section class="card p-5">
              <h2 class="font-bold text-ink-900 text-sm">Kelengkapan Data</h2>
              <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $kelengkapan['persen'] ?><span class="text-base font-semibold text-ink-400">%</span></p>
              <p class="mt-1 text-[12px] text-ink-500"><?= (int) $kelengkapan['terisi'] ?>/<?= (int) $kelengkapan['total'] ?> butir wajib</p>
              <div class="mt-3 h-1.5 rounded-full bg-line-soft overflow-hidden">
                <div class="h-full rounded-full <?= $kelengkapan['persen'] >= 100 ? 'bg-emerald-500' : 'bg-primary' ?>" style="width:<?= (int) $kelengkapan['persen'] ?>%"></div>
              </div>
              <?php if ($kelengkapan['kurang']): ?>
                <p class="mt-3 text-[12px] text-[#92400E]">Kurang: <?= e(implode(', ', array_slice($kelengkapan['kurang'], 0, 4))) ?><?= count($kelengkapan['kurang']) > 4 ? ', …' : '' ?></p>
              <?php endif; ?>
            </section>

            <section class="card p-5">
              <h2 class="font-bold text-ink-900 text-sm">Berkas Persyaratan</h2>
              <p class="mt-2 text-sm text-ink-700">
                <strong class="text-ink-900"><?= (int) $kelengkapan['dok_terisi'] ?></strong> dari
                <strong class="text-ink-900"><?= (int) $kelengkapan['dok_wajib'] ?></strong> berkas wajib terunggah.
              </p>
              <?php
              $dokStatus = ['PENDING' => 0, 'VERIFIED' => 0, 'REJECTED' => 0];
              foreach ($dokumen as $d) {
                  if (!empty($d['file_url']) && isset($dokStatus[$d['status']])) {
                      $dokStatus[$d['status']]++;
                  }
              }
              ?>
              <ul class="mt-3 space-y-1.5 text-[12px] text-ink-500">
                <li>Menunggu verifikasi: <strong class="text-[#B45309]"><?= (int) $dokStatus['PENDING'] ?></strong></li>
                <li>Terverifikasi: <strong class="text-[#065F46]"><?= (int) $dokStatus['VERIFIED'] ?></strong></li>
                <li>Ditolak: <strong class="text-[#991B1B]"><?= (int) $dokStatus['REJECTED'] ?></strong></li>
              </ul>
              <a href="<?= url('app/siswa/data-siswa.php') ?>" class="btn btn-outline btn-block btn-sm mt-3">Buka Berkas</a>
            </section>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($tab === 'jadwal'): ?>
        <?php if ($kartuUjian): ?>
          <?php $mode = $labelMode[$jadwalSiswa['mode'] ?? 'OFFLINE'] ?? ['Tes', '']; ?>
          <div class="mt-5 grid gap-5 lg:grid-cols-3">
            <section class="card lg:col-span-2 p-5 sm:p-6" id="kartu-peserta">
              <div class="flex items-start justify-between gap-3 flex-wrap">
                <div>
                  <h2 class="font-bold text-ink-900">Kartu Peserta Ujian</h2>
                  <p class="mt-1 text-[13px] text-ink-500">Cetak &amp; bawa saat hari ujian (atau simpan tautan daring).</p>
                </div>
                <button type="button" class="btn btn-outline btn-sm no-print" onclick="cetakKartu()"><?= icon('print') ?> Cetak</button>
              </div>

              <div class="mt-4 rounded-2xl border-2 border-dashed border-primary/40 p-5 bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-line pb-3 flex-wrap">
                  <div class="flex items-center gap-3">
                    <img src="<?= e($PENGATURAN['logo_url'] ?? 'assets/img/logo.svg') ?>" alt="Logo" class="w-12 h-12">
                    <div>
                      <p class="font-bold text-ink-900 leading-tight"><?= e($PENGATURAN['nama_sekolah'] ?? APP_SHORT) ?></p>
                      <p class="text-[12px] text-ink-500">Kartu Peserta SPMB</p>
                    </div>
                  </div>
                  <p class="font-mono text-sm font-bold text-primary">No. Pendaftaran: <?= e($siswa['no_pendaftaran']) ?></p>
                </div>

                <div class="mt-4 flex flex-col sm:flex-row sm:items-end gap-4">
                  <div class="flex-1">
                    <p class="text-[12px] text-ink-500">Nama Peserta</p>
                    <p class="text-lg font-bold text-ink-900"><?= e($siswa['nama_siswa']) ?></p>
                    <p class="text-[13px] text-ink-500 mt-1">Asal Sekolah: <?= e($siswa['asal_sekolah']) ?></p>
                  </div>
                  <div class="rounded-xl bg-primary-light border border-primary/20 px-4 py-3 text-center">
                    <p class="text-[11px] text-ink-500 uppercase tracking-wide">Nomor Peserta</p>
                    <p class="text-2xl font-mono font-extrabold text-primary"><?= e($kartuUjian['nomor_peserta']) ?></p>
                  </div>
                </div>

                <div class="mt-4 border-t border-line-soft pt-4">
                  <p class="text-[11px] font-semibold uppercase tracking-wide text-ink-500">Agenda Tes</p>
                  <div class="mt-2 overflow-x-auto scroll-thin">
                    <table class="w-full border-collapse text-[13px]">
                      <thead>
                        <tr>
                          <th class="border border-line bg-canvas px-2 py-1.5 text-left font-semibold text-ink-700">Tanggal</th>
                          <th class="border border-line bg-canvas px-2 py-1.5 text-left font-semibold text-ink-700">Kegiatan</th>
                          <th class="border border-line bg-canvas px-2 py-1.5 text-left font-semibold text-ink-700">Waktu</th>
                          <th class="border border-line bg-canvas px-2 py-1.5 text-left font-semibold text-ink-700">Lokasi / Ruangan</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($agendaBaris as $ag): ?>
                          <tr>
                            <td class="border border-line px-2 py-1.5 align-top whitespace-nowrap">
                              <span class="font-semibold text-ink-900"><?= e(bulan_pendek($ag['tanggal'])) ?></span>
                              <span class="text-[11px] text-ink-500"><?= e(date('Y', strtotime((string) $ag['tanggal']))) ?></span>
                            </td>
                            <td class="border border-line px-2 py-1.5 align-top">
                              <span class="font-semibold text-ink-900"><?= e($ag['nama_kegiatan']) ?></span>
                              <span class="block text-[11px] text-ink-500 font-mono"><?= e($ag['kode']) ?></span>
                            </td>
                            <td class="border border-line px-2 py-1.5 align-top whitespace-nowrap">
                              <span class="font-semibold text-ink-900"><?= e(substr((string) $ag['jam_mulai'], 0, 5)) ?>–<?= e(substr((string) $ag['jam_selesai'], 0, 5)) ?></span>
                              <span class="block text-[11px] text-ink-500">WIB · <?= e($labelMetode[$ag['mode']] ?? $ag['mode']) ?></span>
                            </td>
                            <td class="border border-line px-2 py-1.5 align-top"><?= e(jadwal_lokasi_teks($ag)) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                  <?php if (count($agendaBaris) > 1): ?>
                    <p class="mt-2 text-[12px] text-ink-500">
                      Nomor peserta di atas berlaku pada kegiatan <strong><?= e($kartuUjian['nama_kegiatan']) ?></strong>;
                      seluruh kegiatan lain tetap wajib diikuti sesuai barisnya masing-masing.
                    </p>
                  <?php endif; ?>
                </div>

                <p class="mt-4 text-[12px] text-ink-500 border-t border-line-soft pt-3">
                  Mohon hadir 15 menit lebih awal. Bawa kartu ini &amp; alat tulis. Simpan kartu sampai proses seleksi selesai.
                </p>
              </div>
            </section>

            <div class="space-y-5">
              <section class="card p-5">
                <h2 class="font-bold text-ink-900 text-sm">Mode &amp; Lokasi Tes</h2>
                <p class="mt-2 text-sm font-semibold text-ink-900"><?= e($mode[0]) ?></p>
                <p class="mt-1 text-[13px] text-ink-500"><?= e($mode[1]) ?></p>
                <dl class="mt-3 space-y-2 text-[13px]">
                  <div class="flex justify-between gap-3"><dt class="text-ink-500">Lokasi</dt><dd class="font-semibold text-ink-900 text-right"><?= e($kartuUjian['lokasi'] ?: '—') ?></dd></div>
                  <div class="flex justify-between gap-3"><dt class="text-ink-500">Ruangan</dt><dd class="font-semibold text-ink-900 text-right"><?= e($kartuUjian['ruangan'] ?: ($jadwalSiswa['ruangan'] ?? '—')) ?></dd></div>
                  <div class="flex justify-between gap-3"><dt class="text-ink-500">Status Jadwal</dt><dd class="font-semibold text-ink-900 text-right"><?= e($jadwalSiswa['status'] ?? 'TERJADWAL') ?></dd></div>
                </dl>
              </section>

              <?php if (($jadwalSiswa['mode'] ?? 'OFFLINE') !== 'OFFLINE'): ?>
                <section class="card p-5">
                  <h2 class="font-bold text-ink-900 text-sm">Ujian Online</h2>
                  <?php if ($tautanVideo !== ''): ?>
                    <p class="mt-2 text-[13px] text-ink-500">Gunakan tautan resmi berikut sesuai jadwal:</p>
                    <a href="<?= e($tautanVideo) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary btn-block mt-3">
                      <?= icon('videocam') ?> Buka Ruang Ujian
                    </a>
                  <?php else: ?>
                    <p class="mt-2 text-[13px] text-ink-500">Tautan ujian belum diatur panitia. Periksa kembali mendekati jadwal.</p>
                  <?php endif; ?>
                </section>
              <?php endif; ?>
            </div>
          </div>
        <?php elseif ($pengumumanAktif): ?>
          <section class="mt-5 card p-6">
            <h2 class="font-bold text-ink-900">Jadwal Tes</h2>
            <p class="mt-2 text-sm text-ink-500">Hasil seleksi sudah diumumkan — jadwal tes tidak lagi ditampilkan.</p>
            <a href="<?= url('app/siswa/hasil.php') ?>" class="btn btn-outline btn-sm mt-4">Lihat Hasil Seleksi</a>
          </section>
        <?php else: ?>
          <section class="mt-5 card p-6">
            <h2 class="font-bold text-ink-900">Jadwal Tes</h2>
            <?php if (tahap_minimal((string) $siswa['tahap'], 'BERKAS_TERVERIFIKASI')): ?>
              <p class="mt-2 text-sm text-ink-500">Berkas sudah terverifikasi. Panitia sedang menyiapkan jadwal tes; kartu peserta akan muncul di halaman ini.</p>
            <?php else: ?>
              <p class="mt-2 text-sm text-ink-500">Jadwal tes tersedia setelah berkas persyaratan Anda terverifikasi panitia.</p>
              <a href="<?= url('app/siswa/data-siswa.php') ?>" class="btn btn-outline btn-sm mt-4">Periksa Berkas</a>
            <?php endif; ?>
          </section>
        <?php endif; ?>
      <?php endif; ?>

    <?php endif; ?>
  </main>
</div>

<div id="print-area" aria-hidden="true"></div>
<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  /* Cetak hanya kartu peserta ujian — sisa antarmuka portal disembunyikan. */
  function cetakKartu() {
    var kartu = document.getElementById('kartu-peserta');
    var host  = document.getElementById('print-area');
    if (!kartu || !host) { window.print(); return; }

    host.innerHTML = kartu.outerHTML;
    host.removeAttribute('aria-hidden');
    document.body.classList.add('mode-cetak-kartu');

    var selesai = function () {
      document.body.classList.remove('mode-cetak-kartu');
      host.innerHTML = '';
      host.setAttribute('aria-hidden', 'true');
      window.removeEventListener('afterprint', selesai);
    };
    window.addEventListener('afterprint', selesai);
    window.print();
  }
</script>
</body>
</html>
