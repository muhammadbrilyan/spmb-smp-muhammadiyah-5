<?php
/**
 * Fase 12 D — Pemeriksaan statis kode (12 pemeriksaan, tanpa DB/web).
 *
 * Output : [LULUS] / [GAGAL] per pemeriksaan + ringkasan.
 * Exit   : 0 = semua LULUS, 1 = ada minimal satu GAGAL.
 *
 * CLI-ONLY. Pakai:
 *   php tools/cek-statis.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);

/* --------------------------------------------------------------- */
$hasil = [];
function catat($status, $judul, $detail = '')
{
    global $hasil;
    $hasil[] = [$status, $judul, $detail];
    printf("[%s] %-46s %s\n", $status, $judul, $detail);
}

/* Kumpulkan file teks source (kecuali vendor/biner). */
$KECUALI_DIR = ['.git', 'node_modules', 'vendor', 'dist', 'stitch_mualimku', 'storage'];
$daftar = [];
$riak = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($riak as $f) {
    if (!$f->isFile()) {
        continue;
    }
    $rel = str_replace('\\', '/', substr($f->getPathname(), strlen($root) + 1));
    $bagian = explode('/', $rel);
    $lewat = false;
    foreach ($bagian as $b) {
        if (in_array($b, $KECUALI_DIR, true)) {
            $lewat = true;
            break;
        }
    }
    if ($lewat) {
        continue;
    }
    $daftar[$rel] = $f->getPathname();
}
ksort($daftar);

/* Baca isi + strip komentar (untuk pemeriksaan pola berbahaya). */
function isi_bersih($path)
{
    $isi = (string) file_get_contents($path);
    /* Buang komentar blok & baris agar tidak memicu false positive. */
    $isi = preg_replace('~/\*.*?\*/~s', '', $isi);
    $isi = preg_replace('~//[^\n]*~', '', $isi);
    return $isi;
}

$phpFiles = array_filter($daftar, function ($p) {
    return preg_match('/\.php$/', $p) === 1;
});

echo "CEK STATIS — " . count($daftar) . " berkas teks source\n\n";

/* 1. Syntax PHP (token_get_all + TOKEN_PARSE). */
$sintak = [];
foreach ($phpFiles as $rel => $path) {
    try {
        token_get_all((string) file_get_contents($path), TOKEN_PARSE);
    } catch (ParseError $e) {
        $sintak[] = $rel . ': ' . $e->getMessage();
    }
}
catat($sintak ? 'GAGAL' : 'LULUS', '1. Syntax PHP',
    $sintak ? implode('; ', array_slice($sintak, 0, 5)) : count($phpFiles) . ' file valid');

/* 2. Tanpa BOM (UTF-8 / UTF-16) pada berkas source. */
$bom = [];
foreach ($daftar as $rel => $path) {
    if (!preg_match('/\.(php|css|js|sql|md|txt|htaccess|ini)$/', $rel)) {
        continue;
    }
    $h = (string) file_get_contents($path, false, null, 0, 4);
    if ($h === "\xEF\xBB\xBF" || $h[0] === "\xFF" || $h[0] === "\xFE") {
        $bom[] = $rel;
    }
}
catat($bom ? 'GAGAL' : 'LULUS', '2. Tanpa BOM encoding',
    $bom ? implode(', ', array_slice($bom, 0, 5)) : '0 file ber-BOM');

/* 3. Akhir baris LF (tanpa CRLF) pada source code. */
$crlf = [];
foreach ($daftar as $rel => $path) {
    if (!preg_match('/\.(php|css|js|sql)$/', $rel)) {
        continue;
    }
    if (strpos((string) file_get_contents($path), "\r\n") !== false) {
        $crlf[] = $rel;
    }
}
catat($crlf ? 'GAGAL' : 'LULUS', '3. Akhir baris LF (tanpa CRLF)',
    $crlf ? implode(', ', array_slice($crlf, 0, 5)) : '0 file CRLF');

/* 4. .gitignore memuat entri wajib. */
$gi = (string) @file_get_contents($root . '/.gitignore');
$butuh = ['.env', 'storage/backup/', 'tmp-*', '*.log'];
$giKurang = array_values(array_filter($butuh, function ($b) use ($gi) {
    return strpos($gi, $b) === false;
}));
catat($giKurang ? 'GAGAL' : 'LULUS', '4. .gitignore lengkap',
    $giKurang ? 'kurang: ' . implode(', ', $giKurang) : '.env, storage/backup/, tmp-*, *.log tercakup');

/* 5 & 6. Berkas git — via `git ls-files` (opsional; SKIP bila git absen). */
$gitFiles = null;
if (function_exists('shell_exec')) {
    $nul = strtoupper(substr(PHP_OS_FAMILY, 0, 3)) === 'WIN' ? '2>NUL' : '2>/dev/null';
    $out = @shell_exec('git -C ' . escapeshellarg($root) . ' ls-files ' . $nul);
    if (is_string($out) && trim($out) !== '') {
        $gitFiles = array_values(array_filter(array_map('trim', explode("\n", $out))));
    }
}
if ($gitFiles === null) {
    catat('SKIP', '5. .env tidak masuk git index', 'git/shell_exec tidak tersedia — lewati');
    catat('SKIP', '6. Tidak ada berkas terlarang di git', 'git/shell_exec tidak tersedia — lewati');
} else {
    $envNyata = array_values(array_filter($gitFiles, function ($g) {
        return $g === '.env';
    }));
    catat($envNyata ? 'GAGAL' : 'LULUS', '5. .env tidak masuk git index',
        $envNyata ? '.env TER-STAGE di git!' : 'tidak ditemukan di index');

    $terlarang = array_values(array_filter($gitFiles, function ($g) {
        if (preg_match('/\.(bak|old|swp|sql\.gz|log)$/', $g)) {
            return true;
        }
        if (strpos($g, 'storage/dokumen/') === 0
            && substr($g, -8) !== '.gitkeep'
            && preg_match('/-demo-[A-Za-z0-9._-]+\.(png)$/', $g) !== 1) {
            /* PNG demo 4px netral (Fase 11 B6) diizinkan; lainnya tidak. */
            return true;
        }
        if (preg_match('~(^|/)tmp-~', $g)) {
            return true;
        }
        return false;
    }));
    catat($terlarang ? 'GAGAL' : 'LULUS', '6. Tidak ada berkas terlarang di git',
        $terlarang ? implode(', ', array_slice($terlarang, 0, 5)) : 'index bersih (.bak/.log/tmp-*.sql.gz/storage dokumen)');
}

/* 7. Kredensial hardcode — assignment literal panjang ber-kata kunci. */
$rahasia = [];
foreach ($phpFiles as $rel => $path) {
    $isi = isi_bersih($path);
    if (preg_match_all('/(secret|server_key|client_key|api_key|password|pass|token)\s*(=>|=)\s*[\'"]([A-Za-z0-9+\/=_\-]{20,})[\'"]/i', $isi, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[3] as $cocok) {
            $val = $cocok[0];
            /* Lewati placeholder & hash demo yang memang terbuka. */
            if (in_array($val, ['!LOCKED'], true) || strpos($val, 'ISI_') === 0 || strpos($val, 'ganti') !== false) {
                continue;
            }
            $rahasia[] = $rel;
            break;
        }
    }
}
catat($rahasia ? 'GAGAL' : 'LULUS', '7. Tanpa kredensial hardcode',
    $rahasia ? implode(', ', array_slice($rahasia, 0, 5)) : 'tidak ditemukan literal rahasia 20+ karakter');

/* 8. Tanpa eval / create_function / passthru di mana pun; shell_exec hanya
 * di tool yang menggunakannya dgn guard function_exists. File tool ini
 * sendiri dikeluarkan (kata kuncinya ada di dalam string pemeriksaan). */
$bahaya = [];
$shellIzin = ['tools/backup-db-lib.php', 'tools/set-password.php'];
foreach ($phpFiles as $rel => $path) {
    if ($rel === 'tools/cek-statis.php') {
        continue;
    }
    $isi = isi_bersih($path);
    foreach (['eval(', 'create_function', 'passthru('] as $fn) {
        if (strpos($isi, $fn) !== false) {
            $bahaya[] = $rel . ' (' . rtrim($fn, '(') . ')';
        }
    }
    if (strpos($isi, 'shell_exec(') !== false && !in_array($rel, $shellIzin, true)) {
        $bahaya[] = $rel . ' (shell_exec)';
    }
}
catat($bahaya ? 'GAGAL' : 'LULUS', '8. Tanpa eval/create_function/passthru; shell_exec terbatas',
    $bahaya ? implode(', ', array_slice($bahaya, 0, 5)) : '0 temuan (shell_exec hanya di tool ber-guard)');

/* 9. system()/exec() hanya di tool cadangan (guard function_exists). */
$execIzin = ['tools/backup-db-lib.php', 'tools/backup-db.php', 'tools/set-password.php', 'tools/uji-backup.php'];
$execDiluar = [];
foreach ($phpFiles as $rel => $path) {
    $isi = isi_bersih($path);
    $isi = str_replace('->exec(', '->PDOEXEC(', $isi);   /* method PDO aman */
    $isi = str_replace('q_exec(', 'QEXEC(', $isi);
    if (preg_match('/[^a-zA-Z_0-9]system\s*\(|[^a-zA-Z_0-9]exec\s*\(/', $isi)
        && !in_array($rel, $execIzin, true)) {
        $execDiluar[] = $rel;
    }
}
catat($execDiluar ? 'GAGAL' : 'LULUS', '9. system/exec hanya di tool cadangan',
    $execDiluar ? implode(', ', array_slice($execDiluar, 0, 5)) : 'hanya di daftar izin tools cadangan');

/* 10. Setiap app/*.php yang memproses $_POST memakai csrf_cek. */
$csrfKurang = [];
foreach ($phpFiles as $rel => $path) {
    if (strpos($rel, 'app/') !== 0) {
        continue;
    }
    $isi = isi_bersih($path);
    if (strpos($isi, '$_POST') !== false && strpos($isi, 'csrf_cek') === false) {
        $csrfKurang[] = $rel;
    }
}
catat($csrfKurang ? 'GAGAL' : 'LULUS', '10. $_POST di app/ dgn csrf_cek',
    $csrfKurang ? implode(', ', $csrfKurang) : 'semua berkas POST memakai csrf_cek');

/* 11. move_uploaded_file selalu disertai validasi finfo/mime. */
$upKurang = [];
foreach ($phpFiles as $rel => $path) {
    $isi = isi_bersih($path);
    if (strpos($isi, 'move_uploaded_file') !== false
        && strpos($isi, 'finfo') === false
        && stripos($isi, 'mime') === false) {
        $upKurang[] = $rel;
    }
}
catat($upKurang ? 'GAGAL' : 'LULUS', '11. Unggahan divalidasi finfo/mime',
    $upKurang ? implode(', ', $upKurang) : 'semua upload memeriksa tipe berkas');

/* 12. .htaccess root & .user.ini ada dgn isi minimal. */
$hta = (string) @file_get_contents($root . '/.htaccess');
$ui  = (string) @file_get_contents($root . '/.user.ini');
$masalah12 = [];
if (strpos($hta, 'database/') === false || strpos($hta, 'includes/') === false) {
    $masalah12[] = '.htaccess tidak memblokir database/ atau includes/';
}
if ($ui === '' || strpos($ui, 'display_errors=Off') === false) {
    $masalah12[] = '.user.ini tidak ada/tanpa display_errors=Off';
}
catat($masalah12 ? 'GAGAL' : 'LULUS', '12. .htaccess & .user.ini produksi',
    $masalah12 ? implode('; ', $masalah12) : 'blokir database/includes + display_errors=Off ada');

/* --------------------------------------------------------------- */
$gagal = array_filter($hasil, function ($h) {
    return $h[0] === 'GAGAL';
});
echo "\nRingkasan: " . count($hasil) . " pemeriksaan — "
    . (count($hasil) - count($gagal)) . ' LULUS/SKIP, ' . count($gagal) . " GAGAL\n";
exit($gagal ? 1 : 0);
