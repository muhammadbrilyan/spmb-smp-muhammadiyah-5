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

    return $keluar ?: $HASIL_SELEKSI;
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

/**
 * Impor nilai CSV (Fase 7): upsert nilai tanpa mengubah keputusan
 * LULUS/TIDAK_LULUS yang sudah ditetapkan operator (hasil dipertahankan).
 * @return bool
 */
function seleksi_impor_nilai($pendaftarId, $jadwalId, $cbt, $btq, $waw)
{
    $akhir = round(($cbt + $btq + $waw) / 3, 2);
    $ada = q_col('SELECT id FROM seleksi WHERE pendaftar_id = ?', [$pendaftarId]);
    if ($ada) {
        $hasilLama = q_col('SELECT hasil FROM seleksi WHERE pendaftar_id = ?', [$pendaftarId]) ?: 'PROSES';
        $ok = q_exec(
            'UPDATE seleksi SET jadwal_id = ?, nilai_cbt = ?, nilai_btq = ?, nilai_wawancara = ?,
                    nilai_akhir = ?, updated_at = NOW()
              WHERE pendaftar_id = ?',
            [$jadwalId, $cbt, $btq, $waw, $akhir, $pendaftarId]
        );
        if ($ok) {
            q_exec('UPDATE pendaftar SET status_seleksi = ? WHERE id = ?', [$hasilLama, $pendaftarId]);
        }
    } else {
        $id  = 'sel-' . bin2hex(random_bytes(4));
        $ok  = q_exec(
            'INSERT INTO seleksi (id, pendaftar_id, jadwal_id, nilai_cbt, nilai_btq, nilai_wawancara, nilai_akhir, hasil, input_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, "PROSES", ?)',
            [$id, $pendaftarId, $jadwalId, $cbt, $btq, $waw, $akhir, current_user()['id'] ?? null]
        );
        if ($ok) {
            q_exec('UPDATE pendaftar SET status_seleksi = "PROSES" WHERE id = ?', [$pendaftarId]);
        }
    }
    if ($ok) {
        q_exec('UPDATE pendaftar SET tahap = "NILAI_TERINPUT" WHERE id = ? AND tahap = "JADWAL_DITETAPKAN"', [$pendaftarId]);
    }
    return (bool) $ok;
}

/* ==================================================================
 * 10. GENERATE AKUN SISWA
 * ================================================================== */

/** Detail pendaftar untuk generate akun & kirim kredensial. */
