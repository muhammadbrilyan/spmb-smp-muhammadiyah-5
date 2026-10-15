<?php
/* ==================================================================
 * VERIFIKASI BERKAS PERSYARATAN (Fase 5).
 *   - Antrean pendaftar BERKAS_DISUBMIT / BERKAS_REVISI.
 *   - Putuskan per berkas: Setujui / Tolak (alasan wajib).
 *   - Evaluasi otomatis: semua lolos → BERKAS_TERVERIFIKASI,
 *     ada ditolak → BERKAS_REVISI + WA orang tua.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

/* --- Proses POST --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
        header('Location: berkas.php');
        exit;
    }
    $aksi  = (string) ($_POST['aksi'] ?? '');
    $dokId = (string) ($_POST['dok_id'] ?? '');
    $pendId = (string) ($_POST['pendaftar_id'] ?? '');
    $balik = 'berkas.php' . ($pendId !== '' ? '?id=' . rawurlencode($pendId) : '');

    if ($aksi === 'keputusan' && $dokId !== '') {
        $setuju = ($_POST['setuju'] ?? '') === '1';
        $alasan = trim((string) ($_POST['alasan'] ?? ''));
        $me = current_user();
        $hasil = berkas_keputusan($dokId, $setuju, $alasan, $me['id'] ?? null);
        if (!$hasil['ok']) {
            flash('error', $hasil['pesan'] ?? 'Gagal menyimpan keputusan.');
        } else {
            $eval = berkas_evaluasi_tahap($hasil['pendaftar_id']);
            flash('sukses', ($setuju ? 'Berkas disetujui.' : 'Berkas ditolak — siswa diminta revisi.')
                . ' ' . ($eval['pesan'] ?? ''));
            /* Evaluasi mungkin sudah mengubah tahap — kembali ke daftar bila selesai. */
            if (!empty($eval['ok'])) {
                $balik = 'berkas.php';
            }
        }
    }
    header('Location: ' . $balik);
    exit;
}

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title     = 'Verifikasi Berkas Persyaratan';
$desc      = 'Periksa berkas persyaratan siswa: setujui atau tolak dengan alasan.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'berkas';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik' => 'SPMB', 'Verifikasi Berkas' => null];
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
$detailId = trim((string) ($_GET['id'] ?? ''));
$antrean   = berkas_antrean();
$detail    = $detailId !== '' ? berkas_detail_pendaftar($detailId) : null;
$labelStat = ['PENDING' => 'Menunggu', 'VERIFIED' => 'Terverifikasi', 'REJECTED' => 'Ditolak'];
$toneStat  = ['PENDING' => 'amber', 'VERIFIED' => 'green', 'REJECTED' => 'red'];
$terkunci  = in_array($detail['pend']['tahap'] ?? '', ['BERKAS_TERVERIFIKASI', 'JADWAL_DITETAPKAN', 'NILAI_TERINPUT', 'DIUMUMKAN'], true);
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1280px]">

    <?php if ($flash): ?>
      <div class="mb-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <?php if ($detail && $detail['pend']): ?>
      <!-- ====================== DETAIL PENGGUNA ====================== -->
      <?php $p = $detail['pend']; ?>
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <a href="berkas.php" class="text-[13px] text-primary hover:underline inline-flex items-center gap-1"><?= icon('arrow_back', 'text-[16px]') ?> Kembali ke Antrean</a>
          <h1 class="mt-2 text-2xl font-bold tracking-tight text-ink-900">Berkas <?= e($p['no_pendaftaran']) ?></h1>
          <p class="mt-1 text-sm text-ink-500"><?= e($p['nama_siswa']) ?> · <?= e($p['asal_sekolah']) ?> · WA <?= e($p['no_whatsapp'] ?: '—') ?></p>
        </div>
        <?= badge($p['tahap'], $p['tahap'] === 'BERKAS_DISUBMIT' ? 'amber' : 'red') ?>
      </div>

      <?php if ($terkunci): ?>
        <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 text-blue-800 px-4 py-3 text-sm">
          Pemeriksaan sudah selesai / proses lanjut — keputusan berkas terkunci.
        </div>
      <?php endif; ?>

      <section class="mt-5 space-y-3">
        <?php if (!$detail['dok']): ?>
          <div class="card p-6 text-sm text-ink-500">Belum ada berkas terunggah.</div>
        <?php endif; ?>
        <?php foreach ($detail['dok'] as $d): ?>
          <article class="card p-4 sm:p-5">
            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-semibold text-ink-900"><?= e($d['nama_jenis']) ?></p>
                  <?= badge($labelStat[$d['status_verifikasi']] ?? $d['status_verifikasi'], $toneStat[$d['status_verifikasi']] ?? 'gray') ?>
                  <?php if ($d['ukuran_kb']): ?><span class="text-[12px] text-ink-400"><?= (int) $d['ukuran_kb'] ?> KB · <?= e(bulan($d['uploaded_at'])) ?></span><?php endif; ?>
                </div>
                <?php if ($d['file_url']): ?>
                  <a class="mt-1 inline-flex items-center gap-1 text-[13px] text-primary hover:underline"
                     href="<?= url('app/api/dokumen.php?f=' . rawurlencode(basename($d['file_url']))) ?>" target="_blank" rel="noopener">
                    <?= icon('visibility', 'text-[16px]') ?> Pratinjau berkas
                  </a>
                <?php endif; ?>
                <?php if ($d['status_verifikasi'] === 'REJECTED' && $d['catatan_verifikasi']): ?>
                  <p class="mt-2 text-[13px] text-[#991B1B]">Alasan: <?= e($d['catatan_verifikasi']) ?></p>
                <?php endif; ?>
                <?php if ($d['verified_at']): ?>
                  <p class="mt-1 text-[11px] text-ink-400">Keputusan terakhir: <?= e($d['verifier_nama'] ?? '—') ?> · <?= e(bulan($d['verified_at'])) ?>, <?= e(date('H:i', strtotime($d['verified_at']))) ?> WIB</p>
                <?php endif; ?>
              </div>

              <?php if (!$terkunci && $d['file_url']): ?>
                <div class="flex items-center gap-2 shrink-0">
                  <form method="post" action="berkas.php" class="inline"
                        onsubmit="return confirm('Setujui berkas <?= e($d['nama_jenis']) ?>?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="keputusan">
                    <input type="hidden" name="dok_id" value="<?= e($d['id']) ?>">
                    <input type="hidden" name="pendaftar_id" value="<?= e($p['id']) ?>">
                    <input type="hidden" name="setuju" value="1">
                    <button type="submit" class="btn btn-sm btn-green"><?= icon('task_alt', 'text-[15px]') ?> Setujui</button>
                  </form>
                  <button class="btn btn-sm btn-danger"
                          data-modal-open="modal-tolak-berkas"
                          data-md-dok="<?= e($d['id']) ?>"
                          data-md-pend="<?= e($p['id']) ?>"
                          data-md-jenis="<?= e($d['nama_jenis']) ?>">
                    <?= icon('close', 'text-[15px]') ?> Tolak
                  </button>
                </div>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

    <?php else: ?>
      <!-- ====================== ANTREAN ====================== -->
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-amber"><?= dot('amber') ?> <?= count($antrean) ?> menunggu</span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Verifikasi Berkas Persyaratan</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Periksa berkas tiap pendaftar. Setujui berkas yang lengkap & jelas; tolak dengan alasan bila kurang — siswa hanya dapat mengunggah ulang berkas yang ditolak.
        </p>
      </div>

      <section class="mt-6 card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
          <table class="w-full min-w-[860px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th">No. Pendaftaran</th>
                <th class="th">Calon Siswa</th>
                <th class="th">Tahap</th>
                <th class="th text-center">Berkas</th>
                <th class="th text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php if (!$antrean): ?>
                <tr><td class="td text-center text-ink-400" colspan="5">Tidak ada berkas menunggu pemeriksaan.</td></tr>
              <?php endif; ?>
              <?php foreach ($antrean as $a): ?>
                <tr class="tr-hover">
                  <td class="td font-mono text-[13px] font-semibold text-primary"><?= e($a['no_pendaftaran']) ?></td>
                  <td class="td">
                    <div class="font-semibold text-ink-900"><?= e($a['nama_siswa']) ?></div>
                    <div class="text-[12px] text-ink-500"><?= e($a['asal_sekolah']) ?></div>
                  </td>
                  <td class="td"><?= badge($a['tahap'], $a['tahap'] === 'BERKAS_DISUBMIT' ? 'amber' : 'red') ?></td>
                  <td class="td text-center">
                    <span class="text-[13px]"><strong class="text-ink-900"><?= (int) $a['jml_berkas'] ?></strong> terunggah</span>
                    <?php if ((int) $a['jml_tolak'] > 0): ?>
                      <span class="badge badge-red ml-1"><?= (int) $a['jml_tolak'] ?> ditolak</span>
                    <?php endif; ?>
                  </td>
                  <td class="td text-right">
                    <a href="berkas.php?id=<?= rawurlencode($a['id']) ?>" class="btn btn-sm btn-primary">
                      <?= icon('search', 'text-[15px]') ?> Periksa
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

  </main>
</div>

<!-- Modal: tolak berkas (alasan wajib) -->
<div id="modal-tolak-berkas" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
    <form method="post" action="berkas.php"
          class="animate-pop w-full max-w-lg bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="keputusan">
      <input type="hidden" name="setuju" value="0">
      <input type="hidden" name="dok_id" data-md-fill="dok" value="">
      <input type="hidden" name="pendaftar_id" data-md-fill="pend" value="">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
        <div class="flex items-start gap-3">
          <span class="icon-box bg-[#FEF2F2] text-[#B42318] shrink-0"><?= icon('cancel') ?></span>
          <div>
            <h3 class="font-bold text-ink-900 text-lg leading-tight">Tolak Berkas</h3>
            <p class="text-[13px] text-ink-500"><span data-md-fill="jenis">—</span></p>
          </div>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5">
        <label class="label" for="alasan-berkas">Alasan penolakan <span class="text-red-600">*</span></label>
        <textarea id="alasan-berkas" name="alasan" class="textarea" rows="3" required
                  placeholder="Contoh: foto buram / dokumen tidak terbaca / tidak sesuai ketentuan."></textarea>
        <p class="hint">Alasan dikirim via WhatsApp ke orang tua & ditampilkan di portal siswa.</p>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-danger"><?= icon('close') ?> Tolak Berkas</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<?php if ($flash): ?>
<script>window.APP_FLASH = <?= json_encode(['tipe' => $flash['tipe'], 'pesan' => $flash['pesan']], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
