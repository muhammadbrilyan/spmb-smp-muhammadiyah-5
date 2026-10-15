-- ============================================================
-- fix-grants.sql — jalankan setiap kali MySQL XAMPP restart
-- atau user spmb_app kehilangan hak akses (akibat crash/corrupt).
--
-- Cara pakai:
--   mysql -u root < database/fix-grants.sql
--
-- ATAU set password manual dulu via mysql -u root -p, lalu:
--   SET @pw = 'PASSWORD_DARI_ENV';
--   SOURCE database/fix-grants.sql;
--
-- Password default di bawah HANYA placeholder — ganti sesuai .env.
-- ============================================================

-- Ganti nilai di bawah dengan password dari .env sebelum menjalankan
SET @pw = 'GANTI_DENGAN_PASSWORD_DARI_ENV';

CREATE USER IF NOT EXISTS 'spmb_app'@'localhost' IDENTIFIED BY @pw;
ALTER USER 'spmb_app'@'localhost' IDENTIFIED BY @pw;

-- Hak terbatas: hanya DML, tanpa DDL/DCL
GRANT SELECT, INSERT, UPDATE, DELETE ON db_spmb_mualimku.* TO 'spmb_app'@'localhost';

FLUSH PRIVILEGES;

-- Verifikasi
SHOW GRANTS FOR 'spmb_app'@'localhost';
