# Fungsi Role Operator — SPMB SMP Muhammadiyah 5 Kota Tangerang

Dokumen ini memetakan seluruh fungsi yang dimiliki role **operator** (19 menu,
4 grup) beserta status fungsionalitasnya.

**Tanggal audit:** 28 Okt 2026
**Cakupan:** `app/operator/*`, `app/staf/ganti-password.php`, query & CMS pendukung

---

## Ringkasan Status

| Status | Jumlah keterangan |
|---|---|
| ✅ Berfungsi penuh | 17 |
| ⚠️ Berfungsi sebagian | 10 |
| ❌ Tidak berfungsi | 5 |
| 🚧 Belum diimplementasi | 6 |

**Status ini sudah mencerminkan perbaikan yang dilakukan** (lihat bagian
*Sudah Diperbaiki* di bawah) — angkanya lebih baik daripada audit awal
(13/13/13/7) karena 16 butir sudah tuntas. Penambahan setelah audit
(sub-baris status pembayaran di dashboard, filter **Terarsip** + alur
Arsipkan/Pulihkan di Data Pendaftar, menu **Petugas & Hak Akses** yang berfungsi
penuh) belum dihitung ulang pada tabel ringkasan di atas.

---

## Grup 1 — Operasional SPMB

### 1. Dashboard Operasional — `app/operator/index.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Kartu "Akun Siswa Terbit" (aktif/total + persentase) | ✅ |
| 2 | Badge status WhatsApp Server | ✅ *diperbaiki* — kini dari `fonnte_token`, bukan hardcode `42ms` |
| 3 | Kartu "Pesan Terkirim Bulan Ini" & "Tingkat Pengiriman" | ✅ *diperbaiki* — kini dihitung dari tabel `wa_log`, bukan angka karangan `1.250/5.000` & `99.2%` |
| 4 | Status gateway Fonnte (Aktif / Belum Dikonfigurasi) | ✅ |
| 5 | Tab Akun & WA / Jadwal / Hasil Seleksi / Rombel | ✅ |
| 6 | Generate akun (semua + terpilih) | ✅ form → `akun.php` |
| 7 | Bulk action baris terpilih (toolbar muncul saat ada centang) | ✅ |
| 8 | Tombol "Kirim Blast WA Massal" | ✅ *diperbaiki* — kini membuka `akun.php` (logika blast sudah ada di sana) |
| 9 | Tombol "Panduan SOP SPMB" | ✅ *diperbaiki* — kini ke `panduan.php` (semula `href="#"`) |
| 10 | Edit / Kirim WA jadwal (tab 2) | ✅ *diperbaiki* — kini link ke `jadwal.php` |
| 11 | Edit / Hapus rombel (tab 4) | ✅ *diperbaiki* — kini link ke `kelas.php` |
| 12 | Tombol "Setujui & Kirim Kredensial" di modal detail | ✅ *diperbaiki* — kini ke `berkas.php` |
| 13 | Footer tabel (keterangan jumlah baris) | ✅ *diperbaiki* — semula tombol halaman 1/2/3/…/36 tanpa `href` (murni hiasan) |
| 14 | **Input nilai CBT/BTQ/wawancara di tab 3** | ❌ Input tanpa `name`, tanpa `<form>`, tanpa tombol simpan → murni hiasan. Jalur nyata ada di `seleksi.php` |

### 2. Data Pendaftar — `app/operator/pendaftar.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Pencarian server-side (nama / no. pendaftaran / NISN / WA) | ✅ |
| 2 | Filter status verifikasi | ✅ |
| 3 | Ringkasan jumlah per status | ✅ |
| 4 | Link export CSV | ✅ |
| 5 | Link antrean verifikasi | ✅ |
| 6 | Filter **Tampilkan**: Pendaftar aktif / Terarsip / Semua | ✅ |
| 7 | **Arsipkan Pendaftaran** (soft archive + alasan wajib, modal di halaman) | ✅ — akun siswa `is_active=0`, hilang dari semua antrean/statistik/export/cek-status/tautan bayar |
| 8 | **Pulihkan** pendaftar terarsip | ✅ — kolom arsip dibersihkan, akun diaktifkan lagi |
| 9 | **Pagination & sorting** | 🚧 Semua baris dimuat tanpa `LIMIT` |

### 3. Verifikasi Kelengkapan Formulir — `app/operator/berkas.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Antrean berkas + pratinjau dokumen | ✅ |
| 2 | Setujui / Tolak berkas + alasan | ⚠️ Guard hanya menerima tahap `BERKAS_DISUBMIT`, sehingga selalu gagal untuk `BERKAS_REVISI` (`includes/queries/dokumen.php:368-373`) |
| 3 | Retensi berkas pribadi | ✅ |

### 4. Verifikasi Data Calon Siswa — `app/operator/verifikasi-data.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Antrean biodata + cek kelengkapan (dihitung di server) | ✅ |
| 2 | Verifikasi & Kunci data | ✅ |
| 3 | Minta Perbaikan / Kembalikan | ⚠️ `catatan` **wajib di server** tetapi textarea **tidak `required`** → klik bisa gagal tanpa aba-aba |
| 4 | Alur pengajuan koreksi (MENUNGGU → DIPROSES → DISETUJUI/DITOLAK) | ✅ — migrasi `20261026_fase13_koreksi_data.sql` dijalankan 2026-10-28 (tabel `pengajuan_koreksi` + kolom verifikasi data sudah ada) |
| 5 | Guard pendaftar terarsip | ✅ — `verifikasi_data_set()` menolak dengan pesan "pulihkan dulu dari Data Pendaftar" |

### 5. Notifikasi — `app/operator/notifikasi.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Badge jumlah belum dibaca (di topbar, polling 60 dtk) | ✅ |
| 2 | Tandai baca (per item & semua) | ✅ |
| 3 | Pagination + link per tipe notifikasi | ✅ |
| 4 | Toast untuk pesan galat | ⚠️ `flash('galat')` salah tipe → toast hijau (sukses) untuk pesan error |
| 5 | **Filter / pencarian notifikasi** | 🚧 Selalu `ORDER BY created_at DESC` tanpa `WHERE` |

---

## Grup 2 — Seleksi & Penerimaan

### 6. Jadwal Ujian — `app/operator/jadwal.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Tambah jadwal tes | ✅ |
| 2 | Edit inline (nama, tanggal, jam, mode, ruangan, kapasitas, status) | ✅ *diperbaiki* — form baris sempat kosong sehingga Simpan = nihil |
| 3 | Kirim notifikasi WA jadwal ke wali | ✅ *diperbaiki* — sama |
| 4 | Hapus jadwal (dijaga bila sudah dipakai seleksi) | ✅ *diperbaiki* — sama |

### 7. Peserta Ujian — `app/operator/peserta-ujian.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Atur Peserta Otomatis (transaksi + `FOR UPDATE`, cek kapasitas, nomor peserta unik) | ✅ **Paling rapi di seluruh aplikasi** |
| 2 | Tambah peserta manual | ✅ |
| 3 | Hapus peserta | ✅ |
| 4 | Kirim WA jadwal ke peserta | ✅ |

### 8. Data & Status Tes — `app/operator/seleksi.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Input nilai manual (CBT/BTQ/wawancara) + nilai akhir otomatis | ✅ |
| 2 | Impor CSV nilai + pratinjau | ⚠️ Nilai di luar 0–100 **di-clip diam-diam** jadi 100/0, bukan ditandai galat (`seleksi.php:74-76`) |
| 3 | Ambang kelulusan | ⚠️ Tidak pernah **disimpan** (selalu reset ke 70) dan tidak dipakai server-side |
| 4 | Status LULUS / TIDAK_LULUS | ✅ |

### 9. Pengumuman & Status Penerimaan — `app/operator/pengumuman.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Umumkan Sekarang | ✅ |
| 2 | Jadwalkan Pengumuman | ✅ |
| 3 | Batalkan Pengumuman | ✅ |
| 4 | WA massal + progress bar + retry (benar-benar terkirim ke Fonnte) | ⚠️ **Tanpa dedupe** → klik 2× = WA ganda; tanpa guard server "sudah diumumkan" |

---

## Grup 3 — Pasca Penerimaan

### 10. Penempatan Kelas — `app/operator/penempatan.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Kartu keterisian tiap kelas | ✅ *diperbaiki* — sumber kini `penempatan_kelas` |
| 2 | Pindah manual (kuota dicek di server, opsi kelas penuh `disabled`) | ✅ |
| 3 | Tempatkan Semua (penempatan otomatis) | ✅ |
| 4 | Hapus penempatan | ✅ |
| 5 | Konsistensi dengan halaman Kelas & ekspor rekap | ✅ *diperbaiki* — dulu membaca `pendaftar.kelas_id` yang tak pernah ditulis aplikasi |

### 11. Manajemen Kelas — `app/operator/kelas.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Tambah kelas + kuota + wali + status aktif | ✅ |
| 2 | Edit kelas (inline) | ✅ *diperbaiki* — form baris sempat kosong |
| 3 | Hapus kelas | ✅ *diperbaiki* — dulu kelas berpenghuni bisa dihapus dan `ON DELETE CASCADE` menghapus penempatan siswa diam-diam |
| 4 | Kolom "Terisi" & progress bar | ✅ *diperbaiki* — dulu membaca kolom yang tak pernah ditulis |

---

## Grup 4 — Pengaturan Sistem

### 12. Pendaftaran Manual — `app/operator/pendaftar-baru.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Form siswa + orang tua | ✅ |
| 2 | Validasi NISN 10 digit & WA `628xx` | ✅ |
| 3 | INSERT dalam transaksi | ✅ |

### 13. Jenis Dokumen — `app/operator/jenis-dokumen.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | CRUD berkas persyaratan (kode, format, wajib, urutan) | ✅ |
| 2 | Toggle aktif/nonaktif | ⚠️ Checkbox Aktif **hardcode `checked`** → jenis *Nonaktif* bisa dikaktifkan kembali diam-diam (`jenis-dokumen.php:231-233`) |

### 14. Pengaturan Sistem (CMS) — `app/operator/pengaturan.php`

Satu `<form>` berisi **11 section isian + 1 kartu tautan** — semua isian **tersimpan & terpakai**:

| # | Section | Status |
|---|---|---|
| 1 | Identitas & Kontak Institusi | ✅ |
| 2 | Aset Visual & Warna Tema (unggah logo, banner, **color picker**) | ✅ warna → `head.php` |
| 3 | Running Text / Ticker | ⚠️ Salah kunci baca: `ticker_header` vs `ticker` → selalu kosong di 2 halaman |
| 4 | Tarif / Biaya: biaya formulir, SPP, estimasi **Biaya Pendaftaran** (+ nama labelnya) & **teks program beasiswa** | ✅ label biaya dipakai seluruh aplikasi lewat `label_biaya_pendaftaran()`; `teks_beasiswa` kosong = kartu beasiswa tidak tampil di halaman publik |
| 5 | Rekening Bank | ✅ dipakai di footer & halaman bayar |
| 6 | Gelombang Pendaftaran | ✅ |
| 7 | Kredensial WhatsApp Fonnte | ✅ Token diuji otomatis ke Fonnte (`/device`, tanpa kirim WA) saat form disimpan + badge sumber token (`.env`/DB) — lihat catatan di bawah |
| 8 | Midtrans | ✅ |
| 9 | Metode Bayar + S&K | ✅ |
| 10 | Notifikasi | ⚠️ Notifikasi **email** = stub, selalu gagal (field juga `disabled`) |
| 11 | Field "tulis-saja" (`profil_singkat`, `deskripsi_sekolah`, `tagline`, `deskripsi_gelombang`) | ⚠️ Tersimpan tapi **tak pernah dibaca** |
| — | **Unggah favicon** | 🚧 `favicon_url` dipakai `head.php` tapi tidak ada input-nya |
| 12 | Kartu **Petugas & Hak Akses** (tautan saja) | ✅ Nama/username/password petugas tidak ikut form ini — dikelola di halaman `petugas.php` (tabel `users`) |

Catatan Section 7 (Fonnte) — perilaku sejak 2026-10-28:
- Prioritas baca token: **`.env` (FONNTE_TOKEN) → DB**. Karena itu nilai yang diketik di CMS
  tidak terlihat pengaruhnya bila `.env` sudah terisi; halaman kini menampilkan kotak
  "Mode .env aktif" + sidik jari token efektif (`••••xxxx`, 4 karakter terakhir).
- Setiap submit formulir melakukan uji token ke `https://api.fonnte.com/device` — respons
  success/error flash berisi device, nama, kuota, dan tanggal kedaluwarsa (atau alasan penolakan).
  Endpoint ini **tidak** mengirim pesan WhatsApp.
- Input rahasia (token Fonnte, Server/Client Key Midtrans) tidak lagi dicetak ke atribut `value=`
  dan **kosong = jangan ubah**; penghapusan harus eksplisit lewat checkbox *Hapus token*.
  Sebelumnya submit mana pun dengan field tersebut kosong menghapus token yang tersimpan.

### 15. Retensi Data — `app/operator/retensi.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Pengaturan retensi + kandidat hapus (pratinjau 3 kategori) | ✅ |
| 2 | Eksekusi hapus (nyata: `@unlink` + NULL-kan) dengan konfirmasi ganda | ✅ |
| 3 | Riwayat log | ✅ |
| 4 | Toggle `retensi_aktif` | ⚠️ Hanya mengunci **CLI**, hapus manual lewat web tetap jalan |

### 16. Kesiapan Rilis — `app/operator/kesiapan.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Checklist 18 pemeriksaan nyata (bukan hardcode) + skor % | ✅ **Read-only sungguhan** — tidak mengubah data aplikasi (hasil uji token Fonnte hanya di-cache 10 menit di sesi) |
| 2 | Antrean operasional | ✅ |
| 3 | Pemeriksaan **nomor pengirim WA = nomor panitia** (lewat Fonnte `/device`) | ✅ — kuning saat nomor perangkat Fonnte berbeda dari `kontak_wa_panitia`, kosong/belum ter-connect, atau token belum diisi; tautan "periksa ulang WA" (`?segarkan-wa=1`) membuang cache |

### 17. Panduan & SOP — `app/operator/panduan.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | 17 seksi panduan per peran, konten nyata | ✅ |
| 2 | Perintah CLI konkret | ✅ |
| 3 | Filter status di rekap export | ⚠️ Dokumen mengklaim DITOLAK tersaring — sebenarnya **tidak** (`panduan.php:304`) |

### 18. Ganti Password — `app/staf/ganti-password.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Password lama / baru + konfirmasi | ✅ |
| 2 | Rate limit + regenerasi sesi | ✅ |

### 19. Petugas & Hak Akses — `app/operator/petugas.php`

Menu grup **Pengaturan Sistem** (kunci `petugas`). Menyimpan di tabel `users`,
bukan `pengaturan_sekolah` — hanya role `operator`/`admin` yang boleh membuka.

| # | Fungsi | Status |
|---|---|---|
| 1 | Daftar akun staf (non-siswa) + status Aktif/Nonaktif & Password Belum disetel/Wajib diganti/Tersetel | ✅ |
| 2 | Ubah **nama petugas** & jabatan per akun (yang tampil di header, menu, log) | ✅ |
| 3 | Ubah username petugas (validasi format 4-30 huruf kecil + unik) | ✅ |
| 4 | Reset password petugas lain → password sementara, `wajib_ganti_password = 1` | ✅ — petugas dipaksa ke `app/staf/ganti-password.php` pada login berikutnya |
| 5 | Tambah petugas baru (nama, jabatan, role, username, password sementara) | ✅ — beberapa akun per role diperbolehkan |
| 6 | Nonaktifkan / aktifkan akun (soft, data tetap ada) | ✅ — ditolak bila akun tersebut operator aktif terakhir |
| 7 | Pengaman akun sendiri | ✅ — hanya nama & jabatan yang bisa diubah; username/password/nonaktif akun sendiri dikunci (UI disembunyikan + divalidasi ulang di server) |

Lapisan query: `includes/queries/petugas.php` (`petugas_semua`, `petugas_buat`,
`petugas_ubah_identitas`, `petugas_set_password`, `petugas_set_aktif`,
`petugas_username_valid`, `petugas_password_valid`).
Untuk kondisi awal di mana **belum ada** akun staf yang bisa login (semua
`!LOCKED`), pemulihan tetap lewat `php tools/set-password.php` (CLI) atau
`tools/set-password-web.php`.

---

## Fungsi Pendukung (bukan menu, tapi diakses operator)

| Halaman | Fungsi | Status |
|---|---|---|
| `app/operator/akun.php` | Generate akun + kirim kredensial WA (endpoint POST) + blast WA massal | ✅ |
| `app/operator/export.php` | Ekspor CSV (`?rekap=pendaftar\|seleksi\|pembayaran`) — BOM UTF-8, pemisah `;`, guard formula-injection | ⚠️ Jenis `pembayaran` tidak punya tombol di mana pun |
| `includes/partials/topbar.php` | Tombol notifikasi & bantuan | ⚠️ Kini memberi toast (semula klik hening total); belum jadi link karena topbar lintas role |
| Kartu Peserta (portal siswa) | Cetak kartu peserta | ⚠️ Tidak ada di halaman operator; print CSS cuma 4 baris → cetak ikut sidebar/topbar |

---

## Sudah Diperbaiki (sesi kerja ini)

| # | Perbaikan | Bukti |
|---|---|---|
| 1 | **4 aksi baris tabel** (jadwal: ubah/WA/hapus; kelas: ubah/hapus) — `<form>` sebagai anak `<tr>` membuat form kosong & tombol tanpa form owner | Terbukti empiris di browser: `formKosong:true`, `submitTerima:false`. Kini uji end-to-end HTTP **TERSIMPAN** |
| 2 | **Sumber data `terisi`** → `penempatan_kelas` (3 titik: `queries/kelas.php`, `cms.php::rombel`, JOIN di `pendaftar`/`siswa`/`export`) | `pendaftar.kelas_id` hanya ditulis seed, tak pernah oleh aplikasi |
| 3 | **Proteksi hapus kelas** → cek `penempatan_kelas` | Kini kelas terisi **DITOLAK**, kelas kosong boleh dihapus |
| 4 | **Font Material Symbols** dari `@20..48,100..700,0..1,-50..200` → `@24,400,0,0` | 3.912 KB → 316 KB (**−92%**) |
| 5 | **9 tombol mati** — handler JS hanya mengenali `form[data-demo]`; `button`/`a` `data-demo` klik = nihil total | Ditambahkan handler; 6 di antaranya dijadikan **link nyata** |
| 6 | **Angka dashboard menyesatkan** — badge `42ms`, `1.250/5.000`, `99.2%`, pagination halaman 2/3/36 palsu | Kini dari `wa_log` & `fonnte_token`; footer jujur + link ke `pendaftar.php` |
| 7 | **39 kelas Tailwind hilang** — tidak ada pipeline build | Kini **tinggal 2** (`row-check`, `modal-root` = penanda JS, bukan styling) |
| 8 | **Kelas tidak valid** `text-ink-600`/`text-ink-800` (tidak ada di config) & `bg-white!` (sintaks v4) | Ditambah `ink:600/800` ke config; `bg-white!` → `!bg-white` |
| 9 | **Sub-baris status pembayaran** di kolom "Status Akun & Kredensial" dashboard | Ditolak/diterima bendahara sebelumnya tidak terlihat operator; kini `op_bayar_ringkas()` menampilkan jenis + status bayar per pendaftar |
| 10 | **Alur Arsipkan Pendaftaran** (soft archive) | `pendaftar_arsip()`/`pendaftar_pulih()` + kolom `arsip_at/alasan/oleh`; 12 modul query & halaman disaring `arsip_at IS NULL`; akun siswa nonaktif sementara |
| 11 | **Migrasi Fase 13 tertunda di DB lokal** | `20261026_fase13_koreksi_data.sql` dijalankan 2026-10-28 (setelah `mysqldump` ke `database/backup/`) — tabel `pengajuan_koreksi` + kolom verifikasi data kini ada |
| 12 | **Semua kirim WA gagal** ("Fonnte: invalid/empty body value") | Penyebabnya field `typing=off` pada payload `wa_kirim()` — Fonnte menolak nilai itu (teruji field-per-field dengan target tidak valid, 0 pesan terkirim). Kini `typing=false`; `id`/`reason` respons yang berupa array diratakan lewat `wa_teks()` |
| 13 | **Input token Fonnte di CMS terasa "tidak terjadi apa-apa"** | Token diuji otomatis ke `/device` tiap simpan + badge/sidik jari sumber token; input kosong tidak lagi menghapus token tersimpan (sebelumnya `'' → NULL`), penghapusan kini eksplisit lewat checkbox |

---

## Sisa Pekerjaan

### ❌ Tidak berfungsi (5)

1. Input nilai tab 3 dashboard — tanpa `name`/`<form>`/tombol simpan
2. Verifikasi berkas untuk tahap `BERKAS_REVISI` (guard terlalu sempit)
3. Ambang kelulusan — tak pernah disimpan & tak dipakai server
4. Toast pesan galat di Notifikasi (tipe `flash` salah)
5. Checkbox Aktif di Jenis Dokumen (hardcode `checked`)

### ⚠️ Berfungsi sebagian (10)

1. Impor CSV nilai — nilai di-clip diam-diam
2. WA massal pengumuman — tanpa dedupe
3. Minta Perbaikan / Kembalikan — `catatan` wajib tapi UI tak `required`
4. Ticker (running text) — salah kunci baca
5. Field CMS "tulis-saja" — tersimpan, tak pernah dibaca
6. Retensi toggle — hanya mengunci CLI
7. Notifikasi email — stub, selalu gagal
8. Filter status rekap export — tak jalan
9. Export `pembayaran` — tanpa tombol
10. Kartu Peserta — tak ada di halaman operator

### 🚧 Belum diimplementasi (4)

1. Pagination & sorting server-side (`pendaftar`, `verifikasi-data`, `berkas`) — yang tersedia sekarang paginasi sisi-klien di `app.js`
2. Filter/pencarian notifikasi
3. Unggah favicon di CMS (field-nya sudah dipakai `head.php`, input-nya belum ada)
4. 2 kelas penanda JS (`row-check`, `modal-root`) — sengaja dibiarkan tanpa CSS

> Sudah dituntaskan setelah audit: **uji-coba token Fonnte** (otomatis setiap simpan,
> lihat Section 14) dan **tombol notifikasi & bantuan di topbar** (tautan per-role).

---

## ⚠️ Temuan Baru (belum ditangani)

| Temuan | Dampak |
|---|---|
| **`assets/img/uploads/*.jpg` ikut ter-commit** (2,4 MB, hasil unggah CMS) | Keputusan pemilik repo — bisa dipindah ke `.gitignore` bila tak ingin ikut |

---

*Cara memperbarui dokumen ini setelah perubahan: jalankan
`php tools/cek-statis.php`, render halaman operator, lalu cocokkan status tiap baris.*
