<?php
/**
 * DATA SISWA — portal siswa (Fase 13).
 *
 * Satu halaman utuh dan SATU form: formulir data diri + unggahan berkas
 * dibuat sebagai satu kesatuan dengan dua tombol aksi di bagian paling
 * bawah (setelah bagian dokumen):
 *  - "Simpan Data Sementara"  : menyimpan isian formulir dan berkas terpilih
 *    tanpa mengirim apa pun ke panitia;
 *  - "Ajukan Verifikasi Berkas": menyimpan semuanya lalu mengirim data diri
 *    dan berkas sekaligus untuk diverifikasi panitia (data terkunci setelah
 *    diverifikasi).
 *
 * Data induk hanya dapat diubah selama belum diverifikasi operator. Setelah
 * TERVERIFIKASI, siswa memakai mekanisme pengajuan koreksi di bagian bawah
 * halaman (di luar form utama, tidak otomatis mengubah data).
 */
$title = 'Data Siswa';
$desc  = 'Formulir data siswa (identitas, orang tua/wali, alamat) dan dokumen persyaratan dalam satu halaman.';
require __DIR__ . '/_bootstrap.php';

/** Kembali ke halaman data siswa setelah POST. */
$kembali = function () {
    header('Location: ' . url('app/siswa/data-siswa.php'));
    exit;
};

/** Data diri masih dapat disimpan (belum diverifikasi operator)? */
$bolehSimpanData = in_array($siswa['status_verifikasi'] ?? '', ['DRAFT', 'MENUNGGU_VERIFIKASI', 'DITOLAK'], true);

/** Kumpulan isian formulir data diri dari POST. */
$isianDataDiri = function () {
    return [
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
    ];
};

/**
 * Validasi dan simpan seluruh berkas terpilih pada $_FILES['berkas']
 * sesuai kunci tahap (dipakai tombol simpan maupun ajukan).
 *
 * @return array{ada:bool, ok:bool, pesan:string} ada = ada berkas dikirim
 */
$prosesUnggahan = function () use ($siswa, $dokumen) {
    $tahapKini = (string) ($siswa['tahap'] ?? '');
    if (!dokumen_tahap_boleh_unggah($tahapKini)) {
        return ['ada' => false, 'ok' => false, 'pesan' => 'Unggah berkas dibuka setelah pembayaran formulir diverifikasi.'];
    }

    $statusDok = [];
    foreach ($dokumen as $barisDok) {
        $statusDok[$barisDok['kode']] = $barisDok['status'];
    }

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
        return ['ada' => false, 'ok' => true, 'pesan' => ''];
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
        return ['ada' => true, 'ok' => false, 'pesan' => implode(' ', $galat)];
    }

    $folder = storage_path('dokumen');
    if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
        return ['ada' => true, 'ok' => false, 'pesan' => 'Folder unggahan tidak dapat dibuat.'];
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
    return ['ada' => true, 'ok' => !$galat, 'pesan' => implode(' ', $pesan)];
};

/* ==================================================================
 * PROSES POST
 * ================================================================== */
if ($siswa && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        $kembali();
    }

    /* ---------------- Hapus unggahan berkas yang ditolak ---------------- */
    /* Tombol ini ikut ter-submit bersama form utama, tapi hanya menghapus
     * berkas REJECTED — isian formulir/berkas terpilih lain diabaikan. */
    if (isset($_POST['hapus_dokumen'])) {
        $hasil = dokumen_hapus($siswa['id'], trim((string) $_POST['hapus_dokumen']));
        flash($hasil['ok'] ? 'sukses' : 'error', $hasil['pesan'] ?? ($hasil['ok'] ? 'Berkas dihapus.' : 'Berkas tidak dapat dihapus.'));
        $kembali();
    }

    /* Tombol submit berada di luar urutan normal? Tetap aman: tanpa aksi
     * berarti simpan sementara (misal tombol Enter di dalam form). */
    $aksi = trim((string) ($_POST['aksi'] ?? ''));
    if ($aksi === '') {
        $aksi = 'simpan_sementara';
    }

    /* ---------------- Simpan data sementara (formulir + berkas) ---------------- */
    if ($aksi === 'simpan_sementara') {
        $pesan = [];
        $galat = false;

        if ($bolehSimpanData) {
            $hasil = siswa_simpan_data_diri($siswa['id'], $isianDataDiri());
            $pesan[] = $hasil['pesan'];
            if (empty($hasil['ok'])) {
                $galat = true;
            }
        }

        $hasilDok = $prosesUnggahan();
        if ($hasilDok['ada']) {
            if ($hasilDok['pesan'] !== '') {
                $pesan[] = $hasilDok['pesan'];
            }
            if (!$hasilDok['ok']) {
                $galat = true;
            }
        }

        if (!$pesan) {
            flash('error', 'Tidak ada yang dapat disimpan — data sudah terkunci dan belum ada berkas dipilih.');
        } else {
            flash($galat ? 'error' : 'sukses', implode(' ', $pesan) . ($galat ? '' : ' Perubahan belum dikirim ke panitia.'));
        }
        $kembali();
    }

    /* ---------------- Ajukan verifikasi: data diri + berkas sekaligus ---------------- */
    if ($aksi === 'ajukan_verifikasi') {
        $tahapKini = (string) ($siswa['tahap'] ?? '');
        $bolehDok  = dokumen_tahap_boleh_unggah($tahapKini);
        if (!$bolehSimpanData && !$bolehDok) {
            flash('error', 'Data dan berkas sudah diajukan / terkunci. Tidak ada yang perlu dikirim.');
            $kembali();
        }

        /* Simpan dulu seluruh perubahan — gagal validasi berarti batal mengajukan. */
        if ($bolehSimpanData) {
            $hasil = siswa_simpan_data_diri($siswa['id'], $isianDataDiri());
            if (empty($hasil['ok'])) {
                flash('error', $hasil['pesan']);
                $kembali();
            }
        }
        $hasilDok = $prosesUnggahan();
        if ($hasilDok['ada'] && !$hasilDok['ok']) {
            flash('error', $hasilDok['pesan'] . ' Perbaiki berkas sebelum mengajukan verifikasi.');
            $kembali();
        }

        /* Periksa ulang kelengkapan dari database sebelum mengirim apa pun,
         * agar data dan berkas terkirim bersamaan, bukan sebagian. */
        $kurang = [];
        if ($bolehSimpanData) {
            $detail = pendaftar_data_detail($siswa['id']);
            if ($detail) {
                $kurang = pendaftar_data_kurang($detail);
            }
        }
        if ($bolehDok) {
            $revisi = $tahapKini === 'BERKAS_REVISI';
            foreach (dokumen_siswa($siswa['id']) as $d) {
                if (empty($d['wajib'])) {
                    continue;
                }
                if (empty($d['file_url'])) {
                    $kurang[] = 'Dokumen: ' . $d['nama'];
                } elseif ($revisi && ($d['status'] ?? '') === 'REJECTED') {
                    $kurang[] = 'Dokumen: ' . $d['nama'] . ' (ditolak, belum diunggah ulang)';
                }
            }
        }
        if ($kurang) {
            flash('error', 'Belum dapat diajukan — lengkapi terlebih dahulu: ' . implode(', ', $kurang) . '. Perubahan sudah tersimpan sementara.');
            $kembali();
        }

        $pesanSukses = [];
        if ($bolehSimpanData) {
            $hasil = siswa_ajukan_verifikasi_data($siswa['id']);
            if (empty($hasil['ok'])) {
                flash('error', $hasil['pesan']);
                $kembali();
            }
            $pesanSukses[] = 'Data diri diajukan untuk verifikasi.';
        }
        if ($bolehDok) {
            $hasil = dokumen_submit($siswa['id']);
            if (empty($hasil['ok'])) {
                $pesan = $hasil['pesan'] ?? 'Gagal mengirim berkas.';
                if (!empty($hasil['kurang'])) {
                    $pesan .= ' Kurang: ' . implode(', ', $hasil['kurang']) . '.';
                }
                flash('error', $pesan);
                $kembali();
            }
            $pesanSukses[] = 'Berkas dikirim untuk diverifikasi panitia.';
        }
        flash('sukses', implode(' ', $pesanSukses));
        $kembali();
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
        $kembali();
    }

    if ($aksi === 'batalkan_koreksi') {
        $id = (string) ($_POST['koreksi_id'] ?? '');
        $ok = $id !== '' && koreksi_batalkan($id, $siswa['id']);
        flash($ok ? 'sukses' : 'error', $ok ? 'Pengajuan koreksi dibatalkan.' : 'Pengajuan tidak dapat dibatalkan.');
        $kembali();
    }

    flash('error', 'Aksi tidak dikenali.');
    $kembali();
}

$flash = flash();

/* Status unggah berkas mengikuti tahap — BAYAR_TERVERIFIKASI dan
 * AKUN_TERKIRIM dibuka agar siswa yang sudah membayar / sudah menerima akun
 * dapat langsung melengkapi berkas. */
$tahapSekarang = (string) ($siswa['tahap'] ?? '');
$dokTerkunci   = in_array($tahapSekarang, ['BERKAS_DISUBMIT', 'BERKAS_TERVERIFIKASI', 'JADWAL_DITETAPKAN', 'NILAI_TERINPUT', 'DIUMUMKAN'], true);
$modeRevisi    = $tahapSekarang === 'BERKAS_REVISI';
$bolehAksiDok  = dokumen_tahap_boleh_unggah($tahapSekarang);

/* Pecah kekurangan menjadi butir data diri vs dokumen untuk menentukan
 * apakah tombol "Ajukan Verifikasi Berkas" boleh aktif. */
$kurangData = [];
$kurangDok  = [];
foreach ($kelengkapan['kurang'] as $k) {
    if (strpos((string) $k, 'Dokumen: ') === 0) {
        $kurangDok[] = $k;
    } else {
        $kurangData[] = $k;
    }
}
$dokRevisiKurang = [];
if ($modeRevisi) {
    foreach ($dokumen as $d) {
        /* REJECTED yang masih punya file = belum diunggah ulang. Yang sudah
         * dihapus (file_url kosong) sudah tercakup di $kurangDok. */
        if (($d['status'] ?? '') === 'REJECTED' && !empty($d['file_url'])) {
            $dokRevisiKurang[] = 'Dokumen: ' . $d['nama'] . ' (ditolak, belum diunggah ulang)';
        }
    }
}

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

/* Ada kartu berkas yang boleh diunggah/ganti? */
$adaBolehUnggah = false;
foreach ($dokumen as $dokCek) {
    if ($bolehAksiDok && (!$modeRevisi || $dokCek['status'] === 'REJECTED')) {
        $adaBolehUnggah = true;
        break;
    }
}

/* Form utama + dua tombol aksi tampil bila masih ada yang bisa dilakukan. */
$bolehAksi = $bolehSimpanData || $bolehAksiDok;
$ajukanSiap = (!$bolehSimpanData || !$kurangData)
           && (!$bolehAksiDok || (!$kurangDok && !$dokRevisiKurang));
$daftarKurangAksi = array_merge(
    $bolehSimpanData ? $kurangData : [],
    $bolehAksiDok ? array_merge($kurangDok, $dokRevisiKurang) : []
);

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

      <!-- Navigasi cepat dalam satu halaman (bukan tab terpisah) -->
      <nav class="mt-6 card p-2 flex gap-1.5 overflow-x-auto scroll-thin" aria-label="Bagian data siswa">
        <a href="#data-diri" class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[13px] font-semibold whitespace-nowrap text-ink-500 hover:bg-canvas">
          <?= icon('badge', 'text-[18px]') ?> Data Diri
        </a>
        <a href="#dokumen" class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2.5 text-[13px] font-semibold whitespace-nowrap text-ink-500 hover:bg-canvas">
          <?= icon('folder_shared', 'text-[18px]') ?> Dokumen Persyaratan
        </a>
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

      <?php if ($bolehAksi): ?>
        <?php /* SATU FORM untuk data diri + dokumen; tombol aksi di paling bawah. */ ?>
        <form method="post" action="<?= url('app/siswa/data-siswa.php') ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
      <?php endif; ?>

      <?php /* ================= DATA DIRI ================= */ ?>
        <section id="data-diri" class="mt-5 card p-5 sm:p-6">
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
              <div class="mt-5 grid gap-4 sm:grid-cols-2">
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
        </section>

      <?php /* ================= DOKUMEN ================= */ ?>
        <section id="dokumen" class="mt-5 card p-5 sm:p-6">
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
                    <?php if (!empty($d['file_url']) && ($d['status'] ?? '') === 'REJECTED'): ?>
                      <button type="submit" name="hapus_dokumen" value="<?= e($d['dok_id']) ?>" formnovalidate
                              class="mt-2 inline-flex items-center gap-1 text-[12px] font-semibold text-red-600 hover:underline"
                              onclick="return confirm('Hapus unggahan berkas yang ditolak ini? Setelah dihapus, pilih berkas pengganti lalu simpan.');">
                        <?= icon('delete', 'text-[15px]') ?> Hapus berkas ini
                      </button>
                    <?php endif; ?>
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

          <?php if ($dokTerkunci): ?>
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
          <?php else: ?>
            <p class="mt-5 text-[13px] text-ink-500 border-t border-line-soft pt-4">
              Wajib: <strong class="text-ink-900"><?= $jmlWajibTerisi ?>/<?= $jmlWajib ?></strong> berkas wajib sudah terunggah.
              Pilih berkas di atas, lalu gunakan tombol aksi di bagian paling bawah halaman.
            </p>
          <?php endif; ?>
        </section>

        <?php if ($bolehAksi): ?>
          <?php /* ================= BILAH AKSI: dua tombol untuk data diri + berkas ================= */ ?>
          <div class="mt-6 rounded-2xl border border-primary/30 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div class="min-w-0">
                <p class="text-sm font-bold text-ink-900">Aksi Halaman Ini</p>
                <p class="mt-1 text-[13px] text-ink-500">
                  <?php if (!$ajukanSiap): ?>
                    Lengkapi dulu: <?= e(implode(', ', $daftarKurangAksi)) ?>.
                  <?php else: ?>
                    <strong class="text-ink-700">Simpan Data Sementara</strong> menyimpan isian formulir &amp; berkas terpilih tanpa mengirim.
                    <strong class="text-ink-700">Ajukan Verifikasi Berkas</strong> menyimpan semuanya lalu mengirim data diri + berkas ke panitia sekaligus.
                  <?php endif; ?>
                </p>
              </div>
              <div class="flex flex-col sm:flex-row gap-2.5 shrink-0">
                <button type="submit" name="aksi" value="simpan_sementara" class="btn btn-outline">
                  <?= icon('save') ?> Simpan Data Sementara
                </button>
                <?php if ($ajukanSiap): ?>
                  <button type="submit" name="aksi" value="ajukan_verifikasi" class="btn btn-primary shadow-drop"
                          onclick="return confirm('Kirim data diri dan seluruh berkas untuk diverifikasi panitia? Data akan terkunci setelah diverifikasi.');">
                    <?= icon('send') ?> Ajukan Verifikasi Berkas
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-primary opacity-50 pointer-events-none" disabled>
                    <?= icon('send') ?> Ajukan Verifikasi Berkas
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </form>
        <?php else: ?>
          <div class="mt-6 rounded-2xl bg-canvas border border-line px-5 py-4 text-[13px] text-ink-500">
            <?php if ($dokTerkunci): ?>
              Data dan berkas sudah diajukan. Tidak ada aksi di halaman ini — pantau status melalui menu PPDB &amp; Seleksi.
            <?php else: ?>
              Pengajuan verifikasi dan unggah berkas dibuka setelah pembayaran formulir diverifikasi panitia.
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (($siswa['status_verifikasi'] ?? '') === 'TERVERIFIKASI' || $koreksi): ?>
          <?php /* ================= KOREKSI DATA (form terpisah di luar form utama) ================= */ ?>
          <section id="koreksi" class="mt-5 card p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <div>
                <h2 class="font-bold text-ink-900">Koreksi Data</h2>
                <p class="mt-1 text-[13px] text-ink-500">
                  Bila ada data yang keliru, ajukan koreksi — panitia akan meninjau, dan data TIDAK berubah otomatis.
                </p>
              </div>
              <span class="badge badge-gray">Data Terkunci</span>
            </div>

            <?php if (($siswa['status_verifikasi'] ?? '') === 'TERVERIFIKASI'): ?>
              <form method="post" action="<?= url('app/siswa/data-siswa.php') ?>" class="mt-5 grid gap-3">
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
                    <p class="hint">Minimal 10 karakter. Lampirkan bukti pendukung melalui bagian Dokumen bila diperlukan.</p>
                  </div>
                </div>
                <div class="flex justify-end">
                  <button type="submit" class="btn btn-outline"><?= icon('edit_note') ?> Ajukan Koreksi</button>
                </div>
              </form>
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
                          <form method="post" action="<?= url('app/siswa/data-siswa.php') ?>"
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

    <?php endif; ?>
  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<?php require __DIR__ . '/../../includes/partials/pratinjau-dokumen.php'; ?>
</body>
</html>
