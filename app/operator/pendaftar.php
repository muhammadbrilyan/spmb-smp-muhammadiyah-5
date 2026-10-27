<?php
/**
 * DATA PENDAFTAR — operator SPMB (Fase 13).
 *
 * Daftar seluruh pendaftar dengan pencarian, filter status verifikasi data,
 * ringkasan antrean, dan tautan ke halaman Verifikasi Data Calon Siswa.
 * Halaman ini tidak mengubah data apa pun.
 */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'] ?? 'Operator SPMB',
    'inisial' => $me['inisial'],
];

$title     = 'Data Pendaftar';
$desc      = 'Daftar pendaftar SPMB: pencarian, status verifikasi data, kelengkapan berkas, dan tindak lanjut koreksi.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$statusLabel = [
    'DRAFT'               => ['Draft', 'gray'],
    'MENUNGGU_VERIFIKASI' => ['Menunggu Verifikasi', 'amber'],
    'TERVERIFIKASI'       => ['Terverifikasi', 'green'],
    'DITOLAK'             => ['Perlu Perbaikan', 'red'],
];

$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
if (!array_key_exists($status, $statusLabel)) {
    $status = '';
}
$cari = trim((string) ($_GET['q'] ?? ''));

$daftar = pendaftar_data_antrean($status, $cari);

$ringkas = [
    'total'      => (int) (q_col('SELECT COUNT(*) FROM pendaftar') ?? 0),
    'menunggu'   => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE status_verifikasi = 'MENUNGGU_VERIFIKASI'") ?? 0),
    'terverifikasi' => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE status_verifikasi = 'TERVERIFIKASI'") ?? 0),
    'perbaikan'  => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE status_verifikasi = 'DITOLAK'") ?? 0),
    'koreksi'    => (int) (q_col("SELECT COUNT(*) FROM pengajuan_koreksi WHERE status IN ('MENUNGGU','DIPROSES')") ?? 0),
];

/* Tautan filter yang mempertahankan kata kunci pencarian. */
$tautanFilter = function ($nilai) use ($cari) {
    $q = ['status' => $nilai];
    if ($cari !== '') {
        $q['q'] = $cari;
    }
    return url('app/operator/pendaftar.php?' . http_build_query($q));
};

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'pendaftar';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Operasional SPMB' => url('app/operator/index.php'), 'Data Pendaftar' => null];
$pageActions = '<a href="' . url('app/operator/export.php?rekap=pendaftar') . '" class="btn btn-outline hidden md:inline-flex">' . icon('download') . ' Export CSV</a>';
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1440px]">

    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Operasional SPMB</span>
          <span class="badge badge-gray"><?= e($PENGATURAN['gelombang'] ?? 'SPMB') ?> · <?= e($PENGATURAN['tahun_ajaran'] ?? '') ?></span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Data Pendaftar</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Cari pendaftar, pantau status verifikasi data, dan buka detail untuk memeriksa kelengkapan formulir serta pengajuan koreksi.
        </p>
      </div>
      <a href="<?= url('app/operator/verifikasi-data.php') ?>" class="btn btn-primary shrink-0 shadow-drop">
        <?= icon('fact_check') ?> Antrean Verifikasi Data
      </a>
    </div>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <!-- Ringkasan antrean -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <article class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Total Pendaftar</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $ringkas['total'] ?></p>
          </div>
          <span class="icon-box bg-primary-light text-primary"><?= icon('group', 'text-[20px]') ?></span>
        </div>
        <p class="mt-3 pt-3 border-t border-line-soft text-[12px] text-ink-500">Seluruh data pendaftar pada sistem.</p>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Menunggu Verifikasi</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $ringkas['menunggu'] ?></p>
          </div>
          <span class="icon-box bg-[#FFFBEB] text-[#B45309]"><?= icon('hourglass_top', 'text-[20px]') ?></span>
        </div>
        <a href="<?= e($tautanFilter('MENUNGGU_VERIFIKASI')) ?>" class="mt-3 pt-3 border-t border-line-soft text-[12px] font-semibold text-primary hover:underline inline-flex items-center gap-1">
          Periksa antrean <?= icon('arrow_forward', 'text-[15px]') ?>
        </a>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Terverifikasi</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $ringkas['terverifikasi'] ?></p>
          </div>
          <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E]"><?= icon('verified', 'text-[20px]') ?></span>
        </div>
        <a href="<?= e($tautanFilter('TERVERIFIKASI')) ?>" class="mt-3 pt-3 border-t border-line-soft text-[12px] font-semibold text-primary hover:underline inline-flex items-center gap-1">
          Lihat data terkunci <?= icon('arrow_forward', 'text-[15px]') ?>
        </a>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Perlu Perbaikan / Koreksi</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $ringkas['perbaikan'] ?></p>
          </div>
          <span class="icon-box bg-[#FEF2F2] text-[#991B1B]"><?= icon('report', 'text-[20px]') ?></span>
        </div>
        <p class="mt-3 pt-3 border-t border-line-soft text-[12px] text-ink-500">
          <strong class="text-ink-900"><?= (int) $ringkas['koreksi'] ?></strong> pengajuan koreksi aktif
        </p>
      </article>
    </section>

    <!-- Pencarian & filter -->
    <section class="mt-5 card p-4 sm:p-5">
      <form method="get" action="<?= url('app/operator/pendaftar.php') ?>" class="flex flex-col lg:flex-row lg:items-end gap-3">
        <div class="flex-1 min-w-0">
          <label class="label" for="q">Cari pendaftar</label>
          <div class="relative">
            <span class="ms absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-ink-400">search</span>
            <input id="q" name="q" value="<?= e($cari) ?>" class="input pl-10"
                   placeholder="Nama, no. pendaftaran, NISN, atau nomor WhatsApp">
          </div>
        </div>
        <div class="lg:w-56">
          <label class="label" for="status">Status verifikasi data</label>
          <select id="status" name="status" class="select">
            <option value="">Semua status</option>
            <?php foreach ($statusLabel as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex gap-2.5 shrink-0">
          <button type="submit" class="btn btn-primary"><?= icon('search') ?> Terapkan</button>
          <?php if ($cari !== '' || $status !== ''): ?>
            <a href="<?= url('app/operator/pendaftar.php') ?>" class="btn btn-outline">Reset</a>
          <?php endif; ?>
        </div>
      </form>
    </section>

    <!-- Tabel -->
    <section class="mt-5 card overflow-hidden">
      <div class="p-5 sm:p-6 pb-0 sm:pb-0 flex items-start justify-between gap-3 flex-wrap">
        <div>
          <h2 class="font-bold text-ink-900">Daftar Pendaftar</h2>
          <p class="mt-1 text-[13px] text-ink-500">
            <?= count($daftar) ?> data ditampilkan<?= $cari !== '' ? ' untuk pencarian “' . e($cari) . '”' : '' ?>.
          </p>
        </div>
        <span class="badge badge-gray">Urut antrean verifikasi</span>
      </div>

      <div class="mt-4 overflow-x-auto scroll-thin">
        <table class="w-full min-w-[1080px] border-collapse">
          <thead class="thead">
            <tr>
              <th class="th">Pendaftar</th>
              <th class="th">Kontak</th>
              <th class="th">Tahap</th>
              <th class="th">Verifikasi Data</th>
              <th class="th">Berkas</th>
              <th class="th">Koreksi</th>
              <th class="th text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <?php if (!$daftar): ?>
              <tr>
                <td class="td text-center text-ink-400" colspan="7">
                  Tidak ada pendaftar yang cocok dengan pencarian / filter.
                </td>
              </tr>
            <?php endif; ?>
            <?php foreach ($daftar as $p): ?>
              <?php
              $lv = $statusLabel[$p['status_verifikasi']] ?? [$p['status_verifikasi'], 'gray'];
              $lt = label_tahap($p['tahap']);
              $berubahSetelahVerifikasi = !empty($p['data_verified_at'])
                  && !empty($p['updated_at'])
                  && strtotime((string) $p['updated_at']) > strtotime((string) $p['data_verified_at']) + 1;
              ?>
              <tr class="tr-hover align-top">
                <td class="td">
                  <a href="<?= url('app/operator/verifikasi-data.php?id=' . rawurlencode($p['id'])) ?>" class="font-semibold text-ink-900 hover:text-primary">
                    <?= e($p['nama_siswa']) ?>
                  </a>
                  <p class="mt-0.5 font-mono text-[12px] text-ink-500"><?= e($p['no_pendaftaran']) ?></p>
                  <p class="mt-0.5 text-[12px] text-ink-400">
                    <?= e($p['asal_sekolah'] ?: '—') ?>
                    <?php if (!empty($p['nisn'])): ?> · NISN <?= e($p['nisn']) ?><?php endif; ?>
                  </p>
                </td>
                <td class="td text-[13px]">
                  <p class="font-semibold text-ink-900"><?= e($p['no_whatsapp'] ?: '—') ?></p>
                  <p class="text-[12px] text-ink-400"><?= e($p['nama_orang_tua'] ?: 'Orang tua belum diisi') ?></p>
                </td>
                <td class="td">
                  <span class="badge badge-<?= e($lt['tone']) ?>"><?= e($lt['label']) ?></span>
                  <?php if (!empty($p['kelas_kode'])): ?>
                    <p class="mt-1 text-[11px] text-ink-400">Kelas <?= e($p['kelas_kode']) ?></p>
                  <?php endif; ?>
                </td>
                <td class="td">
                  <span class="badge badge-<?= e($lv[1]) ?>"><?= e($lv[0]) ?></span>
                  <?php if ($berubahSetelahVerifikasi): ?>
                    <p class="mt-1 text-[11px] text-[#B45309] font-semibold">Data berubah setelah verifikasi</p>
                  <?php elseif (!empty($p['data_verified_at'])): ?>
                    <p class="mt-1 text-[11px] text-ink-400"><?= e(bulan($p['data_verified_at'])) ?></p>
                  <?php endif; ?>
                  <?php if (!empty($p['catatan_verifikasi_data'])): ?>
                    <p class="mt-1 text-[11px] text-ink-500 max-w-[210px] truncate" title="<?= e($p['catatan_verifikasi_data']) ?>">
                      <?= e($p['catatan_verifikasi_data']) ?>
                    </p>
                  <?php endif; ?>
                </td>
                <td class="td text-[13px]">
                  <p class="font-semibold text-ink-900"><?= (int) $p['dok_terisi'] ?>/<?= (int) $p['dok_wajib'] ?></p>
                  <p class="text-[11px] text-ink-400"><?= (int) $p['dok_verifikasi'] ?> terverifikasi</p>
                </td>
                <td class="td">
                  <?php if ((int) $p['koreksi_aktif'] > 0): ?>
                    <span class="badge badge-amber"><?= (int) $p['koreksi_aktif'] ?> aktif</span>
                  <?php else: ?>
                    <span class="text-[12px] text-ink-400">—</span>
                  <?php endif; ?>
                </td>
                <td class="td text-right">
                  <a href="<?= url('app/operator/verifikasi-data.php?id=' . rawurlencode($p['id'])) ?>" class="btn btn-sm btn-outline">
                    <?= icon('fact_check', 'text-[15px]') ?> Periksa
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <p class="mt-4 text-[12px] text-ink-400">
      Catatan: daftar ini hanya untuk pemantauan. Perubahan status verifikasi data dilakukan pada halaman detail agar catatan panitia tercatat.
    </p>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
