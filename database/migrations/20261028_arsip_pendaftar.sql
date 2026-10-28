-- ---------------------------------------------------------------------
-- ARSIP PENDAFTARAN (soft archive) — 2026-10-28
--
-- Operator boleh MENGARSIPKAN pendaftar (ganda / salah input / mengundurkan
-- diri), bukan menghapus. Baris pendaftar + pembayaran + berkas + seleksi
-- tetap utuh (hapus permanen memakai ON DELETE CASCADE ke 7 tabel dan tidak
-- ada audit-log, jadi tidak disediakan di UI operasional).
--
-- Efek arsip: baris disembunyikan dari seluruh antrean kerja & statistik
-- (filter `arsip_at IS NULL`), akun portal siswa ikut nonaktif sementara
-- (users.is_active = 0), dan dipulihkan lewat aksi "Pulihkan".
--
-- Migrasi ini idempoten dan portabel (MySQL 5.7+/MariaDB 10.x): kolom/index
-- dicek lewat information_schema, bukan ADD ... IF NOT EXISTS.
-- ---------------------------------------------------------------------

SET @ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND COLUMN_NAME = 'arsip_at'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD COLUMN arsip_at DATETIME NULL AFTER data_verified_by',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND COLUMN_NAME = 'arsip_alasan'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD COLUMN arsip_alasan VARCHAR(255) NULL AFTER arsip_at',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND COLUMN_NAME = 'arsip_oleh'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD COLUMN arsip_oleh VARCHAR(36) NULL AFTER arsip_alasan',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND INDEX_NAME = 'idx_pendaftar_arsip'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD KEY idx_pendaftar_arsip (arsip_at)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
     AND CONSTRAINT_NAME = 'fk_pendaftar_arsip_oleh'
);
SET @sql := IF(@ada = 0,
  'ALTER TABLE pendaftar ADD CONSTRAINT fk_pendaftar_arsip_oleh FOREIGN KEY (arsip_oleh) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
