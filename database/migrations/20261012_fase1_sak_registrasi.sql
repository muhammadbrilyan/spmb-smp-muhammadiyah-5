-- ============================================================
-- 20261012_fase1_sak_registrasi.sql
-- Fase 1: kolom S&K di pengaturan_sekolah, kolom S&K+pay_token+tahap di pendaftar
-- Jalankan sebagai root:
--   mysql -u root < database/migrations/20261012_fase1_sak_registrasi.sql
-- ============================================================

USE db_spmb_mualimku;

-- Kolom S&K di pengaturan_sekolah (teks bisa diedit operator)
ALTER TABLE pengaturan_sekolah
  ADD COLUMN IF NOT EXISTS syarat_ketentuan LONGTEXT NULL,
  ADD COLUMN IF NOT EXISTS syarat_versi INT NOT NULL DEFAULT 1;

-- Kolom bukti persetujuan S&K di pendaftar
ALTER TABLE pendaftar
  ADD COLUMN IF NOT EXISTS setuju_sk_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS setuju_sk_versi INT NULL,
  ADD COLUMN IF NOT EXISTS setuju_sk_ip VARCHAR(45) NULL,
  ADD COLUMN IF NOT EXISTS tahap ENUM(
    'MENUNGGU_PEMBAYARAN',
    'MENUNGGU_VERIFIKASI_BAYAR',
    'BAYAR_TERVERIFIKASI',
    'MELENGKAPI_BERKAS',
    'BERKAS_DISUBMIT',
    'BERKAS_REVISI',
    'BERKAS_TERVERIFIKASI',
    'JADWAL_DITETAPKAN',
    'NILAI_TERINPUT',
    'DIUMUMKAN'
  ) NULL AFTER status_akun;

-- Token pembayaran publik (tautan bayar tanpa login)
ALTER TABLE pendaftar
  ADD COLUMN IF NOT EXISTS pay_token VARCHAR(64) NULL,
  ADD UNIQUE KEY uq_pendaftar_pay_token (pay_token);

-- Kolom metode & catatan di pembayaran (dipakai Fase 2)
ALTER TABLE pembayaran
  ADD COLUMN IF NOT EXISTS metode ENUM('GATEWAY','TRANSFER','QRIS') NULL AFTER payment_method,
  ADD COLUMN IF NOT EXISTS catatan TEXT NULL AFTER keterangan;

-- Backfill tahap dari kolom status lama (hanya untuk data lama)
UPDATE pendaftar
   SET tahap = CASE
     WHEN status_akun = 'AKTIF' THEN 'MELENGKAPI_BERKAS'
     ELSE 'MENUNGGU_PEMBAYARAN'
   END
 WHERE tahap IS NULL;

-- Backfill tahap dari pembayaran terverifikasi
UPDATE pendaftar p
   JOIN pembayaran b ON b.pendaftar_id = p.id AND b.jenis_pembayaran = 'FORMULIR'
 SET p.tahap = 'BAYAR_TERVERIFIKASI'
 WHERE p.tahap = 'MENUNGGU_PEMBAYARAN'
   AND b.status = 'VERIFIED';

-- Teks S&K bawaan (bisa diedit operator setelah ini)
UPDATE pengaturan_sekolah
   SET syarat_ketentuan = 'SYARAT & KETENTUAN PENDAFTARAN\nSMP MUHAMMADIYAH 5 KOTA TANGERANG\n\n1. Pendaftar adalah siswa kelas 6 SD/MI yang akan melanjutkan ke SMP kelas 7 pada tahun ajaran berikutnya.\n\n2. Data yang diisi pada formulir pendaftaran harus benar sesuai dokumen resmi (akta kelahiran, Kartu Keluarga, Ijazah SD/MI).\n\n3. Biaya formulir pendaftaran sebesar yang tertera di halaman ini tidak dapat dikembalikan dalam keadaan apa pun.\n\n4. Pendaftar wajib mengunggah seluruh berkas persyaratan sesuai daftar yang ditentukan panitia.\n\n5. Panitia berhak menolak pendaftar yang memberikan data tidak benar atau berkas tidak lengkap.\n\n6. Jadwal ujian, pengumuman hasil, dan pembagian kelas akan diinformasikan melalui website resmi dan/atau WhatsApp orang tua.\n\n7. Keputusan panitia bersifat final.\n\nDengan menyetujui syarat & ketentuan ini, saya menyatakan bahwa seluruh data yang saya isi adalah benar dan saya memahami seluruh ketentuan pendaftaran di atas.',
       syarat_versi = 1
 WHERE id = 1 AND (syarat_ketentuan IS NULL OR syarat_ketentuan = '');
