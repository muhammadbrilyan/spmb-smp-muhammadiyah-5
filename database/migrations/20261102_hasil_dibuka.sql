-- ==================================================================
-- 2026-11-02 — pendaftar.hasil_dibuka_at
--
-- Menyimpan kapan calon siswa MEMBUKA sendiri hasil seleksinya lewat
-- tombol khusus di halaman Portal Saya → Hasil Seleksi.
--
--   NULL      = hasil belum pernah dibuka siswa. Rincian Biaya & Sisa
--               Pembayaran di portal Keuangan masih terkunci.
--   terisi    = siswa sudah melihat hasilnya → tagihan biaya masuk tampil.
--
-- Timestamp ditulis SEKALI (kondisi WHERE hasil_dibuka_at IS NULL) supaya
-- tanggalnya tidak berubah bila halaman dibuka ulang.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261102_hasil_dibuka.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
               AND COLUMN_NAME = 'hasil_dibuka_at') = 0,
            'ALTER TABLE pendaftar ADD COLUMN hasil_dibuka_at datetime DEFAULT NULL AFTER tahap',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
