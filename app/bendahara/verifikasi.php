<?php
/**
 * POST — verifikasi / penolakan pembayaran & "Verifikasi & Kirim Akun" (Fase 4).
 * Hak akses: bendahara + operator (admin penuh mengikuti role operator).
 *
 * Aksi:
 *   verifikasi          → VERIFIED + tahap BAYAR_TERVERIFIKASI
 *   tolak               → REJECTED + tahap MENUNGGU_PEMBAYARAN + WA alasan
 *                         (alasan WAJIB diisi)
 *   verifikasi_kirim_akun → satu klik: verifikasi + buat akun + kirim
 *                         kredensial WA + tahap AKUN_TERKIRIM → MELENGKAPI_BERKAS
 */
require_once __DIR__ . '/../../includes/config.php';
require_role(['bendahara', 'operator']);

$balik = url('app/bendahara/index.php');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ' . $balik);
    exit;
}

if (!csrf_cek($_POST['_csrf'] ?? '')) {
    flash('error', 'Formulir kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.');
    header('Location: ' . $balik);
    exit;
}

$aksi = (string) ($_POST['aksi'] ?? '');
$id   = trim((string) ($_POST['pembayaran_id'] ?? ''));

if (!in_array($aksi, ['verifikasi', 'tolak', 'verifikasi_kirim_akun'], true) || $id === '') {
    flash('error', 'Permintaan tidak dikenali.');
    header('Location: ' . $balik);
    exit;
}

$me      = current_user();
$meId    = $me['id'] ?? null;
$alasan  = trim((string) ($_POST['keterangan'] ?? ''));

if ($aksi === 'tolak') {
    if ($alasan === '') {
        flash('error', 'Alasan penolakan wajib diisi.');
        header('Location: ' . $balik);
        exit;
    }
    $hasil = verifikasi_bayar_tolak($id, $alasan, $meId);
    if (!$hasil['ok']) {
        flash('error', $hasil['pesan'] ?? 'Gagal memproses penolakan.');
    } elseif (!empty($hasil['sudah'])) {
        flash('sukses', $hasil['pesan']);
    } else {
        $waInfo = !empty($hasil['wa_ok'])
            ? 'WA alasan terkirim ke orang tua.'
            : 'Catatan: ' . ($hasil['wa_pesan'] ?? 'WA tidak terkirim.');
        flash('sukses', 'Pembayaran ' . $hasil['reg'] . ' ditolak. ' . $waInfo);
    }
    header('Location: ' . $balik);
    exit;
}

if ($aksi === 'verifikasi_kirim_akun') {
    $hasil = verifikasi_kirim_akun($id, $meId);
    if (!$hasil['ok']) {
        flash('error', $hasil['pesan'] ?? 'Gagal memproses verifikasi & kirim akun.');
    } else {
        $kredInfo = (!empty($hasil['kred']))
            ? ' Username: ' . $hasil['kred']['username'] . '.'
            : '';
        flash('sukses', $hasil['pesan'] . $kredInfo);
    }
    header('Location: ' . $balik);
    exit;
}

/* aksi === 'verifikasi' */
$hasil = verifikasi_bayar_setujui($id, $meId);
if (!$hasil['ok']) {
    flash('error', $hasil['pesan'] ?? 'Gagal menyimpan verifikasi pembayaran.');
} elseif (!empty($hasil['sudah'])) {
    flash('sukses', 'Pembayaran ' . $hasil['reg'] . ' sudah terverifikasi — tidak diproses ulang.');
} else {
    flash('sukses', 'Pembayaran ' . $hasil['reg'] . ' (' . rupiah($hasil['nominal'] ?? 0) . ') berhasil diverifikasi.');
}

header('Location: ' . $balik);
exit;
