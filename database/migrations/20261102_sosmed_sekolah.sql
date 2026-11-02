-- ==================================================================
-- 2026-11-02 — sosmed & website sekolah pada pengaturan_sekolah
--
-- Operator cukup MENEMPELKAN tautan di Pengaturan Sistem; ikon media
-- sosial di footer halaman publik muncul bila kolomnya terisi.
--
--   sosmed_instagram / sosmed_tiktok / sosmed_youtube : URL profil
--   website_sekolah                                   : URL profil/website resmi
--
-- Semua kolom nullable. Kosong (NULL atau '') = ikon tidak dirender,
-- jadi footer tidak lagi menampilkan tautan mati "#".
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261102_sosmed_sekolah.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'sosmed_instagram') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN sosmed_instagram varchar(255) DEFAULT NULL AFTER email_sekolah',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'sosmed_tiktok') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN sosmed_tiktok varchar(255) DEFAULT NULL AFTER sosmed_instagram',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'sosmed_youtube') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN sosmed_youtube varchar(255) DEFAULT NULL AFTER sosmed_tiktok',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'website_sekolah') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN website_sekolah varchar(255) DEFAULT NULL AFTER sosmed_youtube',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
