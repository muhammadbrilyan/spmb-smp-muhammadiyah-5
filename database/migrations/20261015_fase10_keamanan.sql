-- =====================================================================
-- Fase 10 Bagian B: Hardening akun
--  - users.wajib_ganti_password: di-set 1 oleh tools/set-password.php
--    (reset password admin/CLI), otomatis kembali 0 setelah pengguna
--    sukses ganti password di app/staf/ganti-password.php.
--  - Idempoten (ADD COLUMN IF NOT EXISTS).
-- =====================================================================
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `wajib_ganti_password` TINYINT(1) NOT NULL DEFAULT 0;
