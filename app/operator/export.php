<?php
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin', 'bendahara']);

$jenis = $_GET['rekap'] ?? '';
$izin  = ['pendaftar', 'seleksi', 'pembayaran'];

/* Bendahara hanya punya dashboard keuangan — jangan lempar ke dashboard operator. */
$kembali = current_user()['role'] === 'bendahara'
    ? url('app/bendahara/index.php')
    : url('app/operator/index.php');

if (!in_array($jenis, $izin, true)) {
    flash('error', 'Jenis rekap tidak dikenal.');
    header('Location: ' . $kembali);
    exit;
}

$data = export_rekap($jenis);
if (!$data['header'] || !$data['baris']) {
    flash('error', 'Tidak ada data untuk diekspor.');
    header('Location: ' . $kembali);
    exit;
}

$namaFile = 'rekap-' . $jenis . '-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $namaFile . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
/* BOM UTF-8 agar Excel membaca karakter Indonesia dengan benar. */
fwrite($out, "\xEF\xBB\xBF");

/**
 * Cegah formula injection di Excel/Sheets.
 * Nilai yang diawali =, +, -, @, \t, \r diberi awalan apostrophe
 * agar Excel membaca sebagai teks biasa, bukan formula.
 */
function csv_aman($v)
{
    if ($v === null) {
        return '';
    }
    if (is_float($v)) {
        return number_format($v, 2, '.', '');
    }
    $s = (string) $v;
    if ($s !== '' && strpbrk($s[0], "=+-@\t\r") !== false) {
        $s = "'" . $s;
    }
    return $s;
}

fputcsv($out, array_map('csv_aman', $data['header']), ';');
foreach ($data['baris'] as $baris) {
    fputcsv($out, array_map('csv_aman', $baris), ';');
}
fclose($out);
exit;
