-- ============================================================
-- 20261012_fase2_pembayaran.sql
-- Fase 2: toggle metode pembayaran + QRIS + editor S&K
-- Jalankan sebagai root / lewat phpMyAdmin Import (tanpa DELIMITER):
--   mysql -u root < database/migrations/20261012_fase2_pembayaran.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x).
-- ============================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'bayar_gateway_aktif') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN bayar_gateway_aktif TINYINT(1) NOT NULL DEFAULT 1',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'bayar_transfer_aktif') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN bayar_transfer_aktif TINYINT(1) NOT NULL DEFAULT 1',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'bayar_qris_aktif') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN bayar_qris_aktif TINYINT(1) NOT NULL DEFAULT 0',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'qris_image_url') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN qris_image_url TEXT NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'qris_merchant') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN qris_merchant VARCHAR(100) NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'kirim_akun_otomatis') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN kirim_akun_otomatis TINYINT(1) NOT NULL DEFAULT 0',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
