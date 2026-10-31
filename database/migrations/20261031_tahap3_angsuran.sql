-- ==================================================================
-- MIGRASI TAHAP 3 — TAGIHAN PENDAFTARAN BERTAHAP (ANGSURAN 1 & 2)
--
-- pengaturan_sekolah:
--   angsuran_1_nominal / angsuran_2_nominal  nominal tiap tahap; bila
--       keduanya > 0, jumlahnya WAJIB sama dengan estimasi_uang_masuk.
--   angsuran_1_jatuh_tempo / angsuran_2_jatuh_tempo  tanggal jatuh tempo
--       (opsional) untuk pengingat in-app.
--
-- pembayaran:
--   angsuran_ke 1|2 untuk baris UANG_MASUK; NULL = formulir / pembayaran
--       penuh lama (dihitung FIFO saat rekap).
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261031_tahap3_angsuran.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'angsuran_1_nominal') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN angsuran_1_nominal decimal(12,2) NOT NULL DEFAULT 0.00 AFTER estimasi_uang_masuk',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'angsuran_2_nominal') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN angsuran_2_nominal decimal(12,2) NOT NULL DEFAULT 0.00 AFTER angsuran_1_nominal',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'angsuran_1_jatuh_tempo') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN angsuran_1_jatuh_tempo date DEFAULT NULL AFTER angsuran_2_nominal',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'angsuran_2_jatuh_tempo') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN angsuran_2_jatuh_tempo date DEFAULT NULL AFTER angsuran_1_jatuh_tempo',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran'
               AND COLUMN_NAME = 'angsuran_ke') = 0,
            'ALTER TABLE pembayaran ADD COLUMN angsuran_ke tinyint(3) unsigned DEFAULT NULL AFTER jenis_pembayaran',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran'
               AND INDEX_NAME = 'idx_byr_angsuran') = 0,
            'ALTER TABLE pembayaran ADD INDEX idx_byr_angsuran (pendaftar_id, jenis_pembayaran, angsuran_ke)',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
