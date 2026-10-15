<?php
require_once __DIR__ . '/../../includes/config.php';

$title = 'Cek Status Pendaftaran';
$desc  = 'Cek status verifikasi, pembayaran, seleksi, dan penempatan kelas SPMB tanpa login.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$hasil  = null;          // hasil pencarian tunggal (langsung tampil)
$galat  = [];
$f = ['no' => '', 'wa' => ''];
$pulihHasil = null;      // hasil pemulihan tautan pembayaran
$pengumumanAktif = !empty(pengumuman_status()['diumumkan']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        $galat[] = 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } elseif (($_POST['aksi'] ?? '') === 'pulihkan_bayar') {
        /* ============================================================
         * Pemulihan tautan pembayaran: no_pendaftaran + 4 digit WA.
         * Rate limit: maks 3 percobaan per jam per IP.
         * Fase 11 A5: kunci per-nomor hanya untuk format REG-YYYY-NNNN
         * (huruf besar) agar tabel rate_limit tidak dibanjiri kunci sembarang.
         * ============================================================ */
        $noRek = trim((string) ($_POST['no_recovery'] ?? ''));
        $wa4   = trim((string) ($_POST['wa4'] ?? ''));
        $noRekValid = preg_match('/^REG-\d{4}-\d{4}$/', $noRek) === 1;
        $pesanSeragam = 'Nomor pendaftaran atau 4 digit WhatsApp tidak cocok. Periksa kembali data Anda.';

        if (!rate_limit_cek('pulihkan_bayar', 3, 60)) {
            $galat[] = 'Terlalu banyak percobaan pemulihan tautan. Coba lagi dalam beberapa menit.';
        } elseif (!$noRekValid) {
            /* Format tidak valid → pesan seragam; JANGAN catat kunci per-nomor. */
            rate_limit_catat('pulihkan_bayar');
            $galat[] = $pesanSeragam;
        } elseif (!rate_limit_cek('pulihkan_bayar_no', 3, 60, $noRek)) {
            /* Fase 10: batas lintas IP per no_pendaftaran — brute-force 4 digit
             * tidak bisa dilakukan dengan mengganti IP. */
            $galat[] = 'Terlalu banyak percobaan pemulihan tautan. Coba lagi dalam beberapa menit.';
        } else {
            /* Catat SETIAP percobaan (sukses maupun gagal) agar brute-force
             * 4 digit tidak bisa dilakukan melewati batas. */
            rate_limit_catat('pulihkan_bayar');
            rate_limit_catat('pulihkan_bayar_no', $noRek);
            $pulihHasil = pulihkan_pay_token($noRek, $wa4);
            if ($pulihHasil['ok']) {
                /* Tampilkan hasil pencarian untuk pendaftar ini agar kartu tautan tampil. */
                $kandidat = cek_status_cari($noRek, '', '');
                if (count($kandidat) === 1) {
                    $hasil = $kandidat[0];
                    $hasil['ringkas'] = cek_status_ringkas($hasil['id']);
                }
            } else {
                $galat[] = $pulihHasil['pesan'];
            }
        }
    } else {
        /* Fase 8: pencarian publik diperketat — wajib no_pendaftaran + 4 digit WA.
         * Pencarian nama SAJA dihapus untuk mencegah enumerasi data. */
        $f['no'] = trim((string) ($_POST['no'] ?? ''));
        $f['wa'] = trim((string) ($_POST['wa'] ?? ''));
        $wa4    = preg_replace('/\D+/', '', $f['wa']);

        /* Fase 11 A5: format nomor wajib REG-YYYY-NNNN (huruf besar). */
        $noValid = preg_match('/^REG-\d{4}-\d{4}$/', $f['no']) === 1;
        /* Pesan seragam: "tidak ada" dan "4 digit salah" sengaja sama
         * agar penyerang tidak bisa membedakan nomor valid/tidak (anti-enumerasi). */
        $pesanSeragam = 'Nomor pendaftaran atau 4 digit WhatsApp tidak cocok. Periksa kembali data Anda.';

        if ($f['no'] === '' || strlen($wa4) !== 4) {
            $galat[] = 'Isi nomor pendaftaran DAN 4 digit terakhir WhatsApp orang tua.';
        } elseif (!rate_limit_cek('cek_status', 10, 15)) {
            $galat[] = 'Terlalu banyak percobaan pencarian. Coba lagi beberapa saat lagi.';
        } else {
            /* Pembatasan per IP tetap menghitung semua percobaan. */
            rate_limit_catat('cek_status');
            if (!$noValid) {
                /* Format tidak valid → pesan seragam; JANGAN catat kunci per-nomor
                 * (mencegah tabel rate_limit dibanjiri kunci sembarang). */
                $galat[] = $pesanSeragam;
            } elseif (!rate_limit_cek('cek_status_no', 5, 15, $f['no'])) {
                /* Fase 10: batas lintas IP per no_pendaftaran. */
                $galat[] = 'Terlalu banyak percobaan pencarian. Coba lagi beberapa saat lagi.';
            } else {
                $kandidat = cek_status_cari($f['no'], '', '');
                if (!$kandidat) {
                    /* Fase 11 A5: kunci per-nomor hanya dicatat saat GAGAL —
                     * orang tua sah tidak terkunci karena sering memeriksa. */
                    rate_limit_catat('cek_status_no', $f['no']);
                    $galat[] = $pesanSeragam;
                } else {
                    /* Cocokkan 4 digit terakhir WA — wajib lolos. */
                    $cocok = null;
                    foreach ($kandidat as $k) {
                        $waAsli = preg_replace('/\D+/', '', (string) $k['no_whatsapp']);
                        if ($waAsli !== '' && substr($waAsli, -4) === $wa4) {
                            $cocok = $k;
                            break;
                        }
                    }
                    if (!$cocok) {
                        rate_limit_catat('cek_status_no', $f['no']);
                        $galat[] = $pesanSeragam;
                    } else {
                        $hasil = $cocok;
                        $hasil['ringkas'] = cek_status_ringkas($hasil['id']);
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/../../includes/partials/head.php';

$labelVer = [
    'DRAFT'                 => ['Draft', 'gray'],
    'MENUNGGU_VERIFIKASI'   => ['Menunggu Verifikasi', 'amber'],
    'TERVERIFIKASI'         => ['Terverifikasi', 'green'],
    'DITOLAK'               => ['Ditolak', 'red'],
];
$labelSelesai = [
    'PROSES'      => ['Dalam Proses', 'amber'],
    'LULUS'       => ['Lulus', 'green'],
    'TIDAK_LULUS' => ['Tidak Lulus', 'red'],
];
$labelBayar = [
    'PENDING'    => ['Menunggu', 'amber'],
    'VERIFIED'   => ['Terverifikasi', 'green'],
    'REJECTED'   => ['Ditolak', 'red'],
    'LUNAS'      => ['Lunas', 'green'],
    'ANGSURAN_1' => ['Angsuran 1', 'blue'],
    'ANGSURAN_2' => ['Angsuran 2', 'blue'],
];
?>

<div class="min-h-screen">
  <header class="border-b border-line bg-white">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
      <a href="<?= url('app/spmb/index.php') ?>" class="flex items-center gap-3 min-w-0">
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo" class="w-10 h-10 shrink-0">
        <span class="min-w-0 leading-tight">
          <span class="block font-bold text-ink-900 text-[15px] truncate"><?= e(APP_SHORT) ?></span>
          <span class="block text-[11px] text-ink-500">SPMB <?= e($PENGATURAN['tahun_ajaran']) ?> · <?= e($PENGATURAN['gelombang']) ?></span>
        </span>
      </a>
      <a href="<?= url('app/auth/login.php') ?>" class="btn btn-outline btn-sm">Masuk Portal</a>
    </div>
  </header>

  <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 lg:py-12">

    <!-- Form cari -->
    <div class="card p-6 sm:p-8 shadow-drop">
      <span class="badge badge-blue">Cek Status</span>
      <h1 class="mt-3 text-2xl font-bold text-ink-900">Cek Status Pendaftaran</h1>
      <p class="mt-1.5 text-sm text-ink-500">
        Masukkan nomor pendaftaran dan 4 digit terakhir WhatsApp orang tua untuk melihat status.
      </p>

      <?php if ($galat): ?>
        <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
          <?php foreach ($galat as $g): ?><p><?= e($g) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= url('app/spmb/cek-status.php') ?>" class="mt-6 grid gap-4 sm:grid-cols-2">
        <?= csrf_field() ?>
        <div>
          <label class="label" for="no">No. Pendaftaran</label>
          <input id="no" name="no" class="input font-mono" placeholder="REG-2025-0101" value="<?= e($f['no']) ?>" required>
          <p class="hint">Contoh: REG-2025-0101</p>
        </div>
        <div>
          <label class="label" for="wa">4 Digit Terakhir WhatsApp</label>
          <input id="wa" name="wa" type="tel" inputmode="numeric" maxlength="4" pattern="[0-9]{4}"
                 class="input font-mono w-full sm:w-40" placeholder="1234" value="<?= e($f['wa']) ?>" required>
          <p class="hint">4 digit belakang nomor yang didaftarkan</p>
        </div>
        <div class="sm:col-span-2 flex justify-end">
          <button type="submit" class="btn btn-primary shadow-drop"><?= icon('search') ?> Cari Status</button>
        </div>
      </form>
    </div>

    <!-- Hasil -->
    <?php if ($hasil): $r = $hasil['ringkas']; ?>
      <div class="mt-6 card p-6 sm:p-8 shadow-drop animate-pop">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <p class="font-mono text-sm text-primary font-semibold"><?= e($hasil['no_pendaftaran']) ?></p>
            <h2 class="mt-1 text-xl font-bold text-ink-900"><?= e($hasil['nama_siswa']) ?></h2>
            <p class="text-[13px] text-ink-500">Jalur <?= e($hasil['jalur'] ?: 'Reguler') ?> · Orang Tua: <?= e($hasil['nama_orang_tua']) ?></p>
          </div>
          <div class="flex flex-wrap gap-2">
            <?php $v = $labelVer[$hasil['status_verifikasi']] ?? [$hasil['status_verifikasi'], 'gray']; ?>
            <?= badge('Verifikasi: ' . $v[0], $v[1]) ?>
            <?php if ($pengumumanAktif): $s = $labelSelesai[$hasil['status_seleksi']] ?? [$hasil['status_seleksi'], 'gray']; ?>
              <?= badge('Seleksi: ' . $s[0], $s[1]) ?>
            <?php else: ?>
              <?= badge('Seleksi: Belum Diumumkan', 'gray') ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">

          <!-- Pembayaran -->
          <div class="rounded-2xl border border-line p-4">
            <p class="font-semibold text-sm text-ink-900">Pembayaran</p>
            <?php if ($r['bayar']): ?>
              <ul class="mt-2.5 space-y-2">
                <?php foreach ($r['bayar'] as $b): $lb = $labelBayar[$b['status']] ?? [$b['status'], 'gray']; ?>
                  <li class="flex items-center justify-between gap-2 text-[13px]">
                    <span class="text-ink-700"><?= e($b['jenis_pembayaran'] === 'FORMULIR' ? 'Biaya Formulir' : 'Uang Masuk') ?> · <?= rupiah($b['nominal']) ?></span>
                    <?= badge($lb[0], $lb[1]) ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p class="mt-2 text-[13px] text-ink-400">Belum ada data pembayaran.</p>
            <?php endif; ?>
          </div>

          <!-- Berkas -->
          <div class="rounded-2xl border border-line p-4">
            <p class="font-semibold text-sm text-ink-900">Berkas Persyaratan</p>
            <p class="mt-2.5 text-[13px] text-ink-700">
              <?= (int) $r['dok_total'] ?> berkas terunggah,
              <?= (int) $r['dok_verified'] ?> terverifikasi panitia.
            </p>
          </div>

          <!-- Seleksi -->
          <div class="rounded-2xl border border-line p-4">
            <p class="font-semibold text-sm text-ink-900">Hasil Seleksi</p>
            <?php if (!$pengumumanAktif): ?>
              <?php $waktuUmum = pengumuman_status()['dijadwalkan_pada'] ?? null; ?>
              <p class="mt-2.5 text-[13px] text-ink-400">
                Hasil seleksi akan diumumkan<?= $waktuUmum ? ' pada ' . e($waktuUmum) : ' oleh panitia' ?>.
              </p>
            <?php elseif ($r['seleksi']): ?>
              <p class="mt-2.5 text-[13px] text-ink-700">
                Nilai Akhir: <strong class="text-ink-900"><?= e(number_format((float) $r['seleksi']['nilai_akhir'], 1)) ?></strong>
              </p>
              <?php $hs = $labelSelesai[$r['seleksi']['hasil']] ?? [$r['seleksi']['hasil'], 'gray']; ?>
              <p class="mt-1"><?= badge($hs[0], $hs[1]) ?></p>
              <?php if ($r['seleksi']['nama_kegiatan']): ?>
                <p class="mt-2 text-[12px] text-ink-400"><?= e($r['seleksi']['nama_kegiatan']) ?> · <?= e(bulan($r['seleksi']['tanggal'])) ?></p>
              <?php endif; ?>
            <?php else: ?>
              <p class="mt-2.5 text-[13px] text-ink-400">Hasil seleksi belum diterbitkan.</p>
            <?php endif; ?>
          </div>

          <!-- Penempatan -->
          <div class="rounded-2xl border border-line p-4">
            <p class="font-semibold text-sm text-ink-900">Penempatan Kelas</p>
            <?php if (!$pengumumanAktif): ?>
              <p class="mt-2.5 text-[13px] text-ink-400">Penempatan kelas menyusul setelah pengumuman hasil.</p>
            <?php elseif ($r['penempatan']): ?>
              <p class="mt-2.5 text-lg font-extrabold text-ink-900"><?= e($r['penempatan']['kode']) ?> — <?= e($r['penempatan']['nama_kelas']) ?></p>
              <p class="text-[13px] text-ink-500">Wali kelas: <?= e($r['penempatan']['wali_kelas'] ?: '—') ?></p>
            <?php else: ?>
              <p class="mt-2.5 text-[13px] text-ink-400">Penempatan kelas belum ditentukan.</p>
            <?php endif; ?>
          </div>

        </div>

        <?php if (($hasil['tahap'] ?? '') === 'MENUNGGU_PEMBAYARAN'): ?>
          <!-- Pemulihan tautan pembayaran -->
          <?php if ($pulihHasil && !empty($pulihHasil['ok'])): ?>
            <div class="mt-6 rounded-2xl border border-[#A7F3D0] bg-[#ECFDF5] p-4" role="status">
              <p class="text-sm font-semibold text-[#065F46]">Tautan Pembayaran Baru</p>
              <p class="mt-1 text-[13px] text-[#065F46]/80"><?= e($pulihHasil['pesan']) ?></p>
              <a href="<?= url($pulihHasil['tautan']) ?>" class="mt-2 inline-flex items-center gap-1.5 text-sm font-bold text-primary underline">
                Buka Halaman Pembayaran <?= icon('arrow_forward', 'text-[16px]') ?>
              </a>
            </div>
          <?php else: ?>
            <div class="mt-6 rounded-2xl border border-line p-4">
              <p class="font-semibold text-sm text-ink-900">Tautan Pembayaran Hilang?</p>
              <p class="mt-1 text-[13px] text-ink-500">Masukkan 4 digit terakhir WhatsApp orang tua untuk membuat tautan baru. Tautan lama otomatis tidak berlaku.</p>
              <form method="post" action="<?= url('app/spmb/cek-status.php') ?>" class="mt-3 flex flex-wrap items-end gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="pulihkan_bayar">
                <input type="hidden" name="no_recovery" value="<?= e($hasil['no_pendaftaran']) ?>">
                <div class="grow min-w-[160px]">
                  <label class="label" for="wa4">4 Digit Terakhir WhatsApp</label>
                  <input id="wa4" name="wa4" inputmode="numeric" maxlength="4" pattern="[0-9]{4}"
                         class="input font-mono w-32" placeholder="1234" required>
                </div>
                <button type="submit" class="btn btn-primary shadow-drop"><?= icon('link') ?> Pulihkan Tautan</button>
              </form>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <div class="mt-6 flex flex-wrap justify-end gap-3">
          <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline">Kembali ke Beranda</a>
          <a href="<?= url('app/auth/registrasi.php') ?>" class="btn btn-outline">Daftar Baru</a>
          <a href="<?= url('app/auth/login.php') ?>" class="btn btn-primary shadow-drop">Masuk Portal Siswa</a>
        </div>
      </div>
    <?php endif; ?>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
