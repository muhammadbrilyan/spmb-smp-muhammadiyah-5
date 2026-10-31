<?php
/**
 * PENGINGAT JATUH TEMPO ANGSURAN via CLI/cron.
 *
 * Default DRY-RUN (hanya melaporkan kandidat). Tambah --jalankan untuk
 * membuat notifikasi in-app dan mengirim WhatsApp ke orang tua/wali
 * (bila diaktifkan di Pengaturan Sistem). --tanpa-wa memaksa hanya
 * notifikasi in-app.
 *
 * CLI-ONLY. Pakai:
 *   php tools/pengingat-angsuran.php
 *   php tools/pengingat-angsuran.php --jalankan
 *   php tools/pengingat-angsuran.php --jalankan --tanpa-wa
 *
 * Cron harian (pukul 07.00 WIB):
 *   0 7 * * *  /usr/local/bin/php /path/ke/tools/pengingat-angsuran.php --jalankan
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$arg      = array_slice($argv, 1);
$jalankan = in_array('--jalankan', $arg, true);
$tanpaWa  = in_array('--tanpa-wa', $arg, true);

fwrite(STDOUT, 'PENGINGAT JATUH TEMPO ANGSURAN — ' . date('Y-m-d H:i:s') . "\n");
fwrite(STDOUT, 'Mode: ' . ($jalankan ? 'EKSEKUSI' : 'DRY-RUN (tambah --jalankan utk mengirim)')
    . ($tanpaWa ? ' · tanpa WhatsApp' : '') . "\n");
fwrite(STDOUT, 'Pengingat H-' . angsuran_hari_pengingat()
    . ' · WhatsApp: ' . (angsuran_pengingat_wa_aktif() ? 'aktif' : 'nonaktif') . "\n\n");

if (!db_aktif()) {
    fwrite(STDERR, "GAGAL: koneksi database tidak tersedia.\n");
    exit(1);
}

$daftar = angsuran_pendaftar_lulus();
if (!$daftar) {
    fwrite(STDOUT, "Tidak ada pendaftar LULUS — hasil seleksi belum diumumkan atau belum ada kelulusan.\n");
    exit(0);
}

if (!$jalankan) {
    $jumlah = 0;
    foreach ($daftar as $r) {
        foreach (angsuran_pengingat_kandidat(angsuran_ringkas($r['id'])) as $k) {
            $jumlah++;
            $lama = notif_siswa_cari_ref($r['id'], $k['ref']);
            fwrite(STDOUT, '  ' . $r['no_pendaftaran'] . ' — ' . $r['nama_siswa']
                . ' · ' . $k['judul'] . ' (' . rupiah($k['sisa']) . ')'
                . ($lama ? ' [notifikasi sudah ada]' : '') . "\n");
        }
    }
    fwrite(STDOUT, "\n{$jumlah} pengingat kandidat.\n");
    exit(0);
}

$total = angsuran_pengingat_semua(url('app/siswa/keuangan.php'), $tanpaWa ? false : null);
fwrite(STDOUT, 'Siswa diingatkan: ' . (int) $total['siswa'] . "\n");
fwrite(STDOUT, 'Notifikasi in-app baru: ' . (int) $total['dibuat'] . "\n");
fwrite(STDOUT, 'WhatsApp terkirim: ' . (int) $total['wa']
    . ' · gagal: ' . (int) $total['wa_gagal'] . "\n");
exit(0);
