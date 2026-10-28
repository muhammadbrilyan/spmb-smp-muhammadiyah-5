<?php
/**
 * DATA PENDAFTAR — operator SPMB (Fase 13 + arsip pendaftaran).
 *
 * Daftar seluruh pendaftar dengan pencarian, filter status verifikasi data,
 * filter aktif/terarsip, ringkasan antrean, dan tautan ke halaman Verifikasi
 * Data Calon Siswa. Aksi tulis hanya arsip/pulih (soft) — penghapusan permanen
 * sengaja tidak disediakan karena merambat ke 7 tabel lewat ON DELETE CASCADE.
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
$desc      = 'Daftar pendaftar SPMB: pencarian, status verifikasi data, kelengkapan berkas, pengarsipan, dan tindak lanjut koreksi.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$statusLabel = [
    'DRAFT'               => ['Draft', 'gray'],
    'MENUNGGU_VERIFIKASI' => ['Menunggu Verifikasi', 'amber'],
    'TERVERIFIKASI'       => ['Terverifikasi', 'green'],
    'DITOLAK'             => ['Perlu Perbaikan', 'red'],
];

$tampilkanLabel = [
    'aktif'  => 'Pendaftar aktif',
    'arsip'  => 'Terarsip',
    'semua'  => 'Semua (termasuk terarsip)',
];

$status = strtoupper(trim((string) ($_GET['status'] ?? '')));
if (!array_key_exists($status, $statusLabel)) {
    $status = '';
}
$cari = trim((string) ($_GET['q'] ?? ''));

$tampilkan = strtolower(trim((string) ($_GET['tampilkan'] ?? 'aktif')));
if (!array_key_exists($tampilkan, $tampilkanLabel)) {
    $tampilkan = 'aktif';
}

/** URL daftar kembali dengan filter yang sedang dipakai. */
$tautanDaftar = function ($tambah = []) use ($status, $cari, $tampilkan) {
    $q = array_merge(
        array_filter(['status' => $status, 'q' => $cari, 'tampilkan' => $tampilkan]),
        $tambah
    );
    return 'pendaftar.php' . ($q ? '?' . http_build_query($q) : '');
};

/* --- Proses POST: arsipkan / pulihkan --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Sesi kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.');
        header('Location: ' . $tautanDaftar());
        exit;
    }

    $aksi  = (string) ($_POST['aksi'] ?? '');
    $pend  = (string) ($_POST['pendaftar_id'] ?? '');
    $kunci = 'arsip:' . (string) ($me['id'] ?? '-');

    if ($pend === '') {
        flash('error', 'Pendaftar tidak ditentukan.');
        header('Location: ' . $tautanDaftar());
        exit;
    }
    if (!rate_limit_cek('arsip_pendaftar', 10, 60, $kunci)) {
        flash('error', 'Terlalu banyak aksi arsip/pulih. Batas 10 per jam, coba lagi beberapa menit lagi.');
        header('Location: ' . $tautanDaftar());
        exit;
    }

    if ($aksi === 'arsip') {
        $hasil = pendaftar_arsip($pend, (string) ($_POST['alasan'] ?? ''), $me['id'] ?? null);
    } elseif ($aksi === 'pulihkan') {
        $hasil = pendaftar_pulih($pend);
    } else {
        $hasil = ['ok' => false, 'pesan' => 'Aksi tidak dikenal.'];
    }

    rate_limit_catat('arsip_pendaftar', $kunci);
    flash($hasil['ok'] ? 'sukses' : 'error', $hasil['pesan']);
    header('Location: ' . $tautanDaftar());
    exit;
}

$daftar = pendaftar_data_antrean($status, $cari, '', $tampilkan);

$ringkas = [
    'total'      => (int) (q_col('SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NULL') ?? 0),
    'menunggu'   => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE status_verifikasi = 'MENUNGGU_VERIFIKASI' AND arsip_at IS NULL") ?? 0),
    'terverifikasi' => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE status_verifikasi = 'TERVERIFIKASI' AND arsip_at IS NULL") ?? 0),
    'perbaikan'  => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE status_verifikasi = 'DITOLAK' AND arsip_at IS NULL") ?? 0),
    'koreksi'    => (int) (q_col("SELECT COUNT(*) FROM pengajuan_koreksi WHERE status IN ('MENUNGGU','DIPROSES')") ?? 0),
    'arsip'      => (int) (q_col('SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NOT NULL') ?? 0),
];

/* Tautan filter yang mempertahankan kata kunci pencarian + mode tampilan. */
$tautanFilter = function ($nilai) use ($cari, $tampilkan) {
    $q = ['status' => $nilai, 'tampilkan' => $tampilkan];
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
        <p class="mt-3 pt-3 border-t border-line-soft text-[12px] text-ink-500">
          <a href="<?= e(url('app/operator/pendaftar.php?' . http_build_query(array_merge(['tampilkan' => 'arsip'], $cari !== '' ? ['q' => $cari] : [])))) ?>"
             class="font-semibold text-primary hover:underline"><?= (int) $ringkas['arsip'] ?> pendaftar terarsip</a>
          — tidak dihitung pada antrean kerja.
        </p>
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
        <div class="lg:w-56">
          <label class="label" for="tampilkan">Tampilkan</label>
          <select id="tampilkan" name="tampilkan" class="select">
            <?php foreach ($tampilkanLabel as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= $tampilkan === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="flex gap-2.5 shrink-0">
          <button type="submit" class="btn btn-primary"><?= icon('search') ?> Terapkan</button>
          <?php if ($cari !== '' || $status !== '' || $tampilkan !== 'aktif'): ?>
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
        <span class="badge badge-gray"><?= $tampilkan === 'arsip' ? 'Urut waktu pengarsipan' : ($tampilkan === 'semua' ? 'Termasuk pendaftar terarsip' : 'Urut antrean verifikasi') ?></span>
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
                  <?php if ($tampilkan === 'arsip'): ?>
                    Belum ada pendaftar yang diarsipkan.
                  <?php else: ?>
                    Tidak ada pendaftar yang cocok dengan pencarian / filter.
                  <?php endif; ?>
                </td>
              </tr>
            <?php endif; ?>
            <?php foreach ($daftar as $p): ?>
              <?php
              $lv = $statusLabel[$p['status_verifikasi']] ?? [$p['status_verifikasi'], 'gray'];
              $lt = label_tahap($p['tahap']);
              $terarsip = !empty($p['arsip_at']);
              $berubahSetelahVerifikasi = !$terarsip
                  && !empty($p['data_verified_at'])
                  && !empty($p['updated_at'])
                  && strtotime((string) $p['updated_at']) > strtotime((string) $p['data_verified_at']) + 1;
              ?>
              <tr class="tr-hover align-top <?= $terarsip ? 'opacity-70' : '' ?>">
                <td class="td">
                  <a href="<?= url('app/operator/verifikasi-data.php?id=' . rawurlencode($p['id'])) ?>" class="font-semibold text-ink-900 hover:text-primary">
                    <?= e($p['nama_siswa']) ?>
                  </a>
                  <p class="mt-0.5 font-mono text-[12px] text-ink-500"><?= e($p['no_pendaftaran']) ?></p>
                  <p class="mt-0.5 text-[12px] text-ink-400">
                    <?= e($p['asal_sekolah'] ?: '—') ?>
                    <?php if (!empty($p['nisn'])): ?> · NISN <?= e($p['nisn']) ?><?php endif; ?>
                  </p>
                  <?php if ($terarsip): ?>
                    <p class="mt-1.5 inline-flex items-center gap-1 badge badge-gray">
                      <?= icon('inventory_2', 'text-[13px]') ?> Terarsip
                    </p>
                    <p class="mt-1 text-[11px] text-ink-500 max-w-[240px]">
                      <?= e($p['arsip_alasan'] ?: 'Tanpa catatan') ?>
                      <span class="block text-ink-400">
                        oleh <?= e($p['arsip_petugas'] ?: 'petugas') ?> · <?= e(bulan($p['arsip_at'])) ?>
                      </span>
                    </p>
                  <?php endif; ?>
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
                  <div class="inline-flex items-center gap-2 justify-end">
                    <?php if ($terarsip): ?>
                      <form method="post" action="<?= e($tautanDaftar()) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="pulihkan">
                        <input type="hidden" name="pendaftar_id" value="<?= e($p['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-green"
                                onclick="return confirm('Pulihkan <?= e($p['no_pendaftaran']) ?> ke antrean aktif? Akun portal siswa diaktifkan kembali.')">
                          <?= icon('restore', 'text-[15px]') ?> Pulihkan
                        </button>
                      </form>
                    <?php else: ?>
                      <a href="<?= url('app/operator/verifikasi-data.php?id=' . rawurlencode($p['id'])) ?>" class="btn btn-sm btn-outline">
                        <?= icon('fact_check', 'text-[15px]') ?> Periksa
                      </a>
                      <button type="button" class="btn btn-sm btn-outline"
                              data-modal-open="modal-arsip"
                              data-md-id="<?= e($p['id']) ?>"
                              data-md-reg="<?= e($p['no_pendaftaran']) ?>"
                              data-md-nama="<?= e($p['nama_siswa']) ?>">
                        <?= icon('inventory_2', 'text-[15px]') ?> Arsipkan
                      </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <p class="mt-4 text-[12px] text-ink-400">
      Catatan: perubahan status verifikasi data dilakukan pada halaman detail agar catatan panitia tercatat.
      Pengarsipan bersifat <strong>tidak menghapus apa pun</strong> — data pendaftar, pembayaran, dan berkas tetap utuh
      dan dapat dipulihkan; hanya barisnya dikeluarkan dari antrean kerja, statistik, export, dan akun portal siswa
      dinonaktifkan sementara.
    </p>

  </main>
</div>

<!-- ====================== MODAL ARSIPKAN PENDAFTAR ====================== -->
<div id="modal-arsip" class="modal-root hidden fixed inset-0 z-[60]">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
    <form method="post" action="<?= e($tautanDaftar()) ?>"
          class="animate-pop w-full max-w-lg bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="arsip">
      <input type="hidden" name="pendaftar_id" data-md-fill="id" value="">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
        <div class="flex items-start gap-3">
          <span class="icon-box bg-[#FFFBEB] text-[#B45309] shrink-0"><?= icon('inventory_2') ?></span>
          <div>
            <h3 class="font-bold text-ink-900 text-lg leading-tight">Arsipkan Pendaftaran</h3>
            <p class="text-[13px] text-ink-500">
              <span data-md-fill="reg" class="font-mono text-primary">#REG</span> — <span data-md-fill="nama">—</span>
            </p>
          </div>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5">
        <label class="label" for="alasan">Alasan pengarsipan <span class="text-red-600">*</span></label>
        <textarea id="alasan" name="alasan" class="textarea" rows="3" required minlength="5"
                  placeholder="Contoh: pendaftaran ganda — yang sah REG-2026-0001."></textarea>
        <ul class="mt-3 space-y-1 text-[12px] text-ink-500 list-disc list-inside">
          <li>Pendaftar hilang dari antrean kerja, statistik, export, dan halaman cek status publik.</li>
          <li>Akun portal siswa ikut dinonaktifkan sementara; pembayaran &amp; berkas tetap tersimpan.</li>
          <li>Bisa dibatalkan kapan saja lewat tombol "Pulihkan" pada filter Terarsip.</li>
        </ul>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary"><?= icon('inventory_2') ?> Arsipkan Pendaftaran</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
