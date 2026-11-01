<?php
/* ==================================================================
 * [FASE 14] VERIFIKASI BERKAS dipindah ke verifikasi.php (tab "berkas").
 *
 * Berkas lama dibiarkan ada sebagai pengalih: tautan yang sudah terkirim
 * lewat WhatsApp/notifikasi dan markah browser pengguna harus tetap jalan.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

$id = trim((string) ($_GET['id'] ?? ''));
header('Location: verifikasi.php?tab=berkas' . ($id !== '' ? '&id=' . rawurlencode($id) : ''));
exit;
