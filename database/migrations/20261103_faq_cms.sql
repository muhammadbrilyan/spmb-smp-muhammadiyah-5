-- ==================================================================
-- 2026-11-03 — FAQ LANDING PAGE BISA DIEDIT
--
-- pengaturan_sekolah.faq_list
--   Daftar pertanyaan-jawaban pada section "Pusat Bantuan / Tanya Jawab
--   Seputar SPMB (FAQ)" di landing page, disimpan sebagai JSON:
--     [{"q":"Apakah ...?","a":"Ya. ..."}, ...]
--   Sunting di Pengaturan Sistem → Tanya Jawab (FAQ) Landing.
--   Selama kolom masih NULL, halaman memakai contoh bawaan
--   includes/dummy.php ($FAQ) — landing lama tidak berubah tampilannya.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261103_faq_cms.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'faq_list') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN faq_list text DEFAULT NULL AFTER tagline',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
