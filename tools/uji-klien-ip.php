<?php
/**
 * Fase 12 C3 — Uji klien_ip() (tanpa framework, tanpa DB).
 *
 * CLI-ONLY. Pakai:
 *   php tools/uji-klien-ip.php
 * Keluar dengan kode 1 bila ada kasus yang gagal.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$gagal = 0;
$tampil = function ($label, $diharapkan, $aktual) use (&$gagal) {
    $ok = ($diharapkan === $aktual);
    if (!$ok) {
        $gagal++;
    }
    printf("  [%s] %-52s harap=%-18s dapat=%s\n", $ok ? 'OK' : 'GAGAL', $label, $diharapkan, $aktual);
};

echo "UJI klien_ip() — 12 kasus\n";

/* 1. Tanpa proxy tepercaya: semua header diabaikan. */
$tampil('tanpa proxy, XFF ada (dihiraukan)',
    '203.0.113.5',
    klien_ip(['REMOTE_ADDR' => '203.0.113.5', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'], '', 'X-Forwarded-For'));

/* 2. Proxy tepercaya valid: ambil XFF. */
$tampil('proxy tepercaya + XFF sah',
    '198.51.100.7',
    klien_ip(['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'], '192.0.2.1', 'X-Forwarded-For'));

/* 3. Header dipalsukan dari IP BUKAN proxy: diabaikan. */
$tampil('pemalsuan XFF dari IP bukan proxy',
    '203.0.113.9',
    klien_ip(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'], '192.0.2.1', 'X-Forwarded-For'));

/* 4. Rantai XFF berlapis: lewati proxy tepercaya dari kanan. */
$tampil('rantai XFF (lewati proxy tepercaya)',
    '198.51.100.7',
    klien_ip(['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7, 192.0.2.5'], '192.0.2.0/24', 'X-Forwarded-For'));

/* 5. Seluruh rantai berisi proxy tepercaya -> REMOTE_ADDR. */
$tampil('rantai hanya berisi proxy tepercaya',
    '192.0.2.1',
    klien_ip(['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_FOR' => '192.0.2.5, 192.0.2.9'], '192.0.2.0/24', 'X-Forwarded-For'));

/* 6. CIDR IPv6 + CF-Connecting-IP. */
$tampil('proxy IPv6 + CF-Connecting-IP',
    '2001:db8:aaaa::5',
    klien_ip(['REMOTE_ADDR' => '2001:db8::1', 'HTTP_CF_CONNECTING_IP' => '2001:db8:aaaa::5'], '2001:db8::/32', 'CF-Connecting-IP'));

/* 7. CF-Connecting-IP dari remote BUKAN proxy: diabaikan. */
$tampil('CF-Connecting-IP dari IP bukan proxy',
    '203.0.113.9',
    klien_ip(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_CF_CONNECTING_IP' => '198.51.100.7'], '192.0.2.1', 'CF-Connecting-IP'));

/* 8. Header sampah: nilai tidak valid dilewati, ambil yang sah. */
$tampil('XFF dgn nilai sampah di kiri',
    '198.51.100.7',
    klien_ip(['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_FOR' => 'bukan-ip, 198.51.100.7'], '192.0.2.1', 'X-Forwarded-For'));

/* 9. Seluruh header sampah -> REMOTE_ADDR. */
$tampil('XFF seluruhnya sampah',
    '192.0.2.1',
    klien_ip(['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_FOR' => '!!!, ???'], '192.0.2.1', 'X-Forwarded-For'));

/* 10. CF-Connecting-IP sampah -> REMOTE_ADDR. */
$tampil('CF-Connecting-IP sampah',
    '192.0.2.1',
    klien_ip(['REMOTE_ADDR' => '192.0.2.1', 'HTTP_CF_CONNECTING_IP' => 'bukan-ip'], '192.0.2.1', 'CF-Connecting-IP'));

/* 11. Daftar campuran CIDR + IP tunggal (IPv4). */
$tampil('daftar campuran CIDR+IP tunggal',
    '198.51.100.9',
    klien_ip(['REMOTE_ADDR' => '10.0.0.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9'], '10.0.0.0/8, 192.0.2.1', 'X-Forwarded-For'));

/* 12. REMOTE_ADDR kosong/tidak sah -> 0.0.0.0. */
$tampil('REMOTE_ADDR tidak sah',
    '0.0.0.0',
    klien_ip(['REMOTE_ADDR' => 'bukan-ip', 'HTTP_X_FORWARDED_FOR' => '198.51.100.7'], '192.0.2.1', 'X-Forwarded-For'));

if ($gagal > 0) {
    fwrite(STDERR, "GAGAL: {$gagal} kasus tidak sesuai harapan.\n");
    exit(1);
}
echo "UJI klien_ip() LULUS — 12/12 kasus sesuai.\n";
exit(0);
