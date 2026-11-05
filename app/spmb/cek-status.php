<?php
require_once __DIR__ . '/../../includes/config.php';

$title = 'Cek Status Pendaftaran';
$desc  = 'Cek status verifikasi, pembayaran, seleksi, dan penempatan kelas SPMB tanpa login.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* Verifikasi identitas di halaman publik ini punya dua jalur:
 *   - nomor WhatsApp penuh  → status langsung tampil (hanya pemilik yang tahu nomornya);
 *   - nomor pendaftaran     → tetap wajib 4 digit terakhir WhatsApp, karena nomor
 *     pendaftaran berjalan (REG-YYYY-0001) mudah ditebak.
 * Setelah salah satu jalur lolos, id pendaftar dicatat di sesi. Tombol "Buka
 * Halaman Pembayaran" hanya melayani id yang sudah tercatat itu, jadi orang tua
 * tidak perlu mengetik ulang digit apa pun untuk mengirim ulang bukti transfer. */
$SESI_TTL = 30 * 60;

$hasil    = null;   // hasil pencarian tunggal (langsung tampil)
$kandidat = [];     // lebih dari satu pendaftaran memakai nomor WhatsApp yang sama
$galat    = [];
$f = ['q' => '', 'wa4' => ''];
$pengumumanAktif = !empty(pengumuman_status()['diumumkan']);

$sesi = $_SESSION['cek_status'] ?? ['verif' => [], 'kandidat' => []];
foreach (['verif', 'kandidat'] as $kunciSesi) {
    foreach ($sesi[$kunciSesi] ?? [] as $idSesi => $ts) {
        if (time() - (int) $ts > $SESI_TTL) {
            unset($sesi[$kunciSesi][$idSesi]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        $galat[] = 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        $aksi = (string) ($_POST['aksi'] ?? 'cari');

        if ($aksi === 'pilih') {
            /* Satu nomor WhatsApp dipakai beberapa pendaftar — wali memilih sendiri. */
            $id = trim((string) ($_POST['id'] ?? ''));
            if (!isset($sesi['kandidat'][$id])) {
                $galat[] = 'Pilihan tidak berlaku lagi. Cari ulang data Anda.';
            } else {
                $s = cek_status_satu($id);
                unset($sesi['kandidat'][$id]);
                if (!$s) {
                    $galat[] = 'Data tidak ditemukan.';
                } else {
                    $hasil = $s;
                    $hasil['ringkas'] = cek_status_ringkas($s['id']);
                    $sesi['verif'][$s['id']] = time();
                }
            }
        } elseif ($aksi === 'tautan_bayar') {
            /* Satu klik: buat token baru lalu lompat ke halaman pembayaran. */
            $id = trim((string) ($_POST['id'] ?? ''));
            if (!isset($sesi['verif'][$id])) {
                $galat[] = 'Sesi pemeriksaan status berakhir. Cari ulang data Anda.';
            } elseif (!rate_limit_cek('tautan_bayar', 8, 60, $id)) {
                $galat[] = 'Terlalu sering membuat tautan pembayaran. Coba lagi dalam beberapa menit.';
            } else {
                rate_limit_catat('tautan_bayar', $id);
                $s = cek_status_satu($id);
                $buat = $s ? bayar_tautan_baru($s['id']) : ['ok' => false, 'pesan' => 'Data tidak ditemukan.'];
                if ($buat['ok']) {
                    $_SESSION['cek_status'] = $sesi;
                    header('Location: ' . url($buat['tautan']));
                    exit;
                }
                $galat[] = $buat['pesan'];
                if ($s) {
                    $hasil = $s;
                    $hasil['ringkas'] = cek_status_ringkas($s['id']);
                }
            }
        } else {
            /* Pencarian: satu kolom isian, boleh nomor WhatsApp atau nomor pendaftaran. */
            $f['q']   = trim((string) ($_POST['q'] ?? ''));
            $f['wa4'] = trim((string) ($_POST['wa4'] ?? ''));

            $reg      = strtoupper($f['q']);
            $regValid = preg_match('/^REG-\d{4}-\d{4}$/', $reg) === 1;
            $waDigit  = preg_replace('/\D+/', '', $f['q']);
            $waValid  = !$regValid && strlen($waDigit) >= 9;

            /* Pesan seragam: "tidak ada" dan "cocok tapi digit salah" sengaja sama
             * agar penyerang tidak bisa membedakan nomor valid/tidak (anti-enumerasi). */
            $pesanSeragam = 'Data tidak cocok. Masukkan nomor WhatsApp yang didaftarkan, '
                . 'atau nomor pendaftaran beserta 4 digit terakhir WhatsApp.';

            if ($f['q'] === '') {
                $galat[] = 'Isi nomor WhatsApp yang didaftarkan, atau nomor pendaftaran.';
            } elseif (!$regValid && !$waValid) {
                $galat[] = 'Format tidak dikenali. Masukkan nomor WhatsApp lengkap (contoh: 081234567890) '
                    . 'atau nomor pendaftaran (contoh: REG-2026-0001).';
            } elseif (!rate_limit_cek('cek_status', 10, 15)) {
                $galat[] = 'Terlalu banyak percobaan pencarian. Coba lagi beberapa saat lagi.';
            } else {
                rate_limit_catat('cek_status');

                if ($regValid) {
                    $wa4 = preg_replace('/\D+/', '', $f['wa4']);
                    if (strlen($wa4) !== 4) {
                        $galat[] = 'Isi 4 digit terakhir WhatsApp orang tua.';
                    } elseif (!rate_limit_cek('cek_status_no', 5, 15, $reg)) {
                        /* Batas lintas IP per no_pendaftaran. */
                        $galat[] = 'Terlalu banyak percobaan pencarian. Coba lagi beberapa saat lagi.';
                    } else {
                        $kandidat = cek_status_cari($reg, '', '');
                        $cocok = null;
                        foreach ($kandidat as $k) {
                            $waAsli = preg_replace('/\D+/', '', (string) $k['no_whatsapp']);
                            if ($waAsli !== '' && substr($waAsli, -4) === $wa4) {
                                $cocok = $k;
                                break;
                            }
                        }
                        if (!$cocok) {
                            /* Kunci per-nomor hanya dicatat saat gagal — orang tua sah
                             * tidak terkunci karena sering memeriksa. */
                            rate_limit_catat('cek_status_no', $reg);
                            $galat[] = $pesanSeragam;
                            $kandidat = [];
                        } else {
                            $hasil = $cocok;
                            $hasil['ringkas'] = cek_status_ringkas($cocok['id']);
                            $sesi['verif'][$cocok['id']] = time();
                            $kandidat = [];
                        }
                    }
                } else {
                    $ketemu = cek_status_cari('', $waDigit, '');
                    if (!$ketemu) {
                        $galat[] = $pesanSeragam;
                    } elseif (count($ketemu) === 1) {
                        $hasil = $ketemu[0];
                        $hasil['ringkas'] = cek_status_ringkas($hasil['id']);
                        $sesi['verif'][$hasil['id']] = time();
                    } else {
                        /* Nomor WA dipakai beberapa pendaftar — tampilkan daftar pilihan,
                         * simpan id-nya di sesi agar tidak bisa dipilih dari luar. */
                        $kandidat = $ketemu;
                        foreach ($ketemu as $k) {
                            $sesi['kandidat'][$k['id']] = time();
                        }
                    }
                }
            }
        }
    }
}
$_SESSION['cek_status'] = $sesi;

/* Kotak 4 digit hanya relevan untuk pencarian nomor pendaftaran; JS menampilkannya
 * saat ketikan berubah, dan kondisi ini menutupi pengguna tanpa JavaScript. */
$butuhWa4 = preg_match('/^REG-\d{4}-\d{4}$/', strtoupper($f['q'])) === 1;

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
        Cukup masukkan nomor WhatsApp orang tua yang didaftarkan.
        Bisa juga pakai nomor pendaftaran — lengkap dengan 4 digit terakhir WhatsApp.
      </p>

      <?php if ($galat): ?>
        <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
          <?php foreach ($galat as $g): ?><p><?= e($g) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= url('app/spmb/cek-status.php') ?>" class="mt-6 grid gap-4 sm:grid-cols-2">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="cari">
        <div>
          <label class="label" for="q">Nomor WhatsApp atau No. Pendaftaran</label>
          <input id="q" name="q" type="tel" class="input font-mono" placeholder="081234567890"
                 value="<?= e($f['q']) ?>" autocomplete="tel" required>
          <p class="hint">Contoh: 081234567890 atau REG-2026-0001</p>
        </div>
        <div id="wa4-box" class="<?= $butuhWa4 ? '' : 'hidden' ?>">
          <label class="label" for="wa4">4 Digit Terakhir WhatsApp</label>
          <input id="wa4" name="wa4" type="tel" inputmode="numeric" maxlength="4" pattern="[0-9]{4}"
                 class="input font-mono w-full sm:w-40" placeholder="1234"
                 value="<?= e($f['wa4']) ?>"<?= $butuhWa4 ? ' required' : '' ?>>
          <p class="hint">Hanya perlu jika memakai nomor pendaftaran</p>
        </div>
        <div class="sm:col-span-2 flex justify-end">
          <button type="submit" class="btn btn-primary shadow-drop"><?= icon('search') ?> Cari Status</button>
        </div>
      </form>
    </div>

    <!-- Pilih salah satu (satu nomor WhatsApp dipakai beberapa pendaftaran) -->
    <?php if (!$hasil && $kandidat): ?>
      <div class="mt-6 card p-6 sm:p-8 shadow-drop animate-pop">
        <p class="font-semibold text-sm text-ink-900">Pilih pendaftaran yang ingin dilihat</p>
        <p class="mt-1 text-[13px] text-ink-500">Nomor WhatsApp ini dipakai untuk lebih dari satu pendaftaran.</p>
        <ul class="mt-4 divide-y divide-line">
          <?php foreach ($kandidat as $k): ?>
            <li class="py-3 flex items-center justify-between gap-3">
              <span class="min-w-0">
                <span class="block font-semibold text-sm text-ink-900 truncate"><?= e($k['nama_siswa']) ?></span>
                <span class="block font-mono text-[12px] text-ink-500"><?= e($k['no_pendaftaran']) ?> · Jalur <?= e($k['jalur'] ?: 'Reguler') ?> · <?= e(date('d/m/Y', strtotime((string) $k['created_at']))) ?></span>
              </span>
              <form method="post" action="<?= url('app/spmb/cek-status.php') ?>" class="shrink-0">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="pilih">
                <input type="hidden" name="id" value="<?= e($k['id']) ?>">
                <button type="submit" class="btn btn-outline btn-sm">Lihat Status <?= icon('arrow_forward', 'text-[16px]') ?></button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

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
            <?php
            /* Tagihan biaya pendaftaran baru ditampilkan setelah LULUS & diumumkan. */
            $bayarTampil = array_values(array_filter($r['bayar'], function ($b) use ($hasil, $pengumumanAktif) {
                return $b['jenis_pembayaran'] === 'FORMULIR'
                    || ($pengumumanAktif && ($hasil['status_seleksi'] ?? '') === 'LULUS');
            }));
            ?>
            <?php if ($bayarTampil): ?>
              <ul class="mt-2.5 space-y-2">
                <?php foreach ($bayarTampil as $b): $lb = $labelBayar[$b['status']] ?? [$b['status'], 'gray']; ?>
                  <li class="flex items-center justify-between gap-2 text-[13px]">
                    <span class="text-ink-700">
                      <?= e($b['jenis_pembayaran'] === 'FORMULIR' ? 'Biaya Formulir' : label_biaya_pendaftaran()) ?>
                      <?= !empty($b['angsuran_ke']) ? ' · Angsuran ' . (int) $b['angsuran_ke'] : '' ?>
                      · <?= rupiah($b['nominal']) ?>
                    </span>
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
          <!-- Buka halaman pembayaran: identitas sudah terbukti dari pencarian di atas. -->
          <div class="mt-6 rounded-2xl border border-line p-4">
            <p class="font-semibold text-sm text-ink-900">Halaman Pembayaran</p>
            <p class="mt-1 text-[13px] text-ink-500">
              Lihat tagihan atau unggah ulang bukti transfer bila pembayaran Anda ditolak.
              Tautan pembayaran lama otomatis tidak berlaku.
            </p>
            <form method="post" action="<?= url('app/spmb/cek-status.php') ?>" class="mt-3">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="tautan_bayar">
              <input type="hidden" name="id" value="<?= e($hasil['id']) ?>">
              <button type="submit" class="btn btn-primary shadow-drop">
                <?= icon('link') ?> Buka Halaman Pembayaran <?= icon('arrow_forward', 'text-[16px]') ?>
              </button>
            </form>
          </div>
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
<script>
  /* Nomor pendaftaran berjalan, jadi 4 digit WA tetap wajib — kotak isian ini
   * hanya muncul saat ketikan berbentuk REG-YYYY-NNNN. Tanpa JavaScript,
   * server tetap menampilkan kotak ini setelah pengiriman nomor pendaftaran. */
  (function () {
    var q = document.getElementById('q');
    var box = document.getElementById('wa4-box');
    var wa4 = document.getElementById('wa4');
    if (!q || !box || !wa4) return;
    function sync() {
      var perlu = /^REG-\d{4}-\d{4}$/i.test(q.value.trim());
      box.classList.toggle('hidden', !perlu);
      wa4.required = perlu;
    }
    q.addEventListener('input', sync);
    sync();
  })();
</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
