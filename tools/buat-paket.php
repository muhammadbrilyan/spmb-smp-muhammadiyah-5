<?php
/**
 * Fase 11 B1 — Buat paket rilis (zip) TANPA file rahasia / pribadi / lokal.
 *
 * Dieksklusi dari paket:
 *   - .env                    (kredensial — hanya .env.example yang ikut)
 *   - .git/                   (riwayat lokal)
 *   - dist/                   (folder output)
 *   - storage/dokumen/*       (unggahan pribadi; .gitkeep saja yang ikut)
 *   - storage/backup/         (hasil cadangan)
 *   - "hak akses*", tmp-*, *.bak, *.old, *.swp, *~, Thumbs.db, .DS_Store
 *
 * Setelah zip dibuat, paket DIVERIFIKASI ulang — bila masih ada file terlarang,
 * zip dihapus dan tool keluar dengan kode error.
 *
 * CLI-ONLY (diblokir dari browser). Pakai:
 *   php tools/buat-paket.php           -> dist/mualimku-<YYYYMMDD>.zip
 *   php tools/buat-paket.php v1.2      -> dist/mualimku-v1.2.zip
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function gagal_paket($pesan)
{
    fwrite(STDERR, 'GAGAL: ' . $pesan . "\n");
    exit(1);
}

$root = dirname(__DIR__);

/* Nama versi harus aman untuk nama file. */
$versi = isset($argv[1]) && trim((string) $argv[1]) !== ''
    ? trim((string) $argv[1])
    : date('Ymd');
$versi = preg_replace('/[^A-Za-z0-9._-]/', '-', $versi);
if ($versi === '' || $versi === '-') {
    gagal_paket('Nama versi tidak valid.');
}

if (!class_exists('ZipArchive')) {
    gagal_paket('Ekstensi PHP "zip" belum aktif (ZipArchive tidak ditemukan).');
}

$dist = $root . '/dist';
if (!is_dir($dist) && !mkdir($dist, 0755, true)) {
    gagal_paket('Gagal membuat folder dist/.');
}
$zipFile = $dist . '/mualimku-' . $versi . '.zip';
if (is_file($zipFile)) {
    unlink($zipFile);
}

/* ---------------------------------------------------------------
 * Daftar kecuali (path relatif, pemisah '/').
 * ------------------------------------------------------------- */
$kecualiDirExact = ['.git', 'dist', 'storage/backup'];
$kecualiFileExact = ['.env', 'hak akses.txt', 'Thumbs.db', '.DS_Store'];
$kecualiAwalan = ['hak akses'];           /* "hak akses (salinan).txt", dst. */
$kecualiPolosan = ['tmp-*', '*.bak', '*.old', '*.swp', '*~'];

function paket_dikecualikan($rel, $kecualiDirExact, $kecualiFileExact, $kecualiAwalan, $kecualiPolosan)
{
    $rel = str_replace('\\', '/', $rel);
    $bagian = explode('/', $rel);
    $namaAkhir = end($bagian);

    /* Folder privat: storage/dokumen/* (kecuali .gitkeep) */
    if ($rel === 'storage/dokumen/.gitkeep') {
        return false;
    }
    if ($bagian[0] === 'storage' && isset($bagian[1]) && $bagian[1] === 'dokumen') {
        return true;
    }
    /* Folder per direktori persis */
    foreach ($bagian as $i => $b) {
        if (in_array($b, $kecualiDirExact, true)) {
            return true;
        }
        /* .env pada level mana pun (kecuali .env.example) */
        if ($b === '.env') {
            return true;
        }
        unset($bagian[$i]);
    }
    /* Nama file persis */
    if (in_array($namaAkhir, $kecualiFileExact, true)) {
        return true;
    }
    /* Awalan nama file: "hak akses ..." */
    foreach ($kecualiAwalan as $a) {
        if (strncasecmp($namaAkhir, $a, strlen($a)) === 0) {
            return true;
        }
    }
    /* Polosan: tmp-*, *.bak, dst. */
    foreach ($kecualiPolosan as $p) {
        if (fnmatch($p, $namaAkhir)) {
            return true;
        }
    }
    return false;
}

/* ---------------------------------------------------------------
 * Kumpulkan file.
 * ------------------------------------------------------------- */
$adaZip = new ZipArchive();
if ($adaZip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    gagal_paket('Gagal membuat file zip: ' . basename($zipFile));
}

$jumlah = 0;
$ukuranTotal = 0;
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $abs = $file->getPathname();
    $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($root))), '/');

    if (paket_dikecualikan($rel, $kecualiDirExact, $kecualiFileExact, $kecualiAwalan, $kecualiPolosan)) {
        continue;
    }
    if (!$adaZip->addFile($abs, $rel)) {
        $adaZip->close();
        @unlink($zipFile);
        gagal_paket('Gagal menambahkan file: ' . $rel);
    }
    $jumlah++;
    $ukuranTotal += $file->getSize();
}

if ($jumlah === 0) {
    $adaZip->close();
    @unlink($zipFile);
    gagal_paket('Tidak ada file yang masuk paket — periksa struktur proyek.');
}
$adaZip->close();

/* ---------------------------------------------------------------
 * Verifikasi PASCA-BUILD: paket tidak boleh memuat file terlarang.
 * ------------------------------------------------------------- */
$cek = new ZipArchive();
if ($cek->open($zipFile) !== true) {
    gagal_paket('Gagal membuka kembali zip untuk verifikasi.');
}
$terlarang = [];
for ($i = 0; $i < $cek->numFiles; $i++) {
    $nama = str_replace('\\', '/', $cek->getNameIndex($i));
    if ($nama === '.env' || preg_match('#(^|/)\.env$#', $nama)) {
        $terlarang[] = $nama;                        /* .env asli */
    } elseif (preg_match('#^storage/dokumen/#', $nama) && $nama !== 'storage/dokumen/.gitkeep') {
        $terlarang[] = $nama;                        /* unggahan pribadi */
    } elseif (preg_match('#(^|/)\.git/#', $nama)) {
        $terlarang[] = $nama;                        /* riwayat git */
    }
}
$cek->close();

if ($terlarang) {
    @unlink($zipFile);
    gagal_paket(
        'VERIFIKASI GAGAL — file terlarang terdeteksi (zip dihapus): '
        . implode(', ', array_slice($terlarang, 0, 5))
    );
}

$ukuranZip = filesize($zipFile);
fwrite(STDOUT, "PAKET RILIS BERHASIL (verifikasi lolos — tanpa .env / .git / dokumen pribadi)\n"
    . '  File   : ' . str_replace('\\', '/', $zipFile) . "\n"
    . '  Versi  : ' . $versi . "\n"
    . '  Isi    : ' . $jumlah . ' file (' . round($ukuranTotal / 1024, 1) . ' KB) -> zip '
    . round($ukuranZip / 1024, 1) . " KB\n"
    . "  Kecuali: .env, .git/, dist/, storage/dokumen/*, storage/backup/, hak akses*, tmp-*, *.bak\n"
    . "Catatan: kredensial produksi (.env) diisi di server tujuan — lihat docs/DEPLOY.md.\n");
exit(0);
