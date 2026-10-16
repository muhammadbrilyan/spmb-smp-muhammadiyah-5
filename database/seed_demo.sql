-- =====================================================================
-- SEED DEMO / UAT — SMP Muhammadiyah 5 (SPMB MUALIMKU)
--
-- HANYA untuk pengembangan & UAT (user acceptance testing).
-- JANGAN dijalankan di lingkungan produksi.
--
-- Berisi data FIKTIF yang MENUTUP SELURUH 11 TAHAP alur SPMB:
--   REG-2025-0101  AKUN_TERKIRIM + sudah LULUS & ditempatkan kelas 7A
--   REG-2025-0201  MENUNGGU_VERIFIKASI_BAYAR (bukti transfer sudah masuk)
--   REG-2025-0202  BAYAR_TERVERIFIKASI (akun belum dibuat)
--   REG-2025-0203  MELENGKAPI_BERKAS (2 dari 4 berkas wajib terunggah)
--   REG-2025-0204  BERKAS_DISUBMIT (antrean verifikasi operator)
--   REG-2025-0205  BERKAS_REVISI (1 berkas ditolak)
--   REG-2025-0206  BERKAS_TERVERIFIKASI
--   REG-2025-0207  JADWAL_DITETAPKAN (+ peserta ujian)
--   REG-2025-0208  NILAI_TERINPUT (nilai lengkap, hasil PROSES)
--   REG-2025-0209  DIUMUMKAN (hasil LULUS)
--
-- Berkas contoh (PNG 4x4 px, netral) ada di storage/dokumen/ dengan
-- prefix "dok-demo-*" / "bukti-demo-*" — file kecil ini ikut repository
-- agar tautan "Lihat berkas" di UI tidak 404.
--
-- Cara pakai (setelah database/db_spmb_mualimku.sql diimpor):
--   mysql -u root < database/seed_demo.sql
--
-- Login portal siswa: semua akun terkunci ('!LOCKED'), set dulu:
--   php tools/set-password.php siswa0203
--
-- Hapus data demo (kembalikan kondisi seed utama):
--   mysql -u root < database/seed_demo_hapus.sql
-- =====================================================================

USE db_spmb_mualimku;

-- =====================================================================
-- BAGIAN 1 — Demo inti (satu pendaftar lengkap sampai penempatan kelas)
-- =====================================================================

-- 1a. Akun siswa demo — hash '!LOCKED' (tidak bisa login sampai di-reset).
INSERT INTO users (id, username, password_hash, role, nama_lengkap, jabatan, wajib_ganti_password) VALUES
  ('usr-siswa-0101', 'siswa', '!LOCKED', 'siswa', 'Aisyah Nur Salsabila', 'Calon Siswa SPMB', 1)
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- 1b. Pendaftar demo — tahap AKUN_TERKIRIM, sudah terverifikasi & ditempatkan.
INSERT INTO pendaftar
  (id, user_id, no_pendaftaran, nama_siswa, nisn, jenis_kelamin, alamat, asal_sekolah,
   jalur, is_luar_daerah, kelas_id, status_verifikasi, status_seleksi, status_akun, tahap,
   kred_sent_at, created_at)
VALUES
  ('prd-0101', 'usr-siswa-0101', 'REG-2025-0101', 'Aisyah Nur Salsabila', '0129482099', 'P',
   'Jl. Merdeka No. 12, Cikokol', 'SD Muhammadiyah 01 Tangerang', 'Tahfidz', 0, 'kls-7a',
   'TERVERIFIKASI', 'PROSES', 'AKTIF', 'AKUN_TERKIRIM', '2025-10-08 08:30:00', '2025-10-06 08:15:00')
ON DUPLICATE KEY UPDATE nama_siswa = VALUES(nama_siswa);

INSERT INTO orang_tua (id, pendaftar_id, nama_orang_tua, no_whatsapp, alamat, pekerjaan, penghasilan_bulanan) VALUES
  ('ort-0101', 'prd-0101', 'H. Ahmad Fauzi', '+6281299431102', 'Jl. Merdeka No. 12, Cikokol', 'Karyawan Swasta', 'Rp 5.000.000 - 7.500.000')
ON DUPLICATE KEY UPDATE nama_orang_tua = VALUES(nama_orang_tua);

INSERT INTO pembayaran (id, pendaftar_id, jenis_pembayaran, nominal, status, keterangan, verified_at, verified_by, created_at) VALUES
  ('byr-0101', 'prd-0101', 'FORMULIR', 200000.00, 'VERIFIED', 'Transfer BSI', '2025-10-08 09:05:00', 'usr-bendahara', '2025-10-06 08:45:00')
ON DUPLICATE KEY UPDATE status = VALUES(status);

INSERT INTO seleksi (id, pendaftar_id, jadwal_id, nilai_cbt, nilai_btq, nilai_wawancara, nilai_akhir, hasil, input_by) VALUES
  ('sel-0101', 'prd-0101', 'tes-01', 94, 92, 90, 94.5, 'LULUS', 'usr-operator')
ON DUPLICATE KEY UPDATE hasil = VALUES(hasil);

INSERT INTO penempatan_kelas (id, pendaftar_id, kelas_id, ditempatkan_oleh) VALUES
  ('ptm-0101', 'prd-0101', 'kls-7a', 'usr-operator')
ON DUPLICATE KEY UPDATE kelas_id = VALUES(kelas_id);

-- =====================================================================
-- BAGIAN 2 — Cakupan seluruh tahap (REG-2025-0201 s/d 0209)
-- =====================================================================

-- 2a. Akun portal siswa untuk pendaftar ber-tahap >= MELENGKAPI_BERKAS
--     — semuanya terkunci ('!LOCKED') selayaknya seed utama.
--     (Sebelum INSERT pendaftar: FK pendaftar.user_id -> users.id.)
INSERT INTO users (id, username, password_hash, role, nama_lengkap, jabatan, wajib_ganti_password) VALUES
  ('usr-siswa-0203', 'siswa0203', '!LOCKED', 'siswa', 'Hana Masruroh', 'Calon Siswa SPMB', 1),
  ('usr-siswa-0204', 'siswa0204', '!LOCKED', 'siswa', 'Ilham Fadillah Ramadhan', 'Calon Siswa SPMB', 1),
  ('usr-siswa-0205', 'siswa0205', '!LOCKED', 'siswa', 'Jelita Anindya Ramadhani', 'Calon Siswa SPMB', 1),
  ('usr-siswa-0206', 'siswa0206', '!LOCKED', 'siswa', 'Kamil Rizky Maulana', 'Calon Siswa SPMB', 1),
  ('usr-siswa-0207', 'siswa0207', '!LOCKED', 'siswa', 'Larasati Wijayanti', 'Calon Siswa SPMB', 1),
  ('usr-siswa-0208', 'siswa0208', '!LOCKED', 'siswa', 'Muhammad Andraka Putra', 'Calon Siswa SPMB', 1),
  ('usr-siswa-0209', 'siswa0209', '!LOCKED', 'siswa', 'Nabila Zahwa Aulia', 'Calon Siswa SPMB', 1)
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- 2b. Pendaftar: 9 tahap yang belum dicakup Bagian 1 / seed utama.
INSERT INTO pendaftar
  (id, user_id, no_pendaftaran, nama_siswa, nisn, jenis_kelamin, alamat, asal_sekolah,
   jalur, is_luar_daerah, kelas_id, status_verifikasi, status_seleksi, status_akun, tahap,
   kred_sent_at, created_at)
VALUES
  -- 0201: bukti transfer sudah masuk, menunggu verifikasi bendahara
  ('prd-0201', NULL, 'REG-2025-0201', 'Farida Ayu Larasati', '0125556677', 'P',
   'Jl. Saraswati No. 5, Cikupa', 'SD Muhammadiyah 02 Tangerang', 'Reguler', 0, NULL,
   'MENUNGGU_VERIFIKASI', 'PROSES', 'BELUM_DIBUAT', 'MENUNGGU_VERIFIKASI_BAYAR', NULL, '2025-10-09 07:50:00'),
  -- 0202: bayar terverifikasi, menunggu "Verifikasi & Kirim Akun"
  ('prd-0202', NULL, 'REG-2025-0202', 'Galih Prasetyo Nugroho', '0116667788', 'L',
   'Jl. Kisamaun No. 17', 'SDIT Al-Hikmah Tangerang', 'Tahfidz', 0, NULL,
   'MENUNGGU_VERIFIKASI', 'PROSES', 'BELUM_DIBUAT', 'BAYAR_TERVERIFIKASI', NULL, '2025-10-09 08:20:00'),
  -- 0203: akun aktif, sebagian berkas terunggah (belum submit)
  ('prd-0203', 'usr-siswa-0203', 'REG-2025-0203', 'Hana Masruroh', '0127778899', 'P',
   'Perum Ciledug Indah Blok D1', 'SDN Ciledug 2', 'Prestasi', 0, NULL,
   'MENUNGGU_VERIFIKASI', 'PROSES', 'AKTIF', 'MELENGKAPI_BERKAS', '2025-10-09 09:15:00', '2025-10-09 08:40:00'),
  -- 0204: submit lengkap — antrean verifikasi berkas operator
  ('prd-0204', 'usr-siswa-0204', 'REG-2025-0204', 'Ilham Fadillah Ramadhan', '0118889900', 'L',
   'Jl. Hasyim Ashari No. 88', 'MI Nurul Iman', 'Reguler', 0, NULL,
   'MENUNGGU_VERIFIKASI', 'PROSES', 'AKTIF', 'BERKAS_DISUBMIT', '2025-10-09 10:05:00', '2025-10-09 09:00:00'),
  -- 0205: satu berkas ditolak — menunggu unggah ulang siswa
  ('prd-0205', 'usr-siswa-0205', 'REG-2025-0205', 'Jelita Anindya Ramadhani', '0129990011', 'P',
   'Jl. Satria No. 30, Poris', 'SD Muhammadiyah 03 Tangerang', 'Reguler', 0, NULL,
   'MENUNGGU_VERIFIKASI', 'PROSES', 'AKTIF', 'BERKAS_REVISI', '2025-10-09 11:00:00', '2025-10-09 09:30:00'),
  -- 0206: semua berkas lolos verifikasi
  ('prd-0206', 'usr-siswa-0206', 'REG-2025-0206', 'Kamil Rizky Maulana', '0110102030', 'L',
   'Jl. Dharmawangsa No. 9', 'SDN Panunggangan 1', 'Afirmasi', 1, NULL,
   'TERVERIFIKASI', 'PROSES', 'AKTIF', 'BERKAS_TERVERIFIKASI', '2025-10-09 12:00:00', '2025-10-09 10:00:00'),
  -- 0207: jadwal tes ditetapkan (peserta ujian terdaftar)
  ('prd-0207', 'usr-siswa-0207', 'REG-2025-0207', 'Larasati Wijayanti', '0121212121', 'P',
   'Jl. Otista No. 12, Cipondoh', 'SDIT Fathimah', 'Tahfidz', 0, NULL,
   'TERVERIFIKASI', 'PROSES', 'AKTIF', 'JADWAL_DITETAPKAN', '2025-10-09 13:00:00', '2025-10-09 10:30:00'),
  -- 0208: nilai sudah diinput, hasil masih PROSES (menunggu pengumuman)
  ('prd-0208', 'usr-siswa-0208', 'REG-2025-0208', 'Muhammad Andraka Putra', '0113435363', 'L',
   'Jl. NKRI No. 41, Karawaci', 'SD Muhammadiyah 04', 'Prestasi', 0, NULL,
   'TERVERIFIKASI', 'PROSES', 'AKTIF', 'NILAI_TERINPUT', '2025-10-09 14:00:00', '2025-10-09 11:00:00'),
  -- 0209: pengumuman sudah dirilis — LULUS (penempatan kelas menyusul)
  ('prd-0209', 'usr-siswa-0209', 'REG-2025-0209', 'Nabila Zahwa Aulia', '0125657585', 'P',
   'Perum Pondok Cabe Asri B2', 'SDN Pondok Cabe 1', 'Reguler', 0, NULL,
   'TERVERIFIKASI', 'LULUS', 'AKTIF', 'DIUMUMKAN', '2025-10-09 15:00:00', '2025-10-09 11:30:00')
ON DUPLICATE KEY UPDATE nama_siswa = VALUES(nama_siswa);

-- 2c. Orang tua — nomor WA fiktif unik (utk cek status & 4 digit terakhir).
INSERT INTO orang_tua (id, pendaftar_id, nama_orang_tua, no_whatsapp, alamat, pekerjaan, penghasilan_bulanan) VALUES
  ('ort-0201', 'prd-0201', 'Ibu Siti Larasati',    '+6281399210201', 'Jl. Saraswati No. 5',    'Ibu Rumah Tangga',        'Rp 3.000.000 - 5.000.000'),
  ('ort-0202', 'prd-0202', 'Bapak Bambang Nugroho','+6281399210202', 'Jl. Kisamaun No. 17',    'Wiraswasta',              'Rp 5.000.000 - 7.500.000'),
  ('ort-0203', 'prd-0203', 'Ibu Nur Masruroh',     '+6281399210203', 'Perum Ciledug Indah D1', 'Guru',                    'Rp 3.000.000 - 5.000.000'),
  ('ort-0204', 'prd-0204', 'Bapak Deden Ramadhan', '+6281399210204', 'Jl. Hasyim Ashari No. 88','Pegawai Swasta',         'Rp 5.000.000 - 7.500.000'),
  ('ort-0205', 'prd-0205', 'Ibu Ratna Ramadhani',  '+6281399210205', 'Jl. Satria No. 30',      'Bidan',                   'Rp 3.000.000 - 5.000.000'),
  ('ort-0206', 'prd-0206', 'Bapak Ahmad Maulana',  '+6281399210206', 'Jl. Dharmawangsa No. 9', 'Sopir',                   'Rp 2.000.000 - 3.000.000'),
  ('ort-0207', 'prd-0207', 'Ibu Endang Wijayanti', '+6281399210207', 'Jl. Otista No. 12',      'Pedagang',                'Rp 3.000.000 - 5.000.000'),
  ('ort-0208', 'prd-0208', 'Bapak Ridwan Putra',   '+6281399210208', 'Jl. NKRI No. 41',        'PNS',                     'Rp 5.000.000 - 7.500.000'),
  ('ort-0209', 'prd-0209', 'Ibu Wulan Aulia',      '+6281399210209', 'Perum Pondok Cabe B2',   'Karyawan Swasta',         'Rp 5.000.000 - 7.500.000')
ON DUPLICATE KEY UPDATE nama_orang_tua = VALUES(nama_orang_tua);

-- 2d. Pembayaran biaya formulir sesuai tahap.
INSERT INTO pembayaran (id, pendaftar_id, jenis_pembayaran, nominal, status, keterangan,
                        bukti_transfer_url, metode, verified_at, verified_by, created_at) VALUES
  ('byr-0201', 'prd-0201', 'FORMULIR', 200000.00, 'PENDING',   'Bukti transfer sudah diunggah - menunggu verifikasi',
   'storage/dokumen/bukti-demo-0201.png', 'TRANSFER', NULL, NULL, '2025-10-09 08:05:00'),
  ('byr-0202', 'prd-0202', 'FORMULIR', 200000.00, 'VERIFIED',  'Terverifikasi 09 Okt 2025, 08:50',
   NULL, NULL, '2025-10-09 08:50:00', 'usr-bendahara', '2025-10-09 08:30:00'),
  ('byr-0203', 'prd-0203', 'FORMULIR', 200000.00, 'VERIFIED',  'Terverifikasi 09 Okt 2025, 09:00',
   NULL, NULL, '2025-10-09 09:00:00', 'usr-bendahara', '2025-10-09 08:45:00'),
  ('byr-0204', 'prd-0204', 'FORMULIR', 200000.00, 'VERIFIED',  'Terverifikasi 09 Okt 2025, 09:20',
   NULL, NULL, '2025-10-09 09:20:00', 'usr-bendahara', '2025-10-09 09:05:00'),
  ('byr-0205', 'prd-0205', 'FORMULIR', 200000.00, 'VERIFIED',  'Terverifikasi 09 Okt 2025, 09:45',
   NULL, NULL, '2025-10-09 09:45:00', 'usr-bendahara', '2025-10-09 09:35:00'),
  ('byr-0206', 'prd-0206', 'FORMULIR', 200000.00, 'VERIFIED',  'Terverifikasi 09 Okt 2025, 10:15',
   NULL, NULL, '2025-10-09 10:15:00', 'usr-bendahara', '2025-10-09 10:05:00'),
  ('byr-0207', 'prd-0207', 'FORMULIR', 200000.00, 'VERIFIED',  'Terverifikasi 09 Okt 2025, 10:45',
   NULL, NULL, '2025-10-09 10:45:00', 'usr-bendahara', '2025-10-09 10:40:00'),
  ('byr-0208', 'prd-0208', 'FORMULIR', 200000.00, 'VERIFIED',  'Terverifikasi 09 Okt 2025, 11:15',
   NULL, NULL, '2025-10-09 11:15:00', 'usr-bendahara', '2025-10-09 11:10:00'),
  ('byr-0209', 'prd-0209', 'FORMULIR', 200000.00, 'VERIFIED',  'Terverifikasi 09 Okt 2025, 11:45',
   NULL, NULL, '2025-10-09 11:45:00', 'usr-bendahara', '2025-10-09 11:40:00')
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- 2e. Berkas persyaratan (4 jenis wajib: AKTA, KARTU_KELUARGA, IJAZAH, PAS_FOTO).
--     Berkas contoh = PNG kecil di storage/dokumen/ (prefix dok-demo-*).
INSERT INTO dokumen (id, pendaftar_id, jenis_dokumen, file_url, ukuran_kb,
                     status_verifikasi, catatan_verifikasi, verified_by, verified_at, uploaded_at) VALUES
  -- 0203: 2 dari 4 terunggah, masih PENDING (belum submit)
  ('dk-0203a', 'prd-0203', 'AKTA',           'storage/dokumen/dok-demo-0203-a.png', 1, 'PENDING', NULL, NULL, NULL, '2025-10-09 08:55:00'),
  ('dk-0203b', 'prd-0203', 'KARTU_KELUARGA', 'storage/dokumen/dok-demo-0203-b.png', 1, 'PENDING', NULL, NULL, NULL, '2025-10-09 09:05:00'),
  -- 0204: lengkap semua, menunggu operator
  ('dk-0204a', 'prd-0204', 'AKTA',           'storage/dokumen/dok-demo-0204-a.png', 1, 'PENDING', NULL, NULL, NULL, '2025-10-09 09:25:00'),
  ('dk-0204b', 'prd-0204', 'KARTU_KELUARGA', 'storage/dokumen/dok-demo-0204-b.png', 1, 'PENDING', NULL, NULL, NULL, '2025-10-09 09:28:00'),
  ('dk-0204c', 'prd-0204', 'IJAZAH',         'storage/dokumen/dok-demo-0204-c.png', 1, 'PENDING', NULL, NULL, NULL, '2025-10-09 09:31:00'),
  ('dk-0204d', 'prd-0204', 'PAS_FOTO',       'storage/dokumen/dok-demo-0204-d.png', 1, 'PENDING', NULL, NULL, NULL, '2025-10-09 09:34:00'),
  -- 0205: ijazah ditolak -> tahap BERKAS_REVISI
  ('dk-0205a', 'prd-0205', 'AKTA',           'storage/dokumen/dok-demo-0205-a.png', 1, 'VERIFIED', NULL,               'usr-operator', '2025-10-09 10:40:00', '2025-10-09 09:50:00'),
  ('dk-0205b', 'prd-0205', 'KARTU_KELUARGA', 'storage/dokumen/dok-demo-0205-b.png', 1, 'VERIFIED', NULL,               'usr-operator', '2025-10-09 10:42:00', '2025-10-09 09:53:00'),
  ('dk-0205c', 'prd-0205', 'IJAZAH',         'storage/dokumen/dok-demo-0205-c.png', 1, 'REJECTED', 'Foto kurang jelas - unggah ulang dokumen yang terbaca penuh.', 'usr-operator', '2025-10-09 10:45:00', '2025-10-09 09:56:00'),
  ('dk-0205d', 'prd-0205', 'PAS_FOTO',       'storage/dokumen/dok-demo-0205-d.png', 1, 'VERIFIED', NULL,               'usr-operator', '2025-10-09 10:47:00', '2025-10-09 09:59:00'),
  -- 0206 - 0209: seluruh berkas terverifikasi
  ('dk-0206a', 'prd-0206', 'AKTA',           'storage/dokumen/dok-demo-0206-a.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 11:20:00', '2025-10-09 10:10:00'),
  ('dk-0206b', 'prd-0206', 'KARTU_KELUARGA', 'storage/dokumen/dok-demo-0206-b.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 11:22:00', '2025-10-09 10:12:00'),
  ('dk-0206c', 'prd-0206', 'IJAZAH',         'storage/dokumen/dok-demo-0206-c.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 11:24:00', '2025-10-09 10:14:00'),
  ('dk-0206d', 'prd-0206', 'PAS_FOTO',       'storage/dokumen/dok-demo-0206-d.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 11:26:00', '2025-10-09 10:16:00'),
  ('dk-0207a', 'prd-0207', 'AKTA',           'storage/dokumen/dok-demo-0207-a.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 12:10:00', '2025-10-09 11:05:00'),
  ('dk-0207b', 'prd-0207', 'KARTU_KELUARGA', 'storage/dokumen/dok-demo-0207-b.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 12:12:00', '2025-10-09 11:07:00'),
  ('dk-0207c', 'prd-0207', 'IJAZAH',         'storage/dokumen/dok-demo-0207-c.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 12:14:00', '2025-10-09 11:09:00'),
  ('dk-0207d', 'prd-0207', 'PAS_FOTO',       'storage/dokumen/dok-demo-0207-d.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 12:16:00', '2025-10-09 11:11:00'),
  ('dk-0208a', 'prd-0208', 'AKTA',           'storage/dokumen/dok-demo-0208-a.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 13:05:00', '2025-10-09 11:45:00'),
  ('dk-0208b', 'prd-0208', 'KARTU_KELUARGA', 'storage/dokumen/dok-demo-0208-b.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 13:07:00', '2025-10-09 11:47:00'),
  ('dk-0208c', 'prd-0208', 'IJAZAH',         'storage/dokumen/dok-demo-0208-c.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 13:09:00', '2025-10-09 11:49:00'),
  ('dk-0208d', 'prd-0208', 'PAS_FOTO',       'storage/dokumen/dok-demo-0208-d.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 13:11:00', '2025-10-09 11:51:00'),
  ('dk-0209a', 'prd-0209', 'AKTA',           'storage/dokumen/dok-demo-0209-a.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 14:10:00', '2025-10-09 12:30:00'),
  ('dk-0209b', 'prd-0209', 'KARTU_KELUARGA', 'storage/dokumen/dok-demo-0209-b.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 14:12:00', '2025-10-09 12:32:00'),
  ('dk-0209c', 'prd-0209', 'IJAZAH',         'storage/dokumen/dok-demo-0209-c.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 14:14:00', '2025-10-09 12:34:00'),
  ('dk-0209d', 'prd-0209', 'PAS_FOTO',       'storage/dokumen/dok-demo-0209-d.png', 1, 'VERIFIED', NULL, 'usr-operator', '2025-10-09 14:16:00', '2025-10-09 12:36:00')
ON DUPLICATE KEY UPDATE status_verifikasi = VALUES(status_verifikasi);

-- 2f. Peserta ujian — pendaftar tahap JADWAL_DITETAPKAN / setelahnya.
INSERT INTO peserta_ujian (id, pendaftar_id, jadwal_id, nomor_peserta, ruangan) VALUES
  ('psr-0207', 'prd-0207', 'tes-01', 'P-2025-0207', 'Ruang 1'),
  ('psr-0208', 'prd-0208', 'tes-01', 'P-2025-0208', 'Ruang 1'),
  ('psr-0209', 'prd-0209', 'tes-01', 'P-2025-0209', 'Ruang 2')
ON DUPLICATE KEY UPDATE jadwal_id = VALUES(jadwal_id);

-- 2g. Nilai seleksi.
INSERT INTO seleksi (id, pendaftar_id, jadwal_id, nilai_cbt, nilai_btq, nilai_wawancara, nilai_akhir, hasil, input_by) VALUES
  -- 0208: hasil PROSES — bahan "Finalisasi Pengumuman" operator
  ('sel-0208', 'prd-0208', 'tes-01', 88, 85, 87, 87.5, 'PROSES',   'usr-operator'),
  -- 0209: sudah LULUS & diumumkan
  ('sel-0209', 'prd-0209', 'tes-01', 92, 90, 94, 92.4, 'LULUS', 'usr-operator')
ON DUPLICATE KEY UPDATE hasil = VALUES(hasil);

SELECT 'seed_demo terimpor (11 tahap lengkap). Set password dulu: php tools/set-password.php siswa0203' AS info;
