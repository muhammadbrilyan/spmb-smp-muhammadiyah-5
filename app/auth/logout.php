<?php
/**
 * Keluar dari sesi — hanya menerima POST dengan token CSRF
 * (mencegah "logout paksa" lewat tautan GET/CSRF).
 */
require_once __DIR__ . '/../../includes/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_cek($_POST['_csrf'] ?? '')) {
    http_response_code(405);
    exit('Method not allowed. Gunakan tombol Keluar di aplikasi.');
}

$roleTerakhir = user_role();
logout();

/* Staf sekolah kembali ke login sekolah, siswa ke portal siswa. */
if (in_array($roleTerakhir, ['operator', 'admin', 'bendahara', 'kepsek'], true)) {
    header('Location: ' . url('admin/'));
} else {
    header('Location: ' . url('app/auth/login.php'));
}
exit;
