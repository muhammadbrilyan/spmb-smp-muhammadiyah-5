<?php
/**
 * VERIFIKASI DATA CALON SISWA — operator SPMB (Fase 13).
 *
 * Dua hal yang ditangani halaman ini:
 *   1. Verifikasi DATA calon siswa (identitas, orang tua, alamat) — terpisah
 *      dari verifikasi berkas persyaratan di berkas.php.
 *   2. Antrean pengajuan KOREKSI data dari siswa setelah data terkunci.
 *
 * Data induk tidak pernah diubah otomatis. Menyetujui koreksi hanya membuka
 * kembali data agar siswa memperbaiki sendiri, lalu mengajukan verifikasi.
 */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator']);

$me = current_user();

/* ==================================================================
 * PROSES POST
 * ================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pendId = trim((string) ($_POST['pendaftar_id'] ?? ''));
    $balik  = 'verifikasi-data.php' . ($pendId !== '' ? '?id=' . rawurlencode($pendId) : '');

    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
        header('Location: ' . $balik);
        exit;
    }

    $aksi = (string) ($_POST['aksi'] ?? '');

    if ($aksi === 'set_status_data' && $pendId !== '') {
        $hasil = verifikasi_data_set(
            $pendId,
            (string) ($_POST['status'] ?? ''),
            (string) ($_POST['catatan'] ?? ''),
            (string) ($me['id'] ?? '')
        );
        if (!empty($hasil['ok'])) {
            $statusBaru = strtoupper((string) ($_POST['status'] ?? ''));
            $pesan = $statusBaru === 'TERVERIFIKASI'
                ? 'Data calon siswa diverifikasi & dikunci.'
                : ($statusBaru === 'DITOLAK'
                    ? 'Data dikembalikan ke siswa untuk diperbaiki.'
                    : 'Status verifikasi data diperbarui.');
            flash('sukses', $pesan);
        } else {
            flash('error', $hasil['pesan'] ?? 'Gagal memperbarui status verifikasi data.');
        }
        header('Location: ' . $balik);
        exit;
    }

    if ($aksi === 'koreksi_status') {
        $koreksiId = trim((string) ($_POST['koreksi_id'] ?? ''));
        $hasil = koreksi_ubah_status(
            $koreksiId,
            (string) ($_POST['status'] ?? ''),
            (string) ($_POST['catatan'] ?? ''),
            (string) ($me['id'] ?? '')
        );
        flash(!empty($hasil['ok']) ? 'sukses' : 'error', $hasil['pesan'] ?? 'Gagal memperbarui pengajuan koreksi.');
        header('Location: ' . $balik);
        exit;
    }

    flash('error', 'Aksi tidak dikenali.');
    header('Location: ' . $balik);
    exit;
}

$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'] ?? 'Operator SPMB',
    'inisial' => $me['inisial'],
];

$title     = 'Verifikasi Data Calon Siswa';
$desc      = 'Periksa kelengkapan data calon siswa, tetapkan status verifikasi, dan tindak lanjuti pengajuan koreksi.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$labelVer = [
    'DRAFT'               => ['Draft', 'gray'],
    'MENUNGGU_VERIFIKASI' => ['Menunggu Verifikasi', 'amber'],
    'TERVERIFIKASI'       => ['Terverifikasi', 'green'],
    'DITOLAK'             => ['Perlu Perbaikan', 'red'],
];
$labelSeleksi = ['PROSES' => ['Dalam Proses', 'amber'], 'LULUS' => ['Lulus', 'green'], 'TIDAK_LULUS' => ['Tidak Lulus', 'red']];

$detailId = trim((string) ($_GET['id'] ?? ''));
$detail   = $detailId !== '' ? pendaftar_data_detail($detailId) : null;

if ($detailId !== '' && !$detail) {
    flash('error', 'Data pendaftar tidak ditemukan.');
    header('Location: verifikasi-data.php');
    exit;
}

$flash = flash();

/* Data halaman detail. */
$dokumen     = [];
$koreksi     = [];
$dataKurang  = [];
$dataLengkap = false;
$totalVerif  = 0.0;

if ($detail) {
    $dokumen = dokumen_siswa($detail['id']);
    $koreksi = koreksi_siswa($detail['id']);
    $totalVerif = (float) ($detail['bayar_verified'] ?? 0);

    $dataKurang  = pendaftar_data_kurang($detail);
    $dataLengkap = !$dataKurang;

    $berubahSetelahVerifikasi = !empty($detail['data_verified_at'])
        && !empty($detail['updated_at'])
        && strtotime((string) $detail['updated_at']) > strtotime((string) $detail['data_verified_at']) + 1;
} else {
    $antreanData     = pendaftar_data_antrean('MENUNGGU_VERIFIKASI');
    $antreanDraft    = pendaftar_data_antrean('DRAFT');
    $antreanPerbaikan = pendaftar_data_antrean('DITOLAK');
    $antreanKoreksi  = koreksi_antrean('MENUNGGU');
    $antreanProses   = koreksi_antrean('DIPROSES');

    $ringkas = [
        'menunggu' => count($antreanData),
        'draft'    => count($antreanDraft),
        'perbaikan' => count($antreanPerbaikan),
        'koreksi'  => count($antreanKoreksi) + count($antreanProses),
    ];
}

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'verifikasi-data';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Operasional SPMB' => url('app/operator/index.php'), 'Verifikasi Data Calon Siswa' => null];
$pageActions = $detail
    ? '<a href="' . url('app/operator/pendaftar.php') . '" class="btn btn-outline hidden md:inline-flex">' . icon('group') . ' Data Pendaftar</a>'
    : '<a href="' . url('app/operator/pendaftar.php') . '" class="btn btn-outline hidden md:inline-flex">' . icon('group') . ' Data Pendaftar</a>';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1280px]">

    <?php if ($flash): ?>
      <div class="mb-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <?php if ($detail): ?>
      <?php
      $lv = $labelVer[$detail['status_verifikasi']] ?? [$detail['status_verifikasi'], 'gray'];
      $lt = label_tahap($detail['tahap']);
      $sel = $labelSeleksi[$detail['status_seleksi']] ?? [$detail['status_seleksi'], 'gray'];
      ?>

      <!-- ====================== DETAIL PENDAFTAR ====================== -->
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <a href="verifikasi-data.php" class="text-[13px] text-primary hover:underline inline-flex items-center gap-1">
            <?= icon('arrow_back', 'text-[16px]') ?> Kembali ke Antrean
          </a>
          <h1 class="mt-2 text-2xl font-bold tracking-tight text-ink-900"><?= e($detail['nama_siswa']) ?></h1>
          <p class="mt-1 text-sm text-ink-500">
            <span class="font-mono font-semibold text-ink-700"><?= e($detail['no_pendaftaran']) ?></span>
            · <?= e($detail['asal_sekolah'] ?: 'Asal sekolah belum diisi') ?>
            · WA <?= e($detail['no_whatsapp'] ?: '—') ?>
          </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
          <?= badge($lv[0], $lv[1]) ?>
          <span class="badge badge-<?= e($lt['tone']) ?>"><?= e($lt['label']) ?></span>
          <span class="badge badge-<?= e($sel[1]) ?>">Seleksi: <?= e($sel[0]) ?></span>
        </div>
      </div>

      <?php if ($berubahSetelahVerifikasi): ?>
        <div class="mt-4 rounded-xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-3 text-[13px] text-[#92400E]">
          <strong>Perhatian:</strong> data berubah setelah terakhir diverifikasi. Tinjau kembali sebelum mempertahankan status terverifikasi.
        </div>
      <?php endif; ?>

      <?php if (!empty($detail['catatan_verifikasi_data'])): ?>
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-[#991B1B]">
          <strong>Catatan terakhir untuk siswa:</strong> <?= e($detail['catatan_verifikasi_data']) ?>
        </div>
      <?php endif; ?>

      <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <!-- Kolom kiri: data -->
        <div class="lg:col-span-2 space-y-5">
          <!-- Identitas -->
          <section class="card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <h2 class="font-bold text-ink-900">Identitas Calon Siswa</h2>
              <span class="badge badge-<?= $dataLengkap ? 'green' : 'amber' ?>">
                <?= $dataLengkap ? 'Data wajib lengkap' : count($dataKurang) . ' butir belum lengkap' ?>
              </span>
            </div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
              <div><dt class="label">Nama Lengkap</dt><dd class="text-sm font-semibold text-ink-900"><?= e($detail['nama_siswa']) ?></dd></div>
              <div><dt class="label">NISN</dt><dd class="text-sm font-mono text-ink-900"><?= e($detail['nisn'] ?: '—') ?></dd></div>
              <div><dt class="label">Jenis Kelamin</dt><dd class="text-sm text-ink-900"><?= ($detail['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : (($detail['jenis_kelamin'] ?? '') === 'L' ? 'Laki-laki' : '—') ?></dd></div>
              <div><dt class="label">Tempat, Tanggal Lahir</dt><dd class="text-sm text-ink-900">
                <?= e($detail['tempat_lahir'] ?: '—') ?><?= !empty($detail['tanggal_lahir']) ? ', ' . e(bulan($detail['tanggal_lahir'])) : '' ?>
              </dd></div>
              <div><dt class="label">Jalur Pendaftaran</dt><dd class="text-sm text-ink-900"><?= e($detail['jalur'] ?: 'Reguler') ?></dd></div>
              <div><dt class="label">Domisili</dt><dd class="text-sm text-ink-900"><?= !empty($detail['is_luar_daerah']) ? 'Luar Daerah' : 'Dalam Daerah' ?></dd></div>
              <div class="sm:col-span-2"><dt class="label">Alamat Siswa</dt><dd class="text-sm text-ink-900"><?= e($detail['alamat'] ?: '—') ?></dd></div>
            </dl>
          </section>

          <!-- Orang tua -->
          <section class="card p-5 sm:p-6">
            <h2 class="font-bold text-ink-900">Orang Tua / Wali</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
              <div><dt class="label">Nama</dt><dd class="text-sm font-semibold text-ink-900"><?= e($detail['nama_orang_tua'] ?: '—') ?></dd></div>
              <div><dt class="label">WhatsApp</dt><dd class="text-sm text-ink-900"><?= e($detail['no_whatsapp'] ?: '—') ?></dd></div>
              <div><dt class="label">Pekerjaan</dt><dd class="text-sm text-ink-900"><?= e($detail['pekerjaan'] ?: '—') ?></dd></div>
              <div><dt class="label">Penghasilan / Bulan</dt><dd class="text-sm text-ink-900"><?= e($detail['penghasilan_bulanan'] ?: '—') ?></dd></div>
              <div class="sm:col-span-2"><dt class="label">Alamat Orang Tua / Wali</dt><dd class="text-sm text-ink-900"><?= e($detail['alamat_orang_tua'] ?: '—') ?></dd></div>
            </dl>
          </section>

          <!-- Berkas -->
          <section class="card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <div>
                <h2 class="font-bold text-ink-900">Berkas Persyaratan</h2>
                <p class="mt-1 text-[13px] text-ink-500">Verifikasi keputusan berkas dilakukan di halaman Verifikasi Kelengkapan Formulir.</p>
              </div>
              <a href="<?= url('app/operator/berkas.php?id=' . rawurlencode($detail['id'])) ?>" class="btn btn-sm btn-outline"><?= icon('folder_shared', 'text-[15px]') ?> Buka Berkas</a>
            </div>
            <div class="mt-4 space-y-2.5">
              <?php if (!$dokumen): ?>
                <p class="text-sm text-ink-400">Belum ada jenis dokumen aktif.</p>
              <?php endif; ?>
              <?php
              $statusDokLabel = ['PENDING' => ['Menunggu', 'amber'], 'VERIFIED' => ['Terverifikasi', 'green'], 'REJECTED' => ['Ditolak', 'red']];
              foreach ($dokumen as $d):
                  $sd = $statusDokLabel[$d['status']] ?? ['Kosong', 'gray'];
              ?>
                <div class="flex items-center justify-between gap-3 rounded-xl border border-line px-3.5 py-2.5">
                  <div class="min-w-0">
                    <p class="text-[13px] font-semibold text-ink-900">
                      <?= e($d['nama']) ?><?= !empty($d['wajib']) ? ' <span class="text-red-600">*</span>' : '' ?>
                    </p>
                    <?php if (!empty($d['file_url'])): ?>
                      <?php $tipeBerkas = strtolower(pathinfo(basename((string) $d['file_url']), PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'gambar'; ?>
                      <button type="button" class="text-[12px] font-semibold text-primary hover:underline"
                              data-pratinjau-url="<?= e(url('app/api/dokumen.php?f=' . rawurlencode(basename((string) $d['file_url'])))) ?>"
                              data-pratinjau-nama="<?= e($d['nama']) ?>"
                              data-pratinjau-tipe="<?= e($tipeBerkas) ?>"
                              data-pratinjau-meta="<?= e($tipeBerkas === 'pdf' ? 'PDF' : 'Gambar') ?>">Lihat berkas</button>
                    <?php else: ?>
                      <p class="text-[12px] text-ink-400">Belum diunggah</p>
                    <?php endif; ?>
                  </div>
                  <?= badge($sd[0], $sd[1]) ?>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        </div>

        <!-- Kolom kanan: keputusan -->
        <div class="space-y-5">
          <section class="card p-5">
            <h2 class="font-bold text-ink-900 text-sm">Ringkasan</h2>
            <dl class="mt-3 space-y-2.5 text-[13px]">
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Kelengkapan data</dt><dd class="font-semibold text-ink-900"><?= $dataLengkap ? 'Lengkap' : count($dataKurang) . ' kurang' ?></dd></div>
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Berkas wajib</dt><dd class="font-semibold text-ink-900"><?= (int) $detail['dok_terisi'] ?>/<?= (int) $detail['dok_wajib'] ?></dd></div>
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Pembayaran terverifikasi</dt><dd class="font-semibold text-ink-900"><?= rupiah($totalVerif) ?></dd></div>
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Bukti menunggu</dt><dd class="font-semibold text-ink-900"><?= (int) $detail['bayar_pending'] ?> baris</dd></div>
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Koreksi aktif</dt><dd class="font-semibold text-ink-900"><?= (int) $detail['koreksi_aktif'] ?></dd></div>
            </dl>
            <?php if (!empty($detail['data_verified_at'])): ?>
              <p class="mt-3 border-t border-line-soft pt-3 text-[11px] text-ink-400">
                Diverifikasi <?= e($detail['data_verifier'] ?: 'petugas') ?> · <?= e(bulan($detail['data_verified_at'])) ?>
              </p>
            <?php endif; ?>
          </section>

          <?php if ($dataKurang): ?>
            <section class="card p-5">
              <h2 class="font-bold text-ink-900 text-sm">Belum Lengkap</h2>
              <ul class="mt-2.5 space-y-1.5 text-[12px] text-[#92400E]">
                <?php foreach ($dataKurang as $k): ?>
                  <li class="flex items-start gap-1.5"><?= icon('error', 'text-[14px] mt-0.5') ?> <?= e($k) ?></li>
                <?php endforeach; ?>
              </ul>
              <p class="mt-3 text-[12px] text-ink-500">Verifikasi penuh dinonaktifkan sampai butir wajib terisi. Gunakan “Minta Perbaikan” untuk memberi catatan.</p>
            </section>
          <?php endif; ?>

          <!-- Keputusan verifikasi -->
          <section class="card p-5">
            <h2 class="font-bold text-ink-900 text-sm">Keputusan Verifikasi Data</h2>
            <p class="mt-1 text-[12px] text-ink-500">
              “Verifikasi” mengunci formulir siswa. “Minta Perbaikan” membuka kembali formulir dan mengirim catatan.
            </p>

            <form method="post" action="verifikasi-data.php" class="mt-4 space-y-3">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="set_status_data">
              <input type="hidden" name="pendaftar_id" value="<?= e($detail['id']) ?>">
              <div>
                <label class="label" for="catatan-data">Catatan panitia</label>
                <textarea id="catatan-data" name="catatan" class="textarea" rows="3"
                          placeholder="Wajib untuk “Minta Perbaikan”. Contoh: alamat orang tua belum lengkap."></textarea>
                <p class="hint">Catatan ditampilkan kepada siswa. Kosongkan saat data sudah benar.</p>
              </div>
              <div class="grid gap-2.5">
                <button type="submit" name="status" value="TERVERIFIKASI"
                        class="btn btn-green btn-block <?= $dataLengkap ? '' : 'opacity-50 pointer-events-none' ?>"
                        <?= $dataLengkap ? '' : 'disabled' ?>
                        onclick="return confirm('Verifikasi & kunci data <?= e($detail['no_pendaftaran']) ?>?');">
                  <?= icon('verified') ?> Verifikasi &amp; Kunci Data
                </button>
                <button type="submit" name="status" value="DITOLAK" class="btn btn-danger btn-block"
                        onclick="return confirm('Kembalikan data ke siswa untuk diperbaiki? Pastikan catatan sudah diisi.');">
                  <?= icon('edit_note') ?> Minta Perbaikan
                </button>
                <?php if (in_array($detail['status_verifikasi'], ['TERVERIFIKASI', 'DITOLAK'], true)): ?>
                  <button type="submit" name="status" value="MENUNGGU_VERIFIKASI" class="btn btn-outline btn-block"
                          onclick="return confirm('Kembalikan status ke “Menunggu Verifikasi”?');">
                    <?= icon('undo') ?> Kembalikan ke Antrean
                  </button>
                <?php endif; ?>
              </div>
            </form>
          </section>
        </div>
      </div>

      <!-- ====================== PENGAJUAN KOREKSI ====================== -->
      <section class="mt-6 card p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="font-bold text-ink-900">Pengajuan Koreksi Data</h2>
            <p class="mt-1 text-[13px] text-ink-500">
              Siswa hanya dapat mengajukan koreksi setelah data terverifikasi. Menyetujui koreksi <strong>tidak mengubah data otomatis</strong> —
              formulir dibuka kembali agar siswa memperbaiki sendiri.
            </p>
          </div>
          <span class="badge badge-gray"><?= count($koreksi) ?> pengajuan</span>
        </div>

        <div class="mt-4 space-y-3">
          <?php if (!$koreksi): ?>
            <p class="text-sm text-ink-400">Belum ada pengajuan koreksi dari pendaftar ini.</p>
          <?php endif; ?>
          <?php foreach ($koreksi as $k): $kl = koreksi_status_label($k['status']); ?>
            <article class="rounded-2xl border border-line p-4">
              <div class="flex items-start justify-between gap-2 flex-wrap">
                <div>
                  <p class="text-[13px] font-semibold text-ink-900"><?= e(koreksi_bidang()[$k['bidang']] ?? $k['bidang']) ?></p>
                  <p class="text-[11px] text-ink-400">Diajukan <?= e(bulan($k['created_at'])) ?><?= !empty($k['reviewed_at']) ? ' · ditinjau ' . e(bulan($k['reviewed_at'])) : '' ?></p>
                </div>
                <span class="badge badge-<?= e($kl[1]) ?>"><?= e($kl[0]) ?></span>
              </div>
              <p class="mt-2 text-[13px] text-ink-700 whitespace-pre-line"><?= e($k['pesan']) ?></p>
              <?php if (!empty($k['catatan_operator'])): ?>
                <p class="mt-2 rounded-lg bg-canvas px-3 py-2 text-[12px] text-ink-600"><strong>Catatan operator:</strong> <?= e($k['catatan_operator']) ?></p>
              <?php endif; ?>

              <?php if (in_array($k['status'], ['MENUNGGU', 'DIPROSES'], true)): ?>
                <form method="post" action="verifikasi-data.php" class="mt-3 grid gap-2.5 sm:grid-cols-[180px_1fr_auto] sm:items-end">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="koreksi_status">
                  <input type="hidden" name="koreksi_id" value="<?= e($k['id']) ?>">
                  <input type="hidden" name="pendaftar_id" value="<?= e($detail['id']) ?>">
                  <div>
                    <label class="label" for="ks-<?= e($k['id']) ?>">Tindakan</label>
                    <select id="ks-<?= e($k['id']) ?>" name="status" class="select">
                      <option value="DIPROSES" <?= $k['status'] === 'DIPROSES' ? 'selected' : '' ?>>Tandai Diproses</option>
                      <option value="DISETUJUI">Setujui &amp; Buka Data</option>
                      <option value="DITOLAK">Tolak</option>
                    </select>
                  </div>
                  <div>
                    <label class="label" for="kn-<?= e($k['id']) ?>">Catatan (wajib bila ditolak)</label>
                    <input id="kn-<?= e($k['id']) ?>" name="catatan" class="input" placeholder="Contoh: bukti akta kurang jelas.">
                  </div>
                  <button type="submit" class="btn btn-primary btn-sm h-10"><?= icon('send', 'text-[15px]') ?> Simpan</button>
                </form>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      </section>

    <?php else: ?>

      <!-- ====================== ANTREAN ====================== -->
      <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <span class="badge badge-blue">Operasional SPMB</span>
            <span class="badge badge-gray"><?= e($PENGATURAN['gelombang'] ?? 'SPMB') ?></span>
          </div>
          <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Verifikasi Data Calon Siswa</h1>
          <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
            Periksa data identitas, orang tua/wali, dan alamat. Verifikasi data berbeda dari verifikasi berkas persyaratan.
          </p>
        </div>
        <a href="<?= url('app/operator/pendaftar.php') ?>" class="btn btn-primary shrink-0 shadow-drop">
          <?= icon('group') ?> Semua Pendaftar
        </a>
      </div>

      <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Menunggu Verifikasi</p>
              <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $ringkas['menunggu'] ?></p>
            </div>
            <span class="icon-box bg-[#FFFBEB] text-[#B45309]"><?= icon('hourglass_top', 'text-[20px]') ?></span>
          </div>
        </article>
        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Draft</p>
              <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $ringkas['draft'] ?></p>
            </div>
            <span class="icon-box bg-canvas text-ink-500"><?= icon('edit_note', 'text-[20px]') ?></span>
          </div>
        </article>
        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Perlu Perbaikan</p>
              <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $ringkas['perbaikan'] ?></p>
            </div>
            <span class="icon-box bg-[#FEF2F2] text-[#991B1B]"><?= icon('report', 'text-[20px]') ?></span>
          </div>
        </article>
        <article class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-[13px] font-medium text-ink-500">Koreksi Aktif</p>
              <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $ringkas['koreksi'] ?></p>
            </div>
            <span class="icon-box bg-primary-light text-primary"><?= icon('edit_note', 'text-[20px]') ?></span>
          </div>
        </article>
      </section>

      <!-- Antrean data -->
      <section class="mt-5 card overflow-hidden">
        <div class="p-5 sm:p-6 pb-0 sm:pb-0">
          <h2 class="font-bold text-ink-900">Antrean Verifikasi Data</h2>
          <p class="mt-1 text-[13px] text-ink-500">Pendaftar yang sudah mengajukan verifikasi data.</p>
        </div>
        <div class="mt-4 overflow-x-auto scroll-thin">
          <table class="w-full min-w-[820px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th">Pendaftar</th>
                <th class="th">Kontak</th>
                <th class="th">Berkas</th>
                <th class="th">Koreksi</th>
                <th class="th text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php if (!$antreanData): ?>
                <tr><td class="td text-center text-ink-400" colspan="5">Tidak ada pendaftar yang menunggu verifikasi data.</td></tr>
              <?php endif; ?>
              <?php foreach ($antreanData as $p): ?>
                <tr class="tr-hover">
                  <td class="td">
                    <p class="font-semibold text-ink-900"><?= e($p['nama_siswa']) ?></p>
                    <p class="font-mono text-[12px] text-ink-500"><?= e($p['no_pendaftaran']) ?></p>
                    <p class="text-[12px] text-ink-400"><?= e($p['asal_sekolah'] ?: '—') ?></p>
                  </td>
                  <td class="td text-[13px]"><?= e($p['no_whatsapp'] ?: '—') ?></td>
                  <td class="td text-[13px] font-semibold"><?= (int) $p['dok_terisi'] ?>/<?= (int) $p['dok_wajib'] ?></td>
                  <td class="td">
                    <?php if ((int) $p['koreksi_aktif'] > 0): ?>
                      <span class="badge badge-amber"><?= (int) $p['koreksi_aktif'] ?> aktif</span>
                    <?php else: ?>
                      <span class="text-[12px] text-ink-400">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="td text-right">
                    <a href="verifikasi-data.php?id=<?= rawurlencode($p['id']) ?>" class="btn btn-sm btn-outline"><?= icon('fact_check', 'text-[15px]') ?> Periksa</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Antrean koreksi -->
      <section class="mt-5 card overflow-hidden">
        <div class="p-5 sm:p-6 pb-0 sm:pb-0 flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="font-bold text-ink-900">Pengajuan Koreksi Data</h2>
            <p class="mt-1 text-[13px] text-ink-500">Pengajuan dari siswa yang datanya sudah terverifikasi.</p>
          </div>
          <span class="badge badge-amber"><?= (int) $ringkas['koreksi'] ?> menunggu / diproses</span>
        </div>
        <div class="mt-4 overflow-x-auto scroll-thin">
          <table class="w-full min-w-[860px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th">Pendaftar</th>
                <th class="th">Bidang</th>
                <th class="th">Uraian</th>
                <th class="th">Status</th>
                <th class="th text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php if (!$antreanKoreksi && !$antreanProses): ?>
                <tr><td class="td text-center text-ink-400" colspan="5">Tidak ada pengajuan koreksi aktif.</td></tr>
              <?php endif; ?>
              <?php foreach (array_merge($antreanKoreksi, $antreanProses) as $k): $kl = koreksi_status_label($k['status']); ?>
                <tr class="tr-hover align-top">
                  <td class="td">
                    <p class="font-semibold text-ink-900"><?= e($k['nama_siswa']) ?></p>
                    <p class="font-mono text-[12px] text-ink-500"><?= e($k['no_pendaftaran']) ?></p>
                  </td>
                  <td class="td text-[13px]"><?= e(koreksi_bidang()[$k['bidang']] ?? $k['bidang']) ?></td>
                  <td class="td text-[13px] max-w-[320px]"><?= e($k['pesan']) ?></td>
                  <td class="td"><span class="badge badge-<?= e($kl[1]) ?>"><?= e($kl[0]) ?></span></td>
                  <td class="td text-right">
                    <a href="verifikasi-data.php?id=<?= rawurlencode($k['pendaftar_id']) ?>" class="btn btn-sm btn-outline">Tinjau</a>
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

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php require __DIR__ . '/../../includes/partials/pratinjau-dokumen.php'; ?>
</body>
</html>
