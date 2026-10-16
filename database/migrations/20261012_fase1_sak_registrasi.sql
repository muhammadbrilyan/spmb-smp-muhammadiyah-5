-- ============================================================
-- 20261012_fase1_sak_registrasi.sql
-- Fase 1: kolom S&K di pengaturan_sekolah, kolom S&K+pay_token+tahap di pendaftar
-- Jalankan sebagai root / lewat phpMyAdmin Import (tanpa DELIMITER):
--   mysql -u root < database/migrations/20261012_fase1_sak_registrasi.sql
-- Idempoten & PORTABEL (MySQL 5.7/8 & MariaDB 10.x): memakai
-- information_schema + PREPARE/EXECUTE (bukan ADD COLUMN IF NOT EXISTS).
-- ============================================================

USE db_spmb_mualimku;

-- Kolom S&K di pengaturan_sekolah (teks bisa diedit operator)
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'syarat_ketentuan') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN syarat_ketentuan LONGTEXT NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pengaturan_sekolah'
               AND COLUMN_NAME = 'syarat_versi') = 0,
            'ALTER TABLE pengaturan_sekolah ADD COLUMN syarat_versi INT NOT NULL DEFAULT 1',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kolom bukti persetujuan S&K di pendaftar
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
               AND COLUMN_NAME = 'setuju_sk_at') = 0,
            'ALTER TABLE pendaftar ADD COLUMN setuju_sk_at DATETIME NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
               AND COLUMN_NAME = 'setuju_sk_versi') = 0,
            'ALTER TABLE pendaftar ADD COLUMN setuju_sk_versi INT NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
               AND COLUMN_NAME = 'setuju_sk_ip') = 0,
            'ALTER TABLE pendaftar ADD COLUMN setuju_sk_ip VARCHAR(45) NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
               AND COLUMN_NAME = 'tahap') = 0,
            'ALTER TABLE pendaftar ADD COLUMN tahap ENUM(''MENUNGGU_PEMBAYARAN'',''MENUNGGU_VERIFIKASI_BAYAR'',''BAYAR_TERVERIFIKASI'',''MELENGKAPI_BERKAS'',''BERKAS_DISUBMIT'',''BERKAS_REVISI'',''BERKAS_TERVERIFIKASI'',''JADWAL_DITETAPKAN'',''NILAI_TERINPUT'',''DIUMUMKAN'') NULL AFTER status_akun',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Token pembayaran publik (tautan bayar tanpa login)
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
               AND COLUMN_NAME = 'pay_token') = 0,
            'ALTER TABLE pendaftar ADD COLUMN pay_token VARCHAR(64) NULL',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pendaftar'
               AND INDEX_NAME = 'uq_pendaftar_pay_token') = 0,
            'ALTER TABLE pendaftar ADD UNIQUE KEY uq_pendaftar_pay_token (pay_token)',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- Kolom metode & catatan di pembayaran (dipakai Fase 2)
SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran'
               AND COLUMN_NAME = 'metode') = 0,
            'ALTER TABLE pembayaran ADD COLUMN metode ENUM(''GATEWAY'',''TRANSFER'',''QRIS'') NULL AFTER payment_method',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

SET @s = IF((SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pembayaran'
               AND COLUMN_NAME = 'catatan') = 0,
            'ALTER TABLE pembayaran ADD COLUMN catatan TEXT NULL AFTER keterangan',
            'DO 0');
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

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
