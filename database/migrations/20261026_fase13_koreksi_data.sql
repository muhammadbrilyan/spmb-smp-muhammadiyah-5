-- ---------------------------------------------------------------------
-- FASE 13 - PENGAJUAN KOREKSI DATA SISWA
--
-- Dipakai portal siswa ketika data sudah diverifikasi (terkunci).
-- Siswa mengajukan koreksi, operator menindaklanjuti dari halaman
-- Verifikasi Data Calon Siswa. Tabel ini TIDAK mengubah data induk
-- secara otomatis — perubahan tetap dilakukan petugas yang berwenang.
--
-- Migrasi ini idempoten dan portabel (MySQL 5.7+/MariaDB 10.x):
-- kolom/index dicek lewat information_schema, bukan ADD ... IF NOT EXISTS.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengajuan_koreksi (
  id VARCHAR(36) PRIMARY KEY,
  pendaftar_id VARCHAR(36) NOT NULL,
  user_id VARCHAR(36) NULL,
  bidang ENUM('IDENTITAS','ORANG_TUA','ALAMAT_KONTAK','DOKUMEN','AKADEMIK','LAINNYA')
      NOT NULL DEFAULT 'LAINNYA',
  pesan TEXT NOT NULL,
  status ENUM('MENUNGGU','DIPROSES','DISETUJUI','DITOLAK')
      NOT NULL DEFAULT 'MENUNGGU',
  catatan_operator TEXT NULL,
  reviewed_by VARCHAR(36) NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_koreksi_pendaftar (pendaftar_id),
  KEY idx_koreksi_status (status),
  CONSTRAINT fk_koreksi_pendaftar FOREIGN KEY (pendaftar_id)
      REFERENCES pendaftar(id) ON DELETE CASCADE,
  CONSTRAINT fk_koreksi_user FOREIGN KEY (user_id)
      REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_koreksi_reviewer FOREIGN KEY (reviewed_by)
      REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Kolom verifikasi DATA CALON SISWA (terpisah dari verifikasi berkas).
-- status_verifikasi yang sudah ada dipakai ulang:
--   DRAFT / MENUNGGU_VERIFIKASI / TERVERIFIKASI / DITOLAK
-- ---------------------------------------------------------------------
SET @ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND COLUMN_NAME = 'catatan_verifikasi_data'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD COLUMN catatan_verifikasi_data TEXT NULL AFTER tahap',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND COLUMN_NAME = 'data_verified_at'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD COLUMN data_verified_at DATETIME NULL AFTER catatan_verifikasi_data',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND COLUMN_NAME = 'data_verified_by'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD COLUMN data_verified_by VARCHAR(36) NULL AFTER data_verified_at',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND INDEX_NAME = 'idx_pendaftar_verifikasi_data'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD KEY idx_pendaftar_verifikasi_data (status_verifikasi, updated_at)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND CONSTRAINT_NAME = 'fk_pendaftar_data_verifier'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD CONSTRAINT fk_pendaftar_data_verifier FOREIGN KEY (data_verified_by) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
