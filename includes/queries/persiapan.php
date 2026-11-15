<?php
/**
 * Modul queries: persiapan tahun ajaran — ringkasan musim, cadangan database
 * dari halaman CMS, dan tahun kode musim berjalan.
 * Dipakai app/operator/persiapan.php.
 */

/** Tahun awal tahun ajaran dari pengaturan, mis. "2027/2028" -> 2027. */
function persiapan_tahun_musim()
{
    $ta = trim((string) ($GLOBALS['PENGATURAN']['tahun_ajaran'] ?? ''));
    if (preg_match('/(\d{4})/', $ta, $m)) {
        return (int) $m[1];
    }
    return (int) date('Y') + 1;
}

/**
 * Tahun kode nomor pendaftaran untuk musim berjalan. Nomor REG memakai
 * tahun kalender saat mendaftar, jadi musim 2027/2028 umumnya berisi
 * pendaftar berkode 2026.
 */
function persiapan_tahun_kode_musim()
{
    return persiapan_tahun_musim() - 1;
}

/** Cadangan terbaru di storage/backup (db/ maupun root). */
function persiapan_backup_terakhir()
{
    $daftar = array_merge(
        glob(storage_path('backup/db') . '/*.sql.gz') ?: [],
        glob(storage_path('backup') . '/*.sql.gz') ?: []
    );
    if (!$daftar) {
        return null;
    }
    usort($daftar, static function ($a, $b) {
        return filemtime($b) <=> filemtime($a);
    });
    $file = $daftar[0];
    return [
        'file'   => basename($file),
        'waktu'  => (int) filemtime($file),
        'ukuran' => (int) filesize($file),
    ];
}

/** Ringkasan angka untuk kartu musim. */
function persiapan_ringkasan()
{
    $perTahun = pendaftar_arsip_tahun_ringkasan();
    $aktif = 0;
    foreach ($perTahun as $r) {
        $aktif += (int) $r['jumlah'];
    }

    return [
        'per_tahun'      => $perTahun,
        'aktif'          => $aktif,
        'arsip'          => (int) (q_col('SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NOT NULL') ?? 0),
        'menunggu_verif' => (int) (q_col("SELECT COUNT(*) FROM pendaftar
                                           WHERE arsip_at IS NULL AND status_verifikasi = 'MENUNGGU_VERIFIKASI'") ?? 0),
        'menunggu_bayar' => (int) (q_col("SELECT COUNT(*) FROM pembayaran b
                                           JOIN pendaftar p ON p.id = b.pendaftar_id
                                          WHERE p.arsip_at IS NULL AND b.status = 'PENDING'") ?? 0),
        'menunggu_dok'   => (int) (q_col("SELECT COUNT(*) FROM dokumen d
                                           JOIN pendaftar p ON p.id = d.pendaftar_id
                                          WHERE p.arsip_at IS NULL AND d.status_verifikasi = 'PENDING'") ?? 0),
        'kelas_aktif'    => (int) (q_col('SELECT COUNT(*) FROM kelas WHERE is_aktif = 1') ?? 0),
        'kuota'          => (int) (q_col('SELECT COALESCE(SUM(kuota), 0) FROM kelas WHERE is_aktif = 1') ?? 0),
        'terisi'         => (int) (q_col('SELECT COUNT(*) FROM penempatan_kelas') ?? 0),
        'backup'         => persiapan_backup_terakhir(),
    ];
}

/** Rombel aktif untuk formulir gandakan. */
function persiapan_rombel_aktif()
{
    return q_all(
        'SELECT k.id, k.kode, k.nama_kelas, k.wali_kelas, k.kuota,
                (SELECT COUNT(*) FROM penempatan_kelas pk WHERE pk.kelas_id = k.id) AS terisi
           FROM kelas k
          WHERE k.is_aktif = 1
       ORDER BY k.kode'
    ) ?: [];
}

/**
 * Cadangan database dari halaman CMS (PDO murni, tanpa exec/mysqldump).
 * Berkas ditulis ke storage/backup/db/ dengan nama spmb-manual-*.sql.gz.
 *
 * @return array{ok:bool,pesan:string,file?:string,ukuran?:int,tabel?:int}
 */
function persiapan_backup_db()
{
    require_once dirname(__DIR__, 2) . '/tools/backup-db-lib.php';

    $dir = storage_path('backup/db');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['ok' => false, 'pesan' => 'Gagal membuat folder storage/backup/db.'];
    }

    $info = [
        'host' => env_val('DB_HOST') ?: '127.0.0.1',
        'port' => env_val('DB_PORT') ?: '3306',
        'user' => env_val('DB_USER') ?: 'root',
        'pass' => (string) env_val('DB_PASS'),
        'name' => env_val('DB_NAME') ?: 'db_spmb_mualimku',
    ];
    $file = $dir . '/spmb-manual-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.sql.gz';

    try {
        $tabel = backup_dump_pdo($info, $file);
    } catch (Throwable $e) {
        if (is_file($file)) {
            @unlink($file);
        }
        return ['ok' => false, 'pesan' => 'Cadangan gagal: ' . $e->getMessage()];
    }

    if (!is_file($file) || filesize($file) === 0) {
        if (is_file($file)) {
            @unlink($file);
        }
        return ['ok' => false, 'pesan' => 'Cadangan gagal: berkas hasil kosong.'];
    }
    @chmod($file, 0600);

    return [
        'ok'     => true,
        'pesan'  => 'Cadangan database tersimpan: ' . basename($file),
        'file'   => basename($file),
        'ukuran' => (int) filesize($file),
        'tabel'  => (int) $tabel,
    ];
}
