<?php
/**
 * ENDPOINT AJAX — buat Snap Token untuk pembayaran online.
 * POST (login siswa): pendaftar_id, jenis (FORMULIR|UANG_MASUK), idem_key,
 *                     mode (LUNAS|ANGSURAN, khusus UANG_MASUK), angsuran_ke
 * POST (publik):      pay_token, jenis, idem_key
 * Returns JSON: { ok, snap_token, order_id, client_key, snap_url } atau { ok:false, pesan }
 *
 * Idempotency: idem_key yang sama (retry / double-click) tidak membuat
 * transaksi Midtrans baru — mengembalikan token / order_id yang sudah ada.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/midtrans.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Method not allowed']);
    exit;
}

if (!csrf_cek($_POST['_csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Formulir kedaluwarsa. Muat ulang halaman.']);
    exit;
}

$jenisRaw = ($_POST['jenis'] ?? '') === 'UANG_MASUK' ? 'UANG_MASUK' : 'FORMULIR';
$idemKey  = idem_key_valid($_POST['idem_key'] ?? '');
$payToken = trim((string) ($_POST['pay_token'] ?? ''));

if ($idemKey === null) {
    echo json_encode(['ok' => false, 'pesan' => 'Idempotency key tidak valid. Muat ulang halaman.']);
    exit;
}

/* Rate limit untuk jalur publik. */
if (!rate_limit_cek('snap_token', 20, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'pesan' => 'Terlalu banyak permintaan. Coba lagi nanti.']);
    exit;
}

/* -----------------------------------------------------------
 * Dua jalur autentikasi:
 *   1. pay_token (publik, dari halaman bayar.php)
 *   2. sesi login siswa + pendaftar_id
 * ----------------------------------------------------------- */
$profil      = null;
$pendaftarId = '';
$jenis       = $jenisRaw;
$angsuranKe  = 0;
$skemaUm     = angsuran_skema();
$ringkasAngsuran = null;

if ($payToken !== '') {
    /* Jalur publik — validasi pay_token. */
    if (!preg_match('/^[a-f0-9]{64}$/', $payToken)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'pesan' => 'Token pembayaran tidak valid.']);
        exit;
    }
    rate_limit_catat('snap_token');

    $baris = pendaftar_by_pay_token($payToken);
    if (!$baris) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'pesan' => 'Tautan pembayaran tidak ditemukan.']);
        exit;
    }
    /* Fase 11 A1: jalur publik hanya untuk biaya FORMULIR —
     * UANG_MASUK (uang masuk) hanya lewat portal siswa terverifikasi. */
    $jenis = 'FORMULIR';
    $pendaftarId = $baris['id'];
    $profil = [
        'id'             => $baris['id'],
        'nama_siswa'     => $baris['nama_siswa'],
        'no_pendaftaran' => $baris['no_pendaftaran'],
        'no_whatsapp'    => null,
    ];
    /* Ambil WA orang tua untuk customer_details. */
    $wa = q_col('SELECT o.no_whatsapp FROM orang_tua o WHERE o.pendaftar_id = ? LIMIT 1', [$pendaftarId]);
    $profil['no_whatsapp'] = $wa ?: null;
} else {
    /* Jalur login siswa. */
    require_role(['siswa']);
    $me = current_user();
    $pendaftarId = trim((string) ($_POST['pendaftar_id'] ?? ''));

    $profil = profil_siswa();
    if (!$profil || $profil['id'] !== $pendaftarId) {
        echo json_encode(['ok' => false, 'pesan' => 'Data pendaftar tidak valid.']);
        exit;
    }
    rate_limit_catat('snap_token');

    /* Tagihan biaya pendaftaran hanya terbuka setelah LULUS & diumumkan.
     * Mode LUNAS membayar seluruh sisa tagihan; mode ANGSURAN membayar
     * angsuran berikutnya (setengah tagihan) bila skema 2 angsuran aktif. */
    if ($jenis === 'UANG_MASUK') {
        $ringkasUm = angsuran_ringkas($pendaftarId);
        if (!tagihan_pendaftaran_aktif($profil)) {
            echo json_encode(['ok' => false, 'pesan' => label_biaya_pendaftaran() . ' dapat dibayar setelah hasil seleksi diumumkan dan Anda dinyatakan LULUS.']);
            exit;
        }
        if (!empty($ringkasUm['lunas'])) {
            echo json_encode(['ok' => false, 'pesan' => label_biaya_pendaftaran() . ' untuk pendaftar ini sudah lunas.']);
            exit;
        }
        if ((float) $ringkasUm['pending'] > 0) {
            echo json_encode(['ok' => false, 'pesan' => 'Masih ada pembayaran yang menunggu verifikasi. Selesaikan dulu sebelum membayar lagi.']);
            exit;
        }
        if (($_POST['mode'] ?? '') === 'ANGSURAN') {
            if (!$skemaUm['aktif']) {
                echo json_encode(['ok' => false, 'pesan' => 'Skema angsuran tidak tersedia. Silakan bayar lunas.']);
                exit;
            }
            $angsuranKe = (int) ($_POST['angsuran_ke'] ?? 0);
            $berikut = (int) ($ringkasUm['berikut'] ?? 0);
            if ($angsuranKe <= 0) {
                $angsuranKe = $berikut;
            }
            if ($berikut <= 0 || $angsuranKe !== $berikut) {
                echo json_encode(['ok' => false, 'pesan' => $berikut > 0
                    ? 'Saat ini yang dapat dibayar adalah angsuran ' . $berikut . '.'
                    : 'Tidak ada angsuran yang dapat dibayar saat ini.']);
                exit;
            }
        }
        $ringkasAngsuran = $ringkasUm;
    }
}

/* Cek apakah gateway tersedia (terkonfigurasi DAN diaktifkan di CMS). */
$clientKey = midtrans_client_key();
if (!gateway_tersedia() || $clientKey === null) {
    $pesanGateway = (midtrans_aktif() && empty($PENGATURAN['bayar_gateway_aktif']))
        ? 'Pembayaran online sedang tidak aktif. Silakan gunakan transfer bank atau QRIS.'
        : 'Payment gateway belum dikonfigurasi. Hubungi panitia.';
    echo json_encode(['ok' => false, 'pesan' => $pesanGateway]);
    exit;
}

/* Nominal dari CMS / sisa angsuran berikutnya. */
if ($jenis === 'UANG_MASUK') {
    $nominal = $angsuranKe > 0
        ? (float) $ringkasAngsuran['angsuran'][$angsuranKe]['sisa']
        : (float) ($ringkasAngsuran['sisa'] ?? 0);
} else {
    $nominal = (float) ($PENGATURAN['biaya_formulir'] ?? 200000);
}
if ($nominal <= 0) {
    echo json_encode(['ok' => false, 'pesan' => 'Nominal biaya belum diatur di Pengaturan Sistem.']);
    exit;
}

/* ------------------------------------------------------------------
 * 1) Cek idempotency — key ini sudah pernah diproses?
 *    Bila snap_token masih berlaku → kembalikan langsung tanpa API call.
 * ------------------------------------------------------------------ */
$existing = pembayaran_cari_idem($idemKey);
if ($existing && $existing['snap_token'] && $existing['snap_expires_at']
    && strtotime($existing['snap_expires_at']) > time()) {
    echo json_encode([
        'ok'           => true,
        'reused'       => true,
        'snap_token'   => $existing['snap_token'],
        'order_id'     => $existing['order_id'],
        'client_key'   => $clientKey,
        'snap_url'     => midtrans_snap_base(),
    ]);
    exit;
}

/* ------------------------------------------------------------------
 * 2) Siapkan baris pembayaran (FOR UPDATE — serialize paralel).
 *    Key yang sama → baris & order_id lama dipakai ulang.
 * ------------------------------------------------------------------ */
$orderId = midtrans_order_id($pendaftarId, $jenis);
$siap = pembayaran_siapkan_online($pendaftarId, $jenis, $nominal, $orderId, $idemKey, null, null, $angsuranKe > 0 ? $angsuranKe : null);

if (!$siap['ok']) {
    echo json_encode(['ok' => false, 'pesan' => $siap['pesan'] ?? 'Gagal menyiapkan data pembayaran.']);
    exit;
}

$byrId   = $siap['id'];
$orderId = $siap['order_id'];

/* Reuse dengan snap_token masih berlaku → tidak perlu panggil Midtrans. */
if (!empty($siap['reused']) && !empty($siap['snap_token'])
    && !empty($siap['snap_expires_at']) && strtotime($siap['snap_expires_at']) > time()) {
    echo json_encode([
        'ok'           => true,
        'reused'       => true,
        'snap_token'   => $siap['snap_token'],
        'order_id'     => $orderId,
        'client_key'   => $clientKey,
        'snap_url'     => midtrans_snap_base(),
    ]);
    exit;
}

/* ------------------------------------------------------------------
 * 3) Panggil Midtrans Snap (pakai order_id yang sama — idempotent di
 *    sisi Midtrans juga bila order_id sudah pernah dibuat).
 * ------------------------------------------------------------------ */
$r = midtrans_buat_transaksi([
    'order_id'       => $orderId,
    'gross_amount'   => $nominal,
    'nama_siswa'     => $profil['nama_siswa'] ?? '-',
    'no_pendaftaran' => $profil['no_pendaftaran'] ?? '',
    'jenis'          => $jenis,
    'angsuran_ke'    => $angsuranKe > 0 ? $angsuranKe : null,
    'phone'          => $profil['no_whatsapp'] ?? null,
]);

if (!$r['ok']) {
    echo json_encode(['ok' => false, 'pesan' => $r['pesan']]);
    exit;
}

/* Cache snap token (berlaku ±30 menit) agar retry tidak membuat transaksi baru. */
$expiresAt = date('Y-m-d H:i:s', time() + 1800);
pembayaran_simpan_snap($byrId, $orderId, $r['snap_token'], $expiresAt);

echo json_encode([
    'ok'           => true,
    'reused'       => !empty($siap['reused']),
    'snap_token'   => $r['snap_token'],
    'order_id'     => $orderId,
    'client_key'   => $clientKey,
    'snap_url'     => midtrans_snap_base(),
    'redirect_url' => $r['redirect_url'] ?? '',
]);
