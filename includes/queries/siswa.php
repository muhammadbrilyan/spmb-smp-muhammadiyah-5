<?php
/**
 * Modul queries: siswa — di-include dari includes/queries.php.
 */

function profil_siswa()
{
    $uid = current_user()['id'] ?? null;
    if ($uid === null) {
        return null;
    }

    $baris = q_all(
        'SELECT p.*, o.nama_orang_tua, o.no_whatsapp, o.pekerjaan, o.alamat AS alamat_orang_tua,
                k.kode AS kelas_kode, k.nama_kelas
           FROM pendaftar p
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
      LEFT JOIN kelas k ON k.id = p.kelas_id
          WHERE p.user_id = ?
       LIMIT 1',
        [$uid]
    );

    if ($baris === null || !$baris) {
        return null;
    }
    return $baris[0];
}

/**
 * Pembayaran milik satu pendaftar (urut terbaru).
 */

function siswa_update_biodata($pendaftar_id, array $d)
{
    return q_exec(
        'UPDATE pendaftar SET nisn = ?, jenis_kelamin = ?, tempat_lahir = ?, tanggal_lahir = ?,
                alamat = ?, asal_sekolah = ?, is_luar_daerah = ?
          WHERE id = ? AND status_verifikasi IN ("DRAFT","MENUNGGU_VERIFIKASI")',
        [
            $d['nisn'] !== '' ? $d['nisn'] : null,
            $d['jenis_kelamin'],
            $d['tempat_lahir'] !== '' ? $d['tempat_lahir'] : null,
            $d['tanggal_lahir'] !== '' ? $d['tanggal_lahir'] : null,
            $d['alamat'],
            $d['asal_sekolah'],
            $d['is_luar_daerah'] ? 1 : 0,
            $pendaftar_id,
        ]
    );
}

/**
 * Unggah bukti transfer — update baris pembayaran PENDING, atau tambah baru.
 * Mengembalikan id pembayaran.
 */

/* Catatan: dokumen_siswa() & unggah dokumen kini ada di queries/dokumen.php
 * (Fase 5 — daftar jenis dinamis dari tabel jenis_dokumen). */

/** Hapus berkas fisik bila path ada di folder upload/storage aplikasi. */
