<?php
/**
 * Modul queries: penempatan — di-include dari includes/queries.php.
 */

function penempatan_siswa($pendaftar_id)
{
    if (!db_aktif() || !$pendaftar_id) {
        return null;
    }
    $baris = q_all(
        'SELECT pk.catatan, pk.created_at, k.kode, k.nama_kelas, k.wali_kelas
           FROM penempatan_kelas pk
           JOIN kelas k ON k.id = pk.kelas_id
          WHERE pk.pendaftar_id = ?
       LIMIT 1',
        [$pendaftar_id]
    );
    return ($baris !== null && $baris) ? $baris[0] : null;
}

/* ==================================================================
 * 7b. PORTAL SISWA — perubahan data
 * ================================================================== */

/** Daftar dokumen milik satu pendaftar. */

function penempatan_kelas_opsi()
{
    $baris = q_all(
        'SELECT k.id, k.kode, k.nama_kelas, k.wali_kelas, k.kuota,
                (SELECT COUNT(*) FROM penempatan_kelas pk WHERE pk.kelas_id = k.id) AS terisi
           FROM kelas k
          WHERE k.is_aktif = 1
       ORDER BY k.kode'
    );
    return $baris ?: [];
}

/**
 * Siswa LULUS yang belum ditempatkan.
 */

function penempatan_belum()
{
    $baris = q_all(
        "SELECT p.id, p.no_pendaftaran, p.nama_siswa, p.jalur, s.nilai_akhir, s.hasil
           FROM pendaftar p
           JOIN seleksi s ON s.pendaftar_id = p.id
      LEFT JOIN penempatan_kelas pk ON pk.pendaftar_id = p.id
          WHERE s.hasil = 'LULUS' AND pk.id IS NULL
            AND p.arsip_at IS NULL
       ORDER BY s.nilai_akhir DESC"
    );
    return $baris ?: [];
}

/**
 * Daftar penempatan yang sudah ada (siswa + kelas).
 */

function penempatan_daftar()
{
    $baris = q_all(
        'SELECT pk.id, pk.pendaftar_id, pk.catatan, pk.created_at,
                p.no_pendaftaran, p.nama_siswa, s.nilai_akhir,
                k.kode, k.nama_kelas, k.wali_kelas
           FROM penempatan_kelas pk
           JOIN pendaftar p ON p.id = pk.pendaftar_id
      LEFT JOIN seleksi s ON s.pendaftar_id = p.id
           JOIN kelas k ON k.id = pk.kelas_id
       ORDER BY k.kode, p.nama_siswa'
    );
    return $baris ?: [];
}

/**
 * Tempatkan siswa ke kelas. return true/false.
 */

function penempatan_simpan($pendaftar_id, $kelas_id, $catatan = '')
{
    /* Pastikan kelas masih ada kuota. */
    $kuota  = (int) (q_col('SELECT kuota FROM kelas WHERE id = ? AND is_aktif = 1', [$kelas_id]) ?? 0);
    $terisi = (int) (q_col('SELECT COUNT(*) FROM penempatan_kelas WHERE kelas_id = ?', [$kelas_id]) ?? 0);
    if ($kuota <= 0 || $terisi >= $kuota) {
        return false;   // penuh
    }
    $ada = q_col('SELECT id FROM penempatan_kelas WHERE pendaftar_id = ?', [$pendaftar_id]);
    if ($ada) {
        return q_exec('UPDATE penempatan_kelas SET kelas_id = ?, catatan = ? WHERE id = ?', [$kelas_id, $catatan, $ada]);
    }
    $id = 'ptm-' . bin2hex(random_bytes(4));
    return q_exec(
        'INSERT INTO penempatan_kelas (id, pendaftar_id, kelas_id, catatan, ditempatkan_oleh) VALUES (?, ?, ?, ?, ?)',
        [$id, $pendaftar_id, $kelas_id, $catatan, current_user()['id'] ?? null]
    );
}

/** Pindahkan/batalkan penempatan. */

function penempatan_hapus($penempatan_id)
{
    return q_exec('DELETE FROM penempatan_kelas WHERE id = ?', [$penempatan_id]);
}

/* ==================================================================
 * 14. CEK STATUS PUBLIK (tanpa login)
 * ================================================================== */

/**
 * Cari pendaftar untuk halaman cek-status publik.
 * Mendukung pencarian fleksibel: no_pendaftaran, WA orang tua, dan/atau nama siswa.
 * Minimal satu kriteria wajib diisi; kombinasi beberapa kriteria memperketat hasil.
 * @return array  Daftar baris pendaftar (bisa >1 untuk nama umum).
 */

function penempatan_otomatis()
{
    $siswa = penempatan_belum();
    if (!$siswa) {
        return ['ok' => 0, 'gagal' => 0, 'detail' => ['Tidak ada siswa LULUS yang belum ditempatkan.']];
    }
    $kelas = penempatan_kelas_opsi();
    if (!$kelas) {
        return ['ok' => 0, 'gagal' => count($siswa), 'detail' => ['Tidak ada kelas aktif.']];
    }

    /* Salin kuota → sisa dinamis. */
    $sisa = [];
    foreach ($kelas as $k) {
        $sisa[$k['id']] = max(0, (int) $k['kuota'] - (int) $k['terisi']);
    }

    $ok = 0; $gagal = 0; $detail = [];
    foreach ($siswa as $s) {
        $target = null;
        foreach ($kelas as $k) {
            if ($sisa[$k['id']] > 0) {
                $target = $k;
                break;
            }
        }
        if ($target === null) {
            $gagal++;
            $detail[] = "{$s['nama_siswa']} — semua kelas penuh.";
            continue;
        }
        if (penempatan_simpan($s['id'], $target['id'], 'Penempatan otomatis (nilai ' . number_format((float) $s['nilai_akhir'], 1) . ')')) {
            $sisa[$target['id']]--;
            $ok++;
        } else {
            $gagal++;
            $detail[] = "{$s['nama_siswa']} — gagal disimpan.";
        }
    }
    return ['ok' => $ok, 'gagal' => $gagal, 'detail' => $detail];
}

/* ==================================================================
 * 18. NOTIFIKASI WA JADWAL TES
 * ================================================================== */

/**
 * Kirim notifikasi jadwal tes tertentu ke seluruh wali siswa terjadwal.
 * @return array{ok:int, gagal:int, detail:array}
 */
