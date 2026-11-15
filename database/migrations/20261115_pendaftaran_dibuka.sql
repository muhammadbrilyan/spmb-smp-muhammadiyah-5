-- ==================================================================
-- 2026-11-15 — SAKLAR MANUAL BUKA/TUTUP PENDAFTARAN (CMS)
--
-- pengaturan_sekolah.pendaftaran_dibuka
--   Status sebenarnya pendaftaran publik. Nilai 1 = dibuka, 0 = ditutup.
--   Tanggal buka/tutup di CMS tetap ditampilkan sebagai informasi jadwal,
--   tetapi TIDAK lagi menentukan apakah formulir pendaftaran menerima isian.
--   Operator mengubahnya dari Persiapan Tahun Ajaran — tanpa menyentuh kode
--   atau CLI, sehingga formulir bisa ditutup di antara dua musim SPMB.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261115_pendaftaran_dibuka.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'pendaftaran_dibuka') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN pendaftaran_dibuka tinyint(1) NOT NULL DEFAULT 1 AFTER tanggal_pengumuman',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
