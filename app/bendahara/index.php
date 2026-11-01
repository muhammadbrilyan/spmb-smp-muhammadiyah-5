<?php
/* ==================================================================
 * DASHBOARD BENDAHARA — angka keuangan SPMB.
 *   Halaman pantau: perolehan dana, antrean yang menunggu, aliran dana
 *   harian, dan keputusan terbaru.
 *   Tempat bekerja (setujui / tolak bukti) ada di Verifikasi Pembayaran
 *   (app/bendahara/pembayaran.php).
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

/* Aksi: kirim pengingat jatuh tempo angsuran (in-app + WhatsApp bila aktif). */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['aksi'] ?? '') === 'pengingat_jatuh_tempo') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    } else {
        $hasilPengingat = angsuran_pengingat_semua(url('app/siswa/keuangan.php'));
        if ($hasilPengingat['dibuat'] === 0 && $hasilPengingat['wa'] === 0 && $hasilPengingat['wa_gagal'] === 0) {
            flash('info', 'Tidak ada angsuran yang mendekati atau melewati jatuh tempo.');
        } else {
            $pesan = (int) $hasilPengingat['siswa'] . ' siswa diingatkan · '
                . (int) $hasilPengingat['dibuat'] . ' notifikasi baru';
            if (angsuran_pengingat_wa_aktif()) {
                $pesan .= ' · WhatsApp terkirim ' . (int) $hasilPengingat['wa'];
                if ($hasilPengingat['wa_gagal'] > 0) {
                    $pesan .= ', gagal ' . (int) $hasilPengingat['wa_gagal'];
                }
            } else {
                $pesan .= ' · WhatsApp nonaktif di Pengaturan Sistem';
            }
            flash('success', 'Pengingat jatuh tempo dijalankan: ' . $pesan . '.');
        }
    }
    header('Location: ' . url('app/bendahara/index.php'));
    exit;
}

$title     = 'Dashboard Keuangan Bendahara SPMB';
$desc      = 'Pantau dana terverifikasi, antrean pembayaran, dan aliran keuangan SPMB ' . label_biaya_pendaftaran() . '.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$stat       = stat_bendahara();
$perJenis   = bendahara_per_jenis();
$tindak     = bendahara_antrean_tindak();
$aliran     = bendahara_aliran_harian(14);
$aktivitas  = bendahara_aktivitas(6);

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'dashboard';
$badgeRole  = 'BENDAHARA';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik' => null, 'SPMB' => null, 'Dashboard Bendahara' => null];
$pageActions = '
  <a href="' . url('app/bendahara/rekap.php') . '" class="btn btn-outline hidden md:inline-flex">' . icon('print') . ' Cetak Rekap Keuangan</a>
';
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();

/* Tanggal yang wajib muncul pada grafik (14 hari terakhir) agar sumbu waktu
 * tidak bolong saat satu hari tidak ada transaksi. */
$petaAliran = [];
foreach ($aliran as $a) {
    $petaAliran[$a['tanggal']] = $a;
}
$deret = [];
for ($i = 13; $i >= 0; $i--) {
    $tgl   = date('Y-m-d', strtotime("-$i day"));
    $deret[] = [
        'tanggal' => $tgl,
        'rp'      => $petaAliran[$tgl]['rp'] ?? 0.0,
        'jml'     => $petaAliran[$tgl]['jml'] ?? 0,
        'urutan'  => 13 - $i,
    ];
}
$puncak = 0.0;
foreach ($deret as $d) {
    if ($d['rp'] > $puncak) {
        $puncak = (float) $d['rp'];
    }
}
$total14 = 0.0;
foreach ($deret as $d) {
    $total14 += (float) $d['rp'];
}
?>

<div class="lg:pl-[264px]">

  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1440px]">

    <?php if ($flash): ?>
      <div class="mb-5 rounded-xl border <?= in_array($flash['tipe'], ['sukses', 'success'], true) ? 'border-green-200 bg-green-50 text-green-800' : ($flash['tipe'] === 'info' ? 'border-blue-200 bg-blue-50 text-blue-800' : 'border-red-200 bg-red-50 text-red-800') ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <!-- ====================== JUDUL ====================== -->
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-green"><?= dot('green') ?> Rekening Aktif: <?= e($PENGATURAN['nama_bank']) ?></span>
          <span class="badge badge-gray"><?= e($PENGATURAN['gelombang']) ?> · <?= e($PENGATURAN['tahun_ajaran']) ?></span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Dashboard Keuangan SPMB</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Ringkasan <strong class="text-ink-700"><?= e(label_biaya_pendaftaran()) ?></strong>, biaya formulir,
          dan antrean verifikasi pembayaran <?= e($PENGATURAN['gelombang']) ?>.
        </p>
      </div>

      <div class="flex flex-wrap gap-2.5 shrink-0">
        <form method="post" action="<?= url('app/bendahara/index.php') ?>" class="inline"
              onsubmit="return confirm('Kirim pengingat jatuh tempo ke siswa yang angsurannya mendekati atau melewati jatuh tempo?<?= angsuran_pengingat_wa_aktif() ? ' Pesan WhatsApp juga akan dikirim ke orang tua/wali.' : '' ?>')">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="pengingat_jatuh_tempo">
          <button type="submit" class="btn btn-outline">
            <?= icon('send') ?> Kirim Pengingat Jatuh Tempo
          </button>
        </form>
        <a href="<?= url('app/bendahara/pembayaran.php') ?>" class="btn btn-primary shadow-drop whitespace-nowrap">
          <?= icon('fact_check') ?> Verifikasi Pembayaran
          <?php if ($stat['menunggu'] > 0): ?>
            <span class="ml-1 rounded-full bg-white/25 px-2 py-0.5 text-[12px] font-bold"><?= (int) $stat['menunggu'] ?></span>
          <?php endif; ?>
        </a>
      </div>
    </div>

    <!-- ====================== KARTU STATISTIK ====================== -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Total Pendaftar Masuk</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['total_pendaftar'] ?><span class="text-base font-semibold text-ink-400"> Siswa</span></p>
          </div>
          <span class="icon-box bg-primary-light text-primary"><?= icon('groups', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center justify-between text-xs">
          <span class="text-ink-600"><strong class="text-ink-900"><?= (int) $stat['total_bayar'] ?></strong> tagihan tercatat</span>
          <span class="text-ink-400"><?= e($PENGATURAN['gelombang']) ?></span>
        </div>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Total Dana Terverifikasi</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= rupiah($stat['dana_masuk']) ?></p>
          </div>
          <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E]"><?= icon('account_balance', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center justify-between text-xs">
          <span class="inline-flex items-center gap-1.5 text-[#065F46] font-semibold"><?= dot('green') ?> <?= (int) $stat['jml_masuk'] ?> pembayaran terverifikasi</span>
          <a href="<?= url('app/bendahara/pembayaran.php?s=MASUK') ?>" class="text-primary hover:underline shrink-0">Lihat</a>
        </div>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Menunggu Verifikasi</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['menunggu'] ?><span class="text-base font-semibold text-ink-400"> Bukti</span></p>
          </div>
          <span class="icon-box bg-[#FFF7ED] text-[#EA580C]"><?= icon('schedule', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center justify-between text-xs">
          <span class="text-ink-600"><?= rupiah($stat['rp_menunggu']) ?> belum masuk</span>
          <a href="<?= url('app/bendahara/pembayaran.php') ?>" class="badge badge-amber">Perlu Tindakan</a>
        </div>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Ditolak</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['ditolak'] ?><span class="text-base font-semibold text-ink-400"> Bukti</span></p>
          </div>
          <span class="icon-box bg-[#FEF2F2] text-[#B42318]"><?= icon('cancel', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center justify-between text-xs">
          <span class="text-ink-600"><?= rupiah($stat['rp_ditolak']) ?> harus diunggah ulang</span>
          <a href="<?= url('app/bendahara/pembayaran.php?s=REJECTED') ?>" class="text-primary hover:underline shrink-0">Lihat</a>
        </div>
      </article>

    </section>

    <!-- ====================== PROGRES VERIFIKASI ====================== -->
    <section class="mt-4 card p-5">
      <div class="flex items-center justify-between gap-3 flex-wrap">
        <h2 class="font-bold text-ink-900 text-sm">Progres Pemeriksaan Bukti</h2>
        <span class="text-[13px] font-semibold text-primary"><?= number_format((float) $stat['persen'], 1, ',', '.') ?>% selesai</span>
      </div>
      <div class="mt-3 h-2 rounded-full bg-line-soft overflow-hidden">
        <div class="h-full rounded-full bg-primary" style="width:<?= (float) $stat['persen'] ?>%"></div>
      </div>
      <p class="mt-2.5 text-[12px] text-ink-500">
        <?= (int) $stat['jml_masuk'] ?> terverifikasi + <?= (int) $stat['ditolak'] ?> ditolak dari
        <?= (int) $stat['total_bayar'] ?> tagihan tercatat; <?= (int) $stat['menunggu'] ?> masih menunggu keputusan.
      </p>
    </section>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">

      <!-- ====================== KOLOM KIRI ====================== -->
      <div class="lg:col-span-2 space-y-5">

        <!-- Perlu tindakan -->
        <section class="card p-5 sm:p-6">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <h2 class="font-bold text-ink-900">Perlu Tindakan</h2>
              <p class="mt-1 text-[13px] text-ink-500">
                Bukti yang menunggu keputusan bendahara, dipecah menurut asal pembayaran.
              </p>
            </div>
            <a href="<?= url('app/bendahara/pembayaran.php') ?>" class="btn btn-sm btn-primary whitespace-nowrap">
              <?= icon('fact_check', 'text-[15px]') ?> Buka Antrean
            </a>
          </div>

          <?php if ($tindak['menunggu'] === 0): ?>
            <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-[13px] text-green-800 flex items-center gap-2">
              <?= icon('task_alt', 'text-[16px]') ?> Tidak ada bukti menunggu — semua transaksi sudah diputus.
            </div>
          <?php else: ?>
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
              <div class="rounded-xl border border-line px-4 py-3">
                <dt class="text-[12px] text-ink-500">Transfer manual</dt>
                <dd class="mt-1 text-xl font-bold text-ink-900"><?= (int) $tindak['jml_manual'] ?> bukti</dd>
                <dd class="text-[11px] text-ink-400">cocokkan dengan rekening</dd>
              </div>
              <div class="rounded-xl border border-line px-4 py-3">
                <dt class="text-[12px] text-ink-500">Gateway (Midtrans)</dt>
                <dd class="mt-1 text-xl font-bold text-ink-900"><?= (int) $tindak['jml_gateway'] ?> bukti</dd>
                <dd class="text-[11px] text-ink-400"><?= (int) $tindak['gateway_lunas'] ?> sudah lunas otomatis</dd>
              </div>
              <div class="rounded-xl border border-line px-4 py-3">
                <dt class="text-[12px] text-ink-500">Menunggu nilai</dt>
                <dd class="mt-1 text-xl font-bold text-ink-900"><?= rupiah($tindak['rp']) ?></dd>
                <?php if ($tindak['tertua_hari'] !== null): ?>
                  <dd class="text-[11px] <?= (int) $tindak['tertua_hari'] >= 3 ? 'text-[#B42318] font-semibold' : 'text-ink-400' ?>">
                    terlama <?= (int) $tindak['tertua_hari'] ?> hari
                  </dd>
                <?php else: ?>
                  <dd class="text-[11px] text-ink-400">&nbsp;</dd>
                <?php endif; ?>
              </div>
            </dl>
          <?php endif; ?>
        </section>

        <!-- Ringkasan per jenis pembayaran -->
        <section class="card overflow-hidden">
          <div class="px-5 sm:px-6 py-5 flex items-start justify-between gap-3 flex-wrap border-b border-line">
            <div>
              <h2 class="font-bold text-ink-900">Perolehan per Jenis Pembayaran</h2>
              <p class="mt-1 text-[13px] text-ink-500">Dana terverifikasi, antrean, dan penolakan tiap jenis.</p>
            </div>
            <a href="<?= url('app/operator/export.php?rekap=pembayaran') ?>" class="btn btn-sm btn-outline whitespace-nowrap">
              <?= icon('download', 'text-[15px]') ?> Export CSV
            </a>
          </div>
          <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[640px] border-collapse">
              <thead class="thead">
                <tr>
                  <th class="th">Jenis</th>
                  <th class="th text-right">Terverifikasi</th>
                  <th class="th text-right">Menunggu</th>
                  <th class="th text-right">Ditolak</th>
                  <th class="th text-right">Total Tagihan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-line-soft">
                <?php if (!$perJenis): ?>
                  <tr><td class="td text-center text-ink-400" colspan="5">Belum ada transaksi pembayaran.</td></tr>
                <?php endif; ?>
                <?php foreach ($perJenis as $kode => $j): ?>
                  <tr class="tr-hover">
                    <td class="td">
                      <div class="font-semibold text-ink-900"><?= e($j['label']) ?></div>
                      <div class="text-[11px] text-ink-400 font-mono"><?= e($kode) ?></div>
                    </td>
                    <td class="td text-right">
                      <div class="font-bold text-ink-900"><?= rupiah($j['rp_masuk']) ?></div>
                      <div class="text-[11px] text-ink-400"><?= (int) $j['jml_masuk'] ?> transaksi</div>
                    </td>
                    <td class="td text-right">
                      <span class="badge badge-amber"><?= (int) $j['jml_pending'] ?></span>
                      <div class="mt-1 text-[11px] text-ink-400"><?= rupiah($j['rp_pending']) ?></div>
                    </td>
                    <td class="td text-right">
                      <span class="badge badge-red"><?= (int) $j['jml_tolak'] ?></span>
                    </td>
                    <td class="td text-right font-semibold text-ink-700"><?= (int) $j['jml'] ?> baris</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Aliran dana 14 hari -->
        <section class="card p-5 sm:p-6">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <h2 class="font-bold text-ink-900">Aliran Dana Terverifikasi</h2>
              <p class="mt-1 text-[13px] text-ink-500">14 hari terakhir — hari tanpa transaksi tampil kosong.</p>
            </div>
            <p class="text-right">
              <span class="block text-[12px] text-ink-500">Total 14 hari</span>
              <span class="block text-lg font-extrabold text-ink-900"><?= rupiah($total14) ?></span>
            </p>
          </div>

          <?php if ($puncak <= 0): ?>
            <p class="mt-5 rounded-xl border border-line bg-canvas px-4 py-6 text-center text-[13px] text-ink-400">
              Belum ada dana terverifikasi pada 14 hari terakhir.
            </p>
          <?php else: ?>
            <div class="mt-5 flex items-end gap-1.5 h-36">
              <?php foreach ($deret as $d):
                  $pct = $puncak > 0 ? max(2, (int) round($d['rp'] / $puncak * 100)) : 0;
                  /* Tanggal digantinkan pada layar sempit agar 14 label tidak bertumpuk. */
                  $kelasLabel = $d['urutan'] % 2 === 0
                      ? 'text-[10px] text-ink-400'
                      : 'text-[10px] text-ink-400 hidden sm:block';
              ?>
                <div class="flex-1 h-full flex flex-col justify-end items-center gap-1.5"
                     title="<?= e(bulan($d['tanggal'])) ?>: <?= e(rupiah($d['rp'])) ?> · <?= (int) $d['jml'] ?> transaksi">
                  <?php if ($d['rp'] > 0): ?>
                    <span class="text-[10px] font-semibold text-ink-500"><?= (int) $d['jml'] ?></span>
                  <?php endif; ?>
                  <div class="w-full rounded-t <?= $d['rp'] > 0 ? 'bg-primary' : 'bg-line-soft' ?>" style="height:<?= $d['rp'] > 0 ? $pct : 2 ?>%"></div>
                  <span class="<?= e($kelasLabel) ?>"><?= e(date('d/m', strtotime($d['tanggal']))) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

      </div>

      <!-- ====================== KOLOM KANAN ====================== -->
      <div class="space-y-5">

        <!-- Rekening -->
        <section class="card p-5">
          <h2 class="font-bold text-ink-900 text-sm">Rekening Penampungan</h2>
          <dl class="mt-3 space-y-2.5 text-[13px]">
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Bank / Kanal</dt><dd class="font-semibold text-ink-900 text-right"><?= e($PENGATURAN['nama_bank'] ?: '—') ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">No. Rekening</dt><dd class="font-mono font-semibold text-ink-900 text-right"><?= e($PENGATURAN['no_rekening'] ?: '—') ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Atas Nama</dt><dd class="font-semibold text-ink-900 text-right"><?= e($PENGATURAN['atas_nama_rekening'] ?: '—') ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Biaya Formulir</dt><dd class="font-semibold text-ink-900 text-right"><?= e(rupiah((float) ($PENGATURAN['biaya_formulir'] ?? 0))) ?></dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500"><?= e(label_biaya_pendaftaran()) ?></dt><dd class="font-semibold text-ink-900 text-right"><?= e(rupiah((float) ($PENGATURAN['estimasi_uang_masuk'] ?? 0))) ?></dd></div>
          </dl>
        </section>

        <!-- Keputusan terbaru -->
        <section class="card p-5">
          <div class="flex items-start justify-between gap-2">
            <h2 class="font-bold text-ink-900 text-sm">Keputusan Terbaru</h2>
            <a href="<?= url('app/bendahara/pembayaran.php?s=MASUK') ?>" class="text-[12px] font-semibold text-primary hover:underline">Semua</a>
          </div>
          <div class="mt-3 space-y-3">
            <?php if (!$aktivitas): ?>
              <p class="text-sm text-ink-400">Belum ada keputusan pembayaran tercatat.</p>
            <?php endif; ?>
            <?php foreach ($aktivitas as $a): ?>
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="text-[13px] font-semibold text-ink-900 truncate">
                    <a href="<?= url('app/bendahara/pembayaran.php?s=SEMUA') ?>" class="font-mono text-[12px] text-primary hover:underline"><?= e($a['reg']) ?></a>
                    <?= e($a['nama']) ?>
                  </p>
                  <p class="text-[11px] text-ink-500 truncate"><?= e($a['jenis']) ?> · <?= e(rupiah($a['nominal'])) ?></p>
                  <p class="text-[11px] text-ink-400"><?= e($a['verifikator']) ?> · <?= e(bulan($a['waktu'])) ?></p>
                </div>
                <?= badge($a['status'], $a['tone']) ?>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- Tautan kerja -->
        <section class="card p-5">
          <h2 class="font-bold text-ink-900 text-sm">Ruang Kerja Bendahara</h2>
          <div class="mt-3 space-y-2">
            <a href="<?= url('app/bendahara/pembayaran.php') ?>" class="flex items-center justify-between gap-3 rounded-xl border border-line px-4 py-3 hover:border-primary/40 hover:bg-canvas">
              <span class="flex items-center gap-2.5 min-w-0">
                <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon('fact_check', 'text-[18px]') ?></span>
                <span class="min-w-0">
                  <span class="block text-[13px] font-semibold text-ink-900">Verifikasi Pembayaran</span>
                  <span class="block text-[11px] text-ink-500">Setujui atau tolak bukti transfer</span>
                </span>
              </span>
              <?= icon('arrow_forward', 'text-[16px] text-ink-400') ?>
            </a>
            <a href="<?= url('app/bendahara/rekap.php') ?>" class="flex items-center justify-between gap-3 rounded-xl border border-line px-4 py-3 hover:border-primary/40 hover:bg-canvas">
              <span class="flex items-center gap-2.5 min-w-0">
                <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon('print', 'text-[18px]') ?></span>
                <span class="min-w-0">
                  <span class="block text-[13px] font-semibold text-ink-900">Cetak Rekap Keuangan</span>
                  <span class="block text-[11px] text-ink-500">Laporan siap cetak / PDF</span>
                </span>
              </span>
              <?= icon('arrow_forward', 'text-[16px] text-ink-400') ?>
            </a>
            <a href="<?= url('app/operator/panduan.php#verifikasi-pembayaran-bendahara') ?>" class="flex items-center justify-between gap-3 rounded-xl border border-line px-4 py-3 hover:border-primary/40 hover:bg-canvas">
              <span class="flex items-center gap-2.5 min-w-0">
                <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon('support_agent', 'text-[18px]') ?></span>
                <span class="min-w-0">
                  <span class="block text-[13px] font-semibold text-ink-900">Panduan SOP Keuangan</span>
                  <span class="block text-[11px] text-ink-500">Prosedur verifikasi &amp; penolakan</span>
                </span>
              </span>
              <?= icon('arrow_forward', 'text-[16px] text-ink-400') ?>
            </a>
          </div>
        </section>

        <!-- Skema angsuran -->
        <?php $skema = angsuran_skema(); ?>
        <?php if ($skema['aktif']): ?>
          <section class="card p-5">
            <h2 class="font-bold text-ink-900 text-sm">Skema <?= e(label_biaya_pendaftaran()) ?></h2>
            <ul class="mt-3 space-y-2 text-[13px]">
              <li class="flex justify-between gap-3"><span class="text-ink-500">Total ditagihkan</span><span class="font-semibold text-ink-900"><?= e(rupiah($skema['total'])) ?></span></li>
              <?php foreach ($skema['angsuran'] as $a): ?>
                <li class="flex justify-between gap-3">
                  <span class="text-ink-500">Angsuran <?= (int) $a['ke'] ?><?= $a['jatuh_tempo'] ? ' · ' . e(bulan($a['jatuh_tempo'])) : '' ?></span>
                  <span class="font-semibold text-ink-900"><?= e(rupiah($a['nominal'])) ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
            <p class="mt-3 text-[11px] text-ink-400">Tagihan berubah menjadi <strong>LUNAS</strong> otomatis setelah total dana terverifikasi menutup <?= e(label_biaya_pendaftaran()) ?>.</p>
          </section>
        <?php endif; ?>

      </div>
    </div>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<?php if ($flash): ?>
<script>window.APP_FLASH = <?= json_encode(['tipe' => $flash['tipe'], 'pesan' => $flash['pesan']], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
