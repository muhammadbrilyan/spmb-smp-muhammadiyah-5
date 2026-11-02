<?php
/* ==================================================================
 * PESERTA UJIAN digabung ke jadwal.php (menu "Jadwal Ujian").
 *
 * Halaman lama dibiarkan ada sebagai pengalih: tautan lama, notifikasi
 * in-app, dan markah browser pengguna harus tetap jalan.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

header('Location: ' . url('app/operator/jadwal.php') . '#peserta');
exit;
