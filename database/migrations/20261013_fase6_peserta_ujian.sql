-- ==================================================================
-- FASE 6 — Jadwal ujian offline: tabel peserta_ujian + ruangan &
-- kapasitas di jadwal_tes.
-- Jalankan: mysql -u root db_spmb_mualimku < 20261013_fase6_peserta_ujian.sql
-- ==================================================================

-- Kolom ruangan & kapasitas pada jadwal tes (0 = tanpa batas).
ALTER TABLE `jadwal_tes`
    ADD COLUMN `ruangan`  VARCHAR(100) DEFAULT NULL AFTER `lokasi`,
    ADD COLUMN `kapasitas` INT NOT NULL DEFAULT 0 AFTER `ruangan`;

-- Peserta ujian: satu pendaftar hanya boleh terjadwal sekali.
CREATE TABLE IF NOT EXISTS `peserta_ujian` (
    `id`            VARCHAR(36)  NOT NULL,
    `pendaftar_id`  VARCHAR(36)  NOT NULL,
    `jadwal_id`     VARCHAR(36)  NOT NULL,
    `nomor_peserta` VARCHAR(30)  NOT NULL,
    `ruangan`       VARCHAR(100) DEFAULT NULL,
    `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_peserta_pendaftar` (`pendaftar_id`),
    UNIQUE KEY `uq_peserta_nomor` (`nomor_peserta`),
    KEY `idx_peserta_jadwal` (`jadwal_id`),
    CONSTRAINT `fk_peserta_pendaftar` FOREIGN KEY (`pendaftar_id`)
        REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_peserta_jadwal` FOREIGN KEY (`jadwal_id`)
        REFERENCES `jadwal_tes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
