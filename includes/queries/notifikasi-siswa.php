<?php
/**
 * Modul queries: NOTIFIKASI SISWA (in-app per penerima).
 * Di-include dari includes/queries.php.
 *
 * Berbeda dari tabel `notifikasi` (papan kerja operator), setiap baris di
 * `notifikasi_siswa` milik SATU pendaftar sehingga status baca belum dibaca
 * dihitung per penerima. Semua fungsi di sini tidak pernah melempar exception
 * (q_exec/q_col menangkap error sendiri), jadi aman dipanggil dari alur utama.
 */

/**
 * Kirim satu notifikasi ke satu pendaftar.
 *
 * @param string      $pendaftar_id penerima (pendaftar.id)
 * @param string      $tipe         kode tipe (BERKAS_DITOLAK, BAYAR_TERVERIFIKASI, dst.)
 * @param string      $judul        judul singkat
 * @param string      $pesan        isi pesan (boleh multi-baris)
 * @param string|null $tautan       URL tujuan (relatif via url())
 * @param string|null $ref_id       id sumber (mis. pengumuman_umum.id) untuk pembersihan
 * @return bool
 */
function notif_siswa_kirim($pendaftar_id, $tipe, $judul, $pesan, $tautan = null, $ref_id = null)
{
    $pendaftar_id = trim((string) $pendaftar_id);
    $judul = trim((string) $judul);
    $pesan = trim((string) $pesan);
    if ($pendaftar_id === '' || $judul === '' || $pesan === '') {
        return false;
    }
    return (bool) q_exec(
        'INSERT INTO notifikasi_siswa (id, pendaftar_id, tipe, judul, pesan, tautan, ref_id)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            'nss-' . bin2hex(random_bytes(6)),
            $pendaftar_id,
            mb_substr((string) $tipe, 0, 40),
            mb_substr($judul, 0, 160),
            $pesan,
            ($tautan !== null && $tautan !== '') ? mb_substr((string) $tautan, 0, 255) : null,
            ($ref_id !== null && $ref_id !== '') ? mb_substr((string) $ref_id, 0, 36) : null,
        ]
    );
}

/** Sudah pernah ada notifikasi tipe ini untuk penerima (dipakai pencegah duplikat). */
function notif_siswa_sudah_ada($pendaftar_id, $tipe)
{
    return (int) (q_col(
        'SELECT COUNT(*) FROM notifikasi_siswa WHERE pendaftar_id = ? AND tipe = ?',
        [(string) $pendaftar_id, (string) $tipe]
    ) ?? 0) > 0;
}

/**
 * Siarkan notifikasi ke SELURUH pendaftar aktif (belum diarsipkan).
 * @return int jumlah baris notifikasi yang berhasil dibuat
 */
function notif_siswa_siarkan($tipe, $judul, $pesan, $tautan = null, $ref_id = null)
{
    $rows = q_all('SELECT id FROM pendaftar WHERE arsip_at IS NULL') ?: [];
    $n = 0;
    foreach ($rows as $r) {
        if (notif_siswa_kirim($r['id'], $tipe, $judul, $pesan, $tautan, $ref_id)) {
            $n++;
        }
    }
    return $n;
}

/** Hapus seluruh notifikasi yang berasal dari sumber tertentu (mis. pengumuman ditarik). */
function notif_siswa_hapus_ref($ref_id)
{
    return q_exec('DELETE FROM notifikasi_siswa WHERE ref_id = ?', [(string) $ref_id]);
}

/**
 * Cari notifikasi berdasarkan kunci sumber (ref_id) — dipakai pencegah
 * pengingat ganda per jadwal. null bila belum pernah dibuat.
 */
function notif_siswa_cari_ref($pendaftar_id, $ref_id)
{
    $rows = q_all(
        'SELECT id, dibaca_at, wa_status, wa_at
           FROM notifikasi_siswa
          WHERE pendaftar_id = ? AND ref_id = ?
       ORDER BY created_at ASC
          LIMIT 1',
        [(string) $pendaftar_id, (string) $ref_id]
    );
    return $rows ? $rows[0] : null;
}

/** Catat hasil kirim WhatsApp pada notifikasi dengan ref_id tertentu. */
function notif_siswa_status_wa($pendaftar_id, $ref_id, $status)
{
    return (bool) q_exec(
        'UPDATE notifikasi_siswa SET wa_status = ?, wa_at = NOW()
          WHERE pendaftar_id = ? AND ref_id = ?',
        [mb_substr((string) $status, 0, 20), (string) $pendaftar_id, (string) $ref_id]
    );
}

/**
 * Batas waktu notifikasi yang boleh dibaca satu pendaftar.
 *
 * Isinya waktu masuk PERTAMA akun ini ke portal siswa (users.notif_mulai_at),
 * ditulis sekali pada panggilan pertama — biasanya saat dashboard dimuat.
 * Keputusan panitia yang tercatat sebelum akun pernah dipakai (mis. pembayaran
 * ditolak ketika akun baru dibuat) tetap tersimpan untuk audit, tapi tidak
 * ditampilkan: siswa tidak boleh disambut riwayat yang belum pernah ia alami.
 *
 * NOW() diminta ke server (bukan jam PHP) agar satu skala dengan
 * notifikasi_siswa.created_at.
 *
 * @return string|null 'YYYY-MM-DD HH:MM:SS', atau null bila tidak ada batas
 *                     (pendaftar tanpa akun users, atau kolom belum dimigrasi).
 */
function notif_siswa_batas($pendaftar_id)
{
    static $cache = [];

    $pendaftar_id = trim((string) $pendaftar_id);
    if ($pendaftar_id === '') {
        return null;
    }
    if (array_key_exists($pendaftar_id, $cache)) {
        return $cache[$pendaftar_id];
    }

    $akun = q_all(
        'SELECT u.id AS user_id, u.notif_mulai_at
           FROM pendaftar p
           JOIN users u ON u.id = p.user_id
          WHERE p.id = ? LIMIT 1',
        [$pendaftar_id]
    );
    if (!$akun) {
        return $cache[$pendaftar_id] = null;
    }

    $mulai = $akun[0]['notif_mulai_at'] ?? null;
    if ($mulai === null || $mulai === '') {
        $mulai = q_col('SELECT NOW()');
        q_exec(
            'UPDATE users SET notif_mulai_at = ? WHERE id = ? AND notif_mulai_at IS NULL',
            [(string) $mulai, (string) $akun[0]['user_id']]
        );
    }

    return $cache[$pendaftar_id] = ($mulai !== null && $mulai !== '') ? (string) $mulai : null;
}

/**
 * Daftar notifikasi satu pendaftar, terbaru lebih dulu.
 * Peristiwa sebelum masuk pertama (notif_siswa_batas) tidak dikembalikan.
 */
function notif_siswa_daftar($pendaftar_id, $limit = 200)
{
    $limit = max(1, min(500, (int) $limit));
    $saring = '';
    $params = [(string) $pendaftar_id];
    $batas = notif_siswa_batas($pendaftar_id);
    if ($batas !== null) {
        $saring = ' AND created_at >= ?';
        $params[] = $batas;
    }
    $rows = q_all(
        'SELECT id, tipe, judul, pesan, tautan, dibaca_at, created_at
           FROM notifikasi_siswa
          WHERE pendaftar_id = ?' . $saring . '
       ORDER BY created_at DESC, id DESC
          LIMIT ' . $limit,
        $params
    );
    return $rows ?: [];
}

/**
 * Jumlah notifikasi belum dibaca milik satu pendaftar — dipakai badge sidebar,
 * topbar, dan API. Batasnya sama dengan notif_siswa_daftar agar angka badge
 * tidak pernah menunjuk pesan yang tidak muncul di halaman.
 */
function notif_siswa_belum_dibaca($pendaftar_id)
{
    $saring = '';
    $params = [(string) $pendaftar_id];
    $batas = notif_siswa_batas($pendaftar_id);
    if ($batas !== null) {
        $saring = ' AND created_at >= ?';
        $params[] = $batas;
    }
    return (int) (q_col(
        'SELECT COUNT(*) FROM notifikasi_siswa
          WHERE pendaftar_id = ? AND dibaca_at IS NULL' . $saring,
        $params
    ) ?? 0);
}

/**
 * Tandai dibaca satu notifikasi ($id) atau semua milik pendaftar ($id = null).
 * @return bool
 */
function notif_siswa_tandai_dibaca($pendaftar_id, $id = null)
{
    if ($id !== null && $id !== '') {
        return (bool) q_exec(
            'UPDATE notifikasi_siswa SET dibaca_at = NOW()
              WHERE id = ? AND pendaftar_id = ? AND dibaca_at IS NULL',
            [(string) $id, (string) $pendaftar_id]
        );
    }
    return (bool) q_exec(
        'UPDATE notifikasi_siswa SET dibaca_at = NOW() WHERE pendaftar_id = ? AND dibaca_at IS NULL',
        [(string) $pendaftar_id]
    );
}

/** Tandai dibaca semua notifikasi satu tipe (mis. isi pengumuman sudah tampil di halaman). */
function notif_siswa_tandai_dibaca_tipe($pendaftar_id, $tipe)
{
    return (bool) q_exec(
        'UPDATE notifikasi_siswa SET dibaca_at = NOW()
          WHERE pendaftar_id = ? AND tipe = ? AND dibaca_at IS NULL',
        [(string) $pendaftar_id, (string) $tipe]
    );
}

/** Label + tone lencana per tipe notifikasi siswa. */
function notif_siswa_label($tipe)
{
    $peta = [
        'BERKAS_TERVERIFIKASI'  => ['Berkas Terverifikasi', 'green', 'verified'],
        'BERKAS_DITOLAK'        => ['Berkas Perlu Perbaikan', 'red', 'report'],
        'BAYAR_TERVERIFIKASI'   => ['Pembayaran Terverifikasi', 'green', 'task_alt'],
        'BAYAR_DITOLAK'         => ['Pembayaran Ditolak', 'red', 'money_off'],
        'DATA_TERVERIFIKASI'    => ['Data Terverifikasi', 'green', 'fact_check'],
        'DATA_DITOLAK'          => ['Data Perlu Perbaikan', 'red', 'edit_note'],
        'KOREKSI_DATA'          => ['Koreksi Data', 'blue', 'edit_note'],
        'HASIL_SELEKSI'         => ['Hasil Seleksi', 'blue', 'emoji_events'],
        'JADWAL_UJIAN'          => ['Jadwal Tes', 'blue', 'event'],
        'PENGUMUMAN_UMUM'       => ['Pengumuman Panitia', 'blue', 'campaign'],
        'ANGSURAN_JATUH_TEMPO'  => ['Jatuh Tempo Angsuran', 'amber', 'event_busy'],
    ];
    return $peta[$tipe] ?? [(string) $tipe, 'gray', 'notifications'];
}
