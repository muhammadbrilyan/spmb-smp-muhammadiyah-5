-- ==================================================================
-- 2026-11-06 — peserta_ujian: satu calon boleh ikut lebih dari satu
--                kegiatan (unique per pendaftar -> per pendaftar+jadwal)
--
-- Kenapa: `uq_peserta_pendaftar` memaksa SATU baris peserta untuk satu
-- calon seumur proses SPMB. Padahal kegiatan yang dijadwalkan berbeda
-- jenisnya (Test Baca & Tulis, Tes Wawancara, Test Kesehatan) dan calon
-- harus mengikuti semuanya. Akibatnya "Atur Peserta Otomatis" pada kartu
-- kedua selalu kosong dan calon yang absen pada satu kegiatan tidak bisa
-- dijaring ke jadwal susulan.
--
-- Pengganti: `uq_peserta_jadwal (pendaftar_id, jadwal_id)` — tetap mencegah
-- duplikat pada SATU kartu, tapi membolehkan penempatan di kartu lain.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261106_peserta_ujian_multi_jadwal.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): information_schema +
-- PREPARE/EXECUTE. Data lama tidak diubah — relasi lama tetap sah.
-- ==================================================================

USE db_spmb_mualimku;

-- 1) Pasang kunci baru lebih dulu supaya tidak ada jendela tanpa proteksi.
SET @s = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peserta_ujian'
               AND INDEX_NAME = 'uq_peserta_jadwal') = 0,
            'ALTER TABLE `peserta_ujian` ADD UNIQUE KEY `uq_peserta_jadwal` (`pendaftar_id`, `jadwal_id`)',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- 2) Lepas kunci lama (kalau masih ada).
SET @s = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peserta_ujian'
               AND INDEX_NAME = 'uq_peserta_pendaftar') = 1,
            'ALTER TABLE `peserta_ujian` DROP INDEX `uq_peserta_pendaftar`',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
