<?php
/**
 * LAPISAN QUERY — loader modular.
 *
 * File asli (1.300+ baris) dipecah per domain ke folder includes/queries/.
 * Semua fungsi tetap available secara global — tidak ada perubahan API.
 * Dipanggil dari includes/config.php setelah cms.php.
 */

require_once __DIR__ . '/queries/core.php';
require_once __DIR__ . '/queries/pendaftar.php';
require_once __DIR__ . '/queries/dokumen.php';
require_once __DIR__ . '/queries/jadwal.php';
require_once __DIR__ . '/queries/peserta.php';
require_once __DIR__ . '/queries/seleksi.php';
require_once __DIR__ . '/queries/pembayaran.php';
require_once __DIR__ . '/queries/siswa.php';
require_once __DIR__ . '/queries/kelas.php';
require_once __DIR__ . '/queries/akun.php';
require_once __DIR__ . '/queries/penempatan.php';
require_once __DIR__ . '/queries/pengumuman.php';
require_once __DIR__ . '/queries/publik.php';
require_once __DIR__ . '/queries/export.php';
require_once __DIR__ . '/queries/retensi.php';
