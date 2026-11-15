-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 08 Nov 2026 pada 02.55
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_spmb_mualimku`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `dokumen`
--

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
  `uploaded_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `dokumen`
--

INSERT INTO `dokumen` (`id`, `pendaftar_id`, `jenis_dokumen`, `file_url`, `ukuran_kb`, `status_verifikasi`, `catatan_verifikasi`, `verified_by`, `verified_at`, `uploaded_at`) VALUES
('dok-0b32c4e5', 'prd-54787835', 'PAS_FOTO', 'storage/dokumen/dok-20261106-080057-a57fd47e.png', 10, 'VERIFIED', NULL, 'usr-operator', '2026-11-06 14:02:09', '2026-11-06 14:00:57'),
('dok-a30909e9', 'prd-f8bab97d', 'KARTU_KELUARGA', 'storage/dokumen/dok-20261105-234512-ff778895.png', 10, 'VERIFIED', NULL, 'usr-operator', '2026-11-06 06:06:54', '2026-11-06 05:45:12'),
('dok-a6ed5949', 'prd-54787835', 'IJAZAH', 'storage/dokumen/dok-20261106-080057-16903789.png', 175, 'VERIFIED', NULL, 'usr-operator', '2026-11-06 14:01:57', '2026-11-06 14:00:57'),
('dok-aa99b575', 'prd-54787835', 'AKTA', 'storage/dokumen/dok-20261106-080057-4beed451.png', 243, 'VERIFIED', NULL, 'usr-operator', '2026-11-06 14:01:48', '2026-11-06 14:00:57'),
('dok-ba9000fa', 'prd-f8bab97d', 'IJAZAH', 'storage/dokumen/dok-20261105-234454-117abd4f.png', 35, 'VERIFIED', NULL, 'usr-operator', '2026-11-06 06:06:52', '2026-11-06 05:44:54'),
('dok-f2cd60e3', 'prd-54787835', 'KARTU_KELUARGA', 'storage/dokumen/dok-20261106-080057-610d81b3.png', 243, 'VERIFIED', NULL, 'usr-operator', '2026-11-06 14:02:04', '2026-11-06 14:00:57'),
('dok-fc956a74', 'prd-f8bab97d', 'PAS_FOTO', 'storage/dokumen/dok-20261105-234512-5ddc78db.png', 243, 'VERIFIED', NULL, 'usr-operator', '2026-11-06 06:06:57', '2026-11-06 05:45:12'),
('dok-fd68febc', 'prd-f8bab97d', 'AKTA', 'storage/dokumen/dok-20261105-235147-339b74ad.png', 251, 'VERIFIED', NULL, 'usr-operator', '2026-11-06 06:06:49', '2026-11-06 05:51:47');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_tes`
--

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
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `jadwal_tes`
--

INSERT INTO `jadwal_tes` (`id`, `kode`, `nama_kegiatan`, `tanggal`, `jam_mulai`, `jam_selesai`, `mode`, `lokasi`, `ruangan`, `kapasitas`, `tautan_video`, `peserta_dijadwalkan`, `status`, `created_at`) VALUES
('tes-66ff32c3', 'TES-01', 'Test Baca & Tulis', '2026-11-30', '08:00:00', '12:00:00', 'OFFLINE', NULL, 'Ruang Kelas', 0, NULL, 2, 'TERJADWAL', '2026-11-06 14:02:56'),
('tes-e2096ef2', 'TES-02', 'Tes BTQ', '2026-11-30', '08:00:00', '12:00:00', 'OFFLINE', NULL, 'Masjid', 0, NULL, 0, 'TERJADWAL', '2026-11-06 14:03:15');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jenis_dokumen`
--

CREATE TABLE `jenis_dokumen` (
  `kode` varchar(30) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `wajib` tinyint(1) NOT NULL DEFAULT 1,
  `format_diizinkan` varchar(100) NOT NULL DEFAULT 'jpg,jpeg,png,pdf',
  `maks_kb` int(11) NOT NULL DEFAULT 2048,
  `urutan` int(11) NOT NULL DEFAULT 0,
  `aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `jenis_dokumen`
--

INSERT INTO `jenis_dokumen` (`kode`, `nama`, `wajib`, `format_diizinkan`, `maks_kb`, `urutan`, `aktif`) VALUES
('AKTA', 'Akta Kelahiran', 1, 'jpg,jpeg,png,pdf', 2048, 10, 1),
('IJAZAH', 'Ijazah SD / Surat Keterangan Lulus', 1, 'jpg,jpeg,png,pdf', 2048, 30, 1),
('KARTU_KELUARGA', 'Kartu Keluarga', 1, 'jpg,jpeg,png,pdf', 2048, 20, 1),
('LAINNYA', 'Berkas Lainnya', 0, 'jpg,jpeg,png,pdf', 2048, 60, 0),
('PAS_FOTO', 'Pas Foto 3x4', 1, 'jpg,jpeg,png', 1024, 40, 1),
('SURAT_KETERANGAN_SISWA', 'Surat Keterangan Siswa', 0, 'jpg,jpeg,png,pdf', 2048, 50, 0);

-- --------------------------------------------------------

--
-- Struktur dari tabel `kelas`
--

CREATE TABLE `kelas` (
  `id` varchar(36) NOT NULL,
  `kode` varchar(10) NOT NULL,
  `nama_kelas` varchar(100) NOT NULL,
  `wali_kelas` varchar(100) DEFAULT NULL,
  `kuota` int(11) NOT NULL DEFAULT 30,
  `is_aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `kelas`
--

INSERT INTO `kelas` (`id`, `kode`, `nama_kelas`, `wali_kelas`, `kuota`, `is_aktif`, `created_at`) VALUES
('kls-698a7341', '72', 'AL-FARRABI', 'Mr. Zodi', 30, 1, '2026-11-05 20:59:16'),
('kls-6a02a2db', '73', 'Ibnu Sina', 'Mr. Adam', 30, 1, '2026-11-05 20:59:39'),
('kls-f8d795b6', '71', 'AL-BATTANI', 'Mrs. Dini', 30, 1, '2026-11-05 20:58:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `attempted_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` varchar(36) NOT NULL,
  `tipe` varchar(40) NOT NULL,
  `pendaftar_id` varchar(36) DEFAULT NULL,
  `pesan` text NOT NULL,
  `dibaca_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id`, `tipe`, `pendaftar_id`, `pesan`, `dibaca_at`, `created_at`) VALUES
('ntf-3f3f90ed', 'BUKTI_BAYAR_MASUK', 'prd-f8bab97d', 'Bukti pembayaran Biaya Pendaftaran diunggah via portal siswa.\nNama : Muhammad Brilyan\nNo. Pendaftaran : REG-2026-0001\nNominal : Rp 1.050.000\nPeriksa: http://localhost/mualimku/app/bendahara/pembayaran.php', '2026-11-06 14:12:44', '2026-11-06 10:51:25'),
('ntf-47c2d7fa', 'BUKTI_BAYAR_MASUK', 'prd-54787835', 'Bukti pembayaran baru menunggu verifikasi (via tautan bayar).\nNama : ELVA ROSYIDAH\nNo. Pendaftaran : REG-2026-0002\nMetode : TRANSFER\nNominal : Rp 200.000\nPeriksa: http://localhost/mualimku/app/bendahara/pembayaran.php', '2026-11-06 14:12:44', '2026-11-06 13:52:34'),
('ntf-60107d03', 'BUKTI_BAYAR_MASUK', 'prd-f8bab97d', 'Bukti pembayaran baru menunggu verifikasi (via tautan bayar).\nNama : Muhammad Brilyan\nNo. Pendaftaran : REG-2026-0001\nMetode : TRANSFER\nNominal : Rp 200.000\nPeriksa: http://localhost/mualimku/app/bendahara/pembayaran.php', '2026-11-06 05:16:36', '2026-11-06 05:15:18'),
('ntf-7aa19055', 'VERIFIKASI_DATA', 'prd-f8bab97d', 'Data calon siswa diajukan untuk verifikasi.\nNama : Muhammad Brilyan\nNo. Pendaftaran : REG-2026-0001', '2026-11-06 08:04:57', '2026-11-06 05:51:52'),
('ntf-8db837ab', 'PENDAFTAR_BARU', 'prd-54787835', 'Calon siswa baru mendaftar — biaya formulir belum dibayar.\nNama : ELVA ROSYIDAH\nNo. Pendaftaran : REG-2026-0002\nAsal sekolah : SD MUHAMMADIYAH\nPantau: http://localhost/mualimku/app/operator/pendaftar.php', '2026-11-06 14:12:44', '2026-11-06 13:51:48'),
('ntf-97bf008b', 'VERIFIKASI_DATA', 'prd-54787835', 'Data calon siswa diajukan untuk verifikasi.\nNama : ELVA ROSYIDAH\nNo. Pendaftaran : REG-2026-0002', '2026-11-06 14:12:44', '2026-11-06 14:01:09'),
('ntf-9efd583b', 'VERIFIKASI_DATA', 'prd-f8bab97d', 'Data calon siswa diajukan untuk verifikasi.\nNama : Muhammad Brilyan\nNo. Pendaftaran : REG-2026-0001', '2026-11-06 08:04:57', '2026-11-06 05:45:16'),
('ntf-aa908aa4', 'BUKTI_BAYAR_MASUK', 'prd-f8bab97d', 'Bukti pembayaran baru menunggu verifikasi (via tautan bayar).\nNama : Muhammad Brilyan\nNo. Pendaftaran : REG-2026-0001\nMetode : TRANSFER\nNominal : Rp 200.000\nPeriksa: http://localhost/mualimku/app/bendahara/pembayaran.php', '2026-11-06 04:41:28', '2026-11-06 04:32:43'),
('ntf-aabed70d', 'BUKTI_BAYAR_MASUK', 'prd-54787835', 'Bukti pembayaran baru menunggu verifikasi (via tautan bayar).\nNama : ELVA ROSYIDAH\nNo. Pendaftaran : REG-2026-0002\nMetode : TRANSFER\nNominal : Rp 200.000\nPeriksa: http://localhost/mualimku/app/bendahara/pembayaran.php', '2026-11-06 14:12:44', '2026-11-06 13:57:29'),
('ntf-be61f5f3', 'BUKTI_BAYAR_MASUK', 'prd-54787835', 'Bukti pembayaran Biaya Pendaftaran diunggah via portal siswa.\nNama : ELVA ROSYIDAH\nNo. Pendaftaran : REG-2026-0002\nNominal : Rp 1.050.000\nPeriksa: http://localhost/mualimku/app/bendahara/pembayaran.php', '2026-11-06 14:12:44', '2026-11-06 14:11:20'),
('ntf-beadfee6', 'BERKAS_DISUBMIT', 'prd-54787835', 'Berkas persyaratan dikirim untuk verifikasi.\nNama : ELVA ROSYIDAH\nNo. Pendaftaran : REG-2026-0002\nPeriksa: http://localhost/mualimku/app/operator/verifikasi.php?tab=berkas', '2026-11-06 14:12:44', '2026-11-06 14:01:17'),
('ntf-bf7eed00', 'BERKAS_DISUBMIT', 'prd-f8bab97d', 'Berkas persyaratan dikirim untuk verifikasi.\nNama : Muhammad Brilyan\nNo. Pendaftaran : REG-2026-0001\nPeriksa: http://localhost/mualimku/app/operator/verifikasi.php?tab=berkas', '2026-11-06 08:04:57', '2026-11-06 05:51:52'),
('ntf-c62b1d2e', 'BERKAS_DISUBMIT', 'prd-f8bab97d', 'Berkas persyaratan dikirim untuk verifikasi.\nNama : Muhammad Brilyan\nNo. Pendaftaran : REG-2026-0001\nPeriksa: http://localhost/mualimku/app/operator/verifikasi.php?tab=berkas', '2026-11-06 08:04:57', '2026-11-06 05:45:16'),
('ntf-e276d145', 'BUKTI_BAYAR_MASUK', 'prd-f8bab97d', 'Bukti pembayaran baru menunggu verifikasi (via tautan bayar).\nNama : Muhammad Brilyan\nNo. Pendaftaran : REG-2026-0001\nMetode : TRANSFER\nNominal : Rp 200.000\nPeriksa: http://localhost/mualimku/app/bendahara/pembayaran.php', '2026-11-06 04:41:30', '2026-11-06 03:51:47'),
('ntf-e3e77951', 'BUKTI_BAYAR_MASUK', 'prd-f8bab97d', 'Bukti pembayaran baru menunggu verifikasi (via tautan bayar).\nNama : Muhammad Brilyan\nNo. Pendaftaran : REG-2026-0001\nMetode : TRANSFER\nNominal : Rp 200.000\nPeriksa: http://localhost/mualimku/app/bendahara/pembayaran.php', '2026-11-06 05:18:36', '2026-11-06 05:17:19');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi_siswa`
--

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
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `notifikasi_siswa`
--

INSERT INTO `notifikasi_siswa` (`id`, `pendaftar_id`, `tipe`, `judul`, `pesan`, `tautan`, `ref_id`, `dibaca_at`, `wa_status`, `wa_at`, `created_at`) VALUES
('nss-1119d1744046', 'prd-54787835', 'BAYAR_TERVERIFIKASI', 'Pembayaran lunas', 'Pembayaran Biaya Pendaftaran sebesar Rp 1.050.000 telah diverifikasi dan dinyatakan LUNAS. Terima kasih.', '/mualimku/app/siswa/keuangan.php', NULL, NULL, NULL, NULL, '2026-11-06 14:12:13'),
('nss-16c393a4d1ed', 'prd-f8bab97d', 'JADWAL_UJIAN', 'Jadwal Tes SPMB (3 kegiatan)', 'Assalamu\'alaikum.\n\nJadwal tes seleksi SPMB SMP Muhammadiyah 5 Kota Tangerang:\n\nAnanda: Muhammad Brilyan (REG-2026-0001)\n\n1. Tes BTQ (TES-02)\n   30 November 2026 · 08:00-12:00 WIB\n   Offline di sekolah · Masjid\n\n2. Test Baca & Tulis (TES-01)\n   30 November 2026 · 08:00-12:00 WIB\n   Offline di sekolah · Ruang Kelas\n\n3. Wawancara & Test Kesehatan (TES-03)\n   30 November 2026 · 08:00-12:00 WIB\n   Offline di sekolah · Lab. Komputer\n\nTotal 3 jadwal yang harus diikuti.\n\nMohon hadir 15 menit lebih awal dan membawa kartu peserta (cetak di portal siswa), kartu pendaftaran, serta alat tulis.\n\nSalam,\nPanitia SPMB', '/mualimku/app/siswa/ppdb.php?tab=jadwal', 'jdw-4f86d6fd96c07f7c113c79fa782d', '2026-11-06 06:31:52', 'TERKIRIM', '2026-11-06 06:08:30', '2026-11-06 06:08:29'),
('nss-49b57d5adb5a', 'prd-54787835', 'BAYAR_TERVERIFIKASI', 'Pembayaran terverifikasi', 'Pembayaran Biaya formulir sebesar Rp 200.000 telah diverifikasi panitia. Lanjutkan kelengkapan berkas melalui menu Data Siswa.', '/mualimku/app/siswa/keuangan.php', NULL, NULL, NULL, NULL, '2026-11-06 13:57:59'),
('nss-59cf624dc50f', 'prd-f8bab97d', 'BERKAS_DITOLAK', 'Berkas perlu diperbaiki', 'Panitia menolak 1 berkas. Perbaiki / unggah ulang berkas berikut:\n- Akta Kelahiran: FOTO YANG ANDA KIRIMKAN FOTO IJAZAH BUKAN AKTA KELAHIRAN\n\nBerkas yang sudah lolos tidak perlu diunggah ulang.', '/mualimku/app/siswa/data-siswa.php', NULL, '2026-11-06 05:51:32', NULL, NULL, '2026-11-06 05:50:16'),
('nss-5acb2b867bad', 'prd-54787835', 'BAYAR_DITOLAK', 'Pembayaran ditolak', 'Bukti pembayaran Biaya formulir sebesar Rp 200.000 belum dapat disetujui.\nAlasan: Kirim Ulang Karena Foto Buram\nSilakan unggah ulang bukti pembayaran yang benar melalui menu Keuangan.', '/mualimku/app/siswa/keuangan.php', NULL, NULL, NULL, NULL, '2026-11-06 13:54:27'),
('nss-7834f013890a', 'prd-f8bab97d', 'BERKAS_TERVERIFIKASI', 'Berkas terverifikasi', 'Seluruh berkas persyaratan Anda telah diperiksa dan dinyatakan lengkap. Tahap berikutnya menunggu penjadwalan tes dari panitia.', '/mualimku/app/siswa/data-siswa.php', NULL, '2026-11-06 08:04:17', NULL, NULL, '2026-11-06 06:06:57'),
('nss-7c66c237fa63', 'prd-54787835', 'HASIL_SELEKSI', 'Selamat, Anda dinyatakan LULUS', 'Hasil seleksi SPMB telah resmi diumumkan: Anda dinyatakan LULUS. Ditempatkan di kelas 73 — Ibnu Sina. Silakan lanjutkan proses daftar ulang sesuai jadwal panitia.', '/mualimku/app/siswa/hasil.php', NULL, NULL, NULL, NULL, '2026-11-06 14:08:16'),
('nss-7f76ca0c1d13', 'prd-54787835', 'BERKAS_TERVERIFIKASI', 'Berkas terverifikasi', 'Seluruh berkas persyaratan Anda telah diperiksa dan dinyatakan lengkap. Tahap berikutnya menunggu penjadwalan tes dari panitia.', '/mualimku/app/siswa/data-siswa.php', NULL, NULL, NULL, NULL, '2026-11-06 14:02:09'),
('nss-809ea335a388', 'prd-f8bab97d', 'BAYAR_DITOLAK', 'Pembayaran ditolak', 'Bukti pembayaran Biaya formulir sebesar Rp 200.000 belum dapat disetujui.\nAlasan: FOTO KURANG JELAS\nSilakan unggah ulang bukti pembayaran yang benar melalui menu Keuangan.', '/mualimku/app/siswa/keuangan.php', NULL, '2026-11-06 05:19:58', NULL, NULL, '2026-11-06 05:16:51'),
('nss-8c0fe8850ae8', 'prd-f8bab97d', 'HASIL_SELEKSI', 'Selamat, Anda dinyatakan LULUS', 'Hasil seleksi SPMB telah resmi diumumkan: Anda dinyatakan LULUS. Ditempatkan di kelas 73 — Ibnu Sina. Silakan lanjutkan proses daftar ulang sesuai jadwal panitia.', '/mualimku/app/siswa/hasil.php', NULL, '2026-11-06 08:04:14', NULL, NULL, '2026-11-06 07:05:29'),
('nss-9663eb3a3b96', 'prd-f8bab97d', 'BAYAR_TERVERIFIKASI', 'Pembayaran lunas', 'Pembayaran Biaya Pendaftaran sebesar Rp 1.050.000 telah diverifikasi dan dinyatakan LUNAS. Terima kasih.', '/mualimku/app/siswa/keuangan.php', NULL, NULL, NULL, NULL, '2026-11-06 14:14:39'),
('nss-a1afc5018cb6', 'prd-f8bab97d', 'JADWAL_UJIAN', 'Jadwal Tes SPMB (2 kegiatan)', 'Assalamu\'alaikum.\n\nJadwal tes seleksi SPMB SMP Muhammadiyah 5 Kota Tangerang:\n\nAnanda: Muhammad Brilyan (REG-2026-0001)\n\n1. Test Baca & Tulis (TES-01)\n   30 November 2026 · 08:00-12:00 WIB\n   Offline di sekolah · Ruang Kelas\n\n2. Tes BTQ (TES-02)\n   30 November 2026 · 08:00-12:00 WIB\n   Offline di sekolah · Masjid\n\nTotal 2 jadwal yang harus diikuti.\n\nMohon hadir 15 menit lebih awal dan membawa kartu peserta (cetak di portal siswa), serta alat tulis.\n\nSalam,\nPanitia SPMB', '/mualimku/app/siswa/ppdb.php?tab=jadwal', 'jdw-043b046199770fa044eae90f43d4', NULL, 'TERKIRIM', '2026-11-06 14:04:14', '2026-11-06 14:04:09'),
('nss-b1940bef97e4', 'prd-f8bab97d', 'BAYAR_TERVERIFIKASI', 'Pembayaran terverifikasi', 'Pembayaran Biaya formulir sebesar Rp 200.000 telah diverifikasi panitia. Lanjutkan kelengkapan berkas melalui menu Data Siswa.', '/mualimku/app/siswa/keuangan.php', NULL, '2026-11-06 05:19:58', NULL, NULL, '2026-11-06 05:18:32'),
('nss-baec55966934', 'prd-f8bab97d', 'BAYAR_DITOLAK', 'Pembayaran ditolak', 'Bukti pembayaran Biaya formulir sebesar Rp 200.000 belum dapat disetujui.\nAlasan: NOMINAL SALAH\nSilakan unggah ulang bukti pembayaran yang benar melalui menu Keuangan.', '/mualimku/app/siswa/keuangan.php', NULL, '2026-11-06 05:19:58', NULL, NULL, '2026-11-06 04:33:31'),
('nss-be0e5c3b34bb', 'prd-f8bab97d', 'BAYAR_DITOLAK', 'Pembayaran ditolak', 'Bukti pembayaran Biaya formulir sebesar Rp 200.000 belum dapat disetujui.\nAlasan: tidak jelas\nSilakan unggah ulang bukti pembayaran yang benar melalui menu Keuangan.', '/mualimku/app/siswa/keuangan.php', NULL, '2026-11-06 05:19:58', NULL, NULL, '2026-11-06 04:30:22'),
('nss-d8f1cccff1f3', 'prd-54787835', 'JADWAL_UJIAN', 'Jadwal Tes SPMB (2 kegiatan)', 'Assalamu\'alaikum.\n\nJadwal tes seleksi SPMB SMP Muhammadiyah 5 Kota Tangerang:\n\nAnanda: ELVA ROSYIDAH (REG-2026-0002)\n\n1. Test Baca & Tulis (TES-01)\n   30 November 2026 · 08:00-12:00 WIB\n   Offline di sekolah · Ruang Kelas\n\n2. Tes BTQ (TES-02)\n   30 November 2026 · 08:00-12:00 WIB\n   Offline di sekolah · Masjid\n\nTotal 2 jadwal yang harus diikuti.\n\nMohon hadir 15 menit lebih awal dan membawa kartu peserta (cetak di portal siswa), serta alat tulis.\n\nSalam,\nPanitia SPMB', '/mualimku/app/siswa/ppdb.php?tab=jadwal', 'jdw-043b046199770fa044eae90f43d4', NULL, 'TERKIRIM', '2026-11-06 14:04:28', '2026-11-06 14:04:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `orang_tua`
--

CREATE TABLE `orang_tua` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `nama_orang_tua` varchar(100) NOT NULL,
  `no_whatsapp` varchar(20) NOT NULL,
  `alamat` text DEFAULT NULL,
  `pekerjaan` varchar(100) DEFAULT NULL,
  `penghasilan_bulanan` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `orang_tua`
--

INSERT INTO `orang_tua` (`id`, `pendaftar_id`, `nama_orang_tua`, `no_whatsapp`, `alamat`, `pekerjaan`, `penghasilan_bulanan`) VALUES
('ort-d66dc12c', 'prd-f8bab97d', 'Orang Tua Muhammad Brilyan', '+6285183076136', 'Jalan Danau Batur Bencongan, Kecamatan Kelapa Dua, Kabupaten Tangerang, Banten 15810', 'PNS', '3000000'),
('ort-d725f85b', 'prd-54787835', 'Orang Tua ELVA ROSYIDAH', '+6281943675499', 'ASDFGXJGHBKYJG', 'ABI', '5000000');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `jenis_pembayaran` varchar(20) NOT NULL,
  `angsuran_ke` tinyint(3) UNSIGNED DEFAULT NULL,
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
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pembayaran`
--

INSERT INTO `pembayaran` (`id`, `pendaftar_id`, `jenis_pembayaran`, `angsuran_ke`, `nominal`, `bukti_transfer_url`, `status`, `keterangan`, `order_id`, `midtrans_status`, `idempotency_key`, `snap_token`, `snap_expires_at`, `payment_method`, `metode`, `catatan`, `paid_at`, `verified_at`, `verified_by`, `created_at`) VALUES
('byr-03212ba9', 'prd-54787835', 'FORMULIR', NULL, 200000.00, 'storage/dokumen/bukti-20261106-075729-efcfafc9.png', 'VERIFIED', 'Terverifikasi 06 Nov 2026, 13:57', NULL, NULL, NULL, NULL, NULL, NULL, 'TRANSFER', 'Kirim Ulang Karena Foto Buram', NULL, '2026-11-06 13:57:59', 'usr-bendahara', '2026-11-06 13:51:48'),
('byr-79242c1b', 'prd-f8bab97d', 'FORMULIR', NULL, 200000.00, 'storage/dokumen/bukti-20261105-231719-ca647fc0.png', 'VERIFIED', 'Terverifikasi 06 Nov 2026, 05:18', NULL, NULL, NULL, NULL, NULL, NULL, 'TRANSFER', 'FOTO KURANG JELAS', NULL, '2026-11-06 05:18:32', 'usr-bendahara', '2026-11-06 03:51:25'),
('byr-dbb9b130', 'prd-f8bab97d', 'UANG_MASUK', NULL, 1050000.00, 'storage/dokumen/bukti-20261106-045125-13d8aece.png', 'LUNAS', 'Terverifikasi 06 Nov 2026, 14:14', NULL, NULL, '55bc70c5b84a4ed1b036a376763f4e17', NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-06 14:14:39', 'usr-bendahara', '2026-11-06 10:51:25'),
('byr-f8c1370e', 'prd-54787835', 'UANG_MASUK', NULL, 1050000.00, 'storage/dokumen/bukti-20261106-081120-51a1bc3e.png', 'LUNAS', 'Terverifikasi 06 Nov 2026, 14:12', NULL, NULL, '2ec1f3c931204ef2af26ceca3c6953dd', NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-06 14:12:13', 'usr-bendahara', '2026-11-06 14:11:20');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pendaftar`
--

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
  `hasil_dibuka_at` datetime DEFAULT NULL,
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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pendaftar`
--

INSERT INTO `pendaftar` (`id`, `user_id`, `no_pendaftaran`, `nama_siswa`, `nisn`, `jenis_kelamin`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `asal_sekolah`, `jalur`, `is_luar_daerah`, `kelas_id`, `status_verifikasi`, `status_seleksi`, `status_akun`, `tahap`, `hasil_dibuka_at`, `catatan_verifikasi_data`, `data_verified_at`, `data_verified_by`, `arsip_at`, `arsip_alasan`, `arsip_oleh`, `kred_sent_at`, `setuju_sk_at`, `setuju_sk_versi`, `setuju_sk_ip`, `pay_token`, `pay_token_hash`, `registration_token`, `created_at`, `updated_at`) VALUES
('prd-54787835', 'usr-085538f3de', 'REG-2026-0002', 'ELVA ROSYIDAH', '1111111111', 'P', 'Bogor', '2026-11-06', 'sadfghjklyurhrhfdgfhr', 'SD MUHAMMADIYAH', 'Reguler', 0, NULL, 'MENUNGGU_VERIFIKASI', 'LULUS', 'AKTIF', 'DIUMUMKAN', '2026-11-06 14:08:53', NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-06 13:58:02', '2026-11-06 13:51:48', 3, '::1', NULL, NULL, '1811438a6eac1669e11e4642032a80c5', '2026-11-06 13:51:48', '2026-11-06 14:08:53'),
('prd-f8bab97d', 'usr-54d208646a', 'REG-2026-0001', 'Muhammad Brilyan', NULL, 'L', 'Bogor', '2014-05-06', 'Jalan Danau Batur  Bencongan, Kecamatan Kelapa Dua, Kabupaten Tangerang, Banten 15810', 'SDN DANAU BATUR', 'Reguler', 0, NULL, 'MENUNGGU_VERIFIKASI', 'LULUS', 'AKTIF', 'DIUMUMKAN', '2026-11-06 06:32:04', NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-06 05:18:33', '2026-11-06 03:51:25', 3, '::1', NULL, NULL, 'b345f7ee0fe4fa6250859016bd710310', '2026-11-06 03:51:25', '2026-11-06 10:35:16');

-- --------------------------------------------------------

--
-- Struktur dari tabel `penempatan_kelas`
--

CREATE TABLE `penempatan_kelas` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `kelas_id` varchar(36) NOT NULL,
  `catatan` varchar(255) DEFAULT NULL,
  `ditempatkan_oleh` varchar(36) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `penempatan_kelas`
--

INSERT INTO `penempatan_kelas` (`id`, `pendaftar_id`, `kelas_id`, `catatan`, `ditempatkan_oleh`, `created_at`) VALUES
('ptm-4801a3c3', 'prd-54787835', 'kls-6a02a2db', '', 'usr-operator', '2026-11-06 14:08:06'),
('ptm-9d648101', 'prd-f8bab97d', 'kls-6a02a2db', '', 'usr-operator', '2026-11-06 06:30:09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengajuan_koreksi`
--

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan_sekolah`
--

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
  `rincian_biaya` text DEFAULT NULL,
  `ambang_kelulusan` decimal(5,2) NOT NULL DEFAULT 70.00,
  `angsuran_1_nominal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `angsuran_2_nominal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `angsuran_1_jatuh_tempo` date DEFAULT NULL,
  `angsuran_2_jatuh_tempo` date DEFAULT NULL,
  `pengingat_angsuran_hari` tinyint(3) UNSIGNED NOT NULL DEFAULT 7,
  `pengingat_angsuran_wa` tinyint(1) NOT NULL DEFAULT 0,
  `label_uang_pangkal` varchar(100) DEFAULT 'Uang Pangkal Gedung',
  `label_spp` varchar(100) DEFAULT 'SPP Bulanan',
  `teks_beasiswa` varchar(300) DEFAULT NULL,
  `nama_bank` varchar(50) DEFAULT 'Bank Syariah Indonesia (BSI)',
  `no_rekening` varchar(50) DEFAULT '1234567890',
  `atas_nama_rekening` varchar(100) DEFAULT 'SMP Muhammadiyah 5',
  `kontak_wa_panitia` varchar(20) DEFAULT '628123456789',
  `email_sekolah` varchar(100) DEFAULT NULL,
  `sosmed_instagram` varchar(255) DEFAULT NULL,
  `sosmed_tiktok` varchar(255) DEFAULT NULL,
  `sosmed_youtube` varchar(255) DEFAULT NULL,
  `website_sekolah` varchar(255) DEFAULT NULL,
  `ticker_header` text DEFAULT NULL,
  `profil_singkat` text DEFAULT NULL,
  `deskripsi_sekolah` text DEFAULT NULL,
  `tagline` varchar(255) DEFAULT 'Berakhlak & Berprestasi',
  `faq_list` text DEFAULT NULL,
  `wa_teks` text DEFAULT NULL,
  `tahun_ajaran` varchar(20) DEFAULT '2027/2028',
  `gelombang` varchar(50) DEFAULT 'Gelombang 1',
  `tanggal_buka_ppdb` date DEFAULT NULL,
  `tanggal_tutup_ppdb` date DEFAULT NULL,
  `tanggal_pengumuman` date DEFAULT NULL,
  `pendaftaran_dibuka` tinyint(1) NOT NULL DEFAULT 1,
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
  `notif_wa_bendahara` varchar(255) DEFAULT NULL,
  `notif_wa_aktif` tinyint(1) NOT NULL DEFAULT 0,
  `notif_email` varchar(255) DEFAULT NULL,
  `hasil_diumumkan_pada` datetime DEFAULT NULL,
  `retensi_aktif` tinyint(1) NOT NULL DEFAULT 0,
  `retensi_bulan_tidak_lulus` smallint(5) UNSIGNED DEFAULT NULL,
  `retensi_bulan_batal` smallint(5) UNSIGNED DEFAULT NULL,
  `retensi_bulan_lulus_arsip` smallint(5) UNSIGNED DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `pengaturan_sekolah`
--

INSERT INTO `pengaturan_sekolah` (`id`, `nama_sekolah`, `alamat_sekolah`, `logo_url`, `hero_banner_url`, `login_banner_url`, `favicon_url`, `warna_utama`, `biaya_formulir`, `biaya_spp`, `estimasi_uang_masuk`, `rincian_biaya`, `ambang_kelulusan`, `angsuran_1_nominal`, `angsuran_2_nominal`, `angsuran_1_jatuh_tempo`, `angsuran_2_jatuh_tempo`, `pengingat_angsuran_hari`, `pengingat_angsuran_wa`, `label_uang_pangkal`, `label_spp`, `teks_beasiswa`, `nama_bank`, `no_rekening`, `atas_nama_rekening`, `kontak_wa_panitia`, `email_sekolah`, `sosmed_instagram`, `sosmed_tiktok`, `sosmed_youtube`, `website_sekolah`, `ticker_header`, `profil_singkat`, `deskripsi_sekolah`, `tagline`, `faq_list`, `wa_teks`, `tahun_ajaran`, `gelombang`, `tanggal_buka_ppdb`, `tanggal_tutup_ppdb`, `tanggal_pengumuman`, `deskripsi_gelombang`, `jalur_aktif`, `fonnte_token`, `midtrans_server_key`, `midtrans_client_key`, `midtrans_sandbox`, `syarat_ketentuan`, `syarat_versi`, `bayar_gateway_aktif`, `bayar_transfer_aktif`, `bayar_qris_aktif`, `qris_image_url`, `qris_merchant`, `kirim_akun_otomatis`, `notif_wa_operator`, `notif_wa_bendahara`, `notif_wa_aktif`, `notif_email`, `hasil_diumumkan_pada`, `retensi_aktif`, `retensi_bulan_tidak_lulus`, `retensi_bulan_batal`, `retensi_bulan_lulus_arsip`, `updated_at`) VALUES
(1, 'SMP Muhammadiyah 5 Kota Tangerang', 'Jl. Sawo VI Ujung No.55, RT.002/RW.002, Kelurahan Cibodasari, Kecamatan Cibodas, Kota Tangerang, Banten 15138', 'assets/img/uploads/logo-20261018-005525-8f2cb8.png', 'assets/img/uploads/banner-20261018-004603-3d9eff.jpg', 'assets/img/uploads/login-banner-20261018-004713-ba0c29.jpg', NULL, '#0056B3', 200000.00, 350000.00, 1250000.00, NULL, 70.00, 525000.00, 525000.00, '2027-01-31', '2027-06-30', 7, 1, 'Biaya Pendaftaran', 'SPP Bulanan', NULL, 'Bank BJB', '714-239-5501', 'SMP Muhammadiyah 5 Tangerang', '6281285232704', 'spmb@smpmuh5tng.sch.id', 'https://www.instagram.com/smpmuh5.tng/', 'https://www.tiktok.com/@smpmuh5.tng', 'https://www.youtube.com/@SMPMuhammadiyah5Tangerang', 'https://smpm5tangerang.sch.id/', 'Pendaftaran SPMB Gelombang 1 Dibuka: 1 Oktober - 30 November - Kuota Terbatas 90 Siswa (3 Rombel) - Hubungi Layanan SPMB: 0812-8523-2704', 'SMP Muhammadiyah 5 Kota Tangerang binaan Muhammadiyah dengan pembinaan tahfidz hingga 3 juz, sertifikasi BTQ, prestasi akademik & Non-akademik.', 'Membentuk generasi muda yang unggul dalam ilmu pengetahuan, berkarakter Islam, dan berdaya saing global di wilayah Kota Tangerang.', 'Berakhlak & Berprestasi', '[{\"q\":\"Apakah sekolah ini benar-benar berbasis tahfidz Qur’an?\",\"a\":\"Ya. SMP Muhammadiyah 5 Kota Tangerang menerapkan pembinaan pemahaman dan pengembangan sarana prasarana hafalan hingga tingkat 10 juz yang dilengkapi sertifikasi BTQ.\"},{\"q\":\"Bagaimana jika calon siswa berasal di luar Kota Tangerang?\",\"a\":\"Pendaftar luar daerah tetap diterima. Proses seleksi dapat diikuti secara daring (video call) dan penempatan kelas mengikuti kuota rombel yang tersedia.\"},{\"q\":\"Apakah Biaya Pendaftaran dapat diangsur?\",\"a\":\"Bisa. Tersedia skema angsuran 2 tahap (Angsuran 1 saat daftar ulang dan Angsuran 2 pada periode berikutnya). Ajukan skema melalui panitia SPMB.\"},{\"q\":\"Berapa biaya formulir dan ke mana saya transfer?\",\"a\":\"Biaya formulir ditetapkan panitia (default Rp 200.000) dan dibayarkan ke rekening resmi sekolah yang tertera pada halaman Rincian Biaya. Nominal selalu mengikuti pengaturan CMS terbaru.\"},{\"q\":\"Apakah wajib membayar secara online (virtual account \\/ e-wallet)?\",\"a\":\"Tidak. Metode utama adalah transfer bank manual atau QRIS: transfer sesuai rekening resmi sekolah, lalu unggah bukti pembayaran untuk diverifikasi panitia. Pembayaran online hanya pilihan tambahan bila panitia mengaktifkannya.\"}]', '{\"jadwal_tes\":\"Assalamu\'alaikum.\\n\\nJadwal tes seleksi SPMB {sekolah}:\\n\\nAnanda: {nama} ({no_pendaftaran})\\n\\n{jadwal_list}\\n\\n{total}\\n\\nMohon hadir 15 menit lebih awal dan membawa kartu peserta (cetak di portal siswa), serta alat tulis.\\n\\nSalam,\\nPanitia SPMB\"}', '2027/2028', 'Gelombang 1', '2026-10-01', '2026-11-30', '2026-12-05', 'Pendaftaran daring dibuka 1 Oktober - 30 November. Seleksi meliputi CBT, wawancara BTQ, dan wawancara orang tua.', '[\"Reguler\",\"Tahfidz\",\"Prestasi\",\"Afirmasi\"]', 'ZsA4mja6fjvSxUY4PxRb', NULL, NULL, 0, 'SYARAT & KETENTUAN PENDAFTARAN\r\nSMP MUHAMMADIYAH 5 KOTA TANGERANG\r\n\r\n1. Pendaftar adalah siswa kelas 6 SD/MI yang akan melanjutkan ke SMP kelas 7 pada tahun ajaran berikutnya.\r\n\r\n2. Data yang diisi pada formulir pendaftaran harus benar sesuai dokumen resmi (akta kelahiran, Kartu Keluarga, Ijazah SD/MI).\r\n\r\n3. Biaya formulir sebesar yang tertera di halaman ini tidak dapat dikembalikan dalam keadaan apa pun.\r\n\r\n4. Pendaftar wajib mengunggah seluruh berkas persyaratan sesuai daftar yang ditentukan panitia.\r\n\r\n5. Panitia berhak menolak pendaftar yang memberikan data tidak benar atau berkas tidak lengkap.\r\n\r\n6. Jadwal ujian, pengumuman hasil, dan pembagian kelas akan diinformasikan melalui website resmi dan/atau WhatsApp orang tua.\r\n\r\n7. Keputusan panitia bersifat final.\r\n\r\n8. PRIVASI & RETENSI DATA. Data pribadi siswa dan orang tua (nama, NISN, alamat, nomor WhatsApp, serta berkas Kartu Keluarga/akta/bukti pembayaran) hanya digunakan untuk keperluan seleksi dan administrasi pendaftaran. Berkas pribadi disimpan terbatas dan dapat dihapus melalui kebijakan retensi yang ditetapkan sekolah; catatan pendaftaran ringkas tetap diarsipkan untuk keperluan akademik. Sekolah tidak membagikan data kepada pihak ketiga tanpa dasar hukum atau persetujuan orang tua/wali.\r\n\r\nDengan menyetujui syarat & ketentuan ini, saya menyatakan bahwa seluruh data yang saya isi adalah benar dan saya memahami seluruh ketentuan pendaftaran di atas.', 3, 0, 1, 0, NULL, NULL, 0, '085175070570', '085175070570', 1, NULL, '2026-11-06 06:32:00', 0, NULL, NULL, NULL, '2026-11-06 09:50:56');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengumuman_umum`
--

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

-- --------------------------------------------------------

--
-- Struktur dari tabel `peserta_ujian`
--

CREATE TABLE `peserta_ujian` (
  `id` varchar(36) NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `jadwal_id` varchar(36) NOT NULL,
  `nomor_peserta` varchar(30) NOT NULL,
  `ruangan` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `peserta_ujian`
--

INSERT INTO `peserta_ujian` (`id`, `pendaftar_id`, `jadwal_id`, `nomor_peserta`, `ruangan`, `created_at`) VALUES
('prj-08096ced61', 'prd-54787835', 'tes-66ff32c3', 'TES-01-002', 'Ruang Kelas', '2026-11-06 14:03:55'),
('prj-ca0cc084f2', 'prd-f8bab97d', 'tes-66ff32c3', 'TES-01-001', 'Ruang Kelas', '2026-11-06 14:03:55');

-- --------------------------------------------------------

--
-- Struktur dari tabel `rate_limit`
--

CREATE TABLE `rate_limit` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `aksi` varchar(30) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `rate_limit`
--

INSERT INTO `rate_limit` (`id`, `ip_address`, `aksi`, `created_at`) VALUES
(115, '::1', 'bayar_bukti', '2026-11-06 03:51:47'),
(117, '::1', 'bayar_bukti', '2026-11-06 04:31:08'),
(125, '::1', 'bayar_bukti', '2026-11-06 04:32:43'),
(227, '::1', 'bayar_bukti', '2026-11-06 05:15:18'),
(233, '::1', 'bayar_bukti', '2026-11-06 05:17:19'),
(262, '::1', 'bayar_bukti', '2026-11-06 13:52:34'),
(268, '::1', 'bayar_bukti', '2026-11-06 13:57:29'),
(114, '::1', 'bayar_token', '2026-11-06 03:51:41'),
(116, '::1', 'bayar_token', '2026-11-06 03:52:02'),
(118, '::1', 'bayar_token', '2026-11-06 04:31:13'),
(124, '::1', 'bayar_token', '2026-11-06 04:32:37'),
(126, '::1', 'bayar_token', '2026-11-06 04:32:46'),
(127, '::1', 'bayar_token', '2026-11-06 04:33:50'),
(132, '::1', 'bayar_token', '2026-11-06 04:38:16'),
(136, '::1', 'bayar_token', '2026-11-06 04:39:39'),
(218, '::1', 'bayar_token', '2026-11-06 05:05:09'),
(222, '::1', 'bayar_token', '2026-11-06 05:14:00'),
(226, '::1', 'bayar_token', '2026-11-06 05:15:10'),
(228, '::1', 'bayar_token', '2026-11-06 05:15:20'),
(229, '::1', 'bayar_token', '2026-11-06 05:16:55'),
(232, '::1', 'bayar_token', '2026-11-06 05:17:08'),
(234, '::1', 'bayar_token', '2026-11-06 05:17:20'),
(261, '::1', 'bayar_token', '2026-11-06 13:52:08'),
(263, '::1', 'bayar_token', '2026-11-06 13:53:05'),
(264, '::1', 'bayar_token', '2026-11-06 13:56:42'),
(267, '::1', 'bayar_token', '2026-11-06 13:57:23'),
(269, '::1', 'bayar_token', '2026-11-06 13:57:35'),
(215, '::1', 'cek_status', '2026-11-06 05:04:36'),
(216, '::1', 'cek_status', '2026-11-06 05:04:52'),
(219, '::1', 'cek_status', '2026-11-06 05:05:19'),
(220, '::1', 'cek_status', '2026-11-06 05:05:19'),
(223, '::1', 'cek_status', '2026-11-06 05:14:33'),
(224, '::1', 'cek_status', '2026-11-06 05:15:06'),
(230, '::1', 'cek_status', '2026-11-06 05:17:04'),
(265, '::1', 'cek_status', '2026-11-06 13:57:14'),
(122, '::1', 'pulihkan_bayar', '2026-11-06 04:32:34'),
(130, '::1', 'pulihkan_bayar', '2026-11-06 04:38:01'),
(134, '::1', 'pulihkan_bayar', '2026-11-06 04:38:33'),
(196, '0.0.0.0', 'bayar_token', '2026-11-06 05:00:49'),
(214, '0.0.0.0', 'bayar_token', '2026-11-06 05:01:16'),
(260, 'k:5f2ade19b296a4d0da689e8bc277d0f523b78d06d6', 'registrasi_wa', '2026-11-06 13:51:52'),
(113, 'k:a83ff23027f9465ca88d5a3d933b3a41f66a9c4f91', 'registrasi_wa', '2026-11-06 03:51:25'),
(271, 'k:BERKAS_DISUBMIT:prd-54787835', 'notif_wa_operasional', '2026-11-06 14:01:25'),
(259, 'k:PENDAFTAR_BARU:prd-54787835', 'notif_wa_operasional', '2026-11-06 13:51:52'),
(266, 'k:prd-54787835', 'tautan_bayar', '2026-11-06 13:57:23'),
(217, 'k:prd-f8bab97d', 'tautan_bayar', '2026-11-06 05:05:01'),
(225, 'k:prd-f8bab97d', 'tautan_bayar', '2026-11-06 05:15:10'),
(231, 'k:prd-f8bab97d', 'tautan_bayar', '2026-11-06 05:17:08'),
(221, 'k:REG-2026-0001', 'cek_status_no', '2026-11-06 05:05:19'),
(123, 'k:REG-2026-0001', 'pulihkan_bayar_no', '2026-11-06 04:32:34'),
(131, 'k:REG-2026-0001', 'pulihkan_bayar_no', '2026-11-06 04:38:01'),
(135, 'k:REG-2026-0001', 'pulihkan_bayar_no', '2026-11-06 04:38:33'),
(270, 'k:VERIFIKASI_DATA:prd-54787835', 'notif_wa_operasional', '2026-11-06 14:01:17'),
(235, 'k:warn:prd-f8bab97d', 'wa_revisi', '2026-11-06 05:50:16');

-- --------------------------------------------------------

--
-- Struktur dari tabel `retensi_log`
--

CREATE TABLE `retensi_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `pendaftar_id` varchar(36) NOT NULL,
  `no_pendaftaran` varchar(30) DEFAULT NULL,
  `alasan` varchar(40) NOT NULL,
  `jumlah_berkas` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `operator_id` varchar(36) DEFAULT NULL,
  `dibuat_pada` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `seleksi`
--

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `seleksi`
--

INSERT INTO `seleksi` (`id`, `pendaftar_id`, `jadwal_id`, `nilai_cbt`, `nilai_btq`, `nilai_wawancara`, `nilai_akhir`, `hasil`, `catatan`, `input_by`, `updated_at`) VALUES
('sel-040454fb', 'prd-f8bab97d', NULL, 100.00, 100.00, 100.00, 100.00, 'LULUS', NULL, 'usr-operator', '2026-11-06 06:28:09'),
('sel-7a0ee967', 'prd-54787835', NULL, 100.00, 100.00, 100.00, 100.00, 'LULUS', NULL, 'usr-operator', '2026-11-06 14:07:35');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` varchar(36) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('siswa','bendahara','operator') NOT NULL DEFAULT 'siswa',
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `wajib_ganti_password` tinyint(1) NOT NULL DEFAULT 0,
  `notif_mulai_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `nama_lengkap`, `jabatan`, `is_active`, `wajib_ganti_password`, `notif_mulai_at`, `created_at`, `updated_at`) VALUES
('usr-085538f3de', 'siswa0002', '$2y$10$6nOvJf03/qB20NSxAMD3YOIY/yKZjemIbDHccJlkCQI0lT8MlDA.C', 'siswa', 'ELVA ROSYIDAH', 'Calon Siswa SPMB', 1, 0, '2026-11-06 13:59:04', '2026-11-06 13:57:59', '2026-11-06 13:59:04'),
('usr-54d208646a', 'siswa0001', '$2y$10$OO/Fs0rfrRuAW4UxjW7smu441W/dlRuKObkCstPCOOHRAoIkkiZHC', 'siswa', 'Muhammad Brilyan', 'Calon Siswa SPMB', 1, 0, '2026-11-06 05:34:05', '2026-11-06 05:18:33', '2026-11-06 05:34:05'),
('usr-bendahara', 'bendahara', '$2y$10$yIAZMbP9yk.OJRlJGYW/YexHEhWxgEbEfWpMADBxifRNE6XMly8l.', 'bendahara', 'Mardiyana', 'Bendahara Sekolah', 1, 0, NULL, '2026-10-18 05:18:09', '2026-10-31 23:26:18'),
('usr-operator', 'operator', '$2y$10$MJWJmQgZXDsg9XthEFVF7uWa2QnXwdfmuqixx0RBB1g.a6NeIPuTm', 'operator', 'Muhammad Brilyan, S.Kom', 'Operator SPMB & SIMS', 1, 0, NULL, '2026-10-18 05:18:09', '2026-10-31 23:26:03');

-- --------------------------------------------------------

--
-- Struktur dari tabel `wa_log`
--

CREATE TABLE `wa_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `tujuan` varchar(30) NOT NULL,
  `tipe` varchar(40) NOT NULL,
  `status` varchar(20) NOT NULL,
  `respons` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `wa_log`
--

INSERT INTO `wa_log` (`id`, `tujuan`, `tipe`, `status`, `respons`, `created_at`) VALUES
(45, '085175070570', 'BUKTI_BAYAR_MASUK', 'GAGAL', '{\"pesan\":\"Fonnte: request invalid on disconnected device\",\"id\":null}', '2026-11-06 03:51:47'),
(46, '+6285183076136', 'PENOLAKAN_BAYAR', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191021455\"}', '2026-11-06 04:30:22'),
(47, '085175070570', 'BUKTI_BAYAR_MASUK', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191021567\"}', '2026-11-06 04:32:43'),
(48, '+6285183076136', 'PENOLAKAN_BAYAR', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191021598\"}', '2026-11-06 04:33:32'),
(49, '085175070570', 'BUKTI_BAYAR_MASUK', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191024070\"}', '2026-11-06 05:15:18'),
(50, '+6285183076136', 'PENOLAKAN_BAYAR', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191024179\"}', '2026-11-06 05:16:51'),
(51, '085175070570', 'BUKTI_BAYAR_MASUK', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191024203\"}', '2026-11-06 05:17:19'),
(52, '+6285183076136', 'KIRIM_AKUN', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191024283\"}', '2026-11-06 05:18:33'),
(53, '+6285183076136', 'BERKAS_REVISI', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191027992\"}', '2026-11-06 05:50:16'),
(68, '+6285183076136', 'JADWAL_UJIAN', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191031949\"}', '2026-11-06 06:08:03'),
(69, '+6285183076136', 'JADWAL_UJIAN', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191032043\"}', '2026-11-06 06:08:30'),
(87, '+6285183076136', 'PENGUMUMAN', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191037440\"}', '2026-11-06 06:31:02'),
(123, '085175070570', 'BUKTI_BAYAR_MASUK', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191114204\"}', '2026-11-06 10:51:25'),
(124, '085175070570', 'PENDAFTAR_BARU', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191153928\"}', '2026-11-06 13:51:52'),
(125, '085175070570', 'BUKTI_BAYAR_MASUK', 'GAGAL', '{\"pesan\":\"Gateway tidak terjangkau: Connection timed out after 15008 milliseconds\",\"id\":null}', '2026-11-06 13:52:49'),
(126, '+6281943675499', 'PENOLAKAN_BAYAR', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191154619\"}', '2026-11-06 13:54:28'),
(127, '085175070570', 'BUKTI_BAYAR_MASUK', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191155255\"}', '2026-11-06 13:57:33'),
(128, '+6281943675499', 'KIRIM_AKUN', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191155360\"}', '2026-11-06 13:58:02'),
(129, '085175070570', 'VERIFIKASI_DATA', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191156529\"}', '2026-11-06 14:01:16'),
(130, '085175070570', 'BERKAS_DISUBMIT', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191156579\"}', '2026-11-06 14:01:25'),
(131, '+6285183076136', 'JADWAL_UJIAN', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191157362\"}', '2026-11-06 14:04:14'),
(132, '+6281943675499', 'JADWAL_UJIAN', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191157384\"}', '2026-11-06 14:04:28'),
(133, '+6285183076136', 'PENGUMUMAN', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191158635\"}', '2026-11-06 14:08:27'),
(134, '+6281943675499', 'PENGUMUMAN', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191158657\"}', '2026-11-06 14:08:32'),
(135, '085175070570', 'BUKTI_BAYAR_MASUK', 'TERKIRIM', '{\"pesan\":\"Terkirim.\",\"id\":\"191159568\"}', '2026-11-06 14:11:24');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `dokumen`
--
ALTER TABLE `dokumen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dok_jenis` (`jenis_dokumen`),
  ADD KEY `idx_dok_status` (`status_verifikasi`),
  ADD KEY `pendaftar_id` (`pendaftar_id`);

--
-- Indeks untuk tabel `jadwal_tes`
--
ALTER TABLE `jadwal_tes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indeks untuk tabel `jenis_dokumen`
--
ALTER TABLE `jenis_dokumen`
  ADD PRIMARY KEY (`kode`);

--
-- Indeks untuk tabel `kelas`
--
ALTER TABLE `kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indeks untuk tabel `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_time` (`ip_address`,`attempted_at`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_dibaca` (`dibaca_at`),
  ADD KEY `idx_notif_tipe` (`tipe`),
  ADD KEY `idx_notif_pendaftar` (`pendaftar_id`);

--
-- Indeks untuk tabel `notifikasi_siswa`
--
ALTER TABLE `notifikasi_siswa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ns_pendaftar` (`pendaftar_id`,`dibaca_at`),
  ADD KEY `idx_ns_ref` (`ref_id`);

--
-- Indeks untuk tabel `orang_tua`
--
ALTER TABLE `orang_tua`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pendaftar_id` (`pendaftar_id`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pembayaran_order` (`order_id`),
  ADD UNIQUE KEY `uq_pembayaran_idem` (`idempotency_key`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_byr_angsuran` (`pendaftar_id`,`jenis_pembayaran`,`angsuran_ke`);

--
-- Indeks untuk tabel `pendaftar`
--
ALTER TABLE `pendaftar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_pendaftaran` (`no_pendaftaran`),
  ADD UNIQUE KEY `uq_pendaftar_regtoken` (`registration_token`),
  ADD UNIQUE KEY `uq_pendaftar_pay_token` (`pay_token`),
  ADD UNIQUE KEY `uq_pendaftar_pay_token_hash` (`pay_token_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `idx_pendaftar_verifikasi_data` (`status_verifikasi`,`updated_at`),
  ADD KEY `fk_pendaftar_data_verifier` (`data_verified_by`),
  ADD KEY `idx_pendaftar_arsip` (`arsip_at`),
  ADD KEY `fk_pendaftar_arsip_oleh` (`arsip_oleh`);

--
-- Indeks untuk tabel `penempatan_kelas`
--
ALTER TABLE `penempatan_kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_penempatan` (`pendaftar_id`),
  ADD KEY `kelas_id` (`kelas_id`),
  ADD KEY `ditempatkan_oleh` (`ditempatkan_oleh`);

--
-- Indeks untuk tabel `pengajuan_koreksi`
--
ALTER TABLE `pengajuan_koreksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_koreksi_pendaftar` (`pendaftar_id`),
  ADD KEY `idx_koreksi_status` (`status`),
  ADD KEY `fk_koreksi_user` (`user_id`),
  ADD KEY `fk_koreksi_reviewer` (`reviewed_by`);

--
-- Indeks untuk tabel `pengaturan_sekolah`
--
ALTER TABLE `pengaturan_sekolah`
  ADD PRIMARY KEY (`id`);
--
-- Indeks untuk tabel `peserta_ujian`
--
ALTER TABLE `peserta_ujian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_peserta_nomor` (`nomor_peserta`),
  ADD UNIQUE KEY `uq_peserta_jadwal` (`pendaftar_id`,`jadwal_id`),
  ADD KEY `idx_peserta_jadwal` (`jadwal_id`);

--
-- Indeks untuk tabel `rate_limit`
--
ALTER TABLE `rate_limit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_aksi_time` (`ip_address`,`aksi`,`created_at`);

--
-- Indeks untuk tabel `retensi_log`
--
ALTER TABLE `retensi_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_retensi_pendaftar` (`pendaftar_id`);

--
-- Indeks untuk tabel `seleksi`
--
ALTER TABLE `seleksi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pendaftar_id` (`pendaftar_id`),
  ADD KEY `jadwal_id` (`jadwal_id`),
  ADD KEY `input_by` (`input_by`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `wa_log`
--
ALTER TABLE `wa_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_walog_tipe` (`tipe`),
  ADD KEY `idx_walog_status` (`status`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `rate_limit`
--
ALTER TABLE `rate_limit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=272;

--
-- AUTO_INCREMENT untuk tabel `retensi_log`
--
ALTER TABLE `retensi_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `wa_log`
--
ALTER TABLE `wa_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `dokumen`
--
ALTER TABLE `dokumen`
  ADD CONSTRAINT `dokumen_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `orang_tua`
--
ALTER TABLE `orang_tua`
  ADD CONSTRAINT `orang_tua_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pembayaran_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `pendaftar`
--
ALTER TABLE `pendaftar`
  ADD CONSTRAINT `fk_pendaftar_arsip_oleh` FOREIGN KEY (`arsip_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pendaftar_data_verifier` FOREIGN KEY (`data_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pendaftar_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pendaftar_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `penempatan_kelas`
--
ALTER TABLE `penempatan_kelas`
  ADD CONSTRAINT `penempatan_kelas_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `penempatan_kelas_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `penempatan_kelas_ibfk_3` FOREIGN KEY (`ditempatkan_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `pengajuan_koreksi`
--
ALTER TABLE `pengajuan_koreksi`
  ADD CONSTRAINT `fk_koreksi_pendaftar` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_koreksi_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_koreksi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `peserta_ujian`
--
ALTER TABLE `peserta_ujian`
  ADD CONSTRAINT `peserta_ujian_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `peserta_ujian_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_tes` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `seleksi`
--
ALTER TABLE `seleksi`
  ADD CONSTRAINT `seleksi_ibfk_1` FOREIGN KEY (`pendaftar_id`) REFERENCES `pendaftar` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `seleksi_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_tes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `seleksi_ibfk_3` FOREIGN KEY (`input_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
