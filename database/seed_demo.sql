-- =====================================================================
-- SEED DEMO / UAT — SMP Muhammadiyah 5 (SPMB MUALIMKU)
--
-- HANYA untuk pengembangan & UAT (user acceptance testing).
-- JANGAN dijalankan di lingkungan produksi.
--
-- Berisi data FIKTIF: satu siswa demo lengkap (akun terkunci, pendaftar
-- REG-2025-0101, orang tua, pembayaran terverifikasi, nilai LULUS,
-- penempatan kelas 7A) agar uji alur bisa dimulai tanpa mengetik ulang.
--
-- Cara pakai (setelah database/db_spmb_mualimku.sql diimpor):
--   mysql -u root < database/seed_demo.sql
--
-- Login portal siswa: set password dulu, karena hash sengaja DIKUNCI:
--   php tools/set-password.php siswa
--
-- Hapus data demo (kembalikan kondisi seed utama):
--   mysql -u root < database/seed_demo_hapus.sql
-- =====================================================================

USE db_spmb_mualimku;

-- 1. Akun siswa demo — hash '!LOCKED' (tidak bisa login sampai di-reset).
INSERT INTO users (id, username, password_hash, role, nama_lengkap, jabatan, wajib_ganti_password) VALUES
  ('usr-siswa-0101', 'siswa', '!LOCKED', 'siswa', 'Aisyah Nur Salsabila', 'Calon Siswa SPMB', 1)
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- 2. Pendaftar demo — tahap AKUN_TERKIRIM, sudah terverifikasi & ditempatkan.
INSERT INTO pendaftar
  (id, user_id, no_pendaftaran, nama_siswa, nisn, jenis_kelamin, alamat, asal_sekolah,
   jalur, is_luar_daerah, kelas_id, status_verifikasi, status_seleksi, status_akun, tahap,
   kred_sent_at, created_at)
VALUES
  ('prd-0101', 'usr-siswa-0101', 'REG-2025-0101', 'Aisyah Nur Salsabila', '0129482099', 'P',
   'Jl. Merdeka No. 12, Cikokol', 'SD Muhammadiyah 01 Tangerang', 'Tahfidz', 0, 'kls-7a',
   'TERVERIFIKASI', 'PROSES', 'AKTIF', 'AKUN_TERKIRIM', '2025-10-08 08:30:00', '2025-10-06 08:15:00')
ON DUPLICATE KEY UPDATE nama_siswa = VALUES(nama_siswa);

-- 3. Orang tua demo.
INSERT INTO orang_tua (id, pendaftar_id, nama_orang_tua, no_whatsapp, alamat, pekerjaan, penghasilan_bulanan) VALUES
  ('ort-0101', 'prd-0101', 'H. Ahmad Fauzi', '+6281299431102', 'Jl. Merdeka No. 12, Cikokol', 'Karyawan Swasta', 'Rp 5.000.000 - 7.500.000')
ON DUPLICATE KEY UPDATE nama_orang_tua = VALUES(nama_orang_tua);

-- 4. Pembayaran formulir demo — terverifikasi.
INSERT INTO pembayaran (id, pendaftar_id, jenis_pembayaran, nominal, status, keterangan, verified_at, verified_by, created_at) VALUES
  ('byr-0101', 'prd-0101', 'FORMULIR', 200000.00, 'VERIFIED', 'Transfer BSI', '2025-10-08 09:05:00', 'usr-bendahara', '2025-10-06 08:45:00')
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- 5. Nilai seleksi demo — LULUS.
INSERT INTO seleksi (id, pendaftar_id, jadwal_id, nilai_cbt, nilai_btq, nilai_wawancara, nilai_akhir, hasil, input_by) VALUES
  ('sel-0101', 'prd-0101', 'tes-01', 94, 92, 90, 94.5, 'LULUS', 'usr-operator')
ON DUPLICATE KEY UPDATE hasil = VALUES(hasil);

-- 6. Penempatan demo — kelas 7A.
INSERT INTO penempatan_kelas (id, pendaftar_id, kelas_id, ditempatkan_oleh) VALUES
  ('ptm-0101', 'prd-0101', 'kls-7a', 'usr-operator')
ON DUPLICATE KEY UPDATE kelas_id = VALUES(kelas_id);

SELECT 'seed_demo terimpor. Jalankan: php tools/set-password.php siswa' AS info;
