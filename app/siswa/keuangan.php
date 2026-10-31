<?php
/**
 * KEUANGAN — portal siswa (Fase 13).
 *
 * Tagihan & riwayat pembayaran. Nominal tagihan selalu berasal dari
 * Pengaturan Sistem; halaman ini TIDAK mengubah biaya global.
 * Status "lunas" hanya berasal dari pembayaran yang sudah diverifikasi
 * bendahara — unggah bukti hanya menghasilkan "Menunggu Verifikasi".
 */
$title = 'Keuangan';
require __DIR__ . '/_bootstrap.php';

/* Label biaya mengikuti Pengaturan Sistem — baru bisa dibaca setelah bootstrap. */
$desc  = 'Biaya formulir, ' . label_biaya_pendaftaran() . ', status verifikasi pembayaran, rincian tagihan, dan riwayat.';

$skemaAngsuran = angsuran_skema();
$pendaftaranTerbuka = $siswa ? tagihan_pendaftaran_aktif($siswa) : false;
if ($siswa && $ringkasAngsuran === null) {
    $ringkasAngsuran = angsuran_ringkas($siswa['id']);
}

/* Nilai untuk modal unggah & pembayaran online (dipakai di kedua tab). */
$angsuranAktifGlobal = $skemaAngsuran['aktif'] && $pendaftaranTerbuka && !empty($ringkasAngsuran);
$angsuranBerikutGlobal = $angsuranAktifGlobal ? (int) ($ringkasAngsuran['berikut'] ?? 0) : 0;
$angsuranSisaGlobal = $angsuranBerikutGlobal
    ? (float) $ringkasAngsuran['angsuran'][$angsuranBerikutGlobal]['sisa']
    : 0.0;

$tabs = [
    'tagihan' => ['Tagihan', 'receipt_long'],
    'riwayat' => ['Riwayat', 'history'],
];
$tab = (string) ($_GET['tab'] ?? 'tagihan');
if (!isset($tabs[$tab])) {
    $tab = 'tagihan';
}

$kembali = function ($tujuan) {
    header('Location: ' . url('app/siswa/keuangan.php?tab=' . rawurlencode($tujuan)));
    exit;
};

/* ==================================================================
 * PROSES POST — UNGGAH BUKTI TRANSFER MANUAL
 * ================================================================== */
if ($siswa && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        $kembali($tab);
    }

    $aksi = (string) ($_POST['aksi'] ?? '');
    if ($aksi !== 'unggah_bukti') {
        flash('error', 'Aksi tidak dikenali.');
        $kembali($tab);
    }

    $jenis   = ($_POST['jenis_pembayaran'] ?? '') === 'UANG_MASUK' ? 'UANG_MASUK' : 'FORMULIR';
    $nominal = (float) ($_POST['nominal'] ?? 0);
    $idemKey = idem_key_valid($_POST['idem_key'] ?? '');
    $angsuranKe = $jenis === 'UANG_MASUK' ? (int) ($_POST['angsuran_ke'] ?? 0) : 0;
    $modeBayar  = ($_POST['mode'] ?? '') === 'ANGSURAN' ? 'ANGSURAN' : 'LUNAS';
    $ringkas = $jenis === 'UANG_MASUK' ? $bayarUangMasuk : $bayarFormulir;

    $galat = [];
    if (empty($PENGATURAN['bayar_transfer_aktif'])) {
        $galat[] = 'Pembayaran transfer sedang tidak aktif.';
    }
    if ($idemKey === null) {
        $galat[] = 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    }
    if ($ringkas['tagihan'] <= 0) {
        if ($jenis === 'UANG_MASUK' && !$pendaftaranTerbuka) {
            $galat[] = label_biaya_pendaftaran() . ' belum dapat dibayar. Tagihan dibuka setelah hasil seleksi diumumkan dan Anda dinyatakan LULUS.';
        } else {
            $galat[] = 'Nominal ' . ($jenis === 'UANG_MASUK' ? label_biaya_pendaftaran() : 'Biaya Formulir') . ' belum ditetapkan panitia.';
        }
    }
    if ($ringkas['sisa'] <= 0) {
        $galat[] = 'Tagihan ini sudah lunas / terverifikasi.';
    }
    if ($ringkas['pending_bukti'] > 0) {
        $galat[] = 'Bukti pembayaran sebelumnya masih menunggu verifikasi bendahara.';
    }

    /* Validasi mode bayar: LUNAS = sisa penuh tanpa nomor angsuran;
     * ANGSURAN = hanya angsuran berikutnya (nominal otomatis setengah). */
    $angsuranBayar = null;
    if ($jenis === 'UANG_MASUK' && $ringkas['tagihan'] > 0) {
        if ($modeBayar === 'ANGSURAN' && $skemaAngsuran['aktif']) {
            if (empty($ringkasAngsuran['angsuran'][$angsuranKe])) {
                $galat[] = 'Pilih angsuran yang akan dibayar.';
            } elseif ((float) $ringkasAngsuran['pending'] > 0) {
                $galat[] = 'Masih ada pembayaran angsuran yang menunggu verifikasi / penyelesaian.';
            } elseif ($ringkasAngsuran['lunas']) {
                $galat[] = 'Tagihan ini sudah lunas.';
            } elseif ((int) $ringkasAngsuran['berikut'] !== $angsuranKe) {
                $galat[] = $ringkasAngsuran['berikut']
                    ? 'Saat ini yang dapat dibayar adalah angsuran ' . (int) $ringkasAngsuran['berikut'] . '.'
                    : 'Selesaikan verifikasi angsuran sebelumnya terlebih dahulu.';
            } else {
                $angsuranBayar = $ringkasAngsuran['angsuran'][$angsuranKe];
            }
        } else {
            $modeBayar  = 'LUNAS';
            $angsuranKe = 0;
        }
    }

    $batasSisa = $angsuranBayar ? (float) $angsuranBayar['sisa'] : (float) $ringkas['sisa'];
    if ($nominal <= 0) {
        $galat[] = 'Nominal transfer harus lebih besar dari 0.';
    } elseif ($batasSisa > 0 && $nominal > $batasSisa + 0.01) {
        $galat[] = 'Nominal melebihi sisa ' . ($angsuranBayar ? 'angsuran ' . $angsuranKe : 'tagihan') . ' (' . rupiah($batasSisa) . ').';
    }

    $berkas = $_FILES['bukti'] ?? null;
    if (!$berkas || $berkas['error'] === UPLOAD_ERR_NO_FILE) {
        $galat[] = 'Pilih foto bukti transfer.';
    } elseif ($berkas['error'] !== UPLOAD_ERR_OK) {
        $galat[] = 'Unggahan gagal (kode ' . (int) $berkas['error'] . ').';
    } else {
        $ekstensi = strtolower(pathinfo((string) $berkas['name'], PATHINFO_EXTENSION));
        if (!in_array($ekstensi, ['jpg', 'jpeg', 'png'], true)) {
            $galat[] = 'Bukti transfer harus JPG atau PNG.';
        } elseif ($berkas['size'] > 2 * 1024 * 1024) {
            $galat[] = 'Ukuran bukti transfer maksimal 2 MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = (string) $finfo->file($berkas['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                $galat[] = 'Isi berkas bukan gambar yang sah.';
            }
        }
    }

    if ($galat) {
        flash('error', implode(' ', $galat));
        $kembali('tagihan');
    }

    $folder = storage_path('dokumen');
    if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
        flash('error', 'Folder unggahan tidak dapat dibuat.');
        $kembali('tagihan');
    }

    $namaFile = 'bukti-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ekstensi;
    if (!move_uploaded_file($berkas['tmp_name'], $folder . '/' . $namaFile)) {
        flash('error', 'Berkas gagal disimpan ke server.');
        $kembali('tagihan');
    }

    /* Simpan path bukti lama untuk dibersihkan setelah update sukses. */
    $angsuranSimpan = $angsuranBayar ? $angsuranKe : null;
    $lama = q_col(
        "SELECT bukti_transfer_url FROM pembayaran
          WHERE pendaftar_id = ? AND jenis_pembayaran = ? AND angsuran_ke <=> ?
            AND status IN ('PENDING','REJECTED')
       ORDER BY created_at DESC LIMIT 1",
        [$siswa['id'], $jenis, $angsuranSimpan]
    );

    $rel     = 'storage/dokumen/' . $namaFile;
    $hasilByr = pembayaran_unggah_bukti($siswa['id'], $jenis, $nominal, $rel, $idemKey, $angsuranSimpan);
    if (empty($hasilByr['ok'])) {
        @unlink($folder . '/' . $namaFile);
        flash('error', $hasilByr['pesan'] ?? 'Gagal menyimpan bukti transfer.');
        $kembali('tagihan');
    }

    if (!empty($hasilByr['reused'])) {
        @unlink($folder . '/' . $namaFile);
        flash('sukses', 'Bukti transfer sudah pernah dikirim — tidak diproses dua kali.');
        $kembali('tagihan');
    }

    if (!empty($lama) && $lama !== $rel) {
        hapus_berkas_upload((string) $lama);
    }

    /* Tahap pembayaran formulir: naik ke antrean verifikasi bendahara. */
    if ($jenis === 'FORMULIR') {
        q_exec(
            "UPDATE pendaftar SET tahap = 'MENUNGGU_VERIFIKASI_BAYAR'
              WHERE id = ? AND tahap = 'MENUNGGU_PEMBAYARAN'",
            [$siswa['id']]
        );
    }

    notif_operator(
        'BUKTI_BAYAR_MASUK',
        $siswa['id'],
        'Bukti pembayaran ' . ($jenis === 'UANG_MASUK' ? label_biaya_pendaftaran() : 'biaya formulir')
        . ($angsuranSimpan ? ' angsuran ' . $angsuranSimpan : '')
        . ' diunggah via portal siswa.'
        . "\nNama : {$siswa['nama_siswa']}"
        . "\nNo. Pendaftaran : {$siswa['no_pendaftaran']}"
        . "\nNominal : " . rupiah($nominal)
    );

    flash('sukses', 'Bukti transfer terunggah. Status masih MENUNGGU VERIFIKASI sampai diperiksa bendahara.');
    $kembali('tagihan');
}

$flash = flash();

/** Boleh unggah bukti manual untuk satu ringkasan? */
$bolehUnggah = function ($jenis, array $ringkas, $siswaRow) {
    if (empty($GLOBALS['PENGATURAN']['bayar_transfer_aktif'])) {
        return false;
    }
    if ($ringkas['tagihan'] <= 0 || $ringkas['sisa'] <= 0) {
        return false;
    }
    if ($ringkas['pending_bukti'] > 0) {
        return false;
    }
    if ($jenis === 'UANG_MASUK' && !tagihan_pendaftaran_aktif($siswaRow)) {
        return false;
    }
    return true;
};

$bolehFormulir  = $bolehUnggah('FORMULIR', $bayarFormulir, $siswa ?: []);
$bolehUangMasuk = $bolehUnggah('UANG_MASUK', $bayarUangMasuk, $siswa ?: []);
$bolehAngsuran  = $bolehUangMasuk
    && (!$skemaAngsuran['aktif'] || (!empty($ringkasAngsuran['berikut']) && (float) $ringkasAngsuran['pending'] <= 0));
$gatewayAktif   = gateway_tersedia();
$totalSisa      = $bayarFormulir['sisa'] + $bayarUangMasuk['sisa'];

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'keuangan';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Siswa' => url('app/siswa/index.php'), 'Keuangan' => null];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Keuangan SPMB</span>
          <?php if ($siswa): ?>
            <span class="badge badge-<?= $totalSisa > 0 ? 'amber' : 'green' ?>">
              <?= $totalSisa > 0 ? 'Sisa ' . rupiah($totalSisa) : 'Tidak Ada Sisa Tagihan' ?>
            </span>
          <?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Keuangan</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Nominal tagihan mengikuti Pengaturan Sistem. Status <strong>Lunas</strong> hanya berlaku setelah bendahara memverifikasi pembayaran.
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

      <nav class="mt-6 card p-2 flex gap-1.5" aria-label="Bagian keuangan">
        <?php foreach ($tabs as $key => $meta): $aktif = $key === $tab; ?>
          <a href="<?= url('app/siswa/keuangan.php?tab=' . $key) ?>"
             class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[13px] font-semibold transition
                    <?= $aktif ? 'bg-primary-light text-primary' : 'text-ink-500 hover:bg-canvas' ?>"
             <?= $aktif ? 'aria-current="page"' : '' ?>>
            <?= icon($meta[1], 'text-[18px]') ?> <?= e($meta[0]) ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <?php if ($tab === 'tagihan'): ?>

        <!-- Ringkasan -->
        <section class="mt-5 grid gap-4 sm:grid-cols-3">
          <article class="card p-5">
            <p class="text-[13px] font-medium text-ink-500">Total Terverifikasi</p>
            <p class="mt-2 text-xl font-extrabold text-ink-900"><?= rupiah($totalTerverifikasi) ?></p>
          </article>
          <article class="card p-5">
            <p class="text-[13px] font-medium text-ink-500">Total Tagihan</p>
            <p class="mt-2 text-xl font-extrabold text-ink-900"><?= rupiah($tagihanFormulir + $tagihanUangMasuk) ?></p>
          </article>
          <article class="card p-5">
            <p class="text-[13px] font-medium text-ink-500">Sisa Tagihan</p>
            <p class="mt-2 text-xl font-extrabold <?= $totalSisa > 0 ? 'text-[#B45309]' : 'text-[#0E9F6E]' ?>"><?= rupiah($totalSisa) ?></p>
          </article>
        </section>

        <!-- Tagihan per jenis — hanya tagihan yang belum lunas -->
        <?php
        $tampilUangMasuk = $pendaftaranTerbuka
            || (float) $bayarUangMasuk['verified'] > 0
            || (float) $bayarUangMasuk['pending'] > 0;
        $angsuranAktif = $skemaAngsuran['aktif'] && $pendaftaranTerbuka;
        $kartu = [];
        if ($bayarFormulir['tagihan'] > 0 && $bayarFormulir['sisa'] > 0) {
            $kartu[] = [
                'jenis' => 'FORMULIR',
                'judul' => 'Biaya Formulir',
                'ket'   => 'Dibayar sekali di awal pendaftaran.',
                'data'  => $bayarFormulir,
                'boleh' => $bolehFormulir,
            ];
        }
        if ($tampilUangMasuk && $bayarUangMasuk['tagihan'] > 0 && $bayarUangMasuk['sisa'] > 0) {
            $kartu[] = [
                'jenis' => 'UANG_MASUK',
                'judul' => label_biaya_pendaftaran(),
                'ket'   => $angsuranAktif
                    ? 'Pilih bayar lunas sekali atau angsuran 2 kali (otomatis setengah). Status lunas hanya setelah bendahara memverifikasi.'
                    : 'Dibayar setelah hasil seleksi diumumkan dan Anda dinyatakan lulus.',
                'data'  => $bayarUangMasuk,
                'boleh' => $angsuranAktif ? $bolehAngsuran : $bolehUangMasuk,
            ];
        }
        ?>
        <?php if (!$kartu): ?>
          <section class="mt-5 rounded-2xl border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-4 text-[13px] text-[#065F46]">
            <p class="font-semibold">Tidak ada tagihan yang perlu dibayar.</p>
            <p class="mt-1">Seluruh pembayaran sudah lunas. Rincian pembayaran dapat dilihat di tab
              <a href="<?= url('app/siswa/keuangan.php?tab=riwayat') ?>" class="font-semibold underline">Riwayat</a>.
            </p>
          </section>
        <?php endif; ?>
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
          <?php foreach ($kartu as $k):
              $b = $k['data'];
              $angsuranKeBerikut = ($k['jenis'] === 'UANG_MASUK' && $angsuranAktif && !empty($ringkasAngsuran['berikut']))
                  ? (int) $ringkasAngsuran['berikut'] : 0;
              $tagihanAngsuran = $angsuranKeBerikut
                  ? (float) $ringkasAngsuran['angsuran'][$angsuranKeBerikut]['nominal'] : 0.0;
              $sisaAngsuran = $angsuranKeBerikut
                  ? (float) $ringkasAngsuran['angsuran'][$angsuranKeBerikut]['sisa'] : 0.0;
              $modeDefault = ($k['jenis'] === 'UANG_MASUK' && $angsuranKeBerikut > 0 && $b['verified'] > 0)
                  ? 'ANGSURAN' : 'LUNAS';
              $tagihanTampil = $modeDefault === 'ANGSURAN' ? $tagihanAngsuran : (float) $b['tagihan'];
              $nominalTampil = $modeDefault === 'ANGSURAN' ? $sisaAngsuran : (float) $b['sisa'];
              $bolehOnline = $gatewayAktif && $b['tagihan'] > 0 && $b['pending'] <= 0 && $b['sisa'] > 0
                  && ($k['jenis'] === 'FORMULIR' || $pendaftaranTerbuka);
              $jadwalBerikut = $angsuranKeBerikut ? $ringkasAngsuran['angsuran'][$angsuranKeBerikut] : null;
              $tglBerikut    = $jadwalBerikut['jatuh_tempo'] ?? '';
              $infoAngsuran  = 'Angsuran ' . $angsuranKeBerikut . ' — tagihan menjadi ' . rupiah($tagihanAngsuran)
                  . ($tglBerikut ? ' · jatuh tempo ' . bulan($tglBerikut) : '') . '.';
          ?>
            <section class="card p-5 sm:p-6"
                     data-kartu-bayar="<?= e($k['jenis']) ?>"
                     data-tagihan-lunas="<?= (float) $b['tagihan'] ?>"
                     data-sisa-lunas="<?= (float) $b['sisa'] ?>"
                     data-tagihan-angsuran="<?= $tagihanAngsuran ?>"
                     data-sisa-angsuran="<?= $sisaAngsuran ?>"
                     data-angsuran-ke="<?= $angsuranKeBerikut ?>"
                     data-jatuh-tempo-label="<?= e($tglBerikut ? bulan($tglBerikut) : '') ?>">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h2 class="font-bold text-ink-900"><?= e($k['judul']) ?></h2>
                  <p class="mt-1 text-[13px] text-ink-500"><?= e($k['ket']) ?></p>
                </div>
                <span class="badge badge-<?= e($b['tone']) ?> shrink-0"><?= e($b['label']) ?></span>
              </div>

              <dl class="mt-4 grid grid-cols-2 gap-3 text-[13px]">
                <div class="rounded-xl bg-canvas border border-line px-3 py-2.5">
                  <dt class="text-ink-400">Tagihan</dt>
                  <dd class="font-bold text-ink-900 mt-0.5" data-tampil-tagihan><?= rupiah($tagihanTampil) ?></dd>
                </div>
                <div class="rounded-xl bg-canvas border border-line px-3 py-2.5">
                  <dt class="text-ink-400">Sisa</dt>
                  <dd class="font-bold text-ink-900 mt-0.5" data-tampil-sisa><?= rupiah($nominalTampil) ?></dd>
                </div>
                <div class="rounded-xl bg-canvas border border-line px-3 py-2.5">
                  <dt class="text-ink-400">Terverifikasi</dt>
                  <dd class="font-semibold text-[#065F46] mt-0.5"><?= rupiah($b['verified']) ?></dd>
                </div>
                <div class="rounded-xl bg-canvas border border-line px-3 py-2.5">
                  <dt class="text-ink-400">Menunggu</dt>
                  <dd class="font-semibold text-[#B45309] mt-0.5"><?= rupiah($b['pending']) ?></dd>
                </div>
              </dl>

              <?php if ($k['jenis'] === 'UANG_MASUK' && $angsuranAktif && $angsuranKeBerikut > 0 && ($bolehOnline || $k['boleh'])): ?>
                <div class="mt-4 rounded-xl border border-line p-3">
                  <p class="text-[12px] font-semibold uppercase tracking-wider text-ink-400">Pilihan Pembayaran</p>
                  <div class="mt-2 space-y-2">
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-line px-3 py-2.5 text-[13px] hover:bg-canvas">
                      <input type="radio" name="mode-bayar-<?= e($k['jenis']) ?>" value="LUNAS" data-mode-radio
                             class="mt-0.5" <?= $modeDefault === 'LUNAS' ? 'checked' : '' ?>>
                      <span class="min-w-0">
                        <span class="block font-semibold text-ink-900">Bayar Lunas</span>
                        <span class="block text-[12px] text-ink-500"><?= rupiah($b['sisa']) ?> dibayar sekali</span>
                      </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-line px-3 py-2.5 text-[13px] hover:bg-canvas">
                      <input type="radio" name="mode-bayar-<?= e($k['jenis']) ?>" value="ANGSURAN" data-mode-radio
                             class="mt-0.5" <?= $modeDefault === 'ANGSURAN' ? 'checked' : '' ?>>
                      <span class="min-w-0">
                        <span class="block font-semibold text-ink-900">Angsuran 2× (setengah)</span>
                        <span class="block text-[12px] text-ink-500"><?= rupiah($tagihanAngsuran) ?> per tahap<?= $tglBerikut ? ' · jatuh tempo ' . e(bulan($tglBerikut)) : '' ?></span>
                      </span>
                    </label>
                  </div>
                  <p class="mt-2 text-[12px] text-ink-500" data-mode-info>
                    <?= $modeDefault === 'ANGSURAN'
                        ? e($infoAngsuran)
                        : 'Bayar lunas seluruh sisa tagihan sekaligus.' ?>
                  </p>
                </div>
              <?php endif; ?>

              <?php if ($k['jenis'] === 'UANG_MASUK' && $angsuranAktif): ?>
                <div class="mt-4 rounded-xl border border-line overflow-hidden">
                  <div class="px-3.5 py-2.5 bg-canvas border-b border-line text-[12px] font-semibold uppercase tracking-wider text-ink-400">
                    Jadwal Angsuran
                  </div>
                  <ul class="divide-y divide-line-soft">
                    <?php foreach ($ringkasAngsuran['angsuran'] as $a):
                        $tone = 'gray';
                        $labelA = 'Belum Dibayar';
                        if ($a['status'] === 'LUNAS') {
                            $tone = 'green'; $labelA = 'Lunas';
                        } elseif ($a['status'] === 'MENUNGGU') {
                            $tone = 'amber'; $labelA = 'Menunggu Verifikasi';
                        } elseif ($a['status'] === 'SEBAGIAN') {
                            $tone = 'blue'; $labelA = 'Terbayar Sebagian';
                        }
                        $terlambat = in_array($a['status'], ['BELUM', 'SEBAGIAN'], true)
                            && !empty($a['jatuh_tempo']) && $a['jatuh_tempo'] < date('Y-m-d');
                        if ($terlambat) {
                            $tone = 'red'; $labelA = 'Lewat Jatuh Tempo';
                        }
                    ?>
                      <li class="px-3.5 py-2.5 flex items-center justify-between gap-3 text-[13px]">
                        <div class="min-w-0">
                          <p class="font-semibold text-ink-900">Angsuran <?= (int) $a['ke'] ?> · <?= rupiah($a['nominal']) ?></p>
                          <p class="text-[12px] text-ink-500">
                            Jatuh tempo <?= $a['jatuh_tempo'] ? e(bulan($a['jatuh_tempo'])) : '—' ?>
                            <?php if ($a['sisa'] > 0): ?> · Sisa <?= rupiah($a['sisa']) ?><?php endif; ?>
                          </p>
                        </div>
                        <span class="badge badge-<?= e($tone) ?> shrink-0"><?= e($labelA) ?></span>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <?php if ($b['rejected'] > 0): ?>
                <div class="mt-3 rounded-xl bg-[#FEF2F2] border border-red-200 px-3.5 py-2.5 text-[13px] text-[#991B1B]">
                  Ada pembayaran ditolak (<?= rupiah($b['rejected']) ?>). Periksa catatan bendahara di tab Riwayat, lalu unggah ulang bukti yang benar.
                </div>
              <?php elseif ($b['pending_bukti'] > 0): ?>
                <div class="mt-3 rounded-xl bg-[#FFFBEB] border border-[#FDE68A] px-3.5 py-2.5 text-[13px] text-[#92400E]">
                  Bukti pembayaran sudah diterima dan <strong>menunggu verifikasi bendahara</strong>. Status belum lunas.
                </div>
              <?php elseif ($k['jenis'] === 'UANG_MASUK' && !$pendaftaranTerbuka): ?>
                <div class="mt-3 rounded-xl bg-canvas border border-line px-3.5 py-2.5 text-[13px] text-ink-500">
                  Tagihan <?= e(label_biaya_pendaftaran()) ?> dibuka setelah hasil seleksi diumumkan dan Anda dinyatakan LULUS.
                </div>
              <?php elseif ($b['tagihan'] > 0 && $b['verified'] > 0 && $b['sisa'] > 0): ?>
                <div class="mt-3 rounded-xl bg-blue-50 border border-blue-200 px-3.5 py-2.5 text-[13px] text-blue-800">
                  Pembayaran sebagian sudah terverifikasi. Sisa dapat dilunasi atau diangsur 2 kali.
                </div>
              <?php endif; ?>

              <div class="mt-4 rounded-xl bg-canvas border border-line px-3.5 py-3 text-[13px]">
                <div class="flex items-center gap-2">
                  <span class="ms text-[18px] text-primary">account_balance</span>
                  <p class="font-semibold text-ink-900">Rekening Tujuan Transfer</p>
                </div>
                <dl class="mt-2 grid gap-1.5 sm:grid-cols-2">
                  <div class="flex justify-between gap-3"><dt class="text-ink-500">Bank</dt><dd class="font-semibold text-ink-900 text-right"><?= e($PENGATURAN['nama_bank'] ?? '—') ?></dd></div>
                  <div class="flex justify-between gap-3"><dt class="text-ink-500">No. Rekening</dt><dd class="font-mono font-bold text-primary"><?= e($PENGATURAN['no_rekening'] ?? '—') ?></dd></div>
                  <div class="flex justify-between gap-3 sm:col-span-2"><dt class="text-ink-500">Atas Nama</dt><dd class="font-semibold text-ink-900 text-right"><?= e($PENGATURAN['atas_nama_rekening'] ?? '—') ?></dd></div>
                </dl>
              </div>

              <?php if ($bolehOnline || $k['boleh']): ?>
                <div class="mt-4 flex flex-wrap gap-2.5">
                  <?php if ($bolehOnline): ?>
                    <button type="button" class="btn btn-primary"
                            data-bayar-online
                            data-pendaftar="<?= e($siswa['id']) ?>"
                            data-jenis="<?= e($k['jenis']) ?>"
                            data-mode="<?= $modeDefault ?>"
                            data-angsuran="<?= $modeDefault === 'ANGSURAN' ? $angsuranKeBerikut : '' ?>">
                      <?= icon('payment') ?> <span data-label-bayar><?= $modeDefault === 'ANGSURAN' ? 'Bayar Angsuran ' . $angsuranKeBerikut . ' Online' : 'Bayar Online' ?></span>
                    </button>
                  <?php endif; ?>
                  <?php if ($k['boleh']): ?>
                    <button type="button" class="btn btn-outline"
                            data-modal-open="modal-bukti"
                            data-jenis="<?= e($k['jenis']) ?>"
                            data-mode="<?= $modeDefault ?>"
                            data-angsuran="<?= $modeDefault === 'ANGSURAN' ? $angsuranKeBerikut : '' ?>"
                            data-nominal="<?= $nominalTampil ?>">
                      <?= icon('upload') ?> <span data-label-bayar><?= $modeDefault === 'ANGSURAN' ? 'Unggah Bukti Angsuran ' . $angsuranKeBerikut : 'Unggah Bukti Transfer' ?></span>
                    </button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </section>
          <?php endforeach; ?>
        </div>

        <?php if (!$tampilUangMasuk && ($siswa['status_seleksi'] ?? '') !== 'TIDAK_LULUS'): ?>
          <section class="mt-5 rounded-2xl border border-line bg-white px-4 py-3.5 text-[13px] text-ink-500">
            Tagihan <?= e(label_biaya_pendaftaran()) ?> akan muncul setelah hasil seleksi diumumkan dan Anda dinyatakan LULUS.
          </section>
        <?php endif; ?>

        <!-- Instruksi transfer -->
        <section class="mt-5 card p-5 sm:p-6">
          <h2 class="font-bold text-ink-900">Cara Pembayaran</h2>
          <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-line p-4">
              <div class="flex items-center gap-2">
                <span class="icon-box bg-primary-light text-primary"><?= icon('account_balance', 'text-[19px]') ?></span>
                <p class="font-semibold text-ink-900 text-sm">Transfer Bank</p>
              </div>
              <dl class="mt-3 space-y-2 text-[13px]">
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Bank</dt><dd class="font-semibold text-ink-900 text-right"><?= e($PENGATURAN['nama_bank'] ?? '—') ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">No. Rekening</dt><dd class="font-mono font-bold text-primary"><?= e($PENGATURAN['no_rekening'] ?? '—') ?></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-500">Atas Nama</dt><dd class="font-semibold text-ink-900 text-right"><?= e($PENGATURAN['atas_nama_rekening'] ?? '—') ?></dd></div>
              </dl>
              <p class="mt-3 text-[12px] text-ink-500">Simpan bukti transfer, lalu unggah melalui tombol di kartu tagihan.</p>
            </div>

            <div class="rounded-xl border border-line p-4">
              <div class="flex items-center gap-2">
                <span class="icon-box bg-canvas text-primary"><?= icon('qr_code_2', 'text-[19px]') ?></span>
                <p class="font-semibold text-ink-900 text-sm">QRIS / Pembayaran Online</p>
              </div>
              <?php if ($gatewayAktif): ?>
                <p class="mt-3 text-[13px] text-ink-500">Pembayaran online otomatis terverifikasi oleh sistem setelah transaksi berhasil.</p>
              <?php elseif (!empty($PENGATURAN['bayar_qris_aktif']) && !empty($PENGATURAN['qris_image_url'])): ?>
                <img src="<?= e(asset($PENGATURAN['qris_image_url'])) ?>" alt="QRIS" class="mt-3 max-h-56 rounded-xl border border-line">
                <?php if (!empty($PENGATURAN['qris_merchant'])): ?>
                  <p class="mt-2 text-[12px] text-ink-500">Merchant: <?= e($PENGATURAN['qris_merchant']) ?></p>
                <?php endif; ?>
              <?php else: ?>
                <p class="mt-3 text-[13px] text-ink-500">Pembayaran online / QRIS belum diaktifkan. Gunakan transfer bank.</p>
              <?php endif; ?>
            </div>
          </div>
          <div class="mt-4 rounded-xl bg-canvas border border-line px-4 py-3 text-[12px] text-ink-500">
            Catatan: nominal yang sudah terverifikasi adalah satu-satunya dasar status lunas. Bukti yang baru diunggah tidak mengubah status sampai diperiksa bendahara.
          </div>
        </section>

      <?php endif; ?>

      <?php if ($tab === 'riwayat'): ?>
        <section class="mt-5 card overflow-hidden">
          <div class="p-5 sm:p-6 pb-0 sm:pb-0">
            <h2 class="font-bold text-ink-900">Riwayat Pembayaran</h2>
            <p class="mt-1 text-[13px] text-ink-500">Semua baris pembayaran, termasuk yang masih menunggu dan yang ditolak.</p>
          </div>
          <div class="mt-4 overflow-x-auto scroll-thin">
            <table class="w-full min-w-[780px] border-collapse">
              <thead class="thead">
                <tr>
                  <th class="th">Tanggal</th>
                  <th class="th">Jenis</th>
                  <th class="th text-right">Nominal</th>
                  <th class="th">Metode</th>
                  <th class="th">Status</th>
                  <th class="th">Keterangan</th>
                  <th class="th">Bukti</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-line-soft">
                <?php if (!$pembayaran): ?>
                  <tr><td class="td text-center text-ink-400" colspan="7">Belum ada data pembayaran.</td></tr>
                <?php endif; ?>
                <?php foreach ($pembayaran as $b): ?>
                  <?php
                  $jenisLabel = $b['jenis_pembayaran'] === 'FORMULIR' ? 'Biaya Formulir'
                      : ($b['jenis_pembayaran'] === 'UANG_MASUK' ? label_biaya_pendaftaran() : $b['jenis_pembayaran']);
                  if ($b['jenis_pembayaran'] === 'UANG_MASUK' && !empty($b['angsuran_ke'])) {
                      $jenisLabel .= ' · Angsuran ' . (int) $b['angsuran_ke'];
                  }
                  $statusLabel = $labelBayar[$b['status']] ?? $b['status'];
                  $statusTone  = $toneBayar[$b['status']] ?? 'gray';
                  $metodeLabel = ['GATEWAY' => 'Online', 'TRANSFER' => 'Transfer', 'QRIS' => 'QRIS'][$b['metode'] ?? ''] ?? '—';
                  $tipeBukti   = strtolower(pathinfo(basename((string) ($b['bukti_transfer_url'] ?? '')), PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'gambar';
                  ?>
                  <tr class="tr-hover">
                    <td class="td text-[13px] whitespace-nowrap"><?= e($b['created_at'] ? bulan($b['created_at']) : '—') ?></td>
                    <td class="td font-semibold"><?= e($jenisLabel) ?></td>
                    <td class="td text-right font-semibold whitespace-nowrap"><?= rupiah($b['nominal']) ?></td>
                    <td class="td text-[13px]"><?= e($metodeLabel) ?></td>
                    <td class="td"><span class="badge badge-<?= e($statusTone) ?>"><?= e($statusLabel) ?></span></td>
                    <td class="td text-[13px] text-ink-500 max-w-[260px]">
                      <?= e($b['keterangan'] ?: '—') ?>
                      <?php if (!empty($b['catatan']) && $b['status'] === 'REJECTED'): ?>
                        <span class="block text-[12px] text-[#991B1B] mt-1">Catatan: <?= e($b['catatan']) ?></span>
                      <?php endif; ?>
                    </td>
                    <td class="td text-[13px]">
                      <?php if (!empty($b['bukti_transfer_url'])): ?>
                        <button type="button" class="text-primary hover:underline font-semibold"
                                data-pratinjau-url="<?= e(url('app/api/dokumen.php?f=' . rawurlencode(basename((string) $b['bukti_transfer_url'])))) ?>"
                                data-pratinjau-nama="Bukti Transfer <?= e($jenisLabel) ?>"
                                data-pratinjau-tipe="<?= e($tipeBukti) ?>"
                                data-pratinjau-meta="<?= e(($tipeBukti === 'pdf' ? 'PDF' : 'Gambar') . ' · ' . rupiah($b['nominal'])) ?>">Lihat</button>
                      <?php else: ?>
                        <span class="text-ink-400">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>
  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>

<?php if ($siswa): ?>
<!-- Modal unggah bukti transfer -->
<div id="modal-bukti" class="modal-root fixed inset-0 z-[60] hidden">
  <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-[2px]" data-modal-close></div>
  <div class="relative mx-auto mt-[10vh] w-[min(440px,92vw)] card p-5 sm:p-6 shadow-modal animate-pop">
    <div class="flex items-start justify-between gap-3">
      <h3 class="font-bold text-ink-900">Unggah Bukti Transfer</h3>
      <button class="icon-box border border-line text-ink-500" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
    </div>
    <form method="post" action="<?= url('app/siswa/keuangan.php?tab=tagihan') ?>" enctype="multipart/form-data" class="mt-4 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="unggah_bukti">
      <input type="hidden" name="idem_key" id="idem-key-unggah" value="">
      <input type="hidden" name="angsuran_ke" id="m-angsuran" value="">
      <input type="hidden" name="mode" id="m-mode" value="LUNAS">
      <div>
        <label class="label" for="m-jenis">Jenis Pembayaran</label>
        <select id="m-jenis" name="jenis_pembayaran" class="select">
          <option value="FORMULIR">Biaya Formulir</option>
          <option value="UANG_MASUK"><?= e(label_biaya_pendaftaran()) ?></option>
        </select>
        <p id="m-angsuran-info" class="hint hidden"></p>
      </div>
      <div>
        <label class="label" for="m-nominal">Nominal Transfer (Rp) <span class="text-red-600">*</span></label>
        <input id="m-nominal" name="nominal" type="number" min="1" step="1" class="input"
               value="<?= (float) ($bayarFormulir['sisa'] > 0 ? $bayarFormulir['sisa'] : $bayarUangMasuk['sisa']) ?>" required>
        <p class="hint">Transfer ke <?= e($PENGATURAN['nama_bank'] ?? '—') ?> no. <?= e($PENGATURAN['no_rekening'] ?? '—') ?> a.n. <?= e($PENGATURAN['atas_nama_rekening'] ?? '—') ?>.</p>
      </div>
      <div>
        <label class="label" for="m-bukti">Foto / Scan Bukti (JPG/PNG, maks 2 MB) <span class="text-red-600">*</span></label>
        <input id="m-bukti" name="bukti" type="file" accept=".jpg,.jpeg,.png" class="input" required>
      </div>
      <div class="flex justify-end gap-3 pt-1">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary shadow-drop"><?= icon('upload') ?> Unggah</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php require __DIR__ . '/../../includes/partials/pratinjau-dokumen.php'; ?>
<?php if ($gatewayAktif): ?>
<script src="<?= e(midtrans_snap_base()) ?>"></script>
<?php endif; ?>
<script>
  /* Idempotency key — satu per "niat" unggah; retry memakai key yang sama. */
  function idemBaru() {
    if (window.crypto && crypto.randomUUID) {
      return crypto.randomUUID().replace(/-/g, '');
    }
    var s = '', h = '0123456789abcdef';
    for (var i = 0; i < 32; i++) s += h[Math.floor(Math.random() * 16)];
    return s;
  }
  var idemKeys = {};
  function idemUntuk(jenis) {
    if (!idemKeys[jenis]) idemKeys[jenis] = idemBaru();
    return idemKeys[jenis];
  }
  function idemReset(jenis) { delete idemKeys[jenis]; }

  var ANGSURAN = {
    aktif: <?= $angsuranAktifGlobal ? 'true' : 'false' ?>,
    berikut: <?= $angsuranBerikutGlobal ?>,
    sisa: <?= (float) $angsuranSisaGlobal ?>
  };
  var SISA_JENIS = {
    FORMULIR: <?= (float) $bayarFormulir['sisa'] ?>,
    UANG_MASUK: <?= (float) $bayarUangMasuk['sisa'] ?>
  };
  var MODE_JENIS = { FORMULIR: 'LUNAS', UANG_MASUK: 'LUNAS' };
  function formatRupiah(n) {
    return 'Rp ' + Math.round(Number(n) || 0).toLocaleString('id-ID');
  }

  /* Terapkan mode bayar pada kartu: nominal tagihan & tombol ikut berubah.
     Mode ANGSURAN menampilkan setengah tagihan (angsuran berikutnya). */
  function terapkanModeKartu(kartu) {
    var jenis = kartu.dataset.kartuBayar;
    var radio = kartu.querySelector('[data-mode-radio]:checked');
    var mode = radio ? radio.value : 'LUNAS';
    var angsuran = mode === 'ANGSURAN';
    var tagihan = kartu.dataset[angsuran ? 'tagihanAngsuran' : 'tagihanLunas'];
    var sisa = kartu.dataset[angsuran ? 'sisaAngsuran' : 'sisaLunas'];
    var ke = kartu.dataset.angsuranKe || '';
    MODE_JENIS[jenis] = mode;
    if (!angsuran) ke = '';

    var elTagihan = kartu.querySelector('[data-tampil-tagihan]');
    var elSisa = kartu.querySelector('[data-tampil-sisa]');
    if (elTagihan) elTagihan.textContent = formatRupiah(tagihan);
    if (elSisa) elSisa.textContent = formatRupiah(sisa);

    kartu.querySelectorAll('[data-bayar-online], [data-modal-open="modal-bukti"]').forEach(function (btn) {
      btn.dataset.mode = mode;
      btn.dataset.angsuran = ke;
      if (btn.hasAttribute('data-nominal')) btn.dataset.nominal = sisa;
      var label = btn.querySelector('[data-label-bayar]');
      if (!label) return;
      if (btn.hasAttribute('data-bayar-online')) {
        label.textContent = angsuran ? 'Bayar Angsuran ' + ke + ' Online' : 'Bayar Online';
      } else {
        label.textContent = angsuran ? 'Unggah Bukti Angsuran ' + ke : 'Unggah Bukti Transfer';
      }
    });

    var info = kartu.querySelector('[data-mode-info]');
    if (info) {
      var tgl = kartu.dataset.jatuhTempoLabel || '';
      info.textContent = angsuran
        ? 'Angsuran ' + ke + ' — tagihan menjadi ' + formatRupiah(tagihan)
          + (tgl ? ' · jatuh tempo ' + tgl : '') + '.'
        : 'Bayar lunas seluruh sisa tagihan sekaligus.';
    }
  }

  document.querySelectorAll('[data-mode-radio]').forEach(function (radio) {
    radio.addEventListener('change', function () {
      var kartu = radio.closest('[data-kartu-bayar]');
      if (kartu) {
        terapkanModeKartu(kartu);
        var m = document.getElementById('m-mode');
        if (m) m.value = MODE_JENIS.UANG_MASUK || 'LUNAS';
        sinkronModal();
      }
    });
  });
  document.querySelectorAll('[data-kartu-bayar]').forEach(terapkanModeKartu);

  /* Selaraskan isian modal dengan jenis & mode pembayaran. */
  function sinkronModal() {
    var j = document.getElementById('m-jenis');
    var n = document.getElementById('m-nominal');
    var a = document.getElementById('m-angsuran');
    var modeEl = document.getElementById('m-mode');
    var info = document.getElementById('m-angsuran-info');
    if (!j || !n || !a || !modeEl) return;
    var pakaiAngsuran = ANGSURAN.aktif && j.value === 'UANG_MASUK'
      && modeEl.value === 'ANGSURAN' && ANGSURAN.berikut > 0;
    if (pakaiAngsuran) {
      a.value = ANGSURAN.berikut;
      n.value = ANGSURAN.sisa;
      if (info) {
        info.textContent = 'Angsuran ' + ANGSURAN.berikut + ' — tagihan setengah, sisa '
          + formatRupiah(ANGSURAN.sisa) + '.';
        info.classList.remove('hidden');
      }
    } else {
      a.value = '';
      n.value = SISA_JENIS[j.value] || 0;
      if (info) {
        info.textContent = (j.value === 'UANG_MASUK' && ANGSURAN.aktif)
          ? 'Bayar lunas seluruh sisa tagihan sekaligus.' : '';
        info.classList.toggle('hidden', info.textContent === '');
      }
    }
  }

  document.querySelectorAll('[data-modal-open="modal-bukti"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var j = document.getElementById('m-jenis');
      var m = document.getElementById('m-mode');
      if (btn.dataset.jenis) j.value = btn.dataset.jenis;
      if (m) m.value = btn.dataset.mode || 'LUNAS';
      sinkronModal();
      document.getElementById('idem-key-unggah').value = idemBaru();
    });
  });

  var mJenis = document.getElementById('m-jenis');
  if (mJenis) {
    mJenis.addEventListener('change', function () {
      var m = document.getElementById('m-mode');
      if (m) m.value = mJenis.value === 'UANG_MASUK' ? (MODE_JENIS.UANG_MASUK || 'LUNAS') : 'LUNAS';
      sinkronModal();
    });
  }

  <?php if ($gatewayAktif): ?>
  var CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  document.querySelectorAll('[data-bayar-online]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      var asli = btn.innerHTML;
      btn.innerHTML = 'Memproses…';
      var jenis = btn.dataset.jenis || 'FORMULIR';
      var mode = jenis === 'UANG_MASUK' ? (btn.dataset.mode || 'LUNAS') : 'LUNAS';
      var key = idemUntuk(jenis + ':' + mode);

      var fd = new FormData();
      fd.append('_csrf', CSRF_TOKEN);
      fd.append('pendaftar_id', btn.dataset.pendaftar || '');
      fd.append('jenis', jenis);
      fd.append('idem_key', key);
      if (jenis === 'UANG_MASUK') {
        fd.append('mode', mode);
        if (mode === 'ANGSURAN' && btn.dataset.angsuran) {
          fd.append('angsuran_ke', btn.dataset.angsuran);
        }
      }

      fetch('<?= url('app/api/snap-token.php') ?>', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          btn.disabled = false;
          btn.innerHTML = asli;
          if (!d.ok) {
            showToast(d.pesan || 'Gagal membuat transaksi.', 'error');
            return;
          }
          if (typeof snap === 'undefined') {
            showToast('Snap.js belum termuat. Muat ulang halaman.', 'error');
            return;
          }
          snap.pay(d.snap_token, {
            onSuccess: function () {
              idemReset(jenis + ':' + mode);
              showToast('Pembayaran berhasil! Memuat ulang…', 'sukses');
              setTimeout(function () { location.reload(); }, 1500);
            },
            onPending: function () {
              idemReset(jenis + ':' + mode);
              showToast('Menunggu pembayaran Anda. Status akan diperbarui otomatis.', 'info');
              setTimeout(function () { location.reload(); }, 3000);
            },
            onError: function () {
              showToast('Pembayaran gagal. Silakan coba lagi.', 'error');
            },
            onClose: function () {
              showToast('Jendela pembayaran ditutup sebelum selesai.', 'info');
            }
          });
        })
        .catch(function () {
          btn.disabled = false;
          btn.innerHTML = asli;
          showToast('Gagal menghubungi server pembayaran.', 'error');
        });
    });
  });
  <?php endif; ?>
</script>
</body>
</html>
