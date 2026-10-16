<?php
/**
 * Fase 12 C2 — Fungsi cadangan database PORTABEL (tanpa mysqldump/exec).
 *
 * Dipakai oleh tools/backup-db.php & tools/uji-backup.php.
 * Dump PDO murni: SHOW TABLES / SHOW CREATE TABLE / SELECT bertahap
 * (batch 500 baris dalam snapshot REPEATABLE READ), nilai via PDO::quote,
 * dibungkus SET NAMES utf8mb4 + SET FOREIGN_KEY_CHECKS=0/1, ditulis
 * langsung ke gzopen (streaming). Hasil bisa diimpor ulang phpMyAdmin.
 *
 * ATURAN FORMAT: setiap statement SELALU satu baris (PDO::quote
 * meng-escape newline sebagai \n dua karakter) — aman dipecah per baris
 * oleh tools/uji-backup.php.
 */

function backup_gagal($pesan)
{
    fwrite(STDERR, 'GAGAL: ' . $pesan . "\n");
    exit(1);
}

/** Cari mysqldump — null bila tidak tersedia / shell dinonaktifkan. */
function backup_cari_mysqldump()
{
    $kandidat = [];
    $envPath = getenv('MYSQLDUMP_PATH');
    if ($envPath) {
        $kandidat[] = $envPath;
    }
    $bin = dirname(PHP_BINARY);
    $kandidat[] = $bin . '/../mysql/bin/mysqldump.exe';
    $kandidat[] = $bin . '/mysqldump.exe';
    $kandidat[] = '/usr/bin/mysqldump';
    $kandidat[] = '/usr/local/bin/mysqldump';
    foreach ($kandidat as $k) {
        if ($k !== '' && is_file($k)) {
            return $k;
        }
    }
    if (function_exists('shell_exec')) {
        $cari = (DIRECTORY_SEPARATOR === '\\') ? 'where mysqldump' : 'command -v mysqldump';
        $out = @shell_exec($cari . ' 2>NUL');
        $out = $out ? trim($out) : '';
        if ($out !== '' && is_file($out)) {
            return $out;
        }
    }
    return null;
}

/** Apakah jalur mysqldump (proc_open) bisa dipakai? */
function backup_bisa_mysqldump()
{
    return function_exists('proc_open') && backup_cari_mysqldump() !== null;
}

/** Koneksi PDO ke database aplikasi (tanpa memilih db — utk CREATE DB uji). */
function backup_pdo(array $info, $pilihDb = true)
{
    $dsn = 'mysql:host=' . $info['host'] . ';port=' . $info['port']
        . ($pilihDb ? ';dbname=' . $info['name'] : '') . ';charset=utf8mb4';
    return new PDO($dsn, $info['user'], $info['pass'], [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/**
 * Dump PDO murni -> file .sql.gz (streaming, hemat memori).
 * $info = ['host'=>, 'port'=>, 'user'=>, 'pass'=>, 'name'=>]
 * Mengembalikan jumlah tabel yang didump.
 */
function backup_dump_pdo(array $info, $gzipFile)
{
    try {
        $pdo = backup_pdo($info);
    } catch (PDOException $e) {
        backup_gagal('Koneksi database gagal: ' . $e->getMessage());
    }

    $gz = gzopen($gzipFile, 'wb6');
    if ($gz === false) {
        backup_gagal('Gagal membuka file gzip: ' . basename($gzipFile));
    }
    $tulis = function ($sql) use ($gz) {
        gzwrite($gz, $sql . ";\n");
    };

    $tulis('SET NAMES utf8mb4');
    $tulis('SET FOREIGN_KEY_CHECKS=0');
    /* Snapshot konsisten (setara mysqldump --single-transaction). */
    $pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

    $tabel = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $batch = 500;

    foreach ($tabel as $t) {
        $baris = $pdo->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        $create = is_array($baris) ? (string) $baris[1] : '';
        if ($create === '') {
            $pdo->exec('ROLLBACK');
            gzclose($gz);
            @unlink($gzipFile);
            backup_gagal('Gagal membaca struktur tabel: ' . $t);
        }
        /* Rapikan ke satu baris — aturan format dump: satu statement per baris. */
        $create = preg_replace('/\s*\r?\n\s*/', ' ', $create);
        $tulis("DROP TABLE IF EXISTS `$t`");
        $tulis($create);

        $kolomSql = null;
        $off = 0;
        while (true) {
            $stmt = $pdo->query("SELECT * FROM `$t` LIMIT $batch OFFSET $off");
            $baris = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if (!$baris) {
                break;
            }
            if ($kolomSql === null) {
                $kolomSql = implode(', ', array_map(function ($k) {
                    return '`' . str_replace('`', '``', $k) . '`';
                }, array_keys($baris[0])));
            }
            foreach ($baris as $isi) {
                $nilai = [];
                foreach ($isi as $v) {
                    if ($v === null) {
                        $nilai[] = 'NULL';
                    } elseif (is_int($v) || is_float($v)) {
                        $nilai[] = $v;
                    } else {
                        /* quote menangani teks, biner, dan karakter khusus. */
                        $nilai[] = $pdo->quote((string) $v);
                    }
                }
                $tulis('INSERT INTO `' . str_replace('`', '``', $t) . '` (' . $kolomSql
                    . ') VALUES (' . implode(', ', $nilai) . ')');
            }
            $off += count($baris);
            unset($baris);
        }
    }

    $pdo->exec('COMMIT');
    $tulis('SET FOREIGN_KEY_CHECKS=1');
    gzclose($gz);
    return count($tabel);
}
