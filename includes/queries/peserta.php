<?php
/**
 * Modul queries: peserta ujian & atur otomatis (Fase 6).
 * Di-include dari includes/queries.php.
 */

/** Daftar peserta satu jadwal (untuk tabel operator). */
function peserta_daftar($jadwal_id)
{
    return q_all(
        'SELECT pu.id, pu.nomor_peserta, pu.ruangan, pu.created_at,
                p.id AS pendaftar_id, p.no_pendaftaran, p.nama_siswa, p.asal_sekolah,
                o.no_whatsapp
           FROM peserta_ujian pu
           JOIN pendaftar p ON p.id = pu.pendaftar_id
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE pu.jadwal_id = ?
       ORDER BY pu.nomor_peserta',
        [(string) $jadwal_id]
    ) ?: [];
}

/** Total peserta terjadwal pada satu jadwal. */
function peserta_hitung($jadwal_id)
{
    return (int) (q_col('SELECT COUNT(*) FROM peserta_ujian WHERE jadwal_id = ?', [(string) $jadwal_id]) ?? 0);
}

/**
 * Nomor peserta berikutnya yang bebas pada suatu jadwal
 * (berbasis suffix numerik terbesar — aman setelah penghapusan).
 */
function peserta_nomor_berikutnya($db, $jadwal_id, $kode)
{
    $max = $db->query(
        'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(nomor_peserta, "-", -1) AS UNSIGNED)), 0)
           FROM peserta_ujian WHERE jadwal_id = ' . $db->quote((string) $jadwal_id)
    )->fetchColumn();
    return $kode . '-' . str_pad((string) ((int) $max + 1), 3, '0', STR_PAD_LEFT);
}

/**
 * Atur peserta otomatis: seluruh pendaftar BERKAS_TERVERIFIKASI yang belum
 * punya jadwal, diurutkan no_pendaftaran, diisikan ke jadwal hingga
 * memenuhi kapasitas. Transaksi DB — aman dari pemesongan.
 *
 * @return array{ok:bool,jumlah:int,sisa:int,kode:string,pesan:string}
 */
function peserta_atur_otomatis($jadwal_id)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'jumlah' => 0, 'sisa' => 0, 'kode' => '', 'pesan' => 'Koneksi database gagal.'];
    }
    $jadwal_id = (string) $jadwal_id;
    try {
        $db->beginTransaction();

        $j = $db->prepare('SELECT id, kode, kapasitas, ruangan FROM jadwal_tes WHERE id = ? FOR UPDATE');
        $j->execute([$jadwal_id]);
        $jadwal = $j->fetch();
        if (!$jadwal) {
            $db->rollBack();
            return ['ok' => false, 'jumlah' => 0, 'sisa' => 0, 'kode' => '', 'pesan' => 'Jadwal tidak ditemukan.'];
        }

        $kapasitas = (int) $jadwal['kapasitas'];
        $terpakai  = (int) $db->query('SELECT COUNT(*) FROM peserta_ujian WHERE jadwal_id = ' . $db->quote($jadwal_id))->fetchColumn();
        $sisaKapas = $kapasitas > 0 ? max(0, $kapasitas - $terpakai) : PHP_INT_MAX;
        if ($sisaKapas === 0) {
            $db->rollBack();
            return ['ok' => false, 'jumlah' => 0, 'sisa' => 0, 'kode' => $jadwal['kode'], 'pesan' => 'Kapasitas jadwal sudah penuh (' . $kapasitas . ' peserta).'];
        }

        /* Kandidat: BERKAS_TERVERIFIKASI tanpa peserta_ujian. */
        $k = $db->query(
            "SELECT p.id, p.no_pendaftaran
               FROM pendaftar p
              WHERE p.tahap = 'BERKAS_TERVERIFIKASI'
                AND NOT EXISTS (SELECT 1 FROM peserta_ujian pu WHERE pu.pendaftar_id = p.id)
           ORDER BY p.no_pendaftaran
              LIMIT " . ($sisaKapas === PHP_INT_MAX ? '100000' : (int) $sisaKapas)
        )->fetchAll();
        if (!$k) {
            $db->rollBack();
            return ['ok' => true, 'jumlah' => 0, 'sisa' => 0, 'kode' => $jadwal['kode'],
                'pesan' => 'Tidak ada pendaftar siap jadwal (butuh tahap BERKAS_TERVERIFIKASI & belum terjadwal).'];
        }

        $ruangan = $jadwal['ruangan'] !== null && $jadwal['ruangan'] !== '' ? $jadwal['ruangan'] : null;
        $ins     = $db->prepare('INSERT INTO peserta_ujian (id, pendaftar_id, jadwal_id, nomor_peserta, ruangan) VALUES (?,?,?,?,?)');
        $upd     = $db->prepare('UPDATE pendaftar SET tahap = "JADWAL_DITETAPKAN" WHERE id = ?');
        $n = (int) $db->query(
            'SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(nomor_peserta, "-", -1) AS UNSIGNED)), 0)
               FROM peserta_ujian WHERE jadwal_id = ' . $db->quote($jadwal_id)
        )->fetchColumn();
        foreach ($k as $baris) {
            $n++;
            $nomor = $jadwal['kode'] . '-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $ins->execute(['prj-' . bin2hex(random_bytes(5)), $baris['id'], $jadwal_id, $nomor, $ruangan]);
            $upd->execute([$baris['id']]);
        }

        /* Sinkron jumlah & status jadwal. */
        $db->prepare('UPDATE jadwal_tes SET peserta_dijadwalkan = (SELECT COUNT(*) FROM peserta_ujian WHERE jadwal_id = ?), status = "TERJADWAL" WHERE id = ?')
            ->execute([$jadwal_id, $jadwal_id]);

        $db->commit();
        return ['ok' => true, 'jumlah' => count($k), 'sisa' => $n, 'kode' => $jadwal['kode'],
            'pesan' => count($k) . ' peserta dijadwalkan otomatis pada ' . $jadwal['kode'] . ' (total ' . $n . ').'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'jumlah' => 0, 'sisa' => 0, 'kode' => '', 'pesan' => 'Gagal mengatur peserta: ' . $e->getMessage()];
    }
}

/**
 * Tambah peserta manual (hanya pendaftar BERKAS_TERVERIFIKASI & belum terjadwal).
 */
function peserta_tambah_manual($jadwal_id, $pendaftar_id)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Koneksi database gagal.'];
    }
    try {
        $db->beginTransaction();
        $j = $db->prepare('SELECT id, kode, kapasitas, ruangan FROM jadwal_tes WHERE id = ? FOR UPDATE');
        $j->execute([(string) $jadwal_id]);
        $jadwal = $j->fetch();
        if (!$jadwal) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Jadwal tidak ditemukan.'];
        }
        $p = $db->prepare('SELECT id, tahap, no_pendaftaran FROM pendaftar WHERE id = ? FOR UPDATE');
        $p->execute([(string) $pendaftar_id]);
        $pendaftar = $p->fetch();
        if (!$pendaftar) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.'];
        }
        if ($pendaftar['tahap'] !== 'BERKAS_TERVERIFIKASI') {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Hanya pendaftar BERKAS_TERVERIFIKASI yang dapat dijadwalkan.'];
        }
        $sudah = $db->prepare('SELECT id FROM peserta_ujian WHERE pendaftar_id = ?');
        $sudah->execute([(string) $pendaftar_id]);
        if ($sudah->fetch()) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Pendaftar sudah terjadwal pada jadwal lain.'];
        }
        $kapasitas = (int) $jadwal['kapasitas'];
        $terpakai  = (int) $db->query('SELECT COUNT(*) FROM peserta_ujian WHERE jadwal_id = ' . $db->quote($jadwal_id))->fetchColumn();
        if ($kapasitas > 0 && $terpakai >= $kapasitas) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Kapasitas jadwal sudah penuh.'];
        }
        $nomor   = peserta_nomor_berikutnya($db, $jadwal_id, $jadwal['kode']);
        $ruangan = $jadwal['ruangan'] !== null && $jadwal['ruangan'] !== '' ? $jadwal['ruangan'] : null;
        $db->prepare('INSERT INTO peserta_ujian (id, pendaftar_id, jadwal_id, nomor_peserta, ruangan) VALUES (?,?,?,?,?)')
            ->execute(['prj-' . bin2hex(random_bytes(5)), $pendaftar_id, $jadwal_id, $nomor, $ruangan]);
        $db->prepare('UPDATE pendaftar SET tahap = "JADWAL_DITETAPKAN" WHERE id = ?')->execute([$pendaftar_id]);
        $db->prepare('UPDATE jadwal_tes SET peserta_dijadwalkan = (SELECT COUNT(*) FROM peserta_ujian WHERE jadwal_id = ?), status = "TERJADWAL" WHERE id = ?')
            ->execute([$jadwal_id, $jadwal_id]);
        $db->commit();
        return ['ok' => true, 'pesan' => 'Peserta ' . $pendaftar['no_pendaftaran'] . ' ditambahkan dengan nomor ' . $nomor . '.'];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return ['ok' => false, 'pesan' => 'Gagal menambah peserta: ' . $e->getMessage()];
    }
}

/** Hapus peserta — kembalikan pendaftar ke BERKAS_TERVERIFIKASI. */
function peserta_hapus($peserta_id)
{
    $db = db();
    if ($db === null) {
        return false;
    }
    try {
        $db->beginTransaction();
        $s = $db->prepare('SELECT id, pendaftar_id, jadwal_id FROM peserta_ujian WHERE id = ? FOR UPDATE');
        $s->execute([(string) $peserta_id]);
        $row = $s->fetch();
        if (!$row) {
            $db->rollBack();
            return false;
        }
        $db->prepare('DELETE FROM peserta_ujian WHERE id = ?')->execute([$peserta_id]);
        $db->prepare('UPDATE pendaftar SET tahap = "BERKAS_TERVERIFIKASI" WHERE id = ? AND tahap = "JADWAL_DITETAPKAN"')
            ->execute([$row['pendaftar_id']]);
        $db->prepare('UPDATE jadwal_tes SET peserta_dijadwalkan = (SELECT COUNT(*) FROM peserta_ujian WHERE jadwal_id = ?) WHERE id = ?')
            ->execute([$row['jadwal_id'], $row['jadwal_id']]);
        $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        return false;
    }
}

/** Data kartu peserta untuk portal siswa (null bila belum terjadwal). */
function peserta_kartu_siswa($pendaftar_id)
{
    $baris = q_all(
        'SELECT pu.nomor_peserta, pu.ruangan,
                j.kode, j.nama_kegiatan, j.tanggal, j.jam_mulai, j.jam_selesai, j.mode, j.lokasi
           FROM peserta_ujian pu
           JOIN jadwal_tes j ON j.id = pu.jadwal_id
          WHERE pu.pendaftar_id = ?',
        [(string) $pendaftar_id]
    );
    return $baris[0] ?? null;
}

/**
 * Kirim WA jadwal ke orang tua tiap peserta (best-effort, catat wa_log).
 * @return array{ok:int,gagal:int}
 */
function peserta_kirim_notifikasi_wa($jadwal_id)
{
    $j = q_all('SELECT id, kode, nama_kegiatan, tanggal, jam_mulai, jam_selesai, mode, lokasi, ruangan FROM jadwal_tes WHERE id = ?', [(string) $jadwal_id]);
    if (!$j) {
        return ['ok' => 0, 'gagal' => 0];
    }
    $j = $j[0];
    $peserta = q_all(
        "SELECT p.nama_siswa, p.no_pendaftaran, o.no_whatsapp, pu.nomor_peserta
           FROM peserta_ujian pu
           JOIN pendaftar p ON p.id = pu.pendaftar_id
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE pu.jadwal_id = ? AND o.no_whatsapp IS NOT NULL",
        [(string) $jadwal_id]
    ) ?: [];
    if (!$peserta) {
        return ['ok' => 0, 'gagal' => 0];
    }
    $tglIndo = date('d/m/Y', strtotime($j['tanggal']));
    $metode  = ['OFFLINE' => 'Offline di sekolah', 'DARING' => 'Daring', 'VIDEO_CALL' => 'Video call'][$j['mode']] ?? $j['mode'];
    $lokasi  = $j['ruangan'] ?: ($j['lokasi'] ?: 'Sekolah');
    $ok = 0; $gagal = 0;
    foreach ($peserta as $s) {
        $pesan = "Assalamu'alaikum.\n\n"
            . "Jadwal ujian SPMB " . ($GLOBALS['PENGATURAN']['nama_sekolah'] ?? '') . ":\n\n"
            . "Siswa : {$s['nama_siswa']}\n"
            . "No. Pendaftaran : {$s['no_pendaftaran']}\n"
            . "Nomor Peserta : " . ($s['nomor_peserta'] ?: '-') . "\n"
            . "Kegiatan : {$j['kode']} - {$j['nama_kegiatan']}\n"
            . "Tanggal : {$tglIndo}\n"
            . "Jam : " . substr($j['jam_mulai'], 0, 5) . " - " . substr($j['jam_selesai'], 0, 5) . " WIB\n"
            . "Metode : {$metode}\n"
            . "Lokasi : {$lokasi}\n\n"
            . "Mohon hadir 15 menit lebih awal & membawa kartu peserta (bisa dicetak di portal siswa).\n\n"
            . "Salam,\nPanitia SPMB";
        $r = wa_kirim($s['no_whatsapp'], $pesan);
        wa_log_catat($s['no_whatsapp'], 'JADWAL_UJIAN', $r);
        if (!empty($r['ok'])) {
            $ok++;
        } else {
            $gagal++;
        }
    }
    return ['ok' => $ok, 'gagal' => $gagal];
}
