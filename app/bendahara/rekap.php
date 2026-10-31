<?php
require_once __DIR__ . '/../../includes/config.php';

/* Rekap keuangan siap cetak. Verifikasi pembayaran adalah kewenangan
 * bendahara; admin & kepala sekolah boleh membuka laporan ini. */
require_role(['bendahara', 'admin', 'kepsek']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title     = 'Cetak Rekap Keuangan SPMB';
$desc      = 'Rekap pembayaran SPMB siap cetak: dana terverifikasi, menunggu verifikasi, dan ditolak.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* Filter status — sama dengan pilihan pada dashboard bendahara. */
$labelStatus = [
    'PENDING'    => 'Menunggu',
    'VERIFIED'   => 'Terverifikasi',
    'REJECTED'   => 'Ditolak',
    'LUNAS'      => 'Lunas',
    'ANGSURAN_1' => 'Angsuran 1',
    'ANGSURAN_2' => 'Angsuran 2',
];
$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
if (!isset($labelStatus[$status])) {
    $status = '';
}

$semua = daftar_pembayaran();
$baris = $status === ''
    ? $semua
    : array_values(array_filter($semua, function ($p) use ($status) {
        return $p['status_raw'] === $status;
    }));

/* Urut kronologis untuk laporan. */
usort($baris, function ($a, $b) {
    return strcmp((string) $a['waktu'], (string) $b['waktu']);
});

$ringkas = ['nominal' => 0.0, 'masuk' => 0.0, 'menunggu' => 0.0, 'ditolak' => 0.0, 'n_masuk' => 0];
foreach ($baris as $p) {
    $ringkas['nominal'] += (float) $p['nominal'];
    if (in_array($p['status_raw'], ['VERIFIED', 'LUNAS', 'ANGSURAN_1', 'ANGSURAN_2'], true)) {
        $ringkas['masuk'] += (float) $p['nominal'];
        $ringkas['n_masuk']++;
    } elseif ($p['status_raw'] === 'PENDING') {
        $ringkas['menunggu'] += (float) $p['nominal'];
    } elseif ($p['status_raw'] === 'REJECTED') {
        $ringkas['ditolak'] += (float) $p['nominal'];
    }
}

$jabatanTtd = trim((string) ($me['jabatan'] ?? ''));
if ($jabatanTtd === '') {
    $jabatanTtd = ['bendahara' => 'Bendahara SPMB', 'admin' => 'Administrator', 'kepsek' => 'Kepala Sekolah'][$me['role']] ?? 'Petugas SPMB';
}

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'keuangan';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik' => null, 'SPMB' => null, 'Keuangan & Verifikasi' => url('app/bendahara/index.php'), 'Cetak Rekap Keuangan' => null];
$pageActions = '<button type="button" class="btn btn-primary shadow-drop" onclick="window.print()">'
    . icon('print') . ' Cetak / Simpan PDF</button>';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">

  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <!-- ====================== KOP LAPORAN ====================== -->
    <div class="flex flex-col sm:flex-row items-start gap-4 border-b-2 border-ink-900/10 pb-5">
      <?php if (!empty($PENGATURAN['logo_url'])): ?>
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo <?= e($PENGATURAN['nama_sekolah']) ?>" class="w-14 h-14 shrink-0 object-contain">
      <?php endif; ?>
      <div class="min-w-0 flex-1">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-ink-400">Panitia SPMB</p>
        <h1 class="mt-1 text-xl sm:text-2xl font-bold tracking-tight text-ink-900"><?= e($PENGATURAN['nama_sekolah']) ?></h1>
        <p class="mt-1 text-[12px] text-ink-500"><?= e($PENGATURAN['alamat_sekolah']) ?></p>
        <?php if (!empty($PENGATURAN['email_sekolah']) || !empty($PENGATURAN['kontak_wa_panitia'])): ?>
          <p class="text-[12px] text-ink-500">
            <?php if (!empty($PENGATURAN['email_sekolah'])): ?><?= e($PENGATURAN['email_sekolah']) ?><?php endif; ?>
            <?php if (!empty($PENGATURAN['email_sekolah']) && !empty($PENGATURAN['kontak_wa_panitia'])): ?> · <?php endif; ?>
            <?php if (!empty($PENGATURAN['kontak_wa_panitia'])): ?>WA <?= e($PENGATURAN['kontak_wa_panitia']) ?><?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
      <div class="sm:text-right text-[12px] text-ink-500 shrink-0">
        <p class="font-bold text-ink-900 text-sm">REKAP KEUANGAN</p>
        <p><?= e($PENGATURAN['gelombang']) ?> · TA <?= e($PENGATURAN['tahun_ajaran']) ?></p>
        <p>Dicetak: <?= bulan(date('Y-m-d')) ?></p>
        <p>Oleh: <?= e($USER_LOGIN['nama']) ?></p>
      </div>
    </div>

    <!-- ====================== KONTROL (tidak tercetak) ====================== -->
    <div class="no-print mt-5 card p-4 flex flex-col md:flex-row md:items-end gap-3">
      <form method="get" action="<?= url('app/bendahara/rekap.php') ?>" class="flex flex-col sm:flex-row sm:items-end gap-3">
        <div>
          <label class="label" for="status">Filter status</label>
          <select id="status" name="status" class="select w-full sm:w-56">
            <option value="">Semua Status</option>
            <?php foreach ($labelStatus as $kode => $label): ?>
              <option value="<?= e($kode) ?>" <?= $status === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-outline"><?= icon('filter_alt') ?> Terapkan</button>
      </form>
      <div class="flex-1"></div>
      <div class="flex flex-wrap gap-2">
        <a href="<?= url('app/operator/export.php?rekap=pembayaran') ?>" class="btn btn-outline"><?= icon('download') ?> Unduh CSV</a>
        <a href="<?= url('app/bendahara/index.php') ?>" class="btn btn-outline"><?= icon('arrow_back') ?> Kembali</a>
      </div>
    </div>

    <!-- ====================== RINGKASAN ====================== -->
    <section class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <article class="card p-4">
        <p class="text-[12px] font-medium text-ink-500">Total Transaksi</p>
        <p class="mt-1 text-2xl font-extrabold text-ink-900"><?= count($baris) ?></p>
        <p class="text-[11px] text-ink-400"><?= $status === '' ? 'Semua status' : 'Status: ' . e($labelStatus[$status]) ?></p>
      </article>
      <article class="card p-4">
        <p class="text-[12px] font-medium text-ink-500">Dana Terverifikasi</p>
        <p class="mt-1 text-2xl font-extrabold text-[#065F46]"><?= rupiah($ringkas['masuk']) ?></p>
        <p class="text-[11px] text-ink-400"><?= (int) $ringkas['n_masuk'] ?> pembayaran diterima</p>
      </article>
      <article class="card p-4">
        <p class="text-[12px] font-medium text-ink-500">Menunggu Verifikasi</p>
        <p class="mt-1 text-2xl font-extrabold text-[#92400E]"><?= rupiah($ringkas['menunggu']) ?></p>
        <p class="text-[11px] text-ink-400">Belum masuk rekening</p>
      </article>
      <article class="card p-4">
        <p class="text-[12px] font-medium text-ink-500">Ditolak</p>
        <p class="mt-1 text-2xl font-extrabold text-[#991B1B]"><?= rupiah($ringkas['ditolak']) ?></p>
        <p class="text-[11px] text-ink-400">Perlu unggah ulang bukti</p>
      </article>
    </section>

    <!-- ====================== TABEL ====================== -->
    <div class="mt-5 card overflow-hidden">
      <div class="overflow-x-auto scroll-thin">
        <table class="w-full min-w-[860px] border-collapse">
          <thead class="thead">
            <tr>
              <th class="th w-10">No</th>
              <th class="th">No. Pendaftaran</th>
              <th class="th">Calon Siswa</th>
              <th class="th">Jenis Pembayaran</th>
              <th class="th text-right">Nominal</th>
              <th class="th">Status</th>
              <th class="th">Tanggal</th>
              <th class="th">Verifikator</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <?php if (!$baris): ?>
              <tr>
                <td class="td text-center text-ink-400" colspan="8">
                  Tidak ada transaksi pembayaran<?= $status !== '' ? ' berstatus ' . e($labelStatus[$status]) : '' ?>.
                </td>
              </tr>
            <?php endif; ?>
            <?php foreach ($baris as $i => $p): ?>
              <tr>
                <td class="td text-ink-400"><?= $i + 1 ?></td>
                <td class="td font-mono text-[12px] font-semibold text-ink-900"><?= e($p['reg']) ?></td>
                <td class="td">
                  <div class="font-semibold text-ink-900"><?= e($p['nama']) ?></div>
                  <div class="text-[11px] text-ink-400"><?= e($p['sekolah']) ?></div>
                </td>
                <td class="td"><?= e($p['jenis']) ?></td>
                <td class="td text-right font-bold text-ink-900 whitespace-nowrap"><?= rupiah($p['nominal']) ?></td>
                <td class="td"><?= badge($p['status'], $p['tone']) ?></td>
                <td class="td text-[12px] whitespace-nowrap"><?= bulan($p['waktu']) ?></td>
                <td class="td text-[12px]">
                  <?= e($p['verifier'] ?: '—') ?>
                  <?php if ($p['verified']): ?>
                    <div class="text-[11px] text-ink-400"><?= bulan($p['verified']) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="bg-canvas">
              <td class="td font-semibold text-ink-700" colspan="4">Total (<?= count($baris) ?> transaksi)</td>
              <td class="td text-right font-extrabold text-ink-900 whitespace-nowrap"><?= rupiah($ringkas['nominal']) ?></td>
              <td class="td" colspan="3"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- ====================== CATATAN & TANDA TANGAN ====================== -->
    <div class="mt-4 rounded-xl bg-canvas border border-line p-4 text-[12px] text-ink-500">
      <p>
        Rekening tujuan: <strong class="text-ink-900"><?= e($PENGATURAN['nama_bank']) ?> — <?= e($PENGATURAN['no_rekening']) ?></strong>
        a.n. <?= e($PENGATURAN['atas_nama_rekening']) ?>.
        Dana terverifikasi mencakup status Terverifikasi, Angsuran 1, Angsuran 2, dan Lunas.
        Biaya formulir: <?= rupiah($PENGATURAN['biaya_formulir']) ?> ·
        <?= e(label_biaya_pendaftaran()) ?>: <?= rupiah($PENGATURAN['estimasi_uang_masuk']) ?>
        <?php if (!empty($PENGATURAN['angsuran_1_jatuh_tempo']) || !empty($PENGATURAN['angsuran_2_jatuh_tempo'])): ?>
          (angsuran 2×: <?= rupiah(((float) $PENGATURAN['estimasi_uang_masuk']) / 2) ?> per tahap)
        <?php endif; ?>.
      </p>
    </div>

    <div class="mt-8 flex justify-end">
      <div class="text-center text-sm">
        <p class="text-ink-500"><?= bulan(date('Y-m-d')) ?></p>
        <p class="mt-1 font-medium text-ink-700"><?= e($jabatanTtd) ?></p>
        <div class="h-16"></div>
        <p class="font-semibold text-ink-900 underline decoration-ink-300 underline-offset-4"><?= e($USER_LOGIN['nama']) ?></p>
      </div>
    </div>

  </main>
</div>

<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
