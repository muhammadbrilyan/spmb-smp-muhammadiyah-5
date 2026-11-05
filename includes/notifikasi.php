<?php
/**
 * NOTIFIKASI PANITIA.
 *
 * Alur:
 *   1. Simpan pesan ke tabel `notifikasi` (lencana + halaman Notifikasi).
 *   2. Kirim WhatsApp via Fonnte HANYA untuk pemicu uang masuk, ke nomor
 *      bendahara — lihat `notif_nomor_bendahara()` — dan hanya bila toggle
 *      `notif_wa_aktif` aktif. Pemicu lain (berkas dikirim, data diajukan
 *      verifikasi) berhenti sebagai notifikasi in-app: akun Fonnte gratis
 *      hanya mendaftarkan satu nomor WhatsApp, jadi kanal WA dipakai untuk
 *      transaksi saja. Kolom `notif_wa_operator` masih ada di skema tetapi
 *      tidak dibaca lagi sejak 2026-11-03.
 *   3. Hasil kirim (sukses/gagal) dicatat ke `wa_log`.
 *
 * PENTING: kegagalan apa pun (DB / Fonnte / jaringan) TIDAK BOLEH
 * menggagalkan proses utama pemanggil — semua dibungkus try/catch dan
 * tidak pernah melempar exception.
 *
 * Tipe pemicu:
 *   - BUKTI_BAYAR_MASUK         → unggah bukti transfer/QRIS  → in-app + WA
 *   - PEMBAYARAN_GATEWAY_LUNAS  → callback Midtrans VERIFIED/LUNAS → in-app + WA
 *   - PEMBAYARAN_GATEWAY_MASUK  → callback Midtrans angsuran terverifikasi → in-app + WA
 *   - BERKAS_DISUBMIT           → siswa submit berkas  → in-app saja
 *   - VERIFIKASI_DATA           → siswa mengajukan data  → in-app saja
 *   - (Fase lanjutan: PENOLAKAN_BAYAR, BERKAS_REVISI, JADWAL_DITETAPKAN, dll.)
 */

/**
 * Nomor WA bendahara dari Pengaturan Sistem.
 *
 * Sumber utama: kolom `notif_wa_bendahara` (beberapa nomor dipisah koma).
 * Bila belum diisi, jatuh ke `kontak_wa_panitia` — nomor admin yang sudah
 * ada sejak dulu — supaya notifikasi bukti bayar tidak hilang diam-diam.
 *
 * @return string daftar nomor mentah (boleh kosong)
 */
function notif_nomor_bendahara()
{
    $P = $GLOBALS['PENGATURAN'];
    $nomor = trim((string) ($P['notif_wa_bendahara'] ?? ''));
    return $nomor !== '' ? $nomor : trim((string) ($P['kontak_wa_panitia'] ?? ''));
}

/**
 * Catat kejadian ke panel Notifikasi panitia; kirim WhatsApp hanya bila
 * pemicunya soal uang masuk dan pengiriman WA sedang diaktifkan.
 *
 * @param string $tipe        kode tipe pemicu (lihat daftar di atas)
 * @param string $pendaftarId id pendaftar terkait (boleh null)
 * @param string $pesan       isi pesan singkat (nama, no. pendaftaran, tautan)
 * @return void tidak ada nilai kembali — tidak pernah melempar exception
 */
function notif_operator($tipe, $pendaftarId, $pesan)
{
    $tipe = (string) $tipe;
    $pesan = (string) $pesan;
    if ($tipe === '' || $pesan === '') {
        return;
    }

    /* --- 1. Simpan in-app (best effort) --- */
    try {
        $id = 'ntf-' . bin2hex(random_bytes(4));
        q_exec(
            'INSERT INTO notifikasi (id, tipe, pendaftar_id, pesan) VALUES (?, ?, ?, ?)',
            [$id, $tipe, $pendaftarId !== null ? (string) $pendaftarId : null, $pesan]
        );
    } catch (Throwable $e) {
        /* diamkan — notifikasi tidak boleh merusak proses utama */
    }

    /* --- 2. WhatsApp Fonnte — khusus uang masuk, ke nomor bendahara --- */
    try {
        $transaksi = in_array($tipe, ['BUKTI_BAYAR_MASUK', 'PEMBAYARAN_GATEWAY_MASUK', 'PEMBAYARAN_GATEWAY_LUNAS'], true);
        if (!$transaksi || (int) ($GLOBALS['PENGATURAN']['notif_wa_aktif'] ?? 0) !== 1) {
            return;
        }
        $sudah = [];
        foreach ((preg_split('/[,;]+/', notif_nomor_bendahara()) ?: []) as $no) {
            $no = trim((string) $no);
            if ($no === '') {
                continue;
            }
            $kunci = wa_normalize($no);
            if ($kunci === '' || isset($sudah[$kunci])) {
                continue;
            }
            $sudah[$kunci] = true;
            $hasil = wa_kirim($no, $pesan);
            wa_log_catat($no, $tipe, $hasil);
        }
    } catch (Throwable $e) {
        /* diamkan */
    }
}

/**
 * Catat hasil pengiriman WhatsApp ke tabel `wa_log`.
 *
 * @param string $tujuan nomor tujuan (dinormalisasi Fonnte bila memungkinkan)
 * @param string $tipe   tipe notifikasi
 * @param array{ok:bool, pesan:string, id:string|null} $hasil hasil wa_kirim()
 * @return void
 */
function wa_log_catat($tujuan, $tipe, $hasil)
{
    try {
        $ok = !empty($hasil['ok']);
        q_exec(
            'INSERT INTO wa_log (tujuan, tipe, status, respons) VALUES (?, ?, ?, ?)',
            [
                (string) $tujuan,
                (string) $tipe,
                $ok ? 'TERKIRIM' : 'GAGAL',
                json_encode([
                    'pesan' => (string) ($hasil['pesan'] ?? ''),
                    'id'    => $hasil['id'] ?? null,
                ], JSON_UNESCAPED_UNICODE),
            ]
        );
    } catch (Throwable $e) {
        /* diamkan */
    }
}

/**
 * TITIK SAMBUNGAN EMAIL — BELUM DIIMPLEMENTASIKAN (Fase 3).
 *
 * Notifikasi email sengaja TIDAK dikirim sekarang. Kolom pengaturan
 * `pengaturan_sekolah.notif_email` sudah tersedia untuk menyimpan alamat
 * tujuan, tetapi fungsi ini hanya mengembalikan status "belum tersedia".
 * Isi implementasinya hanya setelah kebutuhan email notifikasi disetujui
 * (lihat daftar "Belum diputuskan" pada prompt fase).
 *
 * @param string $tujuan alamat email tujuan (dari pengaturan notif_email)
 * @param string $subjek subjek email
 * @param string $pesan  isi pesan (HTML/plain — keputusan menyusul)
 * @return array{ok:bool, pesan:string}
 */
function notif_email_kirim($tujuan, $subjek, $pesan)
{
    return [
        'ok'    => false,
        'pesan' => 'Notifikasi email belum diimplementasikan (titik sambungan).',
    ];
}

/**
 * Jumlah notifikasi yang belum dibaca (untuk lencana sidebar).
 * @return int
 */
function notif_belum_dibaca()
{
    try {
        $n = q_col('SELECT COUNT(*) FROM notifikasi WHERE dibaca_at IS NULL');
        return (int) $n;
    } catch (Throwable $e) {
        return 0;
    }
}
