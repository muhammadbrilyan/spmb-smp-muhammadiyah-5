<?php
/**
 * Fase 12 E4 — Eksekusi retensi berkas pribadi via CLI (cron).
 *
 * Default: DRY-RUN (hanya melaporkan kandidat, tidak menghapus apa pun).
 * Eksekusi nyata: tambah argumen --jalankan — hanya dijalankan bila
 * retensi_aktif = 1 di pengaturan_sekolah.
 *
 * CLI-ONLY. Pakai:
 *   php tools/retensi.php                (dry-run)
 *   php tools/retensi.php --jalankan     (eksekusi nyata)
 *
 * Cron disarankan (bulanan, awal bulan, pukul 01.00 WIB):
 *   0 1 1 * *  /usr/local/bin/php /path/ke/tools/retensi.php --jalankan
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

$jalankan = in_array('--jalankan', array_slice($argv, 1), true);

$P = retensi_pengaturan();
$kategori = [
    'tidak_lulus' => ['judul' => 'Tidak lulus seleksi',     'bulan' => $P['bulan_tidak_lulus'], 'alasan' => 'cli_tidak_lulus'],
    'batal'       => ['judul' => 'Ditolak/dibatalkan',      'bulan' => $P['bulan_batal'],       'alasan' => 'cli_batal'],
    'lulus_arsip' => ['judul' => 'Lulus — arsip berkas',    'bulan' => $P['bulan_lulus_arsip'], 'alasan' => 'cli_lulus_arsip'],
];

fwrite(STDOUT, "RETENSI BERKAS PRIBADI — " . date('Y-m-d H:i:s') . " WIB\n");
fwrite(STDOUT, 'Mode: ' . ($jalankan ? 'EKSEKUSI NYATA' : 'DRY-RUN (tambah --jalankan utk eksekusi)') . "\n");
fwrite(STDOUT, 'Pengaturan: retensi_aktif=' . ($P['aktif'] ? '1' : '0') . "\n\n");

if ($jalankan && !$P['aktif']) {
    fwrite(STDERR, "GAGAL: retensi nonaktif di pengaturan_sekolah — aktifkan dulu via halaman operator (Retensi Data) sebelum --jalankan.\n");
    exit(1);
}

if (!$P['aktif']) {
    fwrite(STDOUT, "CATATAN: retensi NONAKTIF — dry-run tetap menampilkan kandidat bila batas bulan terisi.\n\n");
}

$totalKandidat = 0;
$totalBerkas   = 0;
$totalEksekusi  = 0;

foreach ($kategori as $k => $kat) {
    if ($kat['bulan'] === null) {
        fwrite(STDOUT, "[{$kat['judul']}] batas bulan belum diisi — dilewati.\n\n");
        continue;
    }
    $kandidat = retensi_kandidat($k, $kat['bulan']);
    $totalKandidat += count($kandidat);
    $jmlBerkas = array_sum(array_column($kandidat, 'jumlah_berkas'));
    $totalBerkas += $jmlBerkas;

    fwrite(STDOUT, "[{$kat['judul']}] ≥ {$kat['bulan']} bulan — "
        . count($kandidat) . ' pendaftar, ' . $jmlBerkas . " berkas\n");

    if (!$kandidat) {
        fwrite(STDOUT, "  Tidak ada kandidat.\n\n");
        continue;
    }

    if (!$jalankan) {
        foreach ($kandidat as $c) {
            fwrite(STDOUT, '  DRY ' . $c['no_pendaftaran'] . ' — ' . $c['nama_siswa']
                . ' (' . $c['jumlah_berkas'] . " berkas, daftar " . substr((string) $c['created_at'], 0, 10) . ")\n");
        }
        fwrite(STDOUT, "\n");
        continue;
    }

    foreach ($kandidat as $c) {
        $hasil = retensi_hapus_berkas($c['id'], $kat['alasan'], null);
        if ($hasil['ok']) {
            $totalEksekusi++;
            fwrite(STDOUT, '  HAPUS ' . $c['no_pendaftaran'] . ' — ' . $hasil['jumlah'] . ' berkas'
                . (!empty($hasil['gagal']) ? ' (' . $hasil['gagal'] . ' gagal/dilewati)' : '')
                . (isset($hasil['jejak']) && !$hasil['jejak'] ? ' [PERINGATAN: jejak log gagal]' : '')
                . "\n");
        } else {
            fwrite(STDOUT, '  GAGAL ' . $c['no_pendaftaran'] . ' — ' . ($hasil['pesan'] ?? 'tidak diketahui') . "\n");
        }
    }
    fwrite(STDOUT, "\n");
}

fwrite(STDOUT, "Ringkasan: {$totalKandidat} kandidat, {$totalBerkas} berkas terdeteksi"
    . ($jalankan ? ", {$totalEksekusi} pendaftar dieksekusi" : ' (dry-run)') . ".\n");
exit(0);
