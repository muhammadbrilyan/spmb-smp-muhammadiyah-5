<?php
/**
 * Fase 11 B1 / Fase 12 B — Buat paket rilis (zip) TANPA file rahasia / pribadi / lokal.
 *
 * Dieksklusi dari paket (lihat paket_kecuali_*):
 *   - .env pada level mana pun    (kredensial — hanya .env.example yang ikut)
 *   - .git/, dist/, node_modules/, stitch_mualimku/
 *   - storage/dokumen/*           (unggahan pribadi; .gitkeep saja yang ikut)
 *   - storage/backup/             (hasil cadangan)
 *   - database/seed_demo.sql, database/seed_demo_hapus.sql
 *   - "hak akses*", tmp-*, *.log, *.bak, *.old, *.swp, *~, Thumbs.db, .DS_Store
 *
 * Setelah zip dibuat, paket DIVERIFIKASI ulang terhadap SEMUA pola di atas —
 * bila ada satu pun file terlarang, zip dihapus dan tool keluar dengan kode error.
 *
 * CLI-ONLY (diblokir dari browser). Pakai:
 *   php tools/buat-paket.php           -> dist/mualimku-<YYYYMMDD>.zip
 *   php tools/buat-paket.php v1.2      -> dist/mualimku-v1.2.zip
 *   php tools/buat-paket.php --uji     -> mode uji: pohon sementara berisi umpan
 *                                         file terlarang — exit ≠ 0 bila ada yang bocor
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

/* ---------------------------------------------------------------
 * Daftar kecuali — dua kategori agar jelas:
 *   awalan_path : dicocokkan terhadap PATH RELATIF PENUH
 *                 ($rel === $x || str_starts_with($rel, $x . '/'))
 *   nama_segmen : dicocokkan terhadap SATU BAGIAN path
 *                 (nama folder mana pun, mis. .git di root/atau subfolder)
 * ------------------------------------------------------------- */
function paket_kecuali_awalan_path()
{
    return [
        'storage/backup',
        'storage/dokumen',   /* .gitkeep dikecualikan khusus di paket_dikecualikan() */
        'stitch_mualimku',
        'node_modules',
    ];
}

function paket_kecuali_segmen()
{
    return ['.git', 'dist'];
}

function paket_kecuali_path_persis()
{
    return [
        'database/seed_demo.sql',
        'database/seed_demo_hapus.sql',
    ];
}

function paket_kecuali_nama_persis()
{
    return [
        'hak akses.txt',
        'Thumbs.db',
        '.DS_Store',
    ];
}

function paket_kecuali_awalan_nama()
{
    return ['hak akses'];   /* "hak akses (salinan).txt", dst. */
}

function paket_kecuali_polosan()
{
    /* '.env' di sini menangkap .env pada level mana pun;
       .env.example TIDAK cocok (fnmatch butuh kecocokan penuh). */
    return ['.env', 'tmp-*', '*.bak', '*.old', '*.swp', '*~', '*.log'];
}

function paket_dikecualikan($rel)
{
    $rel = str_replace('\\', '/', (string) $rel);

    /* Satu-satunya file storage/dokumen yang ikut paket. */
    if ($rel === 'storage/dokumen/.gitkeep') {
        return false;
    }

    /* Folder bertingkat: cocokkan awalan path penuh. */
    foreach (paket_kecuali_awalan_path() as $a) {
        if ($rel === $a || str_starts_with($rel, $a . '/')) {
            return true;
        }
    }

    /* Path persis (bukan basename) — mis. database/seed_demo.sql. */
    if (in_array($rel, paket_kecuali_path_persis(), true)) {
        return true;
    }

    /* Nama segmen: .git / dist pada posisi path mana pun. */
    foreach (explode('/', $rel) as $b) {
        if (in_array($b, paket_kecuali_segmen(), true)) {
            return true;
        }
    }

    $namaAkhir = basename($rel);

    if (in_array($namaAkhir, paket_kecuali_nama_persis(), true)) {
        return true;
    }
    foreach (paket_kecuali_awalan_nama() as $a) {
        if (strncasecmp($namaAkhir, $a, strlen($a)) === 0) {
            return true;
        }
    }
    foreach (paket_kecuali_polosan() as $p) {
        if (fnmatch($p, $namaAkhir)) {
            return true;
        }
    }
    return false;
}

/* ---------------------------------------------------------------
 * Bangun zip dari suatu root proyek (dipakai mode normal & --uji).
 * ------------------------------------------------------------- */
function paket_bangun($root, $zipFile, $versi)
{
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

        if (paket_dikecualikan($rel)) {
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
    return ['jumlah' => $jumlah, 'ukuran' => $ukuranTotal];
}

/* ---------------------------------------------------------------
 * Verifikasi PASCA-BUILD terhadap SEMUA pola terlarang.
 * ------------------------------------------------------------- */
function paket_verifikasi_terlarang($zipFile)
{
    $cek = new ZipArchive();
    if ($cek->open($zipFile) !== true) {
        gagal_paket('Gagal membuka kembali zip untuk verifikasi.');
    }
    $terlarang = [];
    for ($i = 0; $i < $cek->numFiles; $i++) {
        $nama = str_replace('\\', '/', $cek->getNameIndex($i));
        if ($nama === 'storage/dokumen/.gitkeep') {
            continue;
        }
        if (!paket_dikecualikan($nama)) {
            continue;
        }
        $terlarang[] = $nama;
    }
    $cek->close();
    return $terlarang;
}

function paket_bersihkan($zipFile)
{
    if (is_file($zipFile)) {
        @unlink($zipFile);
    }
}

function paket_hapus_pohon($pohon)
{
    if (!is_dir($pohon)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pohon, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isFile() ? @unlink($f->getPathname()) : @rmdir($f->getPathname());
    }
    @rmdir($pohon);
}

/* ---------------------------------------------------------------
 * MODE UJI --uji : pohon sementara berisi umpan file terlarang.
 * Bila SATU PUN umpan masuk ke zip -> GAGAL (exit ≠ 0).
 * ------------------------------------------------------------- */
function paket_mode_uji()
{
    $pohon = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'paket-uji-' . bin2hex(random_bytes(6));
    $zipUji = $pohon . '-hasil.zip';

    $tulis = function ($rel, $isi) use ($pohon) {
        $abs = $pohon . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $dir = dirname($abs);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($abs, $isi);
    };

    /* Umpan: file yang TIDAK BOLEH masuk zip. */
    $umpan = [
        '.env'                              => "RAHASIA=1\n",
        'storage/backup/x.sql.gz'           => "cadangan",
        'storage/dokumen/rahasia.pdf'       => "berkas pribadi",
        'hak akses.txt'                     => "daftar password",
        'stitch_mualimku/a.png'             => "aset lokal",
        'node_modules/x.js'                 => "dependensi",
        'tmp-x.log'                         => "log",
        'database/seed_demo.sql'            => "demo",
        'database/seed_demo_hapus.sql'      => "demo",
        'app/rapat.php.bak'                 => "cadangan kode",
        'catatan.old'                       => "catatan lama",
    ];
    /* File yang HARUS masuk zip (bukti pengecualian tidak berlebihan). */
    $wajib = [
        '.env.example'                      => "DB_PASS=\n",
        'app/aman.php'                      => "<?php\n",
        'storage/dokumen/.gitkeep'          => '',
        'database/db_spmb_mualimku.sql'     => "-- seed utama\n",
    ];

    foreach ($umpan as $rel => $isi) {
        $tulis($rel, $isi);
    }
    foreach ($wajib as $rel => $isi) {
        $tulis($rel, $isi);
    }

    paket_bangun($pohon, $zipUji, 'uji');

    $isi = [];
    $z = new ZipArchive();
    if ($z->open($zipUji) !== true) {
        paket_hapus_pohon($pohon);
        gagal_paket('Mode uji: gagal membuka zip hasil.');
    }
    for ($i = 0; $i < $z->numFiles; $i++) {
        $isi[] = str_replace('\\', '/', $z->getNameIndex($i));
    }
    $z->close();

    $bocor = array_values(array_intersect($isi, array_keys($umpan)));
    $hilang = array_values(array_diff($wajib ? array_keys($wajib) : [], $isi));

    paket_hapus_pohon($pohon);
    @unlink($zipUji);

    if ($bocor) {
        gagal_paket('MODE UJI GAGAL — umpan bocor ke paket: ' . implode(', ', $bocor));
    }
    if ($hilang) {
        gagal_paket('MODE UJI GAGAL — file wajib tidak masuk paket: ' . implode(', ', $hilang));
    }

    fwrite(STDOUT, "MODE UJI LULUS — tidak ada umpan yang bocor; .env.example, app/aman.php, .gitkeep, seed utama ikut.\n"
        . '  Pohon sementara & zip uji sudah dihapus.' . "\n");
    exit(0);
}

/* ---------------------------------------------------------------
 * MAIN
 * ------------------------------------------------------------- */
if (!class_exists('ZipArchive')) {
    gagal_paket('Ekstensi PHP "zip" belum aktif (ZipArchive tidak ditemukan).');
}

$argvList = $argv;
array_shift($argvList);

if (in_array('--uji', $argvList, true)) {
    paket_mode_uji();
}

$root = dirname(__DIR__);

/* Nama versi harus aman untuk nama file. */
$versi = isset($argvList[0]) && trim((string) $argvList[0]) !== ''
    ? trim((string) $argvList[0])
    : date('Ymd');
$versi = preg_replace('/[^A-Za-z0-9._-]/', '-', $versi);
if ($versi === '' || $versi === '-') {
    gagal_paket('Nama versi tidak valid.');
}

$dist = $root . '/dist';
if (!is_dir($dist) && !mkdir($dist, 0755, true)) {
    gagal_paket('Gagal membuat folder dist/.');
}
$zipFile = $dist . '/mualimku-' . $versi . '.zip';
if (is_file($zipFile)) {
    unlink($zipFile);
}

$stat = paket_bangun($root, $zipFile, $versi);

$terlarang = paket_verifikasi_terlarang($zipFile);
if ($terlarang) {
    paket_hapus_pohon($zipFile);
    gagal_paket(
        'VERIFIKASI GAGAL — file terlarang terdeteksi (zip dihapus): '
        . implode(', ', array_slice($terlarang, 0, 5))
    );
}

$ukuranZip = filesize($zipFile);
fwrite(STDOUT, "PAKET RILIS BERHASIL (verifikasi lolos — tanpa file terlarang)\n"
    . '  File   : ' . str_replace('\\', '/', $zipFile) . "\n"
    . '  Versi  : ' . $versi . "\n"
    . '  Isi    : ' . $stat['jumlah'] . ' file (' . round($stat['ukuran'] / 1024, 1) . ' KB) -> zip '
    . round($ukuranZip / 1024, 1) . " KB\n"
    . "  Kecuali: .env, .git/, dist/, node_modules/, stitch_mualimku/, storage/backup/,\n"
    . "           storage/dokumen/* (kecuali .gitkeep), seed_demo*.sql, hak akses*, tmp-*, *.log\n"
    . "Catatan: jalankan 'php tools/buat-paket.php --uji' sebelum tiap rilis.\n"
    . "         Kredensial produksi (.env) diisi di server tujuan — lihat docs/DEPLOY.md.\n");
exit(0);
