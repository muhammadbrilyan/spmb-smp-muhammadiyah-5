<?php
/**
 * Modul queries: pendaftar "” di-include dari includes/queries.php.
 */


function daftar_pendaftar()
{
    global $PENDAFTAR;

    $baris = q_all(
        'SELECT p.id, p.user_id, p.no_pendaftaran, p.nama_siswa, p.nisn, p.asal_sekolah, p.jalur,
                p.status_verifikasi, p.status_seleksi, p.status_akun, p.kred_sent_at, p.tahap,
                p.created_at, u.username, o.no_whatsapp, o.nama_orang_tua, k.kode AS kelas_kode
           FROM pendaftar p
      LEFT JOIN users u ON u.id = p.user_id
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
      LEFT JOIN penempatan_kelas pk ON pk.pendaftar_id = p.id
      LEFT JOIN kelas k ON k.id = pk.kelas_id
          WHERE p.arsip_at IS NULL
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
            'wa'        => $r['no_whatsapp'] ?: ($r['username'] ? 'Belum ada WA' : '—'),
            'wali'      => $r['nama_orang_tua'] ?: '—',
            'akun'      => $label,
            'akun_tone' => $tone,
            'kred'      => $kred,
            'kred_tone' => $tone,
            'nisn'      => $r['nisn'] ?: '—',
            'tahap'     => $r['tahap'] ?? '',
            'verifikasi'=> $r['status_verifikasi'],
            'seleksi'   => $r['status_seleksi'],
            'kelas'     => $r['kelas_kode'] ?: '',
            'created'   => $r['created_at'],
        ];
    }

    return $keluar; /* kosong = memang belum ada pendaftar (bukan fallback dummy) */
}

/* ==================================================================
 * 2. DASHBOARD OPERATOR — kartu statistik (4 kartu)
 * ================================================================== */

function stat_operator()
{
    /* Nol bila database tidak terbaca — sama seperti stat_bendahara().
     * Angka dummy lama (215/184/...) membuat dashboard operator menampilkan
     * angka palsu yang tidak cocok dengan tabel di bawahnya. */
    $s = [
        'akun_aktif' => 0, 'akun_total' => 0,
        'peserta'    => 0, 'offline'    => 0, 'daring' => 0,
        'dinilai'    => 0, 'lulus'      => 0, 'proses' => 0,
        'terisi'     => 0, 'kuota'      => 0, 'rombel' => 0,
    ];

    if (!db_aktif()) {
        $s['akun_persen']   = 0.0;
        $s['persen_terisi'] = 0.0;
        return $s;
    }

    $dihitung = [
        'akun_total'  => q_col('SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NULL'),
        'akun_aktif'  => q_col("SELECT COUNT(*) FROM pendaftar WHERE status_akun = 'AKTIF' AND arsip_at IS NULL"),
        'peserta'     => q_col("SELECT COALESCE(SUM(peserta_dijadwalkan),0) FROM jadwal_tes WHERE status = 'TERJADWAL'"),
        'offline'     => q_col("SELECT COALESCE(SUM(peserta_dijadwalkan),0) FROM jadwal_tes WHERE status = 'TERJADWAL' AND mode = 'OFFLINE'"),
        'daring'      => q_col("SELECT COALESCE(SUM(peserta_dijadwalkan),0) FROM jadwal_tes WHERE status = 'TERJADWAL' AND mode IN ('DARING','VIDEO_CALL')"),
        /* Data pendaftar terarsip tidak dihitung, agar angkanya sama dengan
         * total pendaftar di dashboard bendahara & rekap export. */
        'dinilai'     => q_col('SELECT COUNT(*) FROM seleksi s JOIN pendaftar p ON p.id = s.pendaftar_id WHERE p.arsip_at IS NULL'),
        'lulus'       => q_col("SELECT COUNT(*) FROM seleksi s JOIN pendaftar p ON p.id = s.pendaftar_id WHERE s.hasil = 'LULUS' AND p.arsip_at IS NULL"),
        'proses'      => q_col("SELECT COUNT(*) FROM seleksi s JOIN pendaftar p ON p.id = s.pendaftar_id WHERE s.hasil = 'PROSES' AND p.arsip_at IS NULL"),
        'terisi'      => q_col('SELECT COUNT(*) FROM penempatan_kelas pk JOIN pendaftar p ON p.id = pk.pendaftar_id WHERE p.arsip_at IS NULL'),
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

/**
 * CORONG PPDB untuk laporan Kepala Sekolah — satu query, enam tahap.
 *
 * Tiap tahap dihitung BEBAS (bukan selisih tahap sebelumnya) karena jalur
 * pendaftar tidak selalu berurutan: misalnya akun bisa aktif sebelum biodata
 * dinyatakan terverifikasi. Karena itu halaman pemanggil menampilkannya
 * sebagai bar "mencapai tahap" per tahap, bukan corong yang menyempit.
 * Semua tahap mengecualikan pendaftar terarsip (arsip_at IS NULL).
 *
 * @return array<string,int>
 */
function corong_ppdb()
{
    $kosong = ['masuk' => 0, 'data' => 0, 'bayar' => 0, 'akun' => 0, 'lulus' => 0, 'ditempat' => 0];
    if (!db_aktif()) {
        return $kosong;
    }

    $d = q_all('SELECT
                  (SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NULL) masuk,
                  (SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NULL
                     AND status_verifikasi = \'TERVERIFIKASI\') data_sah,
                  (SELECT COUNT(DISTINCT p.id) FROM pendaftar p
                     JOIN pembayaran pb ON pb.pendaftar_id = p.id
                    AND pb.jenis_pembayaran = \'UANG_MASUK\'
                    AND pb.status IN (\'VERIFIED\',\'LUNAS\',\'ANGSURAN_1\',\'ANGSURAN_2\')
                   WHERE p.arsip_at IS NULL) bayar,
                  (SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NULL
                     AND status_akun = \'AKTIF\') akun,
                  (SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NULL
                     AND status_seleksi = \'LULUS\') lulus,
                  (SELECT COUNT(DISTINCT p.id) FROM pendaftar p
                     JOIN penempatan_kelas pk ON pk.pendaftar_id = p.id
                   WHERE p.arsip_at IS NULL) ditempatkan');
    if (!$d) {
        return $kosong;
    }
    $r = $d[0];

    return [
        'masuk'    => (int) ($r['masuk'] ?? 0),
        'data'     => (int) ($r['data_sah'] ?? 0),
        'bayar'    => (int) ($r['bayar'] ?? 0),
        'akun'     => (int) ($r['akun'] ?? 0),
        'lulus'    => (int) ($r['lulus'] ?? 0),
        'ditempat' => (int) ($r['ditempatkan'] ?? 0),
    ];
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
                /* Fase 11 A6: token polos tidak lagi disimpan — saat kunci
                 * idempoten diputar ulang, rotasi token & tampilkan yang BARU
                 * (token lama otomatis tidak berlaku). */
                $tokenBaru = pendaftar_rotasi_pay_token($lama['id']);
                $db->query("SELECT RELEASE_LOCK(" . $db->quote($lockName) . ")")->fetchColumn();
                return [
                    'ok' => true, 'reused' => true,
                    'no' => $lama['no_pendaftaran'], 'id' => $lama['id'],
                    'wa' => $waNorm, 'nama' => $lama['nama_siswa'], 'jalur' => $lama['jalur'],
                    'pay_token' => $tokenBaru,
                ];
            }
        }

        /* Maksimal 1 pendaftaran per nomor WA + nama (di bawah kunci).
         * Baris terarsip diabaikan: pendaftar yang diarsip karena salah
         * input / ganda memang boleh mendaftar ulang. */
        $duplikat = $db->prepare(
            'SELECT COUNT(*) FROM pendaftar p JOIN orang_tua o ON o.pendaftar_id = p.id
              WHERE o.no_whatsapp = ? AND p.nama_siswa = ? AND p.arsip_at IS NULL'
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
        $ip            = klien_ip();
        $idPend = 'prd-' . bin2hex(random_bytes(4));
        $idOrt  = 'ort-' . bin2hex(random_bytes(4));
        $idByr  = 'byr-' . bin2hex(random_bytes(4));

        try {
            $db->beginTransaction();
            $db->prepare(
                'INSERT INTO pendaftar (id, no_pendaftaran, nama_siswa, asal_sekolah, jalur,
                        status_verifikasi, status_seleksi, status_akun, tahap,
                        setuju_sk_at, setuju_sk_versi, setuju_sk_ip, pay_token_hash, registration_token)
                 VALUES (?, ?, ?, ?, ?, "MENUNGGU_VERIFIKASI", "PROSES", "BELUM_DIBUAT", "MENUNGGU_PEMBAYARAN",
                        NOW(), ?, ?, ?, ?)'
            )->execute([$idPend, $no, $nama, $asal, $jalur, $syaratVersi, $ip, hash('sha256', $payToken), $regToken]);
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

/**
 * Fase 11 A6: buat pay_token BARU (hanya SHA-256 yang disimpan di DB).
 * Token polos hanya dikembalikan sekali — tidak pernah dibaca dari DB.
 */
function pendaftar_rotasi_pay_token($pendaftar_id)
{
    $db = db();
    if ($db === null) {
        return null;
    }
    $token = bin2hex(random_bytes(32));
    $db->prepare('UPDATE pendaftar SET pay_token_hash = ? WHERE id = ?')
        ->execute([hash('sha256', $token), $pendaftar_id]);
    return $token;
}

/** Cari pendaftar by pay_token (untuk halaman bayar publik) — via hash. */
function pendaftar_by_pay_token($payToken)
{
    $db = db();
    if ($db === null) {
        return null;
    }
    $stmt = $db->prepare(
        'SELECT p.id, p.no_pendaftaran, p.nama_siswa, p.tahap,
                b.id AS bayar_id, b.nominal, b.status AS bayar_status
           FROM pendaftar p
      LEFT JOIN pembayaran b ON b.pendaftar_id = p.id AND b.jenis_pembayaran = "FORMULIR"
          WHERE p.pay_token_hash = SHA2(?, 256)
            AND p.arsip_at IS NULL
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
 * Daftar kelas untuk dropdown penempatan ada di queries/penempatan.php
 * (penempatan_kelas_opsi).
 */

/* ==================================================================
 * 14. VERIFIKASI DATA CALON SISWA (Fase 13)
 * ================================================================== */

/**
 * Daftar pendaftar untuk halaman Data Pendaftar / Verifikasi Data.
 * @param string $status    filter status_verifikasi ('' = semua)
 * @param string $cari      pencarian nama / no. pendaftaran / NISN / WA
 * @param string $id        ambil satu pendaftar saja (dipakai halaman detail)
 * @param string $tampilkan 'aktif' (default, tanpa terarsip) | 'arsip' | 'semua'
 * @return array<int,array>
 */
function pendaftar_data_antrean($status = '', $cari = '', $id = '', $tampilkan = 'aktif')
{
    $sql = "SELECT p.id, p.user_id, p.no_pendaftaran, p.nama_siswa, p.nisn, p.jenis_kelamin,
                   p.tempat_lahir, p.tanggal_lahir, p.alamat, p.asal_sekolah, p.jalur,
                   p.is_luar_daerah, p.kelas_id, p.status_verifikasi, p.status_seleksi,
                   p.status_akun, p.tahap, p.catatan_verifikasi_data, p.data_verified_at,
                   p.data_verified_by, p.arsip_at, p.arsip_alasan, p.arsip_oleh, p.created_at, p.updated_at,
                   u.username, o.nama_orang_tua, o.no_whatsapp, o.pekerjaan,
                   o.penghasilan_bulanan, o.alamat AS alamat_orang_tua,
                   k.kode AS kelas_kode, k.nama_kelas, vu.nama_lengkap AS data_verifier,
                   au.nama_lengkap AS arsip_petugas,
                   (SELECT COUNT(*) FROM dokumen d WHERE d.pendaftar_id = p.id AND d.file_url IS NOT NULL) AS dok_terisi,
                   (SELECT COUNT(*) FROM dokumen d WHERE d.pendaftar_id = p.id AND d.status_verifikasi = 'VERIFIED') AS dok_verifikasi,
                   (SELECT COUNT(*) FROM jenis_dokumen j WHERE j.aktif = 1 AND j.wajib = 1) AS dok_wajib,
                   (SELECT COALESCE(SUM(b.nominal),0) FROM pembayaran b
                     WHERE b.pendaftar_id = p.id
                       AND b.status IN ('VERIFIED','LUNAS','ANGSURAN_1','ANGSURAN_2')) AS bayar_verified,
                   (SELECT COUNT(*) FROM pembayaran b WHERE b.pendaftar_id = p.id AND b.status = 'PENDING') AS bayar_pending,
                   (SELECT COUNT(*) FROM pengajuan_koreksi kx
                     WHERE kx.pendaftar_id = p.id AND kx.status IN ('MENUNGGU','DIPROSES')) AS koreksi_aktif
              FROM pendaftar p
         LEFT JOIN users u ON u.id = p.user_id
         LEFT JOIN users vu ON vu.id = p.data_verified_by
         LEFT JOIN users au ON au.id = p.arsip_oleh
         LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
         LEFT JOIN penempatan_kelas pk ON pk.pendaftar_id = p.id
         LEFT JOIN kelas k ON k.id = pk.kelas_id
             WHERE 1 = 1";
    $params = [];

    $tampilkan = strtolower(trim((string) $tampilkan));
    if ($tampilkan === 'arsip') {
        $sql .= ' AND p.arsip_at IS NOT NULL';
    } elseif ($tampilkan !== 'semua') {
        $sql .= ' AND p.arsip_at IS NULL';
    }

    $status = strtoupper(trim((string) $status));
    if (in_array($status, ['DRAFT', 'MENUNGGU_VERIFIKASI', 'TERVERIFIKASI', 'DITOLAK'], true)) {
        $sql .= ' AND p.status_verifikasi = ?';
        $params[] = $status;
    }

    $id = trim((string) $id);
    if ($id !== '') {
        $sql .= ' AND p.id = ?';
        $params[] = $id;
    }

    $cari = trim((string) $cari);
    if ($cari !== '') {
        $sql .= ' AND (p.nama_siswa LIKE ? OR p.no_pendaftaran LIKE ? OR p.nisn LIKE ? OR o.no_whatsapp LIKE ?)';
        $like = '%' . $cari . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= $tampilkan === 'arsip'
        ? ' ORDER BY p.arsip_at DESC, p.no_pendaftaran'
        : " ORDER BY FIELD(p.status_verifikasi, 'MENUNGGU_VERIFIKASI', 'DRAFT', 'TERVERIFIKASI', 'DITOLAK'),
                      p.updated_at DESC, p.no_pendaftaran";
    return q_all($sql, $params) ?: [];
}

/** Detail satu pendaftar untuk verifikasi data. */
function pendaftar_data_detail($pendaftar_id)
{
    $baris = pendaftar_data_antrean('', '', $pendaftar_id);
    return $baris[0] ?? null;
}

/**
 * Butir data wajib sebelum verifikasi DATA boleh dikunci.
 * Dipakai bersama oleh halaman operator dan validasi server.
 * @return array<string,string> kolom => label
 */
function pendaftar_data_wajib()
{
    return [
        'jenis_kelamin'    => 'Jenis kelamin',
        'tempat_lahir'     => 'Tempat lahir',
        'tanggal_lahir'    => 'Tanggal lahir',
        'alamat'           => 'Alamat siswa',
        'asal_sekolah'     => 'Asal sekolah',
        'nama_orang_tua'   => 'Nama orang tua / wali',
        'no_whatsapp'      => 'Nomor WhatsApp orang tua',
        'alamat_orang_tua' => 'Alamat orang tua / wali',
        'pekerjaan'        => 'Pekerjaan orang tua / wali',
    ];
}

/**
 * Daftar label butir wajib yang masih kosong.
 * @return array<int,string>
 */
function pendaftar_data_kurang(array $detail)
{
    $kurang = [];
    foreach (pendaftar_data_wajib() as $kolom => $label) {
        if (trim((string) ($detail[$kolom] ?? '')) === '') {
            $kurang[] = $label;
        }
    }
    return $kurang;
}

/**
 * Tetapkan status verifikasi data calon siswa.
 * - TERVERIFIKASI : kunci formulir siswa + catatan dibersihkan.
 * - lainnya       : simpan catatan panitia agar siswa tahu yang perlu diperbaiki.
 *
 * @return array{ok:bool,pesan:string}
 */
function verifikasi_data_set($pendaftar_id, $status, $catatan, $verifier_id)
{
    $status = strtoupper(trim((string) $status));
    if (!in_array($status, ['DRAFT', 'MENUNGGU_VERIFIKASI', 'TERVERIFIKASI', 'DITOLAK'], true)) {
        return ['ok' => false, 'pesan' => 'Status verifikasi tidak valid.'];
    }
    $catatan = trim((string) $catatan);
    if ($status === 'DITOLAK' && $catatan === '') {
        return ['ok' => false, 'pesan' => 'Alasan penolakan wajib diisi.'];
    }
    if ($status === 'MENUNGGU_VERIFIKASI' && $catatan === '') {
        return ['ok' => false, 'pesan' => 'Tuliskan bagian yang perlu diperbaiki agar siswa dapat menindaklanjuti.'];
    }

    $arsip = q_col('SELECT IFNULL(arsip_at, "") FROM pendaftar WHERE id = ?', [$pendaftar_id]);
    if ($arsip === null) {
        return ['ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.'];
    }
    if ($arsip !== '') {
        return ['ok' => false, 'pesan' => 'Pendaftar ini sudah diarsipkan — pulihkan dulu dari halaman Data Pendaftar.'];
    }

    if ($status === 'TERVERIFIKASI') {
        /* Verifikasi penuh hanya boleh bila data wajib lengkap — dicek di
         * server, bukan hanya menonaktifkan tombol di tampilan. */
        $detail = pendaftar_data_detail($pendaftar_id);
        $kurang = $detail ? pendaftar_data_kurang($detail) : [];
        if ($kurang) {
            return [
                'ok'    => false,
                'pesan' => 'Data belum lengkap: ' . implode(', ', $kurang) . '. Minta perbaikan terlebih dahulu.',
            ];
        }
        $ok = q_exec(
            "UPDATE pendaftar
                SET status_verifikasi = 'TERVERIFIKASI', catatan_verifikasi_data = NULL,
                    data_verified_at = NOW(), data_verified_by = ?
              WHERE id = ?",
            [$verifier_id ?: null, $pendaftar_id]
        );
    } else {
        $ok = q_exec(
            'UPDATE pendaftar
                SET status_verifikasi = ?, catatan_verifikasi_data = ?,
                    data_verified_at = NULL, data_verified_by = NULL
              WHERE id = ?',
            [$status, $catatan !== '' ? $catatan : null, $pendaftar_id]
        );
    }

    if ($ok) {
        if ($status === 'TERVERIFIKASI') {
            notif_siswa_kirim(
                $pendaftar_id,
                'DATA_TERVERIFIKASI',
                'Data terverifikasi',
                'Data calon siswa Anda telah diverifikasi panitia dan dikunci. '
                    . 'Perubahan setelah ini diajukan melalui menu Data Siswa (pengajuan koreksi).',
                url('app/siswa/data-siswa.php')
            );
        } elseif ($status === 'DITOLAK') {
            notif_siswa_kirim(
                $pendaftar_id,
                'DATA_DITOLAK',
                'Data perlu diperbaiki',
                "Panitia meminta perbaikan data calon siswa.\nCatatan panitia: {$catatan}",
                url('app/siswa/data-siswa.php')
            );
        }
    }

    return $ok
        ? ['ok' => true, 'pesan' => 'Status verifikasi data diperbarui.']
        : ['ok' => false, 'pesan' => 'Gagal memperbarui status verifikasi data.'];
}

/* ==================================================================
 * 15. ARSIP PENDAFTARAN (soft archive)
 * ================================================================== */

/**
 * Arsipkan pendaftar: ganda / salah input / mengundurkan diri.
 * Baris pendaftar, pembayaran, dan berkas TETAP ada (hapus permanen memakai
 * ON DELETE CASCADE ke 7 tabel dan tanpa audit-log, jadi tidak disediakan).
 * Efek: hilang dari antrean kerja & statistik (filter arsip_at IS NULL) dan
 * akun portal siswa ikut dinonaktifkan sementara.
 *
 * @return array{ok:bool,pesan:string}
 */
function pendaftar_arsip($pendaftar_id, $alasan, $oleh = null)
{
    $alasan = trim((string) $alasan);
    if (mb_strlen($alasan) < 5) {
        return ['ok' => false, 'pesan' => 'Alasan pengarsipan wajib diisi (minimal 5 karakter).'];
    }
    $alasan = mb_substr($alasan, 0, 255);

    $baris = q_all(
        'SELECT id, no_pendaftaran, user_id, arsip_at FROM pendaftar WHERE id = ? LIMIT 1',
        [$pendaftar_id]
    );
    $pend = $baris ? $baris[0] : null;
    if (!$pend) {
        return ['ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.'];
    }
    if (!empty($pend['arsip_at'])) {
        return ['ok' => false, 'pesan' => 'Pendaftar ini sudah terarsip.'];
    }

    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }

    try {
        $db->beginTransaction();
        $u = $db->prepare(
            'UPDATE pendaftar SET arsip_at = NOW(), arsip_alasan = ?, arsip_oleh = ?
              WHERE id = ? AND arsip_at IS NULL'
        );
        $u->execute([$alasan, $oleh !== null && $oleh !== '' ? $oleh : null, $pendaftar_id]);
        if ($u->rowCount() === 0) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Gagal mengarsipkan — pendaftar sudah terarsip.'];
        }
        if (!empty($pend['user_id'])) {
            $db->prepare('UPDATE users SET is_active = 0 WHERE id = ?')
               ->execute([$pend['user_id']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal mengarsipkan pendaftar.'];
    }

    return ['ok' => true, 'pesan' => 'Pendaftaran ' . $pend['no_pendaftaran'] . ' diarsipkan.'];
}

/**
 * Pulihkan pendaftar terarsip: kembali ke antrean kerja dan akun portal
 * siswa diaktifkan lagi.
 *
 * @return array{ok:bool,pesan:string}
 */
function pendaftar_pulih($pendaftar_id)
{
    $baris = q_all(
        'SELECT id, no_pendaftaran, user_id, arsip_at FROM pendaftar WHERE id = ? LIMIT 1',
        [$pendaftar_id]
    );
    $pend = $baris ? $baris[0] : null;
    if (!$pend) {
        return ['ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.'];
    }
    if (empty($pend['arsip_at'])) {
        return ['ok' => false, 'pesan' => 'Pendaftar ini tidak sedang terarsip.'];
    }

    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }

    try {
        $db->beginTransaction();
        $u = $db->prepare(
            'UPDATE pendaftar SET arsip_at = NULL, arsip_alasan = NULL, arsip_oleh = NULL
              WHERE id = ? AND arsip_at IS NOT NULL'
        );
        $u->execute([$pendaftar_id]);
        if ($u->rowCount() === 0) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Gagal memulihkan pendaftar.'];
        }
        if (!empty($pend['user_id'])) {
            $db->prepare('UPDATE users SET is_active = 1 WHERE id = ?')
               ->execute([$pend['user_id']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal memulihkan pendaftar.'];
    }

    return ['ok' => true, 'pesan' => 'Pendaftaran ' . $pend['no_pendaftaran'] . ' dipulihkan ke antrean aktif.'];
}

