<?php
/**
 * Halaman Pengumuman lama — sudah digabung ke Notifikasi & Pengumuman.
 * Redirect kecil ini menjaga tautan/bookmark lama (termasuk #pgn-...)
 * tetap sampai ke halaman barunya.
 */
require_once __DIR__ . '/../../includes/config.php';

$tujuan = url('app/siswa/notifikasi.php');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="0;url=<?= e($tujuan) ?>">
<title>Mengalihkan…</title>
<script>location.replace(<?= json_encode($tujuan) ?> + (location.hash || ''));</script>
</head>
<body></body>
</html>
