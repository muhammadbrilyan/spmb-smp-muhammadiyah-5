-- ============================================================
-- migrasi-idempotency.sql
-- Tambah kolom idempotency_key & snap_token di pembayaran,
-- registration_token di pendaftar. Jalankan sekali pada DB live:
--   mysql -u root < database/migrasi-idempotency.sql
-- ============================================================

ALTER TABLE pembayaran
  ADD COLUMN idempotency_key VARCHAR(64) NULL AFTER midtrans_status,
  ADD COLUMN snap_token TEXT NULL,
  ADD COLUMN snap_expires_at DATETIME NULL,
  ADD UNIQUE KEY uq_pembayaran_idem (idempotency_key);

ALTER TABLE pendaftar
  ADD COLUMN registration_token VARCHAR(64) NULL,
  ADD UNIQUE KEY uq_pendaftar_regtoken (registration_token);
