-- =====================================================================
-- RESET DATA DUMMY / UJI COBA — SPMB SMP Muhammadiyah 5 Kota Tangerang
--
-- YANG DIHAPUS  : semua pendaftar beserta data turunannya (orang tua,
--                 pembayaran, dokumen, pengajuan koreksi, peserta ujian,
--                 hasil seleksi, penempatan kelas), akun SISWA, jadwal tes,
--                 notifikasi, log WhatsApp, log percobaan login, rate limit,
--                 dan log retensi.
--
-- YANG DIPERTAHANKAN:
--   * akun operator, bendahara, kepsek (tabel users, role staf)
--   * pengaturan_sekolah (profil, rekening, S&K, tanggal PPDB, dsb.)
--   * jenis_dokumen (daftar berkas persyaratan)
--   * kelas / rombel (struktur & kuota)  -> lihat bagian OPSIONAL di bawah
--
-- CARA PAKAI (PILIH SATU):
--   a) phpMyAdmin : pilih database db_spmb_mualimku -> tab "Import" -> pilih
--                   file ini -> Go.
--   b) Terminal   : mysql -u root < database/reset_data_dummy.sql
--
-- !! PENTING: BACKUP DATABASE DULU (phpMyAdmin -> Export). Penghapusan ini
--    TIDAK BISA dibatalkan. Bila di database ada pendaftar ASLI, JANGAN
--    jalankan skrip ini.
--
-- File fisik di storage/dokumen/ TIDAK ikut terhapus lewat SQL — lihat
-- panduan penghapusan manual di akhir berkas ini.
-- =====================================================================

USE db_spmb_mualimku;

-- Hitungan SEBELUM (untuk dibandingkan dengan SESUDAH di akhir).
SELECT 'SEBELUM' AS tahap,
       (SELECT COUNT(*) FROM users WHERE role IN ('operator','bendahara','kepsek')) AS akun_staf,
       (SELECT COUNT(*) FROM users WHERE role = 'siswa')  AS akun_siswa,
       (SELECT COUNT(*) FROM pendaftar)       AS pendaftar,
       (SELECT COUNT(*) FROM dokumen)         AS dokumen,
       (SELECT COUNT(*) FROM pembayaran)      AS pembayaran,
       (SELECT COUNT(*) FROM jadwal_tes)      AS jadwal_tes;

START TRANSACTION;

-- Urutan mengikuti kunci asing: tabel anak dulu, induk belakangan.
DELETE FROM pengajuan_koreksi;
DELETE FROM peserta_ujian;
DELETE FROM seleksi;
DELETE FROM penempatan_kelas;
DELETE FROM dokumen;
DELETE FROM pembayaran;
DELETE FROM orang_tua;
DELETE FROM pendaftar;

DELETE FROM jadwal_tes;

-- Akun siswa saja. Akun operator/bendahara/kepsek TIDAK disentuh.
DELETE FROM users WHERE role = 'siswa';

-- Log & riwayat operasional (bukan data master).
DELETE FROM notifikasi;
DELETE FROM wa_log;
DELETE FROM login_attempts;
DELETE FROM rate_limit;
DELETE FROM retensi_log;

COMMIT;

-- Reset penomoran otomatis tabel log supaya mulai dari 1 lagi.
-- (Nomor pendaftaran REG-TAHUN-NNNN dihitung dari data pendaftar, jadi
--  otomatis kembali ke REG-....-0001 setelah tabel pendaftar kosong.)
ALTER TABLE notifikasi     AUTO_INCREMENT = 1;
ALTER TABLE wa_log         AUTO_INCREMENT = 1;
ALTER TABLE login_attempts AUTO_INCREMENT = 1;
ALTER TABLE rate_limit     AUTO_INCREMENT = 1;
ALTER TABLE retensi_log    AUTO_INCREMENT = 1;

-- ---------------------------------------------------------------------
-- OPSIONAL: hapus juga rombel contoh (7A/7B/7C, wali kelas contoh).
-- Hapus tanda komentar (--) di 1 baris di bawah HANYA bila Anda akan
-- membuat rombel sendiri di menu Manajemen Kelas. Perhatian: kuota total
-- pendaftaran dihitung dari SUM(kuota) rombel aktif, sehingga tanpa rombel
-- kuota = 0.
-- ---------------------------------------------------------------------
-- DELETE FROM kelas;

-- Hitungan SESUDAH.
SELECT 'SESUDAH' AS tahap,
       (SELECT COUNT(*) FROM users WHERE role IN ('operator','bendahara','kepsek')) AS akun_staf,
       (SELECT COUNT(*) FROM users WHERE role = 'siswa')  AS akun_siswa,
       (SELECT COUNT(*) FROM pendaftar)       AS pendaftar,
       (SELECT COUNT(*) FROM dokumen)         AS dokumen,
       (SELECT COUNT(*) FROM pembayaran)      AS pembayaran,
       (SELECT COUNT(*) FROM jadwal_tes)      AS jadwal_tes;

SELECT username, role, nama_lengkap FROM users ORDER BY role, username;

-- =====================================================================
-- PENGHAPUSAN FILE FISIK (manual, setelah SQL di atas berhasil)
--
-- Windows / XAMPP — buka Command Prompt:
--   cd C:\xampp\htdocs\mualimku\storage\dokumen
--   del /q dok-*.* bukti-*.*
--
-- Terminal Linux/Mac:
--   cd /path/ke/mualimku/storage/dokumen && rm -f dok-* bukti-*
--
-- Folder storage/backup/ sengaja TIDAK disentuh (berisi cadangan lama;
-- cadangan itu masih memuat data dummy — hapus sendiri bila perlu).
-- =====================================================================
