<?php
/**
 * GATE UNDUH BERKAS PRIBADI (KK, akta, ijazah, bukti transfer).
 *
 * Berkas fisik ada di storage/dokumen/ (di luar tree publik assets/) dan
 * folder storage/ ditolak total oleh .htaccess. Endpoint ini satu-satunya
 * jalur sah untuk mengunduh/melihat berkas.
 *
 * Aturan akses:
 *   - Siswa  : hanya boleh mengunduh berkas miliknya sendiri
 *              (dicek lewat tabel dokumen / pembayaran).
 *   - Staff  : operator/admin/bendahara saja boleh semua berkas terdaftar.
 *              [Fase 10] Kepsek TIDAK termasuk — berkas pribadi (KK, akta,
 *              ijazah, bukti transfer) di luar kewenangannya diunduh.
 *   - Tamu   : ditolak.
 */
require_once __DIR__ . '/../../includes/config.php';

header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; media-src \'self\'');

if (!is_logged_in()) {
    http_response_code(401);
    exit('Akses ditolak: silakan login terlebih dahulu.');
}

$nama  = basename((string) ($_GET['f'] ?? ''));
$role  = user_role();
$staff = in_array($role, ['operator', 'admin', 'bendahara'], true);

if ($nama === '' || !preg_match('/^(dok|bukti)-[A-Za-z0-9._-]+\.(jpg|jpeg|png|pdf)$/i', $nama)) {
    http_response_code(400);
    exit('Nama berkas tidak valid.');
}

$abs = dirname(__DIR__, 2) . '/storage/dokumen/' . $nama;
if (!is_file($abs)) {
    http_response_code(404);
    exit('Berkas tidak ditemukan.');
}

/* Siswa: pastikan berkas memang tercatat miliknya. */
if (!$staff) {
    $user = current_user();
    if (empty($user['id'])) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
    $relBaru = 'storage/dokumen/' . $nama;
    $relLama = 'assets/img/uploads/dokumen/' . $nama;
    $db  = db();
    $ok  = false;
    if ($db !== null) {
        $stmt = $db->prepare(
            'SELECT 1
               FROM dokumen d
               JOIN pendaftar p ON p.id = d.pendaftar_id
              WHERE d.file_url IN (?, ?) AND p.user_id = ?
              UNION ALL
             SELECT 1
               FROM pembayaran b
               JOIN pendaftar p2 ON p2.id = b.pendaftar_id
              WHERE b.bukti_transfer_url IN (?, ?) AND p2.user_id = ?
              LIMIT 1'
        );
        $stmt->execute([$relBaru, $relLama, $user['id'], $relBaru, $relLama, $user['id']]);
        $ok = (bool) $stmt->fetchColumn();
    }
    if (!$ok) {
        http_response_code(403);
        exit('Akses ditolak: berkas bukan milik Anda.');
    }
}

$mime = match (strtolower(pathinfo($nama, PATHINFO_EXTENSION))) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png'         => 'image/png',
    'pdf'         => 'application/pdf',
    default       => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Content-Disposition: inline; filename="' . $nama . '"');
header('Cache-Control: private, no-store');

readfile($abs);
exit;
