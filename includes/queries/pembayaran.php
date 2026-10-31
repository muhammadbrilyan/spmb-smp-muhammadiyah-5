<?php
/**
 * Modul queries: pembayaran "” di-include dari includes/queries.php.
 */

/* ==================================================================
 * SKEMA TAGIHAN PENDAFTARAN (TAHAP 3)
 *
 * Biaya pendaftaran hanya ditagihkan setelah siswa dinyatakan LULUS dan
 * hasil seleksi resmi diumumkan. Siswa memilih bayar lunas atau angsuran
 * 2 kali (nominal otomatis setengah) saat membayar.
 * ================================================================== */

/**
 * Skema angsuran dari Pengaturan Sistem.
 * Opsi 2 angsuran selalu tersedia dan nominalnya otomatis dibagi dua sama
 * besar (sisa pembulatan masuk ke angsuran 2). Pengaturan hanya menyimpan
 * jatuh tempo; nominal angsuran tidak lagi diisi manual.
 * @return array{aktif:bool,total:float,angsuran:array<int,array{ke:int,nominal:float,jatuh_tempo:?string}>}
 */
function angsuran_skema()
{
    $total = max(0, (float) ($GLOBALS['PENGATURAN']['estimasi_uang_masuk'] ?? 0));
    $d1    = trim((string) ($GLOBALS['PENGATURAN']['angsuran_1_jatuh_tempo'] ?? ''));
    $d2    = trim((string) ($GLOBALS['PENGATURAN']['angsuran_2_jatuh_tempo'] ?? ''));

    $aktif = $total > 0;
    $a1    = $aktif ? round($total / 2) : 0.0;
    $a2    = $aktif ? $total - $a1 : 0.0;

    return [
        'aktif'    => $aktif,
        'total'    => $total,
        'angsuran' => $aktif ? [
            1 => ['ke' => 1, 'nominal' => $a1, 'jatuh_tempo' => $d1 !== '' ? $d1 : null],
            2 => ['ke' => 2, 'nominal' => $a2, 'jatuh_tempo' => $d2 !== '' ? $d2 : null],
        ] : [],
    ];
}

/** Tagihan biaya pendaftaran baru aktif setelah LULUS dan hasil diumumkan resmi. */
function tagihan_pendaftaran_aktif($pendaftar)
{
    if (!is_array($pendaftar) || ($pendaftar['status_seleksi'] ?? '') !== 'LULUS') {
        return false;
    }
    $p = function_exists('pengumuman_status') ? pengumuman_status() : [];
    return !empty($p['diumumkan']);
}

/**
 * Ringkasan pembayaran biaya pendaftaran per angsuran (FIFO).
 * Baris lama tanpa angsuran_ke tetap dihitung sebagai dana terverifikasi.
 * @return array<string,mixed>
 */
function angsuran_ringkas($pendaftar_id)
{
    $skema = angsuran_skema();
    $out = [
        'aktif'       => $skema['aktif'],
        'total'       => $skema['total'],
        'terverifikasi' => 0.0,
        'pending'     => 0.0,
        'sisa'        => $skema['total'],
        'lunas'       => false,
        'angsuran'    => [],
        'berikut'     => null,
        'jatuh_tempo_terdekat' => null,
    ];
    if (!db_aktif() || !$pendaftar_id) {
        return $out;
    }

    $baris = q_all(
        "SELECT nominal, status, angsuran_ke FROM pembayaran
          WHERE pendaftar_id = ? AND jenis_pembayaran = 'UANG_MASUK'",
        [(string) $pendaftar_id]
    ) ?: [];

    $verified = 0.0;
    $pending  = 0.0;
    $pendingKe = [];
    foreach ($baris as $b) {
        $n = (float) $b['nominal'];
        if (in_array($b['status'], ['VERIFIED', 'LUNAS', 'ANGSURAN_1', 'ANGSURAN_2'], true)) {
            $verified += $n;
        } elseif ($b['status'] === 'PENDING') {
            $pending += $n;
            if (!empty($b['angsuran_ke'])) {
                $pendingKe[(int) $b['angsuran_ke']] = true;
            }
        }
    }

    $out['terverifikasi'] = $verified;
    $out['pending'] = $pending;
    $out['sisa'] = max(0, $skema['total'] - $verified);
    $out['lunas'] = $skema['total'] > 0 && $verified >= $skema['total'] - 0.5;

    if (!$skema['aktif']) {
        return $out;
    }

    /* Baris PENDING lama (tanpa angsuran_ke) memblokir angsuran mana pun. */
    $pendingLegacy = $pending > 0 && empty($pendingKe);
    $offset = 0.0;
    foreach ($skema['angsuran'] as $ke => $a) {
        $nominal  = (float) $a['nominal'];
        $terbayar = max(0, min($nominal, $verified - $offset));
        $sisa     = max(0, $nominal - $terbayar);

        if ($terbayar >= $nominal - 0.5) {
            $status = 'LUNAS';
        } elseif (!empty($pendingKe[$ke])) {
            $status = 'MENUNGGU';
        } elseif ($pendingLegacy && $sisa > 0) {
            $status = 'MENUNGGU';
            $pendingLegacy = false;
        } elseif ($terbayar > 0) {
            $status = 'SEBAGIAN';
        } else {
            $status = 'BELUM';
        }

        $out['angsuran'][$ke] = [
            'ke'          => $ke,
            'nominal'     => $nominal,
            'terbayar'    => $terbayar,
            'sisa'        => $sisa,
            'jatuh_tempo' => $a['jatuh_tempo'],
            'status'      => $status,
        ];

        if ($out['jatuh_tempo_terdekat'] === null && $sisa > 0 && $a['jatuh_tempo'] !== null) {
            $out['jatuh_tempo_terdekat'] = $out['angsuran'][$ke];
        }
        if ($out['berikut'] === null && $sisa > 0) {
            /* Angsuran yang menunggu verifikasi memblokir angsuran sesudahnya. */
            if ($status === 'MENUNGGU') {
                break;
            }
            $out['berikut'] = $ke;
        }
        $offset += $nominal;
    }

    return $out;
}

/**
 * Status baris UANG_MASUK setelah nominal ini diverifikasi.
 * Angsuran pertama TIDAK pernah dianggap pelunasan bila total belum tertutup.
 */
function pembayaran_status_pendaftaran($nominalBaru, $sudahTerverifikasi)
{
    $skema = angsuran_skema();
    if (!$skema['aktif']) {
        return 'VERIFIED';
    }
    $kumulatif = (float) $sudahTerverifikasi + (float) $nominalBaru;
    if ($kumulatif >= $skema['total'] - 0.5) {
        return 'LUNAS';
    }
    if ($kumulatif >= (float) $skema['angsuran'][1]['nominal'] - 0.5) {
        return 'ANGSURAN_1';
    }
    return 'VERIFIED';
}

/* ==================================================================
 * PENGINGAT JATUH TEMPO ANGSURAN (in-app + WhatsApp orang tua)
 *
 * Pemicu: halaman portal siswa, tombol bendahara, atau cron
 * tools/pengingat-angsuran.php. Idempoten per jadwal:
 *   ref_id = angsuran-<ke>-<tanggal>          (pengingat H-N)
 *   ref_id = angsuran-<ke>-<tanggal>-lambat   (pengingat terlambat)
 * ================================================================== */

/** H-berapa hari pengingat dikirim (1–60, default 7). */
function angsuran_hari_pengingat()
{
    $n = (int) ($GLOBALS['PENGATURAN']['pengingat_angsuran_hari'] ?? 7);
    return max(1, min(60, $n));
}

/** Pengingat WhatsApp ke orang tua/wali diaktifkan operator? */
function angsuran_pengingat_wa_aktif()
{
    return (int) ($GLOBALS['PENGATURAN']['pengingat_angsuran_wa'] ?? 0) === 1;
}

/**
 * Pengingat yang layak dikirim untuk satu pendaftar.
 * Hanya angsuran berikutnya (yang masih bisa dibayar) yang diingatkan;
 * angsuran yang sudah lunas atau sedang menunggu verifikasi dilewati.
 *
 * @return array<int,array{ke:int,sisa:float,jatuh_tempo:string,ref:string,judul:string,pesan:string,terlambat:bool,sisa_hari:int}>
 */
function angsuran_pengingat_kandidat(array $ringkas)
{
    if (empty($ringkas['aktif']) || !empty($ringkas['lunas'])) {
        return [];
    }
    $berikut = $ringkas['berikut'] ?? null;
    if ($berikut === null) {
        return []; /* ada angsuran menunggu verifikasi — jangan ditagih dulu */
    }
    $batasHari = angsuran_hari_pengingat();
    $hariIni   = new DateTimeImmutable(date('Y-m-d'));
    $kandidat  = [];
    foreach ($ringkas['angsuran'] as $a) {
        if ($a['sisa'] <= 0 || empty($a['jatuh_tempo']) || (int) $a['ke'] > (int) $berikut) {
            continue;
        }
        if (($a['status'] ?? '') === 'MENUNGGU') {
            continue;
        }
        try {
            $jatuh = new DateTimeImmutable((string) $a['jatuh_tempo']);
        } catch (Throwable $e) {
            continue;
        }
        $sisaHari = (int) $hariIni->diff($jatuh)->format('%r%a');
        if ($sisaHari > $batasHari) {
            continue;
        }
        $terlambat = $sisaHari < 0;
        $ref = 'angsuran-' . $a['ke'] . '-' . $a['jatuh_tempo'] . ($terlambat ? '-lambat' : '');
        $tgl = bulan($a['jatuh_tempo']);
        if ($terlambat) {
            $judul = 'Angsuran ' . $a['ke'] . ' melewati jatuh tempo';
            $pesan = 'Sisa angsuran ' . $a['ke'] . ' sebesar ' . rupiah($a['sisa'])
                . ' sudah melewati jatuh tempo ' . $tgl . ' (' . abs($sisaHari) . ' hari).'
                . ' Mohon segera diselesaikan.';
        } else {
            $judul = 'Jatuh tempo angsuran ' . $a['ke'];
            $pesan = 'Sisa angsuran ' . $a['ke'] . ' sebesar ' . rupiah($a['sisa'])
                . ' jatuh tempo ' . $tgl
                . ($sisaHari === 0 ? ' (hari ini)' : ' (' . $sisaHari . ' hari lagi)') . '.'
                . ' Silakan siapkan pembayaran.';
        }
        $kandidat[] = [
            'ke'          => (int) $a['ke'],
            'sisa'        => (float) $a['sisa'],
            'jatuh_tempo' => (string) $a['jatuh_tempo'],
            'ref'         => $ref,
            'judul'       => $judul,
            'pesan'       => $pesan,
            'terlambat'   => $terlambat,
            'sisa_hari'   => $sisaHari,
        ];
    }
    return $kandidat;
}

/** Pesan WhatsApp pengingat jatuh tempo untuk orang tua/wali. */
function angsuran_pesan_wa(array $siswa, array $kandidat)
{
    $P = $GLOBALS['PENGATURAN'];
    $teks = "Assalamu'alaikum.\n\n"
        . 'Pengingat pembayaran ' . label_biaya_pendaftaran() . ' '
        . ($P['nama_sekolah'] ?? APP_NAME) . ".\n\n"
        . 'Nama : ' . ($siswa['nama_siswa'] ?? '-') . "\n"
        . 'No. Pendaftaran : ' . ($siswa['no_pendaftaran'] ?? '-') . "\n\n"
        . $kandidat['pesan'] . "\n";
    if (!empty($P['bayar_transfer_aktif'])) {
        $teks .= "\nTransfer ke:\n"
            . '   ' . ($P['nama_bank'] ?? '') . "\n"
            . '   No. Rek : ' . ($P['no_rekening'] ?? '') . "\n"
            . '   a.n. ' . ($P['atas_nama_rekening'] ?? '') . "\n";
    }
    $teks .= "\nRincian & pembayaran: " . url_abs('app/siswa/keuangan.php') . "\n";
    $kontak = trim((string) ($P['kontak_wa_panitia'] ?? ''));
    if ($kontak !== '') {
        $teks .= 'Bantuan panitia: ' . $kontak . "\n";
    }
    $teks .= "\nSalam,\nPanitia SPMB " . ($P['nama_sekolah'] ?? APP_SHORT);
    return $teks;
}

/**
 * Kirim pengingat jatuh tempo untuk satu pendaftar: notifikasi in-app
 * (sekali per jadwal) dan, bila diaktifkan, WhatsApp ke orang tua/wali.
 *
 * @param string|null $tautan URL tujuan notifikasi in-app
 * @param bool|null   $kirimWa null = ikut pengaturan; true/false = paksa
 * @return array{dibuat:int,wa:int,wa_gagal:int,lewati:int}
 */
function angsuran_kirim_pengingat($pendaftar_id, array $ringkas, $tautan = null, $kirimWa = null)
{
    $hasil = ['dibuat' => 0, 'wa' => 0, 'wa_gagal' => 0, 'lewati' => 0];
    $kandidat = angsuran_pengingat_kandidat($ringkas);
    if (!$kandidat) {
        return $hasil;
    }
    $kirimWa = $kirimWa === null ? angsuran_pengingat_wa_aktif() : (bool) $kirimWa;

    $siswa = null;
    $noWa  = '';
    if ($kirimWa) {
        $r = q_all(
            'SELECT p.nama_siswa, p.no_pendaftaran, o.no_whatsapp
               FROM pendaftar p
          LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
              WHERE p.id = ? LIMIT 1',
            [(string) $pendaftar_id]
        );
        $siswa = $r[0] ?? null;
        $noWa  = trim((string) ($siswa['no_whatsapp'] ?? ''));
    }

    foreach ($kandidat as $k) {
        $lama = notif_siswa_cari_ref($pendaftar_id, $k['ref']);
        if ($lama) {
            $hasil['lewati']++;
        } elseif (notif_siswa_kirim($pendaftar_id, 'ANGSURAN_JATUH_TEMPO', $k['judul'], $k['pesan'], $tautan, $k['ref'])) {
            $hasil['dibuat']++;
            $lama = notif_siswa_cari_ref($pendaftar_id, $k['ref']);
        } else {
            continue;
        }

        if (!$kirimWa || !$siswa || $noWa === '') {
            continue;
        }
        if (($lama['wa_status'] ?? null) === 'TERKIRIM') {
            continue;
        }
        /* Gagal kirim: jangan coba ulang lebih cepat dari 1 jam. */
        if (($lama['wa_status'] ?? null) === 'GAGAL' && !empty($lama['wa_at'])
            && strtotime((string) $lama['wa_at']) > time() - 3600) {
            continue;
        }
        $kirim = wa_kirim($noWa, angsuran_pesan_wa($siswa, $k));
        wa_log_catat($noWa, 'PENGINGAT_ANGSURAN', $kirim);
        notif_siswa_status_wa($pendaftar_id, $k['ref'], !empty($kirim['ok']) ? 'TERKIRIM' : 'GAGAL');
        if (!empty($kirim['ok'])) {
            $hasil['wa']++;
        } else {
            $hasil['wa_gagal']++;
        }
    }
    return $hasil;
}

/**
 * Pendaftar LULUS yang tagihannya sudah aktif (hasil seleksi diumumkan).
 * @return array<int,array{id:string,nama_siswa:string,no_pendaftaran:string,no_whatsapp:?string}>
 */
function angsuran_pendaftar_lulus()
{
    if (!db_aktif()) {
        return [];
    }
    $p = function_exists('pengumuman_status') ? pengumuman_status() : [];
    if (empty($p['diumumkan'])) {
        return [];
    }
    return q_all(
        "SELECT p.id, p.nama_siswa, p.no_pendaftaran, o.no_whatsapp
           FROM pendaftar p
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.status_seleksi = 'LULUS' AND p.arsip_at IS NULL
       ORDER BY p.no_pendaftaran"
    ) ?: [];
}

/**
 * Jalankan pengingat untuk seluruh pendaftar LULUS (tombol bendahara / cron).
 * @return array{siswa:int,dibuat:int,wa:int,wa_gagal:int,lewati:int}
 */
function angsuran_pengingat_semua($tautan = null, $kirimWa = null)
{
    $total = ['siswa' => 0, 'dibuat' => 0, 'wa' => 0, 'wa_gagal' => 0, 'lewati' => 0];
    foreach (angsuran_pendaftar_lulus() as $r) {
        $ringkas = angsuran_ringkas($r['id']);
        $hasil   = angsuran_kirim_pengingat($r['id'], $ringkas, $tautan, $kirimWa);
        $adaAksi = $hasil['dibuat'] > 0 || $hasil['wa'] > 0 || $hasil['wa_gagal'] > 0;
        if ($adaAksi) {
            $total['siswa']++;
        }
        foreach (['dibuat', 'wa', 'wa_gagal', 'lewati'] as $k) {
            $total[$k] += $hasil[$k];
        }
    }
    return $total;
}


function daftar_pembayaran()
{
    if (!db_aktif()) {
        return [];
    }

    $baris = q_all(
        "SELECT b.id, b.jenis_pembayaran, b.angsuran_ke, b.nominal, b.status, b.keterangan,
                b.bukti_transfer_url, b.verified_at, b.created_at,
                p.id AS pendaftar_id, p.user_id, p.tahap,
                p.no_pendaftaran, p.nama_siswa, p.nisn, p.asal_sekolah,
                o.no_whatsapp, o.nama_orang_tua,
                v.nama_lengkap AS verifier
           FROM pembayaran b
           JOIN pendaftar p ON p.id = b.pendaftar_id
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
      LEFT JOIN users v ON v.id = b.verified_by
        WHERE p.arsip_at IS NULL
       ORDER BY FIELD(b.status,'PENDING','REJECTED','VERIFIED','LUNAS','ANGSURAN_1','ANGSURAN_2'), b.created_at DESC"
    );
    if ($baris === null) {
        return [];
    }

    $labelJenis = ['FORMULIR' => 'Biaya Formulir', 'UANG_MASUK' => label_biaya_pendaftaran()];
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
        $jenisLabel = $labelJenis[$r['jenis_pembayaran']] ?? $r['jenis_pembayaran'];
        if ($r['jenis_pembayaran'] === 'UANG_MASUK' && !empty($r['angsuran_ke'])) {
            $jenisLabel .= ' · Angsuran ' . (int) $r['angsuran_ke'];
        }
        $keluar[] = [
            'id'        => $r['id'],
            'pendaftar_id' => $r['pendaftar_id'],
            'user_id'   => $r['user_id'],
            'tahap'     => $r['tahap'],
            'reg'       => $r['no_pendaftaran'],
            'waktu'     => $r['created_at'],
            'nama'      => $r['nama_siswa'],
            'sekolah'   => $r['asal_sekolah'],
            'nisn'      => $r['nisn'] ?: '—',
            'wa'        => $r['no_whatsapp'] ?: '—',
            'wali'      => $r['nama_orang_tua'] ?: '—',
            'jenis'     => $jenisLabel,
            'angsuran_ke' => $r['angsuran_ke'] !== null ? (int) $r['angsuran_ke'] : null,
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
        'total_pendaftar' => (int) (q_col('SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NULL') ?? 0),
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
        'SELECT id, jenis_pembayaran, angsuran_ke, nominal, status, keterangan, bukti_transfer_url,
                metode, payment_method, paid_at, verified_at, catatan, created_at
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
function pembayaran_unggah_bukti($pendaftar_id, $jenis, $nominal, $bukti_url, $idemKey = null, $angsuranKe = null)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    $idemKey = idem_key_valid($idemKey);
    $angsuranKe = $jenis === 'UANG_MASUK' && in_array((int) $angsuranKe, [1, 2], true)
        ? (int) $angsuranKe
        : null;

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

        /* Baris PENDING/REJECTED untuk jenis & angsuran ini → update in place. */
        $ada = $db->prepare(
            "SELECT id FROM pembayaran
              WHERE pendaftar_id = ? AND jenis_pembayaran = ? AND angsuran_ke <=> ?
                AND status IN ('PENDING','REJECTED')
              ORDER BY created_at DESC LIMIT 1 FOR UPDATE"
        );
        $ada->execute([$pendaftar_id, $jenis, $angsuranKe]);
        $baris = $ada->fetch();

        if ($baris) {
            $upd = $db->prepare(
                'UPDATE pembayaran
                    SET bukti_transfer_url = ?, nominal = ?, status = "PENDING",
                        keterangan = "Menunggu verifikasi bukti", idempotency_key = ?, angsuran_ke = ?
                  WHERE id = ?'
            );
            $upd->execute([$bukti_url, (float) $nominal, $idemKey, $angsuranKe, $baris['id']]);
            $db->commit();
            return ['ok' => true, 'id' => $baris['id'], 'reused' => false];
        }

        $id = 'byr-' . bin2hex(random_bytes(4));
        $ins = $db->prepare(
            'INSERT INTO pembayaran (id, pendaftar_id, jenis_pembayaran, angsuran_ke, nominal, bukti_transfer_url, status, keterangan, idempotency_key)
             VALUES (?, ?, ?, ?, ?, ?, "PENDING", "Menunggu verifikasi bukti", ?)'
        );
        $ins->execute([$id, $pendaftar_id, $jenis, $angsuranKe, (float) $nominal, $bukti_url, $idemKey]);
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
function pembayaran_siapkan_online($pendaftar_id, $jenis, $nominal, $order_id, $idemKey = null, $snapToken = null, $expiresAt = null, $angsuranKe = null)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    $idemKey = idem_key_valid($idemKey);
    if ($idemKey === null) {
        return ['ok' => false, 'pesan' => 'Idempotency key tidak valid.'];
    }
    $angsuranKe = $jenis === 'UANG_MASUK' && in_array((int) $angsuranKe, [1, 2], true)
        ? (int) $angsuranKe
        : null;

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

        /* Baris PENDING/REJECTED untuk jenis & angsuran ini → pakai ulang. */
        $ada = $db->prepare(
            "SELECT id, order_id, snap_token, snap_expires_at, status
               FROM pembayaran
              WHERE pendaftar_id = ? AND jenis_pembayaran = ? AND angsuran_ke <=> ?
                AND status IN ('PENDING','REJECTED')
              ORDER BY created_at DESC LIMIT 1 FOR UPDATE"
        );
        $ada->execute([$pendaftar_id, $jenis, $angsuranKe]);
        $baris = $ada->fetch();

        if ($baris) {
            $byrId = $baris['id'];
            /* Order_id lama masih dipakai — jalur timpa agar transaksi Midtrans aktif tidak jadi yatim. */
            $pakaiOrder = $baris['order_id'] ?: $order_id;
            $upd = $db->prepare(
                'UPDATE pembayaran
                    SET nominal = ?, status = "PENDING",
                        keterangan = "Menunggu pembayaran online",
                        midtrans_status = "pending",
                        order_id = ?,
                        idempotency_key = ?,
                        angsuran_ke = ?
                  WHERE id = ?'
            );
            $upd->execute([(float) $nominal, $pakaiOrder, $idemKey, $angsuranKe, $byrId]);
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
            'INSERT INTO pembayaran (id, pendaftar_id, jenis_pembayaran, angsuran_ke, nominal, status, keterangan, order_id, midtrans_status, idempotency_key, snap_token, snap_expires_at)
             VALUES (?, ?, ?, ?, ?, "PENDING", "Menunggu pembayaran online", ?, "pending", ?, ?, ?)'
        );
        $ins->execute([$byrId, $pendaftar_id, $jenis, $angsuranKe, (float) $nominal, $order_id, $idemKey, $snapToken, $expiresAt]);
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
        "SELECT jenis_pembayaran, angsuran_ke, nominal, status, order_id, midtrans_status, payment_method, paid_at, keterangan
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
            'SELECT b.id, b.status, b.nominal, b.jenis_pembayaran, b.angsuran_ke,
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

        /* Idempotensi: status dana sudah final → tidak ada efek tambahan. */
        if (in_array($baris['status'], ['VERIFIED', 'LUNAS', 'ANGSURAN_1', 'ANGSURAN_2'], true)) {
            $db->commit();
            return [
                'ok' => true, 'sudah' => true,
                'pesan' => 'Pembayaran sudah terverifikasi sebelumnya.',
                'pendaftar_id' => $baris['pendaftar_id'],
                'reg' => $baris['no_pendaftaran'], 'nama' => $baris['nama_siswa'],
                'wa' => $baris['no_whatsapp'] ?? '',
            ];
        }

        /* Tentukan status hasil verifikasi. Untuk biaya pendaftaran, status
         * mengikuti total kumulatif agar angsuran pertama tidak dianggap lunas. */
        $statusBaru = 'VERIFIED';
        $sudahTerverifikasi = 0.0;
        if ($baris['jenis_pembayaran'] === 'UANG_MASUK') {
            $sum = $db->prepare(
                "SELECT COALESCE(SUM(nominal),0)
                   FROM pembayaran
                  WHERE pendaftar_id = ? AND jenis_pembayaran = 'UANG_MASUK'
                    AND id <> ? AND status IN ('VERIFIED','LUNAS','ANGSURAN_1','ANGSURAN_2')"
            );
            $sum->execute([$baris['pendaftar_id'], $baris['id']]);
            $sudahTerverifikasi = (float) $sum->fetchColumn();
            $statusBaru = pembayaran_status_pendaftaran((float) $baris['nominal'], $sudahTerverifikasi);
        }

        $db->prepare(
            "UPDATE pembayaran
                SET status = ?, verified_at = NOW(), verified_by = ?,
                    keterangan = CONCAT('Terverifikasi ', DATE_FORMAT(NOW(), '%d %b %Y, %H:%i'))
              WHERE id = ?"
        )->execute([$statusBaru, $verifierId, $pembayaranId]);

        /* Tahap pendaftar hanya naik untuk pembayaran formulir. */
        if ($baris['jenis_pembayaran'] === 'FORMULIR') {
            $db->prepare(
                "UPDATE pendaftar SET tahap = 'BAYAR_TERVERIFIKASI'
                  WHERE id = ? AND tahap IN ('MENUNGGU_PEMBAYARAN', 'MENUNGGU_VERIFIKASI_BAYAR')"
            )->execute([$baris['pendaftar_id']]);
        }

        $db->commit();

        /* Notifikasi in-app siswa (lonceng portal). */
        try {
            $jenisLabel = $baris['jenis_pembayaran'] === 'UANG_MASUK'
                ? label_biaya_pendaftaran()
                : 'Biaya formulir';
            $angsuranLabel = ($baris['jenis_pembayaran'] === 'UANG_MASUK' && !empty($baris['angsuran_ke']))
                ? ' angsuran ' . (int) $baris['angsuran_ke']
                : '';

            if ($baris['jenis_pembayaran'] === 'UANG_MASUK') {
                $sisaPesan = max(0, angsuran_skema()['total'] - $sudahTerverifikasi - (float) $baris['nominal']);
                $pesanNotif = $statusBaru === 'LUNAS'
                    ? 'Pembayaran ' . $jenisLabel . $angsuranLabel . ' sebesar ' . rupiah($baris['nominal'])
                        . ' telah diverifikasi dan dinyatakan LUNAS. Terima kasih.'
                    : 'Pembayaran ' . $jenisLabel . $angsuranLabel . ' sebesar ' . rupiah($baris['nominal'])
                        . ' telah diverifikasi. Sisa tagihan ' . rupiah($sisaPesan) . '.';
            } else {
                $pesanNotif = 'Pembayaran ' . $jenisLabel . ' sebesar ' . rupiah($baris['nominal'])
                    . ' telah diverifikasi panitia. Lanjutkan kelengkapan berkas melalui menu Data Siswa.';
            }

            notif_siswa_kirim(
                $baris['pendaftar_id'],
                'BAYAR_TERVERIFIKASI',
                $statusBaru === 'LUNAS' ? 'Pembayaran lunas' : 'Pembayaran terverifikasi',
                $pesanNotif,
                url('app/siswa/keuangan.php')
            );
        } catch (Throwable $e) {
            /* abaikan */
        }

        return [
            'ok' => true, 'sudah' => false,
            'pendaftar_id' => $baris['pendaftar_id'],
            'reg' => $baris['no_pendaftaran'], 'nama' => $baris['nama_siswa'],
            'wa' => $baris['no_whatsapp'] ?? '',
            'nominal' => (float) $baris['nominal'],
            'jenis' => $baris['jenis_pembayaran'],
            'status_baru' => $statusBaru,
            'angsuran_ke' => $baris['angsuran_ke'] !== null ? (int) $baris['angsuran_ke'] : null,
            'sisa' => $baris['jenis_pembayaran'] === 'UANG_MASUK'
                ? max(0, angsuran_skema()['total'] - $sudahTerverifikasi - (float) $baris['nominal'])
                : 0,
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
            'SELECT b.id, b.status, b.nominal, b.jenis_pembayaran, b.angsuran_ke,
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

        if ($baris['jenis_pembayaran'] === 'FORMULIR') {
            $db->prepare(
                "UPDATE pendaftar SET tahap = 'MENUNGGU_PEMBAYARAN'
                  WHERE id = ? AND tahap IN ('MENUNGGU_VERIFIKASI_BAYAR', 'BAYAR_TERVERIFIKASI')"
            )->execute([$baris['pendaftar_id']]);
        }

        $db->commit();
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
        }
        return ['ok' => false, 'pesan' => 'Gagal menyimpan penolakan pembayaran.'];
    }

    /* Notifikasi in-app siswa (lonceng portal). */
    try {
        $jenisLabel = $baris['jenis_pembayaran'] === 'UANG_MASUK'
            ? label_biaya_pendaftaran()
            : 'Biaya formulir';
        $angsuranLabel = ($baris['jenis_pembayaran'] === 'UANG_MASUK' && !empty($baris['angsuran_ke']))
            ? ' angsuran ' . (int) $baris['angsuran_ke']
            : '';
        notif_siswa_kirim(
            $baris['pendaftar_id'],
            'BAYAR_DITOLAK',
            'Pembayaran ditolak',
            'Bukti pembayaran ' . $jenisLabel . $angsuranLabel . ' sebesar ' . rupiah($baris['nominal'])
                . " belum dapat disetujui.\nAlasan: {$alasan}\n"
                . 'Silakan unggah ulang bukti pembayaran yang benar melalui menu Keuangan.',
            url('app/siswa/keuangan.php')
        );
    } catch (Throwable $e) {
        /* abaikan */
    }

    /* WA ke orang tua — setelah commit, kegagalan tidak membatalkan penolakan.
     * Fase 11 A6: token bayar polos tidak disimpan di DB — tautan unggah-ulang
     * memakai halaman cek-status (punya tombol "Pulihkan Tautan"). */
    $waOk = false;
    $waPesan = '';
    if (!empty($baris['no_whatsapp'])) {
        $tautan = url('app/spmb/cek-status.php');
        $namaBiaya = $baris['jenis_pembayaran'] === 'UANG_MASUK'
            ? label_biaya_pendaftaran() . (($baris['jenis_pembayaran'] === 'UANG_MASUK' && !empty($baris['angsuran_ke'])) ? ' angsuran ' . (int) $baris['angsuran_ke'] : '')
            : 'biaya formulir';
        $waPesan = "Assalamu'alaikum.\n\n"
            . "Mohon maaf, pembayaran {$namaBiaya} SPMB atas nama:\n"
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
