<?php
/* ==================================================================
 * VERIFIKASI PEMBAYARAN — antrean kerja bendahara (Fase 4).
 *   Halaman khusus tindakan: saring bukti, buka bukti, setujui / tolak.
 *   Angka monitoring ada di Dashboard Bendahara (app/bendahara/index.php).
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';

/* Hak akses: bendahara (keputusan 2026-10-28 — verifikasi pembayaran adalah
 * kewenangan bendahara, operator hanya melihat status) + admin sebagai
 * superuser yang dipakai halaman keuangan lainnya. */
require_role(['bendahara', 'admin']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

/* Saringan antrean — kunci URL ?s= , isi = status_raw yang ikut tampil.
 * Default PENDING: halaman ini ruang kerja, bukan laporan seluruh transaksi. */
$FILTER = [
    'PENDING'   => ['label' => 'Menunggu',          'isi' => ['PENDING']],
    'REJECTED'  => ['label' => 'Ditolak',           'isi' => ['REJECTED']],
    'MASUK'     => ['label' => 'Sudah Terverifikasi', 'isi' => ['VERIFIED', 'LUNAS', 'ANGSURAN_1', 'ANGSURAN_2']],
    'SEMUA'     => ['label' => 'Semua Transaksi',   'isi' => null],
];
$s = strtoupper(trim((string) ($_GET['s'] ?? '')));
if (!isset($FILTER[$s])) {
    $s = 'PENDING';
}
$setIsi = $FILTER[$s]['isi'];

$semuaTransaksi = daftar_pembayaran();
$pembayaran = $setIsi === null
    ? $semuaTransaksi
    : array_values(array_filter($semuaTransaksi, function ($p) use ($setIsi) {
        return in_array($p['status_raw'], $setIsi, true);
    }));

/* Jumlah per chip dihitung dari daftar yang sama agar angkanya konsisten
 * dengan tabel yang tampil. */
$hitung = ['PENDING' => 0, 'REJECTED' => 0, 'MASUK' => 0, 'SEMUA' => count($semuaTransaksi)];
foreach ($semuaTransaksi as $p) {
    if ($p['status_raw'] === 'PENDING') {
        $hitung['PENDING']++;
    } elseif ($p['status_raw'] === 'REJECTED') {
        $hitung['REJECTED']++;
    } elseif (in_array($p['status_raw'], ['VERIFIED', 'LUNAS', 'ANGSURAN_1', 'ANGSURAN_2'], true)) {
        $hitung['MASUK']++;
    }
}

$totalSaringan = count($pembayaran);
$nilaiSaringan = 0.0;
foreach ($pembayaran as $p) {
    $nilaiSaringan += (float) $p['nominal'];
}

$title     = 'Verifikasi Pembayaran SPMB';
$desc      = 'Antrean pembayaran bendahara: periksa bukti transfer, setujui atau tolak dengan alasan.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'pembayaran';
$badgeRole  = 'BENDAHARA';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik' => null, 'SPMB' => null, 'Verifikasi Pembayaran' => null];
$pageActions = '
  <a href="' . url('app/bendahara/index.php') . '" class="btn btn-outline hidden md:inline-flex">' . icon('dashboard') . ' Dashboard Bendahara</a>
  <a href="' . url('app/operator/export.php?rekap=pembayaran') . '" class="btn btn-outline hidden md:inline-flex">' . icon('download') . ' Export CSV</a>
';
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
?>

<div class="lg:pl-[264px]">

  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1440px]">

    <?php if ($flash): ?>
      <div class="mb-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <!-- ====================== JUDUL ====================== -->
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-amber"><?= dot('amber') ?> <?= (int) $hitung['PENDING'] ?> menunggu tindakan</span>
          <span class="badge badge-gray">Rekening: <?= e($PENGATURAN['nama_bank']) ?></span>
          <span class="badge badge-gray"><?= e($PENGATURAN['gelombang']) ?> · <?= e($PENGATURAN['tahun_ajaran']) ?></span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Verifikasi Pembayaran</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Buka bukti transfer, cocokkan nominal dan rekening pengirim, lalu setujui atau tolak.
          Bukti yang ditolak dapat diunggah ulang oleh orang tua/wali.
        </p>
      </div>

      <div class="flex flex-wrap gap-2.5 shrink-0">
        <a href="<?= url('app/bendahara/rekap.php') ?>" class="btn btn-outline">
          <?= icon('print') ?> Cetak Rekap Keuangan
        </a>
        <a href="<?= url('app/bendahara/index.php') ?>" class="btn btn-ghost md:hidden">
          <?= icon('dashboard') ?> Dashboard
        </a>
      </div>
    </div>

    <!-- ====================== CHIP STATUS ====================== -->
    <div class="mt-5 flex items-center gap-2 flex-wrap">
      <?php foreach ($FILTER as $k => $f): ?>
        <a href="<?= url('app/bendahara/pembayaran.php' . ($k === 'PENDING' ? '' : '?s=' . $k)) ?>"
           class="btn btn-sm <?= $s === $k ? 'btn-primary shadow-drop' : 'btn-outline' ?>">
          <?= e($f['label']) ?>
          <span class="ml-1.5 font-mono <?= $s === $k ? 'text-white/80' : 'text-ink-400' ?>"><?= (int) $hitung[$k] ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- ====================== TOOLBAR ====================== -->
    <div class="mt-4 flex flex-col md:flex-row md:items-center gap-3">
      <div class="relative flex-1 max-w-md">
        <span class="ms absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-ink-400">search</span>
        <input id="table-search" class="input pl-10" type="search" placeholder="Cari No. REG, nama siswa, jenis pembayaran...">
      </div>
      <div class="flex-1"></div>
      <span class="text-[13px] text-ink-500">
        <span class="font-semibold text-ink-900"><?= $totalSaringan ?></span> transaksi
        · <span class="font-semibold text-ink-900"><?= rupiah($nilaiSaringan) ?></span>
      </span>
    </div>

    <!-- ====================== TABEL PEMBAYARAN ====================== -->
    <div class="mt-4 card overflow-hidden">
      <div class="overflow-x-auto scroll-thin">
        <table id="data-table" class="w-full min-w-[960px] border-collapse" data-paginate="25">
          <thead class="thead">
            <tr>
              <th class="th">No. Pendaftaran &amp; Waktu</th>
              <th class="th">Calon Siswa &amp; Asal Sekolah</th>
              <th class="th">Jenis Pembayaran</th>
              <th class="th">Nominal &amp; Bukti</th>
              <th class="th">Status</th>
              <th class="th text-right">Aksi Bendahara</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <?php if (!$pembayaran): ?>
              <tr>
                <td class="td text-center text-ink-400" colspan="6">
                  <?= $s === 'PENDING'
                        ? 'Antrean kosong — semua bukti pembayaran sudah diputus.'
                        : 'Tidak ada transaksi pada saringan ini.' ?>
                </td>
              </tr>
            <?php endif; ?>

            <?php foreach ($pembayaran as $p): ?>
              <tr class="tr-hover" data-status="<?= e($p['status_raw']) ?>">
                <td class="td">
                  <button class="font-mono text-[13px] font-semibold text-primary hover:underline text-left"
                          data-modal-open="modal-detail"
                          data-md-reg="<?= e($p['reg']) ?>"
                          data-md-nama="<?= e($p['nama']) ?>"
                          data-md-nisn="<?= e($p['nisn']) ?>"
                          data-md-sekolah="<?= e($p['sekolah']) ?>"
                          data-md-wali="<?= e($p['wali']) ?>"
                          data-md-wa="<?= e($p['wa']) ?>"
                          data-md-jenis="<?= e($p['jenis']) ?>"
                          data-md-nominal="<?= e(rupiah($p['nominal'])) ?>"
                          data-md-status="<?= e($p['status']) ?>"
                          data-md-keterangan="<?= e($p['keterangan'] ?: '—') ?>"
                          data-md-verifier="<?= e($p['verifier'] ?: 'Belum diverifikasi') ?>"
                          data-md-verified="<?= e($p['verified'] ? bulan($p['verified']) . ', ' . date('H:i', strtotime($p['verified'])) . ' WIB' : '—') ?>">
                    <?= e($p['reg']) ?>
                  </button>
                  <div class="text-[11px] text-ink-400"><?= bulan($p['waktu']) ?></div>
                </td>

                <td class="td">
                  <div class="font-semibold text-ink-900"><?= e($p['nama']) ?></div>
                  <div class="text-[12px] text-ink-500"><?= e($p['sekolah']) ?> · WA <?= e($p['wa']) ?></div>
                </td>

                <td class="td">
                  <span class="font-medium text-ink-700"><?= e($p['jenis']) ?></span>
                  <div class="text-[11px] text-ink-400">Transfer <?= e($PENGATURAN['nama_bank']) ?></div>
                </td>

                <td class="td">
                  <div class="font-bold text-ink-900"><?= rupiah($p['nominal']) ?></div>
                  <?php if ($p['bukti']): ?>
                    <?php
                    $namaBukti = basename((string) $p['bukti']);
                    $tipeBukti = strtolower(pathinfo($namaBukti, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'gambar';
                    ?>
                    <button type="button"
                            class="mt-1 inline-flex items-center gap-1 text-[12px] font-semibold text-primary hover:underline"
                            data-pratinjau-url="<?= e(url('app/api/dokumen.php?f=' . rawurlencode($namaBukti))) ?>"
                            data-pratinjau-nama="Bukti Transfer <?= e($p['reg']) ?>"
                            data-pratinjau-tipe="<?= e($tipeBukti) ?>"
                            data-pratinjau-meta="<?= e(($tipeBukti === 'pdf' ? 'PDF' : 'Gambar') . ' · ' . $p['jenis'] . ' · ' . rupiah($p['nominal']) . ' · dikirim ' . bulan($p['waktu'])) ?>">
                      <?= icon('receipt', 'text-[15px]') ?> Lihat Bukti
                    </button>
                  <?php else: ?>
                    <span class="mt-1 inline-flex items-center gap-1 text-[12px] text-ink-400">
                      <?= icon('receipt', 'text-[15px]') ?> Bukti Belum Ada
                    </span>
                  <?php endif; ?>
                </td>

                <td class="td"><?= badge($p['status'], $p['tone']) ?></td>

                <td class="td text-right">
                  <?php if ($p['pending']): ?>
                    <div class="inline-flex flex-wrap items-center gap-2 justify-end">
                      <?php
                      /* Fase 4: tombol satu klik hanya untuk pembayaran formulir
                       * pendaftar yang belum punya akun & tahapnya masih menunggu verifikasi. */
                      $bolehKirimAkun = ($p['jenis'] === 'Biaya Formulir')
                          && empty($p['user_id'])
                          && in_array($p['tahap'] ?? '', ['MENUNGGU_VERIFIKASI_BAYAR', 'BAYAR_TERVERIFIKASI'], true);
                      ?>
                      <?php if ($bolehKirimAkun): ?>
                        <form method="post" action="<?= url('app/bendahara/verifikasi.php') ?>" class="inline"
                              onsubmit="return confirm('Verifikasi pembayaran & kirim akun untuk <?= e($p['reg']) ?>? Kredensial dikirim via WhatsApp.')">
                          <?= csrf_field() ?>
                          <input type="hidden" name="pembayaran_id" value="<?= e($p['id']) ?>">
                          <input type="hidden" name="aksi" value="verifikasi_kirim_akun">
                          <input type="hidden" name="saringan" value="<?= e($s) ?>">
                          <button type="submit" class="btn btn-sm btn-primary shadow-drop" title="Setujui + buat akun + kirim kredensial WA">
                            <?= icon('send', 'text-[15px]') ?> Verifikasi &amp; Kirim Akun
                          </button>
                        </form>
                      <?php endif; ?>

                      <form method="post" action="<?= url('app/bendahara/verifikasi.php') ?>" class="inline"
                            onsubmit="return confirm('Verifikasi pembayaran <?= e($p['reg']) ?> — <?= e($p['jenis']) ?> sebesar <?= e(rupiah($p['nominal'])) ?>?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="pembayaran_id" value="<?= e($p['id']) ?>">
                        <input type="hidden" name="aksi" value="verifikasi">
                        <input type="hidden" name="saringan" value="<?= e($s) ?>">
                        <button type="submit" class="btn btn-sm btn-green"><?= icon('task_alt', 'text-[15px]') ?> Verifikasi</button>
                      </form>

                      <button class="btn btn-sm btn-danger"
                              data-modal-open="modal-tolak"
                              data-md-id="<?= e($p['id']) ?>"
                              data-md-reg="<?= e($p['reg']) ?>"
                              data-md-nama="<?= e($p['nama']) ?>">
                        <?= icon('close', 'text-[15px]') ?> Tolak
                      </button>
                    </div>
                  <?php else: ?>
                    <button class="btn btn-sm btn-outline"
                            data-modal-open="modal-detail"
                            data-md-reg="<?= e($p['reg']) ?>"
                            data-md-nama="<?= e($p['nama']) ?>"
                            data-md-nisn="<?= e($p['nisn']) ?>"
                            data-md-sekolah="<?= e($p['sekolah']) ?>"
                            data-md-wali="<?= e($p['wali']) ?>"
                            data-md-wa="<?= e($p['wa']) ?>"
                            data-md-jenis="<?= e($p['jenis']) ?>"
                            data-md-nominal="<?= e(rupiah($p['nominal'])) ?>"
                            data-md-status="<?= e($p['status']) ?>"
                            data-md-keterangan="<?= e($p['keterangan'] ?: '—') ?>"
                            data-md-verifier="<?= e($p['verifier'] ?: 'Belum diverifikasi') ?>"
                            data-md-verified="<?= e($p['verified'] ? bulan($p['verified']) . ', ' . date('H:i', strtotime($p['verified'])) . ' WIB' : '—') ?>">
                      <?= icon('visibility', 'text-[15px]') ?> Detail
                    </button>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="px-4 py-3.5 border-t border-line bg-white flex flex-col md:flex-row items-center justify-between gap-3" data-pager-for="data-table">
        <p class="text-[13px] text-ink-500" data-pager-info></p>
        <nav class="flex flex-wrap items-center justify-center gap-1.5" data-pager-nav aria-label="Halaman"></nav>
        <label class="flex items-center gap-2 text-[13px] text-ink-500">Per halaman
          <select class="select w-20" data-pager-size><option>10</option><option selected>25</option><option>50</option></select>
        </label>
      </div>
    </div>

    <!-- ====================== AKSI MASSAL ANTREAN ====================== -->
    <section class="mt-5 card p-5">
      <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-start gap-3">
          <span class="icon-box bg-[#FFF7ED] text-[#EA580C] shrink-0"><?= icon('notifications_active') ?></span>
          <div>
            <h2 class="font-semibold text-ink-900 text-sm">Pengingat Jatuh Tempo Angsuran</h2>
            <p class="mt-1 text-[13px] text-ink-500 max-w-2xl">
              Mengirim pengitahuan ke siswa/ortu yang angsurannya mendekati atau melewati jatuh tempo
              (in-app dan WhatsApp bila gateway aktif).
            </p>
          </div>
        </div>
        <form method="post" action="<?= url('app/bendahara/index.php') ?>" class="shrink-0"
              onsubmit="return confirm('Kirim pengingat jatuh tempo ke siswa yang angsurannya mendekati atau melewati jatuh tempo?<?= angsuran_pengingat_wa_aktif() ? ' Pesan WhatsApp juga akan dikirim ke orang tua/wali.' : '' ?>')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="pengingat_jatuh_tempo">
          <button type="submit" class="btn btn-outline">
            <?= icon('send') ?> Kirim Pengingat
          </button>
        </form>
      </div>
    </section>

    <!-- Catatan SOP -->
    <div class="mt-4 rounded-2xl bg-primary-light border border-primary/15 p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
      <div class="flex items-start gap-3">
        <span class="icon-box bg-primary text-white shrink-0"><?= icon('info') ?></span>
        <div>
          <p class="font-semibold text-ink-900 text-sm">Prosedur Verifikasi Pembayaran</p>
          <p class="text-[13px] text-ink-500">
            Cocokkan nominal &amp; rekening pengirim pada bukti transfer. Status <strong>VERIFIED</strong> menandakan
            dana telah diterima; <strong>REJECTED</strong> mewajibkan alasan agar siswa dapat mengunggah ulang.
            Untuk <?= e(label_biaya_pendaftaran()) ?>, sistem menjumlahkan seluruh dana terverifikasi: angsuran 1
            berstatus <strong>ANGSURAN_1</strong>, dan tagihan menjadi <strong>LUNAS</strong> otomatis setelah totalnya
            menutup biaya pendaftaran — baik lewat 2 angsuran maupun sekali bayar.
          </p>
        </div>
      </div>
      <a href="<?= url('app/operator/panduan.php#verifikasi-pembayaran-bendahara') ?>" class="btn btn-sm btn-outline shrink-0">Panduan SOP Keuangan <?= icon('arrow_forward', 'text-[16px]') ?></a>
    </div>

  </main>
</div>

<!-- ====================== MODAL DETAIL PEMBAYARAN ====================== -->
<div id="modal-detail" class="modal-root hidden fixed inset-0 z-[60]">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-start sm:items-center justify-center p-4 sm:p-6 overflow-y-auto scroll-thin">
    <div class="animate-pop w-full max-w-2xl bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden my-auto">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
        <div class="flex items-start gap-3 min-w-0">
          <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon('receipt_long') ?></span>
          <div class="min-w-0">
            <h3 class="font-bold text-ink-900 text-lg leading-tight">
              Detail Bukti Pembayaran
              <span data-md-fill="reg" class="font-mono text-primary text-sm">#REG</span>
            </h3>
            <p class="text-[13px] text-ink-500 truncate">Nama: <strong data-md-fill="nama" class="text-ink-700">—</strong></p>
          </div>
        </div>
        <button class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5 grid sm:grid-cols-2 gap-4">
        <div class="rounded-xl border border-line p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Data Calon Siswa</p>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-ink-500">NISN</dt><dd data-md-fill="nisn" class="font-semibold text-ink-900">—</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Asal Sekolah</dt><dd data-md-fill="sekolah" class="font-semibold text-ink-900 text-right">—</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Wali</dt><dd data-md-fill="wali" class="font-semibold text-ink-900">—</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">WhatsApp</dt><dd data-md-fill="wa" class="font-mono text-ink-900">—</dd></div>
          </dl>
        </div>

        <div class="rounded-xl border border-line p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Rincian Pembayaran</p>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Jenis</dt><dd data-md-fill="jenis" class="font-semibold text-ink-900">—</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Nominal</dt><dd data-md-fill="nominal" class="font-bold text-ink-900">—</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Status</dt><dd data-md-fill="status" class="font-semibold text-ink-900">—</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Verifikator</dt><dd data-md-fill="verifier" class="font-semibold text-ink-900 text-right">—</dd></div>
          </dl>
        </div>

        <div class="sm:col-span-2 rounded-xl border border-line p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Bukti Transfer &amp; Catatan</p>
          <div class="mt-3 rounded-xl bg-canvas border border-dashed border-line-mid p-4 flex items-center gap-3">
            <span class="icon-box bg-white border border-line text-ink-400"><?= icon('image') ?></span>
            <div class="text-sm">
              <p class="font-medium text-ink-900">Pratinjau bukti transfer</p>
              <p class="text-xs text-ink-400">Buka bukti ukuran penuh lewat tombol "Lihat Bukti" pada kolom Nominal &amp; Bukti.</p>
            </div>
          </div>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Catatan</dt><dd data-md-fill="keterangan" class="font-semibold text-ink-900 text-right">—</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Diverifikasi</dt><dd data-md-fill="verified" class="font-semibold text-ink-900 text-right">—</dd></div>
          </dl>
        </div>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end">
        <button class="btn btn-outline" data-modal-close>Tutup</button>
      </div>

    </div>
  </div>
</div>

<!-- ====================== MODAL TOLAK ====================== -->
<div id="modal-tolak" class="modal-root hidden fixed inset-0 z-[60]">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
    <form method="post" action="<?= url('app/bendahara/verifikasi.php') ?>"
          class="animate-pop w-full max-w-lg bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="tolak">
      <input type="hidden" name="pembayaran_id" data-md-fill="id" value="">
      <input type="hidden" name="saringan" value="<?= e($s) ?>">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
        <div class="flex items-start gap-3">
          <span class="icon-box bg-[#FEF2F2] text-[#B42318] shrink-0"><?= icon('cancel') ?></span>
          <div>
            <h3 class="font-bold text-ink-900 text-lg leading-tight">Tolak Pembayaran</h3>
            <p class="text-[13px] text-ink-500">
              <span data-md-fill="reg" class="font-mono text-primary">#REG</span> — <span data-md-fill="nama">—</span>
            </p>
          </div>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5">
        <label class="label" for="keterangan">Alasan penolakan <span class="text-red-600">*</span></label>
        <textarea id="keterangan" name="keterangan" class="textarea" rows="3" required
                  placeholder="Contoh: bukti transfer tidak terbaca / nominal tidak sesuai."></textarea>
        <p class="hint">Alasan dikirim via WhatsApp ke orang tua, dan pendaftar dapat mengunggah ulang bukti.</p>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-danger"><?= icon('close') ?> Tolak Pembayaran</button>
      </div>
    </form>
  </div>
</div>

<!-- ====================== MODAL PRATINJAU BUKTI ====================== -->
<div id="modal-pratinjau-bukti" class="modal-root hidden fixed inset-0 pratinjau-modal" role="dialog" aria-modal="true" aria-labelledby="bukti-judul">
  <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm animate-fade" data-modal-close></div>
  <div class="pratinjau-lapis">
    <div class="pratinjau-panel card shadow-modal animate-pop">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h3 id="bukti-judul" class="font-bold text-sm text-ink-900 truncate">Bukti Pembayaran</h3>
          <p id="bukti-meta" class="mt-0.5 text-[11px] text-ink-500 truncate"></p>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0"
                data-modal-close aria-label="Tutup pratinjau"><?= icon('close') ?></button>
      </div>
      <div id="bukti-isi" class="pratinjau-isi"></div>
      <div class="pratinjau-kaki">
        <button type="button" class="btn btn-sm btn-outline" data-modal-close>Tutup</button>
      </div>
    </div>
  </div>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<?php if ($flash): ?>
<script>window.APP_FLASH = <?= json_encode(['tipe' => $flash['tipe'], 'pesan' => $flash['pesan']], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  (function () {
    var modal = document.getElementById('modal-pratinjau-bukti');
    if (!modal) return;

    var judul = document.getElementById('bukti-judul');
    var meta  = document.getElementById('bukti-meta');
    var isi   = document.getElementById('bukti-isi');
    var tombolTutup = modal.querySelector('.icon-box[data-modal-close]');

    function tutup() {
      modal.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
      isi.innerHTML = '';
    }

    function buka(url, nama, tipe, keterangan) {
      if (!url) return;

      judul.textContent  = nama || 'Bukti Pembayaran';
      meta.textContent   = keterangan || '';
      isi.innerHTML      = '';

      if (tipe === 'pdf') {
        var bingkai = document.createElement('iframe');
        bingkai.src = url;
        bingkai.title = nama || 'Bukti Pembayaran';
        bingkai.className = 'pratinjau-pdf';
        isi.appendChild(bingkai);
      } else {
        var gambar = document.createElement('img');
        gambar.src = url;
        gambar.alt = nama || 'Bukti Pembayaran';
        gambar.className = 'pratinjau-gambar';
        isi.appendChild(gambar);
      }

      modal.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
      if (tombolTutup) tombolTutup.focus();
    }

    document.querySelectorAll('[data-pratinjau-url]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        buka(btn.getAttribute('data-pratinjau-url'),
             btn.getAttribute('data-pratinjau-nama'),
             btn.getAttribute('data-pratinjau-tipe'),
             btn.getAttribute('data-pratinjau-meta'));
      });
    });

    modal.querySelectorAll('[data-modal-close]').forEach(function (el) {
      el.addEventListener('click', tutup);
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !modal.classList.contains('hidden')) tutup();
    });
  })();
</script>
</body>
</html>
