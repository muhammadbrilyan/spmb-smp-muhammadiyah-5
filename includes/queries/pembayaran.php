<?php
/**
 * Modul queries: pembayaran "” di-include dari includes/queries.php.
 */


function daftar_pembayaran()
{
    if (!db_aktif()) {
        return [];
    }

    $baris = q_all(
        "SELECT b.id, b.jenis_pembayaran, b.nominal, b.status, b.keterangan,
                b.bukti_transfer_url, b.verified_at, b.created_at,
                p.id AS pendaftar_id, p.user_id, p.tahap,
                p.no_pendaftaran, p.nama_siswa, p.nisn, p.asal_sekolah,
                o.no_whatsapp, o.nama_orang_tua,
                v.nama_lengkap AS verifier
           FROM pembayaran b
           JOIN pendaftar p ON p.id = b.pendaftar_id
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
      LEFT JOIN users v ON v.id = b.verified_by
       ORDER BY FIELD(b.status,'PENDING','REJECTED','VERIFIED','LUNAS','ANGSURAN_1','ANGSURAN_2'), b.created_at DESC"
    );
    if ($baris === null) {
        return [];
    }

    $labelJenis = ['FORMULIR' => 'Biaya Formulir', 'UANG_MASUK' => 'Uang Masuk'];
    $labelStat  = [
        'PENDING'    => 'Menunggu',
        'VERIFIED'   => 'Terverifikasi',
        'REJECTED'   => 'Ditolak',
        'LUNAS'      => 'Lunas',
        'ANGSURAN_1' => 'Angsuran 1',
        'ANGSURAN_2' => 'Angsuran 2',
    ];
    $toneStat = [
        'PENDING'    => 'amber',
        'VERIFIED'   => 'green',
        'REJECTED'   => 'red',
        'LUNAS'      => 'green',
        'ANGSURAN_1' => 'blue',
        'ANGSURAN_2' => 'blue',
    ];

    $keluar = [];
    foreach ($baris as $r) {
        $keluar[] = [
            'id'        => $r['id'],
            'pendaftar_id' => $r['pendaftar_id'],
            'user_id'   => $r['user_id'],
            'tahap'     => $r['tahap'],
            'reg'       => $r['no_pendaftaran'],
            'waktu'     => $r['created_at'],
            'nama'      => $r['nama_siswa'],
            'sekolah'   => $r['asal_sekolah'],
            'nisn'      => $r['nisn'] ?: '"”',
            'wa'        => $r['no_whatsapp'] ?: '"”',
            'wali'      => $r['nama_orang_tua'] ?: '"”',
            'jenis'     => $labelJenis[$r['jenis_pembayaran']] ?? $r['jenis_pembayaran'],
            'nominal'   => (float) $r['nominal'],
            'status'    => $labelStat[$r['status']] ?? $r['status'],
            'status_raw'=> $r['status'],
            'tone'      => $toneStat[$r['status']] ?? 'gray',
            'bukti'     => $r['bukti_transfer_url'],
            'keterangan'=> $r['keterangan'],
            'verifier'  => $r['verifier'],
            'verified'  => $r['verified_at'],
            'pending'   => $r['status'] === 'PENDING',
        ];
    }

    return $keluar;
}

/* ==================================================================
 * 6. DASHBOARD BENDAHARA — kartu statistik
 * ================================================================== */

function stat_bendahara()
{
    $kosong = [
        'dana_masuk' => 0, 'jml_masuk' => 0,
        'menunggu'   => 0, 'rp_menunggu' => 0,
        'ditolak'    => 0, 'rp_ditolak' => 0,
        'total_bayar'=> 0, 'total_pendaftar' => 0,
        'persen'     => 0.0,
    ];
    if (!db_aktif()) {
        return $kosong;
    }

    $s = [
        'dana_masuk' => (float) (q_col("SELECT COALESCE(SUM(nominal),0) FROM pembayaran WHERE status IN ('VERIFIED','LUNAS','ANGSURAN_1','ANGSURAN_2')") ?? 0),
        'jml_masuk'  => (int) (q_col("SELECT COUNT(*) FROM pembayaran WHERE status IN ('VERIFIED','LUNAS','ANGSURAN_1','ANGSURAN_2')") ?? 0),
        'menunggu'   => (int) (q_col("SELECT COUNT(*) FROM pembayaran WHERE status = 'PENDING'") ?? 0),
        'rp_menunggu'=> (float) (q_col("SELECT COALESCE(SUM(nominal),0) FROM pembayaran WHERE status = 'PENDING'") ?? 0),
        'ditolak'    => (int) (q_col("SELECT COUNT(*) FROM pembayaran WHERE status = 'REJECTED'") ?? 0),
        'rp_ditolak' => (float) (q_col("SELECT COALESCE(SUM(nominal),0) FROM pembayaran WHERE status = 'REJECTED'") ?? 0),
        'total_bayar'=> (int) (q_col('SELECT COUNT(*) FROM pembayaran') ?? 0),
        'total_pendaftar' => (int) (q_col('SELECT COUNT(*) FROM pendaftar') ?? 0),
    ];

    $s['persen'] = $s['total_bayar'] > 0
        ? round(($s['total_bayar'] - $s['menunggu']) / $s['total_bayar'] * 100, 1)
        : 0.0;

    return $s;
}

/* ==================================================================
 * 7. HALAMAN SISWA "” profil berdasarkan user login
 * ================================================================== */

/**
 * Data pendaftar milik user login (users.id -> pendaftar.user_id).
 * @return array|null  null = tidak ada baris / gagal
 */

function pembayaran_siswa($pendaftar_id)
{
    if (!db_aktif() || !$pendaftar_id) {
        return [];
    }
    return q_all(
        'SELECT jenis_pembayaran, nominal, status, keterangan, verified_at, created_at
           FROM pembayaran
          WHERE pendaftar_id = ?
       ORDER BY created_at DESC',
        [$pendaftar_id]
    ) ?: [];
}

/**
 * Hasil seleksi milik satu pendaftar (termasuk jadwal terkait).
 */

/**
 * Unggah bukti transfer manual — idempoten via idempotency_key.
 * Key yang sama (retry / double-click) tidak membuat baris atau berkas baru.
 * @return array{ok:bool, id?:string, pesan?:string}
 */
function pembayaran_unggah_bukti($pendaftar_id, $jenis, $nominal, $bukti_url, $idemKey = null)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    $idemKey = idem_key_valid($idemKey);

    try {
        $db->beginTransaction();

        /* Kunci baris pendaftar — serialize request paralel untuk jenis yang sama. */
        $stmt = $db->prepare('SELECT id FROM pendaftar WHERE id = ? FOR UPDATE');
        $stmt->execute([$pendaftar_id]);
        $stmt->fetch();

        /* Retry dengan key yang sama → tidak ada efek baru. */
        if ($idemKey !== null) {
            $cek = $db->prepare('SELECT id FROM pembayaran WHERE idempotency_key = ? LIMIT 1');
            $cek->execute([$idemKey]);
            $dup = $cek->fetch();
            if ($dup) {
                $db->commit();
                return ['ok' => true, 'id' => $dup['id'], 'reused' => true];
            }
        }

        /* Baris PENDING/REJECTED untuk jenis ini → update in place. */
        $ada = $db->prepare(
            "SELECT id FROM pembayaran
              WHERE pendaftar_id = ? AND jenis_pembayaran = ? AND status IN ('PENDING','REJECTED')
              ORDER BY created_at DESC LIMIT 1 FOR UPDATE"
        );
        $ada->execute([$pendaftar_id, $jenis]);
        $baris = $ada->fetch();

        if ($baris) {
            $upd = $db->prepare(
                'UPDATE pembayaran
                    SET bukti_transfer_url = ?, nominal = ?, status = "PENDING",
                        keterangan = "Menunggu verifikasi bukti", idempotency_key = ?
                  WHERE id = ?'
            );
            $upd->execute([$bukti_url, (float) $nominal, $idemKey, $baris['id']]);
            $db->commit();
            return ['ok' => true, 'id' => $baris['id'], 'reused' => false];
        }

        $id = 'byr-' . bin2hex(random_bytes(4));
        $ins = $db->prepare(
            'INSERT INTO pembayaran (id, pendaftar_id, jenis_pembayaran, nominal, bukti_transfer_url, status, keterangan, idempotency_key)
             VALUES (?, ?, ?, ?, ?, "PENDING", "Menunggu verifikasi bukti", ?)'
        );
        $ins->execute([$id, $pendaftar_id, $jenis, (float) $nominal, $bukti_url, $idemKey]);
        $db->commit();
        return ['ok' => true, 'id' => $id, 'reused' => false];
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal menyimpan bukti transfer.'];
    }
}

/**
 * Cari pembayaran berdasarkan idempotency key (retry / double-click).
 * @return array|null  baris ['id','order_id','status','snap_token','snap_expires_at'] atau null
 */
function pembayaran_cari_idem($idemKey)
{
    $idemKey = idem_key_valid($idemKey);
    if ($idemKey === null || !db_aktif()) {
        return null;
    }
    $baris = q_all(
        'SELECT id, order_id, status, snap_token, snap_expires_at
           FROM pembayaran WHERE idempotency_key = ? LIMIT 1',
        [$idemKey]
    );
    return ($baris !== null && $baris) ? $baris[0] : null;
}

/**
 * Simpan Snap token hasil Midtrans ke baris pembayaran (cache agar retry tidak membuat transaksi baru).
 */
function pembayaran_simpan_snap($byrId, $order_id, $snapToken, $expiresAt)
{
    return q_exec(
        'UPDATE pembayaran SET order_id = ?, snap_token = ?, snap_expires_at = ? WHERE id = ?',
        [$order_id, $snapToken, $expiresAt, $byrId]
    );
}

/**
 * Siapkan / buat baris pembayaran untuk transaksi online (Midtrans).
 * Idempoten: key yang sama tidak membuat baris/order_id baru.
 * @return array{ok:bool, id?:string, order_id?:string, snap_token?:string, snap_expires_at?:string, reused?:bool, pesan?:string}
 */
function pembayaran_siapkan_online($pendaftar_id, $jenis, $nominal, $order_id, $idemKey = null, $snapToken = null, $expiresAt = null)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    $idemKey = idem_key_valid($idemKey);
    if ($idemKey === null) {
        return ['ok' => false, 'pesan' => 'Idempotency key tidak valid.'];
    }

    try {
        $db->beginTransaction();

        /* Kunci baris pendaftar agar request paralel untuk jenis yang sama
         * saling menunggu (serialize) — mencegah dua baris PENDING ganda. */
        $stmt = $db->prepare('SELECT id FROM pendaftar WHERE id = ? FOR UPDATE');
        $stmt->execute([$pendaftar_id]);
        $stmt->fetch();

        /* Cek dulu: key ini sudah pernah diproses? → kembalikan hasil lama. */
        $cek = $db->prepare(
            'SELECT id, order_id, status, snap_token, snap_expires_at
               FROM pembayaran WHERE idempotency_key = ? LIMIT 1'
        );
        $cek->execute([$idemKey]);
        $lama = $cek->fetch();
        if ($lama) {
            $db->commit();
            return [
                'ok' => true, 'reused' => true,
                'id' => $lama['id'], 'order_id' => $lama['order_id'],
                'snap_token' => $lama['snap_token'], 'snap_expires_at' => $lama['snap_expires_at'],
            ];
        }

        /* Baris PENDING/REJECTED untuk jenis ini → pakai ulang (jangan ganti order_id). */
        $ada = $db->prepare(
            "SELECT id, order_id, snap_token, snap_expires_at, status
               FROM pembayaran
              WHERE pendaftar_id = ? AND jenis_pembayaran = ? AND status IN ('PENDING','REJECTED')
              ORDER BY created_at DESC LIMIT 1 FOR UPDATE"
        );
        $ada->execute([$pendaftar_id, $jenis]);
        $baris = $ada->fetch();

        if ($baris) {
            $byrId = $baris['id'];
            /* Order_id lama masih dipakai — jalu timpa agar transaksi Midtrans aktif tidak jadi yatim. */
            $pakaiOrder = $baris['order_id'] ?: $order_id;
            $upd = $db->prepare(
                'UPDATE pembayaran
                    SET nominal = ?, status = "PENDING",
                        keterangan = "Menunggu pembayaran online",
                        midtrans_status = "pending",
                        order_id = ?,
                        idempotency_key = ?
                  WHERE id = ?'
            );
            $upd->execute([(float) $nominal, $pakaiOrder, $idemKey, $byrId]);
            $db->commit();
            return [
                'ok' => true, 'reused' => true,
                'id' => $byrId, 'order_id' => $pakaiOrder,
                'snap_token' => $baris['snap_token'], 'snap_expires_at' => $baris['snap_expires_at'],
            ];
        }

        /* Baris baru. */
        $byrId = 'byr-' . bin2hex(random_bytes(4));
        $ins = $db->prepare(
            'INSERT INTO pembayaran (id, pendaftar_id, jenis_pembayaran, nominal, status, keterangan, order_id, midtrans_status, idempotency_key, snap_token, snap_expires_at)
             VALUES (?, ?, ?, ?, "PENDING", "Menunggu pembayaran online", ?, "pending", ?, ?, ?)'
        );
        $ins->execute([$byrId, $pendaftar_id, $jenis, (float) $nominal, $order_id, $idemKey, $snapToken, $expiresAt]);
        $db->commit();
        return ['ok' => true, 'reused' => false, 'id' => $byrId, 'order_id' => $order_id, 'snap_token' => $snapToken, 'snap_expires_at' => $expiresAt];
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        /* Unique key bentrok (race) → ambil baris yang sudah ada. */
        $lagi = pembayaran_cari_idem($idemKey);
        if ($lagi) {
            return [
                'ok' => true, 'reused' => true,
                'id' => $lagi['id'], 'order_id' => $lagi['order_id'],
                'snap_token' => $lagi['snap_token'], 'snap_expires_at' => $lagi['snap_expires_at'],
            ];
        }
        return ['ok' => false, 'pesan' => 'Gagal menyiapkan data pembayaran.'];
    }
}

/**
 * Detail pembayaran terbaru per jenis untuk satu pendaftar (untuk portal siswa).
 * @return array<int, array> daftar ['jenis','nominal','status','order_id','midtrans_status','payment_method','paid_at','keterangan']
 */

function pembayaran_siswa_detail($pendaftar_id)
{
    return q_all(
        "SELECT jenis_pembayaran, nominal, status, order_id, midtrans_status, payment_method, paid_at, keterangan
           FROM pembayaran
          WHERE pendaftar_id = ?
       ORDER BY created_at DESC",
        [$pendaftar_id]
    ) ?: [];
}

/* ==================================================================
 * FASE 4 — VERIFIKASI PEMBAYARAN & KIRIM AKUN
 * ================================================================== */

/**
 * Setujui pembayaran: status = VERIFIED + tahap pendaftar = BAYAR_TERVERIFIKASI.
 * Idempoten & aman klik ganda (FOR UPDATE + cek status).
 *
 * @param string $pembayaranId id baris pembayaran
 * @param string|null $verifierId users.id petugas
 * @return array{ok:bool, sudah?:bool, pesan?:string, pendaftar_id?:string,
 *               reg?:string, nama?:string, wa?:string, pay_token?:string}
 */
function verifikasi_bayar_setujui($pembayaranId, $verifierId = null)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    try {
        $db->beginTransaction();
        $cek = $db->prepare(
            'SELECT b.id, b.status, b.nominal, b.jenis_pembayaran,
                    p.id AS pendaftar_id, p.no_pendaftaran, p.nama_siswa, p.tahap,
                    o.no_whatsapp
               FROM pembayaran b
               JOIN pendaftar p ON p.id = b.pendaftar_id
          LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
              WHERE b.id = ? FOR UPDATE'
        );
        $cek->execute([$pembayaranId]);
        $baris = $cek->fetch();
        if (!$baris) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Data pembayaran tidak ditemukan.'];
        }

        /* Idempotensi: sudah VERIFIED → tidak ada efek tambahan. */
        if ($baris['status'] === 'VERIFIED') {
            $db->commit();
            return [
                'ok' => true, 'sudah' => true,
                'pesan' => 'Pembayaran sudah terverifikasi sebelumnya.',
                'pendaftar_id' => $baris['pendaftar_id'],
                'reg' => $baris['no_pendaftaran'], 'nama' => $baris['nama_siswa'],
                'wa' => $baris['no_whatsapp'] ?? '',
            ];
        }

        $db->prepare(
            "UPDATE pembayaran
                SET status = 'VERIFIED', verified_at = NOW(), verified_by = ?,
                    keterangan = CONCAT('Terverifikasi ', DATE_FORMAT(NOW(), '%d %b %Y, %H:%i'))
              WHERE id = ?"
        )->execute([$verifierId, $pembayaranId]);

        $db->prepare(
            "UPDATE pendaftar SET tahap = 'BAYAR_TERVERIFIKASI'
              WHERE id = ? AND tahap IN ('MENUNGGU_PEMBAYARAN', 'MENUNGGU_VERIFIKASI_BAYAR')"
        )->execute([$baris['pendaftar_id']]);

        $db->commit();
        return [
            'ok' => true, 'sudah' => false,
            'pendaftar_id' => $baris['pendaftar_id'],
            'reg' => $baris['no_pendaftaran'], 'nama' => $baris['nama_siswa'],
            'wa' => $baris['no_whatsapp'] ?? '',
            'nominal' => (float) $baris['nominal'],
        ];
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal menyimpan verifikasi pembayaran.'];
    }
}

/**
 * Tolak pembayaran: status = REJECTED + tahap kembali MENUNGGU_PEMBAYARAN
 * + kirim WA alasan penolakan (tapi WA TIDAK menggagalkan proses DB).
 *
 * @return array{ok:bool, sudah?:bool, pesan?:string, wa_ok?:bool, wa_pesan?:string}
 */
function verifikasi_bayar_tolak($pembayaranId, $alasan, $verifierId = null)
{
    $alasan = trim((string) $alasan);
    if ($alasan === '') {
        return ['ok' => false, 'pesan' => 'Alasan penolakan wajib diisi.'];
    }
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    try {
        $db->beginTransaction();
        $cek = $db->prepare(
            'SELECT b.id, b.status, b.nominal,
                    p.id AS pendaftar_id, p.no_pendaftaran, p.nama_siswa, p.tahap,
                    o.no_whatsapp, o.nama_orang_tua
               FROM pembayaran b
               JOIN pendaftar p ON p.id = b.pendaftar_id
          LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
              WHERE b.id = ? FOR UPDATE'
        );
        $cek->execute([$pembayaranId]);
        $baris = $cek->fetch();
        if (!$baris) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Data pembayaran tidak ditemukan.'];
        }

        /* Idempotensi. */
        if ($baris['status'] === 'REJECTED') {
            $db->commit();
            return ['ok' => true, 'sudah' => true, 'pesan' => 'Pembayaran sudah ditolak sebelumnya.'];
        }

        $ket = 'Ditolak: ' . mb_substr($alasan, 0, 200);
        $db->prepare(
            'UPDATE pembayaran
                SET status = \'REJECTED\', keterangan = ?, verified_at = NOW(), verified_by = ?, catatan = ?
              WHERE id = ?'
        )->execute([$ket, $verifierId, $alasan, $pembayaranId]);

        $db->prepare(
            "UPDATE pendaftar SET tahap = 'MENUNGGU_PEMBAYARAN'
              WHERE id = ? AND tahap IN ('MENUNGGU_VERIFIKASI_BAYAR', 'BAYAR_TERVERIFIKASI')"
        )->execute([$baris['pendaftar_id']]);

        $db->commit();
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal menyimpan penolakan pembayaran.'];
    }

    /* WA ke orang tua — setelah commit, kegagalan tidak membatalkan penolakan.
     * Fase 11 A6: token bayar polos tidak disimpan di DB — tautan unggah-ulang
     * memakai halaman cek-status (punya tombol "Pulihkan Tautan"). */
    $waOk = false;
    $waPesan = '';
    if (!empty($baris['no_whatsapp'])) {
        $tautan = url('app/spmb/cek-status.php');
        $waPesan = "Assalamu'alaikum.\n\n"
            . "Mohon maaf, pembayaran biaya formulir SPMB atas nama:\n"
            . "Nama : {$baris['nama_siswa']}\n"
            . "No. Pendaftaran : {$baris['no_pendaftaran']}\n"
            . "Nominal : " . rupiah($baris['nominal']) . "\n\n"
            . "BELUM DAPAT kami setujui.\n"
            . "Alasan : {$alasan}\n\n"
            . "Silakan unggah ulang bukti pembayaran yang benar melalui tautan berikut:\n"
            . "{$tautan}\n\n"
            . "Terima kasih.\nPanitia SPMB " . ($GLOBALS['PENGATURAN']['nama_sekolah'] ?? 'SMP Muhammadiyah 5');
        $r = wa_kirim($baris['no_whatsapp'], $waPesan);
        wa_log_catat($baris['no_whatsapp'], 'PENOLAKAN_BAYAR', $r);
        $waOk = !empty($r['ok']);
    }

    return [
        'ok' => true, 'sudah' => false,
        'wa_ok' => $waOk, 'wa_pesan' => $waOk ? 'Terkirim.' : ($waPesan !== '' ? 'Gagal terkirim — tercatat di wa_log.' : 'Tidak ada nomor WA orang tua.'),
        'reg' => $baris['no_pendaftaran'], 'nama' => $baris['nama_siswa'],
    ];
}

/**
 * "Verifikasi & Kirim Akun" (satu klik):
 *   1. Setujui pembayaran (idempoten).
 *   2. Buat akun siswa (atau reset bila sudah ada).
 *   3. Kirim kredensial via WhatsApp.
 *   4. WA sukses  → tahap AKUN_TERKIRIM lalu langsung MELENGKAPI_BERKAS.
 *      WA gagal   → tahap tetap AKUN_TERKIRIM (perlu "Kirim Ulang" operator).
 *
 * Akun TETAP terbentuk walau WA gagal; klik ganda idempoten.
 *
 * @return array{ok:bool, wa_ok?:bool, pesan?:string, kred?:array|null, reg?:string, nama?:string}
 */
function verifikasi_kirim_akun($pembayaranId, $verifierId = null)
{
    $setuju = verifikasi_bayar_setujui($pembayaranId, $verifierId);
    if (!$setuju['ok']) {
        return ['ok' => false, 'pesan' => $setuju['pesan'] ?? 'Gagal verifikasi pembayaran.'];
    }
    $pendId = $setuju['pendaftar_id'];

    /* Siapkan akun: buat baru atau reset bila sudah ada (idempoten). */
    $detail = pendaftar_detail_akun($pendId);
    if (!$detail) {
        return ['ok' => false, 'pesan' => 'Data pendaftar tidak ditemukan.'];
    }
    $kred = null;
    if ($detail['user_id']) {
        $reset = akun_reset_password($detail['user_id']);
        if (!$reset['ok']) {
            return ['ok' => false, 'pesan' => $reset['pesan']];
        }
        $kred = [
            'nama' => $detail['nama_siswa'], 'reg' => $detail['no_pendaftaran'],
            'username' => $detail['username'], 'password' => $reset['password'],
        ];
    } else {
        $baru = akun_buat($pendId);
        if (!$baru['ok']) {
            return ['ok' => false, 'pesan' => $baru['pesan']];
        }
        $kred = [
            'nama' => $baru['nama'], 'reg' => $baru['reg'],
            'username' => $baru['username'], 'password' => $baru['password'],
        ];
    }

    /* Fase 11 A6: nonaktifkan token bayar — pembayaran formulir sudah
     * terverifikasi & akun dibuat; unggah ulang berikutnya via portal siswa. */
    q_exec('UPDATE pendaftar SET pay_token_hash = NULL WHERE id = ?', [$pendId]);

    /* Tahap sementara: akun sudah ada, kredensial belum tentu terkirim. */
    q_exec(
        "UPDATE pendaftar SET tahap = 'AKUN_TERKIRIM'
          WHERE id = ? AND tahap IN ('BAYAR_TERVERIFIKASI', 'AKUN_TERKIRIM')",
        [$pendId]
    );

    /* Kirim kredensial via WA (tidak boleh menggagalkan akun yang sudah dibuat). */
    $wa = ['ok' => false, 'pesan' => 'Tidak ada nomor WA orang tua.', 'id' => null];
    if (!empty($detail['no_whatsapp'])) {
        $pesan = wa_pesan_kredensial($kred + [
            'tahun' => $GLOBALS['PENGATURAN']['tahun_ajaran'] ?? '',
            'spp'   => $GLOBALS['PENGATURAN']['biaya_spp'] ?? null,
        ]);
        $wa = wa_kirim($detail['no_whatsapp'], $pesan);
        wa_log_catat($detail['no_whatsapp'], 'KIRIM_AKUN', $wa);
    }

    if (!empty($wa['ok'])) {
        akun_tandai_terkirim($pendId);
        q_exec(
            "UPDATE pendaftar SET tahap = 'MELENGKAPI_BERKAS'
              WHERE id = ? AND tahap = 'AKUN_TERKIRIM'",
            [$pendId]
        );
    }

    return [
        'ok' => true,
        'wa_ok' => !empty($wa['wa_ok'] ?? $wa['ok']),
        'pesan' => !empty($wa['ok'])
            ? 'Pembayaran terverifikasi, akun dibuat, kredensial terkirim via WhatsApp.'
            : 'Pembayaran terverifikasi & akun dibuat, tetapi kirim WA gagal (' . ($wa['pesan'] ?? '') . '). Gunakan "Kirim Ulang" pada menu SPMB.',
        'kred' => $kred,
        'reg'  => $kred['reg'], 'nama' => $kred['nama'],
        'wa'   => $detail['no_whatsapp'] ?? '',
    ];
}

/**
 * Auto-create akun + kirim kredensial setelah pembayaran GATEWAY lunas
 * (dipanggil dari midtrans-callback; diaktifkan lewat pengaturan
 * `kirim_akun_otomatis`, default nonaktif). Tidak pernah melempar exception.
 *
 * @return array{ok:bool, wa_ok?:bool, pesan?:string}|null
 */
function kirim_akun_gateway_otomatis($pendaftarId)
{
    try {
        if ((int) ($GLOBALS['PENGATURAN']['kirim_akun_otomatis'] ?? 0) !== 1) {
            return null; /* toggle nonaktif */
        }
        $detail = pendaftar_detail_akun($pendaftarId);
        if (!$detail || $detail['user_id']) {
            return null; /* tidak ada / akun sudah ada — jangan reset password otomatis */
        }
        $baru = akun_buat($pendaftarId);
        if (!$baru['ok']) {
            return ['ok' => false, 'pesan' => $baru['pesan']];
        }
        /* Fase 11 A6: nonaktifkan token bayar setelah akun dibuat. */
        q_exec('UPDATE pendaftar SET pay_token_hash = NULL WHERE id = ?', [$pendaftarId]);
        q_exec(
            "UPDATE pendaftar SET tahap = 'AKUN_TERKIRIM'
              WHERE id = ? AND tahap IN ('BAYAR_TERVERIFIKASI', 'AKUN_TERKIRIM')",
            [$pendaftarId]
        );
        $kred = [
            'nama' => $baru['nama'], 'reg' => $baru['reg'],
            'username' => $baru['username'], 'password' => $baru['password'],
        ];
        $wa = ['ok' => false, 'pesan' => 'Tidak ada nomor WA orang tua.'];
        if (!empty($detail['no_whatsapp'])) {
            $pesan = wa_pesan_kredensial($kred + [
                'tahun' => $GLOBALS['PENGATURAN']['tahun_ajaran'] ?? '',
                'spp'   => $GLOBALS['PENGATURAN']['biaya_spp'] ?? null,
            ]);
            $wa = wa_kirim($detail['no_whatsapp'], $pesan);
            wa_log_catat($detail['no_whatsapp'], 'KIRIM_AKUN_OTOMATIS', $wa);
        }
        if (!empty($wa['ok'])) {
            akun_tandai_terkirim($pendaftarId);
            q_exec(
                "UPDATE pendaftar SET tahap = 'MELENGKAPI_BERKAS'
                  WHERE id = ? AND tahap = 'AKUN_TERKIRIM'",
                [$pendaftarId]
            );
        }
        return [
            'ok' => true,
            'wa_ok' => !empty($wa['ok']),
            'pesan' => !empty($wa['ok']) ? 'Akun dibuat & kredensial terkirim otomatis.' : 'Akun dibuat, kirim WA gagal.',
        ];
    } catch (Throwable $e) {
        return ['ok' => false, 'pesan' => 'Gagal proses kirim akun otomatis.'];
    }
}

/* ==================================================================
 * 12. REGISTRASI PUBLIK
 * ================================================================== */

/**
 * Registrasi awal publik: buat no_pendaftaran unik, baris pendaftar
 * (MENUNGGU_VERIFIKASI), orang tua, dan tagihan biaya formulir PENDING.
 *
 * @return array{ok:bool, no?:string, pesan?:string, id?:string}
 */
