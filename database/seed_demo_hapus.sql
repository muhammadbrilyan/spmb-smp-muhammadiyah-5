-- =====================================================================
-- HAPUS DATA DEMO / UAT — mengembalikan kondisi seed utama
-- (database/db_spmb_mualimku.sql tanpa akun/data siswa demo).
--
--   mysql -u root < database/seed_demo_hapus.sql
-- =====================================================================

USE db_spmb_mualimku;

-- Urutan penghapusan mengikuti kunci asing (children dulu).
DELETE FROM penempatan_kelas WHERE pendaftar_id = 'prd-0101';
DELETE FROM seleksi          WHERE pendaftar_id = 'prd-0101';
DELETE FROM peserta_ujian    WHERE pendaftar_id = 'prd-0101';
DELETE FROM dokumen          WHERE pendaftar_id = 'prd-0101';
DELETE FROM pembayaran       WHERE pendaftar_id = 'prd-0101';
DELETE FROM orang_tua        WHERE pendaftar_id = 'prd-0101';
DELETE FROM pendaftar        WHERE id = 'prd-0101';
DELETE FROM users            WHERE id = 'usr-siswa-0101';

SELECT 'Data demo dihapus.' AS info;
