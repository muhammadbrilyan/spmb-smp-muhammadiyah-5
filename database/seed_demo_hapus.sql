-- =====================================================================
-- HAPUS DATA DEMO / UAT — mengembalikan kondisi seed utama
-- (database/db_spmb_mualimku.sql tanpa akun/data siswa demo).
--
--   mysql -u root < database/seed_demo_hapus.sql
--
-- Catatan: file contoh di storage/dokumen/ dengan prefix "dok-demo-*"
-- dan "bukti-demo-*" TIDAK bisa dihapus lewat SQL — hapus manual bila
-- perlu (file kecil, netral, ikut repository).
-- =====================================================================

USE db_spmb_mualimku;

-- Urutan penghapusan mengikuti kunci asing (children dulu).

-- Demo inti REG-2025-0101
DELETE FROM penempatan_kelas WHERE pendaftar_id = 'prd-0101';
DELETE FROM seleksi          WHERE pendaftar_id = 'prd-0101';
DELETE FROM peserta_ujian    WHERE pendaftar_id = 'prd-0101';
DELETE FROM dokumen          WHERE pendaftar_id = 'prd-0101';
DELETE FROM pembayaran       WHERE pendaftar_id = 'prd-0101';
DELETE FROM orang_tua        WHERE pendaftar_id = 'prd-0101';
DELETE FROM pendaftar        WHERE id = 'prd-0101';
DELETE FROM users            WHERE id = 'usr-siswa-0101';

-- Cakupan tahap REG-2025-0201 s/d 0209
DELETE FROM seleksi          WHERE pendaftar_id IN ('prd-0201','prd-0202','prd-0203','prd-0204','prd-0205','prd-0206','prd-0207','prd-0208','prd-0209');
DELETE FROM peserta_ujian    WHERE pendaftar_id IN ('prd-0201','prd-0202','prd-0203','prd-0204','prd-0205','prd-0206','prd-0207','prd-0208','prd-0209');
DELETE FROM dokumen          WHERE pendaftar_id IN ('prd-0201','prd-0202','prd-0203','prd-0204','prd-0205','prd-0206','prd-0207','prd-0208','prd-0209');
DELETE FROM pembayaran       WHERE pendaftar_id IN ('prd-0201','prd-0202','prd-0203','prd-0204','prd-0205','prd-0206','prd-0207','prd-0208','prd-0209');
DELETE FROM orang_tua        WHERE pendaftar_id IN ('prd-0201','prd-0202','prd-0203','prd-0204','prd-0205','prd-0206','prd-0207','prd-0208','prd-0209');
DELETE FROM pendaftar        WHERE id IN ('prd-0201','prd-0202','prd-0203','prd-0204','prd-0205','prd-0206','prd-0207','prd-0208','prd-0209');
DELETE FROM users            WHERE id IN ('usr-siswa-0203','usr-siswa-0204','usr-siswa-0205','usr-siswa-0206','usr-siswa-0207','usr-siswa-0208','usr-siswa-0209');

SELECT 'Data demo dihapus.' AS info;
