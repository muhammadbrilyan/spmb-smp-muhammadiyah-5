<?php
/* ==================================================================
 * VERIFIKASI PENDAFTAR — satu ruang kerja operator (Fase 14).
 *
 * Menggabungkan dua halaman yang dulu dikerjakan berurutan:
 *   - berkas.php          : setujui/tolak tiap berkas persyaratan
 *   - verifikasi-data.php : verifikasi data identitas + antrean koreksi
 * Satu pendaftar kini dibuka di satu layar: datanya, berkasnya,
 * keputusan verifikasinya, dan pengajuan koreksinya.
 *
 * Mode tampilan:
 *   ?tab=berkas|data|koreksi          -> antrean tab tersebut
 *   ?tab=...&id=<pendaftar_id>        -> ruang kerja satu pendaftar
 * Berkas.php & verifikasi-data.php tinggal menjadi pengalih.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

$me = current_user();

$TABS = [
    'berkas'  => 'Berkas Persyaratan',
    'data'    => 'Data Siswa',
    'koreksi' => 'Koreksi Data',
];
$tab = (string) ($_POST['tab'] ?? $_GET['tab'] ?? 'berkas');
if (!isset($TABS[$tab])) {
    $tab = 'berkas';
}

/* ==================================================================
 * PROSES POST
 * ================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $pendId = trim((string) ($_POST['pendaftar_id'] ?? ''));
    $balik  = 'verifikasi.php?tab=' . rawurlencode($tab) . ($pendId !== '' ? '&id=' . rawurlencode($pendId) : '');

    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
        header('Location: verifikasi.php?tab=' . rawurlencode($tab));
        exit;
    }

    $aksi  = (string) ($_POST['aksi'] ?? '');
    $dokId = (string) ($_POST['dok_id'] ?? '');

    /* --- Hapus berkas pribadi (retensi) — konfirmasi ketik ulang nomor --- */
    if ($aksi === 'retensi_hapus' && $pendId !== '') {
        if (!rate_limit_cek('retensi_hapus', 5, 60, 'prtn:' . $pendId)) {
            flash('error', 'Batas eksekusi retensi per pendaftar terlampaui (maks 5/jam).');
            header('Location: ' . $balik);
            exit;
        }
        $cekP = q_all('SELECT no_pendaftaran FROM pendaftar WHERE id = ?', [$pendId]);
        $noP  = $cekP ? (string) $cekP[0]['no_pendaftaran'] : '';
        if ($noP === '') {
            flash('error', 'Pendaftar tidak ditemukan.');
            header('Location: verifikasi.php?tab=' . rawurlencode($tab));
            exit;
        }
        if (trim((string) ($_POST['konfirmasi'] ?? '')) !== $noP) {
            flash('error', 'Konfirmasi tidak cocok — ketik no. pendaftaran (' . e($noP) . ') dengan persis.');
            header('Location: ' . $balik);
            exit;
        }
        rate_limit_catat('retensi_hapus', 'prtn:' . $pendId);
        $hasil = retensi_hapus_berkas($pendId, 'manual_detail_verifikasi', $me['id'] ?? null);
        if ($hasil['ok']) {
            $pesan = 'Berkas pribadi ' . $noP . ' dihapus: ' . $hasil['jumlah'] . ' berkas';
            if (!empty($hasil['gagal'])) {
                $pesan .= ' (' . $hasil['gagal'] . ' gagal/dilewati)';
            }
            if (isset($hasil['jejak']) && !$hasil['jejak']) {
                $pesan .= ' — PERINGATAN: jejak di retensi_log GAGAL tercatat.';
            }
            flash('sukses', $pesan . '.');
        } else {
            flash('error', $hasil['pesan'] ?? 'Gagal menjalankan retensi.');
        }
        header('Location: ' . $balik);
        exit;
    }

    /* --- Keputusan satu berkas (setujui / tolak dengan alasan) --- */
    if ($aksi === 'keputusan' && $dokId !== '') {
        $setuju = ($_POST['setuju'] ?? '') === '1';
        $alasan = trim((string) ($_POST['alasan'] ?? ''));
        $hasil  = berkas_keputusan($dokId, $setuju, $alasan, $me['id'] ?? null);
        if (!$hasil['ok']) {
            flash('error', $hasil['pesan'] ?? 'Gagal menyimpan keputusan.');
        } else {
            $eval = berkas_evaluasi_tahap($hasil['pendaftar_id']);
            flash('sukses', ($setuju ? 'Berkas disetujui.' : 'Berkas ditolak — siswa diminta revisi.')
                . ' ' . ($eval['pesan'] ?? ''));
            /* Semua berkas sudah diputus: kembali ke antrean. */
            if (!empty($eval['ok'])) {
                header('Location: verifikasi.php?tab=berkas');
                exit;
            }
        }
        header('Location: ' . $balik);
        exit;
    }

    /* --- Status verifikasi data calon siswa --- */
    if ($aksi === 'set_status_data' && $pendId !== '') {
        $hasil = verifikasi_data_set(
            $pendId,
            (string) ($_POST['status'] ?? ''),
            (string) ($_POST['catatan'] ?? ''),
            (string) ($me['id'] ?? '')
        );
        if (!empty($hasil['ok'])) {
            $statusBaru = strtoupper((string) ($_POST['status'] ?? ''));
            flash('sukses', $statusBaru === 'TERVERIFIKASI'
                ? 'Data calon siswa diverifikasi & dikunci.'
                : ($statusBaru === 'DITOLAK'
                    ? 'Data dikembalikan ke siswa untuk diperbaiki.'
                    : 'Status verifikasi data diperbarui.'));
        } else {
            flash('error', $hasil['pesan'] ?? 'Gagal memperbarui status verifikasi data.');
        }
        header('Location: ' . $balik);
        exit;
    }

    /* --- Tindak lanjut pengajuan koreksi --- */
    if ($aksi === 'koreksi_status') {
        $hasil = koreksi_ubah_status(
            trim((string) ($_POST['koreksi_id'] ?? '')),
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

/* ==================================================================
 * DATA TAMPILAN
 * ================================================================== */
$labelVer = [
    'DRAFT'               => ['Draft', 'gray'],
    'MENUNGGU_VERIFIKASI' => ['Menunggu Verifikasi', 'amber'],
    'TERVERIFIKASI'       => ['Terverifikasi', 'green'],
    'DITOLAK'             => ['Perlu Perbaikan', 'red'],
];
$labelSeleksi = ['PROSES' => ['Dalam Proses', 'amber'], 'LULUS' => ['Lulus', 'green'], 'TIDAK_LULUS' => ['Tidak Lulus', 'red']];
$labelStatDok = ['PENDING' => 'Menunggu', 'VERIFIED' => 'Terverifikasi', 'REJECTED' => 'Ditolak'];
$toneStatDok  = ['PENDING' => 'amber', 'VERIFIED' => 'green', 'REJECTED' => 'red'];

$detailId = trim((string) ($_GET['id'] ?? ''));
$pend     = $detailId !== '' ? pendaftar_data_detail($detailId) : null;

if ($detailId !== '' && !$pend) {
    flash('error', 'Data pendaftar tidak ditemukan.');
    header('Location: verifikasi.php?tab=' . rawurlencode($tab));
    exit;
}

$hitung   = ['berkas' => 0, 'data' => 0, 'koreksi' => 0];
$antreanBerkas  = [];
$antreanData    = [];
$antreanKoreksi = [];
$semuaData      = [];
$FILTER_DATA = ['MENUNGGU_VERIFIKASI' => 'Menunggu', 'DITOLAK' => 'Perlu Perbaikan', 'DRAFT' => 'Draft', 'SEMUA' => 'Semua'];
$sFilter = strtoupper(trim((string) ($_GET['s'] ?? 'MENUNGGU_VERIFIKASI')));
if (!isset($FILTER_DATA[$sFilter])) {
    $sFilter = 'MENUNGGU_VERIFIKASI';
}

/* Ruang kerja satu pendaftar. */
$dok         = [];
$koreksiSaya = [];
$dataKurang  = [];
if ($pend) {
    $berkasDetail = berkas_detail_pendaftar($detailId) ?: ['pend' => null, 'dok' => []];
    $dok          = $berkasDetail['dok'];
    $koreksiSaya  = koreksi_siswa($detailId);
    $dataKurang   = pendaftar_data_kurang($pend);
    $dataLengkap  = !$dataKurang;
    $terkunci     = in_array((string) $pend['tahap'], ['BERKAS_TERVERIFIKASI', 'JADWAL_DITETAPKAN', 'NILAI_TERINPUT', 'DIUMUMKAN'], true);
    $berubahSetelahVerifikasi = !empty($pend['data_verified_at'])
        && !empty($pend['updated_at'])
        && strtotime((string) $pend['updated_at']) > strtotime((string) $pend['data_verified_at']) + 1;
    $dokSetuju = 0;
    foreach ($dok as $d) {
        if ($d['status_verifikasi'] === 'VERIFIED') { $dokSetuju++; }
    }
} else {
    /* Antrean: satu query per kelompok, angkanya dipakai untuk badge tab. */
    $antreanBerkas  = berkas_antrean();
    $semuaData      = pendaftar_data_antrean('');
    $antreanKoreksi = array_merge(koreksi_antrean('MENUNGGU'), koreksi_antrean('DIPROSES'));

    /* Satu query di atas dipakai untuk badge tab maupun chip filter. */
    $perStatus = ['MENUNGGU_VERIFIKASI' => 0, 'DITOLAK' => 0, 'DRAFT' => 0, 'TERVERIFIKASI' => 0];
    foreach ($semuaData as $r) {
        $st = (string) $r['status_verifikasi'];
        if (isset($perStatus[$st])) { $perStatus[$st]++; }
    }
    $hitung['berkas']  = count($antreanBerkas);
    $hitung['data']    = $perStatus['MENUNGGU_VERIFIKASI'];
    $hitung['koreksi'] = count($antreanKoreksi);

    $antreanData = $sFilter === 'SEMUA'
        ? $semuaData
        : array_values(array_filter($semuaData, function ($r) use ($sFilter) {
            return (string) $r['status_verifikasi'] === $sFilter;
        }));
}

$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'] ?? 'Operator SPMB',
    'inisial' => $me['inisial'],
];

$title     = $pend ? 'Verifikasi ' . $pend['nama_siswa'] : 'Verifikasi Pendaftar';
$desc      = 'Periksa berkas persyaratan, data calon siswa, dan pengajuan koreksi dalam satu halaman.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'verifikasi';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Operasional SPMB' => url('app/operator/index.php'), 'Verifikasi Pendaftar' => null];
$pageActions = '<a href="' . url('app/operator/pendaftar.php') . '" class="btn btn-outline hidden md:inline-flex">' . icon('group') . ' Data Pendaftar</a>';
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1280px]">

    <?php if ($flash): ?>
      <div class="mb-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <?php if ($pend): ?>
      <?php
      /* ================= RUANG KERJA SATU PENDAFTAR ================= */
      $lv  = $labelVer[$pend['status_verifikasi']] ?? [$pend['status_verifikasi'], 'gray'];
      $lt  = label_tahap($pend['tahap']);
      $sel = $labelSeleksi[$pend['status_seleksi']] ?? [$pend['status_seleksi'], 'gray'];
      ?>
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <a href="verifikasi.php?tab=<?= e($tab) ?>" class="text-[13px] text-primary hover:underline inline-flex items-center gap-1">
            <?= icon('arrow_back', 'text-[16px]') ?> Kembali ke antrean <?= e(strtolower($TABS[$tab])) ?>
          </a>
          <h1 class="mt-2 text-2xl font-bold tracking-tight text-ink-900"><?= e($pend['nama_siswa']) ?></h1>
          <p class="mt-1 text-sm text-ink-500">
            <span class="font-mono font-semibold text-ink-700"><?= e($pend['no_pendaftaran']) ?></span>
            · <?= e($pend['asal_sekolah'] ?: 'Asal sekolah belum diisi') ?>
            · WA <?= e($pend['no_whatsapp'] ?: '—') ?>
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

      <?php if (!empty($pend['catatan_verifikasi_data'])): ?>
        <div class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-[13px] text-[#991B1B]">
          <strong>Catatan terakhir untuk siswa:</strong> <?= e($pend['catatan_verifikasi_data']) ?>
        </div>
      <?php endif; ?>

      <div class="mt-5 grid gap-5 lg:grid-cols-3">
        <!-- Kolom kiri: berkas + data -->
        <div class="lg:col-span-2 space-y-5">

          <!-- Berkas persyaratan -->
          <section class="card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <div>
                <h2 class="font-bold text-ink-900">Berkas Persyaratan</h2>
                <p class="mt-1 text-[13px] text-ink-500">
                  Setujui berkas yang jelas &amp; sesuai. Tolak dengan alasan bila kurang — siswa hanya dapat mengunggah ulang berkas yang ditolak.
                </p>
              </div>
              <span class="badge badge-<?= $dokSetuju >= count($dok) && $dok ? 'green' : 'amber' ?>"><?= $dokSetuju ?>/<?= count($dok) ?> disetujui</span>
            </div>

            <?php if ($terkunci): ?>
              <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 text-blue-800 px-4 py-3 text-[13px]">
                Pemeriksaan berkas sudah selesai / proses lanjut — keputusan berkas terkunci.
              </div>
            <?php endif; ?>

            <div class="mt-4 space-y-3">
              <?php if (!$dok): ?>
                <p class="text-sm text-ink-400">Belum ada berkas terunggah.</p>
              <?php endif; ?>
              <?php foreach ($dok as $d): ?>
                <article class="rounded-2xl border border-line p-4">
                  <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                    <div class="flex-1 min-w-0">
                      <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-semibold text-ink-900"><?= e($d['nama_jenis']) ?></p>
                        <?= badge($labelStatDok[$d['status_verifikasi']] ?? $d['status_verifikasi'], $toneStatDok[$d['status_verifikasi']] ?? 'gray') ?>
                        <?php if ($d['ukuran_kb']): ?><span class="text-[12px] text-ink-400"><?= (int) $d['ukuran_kb'] ?> KB · <?= e(bulan($d['uploaded_at'])) ?></span><?php endif; ?>
                      </div>
                      <?php if ($d['file_url']): ?>
                        <?php $tipeBerkas = strtolower(pathinfo(basename((string) $d['file_url']), PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'gambar'; ?>
                        <button type="button" class="mt-1 inline-flex items-center gap-1 text-[13px] font-semibold text-primary hover:underline"
                                data-pratinjau-url="<?= e(url('app/api/dokumen.php?f=' . rawurlencode(basename((string) $d['file_url'])))) ?>"
                                data-pratinjau-nama="<?= e($d['nama_jenis']) ?> · <?= e($pend['no_pendaftaran']) ?>"
                                data-pratinjau-tipe="<?= e($tipeBerkas) ?>"
                                data-pratinjau-meta="<?= e(($tipeBerkas === 'pdf' ? 'PDF' : 'Gambar') . ($d['ukuran_kb'] ? ' · ' . (int) $d['ukuran_kb'] . ' KB' : '')) ?>">
                          <?= icon('visibility', 'text-[16px]') ?> Pratinjau berkas
                        </button>
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
                        <form method="post" action="verifikasi.php" class="inline"
                              onsubmit="return confirm('Setujui berkas <?= e($d['nama_jenis']) ?>?')">
                          <?= csrf_field() ?>
                          <input type="hidden" name="tab" value="<?= e($tab) ?>">
                          <input type="hidden" name="aksi" value="keputusan">
                          <input type="hidden" name="dok_id" value="<?= e($d['id']) ?>">
                          <input type="hidden" name="pendaftar_id" value="<?= e($pend['id']) ?>">
                          <input type="hidden" name="setuju" value="1">
                          <button type="submit" class="btn btn-sm btn-green"><?= icon('task_alt', 'text-[15px]') ?> Setujui</button>
                        </form>
                        <button class="btn btn-sm btn-danger"
                                data-modal-open="modal-tolak-berkas"
                                data-md-dok="<?= e($d['id']) ?>"
                                data-md-pend="<?= e($pend['id']) ?>"
                                data-md-jenis="<?= e($d['nama_jenis']) ?>">
                          <?= icon('close', 'text-[15px]') ?> Tolak
                        </button>
                      </div>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          </section>

          <!-- Identitas -->
          <section class="card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <h2 class="font-bold text-ink-900">Identitas Calon Siswa</h2>
              <span class="badge badge-<?= $dataLengkap ? 'green' : 'amber' ?>">
                <?= $dataLengkap ? 'Data wajib lengkap' : count($dataKurang) . ' butir belum lengkap' ?>
              </span>
            </div>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
              <div><dt class="label">Nama Lengkap</dt><dd class="text-sm font-semibold text-ink-900"><?= e($pend['nama_siswa']) ?></dd></div>
              <div><dt class="label">NISN</dt><dd class="text-sm font-mono text-ink-900"><?= e($pend['nisn'] ?: '—') ?></dd></div>
              <div><dt class="label">Jenis Kelamin</dt><dd class="text-sm text-ink-900"><?= ($pend['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : (($pend['jenis_kelamin'] ?? '') === 'L' ? 'Laki-laki' : '—') ?></dd></div>
              <div><dt class="label">Tempat, Tanggal Lahir</dt><dd class="text-sm text-ink-900">
                <?= e($pend['tempat_lahir'] ?: '—') ?><?= !empty($pend['tanggal_lahir']) ? ', ' . e(bulan($pend['tanggal_lahir'])) : '' ?>
              </dd></div>
              <div><dt class="label">Jalur Pendaftaran</dt><dd class="text-sm text-ink-900"><?= e($pend['jalur'] ?: 'Reguler') ?></dd></div>
              <div><dt class="label">Domisili</dt><dd class="text-sm text-ink-900"><?= !empty($pend['is_luar_daerah']) ? 'Luar Daerah' : 'Dalam Daerah' ?></dd></div>
              <div class="sm:col-span-2"><dt class="label">Alamat Siswa</dt><dd class="text-sm text-ink-900"><?= e($pend['alamat'] ?: '—') ?></dd></div>
            </dl>
          </section>

          <!-- Orang tua -->
          <section class="card p-5 sm:p-6">
            <h2 class="font-bold text-ink-900">Orang Tua / Wali</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
              <div><dt class="label">Nama</dt><dd class="text-sm font-semibold text-ink-900"><?= e($pend['nama_orang_tua'] ?: '—') ?></dd></div>
              <div><dt class="label">WhatsApp</dt><dd class="text-sm text-ink-900"><?= e($pend['no_whatsapp'] ?: '—') ?></dd></div>
              <div><dt class="label">Pekerjaan</dt><dd class="text-sm text-ink-900"><?= e($pend['pekerjaan'] ?: '—') ?></dd></div>
              <div><dt class="label">Penghasilan / Bulan</dt><dd class="text-sm text-ink-900"><?= e($pend['penghasilan_bulanan'] ?: '—') ?></dd></div>
              <div class="sm:col-span-2"><dt class="label">Alamat Orang Tua / Wali</dt><dd class="text-sm text-ink-900"><?= e($pend['alamat_orang_tua'] ?: '—') ?></dd></div>
            </dl>
          </section>
        </div>

        <!-- Kolom kanan: keputusan -->
        <div class="space-y-5">
          <section class="card p-5">
            <h2 class="font-bold text-ink-900 text-sm">Keputusan Verifikasi Data</h2>
            <p class="mt-1 text-[12px] text-ink-500">
              "Verifikasi" mengunci formulir siswa. "Minta Perbaikan" membuka kembali formulir dan mengirim catatan.
            </p>

            <form method="post" action="verifikasi.php" class="mt-4 space-y-3">
              <?= csrf_field() ?>
              <input type="hidden" name="tab" value="<?= e($tab) ?>">
              <input type="hidden" name="aksi" value="set_status_data">
              <input type="hidden" name="pendaftar_id" value="<?= e($pend['id']) ?>">
              <div>
                <label class="label" for="catatan-data">Catatan panitia</label>
                <textarea id="catatan-data" name="catatan" class="textarea" rows="3"
                          placeholder="Wajib untuk &quot;Minta Perbaikan&quot;. Contoh: alamat orang tua belum lengkap."></textarea>
                <p class="hint">Catatan ditampilkan kepada siswa. Kosongkan saat data sudah benar.</p>
              </div>
              <div class="grid gap-2.5">
                <button type="submit" name="status" value="TERVERIFIKASI"
                        class="btn btn-green btn-block <?= $dataLengkap ? '' : 'opacity-50 pointer-events-none' ?>"
                        <?= $dataLengkap ? '' : 'disabled' ?>
                        onclick="return confirm('Verifikasi & kunci data <?= e($pend['no_pendaftaran']) ?>?');">
                  <?= icon('verified') ?> Verifikasi &amp; Kunci Data
                </button>
                <button type="submit" name="status" value="DITOLAK" class="btn btn-danger btn-block"
                        onclick="return confirm('Kembalikan data ke siswa untuk diperbaiki? Pastikan catatan sudah diisi.');">
                  <?= icon('edit_note') ?> Minta Perbaikan
                </button>
                <?php if (in_array($pend['status_verifikasi'], ['TERVERIFIKASI', 'DITOLAK'], true)): ?>
                  <button type="submit" name="status" value="MENUNGGU_VERIFIKASI" class="btn btn-outline btn-block"
                          onclick="return confirm('Kembalikan status ke &quot;Menunggu Verifikasi&quot;?');">
                    <?= icon('undo') ?> Kembalikan ke Antrean
                  </button>
                <?php endif; ?>
              </div>
            </form>
          </section>

          <section class="card p-5">
            <h2 class="font-bold text-ink-900 text-sm">Ringkasan</h2>
            <dl class="mt-3 space-y-2.5 text-[13px]">
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Kelengkapan data</dt><dd class="font-semibold text-ink-900"><?= $dataLengkap ? 'Lengkap' : count($dataKurang) . ' kurang' ?></dd></div>
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Berkas wajib</dt><dd class="font-semibold text-ink-900"><?= (int) $pend['dok_terisi'] ?>/<?= (int) $pend['dok_wajib'] ?></dd></div>
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Berkas disetujui</dt><dd class="font-semibold text-ink-900"><?= (int) $pend['dok_verifikasi'] ?></dd></div>
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Pembayaran terverifikasi</dt><dd class="font-semibold text-ink-900"><?= rupiah((float) $pend['bayar_verified']) ?></dd></div>
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Bukti menunggu</dt><dd class="font-semibold text-ink-900"><?= (int) $pend['bayar_pending'] ?> baris</dd></div>
              <div class="flex justify-between gap-3"><dt class="text-ink-500">Koreksi aktif</dt><dd class="font-semibold text-ink-900"><?= (int) $pend['koreksi_aktif'] ?></dd></div>
            </dl>
            <?php if (!empty($pend['data_verified_at'])): ?>
              <p class="mt-3 border-t border-line-soft pt-3 text-[11px] text-ink-400">
                Diverifikasi <?= e($pend['data_verifier'] ?: 'petugas') ?> · <?= e(bulan($pend['data_verified_at'])) ?>
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
              <p class="mt-3 text-[12px] text-ink-500">Verifikasi penuh dinonaktifkan sampai butir wajib terisi. Gunakan "Minta Perbaikan" untuk memberi catatan.</p>
            </section>
          <?php endif; ?>

          <!-- Zona retensi: aksi jarang & tidak bisa dibatalkan, jadi ditutup rapi -->
          <section class="card overflow-hidden">
            <details>
              <summary class="flex items-center justify-between gap-3 px-5 py-4 cursor-pointer list-none text-[13px] font-semibold text-ink-700 hover:bg-canvas transition-colors [&::-webkit-details-marker]:hidden">
                <span class="flex items-center gap-2"><?= icon('delete', 'text-[16px]') ?> Retensi berkas pribadi</span>
                <span class="ms text-[16px]" aria-hidden="true">expand_more</span>
              </summary>
              <div class="px-5 pb-5 border-t border-line-soft pt-4 bg-canvas">
                <p class="text-[13px] text-ink-500">
                  Menghapus berkas pribadi <strong>terunggah</strong> (KK, akta, bukti transfer) milik pendaftar ini
                  beserta jejaknya di tabel dokumen. Baris pendaftar &amp; data seleksi tetap ada.
                  Eksekusi tercatat di <span class="font-mono">retensi_log</span> (maks 5×/jam per pendaftar).
                </p>
                <form method="post" action="verifikasi.php" class="mt-3 flex flex-wrap items-end gap-3" autocomplete="off"
                      onsubmit="return confirm('Hapus SEMUA berkas pribadi <?= e($pend['no_pendaftaran']) ?>? Tindakan ini tidak bisa dibatalkan.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="tab" value="<?= e($tab) ?>">
                  <input type="hidden" name="aksi" value="retensi_hapus">
                  <input type="hidden" name="pendaftar_id" value="<?= e($pend['id']) ?>">
                  <label class="block">
                    <span class="text-[13px] font-medium text-ink-700">Ketik <span class="font-mono"><?= e($pend['no_pendaftaran']) ?></span> untuk konfirmasi</span>
                    <input type="text" name="konfirmasi" required placeholder="<?= e($pend['no_pendaftaran']) ?>"
                           class="mt-1.5 rounded-xl border border-line bg-white px-3 py-2 text-sm font-mono w-[220px]">
                  </label>
                  <button type="submit" class="btn btn-danger btn-sm"><?= icon('delete', 'text-[15px]') ?> Hapus berkas pribadi</button>
                </form>
              </div>
            </details>
          </section>
        </div>
      </div>

      <!-- Koreksi milik pendaftar ini -->
      <section class="mt-6 card p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="font-bold text-ink-900">Pengajuan Koreksi Data</h2>
            <p class="mt-1 text-[13px] text-ink-500">
              Siswa hanya dapat mengajukan koreksi setelah data terverifikasi. Menyetujui koreksi <strong>tidak mengubah data otomatis</strong> —
              formulir dibuka kembali agar siswa memperbaiki sendiri.
            </p>
          </div>
          <span class="badge badge-gray"><?= count($koreksiSaya) ?> pengajuan</span>
        </div>

        <div class="mt-4 space-y-3">
          <?php if (!$koreksiSaya): ?>
            <p class="text-sm text-ink-400">Belum ada pengajuan koreksi dari pendaftar ini.</p>
          <?php endif; ?>
          <?php foreach ($koreksiSaya as $k): $kl = koreksi_status_label($k['status']); ?>
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
                <form method="post" action="verifikasi.php" class="mt-3 grid gap-2.5 sm:grid-cols-[180px_1fr_auto] sm:items-end">
                  <?= csrf_field() ?>
                  <input type="hidden" name="tab" value="<?= e($tab) ?>">
                  <input type="hidden" name="aksi" value="koreksi_status">
                  <input type="hidden" name="koreksi_id" value="<?= e($k['id']) ?>">
                  <input type="hidden" name="pendaftar_id" value="<?= e($pend['id']) ?>">
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
      <?php /* ================= ANTREAN ================= */ ?>
      <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <span class="badge badge-blue">Operasional SPMB</span>
            <span class="badge badge-gray"><?= e($PENGATURAN['gelombang'] ?? 'SPMB') ?></span>
          </div>
          <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Verifikasi Pendaftar</h1>
          <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
            Satu tempat untuk memeriksa berkas persyaratan, data calon siswa, dan pengajuan koreksi.
            Klik <strong>Periksa</strong> untuk membuka seluruh data pendaftar dalam satu layar.
          </p>
        </div>
        <a href="<?= url('app/operator/pendaftar.php') ?>" class="btn btn-primary shrink-0 shadow-drop">
          <?= icon('group') ?> Semua Pendaftar
        </a>
      </div>

      <!-- Tab antrean -->
      <div class="mt-6 border-b border-line flex gap-1 overflow-x-auto scroll-thin">
        <?php foreach ($TABS as $k => $label): ?>
          <a href="verifikasi.php?tab=<?= e($k) ?>" class="tab-underline <?= $tab === $k ? 'text-primary border-primary' : '' ?>"
             <?= $tab === $k ? 'data-active="true"' : '' ?>>
            <?= e($label) ?>
            <?php if ($hitung[$k] > 0): ?><span class="badge <?= $k === 'koreksi' ? 'badge-blue' : 'badge-amber' ?> ml-1"><?= (int) $hitung[$k] ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($tab === 'berkas'): ?>
        <section class="mt-5 card overflow-hidden">
          <div class="p-5 sm:p-6 pb-0">
            <h2 class="font-bold text-ink-900">Antrean Berkas</h2>
            <p class="mt-1 text-[13px] text-ink-500">Pendaftar tahap BERKAS_DISUBMIT / BERKAS_REVISI yang perlu diperiksa berkasnya.</p>
          </div>
          <div class="mt-4 overflow-x-auto scroll-thin">
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
                <?php if (!$antreanBerkas): ?>
                  <tr><td class="td text-center text-ink-400" colspan="5">Tidak ada berkas menunggu pemeriksaan.</td></tr>
                <?php endif; ?>
                <?php foreach ($antreanBerkas as $a): ?>
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
                      <a href="verifikasi.php?tab=berkas&amp;id=<?= rawurlencode($a['id']) ?>" class="btn btn-sm btn-primary">
                        <?= icon('search', 'text-[15px]') ?> Periksa
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

      <?php elseif ($tab === 'data'): ?>
        <!-- Filter status -->
        <div class="mt-5 flex flex-wrap items-center gap-2">
          <?php foreach ($FILTER_DATA as $st => $lbl): ?>
            <?php
            $n = $st === 'SEMUA' ? count($semuaData) : (isset($perStatus[$st]) ? $perStatus[$st] : 0);
            $aktif = $sFilter === $st;
            ?>
            <a href="verifikasi.php?tab=data&amp;s=<?= e($st) ?>"
               class="badge <?= $aktif ? 'badge-blue' : 'badge-gray' ?> <?= $st === 'MENUNGGU_VERIFIKASI' && !$aktif ? 'badge-amber' : '' ?>">
              <?= e($lbl) ?> · <?= (int) $n ?>
            </a>
          <?php endforeach; ?>
        </div>

        <section class="mt-3 card overflow-hidden">
          <div class="p-5 sm:p-6 pb-0">
            <h2 class="font-bold text-ink-900">Antrean Verifikasi Data</h2>
            <p class="mt-1 text-[13px] text-ink-500">Periksa data identitas, orang tua/wali, dan alamat. Verifikasi data berbeda dari verifikasi berkas persyaratan.</p>
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
                  <tr><td class="td text-center text-ink-400" colspan="5">Tidak ada pendaftar pada status ini.</td></tr>
                <?php endif; ?>
                <?php foreach ($antreanData as $p): $pv = $labelVer[$p['status_verifikasi']] ?? [$p['status_verifikasi'], 'gray']; ?>
                  <tr class="tr-hover">
                    <td class="td">
                      <p class="font-semibold text-ink-900"><?= e($p['nama_siswa']) ?></p>
                      <p class="font-mono text-[12px] text-ink-500"><?= e($p['no_pendaftaran']) ?></p>
                      <p class="text-[12px] text-ink-400"><?= e($p['asal_sekolah'] ?: '—') ?></p>
                    </td>
                    <td class="td text-[13px]">
                      <?= e($p['no_whatsapp'] ?: '—') ?>
                      <span class="badge badge-<?= e($pv[1]) ?> block mt-1 w-fit"><?= e($pv[0]) ?></span>
                    </td>
                    <td class="td text-[13px] font-semibold"><?= (int) $p['dok_terisi'] ?>/<?= (int) $p['dok_wajib'] ?></td>
                    <td class="td">
                      <?php if ((int) $p['koreksi_aktif'] > 0): ?>
                        <span class="badge badge-amber"><?= (int) $p['koreksi_aktif'] ?> aktif</span>
                      <?php else: ?>
                        <span class="text-[12px] text-ink-400">—</span>
                      <?php endif; ?>
                    </td>
                    <td class="td text-right">
                      <a href="verifikasi.php?tab=data&amp;id=<?= rawurlencode($p['id']) ?>" class="btn btn-sm btn-outline"><?= icon('fact_check', 'text-[15px]') ?> Periksa</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>

      <?php else: ?>
        <section class="mt-5 card overflow-hidden">
          <div class="p-5 sm:p-6 pb-0 flex items-start justify-between gap-3 flex-wrap">
            <div>
              <h2 class="font-bold text-ink-900">Pengajuan Koreksi Data</h2>
              <p class="mt-1 text-[13px] text-ink-500">Pengajuan dari siswa yang datanya sudah terverifikasi. Menyetujui koreksi membuka kembali formulir siswa — data tidak berubah otomatis.</p>
            </div>
            <span class="badge badge-amber"><?= (int) $hitung['koreksi'] ?> menunggu / diproses</span>
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
                <?php if (!$antreanKoreksi): ?>
                  <tr><td class="td text-center text-ink-400" colspan="5">Tidak ada pengajuan koreksi aktif.</td></tr>
                <?php endif; ?>
                <?php foreach ($antreanKoreksi as $k): $kl = koreksi_status_label($k['status']); ?>
                  <tr class="tr-hover align-top">
                    <td class="td">
                      <p class="font-semibold text-ink-900"><?= e($k['nama_siswa']) ?></p>
                      <p class="font-mono text-[12px] text-ink-500"><?= e($k['no_pendaftaran']) ?></p>
                    </td>
                    <td class="td text-[13px]"><?= e(koreksi_bidang()[$k['bidang']] ?? $k['bidang']) ?></td>
                    <td class="td text-[13px] max-w-[320px]"><?= e($k['pesan']) ?></td>
                    <td class="td"><span class="badge badge-<?= e($kl[1]) ?>"><?= e($kl[0]) ?></span></td>
                    <td class="td text-right">
                      <a href="verifikasi.php?tab=koreksi&amp;id=<?= rawurlencode($k['pendaftar_id']) ?>" class="btn btn-sm btn-outline">Tinjau</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>

  </main>
</div>

<!-- Modal: tolak berkas (alasan wajib) -->
<div id="modal-tolak-berkas" class="modal-root fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
    <form method="post" action="verifikasi.php"
          class="animate-pop w-full max-w-lg bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
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
        <p class="hint">Alasan dikirim via WhatsApp ke orang tua &amp; ditampilkan di portal siswa.</p>
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
<?php require __DIR__ . '/../../includes/partials/pratinjau-dokumen.php'; ?>
</body>
</html>
