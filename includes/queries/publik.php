<?php
/**
 * Modul queries: publik — di-include dari includes/queries.php.
 */

function cek_status_cari($no_pendaftaran = '', $wa = '', $nama = '', $id = null)
{
    $no_pendaftaran = trim((string) $no_pendaftaran);
    $wa             = trim((string) $wa);
    $nama           = trim((string) $nama);
    $id             = ($id === null || $id === '') ? null : (string) $id;

    if ($no_pendaftaran === '' && $wa === '' && $nama === '' && $id === null) {
        return [];
    }

    $where  = [];
    $params = [];

    if ($id !== null) {
        $where[]  = 'p.id = ?';
        $params[] = $id;
    }

    if ($no_pendaftaran !== '') {
        $where[]  = 'p.no_pendaftaran = ?';
        $params[] = $no_pendaftaran;
    }

    if ($wa !== '') {
        $waD = preg_replace('/\D+/', '', $wa);
        if ($waD !== '' && strpos($waD, '0') === 0) {
            $waD = '62' . substr($waD, 1);
        }
        if ($waD !== '') {
            /* Nomor tersimpan '+62…' lewat jalur registrasi, tetapi suntingan
             * panitia bisa menulis '08…'. Bentuk diuji lewat daftar nilai, bukan
             * fungsi SQL, supaya indeks kolom tetap terpakai. */
            $bentuk = ['+' . $waD, $waD];
            if (strpos($waD, '62') === 0) {
                $bentuk[] = '0' . substr($waD, 2);
            }
            $where[]  = 'o.no_whatsapp IN (' . implode(', ', array_fill(0, count($bentuk), '?')) . ')';
            $params   = array_merge($params, $bentuk);
        }
    }

    if ($nama !== '') {
        $where[]  = 'p.nama_siswa LIKE ?';
        $params[] = '%' . $nama . '%';
    }

    return q_all(
        "SELECT p.id, p.no_pendaftaran, p.nama_siswa, p.jalur, p.status_verifikasi,
                p.status_seleksi, p.status_akun, p.kred_sent_at, p.created_at,
                p.tahap, o.no_whatsapp, o.nama_orang_tua
           FROM pendaftar p
           JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.arsip_at IS NULL
            AND " . implode(' AND ', $where) . "
       ORDER BY p.created_at DESC
       LIMIT 10",
        $params
    ) ?: [];
}

/**
 * Cari satu pendaftar by no_pendaftaran + WA (dipakai legacy/compat).
 * @return array|null
 */

function cek_status_cari_tunggal($no_pendaftaran, $wa)
{
    $hasil = cek_status_cari($no_pendaftaran, $wa, '');
    return $hasil ? $hasil[0] : null;
}

/**
 * Buat pay_token BARU untuk pendaftar yang sudah terbukti di halaman cek status.
 * Token lama otomatis tidak berlaku (digantikan).
 *
 * Syarat: tahap MENUNGGU_PEMBAYARAN. Verifikasi identitas dilakukan pemanggil
 * (sesi cek-status), bukan di sini — jadi tidak perlu mengetik ulang digit WA.
 *
 * @return array{ok:bool, pesan:string, tautan?:string}
 */
function bayar_tautan_baru($pendaftar_id)
{
    $pendaftar_id = trim((string) $pendaftar_id);
    if ($pendaftar_id === '') {
        return ['ok' => false, 'pesan' => 'Data tidak ditemukan.'];
    }

    $baris = q_all(
        'SELECT id, tahap FROM pendaftar WHERE id = ? AND arsip_at IS NULL LIMIT 1',
        [$pendaftar_id]
    );
    $baris = $baris ? ($baris[0] ?? null) : null;
    if (!$baris) {
        return ['ok' => false, 'pesan' => 'Data tidak ditemukan.'];
    }
    if ($baris['tahap'] !== 'MENUNGGU_PEMBAYARAN') {
        return ['ok' => false, 'pesan' => 'Tautan pembayaran hanya dapat dibuat sebelum pembayaran diverifikasi.'];
    }

    $tokenBaru = bin2hex(random_bytes(32));
    /* Fase 11 A6: hanya SHA-256 yang disimpan; token polos tampil sekali di sini. */
    if (!q_exec('UPDATE pendaftar SET pay_token_hash = ? WHERE id = ?', [hash('sha256', $tokenBaru), $baris['id']])) {
        return ['ok' => false, 'pesan' => 'Gagal membuat tautan pembayaran. Coba lagi nanti.'];
    }

    return [
        'ok'     => true,
        'pesan'  => 'Tautan pembayaran baru dibuat. Tautan lama otomatis tidak berlaku.',
        'tautan' => 'app/spmb/bayar.php?t=' . $tokenBaru,
    ];
}

/**
 * Ambil satu pendaftar (termasuk nomor WA-nya) berdasar id.
 * Dipakai pemanggil setelah identitas terverifikasi lewat sesi.
 * @return array|null
 */
function cek_status_satu($pendaftar_id)
{
    $pendaftar_id = trim((string) $pendaftar_id);
    if ($pendaftar_id === '') {
        return null;
    }
    /* id pendaftar adalah string (uuid pendek), jadi jangan pernah dibulatkan. */
    $hasil = cek_status_cari('', '', '', $pendaftar_id);
    return $hasil ? ($hasil[0] ?? null) : null;
}

/**
 * Ringkasan status untuk halaman cek publik.
 * @return array|null
 */

function cek_status_ringkas($pendaftar_id)
{
    if (!db_aktif() || !$pendaftar_id) {
        return null;
    }
    $bayar = q_all(
        'SELECT jenis_pembayaran, angsuran_ke, nominal, status FROM pembayaran WHERE pendaftar_id = ? ORDER BY created_at',
        [$pendaftar_id]
    ) ?: [];
    $sel = q_all(
        'SELECT nilai_akhir, hasil, j.nama_kegiatan, j.tanggal FROM seleksi s
      LEFT JOIN jadwal_tes j ON j.id = s.jadwal_id
          WHERE s.pendaftar_id = ? LIMIT 1',
        [$pendaftar_id]
    );
    $pen = q_all(
        'SELECT k.kode, k.nama_kelas, k.wali_kelas FROM penempatan_kelas pk
           JOIN kelas k ON k.id = pk.kelas_id
          WHERE pk.pendaftar_id = ? LIMIT 1',
        [$pendaftar_id]
    );
    $dok = q_all(
        'SELECT COUNT(*) AS total,
                SUM(CASE WHEN status_verifikasi = "VERIFIED" THEN 1 ELSE 0 END) AS verified
           FROM dokumen WHERE pendaftar_id = ?',
        [$pendaftar_id]
    );

    return [
        'bayar'    => $bayar,
        'seleksi'  => ($sel !== null && $sel) ? $sel[0] : null,
        'penempatan' => ($pen !== null && $pen) ? $pen[0] : null,
        'dok_total' => (int) ($dok[0]['total'] ?? 0),
        'dok_verified' => (int) ($dok[0]['verified'] ?? 0),
    ];
}

/* ==================================================================
 * 15. EXPORT REKAP CSV
 * ================================================================== */

/**
 * Siapkan baris export: 'pendaftar', 'seleksi', 'pembayaran'.
 * @return array{header:array,baris:array}
 */
