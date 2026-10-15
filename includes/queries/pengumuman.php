<?php
/**
 * Modul queries: pengumuman hasil seleksi — di-include dari includes/queries.php.
 * Fase 8: jadwal pengumuman, finalisasi tahap DIUMUMKAN, kirim WA batch.
 */

/**
 * Status pengumuman hasil seleksi — READ-ONLY (Fase 10).
 * - diumumkan = true  → hasil_diumumkan_pada sudah terisi & sudah lewat.
 * - dijadwalkan_pada  → waktu terjadwal di masa depan (belum aktif).
 * - finalized         → waktu pengumuman sudah lewat (waktunya finalisasi).
 *
 * FUNGSI INI TIDAK MENULIS APA PUN (tidak ada side-effect UPDATE) —
 * pemanggilan dari halaman publik (cek-status, portal siswa) murni membaca.
 * Finalisasi tahap (NILAI_TERINPUT → DIUMUMKAN) dijalankan khusus jalur
 * operator: app/operator/pengumuman.php dan pengumuman_umumkan_sekarang().
 *
 * @return array{diumumkan:bool,dijadwalkan_pada:?string,finalized:bool}
 */
function pengumuman_status()
{
    if (!db_aktif()) {
        return ['diumumkan' => false, 'dijadwalkan_pada' => null, 'finalized' => false];
    }
    /* Semua perbandingan waktu memakai clock MySQL (NOW()) agar konsisten
     * antara penyimpanan dan pembacaan — jangan campur time()/strtotime(). */
    $baris = q_all('SELECT hasil_diumumkan_pada, (hasil_diumumkan_pada <= NOW()) AS lewat
                      FROM pengaturan_sekolah WHERE id = 1 LIMIT 1');
    $waktu = $baris ? ($baris[0]['hasil_diumumkan_pada'] ?? null) : null;
    $lewat = $baris ? ((int) ($baris[0]['lewat'] ?? 0) === 1) : false;
    if (!$waktu) {
        return ['diumumkan' => false, 'dijadwalkan_pada' => null, 'finalized' => false];
    }
    if (!$lewat) {
        return ['diumumkan' => false, 'dijadwalkan_pada' => $waktu, 'finalized' => false];
    }
    return ['diumumkan' => true, 'dijadwalkan_pada' => $waktu, 'finalized' => true];
}

/**
 * Tandai semua pendaftar ber-status_seleksi LULUS/TIDAK_LULUS di tahap NILAI_TERINPUT → DIUMUMKAN.
 * Idempoten; hanya jalan bila hasil_diumumkan_pada sudah terisi & ≤ now.
 * @return int jumlah baris diubah
 */
function pengumuman_finalisasi_tahap()
{
    if (!db_aktif()) {
        return 0;
    }
    $d = q_col(
        "SELECT hasil_diumumkan_pada FROM pengaturan_sekolah WHERE id = 1 AND hasil_diumumkan_pada IS NOT NULL AND hasil_diumumkan_pada <= NOW() LIMIT 1"
    );
    if (!$d) {
        return 0;
    }
    q_exec(
        "UPDATE pendaftar
            SET tahap = 'DIUMUMKAN'
          WHERE tahap = 'NILAI_TERINPUT'
            AND status_seleksi IN ('LULUS','TIDAK_LULUS')"
    );
    return 0; /* jumlah baris tidak kritis (idempoten) */
}

/**
 * Umumkan hasil sekarang juga.
 * @return array{ok:bool,pesan:string}
 */
function pengumuman_umumkan_sekarang()
{
    if (!db_aktif()) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    q_exec('UPDATE pengaturan_sekolah SET hasil_diumumkan_pada = NOW() WHERE id = 1');
    pengumuman_finalisasi_tahap();
    return ['ok' => true, 'pesan' => 'Hasil seleksi resmi diumumkan. Tahap pendaftar diperbarui ke DIUMUMKAN.'];
}

/**
 * Jadwalkan pengumuman pada waktu tertentu.
 * @param string $datetime format Y-m-d\TH:i (input datetime-local) atau Y-m-d H:i:s
 * @return array{ok:bool,pesan:string}
 */
function pengumuman_jadwalkan($datetime)
{
    if (!db_aktif()) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    $dt = str_replace('T', ' ', trim((string) $datetime));
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $dt) !== 1) {
        return ['ok' => false, 'pesan' => 'Format waktu tidak valid.'];
    }
    if (strlen($dt) === 16) {
        $dt .= ':00';
    }
    /* Validasi "masa depan" dengan clock MySQL agar konsisten. */
    $depan = (int) q_col('SELECT (? > NOW())', [$dt]);
    if ($depan !== 1) {
        return ['ok' => false, 'pesan' => 'Waktu pengumuman harus di masa depan. Gunakan "Umumkan Sekarang" bila ingin langsung.'];
    }
    q_exec('UPDATE pengaturan_sekolah SET hasil_diumumkan_pada = ? WHERE id = 1', [$dt]);
    return ['ok' => true, 'pesan' => 'Pengumuman dijadwalkan pada ' . $dt . '.'];
}

/**
 * Batalkan jadwal pengumuman (set NULL). Hanya bila belum lewat.
 * @return array{ok:bool,pesan:string}
 */
function pengumuman_batalkan_jadwal()
{
    if (!db_aktif()) {
        return ['ok' => false, 'pesan' => 'Database tidak aktif.'];
    }
    $n = q_col("SELECT COUNT(*) FROM pengaturan_sekolah WHERE id = 1 AND hasil_diumumkan_pada > NOW()");
    if ((int) $n === 0) {
        return ['ok' => false, 'pesan' => 'Tidak ada jadwal aktif untuk dibatalkan (pengumuman mungkin sudah dijalankan).'];
    }
    q_exec('UPDATE pengaturan_sekolah SET hasil_diumumkan_pada = NULL WHERE id = 1');
    return ['ok' => true, 'pesan' => 'Jadwal pengumuman dibatalkan.'];
}

/**
 * Pesan WA pengumuman untuk satu pendaftar.
 * @return string
 */
function pengumuman_pesan_wa($pendaftarId)
{
    $p = q_all(
        "SELECT p.no_pendaftaran, p.nama_siswa, p.status_seleksi, o.nama_orang_tua, o.no_whatsapp
           FROM pendaftar p
           JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.id = ? LIMIT 1",
        [$pendaftarId]
    );
    $baris = $p ? ($p[0] ?? null) : null;
    if (!$baris) {
        return '';
    }
    $nama = $baris['nama_siswa'] ?: $baris['no_pendaftaran'];
    $sekolah = q_col('SELECT nama_sekolah FROM pengaturan_sekolah WHERE id = 1') ?: 'Sekolah';
    if ($baris['status_seleksi'] === 'LULUS') {
        $pen = q_all(
            'SELECT k.kode, k.nama_kelas FROM penempatan_kelas pk JOIN kelas k ON k.id = pk.kelas_id WHERE pk.pendaftar_id = ? LIMIT 1',
            [$pendaftarId]
        );
        $kelasTxt = ($pen && $pen[0]) ? (' Kelas ' . $pen[0]['kode'] . ' — ' . $pen[0]['nama_kelas'] . '.') : '';
        return "Assalamualaikum.\n\n"
            . "Alhamdulillah, {$nama} ({$baris['no_pendaftaran']}) **LULUS** seleksi SPMB {$sekolah}."
            . $kelasTxt . "\n\n"
            . "Silakan lanjutkan proses daftar ulang sesuai jadwal panitia. Info: " . url_abs('app/spmb/cek-status.php') . "\n\n"
            . "Panitia SPMB";
    }
    return "Assalamualaikum.\n\n"
        . "Bersama ini kami sampaikan hasil seleksi SPMB {$sekolah} atas nama {$nama} ({$baris['no_pendaftaran']}): "
        . "*TIDAK LULUS*.\n\n"
        . "Terima kasih atas partisipasi dan kepercayaan Bapak/Ibu. Semoga sukses selalu.\n\n"
        . "Panitia SPMB";
}

/**
 * Kirim WA pengumuman batch (maks $limit per request) via AJAX.
 *
 * @param int    $offset offset target (mode 'kirim')
 * @param string $mode   'kirim' (semua target) | 'ulang_gagal' (hanya yang wa_log PENGUMUMAN GAGAL)
 * @return array{ok:bool,terkirim:int,gagal:int,sisa:int,total:int,selesai:bool,pesan:string}
 */
function pengumuman_wa_batch($offset = 0, $mode = 'kirim')
{
    $limit = 10;
    $offset = max(0, (int) $offset);
    $mode = ($mode === 'ulang_gagal') ? 'ulang_gagal' : 'kirim';

    if (!db_aktif()) {
        return ['ok' => false, 'terkirim' => 0, 'gagal' => 0, 'sisa' => 0, 'total' => 0, 'selesai' => true, 'pesan' => 'Database tidak aktif.'];
    }

    $where = "p.status_seleksi IN ('LULUS','TIDAK_LULUS')";
    if ($mode === 'ulang_gagal') {
        $where .= " AND EXISTS (SELECT 1 FROM wa_log wl2 WHERE wl2.tipe = 'PENGUMUMAN' AND wl2.status = 'GAGAL' AND wl2.tujuan = o.no_whatsapp) ";
    }

    $total = (int) (q_col(
        "SELECT COUNT(*) FROM pendaftar p JOIN orang_tua o ON o.pendaftar_id = p.id WHERE {$where}"
    ) ?: 0);

    $baris = q_all(
        "SELECT p.id FROM pendaftar p JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE {$where}
          ORDER BY p.no_pendaftaran ASC
          LIMIT {$limit} OFFSET {$offset}",
        []
    ) ?: [];

    $terkirim = 0;
    $gagal = 0;
    foreach ($baris as $b) {
        $pesan = pengumuman_pesan_wa($b['id']);
        $p = q_all('SELECT o.no_whatsapp FROM pendaftar p JOIN orang_tua o ON o.pendaftar_id = p.id WHERE p.id = ? LIMIT 1', [$b['id']]);
        $tujuan = $p ? ($p[0]['no_whatsapp'] ?? '') : '';
        if ($pesan === '' || $tujuan === '') {
            $gagal++;
            continue;
        }
        $r = wa_kirim($tujuan, $pesan);
        wa_log_catat($tujuan, 'PENGUMUMAN', $r);
        if (!empty($r['ok'])) {
            $terkirim++;
        } else {
            $gagal++;
        }
    }

    $sisa = max(0, $total - ($offset + count($baris)));
    $selesai = $sisa === 0;
    return [
        'ok' => true,
        'terkirim' => $terkirim,
        'gagal' => $gagal,
        'sisa' => $sisa,
        'total' => $total,
        'selesai' => $selesai,
        'pesan' => $selesai ? 'Semua WA pengumuman selesai diproses.' : 'Batch diproses; masih ada sisa.',
    ];
}

/**
 * Jumlah pendaftar ber-status_seleksi LULUS/TIDAK_LULUS yang punya nomor WA.
 * @return int
 */
function pengumuman_target_wa_jumlah()
{
    return (int) (q_col(
        "SELECT COUNT(*) FROM pendaftar p JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.status_seleksi IN ('LULUS','TIDAK_LULUS') AND o.no_whatsapp <> ''"
    ) ?: 0);
}
