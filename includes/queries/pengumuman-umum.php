<?php
/**
 * Modul queries: PENGUMUMAN UMUM (papan informasi bebas dari panitia).
 * Di-include dari includes/queries.php.
 *
 * - Operator membuat/mengedit pengumuman (DRAFT / TERBIT).
 * - Saat diterbitkan, dibuatkan notifikasi_siswa untuk semua pendaftar aktif
 *   (ref_id = id pengumuman) sehingga muncul di lonceng & halaman Notifikasi.
 * - Ditarik/dihapus → notifikasi turunannya ikut dihapus.
 */

/** Daftar semua pengumuman untuk operator (terbaru dulu). */
function pengumuman_umum_semua()
{
    $rows = q_all(
        'SELECT pu.*, u.nama_lengkap AS pembuat
           FROM pengumuman_umum pu
      LEFT JOIN users u ON u.id = pu.created_by
       ORDER BY pu.created_at DESC'
    );
    return $rows ?: [];
}

/**
 * Daftar pengumuman yang sudah terbit & sudah waktunya tampil.
 * @return array<int,array{id:string,judul:string,isi:string,penting:int,terbit_at:string}>
 */
function pengumuman_umum_terbit($limit = 20)
{
    $limit = max(1, min(100, (int) $limit));
    $rows = q_all(
        "SELECT id, judul, isi, penting, terbit_at
           FROM pengumuman_umum
          WHERE status = 'TERBIT' AND terbit_at IS NOT NULL AND terbit_at <= NOW()
       ORDER BY penting DESC, terbit_at DESC
          LIMIT " . $limit
    );
    return $rows ?: [];
}

/** Satu baris pengumuman (null bila tidak ada). */
function pengumuman_umum_satu($id)
{
    $row = q_all('SELECT * FROM pengumuman_umum WHERE id = ? LIMIT 1', [(string) $id]);
    return $row ? $row[0] : null;
}

/** Ringkas isi pengumuman untuk pesan notifikasi. */
function pengumuman_umum_ringkas($isi, $maks = 320)
{
    $teks = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $isi)));
    return mb_strlen($teks) > $maks ? mb_substr($teks, 0, $maks) . '…' : $teks;
}

/**
 * Simpan (tambah atau ubah) pengumuman.
 * Bila yang diubah sudah TERBIT, notifikasi yang sudah ada ikut diperbarui
 * judul/pesannya (status baca siswa dipertahankan).
 *
 * @return array{ok:bool, pesan:string, id?:string}
 */
function pengumuman_umum_simpan($id, $judul, $isi, $penting, $oleh = null)
{
    $judul = trim((string) $judul);
    $isi = trim((string) $isi);
    if ($judul === '') {
        return ['ok' => false, 'pesan' => 'Judul pengumuman wajib diisi.'];
    }
    if ($isi === '') {
        return ['ok' => false, 'pesan' => 'Isi pengumuman wajib diisi.'];
    }
    $penting = !empty($penting) ? 1 : 0;
    $judul = mb_substr($judul, 0, 160);

    $id = trim((string) $id);
    if ($id === '') {
        $id = 'ann-' . bin2hex(random_bytes(8));
        $ok = q_exec(
            'INSERT INTO pengumuman_umum (id, judul, isi, penting, created_by) VALUES (?, ?, ?, ?, ?)',
            [$id, $judul, $isi, $penting, $oleh ?: null]
        );
        return $ok
            ? ['ok' => true, 'pesan' => 'Pengumuman disimpan sebagai draft.', 'id' => $id]
            : ['ok' => false, 'pesan' => 'Gagal menyimpan pengumuman.'];
    }

    $lama = pengumuman_umum_satu($id);
    if (!$lama) {
        return ['ok' => false, 'pesan' => 'Pengumuman tidak ditemukan.'];
    }
    $ok = q_exec(
        'UPDATE pengumuman_umum SET judul = ?, isi = ?, penting = ? WHERE id = ?',
        [$judul, $isi, $penting, $id]
    );
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal memperbarui pengumuman.'];
    }
    if ($lama['status'] === 'TERBIT') {
        q_exec(
            'UPDATE notifikasi_siswa SET judul = ?, pesan = ? WHERE ref_id = ?',
            [$judul, pengumuman_umum_ringkas($isi), $id]
        );
    }
    return ['ok' => true, 'pesan' => 'Perubahan pengumuman disimpan.', 'id' => $id];
}

/**
 * Terbitkan pengumuman + siarkan notifikasi ke semua pendaftar aktif.
 * @return array{ok:bool, pesan:string}
 */
function pengumuman_umum_terbitkan($id)
{
    $row = pengumuman_umum_satu($id);
    if (!$row) {
        return ['ok' => false, 'pesan' => 'Pengumuman tidak ditemukan.'];
    }
    $ok = q_exec(
        "UPDATE pengumuman_umum SET status = 'TERBIT', terbit_at = NOW() WHERE id = ?",
        [$row['id']]
    );
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal menerbitkan pengumuman.'];
    }
    notif_siswa_hapus_ref($row['id']);
    $jml = notif_siswa_siarkan(
        'PENGUMUMAN_UMUM',
        $row['judul'],
        pengumuman_umum_ringkas($row['isi']),
        url('app/siswa/notifikasi.php') . '#pgn-' . $row['id'],
        $row['id']
    );
    return ['ok' => true, 'pesan' => 'Pengumuman diterbitkan dan notifikasi dikirim ke ' . $jml . ' pendaftar.'];
}

/**
 * Tarik pengumuman kembali ke draft (hilang dari portal & notifikasi dihapus).
 * @return array{ok:bool, pesan:string}
 */
function pengumuman_umum_tarik($id)
{
    $row = pengumuman_umum_satu($id);
    if (!$row) {
        return ['ok' => false, 'pesan' => 'Pengumuman tidak ditemukan.'];
    }
    $ok = q_exec("UPDATE pengumuman_umum SET status = 'DRAFT', terbit_at = NULL WHERE id = ?", [$row['id']]);
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal menarik pengumuman.'];
    }
    notif_siswa_hapus_ref($row['id']);
    return ['ok' => true, 'pesan' => 'Pengumuman ditarik kembali menjadi draft.'];
}

/**
 * Hapus permanen pengumuman beserta notifikasinya.
 * @return array{ok:bool, pesan:string}
 */
function pengumuman_umum_hapus($id)
{
    $row = pengumuman_umum_satu($id);
    if (!$row) {
        return ['ok' => false, 'pesan' => 'Pengumuman tidak ditemukan.'];
    }
    $ok = q_exec('DELETE FROM pengumuman_umum WHERE id = ?', [$row['id']]);
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal menghapus pengumuman.'];
    }
    notif_siswa_hapus_ref($row['id']);
    return ['ok' => true, 'pesan' => 'Pengumuman dihapus.'];
}
