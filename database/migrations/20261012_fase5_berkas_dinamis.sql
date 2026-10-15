-- =============================================================================
-- FASE 5 — Berkas Dinamis, Submit, Verifikasi dan Revisi
-- Jalankan: cmd /c "C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4 db_spmb_mualimku < 20261012_fase5_berkas_dinamis.sql"
-- =============================================================================

-- Daftar jenis berkas dinamis (daftar final menyusul — data sementara
-- disamakan dengan jenis yang selama ini hard-coded).
CREATE TABLE IF NOT EXISTS `jenis_dokumen` (
  `kode` VARCHAR(30) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `wajib` TINYINT(1) NOT NULL DEFAULT 1,
  `format_diizinkan` VARCHAR(100) NOT NULL DEFAULT 'jpg,jpeg,png,pdf',
  `maks_kb` INT NOT NULL DEFAULT 2048,
  `urutan` INT NOT NULL DEFAULT 0,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `jenis_dokumen` (`kode`, `nama`, `wajib`, `format_diizinkan`, `maks_kb`, `urutan`, `aktif`) VALUES
  ('AKTA',                   'Akta Kelahiran',                1, 'jpg,jpeg,png,pdf', 2048, 10, 1),
  ('KARTU_KELUARGA',         'Kartu Keluarga',                1, 'jpg,jpeg,png,pdf', 2048, 20, 1),
  ('IJAZAH',                 'Ijazah SD / Surat Keterangan Lulus', 1, 'jpg,jpeg,png,pdf', 2048, 30, 1),
  ('PAS_FOTO',               'Pas Foto 3x4',                  1, 'jpg,jpeg,png',     1024, 40, 1),
  ('SURAT_KETERANGAN_SISWA', 'Surat Keterangan Siswa',        0, 'jpg,jpeg,png,pdf', 2048, 50, 1),
  ('LAINNYA',                'Berkas Lainnya',                0, 'jpg,jpeg,png,pdf', 2048, 60, 1);

-- Kolom verifikasi + riwayat keputusan terakhir per berkas.
ALTER TABLE `dokumen`
  ADD COLUMN `catatan_verifikasi` TEXT NULL DEFAULT NULL COMMENT 'Alasan penolakan / catatan verifikator' AFTER `status_verifikasi`,
  ADD COLUMN `verified_by` VARCHAR(36) NULL DEFAULT NULL COMMENT 'users.id petugas terakhir' AFTER `catatan_verifikasi`,
  ADD COLUMN `verified_at` DATETIME NULL DEFAULT NULL COMMENT 'waktu keputusan terakhir' AFTER `verified_by`,
  MODIFY COLUMN `jenis_dokumen` VARCHAR(30) NOT NULL DEFAULT 'LAINNYA';

ALTER TABLE `dokumen`
  ADD INDEX `idx_dok_jenis` (`jenis_dokumen`),
  ADD INDEX `idx_dok_status` (`status_verifikasi`);
