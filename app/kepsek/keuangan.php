<?php
/* ==================================================================
 * PEMBIAYAAN & TUNGGAKAN — monitoring Kepala Sekolah (baca saja).
 *
 * Menampilkan siapa di antara calon siswa yang sudah lulus masih punya
 * sisa tagihan biaya pendaftaran, per tahap angsuran. Angka diambil dari
 * fungsi yang sama dengan portal siswa (angsuran_ringkas), jadi apa yang
 * dilihat kepala sekolah identik dengan yang dilihat orang tua siswa.
 *
 * Tidak ada satu pun aksi di halaman ini: verifikasi pembayaran tetap
 * kewenangan bendahara.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['kepsek', 'admin']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title     = 'Pembiayaan & Tunggakan';
$desc      = 'Monitoring pembayaran biaya pendaftaran calon siswa yang lulus — read-only.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$skema = angsuran_skema();
$umum  = pengumuman_status();
/* Tagihan baru berlaku setelah hasil seleksi diterbitkan resmi. */
$tagihanAktif = !empty($umum['diumumkan']) && $skema['aktif'];

/* ==================================================================
 * Data: pendaftar lulus + ringkasan angsurannya
 * ================================================================== */
$lulus = array_values(array_filter(daftar_pendaftar(), function ($p) {
    return ($p['seleksi'] ?? '') === 'LULUS';
}));

$baris  = [];
$rekap  = [
    'n'          => 0,
    'tertagih'   => 0.0,
    'terkumpul'  => 0.0,
    'tunggakan'  => 0.0,
    'lunas'      => 0,
    'belum_lunas' => 0,
    'menunggu'   => 0.0,
    'tahap'      => [1 => ['nominal' => 0.0, 'terbayar' => 0.0], 2 => ['nominal' => 0.0, 'terbayar' => 0.0]],
];

foreach ($lulus as $p) {
    $r = angsuran_ringkas($p['id']);

    $terkumpul = min((float) $r['terverifikasi'], (float) $r['total']);
    $sisa      = max(0, (float) $r['total'] - $terkumpul);

    $perTahap = [];
    foreach ($r['angsuran'] as $a) {
        $ke = (int) $a['ke'];
        $perTahap[$ke] = $a;
        if (isset($rekap['tahap'][$ke])) {
            $rekap['tahap'][$ke]['terbayar'] += (float) $a['terbayar'];
        }
    }

    /* Terlambat = ada sisa pada tahap yang jatuh temponya sudah lewat.
     * Tahap berstatus MENUNGGU dikecualikan: buktinya sudah diserahkan dan
     * sedang diperiksa bendahara, jadi bukan tunggakan yang bisa ditagih ulang. */
    $terlambat = false;
    foreach ($perTahap as $a) {
        if ($a['status'] === 'MENUNGGU') {
            continue;
        }
        if ((float) $a['sisa'] > 0.5 && !empty($a['jatuh_tempo']) && strtotime($a['jatuh_tempo'] . ' 23:59:59') < time()) {
            $terlambat = true;
            break;
        }
    }

    $rekap['n']++;
    $rekap['tertagih']  += (float) $r['total'];
    $rekap['terkumpul'] += $terkumpul;
    $rekap['tunggakan'] += $sisa;
    $rekap['menunggu']  += (float) $r['pending'];
    if ($r['lunas']) {
        $rekap['lunas']++;
    } else {
        $rekap['belum_lunas']++;
    }

    $baris[] = [
        'reg'       => $p['reg'],
        'nama'      => $p['nama'],
        'sekolah'   => $p['sekolah'],
        'jalur'     => $p['jalur'],
        'kelas'     => $p['kelas'],
        'total'     => (float) $r['total'],
        'terkumpul' => $terkumpul,
        'sisa'      => $sisa,
        'lunas'     => (bool) $r['lunas'],
        'terlambat' => $terlambat,
        'tahap'     => $perTahap,
        'jatuh'     => $r['jatuh_tempo_terdekat']['jatuh_tempo'] ?? null,
    ];
}

/* Penyebut per tahap = tagihan tahap itu untuk SELURUH siswa lulus. Tahap yang
 * tidak dikembalikan angsuran_ringkas (terblokir bukti yang menunggu verifikasi)
 * tetap dihitung sebagai tagihan, jadi angkanya tidak menyusut diam-diam. */
foreach ($skema['angsuran'] as $ke => $a) {
    $rekap['tahap'][$ke]['nominal'] = $rekap['n'] * (float) $a['nominal'];
}

/* Yang perlu ditindak lebih dulu tampil paling atas. */
usort($baris, function ($a, $b) {
    return $b['sisa'] <=> $a['sisa'];
});

/* Filter sederhana untuk layar. */
$labelFilter = [
    ''      => 'Semua',
    'tunda' => 'Belum lunas',
    'lambat' => 'Terlambat',
    'lunas' => 'Sudah lunas',
];
$s = (string) ($_GET['s'] ?? '');
if (!isset($labelFilter[$s])) {
    $s = '';
}
$tampil = array_values(array_filter($baris, function ($b) use ($s) {
    if ($s === 'lunas')  return $b['lunas'];
    if ($s === 'tunda')  return !$b['lunas'];
    if ($s === 'lambat') return $b['terlambat'];
    return true;
}));

$toneTahap = ['LUNAS' => 'green', 'SEBAGIAN' => 'amber', 'MENUNGGU' => 'blue', 'BELUM' => 'gray'];

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'pembiayaan';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Monitoring' => null, 'Pembiayaan & Tunggakan' => null];
$pageActions = '<button type="button" class="btn btn-primary shadow-drop" onclick="window.print()">'
    . icon('print') . ' Cetak / Simpan PDF</button>';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <!-- ====================== KOP LAPORAN ====================== -->
    <div class="flex flex-col sm:flex-row items-start gap-4 border-b-2 border-ink-900/10 pb-4">
      <?php if (!empty($PENGATURAN['logo_url'])): ?>
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo <?= e($PENGATURAN['nama_sekolah']) ?>" class="w-12 h-12 shrink-0 object-contain">
      <?php endif; ?>
      <div class="min-w-0 flex-1">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-ink-400">Panitia SPMB</p>
        <p class="mt-0.5 text-[15px] font-bold text-ink-900"><?= e($PENGATURAN['nama_sekolah']) ?></p>
        <p class="text-[12px] text-ink-500"><?= e($PENGATURAN['alamat_sekolah']) ?></p>
      </div>
      <div class="sm:text-right text-[12px] text-ink-500 shrink-0">
        <p><?= e($PENGATURAN['gelombang']) ?> · TA <?= e($PENGATURAN['tahun_ajaran']) ?></p>
        <p>Dicetak: <?= bulan(date('Y-m-d')) ?></p>
        <p>Oleh: <?= e($USER_LOGIN['nama']) ?></p>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Pembiayaan</span>
          <span class="badge badge-gray">Mode Baca Saja</span>
          <?php if ($tagihanAktif): ?>
            <span class="badge badge-green">Tagihan berjalan sejak <?= e(bulan($umum['dijadwalkan_pada'] ?? date('Y-m-d'))) ?></span>
          <?php else: ?>
            <span class="badge badge-amber">Belum terhitung — hasil seleksi belum diterbitkan</span>
          <?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Pembiayaan &amp; Tunggakan</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Rincian <?= e(label_biaya_pendaftaran()) ?> untuk <?= $rekap['n'] ?> calon siswa yang lulus seleksi.
          Nominal per tahap dan status lunas memakai aturan yang sama dengan portal siswa.
        </p>
      </div>
    </div>

    <?php if (!$skema['aktif']): ?>
      <div class="mt-6 card p-5 border-amber-200 bg-amber-50 text-[13px] text-amber-800">
        <?= e(label_biaya_pendaftaran()) ?> di Pengaturan Sistem masih nol, sehingga skema angsuran belum terbentuk.
        Isi nominalnya lebih dulu agar halaman ini punya angka yang berarti.
      </div>
    <?php endif; ?>

    <!-- Kartu ringkas -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="card p-5">
        <p class="text-[13px] text-ink-500">Total tertagih</p>
        <p class="mt-2 text-2xl font-extrabold text-ink-900"><?= rupiah($rekap['tertagih']) ?></p>
        <p class="mt-2 text-xs text-ink-400"><?= $rekap['n'] ?> siswa × <?= rupiah($skema['total']) ?></p>
      </div>
      <div class="card p-5">
        <p class="text-[13px] text-ink-500">Dana terkumpul</p>
        <p class="mt-2 text-2xl font-extrabold text-[#065F46]"><?= rupiah($rekap['terkumpul']) ?></p>
        <p class="mt-2 text-xs text-ink-400">
          <?= $rekap['n'] > 0 ? round($rekap['terkumpul'] / max(1, $rekap['tertagih']) * 100, 1) : 0 ?>% dari tertagih
        </p>
      </div>
      <div class="card p-5">
        <p class="text-[13px] text-ink-500">Sisa tunggakan</p>
        <p class="mt-2 text-2xl font-extrabold text-[#991B1B]"><?= rupiah($rekap['tunggakan']) ?></p>
        <p class="mt-2 text-xs text-ink-400"><?= $rekap['belum_lunas'] ?> siswa belum lunas</p>
      </div>
      <div class="card p-5">
        <p class="text-[13px] text-ink-500">Menunggu verifikasi</p>
        <p class="mt-2 text-2xl font-extrabold text-[#92400E]"><?= rupiah($rekap['menunggu']) ?></p>
        <p class="mt-2 text-xs text-ink-400">Bukti transfer diperiksa bendahara</p>
      </div>
    </section>

    <!-- Per tahap -->
    <?php if ($skema['aktif']): ?>
      <section class="mt-6 grid gap-4 sm:grid-cols-2">
        <?php foreach ($skema['angsuran'] as $ke => $a):
          $nom = $rekap['tahap'][$ke]['nominal'];
          $bay = $rekap['tahap'][$ke]['terbayar'];
          $pct = $nom > 0 ? round($bay / $nom * 100, 1) : 0.0;
        ?>
          <div class="card p-5">
            <div class="flex items-center justify-between gap-3">
              <h2 class="font-bold text-ink-900">Angsuran <?= (int) $ke ?></h2>
              <span class="text-[12px] text-ink-500">
                <?php if (!empty($a['jatuh_tempo'])): ?>
                  jatuh tempo <?= e(bulan($a['jatuh_tempo'])) ?>
                <?php else: ?>
                  jatuh tempo belum diatur
                <?php endif; ?>
              </span>
            </div>
            <p class="mt-3 text-2xl font-extrabold text-ink-900"><?= rupiah($bay) ?> <span class="text-sm font-semibold text-ink-400">/ <?= rupiah($nom) ?></span></p>
            <div class="mt-3 h-2 rounded-full bg-line-soft overflow-hidden">
              <div class="h-full rounded-full <?= $pct >= 100 ? 'bg-[#0E9F6E]' : 'bg-primary' ?>" style="width: <?= min(100, $pct) ?>%"></div>
            </div>
            <p class="mt-2 text-xs text-ink-400"><?= $pct ?>% terbayar · <?= rupiah($a['nominal']) ?> per siswa</p>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <!-- Kontrol layar (tidak tercetak) -->
    <div class="no-print mt-6 card p-4 flex flex-col sm:flex-row sm:items-end gap-3">
      <form method="get" action="<?= url('app/kepsek/keuangan.php') ?>" class="flex flex-col sm:flex-row sm:items-end gap-3">
        <div>
          <label class="label" for="s">Tampilkan</label>
          <select id="s" name="s" class="select w-full sm:w-48">
            <?php foreach ($labelFilter as $kode => $label): ?>
              <option value="<?= e($kode) ?>" <?= $s === $kode ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-outline"><?= icon('filter_alt') ?> Terapkan</button>
      </form>
      <div class="flex-1"></div>
      <p class="text-[12px] text-ink-400"><?= count($tampil) ?> dari <?= count($baris) ?> baris</p>
    </div>

    <!-- Tabel -->
    <div class="mt-4 card overflow-hidden">
      <div class="overflow-x-auto scroll-thin">
        <table class="w-full min-w-[900px] border-collapse">
          <thead class="thead">
            <tr>
              <th class="th w-10">No</th>
              <th class="th">Pendaftaran</th>
              <th class="th">Calon Siswa</th>
              <th class="th">Kelas</th>
              <th class="th text-center">Angsuran 1</th>
              <th class="th text-center">Angsuran 2</th>
              <th class="th text-right">Terkumpul</th>
              <th class="th text-right">Sisa</th>
              <th class="th">Jatuh tempo</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <?php if (!$tampil): ?>
              <tr>
                <td class="td text-center text-ink-400" colspan="9">
                  <?= $rekap['n'] === 0 ? 'Belum ada calon siswa yang lulus seleksi.' : 'Tidak ada baris pada filter ini.' ?>
                </td>
              </tr>
            <?php endif; ?>
            <?php foreach ($tampil as $i => $b): ?>
              <tr class="tr-hover">
                <td class="td text-ink-400"><?= $i + 1 ?></td>
                <td class="td font-mono text-[12px] font-semibold text-ink-900 whitespace-nowrap"><?= e($b['reg']) ?></td>
                <td class="td">
                  <div class="font-semibold text-ink-900"><?= e($b['nama']) ?></div>
                  <div class="text-[11px] text-ink-400"><?= e($b['sekolah']) ?><?= $b['jalur'] ? ' · ' . e($b['jalur']) : '' ?></div>
                </td>
                <td class="td font-mono text-[12px]"><?= e($b['kelas'] ?: '—') ?></td>
                <?php foreach ([1, 2] as $ke): $a = $b['tahap'][$ke] ?? null; ?>
                  <td class="td text-center whitespace-nowrap">
                    <?php if ($a === null): ?>
                      <span class="text-ink-400">—</span>
                    <?php else: ?>
                      <?= badge($a['status'], $toneTahap[$a['status']] ?? 'gray') ?>
                      <div class="text-[11px] text-ink-400 mt-0.5"><?= rupiah($a['terbayar']) ?> / <?= rupiah($a['nominal']) ?></div>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <td class="td text-right font-semibold text-ink-900 whitespace-nowrap"><?= rupiah($b['terkumpul']) ?></td>
                <td class="td text-right font-bold whitespace-nowrap <?= $b['sisa'] > 0 ? ($b['terlambat'] ? 'text-[#991B1B]' : 'text-[#92400E]') : 'text-[#065F46]' ?>">
                  <?= $b['sisa'] > 0 ? rupiah($b['sisa']) : 'Lunas' ?>
                </td>
                <td class="td text-[12px] whitespace-nowrap">
                  <?= $b['jatuh'] ? e(bulan($b['jatuh'])) : '—' ?>
                  <?php if ($b['terlambat']): ?>
                    <span class="badge badge-red ml-1">lewat</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr class="bg-canvas">
              <td class="td font-semibold text-ink-700" colspan="6">Total <?= count($tampil) ?> baris</td>
              <td class="td text-right font-extrabold text-ink-900 whitespace-nowrap">
                <?= rupiah(array_sum(array_column($tampil, 'terkumpul'))) ?>
              </td>
              <td class="td text-right font-extrabold text-ink-900 whitespace-nowrap">
                <?= rupiah(array_sum(array_column($tampil, 'sisa'))) ?>
              </td>
              <td class="td"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <p class="mt-4 text-[12px] text-ink-400">
      Sisa dihitung dari pembayaran <?= e(label_biaya_pendaftaran()) ?> yang sudah diverifikasi bendahara dan dialokasikan
      berurutan dari angsuran 1. Bukti transfer yang masih menunggu verifikasi belum mengurangi sisa tagihan.
    </p>

    <!-- Tanda tangan -->
    <div class="mt-8 flex justify-end">
      <div class="text-center text-sm">
        <p class="text-ink-500"><?= bulan(date('Y-m-d')) ?></p>
        <p class="mt-1 font-medium text-ink-700"><?= e(trim((string) ($me['jabatan'] ?? '')) ?: 'Kepala Sekolah') ?></p>
        <div class="h-16"></div>
        <p class="font-semibold text-ink-900 underline underline-offset-4"><?= e($USER_LOGIN['nama']) ?></p>
      </div>
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
