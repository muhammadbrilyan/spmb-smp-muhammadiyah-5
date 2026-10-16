<?php
/**
 * Fase 11 B3 — Cadangan berkas unggahan pendaftar (storage/dokumen).
 *
 * Output : storage/backup/berkas-<YYYYMMDD-HHMMSS>.zip
 * Rotasi : file lebih tua dari 14 hari otomatis dihapus.
 * Berkas ini MEMUAT dokumen pribadi (KK, akta, bukti transfer) —
 * jangan dibagikan; folder storage/ diblokir dari web (.htaccess).
 *
 * CLI-ONLY. Pakai:
 *   php tools/backup-berkas.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function gagal_backup_berkas($pesan)
{
    fwrite(STDERR, 'GAGAL: ' . $pesan . "\n");
    exit(1);
}

if (!class_exists('ZipArchive')) {
    gagal_backup_berkas('Ekstensi PHP "zip" belum aktif (ZipArchive tidak ditemukan).');
}

require_once dirname(__DIR__) . '/includes/config.php';   /* storage_path(), STORAGE_DIR */

$src  = storage_path('dokumen');
$dir  = storage_path('backup');

if (!is_dir($src)) {
    fwrite(STDOUT, "CADANGAN BERKAS — tidak ada folder storage/dokumen, tidak ada yang dicadangkan.\n");
    exit(0);
}
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    gagal_backup_berkas('Gagal membuat folder storage/backup.');
}

$ts  = date('Ymd-His');
$out = $dir . '/berkas-' . $ts . '.zip';

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    gagal_backup_berkas('Gagal membuat file zip cadangan.');
}

$jumlah = 0;
$ukuran = 0;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $abs = $file->getPathname();
    $rel = 'dokumen/' . ltrim(str_replace('\\', '/', substr($abs, strlen($src))), '/');
    if (!$zip->addFile($abs, $rel)) {
        $zip->close();
        @unlink($out);
        gagal_backup_berkas('Gagal menambahkan file ke zip: ' . $rel);
    }
    $jumlah++;
    $ukuran += $file->getSize();
}
$zip->close();

if ($jumlah === 0) {
    @unlink($out);
    fwrite(STDOUT, "CADANGAN BERKAS — storage/dokumen kosong, tidak ada file yang dicadangkan.\n");
    exit(0);
}
if (function_exists('chmod')) {
    @chmod($out, 0600);
}

/* Rotasi: simpan 14 hari terakhir. */
$hapus = 0;
foreach (glob($dir . '/berkas-*.zip') ?: [] as $f) {
    if (@filemtime($f) < time() - 14 * 86400) {
        @unlink($f);
        $hapus++;
    }
}
$sisa = count(glob($dir . '/berkas-*.zip') ?: []);

fwrite(STDOUT, "CADANGAN BERKAS BERHASIL\n"
    . '  File   : storage/backup/' . basename($out) . "\n"
    . '  Isi    : ' . $jumlah . ' file (' . round($ukuran / 1024, 1) . ' KB) -> zip '
    . round(filesize($out) / 1024, 1) . " KB\n"
    . '  Rotasi : ' . $sisa . ' cadangan tersimpan (retensi 14 hari, ' . $hapus . " dihapus)\n"
    . "Catatan: MEMUAT dokumen pribadi pendaftar — jangan dibagikan.\n");
exit(0);
