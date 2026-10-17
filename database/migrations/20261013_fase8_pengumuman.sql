-- ==================================================================
-- FASE 8 — Pengumuman hasil: waktu pengumuman pada pengaturan_sekolah.
-- Jalankan: mysql -u root db_spmb_mualimku < 20261013_fase8_pengumuman.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): information_schema +
-- PREPARE/EXECUTE (Fase 13 butir 4).
-- ==================================================================

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_diumumkan_pada') = 0,
            'ALTER TABLE `pengaturan_sekolah` ADD COLUMN `hasil_diumumkan_pada` DATETIME DEFAULT NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
