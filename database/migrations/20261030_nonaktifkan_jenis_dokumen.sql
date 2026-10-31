-- 2026-10-30 — Nonaktifkan jenis dokumen yang tidak dipakai lagi
-- (revisi tampilan portal siswa): "Surat Keterangan Siswa" dan
-- "Berkas Lainnya" dihapus dari formulir unggah siswa.
-- Bisa diaktifkan kembali kapan saja dari menu Operator → Jenis Dokumen.

UPDATE `jenis_dokumen` SET `aktif` = 0 WHERE `kode` IN ('SURAT_KETERANGAN_SISWA', 'LAINNYA');
