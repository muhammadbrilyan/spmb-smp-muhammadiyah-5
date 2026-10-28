-- ============================================================
-- Migrasi: teks program beasiswa menjadi pengaturan CMS
-- Tanggal : 2026-10-28
-- Tujuan  : halaman publik tidak lagi hardcoded mempromosikan
--           beasiswa ("Gratis Beasiswa Musyawarah", "Potongan hingga
--           100% ..."). Sekolah belum menjalankan program beasiswa,
--           jadi blok itu sekarang hanya tampil bila operator mengisi
--           teks programnya di Pengaturan Sistem -> Struktur Tarif.
-- Kolom lama tidak dihapus; enum & nama kolom lain tidak berubah.
-- Jalankan sekali (mysql -u root db_spmb_mualimku < berkas ini).
-- ============================================================

ALTER TABLE pengaturan_sekolah
  ADD COLUMN teks_beasiswa VARCHAR(300) DEFAULT NULL AFTER label_spp;
