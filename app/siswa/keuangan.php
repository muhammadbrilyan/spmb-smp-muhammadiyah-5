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
$desc  = 'Biaya formulir, uang masuk PPDB, status verifikasi pembayaran, rincian tagihan, dan riwayat.';
require __DIR__ . '/_bootstrap.php';

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
    $ringkas = $jenis === 'UANG_MASUK' ? $bayarUangMasuk : $bayarFormulir;

    $galat = [];
    if (empty($PENGATURAN['bayar_transfer_aktif'])) {
        $galat[] = 'Pembayaran transfer sedang tidak aktif.';
    }
    if ($idemKey === null) {
        $galat[] = 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    }
    if ($ringkas['tagihan'] <= 0) {
        $galat[] = 'Nominal ' . ($jenis === 'UANG_MASUK' ? 'uang masuk' : 'biaya formulir') . ' belum ditetapkan panitia.';
    }
    if ($ringkas['sisa'] <= 0) {
        $galat[] = 'Tagihan ini sudah lunas / terverifikasi.';
    }
    if ($ringkas['pending_bukti'] > 0) {
        $galat[] = 'Bukti pembayaran sebelumnya masih menunggu verifikasi bendahara.';
    }
    if ($jenis === 'UANG_MASUK' && !tahap_minimal((string) ($siswa['tahap'] ?? ''), 'BERKAS_TERVERIFIKASI')) {
        $galat[] = 'Uang masuk dapat dibayar setelah berkas persyaratan terverifikasi panitia.';
    }
    if ($nominal <= 0) {
        $galat[] = 'Nominal transfer harus lebih besar dari 0.';
    } elseif ($ringkas['sisa'] > 0 && $nominal > $ringkas['sisa'] + 0.01) {
        $galat[] = 'Nominal melebihi sisa tagihan (' . rupiah($ringkas['sisa']) . ').';
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
    $lama = q_col(
        "SELECT bukti_transfer_url FROM pembayaran
          WHERE pendaftar_id = ? AND jenis_pembayaran = ? AND status IN ('PENDING','REJECTED')
       ORDER BY created_at DESC LIMIT 1",
        [$siswa['id'], $jenis]
    );

    $rel     = 'storage/dokumen/' . $namaFile;
    $hasilByr = pembayaran_unggah_bukti($siswa['id'], $jenis, $nominal, $rel, $idemKey);
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
        'Bukti pembayaran ' . ($jenis === 'UANG_MASUK' ? 'uang masuk' : 'biaya formulir')
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
    if ($jenis === 'UANG_MASUK' && !tahap_minimal((string) ($siswaRow['tahap'] ?? ''), 'BERKAS_TERVERIFIKASI')) {
        return false;
    }
    return true;
};

$bolehFormulir  = $bolehUnggah('FORMULIR', $bayarFormulir, $siswa ?: []);
$bolehUangMasuk = $bolehUnggah('UANG_MASUK', $bayarUangMasuk, $siswa ?: []);
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

        <!-- Tagihan per jenis -->
        <div class="mt-5 grid gap-5 lg:grid-cols-2">
          <?php
          $kartu = [
              [
                  'jenis' => 'FORMULIR',
                  'judul' => 'Biaya Formulir',
                  'ket'   => 'Dibayar sekali di awal pendaftaran.',
                  'data'  => $bayarFormulir,
                  'boleh' => $bolehFormulir,
              ],
              [
                  'jenis' => 'UANG_MASUK',
                  'judul' => $PENGATURAN['label_uang_pangkal'] ?? 'Uang Masuk PPDB',
                  'ket'   => 'Dapat dibayar penuh atau dua tahap. Status angsuran ditetapkan bendahara setelah verifikasi.',
                  'data'  => $bayarUangMasuk,
                  'boleh' => $bolehUangMasuk,
              ],
          ];
          foreach ($kartu as $k):
              $b = $k['data'];
              $bolehOnline = $gatewayAktif && $b['tagihan'] > 0 && $b['pending_bukti'] <= 0
                  && ($k['jenis'] === 'FORMULIR' ? $b['sisa'] > 0 : ($b['verified'] <= 0 && $b['sisa'] > 0
                      && tahap_minimal((string) $siswa['tahap'], 'BERKAS_TERVERIFIKASI')));
          ?>
            <section class="card p-5 sm:p-6">
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
                  <dd class="font-bold text-ink-900 mt-0.5"><?= rupiah($b['tagihan']) ?></dd>
                </div>
                <div class="rounded-xl bg-canvas border border-line px-3 py-2.5">
                  <dt class="text-ink-400">Sisa</dt>
                  <dd class="font-bold text-ink-900 mt-0.5"><?= rupiah($b['sisa']) ?></dd>
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

              <?php if ($b['rejected'] > 0): ?>
                <div class="mt-3 rounded-xl bg-[#FEF2F2] border border-red-200 px-3.5 py-2.5 text-[13px] text-[#991B1B]">
                  Ada pembayaran ditolak (<?= rupiah($b['rejected']) ?>). Periksa catatan bendahara di tab Riwayat, lalu unggah ulang bukti yang benar.
                </div>
              <?php elseif ($b['pending_bukti'] > 0): ?>
                <div class="mt-3 rounded-xl bg-[#FFFBEB] border border-[#FDE68A] px-3.5 py-2.5 text-[13px] text-[#92400E]">
                  Bukti pembayaran sudah diterima dan <strong>menunggu verifikasi bendahara</strong>. Status belum lunas.
                </div>
              <?php elseif ($k['jenis'] === 'UANG_MASUK' && !tahap_minimal((string) $siswa['tahap'], 'BERKAS_TERVERIFIKASI')): ?>
                <div class="mt-3 rounded-xl bg-canvas border border-line px-3.5 py-2.5 text-[13px] text-ink-500">
                  Pembayaran uang masuk dibuka setelah berkas persyaratan terverifikasi panitia.
                </div>
              <?php elseif ($b['tagihan'] > 0 && $b['verified'] > 0 && $b['sisa'] > 0): ?>
                <div class="mt-3 rounded-xl bg-blue-50 border border-blue-200 px-3.5 py-2.5 text-[13px] text-blue-800">
                  Pembayaran sebagian sudah terverifikasi. Sisa dapat dilunasi atau diangsur sesuai ketentuan panitia.
                </div>
              <?php endif; ?>

              <?php if ($bolehOnline || $k['boleh']): ?>
                <div class="mt-4 flex flex-wrap gap-2.5">
                  <?php if ($bolehOnline): ?>
                    <button type="button" class="btn btn-primary"
                            data-bayar-online
                            data-pendaftar="<?= e($siswa['id']) ?>"
                            data-jenis="<?= e($k['jenis']) ?>">
                      <?= icon('payment') ?> Bayar Online
                    </button>
                  <?php endif; ?>
                  <?php if ($k['boleh']): ?>
                    <button type="button" class="btn btn-outline"
                            data-modal-open="modal-bukti"
                            data-jenis="<?= e($k['jenis']) ?>"
                            data-nominal="<?= (float) $b['sisa'] ?>">
                      <?= icon('upload') ?> Unggah Bukti Transfer
                    </button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </section>
          <?php endforeach; ?>
        </div>

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
                      : ($b['jenis_pembayaran'] === 'UANG_MASUK' ? 'Uang Masuk' : $b['jenis_pembayaran']);
                  $statusLabel = $labelBayar[$b['status']] ?? $b['status'];
                  $statusTone  = $toneBayar[$b['status']] ?? 'gray';
                  $metodeLabel = ['GATEWAY' => 'Online', 'TRANSFER' => 'Transfer', 'QRIS' => 'QRIS'][$b['metode'] ?? ''] ?? '—';
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
                        <a class="text-primary hover:underline" target="_blank" rel="noopener"
                           href="<?= url('app/api/dokumen.php?f=' . rawurlencode(basename((string) $b['bukti_transfer_url']))) ?>">Lihat</a>
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
<div id="modal-bukti" class="fixed inset-0 z-[60] hidden">
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
      <div>
        <label class="label" for="m-jenis">Jenis Pembayaran</label>
        <select id="m-jenis" name="jenis_pembayaran" class="select">
          <option value="FORMULIR">Biaya Formulir</option>
          <option value="UANG_MASUK">Uang Masuk</option>
        </select>
      </div>
      <div>
        <label class="label" for="m-nominal">Nominal Transfer (Rp) <span class="text-red-600">*</span></label>
        <input id="m-nominal" name="nominal" type="number" min="1" step="1000" class="input"
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

  document.querySelectorAll('[data-modal-open="modal-bukti"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var j = document.getElementById('m-jenis');
      var n = document.getElementById('m-nominal');
      if (btn.dataset.jenis) j.value = btn.dataset.jenis;
      if (btn.dataset.nominal) n.value = btn.dataset.nominal;
      document.getElementById('idem-key-unggah').value = idemBaru();
    });
  });

  <?php if ($gatewayAktif): ?>
  var CSRF_TOKEN = <?= json_encode(csrf_token(), JSON_UNESCAPED_UNICODE) ?>;
  document.querySelectorAll('[data-bayar-online]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      var asli = btn.innerHTML;
      btn.innerHTML = 'Memproses…';
      var jenis = btn.dataset.jenis || 'FORMULIR';
      var key = idemUntuk(jenis);

      var fd = new FormData();
      fd.append('_csrf', CSRF_TOKEN);
      fd.append('pendaftar_id', btn.dataset.pendaftar || '');
      fd.append('jenis', jenis);
      fd.append('idem_key', key);

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
              idemReset(jenis);
              showToast('Pembayaran berhasil! Memuat ulang…', 'sukses');
              setTimeout(function () { location.reload(); }, 1500);
            },
            onPending: function () {
              idemReset(jenis);
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
