<?php
/**
 * LAPISAN QUERY — Retensi data & privasi [Fase 12 E].
 *
 * Retensi HANYA menghapus berkas pribadi fisik di storage/dokumen
 * (KK, akta, bukti transfer) beserta jejaknya di tabel dokumen.
 * Baris pendaftar TIDAK dihapus — arsip ringkas tetap ada.
 * Setiap eksekusi dicatat ke retensi_log.
 */

/** Pengaturan retensi dari pengaturan_sekolah (null = belum ditetapkan). */
function retensi_pengaturan()
{
    $P = pengaturan();
    $amb = function ($k) use ($P) {
        $v = $P[$k] ?? null;
        if ($v === null || $v === '' || !is_numeric($v)) {
            return null;
        }
        $n = (int) $v;
        return ($n >= 1 && $n <= 240) ? $n : null;
    };
    return [
        'aktif'             => (int) ($P['retensi_aktif'] ?? 0) === 1,
        'bulan_tidak_lulus' => $amb('retensi_bulan_tidak_lulus'),
        'bulan_batal'       => $amb('retensi_bulan_batal'),
        'bulan_lulus_arsip' => $amb('retensi_bulan_lulus_arsip'),
    ];
}

/**
 * Kandidat retensi berkas pribadi per kategori.
 * $kategori: 'tidak_lulus' | 'batal' | 'lulus_arsip'.
 */
function retensi_kandidat($kategori, $bulan)
{
    if ($bulan === null || $bulan < 1) {
        return [];
    }
    $where = [
        'tidak_lulus' => "p.status_seleksi = 'TIDAK_LULUS'",
        /* Batal: ditolak verifikasi ATAU terhenti di tahap awal pendaftaran
         * (belum pernah melaju — pendaftar menggantung, berkasnya tetap
         * pribadi). status_seleksi tidak punya nilai DIBATALKAN. */
        'batal'       => "(p.status_verifikasi = 'DITOLAK'
                          OR p.tahap IN ('MENUNGGU_PEMBAYARAN','AKUN_TERKIRIM','MELENGKAPI_BERKAS'))",
        'lulus_arsip' => "p.status_seleksi = 'LULUS'",
    ][$kategori] ?? null;
    if ($where === null) {
        return [];
    }
    return q_all(
        "SELECT p.id, p.no_pendaftaran, p.nama_siswa, p.created_at,
                ((SELECT COUNT(*) FROM dokumen d
                   WHERE d.pendaftar_id = p.id
                     AND d.file_url IS NOT NULL AND d.file_url <> '')
                + (SELECT COUNT(*) FROM pembayaran byr
                   WHERE byr.pendaftar_id = p.id
                     AND byr.bukti_transfer_url IS NOT NULL AND byr.bukti_transfer_url <> '')
                ) AS jumlah_berkas
           FROM pendaftar p
          WHERE $where
            AND p.created_at <= (NOW() - INTERVAL ? MONTH)
            AND (
                  EXISTS (SELECT 1 FROM dokumen d2
                           WHERE d2.pendaftar_id = p.id
                             AND d2.file_url IS NOT NULL AND d2.file_url <> '')
               OR EXISTS (SELECT 1 FROM pembayaran byr2
                           WHERE byr2.pendaftar_id = p.id
                             AND byr2.bukti_transfer_url IS NOT NULL AND byr2.bukti_transfer_url <> '')
            )
          GROUP BY p.id, p.no_pendaftaran, p.nama_siswa, p.created_at
          ORDER BY p.created_at
          LIMIT 200",
        [(int) $bulan]
    ) ?: [];
}

/**
 * Hapus berkas pribadi fisik milik satu pendaftar + catat retensi_log.
 * Kode defensif: hanya path ber-prefix 'storage/dokumen/' dgn nama file
 * berpola (dok-/bukti-...jpg|png|pdf) yang disentuh.
 *
 * @return array{ok:bool,pesan?:string,jumlah?:int,gagal?:int}
 */
function retensi_hapus_berkas($pendaftarId, $alasan, $operatorId = null)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak tersedia.'];
    }
    $baris = q_all(
        'SELECT no_pendaftaran FROM pendaftar WHERE id = ?',
        [$pendaftarId]
    );
    if (!$baris) {
        return ['ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.'];
    }
    $no = (string) $baris[0]['no_pendaftaran'];

    $dok = q_all(
        "SELECT id, file_url FROM dokumen
          WHERE pendaftar_id = ? AND file_url IS NOT NULL AND file_url <> ''",
        [$pendaftarId]
    ) ?: [];

    $prefix  = 'storage/dokumen/';
    $dokuDir = storage_path('dokumen');
    $pola    = '/^(dok|bukti)-[A-Za-z0-9._-]+\.(jpg|jpeg|png|pdf)$/i';
    $hapus   = 0;
    $gagal   = 0;

    foreach ($dok as $d) {
        $rel = (string) $d['file_url'];
        if (strpos($rel, $prefix) !== 0) {
            $gagal++;   /* jalur di luar prefix -> jangan disentuh */
            continue;
        }
        $nama = basename($rel);
        if (!preg_match($pola, $nama)) {
            $gagal++;
            continue;
        }
        $abs = $dokuDir . '/' . $nama;
        if (is_file($abs) && !@unlink($abs)) {
            $gagal++;
            continue;
        }
        q_exec(
            'UPDATE dokumen SET file_url = NULL, ukuran_kb = NULL WHERE id = ?',
            [$d['id']]
        );
        $hapus++;
    }

    /* Fase 13 butir 2 — bukti transfer di tabel pembayaran ikut diretensi. */
    $bay = q_all(
        "SELECT id, bukti_transfer_url FROM pembayaran
          WHERE pendaftar_id = ? AND bukti_transfer_url IS NOT NULL AND bukti_transfer_url <> ''",
        [$pendaftarId]
    ) ?: [];
    foreach ($bay as $b) {
        $rel = (string) $b['bukti_transfer_url'];
        if (strpos($rel, $prefix) !== 0) {
            $gagal++;
            continue;
        }
        $nama = basename($rel);
        if (!preg_match($pola, $nama)) {
            $gagal++;
            continue;
        }
        $abs = $dokuDir . '/' . $nama;
        if (is_file($abs) && !@unlink($abs)) {
            $gagal++;
            continue;
        }
        q_exec('UPDATE pembayaran SET bukti_transfer_url = NULL WHERE id = ?', [$b['id']]);
        $hapus++;
    }

    $jejakOk = true;
    try {
        $stmt = $db->prepare(
            'INSERT INTO retensi_log (pendaftar_id, no_pendaftaran, alasan, jumlah_berkas, operator_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$pendaftarId, $no, substr((string) $alasan, 0, 40), $hapus, $operatorId]);
    } catch (Throwable $e) {
        /* Jangan senyap: jejak retensi wajib tercatat; laporkan ke pemanggil. */
        $jejakOk = false;
        error_log('retensi_log gagal dicatat: ' . $e->getMessage());
    }

    return ['ok' => true, 'jumlah' => $hapus, 'gagal' => $gagal, 'jejak' => $jejakOk];
}

/** Riwayat eksekusi retensi terbaru. */
function retensi_riwayat($limit = 20)
{
    return q_all(
        'SELECT l.*, u.nama_lengkap AS operator_nama
           FROM retensi_log l
           LEFT JOIN users u ON u.id = l.operator_id
          ORDER BY l.dibuat_pada DESC
          LIMIT ' . (int) $limit
    ) ?: [];
}

/** Jumlah pendaftar berkasnya sudah bersih (file_url NULL semua). */
function retensi_sudah_bersih()
{
    return (int) (q_col(
        'SELECT COUNT(*) FROM pendaftar p
          WHERE NOT EXISTS (
            SELECT 1 FROM dokumen d
             WHERE d.pendaftar_id = p.id
               AND d.file_url IS NOT NULL AND d.file_url <> \'\''
        . ')'
    ) ?? 0);
}
