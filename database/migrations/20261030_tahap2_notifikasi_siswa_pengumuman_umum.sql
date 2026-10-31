-- ==================================================================
-- MIGRASI TAHAP 2 — NOTIFIKASI SISWA + PENGUMUMAN UMUM
--
-- notifikasi_siswa : notifikasi in-app per pendaftar (read/unread
--                    masing-masing penerima), dipakai portal siswa.
-- pengumuman_umum  : pengumuman bebas dari panitia (CRUD operator);
--                    saat diterbitkan dibuatkan baris notifikasi_siswa
--                    untuk semua pendaftar aktif (ref_id = id pengumuman).
--
-- Aman dijalankan berulang (IF NOT EXISTS).
-- ==================================================================

CREATE TABLE IF NOT EXISTS `notifikasi_siswa` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `tipe` varchar(40) NOT NULL,
  `judul` varchar(160) NOT NULL,
  `pesan` text NOT NULL,
  `tautan` varchar(255) DEFAULT NULL,
  `ref_id` varchar(36) DEFAULT NULL,
  `dibaca_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ns_pendaftar` (`pendaftar_id`, `dibaca_at`),
  KEY `idx_ns_ref` (`ref_id`),
  CONSTRAINT `notifikasi_siswa_ibfk_1` FOREIGN KEY (`pendaftar_id`)
    REFERENCES `pendaftar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pengumuman_umum` (
  `id` varchar(36) NOT NULL,
  `judul` varchar(160) NOT NULL,
  `isi` text NOT NULL,
  `penting` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('DRAFT','TERBIT') NOT NULL DEFAULT 'DRAFT',
  `terbit_at` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pu_status` (`status`, `terbit_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
