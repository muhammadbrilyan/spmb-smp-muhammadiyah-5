<?php
/**
 * HASIL SELEKSI — portal siswa.
 *
 * Dulu hanya sebuah tab di PPDB & Seleksi; kini menu tersendiri beserta
 * "amplop"-nya. Angka hasil TIDAK PERNAH dirender sebelum tombol
 * "Buka Hasil Seleksi" ditekan: status final baru dibaca dari database
 * setelah kolom pendaftar.hasil_dibuka_at terisi, sehingga tidak ada jalan
 * pintas lewat view-source maupun tautan lama.
 *
 * Pembukaan dikunci POST + CSRF dan hanya menulis sekali (WHERE ... IS NULL)
 * agar tanggal pembukaan tidak berubah saat halaman dimuat ulang. Konfeti
 * dan animasi muncul satu kali, lewat ?baru=1 pada redirect setelah POST.
 */
$title = 'Hasil Seleksi';
$desc  = 'Buka pengumuman hasil seleksi SPMB, lihat status kelulusan dan penempatan kelas.';
require __DIR__ . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    } elseif ($aksi !== 'ungkap') {
        flash('error', 'Aksi tidak dikenali.');
    } elseif (!$siswa) {
        flash('error', 'Data pendaftaran belum terhubung dengan akun ini.');
    } elseif (!$hasilBolehBuka) {
        flash('error', 'Hasil seleksi belum diumumkan oleh panitia. Mohon tunggu informasi resmi.');
    } else {
        q_exec(
            'UPDATE pendaftar SET hasil_dibuka_at = NOW() WHERE id = ? AND hasil_dibuka_at IS NULL',
            [$siswa['id']]
        );
        header('Location: ' . url('app/siswa/hasil.php?baru=1'));
        exit;
    }
    header('Location: ' . url('app/siswa/hasil.php'));
    exit;
}

$flash = flash();

/* ?baru=1 hanya berarti bila amplopnya memang sudah terbuka; kalau tidak,
 * parameter itu diabaikan agar konfeti tidak bisa dipanggil dari URL. */
$rayakan = isset($_GET['baru']) && $hasilTerbuka;

/* Status hasil setelah amplop terbuka. */
$statusSeleksi = (string) ($siswa['status_seleksi'] ?? 'PROSES');
$lulus    = $hasilTerbuka && $statusSeleksi === 'LULUS';
$gagal    = $hasilTerbuka && $statusSeleksi === 'TIDAK_LULUS';
$tagihan  = $lulus && $tagihanAktif;

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'hasil';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Siswa' => url('app/siswa/index.php'), 'Hasil Seleksi' => null];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">SPMB <?= e($PENGATURAN['tahun_ajaran'] ?? '') ?></span>
          <?php if ($siswa && !$hasilTerbuka): ?>
            <span class="badge badge-<?= $hasilBolehBuka ? 'green' : 'amber' ?>">
              <?= $hasilBolehBuka ? 'Siap Dibuka' : 'Menunggu Pengumuman' ?>
            </span>
          <?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Hasil Seleksi</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          <?= $hasilTerbuka
              ? 'Hasil resmi seleksi telah Anda buka. Simpan halaman ini sebagai acuan langkah berikutnya.'
              : 'Pengumuman dibuka sendiri oleh Anda melalui tombol di bawah, supaya hasilnya sampai dengan momen yang berkesan.' ?>
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

    <?php elseif (!$hasilBolehBuka): ?>
      <!-- Amplop belum boleh dibuka: panitia belum mengumumkan resmi. -->
      <section class="mt-6 card p-6 sm:p-8 text-center">
        <span class="icon-box mx-auto bg-canvas text-ink-400"><?= icon('lock', 'text-[22px]') ?></span>
        <h2 class="mt-4 text-lg font-extrabold text-ink-900">Pengumuman Belum Dibuka</h2>
        <?php if (!empty($pengumuman['dijadwalkan_pada'])): ?>
          <p class="mt-2 text-sm text-ink-500">
            Hasil seleksi dijadwalkan diumumkan pada
            <strong class="text-ink-900"><?= e(bulan($pengumuman['dijadwalkan_pada'])) ?>, <?= e(date('H:i', strtotime((string) $pengumuman['dijadwalkan_pada']))) ?> WIB</strong>.
          </p>
        <?php elseif (!empty($PENGATURAN['tanggal_pengumuman'])): ?>
          <p class="mt-2 text-sm text-ink-500">
            Perkiraan pengumuman: <strong class="text-ink-900"><?= e(bulan($PENGATURAN['tanggal_pengumuman'])) ?></strong>.
            Tanggal resmi akan diinformasikan panitia.
          </p>
        <?php else: ?>
          <p class="mt-2 text-sm text-ink-500">Hasil seleksi belum diumumkan. Mohon tunggu informasi resmi panitia.</p>
        <?php endif; ?>
        <p class="mt-4 text-[13px] text-ink-500">
          Tautan WhatsApp juga dikirim ke orang tua / wali begitu pengumuman diterbitkan.
        </p>
        <div class="mt-5 rounded-xl bg-canvas border border-line px-4 py-3 text-[13px] text-ink-500 text-left">
          Nilai, status kelulusan, dan penempatan kelas tidak dapat diakses sebelum waktu pengumuman resmi.
        </div>
      </section>

    <?php elseif (!$hasilTerbuka): ?>
      <!-- Amplop sudah tiba: hasil boleh dibuka, tetapi isinya baru keluar
           setelah siswa menekan tombol. -->
      <section class="mt-6 card overflow-hidden">
        <div class="px-5 sm:px-8 py-8 sm:py-12 text-center bg-primary-light">
          <span class="mx-auto flex items-center justify-center w-16 h-16 rounded-2xl bg-white border border-line shadow-drop">
            <span class="ms text-[30px] text-primary" aria-hidden="true">emoji_events</span>
          </span>
          <h2 class="mt-5 text-xl sm:text-2xl font-extrabold text-ink-900">Amplop Hasil Seleksi Sudah Tiba</h2>
          <p class="mt-2 text-sm text-ink-600 max-w-md mx-auto">
            Panitia sudah menerbitkan hasil resmi SPMB
            <?= e($PENGATURAN['tahun_ajaran'] ?? '') ?> untuk
            <strong><?= e($siswa['nama_siswa']) ?></strong>.
            Tekan tombol di bawah untuk membuka hasil dan penempatan kelasnya.
          </p>
          <form method="post" action="<?= url('app/siswa/hasil.php') ?>" class="mt-6">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="ungkap">
            <button type="submit" class="btn btn-primary amplop-tombol shadow-drop">
              <?= icon('mail') ?> Buka Hasil Seleksi
            </button>
          </form>
          <p class="mt-4 text-[12px] text-ink-500">
            Hasil hanya dapat dibuka satu kali — setelah dibuka, isinya tetap tersimpan di halaman ini.
          </p>
        </div>
        <div class="px-5 sm:px-6 py-4 border-t border-line text-[12px] text-ink-500 flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
          <span><?= icon('badge', 'text-[14px]') ?> No. <?= e($siswa['no_pendaftaran']) ?></span>
          <span><?= icon('school', 'text-[14px]') ?> <?= e($PENGATURAN['nama_sekolah'] ?? APP_SHORT) ?></span>
          <span><?= icon('lock', 'text-[14px]') ?> Terkunci sampai Anda buka</span>
        </div>
      </section>

    <?php else: ?>
      <?php /* ===== Amplop terbuka ===== */ ?>

      <?php if ($lulus): ?>
        <section class="mt-6 rounded-3xl overflow-hidden border border-[#A7F3D0] <?= $rayakan ? 'hasil-baru' : '' ?>">
          <div class="px-5 sm:px-8 py-8 sm:py-10 bg-[#065F46] text-center">
            <p class="text-[13px] font-semibold uppercase tracking-[0.2em] text-[#A7F3D0]">Alhamdulillah</p>
            <h2 class="mt-2 text-2xl sm:text-[32px] font-extrabold leading-tight text-white">
              Selamat, Kamu Dinyatakan <span class="text-[#FDE68A]">LULUS</span>
            </h2>
            <p class="mt-3 text-sm text-[#D1FAE5] max-w-xl mx-auto leading-relaxed">
              Semoga Allah SWT memberkahi langkahmu dalam menuntut ilmu di
              <?= e($PENGATURAN['nama_sekolah'] ?? APP_SHORT) ?>.
              Barakallahu fiik — selamat bergabung dengan keluarga besar sekolah.
            </p>
            <p class="mt-4 text-[12px] text-[#A7F3D0] italic">
              «Rabbi zidnii 'ilman» — Ya Allah, tambahkanlah ilmuku. (QS. Thaha: 114)
            </p>
          </div>
          <div class="px-5 sm:px-8 py-6 bg-white">
            <?php if ($penempatan): ?>
              <div class="rounded-2xl bg-primary-light border border-primary/20 px-5 py-4 text-center">
                <p class="text-[12px] font-semibold uppercase tracking-wide text-ink-500">Kelasmu Sekarang</p>
                <p class="mt-1 text-2xl font-extrabold text-ink-900"><?= e($penempatan['kode']) ?> — <?= e($penempatan['nama_kelas']) ?></p>
                <p class="mt-1 text-[13px] text-ink-500">Wali kelas: <?= e($penempatan['wali_kelas'] ?: '—') ?></p>
              </div>
            <?php else: ?>
              <div class="rounded-2xl bg-canvas border border-line px-5 py-4 text-[13px] text-ink-600 text-center">
                Anda dinyatakan <strong>lulus</strong>. Penempatan kelas akan diinformasikan panitia melalui portal ini.
              </div>
            <?php endif; ?>

            <?php if ($tagihan): ?>
              <div class="mt-5 rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] px-5 py-4">
                <p class="text-sm font-bold text-[#92400E]">Satu langkah lagi: selesaikan rincian biaya masuk</p>
                <p class="mt-1.5 text-[13px] text-[#92400E]/90 leading-relaxed">
                  Rincian dan total <?= e(label_biaya_pendaftaran()) ?> sudah terbuka di menu
                  <strong>Keuangan</strong>. Di sana Anda dapat melihat jumlah yang harus dibayar,
                  membayar lunas sekaligus, atau memilih 2 tahap angsuran lalu mengunggah bukti transfer.
                </p>
                <a href="<?= url('app/siswa/keuangan.php') ?>" class="btn btn-primary btn-block mt-4">
                  <?= icon('account_balance_wallet') ?> Buka Rincian Biaya &amp; Pembayaran
                </a>
              </div>
            <?php endif; ?>

            <div class="mt-4 flex flex-wrap gap-2.5 justify-center">
              <?php if (!$tagihan): ?>
                <a href="<?= url('app/siswa/keuangan.php') ?>" class="btn btn-outline btn-sm"><?= icon('account_balance_wallet') ?> Buka Keuangan</a>
              <?php endif; ?>
              <?php if ($penempatan): ?>
                <a href="<?= url('app/siswa/akademik.php') ?>" class="btn <?= $tagihan ? 'btn-outline' : 'btn-primary' ?> btn-sm"><?= icon('menu_book') ?> Buka Akademik</a>
              <?php endif; ?>
              <a href="<?= url('app/siswa/ppdb.php') ?>" class="btn btn-outline btn-sm"><?= icon('how_to_reg') ?> PPDB &amp; Seleksi</a>
            </div>
            <p class="mt-4 text-[12px] text-ink-400 text-center">
              Hasil dibuka pada <?= e(bulan($siswa['hasil_dibuka_at'])) ?>.
              Informasi resmi juga disampaikan melalui WhatsApp orang tua / wali.
            </p>
          </div>
        </section>
      <?php elseif ($gagal): ?>
        <section class="mt-6 rounded-3xl overflow-hidden border border-line <?= $rayakan ? 'hasil-baru' : '' ?>">
          <div class="px-5 sm:px-8 py-8 sm:py-10 bg-canvas text-center">
            <p class="text-[13px] font-semibold uppercase tracking-[0.2em] text-ink-400">Alhamdulillah</p>
            <h2 class="mt-2 text-xl sm:text-2xl font-extrabold text-ink-900">Terima Kasih Sudah Berusaha</h2>
            <p class="mt-3 text-sm text-ink-600 max-w-xl mx-auto leading-relaxed">
              Pada seleksi SPMB <?= e($PENGATURAN['tahun_ajaran'] ?? '') ?> ini, nama Anda belum dinyatakan lulus.
              Jangan berkecil hati — setiap usaha yang ikhlas memiliki nilainya di sisi Allah, dan Allah Maha Mengetahui
              apa yang terbaik untuk hamba-Nya.
            </p>
          </div>
          <div class="px-5 sm:px-8 py-6 bg-white">
            <blockquote class="rounded-2xl bg-primary-light border border-primary/20 px-5 py-4">
              <p class="text-[13px] leading-relaxed text-ink-700 italic">
                «Boleh jadi kamu membenci sesuatu, padahal ia amat baik bagimu, dan boleh jadi (pula) kamu menyukai sesuatu,
                padahal ia amat buruk bagimu; Allah mengetahui, sedang kamu tidak mengetahui.»
              </p>
              <footer class="mt-2 text-[12px] font-semibold text-primary">QS. Al-Baqarah ayat 216</footer>
            </blockquote>
            <p class="mt-4 text-[13px] text-ink-600">
              Semoga Allah SWT membuka jalan pendidikan lain yang lebih baik untukmu. Aamiin.
              Rincian nilai dan catatan panitia dapat ditanyakan kepada petugas SPMB.
            </p>
            <div class="mt-5 flex flex-wrap gap-2.5">
              <a href="<?= url('app/siswa/ppdb.php') ?>" class="btn btn-outline btn-sm"><?= icon('how_to_reg') ?> PPDB &amp; Seleksi</a>
              <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline btn-sm"><?= icon('support_agent') ?> Info Pendaftaran</a>
            </div>
            <p class="mt-4 text-[12px] text-ink-400">
              Hasil dibuka pada <?= e(bulan($siswa['hasil_dibuka_at'])) ?>.
            </p>
          </div>
        </section>
      <?php else: ?>
        <section class="mt-6 card p-6 sm:p-8 <?= $rayakan ? 'hasil-baru' : '' ?>">
          <div class="flex items-start gap-4">
            <span class="icon-box bg-canvas text-ink-400 shrink-0"><?= icon('hourglass_top', 'text-[22px]') ?></span>
            <div>
              <h2 class="text-lg font-extrabold text-ink-900">Masih Dalam Proses</h2>
              <p class="mt-2 text-sm text-ink-500">
                Pengumuman sudah dibuka, tetapi hasil akhir untuk nama Anda belum ditetapkan panitia.
                Alhamdulillah, tetap semangat — status akan diperbarui di halaman ini begitu nilainya final.
              </p>
              <a href="<?= url('app/siswa/notifikasi.php') ?>" class="btn btn-outline btn-sm mt-4"><?= icon('notifications') ?> Pantau Pengumuman</a>
            </div>
          </div>
        </section>
      <?php endif; ?>

      <?php /* Rincian nilai & keputusan — sama seperti tab sebelumnya, hanya
              tampil setelah amplop dibuka. */ ?>
      <div class="mt-5 grid gap-5 lg:grid-cols-2">
        <section class="card p-5 sm:p-6">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="font-bold text-ink-900">Status Seleksi</h2>
              <p class="mt-1 text-[13px] text-ink-500">Hasil resmi SPMB <?= e($PENGATURAN['tahun_ajaran'] ?? '') ?>.</p>
            </div>
            <?php $sel = $labelSeleksi[$statusSeleksi] ?? [$statusSeleksi, 'gray']; ?>
            <span class="badge badge-<?= e($sel[1]) ?>"><?= e($sel[0]) ?></span>
          </div>

          <?php if ($seleksi): ?>
            <dl class="mt-4 space-y-3 text-sm">
              <?php
              $nilaiBaris = [
                  ['Nilai Akhir', $seleksi['nilai_akhir'] ?? null],
                  ['CBT', $seleksi['nilai_cbt'] ?? null],
                  ['BTQ', $seleksi['nilai_btq'] ?? null],
                  ['Wawancara', $seleksi['nilai_wawancara'] ?? null],
              ];
              foreach ($nilaiBaris as $nb):
                  if ($nb[1] === null) continue;
              ?>
                <li class="flex items-center justify-between gap-3">
                  <span class="text-ink-500"><?= e($nb[0]) ?></span>
                  <span class="font-semibold text-ink-900"><?= e(number_format((float) $nb[1], 1)) ?></span>
                </li>
              <?php endforeach; ?>
            </dl>
            <?php if (!empty($seleksi['catatan'])): ?>
              <p class="mt-3 rounded-xl bg-canvas border border-line px-4 py-3 text-[13px] text-ink-600"><?= e($seleksi['catatan']) ?></p>
            <?php endif; ?>
          <?php else: ?>
            <p class="mt-4 text-sm text-ink-500">Keputusan sudah diumumkan, namun rincian nilai belum tersedia. Hubungi panitia bila diperlukan.</p>
          <?php endif; ?>
        </section>

        <section class="card p-5 sm:p-6">
          <h2 class="font-bold text-ink-900">Langkah Selanjutnya</h2>
          <ol class="mt-3 space-y-3 text-[13px]">
            <?php
            $langkah = [];
            if ($lulus && $penempatan) {
                $langkah[] = ['Lapor diri dan ikuti MPLS sesuai jadwal yang diumumkan panitia.', 'calendar_month'];
                $langkah[] = ['Selesaikan rincian biaya masuk melalui menu Keuangan — lunas sekaligus atau 2 tahap angsuran.', 'account_balance_wallet'];
                $langkah[] = ['Pantau pengumuman dan jadwal melalui menu Notifikasi dan Pengumuman.', 'notifications'];
            } elseif ($lulus) {
                $langkah[] = ['Tunggu penempatan kelas dari panitia; informasinya muncul di portal ini.', 'meeting_room'];
                $langkah[] = ['Setelah kelas ditetapkan, sisa biaya masuk dapat dibayar lewat menu Keuangan.', 'account_balance_wallet'];
            } else {
                $langkah[] = ['Simpan halaman ini sebagai bukti telah membaca hasil resmi.', 'bookmark'];
                $langkah[] = ['Tanyakan rincian nilai atau opsi pendaftaran gelombang berikutnya kepada panitia.', 'support_agent'];
            }
            foreach ($langkah as $ln): ?>
              <li class="flex items-start gap-3">
                <span class="ms text-[18px] text-primary mt-0.5 shrink-0"><?= e($ln[1]) ?></span>
                <span class="text-ink-600"><?= e($ln[0]) ?></span>
              </li>
            <?php endforeach; ?>
          </ol>
          <div class="mt-4 border-t border-line-soft pt-3 text-[12px] text-ink-500">
            Nomor pendaftaran <span class="font-mono font-semibold text-ink-700"><?= e($siswa['no_pendaftaran']) ?></span>
            · <?= e($siswa['nama_siswa']) ?>
          </div>
        </section>
      </div>
    <?php endif; ?>
  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<?php /* Konfeti hanya pada muat pertama setelah amplop dibuka, dan hanya
        untuk yang lulus — yang belum lulus diberi tampilan tenang. */ ?>
<script>window.APP_KONFETI = <?= $rayakan && $lulus ? 'true' : 'false' ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
