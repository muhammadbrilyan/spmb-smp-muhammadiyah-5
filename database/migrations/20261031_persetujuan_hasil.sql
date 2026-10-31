-- ==================================================================
-- 2026-10-31 — PERSETUJUAN & TANDA TANGAN HASIL SELEKSI (role kepsek)
--
-- pengaturan_sekolah:
--   hasil_disetujui_oleh / _at   siapa & kapan kepala sekolah menyetujui
--       daftar hasil SEBELUM operator menerbitkan pengumuman.
--   hasil_disetujui_sidik        sidik (sha256) daftar hasil saat disetujui.
--       Bila operator mengubah hasil sesudah itu, sidik tidak cocok dan
--       persetujuan dianggap batal — penerbitan terkunci lagi.
--   hasil_ttd_oleh / _at / _sidik   tanda tangan digital atas rilis yang
--       SUDAH terbit (arsip administratif, juga untuk gelombang sebelumnya).
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261031_persetujuan_hasil.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_disetujui_oleh') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN hasil_disetujui_oleh varchar(36) DEFAULT NULL AFTER hasil_diumumkan_pada',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_disetujui_at') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN hasil_disetujui_at datetime DEFAULT NULL AFTER hasil_disetujui_oleh',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_disetujui_sidik') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN hasil_disetujui_sidik varchar(64) DEFAULT NULL AFTER hasil_disetujui_at',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_ttd_oleh') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN hasil_ttd_oleh varchar(36) DEFAULT NULL AFTER hasil_disetujui_sidik',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_ttd_at') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN hasil_ttd_at datetime DEFAULT NULL AFTER hasil_ttd_oleh',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'hasil_ttd_sidik') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN hasil_ttd_sidik varchar(64) DEFAULT NULL AFTER hasil_ttd_at',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

/* Kunci asing: akun penyetuju dihapus => persetujuan hilang, bukan menggantung. */
SET @s = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND CONSTRAINT_NAME = 'fk_pengaturan_hasil_disetujui') = 0,
            'ALTER TABLE pengaturan_sekolah ADD CONSTRAINT fk_pengaturan_hasil_disetujui FOREIGN KEY (hasil_disetujui_oleh) REFERENCES users(id) ON DELETE SET NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND CONSTRAINT_NAME = 'fk_pengaturan_hasil_ttd') = 0,
            'ALTER TABLE pengaturan_sekolah ADD CONSTRAINT fk_pengaturan_hasil_ttd FOREIGN KEY (hasil_ttd_oleh) REFERENCES users(id) ON DELETE SET NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
