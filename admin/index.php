<?php
/**
 * ALAMAT MASUK PIHAK SEKOLAH — /mualimku/admin
 *
 * Hanya jembatan ke halaman login staf yang sebenarnya ada di
 * app/auth/login-sekolah.php. Tidak ada logika di sini: cek sesi,
 * validasi password, dan pemisahan role tetap di berkas aslinya.
 *
 * PENTING: alamat pendek ini cuma memudahkan staf mengingat satu URL,
 * BUKAN pembatas akses. Hak akses ditegakkan require_role() di tiap
 * halaman — siapa pun yang menebak URL /admin tetap harus login.
 */
require_once __DIR__ . '/../app/auth/login-sekolah.php';
