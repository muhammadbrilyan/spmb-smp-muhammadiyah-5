<?php
/* ==================================================================
 * HALAMAN BAYAR PUBLIK — tanpa login, diakses via pay_token.
 * URL: app/spmb/bayar.php?t=<pay_token>
 *
 * YANG SERING DIUBAH:
 *   - Metode aktif → CMS Pengaturan Sistem → Pembayaran & S&K
 *   - Teks bawah   → cari "Hubungi Panitia" di bagian HTML
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/midtrans.php';

$payToken = trim((string) ($_GET['t'] ?? ''));
$galat    = '';

/* Token wajib, 64 hex, rate-limited. */
if (!preg_match('/^[a-f0-9]{64}$/', $payToken)) {
    http_response_code(404);
    $galat = 'Tautan pembayaran tidak valid.';
} elseif (!rate_limit_cek('bayar_token', 30, 60)) {
    http_response_code(429);
    $galat = 'Terlalu banyak permintaan. Silakan coba lagi nanti.';
} else {
    rate_limit_catat('bayar_token');
}

$pendaftar = null;
if (!$galat) {
    $pendaftar = pendaftar_by_pay_token($payToken);
    if (!$pendaftar) {
        http_response_code(404);
        $galat = 'Tautan pembayaran tidak ditemukan atau sudah tidak berlaku.';
    }
}

/* Metode aktif dari pengaturan. */
$metodeGateway  = gateway_tersedia();
$metodeTransfer = !empty($PENGATURAN['bayar_transfer_aktif']);
$metodeQris     = !empty($PENGATURAN['bayar_qris_aktif']) && !empty($PENGATURAN['qris_image_url']);

$nominal = (float) ($pendaftar['nominal'] ?? $PENGATURAN['biaya_formulir'] ?? 200000);

$title = 'Pembayaran SPMB';
$desc  = 'Halaman pembayaran biaya formulir SPMB ' . APP_SHORT . '.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
?>

<div class="min-h-screen">
  <header class="border-b border-line bg-white">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
      <a href="<?= url('app/spmb/index.php') ?>" class="flex items-center gap-3 min-w-0">
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo" class="w-10 h-10 shrink-0">
        <span class="min-w-0 leading-tight">
          <span class="block font-bold text-ink-900 text-[15px] truncate"><?= e(APP_SHORT) ?></span>
          <span class="block text-[11px] text-ink-500">Pembayaran SPMB</span>
        </span>
      </a>
    </div>
  </header>

  <main class="max-w-2xl mx-auto px-4 sm:px-6 py-8 lg:py-12">

    <?php if ($galat): ?>
      <div class="card p-6 sm:p-8 text-center">
        <span class="icon-box bg-[#FEE2E2] text-[#991B1B] w-14 h-14 mx-auto"><?= icon('error', 'text-[28px]') ?></span>
        <h1 class="mt-4 text-xl font-bold text-ink-900">Akses Tidak Valid</h1>
        <p class="mt-2 text-sm text-ink-500"><?= e($galat) ?></p>
        <div class="mt-6 flex justify-center gap-3">
          <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline">Ke Beranda</a>
          <a href="<?= url('app/spmb/cek-status.php') ?>" class="btn btn-primary">Cek Status</a>
        </div>
      </div>

    <?php else:
      $sudahBayar = in_array($pendaftar['bayar_status'], ['VERIFIED', 'LUNAS'], true);
    ?>
      <!-- Ringkasan -->
      <div class="card p-5 sm:p-6">
        <div class="flex items-center gap-3">
          <span class="icon-box bg-primary-light text-primary w-10 h-10"><?= icon('receipt_long', 'text-[20px]') ?></span>
          <div>
            <h1 class="font-bold text-ink-900 text-lg">Pembayaran Biaya Formulir</h1>
            <p class="text-xs text-ink-500">Nomor pendaftaran: <span class="font-mono font-semibold"><?= e($pendaftar['no_pendaftaran']) ?></span></p>
          </div>
        </div>

        <div class="mt-4 rounded-2xl bg-primary-light border border-primary/20 p-4 text-center">
          <p class="text-[13px] text-ink-500">Nama Pendaftar</p>
          <p class="font-semibold text-ink-900"><?= e($pendaftar['nama_siswa']) ?></p>
          <p class="mt-3 text-[13px] text-ink-500">Nominal Tagihan</p>
          <p class="text-3xl font-extrabold text-primary"><?= rupiah($nominal) ?></p>
        </div>

        <?php if ($sudahBayar): ?>
          <div class="mt-5 rounded-2xl border border-[#BBF7D0] bg-[#F0FDF4] p-4 text-sm text-[#166534] text-center" role="status">
            <span class="ms text-[18px] inline-block align-middle mr-1">check_circle</span>
            Pembayaran sudah terverifikasi. Silakan lanjut ke tahap berikutnya atau hubungi panitia.
          </div>
        <?php endif; ?>
      </div>

      <?php if (!$sudahBayar && ($metodeGateway || $metodeTransfer || $metodeQris)): ?>
        <div class="mt-5 card p-5 sm:p-6">
          <h2 class="font-bold text-ink-900 mb-4">Pilih Metode Pembayaran</h2>

          <!-- Gateway -->
          <?php if ($metodeGateway): ?>
            <div class="rounded-2xl border border-line p-4 mb-3">
              <div class="flex items-start gap-3">
                <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon('credit_card') ?></span>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-sm text-ink-900">Bayar Online (Opsional)</p>
                  <p class="text-xs text-ink-500 mt-0.5">Pilihan tambahan selain transfer manual: virtual account, e-wallet, QRIS, kartu kredit via Midtrans.</p>
                </div>
              </div>
              <button type="button" id="btn-bayar-gateway"
                      class="btn btn-primary btn-block mt-3 shadow-drop" disabled>
                Bayar Sekarang <?= icon('arrow_forward') ?>
              </button>
              <p id="gateway-loading" class="text-xs text-ink-400 mt-2 text-center">Menyiapkan pembayaran…</p>
              <p id="gateway-error" class="text-xs text-[#991B1B] mt-2 text-center hidden"></p>
            </div>
          <?php endif; ?>

          <!-- Transfer -->
          <?php if ($metodeTransfer): ?>
            <div class="rounded-2xl border border-line p-4 mb-3">
              <div class="flex items-start gap-3">
                <span class="icon-box bg-[#DBEAFE] text-[#1E40AF] shrink-0"><?= icon('account_balance') ?></span>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-sm text-ink-900">Transfer Bank</p>
                  <p class="text-xs text-ink-500 mt-0.5">Transfer sesuai nominal, lalu unggah bukti.</p>
                </div>
              </div>
              <div class="mt-3 rounded-xl bg-canvas border border-line p-3 text-sm space-y-1">
                <div class="flex justify-between"><span class="text-ink-500">Bank</span><span class="font-semibold"><?= e($PENGATURAN['nama_bank']) ?></span></div>
                <div class="flex justify-between"><span class="text-ink-500">No. Rekening</span><span class="font-mono font-semibold"><?= e($PENGATURAN['no_rekening']) ?></span></div>
                <div class="flex justify-between"><span class="text-ink-500">a.n.</span><span class="font-semibold"><?= e($PENGATURAN['atas_nama_rekening']) ?></span></div>
                <div class="flex justify-between border-t border-line pt-1 mt-1"><span class="text-ink-500">Nominal</span><span class="font-bold text-primary"><?= rupiah($nominal) ?></span></div>
              </div>
              <form id="form-bukti-transfer" class="mt-3 space-y-3" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div>
                  <label class="label" for="bukti-transfer">Unggah Bukti Transfer <span class="text-red-600">*</span></label>
                  <input id="bukti-transfer" name="bukti" type="file" accept=".jpg,.jpeg,.png,.pdf" class="input" required>
                  <p class="text-xs text-ink-400 mt-1">JPG/PNG/PDF, maks 2 MB.</p>
                </div>
                <input type="hidden" name="metode" value="TRANSFER">
                <input type="hidden" name="pendaftar_id" value="<?= e($pendaftar['id']) ?>">
                <input type="hidden" name="bayar_id" value="<?= e($pendaftar['bayar_id']) ?>">
                <input type="hidden" name="pay_token" value="<?= e($payToken) ?>">
                <button type="submit" class="btn btn-primary btn-block">Kirim Bukti Transfer</button>
                <p id="transfer-status" class="text-xs text-center hidden"></p>
              </form>
            </div>
          <?php endif; ?>

          <!-- QRIS -->
          <?php if ($metodeQris): ?>
            <div class="rounded-2xl border border-line p-4 mb-3">
              <div class="flex items-start gap-3">
                <span class="icon-box bg-[#D1FAE5] text-[#065F46] shrink-0"><?= icon('qr_code') ?></span>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-sm text-ink-900">QRIS</p>
                  <p class="text-xs text-ink-500 mt-0.5">Scan kode QR dengan aplikasi bank/e-wallet Anda.</p>
                </div>
              </div>
              <?php if (!empty($PENGATURAN['qris_merchant'])): ?>
                <p class="text-xs text-ink-500 mt-2">Merchant: <strong><?= e($PENGATURAN['qris_merchant']) ?></strong></p>
              <?php endif; ?>
              <div class="mt-3 flex flex-col items-center">
                <img src="<?= e(asset($PENGATURAN['qris_image_url'])) ?>" alt="QRIS" class="w-48 h-48 object-contain rounded-xl border border-line bg-white p-2">
                <p class="mt-2 text-xs text-ink-500">Nominal: <strong class="text-ink-900"><?= rupiah($nominal) ?></strong></p>
              </div>
              <form id="form-bukti-qris" class="mt-3 space-y-3" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div>
                  <label class="label" for="bukti-qris">Unggah Bukti Pembayaran <span class="text-red-600">*</span></label>
                  <input id="bukti-qris" name="bukti" type="file" accept=".jpg,.jpeg,.png,.pdf" class="input" required>
                  <p class="text-xs text-ink-400 mt-1">JPG/PNG/PDF, maks 2 MB.</p>
                </div>
                <input type="hidden" name="metode" value="QRIS">
                <input type="hidden" name="pendaftar_id" value="<?= e($pendaftar['id']) ?>">
                <input type="hidden" name="bayar_id" value="<?= e($pendaftar['bayar_id']) ?>">
                <input type="hidden" name="pay_token" value="<?= e($payToken) ?>">
                <button type="submit" class="btn btn-primary btn-block">Kirim Bukti Pembayaran</button>
                <p id="qris-status" class="text-xs text-center hidden"></p>
              </form>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!$sudahBayar && !$metodeGateway && !$metodeTransfer && !$metodeQris): ?>
        <div class="mt-5 card p-5 text-center text-sm text-ink-500">
          Metode pembayaran belum tersedia. Silakan hubungi panitia.
        </div>
      <?php endif; ?>

      <div class="mt-6 text-center text-xs text-ink-400">
        Hubungi panitia: <a class="underline text-primary font-semibold" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $PENGATURAN['kontak_wa_panitia'])) ?>" target="_blank" rel="noopener"><?= e($PENGATURAN['kontak_wa_panitia']) ?></a>
      </div>
    <?php endif; ?>

  </main>
</div>

<!-- Midtrans Snap -->
<?php if ($metodeGateway && $pendaftar): ?>
<script src="<?= e(midtrans_snap_base()) ?>" data-client-key="<?= e(midtrans_client_key()) ?>"></script>
<script>
(function () {
  'use strict';
  var btn = document.getElementById('btn-bayar-gateway');
  var loading = document.getElementById('gateway-loading');
  var errorEl = document.getElementById('gateway-error');
  var payToken = <?= json_encode($payToken) ?>;
  var csrf = <?= json_encode(csrf_token()) ?>;
  var busy = false;

  if (loading) loading.classList.add('hidden');

  if (btn) {
    btn.disabled = false;
    btn.addEventListener('click', function () {
      if (busy) return;
      busy = true;
      btn.disabled = true;
      if (loading) loading.classList.remove('hidden');
      if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }

      fetch('<?= url('app/api/snap-token.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_csrf=' + encodeURIComponent(csrf)
             + '&pay_token=' + encodeURIComponent(payToken)
             + '&jenis=FORMULIR'
      })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (loading) loading.classList.add('hidden');
        busy = false;
        btn.disabled = false;
        if (!d.ok) {
          if (errorEl) { errorEl.textContent = d.pesan || 'Gagal memuat pembayaran.'; errorEl.classList.remove('hidden'); }
          return;
        }
        if (d.snap_token && window.snap) {
          window.snap.pay(d.snap_token, {
            onSuccess: function () { window.location.reload(); },
            onPending: function () { window.location.reload(); },
            onError: function () {
              if (errorEl) { errorEl.textContent = 'Pembayaran gagal. Coba lagi.'; errorEl.classList.remove('hidden'); }
            },
            onClose: function () {
              if (errorEl) { errorEl.textContent = 'Anda menutup jendela pembayaran.'; errorEl.classList.remove('hidden'); }
            }
          });
        } else if (d.redirect_url) {
          window.location.href = d.redirect_url;
        }
      })
      .catch(function () {
        if (loading) loading.classList.add('hidden');
        busy = false;
        btn.disabled = false;
        if (errorEl) { errorEl.textContent = 'Koneksi gagal. Coba lagi.'; errorEl.classList.remove('hidden'); }
      });
    });
  }
})();
</script>
<?php endif; ?>

<!-- Upload bukti (transfer & QRIS) -->
<script>
(function () {
  'use strict';

  function handleUpload(formId, statusId) {
    var form = document.getElementById(formId);
    if (!form) return;
    var statusEl = document.getElementById(statusId);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var fd = new FormData(form);
      var btn = form.querySelector('button[type="submit"]');
      btn.disabled = true;
      if (statusEl) { statusEl.textContent = 'Mengirim…'; statusEl.className = 'text-xs text-center text-ink-500'; statusEl.classList.remove('hidden'); }

      fetch('<?= url('app/api/bayar-bukti.php') ?>', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d.ok) {
            if (statusEl) { statusEl.textContent = 'Bukti terkirim! Menunggu verifikasi panitia.'; statusEl.className = 'text-xs text-center text-[#065F46] font-medium'; }
            setTimeout(function () { window.location.reload(); }, 1500);
          } else {
            if (statusEl) { statusEl.textContent = d.pesan || 'Gagal mengirim.'; statusEl.className = 'text-xs text-center text-[#991B1B]'; }
            btn.disabled = false;
          }
        })
        .catch(function () {
          if (statusEl) { statusEl.textContent = 'Koneksi gagal.'; statusEl.className = 'text-xs text-center text-[#991B1B]'; }
          btn.disabled = false;
        });
    });
  }

  handleUpload('form-bukti-transfer', 'transfer-status');
  handleUpload('form-bukti-qris', 'qris-status');
})();
</script>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
