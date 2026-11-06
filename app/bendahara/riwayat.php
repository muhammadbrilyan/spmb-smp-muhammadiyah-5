<?php
/* ==================================================================
 * RIWAYAT PEMBAYARAN CALON SISWA — rincian perorangan (baca saja).
 *
 * Menjawab pertanyaan "calon ini sudah bayar apa, berapa, lewat kanal mana,
 * kapan, dan siapa yang memverifikasi". Tabel tagihannya memakai
 * daftar_tagihan_baris() — fungsi yang sama dengan portal siswa — supaya
 * tidak pernah ada selisih baca antara wali dan bendahara.
 *
 * Bendahara TIDAK dikunci gerbang hasil seleksi: skema biaya pendaftaran
 * sudah terlihat di sini walau amplop siswa belum dibuka, karena uang yang
 * masuk tetap harus dipantau.
 *
 * Tidak ada aksi uang di halaman ini — menyetujui/menolak bukti pembayaran
 * tetap lewat Verifikasi Pembayaran.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['bendahara', 'admin']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$tagihanFormulir = (float) ($PENGATURAN['biaya_formulir'] ?? 0);
$skema           = angsuran_skema();
$umum            = pengumuman_status();
$hasilDiumumkan  = !empty($umum['diumumkan']);

/* Status yang dihitung sebagai dana masuk — sama dengan aturan di
 * ringkas_bayar_jenis(): bukti yang belum diputus bukan setoran. */
$STATUS_MASUK = ['VERIFIED', 'LUNAS', 'ANGSURAN_1', 'ANGSURAN_2'];

/* ==================================================================
 * Data: satu kueri transaksi untuk seluruh calon, dikelompokkan per pendaftar
 * ================================================================== */
$grup = [];
foreach (daftar_pembayaran() as $t) {
    $pid = (string) $t['pendaftar_id'];
    $g = $grup[$pid] ?? ['n' => 0, 'masuk' => 0.0, 'menunggu' => 0.0, 'ditolak' => 0];
    $g['n']++;
    if (in_array($t['status_raw'], $STATUS_MASUK, true)) {
        $g['masuk'] += (float) $t['nominal'];
    } elseif ($t['status_raw'] === 'PENDING') {
        $g['menunggu'] += (float) $t['nominal'];
    } elseif ($t['status_raw'] === 'REJECTED') {
        $g['ditolak']++;
    }
    $grup[$pid] = $g;
}

$kosongGrup = ['n' => 0, 'masuk' => 0.0, 'menunggu' => 0.0, 'ditolak' => 0];
$kosongBayar = ['tagihan' => 0.0, 'verified' => 0.0, 'pending' => 0.0, 'pending_bukti' => 0.0,
                'rejected' => 0.0, 'sisa' => 0.0, 'label' => '—', 'tone' => 'gray', 'rows' => []];

$calon = [];
foreach (daftar_pendaftar() as $p) {
    $g = $grup[(string) $p['id']] ?? $kosongGrup;

    /* Syarat tagihan biaya pendaftaran = salinan tagihan_pendaftaran_aktif(),
     * tapi memakai data yang sudah terbaca di daftar_pendaftar() (kolom kelas
     * datang dari JOIN penempatan_kelas) supaya daftar ini tidak membuat
     * kueri tambahan per baris. */
    $tertagih  = ($p['seleksi'] ?? '') === 'LULUS' && (($p['kelas'] ?? '') !== '' || $hasilDiumumkan);
    $kewajiban = $tagihanFormulir + ($tertagih ? (float) $skema['total'] : 0.0);
    $sisa      = max(0.0, $kewajiban - $g['masuk']);

    if ($kewajiban > 0 && $sisa <= 0.5) {
        $statusBayar = ['Lunas', 'green'];
    } elseif ($g['menunggu'] > 0) {
        $statusBayar = ['Menunggu Verifikasi', 'amber'];
    } elseif ($g['masuk'] > 0) {
        $statusBayar = ['Terbayar Sebagian', 'blue'];
    } elseif ($g['ditolak'] > 0) {
        $statusBayar = ['Bukti Ditolak', 'red'];
    } elseif ($g['n'] > 0) {
        $statusBayar = ['Menunggu Bukti', 'gray'];
    } else {
        $statusBayar = ['Belum Ada Transaksi', 'gray'];
    }

    $calon[] = [
        'id'         => $p['id'],
        'reg'        => $p['reg'],
        'nama'       => $p['nama'],
        'sekolah'    => $p['sekolah'],
        'nisn'       => $p['nisn'],
        'wali'       => $p['wali'],
        'wa'         => $p['wa'],
        'kelas'      => $p['kelas'],
        'tahap'      => $p['tahap'],
        'verifikasi' => $p['verifikasi'],
        'seleksi'    => $p['seleksi'],
        'tertagih'   => $tertagih,
        'kewajiban'  => $kewajiban,
        'masuk'      => $g['masuk'],
        'menunggu'   => $g['menunggu'],
        'ditolak'    => $g['ditolak'],
        'n'          => $g['n'],
        'sisa'       => $sisa,
        'status'     => $statusBayar[0],
        'tone'       => $statusBayar[1],
    ];
}

/* ---------- Saringan daftar calon (chip + pencarian) ---------- */
$CHIP = [
    'SEMUA'    => 'Semua Calon',
    'TUNDA'    => 'Masih Ada Sisa',
    'MENUNGGU' => 'Menunggu Verifikasi',
    'LUNAS'    => 'Sudah Lunas',
    'KOSONG'   => 'Belum Ada Transaksi',
];
$chip = strtoupper(trim((string) ($_GET['s'] ?? '')));
if (!isset($CHIP[$chip])) {
    $chip = 'SEMUA';
}
$q = trim((string) ($_GET['q'] ?? ''));

$daftarCalon = array_values(array_filter($calon, function ($c) use ($chip, $q) {
    if ($q !== '') {
        $jeruk = strtolower($q);
        $biasa = strtolower($c['reg'] . ' ' . $c['nama'] . ' ' . $c['nisn'] . ' '
            . $c['wali'] . ' ' . $c['sekolah'] . ' ' . $c['wa']);
        if (strpos($biasa, $jeruk) === false) {
            return false;
        }
    }
    if ($chip === 'TUNDA')    return $c['sisa'] > 0.5;
    if ($chip === 'MENUNGGU') return $c['menunggu'] > 0;
    if ($chip === 'LUNAS')    return $c['kewajiban'] > 0 && $c['sisa'] <= 0.5;
    if ($chip === 'KOSONG')   return $c['n'] === 0;
    return true;
}));

$hitungChip = ['SEMUA' => count($calon), 'TUNDA' => 0, 'MENUNGGU' => 0, 'LUNAS' => 0, 'KOSONG' => 0];
foreach ($calon as $c) {
    if ($c['sisa'] > 0.5)                                  $hitungChip['TUNDA']++;
    if ($c['menunggu'] > 0)                                $hitungChip['MENUNGGU']++;
    if ($c['kewajiban'] > 0 && $c['sisa'] <= 0.5)          $hitungChip['LUNAS']++;
    if ($c['n'] === 0)                                     $hitungChip['KOSONG']++;
}

/* ---------- Calon yang sedang dibuka ---------- */
$pilihId = trim((string) ($_GET['p'] ?? ''));
$pilih   = null;
foreach ($calon as $c) {
    if ((string) $c['id'] === $pilihId) {
        $pilih = $c;
        break;
    }
}

$daftar = ['rows' => [], 'total' => 0.0, 'sisa' => 0.0, 'belum_lunas' => 0,
           'formulir' => $kosongBayar, 'uang_masuk' => $kosongBayar];
$transaksi = [];
if ($pilih) {
    $transaksi = pembayaran_siswa($pilih['id']);
    $ringkas   = angsuran_ringkas($pilih['id']);
    $daftar    = daftar_tagihan_baris([
        'pendaftar_id'        => $pilih['id'],
        'rows'               => $transaksi,
        'tagihan_formulir'   => $tagihanFormulir,
        'tagihan_uang_masuk' => $pilih['tertagih'] ? (float) $ringkas['total'] : 0.0,
        'terbuka'            => $pilih['tertagih'],
        /* Bendahara tidak mengenal baris TERKUNCI: angka tagihan selalu dibaca
         * penuh agar setoran bisa dicocokkan sebelum hasil dibuka siswa. */
        'menunggu_hasil'     => false,
        'ringkas'            => $ringkas,
    ]);
}

$masukTerverifikasi = (float) $daftar['formulir']['verified'] + (float) $daftar['uang_masuk']['verified'];
$masukMenunggu      = (float) $daftar['formulir']['pending'] + (float) $daftar['uang_masuk']['pending'];

$warnaStatus = [
    'green' => 'text-[#0E9F6E]',
    'amber' => 'text-[#B45309]',
    'red'   => 'text-[#991B1B]',
    'blue'  => 'text-primary',
    'gray'  => 'text-ink-400',
];
$labelVer = [
    'DRAFT'               => ['Draft', 'gray'],
    'MENUNGGU_VERIFIKASI' => ['Menunggu Verifikasi Berkas', 'amber'],
    'TERVERIFIKASI'       => ['Berkas Terverifikasi', 'green'],
    'DITOLAK'             => ['Berkas Perlu Perbaikan', 'red'],
];
$labelSel = ['LULUS' => ['Lulus', 'green'], 'TIDAK_LULUS' => ['Tidak Lulus', 'red']];
$labelJenis = ['FORMULIR' => 'Biaya Formulir', 'UANG_MASUK' => label_biaya_pendaftaran()];
$labelStat = [
    'PENDING' => 'Menunggu Verifikasi', 'VERIFIED' => 'Terverifikasi', 'REJECTED' => 'Ditolak',
    'LUNAS' => 'Lunas', 'ANGSURAN_1' => 'Angsuran 1', 'ANGSURAN_2' => 'Angsuran 2',
];
$toneStat = [
    'PENDING' => 'amber', 'VERIFIED' => 'green', 'REJECTED' => 'red',
    'LUNAS' => 'green', 'ANGSURAN_1' => 'blue', 'ANGSURAN_2' => 'blue',
];
$labelMetode = ['GATEWAY' => 'Online', 'TRANSFER' => 'Transfer Manual', 'QRIS' => 'QRIS'];

/* Kanal pembayaran satu baris transaksi — aturan tampilan yang sama dengan
 * tabel tagihan (kolom gateway lebih dominan daripada kolom metode). */
$kanalTransaksi = static function (array $b) use ($labelMetode) {
    $kanal = trim((string) ($b['payment_method'] ?? ''));
    if ($kanal !== '') {
        return strtoupper($kanal);
    }
    return $labelMetode[(string) ($b['metode'] ?? '')] ?? '—';
};

$title     = $pilih ? 'Riwayat Pembayaran ' . $pilih['reg'] : 'Riwayat Pembayaran Calon Siswa';
$desc      = 'Rincian tagihan dan seluruh transaksi pembayaran satu calon siswa — read-only.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'riwayat-bayar';
$badgeRole  = 'BENDAHARA';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = $pilih
    ? ['Portal Akademik' => null, 'SPMB' => null, 'Riwayat Pembayaran' => url('app/bendahara/riwayat.php')]
    : ['Portal Akademik' => null, 'SPMB' => null, 'Riwayat Pembayaran' => null];
$pageActions = '
  <a href="' . url('app/bendahara/pembayaran.php') . '" class="btn btn-outline hidden md:inline-flex">' . icon('fact_check') . ' Antrean Verifikasi</a>
  <a href="' . url('app/bendahara/rekap.php') . '" class="btn btn-outline hidden md:inline-flex">' . icon('account_balance_wallet') . ' Rekap Keuangan</a>
';
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
?>

<div class="lg:pl-[264px]">

  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <?php if ($flash): ?>
      <div class="mb-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <!-- ====================== JUDUL ====================== -->
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Rincian Perorangan</span>
          <span class="badge badge-gray">Mode Baca Saja</span>
          <span class="badge badge-gray"><?= $hitungChip['MENUNGGU'] ?> calon menunggu verifikasi</span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">
          <?= $pilih ? 'Riwayat Pembayaran ' . e($pilih['nama']) : 'Riwayat Pembayaran Calon Siswa' ?>
        </h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Tagihan, setoran, kanal pembayaran, dan petugas yang memverifikasi untuk satu calon siswa.
          Susunan tabel tagihan sama persis dengan yang dibaca orang tua/wali di portal siswa.
        </p>
      </div>

      <div class="flex flex-wrap gap-2.5 shrink-0 no-print">
        <?php if ($pilih): ?>
          <a href="<?= url('app/bendahara/riwayat.php') ?>" class="btn btn-outline"><?= icon('manage_search') ?> Ganti Calon</a>
        <?php endif; ?>
        <button type="button" class="btn btn-primary shadow-drop" onclick="window.print()">
          <?= icon('print') ?> Cetak / Simpan PDF
        </button>
      </div>
    </div>

    <?php if ($pilihId !== '' && !$pilih): ?>
      <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Calon siswa tidak ditemukan — tautannya mungkin berasal dari pendaftaran yang sudah diarsipkan.
      </div>
    <?php endif; ?>

    <?php if (!$calon): ?>
      <div class="mt-6 card p-6 text-center">
        <span class="icon-box bg-canvas text-ink-400 mx-auto"><?= icon('group_off') ?></span>
        <p class="mt-3 font-semibold text-ink-900">Belum ada calon siswa terdaftar.</p>
        <p class="mt-1 text-[13px] text-ink-500">Riwayat pembayaran akan muncul begitu pendaftaran pertama masuk.</p>
      </div>

    <?php elseif (!$pilih): ?>
      <!-- ====================== PEMILIH CALON ====================== -->
      <div class="no-print mt-5 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex items-center gap-2 flex-wrap">
          <?php foreach ($CHIP as $k => $label): ?>
            <a href="<?= url('app/bendahara/riwayat.php' . ($k === 'SEMUA' ? '' : '?s=' . $k)) ?>"
               class="btn btn-sm <?= $chip === $k ? 'btn-primary shadow-drop' : 'btn-outline' ?>">
              <?= e($label) ?>
              <span class="ml-1.5 font-mono <?= $chip === $k ? 'text-white/80' : 'text-ink-400' ?>"><?= (int) $hitungChip[$k] ?></span>
            </a>
          <?php endforeach; ?>
        </div>
        <div class="flex-1"></div>
        <form method="get" action="<?= url('app/bendahara/riwayat.php') ?>" class="relative w-full max-w-md">
          <?php if ($chip !== 'SEMUA'): ?>
            <input type="hidden" name="s" value="<?= e($chip) ?>">
          <?php endif; ?>
          <span class="ms absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-ink-400">search</span>
          <input id="q" name="q" class="input pl-10" type="search" value="<?= e($q) ?>"
                 placeholder="Cari No. REG, nama, NISN, wali, atau nomor WhatsApp...">
        </form>
      </div>

      <div class="mt-4 card overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-line flex flex-wrap items-center justify-between gap-2">
          <div>
            <h2 class="font-bold text-ink-900">Pilih Calon Siswa</h2>
            <p class="mt-1 text-[13px] text-ink-500">Nominal mengikuti Pengaturan Sistem; status lunas hanya dari setoran yang diverifikasi.</p>
          </div>
          <span class="text-[13px] text-ink-500">
            <span class="font-semibold text-ink-900"><?= count($daftarCalon) ?></span> dari <?= count($calon) ?> calon
          </span>
        </div>

        <div class="overflow-x-auto scroll-thin">
          <table id="calon-table" class="w-full min-w-[960px] border-collapse" data-paginate="25">
            <thead class="thead">
              <tr>
                <th class="th w-[56px]">No.</th>
                <th class="th">Pendaftaran</th>
                <th class="th">Calon Siswa &amp; Asal Sekolah</th>
                <th class="th">Wali &amp; WhatsApp</th>
                <th class="th">Tahap</th>
                <th class="th text-right">Kewajiban</th>
                <th class="th text-right">Terverifikasi</th>
                <th class="th text-right">Sisa</th>
                <th class="th">Status Bayar</th>
                <th class="th text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php if (!$daftarCalon): ?>
                <tr>
                  <td class="td text-center text-ink-400" colspan="10">
                    Tidak ada calon siswa pada saringan<?= $q !== '' ? ' dan kata kunci "' . e($q) . '"' : '' ?> ini.
                  </td>
                </tr>
              <?php endif; ?>
              <?php foreach ($daftarCalon as $i => $c): ?>
                <tr class="tr-hover">
                  <td class="td text-[13px] text-ink-500"><?= $i + 1 ?></td>
                  <td class="td">
                    <a href="<?= url('app/bendahara/riwayat.php?p=' . rawurlencode((string) $c['id'])) ?>"
                       class="font-mono text-[13px] font-semibold text-primary hover:underline"><?= e($c['reg']) ?></a>
                    <div class="text-[11px] text-ink-400"><?= $c['n'] ?> transaksi</div>
                  </td>
                  <td class="td">
                    <p class="font-semibold text-ink-900"><?= e($c['nama']) ?></p>
                    <p class="mt-0.5 text-[12px] text-ink-500">NISN <?= e($c['nisn']) ?> · <?= e($c['sekolah']) ?></p>
                  </td>
                  <td class="td text-[13px]">
                    <p class="text-ink-700"><?= e($c['wali']) ?></p>
                    <p class="mt-0.5 font-mono text-[12px] text-ink-500"><?= e($c['wa']) ?></p>
                  </td>
                  <td class="td whitespace-nowrap">
                    <?php $t = label_tahap((string) $c['tahap']); ?>
                    <?= badge($t['label'], $t['tone']) ?>
                    <?php if (($sel = $labelSel[(string) $c['seleksi']] ?? null)): ?>
                      <div class="mt-1"><?= badge($sel[0], $sel[1]) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="td text-right font-semibold text-ink-900 whitespace-nowrap"><?= rupiah($c['kewajiban']) ?></td>
                  <td class="td text-right font-bold text-[#065F46] whitespace-nowrap"><?= rupiah($c['masuk']) ?></td>
                  <td class="td text-right font-bold whitespace-nowrap <?= $c['sisa'] > 0.5 ? 'text-[#B45309]' : 'text-ink-400' ?>">
                    <?= $c['sisa'] > 0.5 ? e(rupiah($c['sisa'])) : '—' ?>
                  </td>
                  <td class="td whitespace-nowrap"><?= badge($c['status'], $c['tone']) ?></td>
                  <td class="td text-right">
                    <a href="<?= url('app/bendahara/riwayat.php?p=' . rawurlencode((string) $c['id'])) ?>"
                       class="btn btn-sm btn-outline"><?= icon('receipt_long', 'text-[15px]') ?> Riwayat</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="px-4 py-3.5 border-t border-line bg-white flex flex-col md:flex-row items-center justify-between gap-3" data-pager-for="calon-table">
          <p class="text-[13px] text-ink-500" data-pager-info></p>
          <nav class="flex flex-wrap items-center justify-center gap-1.5" data-pager-nav aria-label="Halaman"></nav>
          <label class="flex items-center gap-2 text-[13px] text-ink-500">Per halaman
            <select class="select w-20" data-pager-size><option>10</option><option selected>25</option><option>50</option></select>
          </label>
        </div>
      </div>

    <?php else: ?>
      <!-- ====================== IDENTITAS CALON ====================== -->
      <section class="mt-5 card overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-line flex flex-wrap items-start justify-between gap-2">
          <div class="flex items-start gap-3 min-w-0">
            <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon('person') ?></span>
            <div class="min-w-0">
              <h2 class="font-bold text-ink-900 truncate"><?= e($pilih['nama']) ?></h2>
              <p class="mt-0.5 text-[13px] text-ink-500">
                <span class="font-mono font-semibold text-primary"><?= e($pilih['reg']) ?></span>
                · NISN <?= e($pilih['nisn']) ?> · <?= e($pilih['sekolah']) ?>
              </p>
            </div>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <?php $tv = $labelVer[(string) $pilih['verifikasi']] ?? [$pilih['verifikasi'] ?: '—', 'gray']; ?>
            <?= badge($tv[0], $tv[1]) ?>
            <?php if (($sel = $labelSel[(string) $pilih['seleksi']] ?? null)): ?>
              <?= badge('Hasil: ' . $sel[0], $sel[1]) ?>
            <?php else: ?>
              <?= badge('Hasil Belum Diumumkan', 'gray') ?>
            <?php endif; ?>
            <?= badge($pilih['status'], $pilih['tone']) ?>
          </div>
        </div>
        <dl class="px-5 sm:px-6 py-4 grid grid-cols-2 lg:grid-cols-4 gap-4 text-[13px]">
          <div>
            <dt class="text-ink-400">Wali / Orang Tua</dt>
            <dd class="mt-0.5 font-semibold text-ink-900"><?= e($pilih['wali']) ?></dd>
          </div>
          <div>
            <dt class="text-ink-400">Nomor WhatsApp</dt>
            <dd class="mt-0.5 font-mono text-ink-900"><?= e($pilih['wa']) ?></dd>
          </div>
          <div>
            <dt class="text-ink-400">Tahap Pendaftaran</dt>
            <dd class="mt-0.5 font-semibold text-ink-900"><?= e(label_tahap((string) $pilih['tahap'])['label']) ?></dd>
          </div>
          <div>
            <dt class="text-ink-400">Kelas</dt>
            <dd class="mt-0.5 font-semibold text-ink-900"><?= e($pilih['kelas'] !== '' ? $pilih['kelas'] : 'Belum ditempatkan') ?></dd>
          </div>
        </dl>
      </section>

      <!-- ====================== RINGKASAN ====================== -->
      <section class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
          <p class="text-[13px] text-ink-500">Total tagihan</p>
          <p class="mt-2 text-xl font-extrabold text-ink-900"><?= rupiah($daftar['total']) ?></p>
          <p class="mt-2 text-xs text-ink-400"><?= count($daftar['rows']) ?> pos tagihan</p>
        </div>
        <div class="card p-5">
          <p class="text-[13px] text-ink-500">Dana terverifikasi</p>
          <p class="mt-2 text-xl font-extrabold text-[#065F46]"><?= rupiah($masukTerverifikasi) ?></p>
          <p class="mt-2 text-xs text-ink-400">
            Formulir <?= e(rupiah((float) $daftar['formulir']['verified'])) ?>
            · <?= e(label_biaya_pendaftaran()) ?> <?= e(rupiah((float) $daftar['uang_masuk']['verified'])) ?>
          </p>
        </div>
        <div class="card p-5">
          <p class="text-[13px] text-ink-500">Menunggu verifikasi</p>
          <p class="mt-2 text-xl font-extrabold <?= $masukMenunggu > 0 ? 'text-[#92400E]' : 'text-ink-900' ?>"><?= rupiah($masukMenunggu) ?></p>
          <p class="mt-2 text-xs text-ink-400">
            <a href="<?= url('app/bendahara/pembayaran.php') ?>" class="text-primary hover:underline font-semibold">Buka antrean</a>
            untuk memutus bukti transfer
          </p>
        </div>
        <div class="card p-5">
          <p class="text-[13px] text-ink-500">Sisa yang harus dibayar</p>
          <p class="mt-2 text-xl font-extrabold <?= $daftar['sisa'] > 0 ? 'text-[#B45309]' : 'text-[#0E9F6E]' ?>"><?= rupiah($daftar['sisa']) ?></p>
          <p class="mt-2 text-xs text-ink-400">
            <?php if (!$pilih['tertagih']): ?>
              <?= e(label_biaya_pendaftaran()) ?> belum tertagih
            <?php elseif ($daftar['sisa'] > 0): ?>
              <?= $daftar['belum_lunas'] ?> pos masih terbuka
            <?php else: ?>
              Seluruh tagihan lunas
            <?php endif; ?>
          </p>
        </div>
      </section>

      <!-- ====================== DAFTAR TAGIHAN (CERMIN PORTAL SISWA) ====================== -->
      <?php if ($daftar['rows']): ?>
        <section class="mt-4 card overflow-hidden">
          <div class="px-5 sm:px-6 py-4 border-b border-line flex flex-wrap items-start justify-between gap-2">
            <div>
              <h2 class="font-bold text-ink-900">Daftar Tagihan &amp; Pembayaran</h2>
              <p class="mt-1 text-[13px] text-ink-500">
                Tampilan dan status yang sama dengan yang dilihat wali di portal siswa.
              </p>
            </div>
            <span class="badge badge-<?= $pilih['tertagih'] ? (($daftar['sisa'] > 0 || $daftar['belum_lunas'] > 0) ? 'amber' : 'green') : 'gray' ?>">
              <?php if (!$pilih['tertagih']): ?>
                Biaya Pendaftaran Belum Berlaku
              <?php elseif ($daftar['sisa'] > 0): ?>
                Sisa <?= e(rupiah($daftar['sisa'])) ?>
              <?php elseif ($daftar['belum_lunas'] > 0): ?>
                Belum Semua Lunas
              <?php else: ?>
                Semua tagihan lunas
              <?php endif; ?>
            </span>
          </div>

          <div class="overflow-x-auto scroll-thin">
            <table class="w-full min-w-[780px] border-collapse">
              <thead class="thead">
                <tr>
                  <th class="th w-[56px]">No.</th>
                  <th class="th">Pembayaran</th>
                  <th class="th text-right">Jumlah Bayar</th>
                  <th class="th">Status Bayar</th>
                  <th class="th">Tanggal Bayar</th>
                  <th class="th">Kanal Bayar</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-line-soft">
                <?php foreach ($daftar['rows'] as $i => $bt): ?>
                  <tr class="tr-hover">
                    <td class="td text-[13px] text-ink-500"><?= $i + 1 ?></td>
                    <td class="td">
                      <p class="font-semibold text-ink-900"><?= e($bt['nama']) ?></p>
                      <p class="mt-0.5 text-[12px] text-ink-500"><?= e($bt['ket']) ?></p>
                    </td>
                    <td class="td text-right font-bold text-ink-900 whitespace-nowrap">
                      <?= $bt['jumlah'] === null ? '—' : e(rupiah($bt['jumlah'])) ?>
                    </td>
                    <td class="td whitespace-nowrap">
                      <span class="inline-flex items-center gap-1.5 text-[13px] font-bold <?= $warnaStatus[$bt['tone']] ?>">
                        <span class="ms text-[17px]" aria-hidden="true"><?= e($bt['ikon']) ?></span>
                        <?= e($bt['status']) ?>
                      </span>
                    </td>
                    <td class="td text-[13px] whitespace-nowrap <?= $bt['tanggal'] ? 'text-ink-700' : 'text-ink-400' ?>">
                      <?= $bt['tanggal'] ? e($bt['tanggal']) : '—' ?>
                    </td>
                    <td class="td text-[13px] whitespace-nowrap <?= $bt['kanal'] ? 'font-semibold text-ink-900' : 'text-ink-400' ?>">
                      <?= $bt['kanal'] ? e($bt['kanal']) : '—' ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="border-t border-line bg-canvas">
                  <td class="td" colspan="2"><span class="font-bold text-ink-900">Total tagihan</span></td>
                  <td class="td text-right font-extrabold text-ink-900 whitespace-nowrap"><?= e(rupiah($daftar['total'])) ?></td>
                  <td class="td text-[13px] text-ink-500" colspan="3">
                    <?php if (!$pilih['tertagih']): ?>
                      Calon ini belum <?= e($pilih['seleksi'] === 'LULUS' ? 'punya kelas / hasilnya belum diumumkan' : 'dinyatakan lulus') ?>,
                      sehingga <?= e(label_biaya_pendaftaran()) ?> belum dihitung sebagai kewajiban.
                    <?php elseif ($daftar['sisa'] > 0): ?>
                      Sisa yang harus dibayar: <strong class="text-[#B45309]"><?= e(rupiah($daftar['sisa'])) ?></strong>
                    <?php elseif ($daftar['belum_lunas'] > 0): ?>
                      Masih ada baris yang belum lunas — periksa statusnya di atas.
                    <?php else: ?>
                      Seluruh tagihan sudah lunas dan terverifikasi.
                    <?php endif; ?>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </section>
      <?php endif; ?>

      <!-- ====================== SELURUH TRANSAKSI ====================== -->
      <section class="mt-4 card overflow-hidden">
        <div class="px-5 sm:px-6 py-4 border-b border-line flex flex-wrap items-start justify-between gap-2">
          <div>
            <h2 class="font-bold text-ink-900">Seluruh Transaksi</h2>
            <p class="mt-1 text-[13px] text-ink-500">
              Termasuk bukti yang masih menunggu dan yang ditolak, lengkap dengan verifikatornya.
            </p>
          </div>
          <span class="badge badge-gray"><?= count($transaksi) ?> baris</span>
        </div>

        <div class="overflow-x-auto scroll-thin">
          <table class="w-full min-w-[960px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th">Tanggal</th>
                <th class="th">Jenis Pembayaran</th>
                <th class="th text-right">Nominal</th>
                <th class="th">Kanal</th>
                <th class="th">Status</th>
                <th class="th">Diverifikasi Oleh</th>
                <th class="th">Bukti &amp; Catatan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php if (!$transaksi): ?>
                <tr>
                  <td class="td text-center text-ink-400" colspan="7">
                    Belum ada transaksi pembayaran untuk calon siswa ini.
                  </td>
                </tr>
              <?php endif; ?>
              <?php foreach ($transaksi as $b):
                $statusRaw = (string) $b['status'];
                $jenisLabel = $labelJenis[(string) $b['jenis_pembayaran']] ?? (string) $b['jenis_pembayaran'];
                if ($b['jenis_pembayaran'] === 'UANG_MASUK' && !empty($b['angsuran_ke'])) {
                    $jenisLabel .= ' · Angsuran ' . (int) $b['angsuran_ke'];
                }
                $waktu = (string) (($b['verified_at'] ?? '') ?: ($b['paid_at'] ?? '') ?: ($b['created_at'] ?? ''));
                $bukti = trim((string) ($b['bukti_transfer_url'] ?? ''));
              ?>
                <tr class="tr-hover">
                  <td class="td text-[13px] whitespace-nowrap">
                    <?= $waktu !== '' ? e(bulan($waktu)) : '—' ?>
                    <div class="text-[11px] text-ink-400">dicatat <?= $b['created_at'] ? e(bulan_pendek($b['created_at'])) : '—' ?></div>
                  </td>
                  <td class="td font-semibold text-ink-900"><?= e($jenisLabel) ?></td>
                  <td class="td text-right font-bold text-ink-900 whitespace-nowrap"><?= e(rupiah($b['nominal'])) ?></td>
                  <td class="td text-[13px] whitespace-nowrap <?= $kanalTransaksi($b) === '—' ? 'text-ink-400' : 'text-ink-700' ?>">
                    <?= e($kanalTransaksi($b)) ?>
                  </td>
                  <td class="td whitespace-nowrap"><?= badge($labelStat[$statusRaw] ?? $statusRaw, $toneStat[$statusRaw] ?? 'gray') ?></td>
                  <td class="td text-[13px] whitespace-nowrap">
                    <?php if (!empty($b['verifier'])): ?>
                      <p class="font-semibold text-ink-900"><?= e($b['verifier']) ?></p>
                      <p class="text-[11px] text-ink-400"><?= $b['verified_at'] ? e(date('H:i', strtotime($b['verified_at'])) . ' WIB') : '' ?></p>
                    <?php else: ?>
                      <span class="text-ink-400">Belum diverifikasi</span>
                    <?php endif; ?>
                  </td>
                  <td class="td text-[13px] max-w-[260px]">
                    <?php if ($bukti !== ''): ?>
                      <a href="<?= e(url('app/api/dokumen.php?f=' . rawurlencode(basename($bukti)))) ?>"
                         target="_blank" rel="noopener"
                         class="inline-flex items-center gap-1 font-semibold text-primary hover:underline">
                        <?= icon('receipt', 'text-[15px]') ?> Buka Bukti
                      </a>
                    <?php else: ?>
                      <span class="text-ink-400">Tanpa bukti lampiran</span>
                    <?php endif; ?>
                    <?php if (!empty($b['keterangan'])): ?>
                      <p class="mt-1 text-ink-500"><?= e($b['keterangan']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($b['catatan']) && $statusRaw === 'REJECTED'): ?>
                      <p class="mt-1 text-[12px] text-[#991B1B]">Alasan penolakan: <?= e($b['catatan']) ?></p>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr class="border-t border-line bg-canvas">
                <td class="td" colspan="2"><span class="font-bold text-ink-900">Total terverifikasi</span></td>
                <td class="td text-right font-extrabold text-[#065F46] whitespace-nowrap"><?= e(rupiah($masukTerverifikasi)) ?></td>
                <td class="td text-[13px] text-ink-500" colspan="4">
                  <?php if ($daftar['sisa'] > 0): ?>
                    Sisa tagihan <?= e(rupiah($daftar['sisa'])) ?>
                  <?php else: ?>
                    Tidak ada sisa tagihan
                  <?php endif; ?>
                  <?php if ($masukMenunggu > 0): ?>
                    · <?= e(rupiah($masukMenunggu)) ?> masih menunggu keputusan bendahara
                  <?php endif; ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </section>

      <div class="mt-4 rounded-2xl bg-primary-light border border-primary/15 p-4 flex items-start gap-3">
        <span class="icon-box bg-primary text-white shrink-0"><?= icon('info') ?></span>
        <div>
          <p class="font-semibold text-ink-900 text-sm">Cara membaca halaman ini</p>
          <p class="text-[13px] text-ink-500">
            Dana terverifikasi adalah satu-satunya dasar status lunas. Angsuran dihitung berurutan
            (FIFO) dari angsuran 1, dan nominal per tahap berasal dari Pengaturan Sistem — bila operator
            mengubah <?= e(label_biaya_pendaftaran()) ?>, seluruh sisa tagihan ikut berubah. Bendahara melihat
            skema <?= e(label_biaya_pendaftaran()) ?> <?= $pilih['tertagih'] ? 'yang sudah berlaku' : 'yang belum berlaku' ?>
            tanpa menunggu siswa membuka hasil seleksi di portalnya.
          </p>
        </div>
      </div>
    <?php endif; ?>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<?php if ($flash): ?>
<script>window.APP_FLASH = <?= json_encode(['tipe' => $flash['tipe'], 'pesan' => $flash['pesan']], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
