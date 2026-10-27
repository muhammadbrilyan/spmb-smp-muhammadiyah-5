<?php
/**
 * Modul queries: siswa — di-include dari includes/queries.php.
 */

function profil_siswa()
{
    $uid = current_user()['id'] ?? null;
    if ($uid === null) {
        return null;
    }

    $baris = q_all(
        'SELECT p.*, o.nama_orang_tua, o.no_whatsapp, o.pekerjaan, o.penghasilan_bulanan,
                o.alamat AS alamat_orang_tua, k.kode AS kelas_kode, k.nama_kelas
           FROM pendaftar p
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
      LEFT JOIN penempatan_kelas pk ON pk.pendaftar_id = p.id
      LEFT JOIN kelas k ON k.id = pk.kelas_id
          WHERE p.user_id = ?
       LIMIT 1',
        [$uid]
    );

    if ($baris === null || !$baris) {
        return null;
    }
    return $baris[0];
}

/**
 * Simpan identitas, orang tua/wali, dan alamat dalam SATU formulir.
 * Semua perubahan dibungkus transaksi agar tidak tersimpan sebagian.
 *
 * @param array<string,mixed> $d
 * @return array{ok:bool,pesan:string}
 */
function siswa_simpan_data_diri($pendaftar_id, array $d)
{
    $db = db();
    if ($db === null) {
        return ['ok' => false, 'pesan' => 'Koneksi database tidak tersedia.'];
    }

    $nisn        = preg_replace('/\D+/', '', (string) ($d['nisn'] ?? ''));
    $jk          = ($d['jenis_kelamin'] ?? '') === 'P' ? 'P' : 'L';
    $tempat      = trim((string) ($d['tempat_lahir'] ?? ''));
    $tglLahir    = trim((string) ($d['tanggal_lahir'] ?? ''));
    $alamat      = trim((string) ($d['alamat'] ?? ''));
    $luarDaerah  = !empty($d['is_luar_daerah']);
    $namaOrtu    = trim((string) ($d['nama_orang_tua'] ?? ''));
    $wa          = trim((string) ($d['no_whatsapp'] ?? ''));
    $alamatOrtu  = trim((string) ($d['alamat_orang_tua'] ?? ''));
    $pekerjaan   = trim((string) ($d['pekerjaan'] ?? ''));
    $penghasilan = trim((string) ($d['penghasilan_bulanan'] ?? ''));

    $galat = [];
    if ($nisn !== '' && strlen($nisn) !== 10) {
        $galat[] = 'NISN harus 10 digit angka (atau dikosongkan).';
    }
    if ($tempat === '') {
        $galat[] = 'Tempat lahir wajib diisi.';
    }
    if ($tglLahir === '') {
        $galat[] = 'Tanggal lahir wajib diisi.';
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tglLahir) !== 1) {
        $galat[] = 'Format tanggal lahir tidak valid.';
    } elseif ($tglLahir > date('Y-m-d')) {
        $galat[] = 'Tanggal lahir tidak boleh di masa depan.';
    }
    if ($alamat === '') {
        $galat[] = 'Alamat siswa wajib diisi.';
    }
    if ($namaOrtu === '') {
        $galat[] = 'Nama orang tua / wali wajib diisi.';
    }
    if ($wa === '' || preg_match('/^[0-9+\-\s()]{8,20}$/', $wa) !== 1) {
        $galat[] = 'Nomor WhatsApp tidak valid (8–20 digit).';
    }
    if ($alamatOrtu === '') {
        $galat[] = 'Alamat orang tua / wali wajib diisi.';
    }
    if ($pekerjaan === '') {
        $galat[] = 'Pekerjaan orang tua / wali wajib diisi.';
    }
    if ($galat) {
        return ['ok' => false, 'pesan' => implode(' ', $galat)];
    }

    try {
        $db->beginTransaction();

        /* Cek status sekaligus kunci baris agar tidak ada perubahan setelah
         * operator memverifikasi di tengah proses simpan. */
        $cek = $db->prepare('SELECT status_verifikasi FROM pendaftar WHERE id = ? FOR UPDATE');
        $cek->execute([$pendaftar_id]);
        $status = $cek->fetchColumn();
        if ($status === false) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Data pendaftar tidak ditemukan.'];
        }
        if (!in_array((string) $status, ['DRAFT', 'MENUNGGU_VERIFIKASI', 'DITOLAK'], true)) {
            $db->rollBack();
            return ['ok' => false, 'pesan' => 'Data sudah terkunci. Gunakan pengajuan koreksi bila perlu perubahan.'];
        }

        $upd = $db->prepare(
            'UPDATE pendaftar
                SET nisn = ?, jenis_kelamin = ?, tempat_lahir = ?, tanggal_lahir = ?,
                    alamat = ?, is_luar_daerah = ?
              WHERE id = ?'
        );
        $upd->execute([
            $nisn !== '' ? $nisn : null,
            $jk,
            $tempat,
            $tglLahir,
            $alamat,
            $luarDaerah ? 1 : 0,
            $pendaftar_id,
        ]);

        $cari = $db->prepare('SELECT COUNT(*) FROM orang_tua WHERE pendaftar_id = ?');
        $cari->execute([$pendaftar_id]);
        $ada = (int) $cari->fetchColumn();

        if ($ada > 0) {
            $updOrtu = $db->prepare(
                'UPDATE orang_tua
                    SET nama_orang_tua = ?, no_whatsapp = ?, alamat = ?, pekerjaan = ?, penghasilan_bulanan = ?
                  WHERE pendaftar_id = ?'
            );
            $updOrtu->execute([
                $namaOrtu,
                $wa,
                $alamatOrtu,
                $pekerjaan,
                $penghasilan !== '' ? $penghasilan : null,
                $pendaftar_id,
            ]);
        } else {
            $insOrtu = $db->prepare(
                'INSERT INTO orang_tua (id, pendaftar_id, nama_orang_tua, no_whatsapp, alamat, pekerjaan, penghasilan_bulanan)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insOrtu->execute([
                'ort-' . bin2hex(random_bytes(4)),
                $pendaftar_id,
                $namaOrtu,
                $wa,
                $alamatOrtu,
                $pekerjaan,
                $penghasilan !== '' ? $penghasilan : null,
            ]);
        }

        $db->commit();
        return ['ok' => true, 'pesan' => 'Data siswa berhasil disimpan.'];
    } catch (Throwable $e) {
        if (method_exists($db, 'rollBack')) {
            try {
                $db->rollBack();
            } catch (Throwable $x) {
                /* abaikan */
            }
        }
        error_log('siswa_simpan_data_diri gagal: ' . $e->getMessage());
        return ['ok' => false, 'pesan' => 'Gagal menyimpan data. Coba lagi.'];
    }
}

/**
 * Ajukan data siswa untuk diverifikasi operator.
 * Data harus dapat diedit (DRAFT/MENUNGGU_VERIFIKASI) dan butir wajib lengkap.
 *
 * @return array{ok:bool,pesan:string,kurang?:array<int,string>}
 */
function siswa_ajukan_verifikasi_data($pendaftar_id)
{
    $baris = q_all(
        'SELECT p.id, p.no_pendaftaran, p.nama_siswa, p.nisn, p.jenis_kelamin,
                p.tempat_lahir, p.tanggal_lahir, p.alamat, p.asal_sekolah,
                p.status_verifikasi, o.nama_orang_tua, o.no_whatsapp,
                o.alamat AS alamat_orang_tua, o.pekerjaan
           FROM pendaftar p
      LEFT JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.id = ? LIMIT 1',
        [$pendaftar_id]
    );
    $row = ($baris !== null && $baris) ? $baris[0] : null;
    if (!$row) {
        return ['ok' => false, 'pesan' => 'Data pendaftar tidak ditemukan.'];
    }
    if (!in_array($row['status_verifikasi'], ['DRAFT', 'MENUNGGU_VERIFIKASI', 'DITOLAK'], true)) {
        return ['ok' => false, 'pesan' => 'Data sudah diverifikasi / terkunci. Gunakan pengajuan koreksi bila perlu perubahan.'];
    }

    $wajib = [
        'Jenis kelamin'                => !empty($row['jenis_kelamin']),
        'Tempat lahir'                 => trim((string) $row['tempat_lahir']) !== '',
        'Tanggal lahir'                => !empty($row['tanggal_lahir']),
        'Alamat siswa'                 => trim((string) $row['alamat']) !== '',
        'Asal sekolah'                 => trim((string) $row['asal_sekolah']) !== '',
        'Nama orang tua / wali'        => trim((string) $row['nama_orang_tua']) !== '',
        'Nomor WhatsApp orang tua'     => trim((string) $row['no_whatsapp']) !== '',
        'Alamat orang tua / wali'      => trim((string) $row['alamat_orang_tua']) !== '',
        'Pekerjaan orang tua / wali'   => trim((string) $row['pekerjaan']) !== '',
    ];
    $kurang = [];
    foreach ($wajib as $label => $ok) {
        if (!$ok) {
            $kurang[] = $label;
        }
    }
    if ($kurang) {
        return [
            'ok' => false,
            'pesan' => 'Lengkapi data berikut sebelum mengajukan verifikasi: ' . implode(', ', $kurang) . '.',
            'kurang' => $kurang,
        ];
    }

    $ok = q_exec(
        "UPDATE pendaftar
            SET status_verifikasi = 'MENUNGGU_VERIFIKASI', catatan_verifikasi_data = NULL,
                data_verified_at = NULL, data_verified_by = NULL
          WHERE id = ? AND status_verifikasi IN ('DRAFT','MENUNGGU_VERIFIKASI','DITOLAK')",
        [$pendaftar_id]
    );
    if (!$ok) {
        return ['ok' => false, 'pesan' => 'Gagal mengirim data untuk verifikasi. Coba lagi.'];
    }

    notif_operator(
        'VERIFIKASI_DATA',
        $pendaftar_id,
        "Data calon siswa diajukan untuk verifikasi.\nNama : {$row['nama_siswa']}\nNo. Pendaftaran : {$row['no_pendaftaran']}"
    );

    return ['ok' => true, 'pesan' => 'Data berhasil diajukan. Operator akan memeriksa dan memverifikasi data Anda.'];
}

/* Catatan: dokumen_siswa() & unggah dokumen kini ada di queries/dokumen.php
 * (Fase 5 — daftar jenis dinamis dari tabel jenis_dokumen). */

/** Hapus berkas fisik bila path ada di folder upload/storage aplikasi. */
