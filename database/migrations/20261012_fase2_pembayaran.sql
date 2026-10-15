-- ============================================================
-- 20261012_fase2_pembayaran.sql
-- Fase 2: toggle metode pembayaran + QRIS + editor S&K
-- Jalankan sebagai root:
--   mysql -u root < database/migrations/20261012_fase2_pembayaran.sql
-- ============================================================

USE db_spmb_mualimku;

ALTER TABLE pengaturan_sekolah
  ADD COLUMN IF NOT EXISTS bayar_gateway_aktif TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS bayar_transfer_aktif TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS bayar_qris_aktif TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS qris_image_url TEXT NULL,
  ADD COLUMN IF NOT EXISTS qris_merchant VARCHAR(100) NULL,
  ADD COLUMN IF NOT EXISTS kirim_akun_otomatis TINYINT(1) NOT NULL DEFAULT 0;
