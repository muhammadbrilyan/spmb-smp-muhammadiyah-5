<?php
/* ==================================================================
 * API UPLOAD BUKTI BAYAR PUBLIK — tanpa login, diakses via pay_token.
 * Dipanggil dari halaman bayar.php (transfer & QRIS).
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'pesan' => 'Metode tidak diizinkan.']);
    exit;
}

/* CSRF — token sesi halaman bayar.php. */
if (!csrf_cek($_POST['_csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Formulir kedaluwarsa. Silakan muat ulang halaman.']);
    exit;
}

/* Rate limit: maks 10 percobaan per jam per IP — dicatat SETIAP percobaan
 * yang lolos cek (termasuk yang kemudian gagal validasi). */
if (!rate_limit_cek('bayar_bukti', 10, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'pesan' => 'Terlalu banyak percobaan unggah. Coba lagi nanti.']);
    exit;
}
rate_limit_catat('bayar_bukti');

$bayarId    = trim((string) ($_POST['bayar_id'] ?? ''));
$metode     = strtoupper(trim((string) ($_POST['metode'] ?? '')));
$pendaftarId = trim((string) ($_POST['pendaftar_id'] ?? ''));
$payToken   = trim((string) ($_POST['pay_token'] ?? ''));

/* Token tautan bayar wajib — 64 hex, bukan sekadar ID yang bisa ditebak. */
if (!preg_match('/^[a-f0-9]{64}$/', $payToken)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Token pembayaran tidak valid.']);
    exit;
}

/* Validasi metode. */
if (!in_array($metode, ['TRANSFER', 'QRIS'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Metode pembayaran tidak valid.']);
    exit;
}

/* Metode harus diaktifkan di Pengaturan Sistem. */
$toggleMetode = $metode === 'TRANSFER' ? 'bayar_transfer_aktif' : 'bayar_qris_aktif';
if (empty($PENGATURAN[$toggleMetode])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Metode pembayaran ' . ($metode === 'TRANSFER' ? 'transfer' : 'QRIS') . ' sedang tidak aktif.']);
    exit;
}

/* Cari pendaftar lewat pay_token (bukan percaya ID kiriman klien). */
if (!function_exists('pendaftar_by_pay_token')) {
    require_once __DIR__ . '/../../includes/queries/pendaftar.php';
}
$baris = pendaftar_by_pay_token($payToken);
if (!$baris || $baris['id'] !== $pendaftarId || (string) $baris['bayar_id'] !== $bayarId) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Permintaan tidak valid.']);
    exit;
}

/* Sudah terverifikasi? → tolak. */
if (in_array($baris['bayar_status'], ['VERIFIED', 'LUNAS'], true)) {
    echo json_encode(['ok' => false, 'pesan' => 'Pembayaran sudah terverifikasi.']);
    exit;
}

/* Tahap pendaftar harus masih dalam masa pembayaran / verifikasi bukti. */
if (!in_array($baris['tahap'], ['MENUNGGU_PEMBAYARAN', 'MENUNGGU_VERIFIKASI_BAYAR'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'pesan' => 'Tidak dalam masa unggah bukti pembayaran.']);
    exit;
}

/* Database harus siap sebelum file diterima (tolak dini, tanpa file yatim). */
$db = db();
if ($db === null) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Database tidak aktif.']);
    exit;
}

/* === Validasi file unggahan === */
if (empty($_FILES['bukti'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Berkas bukti tidak ditemukan.']);
    exit;
}

$f = $_FILES['bukti'];
if ($f['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Unggahan gagal (kode ' . $f['error'] . ').']);
    exit;
}

/* Ukuran maks 2 MB. */
if ($f['size'] > 2 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Ukuran berkas maksimal 2 MB.']);
    exit;
}

/* Ekstensi whitelist. */
$ekstensi = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$izinEkstensi = ['jpg', 'jpeg', 'png', 'pdf'];
if (!in_array($ekstensi, $izinEkstensi, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Format berkas harus JPG, PNG, atau PDF.']);
    exit;
}

/* MIME sniff asli. */
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $f['tmp_name']);
finfo_close($finfo);
$izinMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'application/pdf' => 'pdf',
];
if (!isset($izinMime[$mime])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Tipe berkas tidak diizinkan.']);
    exit;
}

/* Pastikan ekstensi dan MIME cocok. */
if ($izinMime[$mime] !== $ekstensi && !($izinMime[$mime] === 'jpg' && $ekstensi === 'jpeg')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'pesan' => 'Ekstensi dan tipe berkas tidak cocok.']);
    exit;
}

/* Simpan ke storage/dokumen (private). */
$targetDir = storage_path('dokumen');
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$namaFile = 'bukti-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ekstensi;
$pathRel  = 'storage/dokumen/' . $namaFile;
$pathAbs  = $targetDir . '/' . $namaFile;

if (!move_uploaded_file($f['tmp_name'], $pathAbs)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Gagal menyimpan berkas.']);
    exit;
}

/* Urutan: file baru sudah tersimpan → transaksi DB → hapus bukti lama. */
$pathLama = null;
try {
    $db->beginTransaction();

    $stmtLama = $db->prepare(
        'SELECT bukti_transfer_url FROM pembayaran WHERE id = ? AND pendaftar_id = ?'
    );
    $stmtLama->execute([$bayarId, $pendaftarId]);
    $pathLama = $stmtLama->fetchColumn() ?: null;

    $db->prepare(
        'UPDATE pembayaran SET bukti_transfer_url = ?, metode = ?, status = "PENDING",
                keterangan = "Menunggu verifikasi bukti ' . $metode . '"
          WHERE id = ? AND pendaftar_id = ?'
    )->execute([$pathRel, $metode, $bayarId, $pendaftarId]);

    $db->prepare(
        'UPDATE pendaftar SET tahap = "MENUNGGU_VERIFIKASI_BAYAR" WHERE id = ? AND tahap = "MENUNGGU_PEMBAYARAN"'
    )->execute([$pendaftarId]);

    $db->commit();
} catch (Throwable $e) {
    if (method_exists($db, 'rollBack')) {
        try { $db->rollBack(); } catch (Throwable $x) { /* abaikan */ }
    }
    @unlink($pathAbs);
    http_response_code(500);
    echo json_encode(['ok' => false, 'pesan' => 'Gagal menyimpan data pembayaran.']);
    exit;
}

/* Transaksi sukses → bersihkan bukti lama agar tidak menumpuk di storage.
 * Hanya file bernama bukti-* di dalam storage/dokumen (tolak path aneh). */
if ($pathLama !== null && $pathLama !== '' && $pathLama !== $pathRel) {
    $namaLama = basename($pathLama);
    if (strpos($pathLama, 'storage/dokumen/') === 0
        && preg_match('/^bukti-\d{8}-\d{6}-[a-f0-9]{8}\.(jpg|jpeg|png|pdf)$/', $namaLama)) {
        @unlink($targetDir . '/' . $namaLama);
    }
}

/* Notifikasi operator (Fase 3) — tidak boleh menggagalkan unggahan. */
try {
    $tautanVerif = url_abs('app/bendahara/index.php');
    notif_operator(
        'BUKTI_BAYAR_MASUK',
        $pendaftarId,
        "Bukti pembayaran baru menunggu verifikasi.\n"
        . "Nama : {$baris['nama_siswa']}\n"
        . "No. Pendaftaran : {$baris['no_pendaftaran']}\n"
        . "Metode : {$metode}\n"
        . "Verifikasi: {$tautanVerif}"
    );
} catch (Throwable $e) {
    /* abaikan */
}

echo json_encode(['ok' => true, 'pesan' => 'Bukti berhasil dikirim.']);
