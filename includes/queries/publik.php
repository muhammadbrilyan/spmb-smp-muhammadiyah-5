<?php
/**
 * Modul queries: publik — di-include dari includes/queries.php.
 */

function cek_status_cari($no_pendaftaran = '', $wa = '', $nama = '')
{
    $no_pendaftaran = trim((string) $no_pendaftaran);
    $wa             = trim((string) $wa);
    $nama           = trim((string) $nama);

    if ($no_pendaftaran === '' && $wa === '' && $nama === '') {
        return [];
    }

    $where  = [];
    $params = [];

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
            $where[]  = '(o.no_whatsapp = ? OR o.no_whatsapp = ?)';
            $params[] = '+' . $waD;
            $params[] = $waD;
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
 * Pulihkan tautan pembayaran: buat pay_token BARU untuk pendaftar.
 * Token lama otomatis tidak berlaku (digantikan).
 *
 * Syarat: tahap MENUNGGU_PEMBAYARAN + 4 digit terakhir WA orang tua cocok.
 *
 * @return array{ok:bool, pesan:string, tautan?:string}
 */
function pulihkan_pay_token($no_pendaftaran, $wa4)
{
    $no = trim((string) $no_pendaftaran);
    $wa4 = preg_replace('/\D+/', '', (string) $wa4);

    if ($no === '' || strlen($wa4) !== 4) {
        return ['ok' => false, 'pesan' => 'Nomor pendaftaran dan 4 digit WhatsApp wajib diisi.'];
    }

    $hasilQ = q_all(
        'SELECT p.id, p.tahap, o.no_whatsapp
           FROM pendaftar p
           JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.no_pendaftaran = ?
            AND p.arsip_at IS NULL
          LIMIT 1',
        [$no]
    );
    $baris = $hasilQ ? ($hasilQ[0] ?? null) : null;
    if (!$baris) {
        return ['ok' => false, 'pesan' => 'Data tidak ditemukan.'];
    }
    if ($baris['tahap'] !== 'MENUNGGU_PEMBAYARAN') {
        return ['ok' => false, 'pesan' => 'Tautan pembayaran hanya dapat dipulihkan sebelum pembayaran diverifikasi.'];
    }

    /* Cocokkan 4 digit terakhir WA (simpan dalam format 62xxx / +62xxx). */
    $waAsli = preg_replace('/\D+/', '', (string) $baris['no_whatsapp']);
    if ($waAsli === '' || substr($waAsli, -4) !== $wa4) {
        return ['ok' => false, 'pesan' => '4 digit WhatsApp tidak cocok.'];
    }

    $tokenBaru = bin2hex(random_bytes(32));
    /* Fase 11 A6: hanya SHA-256 yang disimpan; token polos tampil sekali di sini. */
    if (!q_exec('UPDATE pendaftar SET pay_token_hash = ? WHERE id = ?', [hash('sha256', $tokenBaru), $baris['id']])) {
        return ['ok' => false, 'pesan' => 'Gagal memulihkan tautan. Coba lagi nanti.'];
    }

    return [
        'ok'     => true,
        'pesan'  => 'Tautan pembayaran baru berhasil dibuat. Tautan lama otomatis tidak berlaku.',
        'tautan' => 'app/spmb/bayar.php?t=' . $tokenBaru,
    ];
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
        'SELECT jenis_pembayaran, nominal, status FROM pembayaran WHERE pendaftar_id = ? ORDER BY created_at',
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
