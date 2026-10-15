<?php
/**
 * NOTIFIKASI OPERATOR (Fase 3).
 *
 * Alur:
 *   1. Simpan pesan ke tabel `notifikasi` (lencana + halaman Notifikasi).
 *   2. Kirim WhatsApp via Fonnte ke nomor di pengaturan `notif_wa_operator`
 *      (beberapa nomor dipisah koma) BILA toggle `notif_wa_aktif` aktif.
 *   3. Hasil kirim (sukses/gagal) dicatat ke `wa_log`.
 *
 * PENTING: kegagalan apa pun (DB / Fonnte / jaringan) TIDAK BOLEH
 * menggagalkan proses utama pemanggil — semua dibungkus try/catch dan
 * tidak pernah melempar exception.
 *
 * Tipe pemicu:
 *   - BUKTI_BAYAR_MASUK         → unggah bukti transfer/QRIS (Fase 2)
 *   - PEMBAYARAN_GATEWAY_LUNAS  → callback Midtrans VERIFIED (Fase 2)
 *   - BERKAS_DISUBMIT           → siswa submit berkas (Fase 5)
 *   - (Fase lanjutan: PENOLAKAN_BAYAR, BERKAS_REVISI, JADWAL_DITETAPKAN, dll.)
 */

/**
 * Kirim notifikasi ke operator (in-app + WhatsApp).
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

    /* --- 2. WhatsApp Fonnte (bila toggle aktif) --- */
    try {
        $aktif = (int) ($GLOBALS['PENGATURAN']['notif_wa_aktif'] ?? 0);
        if ($aktif !== 1) {
            return;
        }
        $daftar = (string) ($GLOBALS['PENGATURAN']['notif_wa_operator'] ?? '');
        foreach (preg_split('/[,;]+/', $daftar) as $no) {
            $no = trim((string) $no);
            if ($no === '') {
                continue;
            }
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
