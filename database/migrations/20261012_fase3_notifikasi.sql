-- =============================================================================
-- FASE 3 — Notifikasi Operator
-- Jalankan: cmd /c "C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 db_spmb_mualimku < 20261012_fase3_notifikasi.sql"
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): information_schema +
-- PREPARE/EXECUTE (Fase 13 butir 4).
-- =============================================================================

-- Tabel notifikasi in-app (lencana operator + halaman Notifikasi)
CREATE TABLE IF NOT EXISTS `notifikasi` (
  `id` VARCHAR(36) NOT NULL,
  `tipe` VARCHAR(40) NOT NULL,
  `pendaftar_id` VARCHAR(36) NULL DEFAULT NULL,
  `pesan` TEXT NOT NULL,
  `dibaca_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_dibaca` (`dibaca_at`),
  KEY `idx_notif_tipe` (`tipe`),
  KEY `idx_notif_pendaftar` (`pendaftar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log pengiriman WhatsApp (hasil Fonnte, suka/gagal tetap dicatat)
CREATE TABLE IF NOT EXISTS `wa_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tujuan` VARCHAR(30) NOT NULL,
  `tipe` VARCHAR(40) NOT NULL,
  `status` VARCHAR(20) NOT NULL,
  `respons` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_walog_tipe` (`tipe`),
  KEY `idx_walog_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kolom notifikasi operator pada pengaturan_sekolah (1 per 1, idempoten)
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'notif_wa_operator') = 0,
            'ALTER TABLE `pengaturan_sekolah` ADD COLUMN `notif_wa_operator` VARCHAR(255) NULL DEFAULT NULL COMMENT ''Daftar nomor WA operator, dipisah koma'' AFTER `kirim_akun_otomatis`',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'notif_wa_aktif') = 0,
            'ALTER TABLE `pengaturan_sekolah` ADD COLUMN `notif_wa_aktif` TINYINT(1) NOT NULL DEFAULT 0 AFTER `notif_wa_operator`',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'notif_email') = 0,
            'ALTER TABLE `pengaturan_sekolah` ADD COLUMN `notif_email` VARCHAR(255) NULL DEFAULT NULL COMMENT ''TITIK SAMBUNGAN — belum diimplementasikan'' AFTER `notif_wa_aktif`',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
