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
        'SELECT kode, nama_kegiatan, tanggal, jam_mulai, jam_selesai, mode, lokasi, ruangan, peserta_dijadwalkan
           FROM jadwal_tes WHERE id = ? LIMIT 1',
        [$jadwal_id]
    );
    if (!$j) {
        return ['ok' => 0, 'gagal' => 0, 'detail' => ['Jadwal tidak ditemukan.']];
    }
    $j = $j[0];

    /* Peserta tercatat di dua tempat: peserta_ujian (halaman Peserta Ujian) dan
     * seleksi.jadwal_id (Input Nilai). Pakai keduanya supaya tidak ada siswa
     * yang terlewat hanya karena dijadwalkan lewat jalur lain. */
    $siswa = q_all(
        "SELECT p.nama_siswa, o.no_whatsapp, o.nama_orang_tua
           FROM pendaftar p
           JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.arsip_at IS NULL
            AND o.no_whatsapp IS NOT NULL AND o.no_whatsapp <> ''
            AND (
                  EXISTS (SELECT 1 FROM peserta_ujian pu
                           WHERE pu.pendaftar_id = p.id AND pu.jadwal_id = ?)
               OR EXISTS (SELECT 1 FROM seleksi s
                           WHERE s.pendaftar_id = p.id AND s.jadwal_id = ?)
                )
       ORDER BY p.no_pendaftaran",
        [$jadwal_id, $jadwal_id]
    ) ?: [];
    if (!$siswa) {
        return ['ok' => 0, 'gagal' => 0, 'detail' => ['Tidak ada siswa terjadwal dengan nomor WA orang tua.']];
    }

    /* Operator mengisi "Ruangan" di halaman Kelola Jadwal; kolom "lokasi" hanya
     * dipakai data lama, jadi jadikan ruangan yang tampil lebih dulu. */
    $lokasi = jadwal_lokasi_teks($j);
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

/* ==================================================================
 * PENGUMUMAN JADWAL GABUNGAN
 * Satu kali klik -> tiap pendaftar yang sudah dijadwalkan menerima SATU
 * pesan WA berisi SELURUH jadwal berstatus Terjadwal, plus SATU
 * notifikasi in-app. Bukan satu pesan per kegiatan.
 * ================================================================== */

/** Semua jadwal yang siap diumumkan (status Terjadwal), terdekat lebih dulu. */
function jadwal_terjadwal()
{
    $baris = q_all(
        "SELECT id, kode, nama_kegiatan, tanggal, jam_mulai, jam_selesai, mode, lokasi, ruangan
           FROM jadwal_tes
          WHERE status = 'TERJADWAL'
       ORDER BY tanggal, jam_mulai"
    );
    return $baris ?: [];
}

/**
 * Penanda kumpulan jadwal saat ini (jadi ref_id notifikasi).
 * Berubah begitu ada jadwal baru/diubah/ditarik, sehingga pengumuman
 * berikutnya dianggap hal baru — sementara klik ganda pada susunan yang
 * sama tidak mengirim ulang.
 */
function jadwal_pengumuman_ref(array $jadwal)
{
    $bagian = [];
    foreach ($jadwal as $j) {
        $bagian[] = $j['id'] . ':' . $j['tanggal'] . ':' . substr($j['jam_mulai'], 0, 5);
    }
    return 'jdw-' . substr(md5(implode('|', $bagian)), 0, 28);
}

/**
 * Tempat kegiatan: "ruangan" yang diisi operator lebih dulu, lalu kolom lama
 * "lokasi" — urutan yang sama dengan kartu peserta di portal siswa.
 */
function jadwal_lokasi_teks($jadwal)
{
    $lokasi = trim((string) ($jadwal['ruangan'] ?? '')) ?: trim((string) ($jadwal['lokasi'] ?? ''));
    if ($lokasi === '') {
        $lokasi = $jadwal['mode'] === 'VIDEO_CALL' ? 'Tautan video call (menyusul)' : 'Sekolah';
    }
    return $lokasi;
}

/**
 * Susun satu pesan berisi seluruh jadwal. Dipakai untuk WA dan salinan
 * notifikasi in-app supaya isinya tidak pernah dua versi.
 */
function jadwal_pesan_pengumuman($nama_siswa, $no_pendaftaran, array $jadwal)
{
    $sekolah = trim((string) ($GLOBALS['PENGATURAN']['nama_sekolah'] ?? ''));
    $metode  = ['OFFLINE' => 'Offline di sekolah', 'DARING' => 'Daring', 'VIDEO_CALL' => 'Video call'];

    $teks = "Assalamu'alaikum.\n\n";
    $teks .= 'Jadwal tes seleksi SPMB' . ($sekolah !== '' ? ' ' . $sekolah : '') . ":\n\n";
    $teks .= 'Ananda: ' . trim((string) $nama_siswa) . ' (' . trim((string) $no_pendaftaran) . ")\n\n";

    $n = 0;
    foreach ($jadwal as $j) {
        $n++;
        $teks .= $n . '. ' . $j['nama_kegiatan'] . ' (' . $j['kode'] . ")\n";
        $teks .= '   ' . bulan($j['tanggal']) . ' · ' . substr($j['jam_mulai'], 0, 5)
            . '-' . substr($j['jam_selesai'], 0, 5) . " WIB\n";
        $teks .= '   ' . ($metode[$j['mode']] ?? $j['mode']) . ' · ' . jadwal_lokasi_teks($j) . "\n\n";
    }

    if ($n > 1) {
        $teks .= 'Total ' . $n . " jadwal yang harus diikuti.\n\n";
    }
    $teks .= "Mohon hadir 15 menit lebih awal dan membawa kartu peserta (cetak di portal siswa), "
        . "kartu pendaftaran, serta alat tulis.\n\nSalam,\nPanitia SPMB";

    return $teks;
}

/**
 * Pendaftar yang sudah dijadwalkan pada minimal satu kegiatan Terjadwal
 * dan punya nomor WA orang tua.
 *
 * Sumber penjadwalan ada dua: peserta_ujian (diisi lewat Halaman Peserta
 * Ujian) dan seleksi.jadwal_id (diisi lewat Input Nilai) — keduanya dipakai
 * agar tidak ada siswa yang terlewat.
 *
 * @return array<int,array{pendaftar_id:string,nama_siswa:string,no_pendaftaran:string,no_whatsapp:string}>
 */
function jadwal_penerima_pengumuman()
{
    $baris = q_all(
        "SELECT p.id AS pendaftar_id, p.nama_siswa, p.no_pendaftaran, o.no_whatsapp
           FROM pendaftar p
           JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.arsip_at IS NULL
            AND o.no_whatsapp IS NOT NULL
            AND o.no_whatsapp <> ''
            AND (
                  EXISTS (SELECT 1 FROM peserta_ujian pu
                             JOIN jadwal_tes jt ON jt.id = pu.jadwal_id
                            WHERE pu.pendaftar_id = p.id AND jt.status = 'TERJADWAL')
               OR EXISTS (SELECT 1 FROM seleksi s
                             JOIN jadwal_tes jt2 ON jt2.id = s.jadwal_id
                            WHERE s.pendaftar_id = p.id AND jt2.status = 'TERJADWAL')
                )
       ORDER BY p.no_pendaftaran"
    );
    return $baris ?: [];
}

/**
 * Siarkan SATU pengumuman berisi semua jadwal Terjadwal.
 *
 * Pencegah ganda: ref_id = sidik jari susunan jadwal, jadi klik ganda pada
 * susunan yang sama tidak mengirim ulang. Pendaftar yang WA-nya tercatat
 * GAGAL dicoba lagi tanpa menduplikasi baris notifikasinya.
 *
 * @return array{ok:int,gagal:int,lewati:int,in_app:int,penerima:int,jadwal:int,detail:array<int,string>}
 */
function jadwal_umumkan_semua()
{
    $hasil = ['ok' => 0, 'gagal' => 0, 'lewati' => 0, 'in_app' => 0, 'penerima' => 0, 'jadwal' => 0, 'detail' => []];

    $jadwal = jadwal_terjadwal();
    $hasil['jadwal'] = count($jadwal);
    if (!$jadwal) {
        $hasil['detail'][] = 'Belum ada jadwal berstatus Terjadwal.';
        return $hasil;
    }

    $penerima = jadwal_penerima_pengumuman();
    $hasil['penerima'] = count($penerima);
    if (!$penerima) {
        $hasil['detail'][] = 'Belum ada pendaftar terjadwal dengan nomor WA orang tua.';
        return $hasil;
    }

    $ref    = jadwal_pengumuman_ref($jadwal);
    $judul  = 'Jadwal Tes SPMB (' . count($jadwal) . ' kegiatan)';
    $tautan = url('app/siswa/ppdb.php?tab=jadwal');

    foreach ($penerima as $p) {
        $pesan = jadwal_pesan_pengumuman($p['nama_siswa'], $p['no_pendaftaran'], $jadwal);
        $lama  = notif_siswa_cari_ref($p['pendaftar_id'], $ref);

        if (!$lama) {
            if (!notif_siswa_kirim($p['pendaftar_id'], 'JADWAL_UJIAN', $judul, $pesan, $tautan, $ref)) {
                $hasil['detail'][] = $p['nama_siswa'] . ': notifikasi gagal dibuat.';
                continue;
            }
            $hasil['in_app']++;
            $lama = notif_siswa_cari_ref($p['pendaftar_id'], $ref);
        }

        if (($lama['wa_status'] ?? null) === 'TERKIRIM') {
            $hasil['lewati']++;
            continue;
        }

        $kirim = wa_kirim($p['no_whatsapp'], $pesan);
        wa_log_catat($p['no_whatsapp'], 'JADWAL_UJIAN', $kirim);
        notif_siswa_status_wa($p['pendaftar_id'], $ref, !empty($kirim['ok']) ? 'TERKIRIM' : 'GAGAL');
        if (!empty($kirim['ok'])) {
            $hasil['ok']++;
        } else {
            $hasil['gagal']++;
            $hasil['detail'][] = $p['nama_siswa'] . ': ' . ($kirim['pesan'] ?? 'pengiriman gagal.');
        }
    }

    return $hasil;
}
