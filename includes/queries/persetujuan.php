<?php
/**
 * LAPISAN QUERY — Persetujuan & tanda tangan hasil seleksi oleh Kepala Sekolah.
 *
 * Dua catatan terpisah di pengaturan_sekolah:
 *   - "disetujui": persetujuan atas DAFTAR HASIL sebelum operator menerbitkan
 *     pengumuman. Inilah yang mengunci tombol "Umumkan Sekarang".
 *   - "ttd": tanda tangan atas rilis yang SUDAH terbit (arsip administratif,
 *     dipakai juga untuk pengumuman gelombang sebelumnya).
 *
 * Keduanya menyimpan SIDIK daftar hasil (sha256 atas no_pendaftaran +
 * status_seleksi seluruh pendaftar aktif). Bila operator mengubah hasil
 * setelah menyetujui, sidik tidak cocok dan catatan lama dianggap batal —
 * bukan sekadar menempel pada angka yang sudah tidak benar lagi.
 *
 * Semua pembacaan memakai q_* yang mengembalikan null saat query gagal, jadi
 * halaman tetap tampil (tanpa persetujuan) alih-alih error 500.
 */

/** Kolom dasar per jenis catatan. Nama kolom dibangun dari daftar tetap ini,
 *  bukan dari input user, karena dipakai di dalam SQL. */
function persetujuan_kolom($jenis)
{
    if (!in_array($jenis, ['disetujui', 'ttd'], true)) {
        throw new InvalidArgumentException('Jenis persetujuan tidak dikenal: ' . $jenis);
    }
    return [
        'oleh'  => 'hasil_' . $jenis . '_oleh',
        'at'    => 'hasil_' . $jenis . '_at',
        'sidik' => 'hasil_' . $jenis . '_sidik',
    ];
}

/**
 * Sidik daftar hasil saat ini. String kosong bila database tidak terbaca,
 * supaya pemanggil memperlakukannya sebagai "tidak ada yang bisa disetujui".
 */
function persetujuan_sidik_hasil()
{
    if (!db_aktif()) {
        return '';
    }
    $baris = q_all(
        'SELECT no_pendaftaran, status_seleksi FROM pendaftar
          WHERE arsip_at IS NULL ORDER BY no_pendaftaran'
    );
    if ($baris === null) {
        return '';
    }
    $rakit = [];
    foreach ($baris as $b) {
        $rakit[] = $b['no_pendaftaran'] . '=' . $b['status_seleksi'];
    }
    return hash('sha256', implode("\n", $rakit));
}

/**
 * Baca satu jenis catatan persetujuan/tanda tangan.
 * @return array{ada:bool,batal:bool,siapa:?string,kapan:?string,sidik:?string,sidik_kini:string}
 */
function persetujuan_baca($jenis)
{
    $kol = persetujuan_kolom($jenis);
    $out = ['ada' => false, 'batal' => false, 'siapa' => null, 'kapan' => null,
            'sidik' => null, 'sidik_kini' => persetujuan_sidik_hasil()];
    if (!db_aktif()) {
        return $out;
    }

    $oleh = $kol['oleh'];
    $at   = $kol['at'];
    $sid  = $kol['sidik'];
    $baris = q_all(
        "SELECT p.{$oleh} AS oleh_id,
                p.{$at}   AS kapan,
                p.{$sid}  AS sidik,
                u.nama_lengkap AS siapa
           FROM pengaturan_sekolah p
           LEFT JOIN users u ON u.id = p.{$oleh}
          WHERE p.id = 1 LIMIT 1"
    );
    if (!$baris) {
        return $out;
    }
    $r = $baris[0];
    if (empty($r['oleh_id'])) {
        return $out;
    }

    $out['ada']   = true;
    $out['siapa'] = $r['siapa'] ?: '(akun sudah dihapus)';
    $out['kapan'] = $r['kapan'];
    $out['sidik'] = $r['sidik'];
    /* Sidik berbeda = daftar hasil diubah setelah catatan ini dibuat. */
    $out['batal'] = ((string) $r['sidik'] !== $out['sidik_kini']);
    return $out;
}

/** Simpan/ubah catatan. Hanya akun kepala sekolah yang aktif yang boleh menulis —
 *  diperiksa di sini, bukan hanya di layar, agar persetujuan tidak bisa menempel
 *  pada akun lain (mis. admin yang ikut membuka halaman ini). */
function persetujuan_simpan($jenis, $userId)
{
    $kol = persetujuan_kolom($jenis);
    $oleh = $kol['oleh'];
    $at   = $kol['at'];
    $sid  = $kol['sidik'];

    $sidik = persetujuan_sidik_hasil();
    if ($userId === null || $sidik === '') {
        return false;
    }
    $sah = q_col(
        "SELECT id FROM users WHERE id = ? AND role = 'kepsek' AND is_active = 1 LIMIT 1",
        [(string) $userId]
    );
    if (!$sah) {
        return false;
    }

    return (bool) q_exec(
        "UPDATE pengaturan_sekolah
            SET {$oleh} = ?, {$at} = NOW(), {$sid} = ?
          WHERE id = 1",
        [(string) $userId, $sidik]
    );
}

/** Hapus catatan (pencabutan persetujuan/tanda tangan). */
function persetujuan_hapus($jenis)
{
    $kol = persetujuan_kolom($jenis);
    $oleh = $kol['oleh'];
    $at   = $kol['at'];
    $sid  = $kol['sidik'];

    return (bool) q_exec(
        "UPDATE pengaturan_sekolah
            SET {$oleh} = NULL, {$at} = NULL, {$sid} = NULL
          WHERE id = 1"
    );
}

/**
 * Apakah hasil boleh diterbitkan? Hanya bila ada persetujuan yang masih sah
 * (sidik cocok dengan daftar hasil saat ini).
 */
function persetujuan_hasil_sah()
{
    $s = persetujuan_baca('disetujui');
    return $s['ada'] && !$s['batal'];
}

/**
 * Ringkasan untuk layar: jumlah per status hasil, jadi kepala sekolah
 * menyetujui angka yang bisa dilihatnya, bukan daftar panjang.
 */
function persetujuan_ringkas_hasil()
{
    $out = ['total' => 0, 'LULUS' => 0, 'TIDAK_LULUS' => 0, 'PROSES' => 0];
    $baris = q_all(
        'SELECT status_seleksi, COUNT(*) n FROM pendaftar
          WHERE arsip_at IS NULL GROUP BY status_seleksi'
    );
    if (!$baris) {
        return $out;
    }
    foreach ($baris as $b) {
        $n = (int) $b['n'];
        $out['total'] += $n;
        $k = (string) $b['status_seleksi'];
        if (isset($out[$k])) {
            $out[$k] += $n;
        }
    }
    return $out;
}
