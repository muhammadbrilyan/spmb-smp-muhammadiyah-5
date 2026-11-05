<?php
/**
 * Modul queries: seleksi "” di-include dari includes/queries.php.
 */


function daftar_hasil_seleksi()
{
    global $HASIL_SELEKSI;

    $baris = q_all(
        "SELECT p.no_pendaftaran, p.nama_siswa, s.nilai_cbt, s.nilai_btq,
                s.nilai_wawancara, s.nilai_akhir, s.hasil
           FROM seleksi s
           JOIN pendaftar p ON p.id = s.pendaftar_id
       ORDER BY p.no_pendaftaran"
    );
    if ($baris === null) {
        return $HASIL_SELEKSI;
    }

    $keluar = [];
    foreach ($baris as $r) {
        $keluar[] = [
            'reg'    => $r['no_pendaftaran'],
            'nama'   => $r['nama_siswa'],
            'cbt'    => (int) $r['nilai_cbt'],
            'btq'    => (int) $r['nilai_btq'],
            'waw'    => (int) $r['nilai_wawancara'],
            'nilai'  => (float) $r['nilai_akhir'],
            'status' => $r['hasil'],
        ];
    }

    return $keluar; /* kosong = memang belum ada hasil (bukan fallback dummy) */
}

/**
 * Judul tiga kolom nilai di halaman input hasil seleksi.
 * Diambil dari nama kegiatan jadwal tes (3 teratas, urut tanggal & jam) supaya
 * istilah yang tampil sama dengan yang dibuat panitia. Angka tetap masuk ke
 * slot lama: kolom 1 = nilai_cbt, kolom 2 = nilai_btq, kolom 3 = nilai_wawancara.
 */
function seleksi_label_kolom()
{
    $bawaan = ['Baca Tulis', 'BTQ', 'Tes Kesehatan'];
    $jadwal = db_aktif() ? (q_all(
        'SELECT kode, nama_kegiatan FROM jadwal_tes
          ORDER BY tanggal, jam_mulai, kode'
    ) ?: []) : [];
    $pakai  = array_slice($jadwal, 0, 3);

    $label = $lengkap = $sumber = [];
    foreach ($pakai as $i => $j) {
        $nama     = trim(preg_replace('/\s+/u', ' ', (string) $j['nama_kegiatan']));
        $singkat  = seleksi_singkat_nama($nama);
        $sumber[] = (string) $j['kode'];
        $lengkap[]= $nama;
        $label[]  = $singkat !== '' ? $singkat : $bawaan[$i];
    }
    for ($i = count($pakai); $i < 3; $i++) {
        $label[] = $bawaan[$i];
    }

    return [
        'label'   => $label,
        'lengkap' => $lengkap,
        'sumber'  => $sumber,
        'jumlah'  => count($pakai),
        'lebih'   => max(0, count($jadwal) - 3),
    ];
}

/**
 * Nama kegiatan -> judul kolom pendek: buang kata generik, pakai bagian
 * terakhir sesudah "&" untuk kegiatan gabungan, lalu potong pada batas kata
 * supaya tabel tidak melebar. Nama lengkap tetap ada di atribut title.
 */
function seleksi_singkat_nama($nama)
{
    $bersih = preg_replace('/\b(test|tes|ujian|seleksi|kegiatan)\b/iu', ' ', (string) $nama);
    $bersih = trim(preg_replace('/\s+/u', ' ', (string) $bersih), " \t\n\r\0\x0B.,-–—&");
    if (mb_strlen($bersih) <= 14) {
        return $bersih;
    }
    $segmen = preg_split('/\s*&\s*/u', $bersih) ?: [];
    if (count($segmen) > 1) {
        $bersih = trim((string) end($segmen));
        if (mb_strlen($bersih) <= 14) {
            return $bersih;
        }
    }
    if (mb_strlen($bersih) <= 20) {
        return $bersih;
    }
    $potong = mb_substr($bersih, 0, 15);
    $spasi  = mb_strrpos($potong, ' ');
    if ($spasi !== false && $spasi > 6) {
        $potong = mb_substr($potong, 0, $spasi);
    }
    return trim($potong) . '…';
}

/* ==================================================================
 * 5. DASHBOARD BENDAHARA "” daftar pembayaran
 * ================================================================== */


function seleksi_siswa($pendaftar_id)
{
    if (!db_aktif() || !$pendaftar_id) {
        return null;
    }
    $baris = q_all(
        'SELECT s.*, j.nama_kegiatan, j.tanggal, j.jam_mulai, j.jam_selesai, j.mode
           FROM seleksi s
      LEFT JOIN jadwal_tes j ON j.id = s.jadwal_id
          WHERE s.pendaftar_id = ?
       LIMIT 1',
        [$pendaftar_id]
    );
    return ($baris !== null && $baris) ? $baris[0] : null;
}

/**
 * Penempatan kelas milik satu pendaftar.
 */

function seleksi_simpan($pendaftarId, $jadwalId, $cbt, $btq, $waw, $akhir, $hasil)
{
    $ada = q_col('SELECT id FROM seleksi WHERE pendaftar_id = ?', [$pendaftarId]);
    if ($ada) {
        $ok = q_exec(
            'UPDATE seleksi SET jadwal_id = ?, nilai_cbt = ?, nilai_btq = ?, nilai_wawancara = ?,
                    nilai_akhir = ?, hasil = ?, updated_at = NOW()
              WHERE pendaftar_id = ?',
            [$jadwalId, $cbt, $btq, $waw, $akhir, $hasil, $pendaftarId]
        );
    } else {
        $id = 'sel-' . bin2hex(random_bytes(4));
        $ok = q_exec(
            'INSERT INTO seleksi (id, pendaftar_id, jadwal_id, nilai_cbt, nilai_btq, nilai_wawancara, nilai_akhir, hasil, input_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $pendaftarId, $jadwalId, $cbt, $btq, $waw, $akhir, $hasil, current_user()['id'] ?? null]
        );
    }
    if ($ok) {
        q_exec('UPDATE pendaftar SET status_seleksi = ? WHERE id = ?', [$hasil, $pendaftarId]);
        /* Fase 7: setelah nilai masuk → NILAI_TERINPUT (tidak menurunkan tahap lanjutan). */
        q_exec('UPDATE pendaftar SET tahap = "NILAI_TERINPUT" WHERE id = ? AND tahap = "JADWAL_DITETAPKAN"', [$pendaftarId]);
    }
    return $ok;
}

/* ==================================================================
 * 10. GENERATE AKUN SISWA
 * ================================================================== */

/** Detail pendaftar untuk generate akun & kirim kredensial. */
