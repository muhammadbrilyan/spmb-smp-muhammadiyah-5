<?php
require_once __DIR__ . '/../../includes/config.php';

/* Hak akses: hanya role operator yang boleh membuka dashboard ini. */
require_role(['operator']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title = 'Dashboard Operasional & Seleksi SPMB';
$desc  = 'Dashboard operasional operator SPMB: manajemen akun & WA gateway, penjadwalan tes, input hasil seleksi, dan penempatan rombel.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* Sumber data: database db_spmb_mualimku (fallback dummy bila DB mati). */
$pendaftar = daftar_pendaftar();
$jadwal    = daftar_jadwal_tes();
$hasil     = daftar_hasil_seleksi();
$stat      = stat_operator();
$totalPend = count($pendaftar);

/* Pembayaran terbaru per pendaftar — dipakai modal detail agar nominal &
 * status "Uang masuk" nyata, bukan hardcoded. */
$bayarMap = [];
foreach ((array) q_all('SELECT pendaftar_id, jenis_pembayaran, nominal, status, metode, paid_at, verified_at, created_at
                          FROM pembayaran ORDER BY created_at') as $b) {
    $bayarMap[$b['pendaftar_id']] = $b;   // baris terakhir = pembayaran terbaru
}

/**
 * Status pembayaran ringkas untuk tabel: [teks, kelas warna, ikon].
 * Warna memakai kelas yang sudah ada di tailwind.css hasil build.
 */
function op_bayar_ringkas(?array $bayar)
{
    if (!$bayar) {
        return ['Belum Ada Pembayaran', 'text-ink-400', 'receipt_long'];
    }

    $jenis = ['FORMULIR' => 'Formulir', 'UANG_MASUK' => label_biaya_pendaftaran()][$bayar['jenis_pembayaran']] ?? 'Biaya';
    $peta  = [
        'PENDING'    => ['Menunggu Verifikasi Bendahara', 'text-[#92400E]', 'pending'],
        'REJECTED'   => ['Ditolak Bendahara', 'text-[#991B1B]', 'error'],
        'VERIFIED'   => ['Diterima Bendahara', 'text-[#065F46]', 'verified'],
        'LUNAS'      => ['Lunas', 'text-[#065F46]', 'paid'],
        'ANGSURAN_1' => ['Angsuran 1 Dibayar', 'text-[#065F46]', 'paid'],
        'ANGSURAN_2' => ['Angsuran 2 Dibayar', 'text-[#065F46]', 'paid'],
    ];
    [$teks, $warna, $ikon] = $peta[$bayar['status']] ?? [$bayar['status'], 'text-ink-500', 'receipt_long'];

    return [$jenis . ': ' . $teks, $warna, $ikon];
}

/** Waktu ringkas utk timeline: "12 Jan, 09:30 WIB" — '' bila kosong. */
function op_waktu($dt)
{
    $ts = $dt ? strtotime($dt) : false;
    return $ts ? bulan_pendek($dt) . ', ' . date('H:i', $ts) . ' WIB' : '';
}

/**
 * Bangun isi modal detail calon siswa 100% dari data nyata pendaftar tsb.
 * Semua nilai di-escape di sini; hasil hanya pernah dipasang via innerHTML
 * ke satu kontainer modal.
 */
function op_detail_html(array $p, ?array $bayar): string
{
    $barisDl = function ($label, $value) {
        return '<div class="flex justify-between gap-3"><dt class="text-ink-500">' . e($label) . '</dt>'
            . '<dd class="font-semibold text-ink-900 text-right break-words">' . e($value !== '' && $value !== null ? $value : '—') . '</dd></div>';
    };

    /* --- Biodata ---
     * NISN & Wali belum diminta saat registrasi awal: NISN diisi siswa di
     * portal (formulir), sedangkan nama wali placeholder dibuat otomatis
     * ("Orang Tua <nama>") oleh registrasi_baru(). Tampilkan statusnya,
     * bukan nilai kosong/otomatis yang terkesan data resmi. */
    $nisnTampil = (isset($p['nisn']) && $p['nisn'] !== '' && $p['nisn'] !== '—')
        ? $p['nisn'] : 'Belum diisi — diisi siswa di portal';
    $waliRaw   = (string) ($p['wali'] ?? '');
    $waliAuto  = 'Orang Tua ' . $p['nama'];
    $waliTampil = ($waliRaw === '' || $waliRaw === '—') ? 'Belum diisi'
        : ($waliRaw === $waliAuto ? 'Belum diisi (otomatis dari nama siswa)' : $waliRaw);

    $biodata = '<div class="rounded-xl border border-line p-4">'
        . '<p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Biodata Singkat</p>'
        . '<dl class="mt-3 space-y-2 text-sm">'
        . $barisDl('NISN', $nisnTampil)
        . $barisDl('Asal Sekolah', $p['sekolah'])
        . $barisDl('Wali', $waliTampil)
        . $barisDl('WhatsApp', $p['wa'])
        . '</dl></div>';

    /* --- Status administrasi (badge nyata per pendaftar) --- */
    $verifPeta = [
        'DRAFT'                => ['Draft Formulir', 'gray'],
        'MENUNGGU_VERIFIKASI'  => ['Menunggu Verifikasi Berkas', 'amber'],
        'TERVERIFIKASI'        => ['Berkas Terverifikasi', 'green'],
        'DITOLAK'              => ['Berkas Ditolak', 'red'],
    ];
    $v = $verifPeta[$p['verifikasi'] ?? ''] ?? ['—', 'gray'];
    $t = label_tahap($p['tahap'] ?? '');

    if ($bayar) {
        $s = $bayar['status'];
        $labelJenisBayar = ['FORMULIR' => 'Biaya formulir',
            'UANG_MASUK' => label_biaya_pendaftaran()][$bayar['jenis_pembayaran']] ?? 'Biaya';
        if ($bayar['jenis_pembayaran'] === 'UANG_MASUK' && !empty($bayar['angsuran_ke'])) {
            $labelJenisBayar .= ' · Angsuran ' . (int) $bayar['angsuran_ke'];
        }
        /* Label sama dengan tabel bendahara: angsuran tidak boleh disebut "Lunas". */
        $petaBayar = [
            'PENDING'    => ['Menunggu Verifikasi', 'amber'],
            'REJECTED'   => ['Bukti Ditolak', 'red'],
            'VERIFIED'   => ['Terverifikasi', 'green'],
            'ANGSURAN_1' => ['Angsuran 1 Dibayar', 'blue'],
            'ANGSURAN_2' => ['Angsuran 2 Dibayar', 'blue'],
            'LUNAS'      => ['Lunas', 'green'],
        ];
        $bayarTeks = $petaBayar[$s] ?? [str_replace('_', ' ', $s), 'blue'];
        $bayarHtml = '<div class="mt-4 rounded-lg bg-canvas border border-line p-3">'
            . '<p class="text-[12px] text-ink-500">' . e($labelJenisBayar) . ' (' . e(str_replace('_', ' ', (string) ($bayar['metode'] ?: 'Transfer'))) . ')</p>'
            . '<p class="font-bold text-ink-900">' . rupiah((float) $bayar['nominal'])
            . ' <span class="badge badge-' . $bayarTeks[1] . ' align-middle ml-1">' . e($bayarTeks[0]) . '</span></p>'
            . '</div>';
    } else {
        $bayarHtml = '<div class="mt-4 rounded-lg bg-canvas border border-line p-3">'
            . '<p class="text-[12px] text-ink-500">Pembayaran</p>'
            . '<p class="font-bold text-ink-400">Belum ada pembayaran tercatat</p></div>';
    }

    $status = '<div class="rounded-xl border border-line p-4">'
        . '<p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Status Administrasi</p>'
        . '<div class="mt-3 flex flex-wrap gap-2">'
        . badge($v[0], $v[1])
        . badge('Akun: ' . $p['akun'], $p['akun_tone'] ?? 'amber')
        . badge($t['label'], $t['tone'])
        . '</div>' . $bayarHtml . '</div>';

    /* --- Riwayat proses: hanya peristiwa yang benar-benar terjadi --- */
    $items = [];
    $items[] = ['check', '#ECFDF5', '#0E9F6E', 'Registrasi awal dibuat', op_waktu($p['created'] ?? '') ?: 'tanggal tidak tercatat'];

    if ($bayar) {
        $st = $bayar['status'];
        if (in_array($st, ['VERIFIED', 'LUNAS'], true)) {
            $items[] = ['check', '#ECFDF5', '#0E9F6E', 'Pembayaran diverifikasi bendahara', op_waktu($bayar['verified_at'] ?: $bayar['paid_at'])];
        } elseif ($st === 'REJECTED') {
            $items[] = ['error', '#FEF2F2', '#DC2626', 'Bukti pembayaran ditolak', op_waktu($bayar['created_at'])];
        } else {
            $items[] = ['schedule', '#FFFBEB', '#B45309', 'Bukti pembayaran menunggu verifikasi', op_waktu($bayar['created_at'])];
        }
    } else {
        $items[] = ['schedule', '#FFFBEB', '#B45309', 'Belum ada pembayaran tercatat', ''];
    }

    if (($p['akun'] ?? '') === 'Aktif') {
        $items[] = ['check', '#ECFDF5', '#0E9F6E', 'Akun portal & kredensial dikirim', (string) ($p['kred'] ?? '')];
    } elseif (($p['akun'] ?? '') === 'Gagal') {
        $items[] = ['error', '#FEF2F2', '#DC2626', 'Pengiriman kredensial gagal', (string) ($p['kred'] ?? '')];
    } else {
        $items[] = ['schedule', '#FFFBEB', '#B45309', 'Akun portal belum dibuat', 'Menunggu aksi operator'];
    }

    if (!empty($p['kelas'])) {
        $items[] = ['check', '#ECFDF5', '#0E9F6E', 'Ditempatkan di rombel ' . $p['kelas'], ''];
    }

    $li = '';
    foreach ($items as $it) {
        $li .= '<li class="flex gap-3">'
            . '<span class="icon-box w-7 h-7 shrink-0" style="background:' . $it[1] . ';color:' . $it[2] . '">' . icon($it[0], 'text-[16px]') . '</span>'
            . '<div class="text-sm"><p class="font-medium text-ink-900">' . e($it[3]) . '</p>'
            . ($it[4] !== '' ? '<p class="text-xs text-ink-400">' . e($it[4]) . '</p>' : '')
            . '</div></li>';
    }
    $riwayat = '<div class="sm:col-span-2 rounded-xl border border-line p-4">'
        . '<p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Riwayat Proses</p>'
        . '<ol class="mt-3 space-y-3">' . $li . '</ol></div>';

    return $biodata . $status . $riwayat;
}

/* Payload modal detail: id pendaftar => {jalur, html}. */
$detailData = [];
foreach ($pendaftar as $p) {
    $detailData[$p['id']] = [
        'jalur' => 'Jalur ' . ($p['jalur'] ?: 'Reguler'),
        'html'  => op_detail_html($p, $bayarMap[$p['id']] ?? null),
    ];
}

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'dashboard';
require __DIR__ . '/../../includes/partials/sidebar.php';

/* Pager paginasi sisi-klien (logika di assets/js/app.js: [data-pager-for]). */
function op_pager(string $idTabel): string
{
    return '<div class="px-4 py-3.5 border-t border-line bg-white flex flex-col md:flex-row items-center justify-between gap-3" data-pager-for="' . e($idTabel) . '">'
        . '<p class="text-[13px] text-ink-500" data-pager-info></p>'
        . '<nav class="flex flex-wrap items-center justify-center gap-1.5" data-pager-nav aria-label="Halaman"></nav>'
        . '<label class="flex items-center gap-2 text-[13px] text-ink-500">Per halaman'
        . '<select class="select w-20" data-pager-size><option>10</option><option>25</option><option>50</option></select></label>'
        . '</div>';
}

$breadcrumbs = ['Portal Akademik' => null, 'SPMB' => null, 'Operasional Operator SPMB' => null];
/* Dashboard ini murni pemantauan: aksi CRUD dan pengiriman dijalankan dari
 * menu sidebar masing-masing, bukan dari tombol di sini. */
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">

  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1440px]">

    <?php
    /* [PERBAIKAN] Status WA & statistik pesan kini dari data nyata.
     * Sebelumnya badge "Terhubung (42ms)", kartu "1.250 / 5.000" dan
     * "99.2% Sukses" seluruhnya hardcode sehingga menyesatkan.
     * Sumber: tabel wa_log (dicatat wa_log_catat()). */
    /* [PERBAIKAN 2] $waAktif semula membaca kolom DB saja, padahal wa_kirim()
     * membaca .env (FONNTE_TOKEN) lebih dulu. Akibatnya token yang diisi di .env
     * membuat badge tetap "Belum dikonfigurasi" walau gateway sebenarnya jalan.
     * Kini memakai fonnte_token() — sumber yang sama dengan wa_kirim(). */
    $waAktif   = fonnte_token() !== null;
    $waBulan   = (int) (q_col("SELECT COUNT(*) FROM wa_log WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')") ?? 0);
    $waSukses  = (int) (q_col("SELECT COUNT(*) FROM wa_log WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND status = 'TERKIRIM'") ?? 0);
    $waPersen  = $waBulan > 0 ? round($waSukses / $waBulan * 100, 1) : null;
    ?>

    <!-- ====================== JUDUL HALAMAN ====================== -->
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge <?= $waAktif ? 'badge-green' : 'badge-amber' ?>">
            <?= $waAktif ? dot('green') . ' WhatsApp Server: Terhubung' : dot('amber') . ' WhatsApp Server: Belum dikonfigurasi' ?>
          </span>
          <span class="badge badge-gray"><?= e($PENGATURAN['gelombang']) ?></span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Dashboard Operasional &amp; Seleksi SPMB</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          <?= e(APP_SHORT) ?> • Verifikasi berkas, penjadwalan tes, pengumuman WhatsApp, dan alokasi rombel <?= e($PENGATURAN['tahun_ajaran']) ?>.
        </p>
      </div>
    </div>

    <!-- ====================== KARTU STATistik ====================== -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Akun Siswa Terbit</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['akun_aktif'] ?><span class="text-base font-semibold text-ink-400"> / <?= (int) $stat['akun_total'] ?></span></p>
          </div>
          <span class="icon-box bg-primary-light text-primary"><?= icon('badge', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center justify-between text-xs">
          <span class="inline-flex items-center gap-1.5 text-[#065F46] font-semibold"><?= dot('green') ?> <?= number_format((float) $stat['akun_persen'], 1, ',', '.') ?>% Akun Aktif</span>
          <span class="text-ink-400">Kredensial Auto-Gen</span>
        </div>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Tes Terjadwal</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['peserta'] ?><span class="text-base font-semibold text-ink-400"> Calon Siswa</span></p>
          </div>
          <span class="icon-box bg-[#FFF7ED] text-[#EA580C]"><?= icon('event', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center justify-between text-xs">
          <span class="text-ink-600"><strong class="text-ink-900"><?= (int) $stat['offline'] ?></strong> Offline di Sekolah</span>
          <span class="text-ink-600"><strong class="text-ink-900"><?= (int) $stat['daring'] ?></strong> Daring VC</span>
        </div>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Hasil Seleksi</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['dinilai'] ?><span class="text-base font-semibold text-ink-400"> Telah Dinilai</span></p>
          </div>
          <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E]"><?= icon('verified', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center gap-2 text-xs">
          <?= badge($stat['lulus'] . ' Lulus', 'green') ?>
          <?= badge($stat['proses'] . ' Proses', 'amber') ?>
        </div>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Alokasi Rombel</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['terisi'] ?><span class="text-base font-semibold text-ink-400"> / <?= (int) $stat['kuota'] ?> Kuota</span></p>
          </div>
          <span class="icon-box bg-[#EFF6FF] text-[#2563EB]"><?= icon('meeting_room', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4">
          <div class="h-1.5 rounded-full bg-line-soft overflow-hidden">
            <div class="h-full rounded-full bg-primary" style="width:<?= (float) $stat['persen_terisi'] ?>%"></div>
          </div>
          <div class="mt-2 flex items-center justify-between text-xs">
            <span class="text-ink-500"><?= (int) $stat['rombel'] ?> Rombel (@<?= (int) round($stat['kuota'] / max(1, (int) $stat['rombel'])) ?> Kursi)</span>
            <span class="font-semibold text-primary"><?= number_format((float) $stat['persen_terisi'], 1, ',', '.') ?>% Terisi</span>
          </div>
        </div>
      </article>

    </section>

    <!-- ====================== TAB UTAMA ====================== -->
    <section class="mt-6 card p-2">
      <div class="flex gap-1 overflow-x-auto scroll-thin" role="tablist">
        <button role="tab" class="flex-1 min-w-[150px] flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-primary bg-white shadow-card transition" data-tab-target="akun" data-active="true">
          <span class="ms text-[19px]">send</span> Akun &amp; WA
        </button>
        <button role="tab" class="flex-1 min-w-[150px] flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-ink-500 hover:text-ink-900 transition" data-tab-target="jadwal" data-active="false">
          <span class="ms text-[19px]">event</span> Jadwal Tes
        </button>
        <button role="tab" class="flex-1 min-w-[150px] flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-ink-500 hover:text-ink-900 transition" data-tab-target="hasil" data-active="false">
          <span class="ms text-[19px]">edit_note</span> Hasil Seleksi
        </button>
        <button role="tab" class="flex-1 min-w-[150px] flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-ink-500 hover:text-ink-900 transition" data-tab-target="rombel" data-active="false">
          <span class="ms text-[19px]">meeting_room</span> Penempatan Kelas
        </button>
      </div>
    </section>

    <!-- ====================== PANEL 1: AKUN & WA ====================== -->
    <section data-tab-panel="akun" class="mt-5">

      <!-- Toolbar: cari + filter status akun -->
      <div class="mt-5 flex flex-col md:flex-row md:items-center gap-3">
        <div class="relative flex-1 max-w-md">
          <span class="ms absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-ink-400">search</span>
          <input id="table-search" class="input pl-10" type="search" placeholder="Cari No. REG, Nama Siswa, Sekolah...">
        </div>
        <select class="select md:w-52" data-filter-status="data-status">
          <option value="">Semua Status Akun</option>
          <option value="Aktif">Aktif</option>
          <option value="Belum Dibuat">Belum Dibuat</option>
          <option value="Gagal">Gagal</option>
        </select>
      </div>

      <!-- Tabel -->
      <div class="mt-4 card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
          <table id="data-table" class="w-full min-w-[820px] border-collapse" data-paginate="10">
            <thead class="thead">
              <tr>
                <th class="th">No. Pendaftaran</th>
                <th class="th">Nama Siswa &amp; Asal Sekolah</th>
                <th class="th">No. WA Orang Tua</th>
                <th class="th">Status Akun &amp; Kredensial</th>
                <th class="th text-right">Detail</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php foreach ($pendaftar as $p): ?>
                <?php $toneAkun = $p['akun'] === 'Aktif' ? 'green' : ($p['akun'] === 'Gagal' ? 'red' : 'amber'); ?>
                <tr class="tr-hover" data-status="<?= e($p['akun']) ?>">
                  <td class="td">
                    <button class="font-mono text-[13px] font-semibold text-primary hover:underline text-left"
                            data-modal-open="modal-detail" data-modal-title="<?= e($p['reg']) ?>" data-modal-name="<?= e($p['nama']) ?>"
                            data-detail-id="<?= e($p['id']) ?>">
                      <?= e($p['reg']) ?>
                    </button>
                    <?php if (!empty($p['nisn']) && $p['nisn'] !== '—'): ?>
                      <div class="text-[11px] text-ink-400">NISN <?= e($p['nisn']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="td">
                    <div class="font-semibold text-ink-900"><?= e($p['nama']) ?></div>
                    <div class="text-[12px] text-ink-500"><?= e($p['sekolah']) ?> • Jalur <?= e($p['jalur']) ?></div>
                  </td>
                  <td class="td"><span class="font-mono text-[13px]"><?= e($p['wa']) ?></span></td>
                  <td class="td">
                    <?= badge($p['akun'], $toneAkun) ?>
                    <div class="mt-1 flex items-center gap-1 text-[12px] <?= $p['kred_tone'] === 'green' ? 'text-[#065F46]' : ($p['kred_tone'] === 'red' ? 'text-[#991B1B]' : 'text-[#92400E]') ?>">
                      <span class="ms text-[14px]"><?= $p['kred_tone'] === 'green' ? 'done_all' : ($p['kred_tone'] === 'red' ? 'error' : 'schedule') ?></span>
                      <?= e($p['kred']) ?>
                    </div>
                    <?php [$bayarTeks, $bayarWarna, $bayarIkon] = op_bayar_ringkas($bayarMap[$p['id']] ?? null); ?>
                    <div class="mt-1 flex items-center gap-1 text-[12px] font-medium <?= $bayarWarna ?>"
                         title="Status pembayaran terakhir — verifikasi dilakukan bendahara">
                      <span class="ms text-[14px]"><?= e($bayarIkon) ?></span>
                      <?= e($bayarTeks) ?>
                    </div>
                  </td>
                  <td class="td text-right">
                    <div class="inline-flex items-center gap-2 justify-end">
                      <button class="icon-box border border-line text-ink-500 hover:text-primary hover:border-primary"
                              data-modal-open="modal-detail" data-modal-title="<?= e($p['reg']) ?>" data-modal-name="<?= e($p['nama']) ?>"
                              data-detail-id="<?= e($p['id']) ?>"
                              aria-label="Lihat detail"><?= icon('visibility', 'text-[18px]') ?></button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?= op_pager('data-table') ?>
        <div class="px-4 pb-3.5 -mt-1 text-right bg-white">
          <a href="<?= url('app/operator/pendaftar.php') ?>" class="text-[13px] font-semibold text-primary hover:underline">Filter &amp; pencarian lanjutan di Data Pendaftar →</a>
        </div>
      </div>

      <!-- Catatan SOP -->
      <div class="mt-4 rounded-2xl bg-primary-light border border-primary/15 p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
        <div class="flex items-start gap-3">
          <span class="icon-box bg-primary text-white shrink-0"><?= icon('info') ?></span>
          <div>
            <p class="font-semibold text-ink-900 text-sm">Prosedur Verifikasi Dokumen &amp; Berkas Ujian CBT</p>
            <p class="text-[13px] text-ink-500">Calon siswa yang terverifikasi otomatis menerima kartu ujian digital dan notifikasi WhatsApp orang tua/wali.</p>
          </div>
        </div>
        <a href="<?= url('app/operator/panduan.php') ?>" class="btn btn-sm btn-outline shrink-0">Panduan SOP SPMB <?= icon('arrow_forward', 'text-[16px]') ?></a>
      </div>

      <!-- Status server WA — dipindah ke sini dari atas panel agar area kerja utama bersih.
           $waAktif dihitung di bagian atas halaman; ringkas jadi satu strip. -->
      <div class="mt-4 rounded-xl border <?= $waAktif ? 'border-[#A7F3D0] bg-[#ECFDF5]' : 'border-[#FDE68A] bg-[#FFFBEB]' ?> px-4 py-3 flex flex-col sm:flex-row sm:items-center gap-3">
        <span class="icon-box <?= $waAktif ? 'bg-[#0E9F6E]' : 'bg-[#D97706]' ?> text-white shrink-0"><?= icon('sms', 'text-[20px]') ?></span>
        <div class="flex-1 min-w-0">
          <p class="font-semibold text-sm <?= $waAktif ? 'text-[#065F46]' : 'text-[#92400E]' ?>"><?= $waAktif ? 'WhatsApp Gateway aktif' : 'WhatsApp Gateway belum dikonfigurasi' ?></p>
          <p class="text-xs <?= $waAktif ? 'text-[#065F46]/80' : 'text-[#92400E]/80' ?> mt-0.5">
            <?php if ($waAktif): ?>
              Bulan ini: <strong><?= number_format($waBulan) ?></strong> pesan tercatat<?= $waPersen !== null ? ', <strong>' . number_format($waPersen, 1, ',', '.') . '%</strong> terkirim' : '' ?>.
            <?php else: ?>
              Atur token Fonnte di <a class="underline font-semibold" href="<?= url('app/operator/pengaturan.php') ?>">Pengaturan Sistem</a> → WhatsApp Gateway.
            <?php endif; ?>
          </p>
        </div>
      </div>

    </section>

    <!-- ====================== PANEL 2: JADWAL TES ====================== -->
    <section data-tab-panel="jadwal" class="mt-5 hidden">
      <p class="text-sm text-ink-500">Jadwal tes CBT, wawancara BTQ, dan seleksi minat bakat (offline / video call).</p>

      <div class="mt-4 card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
          <table id="tabel-jadwal" class="w-full min-w-[820px] border-collapse" data-paginate="10">
            <thead class="thead">
              <tr>
                <th class="th">Kode</th>
                <th class="th">Nama Kegiatan</th>
                <th class="th">Tanggal &amp; Waktu</th>
                <th class="th">Metode</th>
                <th class="th">Peserta</th>
                <th class="th">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php foreach ($jadwal as $j): ?>
                <tr class="tr-hover">
                  <td class="td font-mono text-[13px] text-ink-900"><?= e($j['kode']) ?></td>
                  <td class="td font-semibold text-ink-900"><?= e($j['nama']) ?></td>
                  <td class="td"><?= bulan($j['tgl']) ?><div class="text-[12px] text-ink-400"><?= e($j['jam']) ?> WIB</div></td>
                  <td class="td">
                    <span class="badge <?= $j['mode'] === 'Video Call' ? 'badge-blue' : 'badge-gray' ?>"><?= e($j['mode']) ?></span>
                  </td>
                  <td class="td"><span class="font-semibold text-ink-900"><?= (int) $j['peserta'] ?></span> siswa</td>
                  <td class="td"><?= badge($j['status'], $j['tone']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?= op_pager('tabel-jadwal') ?>
      </div>
    </section>

    <!-- ====================== PANEL 3: HASIL SELEKSI ====================== -->
    <section data-tab-panel="hasil" class="mt-5 hidden">
      <p class="text-sm text-ink-500">Ringkasan nilai CBT, BTQ, dan wawancara — status <strong>LULUS</strong> / <strong>TIDAK_LULUS</strong> ditentukan otomatis dari nilai akhir.</p>

      <div class="mt-4 card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
          <table id="tabel-hasil" class="w-full min-w-[880px] border-collapse" data-paginate="10">
            <thead class="thead">
              <tr>
                <th class="th">No. Pendaftaran</th>
                <th class="th">Nama Calon Siswa</th>
                <th class="th text-center">Nilai CBT</th>
                <th class="th text-center">Nilai BTQ</th>
                <th class="th text-center">Wawancara</th>
                <th class="th text-center">Nilai Akhir</th>
                <th class="th text-center">Keputusan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php foreach ($hasil as $h): ?>
                <?php $tone = $h['status'] === 'LULUS' ? 'green' : ($h['status'] === 'PROSES' ? 'amber' : 'red'); ?>
                <tr class="tr-hover">
                  <td class="td font-mono text-[13px] text-primary"><?= e($h['reg']) ?></td>
                  <td class="td font-semibold text-ink-900"><?= e($h['nama']) ?></td>
                  <td class="td text-center">
                    <span class="font-semibold text-ink-900"><?= (int) $h['cbt'] ?></span>
                  </td>
                  <td class="td text-center">
                    <span class="font-semibold text-ink-900"><?= (int) $h['btq'] ?></span>
                  </td>
                  <td class="td text-center">
                    <span class="font-semibold text-ink-900"><?= (int) $h['waw'] ?></span>
                  </td>
                  <td class="td text-center"><span class="text-base font-extrabold text-ink-900"><?= number_format($h['nilai'], 1, ',', '.') ?></span></td>
                  <td class="td text-center"><?= badge($h['status'], $tone) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?= op_pager('tabel-hasil') ?>
      </div>
    </section>

    <!-- ====================== PANEL 4: ROMBEL ====================== -->
    <section data-tab-panel="rombel" class="mt-5 hidden">
      <p class="text-sm text-ink-500">Penempatan kelas dinamis. Total kuota otomatis dihitung dari seluruh rombel aktif (<strong><?= (int) $PENGATURAN['kuota_total'] ?> siswa</strong>).</p>

      <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($ROMBEL as $r):
          $persen = round($r['terisi'] / $r['kuota'] * 100);
          $sisa   = $r['kuota'] - $r['terisi'];
          $tone   = $persen >= 90 ? 'bg-red-500' : ($persen >= 75 ? 'bg-amber-500' : 'bg-primary');
          $badge  = $persen >= 90 ? ['Hampir Penuh', 'red'] : ($persen >= 75 ? ['Terisi', 'amber'] : ['Tersedia', 'green']);
        ?>
          <article class="card p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-start gap-3 min-w-0">
                <span class="w-11 h-11 rounded-xl bg-primary-light text-primary grid place-items-center font-bold shrink-0"><?= e($r['kode']) ?></span>
                <div class="min-w-0">
                  <h3 class="font-bold text-ink-900 text-sm leading-snug"><?= e($r['nama']) ?></h3>
                  <p class="text-[12px] text-ink-500 mt-0.5">Wali Kelas: <?= e($r['wali']) ?></p>
                </div>
              </div>
              <?= badge($badge[0], $badge[1]) ?>
            </div>

            <div class="mt-4 flex items-end justify-between">
              <div>
                <span class="text-2xl font-extrabold text-ink-900"><?= (int) $r['terisi'] ?></span>
                <span class="text-sm font-semibold text-ink-400"> / <?= (int) $r['kuota'] ?> Siswa</span>
              </div>
              <span class="text-sm font-semibold <?= $persen >= 90 ? 'text-red-600' : 'text-primary' ?>">Sisa <?= $sisa ?> Kursi</span>
            </div>

            <div class="mt-3 h-2 rounded-full bg-line-soft overflow-hidden">
              <div class="h-full rounded-full <?= $tone ?>" style="width:<?= $persen ?>%"></div>
            </div>

            <div class="mt-4 pt-4 border-t border-line-soft">
              <span class="text-xs text-ink-500">Persentase Keterisian: <strong class="text-ink-900"><?= number_format($persen, 1, ',', '.') ?>%</strong></span>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="mt-4 rounded-2xl bg-white border border-line p-4 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
          <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E]"><?= icon('groups') ?></span>
          <p class="text-sm text-ink-600">Total <strong class="text-ink-900"><?= (int) $stat['terisi'] ?> siswa</strong> telah ditempatkan dari <strong class="text-ink-900"><?= (int) $stat['kuota'] ?> kursi</strong> tersedia</p>
        </div>
        <span class="badge badge-blue">Keterisian Total: <?= number_format((float) $stat['persen_terisi'], 1, ',', '.') ?>%</span>
      </div>
    </section>

  </main>
</div>

<!-- ====================== MODAL DETAIL PENDAFTAR ====================== -->
<div id="modal-detail" class="modal-root hidden fixed inset-0 z-[60]">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-start sm:items-center justify-center p-4 sm:p-6 overflow-y-auto scroll-thin">
    <div class="animate-pop w-full max-w-2xl bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden my-auto">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
        <div class="flex items-start gap-3 min-w-0">
          <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon('badge') ?></span>
          <div class="min-w-0">
            <h3 class="font-bold text-ink-900 text-lg leading-tight">Detail Calon Siswa <span id="modal-reg" class="font-mono text-primary text-sm">#REG</span></h3>
            <p class="text-[13px] text-ink-500 truncate">Nama: <strong id="modal-nama" class="text-ink-700">—</strong> • <span id="modal-jalur">—</span></p>
          </div>
        </div>
        <button class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <!-- Isi 100% data nyata — dipasang dari window.__DETAIL (lihat bawah halaman) -->
      <div id="modal-detail-body" class="p-5 grid sm:grid-cols-2 gap-4">
        <p class="sm:col-span-2 text-sm text-ink-500">Tidak ada data untuk pendaftar ini.</p>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end">
        <button class="btn btn-outline" data-modal-close>Batal</button>
      </div>

    </div>
  </div>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script id="detail-data" type="application/json"><?= json_encode($detailData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>
  (function () {
    /* Isi modal detail calon siswa dengan data nyata per pendaftar
       (dibangun PHP di server, sudah ter-escape). */
    var data = {};
    try { data = JSON.parse(document.getElementById('detail-data').textContent) || {}; } catch (e) {}
    document.addEventListener('click', function (ev) {
      var t = ev.target.closest && ev.target.closest('[data-detail-id]');
      if (!t) return;
      var d    = data[t.getAttribute('data-detail-id')];
      var body = document.getElementById('modal-detail-body');
      var jl   = document.getElementById('modal-jalur');
      if (!body) return;
      body.innerHTML = d ? d.html : '<p class="sm:col-span-2 text-sm text-ink-500">Data pendaftar tidak ditemukan — muat ulang halaman.</p>';
      if (jl) jl.textContent = d ? d.jalur : '\u2014';
    });
  })();
</script>
</body>
</html>
