<?php
/**
 * MIGRASI DATABASE LEWAT BROWSER (hosting tanpa SSH).
 *
 * Kenapa alat ini ada: panduan lama menyuruh `mysql -u root < berkas.sql`, dan
 * tiap berkas membuka diri dengan `USE db_spmb_mualimku;`. Di hosting nama
 * database hampir selalu berbeda; kalau baris itu tidak ikut diganti, seluruh
 * pemeriksaan information_schema berjalan di skema yang SALAH dan migrasi
 * diam-diam tidak melakukan apa-apa — kolom tidak bertambah, tidak ada pesan
 * galat. Alat ini memakai koneksi aplikasi (DB_HOST/DB_NAME/DB_USER dari
 * .env), jadi nama database tidak perlu diketik dan baris USE dilewati.
 *
 * Keamanan berlapis — pola yang sama dengan set-password-web.php:
 *   1. Hanya aktif bila penanda storage/.izinkan-migrasi ADA (404 bila tidak).
 *   2. Isi penanda = KODE ACAK (min 8 karakter) yang harus diketik sebelum
 *      apa pun ditampilkan — pengunjung anonim tidak melihat struktur database.
 *   3. CSRF token per sesi + rate limit per IP untuk tiap percobaan.
 *   4. Kredensial database hanya dipakai di dalam request ini: tidak disimpan
 *      di sesi, tidak pernah dicetak balik ke atribut value=.
 *   5. Berkas yang dijalankan HANYA yang terdaftar di $TARGET di bawah, dan
 *      setiap berkas punya pemeriksaan hasilnya sendiri.
 *   6. Setelah semua target terverifikasi: penanda instalasi DIHAPUS, sehingga
 *      alat ini kembali 404. Dirinya sendiri tidak dihapus — untuk batch
 *      migrasi berikutnya cukup buat penanda baru (dan tambahkan nama berkasnya
 *      di $TARGET).
 *
 * Pakai:
 *   1. File Manager cPanel -> buat berkas storage/.izinkan-migrasi berisi kode
 *      acak (hasil password generator, minimal 8 karakter).
 *   2. Buka https://domain-anda/tools/migrasi-web.php -> ketik kode -> Jalankan.
 *   3. Selesai: penanda sudah terhapus sendiri. Pindahkan berkas ini ke luar
 *      document root bila memang tidak dipakai lagi.
 */
require_once __DIR__ . '/../includes/config.php';

/* ==================================================================
 * Berkas migrasi yang dikelola alat ini.
 * Tambah nama berkas baru setiap batch migrasi turun — berkas LAMA di
 * database/migrations/ sengaja tidak ikut dijalankan karena sebagian belum
 * berpemeriksaan "sudah ada", sehingga menjalankannya ulang memberi pesan
 * galat "duplicate column" yang tidak membantu.
 * ================================================================== */
$TARGET = [
    [
        'file'  => '20261106_wa_teks_cms.sql',
        'judul' => 'Teks pesan WhatsApp ke wali bisa disunting dari Pengaturan Sistem',
        'penting' => 'Tanpa kolom ini, SETIAP simpan di Pengaturan Sistem batal '
            . '(1054 Unknown column) — bukan hanya bagian teks WA.',
        'cek' => [
            ['ada' => true, 'label' => 'kolom pengaturan_sekolah.wa_teks',
             'sql' => "SELECT COUNT(*) FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
                         AND COLUMN_NAME = 'wa_teks'"],
        ],
    ],
    [
        'file'  => '20261106_peserta_ujian_multi_jadwal.sql',
        'judul' => 'Satu calon bisa memegang beberapa kartu kegiatan',
        'penting' => 'Tanpa kunci baru, "Atur Peserta Otomatis" pada kartu kegiatan '
            . 'kedua selalu menghasilkan 0 peserta.',
        'cek' => [
            ['ada' => true, 'label' => 'index peserta_ujian.uq_peserta_jadwal (pendaftar_id, jadwal_id)',
             'sql' => "SELECT COUNT(*) FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peserta_ujian'
                         AND INDEX_NAME = 'uq_peserta_jadwal'"],
            ['ada' => false, 'label' => 'index lama peserta_ujian.uq_peserta_pendaftar sudah dilepas',
             'sql' => "SELECT COUNT(*) FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peserta_ujian'
                         AND INDEX_NAME = 'uq_peserta_pendaftar'"],
        ],
    ],
    [
        'file'  => '20261115_pendaftaran_dibuka.sql',
        'judul' => 'Saklar manual buka/tutup pendaftaran SPMB',
        'penting' => 'Tanpa kolom ini, tombol Buka/Tutup di Persiapan Tahun Ajaran '
            . 'gagal menyimpan dan formulir pendaftaran publik tidak bisa ditutup '
            . 'di antara dua musim SPMB.',
        'cek' => [
            ['ada' => true, 'label' => 'kolom pengaturan_sekolah.pendaftaran_dibuka',
             'sql' => "SELECT COUNT(*) FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
                         AND COLUMN_NAME = 'pendaftaran_dibuka'"],
        ],
    ],
];

/* ================================================================== *
 *  PUSTAKA KECIL
 * ================================================================== */

/**
 * Pecah isi berkas SQL menjadi satu pernyataan per elemen.
 * Komentar baris (pemisah dua tanda kurang, satu tanda kurang, pagar) dan
 * komentar blok dibuang, begitu pula kata kunci USE; tanda baca di dalam
 * petik (kutip satu, kutip dua, backtick) tidak dianggap pemisah. Berkas
 * migrasi di proyek ini tidak memakai DELIMITER/prosedur, jadi pemecah
 * sederhana-butahuruf ini cukup.
 */
function migrasi_pecah($isi)
{
    $stmts = [];
    $buf   = '';
    $petik = null;
    $len   = strlen($isi);

    for ($i = 0; $i < $len; $i++) {
        $c = $isi[$i];

        if ($petik !== null) {
            $buf .= $c;
            if ($c === '\\' && $petik !== '`' && $i + 1 < $len) {
                $buf .= $isi[++$i];
                continue;
            }
            if ($c === $petik) {
                if ($i + 1 < $len && $isi[$i + 1] === $petik) {
                    $buf .= $isi[++$i]; /* petik ganda = escape, masih di dalam */
                    continue;
                }
                $petik = null;
            }
            continue;
        }

        if ($c === "'" || $c === '"' || $c === '`') {
            $petik = $c;
            $buf  .= $c;
            continue;
        }
        if ($c === '-' && ($isi[$i + 1] ?? '') === '-') {
            while ($i < $len && $isi[$i] !== "\n") {
                $i++;
            }
            $buf .= "\n";
            continue;
        }
        if ($c === '#') {
            while ($i < $len && $isi[$i] !== "\n") {
                $i++;
            }
            $buf .= "\n";
            continue;
        }
        if ($c === '/' && ($isi[$i + 1] ?? '') === '*') {
            $i += 2;
            while ($i < $len && !($isi[$i] === '*' && ($isi[$i + 1] ?? '') === '/')) {
                $i++;
            }
            $i++;
            continue;
        }
        if ($c === ';') {
            $t = trim($buf);
            if ($t !== '') {
                $stmts[] = $t;
            }
            $buf = '';
            continue;
        }
        $buf .= $c;
    }

    $t = trim($buf);
    if ($t !== '') {
        $stmts[] = $t;
    }
    return $stmts;
}

/** Hitung satu SELECT COUNT(*) — null bila kuerinya gagal (mis. tabel tidak ada). */
function migrasi_hitung(PDO $pdo, $sql)
{
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
}

/** Semua pemeriksaan satu berkas sudah terpenuhi? */
function migrasi_sudah_pasangan(PDO $pdo, array $target)
{
    foreach ($target['cek'] as $c) {
        $n = migrasi_hitung($pdo, $c['sql']);
        if ($n === null) {
            return false;
        }
        if ($c['ada'] ? $n < 1 : $n > 0) {
            return false;
        }
    }
    return true;
}

/**
 * Jalankan satu berkas migrasi. return [status, pesan]
 * status: 'ok' | 'galat'
 */
function migrasi_jalankan(PDO $pdo, $path, array $target)
{
    if (!is_file($path)) {
        return ['galat', 'Berkas migrasi tidak ditemukan di ' . basename($path) . '.'];
    }
    $isi = file_get_contents($path);
    if ($isi === false) {
        return ['galat', 'Berkas ' . basename($path) . ' tidak terbaca.'];
    }

    $dijalankan = 0;
    $lewati     = 0;
    foreach (migrasi_pecah($isi) as $stmt) {
        if (preg_match('/^USE\b/i', $stmt)) {
            /* Nama database sudah benar karena koneksi memakai .env aplikasi. */
            $lewati++;
            continue;
        }
        try {
            $pdo->exec($stmt);
            $dijalankan++;
        } catch (PDOException $e) {
            $kode = (int) ($e->errorInfo[1] ?? 0);
            $pesan = $e->getMessage();
            if (in_array($kode, [1044, 1142, 1143], true)) {
                return ['galat', 'Akun database ini tidak punya hak ALTER/CREATE — jalankan '
                    . 'lewat phpMyAdmin (login sebagai pemilik database) atau isi kredensial '
                    . 'pemilik database di kolom "Akun database lain". Detail: ' . $pesan];
            }
            return ['galat', 'Pernyataan gagal (kode ' . ($kode ?: '?') . '): ' . $pesan];
        }
    }

    if (!migrasi_sudah_pasangan($pdo, $target)) {
        return ['galat', 'Pernyataan berjalan (' . $dijalankan . ' dijalankan'
            . ($lewati ? ', ' . $lewati . ' baris USE dilewati' : '') . ') tetapi hasil yang '
            . 'diharapkan belum terlihat di information_schema — periksa apakah ini benar-benar '
            . 'database aplikasi yang dipakai portal.'];
    }
    return ['ok', $dijalankan . ' pernyataan dijalankan'
        . ($lewati ? ', ' . $lewati . ' baris USE dilewati (nama database diambil dari .env)' : '') . '.'];
}

/* ================================================================== *
 *  ALUR: penanda -> kode -> (opsional) kredensial -> jalankan
 * ================================================================== */

$penanda = storage_path('.izinkan-migrasi');
if (!is_file($penanda)) {
    http_response_code(404);
    exit('Not Found');
}
$kodeFile = trim((string) file_get_contents($penanda));

$authorized = !empty($_SESSION['mg_authorized']);
$pesan      = null;
$selesaiSukses = false;
$sumberKoneksi = 'kredensial aplikasi (.env)';
$hasil      = [];   /* nama berkas => [status, pesan] */
$peraga     = [];   /* hasil pemeriksaan per berkas, untuk dirender */

/* Koneksi default = koneksi aplikasi. Kredensial lain hanya bila diketik,
 * dan hanya hidup selama request ini. */
function migrasi_koneksi(array $post)
{
    if (trim((string) ($post['db_user'] ?? '')) === '' && trim((string) ($post['db_pass'] ?? '')) === '') {
        return [db(), 'kredensial aplikasi (.env)'];
    }
    $user = trim((string) ($post['db_user'] ?? ''));
    if ($user === '') {
        return [db(), 'kredensial aplikasi (.env)'];
    }
    $host = trim((string) ($post['db_host'] ?? '')) ?: DB_HOST;
    $port = (int) (trim((string) ($post['db_port'] ?? '')) ?: DB_PORT);
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, DB_NAME),
            $user,
            (string) ($post['db_pass'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        return [$pdo, 'akun "' . $user . '" (tidak disimpan)'];
    } catch (Throwable $e) {
        return [null, 'gagal koneksi dengan akun "' . $user . '": ' . $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    /* Dua pencacah terpisah: salah ketik kode tidak boleh membuat tombol
     * "Jalankan" ikut terkunci, karena menjalankan ulang berkas migrasi yang
     * berpemeriksaan justru cara memperbaiki kegagalan di layar sebelumnya. */
    $langkah = (string) ($_POST['langkah'] ?? '');
    $aksi    = $langkah === 'jalan' ? 'migrasi_web_jalan' : 'migrasi_web_kode';
    rate_limit_catat($aksi);

    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        $pesan = 'Formulir kedaluwarsa — muat ulang halaman lalu coba lagi.';
    } elseif (!rate_limit_cek($aksi, $aksi === 'migrasi_web_jalan' ? 20 : 8, 60)) {
        $pesan = $aksi === 'migrasi_web_jalan'
            ? 'Terlalu banyak pelaksanaan migrasi (maks 20/jam per IP). Coba lagi nanti.'
            : 'Terlalu banyak percobaan kode (maks 8/jam per IP). Coba lagi nanti.';
    } else {
        if ($langkah === 'kode') {
            $kode = (string) ($_POST['kode_instalasi'] ?? '');
            if ($kodeFile === '' || strlen($kodeFile) < 8) {
                $pesan = 'Penanda storage/.izinkan-migrasi harus berisi kode acak minimal 8 karakter. '
                    . 'Perbaiki isinya lewat File Manager lalu muat ulang.';
            } elseif (!hash_equals($kodeFile, $kode)) {
                $pesan = 'Kode instalasi salah.';
            } else {
                $_SESSION['mg_authorized'] = true;
                $authorized = true;
            }
        } elseif ($langkah === 'jalan') {
            if (!$authorized) {
                $pesan = 'Sesi belum terverifikasi — ketik kode instalasi terlebih dahulu.';
            } else {
                list($pdo, $sumberKoneksi) = migrasi_koneksi($_POST);
                if ($pdo === null) {
                    $pesan = $sumberKoneksi === 'kredensial aplikasi (.env)'
                        ? 'Koneksi database aplikasi gagal — periksa .env di server.'
                        : $sumberKoneksi;
                } else {
                    $pdoRender = $pdo;
                    foreach ($TARGET as $t) {
                        $path = __DIR__ . '/../database/migrations/' . $t['file'];
                        if (migrasi_sudah_pasangan($pdo, $t)) {
                            list($status, $txt) = ['ok', 'Sudah terpasang — tidak ada yang perlu dikerjakan.'];
                        } else {
                            list($status, $txt) = migrasi_jalankan($pdo, $path, $t);
                        }
                        $hasil[$t['file']] = [$status, $txt];
                    }

                    /* Semua berkas harus benar-benar terverifikasi sebelum penanda dilepas. */
                    $semuaBersih = count($hasil) === count($TARGET);
                    foreach ($hasil as $h) {
                        if ($h[0] !== 'ok') {
                            $semuaBersih = false;
                        }
                    }
                    if ($semuaBersih) {
                        /* Penanda adalah satu-satunya gerbang: tanpa berkas itu
                         * alat ini kembali 404. Dirinya sendiri TIDAK dihapus agar
                         * batch migrasi berikutnya tinggal membuat penanda baru. */
                        @unlink($penanda);
                        unset($_SESSION['mg_authorized']);
                        $authorized = false;
                        $pesan = 'SEMUA target terverifikasi di database "' . DB_NAME . '" ('
                            . $sumberKoneksi . '). Penanda instalasi sudah dihapus, jadi alat ini '
                            . 'kembali tidak aktif (404).';
                        $selesaiSukses = true;
                    } else {
                        $pesan = 'Dijalankan di database "' . DB_NAME . '" (' . $sumberKoneksi . ').'
                            . ' Periksa baris yang masih merah di bawah, lalu jalankan ulang — '
                            . 'berkas migrasinya berpemeriksaan sehingga aman diulang.';
                    }
                }
            }
        } else {
            $pesan = 'Langkah tidak dikenal.';
        }
    }
}

/* Pemeriksaan per berkas untuk dirender (hanya setelah kode valid).
 * Pakai koneksi yang barusan dipakai menjalankan, supaya tabel status dan
 * tombol "Jalankan" menunjuk database yang sama. */
if ($authorized || $hasil) {
    $pdoRender = $pdoRender ?? db();
    foreach ($TARGET as $t) {
        $baris = [];
        foreach ($t['cek'] as $c) {
            $n = $pdoRender ? migrasi_hitung($pdoRender, $c['sql']) : null;
            $baris[] = ['label' => $c['label'], 'pas' => $n !== null && ($c['ada'] ? $n >= 1 : $n === 0), 'hitung' => $n];
        }
        $peraga[$t['file']] = $baris;
    }
}

$csrf = csrf_token();
$host = defined('DB_HOST') ? DB_HOST : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Migrasi Database — SPMB</title>
<style>
  body { font-family: system-ui, sans-serif; background: #f1f5f9; min-height: 100vh; padding: 1.5rem; }
  .wrap { max-width: 760px; margin: 0 auto; }
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.5rem; box-shadow: 0 8px 24px rgba(15,23,42,.06); }
  h1 { font-size: 1.15rem; margin: 0 0 .35rem; color: #0f172a; }
  h2 { font-size: .95rem; margin: 1.25rem 0 .35rem; color: #0f172a; }
  p.hint { font-size: .8rem; color: #64748b; margin: 0 0 1rem; line-height: 1.6; }
  label { display: block; font-size: .78rem; font-weight: 600; color: #334155; margin: .7rem 0 .2rem; }
  input { width: 100%; box-sizing: border-box; padding: .55rem .7rem; border: 1px solid #cbd5e1; border-radius: 10px; font-size: .88rem; }
  button { margin-top: 1.1rem; padding: .65rem 1.1rem; border: 0; border-radius: 10px; background: #b91c1c; color: #fff; font-weight: 700; font-size: .88rem; cursor: pointer; }
  button:hover { background: #991b1b; }
  .err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 10px; padding: .6rem .8rem; font-size: .82rem; margin-bottom: .75rem; line-height: 1.6; }
  .ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 10px; padding: .6rem .8rem; font-size: .82rem; line-height: 1.6; }
  .info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; border-radius: 10px; padding: .6rem .8rem; font-size: .8rem; line-height: 1.6; margin-bottom: 1rem; }
  table { width: 100%; border-collapse: collapse; font-size: .8rem; }
  th, td { text-align: left; padding: .45rem .5rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  th { color: #475569; font-size: .74rem; text-transform: uppercase; letter-spacing: .03em; }
  .ya { color: #065f46; font-weight: 700; }
  .tidak { color: #b91c1c; font-weight: 700; }
  .detail { font-size: .74rem; color: #94a3b8; margin-top: 4px; }
  .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Migrasi Database SPMB</h1>

    <?php if (!empty($selesaiSukses)): ?>
      <div class="ok">
        <?= e($pesan) ?><br><br>
        <strong>Selesai.</strong> Buka halaman ini lagi untuk memastikan isinya 404.
        Untuk batch migrasi berikutnya: tambahkan nama berkasnya di <code>$TARGET</code>
        pada berkas ini, buat lagi <code>storage/.izinkan-migrasi</code> berisi kode acak,
        lalu jalankan dari halaman ini — tidak perlu SSH dan tidak perlu menyunting baris
        <code>USE</code> di berkas SQL.
        <table style="margin-top:.8rem">
          <tr><th>Berkas</th><th>Catatan</th></tr>
          <?php foreach ($TARGET as $t): ?>
            <tr>
              <td><code><?= e($t['file']) ?></code></td>
              <td><span class="ya">terverifikasi</span>
                <div class="detail"><?= e($hasil[$t['file']][1] ?? '') ?></div></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php elseif (!$authorized): ?>
      <p class="hint">
        Alat instalasi sekali-pakai untuk menjalankan berkas migrasi
        <code>database/migrations/*.sql</code> yang terdaftar di halaman ini di database yang sedang dipakai portal ini —
        tanpa SSH dan tanpa menyunting baris <code>USE</code> di berkas SQL.
        Masukkan <strong>KODE INSTALASI</strong> yang tertulis di dalam
        <code>storage/.izinkan-migrasi</code> (Anda yang membuatnya lewat File Manager).
        Selama kode belum benar, tidak ada satu pun informasi struktur database yang ditampilkan.
      </p>
      <?php if ($pesan): ?><div class="err"><?= e($pesan) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="langkah" value="kode">
        <label for="kode_instalasi">Kode instalasi</label>
        <input id="kode_instalasi" name="kode_instalasi" type="password" required
               minlength="8" placeholder="isi berkas .izinkan-migrasi" autofocus>
        <button type="submit">Verifikasi kode</button>
      </form>
      <p class="hint" style="margin-top:.9rem">Maks 8 percobaan kode per jam per IP — terpisah dari batas tombol "Jalankan" (20/jam).</p>
    <?php else: ?>
      <div class="info">
        Target: <strong>database &quot;<?= e(DB_NAME) ?>&quot;</strong> di
        <code><?= e($host) ?><?= DB_PORT !== 3306 ? ':' . e((string) DB_PORT) : '' ?></code>,
        akun database <code><?= e(DB_USER) ?></code>.
        Nama database diambil dari <code>.env</code> aplikasi, jadi tidak ada baris
        <code>USE</code> yang perlu diedit — dan itulah sebabnya alat ini ada.
      </div>

      <?php if ($pesan): ?><div class="err"><?= e($pesan) ?></div><?php endif; ?>

      <h2>Yang akan diperiksa &amp; dijalankan</h2>
      <table>
        <tr><th>Berkas</th><th>Hasil yang diharapkan</th><th>Status</th></tr>
        <?php foreach ($TARGET as $t): $fd = $t['file']; ?>
          <tr>
            <td>
              <code><?= e($fd) ?></code>
              <div class="detail"><?= e($t['judul']) ?></div>
              <div class="detail"><?= e($t['penting']) ?></div>
            </td>
            <td>
              <?php foreach ($peraga[$fd] ?? [] as $b): ?>
                <div><?= e($b['label']) ?> <span class="detail">(hitung: <?= $b['hitung'] === null ? '?' : (int) $b['hitung'] ?>)</span></div>
              <?php endforeach; ?>
            </td>
            <td>
              <?php $sudah = !empty($peraga[$fd]) && array_reduce($peraga[$fd], fn($c, $b) => $c && $b['pas'], true); ?>
              <?php if (isset($hasil[$fd])): ?>
                <span class="<?= $hasil[$fd][0] === 'ok' ? 'ya' : 'tidak' ?>"><?= $hasil[$fd][0] === 'ok' ? 'SELESAI' : 'GAGAL' ?></span>
                <div class="detail"><?= e($hasil[$fd][1]) ?></div>
              <?php elseif ($sudah): ?>
                <span class="ya">sudah terpasang</span>
              <?php else: ?>
                <span class="tidak">belum terpasang</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>

      <form method="post" autocomplete="off" style="margin-top:1.25rem">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="langkah" value="jalan">
        <button type="submit" onclick="return confirm('Jalankan berkas migrasi pada database aplikasi ini?')">
          Jalankan migrasi tertunda
        </button>
        <p class="hint" style="margin-top:.7rem">
          Berkas migrasi di repo ini berpemeriksaan (memakai <code>information_schema</code> +
          <code>PREPARE</code>), jadi menekannya ulang tidak mengubah apa pun yang sudah terpasang
          dan tidak menyentuh data pendaftar.
        </p>

        <h2>Akun database lain (opsional)</h2>
        <p class="hint">
          Hanya perlu bila akun di <code>.env</code> tidak punya hak <code>ALTER</code> — di cPanel
          biasanya pemilik database adalah akun lain. Kredensial ini dipakai sekali untuk request ini:
          tidak disimpan di sesi, tidak ditulis ke berkas, dan tidak muncul lagi setelah halaman termuat.
        </p>
        <div class="grid2">
          <div>
            <label for="db_user">User database</label>
            <input id="db_user" name="db_user" type="text" autocomplete="off" placeholder="kosongkan = akun .env">
          </div>
          <div>
            <label for="db_pass">Password</label>
            <input id="db_pass" name="db_pass" type="password" autocomplete="new-password">
          </div>
          <div>
            <label for="db_host">Host (opsional)</label>
            <input id="db_host" name="db_host" type="text" autocomplete="off" placeholder="<?= e($host) ?>">
          </div>
          <div>
            <label for="db_port">Port (opsional)</label>
            <input id="db_port" name="db_port" type="text" autocomplete="off" placeholder="<?= e((string) DB_PORT) ?>">
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
