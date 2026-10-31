-- ==================================================================
-- SKEMA + SEED DEMO  —  db_spmb_mualimku
--
-- Struktur: hasil mysqldump --no-data dari skema terbaru, disamakan
--           dengan isi database/migrations/* (semua ALTER sudah masuk).
-- Data    : HANYA baris demo fiktif (REG-2025-xxxx) untuk pengembangan.
--
-- JANGAN PERNAH menimpa berkas ini dengan dump database produksi atau
-- dump hasil uji coba: repo ini PUBLIK dan baris pendaftar memuat nama,
-- NISN, alamat, serta token bayar/akun. Cadangan sungguhan disimpan di
-- database/backup/ (ter-ignore git).
--
-- Untuk database yang sudah ada, cukup jalankan berkas di
-- database/migrations/ secara berurutan — tidak perlu import berkas ini.
-- ==================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `dokumen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dokumen` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `jenis_dokumen` varchar(30) NOT NULL DEFAULT 'LAINNYA',
  `file_url` text DEFAULT NULL,
  `ukuran_kb` int(11) DEFAULT NULL,
  `status_verifikasi` enum('PENDING','VERIFIED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `catatan_verifikasi` text DEFAULT NULL,
  `verified_by` varchar(36) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dok_jenis` (`jenis_dokumen`),
  KEY `idx_dok_status` (`status_verifikasi`),
  KEY `pendaftar_id` (`pendaftar_id`),
  CONSTRAINT `dokumen_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jadwal_tes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jadwal_tes` (
  `id` varchar(36) NOT NULL,
  `kode` varchar(20) NOT NULL,
  `nama_kegiatan` varchar(120) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `mode` enum('OFFLINE','DARING','VIDEO_CALL') NOT NULL DEFAULT 'OFFLINE',
  `lokasi` varchar(150) DEFAULT NULL,
  `ruangan` varchar(100) DEFAULT NULL,
  `kapasitas` int(11) NOT NULL DEFAULT 0,
  `tautan_video` varchar(255) DEFAULT NULL,
  `peserta_dijadwalkan` int(11) DEFAULT 0,
  `status` enum('DRAFT','TERJADWAL','SELESAI') NOT NULL DEFAULT 'DRAFT',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jenis_dokumen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jenis_dokumen` (
  `kode` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `wajib` tinyint(1) NOT NULL DEFAULT 1,
  `format_diizinkan` varchar(100) NOT NULL DEFAULT 'jpg,jpeg,png,pdf',
  `maks_kb` int(11) NOT NULL DEFAULT 2048,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kelas` (
  `id` varchar(36) NOT NULL,
  `kode` varchar(10) NOT NULL,
  `nama_kelas` varchar(100) NOT NULL,
  `wali_kelas` varchar(100) DEFAULT NULL,
  `kuota` int(11) NOT NULL DEFAULT 30,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `attempted_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifikasi` (
  `id` varchar(36) NOT NULL,
  `tipe` varchar(40) NOT NULL,
  `pendaftar_id` varchar(36) DEFAULT NULL,
  `pesan` text NOT NULL,
  `dibaca_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notif_dibaca` (`dibaca_at`),
  KEY `idx_notif_tipe` (`tipe`),
  KEY `idx_notif_pendaftar` (`pendaftar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifikasi_siswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifikasi_siswa` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `tipe` varchar(40) NOT NULL,
  `judul` varchar(160) NOT NULL,
  `pesan` text NOT NULL,
  `tautan` varchar(255) DEFAULT NULL,
  `ref_id` varchar(36) DEFAULT NULL,
  `dibaca_at` datetime DEFAULT NULL,
  `wa_status` varchar(20) DEFAULT NULL,
  `wa_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ns_pendaftar` (`pendaftar_id`,`dibaca_at`),
  KEY `idx_ns_ref` (`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `orang_tua`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orang_tua` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `nama_orang_tua` varchar(100) NOT NULL,
  `no_whatsapp` varchar(20) NOT NULL,
  `alamat` text DEFAULT NULL,
  `pekerjaan` varchar(100) DEFAULT NULL,
  `penghasilan_bulanan` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pendaftar_id` (`pendaftar_id`),
  CONSTRAINT `orang_tua_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pembayaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pembayaran` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `jenis_pembayaran` varchar(20) NOT NULL,
  `angsuran_ke` tinyint(3) unsigned DEFAULT NULL,
  `nominal` decimal(12,2) NOT NULL,
  `bukti_transfer_url` text DEFAULT NULL,
  `status` enum('PENDING','VERIFIED','REJECTED','LUNAS','ANGSURAN_1','ANGSURAN_2') NOT NULL DEFAULT 'PENDING',
  `keterangan` varchar(255) DEFAULT NULL,
  `order_id` varchar(64) DEFAULT NULL,
  `midtrans_status` varchar(20) DEFAULT NULL,
  `idempotency_key` varchar(64) DEFAULT NULL,
  `snap_token` text DEFAULT NULL,
  `snap_expires_at` datetime DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `metode` enum('GATEWAY','TRANSFER','QRIS') DEFAULT NULL,
  `catatan` text DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `verified_by` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pembayaran_order` (`order_id`),
  UNIQUE KEY `uq_pembayaran_idem` (`idempotency_key`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_byr_angsuran` (`pendaftar_id`,`jenis_pembayaran`,`angsuran_ke`),
  CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembayaran_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pendaftar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pendaftar` (
  `id` varchar(36) NOT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `no_pendaftaran` varchar(20) NOT NULL,
  `nama_siswa` varchar(100) NOT NULL,
  `nisn` varchar(20) DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `tempat_lahir` varchar(80) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `asal_sekolah` varchar(100) NOT NULL,
  `jalur` varchar(30) DEFAULT 'Reguler',
  `is_luar_daerah` tinyint(1) DEFAULT 0,
  `kelas_id` varchar(36) DEFAULT NULL,
  `status_verifikasi` enum('DRAFT','MENUNGGU_VERIFIKASI','TERVERIFIKASI','DITOLAK') NOT NULL DEFAULT 'DRAFT',
  `status_seleksi` enum('PROSES','LULUS','TIDAK_LULUS') DEFAULT 'PROSES',
  `status_akun` enum('BELUM_DIBUAT','AKTIF','GAGAL') DEFAULT 'BELUM_DIBUAT',
  `tahap` enum('MENUNGGU_PEMBAYARAN','MENUNGGU_VERIFIKASI_BAYAR','BAYAR_TERVERIFIKASI','AKUN_TERKIRIM','MELENGKAPI_BERKAS','BERKAS_DISUBMIT','BERKAS_REVISI','BERKAS_TERVERIFIKASI','JADWAL_DITETAPKAN','NILAI_TERINPUT','DIUMUMKAN') DEFAULT 'MENUNGGU_PEMBAYARAN',
  `catatan_verifikasi_data` text DEFAULT NULL,
  `data_verified_at` datetime DEFAULT NULL,
  `data_verified_by` varchar(36) DEFAULT NULL,
  `arsip_at` datetime DEFAULT NULL,
  `arsip_alasan` varchar(255) DEFAULT NULL,
  `arsip_oleh` varchar(36) DEFAULT NULL,
  `kred_sent_at` datetime DEFAULT NULL,
  `setuju_sk_at` datetime DEFAULT NULL,
  `setuju_sk_versi` int(11) DEFAULT NULL,
  `setuju_sk_ip` varchar(45) DEFAULT NULL,
  `pay_token` varchar(64) DEFAULT NULL,
  `pay_token_hash` char(64) DEFAULT NULL,
  `registration_token` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_pendaftaran` (`no_pendaftaran`),
  UNIQUE KEY `uq_pendaftar_regtoken` (`registration_token`),
  UNIQUE KEY `uq_pendaftar_pay_token` (`pay_token`),
  UNIQUE KEY `uq_pendaftar_pay_token_hash` (`pay_token_hash`),
  KEY `user_id` (`user_id`),
  KEY `kelas_id` (`kelas_id`),
  KEY `idx_pendaftar_verifikasi_data` (`status_verifikasi`,`updated_at`),
  KEY `fk_pendaftar_data_verifier` (`data_verified_by`),
  KEY `idx_pendaftar_arsip` (`arsip_at`),
  KEY `fk_pendaftar_arsip_oleh` (`arsip_oleh`),
  CONSTRAINT `fk_pendaftar_arsip_oleh` FOREIGN KEY (`arsip_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pendaftar_data_verifier` FOREIGN KEY (`data_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pendaftar_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pendaftar_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `penempatan_kelas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `penempatan_kelas` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `kelas_id` varchar(36) NOT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `ditempatkan_oleh` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_penempatan` (`pendaftar_id`),
  KEY `kelas_id` (`kelas_id`),
  KEY `ditempatkan_oleh` (`ditempatkan_oleh`),
  CONSTRAINT `penempatan_kelas_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penempatan_kelas_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `penempatan_kelas_ibfk_3` FOREIGN KEY (`ditempatkan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengajuan_koreksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengajuan_koreksi` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `user_id` varchar(36) DEFAULT NULL,
  `bidang` enum('IDENTITAS','ORANG_TUA','ALAMAT_KONTAK','DOKUMEN','AKADEMIK','LAINNYA') NOT NULL DEFAULT 'LAINNYA',
  `pesan` text NOT NULL,
  `status` enum('MENUNGGU','DIPROSES','DISETUJUI','DITOLAK') NOT NULL DEFAULT 'MENUNGGU',
  `catatan_operator` text DEFAULT NULL,
  `reviewed_by` varchar(36) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_koreksi_pendaftar` (`pendaftar_id`),
  KEY `idx_koreksi_status` (`status`),
  KEY `fk_koreksi_user` (`user_id`),
  KEY `fk_koreksi_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_koreksi_pendaftar` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_koreksi_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_koreksi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengaturan_sekolah`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengaturan_sekolah` (
  `id` int(11) NOT NULL DEFAULT 1,
  `nama_sekolah` varchar(150) NOT NULL DEFAULT 'SMP Muhammadiyah 5 Kota Tangerang',
  `alamat_sekolah` text DEFAULT NULL,
  `logo_url` text DEFAULT NULL,
  `hero_banner_url` text DEFAULT NULL,
  `login_banner_url` text DEFAULT NULL,
  `favicon_url` text DEFAULT NULL,
  `warna_utama` varchar(20) DEFAULT '#0056b3',
  `biaya_formulir` decimal(12,2) DEFAULT 200000.00,
  `biaya_spp` decimal(12,2) DEFAULT 0.00,
  `estimasi_uang_masuk` decimal(12,2) DEFAULT 0.00,
  `angsuran_1_nominal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `angsuran_2_nominal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `angsuran_1_jatuh_tempo` date DEFAULT NULL,
  `angsuran_2_jatuh_tempo` date DEFAULT NULL,
  `label_uang_pangkal` varchar(100) DEFAULT 'Uang Pangkal Gedung',
  `label_spp` varchar(100) DEFAULT 'SPP Bulanan',
  `teks_beasiswa` varchar(300) DEFAULT NULL,
  `nama_bank` varchar(50) DEFAULT 'Bank Syariah Indonesia (BSI)',
  `no_rekening` varchar(50) DEFAULT '1234567890',
  `atas_nama_rekening` varchar(100) DEFAULT 'SMP Muhammadiyah 5',
  `kontak_wa_panitia` varchar(20) DEFAULT '628123456789',
  `email_sekolah` varchar(100) DEFAULT NULL,
  `ticker_header` text DEFAULT NULL,
  `profil_singkat` text DEFAULT NULL,
  `deskripsi_sekolah` text DEFAULT NULL,
  `tagline` varchar(255) DEFAULT 'Berakhlak & Berprestasi',
  `tahun_ajaran` varchar(20) DEFAULT '2027/2028',
  `gelombang` varchar(50) DEFAULT 'Gelombang 1',
  `tanggal_buka_ppdb` date DEFAULT NULL,
  `tanggal_tutup_ppdb` date DEFAULT NULL,
  `tanggal_pengumuman` date DEFAULT NULL,
  `deskripsi_gelombang` text DEFAULT NULL,
  `jalur_aktif` text DEFAULT NULL,
  `fonnte_token` varchar(255) DEFAULT NULL,
  `midtrans_server_key` varchar(255) DEFAULT NULL,
  `midtrans_client_key` varchar(255) DEFAULT NULL,
  `midtrans_sandbox` tinyint(1) DEFAULT 1,
  `syarat_ketentuan` longtext DEFAULT NULL,
  `syarat_versi` int(11) NOT NULL DEFAULT 1,
  `bayar_gateway_aktif` tinyint(1) NOT NULL DEFAULT 0,
  `bayar_transfer_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `bayar_qris_aktif` tinyint(1) NOT NULL DEFAULT 0,
  `qris_image_url` text DEFAULT NULL,
  `qris_merchant` varchar(100) DEFAULT NULL,
  `kirim_akun_otomatis` tinyint(1) NOT NULL DEFAULT 0,
  `notif_wa_operator` varchar(255) DEFAULT NULL,
  `notif_wa_aktif` tinyint(1) NOT NULL DEFAULT 0,
  `notif_email` varchar(255) DEFAULT NULL,
  `hasil_diumumkan_pada` datetime DEFAULT NULL,
  `hasil_disetujui_oleh` varchar(36) DEFAULT NULL,
  `hasil_disetujui_at` datetime DEFAULT NULL,
  `hasil_disetujui_sidik` varchar(64) DEFAULT NULL,
  `hasil_ttd_oleh` varchar(36) DEFAULT NULL,
  `hasil_ttd_at` datetime DEFAULT NULL,
  `hasil_ttd_sidik` varchar(64) DEFAULT NULL,
  `retensi_aktif` tinyint(1) NOT NULL DEFAULT 0,
  `retensi_bulan_tidak_lulus` smallint(5) unsigned DEFAULT NULL,
  `retensi_bulan_batal` smallint(5) unsigned DEFAULT NULL,
  `retensi_bulan_lulus_arsip` smallint(5) unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pengaturan_hasil_disetujui` (`hasil_disetujui_oleh`),
  KEY `fk_pengaturan_hasil_ttd` (`hasil_ttd_oleh`),
  CONSTRAINT `fk_pengaturan_hasil_disetujui` FOREIGN KEY (`hasil_disetujui_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pengaturan_hasil_ttd` FOREIGN KEY (`hasil_ttd_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengumuman_umum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengumuman_umum` (
  `id` varchar(36) NOT NULL,
  `judul` varchar(160) NOT NULL,
  `isi` text NOT NULL,
  `penting` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('DRAFT','TERBIT') NOT NULL DEFAULT 'DRAFT',
  `terbit_at` datetime DEFAULT NULL,
  `created_by` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `peserta_ujian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `peserta_ujian` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `jadwal_id` varchar(36) NOT NULL,
  `nomor_peserta` varchar(30) NOT NULL,
  `ruangan` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_peserta_pendaftar` (`pendaftar_id`),
  UNIQUE KEY `uq_peserta_nomor` (`nomor_peserta`),
  KEY `idx_peserta_jadwal` (`jadwal_id`),
  CONSTRAINT `peserta_ujian_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  CONSTRAINT `peserta_ujian_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_tes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `rate_limit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `aksi` varchar(30) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_aksi_time` (`ip_address`,`aksi`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retensi_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `retensi_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pendaftar_id` varchar(36) NOT NULL,
  `no_pendaftaran` varchar(30) DEFAULT NULL,
  `alasan` varchar(40) NOT NULL,
  `jumlah_berkas` int(10) unsigned NOT NULL DEFAULT 0,
  `operator_id` varchar(36) DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_retensi_pendaftar` (`pendaftar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seleksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `seleksi` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `jadwal_id` varchar(36) DEFAULT NULL,
  `nilai_cbt` decimal(5,2) DEFAULT NULL,
  `nilai_btq` decimal(5,2) DEFAULT NULL,
  `nilai_wawancara` decimal(5,2) DEFAULT NULL,
  `nilai_akhir` decimal(5,2) DEFAULT NULL,
  `hasil` enum('PROSES','LULUS','TIDAK_LULUS') NOT NULL DEFAULT 'PROSES',
  `catatan` varchar(255) DEFAULT NULL,
  `input_by` varchar(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pendaftar_id` (`pendaftar_id`),
  KEY `jadwal_id` (`jadwal_id`),
  KEY `input_by` (`input_by`),
  CONSTRAINT `seleksi_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  CONSTRAINT `seleksi_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_tes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `seleksi_ibfk_3` FOREIGN KEY (`input_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` varchar(36) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('siswa','bendahara','operator','kepsek') NOT NULL DEFAULT 'siswa',
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `wajib_ganti_password` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `wa_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wa_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tujuan` varchar(30) NOT NULL,
  `tipe` varchar(40) NOT NULL,
  `status` varchar(20) NOT NULL,
  `respons` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_walog_tipe` (`tipe`),
  KEY `idx_walog_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



-- ---------- DATA DEMO (fiktif) ----------
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
   'Biaya Pendaftaran',
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
   '2027/2028',
   'Gelombang 1',
   '2026-10-01',
   '2026-11-30',
   '2026-12-05',
   'Pendaftaran daring dibuka 1 Oktober - 30 November. Seleksi meliputi CBT, wawancara BTQ, dan wawancara orang tua.',
   '["Reguler","Tahfidz","Prestasi","Afirmasi"]',
   'SYARAT & KETENTUAN PENDAFTARAN\nSMP MUHAMMADIYAH 5 KOTA TANGERANG\n\n1. Pendaftar adalah siswa kelas 6 SD/MI yang akan melanjutkan ke SMP kelas 7 pada tahun ajaran berikutnya.\n\n2. Data yang diisi pada formulir pendaftaran harus benar sesuai dokumen resmi (akta kelahiran, Kartu Keluarga, Ijazah SD/MI).\n\n3. Biaya formulir pendaftaran sebesar yang tertera di halaman ini tidak dapat dikembalikan dalam keadaan apa pun.\n\n4. Pendaftar wajib mengunggah seluruh berkas persyaratan sesuai daftar yang ditentukan panitia.\n\n5. Panitia berhak menolak pendaftar yang memberikan data tidak benar atau berkas tidak lengkap.\n\n6. Jadwal ujian, pengumuman hasil, dan pembagian kelas akan diinformasikan melalui website resmi dan/atau WhatsApp orang tua.\n\n7. Keputusan panitia bersifat final.\n\n8. PRIVASI & RETENSI DATA. Data pribadi siswa dan orang tua (nama, NISN, alamat, nomor WhatsApp, serta berkas Kartu Keluarga/akta/bukti pembayaran) hanya digunakan untuk keperluan seleksi dan administrasi pendaftaran. Berkas pribadi disimpan terbatas dan dapat dihapus melalui kebijakan retensi yang ditetapkan sekolah; catatan pendaftaran ringkas tetap diarsipkan untuk keperluan akademik. Sekolah tidak membagikan data kepada pihak ketiga tanpa dasar hukum atau persetujuan orang tua/wali.\n\nDengan menyetujui syarat & ketentuan ini, saya menyatakan bahwa seluruh data yang saya isi adalah benar dan saya memahami seluruh ketentuan pendaftaran di atas.',
   1,
   0,
   1,
   0,
   NULL,
   0,
   NULL)
ON DUPLICATE KEY UPDATE id = 1;

INSERT INTO users (id, username, password_hash, role, nama_lengkap, jabatan, wajib_ganti_password) VALUES
  ('usr-operator', 'operator', '!LOCKED', 'operator', 'Ust. M. Rizky Pratama, S.Pd.', 'Operator SPMB & SIMS', 1),
  ('usr-bendahara', 'bendahara', '!LOCKED', 'bendahara', 'Hj. Siti Rohmah, S.E.', 'Bendahara Sekolah', 1),
  ('usr-kepsek', 'kepsek', '!LOCKED', 'kepsek', 'Drs. H. Ahmad Sudrajat, M.Pd', 'Kepala Sekolah', 1);

INSERT INTO kelas (id, kode, nama_kelas, wali_kelas, kuota) VALUES
  ('kls-7a', '7A', 'Kelas VII-A (Tahfidz & Sains Unggulan)', 'Ustadz Fahru Rozi, S.Pd.I', 30),
  ('kls-7b', '7B', 'Kelas VII-B (Bilingual & IT Digital)',   'Siti Rahmawati, M.Pd',       30),
  ('kls-7c', '7C', 'Kelas VII-C (Reguler Inovatif)',          'Drs. Bambang Irawan',        30);

INSERT INTO pendaftar
  (id, user_id, no_pendaftaran, nama_siswa, nisn, jenis_kelamin, alamat, asal_sekolah,
   jalur, is_luar_daerah, kelas_id, status_verifikasi, status_seleksi, status_akun, tahap,
   kred_sent_at, created_at)
VALUES
  ('prd-0102', NULL,                'REG-2025-0102', 'Muhammad Farhan Al-Ghidari','0118492041', 'L', 'Jl. KH. Hasyim Ashari No. 4',        'MI Negeri 1 Tangerang',        'Reguler',  0, 'kls-7a', 'TERVERIFIKASI',    'PROSES',    'AKTIF',      'AKUN_TERKIRIM',    '2025-10-08 08:35:00', '2025-10-06 09:40:00'),
  ('prd-0103', NULL,                'REG-2025-0103', 'Zahra Annisa Khairunnisa',  '0123910245', 'P', 'Perum Bumi Indah Blok C2',           'SDIT As-Syifa Karawaci',       'Prestasi', 0, NULL,     'MENUNGGU_VERIFIKASI', 'PROSES', 'BELUM_DIBUAT', 'MENUNGGU_PEMBAYARAN', NULL,                   '2025-10-07 10:05:00'),
  ('prd-0104', NULL,                'REG-2025-0104', 'Bilal Dwi Kurniawan',       '0117392812', 'L', 'Jl. Cikokol Raya No. 88',            'SD Negeri Cikokol 3',           'Reguler',  0, NULL,     'MENUNGGU_VERIFIKASI', 'PROSES', 'BELUM_DIBUAT', 'MENUNGGU_PEMBAYARAN', NULL,                   '2025-10-07 13:22:00'),
  ('prd-0105', NULL,                'REG-2025-0105', 'Nadira Putri Az-Zahra',     '0128841029', 'P', 'Perum Poris Pelawad Blok A5',        'SDN Poris Pelawad 1',           'Reguler',  0, 'kls-7b', 'TERVERIFIKASI',    'PROSES',    'AKTIF',      'AKUN_TERKIRIM',    '2025-10-08 09:12:00', '2025-10-07 15:48:00'),
  ('prd-0106', NULL,                'REG-2025-0106', 'Rizky Maulana Alamsyah',    '0115598101', 'L', 'Jl. Batusari No. 21',                'SD Muhammadiyah 2 Batusari',    'Afirmasi', 1, NULL,     'DITOLAK',          'PROSES',    'GAGAL',      'MENUNGGU_PEMBAYARAN', '2025-10-08 09:20:00', '2025-10-08 08:05:00');

INSERT INTO orang_tua
  (id, pendaftar_id, nama_orang_tua, no_whatsapp, alamat, pekerjaan, penghasilan_bulanan)
VALUES
  ('ort-0102', 'prd-0102', 'Siti Aminah',           '+6281388214099', 'Jl. KH. Hasyim Ashari No. 4', 'Guru',            'Rp 3.000.000 - 5.000.000'),
  ('ort-0103', 'prd-0103', 'Rahmat Hidayat',        '+6285719402391', 'Perum Bumi Indah Blok C2',    'Wiraswasta',      'Rp 7.500.000 - 10.000.000'),
  ('ort-0104', 'prd-0104', 'Dewi Lestari',          '+6282144789012', 'Jl. Cikokol Raya No. 88',     'Pegawai Negeri',  'Rp 5.000.000 - 7.500.000'),
  ('ort-0105', 'prd-0105', 'Nur Hidayatullah',      '+6285677123380', 'Perum Poris Pelawad Blok A5', 'Petani',          'Rp 2.000.000 - 3.000.000'),
  ('ort-0106', 'prd-0106', 'Muhammad Yusuf',        '+6282388214477', 'Jl. Batusari No. 21',         'Pedagang',        'Rp 3.000.000 - 5.000.000');

INSERT INTO pembayaran
  (id, pendaftar_id, jenis_pembayaran, nominal, status, keterangan, verified_at, verified_by, created_at)
VALUES
  ('byr-0102', 'prd-0102', 'FORMULIR', 200000.00, 'VERIFIED', 'Transfer BSI', '2025-10-08 09:10:00', 'usr-bendahara', '2025-10-06 09:52:00'),
  ('byr-0103', 'prd-0103', 'FORMULIR', 200000.00, 'PENDING',  'Menunggu unggah bukti', NULL, NULL, '2025-10-07 10:20:00'),
  ('byr-0104', 'prd-0104', 'FORMULIR', 200000.00, 'PENDING',  'Menunggu unggah bukti', NULL, NULL, '2025-10-07 13:35:00'),
  ('byr-0105', 'prd-0105', 'FORMULIR', 200000.00, 'VERIFIED', 'Transfer BSI', '2025-10-08 09:30:00', 'usr-bendahara', '2025-10-07 16:02:00'),
  ('byr-0106', 'prd-0106', 'FORMULIR', 200000.00, 'REJECTED', 'Bukti tidak terbaca, minta ulang', '2025-10-08 09:45:00', 'usr-bendahara', '2025-10-08 08:20:00');

INSERT INTO jenis_dokumen (kode, nama, wajib, format_diizinkan, maks_kb, urutan, aktif) VALUES
  ('AKTA',                   'Akta Kelahiran',                1, 'jpg,jpeg,png,pdf', 2048, 10, 1),
  ('KARTU_KELUARGA',         'Kartu Keluarga',                1, 'jpg,jpeg,png,pdf', 2048, 20, 1),
  ('IJAZAH',                 'Ijazah SD / Surat Keterangan Lulus', 1, 'jpg,jpeg,png,pdf', 2048, 30, 1),
  ('PAS_FOTO',               'Pas Foto 3x4',                  1, 'jpg,jpeg,png',     1024, 40, 1),
  ('SURAT_KETERANGAN_SISWA', 'Surat Keterangan Siswa',        0, 'jpg,jpeg,png,pdf', 2048, 50, 1),
  ('LAINNYA',                'Berkas Lainnya',                0, 'jpg,jpeg,png,pdf', 2048, 60, 1);

INSERT INTO jadwal_tes
  (id, kode, nama_kegiatan, tanggal, jam_mulai, jam_selesai, mode, lokasi, ruangan, kapasitas, peserta_dijadwalkan, status)
VALUES
  ('tes-01', 'TES-01', 'Ujian CBT & Wawancara BTQ',    '2025-10-18', '08:00:00', '11:00:00', 'OFFLINE',    'Lab Komputer & Aula', 'Lab Komputer 1-2', 80, 142, 'TERJADWAL'),
  ('tes-02', 'TES-02', 'Wawancara Orang Tua / Wali',   '2025-10-19', '09:00:00', '14:00:00', 'OFFLINE',    'Ruang Rapat', 'Ruang Rapat', 40, 96, 'TERJADWAL'),
  ('tes-03', 'TES-03', 'Seleksi Prestasi & Tahfidz',   '2025-10-20', '13:00:00', '16:00:00', 'VIDEO_CALL', NULL, NULL, 0, 42, 'DRAFT'),
  ('tes-04', 'TES-04', 'Tes Kesehatan & Psikotes',     '2025-10-25', '08:00:00', '12:00:00', 'OFFLINE',    'UKS & Aula', 'UKS & Aula', 100, 184, 'DRAFT');

INSERT INTO seleksi
  (id, pendaftar_id, jadwal_id, nilai_cbt, nilai_btq, nilai_wawancara, nilai_akhir, hasil, input_by)
VALUES
  ('sel-0102', 'prd-0102', 'tes-01', 88, 95, 92, 91.8, 'LULUS',       'usr-operator'),
  ('sel-0103', 'prd-0103', 'tes-01', 85, 87, 86, 86.2, 'PROSES',      'usr-operator'),
  ('sel-0104', 'prd-0104', 'tes-01', 90, 88, 87, 88.7, 'LULUS',       'usr-operator'),
  ('sel-0106', 'prd-0106', 'tes-01', 62, 70, 74, 68.4, 'TIDAK_LULUS', 'usr-operator');

INSERT INTO penempatan_kelas (id, pendaftar_id, kelas_id, ditempatkan_oleh) VALUES
  ('ptm-0102', 'prd-0102', 'kls-7a', 'usr-operator'),
  ('ptm-0105', 'prd-0105', 'kls-7b', 'usr-operator');

SET FOREIGN_KEY_CHECKS = 1;
