<?php
/**
 * ENDPOINT AJAX — buat Snap Token untuk pembayaran online.
 * POST (login siswa): pendaftar_id, jenis (FORMULIR|UANG_MASUK), idem_key
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

    /* Fase 11 A1: UANG_MASUK hanya bila tahap minimal BERKAS_TERVERIFIKASI
     * (urutan tahap resmi — tahap_minimal()) dan belum pernah VERIFIED/LUNAS. */
    if ($jenis === 'UANG_MASUK') {
        if (!tahap_minimal($profil['tahap'] ?? '', 'BERKAS_TERVERIFIKASI')) {
            echo json_encode(['ok' => false, 'pesan' => 'Uang masuk dapat dibayar setelah berkas terverifikasi panitia.']);
            exit;
        }
        $sudahUm = (int) (q_col(
            "SELECT COUNT(*) FROM pembayaran
              WHERE pendaftar_id = ? AND jenis_pembayaran = 'UANG_MASUK'
                AND status IN ('VERIFIED','LUNAS')",
            [$pendaftarId]
        ) ?? 0);
        if ($sudahUm > 0) {
            echo json_encode(['ok' => false, 'pesan' => 'Uang masuk untuk pendaftar ini sudah terverifikasi/lunas.']);
            exit;
        }
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

/* Nominal dari CMS. */
$nominal = $jenis === 'UANG_MASUK'
    ? (float) ($PENGATURAN['estimasi_uang_masuk'] ?? 0)
    : (float) ($PENGATURAN['biaya_formulir'] ?? 200000);
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
$siap = pembayaran_siapkan_online($pendaftarId, $jenis, $nominal, $orderId, $idemKey);

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
