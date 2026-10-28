<?php
/* ==================================================================
 * PENGATURAN SEKOLAH & SPMB (CMS)
 * Halaman admin untuk mengatur seluruh data sekolah & SPMB.
 *
 * YANG SERING DIUBAH (cari komentar [BAGIAN: ...] di bawah):
 *   - Identitas Sekolah   → nama, alamat, WA, email, logo, banner
 *   - Warna Tema          → warna utama website
 *   - Biaya & Rekening    → biaya formulir, SPP, uang masuk, bank
 *   - Jadwal Gelombang    → tahun ajaran, gelombang, tanggal buka/tutup
 *   - Jalur Pendaftaran   → centang jalur yang aktif
 *   - WhatsApp Fonnte     → token API untuk kirim WA otomatis
 *   - Pengaturan Lainnya  → estimasi, deskripsi gelombang
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';

/* Hak akses: hanya operator/admin yang boleh buka halaman ini. */
require_role(['operator', 'admin']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title = 'Pengaturan Sekolah & SPMB';
$desc  = 'CMS pengaturan sekolah: identitas, warna tema, biaya, rekening, dan jadwal gelombang SPMB.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* ------------------------------------------------------------------
 * Resize otomatis gambar upload agar ukuran file & dimensi tidak
 * berlebihan — logo maks 512px, banner maks 1920px (sisi terpanjang).
 * ------------------------------------------------------------------ */
function gambar_resize_otomatis(string $path, int $maksSisi, int $kualitas = 85): bool
{
    if (!extension_loaded('gd') || !is_file($path)) {
        return false;
    }

    $info = @getimagesize($path);
    if ($info === false) {
        return false;
    }

    [$lebar, $tinggi] = $info;
    if ($lebar <= $maksSisi && $tinggi <= $maksSisi) {
        return false; /* tidak perlu diubah */
    }

    $skala = $maksSisi / max($lebar, $tinggi);
    $barul = (int) round($lebar * $skala);
    $barut = (int) round($tinggi * $skala);

    switch ($info['mime']) {
        case 'image/jpeg':
            $sumber = @imagecreatefromjpeg($path);
            break;
        case 'image/png':
            $sumber = @imagecreatefrompng($path);
            break;
        case 'image/webp':
            $sumber = @imagecreatefromwebp($path);
            break;
        default:
            return false;
    }
    if ($sumber === false) {
        return false;
    }

    $hasil = imagecreatetruecolor($barul, $barut);
    if ($info['mime'] === 'image/png' || $info['mime'] === 'image/webp') {
        imagealphablending($hasil, false);
        imagesavealpha($hasil, true);
    }
    imagecopyresampled($hasil, $sumber, 0, 0, 0, 0, $barul, $barut, $lebar, $tinggi);

    switch ($info['mime']) {
        case 'image/jpeg':
            $ok = imagejpeg($hasil, $path, $kualitas);
            break;
        case 'image/png':
            $ok = imagepng($hasil, $path, (int) round((100 - $kualitas) / 10) * 1); /* 0–9 */
            break;
        case 'image/webp':
            $ok = imagewebp($hasil, $path, $kualitas);
            break;
        default:
            $ok = false;
    }

    imagedestroy($sumber);
    imagedestroy($hasil);
    return (bool) $ok;
}

/* ==================================================================
 * Nilai awal formulir dari CMS (pengaturan_sekolah id = 1)
 * ================================================================== */
$f = [
    'nama_sekolah'         => $PENGATURAN['nama_sekolah'],
    'alamat_sekolah'       => $PENGATURAN['alamat_sekolah'],
    'kontak_wa_panitia'    => $PENGATURAN['kontak_wa_panitia'],
    'email_sekolah'        => $PENGATURAN['email_sekolah'],
    'ticker_header'        => $PENGATURAN['ticker'],
    'profil_singkat'       => $PENGATURAN['profil_singkat'] ?? '',
    'warna_utama'          => $PENGATURAN['warna_utama'],
    'biaya_formulir'       => $PENGATURAN['biaya_formulir'],
    'biaya_spp'            => $PENGATURAN['biaya_spp'] ?? 0,
    'estimasi_uang_masuk'  => $PENGATURAN['estimasi_uang_masuk'] ?? 0,
    'nama_bank'            => $PENGATURAN['nama_bank'],
    'no_rekening'          => $PENGATURAN['no_rekening'],
    'atas_nama_rekening'   => $PENGATURAN['atas_nama_rekening'],
    'tahun_ajaran'         => $PENGATURAN['tahun_ajaran'],
    'gelombang'            => $PENGATURAN['gelombang'],
    'tanggal_buka_ppdb'    => $PENGATURAN['tanggal_buka_ppdb'] ?? '',
    'tanggal_tutup_ppdb'   => $PENGATURAN['tanggal_tutup_ppdb'] ?? '',
    'tanggal_pengumuman'   => $PENGATURAN['tanggal_pengumuman'] ?? '',
    'deskripsi_gelombang'  => $PENGATURAN['deskripsi_gelombang'] ?? '',
    'fonnte_token'         => $PENGATURAN['fonnte_token'] ?? '',
    'midtrans_server_key'  => $PENGATURAN['midtrans_server_key'] ?? '',
    'midtrans_client_key'  => $PENGATURAN['midtrans_client_key'] ?? '',
    'midtrans_sandbox'     => (int) ($PENGATURAN['midtrans_sandbox'] ?? 1),
    'bayar_gateway_aktif'  => (int) ($PENGATURAN['bayar_gateway_aktif'] ?? 0),
    'bayar_transfer_aktif' => (int) ($PENGATURAN['bayar_transfer_aktif'] ?? 1),
    'bayar_qris_aktif'     => (int) ($PENGATURAN['bayar_qris_aktif'] ?? 0),
    'qris_merchant'        => $PENGATURAN['qris_merchant'] ?? '',
    'qris_image_url'       => $PENGATURAN['qris_image_url'] ?? '',
    'kirim_akun_otomatis'  => (int) ($PENGATURAN['kirim_akun_otomatis'] ?? 0),
    'notif_wa_operator'    => $PENGATURAN['notif_wa_operator'] ?? '',
    'notif_wa_aktif'       => (int) ($PENGATURAN['notif_wa_aktif'] ?? 0),
    'notif_email'          => $PENGATURAN['notif_email'] ?? '',
    'syarat_ketentuan'     => $PENGATURAN['syarat_ketentuan'] ?? '',
    'syarat_versi'         => (int) ($PENGATURAN['syarat_versi'] ?? 1),
    'hero_banner_url'      => $PENGATURAN['hero_banner_url'] ?? '',
    'login_banner_url'     => $PENGATURAN['login_banner_url'] ?? '',
    'deskripsi_sekolah'    => $PENGATURAN['deskripsi_sekolah'] ?? '',
    'tagline'              => $PENGATURAN['tagline'] ?? '',
    'label_uang_pangkal'   => $PENGATURAN['label_uang_pangkal'] ?? '',
    'label_spp'            => $PENGATURAN['label_spp'] ?? '',
    'jalur_aktif'          => $PENGATURAN['jalur_aktif'] ?? '',
];

$galat   = [];
$flashx  = null;

/* ==================================================================
 * Proses simpan (POST)
 * ================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.');
        header('Location: ' . url('app/operator/pengaturan.php'));
        exit;
    }

    /* Ambil nilai form (array dilewati — contoh: jalur_aktif[] diproses terpisah). */
    foreach (array_keys($f) as $kunci) {
        if (array_key_exists($kunci, $_POST) && !is_array($_POST[$kunci])) {
            $f[$kunci] = trim((string) $_POST[$kunci]);
        }
    }

    /* Jalur aktif: checkbox[] jalur_aktif[] — wajib minimal 1. */
    $jalurDipilih = array_values(array_intersect(
        (array) ($_POST['jalur_aktif'] ?? []),
        array_keys(jalur_katalog())
    ));
    if (!$jalurDipilih) {
        $galat[] = 'Aktifkan minimal satu jalur pendaftaran.';
    }
    $f['jalur_aktif'] = json_encode($jalurDipilih, JSON_UNESCAPED_UNICODE);

    /* Metode pembayaran: checkbox bayar_*_aktif — wajib minimal 1 aktif. */
    $f['bayar_gateway_aktif']  = empty($_POST['bayar_gateway_aktif'])  ? 0 : 1;
    $f['bayar_transfer_aktif'] = empty($_POST['bayar_transfer_aktif']) ? 0 : 1;
    $f['bayar_qris_aktif']     = empty($_POST['bayar_qris_aktif'])     ? 0 : 1;
    $f['kirim_akun_otomatis']  = empty($_POST['kirim_akun_otomatis'])  ? 0 : 1;

    /* [Fase 10] Gateway opsional — paksa nonaktif selama API key belum
     * dikonfigurasi; auto-kirim akun (hanya jalan via callback gateway)
     * ikut dimatikan karena tidak akan pernah terpicu tanpa gateway. */
    if (!midtrans_aktif()) {
        $f['bayar_gateway_aktif'] = 0;
        $f['kirim_akun_otomatis'] = 0;
    }
    if (!$f['bayar_gateway_aktif'] && !$f['bayar_transfer_aktif'] && !$f['bayar_qris_aktif']) {
        $galat[] = 'Aktifkan minimal satu metode pembayaran.';
    }

    /* QRIS merchant (opsional, hanya label tampilan). */
    $f['qris_merchant'] = trim((string) ($_POST['qris_merchant'] ?? ''));

    /* Notifikasi WhatsApp operator (Fase 3). */
    $f['notif_wa_aktif']    = empty($_POST['notif_wa_aktif']) ? 0 : 1;
    $f['notif_wa_operator'] = trim((string) ($_POST['notif_wa_operator'] ?? ''));
    if ($f['notif_wa_aktif']) {
        if ($f['notif_wa_operator'] === '') {
            $galat[] = 'Notifikasi WhatsApp aktif tetapi daftar nomor tujuan masih kosong.';
        } else {
            foreach (preg_split('/[,;]+/', $f['notif_wa_operator']) as $noWa) {
                $noWa = trim($noWa);
                if ($noWa === '' || !preg_match('/^\+?[0-9]{6,16}$/', $noWa)) {
                    $galat[] = 'Format nomor WhatsApp operator tidak valid. Pisahkan beberapa nomor dengan koma.';
                    break;
                }
            }
        }
    }

    /* QRIS aktif → wajib punya gambar (baru diupload ATAU sudah ada, dan tidak direset).
     * Catatan: blok ini dieksekusi sebelum proses upload, jadi cek $_FILES langsung. */
    $qrisReset = !empty($_POST['qris_reset']);
    $qrisBaruAda = !empty($_FILES['qris_image']['name']);
    $qrisLamaAda = !$qrisReset && $f['qris_image_url'] !== '';
    if ($f['bayar_qris_aktif'] && !$qrisBaruAda && !$qrisLamaAda) {
        $galat[] = 'QRIS aktif tetapi gambar belum diunggah. Unggah gambar QRIS atau nonaktifkan metode QRIS.';
    }

    /* S&K: ambil teks dari textarea. Bila kosong, pertahankan nilai lama
     * agar teks bawaan tidak hilang karena POST parsial / API call. */
    $sakLama = trim((string) ($PENGATURAN['syarat_ketentuan'] ?? ''));
    $sakBaru = trim((string) ($_POST['syarat_ketentuan'] ?? ''));
    $f['syarat_ketentuan'] = $sakBaru !== '' ? $sakBaru : $sakLama;
    $f['syarat_versi']     = (int) ($PENGATURAN['syarat_versi'] ?? 1);
    if ($sakBaru !== '' && $sakBaru !== $sakLama) {
        $f['syarat_versi']++; /* Auto-bump versi saat teks berubah. */
    }

    /* Validasi */
    if ($f['nama_sekolah'] === '') {
        $galat[] = 'Nama sekolah wajib diisi.';
    }
    if (!warna_valid($f['warna_utama'])) {
        $galat[] = 'Warna utama harus berupa kode hex seperti #0056B3.';
    } else {
        $f['warna_utama'] = strtoupper($f['warna_utama']);
    }
    if ($f['email_sekolah'] !== '' && !filter_var($f['email_sekolah'], FILTER_VALIDATE_EMAIL)) {
        $galat[] = 'Format email tidak valid.';
    }
    if (!preg_match('/^\+?[0-9\s-]{6,20}$/', $f['kontak_wa_panitia'])) {
        $galat[] = 'Kontak WhatsApp hanya boleh berisi angka (6-20 digit).';
    }
    foreach (['biaya_formulir', 'biaya_spp', 'estimasi_uang_masuk'] as $nominal) {
        if ($f[$nominal] === '' || !is_numeric($f[$nominal]) || (float) $f[$nominal] < 0) {
            $galat[] = 'Nilai "' . str_replace('_', ' ', $nominal) . '" harus angka >= 0.';
        }
    }
    foreach (['tanggal_buka_ppdb', 'tanggal_tutup_ppdb', 'tanggal_pengumuman'] as $tgl) {
        if ($f[$tgl] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f[$tgl])) {
            $galat[] = 'Format tanggal tidak valid.';
        }
    }
    if (strlen($f['nama_sekolah']) > 150) {
        $galat[] = 'Nama sekolah maksimal 150 karakter.';
    }

    /* ------------------------------------------------------------
     * Upload logo (opsional) — JPG/PNG/WEBP/SVG, maks 2MB
     * ------------------------------------------------------------ */
    $logoBaru = null;
    if (!empty($_FILES['logo']['name'])) {
        $berkas = $_FILES['logo'];
        $izinEkstensi  = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        $izinMime      = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];

        if ($berkas['error'] !== UPLOAD_ERR_OK && $berkas['error'] !== UPLOAD_ERR_NO_FILE) {
            $galat[] = 'Upload logo gagal (kode ' . (int) $berkas['error'] . ').';
        } else {
            $ekstensi = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
            if (!in_array($ekstensi, $izinEkstensi, true)) {
                $galat[] = 'Logo harus berformat JPG, PNG, WEBP, atau SVG.';
            } elseif ($berkas['size'] > 2 * 1024 * 1024) {
                $galat[] = 'Ukuran logo maksimal 2 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = (string) $finfo->file($berkas['tmp_name']);
                if (!in_array($mime, $izinMime, true)) {
                    $galat[] = 'Isi berkas bukan gambar yang sah.';
                } else {
                    $folder = dirname(__DIR__, 2) . '/assets/img/uploads';
                    if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
                        $galat[] = 'Folder upload tidak dapat dibuat.';
                    } else {
                        $namaFile = 'logo-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ekstensi;
                        if (!move_uploaded_file($berkas['tmp_name'], $folder . '/' . $namaFile)) {
                            $galat[] = 'Berkas logo gagal disimpan ke server.';
                        } else {
                            /* Resize otomatis agar ukuran file tidak berlebihan (SVG dilewati). */
                            if ($ekstensi !== 'svg') {
                                gambar_resize_otomatis($folder . '/' . $namaFile, 512, 88);
                            }
                            $logoBaru = 'assets/img/uploads/' . $namaFile;
                        }
                    }
                }
            }
        }
    }

    /* Logo di-reset ke bawaan? */
    $logoReset = !empty($_POST['logo_reset']);
    if ($logoReset) {
        $logoBaru = 'assets/img/uploads/logo-sekolah.png';
    }

    /* ------------------------------------------------------------
     * Upload banner hero (opsional) — JPG/PNG/WEBP, maks 4MB
     * ------------------------------------------------------------ */
    $bannerBaru = null;
    if (!empty($_FILES['hero_banner']['name'])) {
        $berkas = $_FILES['hero_banner'];
        $izinEkstensi  = ['jpg', 'jpeg', 'png', 'webp'];
        $izinMime      = ['image/jpeg', 'image/png', 'image/webp'];

        if ($berkas['error'] !== UPLOAD_ERR_OK && $berkas['error'] !== UPLOAD_ERR_NO_FILE) {
            $galat[] = 'Upload banner gagal (kode ' . (int) $berkas['error'] . ').';
        } else {
            $ekstensi = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
            if (!in_array($ekstensi, $izinEkstensi, true)) {
                $galat[] = 'Banner harus berformat JPG, PNG, atau WEBP.';
            } elseif ($berkas['size'] > 4 * 1024 * 1024) {
                $galat[] = 'Ukuran banner maksimal 4 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = (string) $finfo->file($berkas['tmp_name']);
                if (!in_array($mime, $izinMime, true)) {
                    $galat[] = 'Isi berkas banner bukan gambar yang sah.';
                } else {
                    $folder = dirname(__DIR__, 2) . '/assets/img/uploads';
                    if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
                        $galat[] = 'Folder upload tidak dapat dibuat.';
                    } else {
                        $namaFile = 'banner-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ekstensi;
                        if (!move_uploaded_file($berkas['tmp_name'], $folder . '/' . $namaFile)) {
                            $galat[] = 'Berkas banner gagal disimpan ke server.';
                        } else {
                            /* Resize otomatis: sisi terpanjang maks 1920px. */
                            gambar_resize_otomatis($folder . '/' . $namaFile, 1920, 82);
                            $bannerBaru = 'assets/img/uploads/' . $namaFile;
                        }
                    }
                }
            }
        }
    }

    /* Banner di-reset (hapus)? */
    $bannerReset = !empty($_POST['hero_banner_reset']);
    if ($bannerReset) {
        $bannerBaru = '';
    }

    /* ------------------------------------------------------------
     * Upload banner halaman login (login.php / login-sekolah.php)
     * ------------------------------------------------------------ */
    $loginBannerBaru = null;
    if (!empty($_FILES['login_banner']['name'])) {
        $berkas = $_FILES['login_banner'];
        $izinEkstensi  = ['jpg', 'jpeg', 'png', 'webp'];
        $izinMime      = ['image/jpeg', 'image/png', 'image/webp'];

        if ($berkas['error'] !== UPLOAD_ERR_OK && $berkas['error'] !== UPLOAD_ERR_NO_FILE) {
            $galat[] = 'Upload banner login gagal (kode ' . (int) $berkas['error'] . ').';
        } else {
            $ekstensi = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
            if (!in_array($ekstensi, $izinEkstensi, true)) {
                $galat[] = 'Banner login harus berformat JPG, PNG, atau WEBP.';
            } elseif ($berkas['size'] > 4 * 1024 * 1024) {
                $galat[] = 'Ukuran banner login maksimal 4 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = (string) $finfo->file($berkas['tmp_name']);
                if (!in_array($mime, $izinMime, true)) {
                    $galat[] = 'Isi berkas banner login bukan gambar yang sah.';
                } else {
                    $folder = dirname(__DIR__, 2) . '/assets/img/uploads';
                    if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
                        $galat[] = 'Folder upload tidak dapat dibuat.';
                    } else {
                        $namaFile = 'login-banner-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ekstensi;
                        if (!move_uploaded_file($berkas['tmp_name'], $folder . '/' . $namaFile)) {
                            $galat[] = 'Berkas banner login gagal disimpan ke server.';
                        } else {
                            gambar_resize_otomatis($folder . '/' . $namaFile, 1920, 82);
                            $loginBannerBaru = 'assets/img/uploads/' . $namaFile;
                        }
                    }
                }
            }
        }
    }
    $loginBannerReset = !empty($_POST['login_banner_reset']);
    if ($loginBannerReset) {
        $loginBannerBaru = '';
    }

    /* ------------------------------------------------------------
     * Upload gambar QRIS (opsional) — JPG/PNG, maks 2MB
     * ------------------------------------------------------------ */
    $qrisBaru = null;
    if (!empty($_FILES['qris_image']['name'])) {
        $berkas = $_FILES['qris_image'];
        $izinEkstensi  = ['jpg', 'jpeg', 'png'];
        $izinMime      = ['image/jpeg', 'image/png'];

        if ($berkas['error'] !== UPLOAD_ERR_OK && $berkas['error'] !== UPLOAD_ERR_NO_FILE) {
            $galat[] = 'Upload QRIS gagal (kode ' . (int) $berkas['error'] . ').';
        } else {
            $ekstensi = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
            if (!in_array($ekstensi, $izinEkstensi, true)) {
                $galat[] = 'Gambar QRIS harus berformat JPG atau PNG.';
            } elseif ($berkas['size'] > 2 * 1024 * 1024) {
                $galat[] = 'Ukuran gambar QRIS maksimal 2 MB.';
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime  = (string) $finfo->file($berkas['tmp_name']);
                if (!in_array($mime, $izinMime, true)) {
                    $galat[] = 'Isi berkas QRIS bukan gambar yang sah.';
                } elseif (@getimagesize($berkas['tmp_name']) === false) {
                    $galat[] = 'Berkas QRIS bukan gambar valid.';
                } else {
                    $folder = dirname(__DIR__, 2) . '/assets/img/uploads';
                    if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
                        $galat[] = 'Folder upload tidak dapat dibuat.';
                    } else {
                        $namaFile = 'qris-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.' . $ekstensi;
                        if (!move_uploaded_file($berkas['tmp_name'], $folder . '/' . $namaFile)) {
                            $galat[] = 'Berkas QRIS gagal disimpan ke server.';
                        } else {
                            gambar_resize_otomatis($folder . '/' . $namaFile, 1024, 1024);
                            $qrisBaru = 'assets/img/uploads/' . $namaFile;
                        }
                    }
                }
            }
        }
    }
    /* Reset menang atas upload bila keduanya dikirim. */
    if ($qrisReset) {
        $qrisBaru = '';
    }

    /* ------------------------------------------------------------
     * Simpan ke database
     * ------------------------------------------------------------ */
    if (!$galat) {
        $db = db();
        if ($db === null) {
            flash('error', 'Database tidak aktif — perubahan tidak disimpan.');
            header('Location: ' . url('app/operator/pengaturan.php'));
            exit;
        }

        $logoAkhir = $logoBaru !== null ? $logoBaru : ($PENGATURAN['logo_url'] ?? '');
        /* $PENGATURAN['logo_url'] sudah absolut — kembalikan ke bentuk relatif. */
        if ($logoBaru === null) {
            $logoAkhir = 'assets/img/uploads/logo-sekolah.png';
            foreach (['assets/img/uploads/', 'assets/img/'] as $prefix) {
                $cari = BASE_URL . '/' . $prefix;
                if (strpos($PENGATURAN['logo_url'], $cari) === 0) {
                    $logoAkhir = $prefix . substr($PENGATURAN['logo_url'], strlen($cari));
                    break;
                }
            }
        }

        $bannerAkhir = $bannerBaru !== null ? $bannerBaru : ($f['hero_banner_url'] !== '' ? $f['hero_banner_url'] : null);
        /* $f['hero_banner_url'] dari DB sudah absolut — kembalikan ke bentuk relatif. */
        if ($bannerBaru === null && $f['hero_banner_url'] !== '') {
            $bannerAkhir = null;
            $cari = BASE_URL . '/assets/img/uploads/';
            if (strpos($f['hero_banner_url'], $cari) === 0) {
                $bannerAkhir = 'assets/img/uploads/' . substr($f['hero_banner_url'], strlen($cari));
            }
        }

        try {
            /* Login banner: konversi path absolut -> relatif untuk disimpan ke DB. */
            $loginBannerAkhir = $loginBannerBaru !== null
                ? $loginBannerBaru
                : ($f['login_banner_url'] !== '' ? $f['login_banner_url'] : null);
            if ($loginBannerBaru === null && $f['login_banner_url'] !== '') {
                $loginBannerAkhir = null;
                $cariLB = BASE_URL . '/assets/img/uploads/';
                if (strpos($f['login_banner_url'], $cariLB) === 0) {
                    $loginBannerAkhir = 'assets/img/uploads/' . substr($f['login_banner_url'], strlen($cariLB));
                }
            }

            /* QRIS: upload baru, reset, atau pertahankan lama. */
            $qrisAkhir = $qrisBaru !== null ? $qrisBaru : ($f['qris_image_url'] !== '' ? $f['qris_image_url'] : null);
            if ($qrisBaru === null && $f['qris_image_url'] !== '') {
                /* Bila tersimpan absolut, kembalikan ke relatif. Bila sudah relatif, pertahankan. */
                if (strpos($f['qris_image_url'], BASE_URL . '/assets/img/uploads/') === 0) {
                    $qrisAkhir = 'assets/img/uploads/' . substr($f['qris_image_url'], strlen(BASE_URL . '/assets/img/uploads/'));
                } elseif (strpos($f['qris_image_url'], 'assets/img/uploads/') === 0) {
                    $qrisAkhir = $f['qris_image_url'];
                } else {
                    $qrisAkhir = null;
                }
            }

            $stmt = $db->prepare(
                'UPDATE pengaturan_sekolah SET
                    nama_sekolah = ?, alamat_sekolah = ?, kontak_wa_panitia = ?, email_sekolah = ?,
                    ticker_header = ?, profil_singkat = ?, deskripsi_sekolah = ?, tagline = ?,
                    warna_utama = ?,
                    biaya_formulir = ?, biaya_spp = ?, estimasi_uang_masuk = ?,
                    label_uang_pangkal = ?, label_spp = ?,
                    nama_bank = ?, no_rekening = ?, atas_nama_rekening = ?,
                    tahun_ajaran = ?, gelombang = ?,
                    tanggal_buka_ppdb = ?, tanggal_tutup_ppdb = ?, tanggal_pengumuman = ?,
                    deskripsi_gelombang = ?, logo_url = ?, hero_banner_url = ?, login_banner_url = ?,
                    jalur_aktif = ?,
                    fonnte_token = ?,
                    midtrans_server_key = ?, midtrans_client_key = ?, midtrans_sandbox = ?,
                    bayar_gateway_aktif = ?, bayar_transfer_aktif = ?, bayar_qris_aktif = ?,
                    qris_image_url = ?, qris_merchant = ?, kirim_akun_otomatis = ?,
                    notif_wa_operator = ?, notif_wa_aktif = ?, notif_email = ?,
                    syarat_ketentuan = ?, syarat_versi = ?
                  WHERE id = 1'
            );
            $stmt->execute([
                $f['nama_sekolah'],
                $f['alamat_sekolah'] !== '' ? $f['alamat_sekolah'] : null,
                $f['kontak_wa_panitia'],
                $f['email_sekolah'] !== '' ? $f['email_sekolah'] : null,
                $f['ticker_header'] !== '' ? $f['ticker_header'] : null,
                $f['profil_singkat'] !== '' ? $f['profil_singkat'] : null,
                $f['deskripsi_sekolah'] !== '' ? $f['deskripsi_sekolah'] : null,
                $f['tagline'] !== '' ? $f['tagline'] : null,
                $f['warna_utama'],
                (float) $f['biaya_formulir'],
                (float) $f['biaya_spp'],
                (float) $f['estimasi_uang_masuk'],
                $f['label_uang_pangkal'] !== '' ? $f['label_uang_pangkal'] : 'Uang Pangkal Gedung',
                $f['label_spp'] !== '' ? $f['label_spp'] : 'SPP Bulanan',
                $f['nama_bank'],
                $f['no_rekening'],
                $f['atas_nama_rekening'],
                $f['tahun_ajaran'],
                $f['gelombang'],
                $f['tanggal_buka_ppdb'] !== '' ? $f['tanggal_buka_ppdb'] : null,
                $f['tanggal_tutup_ppdb'] !== '' ? $f['tanggal_tutup_ppdb'] : null,
                $f['tanggal_pengumuman'] !== '' ? $f['tanggal_pengumuman'] : null,
                $f['deskripsi_gelombang'] !== '' ? $f['deskripsi_gelombang'] : null,
                $logoAkhir,
                $bannerAkhir,
                $loginBannerAkhir,
                $f['jalur_aktif'],
                $f['fonnte_token'] !== '' ? $f['fonnte_token'] : null,
                $f['midtrans_server_key'] !== '' ? $f['midtrans_server_key'] : null,
                $f['midtrans_client_key'] !== '' ? $f['midtrans_client_key'] : null,
                (int) $f['midtrans_sandbox'],
                $f['bayar_gateway_aktif'],
                $f['bayar_transfer_aktif'],
                $f['bayar_qris_aktif'],
                $qrisAkhir,
                $f['qris_merchant'] !== '' ? $f['qris_merchant'] : null,
                $f['kirim_akun_otomatis'],
                $f['notif_wa_operator'] !== '' ? $f['notif_wa_operator'] : null,
                $f['notif_wa_aktif'],
                $f['notif_email'] !== '' ? $f['notif_email'] : null,
                $f['syarat_ketentuan'] !== '' ? $f['syarat_ketentuan'] : null,
                $f['syarat_versi'],
            ]);

            /* Hapus berkas logo lama bila masih berada di folder upload. */
            if ($logoBaru !== null) {
                $lama = $PENGATURAN['logo_url'] ?? '';
                $pos  = strpos($lama, '/assets/img/uploads/');
                if ($pos !== false) {
                    $pathLama = dirname(__DIR__, 2) . substr($lama, $pos);
                    if (is_file($pathLama) && realpath($pathLama) !== ($logoBaru ? realpath(dirname(__DIR__, 2) . '/' . $logoBaru) : null)) {
                        @unlink($pathLama);
                    }
                }
            }

            /* Hapus berkas banner lama bila masih berada di folder upload. */
            if ($bannerBaru !== null && $bannerBaru !== '') {
                $lamaBanner = $f['hero_banner_url'] ?? '';
                $posBanner  = strpos($lamaBanner, '/assets/img/uploads/');
                if ($posBanner !== false) {
                    $pathLamaBanner = dirname(__DIR__, 2) . substr($lamaBanner, $posBanner);
                    if (is_file($pathLamaBanner)) {
                        @unlink($pathLamaBanner);
                    }
                }
            }

            /* Hapus berkas QRIS lama bila di-reset atau diganti. */
            if ($qrisBaru !== null && $qrisBaru !== '') {
                $lamaQris = $f['qris_image_url'] ?? '';
                $posQris  = strpos($lamaQris, '/assets/img/uploads/');
                if ($posQris !== false) {
                    $pathLamaQris = dirname(__DIR__, 2) . substr($lamaQris, $posQris);
                    if (is_file($pathLamaQris) && realpath($pathLamaQris) !== realpath(dirname(__DIR__, 2) . '/' . $qrisBaru)) {
                        @unlink($pathLamaQris);
                    }
                }
            }

            flash('sukses', 'Pengaturan sekolah berhasil disimpan — halaman publik langsung diperbarui.');
        } catch (Throwable $e) {
            flash('error', 'Gagal menyimpan ke database.');
        }

        header('Location: ' . url('app/operator/pengaturan.php'));
        exit;
    }

    $flashx = $galat;
}

/* Palet institusional (mockup CMS) untuk pemilihan cepat warna. */
$palet = ['#0056B3', '#0F766E', '#7C3AED', '#B45309', '#BE123C', '#1F2937'];

/* ------------------------------------------------------------------
 * Render: semua POST selesai di atas, sehingga header() tidak bentrok
 * dengan output HTML.
 * ------------------------------------------------------------------ */
require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'pengaturan';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik', 'Pengaturan Sistem', 'CMS Sekolah'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue"><?= icon('settings', 'text-[13px]') ?> CMS Sekolah</span>
          <span class="badge badge-gray">Baris tunggal: id = 1</span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Pengaturan Sekolah &amp; SPMB</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Perubahan tersimpan ke tabel <span class="font-mono">pengaturan_sekolah</span> dan langsung
          dipakai landing page, tema warna, serta seluruh dashboard.
        </p>
      </div>
      <div class="flex items-center gap-2.5 shrink-0">
        <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline" target="_blank" rel="noopener">
          <?= icon('language') ?> Lihat Landing
        </a>
      </div>
    </div>

    <?php if ($flashx): ?>
      <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
        <p class="font-semibold">Perubahan belum disimpan — perbaiki isian berikut:</p>
        <ul class="mt-2 space-y-1 list-disc list-inside">
          <?php foreach ($flashx as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" action="<?= url('app/operator/pengaturan.php') ?>" class="mt-6 space-y-5">
      <?= csrf_field() ?>

      <!-- ============ 1. IDENTITAS & KONTAK ============ -->
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Identitas &amp; Kontak Institusi</h2>
        <p class="mt-1 text-[13px] text-ink-500">Nama resmi, alamat, WhatsApp panitia, dan email institusi.</p>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <div class="sm:col-span-2">
            <label class="label" for="nama_sekolah">Nama Resmi Sekolah <span class="text-red-600">*</span></label>
            <input id="nama_sekolah" name="nama_sekolah" class="input" maxlength="150" required value="<?= e($f['nama_sekolah']) ?>">
          </div>

          <div class="sm:col-span-2">
            <label class="label" for="alamat_sekolah">Alamat Lengkap Sekolah</label>
            <textarea id="alamat_sekolah" name="alamat_sekolah" class="textarea" rows="2"><?= e($f['alamat_sekolah']) ?></textarea>
          </div>

          <div>
            <label class="label" for="kontak_wa_panitia">Kontak WhatsApp Panitia SPMB</label>
            <input id="kontak_wa_panitia" name="kontak_wa_panitia" class="input font-mono" placeholder="628123456789" value="<?= e($f['kontak_wa_panitia']) ?>">
          </div>

          <div>
            <label class="label" for="email_sekolah">Email Resmi Institusi</label>
            <input id="email_sekolah" name="email_sekolah" type="email" class="input" placeholder="spmb@sekolah.sch.id" value="<?= e($f['email_sekolah']) ?>">
          </div>
        </div>
      </section>

      <!-- ============ 2. LOGO & WARNA TEMA ============ -->
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Aset Visual &amp; Warna Tema</h2>
        <p class="mt-1 text-[13px] text-ink-500">Logo dipakai di landing, login, sidebar, dan footer (maks 2 MB).</p>

        <div class="mt-5 grid gap-5 sm:grid-cols-2">
          <div>
            <label class="label">Logo Saat Ini</label>
            <div class="flex items-center gap-3 rounded-xl border border-line bg-canvas p-3">
              <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo" class="w-14 h-14 rounded-lg bg-white border border-line object-contain p-1">
              <div class="text-[12px] text-ink-500 min-w-0">
                <p class="font-semibold text-ink-900">Pratinjau langsung</p>
                <p class="truncate"><?= e($PENGATURAN['logo_url']) ?></p>
              </div>
            </div>

            <label class="label mt-4" for="logo">Unggah Logo Baru (JPG/PNG/WEBP/SVG)</label>
            <input id="logo" name="logo" type="file" accept=".jpg,.jpeg,.png,.png,.webp,.svg" class="input h-auto py-2 file:mr-3 file:btn file:btn-sm file:bg-canvas file:border-0 file:text-ink-700 file:font-semibold">
            <!-- REKOMENDASI UKURAN LOGO — ubah teks di bawah jika ingin ganti panduan -->
            <div class="hint mt-1.5 space-y-0.5">
              <p><strong>Rekomendasi:</strong> <strong>512 &times; 512 px</strong> (bentuk persegi, latar transparan/putih).</p>
              <p>Format terbaik: <strong>PNG</strong> (transparan) atau SVG. Maks 2 MB.</p>
              <p>Gambar akan otomatis di-resize ke maks 512 px.</p>
            </div>
            <p class="hint mt-1">Kosongkan bila tidak ingin mengganti.</p>

            <label class="mt-3 flex items-center gap-2 text-[13px] text-ink-700">
              <input type="checkbox" name="logo_reset" value="1" class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
              Kembalikan ke logo bawaan (logo-sekolah.png)
            </label>
          </div>

          <div>
            <label class="label">Banner Hero Saat Ini</label>
            <div class="rounded-xl border border-line bg-canvas p-3">
              <?php $bannerSekarang = $f['hero_banner_url']; ?>
              <?php if (!empty($bannerSekarang)): ?>
                <img src="<?= e($bannerSekarang) ?>" alt="Banner Hero" class="w-full h-28 rounded-lg object-cover border border-line">
                <p class="mt-2 text-[12px] text-ink-500 truncate"><?= e($bannerSekarang) ?></p>
              <?php else: ?>
                <div class="w-full h-28 rounded-lg bg-gradient-to-br from-primary/20 to-primary/5 border border-dashed border-line-mid flex items-center justify-center text-[13px] text-ink-400">
                  Belum ada banner — gunakan gradient default
                </div>
              <?php endif; ?>
            </div>

            <label class="label mt-4" for="hero_banner">Unggah Banner Hero (JPG/PNG/WEBP, maks 4 MB)</label>
            <input id="hero_banner" name="hero_banner" type="file" accept=".jpg,.jpeg,.png,.webp" class="input h-auto py-2 file:mr-3 file:btn file:btn-sm file:bg-canvas file:border-0 file:text-ink-700 file:font-semibold">
            <!-- REKOMENDASI UKURAN BANNER — ubah teks di bawah jika ingin ganti panduan -->
            <div class="hint mt-1.5 space-y-0.5">
              <p><strong>Rekomendasi:</strong> <strong>1920 &times; 995 px</strong> (rasio ~2:1, tidak terlalu tinggi).</p>
              <p>Format terbaik: <strong>JPG</strong> (kualitas 80–90, ukuran kecil). Maks 4 MB.</p>
              <p>Gambar akan otomatis di-resize ke lebar maks 1920 px.</p>
            </div>

            <?php if (!empty($bannerSekarang)): ?>
              <label class="mt-3 flex items-center gap-2 text-[13px] text-ink-700">
                <input type="checkbox" name="hero_banner_reset" value="1" class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
                Hapus banner (kembali ke gradient default)
              </label>
            <?php endif; ?>
          </div>

          <!-- BANNER HALAMAN LOGIN — tampil di login.php & login-sekolah.php -->
          <div>
            <?php $loginBannerSekarang = $f['login_banner_url']; ?>
            <label class="label">Banner Halaman Login Saat Ini</label>
            <div class="rounded-xl border border-line bg-canvas p-3">
              <?php if (!empty($loginBannerSekarang)): ?>
                <img src="<?= e($loginBannerSekarang) ?>" alt="Banner Login" class="w-full h-28 rounded-lg object-cover border border-line">
                <p class="mt-2 text-[12px] text-ink-500 truncate"><?= e($loginBannerSekarang) ?></p>
              <?php else: ?>
                <div class="w-full h-28 rounded-lg bg-gradient-to-br from-primary/20 to-primary/5 border border-dashed border-line-mid flex items-center justify-center text-[13px] text-ink-400">
                  Belum ada banner — gunakan gradient default
                </div>
              <?php endif; ?>
            </div>

            <label class="label mt-4" for="login_banner">Unggah Banner Login (JPG/PNG/WEBP, maks 4 MB)</label>
            <input id="login_banner" name="login_banner" type="file" accept=".jpg,.jpeg,.png,.webp" class="input h-auto py-2 file:mr-3 file:btn file:btn-sm file:bg-canvas file:border-0 file:text-ink-700 file:font-semibold">
            <!-- REKOMENDASI UKURAN BANNER LOGIN -->
            <div class="hint mt-1.5 space-y-0.5">
              <p><strong>Rekomendasi:</strong> <strong>1920 &times; 1080 px</strong> (rasio 16:9, portrait/landscape).</p>
              <p>Format terbaik: <strong>JPG</strong> (kualitas 80–90). Maks 4 MB.</p>
              <p>Gambar akan otomatis di-resize ke lebar maks 1920 px.</p>
            </div>

            <?php if (!empty($loginBannerSekarang)): ?>
              <label class="mt-3 flex items-center gap-2 text-[13px] text-ink-700">
                <input type="checkbox" name="login_banner_reset" value="1" class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
                Hapus banner login (kembali ke gradient default)
              </label>
            <?php endif; ?>
          </div>

          <div>
            <label class="label" for="warna_utama">Warna Utama Website <span class="text-red-600">*</span></label>
            <div class="flex items-center gap-3">
              <input id="warna_utama" name="warna_utama" class="input font-mono w-36" maxlength="7" value="<?= e($f['warna_utama']) ?>">
              <input type="color" id="warna-picker" value="<?= e(warna_valid($f['warna_utama']) ? $f['warna_utama'] : '#0056B3') ?>" class="h-10 w-14 rounded-lg border border-line-mid bg-white p-1 cursor-pointer" aria-label="Pilih warna">
              <div class="flex-1 h-10 rounded-lg border border-line flex items-center px-3 text-sm font-semibold text-white"
                   id="warna-preview" style="background-color: <?= e(warna_valid($f['warna_utama']) ? $f['warna_utama'] : '#0056B3') ?>">
                Preview Tombol Utama
              </div>
            </div>

            <p class="label mt-4">Palet Institusional Terstandar</p>
            <div class="flex flex-wrap gap-2">
              <?php foreach ($palet as $w): ?>
                <button type="button" class="h-9 w-9 rounded-lg border-2 border-white shadow-card ring-1 ring-line hover:scale-110 transition"
                        data-warna="<?= e($w) ?>" style="background-color: <?= e($w) ?>" aria-label="Pakai warna <?= e($w) ?>"></button>
              <?php endforeach; ?>
            </div>
            <p class="hint">Warna dipakai untuk tombol, link, badge, dan progress bar seluruh halaman.</p>
          </div>
        </div>
      </section>

      <!-- ============ 3. RUNNING TEXT & PROFIL ============ -->
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Running Text &amp; Profil Singkat</h2>
        <div class="mt-5 space-y-4">
          <div>
            <label class="label" for="ticker_header">Running Text Pengumuman Header (Ticker Bar)</label>
            <textarea id="ticker_header" name="ticker_header" class="textarea" rows="2"><?= e($f['ticker_header']) ?></textarea>
          </div>
          <div>
            <label class="label" for="profil_singkat">Profil Singkat Sekolah (Hero Overview)</label>
            <textarea id="profil_singkat" name="profil_singkat" class="textarea" rows="3"><?= e($f['profil_singkat']) ?></textarea>
          </div>
          <!-- DESKRIPSI SEKOLAH — tampil di hero landing page sebagai paragraf pengantar -->
          <div>
            <label class="label" for="deskripsi_sekolah">Deskripsi Sekolah (Hero Landing)</label>
            <textarea id="deskripsi_sekolah" name="deskripsi_sekolah" class="textarea" rows="2"><?= e($f['deskripsi_sekolah']) ?></textarea>
            <p class="hint">Kalimat pengantar di bawah judul utama landing page, misal: "Membentuk generasi muda yang unggul..."</p>
          </div>
          <!-- TAGLINE — tampil di bawah nama sekolah di header/footer -->
          <div>
            <label class="label" for="tagline">Tagline Sekolah</label>
            <input id="tagline" name="tagline" class="input" value="<?= e($f['tagline']) ?>" placeholder="Berakhlak & Berprestasi">
            <p class="hint">Tampil di bawah nama sekolah pada header & footer, misal: "Berakhlak & Berprestasi".</p>
          </div>
        </div>
      </section>

      <!-- ============ 4. TARIF & BIAYA ============ -->
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Struktur Tarif &amp; Biaya SPMB</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
          <div>
            <label class="label" for="biaya_formulir">Biaya Formulir Pendaftaran <span class="text-red-600">*</span></label>
            <input id="biaya_formulir" name="biaya_formulir" type="number" min="0" step="1000" class="input font-semibold" value="<?= e($f['biaya_formulir']) ?>">
          </div>
          <div>
            <label class="label" for="biaya_spp">Nominal SPP Bulanan</label>
            <input id="biaya_spp" name="biaya_spp" type="number" min="0" step="1000" class="input font-semibold" value="<?= e($f['biaya_spp']) ?>">
          </div>
          <div>
            <label class="label" for="estimasi_uang_masuk">Estimasi Uang Masuk / Pangkal</label>
            <input id="estimasi_uang_masuk" name="estimasi_uang_masuk" type="number" min="0" step="1000" class="input font-semibold" value="<?= e($f['estimasi_uang_masuk']) ?>">
          </div>
        </div>
        <p class="hint">Nilai dipakai langsung pada bagian "Rincian Biaya" di landing page.</p>
        <!-- LABEL BIAYA — ubah nama tampilan tanpa mengubah nominal -->
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="label_uang_pangkal">Nama Label Uang Pangkal</label>
            <input id="label_uang_pangkal" name="label_uang_pangkal" class="input" value="<?= e($f['label_uang_pangkal']) ?>" placeholder="Uang Pangkal Gedung">
            <p class="hint">Nama yang tampil di kartu biaya landing page, misal: "Uang Pangkal Gedung".</p>
          </div>
          <div>
            <label class="label" for="label_spp">Nama Label SPP</label>
            <input id="label_spp" name="label_spp" class="input" value="<?= e($f['label_spp']) ?>" placeholder="SPP Bulanan">
            <p class="hint">Nama yang tampil di kartu biaya landing page, misal: "SPP Bulanan".</p>
          </div>
        </div>
      </section>

      <!-- ============ 5. REKENING ============ -->
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Rekening Bank Resmi Institusi</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-3">
          <div>
            <label class="label" for="nama_bank">Nama Bank Mitra</label>
            <input id="nama_bank" name="nama_bank" class="input" value="<?= e($f['nama_bank']) ?>">
          </div>
          <div>
            <label class="label" for="no_rekening">Nomor Rekening Virtual / Giro</label>
            <input id="no_rekening" name="no_rekening" class="input font-mono" value="<?= e($f['no_rekening']) ?>">
          </div>
          <div>
            <label class="label" for="atas_nama_rekening">Atas Nama Rekening</label>
            <input id="atas_nama_rekening" name="atas_nama_rekening" class="input" value="<?= e($f['atas_nama_rekening']) ?>">
          </div>
        </div>
      </section>

      <!-- ============ 6. JADWAL GELOMBANG ============ -->
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Jadwal Gelombang Pendaftaran</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <div>
            <label class="label" for="tahun_ajaran">Tahun Ajaran</label>
            <input id="tahun_ajaran" name="tahun_ajaran" class="input" placeholder="2025/2026" value="<?= e($f['tahun_ajaran']) ?>">
          </div>
          <div>
            <label class="label" for="gelombang">Nama Gelombang</label>
            <input id="gelombang" name="gelombang" class="input" placeholder="Gelombang 1" value="<?= e($f['gelombang']) ?>">
          </div>
          <div>
            <label class="label" for="tanggal_buka_ppdb">Tanggal Buka SPMB</label>
            <input id="tanggal_buka_ppdb" name="tanggal_buka_ppdb" type="date" class="input" value="<?= e($f['tanggal_buka_ppdb']) ?>">
          </div>
          <div>
            <label class="label" for="tanggal_tutup_ppdb">Tanggal Tutup SPMB</label>
            <input id="tanggal_tutup_ppdb" name="tanggal_tutup_ppdb" type="date" class="input" value="<?= e($f['tanggal_tutup_ppdb']) ?>">
          </div>
          <div>
            <label class="label" for="tanggal_pengumuman">Pengumuman Hasil Seleksi</label>
            <input id="tanggal_pengumuman" name="tanggal_pengumuman" type="date" class="input" value="<?= e($f['tanggal_pengumuman']) ?>">
          </div>
          <div class="sm:col-span-2 lg:col-span-5">
            <label class="label" for="deskripsi_gelombang">Deskripsi Gelombang &amp; Syarat Pendaftaran</label>
            <textarea id="deskripsi_gelombang" name="deskripsi_gelombang" class="textarea" rows="2"><?= e($f['deskripsi_gelombang']) ?></textarea>
          </div>
        </div>
      </section>

      <!-- ============ 6b. JALUR PENDAFTARAN ============ -->
      <?php $katalogJalur = jalur_katalog(); ?>
      <?php $aktifSekarang = array_keys(jalur_aktif()); ?>
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Jalur Pendaftaran Aktif</h2>
        <p class="mt-1 text-[13px] text-ink-500">Centang jalur yang ditampilkan di formulir pendaftaran calon siswa. Minimal satu jalur harus aktif.</p>

        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <?php foreach ($katalogJalur as $key => $label): ?>
            <label class="flex items-start gap-3 rounded-xl border border-line bg-canvas p-4 cursor-pointer hover:border-primary/40 transition <?= in_array($key, $aktifSekarang, true) ? 'ring-1 ring-primary/30' : '' ?>">
              <input type="checkbox" name="jalur_aktif[]" value="<?= e($key) ?>"
                     <?= in_array($key, $aktifSekarang, true) ? 'checked' : '' ?>
                     class="mt-0.5 rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
              <span class="min-w-0">
                <span class="block text-sm font-semibold text-ink-900"><?= e($label) ?></span>
                <span class="block text-[12px] text-ink-500 mt-0.5 font-mono"><?= e($key) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- ============ 7. WHATSAPP GATEWAY (FONNTE) ============ -->
      <section class="card p-5 sm:p-6">
        <div class="flex items-center justify-between gap-3 flex-wrap">
          <h2 class="font-bold text-ink-900">WhatsApp Gateway (Fonnte)</h2>
          <?php
          /* [PERBAIKAN] Badge semula hanya membaca kolom DB, sehingga token
           * yang diisi di .env (FONNTE_TOKEN) tetap dianggap "Belum diatur"
           * padahal wa_kirim() sudah memakainya. */
          $fonnteAktif = fonnte_token() !== null;
          $dariEnv     = env_val('FONNTE_TOKEN', '') !== '';
          ?>
          <?php if ($fonnteAktif): ?>
            <span class="badge badge-green"><?= dot('green') ?> <?= $dariEnv ? 'Token aktif (dari .env)' : 'Token tersimpan' ?></span>
          <?php else: ?>
            <span class="badge badge-amber"><?= dot('amber') ?> Belum diatur</span>
          <?php endif; ?>
        </div>
        <p class="hint mt-2">Token API Fonnte dipakai untuk mengirim kredensial akun siswa. Dapatkan token di dasbor Fonnte, menu Settings, lalu Token.</p>
        <?php if (env_val('FONNTE_TOKEN', '') !== ''): ?>
          <div class="mt-2 rounded-lg bg-green-50 border border-green-200 px-3 py-2 text-[12px] text-green-800">
            <strong>Mode .env aktif:</strong> token Fonnte dibaca dari file <code>.env</code> (FONNTE_TOKEN). Nilai di bawah hanya dipakai bila .env kosong.
          </div>
        <?php endif; ?>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="fonnte_token">API Token Fonnte</label>
            <input id="fonnte_token" name="fonnte_token" type="password" autocomplete="off" class="input font-mono" placeholder="tempel token Fonnte di sini" value="<?= e($f['fonnte_token']) ?>">
            <p class="hint">Disimpan sebagai rahasia — jangan bagikan token ini. Untuk production, isi di <code>.env</code> (FONNTE_TOKEN).</p>
          </div>
        </div>
      </section>

      <!-- 8. Payment Gateway (Midtrans) -->
      <section class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-center gap-3">
          <h2 class="font-bold text-ink-900">Payment Gateway (Midtrans)</h2>
          <?php if (midtrans_aktif() && $f['bayar_gateway_aktif']): ?>
            <span class="badge badge-green"><?= dot('green') ?> Aktif (<?= e(midtrans_mode()) ?>)</span>
          <?php elseif (midtrans_aktif()): ?>
            <span class="badge badge-blue"><?= dot('blue') ?> Terkonfigurasi — Nonaktif</span>
          <?php else: ?>
            <span class="badge badge-gray"><?= dot('gray') ?> Belum diatur (opsional)</span>
          <?php endif; ?>
        </div>
        <p class="hint mt-2">
          <strong>Opsional.</strong> Pembayaran online hanya pelengkap — alur utama tetap transfer bank manual / QRIS
          dengan unggah bukti, dan tidak memerlukan bagian ini sama sekali.
          Bila ingin mengaktifkannya: daftar gratis di
          <a href="https://midtrans.com" target="_blank" rel="noopener" class="underline font-semibold">midtrans.com</a>,
          lalu salin API keys dari dasbor '†’ Settings '†’ Access Keys.
          Mendukung Virtual Account (BCA/Mandiri/BRI/BNI), QRIS, dan e-wallet (GoPay/OVO/DANA/ShopeePay).
        </p>

        <?php if (env_val('MIDTRANS_SERVER_KEY', '') !== ''): ?>
          <div class="mt-2 rounded-lg bg-green-50 border border-green-200 px-3 py-2 text-[12px] text-green-800">
            <strong>Mode .env aktif:</strong> API key Midtrans dibaca dari file <code>.env</code> (MIDTRANS_SERVER_KEY / MIDTRANS_CLIENT_KEY). Nilai di bawah hanya dipakai bila .env kosong.
          </div>
        <?php endif; ?>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="midtrans_server_key">Server Key <span class="text-red-600">*</span></label>
            <input id="midtrans_server_key" name="midtrans_server_key" type="password" autocomplete="off" class="input font-mono" placeholder="Mid-server-xxxxxxxx" value="<?= e($f['midtrans_server_key']) ?>">
            <p class="hint">Rahasia server — jangan dibagikan. Format: <code>Mid-server-…</code>. Untuk production, isi di <code>.env</code>.</p>
          </div>
          <div>
            <label class="label" for="midtrans_client_key">Client Key <span class="text-red-600">*</span></label>
            <input id="midtrans_client_key" name="midtrans_client_key" type="password" autocomplete="off" class="input font-mono" placeholder="Mid-client-xxxxxxxx" value="<?= e($f['midtrans_client_key']) ?>">
            <p class="hint">Dipakai browser siswa (Snap.js). Format: <code>Mid-client-…</code></p>
          </div>
          <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-canvas border border-line px-4 py-3">
            <div>
              <p class="text-sm font-semibold text-ink-900">Mode Environment</p>
              <p class="text-[13px] text-ink-500">
                <strong>Sandbox</strong> = uji coba (tanpa uang asli, akun test). <strong>Production</strong> = transaksi nyata.
              </p>
            </div>
            <select name="midtrans_sandbox" class="select w-44">
              <option value="1" <?= (int) $f['midtrans_sandbox'] === 1 ? 'selected' : '' ?>>Sandbox (Dev)</option>
              <option value="0" <?= (int) $f['midtrans_sandbox'] === 0 ? 'selected' : '' ?>>Production (Nyata)</option>
            </select>
          </div>
        </div>

        <?php if (!midtrans_aktif()): ?>
          <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] text-amber-800">
            Payment gateway belum dikonfigurasi — semua opsi Midtrans di bawah nonaktif otomatis dan
            <em>tidak wajib</em>: transfer bank manual / QRIS tetap bisa dipakai tanpa API key apa pun.
            Untuk mengaktifkannya, isi kedua API key di atas (utamakan lewat file <code>.env</code>:
            <code>MIDTRANS_SERVER_KEY</code>, <code>MIDTRANS_CLIENT_KEY</code>, <code>MIDTRANS_SANDBOX</code>),
            lalu centang <strong>"Bayar Online"</strong> di bagian Metode Pembayaran. Gunakan <strong>Sandbox</strong>
            terlebih dahulu untuk uji coba sebelum ganti ke Production.
          </div>
        <?php endif; ?>
      </section>

      <!-- 9. Metode Pembayaran & Syarat & Ketentuan -->
      <section class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="font-bold text-ink-900">Metode Pembayaran &amp; Syarat &amp; Ketentuan</h2>
          <span class="badge badge-gray">Halaman Bayar Publik</span>
        </div>
        <p class="mt-1 text-[13px] text-ink-500">
          Atur metode pembayaran yang ditawarkan di halaman bayar publik (akses via tautan pay_token), serta teks Syarat &amp; Ketentuan yang harus disetujui calon pendaftar sebelum mendaftar.
        </p>

        <div class="mt-4 space-y-3">
          <label class="flex items-start gap-3 rounded-xl border border-line p-3 hover:bg-canvas <?= midtrans_aktif() ? 'cursor-pointer' : 'opacity-60' ?>">
            <input type="checkbox" name="bayar_gateway_aktif" value="1" class="mt-1 w-4 h-4 accent-primary" <?= $f['bayar_gateway_aktif'] ? 'checked' : '' ?> <?= midtrans_aktif() ? '' : 'disabled' ?>>
            <span>
              <span class="text-sm font-semibold text-ink-900">Bayar Online (Midtrans Snap) — opsional</span>
              <span class="block text-[13px] text-ink-500">Pilihan tambahan: virtual account, e-wallet, QRIS otomatis, kartu kredit. Memerlukan API key Midtrans di atas; tanpanya metode ini nonaktif otomatis.</span>
            </span>
          </label>
          <label class="flex items-start gap-3 rounded-xl border border-line p-3 hover:bg-canvas cursor-pointer">
            <input type="checkbox" name="bayar_transfer_aktif" value="1" class="mt-1 w-4 h-4 accent-primary" <?= $f['bayar_transfer_aktif'] ? 'checked' : '' ?>>
            <span>
              <span class="text-sm font-semibold text-ink-900">Transfer Bank Manual</span>
              <span class="block text-[13px] text-ink-500">Pendaftar transfer sesuai rekening sekolah, lalu unggah bukti (JPG/PNG/PDF) untuk diverifikasi bendahara.</span>
            </span>
          </label>
          <label class="flex items-start gap-3 rounded-xl border border-line p-3 hover:bg-canvas cursor-pointer">
            <input type="checkbox" name="bayar_qris_aktif" value="1" class="mt-1 w-4 h-4 accent-primary" <?= $f['bayar_qris_aktif'] ? 'checked' : '' ?>>
            <span>
              <span class="text-sm font-semibold text-ink-900">QRIS Manual</span>
              <span class="block text-[13px] text-ink-500">Tampilkan gambar QRIS statis, pendaftar scan dengan aplikasi apa pun, lalu unggah bukti.</span>
            </span>
          </label>
          <label class="flex items-start gap-3 rounded-xl border border-line p-3 hover:bg-canvas <?= midtrans_aktif() ? 'cursor-pointer' : 'opacity-60' ?>">
            <input type="checkbox" name="kirim_akun_otomatis" value="1" class="mt-1 w-4 h-4 accent-primary" <?= $f['kirim_akun_otomatis'] ? 'checked' : '' ?> <?= midtrans_aktif() ? '' : 'disabled' ?>>
            <span>
              <span class="text-sm font-semibold text-ink-900">Kirim Akun Siswa Otomatis setelah Terverifikasi</span>
              <span class="block text-[13px] text-ink-500">Bila aktif, akun portal siswa dibuat &amp; kredensial dikirim via WhatsApp begitu pembayaran via gateway lunas. Hanya berlaku bila payment gateway aktif; verifikasi manual bendahara selalu mengirim akun tanpa syarat ini.</span>
            </span>
          </label>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="qris_merchant">Nama Merchant QRIS</label>
            <input id="qris_merchant" name="qris_merchant" type="text" class="input" placeholder="SMP Muhammadiyah 5 TGR" value="<?= e($f['qris_merchant']) ?>">
            <p class="hint">Nama yang tampil di bawah gambar QRIS pada halaman bayar.</p>
          </div>
          <div>
            <label class="label" for="qris_image">Gambar QRIS (JPG/PNG, maks 2 MB)</label>
            <input id="qris_image" name="qris_image" type="file" accept=".jpg,.jpeg,.png" class="input">
            <?php if ($f['qris_image_url']): ?>
              <div class="mt-2 flex items-center gap-3">
                <img src="<?= e(asset($f['qris_image_url'])) ?>" alt="QRIS" class="w-16 h-16 object-contain rounded border border-line bg-white p-1">
                <label class="flex items-center gap-1.5 text-[13px] text-ink-500 cursor-pointer">
                  <input type="checkbox" name="qris_reset" value="1" class="w-4 h-4 accent-red-600"> Hapus QRIS saat ini
                </label>
              </div>
            <?php else: ?>
              <p class="hint">Belum ada gambar QRIS. Unggah agar metode QRIS dapat ditampilkan.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-5">
          <label class="label" for="syarat_ketentuan">Teks Syarat &amp; Ketentuan (S&amp;K)</label>
          <p class="hint -mt-1 mb-2">Tulis per pasal dengan awalan nomor (mis. <code>1.</code>, <code>2.</code>) — tampilan di halaman registrasi otomatis menjadi daftar kartu bernomor.
            Percabangan versi otomatis naik bila teks diubah. Calon pendaftar harus menggulir &amp; mencentang persetujuan sebelum formulir dapat dikirim.</p>
          <textarea id="syarat_ketentuan" name="syarat_ketentuan" rows="16" class="textarea font-mono text-[13px] leading-relaxed" placeholder="SYARAT & KETENTUAN PENDAFTARAN&#10;SMP MUHAMMADIYAH 5 KOTA TANGERANG&#10;&#10;1. Pendaftar adalah siswa kelas 6 SD/MI ...&#10;&#10;2. Data yang diisi harus benar ..."><?= e($f['syarat_ketentuan']) ?></textarea>
          <p class="hint mt-1">Versi saat ini: <strong class="text-ink-900"><?= (int) $f['syarat_versi'] ?></strong>
            — setiap perubahan teks otomatis menaikkan versi; pendaftar lama tetap terikat versi yang mereka setujui saat mendaftar.</p>
          <p class="hint mt-1">Pasal Privasi &amp; Retensi Data (Pasal 8) wajib ditinjau sekolah/bagian hukum sebelum disetujui
            (draf lengkap: <a class="text-primary underline" href="docs/KEBIJAKAN-DATA.md" target="_blank" rel="noopener">docs/KEBIJAKAN-DATA.md</a>).</p>
        </div>
      </section>

      <!-- 10. Notifikasi Operator (Fase 3) -->
      <section class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="font-bold text-ink-900">Notifikasi Operator</h2>
          <span class="badge badge-gray">Fase 3</span>
        </div>
        <p class="mt-1 text-[13px] text-ink-500">
          Notifikasi masuk tersimpan di menu <a href="<?= url('app/operator/notifikasi.php') ?>" class="text-primary underline">Notifikasi</a>
          (lencana di sidebar, diperbarui tiap 60 detik). Bila toggle WhatsApp aktif, notifikasi yang sama juga dikirim
          ke nomor di bawah via Fonnte — kegagalan kirim tidak mengganggu proses utama dan tercatat di tabel <code class="text-[12px]">wa_log</code>.
        </p>

        <div class="mt-4 space-y-3">
          <label class="flex items-start gap-3 rounded-xl border border-line p-3 hover:bg-canvas cursor-pointer">
            <input type="checkbox" name="notif_wa_aktif" value="1" class="mt-1 w-4 h-4 accent-primary" <?= $f['notif_wa_aktif'] ? 'checked' : '' ?>>
            <span>
              <span class="text-sm font-semibold text-ink-900">Kirim Notifikasi via WhatsApp (Fonnte)</span>
              <span class="block text-[13px] text-ink-500">Pemicu: bukti bayar masuk, pembayaran gateway lunas, submit berkas (berlaku setelah Fase 5).</span>
            </span>
          </label>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="notif_wa_operator">Nomor WA Operator (dipisah koma)</label>
            <input id="notif_wa_operator" name="notif_wa_operator" type="text" class="input" placeholder="08123456789, 08987654321" value="<?= e($f['notif_wa_operator']) ?>">
            <p class="hint">Wajib diisi bila toggle di atas aktif. Format: angka saja, boleh +.</p>
          </div>
          <div>
            <label class="label" for="notif_email">Email Notifikasi</label>
            <input id="notif_email" name="notif_email" type="text" class="input opacity-60" value="<?= e($f['notif_email']) ?>" disabled>
            <p class="hint">Email notifikasi belum diimplementasikan — kolom disiapkan sebagai titik sambungan saja (keputusan menyusul).</p>
          </div>
        </div>
      </section>

      <!-- 11. Daftar Berkas (Fase 5) -->
      <section class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="font-bold text-ink-900">Daftar Berkas Persyaratan</h2>
          <span class="badge badge-gray">Fase 5</span>
        </div>
        <p class="mt-1 text-[13px] text-ink-500">
          Jenis berkas yang diminta dari calon siswa di portal (nama, wajib/opsional, format, maks ukuran).
          Daftar final menyusul — data awal adalah salinan jenis yang sebelumnya tertanam di kode.
        </p>
        <div class="mt-4">
          <a href="<?= url('app/operator/jenis-dokumen.php') ?>" class="btn btn-outline">
            <?= icon('edit') ?> Kelola Daftar Berkas
          </a>
        </div>
      </section>

      <div class="sticky bottom-0 -mx-4 sm:-mx-6 lg:-mx-8 px-4 sm:px-6 lg:px-8 py-4 bg-white/95 backdrop-blur border-t border-line flex flex-wrap items-center gap-3 justify-end">
        <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-primary shadow-drop"><?= icon('save') ?> Simpan Pengaturan</button>
      </div>
    </form>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
  (function () {
    var txt = document.getElementById('warna_utama');
    var pick = document.getElementById('warna-picker');
    var prev = document.getElementById('warna-preview');
    if (!txt || !pick || !prev) return;
    function sinkron(v) {
      if (!/^#[0-9A-Fa-f]{6}$/.test(v)) return;
      txt.value = v.toUpperCase();
      pick.value = v;
      prev.style.backgroundColor = v;
    }
    txt.addEventListener('input', function () { sinkron(txt.value); });
    pick.addEventListener('input', function () { sinkron(pick.value); });
    document.querySelectorAll('[data-warna]').forEach(function (b) {
      b.addEventListener('click', function () { sinkron(b.dataset.warna); });
    });
  })();
</script>
</body>
</html>
