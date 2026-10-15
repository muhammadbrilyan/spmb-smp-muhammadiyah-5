-- ============================================================
-- 20261012_fase0_prasyarat.sql
-- Fase 0: tabel login_attempts + rate_limit
-- Jalankan sebagai root:
--   mysql -u root < database/migrations/20261012_fase0_prasyarat.sql
-- ============================================================

USE db_spmb_mualimku;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  username VARCHAR(100) NULL,
  attempted_at DATETIME NOT NULL,
  INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  aksi VARCHAR(30) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_aksi_time (ip_address, aksi, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
