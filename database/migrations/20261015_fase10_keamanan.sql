-- =====================================================================
-- Fase 10 Bagian B: Hardening akun
--  - users.wajib_ganti_password: di-set 1 oleh tools/set-password.php
--    (reset password admin/CLI), otomatis kembali 0 setelah pengguna
--    sukses ganti password di app/staf/ganti-password.php.
--  - Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
--    information_schema + PREPARE/EXECUTE (bukan ADD COLUMN IF NOT EXISTS).
-- =====================================================================

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'wajib_ganti_password') = 0,
            'ALTER TABLE `users` ADD COLUMN `wajib_ganti_password` TINYINT(1) NOT NULL DEFAULT 0',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
