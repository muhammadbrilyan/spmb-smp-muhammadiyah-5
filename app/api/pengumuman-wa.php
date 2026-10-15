<?php
/* ==================================================================
 * API BATCH WA PENGUMUMAN (Fase 8) — dipanggil via AJAX dari
 * app/operator/pengumuman.php. Maksimal 10 nomor per request.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

if (!is_logged_in() || !in_array(user_role(), ['operator', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Akses ditolak.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Metode tidak diizinkan.']);
    exit;
}

if (!csrf_cek($_POST['_csrf'] ?? '')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Formulir kedaluwarsa. Muat ulang halaman.']);
    exit;
}

$mode   = (string) ($_POST['mode'] ?? 'kirim');
$offset = (int) ($_POST['offset'] ?? 0);

echo json_encode(pengumuman_wa_batch($offset, $mode));
