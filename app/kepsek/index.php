<?php
require_once __DIR__ . '/../../includes/config.php';

/* Hak akses: Kepala Sekolah — hanya membaca rekap, tanpa tombol aksi (RBAC PRD). */
require_role(['kepsek']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title = 'Dashboard Kepala Sekolah';
$desc  = 'Rekapitulasi SPMB: pendaftar, verifikasi, seleksi, keterisian rombel, dan penerimaan dana — hanya baca.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* ==================================================================
 * Data (read-only)
 * ================================================================== */
$stat      = stat_bendahara();
$operator  = stat_operator();
$corong    = corong_ppdb();
$umum      = pengumuman_status();
$pendaftar = daftar_pendaftar();
$rombel    = rombel();
$hasil     = daftar_hasil_seleksi();
$jadwal    = daftar_jadwal_tes();

/* Rekap per jalur */
$jalur = [];
foreach ($pendaftar as $p) {
    $j = $p['jalur'] ?: 'Reguler';
    $jalur[$j] = ($jalur[$j] ?? 0) + 1;
}
arsort($jalur);

/* Rekap hasil seleksi */
$rekapHasil = ['LULUS' => 0, 'PROSES' => 0, 'TIDAK_LULUS' => 0];
foreach ($hasil as $h) {
    $k = $h['status'];
    if (isset($rekapHasil[$k])) {
        $rekapHasil[$k]++;
    }
}

/* Keterisian rombel */
$totalTerisi = 0;
$totalKuota  = 0;
foreach ($rombel as $r) {
    $totalTerisi += $r['terisi'];
    $totalKuota  += $r['kuota'];
}
$persenKelas = $totalKuota > 0 ? round($totalTerisi / $totalKuota * 100, 1) : 0.0;

/* Corong: label tampilan => jumlah pendaftar yang mencapai tahap itu. */
$corongTahap = [
    'Pendaftar masuk'                  => $corong['masuk'],
    'Biodata terverifikasi'            => $corong['data'],
    'Biaya pendaftaran terverifikasi'  => $corong['bayar'],
    'Akun portal aktif'                => $corong['akun'],
    'Lulus seleksi'                    => $corong['lulus'],
    'Ditempatkan di rombel'            => $corong['ditempat'],
];
$corongDasar = max(1, $corong['masuk']);

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'dashboard';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik', 'Rekap SPMB', 'Kepala Sekolah'];
$pageActions = '<button type="button" class="btn btn-outline shadow-drop" onclick="window.print()">'
    . icon('print') . ' Cetak Ringkasan</button>';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <!-- Hanya muncul di kertas: identitas laporan -->
    <div class="hanya-cetak text-center border-b-2 border-ink-900/10 pb-3 mb-4">
      <p class="text-[15px] font-bold text-ink-900"><?= e($PENGATURAN['nama_sekolah']) ?></p>
      <p class="text-[12px] text-ink-500">Ringkasan SPMB <?= e($PENGATURAN['gelombang']) ?> · TA <?= e($PENGATURAN['tahun_ajaran']) ?> · dicetak <?= bulan(date('Y-m-d')) ?></p>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Rekap SPMB</span>
          <span class="badge badge-gray">Mode Baca Saja</span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Dashboard Kepala Sekolah</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Ringkasan penerimaan peserta didik baru <?= e($PENGATURAN['tahun_ajaran']) ?> —
          <?= e($PENGATURAN['gelombang']) ?>. Semua angka diambil langsung dari database operasional.
        </p>
      </div>
      <div class="flex items-center gap-2.5 shrink-0">
        <a href="<?= url('app/spmb/index.php') ?>" target="_blank" rel="noopener" class="btn btn-outline">
          <?= icon('language') ?> Lihat Landing
        </a>
      </div>
    </div>

    <!-- Kartu ringkas -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[13px] text-ink-500">Total Pendaftar Masuk</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['total_pendaftar'] ?></p>
          </div>
          <span class="icon-box bg-primary-light text-primary"><?= icon('how_to_reg') ?></span>
        </div>
        <p class="mt-3 text-xs text-ink-400">Sepanjang <?= e($PENGATURAN['gelombang']) ?></p>
      </div>

      <div class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[13px] text-ink-500">Terverifikasi</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= $operator['akun_aktif'] ?></p>
          </div>
          <span class="icon-box bg-[#ECFDF5] text-[#059669]"><?= icon('verified_user') ?></span>
        </div>
        <p class="mt-3 text-xs text-ink-400"><?= $operator['akun_persen'] ?>% dari total</p>
      </div>

      <div class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[13px] text-ink-500">Dana Terverifikasi</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= rupiah($stat['dana_masuk']) ?></p>
          </div>
          <span class="icon-box bg-[#EFF6FF] text-[#1D4ED8]"><?= icon('account_balance_wallet') ?></span>
        </div>
        <p class="mt-3 text-xs text-ink-400"><?= $stat['jml_masuk'] ?> transaksi</p>
      </div>

      <div class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[13px] text-ink-500">Keterisian Rombel</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= $persenKelas ?>%</p>
          </div>
          <span class="icon-box bg-[#FFF7ED] text-[#C2410C]"><?= icon('groups') ?></span>
        </div>
        <p class="mt-3 text-xs text-ink-400"><?= $totalTerisi ?> / <?= $totalKuota ?> kursi · <?= count($rombel) ?> rombel</p>
      </div>
    </section>

    <!-- Corong PPDB -->
    <section class="mt-6 card p-5 sm:p-6">
      <div class="flex items-start justify-between gap-3 flex-wrap">
        <div class="min-w-0">
          <h2 class="font-bold text-ink-900">Corong Pendaftaran</h2>
          <p class="mt-1 text-[13px] text-ink-500">
            Setiap bar adalah jumlah pendaftar yang <strong>sudah mencapai</strong> tahap tersebut
            — dihitung lepas per tahap, jadi urutannya tidak selalu menyempit.
          </p>
        </div>
        <?php if ($umum['diumumkan']): ?>
          <span class="badge badge-green"><?= icon('campaign') ?> Hasil terbit <?= e(bulan($umum['dijadwalkan_pada'] ?? date('Y-m-d'))) ?></span>
        <?php elseif ($umum['dijadwalkan_pada']): ?>
          <span class="badge badge-amber"><?= icon('schedule') ?> Hasil dijadwalkan <?= e(bulan($umum['dijadwalkan_pada'])) ?></span>
        <?php else: ?>
          <span class="badge badge-gray"><?= icon('schedule') ?> Jadwal pengumuman belum diisi</span>
        <?php endif; ?>
      </div>

      <ul class="mt-5 space-y-3">
        <?php foreach ($corongTahap as $label => $n): $pct = round($n / $corongDasar * 100, 1); ?>
          <li class="flex flex-wrap items-center gap-x-3 gap-y-1.5">
            <span class="w-full sm:w-56 shrink-0 text-[13px] text-ink-700"><?= e($label) ?></span>
            <div class="flex-1 min-w-[80px] h-2 rounded-full bg-line-soft overflow-hidden">
              <div class="h-full rounded-full <?= $n >= $corong['masuk'] ? 'bg-[#0E9F6E]' : 'bg-primary' ?>" style="width: <?= min(100, $pct) ?>%"></div>
            </div>
            <span class="w-20 shrink-0 text-right text-[13px] font-semibold text-ink-900 whitespace-nowrap">
              <?= (int) $n ?> <span class="text-ink-400 font-normal">(<?= $pct ?>%)</span>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>

      <p class="mt-4 text-[12px] text-ink-400 border-t border-line-soft pt-3">
        Tagihan <?= e(label_biaya_pendaftaran()) ?> baru mulai dihitung setelah hasil seleksi diterbitkan,
        jadi angka tahap pembayaran dapat terlihat lebih kecil daripada tahap di atasnya.
      </p>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
      <!-- Keterisian per rombel -->
      <section class="card lg:col-span-2 p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Keterisian Rombel</h2>
        <p class="mt-1 text-[13px] text-ink-500">Jumlah siswa sudah ditempatkan vs kuota per kelas.</p>

        <div class="mt-4 overflow-x-auto">
          <table class="w-full min-w-[520px]">
            <thead class="thead">
              <tr>
                <th class="th">Kode</th>
                <th class="th">Nama Kelas</th>
                <th class="th">Wali Kelas</th>
                <th class="th text-right">Terisi / Kuota</th>
                <th class="th">Progres</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rombel as $r): $p = $r['kuota'] > 0 ? round($r['terisi'] / $r['kuota'] * 100) : 0; ?>
                <tr class="tr-hover">
                  <td class="td font-mono text-[13px]"><?= e($r['kode']) ?></td>
                  <td class="td font-semibold text-ink-900"><?= e($r['nama']) ?></td>
                  <td class="td"><?= e($r['wali']) ?></td>
                  <td class="td text-right font-semibold"><?= $r['terisi'] ?> / <?= $r['kuota'] ?></td>
                  <td class="td min-w-[140px]">
                    <div class="flex items-center gap-2">
                      <div class="flex-1 h-2 rounded-full bg-line-soft overflow-hidden">
                        <div class="h-full rounded-full <?= $p >= 100 ? 'bg-[#0E9F6E]' : 'bg-primary' ?>" style="width: <?= min($p, 100) ?>%"></div>
                      </div>
                      <span class="text-xs font-semibold text-ink-500 w-10 text-right"><?= $p ?>%</span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Rekap jalur & hasil -->
      <div class="space-y-6">
        <section class="card p-5 sm:p-6">
          <h2 class="font-bold text-ink-900">Pendaftar per Jalur</h2>
          <ul class="mt-4 space-y-3">
            <?php foreach ($jalur as $j => $n): ?>
              <li class="flex items-center justify-between gap-3">
                <span class="text-sm text-ink-700"><?= e($j) ?></span>
                <span class="badge badge-blue"><?= $n ?></span>
              </li>
            <?php endforeach; ?>
            <?php if (!$jalur): ?>
              <li class="text-sm text-ink-400">Belum ada data pendaftar.</li>
            <?php endif; ?>
          </ul>
        </section>

        <section class="card p-5 sm:p-6">
          <h2 class="font-bold text-ink-900">Hasil Seleksi</h2>
          <ul class="mt-4 space-y-3">
            <li class="flex items-center justify-between gap-3">
              <span class="text-sm text-ink-700">Lulus</span>
              <span class="badge badge-green"><?= $rekapHasil['LULUS'] ?></span>
            </li>
            <li class="flex items-center justify-between gap-3">
              <span class="text-sm text-ink-700">Dalam Proses</span>
              <span class="badge badge-amber"><?= $rekapHasil['PROSES'] ?></span>
            </li>
            <li class="flex items-center justify-between gap-3">
              <span class="text-sm text-ink-700">Tidak Lulus</span>
              <span class="badge badge-red"><?= $rekapHasil['TIDAK_LULUS'] ?></span>
            </li>
          </ul>
        </section>
      </div>
    </div>

    <!-- Jadwal tes terdekat -->
    <section class="mt-6 card p-5 sm:p-6">
      <h2 class="font-bold text-ink-900">Jadwal Tes Terdekat</h2>
      <p class="mt-1 text-[13px] text-ink-500">Agenda seleksi yang sudah dijadwalkan.</p>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[560px]">
          <thead class="thead">
            <tr>
              <th class="th">Kode</th>
              <th class="th">Kegiatan</th>
              <th class="th">Tanggal</th>
              <th class="th">Jam</th>
              <th class="th">Mode</th>
              <th class="th text-right">Peserta</th>
              <th class="th">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($jadwal as $j): ?>
              <tr class="tr-hover">
                <td class="td font-mono text-[13px]"><?= e($j['kode']) ?></td>
                <td class="td font-semibold text-ink-900"><?= e($j['nama']) ?></td>
                <td class="td"><?= e(bulan($j['tgl'])) ?></td>
                <td class="td font-mono text-[13px]"><?= e($j['jam']) ?></td>
                <td class="td"><?= e($j['mode']) ?></td>
                <td class="td text-right font-semibold"><?= $j['peserta'] ?></td>
                <td class="td"><?= badge($j['status'], $j['tone']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$jadwal): ?>
              <tr><td colspan="7" class="td text-center text-ink-400">Belum ada jadwal tes.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Hanya di kertas: blok tanda tangan -->
    <div class="hanya-cetak mt-8 text-center text-sm">
      <p class="text-ink-500"><?= bulan(date('Y-m-d')) ?></p>
      <p class="mt-1 font-medium text-ink-700"><?= e(trim((string) ($me['jabatan'] ?? '')) ?: 'Kepala Sekolah') ?></p>
      <div class="h-16"></div>
      <p class="font-semibold text-ink-900 underline underline-offset-4"><?= e($USER_LOGIN['nama']) ?></p>
    </div>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
</script>
</body>
</html>
