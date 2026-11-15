<?php
/**
 * Modul queries: kelas — di-include dari includes/queries.php.
 */

function daftar_kelas_admin()
{
    $baris = q_all(
        'SELECT k.id, k.kode, k.nama_kelas, k.wali_kelas, k.kuota, k.is_aktif,
                (SELECT COUNT(*) FROM penempatan_kelas pk WHERE pk.kelas_id = k.id) AS terisi
           FROM kelas k
       ORDER BY k.kode'
    );
    return $baris ?: [];
}

/** Tambah kelas baru. return id kelas atau null. */

function kelas_tambah($kode, $nama, $wali, $kuota, $aktif = 1)
{
    $id = 'kls-' . bin2hex(random_bytes(4));
    $ok = q_exec(
        'INSERT INTO kelas (id, kode, nama_kelas, wali_kelas, kuota, is_aktif)
         VALUES (?, ?, ?, ?, ?, ?)',
        [$id, $kode, $nama, $wali, (int) $kuota, $aktif ? 1 : 0]
    );
    return $ok ? $id : null;
}

/** Ubah kelas. */

function kelas_ubah($id, $kode, $nama, $wali, $kuota, $aktif)
{
    return q_exec(
        'UPDATE kelas SET kode = ?, nama_kelas = ?, wali_kelas = ?, kuota = ?, is_aktif = ?
          WHERE id = ?',
        [$kode, $nama, $wali, (int) $kuota, $aktif ? 1 : 0, $id]
    );
}

/** Hapus kelas. false bila masih ada pendaftar tertempat. */

function kelas_hapus($id)
{
    /* [PERBAIKAN] Cek keterisian harus ke tabel penempatan_kelas.
     * pendaftar.kelas_id tidak pernah ditulis aplikasi (hanya seed demo),
     * sehingga kelas berpenghuni lolos cek lalu FK ON DELETE CASCADE
     * menghapus seluruh penempatan siswa secara diam-diam. */
    $terisi = (int) (q_col('SELECT COUNT(*) FROM penempatan_kelas WHERE kelas_id = ?', [$id]) ?? 0);
    if ($terisi > 0) {
        return false;
    }
    return q_exec('DELETE FROM kelas WHERE id = ?', [$id]);
}

/** Cek apakah kode kelas sudah dipakai (untuk validasi). */

function kelas_kode_dipakai($kode, $kecualiId = null)
{
    if ($kecualiId) {
        $n = q_col('SELECT COUNT(*) FROM kelas WHERE kode = ? AND id != ?', [$kode, $kecualiId]);
    } else {
        $n = q_col('SELECT COUNT(*) FROM kelas WHERE kode = ?', [$kode]);
    }
    return (int) ($n ?? 0) > 0;
}

/**
 * Gandakan rombel untuk tahun ajaran baru (satu transaksi).
 * $baris = [['dari_id'=>, 'kode'=>, 'nama_kelas'=>, 'wali_kelas'=>, 'kuota'=>], ...]
 * Baris dengan kode kosong dilewati. Bila $nonaktifkanLama, kelas sumber
 * (dari_id) dinonaktifkan setelah salinan tersimpan — penempatan lama tetap.
 *
 * @return array{ok:bool,pesan:string,jumlah:int,nonaktif:int}
 */
function kelas_gandakan(array $baris, $nonaktifkanLama = false)
{
    $bersih = [];
    $kodeDipilih = [];
    $idSumber = [];

    foreach ($baris as $r) {
        $kode = strtoupper(trim((string) ($r['kode'] ?? '')));
        if ($kode === '') {
            continue;
        }
        $nama  = trim((string) ($r['nama_kelas'] ?? ''));
        $wali  = trim((string) ($r['wali_kelas'] ?? ''));
        $kuota = (int) ($r['kuota'] ?? 0);
        $dari  = trim((string) ($r['dari_id'] ?? ''));

        if (!preg_match('/^[A-Z0-9][A-Z0-9-]{0,9}$/', $kode)) {
            return ['ok' => false, 'pesan' => 'Kode "' . $kode . '" tidak valid (huruf/angka/tanda hubung, maks 10 karakter).', 'jumlah' => 0, 'nonaktif' => 0];
        }
        if ($nama === '') {
            return ['ok' => false, 'pesan' => 'Nama rombel untuk kode ' . $kode . ' wajib diisi.', 'jumlah' => 0, 'nonaktif' => 0];
        }
        if ($kuota < 1 || $kuota > 500) {
            return ['ok' => false, 'pesan' => 'Kuota ' . $kode . ' harus antara 1 dan 500.', 'jumlah' => 0, 'nonaktif' => 0];
        }
        if (isset($kodeDipilih[$kode])) {
            return ['ok' => false, 'pesan' => 'Kode ' . $kode . ' dipakai lebih dari satu baris.', 'jumlah' => 0, 'nonaktif' => 0];
        }
        if (kelas_kode_dipakai($kode)) {
            return ['ok' => false, 'pesan' => 'Kode ' . $kode . ' sudah dipakai kelas lain.', 'jumlah' => 0, 'nonaktif' => 0];
        }
        $kodeDipilih[$kode] = true;
        if ($dari !== '') {
            $idSumber[$dari] = true;
        }
        $bersih[] = ['kode' => $kode, 'nama' => $nama, 'wali' => $wali, 'kuota' => $kuota, 'dari' => $dari];
    }

    if (!$bersih) {
        return ['ok' => false, 'pesan' => 'Isi minimal satu kode rombel baru.', 'jumlah' => 0, 'nonaktif' => 0];
    }

    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.', 'jumlah' => 0, 'nonaktif' => 0];
    }

    try {
        $db->beginTransaction();
        $ins = $db->prepare(
            'INSERT INTO kelas (id, kode, nama_kelas, wali_kelas, kuota, is_aktif)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        foreach ($bersih as $b) {
            $ins->execute(['kls-' . bin2hex(random_bytes(4)), $b['kode'], $b['nama'], $b['wali'], $b['kuota']]);
        }

        $nonaktif = 0;
        if ($nonaktifkanLama && $idSumber) {
            $ids = array_keys($idSumber);
            $tanda = implode(',', array_fill(0, count($ids), '?'));
            $u = $db->prepare("UPDATE kelas SET is_aktif = 0 WHERE is_aktif = 1 AND id IN ($tanda)");
            $u->execute($ids);
            $nonaktif = $u->rowCount();
        }
        $db->commit();
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        if ((int) $e->getCode() === 23000) {
            return ['ok' => false, 'pesan' => 'Kode rombel bentrok saat disimpan — periksa daftar lalu coba lagi.', 'jumlah' => 0, 'nonaktif' => 0];
        }
        return ['ok' => false, 'pesan' => 'Gagal menggandakan rombel.', 'jumlah' => 0, 'nonaktif' => 0];
    }

    return [
        'ok'       => true,
        'pesan'    => count($bersih) . ' rombel baru dibuat'
            . ($nonaktif ? ', ' . $nonaktif . ' rombel lama dinonaktifkan' : '') . '.',
        'jumlah'   => count($bersih),
        'nonaktif' => (int) $nonaktif,
    ];
}

/* ==================================================================
 * 9. JADWAL TES & SELEKSI — tulis
 * ================================================================== */

/** Tambah jadwal tes baru. */
