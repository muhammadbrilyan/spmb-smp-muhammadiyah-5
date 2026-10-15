<?php
/**
 * KLIENT WHATSAPP GATEWAY — Fonnte.
 *
 * Token API diambil dari .env (FONNTE_TOKEN), fallback CMS (pengaturan_sekolah.fonnte_token).
 * Semua fungsi aman dipanggil saat token kosong / jaringan mati:
 * mengembalikan ['ok' => false, 'pesan' => '<alasan>'] tanpa exception.
 */

/** Token Fonnte dari .env (FONNTE_TOKEN) atau CMS. null bila belum diatur. */
function fonnte_token()
{
    $t = trim((string) (env_val('FONNTE_TOKEN', $GLOBALS['PENGATURAN']['fonnte_token'] ?? '')));
    return $t !== '' ? $t : null;
}

/** Normalisasi nomor WA Indonesia ke format internasional 628xx. */
function wa_normalize($nomor)
{
    $d = preg_replace('/\D+/', '', (string) $nomor);
    if ($d === '') {
        return '';
    }
    if (strpos($d, '0') === 0) {
        $d = '62' . substr($d, 1);
    } elseif (strpos($d, '8') === 0) {
        $d = '62' . $d;
    }
    return $d;
}

/**
 * Kirim pesan WhatsApp via Fonnte.
 * @return array{ok:bool, pesan:string, id:string|null}
 */
function wa_kirim($nomor, $pesan)
{
    $token = fonnte_token();
    if ($token === null) {
        return ['ok' => false, 'pesan' => 'Token Fonnte belum diatur di Pengaturan Sistem.', 'id' => null];
    }
    $target = wa_normalize($nomor);
    if ($target === '') {
        return ['ok' => false, 'pesan' => 'Nomor WhatsApp tujuan kosong/tidak valid.', 'id' => null];
    }
    if (trim((string) $pesan) === '') {
        return ['ok' => false, 'pesan' => 'Pesan tidak boleh kosong.', 'id' => null];
    }

    $ch = curl_init('https://api.fonnte.com/send');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'target'  => $target,
            'message' => $pesan,
            'typing'  => 'off',
            'delay'   => 1,
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: ' . $token,
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'pesan' => 'Gateway tidak terjangkau: ' . $err, 'id' => null];
    }
    $j = json_decode((string) $body, true);
    if (!is_array($j)) {
        return ['ok' => false, 'pesan' => 'Respons gateway tidak valid.', 'id' => null];
    }
    if (!empty($j['status'])) {
        return ['ok' => true, 'pesan' => 'Terkirim.', 'id' => isset($j['id']) ? (string) $j['id'] : null];
    }
    $alasan = isset($j['reason']) ? (string) $j['reason'] : (string) ($j['message'] ?? 'Gagal.');
    return ['ok' => false, 'pesan' => 'Fonnte: ' . $alasan, 'id' => null];
}

/**
 * Susun pesan kredensial akun siswa.
 * @param array $data nama, reg, username, password, tahun, spp
 */
function wa_pesan_kredensial(array $data)
{
    $ta   = $data['tahun'] ?? '';
    $spp  = isset($data['spp']) ? number_format((float) $data['spp'], 0, ',', '.') : '';
    $teks = "Assalamu'alaikum.\n\n"
        . "Akun SPMB/Portal Siswa telah dibuat untuk:\n"
        . "Nama : {$data['nama']}\n"
        . "No. REG : {$data['reg']}\n\n"
        . "Username : {$data['username']}\n"
        . "Password : {$data['password']}\n\n"
        . "Silakan login di portal sekolah. Mohon simpan kredensial ini baik-baik dan segera ganti password setelah login pertama.";
    if ($ta !== '') {
        $teks .= "\n\nTahun Ajaran: {$ta}";
    }
    if ($spp !== '') {
        $teks .= "\nInformasi biaya SPP terbaru tersedia di halaman publik sekolah.";
    }
    $teks .= "\n\nSalam,\nPanitia SPMB SMP Muhammadiyah 5 Kota Tangerang";
    return $teks;
}

/** Password acak yang aman untuk akun siswa (8–12 karakter, ada huruf+angka). */
function password_acak()
{
    $huruf = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $kecil = 'abcdefghjkmnpqrstuvwxyz';
    $angka = '23456789';
    $semua = $huruf . $kecil . $angka;
    $pw = $huruf[random_int(0, strlen($huruf) - 1)]
        . $kecil[random_int(0, strlen($kecil) - 1)]
        . $angka[random_int(0, strlen($angka) - 1)];
    for ($i = 0; $i < 7; $i++) {
        $pw .= $semua[random_int(0, strlen($semua) - 1)];
    }
    /* acak urutan */
    $pw = str_split($pw);
    for ($i = count($pw) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$pw[$i], $pw[$j]] = [$pw[$j], $pw[$i]];
    }
    return implode('', $pw);
}

/** Pesan WA instruksi pembayaran setelah registrasi awal. */
function wa_pesan_instruksi(array $r)
{
    $biaya = number_format((float) ($GLOBALS['PENGATURAN']['biaya_formulir'] ?? 0), 0, ',', '.');
    /* Tautan praktis (Fase 10): bayar/unggah bukti tanpa menunggu akun
     * (tautan pay_token) + cek status publik. */
    $tautanBayar = !empty($r['pay_token'])
        ? url_abs('app/spmb/bayar.php?t=' . $r['pay_token'])
        : '';
    $tautanCek = url_abs('app/spmb/cek-status.php');

    $langkah23 = $tautanBayar !== ''
        ? "2. Bayar / unggah bukti transfer lewat tautan ini:\n   {$tautanBayar}\n"
          . "3. Lengkapi biodata & berkas persyaratan di portal setelah akun dibuat panitia.\n"
        : "2. Unggah bukti transfer melalui portal siswa setelah akun dibuat operator.\n"
          . "3. Lengkapi biodata & berkas persyaratan di portal.\n";

    return "Assalamu'alaikum.\n\n"
        . "Terima kasih telah mendaftar SPMB " . ($GLOBALS['PENGATURAN']['nama_sekolah'] ?? 'SMP Muhammadiyah 5') . ".\n\n"
        . "No. Pendaftaran : {$r['no']}\n"
        . "Nama Siswa : {$r['nama']}\n"
        . "Jalur : {$r['jalur']}\n\n"
        . "Langkah selanjutnya:\n"
        . "1. Transfer biaya formulir sebesar Rp {$biaya} ke:\n"
        . "   " . ($GLOBALS['PENGATURAN']['nama_bank'] ?? '') . "\n"
        . "   No. Rek : " . ($GLOBALS['PENGATURAN']['no_rekening'] ?? '') . "\n"
        . "   a.n. " . ($GLOBALS['PENGATURAN']['atas_nama_rekening'] ?? '') . "\n"
        . $langkah23
        . "\nCek status pendaftaran (siapkan 4 digit WA orang tua):\n   {$tautanCek}\n\n"
        . "Simpan nomor pendaftaran ini.";
}
