<?php
/**
 * Modul queries: kelas — di-include dari includes/queries.php.
 */

function daftar_kelas_admin()
{
    $baris = q_all(
        'SELECT k.id, k.kode, k.nama_kelas, k.wali_kelas, k.kuota, k.is_aktif,
                (SELECT COUNT(*) FROM pendaftar p WHERE p.kelas_id = k.id) AS terisi
           FROM kelas k
       ORDER BY k.kode'
    );
    return $baris ?: [];
}

/** Tambah kelas baru. return id kelas atau null. */

function kelas_tambah($kode, $nama, $wali, $kuota, $aktif = 1)
{
    $id = 'kls-' . bin2hex(random_bytes(4));
    $ok = q_exec(
        'INSERT INTO kelas (id, kode, nama_kelas, wali_kelas, kuota, is_aktif)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$id, $kode, $nama, $wali, (int) $kuota, $aktif ? 1 : 0]
    );
    return $ok ? $id : null;
}

/** Ubah kelas. */

function kelas_ubah($id, $kode, $nama, $wali, $kuota, $aktif)
{
    return q_exec(
        'UPDATE kelas SET kode = ?, nama_kelas = ?, wali_kelas = ?, kuota = ?, is_aktif = ?
          WHERE id = ?',
        [$kode, $nama, $wali, (int) $kuota, $aktif ? 1 : 0, $id]
    );
}

/** Hapus kelas. false bila masih ada pendaftar tertempat. */

function kelas_hapus($id)
{
    $terisi = (int) (q_col('SELECT COUNT(*) FROM pendaftar WHERE kelas_id = ?', [$id]) ?? 0);
    if ($terisi > 0) {
        return false;
    }
    return q_exec('DELETE FROM kelas WHERE id = ?', [$id]);
}

/** Cek apakah kode kelas sudah dipakai (untuk validasi). */

function kelas_kode_dipakai($kode, $kecualiId = null)
{
    if ($kecualiId) {
        $n = q_col('SELECT COUNT(*) FROM kelas WHERE kode = ? AND id != ?', [$kode, $kecualiId]);
    } else {
        $n = q_col('SELECT COUNT(*) FROM kelas WHERE kode = ?', [$kode]);
    }
    return (int) ($n ?? 0) > 0;
}

/* ==================================================================
 * 9. JADWAL TES & SELEKSI — tulis
 * ================================================================== */

/** Tambah jadwal tes baru. */
