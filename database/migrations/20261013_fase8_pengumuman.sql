-- ==================================================================
-- FASE 8 — Pengumuman hasil: waktu pengumuman pada pengaturan_sekolah.
-- Jalankan: mysql -u root db_spmb_mualimku < 20261013_fase8_pengumuman.sql
-- ==================================================================

ALTER TABLE `pengaturan_sekolah`
    ADD COLUMN `hasil_diumumkan_pada` DATETIME DEFAULT NULL;
