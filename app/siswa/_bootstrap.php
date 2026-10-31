<?php
/**
 * BOOTSTRAP PORTAL SISWA (Fase 13).
 *
 * Dimuat oleh seluruh halaman app/siswa/*.php agar autentikasi, data
 * pendaftar, label status, ringkasan pembayaran, dan kelengkapan data
 * dihitung dengan cara yang sama. Handler POST tetap berada di halaman
 * masing-masing (data-siswa.php, keuangan.php, akun.php).
 */
if (basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === '_bootstrap.php') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../includes/config.php';

require_role(['siswa']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'] ?? 'Siswa',
    'inisial' => $me['inisial'],
];

$title = $title ?? 'Portal Siswa';
$desc  = $desc ?? 'Portal siswa SPMB SMP Muhammadiyah 5 Kota Tangerang.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$siswa = profil_siswa();
$pengumuman = pengumuman_status();
$pengumumanAktif = !empty($pengumuman['diumumkan']);

$pembayaran = [];
$seleksi = null;
$penempatan = null;
$dokumen = [];
$kartuUjian = null;
$jadwalSiswa = null;
$biodataBolehEdit = false;
$verifikasi = ['Belum Ada Data', 'gray'];
$labelSeleksi = ['PROSES' => ['Dalam Proses', 'amber'], 'LULUS' => ['Lulus', 'green'], 'TIDAK_LULUS' => ['Tidak Lulus', 'red']];
$labelAkun = ['AKTIF' => ['Aktif', 'green'], 'BELUM_DIBUAT' => ['Belum Dibuat', 'amber'], 'GAGAL' => ['Gagal', 'red']];
$labelBayar = [
    'PENDING' => 'Menunggu Verifikasi',
    'VERIFIED' => 'Terverifikasi',
    'REJECTED' => 'Ditolak',
    'LUNAS' => 'Lunas',
    'ANGSURAN_1' => 'Angsuran 1',
    'ANGSURAN_2' => 'Angsuran 2',
];
$toneBayar = [
    'PENDING' => 'amber',
    'VERIFIED' => 'green',
    'REJECTED' => 'red',
    'LUNAS' => 'green',
    'ANGSURAN_1' => 'blue',
    'ANGSURAN_2' => 'blue',
];

$tagihanFormulir = (float) ($PENGATURAN['biaya_formulir'] ?? 0);
$tagihanUangMasuk = (float) ($PENGATURAN['estimasi_uang_masuk'] ?? 0);
$bayarFormulir = ['tagihan' => 0, 'verified' => 0, 'pending' => 0, 'pending_bukti' => 0, 'rejected' => 0, 'sisa' => 0, 'label' => 'Belum Ada Data', 'tone' => 'gray', 'rows' => []];
$bayarUangMasuk = $bayarFormulir;
$ringkasAngsuran = null;
$totalTerverifikasi = 0;
$koreksi = [];
$koreksiBidang = koreksi_bidang();
$siswaDiterima = false;
$siswaAktif = false;
$kelengkapan = [
    'persen' => 0, 'terisi' => 0, 'total' => 0, 'items' => [], 'kurang' => [],
    'dok_wajib' => 0, 'dok_terisi' => 0,
];
$tahap = null;
$langkahBerikut = null;

if ($siswa) {
    $pembayaran = pembayaran_siswa($siswa['id']);
    $seleksi    = seleksi_siswa($siswa['id']);
    $penempatan = penempatan_siswa($siswa['id']);
    $dokumen    = dokumen_siswa($siswa['id']);
    $kartuUjian = peserta_kartu_siswa($siswa['id']);
    $koreksi    = koreksi_siswa($siswa['id']);
    $biodataBolehEdit = in_array($siswa['status_verifikasi'], ['DRAFT', 'MENUNGGU_VERIFIKASI', 'DITOLAK'], true);

    $labelVer = [
        'DRAFT'               => ['Draft', 'gray'],
        'MENUNGGU_VERIFIKASI' => ['Menunggu Verifikasi', 'amber'],
        'TERVERIFIKASI'       => ['Terverifikasi', 'green'],
        'DITOLAK'             => ['Perlu Perbaikan', 'red'],
    ];
    $verifikasi = $labelVer[$siswa['status_verifikasi']] ?? [$siswa['status_verifikasi'], 'gray'];

    if ($kartuUjian) {
        $jadwalSiswa = q_all(
            'SELECT j.mode, j.tautan_video, j.status, j.lokasi, j.ruangan, j.tanggal, j.jam_mulai, j.jam_selesai
               FROM peserta_ujian pu
               JOIN jadwal_tes j ON j.id = pu.jadwal_id
              WHERE pu.pendaftar_id = ? LIMIT 1',
            [$siswa['id']]
        );
        $jadwalSiswa = $jadwalSiswa[0] ?? null;
    }

    $siswaDiterima = $pengumumanAktif && ($siswa['status_seleksi'] ?? '') === 'LULUS';
    $siswaAktif = $siswaDiterima;

    /* Tagihan biaya pendaftaran hanya aktif setelah LULUS + diumumkan. */
    $ringkasAngsuran = angsuran_ringkas($siswa['id']);
    $tagihanUangMasuk = $siswaDiterima ? $ringkasAngsuran['total'] : 0;

    $bayarFormulir   = siswa_ringkas_bayar($pembayaran, 'FORMULIR', $tagihanFormulir);
    $bayarUangMasuk  = siswa_ringkas_bayar($pembayaran, 'UANG_MASUK', $tagihanUangMasuk);
    $totalTerverifikasi = $bayarFormulir['verified'] + $bayarUangMasuk['verified'];
    $kelengkapan = siswa_kelengkapan($siswa, $dokumen);
    $tahap = siswa_tahap($siswa['tahap'] ?? '');
    /* Tahap DIUMUMKAN di database belum tentu sudah diumumkan resmi ke siswa.
     * Selama panitia belum menekan "Umumkan Sekarang", tampilkan status
     * menunggu agar siswa tidak melihat hasil lebih awal. */
    if (($siswa['tahap'] ?? '') === 'DIUMUMKAN' && !$pengumumanAktif) {
        $tahap['label']  = 'Menunggu pengumuman hasil';
        $tahap['desc']   = 'Hasil seleksi belum diumumkan resmi. Pantau menu PPDB & Seleksi.';
        $tahap['persen'] = 95;
    }
    /* Pengingat jatuh tempo angsuran (idempoten per jadwal). */
    if ($siswaDiterima && !empty($ringkasAngsuran['aktif'])) {
        angsuran_kirim_pengingat($siswa['id'], $ringkasAngsuran, url('app/siswa/keuangan.php'));
    }
    $langkahBerikut = siswa_langkah_berikut(
        $siswa, $bayarFormulir, $dokumen, $kartuUjian, $pengumuman, $seleksi, $penempatan
    );
}

/* ==================================================================
 * Helper tampilan & ringkasan
 * ================================================================== */

/** Ringkas pembayaran per jenis. Hanya status terverifikasi yang dihitung lunas. */
function siswa_ringkas_bayar(array $pembayaran, $jenis, $tagihan)
{
    $tagihan = (float) $tagihan;
    $out = [
        'tagihan' => $tagihan,
        'verified' => 0.0,
        'pending' => 0.0,
        'pending_bukti' => 0.0,
        'rejected' => 0.0,
        'sisa' => 0.0,
        'label' => 'Belum Dibayar',
        'tone' => 'gray',
        'rows' => [],
    ];
    foreach ($pembayaran as $b) {
        if (($b['jenis_pembayaran'] ?? '') !== $jenis) {
            continue;
        }
        $out['rows'][] = $b;
        $nominal = (float) ($b['nominal'] ?? 0);
        $status = (string) ($b['status'] ?? 'PENDING');
        if (in_array($status, ['VERIFIED', 'LUNAS', 'ANGSURAN_1', 'ANGSURAN_2'], true)) {
            $out['verified'] += $nominal;
        } elseif ($status === 'PENDING') {
            $out['pending'] += $nominal;
            if (!empty($b['bukti_transfer_url'])) {
                $out['pending_bukti'] += $nominal;
            }
        } elseif ($status === 'REJECTED') {
            $out['rejected'] += $nominal;
        }
    }
    $out['sisa'] = max(0, $tagihan - $out['verified']);

    if ($tagihan > 0 && $out['verified'] >= $tagihan) {
        $out['label'] = 'Lunas / Terverifikasi';
        $out['tone'] = 'green';
    } elseif ($out['pending_bukti'] > 0) {
        $out['label'] = 'Menunggu Verifikasi';
        $out['tone'] = 'amber';
    } elseif ($out['rejected'] > 0) {
        $out['label'] = 'Ditolak — Perlu Unggah Ulang';
        $out['tone'] = 'red';
    } elseif ($out['verified'] > 0) {
        $out['label'] = 'Terbayar Sebagian';
        $out['tone'] = 'blue';
    } elseif ($out['pending'] > 0) {
        $out['label'] = 'Belum Dibayar';
        $out['tone'] = 'gray';
    }
    return $out;
}

/** Indikator kelengkapan data (bukan pengganti verifikasi petugas). */
function siswa_kelengkapan(array $siswa, array $dokumen)
{
    $items = [];
    $tambah = function ($label, $ok, $opsional = false) use (&$items) {
        $items[] = ['label' => $label, 'ok' => (bool) $ok, 'opsional' => (bool) $opsional];
    };

    $tambah('NISN', trim((string) ($siswa['nisn'] ?? '')) !== '', true);
    $tambah('Jenis kelamin', !empty($siswa['jenis_kelamin']));
    $tambah('Tempat lahir', trim((string) ($siswa['tempat_lahir'] ?? '')) !== '');
    $tambah('Tanggal lahir', !empty($siswa['tanggal_lahir']));
    $tambah('Alamat siswa', trim((string) ($siswa['alamat'] ?? '')) !== '');
    $tambah('Asal sekolah', trim((string) ($siswa['asal_sekolah'] ?? '')) !== '');
    $tambah('Nama orang tua / wali', trim((string) ($siswa['nama_orang_tua'] ?? '')) !== '');
    $tambah('Nomor WhatsApp orang tua / wali', trim((string) ($siswa['no_whatsapp'] ?? '')) !== '');
    $tambah('Alamat orang tua / wali', trim((string) ($siswa['alamat_orang_tua'] ?? '')) !== '');
    $tambah('Pekerjaan orang tua / wali', trim((string) ($siswa['pekerjaan'] ?? '')) !== '');
    $tambah('Penghasilan orang tua / wali', trim((string) ($siswa['penghasilan_bulanan'] ?? '')) !== '', true);

    $dokWajib = 0;
    $dokTerisi = 0;
    foreach ($dokumen as $d) {
        if (empty($d['wajib'])) {
            continue;
        }
        $dokWajib++;
        $ada = !empty($d['file_url']);
        if ($ada) {
            $dokTerisi++;
        }
        $items[] = ['label' => 'Dokumen: ' . ($d['nama'] ?? $d['kode']), 'ok' => $ada, 'opsional' => false];
    }

    $wajib = array_values(array_filter($items, function ($i) { return !$i['opsional']; }));
    $terisi = count(array_filter($wajib, function ($i) { return $i['ok']; }));
    $total = count($wajib);
    $kurang = [];
    foreach ($items as $i) {
        if (!$i['opsional'] && !$i['ok']) {
            $kurang[] = $i['label'];
        }
    }

    return [
        'persen' => $total > 0 ? (int) round($terisi / $total * 100) : 0,
        'terisi' => $terisi,
        'total' => $total,
        'items' => $items,
        'kurang' => $kurang,
        'dok_wajib' => $dokWajib,
        'dok_terisi' => $dokTerisi,
    ];
}

/** Tahap alur SPMB untuk tampilan progres. */
function siswa_tahap($tahap)
{
    $peta = [
        'MENUNGGU_PEMBAYARAN'       => ['Pembayaran biaya formulir', 'Selesaikan pembayaran biaya formulir agar pendaftaran diproses.', 10],
        'MENUNGGU_VERIFIKASI_BAYAR' => ['Verifikasi pembayaran', 'Bukti pembayaran sedang diperiksa bendahara.', 20],
        'BAYAR_TERVERIFIKASI'       => ['Pembayaran terverifikasi', 'Pembayaran sudah diverifikasi. Akun siswa disiapkan panitia.', 30],
        'AKUN_TERKIRIM'             => ['Akun siswa aktif', 'Lengkapi berkas persyaratan melalui menu Data Siswa.', 35],
        'MELENGKAPI_BERKAS'         => ['Melengkapi berkas', 'Unggah seluruh dokumen wajib, lalu kirim untuk verifikasi.', 45],
        'BERKAS_DISUBMIT'           => ['Berkas dikirim', 'Berkas sedang diperiksa panitia.', 55],
        'BERKAS_REVISI'             => ['Revisi berkas', 'Ada berkas yang perlu diunggah ulang.', 50],
        'BERKAS_TERVERIFIKASI'      => ['Berkas terverifikasi', 'Berkas lengkap. Menunggu penjadwalan tes.', 65],
        'JADWAL_DITETAPKAN'         => ['Jadwal tes ditetapkan', 'Lihat kartu peserta dan hadir sesuai jadwal.', 75],
        'NILAI_TERINPUT'            => ['Tes selesai', 'Hasil seleksi sedang disiapkan untuk diumumkan.', 85],
        'DIUMUMKAN'                 => ['Hasil diumumkan', 'Hasil seleksi resmi sudah dapat dilihat.', 100],
    ];
    $d = $peta[$tahap] ?? ['Status pendaftaran', 'Status pendaftaran sedang diproses.', 5];
    return ['raw' => $tahap, 'label' => $d[0], 'desc' => $d[1], 'persen' => $d[2]];
}

/** Satu langkah yang paling perlu dilakukan siswa saat ini. */
function siswa_langkah_berikut($siswa, $bayarFormulir, $dokumen, $kartuUjian, $pengumuman, $seleksi, $penempatan)
{
    if ($bayarFormulir['tagihan'] > 0 && $bayarFormulir['verified'] < $bayarFormulir['tagihan']) {
        if ($bayarFormulir['pending_bukti'] > 0) {
            return ['judul' => 'Menunggu verifikasi pembayaran', 'pesan' => 'Bukti pembayaran sudah dikirim. Status lunas menunggu verifikasi bendahara.', 'ikon' => 'hourglass_top', 'tautan' => url('app/siswa/keuangan.php'), 'label' => 'Lihat Keuangan', 'tone' => 'amber'];
        }
        return ['judul' => 'Selesaikan pembayaran formulir', 'pesan' => 'Unggah bukti transfer atau bayar online agar pendaftaran dapat diproses.', 'ikon' => 'payments', 'tautan' => url('app/siswa/keuangan.php'), 'label' => 'Buka Keuangan', 'tone' => 'amber'];
    }

    if (in_array($siswa['tahap'] ?? '', ['MELENGKAPI_BERKAS', 'BERKAS_REVISI'], true) || $bayarFormulir['verified'] >= $bayarFormulir['tagihan']) {
        $wajib = 0;
        $terisi = 0;
        foreach ($dokumen as $d) {
            if (!empty($d['wajib'])) {
                $wajib++;
                if (!empty($d['file_url'])) {
                    $terisi++;
                }
            }
        }
        if (($siswa['tahap'] ?? '') === 'BERKAS_REVISI') {
            return ['judul' => 'Perbaiki berkas yang ditolak', 'pesan' => 'Unggah ulang berkas dengan catatan panitia, lalu kirim kembali.', 'ikon' => 'folder_shared', 'tautan' => url('app/siswa/data-siswa.php'), 'label' => 'Perbaiki Berkas', 'tone' => 'red'];
        }
        if ($wajib > 0 && $terisi < $wajib && in_array($siswa['tahap'] ?? '', ['MELENGKAPI_BERKAS', 'AKUN_TERKIRIM', 'BAYAR_TERVERIFIKASI'], true)) {
            return ['judul' => 'Lengkapi berkas persyaratan', 'pesan' => ($wajib - $terisi) . ' berkas wajib belum diunggah.', 'ikon' => 'upload_file', 'tautan' => url('app/siswa/data-siswa.php'), 'label' => 'Lengkapi Berkas', 'tone' => 'amber'];
        }
    }

    if (!empty($pengumuman['diumumkan'])) {
        return ['judul' => 'Hasil seleksi sudah diumumkan', 'pesan' => 'Buka PPDB & Seleksi untuk melihat hasil resmi dan penempatan kelas.', 'ikon' => 'campaign', 'tautan' => url('app/siswa/ppdb.php?tab=hasil'), 'label' => 'Lihat Hasil', 'tone' => 'green'];
    }
    if (!empty($pengumuman['dijadwalkan_pada'])) {
        return ['judul' => 'Menunggu pengumuman hasil', 'pesan' => 'Hasil seleksi dijadwalkan diumumkan pada ' . bulan($pengumuman['dijadwalkan_pada']) . '.', 'ikon' => 'schedule', 'tautan' => url('app/siswa/ppdb.php?tab=hasil'), 'label' => 'Lihat Jadwal', 'tone' => 'blue'];
    }
    if ($kartuUjian) {
        return ['judul' => 'Jadwal tes sudah tersedia', 'pesan' => 'Periksa kartu peserta, lokasi, dan ruang ujian Anda.', 'ikon' => 'badge', 'tautan' => url('app/siswa/ppdb.php?tab=jadwal'), 'label' => 'Lihat Jadwal Tes', 'tone' => 'blue'];
    }
    if ($penempatan && !empty($pengumuman['diumumkan'])) {
        return ['judul' => 'Penempatan kelas tersedia', 'pesan' => 'Anda ditempatkan di ' . $penempatan['kode'] . ' — ' . $penempatan['nama_kelas'] . '.', 'ikon' => 'school', 'tautan' => url('app/siswa/akademik.php'), 'label' => 'Lihat Akademik', 'tone' => 'green'];
    }
    return ['judul' => 'Pantau status pendaftaran', 'pesan' => 'Belum ada tindakan mendesak. Panitia akan memperbarui status secara berkala.', 'ikon' => 'info', 'tautan' => url('app/siswa/ppdb.php'), 'label' => 'Lihat Status', 'tone' => 'gray'];
}

/** Kabar/informasi terbaru yang berasal dari data nyata (bukan dummy). */
function siswa_kabar_terbaru($siswa, $pembayaran, $kartuUjian, $pengumuman, $penempatan, $dokumen, $bayarFormulir, $bayarUangMasuk)
{
    $kabar = [];

    if (!empty($pengumuman['diumumkan'])) {
        $kabar[] = [
            'ikon' => 'campaign', 'tone' => 'green',
            'judul' => 'Hasil seleksi resmi diumumkan',
            'pesan' => 'Hasil seleksi sudah dapat dilihat pada menu PPDB & Seleksi.',
            'tanggal' => $pengumuman['dijadwalkan_pada'] ?? null,
            'tautan' => url('app/siswa/ppdb.php?tab=hasil'),
        ];
    } elseif (!empty($pengumuman['dijadwalkan_pada'])) {
        $kabar[] = [
            'ikon' => 'schedule', 'tone' => 'blue',
            'judul' => 'Jadwal pengumuman hasil seleksi',
            'pesan' => 'Hasil seleksi dijadwalkan diumumkan pada ' . bulan($pengumuman['dijadwalkan_pada']) . '.',
            'tanggal' => $pengumuman['dijadwalkan_pada'],
            'tautan' => url('app/siswa/ppdb.php?tab=hasil'),
        ];
    }

    if ($kartuUjian) {
        $kabar[] = [
            'ikon' => 'event', 'tone' => 'blue',
            'judul' => 'Jadwal tes: ' . $kartuUjian['nama_kegiatan'],
            'pesan' => bulan($kartuUjian['tanggal']) . ', ' . substr($kartuUjian['jam_mulai'], 0, 5) . '–' . substr($kartuUjian['jam_selesai'], 0, 5) . ' WIB · ' . ($kartuUjian['ruangan'] ?: ($kartuUjian['lokasi'] ?: 'Sekolah')),
            'tanggal' => $kartuUjian['tanggal'],
            'tautan' => url('app/siswa/ppdb.php?tab=jadwal'),
        ];
    }

    foreach ([[$bayarFormulir, 'Biaya formulir'], [$bayarUangMasuk, label_biaya_pendaftaran()]] as $pasangan) {
        $b = $pasangan[0];
        $nama = $pasangan[1];
        if ($b['rejected'] > 0) {
            $kabar[] = [
                'ikon' => 'error', 'tone' => 'red',
                'judul' => 'Pembayaran ' . $nama . ' ditolak',
                'pesan' => 'Periksa catatan panitia, lalu unggah ulang bukti pembayaran yang benar.',
                'tanggal' => null,
                'tautan' => url('app/siswa/keuangan.php'),
            ];
        } elseif ($b['pending_bukti'] > 0) {
            $kabar[] = [
                'ikon' => 'hourglass_top', 'tone' => 'amber',
                'judul' => 'Pembayaran ' . $nama . ' menunggu verifikasi',
                'pesan' => 'Bukti sudah diterima. Status lunas menunggu pemeriksaan bendahara.',
                'tanggal' => null,
                'tautan' => url('app/siswa/keuangan.php'),
            ];
        } elseif ($b['tagihan'] > 0 && $b['verified'] < $b['tagihan']) {
            $kabar[] = [
                'ikon' => 'payments', 'tone' => 'amber',
                'judul' => 'Tagihan ' . $nama . ' belum lunas',
                'pesan' => 'Sisa tagihan ' . rupiah($b['sisa']) . '. Unggah bukti transfer melalui menu Keuangan.',
                'tanggal' => null,
                'tautan' => url('app/siswa/keuangan.php'),
            ];
        }
    }

    if ($penempatan && !empty($pengumuman['diumumkan'])) {
        $kabar[] = [
            'ikon' => 'school', 'tone' => 'green',
            'judul' => 'Penempatan kelas',
            'pesan' => 'Anda ditempatkan di ' . $penempatan['kode'] . ' — ' . $penempatan['nama_kelas'] . '.',
            'tanggal' => null,
            'tautan' => url('app/siswa/akademik.php'),
        ];
    } elseif ($siswa && ($siswa['status_seleksi'] ?? '') === 'LULUS' && !empty($pengumuman['diumumkan'])) {
        $kabar[] = [
            'ikon' => 'meeting_room', 'tone' => 'blue',
            'judul' => 'Menunggu penempatan kelas',
            'pesan' => 'Anda dinyatakan lulus. Penempatan kelas akan diinformasikan panitia.',
            'tanggal' => null,
            'tautan' => url('app/siswa/ppdb.php?tab=hasil'),
        ];
    }

    if (!empty($siswa)) {
        $kabar[] = [
            'ikon' => 'campaign', 'tone' => 'gray',
            'judul' => 'Informasi SPMB ' . ($GLOBALS['PENGATURAN']['gelombang'] ?? ''),
            'pesan' => trim((string) ($GLOBALS['PENGATURAN']['ticker_header'] ?? '')),
            'tanggal' => null,
            'tautan' => url('app/siswa/notifikasi.php'),
        ];
    }

    return array_slice($kabar, 0, 6);
}
