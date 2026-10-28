<?php
/**
 * Modul queries: pengajuan koreksi data siswa (Fase 13).
 * Di-include dari includes/queries.php.
 */

/** Daftar bidang koreksi yang boleh dipilih siswa. */
function koreksi_bidang()
{
    return [
        'IDENTITAS'     => 'Identitas siswa',
        'ORANG_TUA'     => 'Data orang tua / wali',
        'ALAMAT_KONTAK' => 'Alamat & kontak aktif',
        'DOKUMEN'       => 'Dokumen persyaratan',
        'AKADEMIK'      => 'Data akademik dasar',
        'LAINNYA'       => 'Lainnya',
    ];
}

/** Label + warna status pengajuan koreksi. */
function koreksi_status_label($status)
{
    $peta = [
        'MENUNGGU'  => ['Menunggu Tinjauan', 'amber'],
        'DIPROSES'  => ['Sedang Diproses', 'blue'],
        'DISETUJUI' => ['Disetujui', 'green'],
        'DITOLAK'   => ['Ditolak', 'red'],
    ];
    return $peta[$status] ?? [$status, 'gray'];
}

/**
 * Ajukan koreksi data. Hanya untuk pendaftar yang datanya sudah
 * TERVERIFIKASI (terkunci) agar tidak tumpang tindih dengan edit biasa.
 *
 * @return array{ok:bool,pesan:string,id?:string}
 */
function koreksi_ajukan($pendaftar_id, $user_id, $bidang, $pesan)
{
    $pendaftar_id = trim((string) $pendaftar_id);
    $pesan        = trim((string) $pesan);
    $bidang       = strtoupper(trim((string) $bidang));

    if ($pendaftar_id === '') {
        return ['ok' => false, 'pesan' => 'Data pendaftar tidak ditemukan.'];
    }
    if (!array_key_exists($bidang, koreksi_bidang())) {
        return ['ok' => false, 'pesan' => 'Bidang koreksi tidak dikenali.'];
    }
    if (mb_strlen($pesan) < 10) {
        return ['ok' => false, 'pesan' => 'Uraian koreksi minimal 10 karakter agar panitia dapat menindaklanjuti.'];
    }
    if (mb_strlen($pesan) > 2000) {
        return ['ok' => false, 'pesan' => 'Uraian koreksi maksimal 2.000 karakter.'];
    }

    $status = q_col('SELECT status_verifikasi FROM pendaftar WHERE id = ?', [$pendaftar_id]);
    if ($status === null) {
        return ['ok' => false, 'pesan' => 'Data pendaftar tidak ditemukan.'];
    }
    if ($status !== 'TERVERIFIKASI') {
        return ['ok' => false, 'pesan' => 'Pengajuan koreksi tersedia setelah data Anda diverifikasi. Sebelum itu, gunakan formulir edit biasa.'];
    }

    $menunggu = (int) (q_col(
        "SELECT COUNT(*) FROM pengajuan_koreksi WHERE pendaftar_id = ? AND status IN ('MENUNGGU','DIPROSES')",
        [$pendaftar_id]
    ) ?? 0);
    if ($menunggu >= 3) {
        return ['ok' => false, 'pesan' => 'Masih ada 3 pengajuan yang sedang ditinjau. Tunggu tindak lanjut panitia sebelum mengajukan lagi.'];
    }

    $id = 'krx-' . bin2hex(random_bytes(6));
    $ok = q_exec(
        'INSERT INTO pengajuan_koreksi (id, pendaftar_id, user_id, bidang, pesan, status)
         VALUES (?, ?, ?, ?, ?, "MENUNGGU")',
        [$id, $pendaftar_id, $user_id ?: null, $bidang, $pesan]
    );
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal menyimpan pengajuan koreksi. Coba lagi.'];
    }

    notif_operator(
        'KOREKSI_DATA',
        $pendaftar_id,
        'Pengajuan koreksi data baru (' . (koreksi_bidang()[$bidang] ?? $bidang) . '): ' . mb_substr($pesan, 0, 160)
    );

    return ['ok' => true, 'pesan' => 'Pengajuan koreksi terkirim. Panitia akan meninjau dan menghubungi Anda.', 'id' => $id];
}

/** Riwayat pengajuan koreksi milik satu pendaftar (terbaru dulu). */
function koreksi_siswa($pendaftar_id)
{
    return q_all(
        'SELECT id, bidang, pesan, status, catatan_operator, reviewed_at, created_at, updated_at
           FROM pengajuan_koreksi
          WHERE pendaftar_id = ?
       ORDER BY created_at DESC
          LIMIT 25',
        [$pendaftar_id]
    ) ?: [];
}

/** Batalkan pengajuan yang belum ditinjau (status MENUNGGU). */
function koreksi_batalkan($id, $pendaftar_id)
{
    return q_exec(
        "UPDATE pengajuan_koreksi
            SET status = 'DITOLAK', catatan_operator = 'Dibatalkan oleh siswa.', reviewed_at = NOW()
          WHERE id = ? AND pendaftar_id = ? AND status = 'MENUNGGU'",
        [$id, $pendaftar_id]
    );
}

/** Antrean koreksi untuk operator (join data pendaftar). */
function koreksi_antrean($status = '')
{
    $sql = 'SELECT k.id, k.bidang, k.pesan, k.status, k.catatan_operator, k.created_at,
                   k.reviewed_at, p.id AS pendaftar_id, p.no_pendaftaran, p.nama_siswa,
                   p.status_verifikasi, u.nama_lengkap AS reviewer
              FROM pengajuan_koreksi k
              JOIN pendaftar p ON p.id = k.pendaftar_id
         LEFT JOIN users u ON u.id = k.reviewed_by
          WHERE p.arsip_at IS NULL';
    $params = [];
    if ($status !== '' && array_key_exists($status, koreksi_status_label($status))) {
        $sql .= ' AND k.status = ?';
        $params[] = $status;
    }
    $sql .= ' ORDER BY FIELD(k.status, "MENUNGGU", "DIPROSES", "DISETUJUI", "DITOLAK"), k.created_at DESC LIMIT 100';
    return q_all($sql, $params) ?: [];
}

/**
 * Ubah status pengajuan koreksi (operator).
 * @return array{ok:bool,pesan:string}
 */
function koreksi_ubah_status($id, $status, $catatan, $reviewer_id)
{
    $status = strtoupper(trim((string) $status));
    if (!in_array($status, ['MENUNGGU', 'DIPROSES', 'DISETUJUI', 'DITOLAK'], true)) {
        return ['ok' => false, 'pesan' => 'Status koreksi tidak valid.'];
    }
    $catatan = trim((string) $catatan);
    if ($status === 'DITOLAK' && $catatan === '') {
        return ['ok' => false, 'pesan' => 'Alasan penolakan wajib diisi.'];
    }

    $baris = q_all(
        'SELECT k.id, k.pendaftar_id, k.status, p.status_verifikasi
           FROM pengajuan_koreksi k
           JOIN pendaftar p ON p.id = k.pendaftar_id
          WHERE k.id = ? LIMIT 1',
        [$id]
    );
    $row = ($baris !== null && $baris) ? $baris[0] : null;
    if (!$row) {
        return ['ok' => false, 'pesan' => 'Pengajuan koreksi tidak ditemukan.'];
    }

    $ok = q_exec(
        'UPDATE pengajuan_koreksi
            SET status = ?, catatan_operator = ?, reviewed_by = ?, reviewed_at = NOW()
          WHERE id = ?',
        [$status, $catatan !== '' ? $catatan : null, $reviewer_id ?: null, $id]
    );
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal memperbarui pengajuan koreksi.'];
    }

    /* Koreksi disetujui → buka kembali data siswa agar ia dapat memperbaiki
     * sendiri. Data TIDAK diubah otomatis oleh operator. */
    if ($status === 'DISETUJUI' && $row['status_verifikasi'] === 'TERVERIFIKASI') {
        q_exec(
            "UPDATE pendaftar
                SET status_verifikasi = 'MENUNGGU_VERIFIKASI',
                    catatan_verifikasi_data = ?,
                    data_verified_at = NULL, data_verified_by = NULL
              WHERE id = ? AND status_verifikasi = 'TERVERIFIKASI'",
            [
                $catatan !== ''
                    ? $catatan
                    : 'Koreksi data disetujui. Perbarui data sesuai pengajuan, lalu ajukan verifikasi kembali.',
                $row['pendaftar_id'],
            ]
        );
    }

    return ['ok' => true, 'pesan' => 'Status pengajuan koreksi diperbarui.'];
}
