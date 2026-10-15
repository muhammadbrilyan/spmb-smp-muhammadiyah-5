-- ============================================================
-- Fase 11 A6 — pay_token_hash (SHA-256) utk tautan bayar publik.
-- Idempoten: aman dijalankan berulang. Token polos dikosongkan;
-- token baru hanya tampil sekali (registrasi / pemulihan cek-status).
-- ============================================================
ALTER TABLE pendaftar
  ADD COLUMN IF NOT EXISTS pay_token_hash CHAR(64) NULL AFTER pay_token;

UPDATE pendaftar
   SET pay_token_hash = SHA2(pay_token, 256)
 WHERE pay_token IS NOT NULL AND pay_token <> '' AND pay_token_hash IS NULL;

UPDATE pendaftar
   SET pay_token = NULL
 WHERE pay_token IS NOT NULL;

ALTER TABLE pendaftar
  ADD UNIQUE KEY IF NOT EXISTS uq_pendaftar_pay_token_hash (pay_token_hash);
