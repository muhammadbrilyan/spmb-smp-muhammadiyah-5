-- ==================================================================
-- MIGRASI — PENGINGAT JATUH TEMPO ANGSURAN (IN-APP + WHATSAPP)
--
-- pengaturan_sekolah:
--   pengingat_angsuran_hari  H-berapa hari pengingat dikirim (default 7).
--   pengingat_angsuran_wa    1 = kirim juga WhatsApp ke orang tua/wali.
--
-- notifikasi_siswa:
--   wa_status  hasil kirim WhatsApp untuk pengingat ini
--              (TERKIRIM | GAGAL | NULL = belum/bukan pengingat WA).
--              Dipakai agar pengingat per jadwal tidak terkirim berulang.
--   wa_at      waktu percobaan kirim WhatsApp terakhir.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261031_pengingat_angsuran.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD COLUMN IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'pengingat_angsuran_hari') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN pengingat_angsuran_hari tinyint(3) unsigned NOT NULL DEFAULT 7 AFTER angsuran_2_jatuh_tempo',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'pengingat_angsuran_wa') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN pengingat_angsuran_wa tinyint(1) NOT NULL DEFAULT 0 AFTER pengingat_angsuran_hari',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifikasi_siswa'
               AND COLUMN_NAME = 'wa_status') = 0,
            'ALTER TABLE notifikasi_siswa ADD COLUMN wa_status varchar(20) DEFAULT NULL AFTER dibaca_at',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifikasi_siswa'
               AND COLUMN_NAME = 'wa_at') = 0,
            'ALTER TABLE notifikasi_siswa ADD COLUMN wa_at datetime DEFAULT NULL AFTER wa_status',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
