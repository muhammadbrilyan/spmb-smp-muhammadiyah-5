<?php
/* ==================================================================
 * API POLLING JUMLAH NOTIFIKASI BELUM DIBACA (Fase 3).
 * Dipanggil sidebar tiap 60 detik oleh staf yang login.
 * Respons sangat ringan: satu COUNT(*), tanpa library tambahan.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'jumlah' => 0]);
    exit;
}

$role = user_role();
if ($role === 'siswa') {
    /* Notifikasi siswa bersifat per penerima (notifikasi_siswa). */
    $pid = (string) (q_col(
        'SELECT id FROM pendaftar WHERE user_id = ? LIMIT 1',
        [(string) (current_user()['id'] ?? '')]
    ) ?? '');
    echo json_encode(['ok' => true, 'jumlah' => $pid !== '' ? notif_siswa_belum_dibaca($pid) : 0]);
    exit;
}

if (!in_array($role, ['operator', 'admin', 'bendahara', 'kepsek'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'jumlah' => 0]);
    exit;
}

echo json_encode(['ok' => true, 'jumlah' => notif_belum_dibaca()]);
