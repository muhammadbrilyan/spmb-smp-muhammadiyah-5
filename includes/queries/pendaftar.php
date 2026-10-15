<?php
/**
 * Modul queries: pendaftar "” di-include dari includes/queries.php.
 */


function daftar_pendaftar()
{
    global $PENDAFTAR;

    $baris = q_all(
        'SELECT p.id, p.user_id, p.no_pendaftaran, p.nama_siswa, p.nisn, p.asal_sekolah, p.jalur,
                p.status_verifikasi, p.status_seleksi, p.status_akun, p.kred_sent_at,
                p.created_at, u.username, o.no_whatsapp, o.nama_orang_tua, k.kode AS kelas_kode
           FROM pendaftar p
      LEFT JOIN users u ON u.id = p.user_id
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
      LEFT JOIN kelas k ON k.id = p.kelas_id
       ORDER BY p.created_at, p.no_pendaftaran'
    );
    if ($baris === null) {
        return $PENDAFTAR;
    }

    $keluar = [];
    foreach ($baris as $r) {
        $akun  = $r['status_akun'] ?: 'BELUM_DIBUAT';
        $label = ['AKTIF' => 'Aktif', 'GAGAL' => 'Gagal'][$akun] ?? 'Belum Dibuat';
        $tone  = ['AKTIF' => 'green', 'GAGAL' => 'red'][$akun] ?? 'amber';

        if ($akun === 'AKTIF' && $r['kred_sent_at']) {
            $kred = 'Terkirim (' . date('H:i', strtotime($r['kred_sent_at'])) . ' WIB)';
        } elseif ($akun === 'GAGAL') {
            $kred = 'Nomor Tidak Aktif';
        } else {
            $kred = 'Menunggu Operator';
        }

        $keluar[] = [
            'id'        => $r['id'],
            'user_id'   => $r['user_id'],
            'reg'       => $r['no_pendaftaran'],
            'nama'      => $r['nama_siswa'],
            'sekolah'   => $r['asal_sekolah'],
            'asal'      => $r['asal_sekolah'],
            'jalur'     => $r['jalur'] ?: 'Reguler',
            'wa'        => $r['no_whatsapp'] ?: ($r['username'] ? 'Belum ada WA' : '"”'),
            'wali'      => $r['nama_orang_tua'] ?: '"”',
            'akun'      => $label,
            'akun_tone' => $tone,
            'kred'      => $kred,
            'kred_tone' => $tone,
            'nisn'      => $r['nisn'] ?: '"”',
            'verifikasi'=> $r['status_verifikasi'],
            'seleksi'   => $r['status_seleksi'],
            'kelas'     => $r['kelas_kode'] ?: '',
            'created'   => $r['created_at'],
        ];
    }

    return $keluar ?: $PENDAFTAR;
}

/* ==================================================================
 * 2. DASHBOARD OPERATOR — kartu statistik (4 kartu)
 * ================================================================== */

function stat_operator()
{
    /* Angka dummy lama dipertahankan bila database tidak terbaca. */
    $s = [
        'akun_aktif'  => 215, 'akun_total' => 215,
        'peserta'     => 184, 'offline'    => 142, 'daring' => 42,
        'dinilai'     => 146, 'lulus'      => 128, 'proses' => 18,
        'terisi'      => 43,  'kuota'      => 90,  'rombel' => 3,
    ];

    $dihitung = [
        'akun_total'  => q_col('SELECT COUNT(*) FROM pendaftar'),
        'akun_aktif'  => q_col("SELECT COUNT(*) FROM pendaftar WHERE status_akun = 'AKTIF'"),
        'peserta'     => q_col("SELECT COALESCE(SUM(peserta_dijadwalkan),0) FROM jadwal_tes WHERE status = 'TERJADWAL'"),
        'offline'     => q_col("SELECT COALESCE(SUM(peserta_dijadwalkan),0) FROM jadwal_tes WHERE status = 'TERJADWAL' AND mode = 'OFFLINE'"),
        'daring'      => q_col("SELECT COALESCE(SUM(peserta_dijadwalkan),0) FROM jadwal_tes WHERE status = 'TERJADWAL' AND mode IN ('DARING','VIDEO_CALL')"),
        'dinilai'     => q_col('SELECT COUNT(*) FROM seleksi'),
        'lulus'       => q_col("SELECT COUNT(*) FROM seleksi WHERE hasil = 'LULUS'"),
        'proses'      => q_col("SELECT COUNT(*) FROM seleksi WHERE hasil = 'PROSES'"),
        'terisi'      => q_col('SELECT COUNT(*) FROM penempatan_kelas'),
        'kuota'       => q_col('SELECT COALESCE(SUM(kuota),0) FROM kelas WHERE is_aktif = 1'),
        'rombel'      => q_col('SELECT COUNT(*) FROM kelas WHERE is_aktif = 1'),
    ];
    foreach ($dihitung as $kunci => $nilai) {
        if ($nilai !== null) {
            $s[$kunci] = (int) $nilai;
        }
    }

    $s['akun_persen']    = $s['akun_total'] > 0 ? round($s['akun_aktif'] / $s['akun_total'] * 100, 1) : 0.0;
    $s['persen_terisi']  = $s['kuota'] > 0 ? round($s['terisi'] / $s['kuota'] * 100, 1) : 0.0;

    return $s;
}

/* ==================================================================
 * 3. DASHBOARD OPERATOR "” tab 2: jadwal tes
 * ================================================================== */


function registrasi_baru($nama, $wa, $jalur, $asal_sekolah, $regToken = null, $setujuSk = null)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Sistem sedang sibuk. Silakan coba lagi beberapa saat.'];
    }

    $nama = trim((string) $nama);
    $asal = trim((string) $asal_sekolah);
    $waD  = preg_replace('/\D+/', '', (string) $wa);
    if (strpos($waD, '0') === 0) {
        $waD = '62' . substr($waD, 1);
    }
    $regToken = idem_key_valid($regToken);
    $waNorm   = '+' . $waD;

    /* Server wajib memastikan S&K disetujui. */
    if (empty($setujuSk)) {
        return ['ok' => false, 'pesan' => 'Anda harus menyetujui Syarat & Ketentuan terlebih dahulu.'];
    }

    /* Kunci distribusi MySQL — serialisasi registrasi dengan WA+nama yang sama
     * agar race (dua request paralel) tidak membuat dua pendaftar. */
    $lockName = 'spmb_reg_' . substr(hash('sha256', $waNorm . '|' . $nama), 0, 40);
    $db->query("SELECT GET_LOCK(" . $db->quote($lockName) . ", 5)")->fetchColumn();

    try {
        /* Idempotency: token yang sama (retry / double-submit) → hasil lama. */
        if ($regToken !== null) {
            $cekTok = $db->prepare(
                'SELECT id, no_pendaftaran, nama_siswa, jalur FROM pendaftar
                  WHERE registration_token = ? LIMIT 1'
            );
            $cekTok->execute([$regToken]);
            $lama = $cekTok->fetch();
            if ($lama) {
                $db->query("SELECT RELEASE_LOCK(" . $db->quote($lockName) . ")")->fetchColumn();
                return [
                    'ok' => true, 'reused' => true,
                    'no' => $lama['no_pendaftaran'], 'id' => $lama['id'],
                    'wa' => $waNorm, 'nama' => $lama['nama_siswa'], 'jalur' => $lama['jalur'],
                    'pay_token' => pendaftar_pay_token($lama['id']),
                ];
            }
        }

        /* Maksimal 1 pendaftaran per nomor WA + nama (di bawah kunci). */
        $duplikat = $db->prepare(
            'SELECT COUNT(*) FROM pendaftar p JOIN orang_tua o ON o.pendaftar_id = p.id
              WHERE o.no_whatsapp = ? AND p.nama_siswa = ?'
        );
        $duplikat->execute([$waNorm, $nama]);
        if ((int) $duplikat->fetchColumn() > 0) {
            $db->query("SELECT RELEASE_LOCK(" . $db->quote($lockName) . ")")->fetchColumn();
            return ['ok' => false, 'pesan' => 'Nomor WA + nama ini sudah pernah mendaftar. Gunakan menu cek status / hubungi panitia bila perlu bantuan.'];
        }

        /* Nomor pendaftaran: REG-YYYY-NNNN (serial di bawah kunci). */
        $tahun = date('Y');
        $maks = $db->prepare('SELECT MAX(CAST(SUBSTRING(no_pendaftaran, 10) AS UNSIGNED)) FROM pendaftar WHERE no_pendaftaran LIKE ?');
        $maks->execute(['REG-' . $tahun . '-%']);
        $urut = (int) $maks->fetchColumn() + 1;
        $no   = 'REG-' . $tahun . '-' . str_pad((string) $urut, 4, '0', STR_PAD_LEFT);

        $biayaFormulir = (float) ($GLOBALS['PENGATURAN']['biaya_formulir'] ?? 200000);
        $syaratVersi   = (int) ($GLOBALS['PENGATURAN']['syarat_versi'] ?? 1);
        $payToken      = bin2hex(random_bytes(32));
        $ip            = $_SERVER['REMOTE_ADDR'] ?? '';
        $idPend = 'prd-' . bin2hex(random_bytes(4));
        $idOrt  = 'ort-' . bin2hex(random_bytes(4));
        $idByr  = 'byr-' . bin2hex(random_bytes(4));

        try {
            $db->beginTransaction();
            $db->prepare(
                'INSERT INTO pendaftar (id, no_pendaftaran, nama_siswa, asal_sekolah, jalur,
                        status_verifikasi, status_seleksi, status_akun, tahap,
                        setuju_sk_at, setuju_sk_versi, setuju_sk_ip, pay_token, registration_token)
                 VALUES (?, ?, ?, ?, ?, "MENUNGGU_VERIFIKASI", "PROSES", "BELUM_DIBUAT", "MENUNGGU_PEMBAYARAN",
                        NOW(), ?, ?, ?, ?)'
            )->execute([$idPend, $no, $nama, $asal, $jalur, $syaratVersi, $ip, $payToken, $regToken]);
            $db->prepare(
                'INSERT INTO orang_tua (id, pendaftar_id, nama_orang_tua, no_whatsapp) VALUES (?, ?, ?, ?)'
            )->execute([$idOrt, $idPend, 'Orang Tua ' . $nama, $waNorm]);
            $db->prepare(
                'INSERT INTO pembayaran (id, pendaftar_id, jenis_pembayaran, nominal, status, keterangan)
                 VALUES (?, ?, "FORMULIR", ?, "PENDING", "Menunggu pembayaran")'
            )->execute([$idByr, $idPend, $biayaFormulir]);
            $db->commit();
        } catch (Throwable $e) {
            if (method_exists($db, 'rollBack')) {
                try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
            }
            $db->query("SELECT RELEASE_LOCK(" . $db->quote($lockName) . ")")->fetchColumn();
            return ['ok' => false, 'pesan' => 'Pendaftaran gagal disimpan. Coba lagi atau hubungi panitia.'];
        }

        $db->query("SELECT RELEASE_LOCK(" . $db->quote($lockName) . ")")->fetchColumn();
        return [
            'ok' => true, 'no' => $no, 'id' => $idPend,
            'wa' => $waNorm, 'nama' => $nama, 'jalur' => $jalur,
            'pay_token' => $payToken,
        ];
    } catch (Throwable $e) {
        try { $db->exec("SELECT RELEASE_LOCK(" . $db->quote($lockName) . ")"); } catch (Throwable $x) { /* abaikan */ }
        return ['ok' => false, 'pesan' => 'Sistem sedang sibuk. Silakan coba lagi beberapa saat.'];
    }
}

function pendaftar_pay_token($pendaftar_id)
{
    $db = db();
    if ($db === null) {
        return null;
    }
    $stmt = $db->prepare('SELECT pay_token FROM pendaftar WHERE id = ?');
    $stmt->execute([$pendaftar_id]);
    $token = $stmt->fetchColumn();
    if ($token) {
        return $token;
    }
    $token = bin2hex(random_bytes(32));
    $db->prepare('UPDATE pendaftar SET pay_token = ? WHERE id = ?')->execute([$token, $pendaftar_id]);
    return $token;
}

/** Cari pendaftar by pay_token (untuk halaman bayar publik). */
function pendaftar_by_pay_token($payToken)
{
    $db = db();
    if ($db === null) {
        return null;
    }
    $stmt = $db->prepare(
        'SELECT p.id, p.no_pendaftaran, p.nama_siswa, p.tahap, p.pay_token,
                b.id AS bayar_id, b.nominal, b.status AS bayar_status
           FROM pendaftar p
      LEFT JOIN pembayaran b ON b.pendaftar_id = p.id AND b.jenis_pembayaran = "FORMULIR"
          WHERE p.pay_token = ?
       LIMIT 1'
    );
    $stmt->execute([$payToken]);
    return $stmt->fetch() ?: null;
}

/* ==================================================================
 * 8. MANAJEMEN KELAS & KUOTA
 * ================================================================== */

/** Daftar kelas lengkap (aktif + nonaktif) + jumlah pendaftar tertempat. */

function pendaftar_manual(array $d)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }

    $tahun = date('Y');
    $maks  = q_col('SELECT MAX(CAST(SUBSTRING(no_pendaftaran, 10) AS UNSIGNED)) FROM pendaftar WHERE no_pendaftaran LIKE ?', ['REG-' . $tahun . '-%']);
    $no    = 'REG-' . $tahun . '-' . str_pad((string) ((int) ($maks ?? 0) + 1), 4, '0', STR_PAD_LEFT);

    $idPend = 'prd-' . bin2hex(random_bytes(4));
    $idOrt  = 'ort-' . bin2hex(random_bytes(4));
    $biaya  = (float) ($GLOBALS['PENGATURAN']['biaya_formulir'] ?? 200000);

    try {
        $db->beginTransaction();
        $db->prepare(
            'INSERT INTO pendaftar (id, no_pendaftaran, nama_siswa, nisn, jenis_kelamin, tempat_lahir, tanggal_lahir,
                    alamat, asal_sekolah, jalur, is_luar_daerah, status_verifikasi, status_seleksi, status_akun, tahap)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "PROSES", "BELUM_DIBUAT", "MENUNGGU_PEMBAYARAN")'
        )->execute([
            $idPend, $no, $d['nama'], $d['nisn'] ?: null, $d['jk'],
            $d['ttl'] ?: null, $d['tgl_lahir'] ?: null,
            $d['alamat'], $d['asal'], $d['jalur'], $d['luar'] ? 1 : 0,
            $d['verifikasi_manual'] ? 'TERVERIFIKASI' : 'MENUNGGU_VERIFIKASI',
        ]);
        $db->prepare(
            'INSERT INTO orang_tua (id, pendaftar_id, nama_orang_tua, no_whatsapp, alamat, pekerjaan)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$idOrt, $idPend, $d['nama_ot'], $d['wa'], $d['alamat_ot'] ?: null, $d['pekerjaan_ot'] ?: null]);
        $db->prepare(
            'INSERT INTO pembayaran (id, pendaftar_id, jenis_pembayaran, nominal, status, keterangan)
             VALUES (?, ?, "FORMULIR", ?, "PENDING", "Pendaftaran manual operator")'
        )->execute(['byr-' . bin2hex(random_bytes(4)), $idPend, $biaya]);
        $db->commit();
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal menyimpan pendaftar.'];
    }

    return ['ok' => true, 'no' => $no, 'id' => $idPend];
}

/* ==================================================================
 * 17. PENEMPATAN OTOMATIS MASSAL
 * ================================================================== */

/**
 * Tempatkan semua siswa LULUS belum ditempatkan secara otomatis (greedy per nilai)
 * ke kelas aktif berdasarkan sisa kuota. return array{ok:int, gagal:int, detail:array}.
 */

function pendaftar_detail_akun($pendaftar_id)
{
    $baris = q_all(
        'SELECT p.id, p.user_id, p.no_pendaftaran, p.nama_siswa, p.status_akun, p.status_verifikasi,
                u.username, o.no_whatsapp, o.nama_orang_tua
           FROM pendaftar p
      LEFT JOIN users u ON u.id = p.user_id
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.id = ?
       LIMIT 1',
        [$pendaftar_id]
    );
    return ($baris !== null && $baris) ? $baris[0] : null;
}

/** Username unik dari nomor pendaftaran: siswa + 4 digit terakhir. */

function pendaftar_untuk_nilai()
{
    /* Fase 7: hanya pendaftar siap dinilai — sudah terjadwal ujian
     * (JADWAL_DITETAPKAN) atau sudah pernah dinilai (NILAI_TERINPUT). */
    $baris = q_all(
        "SELECT p.id, p.no_pendaftaran, p.nama_siswa, p.jalur, p.status_verifikasi, p.tahap,
                s.nilai_cbt, s.nilai_btq, s.nilai_wawancara, s.nilai_akhir, s.hasil, s.jadwal_id
           FROM pendaftar p
      LEFT JOIN seleksi s ON s.pendaftar_id = p.id
          WHERE p.tahap IN ('JADWAL_DITETAPKAN', 'NILAI_TERINPUT')
       ORDER BY p.no_pendaftaran"
    );
    return $baris ?: [];
}

/** Jumlah siswa LULUS yang belum ditempatkan kelas (peringatan). */
function lulus_belum_ditempatkan_jumlah()
{
    return (int) (q_col(
        "SELECT COUNT(*) FROM pendaftar p
          WHERE p.status_seleksi = 'LULUS'
            AND NOT EXISTS (SELECT 1 FROM penempatan_kelas pk WHERE pk.pendaftar_id = p.id)"
    ) ?? 0);
}

/* ==================================================================
 * 13. PENEMPATAN KELAS
 * ================================================================== */

/**
 * Daftar kelas untuk dropdown penempatan: id, kode, nama, wali, kuota, terisi.
 */
