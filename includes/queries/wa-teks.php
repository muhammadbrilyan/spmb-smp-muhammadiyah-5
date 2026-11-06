<?php
/**
 * TEKS PESAN WHATSAPP (CMS).
 *
 * Seluruh kalimat yang berangkat ke WA calon murid/wali siswa disusun dari
 * satu katalog template di sini, dan operator boleh menggantinya lewat
 * Pengaturan Sistem → Teks Pesan WhatsApp (kolom `pengaturan_sekolah.wa_teks`,
 * JSON per kunci). Aturan utamanya:
 *
 *   - Kolom kosong / kunci tidak ada  → teks bawaan di katalog ini dipakai.
 *     Jadi upgrade berikutnya selalu membawa kalimat baru selama operator
 *     belum menyentuh field-nya, dan "Pulihkan bawaan" cukup dikosongkan.
 *   - Placeholder `{nama}`, `{tautan_hasil}`, ... diisi oleh pemanggil lewat
 *     wa_teks_isi(). Placeholder yang tidak dikenal ditolak saat menyimpan,
 *     supaya tidak ada `{namaa}` yang ikut terkirim ke wali siswa.
 *   - Placeholder wajib (lihat `wajib`) harus tetap ada di template —
 *     tanpanya pesan kehilangan isi pentingnya (daftar jadwal, tautan hasil,
 *     username & password).
 *
 * Placeholder yang bernilai kosong (mis. baris "Tahun Ajaran: …" saat tahun
 * ajaran tidak diisi) membuat barisnya ikut hilang, bukan meninggalkan
 * baris kosong di tengah pesan — lihat wa_teks_rapikan().
 */

/** Panjang maksimum satu template — pesan WA yang lebih panjang dari ini
 *  biasanya terpotong di layar ponsel dan justru tidak dibaca. */
define('WA_TEKS_MAX_PANJANG', 1200);

/**
 * Katalog seluruh pesan WA ke calon murid.
 * @return array<string,array{label:string,kapan:string,wajib:array<int,string>,
 *         placeholder:array<string,string>,bawaan:string}>
 */
function wa_teks_katalog()
{
    return [
        'instruksi_bayar' => [
            'label' => 'Instruksi pembayaran setelah mendaftar',
            'kapan' => 'Otomatis, sesaat setelah calon murid menyelesaikan formulir registrasi.',
            'wajib' => ['{langkah}'],
            'placeholder' => [
                '{sekolah}'        => 'Nama sekolah',
                '{no_pendaftaran}' => 'Nomor pendaftaran',
                '{nama}'           => 'Nama siswa',
                '{jalur}'          => 'Jalur pendaftaran yang dipilih',
                '{langkah}'        => 'Daftar langkah bayar (hanya metode yang aktif; rekening, QRIS, tautan bayar)',
                '{tautan_cek}'     => 'Tautan cek status publik',
            ],
            'bawaan' => "Assalamu'alaikum.\n\n"
                . "Terima kasih telah mendaftar SPMB {sekolah}.\n\n"
                . "No. Pendaftaran : {no_pendaftaran}\n"
                . "Nama Siswa : {nama}\n"
                . "Jalur : {jalur}\n\n"
                . "Langkah selanjutnya:\n{langkah}\n\n"
                . "Cek status pendaftaran (cukup nomor WhatsApp orang tua):\n   {tautan_cek}\n\n"
                . "Simpan nomor pendaftaran ini.",
        ],
        'kredensial_akun' => [
            'label' => 'Akun & password portal siswa',
            'kapan' => 'Saat bendahara memverifikasi pembayaran dan akun siswa dibuat (juga dipakai tombol kirim ulang di halaman Akun).',
            'wajib' => ['{username}', '{password}'],
            'placeholder' => [
                '{nama}'           => 'Nama siswa',
                '{no_pendaftaran}' => 'Nomor pendaftaran (No. REG)',
                '{username}'       => 'Username akun portal',
                '{password}'       => 'Password awal akun portal',
                '{tahun_ajaran}'   => 'Baris "Tahun Ajaran: …" (kosong bila tidak diatur)',
                '{info_spp}'       => 'Baris info SPP (kosong bila SPP tidak diisi)',
                '{sekolah}'        => 'Nama sekolah',
            ],
            'bawaan' => "Assalamu'alaikum.\n\n"
                . "Akun SPMB/Portal Siswa telah dibuat untuk:\n"
                . "Nama : {nama}\n"
                . "No. REG : {no_pendaftaran}\n\n"
                . "Username : {username}\n"
                . "Password : {password}\n\n"
                . "Silakan login di portal sekolah. Mohon simpan kredensial ini baik-baik dan segera ganti password setelah login pertama.\n\n"
                . "{tahun_ajaran}\n{info_spp}\n"
                . "Salam,\nPanitia SPMB {sekolah}",
        ],
        'jadwal_tes' => [
            'label' => 'Pengumuman jadwal tes',
            'kapan' => 'Tombol "Umumkan Semua Jadwal" dan "Kirim WA Jadwal" per kegiatan di halaman Jadwal Ujian. Salinan yang sama dipakai untuk notifikasi lonceng portal siswa.',
            'wajib' => ['{jadwal_list}'],
            'placeholder' => [
                '{sekolah}'        => 'Nama sekolah',
                '{nama}'           => 'Nama siswa',
                '{no_pendaftaran}' => 'Nomor pendaftaran',
                '{jadwal_list}'    => 'Daftar bernomor kegiatan: nama (kode), tanggal, jam, metode, ruangan',
                '{total}'          => 'Baris "Total N jadwal yang harus diikuti." (kosong bila cuma satu kegiatan)',
            ],
            'bawaan' => "Assalamu'alaikum.\n\n"
                . "Jadwal tes seleksi SPMB {sekolah}:\n\n"
                . "Ananda: {nama} ({no_pendaftaran})\n\n"
                . "{jadwal_list}\n\n"
                . "{total}\n\n"
                . "Mohon hadir 15 menit lebih awal dan membawa kartu peserta (cetak di portal siswa), kartu pendaftaran, serta alat tulis.\n\n"
                . "Salam,\nPanitia SPMB",
        ],
        'hasil_seleksi' => [
            'label' => 'Pengumuman hasil seleksi',
            'kapan' => 'Tombol "Kirim WA ke Semua" di halaman Pengumuman. Satu kalimat yang sama untuk seluruh wali siswa — hasil LULUS/TIDAK LULUS sengaja TIDAK ditulis di WhatsApp, hanya di lonceng portal setelah login.',
            'wajib' => ['{tautan_hasil}'],
            'placeholder' => [
                '{sekolah}'        => 'Nama sekolah',
                '{tautan_hasil}'   => 'Tautan halaman hasil seleksi (login portal siswa)',
                '{tautan_cek}'     => 'Tautan cek status publik',
                '{nama}'           => 'Nama siswa (tidak dipakai di teks bawaan)',
                '{no_pendaftaran}' => 'Nomor pendaftaran (tidak dipakai di teks bawaan)',
                '{kelas}'          => 'Kode & nama kelas bila sudah ditempatkan (tidak dipakai di teks bawaan)',
            ],
            'bawaan' => "Assalamualaikum.\n\n"
                . "Alhamdulillah, Pengumuman Kelulusan seleksi SPMB {sekolah} sudah RILIS.\n\n"
                . "Silakan buka hasil seleksi lengkap pada tautan berikut (login memakai akun portal siswa):\n{tautan_hasil}\n\n"
                . "Silakan lanjutkan proses daftar ulang sesuai jadwal panitia. Info: {tautan_cek}\n\n"
                . "Panitia SPMB",
        ],
        'pengingat_biaya' => [
            'label' => 'Pengingat jatuh tempo biaya',
            'kapan' => 'Pengingat otomatis H-N jatuh tempo angsuran (saklar "Kirim pengingat via WhatsApp" di kartu Pengaturan Biaya).',
            'wajib' => ['{rincian}'],
            'placeholder' => [
                '{sekolah}'        => 'Nama sekolah',
                '{nama}'           => 'Nama siswa',
                '{no_pendaftaran}' => 'Nomor pendaftaran',
                '{nama_biaya}'     => 'Sebutan biaya yang ditagih (label Biaya Pendaftaran + angsuran ke-n)',
                '{rincian}'        => 'Rincian tagihan & jatuh temponya',
                '{transfer}'       => 'Blok rekening transfer (kosong bila metode transfer nonaktif)',
                '{tautan}'         => 'Tautan halaman Keuangan portal siswa',
                '{bantuan}'        => 'Baris "Bantuan panitia: <nomor>" (kosong bila kontak tidak diisi)',
            ],
            'bawaan' => "Assalamu'alaikum.\n\n"
                . "Pengingat pembayaran {nama_biaya} {sekolah}.\n\n"
                . "Nama : {nama}\n"
                . "No. Pendaftaran : {no_pendaftaran}\n\n"
                . "{rincian}\n\n"
                . "{transfer}\n\n"
                . "Rincian & pembayaran: {tautan}\n\n"
                . "{bantuan}\n\n"
                . "Salam,\nPanitia SPMB {sekolah}",
        ],
        'bukti_ditolak' => [
            'label' => 'Bukti pembayaran ditolak',
            'kapan' => 'Saat bendahara menolak bukti transfer dengan alasan (termasuk saat bukti perlu diunggah ulang).',
            'wajib' => ['{tautan}'],
            'placeholder' => [
                '{sekolah}'        => 'Nama sekolah',
                '{nama}'           => 'Nama siswa',
                '{no_pendaftaran}' => 'Nomor pendaftaran',
                '{nama_biaya}'     => 'Sebutan biaya yang dibayar (Biaya Pendaftaran / biaya formulir)',
                '{nominal}'        => 'Nominal yang diajukan, format rupiah',
                '{alasan}'         => 'Alasan penolakan yang ditulis bendahara',
                '{petunjuk}'       => 'Kalimat petunjuk unggah ulang',
                '{tautan}'         => 'Tautan unggah ulang / cek status',
            ],
            'bawaan' => "Assalamu'alaikum.\n\n"
                . "Mohon maaf, pembayaran {nama_biaya} SPMB atas nama:\n"
                . "Nama : {nama}\n"
                . "No. Pendaftaran : {no_pendaftaran}\n"
                . "Nominal : {nominal}\n\n"
                . "BELUM DAPAT kami setujui.\n"
                . "Alasan : {alasan}\n\n"
                . "{petunjuk}\n{tautan}\n\n"
                . "Terima kasih.\nPanitia SPMB {sekolah}",
        ],
        'berkas_ditolak' => [
            'label' => 'Berkas perlu diperbaiki',
            'kapan' => 'Saat panitia menolak satu/beberapa berkas pada Verifikasi Berkas. Salinan yang sama masuk ke lonceng portal siswa.',
            'wajib' => ['{daftar}'],
            'placeholder' => [
                '{sekolah}'        => 'Nama sekolah',
                '{nama}'           => 'Nama siswa',
                '{no_pendaftaran}' => 'Nomor pendaftaran',
                '{daftar}'         => 'Daftar berkas yang ditolak beserta alasannya',
            ],
            'bawaan' => "Assalamu'alaikum.\n\n"
                . "Hasil pemeriksaan berkas SPMB atas nama:\n"
                . "Nama : {nama}\n"
                . "No. Pendaftaran : {no_pendaftaran}\n\n"
                . "Berkas berikut perlu DIPERBAIKI / diganti:\n{daftar}\n\n"
                . "Silakan unggah ulang berkas tersebut melalui portal siswa, lalu klik \"Kirim untuk Verifikasi\" kembali. "
                . "Berkas yang sudah lolos tidak perlu diunggah ulang.\n\n"
                . "Terima kasih.\nPanitia SPMB {sekolah}",
        ],
    ];
}

/**
 * Template yang berlaku untuk satu kunci: override dari CMS bila ada dan
 * terisi, selain itu teks bawaan kode.
 */
function wa_teks_template($kunci)
{
    $katalog = wa_teks_katalog();
    if (!isset($katalog[$kunci])) {
        return '';
    }
    $mentah = trim((string) ($GLOBALS['PENGATURAN']['wa_teks'] ?? ''));
    if ($mentah !== '') {
        $simpan = json_decode($mentah, true);
        if (is_array($simpan) && isset($simpan[$kunci])) {
            $override = str_replace(["\r\n", "\r"], "\n", (string) $simpan[$kunci]);
            if (trim($override) !== '') {
                return trim($override);
            }
        }
    }
    return trim($katalog[$kunci]['bawaan']);
}

/**
 * Susun pesan dari template + isi placeholder. Nilai yang bukan string
 * (nomor, array asosiatif) tidak pernah diterima apa adanya — hanya string
 * yang dipakai, sisanya menjadi baris kosong sehingga barisnya ikut hilang.
 */
function wa_teks_isi($kunci, array $vars = [])
{
    $katalog = wa_teks_katalog();
    $teks    = wa_teks_template($kunci);
    if ($teks === '' || !isset($katalog[$kunci])) {
        return $teks;
    }
    $dikenal = array_keys($katalog[$kunci]['placeholder']); /* bentuk "{nama}" */

    $cari = [];
    $ganti = [];
    foreach ($vars as $nama => $nilai) {
        if (is_scalar($nilai) || $nilai === null) {
            $cari[]  = '{' . $nama . '}';
            $ganti[] = is_null($nilai) ? '' : trim((string) $nilai);
        }
    }
    $teks = str_replace($cari, $ganti, $teks);

    /* Placeholder yang dikenal tapi tidak diisi pemanggil, dan placeholder
     * hasil editan operator yang tidak lagi dipakai kode, sama-sama
     * dikosongkan — jangan pernah ada kurung kurawal yang ikut terkirim. */
    $sisa = array_values(array_diff($dikenal, $cari));
    $teks = str_replace($sisa, '', $teks);

    return wa_teks_rapikan($teks, $dikenal);
}

/**
 * Bersihkan hasil render: baris yang isinya hanya placeholder kosong dibuang
 * (bukan ditinggalkan sebagai baris kosong), lalu baris kosong beruntun
 * dirapatkan dan spasi di ujung baris dihapus.
 *
 * @param array<int,string> $dikenal daftar placeholder bentuk "{nama}"
 */
function wa_teks_rapikan($teks, array $dikenal = [])
{
    $teks = str_replace(["\r\n", "\r"], "\n", (string) $teks);
    $hasil = [];
    foreach (explode("\n", $teks) as $baris) {
        $sisa = trim(preg_replace('/\s+/u', ' ', str_replace($dikenal, '', trim($baris))));
        if ($sisa === '' && trim($baris) !== '') {
            continue; /* baris yang seluruh isinya placeholder kosong */
        }
        $hasil[] = rtrim($baris);
    }
    $teks = preg_replace('/\n{3,}/', "\n\n", implode("\n", $hasil));
    return trim((string) $teks, "\n ");
}

/**
 * Nilai template untuk ditampilkan di editor CMS: override bila ada,
 * teks bawaan bila tidak (editor selalu terisi supaya operator melihat
 * kalimat yang sedang berjalan).
 */
function wa_teks_editor($kunci)
{
    $katalog = wa_teks_katalog();
    if (!isset($katalog[$kunci])) {
        return '';
    }
    $mentah = trim((string) ($GLOBALS['PENGATURAN']['wa_teks'] ?? ''));
    if ($mentah !== '') {
        $simpan = json_decode($mentah, true);
        if (is_array($simpan) && isset($simpan[$kunci]) && trim((string) $simpan[$kunci]) !== '') {
            return str_replace(["\r\n", "\r"], "\n", trim((string) $simpan[$kunci]));
        }
    }
    return trim($katalog[$kunci]['bawaan']);
}

/**
 * Kunci yang sedang disunting operator berbeda dari bawaan (dipakai editor
 * untuk menandai "sudah diubah" dan oleh wa_teks_json() untuk menyimpan
 * sesedikit mungkin.
 */
function wa_teks_ubah(array $input)
{
    $katalog = wa_teks_katalog();
    $simpan  = [];
    foreach ($katalog as $kunci => $meta) {
        $teks = str_replace(["\r\n", "\r"], "\n", trim((string) ($input[$kunci] ?? '')));
        if ($teks === '' || $teks === trim($meta['bawaan'])) {
            continue; /* kosong atau sama dengan bawaan = jangan menyimpan apa-apa */
        }
        $simpan[$kunci] = $teks;
    }
    return $simpan;
}

/** Encode override menjadi nilai kolom wa_teks; tidak ada override = NULL. */
function wa_teks_json(array $input)
{
    $simpan = wa_teks_ubah($input);
    return $simpan ? json_encode($simpan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
}

/**
 * Validasi teks yang dikirim editor CMS.
 * @return array<int,string> daftar galat (kosong = aman disimpan)
 */
function wa_teks_galat(array $input)
{
    $katalog = wa_teks_katalog();
    $galat   = [];
    foreach ($katalog as $kunci => $meta) {
        $teks = trim((string) ($input[$kunci] ?? ''));
        if ($teks === '') {
            continue; /* kosong = kembali ke bawaan, tidak perlu divalidasi */
        }
        $nama = mb_strtolower($meta['label']);
        if (mb_strlen($teks) > WA_TEKS_MAX_PANJANG) {
            $galat[] = 'Teks "' . $meta['label'] . '" terlalu panjang (' . mb_strlen($teks)
                . ' karakter, maksimal ' . WA_TEKS_MAX_PANJANG . ') — pesan sepanjan itu biasanya terpotong di layar ponsel.';
            continue;
        }
        foreach ($meta['wajib'] as $wajib) {
            if (strpos($teks, $wajib) === false) {
                $galat[] = 'Teks "' . $nama . '" kehilangan ' . $wajib
                    . ' — tanpa itu pesan tidak lengkap. Sisipkan kembali placeholder tersebut.';
            }
        }
        /* Placeholder typo ({namaa}) akan terkirim mentah ke wali siswa. */
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $teks, $cocok);
        foreach (array_unique($cocok[1] ?? []) as $token) {
            if (!array_key_exists('{' . $token . '}', $meta['placeholder'])) {
                $galat[] = 'Teks "' . $nama . '" memakai placeholder tidak dikenal: {' . $token
                    . '}. Yang tersedia: ' . implode(', ', array_keys($meta['placeholder'])) . '.';
            }
        }
    }
    return $galat;
}
