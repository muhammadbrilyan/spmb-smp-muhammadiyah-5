-- =============================================================================
-- FASE 4 — Verifikasi Pembayaran dan Kirim Akun
-- Jalankan: cmd /c "C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 db_spmb_mualimku < 20261012_fase4_verifikasi_akun.sql"
-- =============================================================================

-- Nilai enum baru AKUN_TERKIRIM (dipakai saat kredensial dibuat tetapi
-- pengiriman WA belum berhasil — menunggu aksi "Kirim Ulang" operator).
ALTER TABLE `pendaftar`
  MODIFY COLUMN `tahap` ENUM(
    'MENUNGGU_PEMBAYARAN',
    'MENUNGGU_VERIFIKASI_BAYAR',
    'BAYAR_TERVERIFIKASI',
    'AKUN_TERKIRIM',
    'MELENGKAPI_BERKAS',
    'BERKAS_DISUBMIT',
    'BERKAS_REVISI',
    'BERKAS_TERVERIFIKASI',
    'JADWAL_DITETAPKAN',
    'NILAI_TERINPUT',
    'DIUMUMKAN'
  ) NULL DEFAULT NULL;
