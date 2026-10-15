<?php
/**
 * Fase 10 Bagian B — Reset password akun + paksa ganti password.
 *
 * Menggantikan isi "hak akses.txt" yang tidak boleh ada di repository.
 * Setelah reset, akun ditandai wajib_ganti_password = 1 sehingga pada login
 * berikutnya pengguna dipaksa ke halaman Ganti Password (app/staf/ganti-password.php).
 *
 * CLI-ONLY — memblokir akses lewat browser (404).
 *
 * Pakai:
 *   php tools/set-password.php
 *   php tools/set-password.php operator
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/config.php';

/** Baca satu baris dari STDIN (tanpa echo untuk password). */
function tanya($prompt, $sembunyi = false)
{
    fwrite(STDOUT, $prompt);
    if ($sembunyi && DIRECTORY_SEPARATOR === '/') {
        /* Unix: matikan echo */
        shell_exec('stty -echo 2>/dev/null');
        $b = fgets(STDIN);
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDOUT, "\n");
    } else {
        $b = fgets(STDIN);
    }
    return $b === false ? '' : trim($b);
}

function gagal($pesan)
{
    fwrite(STDERR, 'GAGAL: ' . $pesan . "\n");
    exit(1);
}

/* ------------------------------------------------------------- */
$username = isset($argv[1]) && $argv[1] !== '' ? trim($argv[1]) : tanya('Username akun : ');
if ($username === '') {
    gagal('Username wajib diisi.');
}

$rows = q_all('SELECT id, username, role, nama_lengkap FROM users WHERE username = ?', [$username]);
if (!$rows) {
    gagal('Akun "' . $username . '" tidak ditemukan di database.');
}
$akun = $rows[0];

fwrite(STDOUT, 'Akun ditemukan : ' . $akun['username'] . ' (' . $akun['role'] . ') - ' . ($akun['nama_lengkap'] ?? '-') . "\n");

/* Password baru: minimal 10 karakter, konfirmasi 2x, beda dari username. */
for (;;) {
    $baru = tanya('Password baru (min 10) : ', true);
    if (strlen($baru) < 10) {
        fwrite(STDOUT, "  Minimal 10 karakter. Ulangi.\n");
        continue;
    }
    if ($baru === $username) {
        fwrite(STDOUT, "  Password tidak boleh sama dengan username. Ulangi.\n");
        continue;
    }
    $ulang = tanya('Ulangi password baru    : ', true);
    if ($baru !== $ulang) {
        fwrite(STDOUT, "  Tidak sama. Ulangi.\n");
        continue;
    }
    break;
}

$hash = password_hash($baru, PASSWORD_DEFAULT);
$ok   = q_exec('UPDATE users SET password_hash = ?, wajib_ganti_password = 1 WHERE username = ?',
    [$hash, $username]);

if (!$ok) {
    gagal('Query UPDATE gagal dijalankan.');
}

fwrite(STDOUT, "\nBERHASIL.\n"
    . '  - Password "' . $username . '" sudah diganti (bcrypt).\n'
    . '  - Akun ditandai WAJIB GANTI PASSWORD pada login berikutnya\n'
    . '    (otomatis lepas setelah pengguna sukses ganti di halaman Ganti Password).\n');
exit(0);
