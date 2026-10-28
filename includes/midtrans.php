<?php
/**
 * KLIENT PAYMENT GATEWAY — Midtrans Snap.
 *
 * Konfigurasi diambil dari .env (utama) atau CMS (fallback):
 *   - MIDTRANS_SERVER_KEY : rahasia, dipakai server-side API
 *   - MIDTRANS_CLIENT_KEY : publik, dipakai Snap.js di browser
 *   - MIDTRANS_SANDBOX    : 1=sandbox (dev), 0=production
 *
 * Semua fungsi aman dipanggil saat key belum diatur:
 * mengembalikan ['ok'=>false, 'pesan'=>'<alasan>'] tanpa exception.
 */

/** Base URL API Midtrans sesuai mode sandbox/production. */
function midtrans_api_base()
{
    $sandbox = midtrans_mode_sandbox();
    return $sandbox ? 'https://api.sandbox.midtrans.com' : 'https://api.midtrans.com';
}

/** Base URL Snap sesuai mode. */
function midtrans_snap_base()
{
    $sandbox = midtrans_mode_sandbox();
    return $sandbox ? 'https://app.sandbox.midtrans.com/snap/snap.js' : 'https://app.midtrans.com/snap/snap.js';
}

/** Flag sandbox: .env MIDTRANS_SANDBOX dulu, fallback CMS. 1=sandbox, 0=production. */
function midtrans_mode_sandbox()
{
    $env = env_val('MIDTRANS_SANDBOX', null);
    if ($env !== null) {
        return (int) $env === 1;
    }
    return (int) ($GLOBALS['PENGATURAN']['midtrans_sandbox'] ?? 1) === 1;
}

/** Server Key dari .env (MIDTRANS_SERVER_KEY) atau CMS. null bila belum diatur. */
function midtrans_server_key()
{
    $t = trim((string) (env_val('MIDTRANS_SERVER_KEY', $GLOBALS['PENGATURAN']['midtrans_server_key'] ?? '')));
    return $t !== '' ? $t : null;
}

/** Client Key dari .env (MIDTRANS_CLIENT_KEY) atau CMS. null bila belum diatur. */
function midtrans_client_key()
{
    $t = trim((string) (env_val('MIDTRANS_CLIENT_KEY', $GLOBALS['PENGATURAN']['midtrans_client_key'] ?? '')));
    return $t !== '' ? $t : null;
}

/** Apakah payment gateway sudah dikonfigurasi? (API key lengkap) */
function midtrans_aktif()
{
    return midtrans_server_key() !== null && midtrans_client_key() !== null;
}

/**
 * Apakah gateway SIAP dipakai pengguna? = terkonfigurasi DAN toggle CMS aktif.
 * Pakai fungsi ini (bukan midtrans_aktif) untuk keputusan tampilan/akses:
 * tombol Bayar Online, muat Snap.js, dan menerima snap token.
 */
function gateway_tersedia()
{
    return midtrans_aktif() && !empty($GLOBALS['PENGATURAN']['bayar_gateway_aktif']);
}

/** Mode saat ini: 'sandbox' | 'production'. */
function midtrans_mode()
{
    return midtrans_mode_sandbox() ? 'sandbox' : 'production';
}

/**
 * Buat order_id unik untuk transaksi pembayaran.
 * Format: SPMB-<jenis>-<pendaftar>-<timestamp>-<rand>
 */
function midtrans_order_id($pendaftar_id, $jenis)
{
    $jenisKode = strtoupper(substr($jenis, 0, 4));
    $pendek    = preg_replace('/[^a-z0-9]/i', '', (string) $pendaftar_id);
    return sprintf('SPMB-%s-%s-%s-%s', $jenisKode, $pendek, date('YmdHis'), bin2hex(random_bytes(3)));
}

/**
 * Buat Snap Token via API Midtrans.
 *
 * @param array $info = [
 *   'order_id'    => string (unik),
 *   'gross_amount'=> float,
 *   'nama_siswa'  => string,
 *   'no_pendaftaran' => string,
 *   'jenis'       => 'FORMULIR'|'UANG_MASUK',
 *   'email'       => string|null,
 *   'phone'       => string|null,
 * ]
 * @return array{ok:bool, snap_token?:string, redirect_url?:string, order_id?:string, pesan?:string}
 */
function midtrans_buat_transaksi(array $info)
{
    $serverKey = midtrans_server_key();
    if ($serverKey === null) {
        return ['ok' => false, 'pesan' => 'Payment gateway belum dikonfigurasi. Isi API keys di Pengaturan Sistem.'];
    }
    $orderId = trim((string) ($info['order_id'] ?? ''));
    $jumlah  = (float) ($info['gross_amount'] ?? 0);
    if ($orderId === '' || $jumlah <= 0) {
        return ['ok' => false, 'pesan' => 'Data transaksi tidak valid (order_id / nominal).'];
    }

    $payload = [
        'transaction_details' => [
            'order_id'     => $orderId,
            'gross_amount' => (int) round($jumlah),
        ],
        'item_details' => [[
            'id'       => $info['jenis'] ?? 'FORMULIR',
            'price'    => (int) round($jumlah),
            'quantity' => 1,
            'name'     => ($info['jenis'] === 'UANG_MASUK'
                ? label_biaya_pendaftaran() : 'Biaya Formulir') . ' SPMB',
        ]],
        'customer_details' => [
            'first_name' => (string) ($info['nama_siswa'] ?? '-'),
            'email'      => (string) ($info['email'] ?? 'siswa@example.com'),
            'phone'      => (string) ($info['phone'] ?? ''),
        ],
    ];

    $ch = curl_init(midtrans_api_base() . '/snap/v1/transactions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($serverKey . ':'),
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res   = curl_exec($ch);
    $kode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        return ['ok' => false, 'pesan' => 'Gagal terhubung ke Midtrans: ' . $err];
    }
    $data = json_decode((string) $res, true);
    if ($kode >= 200 && $kode < 300 && !empty($data['token'])) {
        return [
            'ok'           => true,
            'snap_token'   => (string) $data['token'],
            'redirect_url' => (string) ($data['redirect_url'] ?? ''),
            'order_id'     => $orderId,
        ];
    }
    $pesanApi = $data['error_messages'][0] ?? $data['error_message'] ?? ('HTTP ' . $kode);
    return ['ok' => false, 'pesan' => 'Midtrans menolak: ' . $pesanApi];
}

/**
 * Cek status transaksi Midtrans (GET /v2/{order_id}/status).
 * @return array{ok:bool, status?:string, pesan?:string}
 */
function midtrans_cek_status($order_id)
{
    $serverKey = midtrans_server_key();
    if ($serverKey === null) {
        return ['ok' => false, 'pesan' => 'Payment gateway belum dikonfigurasi.'];
    }
    $orderId = trim((string) $order_id);
    if ($orderId === '') {
        return ['ok' => false, 'pesan' => 'Order ID kosong.'];
    }

    $ch = curl_init(midtrans_api_base() . '/v2/' . rawurlencode($orderId) . '/status');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($serverKey . ':'),
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $kode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        return ['ok' => false, 'pesan' => 'Gagal terhubung ke Midtrans: ' . $err];
    }
    $data = json_decode((string) $res, true);
    if ($kode >= 200 && $kode < 300 && !empty($data['transaction_status'])) {
        return ['ok' => true, 'status' => (string) $data['transaction_status']];
    }
    return ['ok' => false, 'pesan' => 'Transaksi tidak ditemukan di Midtrans (HTTP ' . $kode . ').'];
}

/**
 * Verifikasi signature notifikasi webhook Midtrans (HMAC SHA512).
 * @return bool
 */
function midtrans_verifikasi_notifikasi(array $notif, $serverKey)
{
    if ($serverKey === null || $serverKey === '') {
        return false;
    }
    $orderId  = (string) ($notif['order_id'] ?? '');
    $statusCode = (string) ($notif['status_code'] ?? '');
    $gross    = (string) ($notif['gross_amount'] ?? '');
    $sigKey   = (string) ($notif['signature_key'] ?? '');
    if ($orderId === '' || $statusCode === '' || $gross === '' || $sigKey === '') {
        return false;
    }
    $hitung = hash_hmac('sha512', $orderId . $statusCode . $gross . $serverKey, $serverKey);
    return hash_equals($hitung, $sigKey);
}

/**
 * Map status Midtrans → status enum pembayaran aplikasi.
 * settlement/capture → VERIFIED; deny/expired/cancel → REJECTED; pending → PENDING.
 */
function midtrans_map_status($midtrans_status)
{
    switch ((string) $midtrans_status) {
        case 'settlement':
        case 'capture':
            return 'VERIFIED';
        case 'deny':
        case 'expired':
        case 'cancel':
            return 'REJECTED';
        default:
            return 'PENDING';
    }
}
