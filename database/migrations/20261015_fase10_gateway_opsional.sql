-- ==================================================================
-- FASE 10 BAGIAN A — Payment gateway (Midtrans) OPSIONAL.
-- Jalankan: mysql -u root db_spmb_mualimku < 20261015_fase10_gateway_opsional.sql
--
-- Sebelumnya bayar_gateway_aktif DEFAULT 1 sehingga seluruh instalasi
-- dianggap memakai gateway. Sekarang gateway jadi OPSIONAL:
--   - DEFAULT 0 (fresh install nonaktif, cukup transfer/QRIS manual)
--   - instalasi existing dipaksa 0 (aktifkan lagi setelah API key
--     Midtrans benar-benar diisi & diuji di Pengaturan Sistem)
-- Idempoten: jalankan berulang aman (MODIFY tidak mengubah struktur
-- bila sudah cocok; UPDATE tidak menemukan baris pada run kedua).
-- ==================================================================

ALTER TABLE `pengaturan_sekolah`
    MODIFY COLUMN `bayar_gateway_aktif` TINYINT(1) NOT NULL DEFAULT 0;

UPDATE `pengaturan_sekolah`
   SET `bayar_gateway_aktif` = 0
 WHERE `bayar_gateway_aktif` <> 0;
