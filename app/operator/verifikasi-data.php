<?php
/* ==================================================================
 * [FASE 14] VERIFIKASI DATA dipindah ke verifikasi.php (tab "data").
 *
 * Berkas lama dibiarkan ada sebagai pengalih agar tautan lama
 * (notifikasi, halaman Data Pendaftar, markah browser) tetap berfungsi.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator']);

$id = trim((string) ($_GET['id'] ?? ''));
header('Location: verifikasi.php?tab=data' . ($id !== '' ? '&id=' . rawurlencode($id) : ''));
exit;
