<?php
/**
 * Modul queries: dokumen / berkas persyaratan (Fase 5) —
 * di-include dari includes/queries.php.
 *
 * Daftar jenis berkas dibaca dari tabel `jenis_dokumen` (dinamis, bisa
 * diubah operator via Pengaturan Sistem). Daftar final menyusul — data
 * awal adalah salinan jenis yang selama ini hard-coded.
 */

/* Tahap yang mengunci berkas dari perubahan siswa. */
function _dok_tahap_terkunci($tahap)
{
    return in_array($tahap, [
        'BERKAS_DISUBMIT', 'BERKAS_TERVERIFIKASI', 'JADWAL_DITETAPKAN',
        'NILAI_TERINPUT', 'DIUMUMKAN',
    ], true);
}

/* Tahap yang membuka unggah / kirim berkas. BAYAR_TERVERIFIKASI ikut
 * dibuka karena siswa sudah dapat login dan pembayarannya sudah sah —
 * akun mungkin belum sempat dikirim ulang oleh operator. */
function dokumen_tahap_boleh_unggah($tahap)
{
    return in_array($tahap, [
        'BAYAR_TERVERIFIKASI', 'AKUN_TERKIRIM', 'MELENGKAPI_BERKAS', 'BERKAS_REVISI',
    ], true);
}

/* ==================================================================
 * JENIS DOKUMEN (CRUD operator)
 * ================================================================== */

/** Daftar jenis aktif untuk portal siswa (terurut). */
function jenis_dokumen_aktif()
{
    return q_all('SELECT * FROM jenis_dokumen WHERE aktif = 1 ORDER BY urutan, nama') ?: [];
}

/** Seluruh jenis (termasuk nonaktif) untuk CMS. */
function jenis_dokumen_semua()
{
    return q_all('SELECT * FROM jenis_dokumen ORDER BY urutan, nama') ?: [];
}

/** Apakah ada berkas siswa memakai kode ini? (untuk cek sebelum nonaktif/hapus) */
function jenis_dokumen_dipakai($kode)
{
    return (int) (q_col('SELECT COUNT(*) FROM dokumen WHERE jenis_dokumen = ?', [(string) $kode]) ?? 0);
}

/**
 * Simpan jenis dokumen (insert / update — upsert by kode).
 * @return array{ok:bool, pesan?:string}
 */
function jenis_dokumen_simpan($data)
{
    $kode  = strtoupper(preg_replace('/[^A-Z0-9_]/', '', (string) ($data['kode'] ?? '')));
    $nama  = trim((string) ($data['nama'] ?? ''));
    $wajib = !empty($data['wajib']) ? 1 : 0;
    $aktif = !empty($data['aktif']) ? 1 : 0;
    $fmt   = strtolower(trim((string) ($data['format_diizinkan'] ?? 'jpg,jpeg,png,pdf')));
    $maks  = max(64, (int) ($data['maks_kb'] ?? 2048));
    $urut  = (int) ($data['urutan'] ?? 0);

    if ($kode === '' || strlen($kode) > 30) {
        return ['ok' => false, 'pesan' => 'Kode berkas wajib diisi (huruf besar/angka/underscore, maks 30).'];
    }
    if ($nama === '' || strlen($nama) > 100) {
        return ['ok' => false, 'pesan' => 'Nama berkas wajib diisi (maks 100 karakter).'];
    }
    /* Validasi daftar format. */
    $daftarFmt = array_values(array_filter(array_map('trim', explode(',', $fmt))));
    if (!$daftarFmt) {
        return ['ok' => false, 'pesan' => 'Daftar format diizinkan tidak boleh kosong.'];
    }
    foreach ($daftarFmt as $f) {
        if (!preg_match('/^[a-z0-9]{2,5}$/', $f)) {
            return ['ok' => false, 'pesan' => 'Format "' . e($f) . '" tidak valid (contoh: jpg,jpeg,png,pdf).'];
        }
    }
    $fmt = implode(',', $daftarFmt);

    $ok = q_exec(
        'INSERT INTO jenis_dokumen (kode, nama, wajib, format_diizinkan, maks_kb, urutan, aktif)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE nama = VALUES(nama), wajib = VALUES(wajib),
             format_diizinkan = VALUES(format_diizinkan), maks_kb = VALUES(maks_kb),
             urutan = VALUES(urutan), aktif = VALUES(aktif)',
        [$kode, $nama, $wajib, $fmt, $maks, $urut, $aktif]
    );
    return $ok ? ['ok' => true] : ['ok' => false, 'pesan' => 'Gagal menyimpan jenis berkas.'];
}

/** Nonaktifkan jenis (tidak dihapus permanen agar riwayat berkas aman). */
function jenis_dokumen_nonaktifkan($kode)
{
    return q_exec('UPDATE jenis_dokumen SET aktif = 0 WHERE kode = ?', [(string) $kode]);
}

function jenis_dokumen_aktifkan($kode)
{
    return q_exec('UPDATE jenis_dokumen SET aktif = 1 WHERE kode = ?', [(string) $kode]);
}

/* ==================================================================
 * UNGGAH & STATUS BERKAS SISWA
 * ================================================================== */

/**
 * Daftar berkas satu pendaftar: setiap jenis AKTIF + baris dokumen bila ada.
 * @return array<int, array> ['kode','nama','wajib','format','maks_kb',
 *                            'dok_id','file_url','status','catatan','verified_by','verified_at','ukuran_kb','uploaded_at']
 */
function dokumen_siswa($pendaftar_id)
{
    $jenis = jenis_dokumen_aktif();
    $baris = q_all(
        'SELECT id, jenis_dokumen, file_url, ukuran_kb, status_verifikasi,
                catatan_verifikasi, verified_by, verified_at, uploaded_at
           FROM dokumen WHERE pendaftar_id = ?',
        [$pendaftar_id]
    ) ?: [];
    $per = [];
    foreach ($baris as $b) {
        $per[$b['jenis_dokumen']] = $b;
    }
    $keluar = [];
    foreach ($jenis as $j) {
        $d = $per[$j['kode']] ?? null;
        $keluar[] = [
            'kode'      => $j['kode'],
            'nama'      => $j['nama'],
            'wajib'     => (int) $j['wajib'],
            'format'    => $j['format_diizinkan'],
            'maks_kb'   => (int) $j['maks_kb'],
            'dok_id'    => $d['id'] ?? null,
            'file_url'  => $d['file_url'] ?? null,
            'status'    => $d['status_verifikasi'] ?? null,
            'catatan'   => $d['catatan_verifikasi'] ?? null,
            'verifier'  => $d['verified_by'] ?? null,
            'verified_at' => $d['verified_at'] ?? null,
            'ukuran_kb' => $d['ukuran_kb'] ?? null,
            'uploaded_at' => $d['uploaded_at'] ?? null,
        ];
    }
    return $keluar;
}

/**
 * Unggah / ganti berkas satu jenis. Menghormati kunci tahap:
 *   - tahap terkunci (BERKAS_DISUBMIT dst.) → tolak semua;
 *   - BERKAS_REVISI → hanya jenis berstatus REJECTED;
 *   - selain itu → jenis apa pun (wajib/opsional) boleh.
 *
 * Update in-place per (pendaftar, jenis) — idempoten, tidak membuat baris ganda.
 *
 * @return array{ok:bool, pesan?:string}
 */
function dokumen_upload($pendaftar_id, $kode, $pathRel, $ukuranKb)
{
    $tahap = q_col('SELECT tahap FROM pendaftar WHERE id = ?', [$pendaftar_id]);
    if ($tahap === null) {
        return ['ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.'];
    }
    if (_dok_tahap_terkunci($tahap)) {
        return ['ok' => false, 'pesan' => 'Berkas terkunci pada tahap ini. Menunggu keputusan panitia / proses selanjutnya.'];
    }
    if (!dokumen_tahap_boleh_unggah($tahap)) {
        return ['ok' => false, 'pesan' => 'Unggah berkas dibuka setelah pembayaran formulir diverifikasi.'];
    }

    $j = q_all('SELECT * FROM jenis_dokumen WHERE kode = ? AND aktif = 1', [(string) $kode]);
    if (!$j) {
        return ['ok' => false, 'pesan' => 'Jenis berkas tidak dikenali / tidak aktif.'];
    }

    if ($tahap === 'BERKAS_REVISI') {
        $st = q_col('SELECT status_verifikasi FROM dokumen WHERE pendaftar_id = ? AND jenis_dokumen = ?', [$pendaftar_id, (string) $kode]);
        if ($st !== 'REJECTED') {
            return ['ok' => false, 'pesan' => 'Hanya berkas yang ditolak yang dapat diunggah ulang saat revisi.'];
        }
    }

    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    try {
        $db->beginTransaction();
        $ada = $db->prepare('SELECT id, file_url FROM dokumen WHERE pendaftar_id = ? AND jenis_dokumen = ? LIMIT 1 FOR UPDATE');
        $ada->execute([$pendaftar_id, (string) $kode]);
        $lama = $ada->fetch();
        if ($lama) {
            $db->prepare(
                'UPDATE dokumen SET file_url = ?, ukuran_kb = ?, status_verifikasi = "PENDING",
                        uploaded_at = NOW(), verified_at = NULL, verified_by = NULL
                  WHERE id = ?'
            )->execute([$pathRel, (int) $ukuranKb, $lama['id']]);
            $id = $lama['id'];
            $lamaUrl = $lama['file_url'];
        } else {
            $id = 'dok-' . bin2hex(random_bytes(4));
            $db->prepare(
                'INSERT INTO dokumen (id, pendaftar_id, jenis_dokumen, file_url, ukuran_kb, status_verifikasi)
                 VALUES (?, ?, ?, ?, ?, "PENDING")'
            )->execute([$id, $pendaftar_id, (string) $kode, $pathRel, (int) $ukuranKb]);
            $lamaUrl = null;
        }
        $db->commit();
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal menyimpan berkas.'];
    }

    /* Hapus berkas lama setelah update sukses. */
    if (!empty($lamaUrl)) {
        hapus_berkas_upload($lamaUrl);
    }
    return ['ok' => true];
}

/**
 * "Kirim untuk Verifikasi": semua berkas WAJIB harus sudah diunggah.
 * Setelah submit: tahap = BERKAS_DISUBMIT + notifikasi operator.
 *
 * @return array{ok:bool, pesan?:string, kurang?:string[]}
 */
function dokumen_submit($pendaftar_id)
{
    $tahap = q_col('SELECT tahap FROM pendaftar WHERE id = ?', [$pendaftar_id]);
    if ($tahap === null) {
        return ['ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.'];
    }
    if (_dok_tahap_terkunci($tahap)) {
        return ['ok' => false, 'pesan' => 'Berkas sudah terkirim & dikunci pada tahap ini.'];
    }
    if (!dokumen_tahap_boleh_unggah($tahap)) {
        return ['ok' => false, 'pesan' => 'Tahap saat ini belum memungkinkan pengiriman berkas (' . $tahap . ').'];
    }

    $daftar = dokumen_siswa($pendaftar_id);
    $kurang = [];
    foreach ($daftar as $d) {
        if ($d['wajib'] && empty($d['file_url'])) {
            $kurang[] = $d['nama'];
        }
    }
    if ($kurang) {
        return ['ok' => false, 'pesan' => 'Berkas wajib belum lengkap.', 'kurang' => $kurang];
    }

    /* Pastikan minimal ada satu berkas. */
    $ada = (int) (q_col('SELECT COUNT(*) FROM dokumen WHERE pendaftar_id = ? AND file_url IS NOT NULL', [$pendaftar_id]) ?? 0);
    if ($ada === 0) {
        return ['ok' => false, 'pesan' => 'Belum ada berkas yang diunggah.'];
    }

    $ok = q_exec(
        "UPDATE pendaftar SET tahap = 'BERKAS_DISUBMIT' WHERE id = ? AND tahap IN ('BAYAR_TERVERIFIKASI', 'AKUN_TERKIRIM', 'MELENGKAPI_BERKAS', 'BERKAS_REVISI')",
        [$pendaftar_id]
    );
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal menyimpan pengiriman berkas.'];
    }

    /* Notifikasi operator (Fase 3) — tidak boleh menggagalkan submit. */
    try {
        $info = q_all('SELECT no_pendaftaran, nama_siswa FROM pendaftar WHERE id = ?', [$pendaftar_id]);
        $info = $info ? $info[0] : null;
        if ($info !== null) {
            $tautan = url_abs('app/operator/berkas.php');
            notif_operator(
                'BERKAS_DISUBMIT',
                $pendaftar_id,
                "Berkas persyaratan dikirim untuk verifikasi.\n"
                . "Nama : {$info['nama_siswa']}\n"
                . "No. Pendaftaran : {$info['no_pendaftaran']}\n"
                . "Periksa: {$tautan}"
            );
        }
    } catch (Throwable $e) {
        /* abaikan */
    }

    return ['ok' => true];
}

/* ==================================================================
 * VERIFIKASI BERKAS (operator)
 * ================================================================== */

/** Antrean: pendaftar dengan tahap BERKAS_DISUBMIT + ringkasan berkas. */
function berkas_antrean()
{
    $baris = q_all(
        "SELECT p.id, p.no_pendaftaran, p.nama_siswa, p.asal_sekolah, p.tahap, p.created_at,
                o.no_whatsapp, o.nama_orang_tua,
                (SELECT COUNT(*) FROM dokumen d WHERE d.pendaftar_id = p.id AND d.file_url IS NOT NULL) AS jml_berkas,
                (SELECT COUNT(*) FROM dokumen d WHERE d.pendaftar_id = p.id AND d.status_verifikasi = 'VERIFIED') AS jml_lolos,
                (SELECT COUNT(*) FROM dokumen d WHERE d.pendaftar_id = p.id AND d.status_verifikasi = 'REJECTED') AS jml_tolak
           FROM pendaftar p
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.tahap IN ('BERKAS_DISUBMIT', 'BERKAS_REVISI')
            AND p.arsip_at IS NULL
       ORDER BY FIELD(p.tahap,'BERKAS_DISUBMIT','BERKAS_REVISI'), p.no_pendaftaran"
    );
    return $baris ?: [];
}

/** Detail berkas satu pendaftar untuk halaman verifikasi. */
function berkas_detail_pendaftar($pendaftar_id)
{
    $pend = q_all(
        'SELECT p.id, p.no_pendaftaran, p.nama_siswa, p.tahap, p.asal_sekolah,
                o.no_whatsapp, o.nama_orang_tua
           FROM pendaftar p
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.id = ? LIMIT 1',
        [$pendaftar_id]
    );
    $dok = q_all(
        "SELECT d.*, v.nama_lengkap AS verifier_nama
           FROM dokumen d
      LEFT JOIN users v ON v.id = d.verified_by
          WHERE d.pendaftar_id = ?
       ORDER BY d.jenis_dokumen",
        [$pendaftar_id]
    ) ?: [];
    $jenis = jenis_dokumen_semua();
    $namaJenis = [];
    foreach ($jenis as $j) {
        $namaJenis[$j['kode']] = $j['nama'];
    }
    foreach ($dok as &$d) {
        $d['nama_jenis'] = $namaJenis[$d['jenis_dokumen']] ?? $d['jenis_dokumen'];
    }
    unset($d);
    return ['pend' => ($pend ? $pend[0] : null), 'dok' => $dok];
}

/**
 * Putuskan satu berkas: Setujui (VERIFIED) / Tolak (REJECTED + alasan wajib).
 * Menyimpan catatan + verified_by/verified_at (riwayat keputusan terakhir).
 *
 * @return array{ok:bool, pesan?:string}
 */
function berkas_keputusan($dokId, $setuju, $alasan, $verifierId = null)
{
    $alasan = trim((string) $alasan);
    if (!$setuju && $alasan === '') {
        return ['ok' => false, 'pesan' => 'Alasan penolakan wajib diisi.'];
    }
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    try {
        $db->beginTransaction();
        $ada = $db->prepare('SELECT d.id, d.pendaftar_id, d.status_verifikasi FROM dokumen d WHERE d.id = ? FOR UPDATE');
        $ada->execute([(string) $dokId]);
        $baris = $ada->fetch();
        if (!$baris) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Berkas tidak ditemukan.'];
        }
        /* Hanya berkas pendaftar berstatus BERKAS_DISUBMIT. */
        $tahap = q_col('SELECT tahap FROM pendaftar WHERE id = ?', [$baris['pendaftar_id']]);
        if ($tahap !== 'BERKAS_DISUBMIT') {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Berkas hanya dapat diputuskan saat tahap BERKAS_DISUBMIT.'];
        }

        $st   = $setuju ? 'VERIFIED' : 'REJECTED';
        $cat  = $setuju ? null : mb_substr($alasan, 0, 2000);
        $db->prepare(
            'UPDATE dokumen SET status_verifikasi = ?, catatan_verifikasi = ?, verified_by = ?, verified_at = NOW()
              WHERE id = ?'
        )->execute([$st, $cat, $verifierId, $dokId]);
        $db->commit();
        return ['ok' => true, 'pendaftar_id' => $baris['pendaftar_id']];
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal menyimpan keputusan berkas.'];
    }
}

/**
 * Evaluasi seluruh berkas pendaftar SETELAH keputusan terakhir:
 *   - semua unggahan VERIFIED  → tahap BERKAS_TERVERIFIKASI
 *   - ada REJECTED             → tahap BERKAS_REVISI + WA daftar ditolak
 * Ditolak → hanya berkas REJECTED yang dibuka untuk unggah ulang.
 *
 * @return array{ok:bool, tahap?:string, pesan?:string}
 */
function berkas_evaluasi_tahap($pendaftar_id)
{
    $tahap = q_col('SELECT tahap FROM pendaftar WHERE id = ?', [$pendaftar_id]);
    if ($tahap !== 'BERKAS_DISUBMIT') {
        return ['ok' => false, 'pesan' => 'Bukan tahap BERKAS_DISUBMIT.'];
    }

    $jml     = (int) (q_col('SELECT COUNT(*) FROM dokumen WHERE pendaftar_id = ? AND file_url IS NOT NULL', [$pendaftar_id]) ?? 0);
    $lolos   = (int) (q_col("SELECT COUNT(*) FROM dokumen WHERE pendaftar_id = ? AND status_verifikasi = 'VERIFIED'", [$pendaftar_id]) ?? 0);
    $ditolak = (int) (q_col("SELECT COUNT(*) FROM dokumen WHERE pendaftar_id = ? AND status_verifikasi = 'REJECTED'", [$pendaftar_id]) ?? 0);

    if ($ditolak > 0) {
        q_exec("UPDATE pendaftar SET tahap = 'BERKAS_REVISI' WHERE id = ? AND tahap = 'BERKAS_DISUBMIT'", [$pendaftar_id]);
        /* WA orang tua: daftar berkas ditolak + alasan. */
        try {
            $info = q_all(
                'SELECT p.no_pendaftaran, p.nama_siswa, o.no_whatsapp
                   FROM pendaftar p LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
                  WHERE p.id = ? LIMIT 1',
                [$pendaftar_id]
            );
            $info = $info ? $info[0] : null;
            if ($info && !empty($info['no_whatsapp'])) {
                $daftar = q_all(
                    "SELECT jenis_dokumen, catatan_verifikasi FROM dokumen
                      WHERE pendaftar_id = ? AND status_verifikasi = 'REJECTED'",
                    [$pendaftar_id]
                ) ?: [];
                $namaJenis = [];
                foreach (jenis_dokumen_semua() as $j) {
                    $namaJenis[$j['kode']] = $j['nama'];
                }
                $baris = [];
                foreach ($daftar as $d) {
                    $nm = $namaJenis[$d['jenis_dokumen']] ?? $d['jenis_dokumen'];
                    $baris[] = "- {$nm}: " . ($d['catatan_verifikasi'] ?: 'Ditolak tanpa catatan');
                }
                $daftarTeks = implode("\n", $baris);
                $pesan = "Assalamu'alaikum.\n\n"
                    . "Hasil pemeriksaan berkas SPMB atas nama:\n"
                    . "Nama : {$info['nama_siswa']}\n"
                    . "No. Pendaftaran : {$info['no_pendaftaran']}\n\n"
                    . "Berkas berikut perlu DIPERBAIKI / diganti:\n"
                    . "{$daftarTeks}\n\n"
                    . "Silakan unggah ulang berkas tersebut melalui portal siswa, lalu klik "
                    . "\"Kirim untuk Verifikasi\" kembali. Berkas yang sudah lolos tidak perlu diunggah ulang.\n\n"
                    . "Terima kasih.\nPanitia SPMB " . ($GLOBALS['PENGATURAN']['nama_sekolah'] ?? 'SMP Muhammadiyah 5');
                $r = wa_kirim($info['no_whatsapp'], $pesan);
                wa_log_catat($info['no_whatsapp'], 'BERKAS_REVISI', $r);
            }
        } catch (Throwable $e) {
            /* abaikan */
        }
        return ['ok' => true, 'tahap' => 'BERKAS_REVISI', 'pesan' => "{$ditolak} berkas ditolak — pendaftar diminta revisi."];
    }

    if ($jml > 0 && $lolos === $jml) {
        q_exec("UPDATE pendaftar SET tahap = 'BERKAS_TERVERIFIKASI' WHERE id = ? AND tahap = 'BERKAS_DISUBMIT'", [$pendaftar_id]);
        return ['ok' => true, 'tahap' => 'BERKAS_TERVERIFIKASI', 'pesan' => 'Seluruh berkas lolos — BERKAS_TERVERIFIKASI.'];
    }

    return ['ok' => false, 'pesan' => 'Masih ada berkas yang belum diputuskan.'];
}
