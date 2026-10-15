<?php
/* ==================================================================
 * PENGUMUMAN HASIL SELEKSI (Fase 8).
 * - Umumkan sekarang / jadwalkan / batalkan jadwal.
 * - Peringatan bila ada LULUS belum ditempatkan kelas.
 * - Kirim WA batch 10/request via AJAX + "kirim ulang yang gagal".
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

$flash = flash();
$aksi  = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['aksi'] ?? '') : '';
$jmlLulusTanpa = (int) lulus_belum_ditempatkan_jumlah();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang halaman.');
        header('Location: ' . url('app/operator/pengumuman.php'));
        exit;
    } elseif ($aksi === 'umumkan_sekarang') {
        if ($jmlLulusTanpa > 0 && ($_POST['konfirmasi'] ?? '') !== '1') {
            flash('error', 'Konfirmasi diperlukan: masih ada siswa LULUS tanpa kelas.');
            header('Location: ' . url('app/operator/pengumuman.php'));
            exit;
        }
        $r = pengumuman_umumkan_sekarang();
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        header('Location: ' . url('app/operator/pengumuman.php'));
        exit;
    } elseif ($aksi === 'jadwalkan') {
        $r = pengumuman_jadwalkan($_POST['jadwal_waktu'] ?? '');
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        header('Location: ' . url('app/operator/pengumuman.php'));
        exit;
    } elseif ($aksi === 'batalkan_jadwal') {
        $r = pengumuman_batalkan_jadwal();
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        header('Location: ' . url('app/operator/pengumuman.php'));
        exit;
    }
}

$status      = pengumuman_status();
/* Fase 10: pengumuman_status() kini READ-ONLY — finalisasi tahap
 * (NILAI_TERINPUT → DIUMUMKAN) dijalankan di sini, jalur operator
 * (idempoten; hanya jalan bila jadwal pengumuman sudah lewat). */
if (!empty($status['diumumkan'])) {
    pengumuman_finalisasi_tahap();
}
$jmlTargetWa = pengumuman_target_wa_jumlah();
/* Default input datetime-local: clock MySQL + 1 jam (server & DB seragam). */
$waktuDefault = q_col("SELECT DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 HOUR), '%Y-%m-%dT%H:%i')") ?: date('Y-m-d\TH:i');

$title = 'Pengumuman Hasil Seleksi';
$desc  = 'Umumkan atau jadwalkan pengumuman hasil seleksi SPMB, kirim WA ke pendaftar.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';
$activeMenu = 'pengumuman';
require_once __DIR__ . '/../../includes/partials/head.php';
$breadcrumbs = [
    ['label' => 'Dashboard', 'href' => role_home(user_role())],
    ['label' => 'Pengumuman Hasil'],
];
require_once __DIR__ . '/../../includes/partials/sidebar.php';
require_once __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="px-4 sm:px-6 lg:px-8 py-6 space-y-6 max-w-[1100px]">

  <?php if ($flash): ?>
    <div class="rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
      <?= e($flash['pesan']) ?>
    </div>
  <?php endif; ?>

  <!-- Status pengumuman -->
  <div class="card p-6 shadow-drop">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <span class="badge badge-blue">Pengumuman</span>
        <h1 class="mt-2 text-xl font-bold text-ink-900">Status Pengumuman Hasil</h1>
        <p class="mt-1 text-sm text-ink-500">Hasil seleksi (LULUS / TIDAK LULUS) hanya tampil publik setelah diumumkan.</p>
      </div>
      <?php if (!empty($status['diumumkan'])): ?>
        <?= badge('Sudah Diumumkan', 'green') ?>
      <?php elseif (!empty($status['dijadwalkan_pada'])): ?>
        <?= badge('Dijadwalkan', 'amber') ?>
      <?php else: ?>
        <?= badge('Belum Diumumkan', 'gray') ?>
      <?php endif; ?>
    </div>

    <div class="mt-5 grid gap-4 sm:grid-cols-3">
      <div class="rounded-2xl border border-line p-4">
        <p class="text-[12px] text-ink-500 uppercase tracking-wide">Waktu Pengumuman</p>
        <?php if (!empty($status['diumumkan'])): ?>
          <p class="mt-1.5 font-bold text-ink-900"><?= e($status['dijadwalkan_pada']) ?></p>
          <p class="text-[12px] text-ink-400 mt-0.5">Sudah lewat — hasil tampil publik.</p>
        <?php elseif (!empty($status['dijadwalkan_pada'])): ?>
          <p class="mt-1.5 font-bold text-amber-600"><?= e($status['dijadwalkan_pada']) ?></p>
          <p class="text-[12px] text-ink-400 mt-0.5">Belum tiba.</p>
        <?php else: ?>
          <p class="mt-1.5 font-bold text-ink-400">Belum diatur</p>
        <?php endif; ?>
      </div>
      <div class="rounded-2xl border border-line p-4">
        <p class="text-[12px] text-ink-500 uppercase tracking-wide">Target WA Pengumuman</p>
        <p class="mt-1.5 font-bold text-ink-900 text-2xl"><?= (int) $jmlTargetWa ?></p>
        <p class="text-[12px] text-ink-400 mt-0.5">pendaftar dengan status seleksi + nomor WA</p>
      </div>
      <div class="rounded-2xl border border-line p-4">
        <p class="text-[12px] text-ink-500 uppercase tracking-wide">Integritas Data</p>
        <?php if ($jmlLulusTanpa > 0): ?>
          <p class="mt-1.5 font-bold text-red-600"><?= $jmlLulusTanpa ?> siswa LULUS</p>
          <p class="text-[12px] text-red-500 mt-0.5">belum ditempatkan kelas — lihat Penempatan.</p>
        <?php else: ?>
          <p class="mt-1.5 font-bold text-green-600">Aman</p>
          <p class="text-[12px] text-ink-400 mt-0.5">semua LULUS sudah punya kelas.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($jmlLulusTanpa > 0): ?>
      <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
        <p class="font-semibold">Peringatan: ada siswa LULUS tanpa penempatan kelas.</p>
        <p class="mt-1">Selesaikan penempatan dulu, atau konfirmasi eksplisit di bawah untuk tetap mengumumkan.</p>
        <a href="<?= url('app/operator/penempatan.php') ?>" class="mt-2 inline-flex btn btn-sm btn-outline">Buka Penempatan Kelas</a>
      </div>
    <?php endif; ?>

    <?php if (empty($status['diumumkan'])): ?>
      <!-- Aksi umumkan / jadwalkan -->
      <div class="mt-6 grid gap-5 sm:grid-cols-2">
        <form method="post" action="<?= url('app/operator/pengumuman.php') ?>" class="rounded-2xl border border-line p-5">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="umumkan_sekarang">
          <p class="font-semibold text-ink-900">Umumkan Sekarang</p>
          <p class="mt-1 text-[13px] text-ink-500">Hasil langsung tampil publik & tahap pendaftar menjadi DIUMUMKAN.</p>
          <?php if ($jmlLulusTanpa > 0): ?>
            <label class="mt-4 flex items-start gap-2.5 text-[13px] text-ink-700 cursor-pointer">
              <input type="checkbox" name="konfirmasi" value="1" class="mt-0.5" required>
              <span>Saya memahami masih ada <strong><?= $jmlLulusTanpa ?> siswa LULUS tanpa kelas</strong> dan tetap ingin mengumumkan sekarang.</span>
            </label>
          <?php endif; ?>
          <button type="submit" class="mt-4 btn btn-primary shadow-drop w-full sm:w-auto"><?= icon('campaign') ?> Umumkan Sekarang</button>
        </form>

        <form method="post" action="<?= url('app/operator/pengumuman.php') ?>" class="rounded-2xl border border-line p-5">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="jadwalkan">
          <p class="font-semibold text-ink-900">Jadwalkan</p>
          <p class="mt-1 text-[13px] text-ink-500">Tentukan waktu pengumuman otomatis (masa depan).</p>
          <label class="label mt-4" for="jadwal_waktu">Waktu Pengumuman</label>
          <input id="jadwal_waktu" type="datetime-local" name="jadwal_waktu" required
                 value="<?= e($waktuDefault) ?>" class="input w-full sm:w-64">
          <button type="submit" class="mt-4 btn btn-outline shadow-drop"><?= icon('schedule') ?> Simpan Jadwal</button>
        </form>
      </div>
    <?php elseif (!empty($status['dijadwalkan_pada']) && empty($status['finalized'])): ?>
      <!-- Sudah terjadwal tapi belum lewat → bisa batalkan -->
      <form method="post" action="<?= url('app/operator/pengumuman.php') ?>" class="mt-5">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="batalkan_jadwal">
        <button type="submit" class="btn btn-outline btn-sm"><?= icon('event_busy') ?> Batalkan Jadwal</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Kirim WA -->
  <div class="card p-6 shadow-drop">
    <span class="badge badge-green">Notifikasi WA</span>
    <h2 class="mt-2 text-lg font-bold text-ink-900">Kirim WA Pengumuman</h2>
    <p class="mt-1 text-sm text-ink-500">
      Batch 10 nomor per request. Pastikan hasil sudah diumumkan sebelum mengirim agar isi pesan sesuai.
    </p>

    <div class="mt-5 flex flex-wrap gap-3 items-center">
      <button id="btn-wa-kirim" class="btn btn-primary shadow-drop" <?= $jmlTargetWa === 0 ? 'disabled' : '' ?>>
        <?= icon('send') ?> Kirim WA ke Semua (<?= (int) $jmlTargetWa ?>)
      </button>
      <button id="btn-wa-ulang" class="btn btn-outline"><?= icon('refresh') ?> Kirim Ulang yang Gagal</button>
    </div>

    <div id="wa-progress" class="mt-4 hidden">
      <div class="h-2 rounded-full bg-line-soft overflow-hidden">
        <div id="wa-progress-bar" class="h-full bg-primary transition-all" style="width:0%"></div>
      </div>
      <p id="wa-progress-text" class="mt-2 text-[13px] text-ink-500">Memproses…</p>
    </div>

    <div id="wa-result" class="mt-4 hidden rounded-2xl border border-line p-4 text-sm"></div>
  </div>

  <!-- Panduan singkat -->
  <div class="card p-6 shadow-drop">
    <p class="font-semibold text-ink-900">Alur Singkat</p>
    <ol class="mt-3 space-y-1.5 text-[13px] text-ink-700 list-decimal list-inside">
      <li>Selesaikan Input Seleksi & Penempatan Kelas (peringatan di atas harus aman).</li>
      <li>Umumkan sekarang atau jadwalkan — hasil publik terbuka otomatis pada waktunya.</li>
      <li>Kirim WA pengumuman batch (10/request) — pantau progres di atas.</li>
      <li>Bila ada kegagalan kirim, tekan "Kirim Ulang yang Gagal".</li>
    </ol>
  </div>

</div>

<script>
(function () {
  var csrf = '<?= e(csrf_token()) ?>';
  var btnKirim = document.getElementById('btn-wa-kirim');
  var btnUlang = document.getElementById('btn-wa-ulang');
  var box = document.getElementById('wa-progress');
  var bar = document.getElementById('wa-progress-bar');
  var txt = document.getElementById('wa-progress-text');
  var res = document.getElementById('wa-result');
  var busy = false;

  function tampil(terkirim, gagal, sisa, total) {
    box.classList.remove('hidden');
    res.classList.remove('hidden');
    var diproses = (total - sisa);
    var pct = total > 0 ? Math.round(diproses / total * 100) : 100;
    bar.style.width = pct + '%';
    txt.textContent = 'Memproses… ' + diproses + '/' + total + ' (' + terkirim + ' terkirim, ' + gagal + ' gagal)';
  }

  function selesai(terkirim, gagal, pesan) {
    txt.textContent = pesan;
    res.innerHTML = '<p class="font-semibold text-ink-900">Ringkasan</p>' +
      '<p class="mt-1 text-ink-700">Terkirim: <strong>' + terkirim + '</strong> · Gagal: <strong>' + gagal + '</strong></p>' +
      (gagal > 0 ? '<p class="mt-1 text-[13px] text-amber-600">Tekan "Kirim Ulang yang Gagal" bila perlu.</p>' : '');
  }

  function jalankan(mode) {
    if (busy) return;
    busy = true;
    btnKirim.disabled = true; btnUlang.disabled = true;
    res.classList.add('hidden');
    var offset = 0, totalTerkirim = 0, totalGagal = 0, total = 0;

    function batch() {
      var fd = new FormData();
      fd.append('_csrf', csrf);
      fd.append('mode', mode);
      fd.append('offset', offset);
      fetch('<?= url('app/api/pengumuman-wa.php') ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.ok) { selesai(0, 0, d.pesan || 'Terjadi kesalahan.'); busy = false; btnKirim.disabled = false; btnUlang.disabled = false; return; }
          totalTerkirim += d.terkirim; totalGagal += d.gagal; total = d.total;
          tampil(totalTerkirim, totalGagal, d.sisa, total);
          if (d.selesai) {
            selesai(totalTerkirim, totalGagal, d.pesan || 'Selesai.');
            busy = false; btnKirim.disabled = false; btnUlang.disabled = false;
            return;
          }
          offset += 10;
          batch();
        })
        .catch(function () { selesai(0, 0, 'Koneksi gagal. Coba lagi.'); busy = false; btnKirim.disabled = false; btnUlang.disabled = false; });
    }
    batch();
  }

  btnKirim.addEventListener('click', function () { jalankan('kirim'); });
  btnUlang.addEventListener('click', function () { jalankan('ulang_gagal'); });
})();
</script>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
