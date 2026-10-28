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
        /* 'typing' => 'off' ditolak Fonnte dengan reason menyesatkan
         * "invalid/empty body value" (teruji 2026-10-28); nilai yang diterima
         * adalah 'false'/'0'/'on'. Transport urlencoded sendiri tidak masalah. */
        CURLOPT_POSTFIELDS     => http_build_query([
            'target'  => $target,
            'message' => $pesan,
            'typing'  => 'false',
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
        return ['ok' => true, 'pesan' => 'Terkirim.', 'id' => wa_teks($j['id'] ?? null) ?: null];
    }
    $alasan = wa_teks($j['reason'] ?? ($j['message'] ?? null));
    return ['ok' => false, 'pesan' => 'Fonnte: ' . ($alasan !== '' ? $alasan : 'Gagal.'), 'id' => null];
}

/** Fonnte ada yang mengirim field sebagai array (mis. "id":[123]) — ratakan ke satu teks. */
function wa_teks($v)
{
    if (is_array($v)) {
        $v = $v ? reset($v) : '';
    }
    return is_scalar($v) ? trim((string) $v) : '';
}

/**
 * Uji sebuah token ke Fonnte lewat endpoint /device.
 * TIDAK mengirim pesan WhatsApp apa pun — dipakai untuk memberi umpan balik
 * langsung di Pengaturan Sistem (operator tidak bisa menebak apakah token benar).
 *
 * @param string|null $token token spesifik; null = token efektif (.env lalu DB)
 * @return array{ok:bool,pesan:string,data:array}
 */
function fonnte_cek_token($token = null)
{
    $token = is_string($token) && trim($token) !== '' ? trim($token) : fonnte_token();
    if ($token === null) {
        return ['ok' => false, 'pesan' => 'Token belum diisi.', 'data' => []];
    }

    $ch = curl_init('https://api.fonnte.com/device');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $token],
    ]);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['ok' => false, 'pesan' => 'Gateway tidak terjangkau: ' . $err, 'data' => []];
    }
    $j = json_decode((string) $body, true);
    if (!is_array($j)) {
        return ['ok' => false, 'pesan' => 'Respons gateway tidak valid.', 'data' => []];
    }
    if (empty($j['status'])) {
        return ['ok' => false, 'pesan' => 'Token ditolak Fonnte (' . (wa_teks($j['reason'] ?? '') ?: 'tanpa alasan') . ').', 'data' => []];
    }

    $dev    = wa_teks($j['device'] ?? '');
    $status = wa_teks($j['device_status'] ?? '');
    $nama   = wa_teks($j['name'] ?? '');
    $kuota  = wa_teks($j['quota'] ?? '');
    $kadrsa = wa_teks($j['expired'] ?? '');
    $info   = 'Device ' . ($dev !== '' ? $dev : '—')
        . ($nama !== '' ? ' ("' . $nama . '")' : '')
        . ', kuota ' . ($kuota !== '' ? $kuota : '?')
        . ($kadrsa !== '' ? ', kedaluwarsa ' . $kadrsa : '');
    if ($status !== '' && $status !== 'connect') {
        return ['ok' => false, 'pesan' => 'Token benar, tetapi perangkat ' . $status . ' — WA tidak akan terkirim sampai nomor Fonnte ter-connect. ' . $info, 'data' => $j];
    }
    return ['ok' => true, 'pesan' => 'Token aktif — ' . $info, 'data' => $j];
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

/**
 * Pesan WA instruksi pembayaran setelah registrasi awal.
 * Fase 11 A4: hanya menampilkan metode yang AKTIF di pengaturan
 * (transfer / QRIS / gateway online) — rekening tidak ditampilkan
 * bila transfer nonaktif. Langkah akhir: akun & password dikirim via WA.
 */
function wa_pesan_instruksi(array $r)
{
    $P = $GLOBALS['PENGATURAN'];
    $biaya = number_format((float) ($P['biaya_formulir'] ?? 0), 0, ',', '.');
    $transferAktif = !empty($P['bayar_transfer_aktif']);
    $qrisAktif     = !empty($P['bayar_qris_aktif']);
    $gatewayAktif  = function_exists('gateway_tersedia') && gateway_tersedia();

    /* Tautan praktis (Fase 10): bayar/unggah bukti tanpa menunggu akun
     * (tautan pay_token) + cek status publik. */
    $tautanBayar = !empty($r['pay_token'])
        ? url_abs('app/spmb/bayar.php?t=' . $r['pay_token'])
        : '';
    $tautanCek = url_abs('app/spmb/cek-status.php');

    /* Susun langkah hanya dari metode aktif. */
    $langkah = [];
    if ($transferAktif) {
        $langkah[] = "Transfer biaya formulir sebesar Rp {$biaya} ke:\n"
            . "   " . ($P['nama_bank'] ?? '') . "\n"
            . "   No. Rek : " . ($P['no_rekening'] ?? '') . "\n"
            . "   a.n. " . ($P['atas_nama_rekening'] ?? '');
    }
    if ($qrisAktif) {
        $langkah[] = 'Scan QRIS untuk membayar biaya formulir' . ($transferAktif ? ' (alternatif transfer)' : '') . '.';
    }
    if ($gatewayAktif) {
        $langkah[] = 'Atau bayar online (virtual account, e-wallet, kartu) melalui halaman pembayaran.';
    }
    if (!$langkah) {
        $langkah[] = 'Silakan hubungi panitia untuk informasi metode pembayaran.';
    }
    if ($tautanBayar !== '') {
        $langkah[] = "Pembayaran & unggah bukti melalui tautan ini:\n   {$tautanBayar}";
    }
    /* Fase 11 A4 — langkah akhir yang selalu tampil. */
    $langkah[] = 'Setelah pembayaran terverifikasi, akun dan password dikirim lewat WhatsApp.';

    $no = 1;
    $blok = '';
    foreach ($langkah as $l) {
        $blok .= $no++ . '. ' . $l . "\n";
    }

    return "Assalamu'alaikum.\n\n"
        . "Terima kasih telah mendaftar SPMB " . ($P['nama_sekolah'] ?? 'SMP Muhammadiyah 5') . ".\n\n"
        . "No. Pendaftaran : {$r['no']}\n"
        . "Nama Siswa : {$r['nama']}\n"
        . "Jalur : {$r['jalur']}\n\n"
        . "Langkah selanjutnya:\n"
        . $blok
        . "\nCek status pendaftaran (siapkan 4 digit WA orang tua):\n   {$tautanCek}\n\n"
        . "Simpan nomor pendaftaran ini.";
}
