-- ============================================================
-- Fase 12 E1 — Retensi data & privasi.
-- Menambahkan pengaturan retensi (single-row pengaturan_sekolah)
-- dan tabel retensi_log (jejak eksekusi hapus berkas pribadi).
-- Idempoten: aman dijalankan berulang. Portabel (MySQL 5.7/8 &
-- MariaDB 10.x): information_schema + PREPARE/EXECUTE.
-- ============================================================

-- Pengaturan retensi (default nonaktif / NULL = belum ditetapkan).
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'retensi_aktif') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN retensi_aktif TINYINT(1) NOT NULL DEFAULT 0 AFTER hasil_diumumkan_pada',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'retensi_bulan_tidak_lulus') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN retensi_bulan_tidak_lulus SMALLINT UNSIGNED DEFAULT NULL AFTER retensi_aktif',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'retensi_bulan_batal') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN retensi_bulan_batal SMALLINT UNSIGNED DEFAULT NULL AFTER retensi_bulan_tidak_lulus',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'retensi_bulan_lulus_arsip') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN retensi_bulan_lulus_arsip SMALLINT UNSIGNED DEFAULT NULL AFTER retensi_bulan_batal',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Jejak eksekusi retensi berkas pribadi.
CREATE TABLE IF NOT EXISTS retensi_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pendaftar_id VARCHAR(36) NOT NULL,
  no_pendaftaran VARCHAR(30) DEFAULT NULL,
  alasan VARCHAR(40) NOT NULL,
  jumlah_berkas INT UNSIGNED NOT NULL DEFAULT 0,
  operator_id VARCHAR(36) DEFAULT NULL,
  dibuat_pada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_retensi_pendaftar (pendaftar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
