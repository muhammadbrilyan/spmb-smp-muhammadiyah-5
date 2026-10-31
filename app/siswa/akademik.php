<?php
/**
 * AKADEMIK — portal siswa (Fase 13).
 *
 * Hanya terbuka untuk siswa yang SUDAH DITERIMA (LULUS dan hasil sudah
 * diumumkan resmi). Sistem SPMB ini belum menyimpan nilai rapor, absensi,
 * atau jadwal pelajaran, sehingga bagian tersebut ditandai belum tersedia
 * dan tidak diisi data contoh.
 */
$title = 'Akademik';
$desc  = 'Informasi akademik siswa diterima: penempatan kelas, wali kelas, dan data dasar sekolah.';
require __DIR__ . '/_bootstrap.php';

$flash = flash();

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'akademik';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Siswa' => url('app/siswa/index.php'), 'Akademik' => null];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Akademik</span>
          <span class="badge badge-gray"><?= e($PENGATURAN['tahun_ajaran'] ?? '') ?></span>
          <?php if ($siswaDiterima): ?>
            <span class="badge badge-green">Siswa Diterima</span>
          <?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Akademik</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Informasi akademik resmi untuk siswa yang telah dinyatakan diterima. Data nilai, absensi, dan jadwal pelajaran dikelola sekolah setelah siswa aktif.
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

    <?php elseif (!$pengumumanAktif): ?>
      <section class="mt-6 card p-6 sm:p-8 text-center">
        <span class="icon-box bg-canvas text-ink-400 mx-auto"><?= icon('lock', 'text-[26px]') ?></span>
        <h2 class="mt-3 text-lg font-bold text-ink-900">Akademik belum dapat diakses</h2>
        <p class="mt-2 text-sm text-ink-500 max-w-xl mx-auto">
          Menu Akademik hanya terbuka bagi siswa yang sudah dinyatakan diterima dan hasil seleksinya telah diumumkan resmi oleh panitia.
        </p>
        <div class="mt-4 flex flex-wrap items-center justify-center gap-2.5">
          <a href="<?= url('app/siswa/ppdb.php?tab=hasil') ?>" class="btn btn-primary btn-sm"><?= icon('emoji_events') ?> Lihat Status Seleksi</a>
        </div>
      </section>

    <?php elseif (!$siswaDiterima): ?>
      <?php $sel = $labelSeleksi[$siswa['status_seleksi']] ?? [$siswa['status_seleksi'], 'gray']; ?>
      <section class="mt-6 card p-6 sm:p-8">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
          <span class="icon-box bg-canvas text-ink-400 shrink-0"><?= icon('info', 'text-[24px]') ?></span>
          <div>
            <h2 class="font-bold text-ink-900">Menu Akademik khusus siswa diterima</h2>
            <p class="mt-1.5 text-sm text-ink-500">
              Berdasarkan hasil seleksi resmi, akun Anda berstatus
              <span class="badge badge-<?= e($sel[1]) ?>"><?= e($sel[0]) ?></span>.
              Menu Akademik hanya tersedia bagi siswa yang dinyatakan lulus dan diterima.
            </p>
            <p class="mt-3 text-[13px] text-ink-500">
              Anda tetap dapat melihat data pendaftaran dan hasil seleksi melalui menu terkait di bawah ini.
            </p>
            <div class="mt-4 flex flex-wrap gap-2.5">
              <a href="<?= url('app/siswa/ppdb.php?tab=hasil') ?>" class="btn btn-outline btn-sm"><?= icon('emoji_events') ?> Hasil Seleksi</a>
              <a href="<?= url('app/siswa/data-siswa.php') ?>" class="btn btn-outline btn-sm"><?= icon('badge') ?> Data Siswa</a>
            </div>
          </div>
        </div>
      </section>

    <?php else: ?>

      <!-- Status & penempatan -->
      <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Status Siswa</p>
              <p class="mt-2 text-lg font-extrabold text-ink-900">Diterima</p>
              <p class="mt-0.5 text-[12px] text-ink-500">Hasil seleksi resmi</p>
            </div>
            <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E]"><?= icon('verified', 'text-[20px]') ?></span>
          </div>
        </article>

        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Kelas</p>
              <p class="mt-2 text-lg font-extrabold text-ink-900"><?= e($penempatan['kode'] ?? 'Belum Ditentukan') ?></p>
              <p class="mt-0.5 text-[12px] text-ink-500"><?= e($penempatan['nama_kelas'] ?? 'Menunggu penempatan panitia') ?></p>
            </div>
            <span class="icon-box bg-primary-light text-primary"><?= icon('class', 'text-[20px]') ?></span>
          </div>
        </article>

        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Wali Kelas</p>
              <p class="mt-2 text-lg font-extrabold text-ink-900 leading-tight"><?= e($penempatan['wali_kelas'] ?? '') ?: '—' ?></p>
              <p class="mt-0.5 text-[12px] text-ink-500"><?= e($PENGATURAN['tahun_ajaran'] ?? '') ?></p>
            </div>
            <span class="icon-box bg-[#EFF6FF] text-[#2563EB]"><?= icon('person', 'text-[20px]') ?></span>
          </div>
        </article>

        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">NISN</p>
              <p class="mt-2 text-lg font-mono font-extrabold text-ink-900"><?= e($siswa['nisn'] ?: '—') ?></p>
              <p class="mt-0.5 text-[12px] text-ink-500">Nomor induk siswa nasional</p>
            </div>
            <span class="icon-box bg-[#FFF7ED] text-[#EA580C]"><?= icon('badge', 'text-[20px]') ?></span>
          </div>
        </article>
      </section>

      <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <!-- Penempatan -->
        <section class="card p-5 sm:p-6 lg:col-span-2">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <h2 class="font-bold text-ink-900">Penempatan Kelas</h2>
              <p class="mt-1 text-[13px] text-ink-500">Ditetapkan panitia setelah hasil seleksi diumumkan.</p>
            </div>
            <?php if ($penempatan): ?>
              <span class="badge badge-green">Ditempatkan</span>
            <?php else: ?>
              <span class="badge badge-amber">Menunggu</span>
            <?php endif; ?>
          </div>

          <?php if ($penempatan): ?>
            <div class="mt-4 rounded-xl bg-primary-light border border-primary/20 p-4">
              <p class="text-[13px] text-primary font-semibold">Kelas Anda</p>
              <p class="mt-1 text-xl font-extrabold text-ink-900"><?= e($penempatan['kode']) ?> — <?= e($penempatan['nama_kelas']) ?></p>
              <p class="mt-1 text-[13px] text-ink-500">Wali kelas: <?= e($penempatan['wali_kelas'] ?: '—') ?></p>
            </div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
              <div><dt class="label">Tahun Ajaran</dt><dd class="text-sm font-semibold text-ink-900"><?= e($PENGATURAN['tahun_ajaran'] ?? '—') ?></dd></div>
              <div><dt class="label">Tanggal Penempatan</dt><dd class="text-sm text-ink-900"><?= e($penempatan['created_at'] ? bulan($penempatan['created_at']) : '—') ?></dd></div>
              <div class="sm:col-span-2"><dt class="label">Catatan Panitia</dt><dd class="text-sm text-ink-700"><?= e($penempatan['catatan'] ?: '—') ?></dd></div>
            </dl>
          <?php else: ?>
            <div class="mt-4 rounded-xl bg-[#FFFBEB] border border-[#FDE68A] px-4 py-3 text-[13px] text-[#92400E]">
              Anda sudah dinyatakan lulus, namun penempatan kelas belum ditetapkan. Informasi akan tampil otomatis setelah panitia menyelesaikan penempatan.
            </div>
          <?php endif; ?>

          <div class="mt-5 border-t border-line-soft pt-4 text-[12px] text-ink-500">
            Perubahan data diri dilakukan melalui menu
            <a class="font-semibold text-primary hover:underline" href="<?= url('app/siswa/data-siswa.php') ?>">Data Siswa</a>.
          </div>
        </section>

        <!-- Data dasar -->
        <section class="card p-5 sm:p-6">
          <h2 class="font-bold text-ink-900">Data Akademik Dasar</h2>
          <dl class="mt-4 space-y-3 text-[13px]">
            <div><dt class="text-ink-400">Nama siswa</dt><dd class="font-semibold text-ink-900"><?= e($siswa['nama_siswa']) ?></dd></div>
            <div><dt class="text-ink-400">Asal sekolah</dt><dd class="font-semibold text-ink-900"><?= e($siswa['asal_sekolah'] ?: '—') ?></dd></div>
            <div><dt class="text-ink-400">Jalur pendaftaran</dt><dd class="font-semibold text-ink-900"><?= e($siswa['jalur'] ?: 'Reguler') ?></dd></div>
            <div><dt class="text-ink-400">No. pendaftaran</dt><dd class="font-mono font-semibold text-ink-900"><?= e($siswa['no_pendaftaran']) ?></dd></div>
            <div><dt class="text-ink-400">Tahun ajaran</dt><dd class="font-semibold text-ink-900"><?= e($PENGATURAN['tahun_ajaran'] ?? '—') ?></dd></div>
          </dl>
          <a href="<?= url('app/siswa/ppdb.php?tab=hasil') ?>" class="btn btn-outline btn-block btn-sm mt-4"><?= icon('emoji_events') ?> Lihat Hasil Seleksi</a>
        </section>
      </div>

      <!-- Ketersediaan data -->
      <section class="mt-5 card p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="font-bold text-ink-900">Data Akademik Lanjutan</h2>
            <p class="mt-1 text-[13px] text-ink-500">Bagian berikut belum dikelola oleh sistem SPMB ini.</p>
          </div>
          <span class="badge badge-gray">Belum Tersedia</span>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
          <div class="rounded-xl border border-line p-4">
            <span class="ms text-[22px] text-ink-400">grading</span>
            <p class="mt-2 text-sm font-semibold text-ink-900">Nilai Rapor</p>
            <p class="mt-1 text-[12px] text-ink-500">Belum ada modul nilai rapor pada sistem ini. Data nilai tidak ditampilkan agar tidak menyesatkan.</p>
          </div>
          <div class="rounded-xl border border-line p-4">
            <span class="ms text-[22px] text-ink-400">fact_check</span>
            <p class="mt-2 text-sm font-semibold text-ink-900">Absensi</p>
            <p class="mt-1 text-[12px] text-ink-500">Absensi belum tersedia. Rekap kehadiran akan dikelola sekolah setelah kegiatan belajar dimulai.</p>
          </div>
          <div class="rounded-xl border border-line p-4">
            <span class="ms text-[22px] text-ink-400">calendar_month</span>
            <p class="mt-2 text-sm font-semibold text-ink-900">Jadwal Pelajaran</p>
            <p class="mt-1 text-[12px] text-ink-500">Jadwal pelajaran belum diterbitkan melalui portal SPMB. Ikuti informasi resmi dari sekolah.</p>
          </div>
        </div>
      </section>

    <?php endif; ?>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
