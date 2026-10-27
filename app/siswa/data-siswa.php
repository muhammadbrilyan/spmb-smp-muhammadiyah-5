<?php
/**
 * DATA SISWA — portal siswa (Fase 13).
 *
 * Tiga tab server-side (?tab=...):
 *   data-diri | dokumen | akademik
 *
 * Identitas, orang tua/wali, dan alamat digabung dalam SATU formulir dengan
 * satu tombol simpan agar siswa tidak perlu menyimpan tiga kali. Unggahan
 * berkas tetap punya aksi sendiri karena setiap berkas punya tombol
 * lihat/ganti. Alias tab lama (identitas/orang-tua/alamat) diarahkan ke
 * data-diri supaya tautan lama tetap berfungsi.
 *
 * Data induk hanya dapat diubah selama belum diverifikasi operator. Setelah
 * TERVERIFIKASI, siswa memakai mekanisme pengajuan koreksi (tidak otomatis
 * mengubah data).
 */
$title = 'Data Siswa';
$desc  = 'Formulir data siswa (identitas, orang tua/wali, alamat), dokumen persyaratan, dan data akademik dasar.';
require __DIR__ . '/_bootstrap.php';

$tabs = [
    'data-diri' => ['Data Diri', 'badge'],
    'dokumen'   => ['Dokumen Persyaratan', 'folder_shared'],
    'akademik'  => ['Data Akademik', 'menu_book'],
];
$aliasTab = [
    'identitas'  => 'data-diri',
    'orang-tua'  => 'data-diri',
    'alamat'     => 'data-diri',
];
$tab = (string) ($_GET['tab'] ?? 'data-diri');
$tab = $aliasTab[$tab] ?? $tab;
if (!isset($tabs[$tab])) {
    $tab = 'data-diri';
}

/** Kembali ke tab tertentu setelah POST. */
$kembali = function ($tujuan) {
    header('Location: ' . url('app/siswa/data-siswa.php?tab=' . rawurlencode($tujuan)));
    exit;
};

/* ==================================================================
 * PROSES POST
 * ================================================================== */
if ($siswa && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        $kembali($tab);
    }

    $aksi = (string) ($_POST['aksi'] ?? '');
    $tabKembali = (string) ($_POST['tab_kembali'] ?? $tab);
    $tabKembali = $aliasTab[$tabKembali] ?? $tabKembali;
    if (!isset($tabs[$tabKembali])) {
        $tabKembali = 'data-diri';
    }

    /* ---------------- Satu formulir data siswa ---------------- */
    if ($aksi === 'data_diri') {
        if (!$biodataBolehEdit) {
            flash('error', 'Data sudah terkunci. Gunakan pengajuan koreksi bila perlu perubahan.');
            $kembali('data-diri');
        }
        $hasil = siswa_simpan_data_diri($siswa['id'], [
            'nisn'                  => (string) ($_POST['nisn'] ?? ''),
            'jenis_kelamin'         => (string) ($_POST['jenis_kelamin'] ?? ''),
            'tempat_lahir'          => (string) ($_POST['tempat_lahir'] ?? ''),
            'tanggal_lahir'         => (string) ($_POST['tanggal_lahir'] ?? ''),
            'alamat'                => (string) ($_POST['alamat'] ?? ''),
            'is_luar_daerah'        => !empty($_POST['is_luar_daerah']),
            'nama_orang_tua'        => (string) ($_POST['nama_orang_tua'] ?? ''),
            'no_whatsapp'           => (string) ($_POST['no_whatsapp'] ?? ''),
            'alamat_orang_tua'      => (string) ($_POST['alamat_orang_tua'] ?? ''),
            'pekerjaan'             => (string) ($_POST['pekerjaan'] ?? ''),
            'penghasilan_bulanan'   => (string) ($_POST['penghasilan_bulanan'] ?? ''),
        ]);
        flash($hasil['ok'] ? 'sukses' : 'error', $hasil['pesan']);
        $kembali('data-diri');
    }

    /* ---------------- Unggah berkas persyaratan (satu aksi simpan) ---------------- */
    if ($aksi === 'unggah_berkas') {
        $tahapKini = (string) ($siswa['tahap'] ?? '');
        $bolehUnggah = dokumen_tahap_boleh_unggah($tahapKini);
        if (!$bolehUnggah) {
            $labelKini = label_tahap($tahapKini)['label'] ?? $tahapKini;
            flash('error', 'Unggah berkas dibuka setelah pembayaran formulir diverifikasi. Tahap saat ini: ' . $labelKini . '.');
            $kembali('dokumen');
        }

        $statusDok = [];
        foreach ($dokumen as $barisDok) {
            $statusDok[$barisDok['kode']] = $barisDok['status'];
        }

        /* Formulir baru mengirim berkas[kode]. Tetap dukung kiriman satu
         * berkas (jenis_dokumen + berkas) agar tautan/proses lama tidak rusak. */
        $masuk = $_FILES['berkas'] ?? null;
        $daftar = [];
        if (is_array($masuk) && isset($masuk['name'])) {
            if (is_array($masuk['name'])) {
                foreach ($masuk['name'] as $kode => $namaAsli) {
                    $kode = (string) $kode;
                    $err  = (int) ($masuk['error'][$kode] ?? UPLOAD_ERR_NO_FILE);
                    if ($err === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $daftar[$kode] = [
                        'name'     => (string) $namaAsli,
                        'tmp_name' => (string) ($masuk['tmp_name'][$kode] ?? ''),
                        'error'    => $err,
                        'size'     => (int) ($masuk['size'][$kode] ?? 0),
                    ];
                }
            } elseif ((int) $masuk['error'] !== UPLOAD_ERR_NO_FILE) {
                $kode = trim((string) ($_POST['jenis_dokumen'] ?? ''));
                if ($kode !== '') {
                    $daftar[$kode] = [
                        'name'     => (string) $masuk['name'],
                        'tmp_name' => (string) $masuk['tmp_name'],
                        'error'    => (int) $masuk['error'],
                        'size'     => (int) $masuk['size'],
                    ];
                }
            }
        }

        if (!$daftar) {
            flash('error', 'Pilih minimal satu berkas yang akan diunggah, lalu simpan.');
            $kembali('dokumen');
        }

        $izinMime = [
            'jpg'  => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',  'pdf'  => 'application/pdf',
        ];
        $siap  = [];
        $galat = [];

        /* Validasi seluruh berkas lebih dulu supaya satu kesalahan tidak
         * menyimpan sebagian berkas lain. */
        foreach ($daftar as $kode => $berkas) {
            $jList = q_all('SELECT * FROM jenis_dokumen WHERE kode = ? AND aktif = 1', [$kode]);
            if (!$jList) {
                $galat[] = 'Jenis dokumen tidak dikenali / tidak aktif.';
                continue;
            }
            $j = $jList[0];

            if ($tahapKini === 'BERKAS_REVISI' && ($statusDok[$kode] ?? null) !== 'REJECTED') {
                $galat[] = '"' . $j['nama'] . '" tidak perlu diunggah ulang pada tahap revisi.';
                continue;
            }
            if ($berkas['error'] !== UPLOAD_ERR_OK) {
                $galat[] = '"' . $j['nama'] . '" gagal diunggah (kode ' . $berkas['error'] . ').';
                continue;
            }

            $izinEkstensi = array_values(array_filter(array_map('trim', explode(',', strtolower((string) $j['format_diizinkan'])))));
            $maksBytes    = (int) $j['maks_kb'] * 1024;
            $ekstensi     = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));

            if (!in_array($ekstensi, $izinEkstensi, true)) {
                $galat[] = '"' . $j['nama'] . '" harus berformat: ' . implode(', ', $izinEkstensi) . '.';
                continue;
            }
            if ($berkas['size'] > $maksBytes) {
                $galat[] = 'Ukuran "' . $j['nama'] . '" maksimal ' . (int) $j['maks_kb'] . ' KB.';
                continue;
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = (string) $finfo->file($berkas['tmp_name']);
            if (!in_array($mime, $izinMime, true)) {
                $galat[] = 'Isi berkas "' . $j['nama'] . '" bukan gambar/PDF yang sah.';
                continue;
            }

            $siap[$kode] = ['jenis' => $j, 'berkas' => $berkas, 'ekstensi' => $ekstensi];
        }

        if ($galat) {
            flash('error', implode(' ', $galat));
            $kembali('dokumen');
        }

        $folder = storage_path('dokumen');
        if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
            flash('error', 'Folder unggahan tidak dapat dibuat.');
            $kembali('dokumen');
        }

        $berhasil = 0;
        foreach ($siap as $item) {
            $j        = $item['jenis'];
            $namaFile = 'dok-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $item['ekstensi'];
            if (!move_uploaded_file($item['berkas']['tmp_name'], $folder . '/' . $namaFile)) {
                $galat[] = 'Berkas "' . $j['nama'] . '" gagal disimpan ke server.';
                continue;
            }

            $rel     = 'storage/dokumen/' . $namaFile;
            $kb      = (int) round($item['berkas']['size'] / 1024);
            $hasilUp = dokumen_upload($siswa['id'], $j['kode'], $rel, $kb);
            if (!empty($hasilUp['ok'])) {
                $berhasil++;
            } else {
                @unlink($folder . '/' . $namaFile);
                $galat[] = '"' . $j['nama'] . '": ' . ($hasilUp['pesan'] ?? 'gagal disimpan.');
            }
        }

        $pesan = [];
        if ($berhasil > 0) {
            $pesan[] = $berhasil . ' berkas berhasil disimpan.';
        }
        if ($galat) {
            $pesan[] = implode(' ', $galat);
        }
        flash($galat ? 'error' : 'sukses', implode(' ', $pesan));
        $kembali('dokumen');
    }

    /* ---------------- Kirim berkas untuk verifikasi ---------------- */
    if ($aksi === 'submit_berkas') {
        $hasilSubmit = dokumen_submit($siswa['id']);
        if (!empty($hasilSubmit['ok'])) {
            flash('sukses', 'Berkas dikirim untuk diverifikasi panitia.');
        } else {
            $pesan = $hasilSubmit['pesan'] ?? 'Gagal mengirim berkas.';
            if (!empty($hasilSubmit['kurang'])) {
                $pesan .= ' Kurang: ' . implode(', ', $hasilSubmit['kurang']) . '.';
            }
            flash('error', $pesan);
        }
        $kembali('dokumen');
    }

    /* ---------------- Ajukan verifikasi data ---------------- */
    if ($aksi === 'ajukan_verifikasi') {
        if (!$biodataBolehEdit) {
            flash('error', 'Data sudah diajukan / diverifikasi.');
            $kembali('identitas');
        }
        $hasil = siswa_ajukan_verifikasi_data($siswa['id']);
        flash($hasil['ok'] ? 'sukses' : 'error', $hasil['pesan']);
        $kembali('data-diri');
    }

    /* ---------------- Pengajuan koreksi data ---------------- */
    if ($aksi === 'ajukan_koreksi') {
        $hasil = koreksi_ajukan(
            $siswa['id'],
            (string) ($me['id'] ?? ''),
            (string) ($_POST['bidang'] ?? ''),
            (string) ($_POST['pesan'] ?? '')
        );
        flash($hasil['ok'] ? 'sukses' : 'error', $hasil['pesan']);
        $kembali('data-diri');
    }

    if ($aksi === 'batalkan_koreksi') {
        $id = (string) ($_POST['koreksi_id'] ?? '');
        $ok = $id !== '' && koreksi_batalkan($id, $siswa['id']);
        flash($ok ? 'sukses' : 'error', $ok ? 'Pengajuan koreksi dibatalkan.' : 'Pengajuan tidak dapat dibatalkan.');
        $kembali('data-diri');
    }

    flash('error', 'Aksi tidak dikenali.');
    $kembali($tabKembali);
}

$flash = flash();

/* Status unggah berkas mengikuti tahap — BAYAR_TERVERIFIKASI dan
 * AKUN_TERKIRIM dibuka agar siswa yang sudah membayar / sudah menerima akun
 * dapat langsung melengkapi berkas. */
$tahapSekarang = (string) ($siswa['tahap'] ?? '');
$dokTerkunci   = in_array($tahapSekarang, ['BERKAS_DISUBMIT', 'BERKAS_TERVERIFIKASI', 'JADWAL_DITETAPKAN', 'NILAI_TERINPUT', 'DIUMUMKAN'], true);
$modeRevisi    = $tahapSekarang === 'BERKAS_REVISI';
$bolehAksiDok  = dokumen_tahap_boleh_unggah($tahapSekarang);
$bolehSubmitDok = $bolehAksiDok;
$jmlWajib = 0;
$jmlWajibTerisi = 0;
foreach ($dokumen as $dW) {
    if (!empty($dW['wajib'])) {
        $jmlWajib++;
        if (!empty($dW['file_url'])) {
            $jmlWajibTerisi++;
        }
    }
}
$submitSiap = $bolehSubmitDok && $jmlWajib > 0 && $jmlWajibTerisi === $jmlWajib;

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'siswa';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Siswa' => url('app/siswa/index.php'), 'Data Siswa' => null];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Data Saya</span>
          <?php if ($siswa): ?>
            <span class="badge badge-<?= e($verifikasi[1]) ?>"><?= e($verifikasi[0]) ?></span>
            <span class="badge badge-gray"><?= (int) $kelengkapan['persen'] ?>% lengkap</span>
          <?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Data Siswa</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Lengkapi data dengan benar. Setelah operator memverifikasi, data terkunci dan perubahan dilakukan melalui pengajuan koreksi.
        </p>
      </div>
      <?php if ($siswa): ?>
        <a href="<?= url('app/siswa/index.php') ?>" class="btn btn-outline shrink-0"><?= icon('arrow_back') ?> Dashboard</a>
      <?php endif; ?>
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
    <?php else: ?>

      <!-- Navigasi tab server-side -->
      <nav class="mt-6 card p-2 flex gap-1.5 overflow-x-auto scroll-thin" aria-label="Bagian data siswa">
        <?php foreach ($tabs as $key => $meta): $aktif = $key === $tab; ?>
          <a href="<?= url('app/siswa/data-siswa.php?tab=' . $key) ?>"
             class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[13px] font-semibold whitespace-nowrap transition
                    <?= $aktif ? 'bg-primary-light text-primary' : 'text-ink-500 hover:bg-canvas' ?>"
             <?= $aktif ? 'aria-current="page"' : '' ?>>
            <?= icon($meta[1], 'text-[18px]') ?> <?= e($meta[0]) ?>
          </a>
        <?php endforeach; ?>
      </nav>

      <!-- Ringkasan kelengkapan -->
      <section class="mt-5 card p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="min-w-0">
            <h2 class="font-bold text-ink-900">Kelengkapan data</h2>
            <p class="mt-1 text-[13px] text-ink-500">
              <?= (int) $kelengkapan['terisi'] ?>/<?= (int) $kelengkapan['total'] ?> butir wajib terisi
              · <?= (int) $kelengkapan['dok_terisi'] ?>/<?= (int) $kelengkapan['dok_wajib'] ?> berkas wajib terunggah
            </p>
          </div>
          <div class="flex items-center gap-3 shrink-0">
            <div class="w-40 h-2 rounded-full bg-line-soft overflow-hidden">
              <div class="h-full rounded-full <?= $kelengkapan['persen'] >= 100 ? 'bg-emerald-500' : 'bg-primary' ?>" style="width:<?= (int) $kelengkapan['persen'] ?>%"></div>
            </div>
            <span class="font-extrabold text-ink-900"><?= (int) $kelengkapan['persen'] ?>%</span>
          </div>
        </div>
        <?php if ($kelengkapan['kurang']): ?>
          <div class="mt-3 rounded-xl bg-[#FFFBEB] border border-[#FDE68A] px-4 py-3 text-[13px] text-[#92400E]">
            <strong>Belum lengkap:</strong> <?= e(implode(', ', $kelengkapan['kurang'])) ?>.
          </div>
        <?php endif; ?>
        <?php if (!empty($siswa['catatan_verifikasi_data'])): ?>
          <div class="mt-3 rounded-xl bg-[#FEF2F2] border border-red-200 px-4 py-3 text-[13px] text-[#991B1B]">
            <strong>Catatan panitia:</strong> <?= e($siswa['catatan_verifikasi_data']) ?>
          </div>
        <?php endif; ?>
      </section>

      <?php /* ================= DATA DIRI (SATU FORMULIR) ================= */ ?>
      <?php if ($tab === 'data-diri'): ?>
        <section class="mt-5 card p-5 sm:p-6">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <h2 class="font-bold text-ink-900">Formulir Data Siswa</h2>
              <p class="mt-1 text-[13px] text-ink-500">
                Identitas, orang tua/wali, dan alamat diisi dalam satu formulir.
                No. pendaftaran <span class="font-mono font-semibold text-ink-700"><?= e($siswa['no_pendaftaran']) ?></span>
              </p>
            </div>
            <span class="badge badge-<?= $biodataBolehEdit ? 'blue' : 'gray' ?>">
              <?= $biodataBolehEdit ? 'Dapat Diubah' : 'Terkunci' ?>
            </span>
          </div>

          <?php if ($biodataBolehEdit): ?>
            <form method="post" action="<?= url('app/siswa/data-siswa.php?tab=data-diri') ?>" class="mt-5">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="data_diri">
              <input type="hidden" name="tab_kembali" value="data-diri">

              <div class="grid gap-4 sm:grid-cols-2">
                <div>
                  <label class="label" for="nama_siswa">Nama Lengkap</label>
                  <input id="nama_siswa" class="input bg-canvas" value="<?= e($siswa['nama_siswa']) ?>" disabled>
                  <p class="hint">Nama sesuai akta kelahiran. Hubungi panitia untuk koreksi nama.</p>
                </div>
                <div>
                  <label class="label" for="nisn">NISN (10 digit)</label>
                  <input id="nisn" name="nisn" class="input font-mono" inputmode="numeric" maxlength="10"
                         value="<?= e($siswa['nisn'] ?? '') ?>" placeholder="0129482099">
                  <p class="hint">Opsional. Isi bila sudah memiliki NISN.</p>
                </div>
                <div>
                  <label class="label" for="jenis_kelamin">Jenis Kelamin <span class="text-red-600">*</span></label>
                  <select id="jenis_kelamin" name="jenis_kelamin" class="select" required>
                    <option value="L" <?= ($siswa['jenis_kelamin'] ?? 'L') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                    <option value="P" <?= ($siswa['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
                  </select>
                </div>
                <div>
                  <label class="label" for="tempat_lahir">Tempat Lahir <span class="text-red-600">*</span></label>
                  <input id="tempat_lahir" name="tempat_lahir" class="input" required
                         value="<?= e($siswa['tempat_lahir'] ?? '') ?>" placeholder="Tangerang">
                </div>
                <div>
                  <label class="label" for="tanggal_lahir">Tanggal Lahir <span class="text-red-600">*</span></label>
                  <input id="tanggal_lahir" name="tanggal_lahir" type="date" class="input" required
                         value="<?= e($siswa['tanggal_lahir'] ?? '') ?>">
                </div>
                <div>
                  <label class="label" for="asal_sekolah">Asal Sekolah</label>
                  <input id="asal_sekolah" class="input bg-canvas" value="<?= e($siswa['asal_sekolah'] ?? '') ?>" disabled>
                </div>
                <div class="sm:col-span-2">
                  <label class="label" for="alamat">Alamat Lengkap Siswa <span class="text-red-600">*</span></label>
                  <textarea id="alamat" name="alamat" class="textarea" rows="3" required><?= e($siswa['alamat'] ?? '') ?></textarea>
                </div>
                <div class="sm:col-span-2 rounded-xl bg-canvas border border-line px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-semibold text-ink-900">Domisili Luar Daerah</p>
                    <p class="text-[13px] text-ink-500">Aktifkan bila domisili di luar Kota Tangerang.</p>
                  </div>
                  <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_luar_daerah" value="1" <?= !empty($siswa['is_luar_daerah']) ? 'checked' : '' ?> class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
                    <span class="text-sm font-semibold">Luar Daerah</span>
                  </label>
                </div>
                <div>
                  <label class="label" for="nama_orang_tua">Nama Orang Tua / Wali <span class="text-red-600">*</span></label>
                  <input id="nama_orang_tua" name="nama_orang_tua" class="input" required value="<?= e($siswa['nama_orang_tua'] ?? '') ?>">
                </div>
                <div>
                  <label class="label" for="no_whatsapp">Nomor WhatsApp <span class="text-red-600">*</span></label>
                  <input id="no_whatsapp" name="no_whatsapp" class="input" inputmode="tel" maxlength="20" required
                         value="<?= e($siswa['no_whatsapp'] ?? '') ?>" placeholder="08xxxxxxxxxx">
                  <p class="hint">Nomor aktif yang dapat dihubungi panitia.</p>
                </div>
                <div>
                  <label class="label" for="pekerjaan">Pekerjaan Orang Tua / Wali <span class="text-red-600">*</span></label>
                  <input id="pekerjaan" name="pekerjaan" class="input" required value="<?= e($siswa['pekerjaan'] ?? '') ?>">
                </div>
                <div>
                  <label class="label" for="penghasilan_bulanan">Penghasilan per Bulan</label>
                  <input id="penghasilan_bulanan" name="penghasilan_bulanan" class="input"
                         value="<?= e($siswa['penghasilan_bulanan'] ?? '') ?>" placeholder="Contoh: Rp 3.000.000">
                </div>
                <div class="sm:col-span-2">
                  <label class="label" for="alamat_orang_tua">Alamat Orang Tua / Wali <span class="text-red-600">*</span></label>
                  <textarea id="alamat_orang_tua" name="alamat_orang_tua" class="textarea" rows="2" required><?= e($siswa['alamat_orang_tua'] ?? '') ?></textarea>
                  <p class="hint">Boleh sama dengan alamat siswa bila domisili orang tua/wali sama.</p>
                </div>
                <div class="sm:col-span-2">
                  <label class="label" for="jalur">Jalur Pendaftaran</label>
                  <input id="jalur" class="input bg-canvas" value="<?= e($siswa['jalur'] ?: 'Reguler') ?>" disabled>
                </div>
              </div>

              <div class="mt-5 border-t border-line-soft pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p class="text-[13px] text-ink-500">Seluruh perubahan pada formulir ini disimpan sekaligus.</p>
                <button type="submit" class="btn btn-primary shadow-drop"><?= icon('save') ?> Simpan Data</button>
              </div>
            </form>
          <?php else: ?>
            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
              <div><dt class="label">Nama Lengkap</dt><dd class="text-sm font-semibold text-ink-900"><?= e($siswa['nama_siswa']) ?></dd></div>
              <div><dt class="label">NISN</dt><dd class="text-sm font-mono text-ink-900"><?= e($siswa['nisn'] ?: '—') ?></dd></div>
              <div><dt class="label">Jenis Kelamin</dt><dd class="text-sm text-ink-900"><?= ($siswa['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan' : 'Laki-laki' ?></dd></div>
              <div><dt class="label">Tempat, Tanggal Lahir</dt><dd class="text-sm text-ink-900"><?= e($siswa['tempat_lahir'] ?: '—') ?><?= $siswa['tanggal_lahir'] ? ', ' . e(bulan($siswa['tanggal_lahir'])) : '' ?></dd></div>
              <div class="sm:col-span-2"><dt class="label">Alamat Siswa</dt><dd class="text-sm text-ink-900"><?= e($siswa['alamat'] ?: '—') ?></dd></div>
              <div><dt class="label">Domisili</dt><dd class="text-sm text-ink-900"><?= !empty($siswa['is_luar_daerah']) ? 'Luar Daerah' : 'Dalam Daerah' ?></dd></div>
              <div><dt class="label">Nama Orang Tua / Wali</dt><dd class="text-sm text-ink-900"><?= e($siswa['nama_orang_tua'] ?: '—') ?></dd></div>
              <div><dt class="label">Nomor WhatsApp</dt><dd class="text-sm text-ink-900"><?= e($siswa['no_whatsapp'] ?: '—') ?></dd></div>
              <div><dt class="label">Pekerjaan</dt><dd class="text-sm text-ink-900"><?= e($siswa['pekerjaan'] ?: '—') ?></dd></div>
              <div><dt class="label">Penghasilan</dt><dd class="text-sm text-ink-900"><?= e($siswa['penghasilan_bulanan'] ?: '—') ?></dd></div>
              <div class="sm:col-span-2"><dt class="label">Alamat Orang Tua / Wali</dt><dd class="text-sm text-ink-900"><?= e($siswa['alamat_orang_tua'] ?: '—') ?></dd></div>
              <div><dt class="label">Asal Sekolah</dt><dd class="text-sm text-ink-900"><?= e($siswa['asal_sekolah'] ?: '—') ?></dd></div>
              <div><dt class="label">Jalur Pendaftaran</dt><dd class="text-sm text-ink-900"><?= e($siswa['jalur'] ?: 'Reguler') ?></dd></div>
              <div><dt class="label">Status Verifikasi</dt><dd class="mt-0.5"><?= badge($verifikasi[0], $verifikasi[1]) ?></dd></div>
            </dl>
            <?php if (!empty($siswa['data_verified_at'])): ?>
              <p class="mt-4 text-[12px] text-ink-400">Diverifikasi panitia: <?= e(bulan($siswa['data_verified_at'])) ?>, <?= e(date('H:i', strtotime($siswa['data_verified_at']))) ?> WIB</p>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($biodataBolehEdit): ?>
            <div class="mt-5 border-t border-line-soft pt-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <p class="text-sm font-semibold text-ink-900">Ajukan verifikasi data</p>
                <p class="text-[13px] text-ink-500">
                  <?= $kelengkapan['kurang'] ? 'Lengkapi seluruh butir wajib terlebih dahulu.' : 'Data wajib sudah lengkap. Ajukan agar diperiksa operator.' ?>
                </p>
              </div>
              <form method="post" action="<?= url('app/siswa/data-siswa.php?tab=data-diri') ?>"
                    onsubmit="return confirm('Ajukan data untuk diverifikasi operator? Setelah diverifikasi, data terkunci.');">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="ajukan_verifikasi">
                <button type="submit" class="btn btn-primary" <?= $kelengkapan['kurang'] ? 'disabled' : '' ?>>
                  <?= icon('send') ?> <?= ($siswa['status_verifikasi'] ?? '') === 'MENUNGGU_VERIFIKASI' ? 'Kirim Ulang' : 'Ajukan Verifikasi' ?>
                </button>
              </form>
            </div>
          <?php elseif (($siswa['status_verifikasi'] ?? '') === 'TERVERIFIKASI'): ?>
            <div class="mt-5 border-t border-line-soft pt-5">
              <div class="rounded-xl bg-blue-50 border border-blue-200 px-4 py-3 text-[13px] text-blue-800">
                Data sudah diverifikasi dan terkunci. Bila ada yang keliru, ajukan koreksi — panitia akan meninjau, dan data TIDAK berubah otomatis.
              </div>
              <form method="post" action="<?= url('app/siswa/data-siswa.php?tab=data-diri') ?>" class="mt-4 grid gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="ajukan_koreksi">
                <div class="grid gap-3 sm:grid-cols-2">
                  <div>
                    <label class="label" for="bidang_koreksi">Bagian yang dikoreksi</label>
                    <select id="bidang_koreksi" name="bidang" class="select" required>
                      <?php foreach ($koreksiBidang as $kb => $lb): ?>
                        <option value="<?= e($kb) ?>"><?= e($lb) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="sm:col-span-2">
                    <label class="label" for="pesan_koreksi">Uraian koreksi <span class="text-red-600">*</span></label>
                    <textarea id="pesan_koreksi" name="pesan" class="textarea" rows="3" minlength="10" maxlength="2000" required
                              placeholder="Contoh: tanggal lahir tertulis 12/05/2012, seharusnya 15/05/2012 sesuai akta."></textarea>
                    <p class="hint">Minimal 10 karakter. Lampirkan bukti pendukung melalui tab Dokumen bila diperlukan.</p>
                  </div>
                </div>
                <div class="flex justify-end">
                  <button type="submit" class="btn btn-outline"><?= icon('edit_note') ?> Ajukan Koreksi</button>
                </div>
              </form>
            </div>
          <?php endif; ?>

          <?php if ($koreksi): ?>
            <div class="mt-5 border-t border-line-soft pt-4">
              <h3 class="font-semibold text-ink-900 text-sm">Riwayat Pengajuan Koreksi</h3>
              <div class="mt-3 space-y-2.5">
                <?php foreach ($koreksi as $k): $kl = koreksi_status_label($k['status']); ?>
                  <article class="rounded-xl border border-line p-3.5">
                    <div class="flex items-start justify-between gap-2 flex-wrap">
                      <p class="text-[13px] font-semibold text-ink-900"><?= e($koreksiBidang[$k['bidang']] ?? $k['bidang']) ?></p>
                      <span class="badge badge-<?= e($kl[1]) ?>"><?= e($kl[0]) ?></span>
                    </div>
                    <p class="mt-1.5 text-[13px] text-ink-600 whitespace-pre-line"><?= e($k['pesan']) ?></p>
                    <?php if (!empty($k['catatan_operator'])): ?>
                      <p class="mt-2 rounded-lg bg-canvas px-3 py-2 text-[12px] text-ink-600"><strong>Panitia:</strong> <?= e($k['catatan_operator']) ?></p>
                    <?php endif; ?>
                    <div class="mt-2 flex items-center justify-between gap-2 flex-wrap">
                      <p class="text-[11px] text-ink-400">Diajukan <?= e(bulan($k['created_at'])) ?></p>
                      <?php if ($k['status'] === 'MENUNGGU'): ?>
                        <form method="post" action="<?= url('app/siswa/data-siswa.php?tab=data-diri') ?>"
                              onsubmit="return confirm('Batalkan pengajuan koreksi ini?');">
                          <?= csrf_field() ?>
                          <input type="hidden" name="aksi" value="batalkan_koreksi">
                          <input type="hidden" name="koreksi_id" value="<?= e($k['id']) ?>">
                          <button type="submit" class="text-[12px] font-semibold text-red-600 hover:underline">Batalkan</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php /* ================= DOKUMEN ================= */ ?>
      <?php if ($tab === 'dokumen'): ?>
        <section class="mt-5 card p-5 sm:p-6">
          <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <h2 class="font-bold text-ink-900">Dokumen Persyaratan</h2>
              <p class="mt-1 text-[13px] text-ink-500">Format dan ukuran mengikuti ketentuan tiap jenis berkas.</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <?php if ($dokTerkunci): ?>
                <span class="badge badge-blue">Terkunci</span>
              <?php elseif ($modeRevisi): ?>
                <span class="badge badge-red">Revisi</span>
              <?php else: ?>
                <span class="badge badge-gray"><?= count($dokumen) ?> jenis</span>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($modeRevisi): ?>
            <div class="mt-4 rounded-xl bg-[#FEF2F2] border border-red-200 px-4 py-3 text-[13px] text-[#991B1B]">
              Ada berkas yang ditolak. Unggah ulang hanya berkas bertanda <strong>Ditolak</strong>, lalu kirim kembali.
            </div>
          <?php elseif (!$bolehAksiDok && !$dokTerkunci): ?>
            <div class="mt-4 rounded-xl bg-[#FFFBEB] border border-[#FDE68A] px-4 py-3 text-[13px] text-[#92400E]">
              Unggah berkas dibuka setelah pembayaran formulir diverifikasi panitia. Selama menunggu, Anda dapat melengkapi data diri.
            </div>
          <?php endif; ?>

          <?php
          $adaBolehUnggah = false;
          foreach ($dokumen as $dokCek) {
              if ($bolehAksiDok && (!$modeRevisi || $dokCek['status'] === 'REJECTED')) {
                  $adaBolehUnggah = true;
                  break;
              }
          }
          ?>
          <?php if ($adaBolehUnggah): ?>
            <form method="post" action="<?= url('app/siswa/data-siswa.php?tab=dokumen') ?>" enctype="multipart/form-data">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="unggah_berkas">
          <?php endif; ?>

          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <?php
            $toneVerDok  = ['PENDING' => 'amber', 'VERIFIED' => 'green', 'REJECTED' => 'red'];
            $labelVerDok = ['PENDING' => 'Menunggu', 'VERIFIED' => 'Terverifikasi', 'REJECTED' => 'Ditolak'];
            foreach ($dokumen as $d):
                $bolehUnggah = $bolehAksiDok
                    && (!$modeRevisi || ($d['status'] === 'REJECTED'));
                $ekstensiPratinjau = strtolower(pathinfo(basename((string) $d['file_url']), PATHINFO_EXTENSION));
                $tipePratinjau = $ekstensiPratinjau === 'pdf' ? 'pdf' : 'gambar';
            ?>
              <div class="rounded-2xl border <?= ($d['status'] === 'REJECTED') ? 'border-red-200 bg-red-50/40' : 'border-line' ?> p-4">
                <div class="flex items-start justify-between gap-2">
                  <p class="font-semibold text-sm text-ink-900">
                    <?= e($d['nama']) ?>
                    <?php if (!empty($d['wajib'])): ?><span class="text-red-600" title="Wajib">*</span><?php endif; ?>
                  </p>
                  <?php if ($d['file_url']): ?>
                    <?= badge($labelVerDok[$d['status']] ?? $d['status'], $toneVerDok[$d['status']] ?? 'gray') ?>
                  <?php else: ?>
                    <span class="badge badge-gray">Kosong</span>
                  <?php endif; ?>
                </div>
                <?php if ($d['file_url']): ?>
                  <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[12px] text-ink-500">
                    <button type="button"
                            class="inline-flex items-center gap-1 font-semibold text-primary hover:underline"
                            data-pratinjau-url="<?= e(url('app/api/dokumen.php?f=' . rawurlencode(basename((string) $d['file_url'])))) ?>"
                            data-pratinjau-nama="<?= e($d['nama']) ?>"
                            data-pratinjau-tipe="<?= e($tipePratinjau) ?>"
                            data-pratinjau-meta="<?= e(($tipePratinjau === 'pdf' ? 'PDF' : 'Gambar') . ' · ' . (int) $d['ukuran_kb'] . ' KB · ' . bulan($d['uploaded_at'])) ?>">
                      <?= icon('visibility', 'text-[15px]') ?> Lihat berkas
                    </button>
                    <span>· <?= (int) $d['ukuran_kb'] ?> KB · <?= e(bulan($d['uploaded_at'])) ?></span>
                  </div>
                <?php else: ?>
                  <p class="mt-1 text-[12px] text-ink-400">Format: <?= e(strtoupper(str_replace(',', ' / ', (string) $d['format']))) ?> · maks <?= (int) $d['maks_kb'] ?> KB<?= !empty($d['wajib']) ? ' · wajib' : ' · opsional' ?></p>
                <?php endif; ?>

                <?php if ($d['status'] === 'REJECTED' && !empty($d['catatan'])): ?>
                  <div class="mt-2 rounded-lg bg-white border border-red-200 px-3 py-2">
                    <p class="text-[12px] font-semibold text-[#991B1B]">Catatan panitia:</p>
                    <p class="text-[13px] text-ink-700"><?= e($d['catatan']) ?></p>
                  </div>
                <?php elseif ($d['status'] === 'VERIFIED' && !empty($d['verified_at'])): ?>
                  <p class="mt-1 text-[11px] text-[#065F46]">Terverifikasi: <?= e(bulan($d['verified_at'])) ?></p>
                <?php endif; ?>

                <?php if ($bolehUnggah): ?>
                  <div class="mt-3">
                    <label class="label text-[12px]" for="berkas-<?= e($d['kode']) ?>">
                      <?= $d['file_url'] ? 'Ganti berkas (edit)' : 'Pilih berkas' ?>
                    </label>
                    <input id="berkas-<?= e($d['kode']) ?>" name="berkas[<?= e($d['kode']) ?>]"
                           type="file" accept="<?= e('.' . str_replace(',', ',.', (string) $d['format'])) ?>"
                           class="input h-9 text-[13px] file:mr-3">
                  </div>
                <?php elseif ($dokTerkunci): ?>
                  <p class="mt-3 text-[12px] text-ink-500 flex items-center gap-1"><?= icon('lock', 'text-[15px]') ?> Berkas terkunci.</p>
                <?php elseif (!$bolehAksiDok): ?>
                  <p class="mt-3 text-[12px] text-ink-500 flex items-center gap-1"><?= icon('lock', 'text-[15px]') ?> Unggah dibuka setelah pembayaran terverifikasi.</p>
                <?php else: ?>
                  <p class="mt-3 text-[12px] text-ink-500 flex items-center gap-1"><?= icon('done', 'text-[15px]') ?> Berkas sudah lolos pemeriksaan.</p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php if (!$dokumen): ?>
              <p class="text-sm text-ink-400">Belum ada jenis dokumen yang diaktifkan panitia.</p>
            <?php endif; ?>
          </div>

          <?php if ($adaBolehUnggah): ?>
            <div class="mt-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-line-soft pt-4">
              <p class="text-[13px] text-ink-500">
                Pilih satu atau beberapa berkas pada kartu di atas. Semua berkas yang dipilih disimpan sekaligus.
              </p>
              <button type="submit" class="btn btn-primary shadow-drop shrink-0"><?= icon('save') ?> Simpan Dokumen</button>
            </div>
            </form>
          <?php endif; ?>

          <?php if ($bolehSubmitDok): ?>
            <div class="mt-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-line-soft pt-4">
              <p class="text-[13px] text-ink-500">
                Wajib: <strong class="text-ink-900"><?= $jmlWajibTerisi ?>/<?= $jmlWajib ?></strong> berkas wajib sudah terunggah.
              </p>
              <?php if ($submitSiap): ?>
                <form method="post" action="<?= url('app/siswa/data-siswa.php?tab=dokumen') ?>"
                      onsubmit="return confirm('Kirim seluruh berkas untuk verifikasi panitia? Berkas akan terkunci sementara.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="submit_berkas">
                  <button type="submit" class="btn btn-primary shadow-drop"><?= icon('send') ?> Kirim untuk Verifikasi</button>
                </form>
              <?php else: ?>
                <button class="btn btn-primary opacity-50 pointer-events-none" disabled><?= icon('send') ?> Kirim untuk Verifikasi</button>
              <?php endif; ?>
            </div>
          <?php elseif ($dokTerkunci): ?>
            <p class="mt-5 text-[13px] text-ink-500 border-t border-line-soft pt-4">
              <?php if ($tahapSekarang === 'BERKAS_DISUBMIT'): ?>
                Berkas sedang diperiksa panitia. Unggahan dinonaktifkan sampai ada hasil.
              <?php else: ?>
                Pemeriksaan berkas selesai — berkas tidak dapat diubah.
              <?php endif; ?>
            </p>
          <?php elseif (!$bolehAksiDok): ?>
            <p class="mt-5 text-[13px] text-ink-500 border-t border-line-soft pt-4">
              Unggah dan pengiriman berkas akan aktif setelah pembayaran formulir diverifikasi panitia.
            </p>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <?php /* ================= AKADEMIK DASAR ================= */ ?>
      <?php if ($tab === 'akademik'): ?>
        <section class="mt-5 card p-5 sm:p-6">
          <h2 class="font-bold text-ink-900">Data Akademik Dasar</h2>
          <p class="mt-1 text-[13px] text-ink-500">Data yang tercatat pada sistem SPMB. Bukan rapor atau nilai akademik sekolah.</p>

          <dl class="mt-5 grid gap-4 sm:grid-cols-2">
            <div><dt class="label">NISN</dt><dd class="text-sm font-mono text-ink-900"><?= e($siswa['nisn'] ?: '—') ?></dd></div>
            <div><dt class="label">Asal Sekolah</dt><dd class="text-sm text-ink-900"><?= e($siswa['asal_sekolah'] ?: '—') ?></dd></div>
            <div><dt class="label">Jalur Pendaftaran</dt><dd class="text-sm text-ink-900"><?= e($siswa['jalur'] ?: 'Reguler') ?></dd></div>
            <div><dt class="label">Domisili</dt><dd class="text-sm text-ink-900"><?= !empty($siswa['is_luar_daerah']) ? 'Luar Daerah' : 'Dalam Daerah' ?></dd></div>
            <div><dt class="label">Tahun Ajaran</dt><dd class="text-sm text-ink-900"><?= e($PENGATURAN['tahun_ajaran'] ?? '—') ?></dd></div>
            <div><dt class="label">Status Seleksi</dt>
              <dd class="mt-0.5">
                <?php if ($pengumumanAktif): $sel = $labelSeleksi[$siswa['status_seleksi']] ?? [$siswa['status_seleksi'], 'gray']; ?>
                  <?= badge($sel[0], $sel[1]) ?>
                <?php else: ?>
                  <?= badge('Belum Diumumkan', 'gray') ?>
                <?php endif; ?>
              </dd>
            </div>
            <?php if ($siswaDiterima): ?>
              <div class="sm:col-span-2">
                <dt class="label">Penempatan Kelas</dt>
                <dd class="text-sm text-ink-900">
                  <?php if ($penempatan): ?>
                    <strong><?= e($penempatan['kode']) ?></strong> — <?= e($penempatan['nama_kelas']) ?>
                    <?php if (!empty($penempatan['wali_kelas'])): ?><span class="text-ink-500"> · Wali kelas: <?= e($penempatan['wali_kelas']) ?></span><?php endif; ?>
                  <?php else: ?>
                    Belum ditempatkan panitia.
                  <?php endif; ?>
                </dd>
              </div>
            <?php endif; ?>
          </dl>

          <div class="mt-5 rounded-xl bg-canvas border border-line px-4 py-3 text-[13px] text-ink-600">
            Nilai rapor, absensi, dan jadwal pelajaran belum tersedia pada sistem SPMB ini. Informasi tersebut akan diberikan sekolah setelah siswa aktif.
          </div>

          <div class="mt-4">
            <a href="<?= url('app/siswa/data-siswa.php?tab=data-diri') ?>" class="btn btn-outline btn-sm"><?= icon('edit_note') ?> Ajukan Koreksi Data</a>
          </div>
        </section>
      <?php endif; ?>

    <?php endif; ?>
  </main>
</div>

<?php if ($siswa && $tab === 'dokumen'): ?>
<!-- Modal pratinjau dokumen: kartu kecil mengambang di tengah halaman, tanpa pindah halaman -->
<div id="modal-pratinjau-dokumen" class="modal-root hidden fixed inset-0 pratinjau-modal" role="dialog" aria-modal="true" aria-labelledby="pratinjau-judul">
  <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm animate-fade" data-modal-close></div>
  <div class="pratinjau-lapis">
    <div class="pratinjau-panel card shadow-modal animate-pop">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h3 id="pratinjau-judul" class="font-bold text-sm text-ink-900 truncate">Pratinjau Dokumen</h3>
          <p id="pratinjau-meta" class="mt-0.5 text-[11px] text-ink-500 truncate"></p>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0"
                data-modal-close aria-label="Tutup pratinjau"><?= icon('close') ?></button>
      </div>
      <div id="pratinjau-isi" class="pratinjau-isi"></div>
      <div class="pratinjau-kaki">
        <button type="button" class="btn btn-sm btn-outline" data-modal-close>Tutup</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php if ($siswa && $tab === 'dokumen'): ?>
<script>
  (function () {
    var modal = document.getElementById('modal-pratinjau-dokumen');
    if (!modal) return;

    var judul = document.getElementById('pratinjau-judul');
    var meta  = document.getElementById('pratinjau-meta');
    var isi   = document.getElementById('pratinjau-isi');
    var tutup = modal.querySelector('.icon-box[data-modal-close]');

    function bersihkan() {
      isi.innerHTML = '';
    }

    function tutupModal() {
      modal.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
      bersihkan();
    }

    document.querySelectorAll('[data-pratinjau-url]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var url  = btn.getAttribute('data-pratinjau-url');
        var nama = btn.getAttribute('data-pratinjau-nama') || 'Pratinjau Dokumen';
        var tipe = btn.getAttribute('data-pratinjau-tipe');
        if (!url) return;

        judul.textContent = nama;
        meta.textContent  = btn.getAttribute('data-pratinjau-meta') || '';
        bersihkan();

        if (tipe === 'pdf') {
          var bingkai = document.createElement('iframe');
          bingkai.src = url;
          bingkai.title = nama;
          bingkai.className = 'pratinjau-pdf';
          isi.appendChild(bingkai);
        } else {
          var gambar = document.createElement('img');
          gambar.src = url;
          gambar.alt = nama;
          gambar.className = 'pratinjau-gambar';
          isi.appendChild(gambar);
        }

        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        if (tutup) tutup.focus();
      });
    });

    modal.querySelectorAll('[data-modal-close]').forEach(function (el) {
      el.addEventListener('click', tutupModal);
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !modal.classList.contains('hidden')) {
        tutupModal();
      }
    });
  })();
</script>
<?php endif; ?>
</body>
</html>
