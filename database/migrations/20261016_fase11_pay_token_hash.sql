-- ============================================================
-- Fase 11 A6 — pay_token_hash (SHA-256) utk tautan bayar publik.
-- Idempoten: aman dijalankan berulang. Token polos dikosongkan;
-- token baru hanya tampil sekali (registrasi / pemulihan cek-status).
-- PORTABEL (MySQL 5.7/8 & MariaDB 10.x): information_schema +
-- PREPARE/EXECUTE (bukan ADD COLUMN/UNIQUE KEY IF NOT EXISTS).
-- ============================================================

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
               AND COLUMN_NAME = 'pay_token_hash') = 0,
            'ALTER TABLE pendaftar ADD COLUMN pay_token_hash CHAR(64) NULL AFTER pay_token',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE pendaftar
   SET pay_token_hash = SHA2(pay_token, 256)
 WHERE pay_token IS NOT NULL AND pay_token <> '' AND pay_token_hash IS NULL;

UPDATE pendaftar
   SET pay_token = NULL
 WHERE pay_token IS NOT NULL;

SET @s = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
               AND INDEX_NAME = 'uq_pendaftar_pay_token_hash') = 0,
            'ALTER TABLE pendaftar ADD UNIQUE KEY uq_pendaftar_pay_token_hash (pay_token_hash)',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
