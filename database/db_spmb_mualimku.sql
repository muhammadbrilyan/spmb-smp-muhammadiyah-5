-- =====================================================================
-- DATABASE: db_spmb_mualimku
-- Sistem PPDB & CMS - SMP Muhammadiyah 5 Kota Tangerang
-- Target: MySQL 8 / MariaDB 10.4 (XAMPP)
--
-- Sumber utama : PRD.md.txt bagian "5. SKEMA DATABASE & DATA MODEL"
-- Catatan      : berkas PRD terpotong di tengah tabel `pembayaran`
--                (kolom `status` terpotong). Bagian yang dirampungkan
--                dan tabel/kolom yang dibutuhkan fitur FR-02 / FR-05
--                serta mockup stitch_mualimku ditandai [TAMBAHAN].
--
-- Impor: mysql -u root < database/db_spmb_mualimku.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS db_spmb_mualimku
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE db_spmb_mualimku;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS peserta_ujian;
DROP TABLE IF EXISTS penempatan_kelas;
DROP TABLE IF EXISTS seleksi;
DROP TABLE IF EXISTS jadwal_tes;
DROP TABLE IF EXISTS dokumen;
DROP TABLE IF EXISTS pembayaran;
DROP TABLE IF EXISTS orang_tua;
DROP TABLE IF EXISTS pendaftar;
DROP TABLE IF EXISTS kelas;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS pengaturan_sekolah;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS rate_limit;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. pengaturan_sekolah - konfigurasi CMS (single row, id = 1)
--     [TAMBAHAN] kolom yang tampil pada mockup CMS Settings
--     (admin_cms_settings_...): email, ticker, profil singkat, SPP,
--     estimasi uang masuk, jadwal gelombang.
-- ---------------------------------------------------------------------
CREATE TABLE pengaturan_sekolah (
  id INT PRIMARY KEY DEFAULT 1,
  nama_sekolah VARCHAR(150) NOT NULL DEFAULT 'SMP Muhammadiyah 5 Kota Tangerang',
  alamat_sekolah TEXT,
  logo_url TEXT,
  hero_banner_url TEXT,                             -- [TAMBAHAN] banner hero landing page
  login_banner_url TEXT,                            -- [TAMBAHAN] banner halaman login
  favicon_url TEXT,
  warna_utama VARCHAR(20) DEFAULT '#0056b3',
  biaya_formulir DECIMAL(12,2) DEFAULT 200000.00,
  biaya_spp DECIMAL(12,2) DEFAULT 0.00,              -- [TAMBAHAN]
  estimasi_uang_masuk DECIMAL(12,2) DEFAULT 0.00,    -- [TAMBAHAN]
  label_uang_pangkal VARCHAR(100) DEFAULT 'Uang Pangkal Gedung', -- [TAMBAHAN] label bisa diganti
  label_spp VARCHAR(100) DEFAULT 'SPP Bulanan',       -- [TAMBAHAN] label bisa diganti
  nama_bank VARCHAR(50) DEFAULT 'Bank Syariah Indonesia (BSI)',
  no_rekening VARCHAR(50) DEFAULT '1234567890',
  atas_nama_rekening VARCHAR(100) DEFAULT 'SMP Muhammadiyah 5',
  kontak_wa_panitia VARCHAR(20) DEFAULT '628123456789',
  email_sekolah VARCHAR(100),                         -- [TAMBAHAN]
  ticker_header TEXT,                                 -- [TAMBAHAN] running text header
  profil_singkat TEXT,                                -- [TAMBAHAN] hero overview
  deskripsi_sekolah TEXT,                             -- [TAMBAHAN] deskripsi sekolah untuk landing
  tagline VARCHAR(255) DEFAULT 'Berakhlak & Berprestasi', -- [TAMBAHAN] tagline di bawah nama sekolah
  tahun_ajaran VARCHAR(20) DEFAULT '2025/2026',       -- [TAMBAHAN]
  gelombang VARCHAR(50) DEFAULT 'Gelombang 1',        -- [TAMBAHAN]
  tanggal_buka_ppdb DATE,                             -- [TAMBAHAN]
  tanggal_tutup_ppdb DATE,                            -- [TAMBAHAN]
  tanggal_pengumuman DATE,                            -- [TAMBAHAN]
  deskripsi_gelombang TEXT,                           -- [TAMBAHAN]
  jalur_aktif TEXT,                                   -- [TAMBAHAN] JSON array key jalur aktif
  fonnte_token VARCHAR(255),                          -- [TAMBAHAN] API token Fonnte (WA gateway)
  midtrans_server_key VARCHAR(255),                   -- [TAMBAHAN] Midtrans Server Key (rahasia)
  midtrans_client_key VARCHAR(255),                   -- [TAMBAHAN] Midtrans Client Key (publik)
  midtrans_sandbox TINYINT(1) DEFAULT 1,              -- [TAMBAHAN] 1=sandbox, 0=production
  syarat_ketentuan LONGTEXT,                          -- [TAMBAHAN] teks S&K (Fase 1)
  syarat_versi INT NOT NULL DEFAULT 1,                -- [TAMBAHAN] versi S&K (naik saat diubah)
  bayar_gateway_aktif TINYINT(1) NOT NULL DEFAULT 0,  -- [Fase 2/10] toggle Midtrans Snap (opsional, default nonaktif)
  bayar_transfer_aktif TINYINT(1) NOT NULL DEFAULT 1, -- [Fase 2] toggle transfer bank manual
  bayar_qris_aktif TINYINT(1) NOT NULL DEFAULT 0,     -- [Fase 2] toggle QRIS manual
  qris_image_url TEXT NULL,                           -- [Fase 2] path upload gambar QRIS
  qris_merchant VARCHAR(100) NULL,                    -- [Fase 2] nama merchant QRIS
  kirim_akun_otomatis TINYINT(1) NOT NULL DEFAULT 0,  -- [Fase 2] auto-create akun siswa setelah bayar
  notif_wa_operator VARCHAR(255),                     -- [Fase 3] daftar nomor WA operator, dipisah koma
  notif_wa_aktif TINYINT(1) NOT NULL DEFAULT 0,       -- [Fase 3] toggle kirim WA notifikasi operator
  notif_email VARCHAR(255),                           -- [Fase 3] TITIK SAMBUNGAN — belum diimplementasikan
  hasil_diumumkan_pada DATETIME DEFAULT NULL,         -- [Fase 8] waktu pengumuman hasil seleksi
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO pengaturan_sekolah
  (id, nama_sekolah, alamat_sekolah, logo_url, warna_utama, biaya_formulir, biaya_spp,
   estimasi_uang_masuk, label_uang_pangkal, label_spp, nama_bank, no_rekening, atas_nama_rekening, kontak_wa_panitia,
   email_sekolah, ticker_header, profil_singkat, deskripsi_sekolah, tagline, tahun_ajaran, gelombang,
   tanggal_buka_ppdb, tanggal_tutup_ppdb, tanggal_pengumuman, deskripsi_gelombang,
   jalur_aktif, syarat_ketentuan, syarat_versi,
   bayar_gateway_aktif, bayar_transfer_aktif, bayar_qris_aktif,
   notif_wa_operator, notif_wa_aktif, notif_email)
VALUES
  (1,
   'SMP Muhammadiyah 5 Kota Tangerang',
   'Jl. Perintis Kemerdekaan No. 1, Cikokol, Kota Tangerang, Banten 15111',
   'assets/img/uploads/logo-sekolah.png',
   '#0056b3',
   200000.00,
   350000.00,
   4500000.00,
   'Uang Pangkal Gedung',
   'SPP Bulanan',
   'Bank Syariah Indonesia (BSI)',
   '714-239-5501',
   'SMP Muhammadiyah 5 Tangerang',
   '628123456789',
   'spmb@smpmuh5tng.sch.id',
   'Pendaftaran SPMB Gelombang 1 Dibuka: 1 Oktober - 30 November - Kuota Terbatas 90 Siswa (3 Rombel) - Hubungi Layanan SPMB: 0812-9876-5501',
   'SMP Muhammadiyah 5 Kota Tangerang binaan Muhammadiyah dengan pembinaan tahfidz hingga 10 juz, sertifikasi BTQ, dan prestasi akademik.',
   'Membentuk generasi muda yang unggul dalam ilmu pengetahuan, berkarakter Islam, dan berdaya saing global di wilayah Kota Tangerang.',
   'Berakhlak & Berprestasi',
   '2025/2026',
   'Gelombang 1',
   '2025-10-01',
   '2025-11-30',
   '2025-12-05',
   'Pendaftaran daring dibuka 1 Oktober - 30 November. Seleksi meliputi CBT, wawancara BTQ, dan wawancara orang tua.',
   '["Reguler","Tahfidz","Prestasi","Afirmasi"]',
   'SYARAT & KETENTUAN PENDAFTARAN\nSMP MUHAMMADIYAH 5 KOTA TANGERANG\n\n1. Pendaftar adalah siswa kelas 6 SD/MI yang akan melanjutkan ke SMP kelas 7 pada tahun ajaran berikutnya.\n\n2. Data yang diisi pada formulir pendaftaran harus benar sesuai dokumen resmi (akta kelahiran, Kartu Keluarga, Ijazah SD/MI).\n\n3. Biaya formulir pendaftaran sebesar yang tertera di halaman ini tidak dapat dikembalikan dalam keadaan apa pun.\n\n4. Pendaftar wajib mengunggah seluruh berkas persyaratan sesuai daftar yang ditentukan panitia.\n\n5. Panitia berhak menolak pendaftar yang memberikan data tidak benar atau berkas tidak lengkap.\n\n6. Jadwal ujian, pengumuman hasil, dan pembagian kelas akan diinformasikan melalui website resmi dan/atau WhatsApp orang tua.\n\n7. Keputusan panitia bersifat final.\n\nDengan menyetujui syarat & ketentuan ini, saya menyatakan bahwa seluruh data yang saya isi adalah benar dan saya memahami seluruh ketentuan pendaftaran di atas.',
   1,
   0,
   1,
   0,
   NULL,
   0,
   NULL)
ON DUPLICATE KEY UPDATE id = 1;

-- ---------------------------------------------------------------------
-- 1b. notifikasi + wa_log (Fase 3 — Notifikasi Operator)
--     notifikasi: lencana/halaman Notifikasi di dashboard operator.
--     wa_log: hasil kirim WhatsApp Fonnte (sukses/gagal tetap dicatat).
-- ---------------------------------------------------------------------
CREATE TABLE notifikasi (
  id VARCHAR(36) PRIMARY KEY,
  tipe VARCHAR(40) NOT NULL,
  pendaftar_id VARCHAR(36) NULL,
  pesan TEXT NOT NULL,
  dibaca_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_dibaca (dibaca_at),
  KEY idx_notif_tipe (tipe),
  KEY idx_notif_pendaftar (pendaftar_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wa_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tujuan VARCHAR(30) NOT NULL,
  tipe VARCHAR(40) NOT NULL,
  status VARCHAR(20) NOT NULL,
  respons TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_walog_tipe (tipe),
  KEY idx_walog_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. users - akun login (PRD F-04)
--     [TAMBAHAN] nama_lengkap, jabatan (tampil di topbar/sidebar),
--                is_active (menonaktifkan akun tanpa menghapus riwayat)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id VARCHAR(36) PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,      -- password_hash(PASSWORD_DEFAULT)
  role ENUM('siswa','bendahara','operator','kepsek') NOT NULL DEFAULT 'siswa',
  nama_lengkap VARCHAR(100),                -- [TAMBAHAN]
  jabatan VARCHAR(100),                     -- [TAMBAHAN]
  is_active TINYINT(1) NOT NULL DEFAULT 1,  -- [TAMBAHAN]
  wajib_ganti_password TINYINT(1) NOT NULL DEFAULT 0,  -- Fase 10: 1 = wajib ganti password (reset via tools/set-password.php)
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Kredensial awal (GANTI sebelum go-live - lihat app/operator/panduan.php).
-- Password operator/bendahara/kepsek/siswa WAJIB diganti via menu Pengaturan / reset DB.
-- Hash bcrypt hasil PHP password_hash() - jangan commit password asli ke repository.
INSERT INTO users (id, username, password_hash, role, nama_lengkap, jabatan) VALUES
  ('usr-operator', 'operator',
   '$2y$10$cCV3vfP22ehds/erObf1AORhgz7eHd9eo4VLGxhI0Tf.ltSQZto6K',
   'operator', 'Ust. M. Rizky Pratama, S.Pd.', 'Operator SPMB & SIMS'),
  ('usr-bendahara', 'bendahara',
   '$2y$10$qMqVVnB4oMz7NOaZwNXsw.TXWMCqA1Y0rd6ERbBz6Zz6B.9PSRoM6',
   'bendahara', 'Hj. Siti Rohmah, S.E.', 'Bendahara Sekolah'),
  ('usr-kepsek', 'kepsek',
   '$2y$10$qYXRbDZWJKJ5ZTKHCNGAJe8PO6mFU7J53Jc4.O74ovRYDJap/bXeu',
   'kepsek', 'Drs. H. Ahmad Sudrajat, M.Pd', 'Kepala Sekolah'),
  ('usr-siswa-0101', 'siswa',
   '$2y$10$AVHe0zjfUmVZX4vnYkQNr.Z5kn5xYcXtzg222XqpSnIkagGuTOrDe',
   'siswa', 'Aisyah Nur Salsabila', 'Calon Siswa SPMB');

-- ---------------------------------------------------------------------
-- 3. kelas - rombel & kuota (FR-02, CMS "Rombel Kelas")
--     Kuota total pendaftaran = SUM(kuota) WHERE is_aktif = 1 (FR-02.3)
-- ---------------------------------------------------------------------
CREATE TABLE kelas (
  id VARCHAR(36) PRIMARY KEY,
  kode VARCHAR(10) UNIQUE NOT NULL,          -- '7A'
  nama_kelas VARCHAR(100) NOT NULL,
  wali_kelas VARCHAR(100),
  kuota INT NOT NULL DEFAULT 30,             -- batas kapasitas maksimal
  is_aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO kelas (id, kode, nama_kelas, wali_kelas, kuota) VALUES
  ('kls-7a', '7A', 'Kelas VII-A (Tahfidz & Sains Unggulan)', 'Ustadz Fahru Rozi, S.Pd.I', 30),
  ('kls-7b', '7B', 'Kelas VII-B (Bilingual & IT Digital)',   'Siti Rahmawati, M.Pd',       30),
  ('kls-7c', '7C', 'Kelas VII-C (Reguler Inovatif)',          'Drs. Bambang Irawan',        30);

-- ---------------------------------------------------------------------
-- 4. pendaftar (PRD + [TAMBAHAN] sesuai FR-05.1 & dashboard operator)
-- ---------------------------------------------------------------------
CREATE TABLE pendaftar (
  id VARCHAR(36) PRIMARY KEY,
  user_id VARCHAR(36),                                  -- akun siswa (F-04)
  no_pendaftaran VARCHAR(20) UNIQUE NOT NULL,
  nama_siswa VARCHAR(100) NOT NULL,
  nisn VARCHAR(20),                                     -- [TAMBAHAN] FR-05.1
  jenis_kelamin ENUM('L','P'),                          -- [TAMBAHAN]
  tempat_lahir VARCHAR(80),                             -- [TAMBAHAN]
  tanggal_lahir DATE,                                   -- [TAMBAHAN]
  alamat TEXT,                                          -- [TAMBAHAN]
  asal_sekolah VARCHAR(100) NOT NULL,
  jalur VARCHAR(30) DEFAULT 'Reguler',                  -- [TAMBAHAN] Tahfidz/Reguler/Prestasi/Afirmasi
  is_luar_daerah TINYINT(1) DEFAULT 0,
  kelas_id VARCHAR(36),                                 -- [TAMBAHAN] penempatan akhir
  status_verifikasi ENUM('DRAFT','MENUNGGU_VERIFIKASI','TERVERIFIKASI','DITOLAK')
      NOT NULL DEFAULT 'DRAFT',                         -- [TAMBAHAN] kunci form (FR-03.2)
  status_seleksi ENUM('PROSES','LULUS','TIDAK_LULUS') DEFAULT 'PROSES',  -- [TAMBAHAN] FR-05.3
  status_akun ENUM('BELUM_DIBUAT','AKTIF','GAGAL') DEFAULT 'BELUM_DIBUAT', -- [TAMBAHAN] kredensial WA
  tahap ENUM(
    'MENUNGGU_PEMBAYARAN','MENUNGGU_VERIFIKASI_BAYAR','BAYAR_TERVERIFIKASI',
    'AKUN_TERKIRIM',
    'MELENGKAPI_BERKAS','BERKAS_DISUBMIT','BERKAS_REVISI','BERKAS_TERVERIFIKASI',
    'JADWAL_DITETAPKAN','NILAI_TERINPUT','DIUMUMKAN'
  ) DEFAULT 'MENUNGGU_PEMBAYARAN',                        -- [TAMBAHAN] model status alur SPMB
  kred_sent_at DATETIME,                                -- [TAMBAHAN] waktu kirim WA
  setuju_sk_at DATETIME,                                -- [TAMBAHAN] bukti persetujuan S&K
  setuju_sk_versi INT,                                  -- [TAMBAHAN] versi S&K yang disetujui
  setuju_sk_ip VARCHAR(45),                             -- [TAMBAHAN] IP saat menyetujui
  pay_token VARCHAR(64),                                -- [TAMBAHAN] token tautan bayar publik
  registration_token VARCHAR(64),                       -- [TAMBAHAN] idempotency key registrasi publik
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pendaftar_regtoken (registration_token),
  UNIQUE KEY uq_pendaftar_pay_token (pay_token),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO pendaftar
  (id, user_id, no_pendaftaran, nama_siswa, nisn, jenis_kelamin, alamat, asal_sekolah,
   jalur, is_luar_daerah, kelas_id, status_verifikasi, status_seleksi, status_akun, tahap,
   kred_sent_at, created_at)
VALUES
  ('prd-0101', 'usr-siswa-0101', 'REG-2025-0101', 'Aisyah Nur Salsabila',       '0129482099', 'P', 'Jl. Merdeka No. 12, Cikokol',        'SD Muhammadiyah 01 Tangerang', 'Tahfidz',  0, 'kls-7a', 'TERVERIFIKASI',    'PROSES',    'AKTIF',      'AKUN_TERKIRIM',    '2025-10-08 08:30:00', '2025-10-06 08:15:00'),
  ('prd-0102', NULL,                'REG-2025-0102', 'Muhammad Farhan Al-Ghidari','0118492041', 'L', 'Jl. KH. Hasyim Ashari No. 4',        'MI Negeri 1 Tangerang',        'Reguler',  0, 'kls-7a', 'TERVERIFIKASI',    'PROSES',    'AKTIF',      'AKUN_TERKIRIM',    '2025-10-08 08:35:00', '2025-10-06 09:40:00'),
  ('prd-0103', NULL,                'REG-2025-0103', 'Zahra Annisa Khairunnisa',  '0123910245', 'P', 'Perum Bumi Indah Blok C2',           'SDIT As-Syifa Karawaci',       'Prestasi', 0, NULL,     'MENUNGGU_VERIFIKASI', 'PROSES', 'BELUM_DIBUAT', 'MENUNGGU_PEMBAYARAN', NULL,                   '2025-10-07 10:05:00'),
  ('prd-0104', NULL,                'REG-2025-0104', 'Bilal Dwi Kurniawan',       '0117392812', 'L', 'Jl. Cikokol Raya No. 88',            'SD Negeri Cikokol 3',           'Reguler',  0, NULL,     'MENUNGGU_VERIFIKASI', 'PROSES', 'BELUM_DIBUAT', 'MENUNGGU_PEMBAYARAN', NULL,                   '2025-10-07 13:22:00'),
  ('prd-0105', NULL,                'REG-2025-0105', 'Nadira Putri Az-Zahra',     '0128841029', 'P', 'Perum Poris Pelawad Blok A5',        'SDN Poris Pelawad 1',           'Reguler',  0, 'kls-7b', 'TERVERIFIKASI',    'PROSES',    'AKTIF',      'AKUN_TERKIRIM',    '2025-10-08 09:12:00', '2025-10-07 15:48:00'),
  ('prd-0106', NULL,                'REG-2025-0106', 'Rizky Maulana Alamsyah',    '0115598101', 'L', 'Jl. Batusari No. 21',                'SD Muhammadiyah 2 Batusari',    'Afirmasi', 1, NULL,     'DITOLAK',          'PROSES',    'GAGAL',      'MENUNGGU_PEMBAYARAN', '2025-10-08 09:20:00', '2025-10-08 08:05:00');

-- ---------------------------------------------------------------------
-- 5. orang_tua (PRD, tanpa perubahan)
-- ---------------------------------------------------------------------
CREATE TABLE orang_tua (
  id VARCHAR(36) PRIMARY KEY,
  pendaftar_id VARCHAR(36) NOT NULL,
  nama_orang_tua VARCHAR(100) NOT NULL,
  no_whatsapp VARCHAR(20) NOT NULL,
  alamat TEXT,
  pekerjaan VARCHAR(100),
  penghasilan_bulanan VARCHAR(50),
  FOREIGN KEY (pendaftar_id) REFERENCES pendaftar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO orang_tua
  (id, pendaftar_id, nama_orang_tua, no_whatsapp, alamat, pekerjaan, penghasilan_bulanan)
VALUES
  ('ort-0101', 'prd-0101', 'H. Ahmad Fauzi',        '+6281299431102', 'Jl. Merdeka No. 12, Cikokol',  'Karyawan Swasta', 'Rp 5.000.000 - 7.500.000'),
  ('ort-0102', 'prd-0102', 'Siti Aminah',           '+6281388214099', 'Jl. KH. Hasyim Ashari No. 4', 'Guru',            'Rp 3.000.000 - 5.000.000'),
  ('ort-0103', 'prd-0103', 'Rahmat Hidayat',        '+6285719402391', 'Perum Bumi Indah Blok C2',    'Wiraswasta',      'Rp 7.500.000 - 10.000.000'),
  ('ort-0104', 'prd-0104', 'Dewi Lestari',          '+6282144789012', 'Jl. Cikokol Raya No. 88',     'Pegawai Negeri',  'Rp 5.000.000 - 7.500.000'),
  ('ort-0105', 'prd-0105', 'Nur Hidayatullah',      '+6285677123380', 'Perum Poris Pelawad Blok A5', 'Petani',          'Rp 2.000.000 - 3.000.000'),
  ('ort-0106', 'prd-0106', 'Muhammad Yusuf',        '+6282388214477', 'Jl. Batusari No. 21',         'Pedagang',        'Rp 3.000.000 - 5.000.000');

-- ---------------------------------------------------------------------
-- 6. pembayaran (PRD, kolom `status` dirampungkan)
--     [TAMBAHAN] keterangan, verified_at, verified_by
--     Status LUNAS ditambahkan mengikuti dashboard Bendahara mockup.
-- ---------------------------------------------------------------------
CREATE TABLE pembayaran (
  id VARCHAR(36) PRIMARY KEY,
  pendaftar_id VARCHAR(36) NOT NULL,
  jenis_pembayaran VARCHAR(20) NOT NULL,   -- 'FORMULIR' | 'UANG_MASUK'
  nominal DECIMAL(12,2) NOT NULL,
  bukti_transfer_url TEXT,
  status ENUM('PENDING','VERIFIED','REJECTED','LUNAS','ANGSURAN_1','ANGSURAN_2')
      NOT NULL DEFAULT 'PENDING',
  keterangan VARCHAR(255),
  order_id VARCHAR(64),                    -- [TAMBAHAN] ID transaksi Midtrans
  midtrans_status VARCHAR(20),             -- [TAMBAHAN] pending/settlement/deny/expired
  idempotency_key VARCHAR(64),             -- [TAMBAHAN] kunci idempotensi request pembayaran (UNIQUE)
  snap_token TEXT,                         -- [TAMBAHAN] cache Snap token agar retry tidak membuat transaksi baru
  snap_expires_at DATETIME,                -- [TAMBAHAN] masa berlaku snap_token
  payment_method VARCHAR(30),              -- [TAMBAHAN] bca_va/qris/gopay/dll
  metode ENUM('GATEWAY','TRANSFER','QRIS'), -- [TAMBAHAN] Fase 2: metode pembayaran
  catatan TEXT,                            -- [TAMBAHAN] Fase 2: catatan (alasan tolak, dll)
  paid_at DATETIME,                        -- [TAMBAHAN] waktu pembayaran berhasil
  verified_at DATETIME,
  verified_by VARCHAR(36),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pembayaran_order (order_id),
  UNIQUE KEY uq_pembayaran_idem (idempotency_key),
  FOREIGN KEY (pendaftar_id) REFERENCES pendaftar(id) ON DELETE CASCADE,
  FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO pembayaran
  (id, pendaftar_id, jenis_pembayaran, nominal, status, keterangan, verified_at, verified_by, created_at)
VALUES
  ('byr-0101', 'prd-0101', 'FORMULIR', 200000.00, 'VERIFIED', 'Transfer BSI', '2025-10-08 09:05:00', 'usr-bendahara', '2025-10-06 08:45:00'),
  ('byr-0102', 'prd-0102', 'FORMULIR', 200000.00, 'VERIFIED', 'Transfer BSI', '2025-10-08 09:10:00', 'usr-bendahara', '2025-10-06 09:52:00'),
  ('byr-0103', 'prd-0103', 'FORMULIR', 200000.00, 'PENDING',  'Menunggu unggah bukti', NULL, NULL, '2025-10-07 10:20:00'),
  ('byr-0104', 'prd-0104', 'FORMULIR', 200000.00, 'PENDING',  'Menunggu unggah bukti', NULL, NULL, '2025-10-07 13:35:00'),
  ('byr-0105', 'prd-0105', 'FORMULIR', 200000.00, 'VERIFIED', 'Transfer BSI', '2025-10-08 09:30:00', 'usr-bendahara', '2025-10-07 16:02:00'),
  ('byr-0106', 'prd-0106', 'FORMULIR', 200000.00, 'REJECTED', 'Bukti tidak terbaca, minta ulang', '2025-10-08 09:45:00', 'usr-bendahara', '2025-10-08 08:20:00');

-- ---------------------------------------------------------------------
-- 7. dokumen - unggah berkas persyaratan (FR-05.1, maks 2MB)
--     [TAMBAHAN] seluruh tabel (PRD hanya menyebut unggah berkas)
--     [Fase 5] jenis_dokumen dinamis (tabel jenis_dokumen) + kolom verifikasi
-- ---------------------------------------------------------------------
CREATE TABLE jenis_dokumen (
  kode VARCHAR(30) PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  wajib TINYINT(1) NOT NULL DEFAULT 1,
  format_diizinkan VARCHAR(100) NOT NULL DEFAULT 'jpg,jpeg,png,pdf',
  maks_kb INT NOT NULL DEFAULT 2048,
  urutan INT NOT NULL DEFAULT 0,
  aktif TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- Data sementara — daftar final menyusul (bisa diubah via Pengaturan Sistem).
INSERT INTO jenis_dokumen (kode, nama, wajib, format_diizinkan, maks_kb, urutan, aktif) VALUES
  ('AKTA',                   'Akta Kelahiran',                1, 'jpg,jpeg,png,pdf', 2048, 10, 1),
  ('KARTU_KELUARGA',         'Kartu Keluarga',                1, 'jpg,jpeg,png,pdf', 2048, 20, 1),
  ('IJAZAH',                 'Ijazah SD / Surat Keterangan Lulus', 1, 'jpg,jpeg,png,pdf', 2048, 30, 1),
  ('PAS_FOTO',               'Pas Foto 3x4',                  1, 'jpg,jpeg,png',     1024, 40, 1),
  ('SURAT_KETERANGAN_SISWA', 'Surat Keterangan Siswa',        0, 'jpg,jpeg,png,pdf', 2048, 50, 1),
  ('LAINNYA',                'Berkas Lainnya',                0, 'jpg,jpeg,png,pdf', 2048, 60, 1);

CREATE TABLE dokumen (
  id VARCHAR(36) PRIMARY KEY,
  pendaftar_id VARCHAR(36) NOT NULL,
  jenis_dokumen VARCHAR(30) NOT NULL DEFAULT 'LAINNYA',
  file_url TEXT,
  ukuran_kb INT,
  status_verifikasi ENUM('PENDING','VERIFIED','REJECTED') NOT NULL DEFAULT 'PENDING',
  catatan_verifikasi TEXT,                              -- [Fase 5] alasan penolakan / catatan
  verified_by VARCHAR(36),                              -- [Fase 5] users.id petugas terakhir
  verified_at DATETIME,                                 -- [Fase 5] waktu keputusan terakhir
  uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY idx_dok_jenis (jenis_dokumen),
  KEY idx_dok_status (status_verifikasi),
  FOREIGN KEY (pendaftar_id) REFERENCES pendaftar(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. jadwal_tes - jadwal tes offline / video call (FR-05.3)
--     [TAMBAHAN] seluruh tabel
-- ---------------------------------------------------------------------
CREATE TABLE jadwal_tes (
  id VARCHAR(36) PRIMARY KEY,
  kode VARCHAR(20) UNIQUE NOT NULL,          -- 'TES-01'
  nama_kegiatan VARCHAR(120) NOT NULL,
  tanggal DATE NOT NULL,
  jam_mulai TIME NOT NULL,
  jam_selesai TIME NOT NULL,
  mode ENUM('OFFLINE','DARING','VIDEO_CALL') NOT NULL DEFAULT 'OFFLINE',
  lokasi VARCHAR(150),
  ruangan VARCHAR(100),                      -- Fase 6: penamaan ruang uji
  kapasitas INT NOT NULL DEFAULT 0,          -- Fase 6: 0 = tanpa batas
  tautan_video VARCHAR(255),
  peserta_dijadwalkan INT DEFAULT 0,
  status ENUM('DRAFT','TERJADWAL','SELESAI') NOT NULL DEFAULT 'DRAFT',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO jadwal_tes
  (id, kode, nama_kegiatan, tanggal, jam_mulai, jam_selesai, mode, lokasi, ruangan, kapasitas, peserta_dijadwalkan, status)
VALUES
  ('tes-01', 'TES-01', 'Ujian CBT & Wawancara BTQ',    '2025-10-18', '08:00:00', '11:00:00', 'OFFLINE',    'Lab Komputer & Aula', 'Lab Komputer 1-2', 80, 142, 'TERJADWAL'),
  ('tes-02', 'TES-02', 'Wawancara Orang Tua / Wali',   '2025-10-19', '09:00:00', '14:00:00', 'OFFLINE',    'Ruang Rapat', 'Ruang Rapat', 40, 96, 'TERJADWAL'),
  ('tes-03', 'TES-03', 'Seleksi Prestasi & Tahfidz',   '2025-10-20', '13:00:00', '16:00:00', 'VIDEO_CALL', NULL, NULL, 0, 42, 'DRAFT'),
  ('tes-04', 'TES-04', 'Tes Kesehatan & Psikotes',     '2025-10-25', '08:00:00', '12:00:00', 'OFFLINE',    'UKS & Aula', 'UKS & Aula', 100, 184, 'DRAFT');

-- Peserta ujian (Fase 6) — satu pendaftar satu jadwal, nomor unik.
CREATE TABLE peserta_ujian (
  id VARCHAR(36) PRIMARY KEY,
  pendaftar_id VARCHAR(36) NOT NULL,
  jadwal_id VARCHAR(36) NOT NULL,
  nomor_peserta VARCHAR(30) NOT NULL,
  ruangan VARCHAR(100),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_peserta_pendaftar (pendaftar_id),
  UNIQUE KEY uq_peserta_nomor (nomor_peserta),
  KEY idx_peserta_jadwal (jadwal_id),
  FOREIGN KEY (pendaftar_id) REFERENCES pendaftar(id) ON DELETE CASCADE,
  FOREIGN KEY (jadwal_id) REFERENCES jadwal_tes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. seleksi - nilai & hasil seleksi (FR-05.3)
--     [TAMBAHAN] seluruh tabel
-- ---------------------------------------------------------------------
CREATE TABLE seleksi (
  id VARCHAR(36) PRIMARY KEY,
  pendaftar_id VARCHAR(36) NOT NULL,
  jadwal_id VARCHAR(36),
  nilai_cbt DECIMAL(5,2),
  nilai_btq DECIMAL(5,2),
  nilai_wawancara DECIMAL(5,2),
  nilai_akhir DECIMAL(5,2),
  hasil ENUM('PROSES','LULUS','TIDAK_LULUS') NOT NULL DEFAULT 'PROSES',
  catatan VARCHAR(255),
  input_by VARCHAR(36),
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (pendaftar_id) REFERENCES pendaftar(id) ON DELETE CASCADE,
  FOREIGN KEY (jadwal_id) REFERENCES jadwal_tes(id) ON DELETE SET NULL,
  FOREIGN KEY (input_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO seleksi
  (id, pendaftar_id, jadwal_id, nilai_cbt, nilai_btq, nilai_wawancara, nilai_akhir, hasil, input_by)
VALUES
  ('sel-0101', 'prd-0101', 'tes-01', 94, 92, 90, 94.5, 'LULUS',       'usr-operator'),
  ('sel-0102', 'prd-0102', 'tes-01', 88, 95, 92, 91.8, 'LULUS',       'usr-operator'),
  ('sel-0103', 'prd-0103', 'tes-01', 85, 87, 86, 86.2, 'PROSES',      'usr-operator'),
  ('sel-0104', 'prd-0104', 'tes-01', 90, 88, 87, 88.7, 'LULUS',       'usr-operator'),
  ('sel-0106', 'prd-0106', 'tes-01', 62, 70, 74, 68.4, 'TIDAK_LULUS', 'usr-operator');

-- ---------------------------------------------------------------------
-- 10. penempatan_kelas - penempatan rombel dinamis (FR-05.3 / dashboard)
--      [TAMBAHAN] seluruh tabel
-- ---------------------------------------------------------------------
CREATE TABLE penempatan_kelas (
  id VARCHAR(36) PRIMARY KEY,
  pendaftar_id VARCHAR(36) NOT NULL,
  kelas_id VARCHAR(36) NOT NULL,
  catatan VARCHAR(255),
  ditempatkan_oleh VARCHAR(36),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_penempatan (pendaftar_id),
  FOREIGN KEY (pendaftar_id) REFERENCES pendaftar(id) ON DELETE CASCADE,
  FOREIGN KEY (kelas_id) REFERENCES kelas(id) ON DELETE CASCADE,
  FOREIGN KEY (ditempatkan_oleh) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO penempatan_kelas (id, pendaftar_id, kelas_id, ditempatkan_oleh) VALUES
  ('ptm-0101', 'prd-0101', 'kls-7a', 'usr-operator'),
  ('ptm-0102', 'prd-0102', 'kls-7a', 'usr-operator'),
  ('ptm-0105', 'prd-0105', 'kls-7b', 'usr-operator');

-- Ringkasan impor
SELECT (SELECT COUNT(*) FROM users) AS users,
       (SELECT COUNT(*) FROM pendaftar) AS pendaftar,
       (SELECT COUNT(*) FROM kelas) AS kelas,
       (SELECT COALESCE(SUM(kuota),0) FROM kelas WHERE is_aktif = 1) AS total_kuota,
       (SELECT COUNT(*) FROM pembayaran) AS pembayaran;

-- ---------------------------------------------------------------------
-- 11. login_attempts - rate limit percobaan login
--     [TAMBAHAN] dibutuhkan auth.php (5 percobaan / 15 menit per IP)
-- ---------------------------------------------------------------------
CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  username VARCHAR(100),
  attempted_at DATETIME NOT NULL,
  INDEX idx_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 12. rate_limit - proteksi brute-force generik (registrasi, dsb)
--     [TAMBAHAN] dibutuhkan helper rate_limit_cek() / rate_limit_catat()
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS rate_limit;

CREATE TABLE rate_limit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  aksi VARCHAR(30) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_aksi_time (ip_address, aksi, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
