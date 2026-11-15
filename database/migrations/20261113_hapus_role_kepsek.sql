-- ==================================================================
-- 2026-11-13 — HAPUS ROLE KEPALA SEKOLAH (kepsek)
--
-- Aplikasi hanya memakai tiga role: siswa, bendahara, operator.
-- Langkah (idempoten):
--   1. hapus akun ber-role kepsek dari tabel users;
--   2. lepas foreign key + hapus kolom persetujuan/tanda tangan hasil
--      seleksi di pengaturan_sekolah (fitur halaman kepsek);
--   3. sempitkan enum users.role menjadi tiga nilai.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261113_hapus_role_kepsek.sql
-- Portabel MySQL 5.7/8 & MariaDB 10.x (information_schema + PREPARE).
-- ==================================================================

USE db_spmb_mualimku;

DELETE FROM users WHERE role = 'kepsek';

SET @s = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND CONSTRAINT_NAME = 'fk_pengaturan_hasil_disetujui') > 0,
            'ALTER TABLE pengaturan_sekolah DROP FOREIGN KEY fk_pengaturan_hasil_disetujui',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND CONSTRAINT_NAME = 'fk_pengaturan_hasil_ttd') > 0,
            'ALTER TABLE pengaturan_sekolah DROP FOREIGN KEY fk_pengaturan_hasil_ttd',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_disetujui_oleh') > 0,
            'ALTER TABLE pengaturan_sekolah DROP COLUMN hasil_disetujui_oleh',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_disetujui_at') > 0,
            'ALTER TABLE pengaturan_sekolah DROP COLUMN hasil_disetujui_at',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_disetujui_sidik') > 0,
            'ALTER TABLE pengaturan_sekolah DROP COLUMN hasil_disetujui_sidik',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_ttd_oleh') > 0,
            'ALTER TABLE pengaturan_sekolah DROP COLUMN hasil_ttd_oleh',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_ttd_at') > 0,
            'ALTER TABLE pengaturan_sekolah DROP COLUMN hasil_ttd_at',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_ttd_sidik') > 0,
            'ALTER TABLE pengaturan_sekolah DROP COLUMN hasil_ttd_sidik',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'role') LIKE '%kepsek%',
            "ALTER TABLE users MODIFY role ENUM('siswa','bendahara','operator') NOT NULL DEFAULT 'siswa'",
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
