<?php
require_once __DIR__ . '/../../includes/config.php';

/* Hak akses: siswa (PRD F-04) — hanya melihat datanya sendiri. */
require_role(['siswa']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'] ?? 'Siswa',
    'inisial' => $me['inisial'],
];

$title = 'Portal Saya';
$desc  = 'Biodata pendaftar, status verifikasi, pembayaran, hasil seleksi, dan penempatan kelas.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$siswa = profil_siswa();
$pengumumanAktif = !empty(pengumuman_status()['diumumkan']);
if ($siswa) {
    $pembayaran = pembayaran_siswa($siswa['id']);
    $seleksi    = seleksi_siswa($siswa['id']);
    $penempatan = penempatan_siswa($siswa['id']);
    $dokumen    = dokumen_siswa($siswa['id']);
    $kartuUjian = peserta_kartu_siswa($siswa['id']);

    /* Form biodata terkunci setelah verifikasi — hanya bisa diedit saat DRAFT/MENUNGGU_VERIFIKASI. */
    $biodataBolehEdit = in_array($siswa['status_verifikasi'], ['DRAFT', 'MENUNGGU_VERIFIKASI'], true);

    /* Badge status verifikasi */
    $labelVer = [
        'MENUNGGU_VERIFIKASI' => ['Menunggu Verifikasi', 'amber'],
        'TERVERIFIKASI'       => ['Terverifikasi', 'green'],
        'DITOLAK'             => ['Ditolak', 'red'],
    ];
    $verifikasi = $labelVer[$siswa['status_verifikasi']] ?? [$siswa['status_verifikasi'], 'gray'];

    $labelSelesai = [
        'PROSES'      => ['Dalam Proses', 'amber'],
        'LULUS'       => ['Lulus', 'green'],
        'TIDAK_LULUS' => ['Tidak Lulus', 'red'],
    ];

    $labelAkun = [
        'AKTIF'          => ['Aktif', 'green'],
        'BELUM_DIBUAT'   => ['Belum Dibuat', 'amber'],
        'GAGAL'          => ['Gagal', 'red'],
    ];

    /* Total nominal terverifikasi */
    $totalBayar = 0;
    foreach ($pembayaran as $b) {
        if (in_array($b['status'], ['VERIFIED', 'LUNAS', 'ANGSURAN_1', 'ANGSURAN_2'], true)) {
            $totalBayar += (float) $b['nominal'];
        }
    }
}

/* ==================================================================
 * Proses POST (sebelum output HTML)
 * ================================================================== */
if ($siswa && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        header('Location: ' . url('app/siswa/index.php'));
        exit;
    }
    $aksi = $_POST['aksi'] ?? '';

    /* ------------------------------------------------------------
     * A. Simpan perubahan biodata + toggle luar daerah
     * ------------------------------------------------------------ */
    if ($aksi === 'biodata') {
        if (!$biodataBolehEdit) {
            flash('error', 'Data terkunci — pendaftaran sudah diverifikasi. Hubungi panitia bila ada koreksi.');
            header('Location: ' . url('app/siswa/index.php'));
            exit;
        }
        $nisn    = preg_replace('/\D+/', '', (string) ($_POST['nisn'] ?? ''));
        $jk      = ($_POST['jenis_kelamin'] ?? '') === 'P' ? 'P' : 'L';
        $ttl     = trim((string) ($_POST['tempat_lahir'] ?? ''));
        $tglLhr  = trim((string) ($_POST['tanggal_lahir'] ?? ''));
        $alamat  = trim((string) ($_POST['alamat'] ?? ''));
        $asal    = trim((string) ($_POST['asal_sekolah'] ?? ''));
        $luar    = !empty($_POST['is_luar_daerah']) ? 1 : 0;

        $galatB = [];
        if ($nisn !== '' && strlen($nisn) !== 10) {
            $galatB[] = 'NISN harus 10 digit angka (atau dikosongkan).';
        }
        if ($alamat === '') {
            $galatB[] = 'Alamat wajib diisi.';
        }
        if ($asal === '') {
            $galatB[] = 'Asal sekolah wajib diisi.';
        }
        if ($tglLhr !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglLhr)) {
            $galatB[] = 'Format tanggal lahir tidak valid.';
        }
        if ($galatB) {
            flash('error', implode(' ', $galatB));
        } else {
            flash(
                siswa_update_biodata($siswa['id'], [
                    'nisn' => $nisn, 'jenis_kelamin' => $jk, 'tempat_lahir' => $ttl,
                    'tanggal_lahir' => $tglLhr, 'alamat' => $alamat, 'asal_sekolah' => $asal,
                    'is_luar_daerah' => $luar,
                ]) ? 'sukses' : 'error',
                'Biodata berhasil diperbarui.'
            );
        }
        header('Location: ' . url('app/siswa/index.php'));
        exit;
    }

    /* ------------------------------------------------------------
     * B. Unggah berkas persyaratan (Fase 5: jenis dinamis dari
     *    tabel jenis_dokumen; format & maks KB per jenis)
     * ------------------------------------------------------------ */
    if ($aksi === 'unggah_berkas') {
        $jenis = (string) ($_POST['jenis_dokumen'] ?? '');
        $jList = q_all('SELECT * FROM jenis_dokumen WHERE kode = ? AND aktif = 1', [$jenis]);
        if (!$jList) {
            flash('error', 'Jenis dokumen tidak dikenali / tidak aktif.');
            header('Location: ' . url('app/siswa/index.php'));
            exit;
        }
        $j = $jList[0];
        $izinEkstensi = array_values(array_filter(array_map('trim', explode(',', strtolower($j['format_diizinkan'])))));
        $maksBytes    = (int) $j['maks_kb'] * 1024;
        $izinMime     = [
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',  'pdf'  => 'application/pdf',
        ];

        $berkas = $_FILES['berkas'] ?? null;
        if (!$berkas || $berkas['error'] === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Pilih berkas yang akan diunggah.');
        } elseif ($berkas['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Unggahan gagal (kode ' . (int) $berkas['error'] . ').');
        } else {
            $ekstensi = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
            if (!in_array($ekstensi, $izinEkstensi, true)) {
                flash('error', '"' . $j['nama'] . '" harus berformat: ' . implode(', ', $izinEkstensi) . '.');
            } elseif ($berkas['size'] > $maksBytes) {
                flash('error', 'Ukuran "' . $j['nama'] . '" maksimal ' . (int) $j['maks_kb'] . ' KB.');
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = (string) $finfo->file($berkas['tmp_name']);
                if (!in_array($mime, $izinMime, true)) {
                    flash('error', 'Isi berkas bukan gambar/PDF yang sah.');
                } else {
                    $folder = dirname(__DIR__, 2) . '/storage/dokumen';
                    if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
                        flash('error', 'Folder unggahan tidak dapat dibuat.');
                    } else {
                        $namaFile = 'dok-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ekstensi;
                        if (!move_uploaded_file($berkas['tmp_name'], $folder . '/' . $namaFile)) {
                            flash('error', 'Berkas gagal disimpan ke server.');
                        } else {
                            $rel = 'storage/dokumen/' . $namaFile;
                            $kb  = (int) round($berkas['size'] / 1024);
                            $hasilUp = dokumen_upload($siswa['id'], $j['kode'], $rel, $kb);
                            if (!empty($hasilUp['ok'])) {
                                flash('sukses', 'Berkas "' . $j['nama'] . '" berhasil diunggah.');
                            } else {
                                @unlink($folder . '/' . $namaFile);
                                flash('error', $hasilUp['pesan'] ?? 'Gagal menyimpan berkas.');
                            }
                        }
                    }
                }
            }
        }
        header('Location: ' . url('app/siswa/index.php'));
        exit;
    }

    /* ------------------------------------------------------------
     * B2. Kirim untuk verifikasi (Fase 5) — semua berkas wajib lengkap
     * ------------------------------------------------------------ */
    if ($aksi === 'submit_berkas') {
        $hasilSubmit = dokumen_submit($siswa['id']);
        if (!empty($hasilSubmit['ok'])) {
            flash('sukses', 'Berkas dikirim untuk verifikasi panitia. Anda akan diberi tahu hasil pemeriksaan.');
        } else {
            $pesan = $hasilSubmit['pesan'] ?? 'Gagal mengirim berkas.';
            if (!empty($hasilSubmit['kurang'])) {
                $pesan .= ' Kurang: ' . implode(', ', $hasilSubmit['kurang']) . '.';
            }
            flash('error', $pesan);
        }
        header('Location: ' . url('app/siswa/index.php'));
        exit;
    }

    /* ------------------------------------------------------------
     * C. Unggah bukti transfer (masuk antrean verifikasi bendahara)
     * ------------------------------------------------------------ */
    if ($aksi === 'unggah_bukti') {
        $jenisBayar = ($_POST['jenis_pembayaran'] ?? '') === 'UANG_MASUK' ? 'UANG_MASUK' : 'FORMULIR';
        $nominal    = (float) ($_POST['nominal'] ?? 0);
        $idemKey    = idem_key_valid($_POST['idem_key'] ?? '');
        $berkas = $_FILES['bukti'] ?? null;
        if ($idemKey === null) {
            flash('error', 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        } elseif ($nominal <= 0) {
            flash('error', 'Nominal transfer harus lebih besar dari 0.');
        } elseif (!$berkas || $berkas['error'] === UPLOAD_ERR_NO_FILE) {
            flash('error', 'Pilih foto bukti transfer.');
        } elseif ($berkas['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Unggahan gagal (kode ' . (int) $berkas['error'] . ').');
        } else {
            $ekstensi = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
            $izinEkstensi = ['jpg', 'jpeg', 'png'];
            $izinMime     = ['image/jpeg', 'image/png'];
            if (!in_array($ekstensi, $izinEkstensi, true)) {
                flash('error', 'Bukti transfer harus JPG atau PNG.');
            } elseif ($berkas['size'] > 2 * 1024 * 1024) {
                flash('error', 'Ukuran bukti transfer maksimal 2 MB.');
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = (string) $finfo->file($berkas['tmp_name']);
                if (!in_array($mime, $izinMime, true)) {
                    flash('error', 'Isi berkas bukan gambar yang sah.');
                } else {
                    $folder = dirname(__DIR__, 2) . '/storage/dokumen';
                    if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
                        flash('error', 'Folder unggahan tidak dapat dibuat.');
                    } else {
                        $namaFile = 'bukti-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ekstensi;
                        if (!move_uploaded_file($berkas['tmp_name'], $folder . '/' . $namaFile)) {
                            flash('error', 'Berkas gagal disimpan ke server.');
                        } else {
                            $rel = 'storage/dokumen/' . $namaFile;
                            $hasilByr = pembayaran_unggah_bukti($siswa['id'], $jenisBayar, $nominal, $rel, $idemKey);
                            if (!empty($hasilByr['reused'])) {
                                @unlink($folder . '/' . $namaFile);
                                flash('sukses', 'Bukti transfer sudah terunggah sebelumnya — tidak diproses dua kali.');
                            } else {
                                flash($hasilByr['ok'] ? 'sukses' : 'error',
                                    $hasilByr['ok'] ? 'Bukti transfer terunggah & menunggu verifikasi bendahara.'
                                                     : ($hasilByr['pesan'] ?? 'Gagal menyimpan bukti transfer.'));
                            }
                        }
                    }
                }
            }
        }
        header('Location: ' . url('app/siswa/index.php'));
        exit;
    }
}

require_once __DIR__ . '/../../includes/partials/head.php';

$flashAmbil = flash();
$activeMenu = 'siswa';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik', 'Portal Saya'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Akun Siswa</span>
          <?php if ($siswa): ?>
            <span class="badge badge-<?= e($verifikasi[1]) ?>"><?= e($verifikasi[0]) ?></span>
            <?php $akun = $labelAkun[$siswa['status_akun']] ?? [$siswa['status_akun'], 'gray']; ?>
            <span class="badge badge-<?= e($akun[1]) ?>">Akun <?= e($akun[0]) ?></span>
          <?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">
          Halo, <?= e($siswa['nama_siswa'] ?? $me['nama']) ?>
        </h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Berikut ringkasan pendaftaran Anda pada <?= e($PENGATURAN['gelombang']) ?>
          <?= e($PENGATURAN['tahun_ajaran']) ?>.
        </p>
      </div>
      <div class="flex items-center gap-2.5 shrink-0">
        <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline" target="_blank" rel="noopener">
          <?= icon('language') ?> Info SPMB
        </a>
      </div>
    </div>

    <?php if (!$siswa): ?>
      <div class="mt-6 rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] p-5 text-sm text-[#92400E]">
        <p class="font-semibold">Data pendaftaran belum ditemukan untuk akun ini.</p>
        <p class="mt-1">Silakan hubungi panitia SPMB untuk proses verifikasi akun Anda.</p>
      </div>
    <?php else: ?>

      <!-- Biodata & status -->
      <section class="mt-6 card p-5 sm:p-6">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="font-bold text-ink-900">Biodata Pendaftar</h2>
            <p class="mt-1 text-[13px] text-ink-500">No. pendaftaran <span class="font-mono font-semibold text-ink-700"><?= e($siswa['no_pendaftaran']) ?></span></p>
          </div>
          <?php if ($biodataBolehEdit): ?>
            <span class="badge badge-blue">Dapat Diubah</span>
          <?php else: ?>
            <span class="badge badge-gray">Terkunci (sudah diverifikasi)</span>
          <?php endif; ?>
        </div>

        <?php if ($biodataBolehEdit): ?>
          <form method="post" action="<?= url('app/siswa/index.php') ?>" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="biodata">
            <div class="sm:col-span-2">
              <label class="label" for="nama_siswa">Nama Lengkap</label>
              <input id="nama_siswa" class="input bg-canvas" value="<?= e($siswa['nama_siswa']) ?>" disabled>
              <p class="hint">Nama sesuai akta kelahiran — hubungi panitia untuk koreksi nama.</p>
            </div>
            <div>
              <label class="label" for="nisn">NISN (10 digit)</label>
              <input id="nisn" name="nisn" class="input font-mono" inputmode="numeric" maxlength="10" value="<?= e($siswa['nisn'] ?? '') ?>" placeholder="0129482099">
            </div>
            <div>
              <label class="label" for="jenis_kelamin">Jenis Kelamin <span class="text-red-600">*</span></label>
              <select id="jenis_kelamin" name="jenis_kelamin" class="select">
                <option value="L" <?= ($siswa['jenis_kelamin'] ?? 'L') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= ($siswa['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
              </select>
            </div>
            <div>
              <label class="label" for="tempat_lahir">Tempat Lahir</label>
              <input id="tempat_lahir" name="tempat_lahir" class="input" value="<?= e($siswa['tempat_lahir'] ?? '') ?>" placeholder="Tangerang">
            </div>
            <div>
              <label class="label" for="tanggal_lahir">Tanggal Lahir</label>
              <input id="tanggal_lahir" name="tanggal_lahir" type="date" class="input" value="<?= e($siswa['tanggal_lahir'] ?? '') ?>">
            </div>
            <div class="sm:col-span-2">
              <label class="label" for="alamat">Alamat Lengkap <span class="text-red-600">*</span></label>
              <textarea id="alamat" name="alamat" class="textarea" rows="2" required><?= e($siswa['alamat'] ?? '') ?></textarea>
            </div>
            <div>
              <label class="label" for="asal_sekolah">Asal Sekolah <span class="text-red-600">*</span></label>
              <input id="asal_sekolah" name="asal_sekolah" class="input" value="<?= e($siswa['asal_sekolah'] ?? '') ?>" required>
            </div>
            <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-canvas border border-line px-4 py-3">
              <div>
                <p class="text-sm font-semibold text-ink-900">Domisili Luar Daerah</p>
                <p class="text-[13px] text-ink-500">Aktifkan bila domisili di luar Kota Tangerang (berdampak pada penempatan sesuai ketentuan).</p>
              </div>
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_luar_daerah" value="1" <?= !empty($siswa['is_luar_daerah']) ? 'checked' : '' ?> class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
                <span class="text-sm font-semibold">Luar Daerah</span>
              </label>
            </div>
            <div class="sm:col-span-2 lg:col-span-3 flex justify-end gap-3">
              <button type="submit" class="btn btn-primary shadow-drop"><?= icon('save') ?> Simpan Perubahan</button>
            </div>
          </form>
        <?php else: ?>
          <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
              <p class="label">Nama Lengkap</p>
              <p class="text-sm font-semibold text-ink-900"><?= e($siswa['nama_siswa']) ?></p>
            </div>
            <div>
              <p class="label">NISN</p>
              <p class="text-sm font-mono text-ink-900"><?= e($siswa['nisn'] ?: '—') ?></p>
            </div>
            <div>
              <p class="label">Jenis Kelamin</p>
              <p class="text-sm text-ink-900"><?= ($siswa['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki' ?></p>
            </div>
            <div class="sm:col-span-2">
              <p class="label">Alamat</p>
              <p class="text-sm text-ink-900"><?= e($siswa['alamat'] ?: '—') ?></p>
            </div>
            <div>
              <p class="label">Asal Sekolah</p>
              <p class="text-sm text-ink-900"><?= e($siswa['asal_sekolah'] ?: '—') ?></p>
            </div>
            <div>
              <p class="label">Jalur Pendaftaran</p>
              <p class="text-sm text-ink-900"><?= e($siswa['jalur'] ?: 'Reguler') ?></p>
            </div>
            <div>
              <p class="label">Luar Daerah</p>
              <p class="text-sm text-ink-900"><?= !empty($siswa['is_luar_daerah']) ? 'Ya' : 'Tidak' ?></p>
            </div>
            <div>
              <p class="label">Status Verifikasi</p>
              <p><?= badge($verifikasi[0], $verifikasi[1]) ?></p>
            </div>
            <div>
              <p class="label">Status Seleksi</p>
              <?php $selesai = $labelSelesai[$siswa['status_seleksi']] ?? [$siswa['status_seleksi'], 'gray']; ?>
              <p><?= badge($selesai[0], $selesai[1]) ?></p>
            </div>
            <?php if ($siswa['nama_orang_tua']): ?>
              <div class="sm:col-span-2 lg:col-span-3 border-t border-line-soft pt-4 mt-1">
                <p class="label">Orang Tua / Wali</p>
                <p class="text-sm text-ink-900">
                  <?= e($siswa['nama_orang_tua']) ?>
                  <?php if ($siswa['no_whatsapp']): ?>
                    <span class="text-ink-400">· WA <?= e($siswa['no_whatsapp']) ?></span>
                  <?php endif; ?>
                </p>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Unggah berkas persyaratan (Fase 5: daftar dinamis dari jenis_dokumen) -->
      <section class="mt-6 card p-5 sm:p-6">
        <?php
        /* Kunci berkas: tahap terkunci → semua unggah dinonaktifkan;
         * BERKAS_REVISI → hanya berkas berstatus Ditolak yang dibuka. */
        $tahapSekarang = $siswa['tahap'] ?? '';
        $berkasTerkunci = in_array($tahapSekarang, [
            'BERKAS_DISUBMIT', 'BERKAS_TERVERIFIKASI', 'JADWAL_DITETAPKAN',
            'NILAI_TERINPUT', 'DIUMUMKAN', 'AKUN_TERKIRIM',
        ], true);
        $modeRevisi = $tahapSekarang === 'BERKAS_REVISI';
        $bolehSubmit = !$berkasTerkunci
            && in_array($tahapSekarang, ['MELENGKAPI_BERKAS', 'BERKAS_REVISI'], true);
        $jmlWajib = 0; $jmlWajibTerisi = 0;
        foreach ($dokumen as $dW) { if ($dW['wajib']) { $jmlWajib++; if (!empty($dW['file_url'])) { $jmlWajibTerisi++; } } }
        $submitSiap = $bolehSubmit && $jmlWajib > 0 && $jmlWajibTerisi === $jmlWajib;
        ?>
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="font-bold text-ink-900">Berkas Persyaratan</h2>
            <p class="mt-1 text-[13px] text-ink-500">
              Daftar berkas sesuai ketentuan panitia. Batas ukuran & format mengikuti jenis masing-masing.
            </p>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <?php if ($berkasTerkunci): ?>
              <span class="badge badge-blue">Terkunci</span>
            <?php elseif ($modeRevisi): ?>
              <span class="badge badge-red">Revisi — unggah ulang berkas yang ditolak</span>
            <?php else: ?>
              <span class="badge badge-gray"><?= count($dokumen) ?> jenis</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-4 grid gap-3 md:grid-cols-2">
          <?php
          $toneVerDok  = ['PENDING' => 'amber', 'VERIFIED' => 'green', 'REJECTED' => 'red'];
          $labelVerDok = ['PENDING' => 'Menunggu', 'VERIFIED' => 'Terverifikasi', 'REJECTED' => 'Ditolak'];
          foreach ($dokumen as $d):
            $bolehUnggah = !$berkasTerkunci
                && (!$modeRevisi || ($d['status'] === 'REJECTED'));
          ?>
            <div class="rounded-2xl border <?= ($d['status'] === 'REJECTED') ? 'border-red-200 bg-red-50/40' : 'border-line' ?> p-4">
              <div class="flex items-start justify-between gap-2">
                <p class="font-semibold text-sm text-ink-900">
                  <?= e($d['nama']) ?>
                  <?php if ($d['wajib']): ?><span class="text-red-600" title="Wajib">*</span><?php endif; ?>
                </p>
                <?php if ($d['file_url']): ?>
                  <?= badge($labelVerDok[$d['status']] ?? $d['status'], $toneVerDok[$d['status']] ?? 'gray') ?>
                <?php else: ?>
                  <span class="badge badge-gray">Kosong</span>
                <?php endif; ?>
              </div>
              <?php if ($d['file_url']): ?>
                <p class="mt-1 text-[12px] text-ink-500">
                  <a class="underline text-primary" href="<?= url('app/api/dokumen.php?f=' . rawurlencode(basename($d['file_url']))) ?>" target="_blank" rel="noopener">Lihat berkas</a>
                  · <?= (int) $d['ukuran_kb'] ?> KB · <?= e(bulan($d['uploaded_at'])) ?>
                </p>
              <?php else: ?>
                <p class="mt-1 text-[12px] text-ink-400">Format: <?= e(strtoupper(str_replace(',', ' / ', $d['format']))) ?> · maks <?= (int) $d['maks_kb'] ?> KB<?= $d['wajib'] ? ' · wajib' : ' · opsional' ?></p>
              <?php endif; ?>

              <?php if ($d['status'] === 'REJECTED' && $d['catatan']): ?>
                <div class="mt-2 rounded-lg bg-white border border-red-200 px-3 py-2">
                  <p class="text-[12px] font-semibold text-[#991B1B]">Catatan panitia:</p>
                  <p class="text-[13px] text-ink-700"><?= e($d['catatan']) ?></p>
                  <?php if ($d['verified_at']): ?>
                    <p class="text-[11px] text-ink-400 mt-1">Diputuskan: <?= e(bulan($d['verified_at'])) ?>, <?= e(date('H:i', strtotime($d['verified_at']))) ?> WIB</p>
                  <?php endif; ?>
                </div>
              <?php elseif ($d['status'] === 'VERIFIED' && $d['verified_at']): ?>
                <p class="mt-1 text-[11px] text-[#065F46]">Terverifikasi: <?= e(bulan($d['verified_at'])) ?></p>
              <?php endif; ?>

              <?php if ($bolehUnggah): ?>
                <form method="post" action="<?= url('app/siswa/index.php') ?>" enctype="multipart/form-data" class="mt-3 flex items-end gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="unggah_berkas">
                  <input type="hidden" name="jenis_dokumen" value="<?= e($d['kode']) ?>">
                  <div class="flex-1 min-w-0">
                    <input type="file" name="berkas" accept="<?= e('.' . str_replace(',', ',.', $d['format'])) ?>" class="input h-9 text-[13px] file:mr-3" required>
                  </div>
                  <button type="submit" class="btn btn-sm btn-outline shrink-0"><?= icon('upload', 'text-[15px]') ?> <?= $d['file_url'] ? 'Ganti' : 'Unggah' ?></button>
                </form>
              <?php elseif ($berkasTerkunci): ?>
                <p class="mt-3 text-[12px] text-ink-500 flex items-center gap-1"><?= icon('lock', 'text-[15px]') ?> Berkas terkunci — menunggu / hasil pemeriksaan panitia.</p>
              <?php else: ?>
                <p class="mt-3 text-[12px] text-ink-500 flex items-center gap-1"><?= icon('done', 'text-[15px]') ?> Berkas lolos — tidak perlu diunggah ulang.</p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($bolehSubmit): ?>
          <div class="mt-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-line-soft pt-4">
            <p class="text-[13px] text-ink-500">
              <?php if ($modeRevisi): ?>
                Setelah unggah ulang seluruh berkas yang ditolak, kirim kembali untuk pemeriksaan.
              <?php else: ?>
                Wajib: <strong class="text-ink-900"><?= $jmlWajibTerisi ?>/<?= $jmlWajib ?></strong> berkas wajib sudah terunggah.
              <?php endif; ?>
            </p>
            <?php if ($submitSiap): ?>
              <form method="post" action="<?= url('app/siswa/index.php') ?>" onsubmit="return confirm('Kirim seluruh berkas untuk verifikasi panitia? Berkas akan terkunci sementara.');">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="submit_berkas">
                <button type="submit" class="btn btn-primary shadow-drop"><?= icon('send') ?> Kirim untuk Verifikasi</button>
              </form>
            <?php else: ?>
              <button class="btn btn-primary opacity-50 pointer-events-none" disabled><?= icon('send') ?> Kirim untuk Verifikasi</button>
              <p class="text-[12px] text-amber-700 sm:text-right">Lengkapi seluruh berkas wajib terlebih dahulu.</p>
            <?php endif; ?>
          </div>
        <?php elseif ($berkasTerkunci): ?>
          <p class="mt-5 text-[13px] text-ink-500 border-t border-line-soft pt-4">
            <?php if ($tahapSekarang === 'BERKAS_DISUBMIT'): ?>
              Berkas sedang diperiksa panitia. Unggahan dinonaktifkan sampai ada hasil pemeriksaan.
            <?php else: ?>
              Pemeriksaan berkas selesai / proses selanjutnya berjalan — berkas tidak dapat diubah.
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </section>

      <div class="mt-6 grid gap-6 lg:grid-cols-3">

        <!-- Pembayaran -->
        <section class="card lg:col-span-2 p-5 sm:p-6">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="font-bold text-ink-900">Pembayaran</h2>
              <p class="mt-1 text-[13px] text-ink-500">Total terverifikasi: <strong class="text-ink-900"><?= rupiah($totalBayar) ?></strong></p>
            </div>
          </div>

          <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[480px]">
              <thead class="thead">
                <tr>
                  <th class="th">Jenis</th>
                  <th class="th text-right">Nominal</th>
                  <th class="th">Status</th>
                  <th class="th">Keterangan</th>
                  <th class="th text-right">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pembayaran as $b): ?>
                  <?php $lbl = $labelSelesai[$b['status']] ?? null; ?>
                  <?php $toneMap = ['PENDING' => 'amber', 'VERIFIED' => 'green', 'REJECTED' => 'red', 'LUNAS' => 'green', 'ANGSURAN_1' => 'blue', 'ANGSURAN_2' => 'blue']; ?>
                  <?php $statusLabel = ['PENDING' => 'Menunggu', 'VERIFIED' => 'Terverifikasi', 'REJECTED' => 'Ditolak', 'LUNAS' => 'Lunas', 'ANGSURAN_1' => 'Angsuran 1', 'ANGSURAN_2' => 'Angsuran 2'][$b['status']] ?? $b['status']; ?>
                  <tr class="tr-hover">
                    <td class="td font-semibold"><?= e($b['jenis_pembayaran'] === 'FORMULIR' ? 'Biaya Formulir' : $b['jenis_pembayaran']) ?></td>
                    <td class="td text-right font-semibold"><?= rupiah($b['nominal']) ?></td>
                    <td class="td"><?= badge($statusLabel, $toneMap[$b['status']] ?? 'gray') ?></td>
                    <td class="td text-[13px] text-ink-500"><?= e($b['keterangan'] ?: '—') ?></td>
                    <td class="td text-right whitespace-nowrap">
                      <?php if (in_array($b['status'], ['PENDING', 'REJECTED'], true)): ?>
                        <?php if (gateway_tersedia()): ?>
                          <button type="button" class="btn btn-sm btn-primary mr-1"
                                  data-bayar-online
                                  data-pendaftar="<?= e($siswa['id']) ?>"
                                  data-jenis="<?= $b['jenis_pembayaran'] === 'FORMULIR' ? 'FORMULIR' : 'UANG_MASUK' ?>">
                            <?= icon('payment', 'text-[15px]') ?> Bayar Online
                          </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-outline" data-modal-open="modal-bukti"
                                data-jenis="<?= $b['jenis_pembayaran'] === 'FORMULIR' ? 'FORMULIR' : 'UANG_MASUK' ?>"
                                data-nominal="<?= (float) $b['nominal'] ?>">
                          <?= icon('upload', 'text-[15px]') ?> Unggah Bukti
                        </button>
                      <?php else: ?>
                        <span class="text-[12px] text-ink-400">—</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$pembayaran): ?>
                  <tr><td colspan="5" class="td text-center text-ink-400">Belum ada data pembayaran.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </section>

        <!-- Kartu Peserta Ujian (Fase 6) -->
        <?php if ($kartuUjian): ?>
          <section class="card p-5 sm:p-6" id="kartu-peserta">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <div>
                <h2 class="font-bold text-ink-900">Kartu Peserta Ujian</h2>
                <p class="mt-1 text-[13px] text-ink-500">Cetak &amp; bawa saat hari ujian.</p>
              </div>
              <button type="button" class="btn btn-primary no-print shadow-drop" onclick="window.print()">
                <?= icon('print') ?> Cetak Kartu
              </button>
            </div>

            <div class="mt-4 rounded-2xl border-2 border-dashed border-primary/40 p-5 sm:p-6 bg-white">
              <div class="flex items-center justify-between gap-3 border-b border-line pb-3">
                <div class="flex items-center gap-3">
                  <img src="<?= e(asset($PENGATURAN['logo_url'] ?? 'assets/img/logo.svg')) ?>" alt="Logo" class="w-12 h-12">
                  <div>
                    <p class="font-bold text-ink-900 leading-tight"><?= e($PENGATURAN['nama_sekolah'] ?? APP_SHORT) ?></p>
                    <p class="text-[12px] text-ink-500">Kartu Peserta SPMB</p>
                  </div>
                </div>
                <p class="font-mono text-sm font-bold text-primary">No. Pendaftaran: <?= e($siswa['no_pendaftaran']) ?></p>
              </div>

              <div class="mt-4 flex flex-col sm:flex-row sm:items-end gap-4">
                <div class="flex-1">
                  <p class="text-[12px] text-ink-500">Nama Peserta</p>
                  <p class="text-lg font-bold text-ink-900"><?= e($siswa['nama_siswa']) ?></p>
                  <p class="text-[13px] text-ink-500 mt-1">Asal Sekolah: <?= e($siswa['asal_sekolah']) ?></p>
                </div>
                <div class="rounded-xl bg-primary-light border border-primary/20 px-4 py-3 text-center">
                  <p class="text-[11px] text-ink-500 uppercase tracking-wide">Nomor Peserta</p>
                  <p class="text-2xl font-mono font-extrabold text-primary"><?= e($kartuUjian['nomor_peserta']) ?></p>
                </div>
              </div>

              <dl class="mt-4 grid gap-x-6 gap-y-2 sm:grid-cols-2 text-sm border-t border-line-soft pt-4">
                <div class="flex justify-between gap-3">
                  <dt class="text-ink-500">Kegiatan</dt>
                  <dd class="font-semibold text-ink-900 text-right"><?= e($kartuUjian['kode']) ?> — <?= e($kartuUjian['nama_kegiatan']) ?></dd>
                </div>
                <div class="flex justify-between gap-3">
                  <dt class="text-ink-500">Tanggal</dt>
                  <dd class="font-semibold text-ink-900"><?= e(bulan($kartuUjian['tanggal'])) ?></dd>
                </div>
                <div class="flex justify-between gap-3">
                  <dt class="text-ink-500">Jam</dt>
                  <dd class="font-semibold text-ink-900"><?= e(substr($kartuUjian['jam_mulai'], 0, 5)) ?>–<?= e(substr($kartuUjian['jam_selesai'], 0, 5)) ?> WIB</dd>
                </div>
                <div class="flex justify-between gap-3">
                  <dt class="text-ink-500">Lokasi / Ruangan</dt>
                  <dd class="font-semibold text-ink-900"><?= e($kartuUjian['ruangan'] ?: ($kartuUjian['lokasi'] ?: 'Sekolah')) ?></dd>
                </div>
              </dl>

              <p class="mt-4 text-[12px] text-ink-500 border-t border-line-soft pt-3">
                Mohon hadir 15 menit lebih awal. Bawa kartu ini &amp; alat tulis. Simpan kartu sampai proses seleksi selesai.
              </p>
            </div>
          </section>
        <?php endif; ?>

        <!-- Seleksi & Penempatan -->
        <div class="space-y-6">
          <?php if (!$pengumumanAktif): ?>
            <section class="card p-5 sm:p-6">
              <h2 class="font-bold text-ink-900">Hasil Seleksi</h2>
              <p class="mt-4 text-sm text-ink-400">Hasil seleksi akan diumumkan oleh panitia. Mohon tunggu informasi resmi.</p>
            </section>
          <?php else: ?>
          <section class="card p-5 sm:p-6">
            <h2 class="font-bold text-ink-900">Hasil Seleksi</h2>
            <?php if ($seleksi): ?>
              <ul class="mt-4 space-y-3">
                <li class="flex items-center justify-between gap-3">
                  <span class="text-sm text-ink-500">Nilai Akhir</span>
                  <span class="font-bold text-ink-900"><?= e(number_format((float) $seleksi['nilai_akhir'], 1)) ?></span>
                </li>
                <?php if ($seleksi['nilai_cbt'] !== null): ?>
                  <li class="flex items-center justify-between gap-3">
                    <span class="text-sm text-ink-500">CBT</span>
                    <span class="font-semibold"><?= e(number_format((float) $seleksi['nilai_cbt'], 1)) ?></span>
                  </li>
                <?php endif; ?>
                <?php if ($seleksi['nilai_btq'] !== null): ?>
                  <li class="flex items-center justify-between gap-3">
                    <span class="text-sm text-ink-500">BTQ</span>
                    <span class="font-semibold"><?= e(number_format((float) $seleksi['nilai_btq'], 1)) ?></span>
                  </li>
                <?php endif; ?>
                <?php if ($seleksi['nilai_wawancara'] !== null): ?>
                  <li class="flex items-center justify-between gap-3">
                    <span class="text-sm text-ink-500">Wawancara</span>
                    <span class="font-semibold"><?= e(number_format((float) $seleksi['nilai_wawancara'], 1)) ?></span>
                  </li>
                <?php endif; ?>
                <li class="pt-2 border-t border-line-soft flex items-center justify-between gap-3">
                  <span class="text-sm text-ink-500">Status</span>
                  <?php $sSel = $labelSelesai[$seleksi['hasil']] ?? [$seleksi['hasil'], 'gray']; ?>
                  <?= badge($sSel[0], $sSel[1]) ?>
                </li>
                <?php if ($seleksi['nama_kegiatan']): ?>
                  <li class="pt-2 border-t border-line-soft">
                    <p class="text-[13px] text-ink-500">Tes terkait</p>
                    <p class="text-sm font-semibold text-ink-900 mt-1"><?= e($seleksi['nama_kegiatan']) ?></p>
                    <p class="text-[13px] text-ink-400"><?= e(bulan($seleksi['tanggal'])) ?> · <?= e(substr($seleksi['jam_mulai'],0,5)) ?>–<?= e(substr($seleksi['jam_selesai'],0,5)) ?></p>
                  </li>
                <?php endif; ?>
              </ul>
            <?php else: ?>
              <p class="mt-4 text-sm text-ink-400">Hasil seleksi belum diterbitkan.</p>
            <?php endif; ?>
          </section>

          <section class="card p-5 sm:p-6">
            <h2 class="font-bold text-ink-900">Penempatan Kelas</h2>
            <?php if ($penempatan): ?>
              <div class="mt-4 rounded-xl bg-primary-light border border-primary/20 p-4">
                <p class="text-sm text-primary font-semibold">Selamat! Anda ditempatkan di</p>
                <p class="mt-1 text-xl font-extrabold text-ink-900"><?= e($penempatan['kode']) ?> — <?= e($penempatan['nama_kelas']) ?></p>
                <p class="mt-1 text-[13px] text-ink-500">Wali kelas: <?= e($penempatan['wali_kelas']) ?></p>
                <?php if ($penempatan['catatan']): ?>
                  <p class="mt-2 text-[13px] text-ink-500 italic"><?= e($penempatan['catatan']) ?></p>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <p class="mt-4 text-sm text-ink-400">Penempatan kelas belum ditentukan.</p>
            <?php endif; ?>
          </section>
          <?php endif; ?>
        </div>
      </div>

    <?php endif; ?>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>

<!-- Modal unggah bukti transfer -->
<div id="modal-bukti" class="fixed inset-0 z-[60] hidden">
  <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-[2px]" data-modal-close></div>
  <div class="relative mx-auto mt-[10vh] w-[min(440px,92vw)] card p-5 sm:p-6 shadow-modal animate-pop">
    <div class="flex items-start justify-between gap-3">
      <h3 class="font-bold text-ink-900">Unggah Bukti Transfer</h3>
      <button class="icon-box border border-line text-ink-500" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
    </div>
    <form method="post" action="<?= url('app/siswa/index.php') ?>" enctype="multipart/form-data" class="mt-4 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="unggah_bukti">
      <input type="hidden" name="idem_key" id="idem-key-unggah" value="">
      <div>
        <label class="label" for="m-jenis">Jenis Pembayaran</label>
        <select id="m-jenis" name="jenis_pembayaran" class="select">
          <option value="FORMULIR">Biaya Formulir</option>
          <option value="UANG_MASUK">Uang Masuk</option>
        </select>
      </div>
      <div>
        <label class="label" for="m-nominal">Nominal Transfer (Rp) <span class="text-red-600">*</span></label>
        <input id="m-nominal" name="nominal" type="number" min="1" step="1000" class="input" value="<?= (float) ($PENGATURAN['biaya_formulir'] ?? 200000) ?>" required>
        <p class="hint">Transfer ke <?= e($PENGATURAN['nama_bank']) ?> no. <?= e($PENGATURAN['no_rekening']) ?> a.n. <?= e($PENGATURAN['atas_nama_rekening']) ?></p>
      </div>
      <div>
        <label class="label" for="m-bukti">Foto / Scan Bukti (JPG/PNG, maks 2 MB) <span class="text-red-600">*</span></label>
        <input id="m-bukti" name="bukti" type="file" accept=".jpg,.jpeg,.png" class="input" required>
      </div>
      <div class="flex justify-end gap-3 pt-1">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary shadow-drop"><?= icon('upload') ?> Unggah</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php if (gateway_tersedia()): ?>
<script src="<?= e(midtrans_snap_base()) ?>"></script>
<?php endif; ?>
<script>
  window.APP_FLASH = <?= json_encode($flashAmbil ?? null, JSON_UNESCAPED_UNICODE) ?>;

  /* Idempotency key — satu per "niat" bayar/jenis; retry memakai key yang sama
   * sehingga request berulang tidak membuat transaksi / baris ganda. */
  function idemBaru() {
    if (window.crypto && crypto.randomUUID) {
      return crypto.randomUUID().replace(/-/g, '');
    }
    var s = '', h = '0123456789abcdef';
    for (var i = 0; i < 32; i++) s += h[Math.floor(Math.random() * 16)];
    return s;
  }
  var idemKeys = {};
  function idemUntuk(jenis) {
    if (!idemKeys[jenis]) idemKeys[jenis] = idemBaru();
    return idemKeys[jenis];
  }
  /* Setelah pembayaran sukses / metode berubah, buat key baru untuk niat berikutnya. */
  function idemReset(jenis) { delete idemKeys[jenis]; }

  /* Modal unggah bukti — key baru tiap modal dibuka (niat unggah berbeda). */
  document.querySelectorAll('[data-modal-open="modal-bukti"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var j = document.getElementById('m-jenis');
      var n = document.getElementById('m-nominal');
      if (btn.dataset.jenis) j.value = btn.dataset.jenis;
      if (btn.dataset.nominal) n.value = btn.dataset.nominal;
      document.getElementById('idem-key-unggah').value = idemBaru();
    });
  });

  /* Tombol Bayar Online — ambil Snap Token lalu buka popup */
  var CSRF_TOKEN = <?= json_encode($_SESSION['csrf'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
  document.querySelectorAll('[data-bayar-online]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      var asli = btn.innerHTML;
      btn.innerHTML = 'Memproses…';
      var jenis = btn.dataset.jenis || 'FORMULIR';
      var key = idemUntuk(jenis);

      var fd = new FormData();
      fd.append('_csrf', CSRF_TOKEN);
      fd.append('pendaftar_id', btn.dataset.pendaftar || '');
      fd.append('jenis', jenis);
      fd.append('idem_key', key);

      fetch('<?= url('app/api/snap-token.php') ?>', { method: 'POST', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          btn.disabled = false;
          btn.innerHTML = asli;
          if (!d.ok) {
            showToast(d.pesan || 'Gagal membuat transaksi.', 'error');
            return;
          }
          if (typeof snap === 'undefined') {
            showToast('Snap.js belum termuat. Muat ulang halaman.', 'error');
            return;
          }
          snap.pay(d.snap_token, {
            onSuccess: function () {
              idemReset(jenis);
              showToast('Pembayaran berhasil! Memuat ulang…', 'sukses');
              setTimeout(function () { location.reload(); }, 1500);
            },
            onPending: function () {
              idemReset(jenis);
              showToast('Menunggu pembayaran Anda. Status akan diperbarui otomatis.', 'info');
              setTimeout(function () { location.reload(); }, 3000);
            },
            onError: function () {
              showToast('Pembayaran gagal atau dibatalkan.', 'error');
            },
            onClose: function () {
              showToast('Popup ditutup. Anda bisa mencoba lagi kapan saja.', 'info');
            }
          });
        })
        .catch(function () {
          btn.disabled = false;
          btn.innerHTML = asli;
          showToast('Koneksi gagal. Coba lagi.', 'error');
        });
    });
  });
</script>
<style>
  /* Cetak kartu peserta (Fase 6): hanya #kartu-peserta yang dicetak. */
  @media print {
    body { background: #fff !important; }
    body * { visibility: hidden !important; }
    #kartu-peserta, #kartu-peserta * { visibility: visible !important; }
    #kartu-peserta {
      position: absolute !important;
      left: 0 !important; top: 0 !important;
      width: 100% !important;
      box-shadow: none !important; border: 0 !important;
      padding: 0 !important; margin: 0 !important;
    }
    #kartu-peserta .no-print { display: none !important; }
  }
</style>
</body>
</html>
