-- ==================================================================
-- 2026-11-06 — users.notif_mulai_at
--
-- Batas waktu notifikasi yang BOLEH dilihat calon siswa di portal.
--
--   NULL      = akun belum pernah masuk portal → saat portal pertama
--               kali dimuat, kolom ini diisi NOW() oleh aplikasi.
--   terisi    = waktu masuk pertama ke portal siswa.
--
-- Kenapa perlu: panitia (bendahara/operator) membuat akun begitu
-- pembayaran terverifikasi, dan keputusan-keputusan SEBELUM itu
-- (mis. BAYAR_DITOLAK dari uji manual) sudah tercatat di
-- notifikasi_siswa. Tanpa batas, siswa yang baru pertama kali login
-- langsung disambut daftar "pembayaran ditolak" dari peristiwa yang
-- belum pernah dialaminya. Baris lama tetap disimpan untuk audit,
-- hanya tidak ditampilkan.
--
-- Timestamp ditulis SEKALI (WHERE notif_mulai_at IS NULL) supaya
-- daftar notifikasi tidak bergeser setiap kali login.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261106_notifikasi_baseline.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'notif_mulai_at') = 0,
            'ALTER TABLE users ADD COLUMN notif_mulai_at datetime DEFAULT NULL AFTER wajib_ganti_password',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
