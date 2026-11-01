-- ==================================================================
-- 2026-11-02 — RINCIAN BIAYA SPMB & AMBANG KELULUSAN
--
-- pengaturan_sekolah.rincian_biaya
--   Daftar item biaya yang boleh disusun bebas oleh operator (label +
--   nominal), disimpan sebagai JSON:
--     [{"l":"Formulir Pendaftaran","n":200000},{"l":"Infaq 3 Tahun","n":350000}]
--   Total daftar ini menjadi Biaya Masuk (estimasi_uang_masuk), jadi
--   angsuran_skema() dan seluruh halaman biaya tidak perlu berubah rumus.
--   Kolom boleh NULL: selama belum diisi, aplikasi memakai Biaya Masuk
--   yang diketik manual seperti sebelumnya.
--
-- pengaturan_sekolah.ambang_kelulusan
--   Nilai akhir minimal agar calon siswa otomatis berstatus LULUS di
--   halaman Input Hasil Seleksi. Dulu ambang hanya hidup di layar dan
--   hilang saat halaman dimuat ulang; sekarang ikut tersimpan.
--
-- Jalankan sebagai root (akun spmb_app tidak punya hak ALTER):
--   mysql -u root < database/migrations/20261102_rincian_biaya.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD ... IF NOT EXISTS).
-- ==================================================================

USE db_spmb_mualimku;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'rincian_biaya') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN rincian_biaya text DEFAULT NULL AFTER estimasi_uang_masuk',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'ambang_kelulusan') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN ambang_kelulusan decimal(5,2) NOT NULL DEFAULT 70.00 AFTER rincian_biaya',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
