-- ==================================================================
-- 2026-11-06 — TEKS PESAN WHATSAPP BISA DIEDIT (CMS)
--
-- pengaturan_sekolah.wa_teks
--   Template pesan WA yang dikirim ke calon murid/wali siswa, disimpan
--   sebagai JSON per jenis pesan:
--     {"instruksi_bayar":"Assalamu'alaikum...","hasil_seleksi":"...",...}
--   Hanya kunci yang benar-benar diubah operator yang disimpan; kunci yang
--   kosong/hilang otomatis jatuh ke teks bawaan di kode
--   (includes/queries/wa-teks.php, wa_teks_katalog()) sehingga perilaku lama
--   tidak pernah hilang dan upgrade aplikasi berikutnya tetap membawa kalimat
--   baru selama operator belum menyentuh field-nya.
--
--   Placeholder yang tersedia tergantung jenis pesannya — {nama},
--   {no_pendaftaran}, {sekolah}, {tautan_hasil}, {jadwal_list}, dsb. Daftar
--   lengkapnya tampil di Pengaturan Sistem → Teks Pesan WhatsApp.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261106_wa_teks_cms.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'wa_teks') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN wa_teks text DEFAULT NULL AFTER faq_list',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
