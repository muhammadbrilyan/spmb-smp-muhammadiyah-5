<?php
/**
 * WEBHOOK NOTIFIKASI MIDTRANS.
 * Dipanggil otomatis oleh Midtrans saat status transaksi berubah.
 * URL: http://localhost/mualimku/app/api/midtrans-callback.php
 *
 * Keamanan: signature HMAC SHA512 diverifikasi sebelum update status.
 */
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Method not allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$notif = json_decode($raw, true);
if (!is_array($notif)) {
    http_response_code(400);
    echo json_encode(['message' => 'Invalid JSON']);
    exit;
}

$serverKey = midtrans_server_key();
if ($serverKey === null) {
    /* Respons netral — jangan bocorkan status konfigurasi gateway.
     * (Callback tetap memproses order yang sudah ada walau toggle CMS mati;
     *  tanpa server key signature tak bisa diverifikasi sehingga ditolak.) */
    http_response_code(503);
    echo json_encode(['message' => 'Service unavailable']);
    exit;
}

/* --- Verifikasi signature --- */
if (!midtrans_verifikasi_notifikasi($notif, $serverKey)) {
    http_response_code(403);
    echo json_encode(['message' => 'Invalid signature']);
    exit;
}

$orderId       = (string) ($notif['order_id'] ?? '');
$statusMid     = (string) ($notif['transaction_status'] ?? '');
$paymentMethod = (string) ($notif['payment_type'] ?? '');
$statusCode    = (string) ($notif['status_code'] ?? '');

if ($orderId === '' || $statusMid === '') {
    http_response_code(400);
    echo json_encode(['message' => 'Missing order_id / transaction_status']);
    exit;
}

$db = db();
if ($db === null) {
    http_response_code(500);
    echo json_encode(['message' => 'Database unavailable']);
    exit;
}

/* Cari baris pembayaran berdasarkan order_id — FOR UPDATE agar notifikasi
 * paralel untuk order yang sama saling menunggu (serialize). */
try {
    $db->beginTransaction();
    $sel = $db->prepare('SELECT id, pendaftar_id, status, nominal, midtrans_status, paid_at FROM pembayaran WHERE order_id = ? LIMIT 1 FOR UPDATE');
    $sel->execute([$orderId]);
    $row = $sel->fetch();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['message' => 'DB error']);
    exit;
}
if (!$row) {
    try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
    http_response_code(404);
    echo json_encode(['message' => 'Order not found']);
    exit;
}
$byrId = $row['id'];
$statusLama = (string) $row['status'];
$nominalDb  = (float) $row['nominal'];

/* --- Verifikasi nominal: gross_amount notifikasi HARUS sama dengan
 *     nominal di database (cegah manipulasi harga via webhook palsu
 *     yang lolos signature bila server key pernah bocor). --- */
$grossNotif = (float) ($notif['gross_amount'] ?? 0);
if ($nominalDb <= 0 || abs($grossNotif - $nominalDb) > 0.5) {
    try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
    http_response_code(400);
    echo json_encode([
        'message' => 'Nominal tidak sesuai',
        'order_id' => $orderId,
        'expected' => $nominalDb,
        'received' => $grossNotif,
    ]);
    exit;
}

$statusBaru = midtrans_map_status($statusMid);

/* --- Idempotensi: notifikasi ganda / retry dengan status yang sama ---
 * Bila midtrans_status & status baris sudah identik dengan notifikasi ini,
 * tidak ada efek tambahan — kembalikan OK tanpa UPDATE. */
if ((string) $row['midtrans_status'] === $statusMid
    && $statusLama === $statusBaru
    && ($statusBaru !== 'VERIFIED' || $row['paid_at'] !== null)) {
    try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
    http_response_code(200);
    echo json_encode(['message' => 'OK (already processed)', 'order_id' => $orderId, 'status' => $statusMid]);
    exit;
}

/* --- Larang penurunan status ---
 * Status final (VERIFIED/LUNAS/ANGSURAN_*) tidak boleh ditimpa
 * notifikasi terlambat berupa expired/deny/cancel/pending. */
$rank = ['PENDING' => 0, 'REJECTED' => 1, 'VERIFIED' => 2, 'LUNAS' => 3, 'ANGSURAN_1' => 2, 'ANGSURAN_2' => 3];
$rankLama = $rank[$statusLama] ?? 0;
$rankBaru = $rank[$statusBaru] ?? 0;
if ($rankBaru < $rankLama) {
    try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
    http_response_code(200);
    echo json_encode([
        'message' => 'Ignored: status tidak boleh turun',
        'order_id' => $orderId,
        'current'  => $statusLama,
        'incoming' => $statusMid,
    ]);
    exit;
}

/* Update status pembayaran. */
$paidAt = ($statusBaru === 'VERIFIED') ? date('Y-m-d H:i:s') : null;
$ket    = 'Midtrans: ' . $statusMid . ($paymentMethod !== '' ? ' via ' . $paymentMethod : '');

try {
    $stmt = $db->prepare(
        'UPDATE pembayaran SET
            midtrans_status = ?, payment_method = ?, status = ?,
            keterangan = ?, paid_at = COALESCE(?, paid_at)
         WHERE id = ?'
    );
    $stmt->execute([$statusMid, $paymentMethod ?: null, $statusBaru, $ket, $paidAt, $byrId]);

    /* Naikkan tahap pendaftar bila pembayaran formulir terverifikasi. */
    if ($statusBaru === 'VERIFIED' || $statusBaru === 'LUNAS') {
        $db->prepare(
            'UPDATE pendaftar SET tahap = "BAYAR_TERVERIFIKASI"
              WHERE id = ?
                AND tahap IN ("MENUNGGU_PEMBAYARAN", "MENUNGGU_VERIFIKASI_BAYAR")
                AND EXISTS (
                    SELECT 1 FROM pembayaran b2
                     WHERE b2.pendaftar_id = pendaftar.id
                       AND b2.jenis_pembayaran = "FORMULIR"
                       AND b2.status IN ("VERIFIED", "LUNAS")
                )'
        )->execute([$row['pendaftar_id']]);
    }

    $db->commit();
} catch (Throwable $e) {
    try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
    http_response_code(500);
    echo json_encode(['message' => 'DB error']);
    exit;
}

/* Notifikasi operator (Fase 3) — tidak boleh memengaruhi respons webhook. */
if ($statusBaru === 'VERIFIED' || $statusBaru === 'LUNAS') {
    try {
        $info = q_all(
            'SELECT nama_siswa, no_pendaftaran FROM pendaftar WHERE id = ? LIMIT 1',
            [$row['pendaftar_id']]
        );
        $info = $info ? $info[0] : null;
        if ($info !== null) {
            $tautanVerif = url_abs('app/bendahara/index.php');
            notif_operator(
                'PEMBAYARAN_GATEWAY_LUNAS',
                $row['pendaftar_id'],
                "Pembayaran gateway LUNAS (otomatis terverifikasi).\n"
                . "Nama : {$info['nama_siswa']}\n"
                . "No. Pendaftaran : {$info['no_pendaftaran']}\n"
                . "Status : {$statusMid}\n"
                . "Detail: {$tautanVerif}"
            );
        }
    } catch (Throwable $e) {
        /* abaikan */
    }

    /* Fase 4: kirim akun otomatis setelah gateway lunas (toggle CMS, default nonaktif). */
    try {
        kirim_akun_gateway_otomatis($row['pendaftar_id']);
    } catch (Throwable $e) {
        /* abaikan — webhook tetap 200 */
    }
}

http_response_code(200);
echo json_encode(['message' => 'OK', 'order_id' => $orderId, 'status' => $statusMid]);
