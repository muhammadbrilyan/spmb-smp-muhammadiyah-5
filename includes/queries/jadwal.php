<?php
/**
 * Modul queries: jadwal "” di-include dari includes/queries.php.
 */


function daftar_jadwal_tes()
{
    global $JADWAL_TES;

    $baris = q_all(
        'SELECT kode, nama_kegiatan, tanggal, jam_mulai, jam_selesai, mode, peserta_dijadwalkan, status
           FROM jadwal_tes
       ORDER BY tanggal, jam_mulai'
    );
    if ($baris === null) {
        return $JADWAL_TES;
    }

    $labelMode = ['OFFLINE' => 'Offline Sekolah', 'DARING' => 'Daring', 'VIDEO_CALL' => 'Video Call'];
    $toneStat  = ['DRAFT' => 'amber', 'TERJADWAL' => 'blue', 'SELESAI' => 'green'];
    $labelStat = ['DRAFT' => 'Draft', 'TERJADWAL' => 'Terjadwal', 'SELESAI' => 'Selesai'];

    $keluar = [];
    foreach ($baris as $r) {
        $keluar[] = [
            'kode'    => $r['kode'],
            'nama'    => $r['nama_kegiatan'],
            'tgl'     => $r['tanggal'],
            'jam'     => substr($r['jam_mulai'], 0, 5) . ' "“ ' . substr($r['jam_selesai'], 0, 5),
            'mode'    => $labelMode[$r['mode']] ?? $r['mode'],
            'peserta' => (int) $r['peserta_dijadwalkan'],
            'status'  => $labelStat[$r['status']] ?? $r['status'],
            /* dipakai badge($j['status'], $j['tone']) "” sama seperti data dummy */
            'tone'    => $toneStat[$r['status']] ?? 'gray',
        ];
    }

    return $keluar; /* kosong = memang belum ada jadwal (bukan fallback dummy) */
}

/** Jadwal lengkap (untuk halaman kelola & dropdown seleksi). */

function daftar_jadwal_tes_admin()
{
    $baris = q_all(
        'SELECT id, kode, nama_kegiatan, tanggal, jam_mulai, jam_selesai, mode, peserta_dijadwalkan, status, ruangan, kapasitas
           FROM jadwal_tes
       ORDER BY tanggal, jam_mulai'
    );
    return $baris ?: [];
}


function jadwal_ubah($id, $nama, $tgl, $jamMulai, $jamSelesai, $mode, $peserta, $status, $ruangan = null, $kapasitas = 0)
{
    return q_exec(
        'UPDATE jadwal_tes SET nama_kegiatan = ?, tanggal = ?, jam_mulai = ?, jam_selesai = ?,
                mode = ?, peserta_dijadwalkan = ?, status = ?, ruangan = ?, kapasitas = ?
          WHERE id = ?',
        [$nama, $tgl, $jamMulai, $jamSelesai, $mode, (int) $peserta, $status,
         $ruangan !== '' ? $ruangan : null, (int) $kapasitas, $id]
    );
}


function jadwal_hapus($id)
{
    $dipakai = (int) (q_col('SELECT COUNT(*) FROM seleksi WHERE jadwal_id = ?', [$id]) ?? 0);
    if ($dipakai > 0) {
        return false;
    }
    return q_exec('DELETE FROM jadwal_tes WHERE id = ?', [$id]);
}

/* ==================================================================
 * 4. DASHBOARD OPERATOR "” tab 3: hasil seleksi
 * ================================================================== */


function jadwal_tambah($nama, $tgl, $jamMulai, $jamSelesai, $mode, $peserta, $status, $ruangan = null, $kapasitas = 0)
{
    $id  = 'tes-' . bin2hex(random_bytes(4));
    $n   = (int) (q_col('SELECT COUNT(*) FROM jadwal_tes') ?? 0) + 1;
    $kode = 'TES-' . str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    $ok = q_exec(
        'INSERT INTO jadwal_tes (id, kode, nama_kegiatan, tanggal, jam_mulai, jam_selesai, mode, peserta_dijadwalkan, status, ruangan, kapasitas)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$id, $kode, $nama, $tgl, $jamMulai, $jamSelesai, $mode, (int) $peserta, $status,
         $ruangan !== '' ? $ruangan : null, (int) $kapasitas]
    );
    return $ok ? $id : null;
}

/** Simpan/ubah nilai seleksi satu pendaftar (upsert) + sinkron status_seleksi. */

function jadwal_kirim_notifikasi($jadwal_id)
{
    $j = q_all(
        'SELECT kode, nama_kegiatan, tanggal, jam_mulai, jam_selesai, mode, lokasi, peserta_dijadwalkan
           FROM jadwal_tes WHERE id = ? LIMIT 1',
        [$jadwal_id]
    );
    if (!$j) {
        return ['ok' => 0, 'gagal' => 0, 'detail' => ['Jadwal tidak ditemukan.']];
    }
    $j = $j[0];

    $siswa = q_all(
        "SELECT p.nama_siswa, o.no_whatsapp, o.nama_orang_tua
           FROM seleksi s
           JOIN pendaftar p ON p.id = s.pendaftar_id
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE s.jadwal_id = ? AND o.no_whatsapp IS NOT NULL
       ORDER BY p.no_pendaftaran",
        [$jadwal_id]
    ) ?: [];
    if (!$siswa) {
        return ['ok' => 0, 'gagal' => 0, 'detail' => ['Tidak ada siswa terjadwal dengan nomor WA orang tua.']];
    }

    $lokasi = $j['lokasi'] ?: ($j['mode'] === 'VIDEO_CALL' ? 'Video call (tautan menyusul)' : 'Sekolah');
    $metode = ['OFFLINE' => 'Offline di sekolah', 'DARING' => 'Daring', 'VIDEO_CALL' => 'Video call'][$j['mode'] ?? 'OFFLINE'] ?? $j['mode'];
    $tglIndo = date('d/m/Y', strtotime($j['tanggal']));

    $ok = 0; $gagal = 0; $detail = [];
    foreach ($siswa as $s) {
        $pesan = "Assalamu'alaikum.\n\n"
            . "Pengumuman jadwal tes SPMB " . ($GLOBALS['PENGATURAN']['nama_sekolah'] ?? '') . ":\n\n"
            . "Siswa : {$s['nama_siswa']}\n"
            . "Kegiatan : {$j['kode']} - {$j['nama_kegiatan']}\n"
            . "Tanggal : {$tglIndo}\n"
            . "Jam : " . substr($j['jam_mulai'], 0, 5) . " - " . substr($j['jam_selesai'], 0, 5) . " WIB\n"
            . "Metode : {$metode}\n"
            . "Lokasi : {$lokasi}\n\n"
            . "Mohon hadir 15 menit lebih awal dan membawa alat tulis serta kartu pendaftaran.\n\n"
            . "Salam,\nPanitia SPMB";
        $r = wa_kirim($s['no_whatsapp'], $pesan);
        if ($r['ok']) {
            $ok++;
        } else {
            $gagal++;
            $detail[] = "{$s['nama_siswa']}: {$r['pesan']}";
        }
    }
    return ['ok' => $ok, 'gagal' => $gagal, 'detail' => $detail];
}
