# Fungsi Role Kepala Sekolah — SPMB SMP Muhammadiyah 5 Kota Tangerang

Dokumen ini memetakan seluruh fungsi yang dimiliki role **kepala sekolah**
(6 menu) beserta status dan aturan mainnya.

**Tanggal penulisan:** 31 Okt 2026
**Cakupan:** `app/kepsek/*`, `app/bendahara/rekap.php` (akses kepsek), gerbang
persetujuan pada `app/operator/pengumuman.php`

---

## Prinsip akses

- Kepala sekolah **tidak pernah** mengubah data pendaftar, pembayaran, atau nilai.
  Semua halamannya read-only, kecuali satu: membubuhkan/mencabut persetujuan
  dan tanda tangan pada daftar hasil.
- Verifikasi pembayaran tetap kewenangan bendahara, penjadwalan & penerbitan
  tetap kewenangan operator. Kepala sekolah mengendalikan **kapan boleh terbit**,
  bukan siapa yang menerbitkan.
- Menu dibangun di `includes/partials/sidebar.php` (grup `Monitoring`).

---

## 1. Dashboard Kepala Sekolah — `app/kepsek/index.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | 4 kartu rekap (pendaftar, verifikasi, seleksi, dana) | ✅ dari `stat_bendahara()` + `stat_operator()` |
| 2 | **Corong Pendaftaran** — 6 tahap: masuk → biodata terverifikasi → biaya terverifikasi → akun aktif → lulus → ditempatkan | ✅ `corong_ppdb()` (satu query, semua tahap menyaring `arsip_at IS NULL`) |
| 3 | Keterisian rombel + pendaftar per jalur + rekap hasil | ✅ |
| 4 | Jadwal tes terdekat | ✅ |
| 5 | Status pengumuman (terbit / terjadwal / belum) | ✅ |
| 6 | Cetak / Simpan PDF — kop laporan + blok tanda tangan | ✅ kelas `.hanya-cetak` (tampil hanya saat dicetak) |

Bar corong memakai `flex flex-wrap` + `min-w-[80px]` agar tetap terlihat pada
layar sempit; lembar gaya dipaksa muat ulang lewat penanda versi `?v=<filemtime>`
di `includes/partials/head.php`.

---

## 2. Persetujuan Penetapan Hasil — `app/kepsek/penetapan.php`

Satu-satunya halaman tempat kepala sekolah bertindak. Dua catatan terpisah,
keduanya direkam di `pengaturan_sekolah`:

| Aksi | Kolom | Artinya |
|---|---|---|
| **Setujui Daftar Ini** | `hasil_disetujui_oleh/_at/_sidik` | Membuka gerbang "Umumkan Sekarang" & "Jadwalkan" di sisi operator |
| **Cabut Persetujuan** | (dikosongkan) | Penerbitan terkunci lagi |
| **Tandatangani Rilis** | `hasil_ttd_oleh/_at/_sidik` | Tanda tangan atas rilis yang **sudah** terbit; muncul di blok tanda tangan hasil cetak |
| **Cabut Tanda Tangan** | (dikosongkan) | Rilis tetap tampil publik, catatannya hilang |

### Aturan sidik

`persetujuan_sidik_hasil()` = `sha256` atas baris `NO_PENDAFTARAN=STATUS_SELEKSI`
seluruh pendaftar aktif (`arsip_at IS NULL`, diurutkan berdasarkan
`no_pendaftaran`). Sidik disimpan bersama catatan; saat dibaca, sidik dihitung
ulang:

- sidik sama → catatan **sah**;
- sidik beda → catatan **BATAL**. Operator harus meminta persetujuan baru.

Ini yang membuat persetujuan tidak bisa "dipinjam" untuk daftar yang isinya
sudah berubah, dan sekaligus membuat pencabutan tidak perlu menyimpan riwayat.

### Batas yang ditegakkan di server

| Lapisan | Aturan |
|---|---|
| `persetujuan_simpan()` | hanya akun `role='kepsek'` dan `is_active=1` yang boleh menulis; sidik kosong (DB tak terbaca) ditolak |
| `app/kepsek/penetapan.php` | admin melihat halaman ini **hanya-baca**; `aksi` di luar 4 nilai yang dikenal ditolak; `setujui`/`ttd` wajib mencentang pernyataan (`nyata=1`) |
| `app/operator/pengumuman.php` | `umumkan_sekarang` **dan** `jadwalkan` berhenti bila `persetujuan_hasil_sah()` false |
| CSRF + PRG | setiap aksi POST diverifikasi `csrf_cek()` lalu dialihkan dengan `flash()` |

### Status yang ditampilkan

- Belum Disetujui (abu) · Disetujui (hijau) · **Batal** (merah, disertai nama &
  waktu catatan lama) — sama untuk tanda tangan.
- Peringatan bila masih ada pendaftar berstatus PROSES pada daftar yang akan
  disetujui, dan tombol persetujuan tidak ada gunanya (nonaktif) selama belum
  ada satu pun keputusan LULUS/TIDAK_LULUS.
- Tombol "Tandatangani Rilis" nonaktif selama belum ada rilis yang terbit.

### Skema basis data

`database/migrations/20261031_persetujuan_hasil.sql` — 6 kolom + 2 foreign key
`ON DELETE SET NULL` ke `users(id)` (akun kepsek dihapus → persetujuan hilang,
bukan menggantung). Idempoten, memakai `information_schema` + `PREPARE/EXECUTE`
sehingga portabel ke MySQL 5.7/8 dan MariaDB 10.x.

---

## 3. Rekap Keuangan — `app/bendahara/rekap.php` (dibuka lewat menu kepsek)

Halaman milik bendahara yang boleh dibaca kepala sekolah.

- Tombol **Ekspor CSV** hanya tampil untuk `operator`/`admin`/`bendahara`;
  kepsek tidak menautkannya ke jalur CSV.
- Breadcrumb & tombol *Kembali* mengikuti peran: kepsek diarahkan ke
  `role_home('kepsek')`, bendahara ke `app/bendahara/index.php`.

## 4. Pembiayaan & Tunggakan — `app/kepsek/keuangan.php`

Read-only, khusus `kepsek` + `admin`.

| # | Fungsi | Status |
|---|---|---|
| 1 | Daftar calon siswa LULUS + sisa tagihan per angsuran | ✅ memakai `angsuran_ringkas()` — angka identik dengan portal siswa |
| 2 | 4 kartu rekap (tertagih, terkumpul, tunggakan, menunggu verifikasi) | ✅ |
| 3 | Progres per tahap (Angsuran 1 & 2) | ✅ penyebut = jumlah siswa × nominal tahap, bukan hanya tahap yang dikembalikan `angsuran_ringkas()` |
| 4 | Filter Semua / Belum lunas / Terlambat / Sudah lunas | ✅ |
| 5 | Urutan "perlu ditindak lebih dulu" (sisa terbesar di atas) | ✅ |
| 6 | Cetak / Simpan PDF + blok tanda tangan | ✅ |

Tahap berstatus MENUNGGU (bukti sedang diperiksa bendahara) **tidak** dihitung
sebagai terlambat. Tagihan baru berlaku setelah hasil seleksi diterbitkan, dan
halaman memberi badge penjelasan bila tagihan belum berjalan.

## 5-6. Notifikasi, Panduan & SOP, Ganti Password

Halaman bersama staf lain (`app/operator/notifikasi.php`,
`app/operator/panduan.php`, `app/staf/ganti-password.php`) — tidak ada perilaku
khusus kepsek.

---

## Uji yang sudah dijalankan (31 Okt 2026)

Dijalankan lewat harness CLI sementara (session disuntik, halaman di-`require`,
satu halaman per proses) terhadap DB lokal, lalu DB dikembalikan persis ke
kondisi awal:

1. Operator mencoba mengumumkan/menjadwalkan tanpa persetujuan → ditolak,
   `hasil_diumumkan_pada` tetap NULL.
2. `setujui` tanpa mencentang pernyataan → ditolak; `aksi` tak dikenal → ditolak;
   admin mencoba menulis → ditolak.
3. `setujui` oleh kepsek → terekam dengan sidik yang sama dengan daftar.
4. Operator mengumumkan → berhasil, tahap pendaftar naik ke DIUMUMKAN.
5. `ttd` → terekam; sebelum rilis, tombol & POST `ttd` ditolak.
6. Salah satu status diubah → kedua catatan menjadi **BATAL**, `sah=false`,
   operator ditolak lagi dengan pesan "persetujuan tidak lagi sah".
7. `cabut` & `cabut_ttd` → kolom dikosongkan, halaman kembali ke state awal.
8. Render halaman (kepsek, admin, operator) bersih dari warning; modal
   ber-`modal-root` terbuka/tertutup; badge & tabel terverifikasi di browser.
9. Seed `database/db_spmb_mualimku.sql` diperbarui mengikuti skema baru dan
   diverifikasi identik dengan DB live (kolom & nama foreign key) lewat impor
   ke DB sementara.
