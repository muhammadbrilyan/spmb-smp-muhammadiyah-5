<?php
/**
 * Modul queries: export — di-include dari includes/queries.php.
 */

function export_rekap($jenis)
{
    if ($jenis === 'pendaftar') {
        $baris = q_all(
            "SELECT p.no_pendaftaran, p.nama_siswa, p.nisn, p.jenis_kelamin, p.jalur,
                    p.asal_sekolah, p.alamat, p.status_verifikasi, p.status_seleksi, p.status_akun,
                    o.nama_orang_tua, o.no_whatsapp, k.kode AS kelas,
                    DATE_FORMAT(p.created_at, '%d/%m/%Y %H:%i') AS terdaftar
               FROM pendaftar p
          LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
          LEFT JOIN penempatan_kelas pk ON pk.pendaftar_id = p.id
          LEFT JOIN kelas k ON k.id = pk.kelas_id
              WHERE p.arsip_at IS NULL
           ORDER BY p.no_pendaftaran"
        ) ?: [];
        $header = ['No. Pendaftaran', 'Nama', 'NISN', 'JK', 'Jalur', 'Asal Sekolah', 'Alamat',
                    'Verifikasi', 'Seleksi', 'Akun', 'Orang Tua', 'WA', 'Kelas', 'Terdaftar'];
    } elseif ($jenis === 'seleksi') {
        $baris = q_all(
            "SELECT p.no_pendaftaran, p.nama_siswa, p.jalur,
                    s.nilai_cbt, s.nilai_btq, s.nilai_wawancara, s.nilai_akhir, s.hasil,
                    j.kode AS jadwal, k.kode AS kelas
               FROM seleksi s
               JOIN pendaftar p ON p.id = s.pendaftar_id
          LEFT JOIN jadwal_tes j ON j.id = s.jadwal_id
          LEFT JOIN penempatan_kelas pk ON pk.pendaftar_id = p.id
          LEFT JOIN kelas k ON k.id = pk.kelas_id
              WHERE p.arsip_at IS NULL
           ORDER BY s.nilai_akhir DESC"
        ) ?: [];
        $header = ['No. Pendaftaran', 'Nama', 'Jalur', 'CBT', 'BTQ', 'Wawancara', 'Akhir', 'Hasil', 'Jadwal', 'Kelas'];
    } elseif ($jenis === 'pembayaran') {
        $baris = q_all(
            "SELECT p.no_pendaftaran, p.nama_siswa, b.jenis_pembayaran, b.angsuran_ke, b.nominal, b.status,
                    b.keterangan, DATE_FORMAT(b.created_at, '%d/%m/%Y') AS tanggal,
                    DATE_FORMAT(b.verified_at, '%d/%m/%Y %H:%i') AS diverifikasi
               FROM pembayaran b
               JOIN pendaftar p ON p.id = b.pendaftar_id
              WHERE p.arsip_at IS NULL
           ORDER BY p.no_pendaftaran, b.created_at"
        ) ?: [];
        $header = ['No. Pendaftaran', 'Nama', 'Jenis', 'Nominal', 'Status', 'Keterangan', 'Tanggal', 'Diverifikasi'];

        /* Samakan label dengan tampilan dashboard: jenis "UANG_MASUK" memakai
         * nama biaya dari pengaturan + keterangan angsuran, status diterjemahkan. */
        $labelJenis = ['FORMULIR' => 'Biaya Formulir', 'UANG_MASUK' => label_biaya_pendaftaran()];
        $labelStat  = [
            'PENDING'    => 'Menunggu',
            'VERIFIED'   => 'Terverifikasi',
            'REJECTED'   => 'Ditolak',
            'LUNAS'      => 'Lunas',
            'ANGSURAN_1' => 'Angsuran 1',
            'ANGSURAN_2' => 'Angsuran 2',
        ];
        foreach ($baris as &$b) {
            $jenisBayar = $labelJenis[$b['jenis_pembayaran']] ?? $b['jenis_pembayaran'];
            if ($b['jenis_pembayaran'] === 'UANG_MASUK' && !empty($b['angsuran_ke'])) {
                $jenisBayar .= ' · Angsuran ' . (int) $b['angsuran_ke'];
            }
            $b['jenis_pembayaran'] = $jenisBayar;
            $b['status'] = $labelStat[$b['status']] ?? $b['status'];
            unset($b['angsuran_ke']);
        }
        unset($b);
    } else {
        return ['header' => [], 'baris' => []];
    }
    return ['header' => $header, 'baris' => $baris];
}

/* ==================================================================
 * 16. PENDAFTARAN MANUAL OPERATOR
 * ================================================================== */

/**
 * Tambah pendaftar manual (input lengkap operator) — tanpa batas duplikat.
 * @return array{ok:bool, no?:string, id?:string, pesan?:string}
 */
