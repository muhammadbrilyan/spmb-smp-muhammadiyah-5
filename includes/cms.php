<?php
/**
 * LAPISAN CMS — sumber data pengaturan sekolah & rombel.
 *
 * Nilai diambil dari database db_spmb_mualimku (tabel `pengaturan_sekolah`
 * dan `kelas`); bila database tidak aktif, nilai dummy di
 * includes/dummy.php tetap dipakai. Dipanggil dari includes/config.php
 * setelah db.php, sehingga seluruh view memakai $PENGATURAN / $ROMBEL
 * tanpa perlu diubah.
 */

/** Logo boleh URL luar, path lokal relatif, atau path lama ber-BASE_URL. */
function normalisasi_logo($logo)
{
    $logo = trim((string) $logo);
    if ($logo === '') {
        return asset('assets/img/uploads/logo-sekolah.png');
    }
    if (preg_match('#^(https?:)?//#i', $logo) || stripos($logo, 'data:') === 0) {
        return $logo;
    }
    if (BASE_URL !== '' && strpos($logo, BASE_URL . '/') === 0) {
        $logo = substr($logo, strlen(BASE_URL));
    }
    return url($logo);
}

/**
 * Pengaturan CMS (single row id = 1) — kolom DB menimpa nilai dummy.
 * @return array
 */
function pengaturan()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $nilai = $GLOBALS['PENGATURAN'] ?? [];

    $db = db();
    if ($db !== null) {
        try {
            $baris = $db->query('SELECT * FROM pengaturan_sekolah WHERE id = 1')->fetch();
            if ($baris) {
                /* kolom DB yang namanya berbeda di tampilan */
                $peta = ['ticker_header' => 'ticker'];
                foreach ($baris as $kolom => $nilai_kolom) {
                    if ($nilai_kolom === null || $nilai_kolom === '') {
                        continue;
                    }
                    $kunci = $peta[$kolom] ?? $kolom;
                    if ($kunci === 'id') {
                        continue;
                    }
                    $nilai[$kunci] = $nilai_kolom;
                }
            }
            /* Total kuota = jumlah seluruh rombel aktif. */
            $jumlah = $db->query('SELECT COALESCE(SUM(kuota),0) FROM kelas WHERE is_aktif = 1')->fetchColumn();
            if ($jumlah !== false && $jumlah !== null) {
                $nilai['kuota_total'] = (int) $jumlah;
            }
        } catch (Throwable $e) {
            /* database tidak terbaca -> nilai dummy tetap dipakai */
        }
    }

    $nilai['logo_url'] = normalisasi_logo($nilai['logo_url'] ?? '');
    /* Banner hero & banner login juga perlu dinormalisasi agar path
     * relatif diubah ke URL absolut — sama seperti logo. */
    $nilai['hero_banner_url']   = normalisasi_logo($nilai['hero_banner_url'] ?? '');
    $nilai['login_banner_url']  = normalisasi_logo($nilai['login_banner_url'] ?? '');
    /* Nilai default untuk kolom baru agar tidak kosong. */
    $nilai['deskripsi_sekolah'] = $nilai['deskripsi_sekolah']
        ?? 'Membentuk generasi muda yang unggul dalam ilmu pengetahuan, berkarakter Islam, dan berdaya saing global di wilayah Kota Tangerang.';
    $nilai['tagline']           = $nilai['tagline'] ?? 'Berakhlak & Berprestasi';
    $nilai['label_uang_pangkal'] = $nilai['label_uang_pangkal'] ?? 'Uang Pangkal Gedung';
    $nilai['label_spp']         = $nilai['label_spp'] ?? 'SPP Bulanan';
    $cache = $nilai;
    return $cache;
}

/**
 * Katalog lengkap jalur pendaftaran (key => label).
 * Key dipakai di DB pendaftar.jalur; label tampilan bisa diganti operator.
 */
function jalur_katalog()
{
    return [
        'Reguler'  => 'Reguler Akademik',
        'Tahfidz'  => 'Prestasi Tahfidz',
        'Prestasi' => 'Prestasi Sains & Robotik',
        'Afirmasi' => 'Afirmasi Muhammadiyah',
    ];
}

/**
 * Daftar jalur yang sedang AKTIF (diatur operator di CMS Pengaturan).
 * @return array<string,string> key => label
 */
function jalur_aktif()
{
    $katalog = jalur_katalog();
    $P       = pengaturan();
    $json    = $P['jalur_aktif'] ?? '';

    /* Bila kolom kosong / belum di-set, semua jalur aktif. */
    if (!is_string($json) || trim($json) === '') {
        return $katalog;
    }

    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return $katalog;
    }

    $hasil = [];
    foreach ($decoded as $key) {
        $key = (string) $key;
        if (isset($katalog[$key])) {
            $hasil[$key] = $katalog[$key];
        }
    }

    /* Cadangan: minimal harus ada satu jalur. */
    return $hasil ?: $katalog;
}

/**
 * Daftar rombel aktif, termasuk jumlah pendaftar yang sudah tertempat.
 * @return array<int, array{kode:string,nama:string,wali:string,kuota:int,terisi:int}>
 */
function rombel()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $hasil = $GLOBALS['ROMBEL'] ?? [];

    $db = db();
    if ($db !== null) {
        try {
            $baris = $db->query(
                'SELECT k.kode, k.nama_kelas, k.wali_kelas, k.kuota,
                        (SELECT COUNT(*) FROM pendaftar p WHERE p.kelas_id = k.id) AS terisi
                   FROM kelas k
                  WHERE k.is_aktif = 1
                  ORDER BY k.kode'
            )->fetchAll();
            if ($baris) {
                $hasil = [];
                foreach ($baris as $b) {
                    $hasil[] = [
                        'kode'   => $b['kode'],
                        'nama'   => $b['nama_kelas'],
                        'wali'   => $b['wali_kelas'],
                        'kuota'  => (int) $b['kuota'],
                        'terisi' => (int) $b['terisi'],
                    ];
                }
            }
        } catch (Throwable $e) {
            /* database tidak terbaca -> nilai dummy tetap dipakai */
        }
    }

    $cache = $hasil;
    return $cache;
}

/* ------------------------------------------------------------------
 * Terapkan ke variabel global yang dipakai seluruh view.
 * (require config.php dilakukan pada scope global setiap halaman)
 * ------------------------------------------------------------------ */
$PENGATURAN = pengaturan();
$ROMBEL     = rombel();
