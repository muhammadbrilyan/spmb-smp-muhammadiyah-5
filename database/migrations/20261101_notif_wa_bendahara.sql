-- ==================================================================
-- 2026-11-01 — NOMOR WA BENDAHARA TERPISAH
--
-- pengaturan_sekolah.notif_wa_bendahara
--   Tujuan khusus notifikasi pembayaran (bukti bayar manual masuk, sama
--   seperti daftar notif_wa_operator). Dulu pembayaran hanya bisa
--   "dilaporkan" ke nomor panitia publik (kontak_wa_panitia) yang juga
--   tampil sebagai tombol chat di halaman publik — padahal bendahara
--   perlu nomor kerjanya sendiri.
--
-- Kolom boleh NULL/kosong: lapisan notifikasi jatuh ke kontak_wa_panitia
-- bila nomor bendahara belum diisi, jadi perilaku lama tidak hilang.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261101_notif_wa_bendahara.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'notif_wa_bendahara') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN notif_wa_bendahara varchar(255) DEFAULT NULL AFTER notif_wa_operator',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
