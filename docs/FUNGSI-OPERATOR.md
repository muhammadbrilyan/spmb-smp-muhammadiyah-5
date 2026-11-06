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
| 12 | Tombol "Setujui & Kirim Kredensial" di modal detail | ✅ *diperbaiki* — kini ke tab Berkas pada `verifikasi.php` |
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

### 3. Verifikasi Berkas — `app/operator/verifikasi.php?tab=berkas` (dahulu `berkas.php`)

| # | Fungsi | Status |
|---|---|---|
| 1 | Antrean berkas + pratinjau dokumen | ✅ |
| 2 | Setujui / Tolak berkas + alasan | ✅ sejak 2026-11-02 — guard menerima `BERKAS_DISUBMIT` **dan** `BERKAS_REVISI`, jadi beberapa berkas bisa ditolak pada pemeriksaan yang sama |
| 3 | Kirim Pesan Revisi via WA (tombol di ruang kerja berkas) | ✅ baru 2026-11-02 — pemberitahuan ke wali tidak perlu menunggu seluruh berkas diputus; `berkas_wa_revisi()` mengirim notifikasi portal + WA orang tua, dibatasi 5×/jam per pendaftar |
| 4 | Retensi berkas pribadi | ✅ |

### 4. Verifikasi Data — `app/operator/verifikasi.php?tab=data` (dahulu `verifikasi-data.php`)

| # | Fungsi | Status |
|---|---|---|
| 1 | Antrean biodata + cek kelengkapan (dihitung di server) | ✅ |
| 2 | Verifikasi & Kunci data | ✅ |
| 3 | Minta Perbaikan / Kembalikan | ✅ sejak 2026-11-02 — tombol menolak dikirim sebelum catatan ditulis (pengawasan di peramban + tetap diverifikasi server), catatan tampil di portal siswa sebagai banner "Panitia meminta perbaikan data", formulir dibuka kembali, dan kirim ulang memakai tombol yang sama |
| 4 | Alur pengajuan koreksi (MENUNGGU → DIPROSES → DISETUJUI/DITOLAK) | ❌ **dihapus total 2026-11-03.** Sisi siswa ditutup 2026-11-02, lalu tab **Koreksi Data** di halaman ini, blok "Pengajuan Koreksi Data" pada ruang kerja pendaftar, kolom/chip di Data Pendaftar, serta lapisan `includes/queries/koreksi.php` (+ `require_once`-nya) ikut dihapus. Jalur perbaikan data kini hanya butir 3 (Minta Perbaikan). Tabel `pengajuan_koreksi` **tidak** di-drop — baris lama tinggal arsip, tidak ada UI yang membacanya lagi |
| 5 | Guard pendaftar terarsip | ✅ — `verifikasi_data_set()` menolak dengan pesan "pulihkan dulu dari Data Pendaftar" |

### 5. Notifikasi — `app/operator/notifikasi.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Badge jumlah belum dibaca (di topbar, polling 60 dtk) | ✅ |
| 2 | Tandai baca (per item & semua) | ✅ |
| 3 | Pagination + link per tipe notifikasi | ✅ — termasuk tipe baru `PENDAFTAR_BARU` yang tombolnya membuka Data Pendaftar (2026-11-06) |
| 4 | Toast untuk pesan galat | ⚠️ `flash('galat')` salah tipe → toast hijau (sukses) untuk pesan error |
| 5 | **Filter / pencarian notifikasi** | 🚧 Selalu `ORDER BY created_at DESC` tanpa `WHERE` |
| 6 | Kanal WhatsApp untuk pemicu panitia | ✅ sejak 2026-11-06 — `notif_pemicu_wa()` (`includes/notifikasi.php`) mendaftar `BUKTI_BAYAR_MASUK`, `PEMBAYARAN_GATEWAY_MASUK`, `PEMBAYARAN_GATEWAY_LUNAS`, **`PENDAFTAR_BARU`, `VERIFIKASI_DATA`, `BERKAS_DISUBMIT`**; kiriman operasional dibatasi 3 pesan/jam per pendaftar (`rate_limit_cek('notif_wa_operasional', 3, 60, "tipe:pendaftarId")`) supaya tombol yang diklik berulang tidak menghabiskan kuota Fonnte, uang masuk tetap tidak dibatasi |

---

## Grup 2 — Seleksi & Penerimaan

### 6. Jadwal Ujian — `app/operator/jadwal.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Tambah jadwal tes | ✅ *dirombak* — tidak lagi form 8 kolom di atas daftar, tapi satu modal bersama |
| 2 | Ubah jadwal lewat modal (nama, tanggal, jam, mode, ruangan, kapasitas, status) | ✅ *dirombak* — dahulu inline-edit 9 kolom dengan `min-w-[960px]`; scrollbar samping & jumlah peserta mudah terhapus dari simpan |
| 3 | Jumlah peserta & status tersinkron dari Peserta Ujian | ✅ nilai ikut terbawa saat menyimpan (field tersembunyi), tidak ditimpa jadi 0 |
| 4 | **Umumkan Semua Jadwal** — satu pesan WA berisi seluruh jadwal Terjadwal + satu notifikasi portal per siswa | ✅ *dirapikan* — `jadwal_umumkan_semua()` kini menyusun pesan lalu menyerahkan pengirimannya ke `jadwal_kirim_ke_wali()`, fungsi yang sama dipakai tombol per kegiatan; `ref_id` sidik jari susunan jadwal sehingga klik ganda tidak mengirim ulang |
| 5 | Kirim notifikasi WA jadwal ke wali (per kegiatan) | ✅ **diperbaiki total** (2026-11-06) — dahulu selalu 0 penerima karena hanya membaca `seleksi.jadwal_id`, padahal penjadwalan ditulis ke `peserta_ujian`; kini kedua sumber dibaca untuk **satu kartu itu saja**, Lokasi memakai **Ruangan** yang diisi operator, dan tiap wali juga menerima **notifikasi lonceng portal** (`notifikasi_siswa` tipe `JADWAL_UJIAN`) — bukan WA saja |
| 6 | Hapus jadwal (dijaga bila sudah dipakai seleksi) | ✅ |
| 7 | Ringkasan jumlah jadwal per status (Draft / Terjadwal / Selesai) | ✅ |
| 8 | Bagian **Peserta Ujian** menyatu di halaman yang sama | ✅ sejak 2026-11-02 — letaknya di bawah pengaturan jadwal; `app/operator/peserta-ujian.php` menjadi pengalih 302 ke `jadwal.php#peserta` dan menu sidebar "Peserta Ujian" dihapus (sidebar operator 15 → 14 item) |

### 7. Peserta Ujian — bagian dari `app/operator/jadwal.php` (dahulu `peserta-ujian.php`)

| # | Fungsi | Status |
|---|---|---|
| 1 | Atur Peserta Otomatis (transaksi + `FOR UPDATE`, cek kapasitas, nomor peserta unik) | ✅ **Paling rapi di seluruh aplikasi** — sejak 2026-11-06 yang dilewati hanya calon yang **sudah ada di kartu ini**, bukan calon yang pernah masuk kartu mana pun; jadi calon yang absen di satu kegiatan bisa dijaring ulang pada jadwal susulan tanpa menghapus barisnya lebih dulu |
| 2 | Tambah peserta manual | ✅ satu dropdown per jadwal; penolakan "sudah terdaftar" berlaku per kartu (`pendaftar_id` + `jadwal_id`), daftar isi hanya menampilkan calon yang belum ada di kartu ini dengan catatan "(sudah: KODE)" bila ia memang ikut kegiatan lain |
| 3 | Hapus peserta | ✅ tahap calon hanya dipundahkan ke `BERKAS_TERVERIFIKASI` bila ia tinggal tidak punya jadwal sama sekali |
| 4 | Kirim WA jadwal ke peserta | ✅ *diperbaiki* — lihat §6 baris 5: satu kartu = satu pesan WA + satu lonceng portal, penerima kartu itu saja |
| 5 | Semua jadwal Terjadwal tampil sekaligus (tanpa dropdown pilih jadwal) | ✅ kartu bertumpuk, berisi meta kegiatan, kapasitas & bar terpakai, sisa kuota, peringatan peserta tanpa nomor WA orang tua |

> **2026-11-01 — halaman dirombak.** Dropdown "Jadwal Tes" dihapus: yang bisa
> dikelola hanyalah jadwal berstatus **Terjadwal**, karena hanya status itu yang
> dikirim ke calon siswa lewat *Umumkan Semua Jadwal* (§6). Jadwal Draft/Selesai
> kini hanya disebut sekilas di catatan bawah beserta jumlah pesertanya, supaya
> operator tidak menempatkan peserta pada jadwal yang tidak akan sampai ke siswa.
> Tabel mendatar `min-w-[760px]` (harus digulir ke samping di ponsel) diganti
> daftar peserta vertikal. Tombol dinonaktifkan bila tidak ada yang bisa
> dilakukan: *Atur Peserta Otomatis* saat kapasitas penuh, *Kirim WA Jadwal* saat
> peserta masih kosong.
>
> **2026-11-02 — digabung ke halaman Jadwal Ujian.** Seluruh kartu peserta kini
> dirender di `app/operator/jadwal.php` bagian bawah (`#peserta`), satu form
> dengan jadwalnya; tidak ada lagi pindah halaman saat mengurus peserta.
> Halaman lama tinggal jadi pengalih, jadi tautan lama tetap hidup.
>
> **2026-11-06 — satu calon boleh ikut beberapa kegiatan.** Sebelumnya
> `peserta_ujian` punya `UNIQUE (pendaftar_id)`, sehingga seorang calon hanya
> bisa menempati satu kartu **seumur hidupnya**: calon yang absen di Tes Baca
> & Tulis tidak bisa dimasukkan ke kartu Wawancara sebelum barisnya dihapus,
> dan *Atur Peserta Otomatis* diam-diam menjadikannya 0 peserta. Batas itu
> dilepas lewat `database/migrations/20261106_peserta_ujian_multi_jadwal.sql`
> (indeks baru `uq_peserta_jadwal (pendaftar_id, jadwal_id)` dibuat lebih dulu
> agar FK tetap punya indeks pendukung, lalu `uq_peserta_pendaftar` dihapus).
> Sejak itu:
> - *Atur Peserta Otomatis*, *Tambah manual*, dan daftar kandidat menyaring
>   **per kartu**; syarat tahap terpusat di `peserta_tahap_siap_jadwal()`
>   (`BERKAS_TERVERIFIKASI`, `JADWAL_DITETAPKAN`, `NILAI_TERINPUT`, `DIUMUMKAN`)
>   supaya calon yang sudah punya nilai tidak dipundahkan tahapnya, dan
>   `UPDATE tahap` diberi syarat `AND tahap = 'BERKAS_TERVERIFIKASI'`.
> - Tile statistik "Belum dijadwalkan" menjadi **"Belum punya jadwal"** dan
>   dihitung dari `tanpa_jadwal` (belum pernah masuk kartu mana pun), bukan dari
>   selisih kapasitas — angka yang dahulu menyesatkan karena kandidat yang
>   sudah lulus seleksi keluar dari daftar.
> - Tombol per kartu *Kirim WA Jadwal* memakai pengirim yang sama dengan
>   *Umumkan Semua Jadwal* (`jadwal_kirim_ke_wali()`), sehingga isi tabel
>   kegiatan di pesan wali dan di kartu portal tidak pernah berbeda, dan
>   pemakai tidak perlu lagi mengklik tombol gabungan untuk mendapat lonceng
>   portal. Konsekuensi yang diterima: satu wali bisa punya dua lonceng jadwal
>   (pengumuman gabungan dan per kegiatan) karena `ref_id`-nya beda; klik ganda
>   pada kartu yang sama tetap ditahan (`lewati`).
> - Di portal siswa, "Kartu Ujian" = kegiatan terdekat yang belum lewat
>   (`peserta_kartu_siswa()`) dan tab **Agenda Tes** hanya daftar kegiatan milik
>   siswa itu sendiri (`jadwal_agenda_siswa()`); bila ia belum ditempatkan di
>   kartu mana pun, Agenda masih menampilkan seluruh jadwal sekolah sebagai
>   pengumuman umum seperti sebelumnya.

### 8. Data & Status Tes — `app/operator/seleksi.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Input nilai manual (3 kolom nilai) + nilai akhir otomatis | ✅ sejak 2026-11-02: hanya kolom hasil tes yang tampil (kolom **Jadwal Tes** dihapus — `jadwal_id` lama dipertahankan lewat hidden input), Nilai Akhir dan Keputusan terhitung **langsung saat angka diketik**. ✅ sejak 2026-11-05: judul kolom tidak lagi tertulis CBT/BTQ/Wawancara — diambil dari `jadwal_tes.nama_kegiatan` lewat `seleksi_label_kolom()` (lihat catatan di bawah) |
| 2 | ~~Impor CSV nilai + pratinjau~~ | 🚫 **Dihapus 2026-11-05** — nilai diisi manual saja. Kartu "Impor Nilai CSV", aksi POST `preview_csv`/`impor_csv`, dan `seleksi_impor_nilai()` di `includes/queries/seleksi.php` dihapus (bersamaan dengan hilangnya bug clipping 0–100 yang tercatat di dokumen ini) |
| 3 | Ambang kelulusan | ✅ sejak 2026-11-02: ambang (default **70**) tersimpan di `pengaturan_sekolah.ambang_kelulusan` dan dipakai menghitung keputusan LULUS/TIDAK_LULUS; tombol "Hitung Ulang Otomatis" dihapus karena perhitungan sudah otomatis |
| 4 | Status LULUS / TIDAK_LULUS | ✅ |
| 5 | Pencarian No. Pendaftaran / Nama Calon Siswa | ✅ sejak 2026-11-05 — sisi klien, tanpa reload: mengetik di kotak cari menyembunyikan baris yang tidak cocok. Enter **tidak** mengirim form (guard `keydown`) supaya menekan Enter di kotak cari tidak menyimpan nilai |
| 6 | Urutan baris berdasarkan abjad nama | ✅ sejak 2026-11-05 — `pendaftar_untuk_nilai()` memakai `ORDER BY p.nama_siswa, p.no_pendaftaran`; kolasi `utf8mb4_unicode_ci` membuat "aisyah" tetap masuk di bawah A (case- & accent-insensitive), `no_pendaftaran` hanya pemecah nama yang sama |
| 7 | ~~Filter abjad A–Z (chip huruf)~~ | 🚫 **Dihapus 2026-11-05** — sempat dibuat, lalu dicabut atas permintaan operator karena daftar sudah diurutkan abjad; pencarian No. Pendaftaran / Nama tetap dipertahankan |

> **Judul kolom nilai = nama kegiatan jadwal tes (2026-11-05).**
> `seleksi_label_kolom()` di `includes/queries/seleksi.php` mengambil **3 kegiatan
> pertama** dari `jadwal_tes` (urut `tanggal, jam_mulai, kode`) lalu
> `seleksi_singkat_nama()` memangkasnya: kata generik (`test`, `tes`, `ujian`,
> `seleksi`, `kegiatan`) dibuang, kegiatan gabungan ("Wawancara & Test Kesehatan")
> mengambil bagian sesudah `&`, dan sisanya dipotong pada batas kata. Nama lengkap
> tetap terbaca lewat `title` di `<th>` dan satu catatan kecil di bawah tabel.
> **Slot database tidak berubah** — kolom 1 = `nilai_cbt`, kolom 2 = `nilai_btq`,
> kolom 3 = `nilai_wawancara`; jadi mengganti nama kegiatan hanya mengganti label,
> bukan memindahkan angka. Kegiatan ke-4 dan seterusnya tidak punya kolom sendiri
> (disebutkan di catatan halaman), dan bila jadwal belum ada, label bawaan
> "Baca Tulis / BTQ / Tes Kesehatan" yang dipakai.

### 9. Pengumuman & Status Penerimaan — `app/operator/pengumuman.php`

| # | Fungsi | Status |
|---|---|---|
| 1 | Umumkan Sekarang | ✅ sejak 2026-11-02 — langsung diterbitkan; gerbang persetujuan kepala sekolah dilepas |
| 2 | Jadwalkan Pengumuman | ✅ sejak 2026-11-02 — tidak lagi menunggu persetujuan |
| 3 | Batalkan Pengumuman | ✅ |
| 4 | WA massal + progress bar + retry (benar-benar terkirim ke Fonnte) | ⚠️ **Tanpa dedupe** → klik 2× = WA ganda; tanpa guard server "sudah diumumkan" |
| 5 | Isi pesan WA memuat tautan hasil seleksi | ✅ sejak 2026-11-02 — `pengumuman_pesan_wa()` menyertakan `url_abs('app/siswa/hasil.php')`. Sejak 2026-11-06 teksnya **satu kalimat umum untuk semua penerima** (tanpa nama/No. REG/status) dan bisa disunting di Pengaturan Sistem → *Teks Pesan WhatsApp* kunci `hasil_seleksi` — lihat §14 baris 7b. Rincian LULUS/TIDAK LULUS + kelas tetap hanya di lonceng portal siswa |
| 6 | Nama petugas di sidebar/topbar | ✅ sejak 2026-11-02 — halaman ini sempat memakai `$USER_LOGIN` bawaan `includes/dummy.php` (nama demo) karena lupa mengambil akun yang sedang login |

> **Gerbang persetujuan pernah ada (31 Okt 2026) dan dilepas 2 Nov 2026.**
> Sebelumnya Umumkan & Jadwalkan menolak jalan tanpa persetujuan kepala sekolah
> (sidik daftar hasil dicocokkan). kini operator menerbitkan langsung; menu
> **Persetujuan Hasil** di portal kepsek tetap ada sebagai catatan resmi,
> tetapi tidak mengunci halaman ini.

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
| 1 | Tambah kelas + kuota + wali + status aktif | ✅ formulir dirombak 2026-11-01: 4 field + hint per field, label <em>Aktif</em> kini benar-benar menandai checkbox-nya |
| 2 | Edit kelas | ✅ **Simpan Semua** (2026-11-01) — seluruh baris Daftar Rombel ditautkan ke satu `<form id="rombel-all">` lewat atribut `form` pada tiap input, jadi satu tombol menyimpan semua perubahan (`aksi=simpan_massal`). Tidak ada tombol Simpan per baris lagi; chip "Belum disimpan" + pengingat `beforeunload` menandai edits yang belum dikirim. Bila satu baris tidak valid, **tidak ada** yang tersimpan dan pesan galat diberi awalan kode kelasnya |
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

Satu `<form>` berisi **section 1–11** (termasuk sub-section 3b FAQ, 6b Jalur dan 7b Teks Pesan WhatsApp) — section 10 & 11 hanya kartu tautan; semua isian **tersimpan & terpakai**:

| # | Section | Status |
|---|---|---|
| 1 | Identitas & Kontak Institusi | ✅ sejak 2026-11-02 tambahkan blok **Media Sosial & Website Sekolah**: empat kolom `sosmed_instagram`, `sosmed_tiktok`, `sosmed_youtube`, `website_sekolah` (migrasi `20261102_sosmed_sekolah.sql`). Operator cukup menempel link — `tautan_normal()` menambahkan `https://` bila kurang dan menolak teks yang bukan alamat situs. Kolom terisi = ikon tampil di footer halaman publik (`includes/partials/footer-public.php`) dan membuka tab baru; kolom kosong = ikonnya tidak dirender sama sekali, bukan tautan `#` mati. Nomor WA panitia di footer juga ikut jadi tautan `wa.me` |
| 2 | Aset Visual & Warna Tema (unggah logo, banner, **color picker**) | ✅ warna → `head.php` |
| 3 | Running Text / Ticker | ⚠️ Salah kunci baca: `ticker_header` vs `ticker` → selalu kosong di 2 halaman |
| 3b | **Tanya Jawab (FAQ) Landing** (baru 2026-11-03) | ✅ Section **Pusat Bantuan — Tanya Jawab Seputar SPMB** di `app/spmb/index.php` tidak lagi hardcode. Baris "pertanyaan + jawaban" bisa ditambah/dihapus/urutkan ulang (maks 15, minimal 1), disimpan sebagai JSON `[{"q":…,"a":…}]` di kolom `pengaturan_sekolah.faq_list` (migrasi `20261103_faq_cms.sql`). Dibaca lewat `faq_daftar()` di `includes/cms.php`; kolom masih NULL = contoh bawaan `includes/dummy.php` ($FAQ) yang tampil, dan formulir sudah terisi contoh itu sejak awal — jadi apa yang dilihat operator di layar sama dengan yang akan disimpan. Spasi & baris baru dinormalkan jadi satu paragraf (`faq_json()`), pertanyaan pertama otomatis terbuka di halaman |
| 4 | **Rincian Biaya SPMB**: Biaya Formulir, SPP, **daftar item biaya masuk yang bisa disusun bebas**, **Biaya Masuk** (+ nama labelnya) dan **Teks Promo** | ✅ sejak 2026-11-01, daftar item sejak 2026-11-02. Operator menambah/menghapus item "nama + nominal" (maks 15) — tersimpan sebagai JSON di `pengaturan_sekolah.rincian_biaya` dan **totalnya otomatis menjadi Biaya Masuk** (`estimasi_uang_masuk`), jadi `angsuran_skema()` di `queries/pembayaran.php` tetap satu-satunya sumber angka. Kartu "Tagihan Setelah Lulus" menampilkan `Biaya Masuk − Biaya Formulir = Sisa` yang dibagi 2 angsuran sama besar (`angsuran_*_nominal` tidak dibaca lagi); nilai berubah langsung saat operator mengetik. Daftar dikosongkan = Biaya Masuk diketik manual. Rincian ikut tampil di kartu biaya halaman publik dan di panel **Rincian Biaya & Sisa Pembayaran** portal siswa. `teks_beasiswa` (label barunya **Teks Promo**) kosong = kartu promo tidak tampil di halaman publik; kolom DB-nya tidak diganti |
| 5 | Rekening Bank | ✅ dipakai di footer & halaman bayar |
| 6 | Gelombang Pendaftaran | ✅ |
| 7 | Kredensial WhatsApp Fonnte + blok **Notifikasi WhatsApp Panitia** | ✅ Token diuji otomatis ke Fonnte (`/device`, tanpa kirim WA) saat form disimpan + badge sumber token (`.env`/DB) — lihat catatan di bawah. Blok di bagian bawah kartu ini memegang saklar `notif_wa_aktif` dan **Nomor WA Panitia & Bendahara (dipisah koma**, validasi `+?[0-9]{6,16}` per nomor) — tujuan daftar notifikasi `notif_operator()`: kabar uang masuk **dan** kabar operasional panitia (pendaftar baru, data diajukan, berkas dikirim/dikirim ulang; lihat §5 butir 6), kosong = fallback ke Kontak WhatsApp Panitia bagian 1. Judul blok ini dulu "Notifikasi Transaksi" — nama diganti 2026-11-06 karena tujuannya bukan lagi bendahara saja |
| 7b | **Teks Pesan WhatsApp ke Calon Murid** (baru 2026-11-06) | ✅ Tujuh pesan keluar ke wali siswa tidak lagi tertulis di dalam kode: satu textarea per pesan, disimpan sebagai JSON `{kunci: teks}` di kolom `pengaturan_sekolah.wa_teks` (migrasi `20261106_wa_teks_cms.sql`). Katalog + teks bawaan ada di `includes/queries/wa-teks.php` → `wa_teks_katalog()`; semua builder pesan memanggil `wa_teks_isi(kunci, placeholder)`. **Kosong = teks bawaan kode** (upgrade berikutnya tetap membawa kalimat baru), teks yang sama dengan bawaan tidak ikut disimpan, badge menandai kunci yang sudah diubah, dan maksimal 1200 karakter. Validasi menolak placeholder wajib dihapus (`{tautan_hasil}`, `{langkah}`, `{jadwal_list}`, `{username}`+`{password}`, `{rincian}`, `{daftar}`) dan placeholder typo (`{namaa}`) yang akan terkirim mentah. Placeholder yang nilainya kosong (mis. tahun ajaran / blok rekening nonaktif) membuat **barisnya ikut hilang**, bukan meninggalkan baris kosong |
| 8 | Midtrans | ✅ |
| 9 | Metode Bayar + S&K | ✅ |
| 10 | Kartu **Daftar Berkas Persyaratan** (tautan saja) | ✅ Sejak 2026-11-05 section "Notifikasi Operator" dihapus — pengaturannya pindah ke blok *Notifikasi WhatsApp Panitia* di kartu WhatsApp Gateway (baris 7). Berkas persyaratan dikelola di `app/operator/jenis-dokumen.php` (bagian 13) |
| — | Field "tulis-saja" (`profil_singkat`, `deskripsi_sekolah`, `tagline`, `deskripsi_gelombang`) | ⚠️ Tersimpan tapi **tak pernah dibaca** |
| — | **Unggah favicon** | 🚧 `favicon_url` dipakai `head.php` tapi tidak ada input-nya |
| 11 | Kartu **Petugas & Hak Akses** (tautan saja) | ✅ Nama/username/password petugas tidak ikut form ini — dikelola di halaman `petugas.php` (tabel `users`) |

Catatan Section 7 (Fonnte) — perilaku sejak 2026-10-28:
- Prioritas baca token: **`.env` (FONNTE_TOKEN) → DB**. Karena itu nilai yang diketik di CMS
  tidak terlihat pengaruhnya bila `.env` sudah terisi; halaman kini menampilkan kotak **kuning**
  "Token dari `.env` yang dipakai" + sidik jari (`••••xxxx`, 4 karakter terakhir) kedua token, dan
  menyebutkan secara eksplisit bahwa token yang tersimpan di halaman ini **diabaikan** bila isinya
  berbeda — sebelumnya kotak ini hijau sehingga terbaca "aman" (2026-11-06: notifikasi bukti bayar
  gagal tanpa suara, tanpa satu layar pun yang menunjukkan kegagalannya).
- Baris **Kirim terakhir gagal/terkirim** di bawah Nomor WA Panitia & Bendahara membaca percobaan
  terakhir dari `wa_log` untuk **keenam** pemicu `notif_pemicu_wa()` — `BUKTI_BAYAR_MASUK` /
  `PEMBAYARAN_GATEWAY_*` dan `PENDAFTAR_BARU` / `VERIFIKASI_DATA` / `BERKAS_DISUBMIT`
  (tujuan, tipe, waktu, dan alasan dari Fonnte) — kegagalan kirim tidak lagi hanya terlihat di
  database.
- Setiap submit formulir melakukan uji token ke `https://api.fonnte.com/device` — respons
  success/error flash berisi device, nama, kuota, dan tanggal kedaluwarsa (atau alasan penolakan).
  Endpoint ini **tidak** mengirim pesan WhatsApp. Status perangkat bisa berkedip: `/device`
  pernah mengembalikan `disconnect` sesaat setelah beberapa uji berturut-turut, lalu `connect`
  lagi — periksa ulang sebelum menyimpulkan nomor Fonnte bermasalah.
- Input rahasia (token Fonnte, Server/Client Key Midtrans) tidak lagi dicetak ke atribut `value=`
  dan **kosong = jangan ubah**; penghapusan harus eksplisit lewat checkbox *Hapus token*.
  Sebelumnya submit mana pun dengan field tersebut kosong menghapus token yang tersimpan.

Catatan Section 7b (Teks Pesan WhatsApp) — sejak 2026-11-06:
- Peta pesan → kunci katalog: instruksi bayar registrasi `instruksi_bayar` (`wa_pesan_instruksi()`),
  akun & password portal `kredensial_akun` (`wa_pesan_kredensial()`), pengumuman jadwal tes
  `jadwal_tes` (`jadwal_pesan_pengumuman()` — **salinan yang sama** dipakai lonceng portal siswa),
  hasil seleksi `hasil_seleksi` (`pengumuman_pesan_wa()`), pengingat jatuh tempo `pengingat_biaya`
  (`angsuran_pesan_wa()`), bukti bayar ditolak `bukti_ditolak`, berkas perlu diperbaiki
  `berkas_ditolak` (`dokumen_tolak()` — juga jadi isi lonceng portal).
- Editor selalu menampilkan teks yang **sedang berjalan** (override bila ada, selain itu bawaan),
  jadi operator tidak pernah menatap kotak kosong; cara "kembali ke kalimat terbaru aplikasi"
  adalah mengosongkan kotak lalu Simpan.
- Placeholder diisi oleh pemanggil, bukan oleh form: menambah tag baru di luar katalog ditolak saat
  menyimpan, dan tag dikenal yang tidak diisi pemanggil dikosongkan bersama barisnya
  (`wa_teks_rapikan()`) — tidak ada kurung kurawal yang ikut terkirim ke wali siswa.
- Hasil LULUS / TIDAK LULUS sengaja tidak ditulis di WhatsApp (satu teks umum untuk semua
  penerima); rinciannya hanya di lonceng portal siswa setelah login.

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
| 3 | Pemeriksaan **nomor pengirim WA = nomor panitia** (lewat Fonnte `/device`) | ✅ — kuning saat nomor perangkat Fonnte berbeda dari `kontak_wa_panitia`, kosong/belum ter-connect, atau token belum diisi; tautan "periksa ulang WA" (`?segarkan-wa=1`) membuang cache. Sejak 2026-11-06 perangkat yang **disconnect** ditandai kuning sendiri: sebelumnya nomor yang sama dengan panitia membuat pemeriksaan ini hijau "OK" padahal Fonnte menolak semua kiriman (`request invalid on disconnected device`) |

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

Menu grup **Pengaturan Sistem** (kunci `petugas`). Akun staf disimpan di tabel
`users`; hanya role `operator`/`admin` yang boleh membuka. Sejak 2026-11-03 halaman
ini juga menulis dua kolom `pengaturan_sekolah` — sejak 2026-11-05 yang ditulis
`notif_wa_bendahara` + `notif_wa_aktif` (bukan `notif_wa_operator` lagi) lewat panel
Notifikasi WhatsApp Panitia — satu-satunya pengecualian. Panel ini menulis daftar
tujuan yang sama dengan blok di kartu WhatsApp Gateway, dan sejak 2026-11-06 isinya
memang dipakai bersama: nomor panitia sekaligus nomor bendahara.

| # | Fungsi | Status |
|---|---|---|
| 1 | Daftar akun staf (non-siswa) + status Aktif/Nonaktif & Password Belum disetel/Wajib diganti/Tersetel | ✅ |
| 2 | Ubah **nama petugas** & jabatan per akun (yang tampil di header, menu, log) | ✅ |
| 3 | Ubah username petugas (validasi format 4-30 huruf kecil + unik) | ✅ |
| 4 | Reset password petugas lain → password sementara, `wajib_ganti_password = 1` | ✅ — petugas dipaksa ke `app/staf/ganti-password.php` pada login berikutnya |
| 5 | Tambah petugas baru (nama, jabatan, role, username, password sementara) | ✅ — beberapa akun per role diperbolehkan |
| 6 | Nonaktifkan / aktifkan akun (soft, data tetap ada) | ✅ — ditolak bila akun tersebut operator aktif terakhir |
| 7 | Pengaman akun sendiri | ✅ — hanya nama & jabatan yang bisa diubah; username/password/nonaktif akun sendiri dikunci (UI disembunyikan + divalidasi ulang di server) |
| 8 | Panel **Notifikasi WhatsApp Panitia** (2026-11-03, dialihkan ke bendahara 2026-11-05, dinamai ulang 2026-11-06) | ✅ — mengganti **Nomor WA Panitia & Bendahara** (boleh beberapa, dipisah koma) dan saklar `notif_wa_aktif` tanpa membuka formulir Pengaturan Sistem. Judul panel dan label field mengikuti perluasan rute: daftar ini sekarang menerima kabar uang masuk **dan** kabar operasional (pendaftar baru, data diajukan, berkas dikirim) sehingga bukan lagi murni "transaksi" / "bendahara". Format nomor divalidasi di server dengan aturan yang sama seperti CMS (`+?[0-9]{6,16}` per nomor). Nomor dikosongkan boleh — selama Kontak WhatsApp Panitia terisi, dan pesan flash menyebut nomor tujuan hasil fallback; keduanya kosong dan saklar aktif baru ditolak. Kolom yang ditulis sama persis dengan blok *Notifikasi WhatsApp Panitia* di kartu WhatsApp Gateway — keduanya tetap bisa dipakai, yang terakhir menyimpan menang. Hint di layar menampilkan tujuan efektif saat ini |

Lapisan query: `includes/queries/petugas.php` (`petugas_semua`, `petugas_buat`,
`petugas_ubah_identitas`, `petugas_set_password`, `petugas_set_aktif`,
`petugas_username_valid`, `petugas_password_valid`, `petugas_notif_wa_simpan`,
`petugas_notif_wa_bhawa`).
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
| 14 | **Semua perubahan di Pengaturan Sistem hilang tanpa jejak** (2026-11-01) | Akar: database lokal belum menjalankan `20261031_pengingat_angsuran.sql`, padahal satu `UPDATE` di `pengaturan.php` menulis 49 kolom sekaligus termasuk `pengingat_angsuran_hari`/`_wa` → MySQL menolak `1054 Unknown column` dan **seluruh** kolom ikut batal; pesan saat itu hanya "Gagal menyimpan ke database." Kini: migrasi diterapkan + seed `database/db_spmb_mualimku.sql` disamakan, pesan galat menyebut kolom yang hilang dan arahkan ke `database/migrations/`, dan pemeriksaan 5 `tools/cek-proyek.php` tidak lagi mengandalkan daftar tangan — ia membandingkan **semua** kolom seed vs database (251 kolom) |
| 15 | **Nilai contoh `includes/dummy.php` bocor ke database** | Formulir CMS dulu diisi dari `$PENGATURAN` (dummy ditimpa DB). Kolom yang kosong di DB ikut terganti alamat/rekening/telepon contoh setiap kali operator menekan Simpan, sehingga perubahan kelihatan "kembali sendiri". Kini lewat `cms.php::pengaturan_baris()` (baris mentah database) — kolom yang dikosongkan tersimpan NULL. Kolom gambar tetap dari `$PENGATURAN` karena nilainya sudah dinormalisasi menjadi URL absolut |
| 16 | Sebutan **"Kampus"** pada teks yang dilihat pengguna | `includes/dummy.php` langkah 3, badge jadwal di dashboard operator, dan `alt` di dua halaman login → "sekolah" / "Gedung SMP Muhammadiyah 5 Kota Tangerang". Nama berkas `assets/img/kampus.png` tidak diubah |
| 17 | **Dua menu verifikasi yang dikerjakan berurutan digabung** (2026-11-01) | `berkas.php` + `verifikasi-data.php` → satu halaman `app/operator/verifikasi.php` dengan tab **Berkas Persyaratan / Data Siswa / Koreksi Data**. Satu pendaftar dibuka di satu layar (berkas + keputusan per berkas, identitas, orang tua, ringkasan, keputusan verifikasi data, koreksi, retensi yang kini ditutup rapi di `<details>`). Sidebar operator 14 → 13 menu. `berkas.php` & `verifikasi-data.php` tetap ada sebagai pengalih 302 (tautan lama di notifikasi/WA/markah tidak mati); semua tautan internal ikut dipindah ke `verifikasi.php?tab=…` |
| 18 | **Peserta Ujian harus memilih jadwal dulu** (2026-11-01) | Dropdown `?j=` dihapus — halaman menampilkan semua jadwal **Terjadwal** sekaligus sebagai kartu (meta kegiatan, kapasitas & bar terpakai, sisa kuota, peserta tanpa nomor WA) dengan tombol *Atur Peserta Otomatis*/*Kirim WA Jadwal* per kartu. Jadwal Draft/Selesai tidak lagi bisa diisi peserta dari sini karena tidak pernah dikirim ke calon siswa. Tabel `min-w-[760px]` → daftar vertikal tanpa gulir samping |
| 19 | **Portal calon siswa lebih ramai daripada portal sekolah** (2026-11-01) | Topbar kini seragam untuk semua peran: breadcrumb "Portal Siswa › …" dan chip "Gateway WA dikonfigurasi — % terkirim bulan ini" dihapus (status gateway dibaca di Kesiapan Rilis, dan bukan informasi untuk calon siswa). Sidebar siswa dua grup → satu grup "Portal Saya", menu *Akademik* hanya muncul bila siswa sudah diterima, CTA "Info SPMB" dihapus (ikon `?` di topbar sudah mengarah ke sana), dan tautan *Ganti Password* di kaki sidebar disembunyikan untuk peran siswa — halaman `app/staf/ganti-password.php` menolak peran itu, siswa ganti sandal lewat **Pengaturan Akun** |
| 20 | **Tagihan pendaftar terhitung biaya masuk penuh** (2026-11-01) | Dasar tagihan kini **sisa Biaya Masuk**: `angsuran_skema()` memotong Biaya Formulir (sudah dibayar saat registrasi) dari Biaya Masuk — contoh 1.250.000 − 200.000 = 1.050.000 → 2 × 525.000. Satu perubahan ini otomatis membetulkan portal siswa, verifikasi bendahara, monitoring kepsek, dan nominal Midtrans (`snap-token`) karena semuanya sudah membaca fungsi yang sama. Label "Estimasi Biaya Pendaftaran" → **Biaya Masuk**; kartu landing, dashboard bendahara, dan rekap cetak menampilkan rantai rincianya |
| 21 | **"Beasiswa" dipromosikan padahal programnya belum ada** (2026-11-01) | Field CMS diganti namanya menjadi **Teks Promo (opsional)** (kolom tetap `teks_beasiswa`, tanpa migrasi) dan kartu publik ikut berubah menjadi badge *Promo Tersedia* + blok *Program Promo*. Bila field dikosongkan, kartu hilang. Di `kelas.php`, formulir *Tambah Kelas Baru* disederhanakan (4 field + hint) dan **Daftar Rombel kini punya satu tombol "Simpan Semua"** — tidak ada lagi simpan per baris |
| 22 | **Halaman Input Hasil Seleksi mencampur urusan nilai dan jadwal** (2026-11-02) | Kolom **Jadwal Tes** dihapus (penjadwalan memang bukan urusan halaman ini) — `jadwal_id` siswa tetap dikirim sebagai hidden input supaya tidak tertimpa NULL saat nilai disimpan. Tombol **Hitung Ulang Otomatis** dihapus: Nilai Akhir dan Keputusan kini terhitung per baris setiap angka diketik, dan berubah lagi begitu ambang diedit. Baris kosong tetap "Proses" (dilewati saat simpan). Galat teks `” tanpa jadwal ”` ikut hilang |
| 23 | **Ambang kelulusan tidak pernah tersimpan** (2026-11-02) | Kolom baru `pengaturan_sekolah.ambang_kelulusan` (default **70**) — nilai dibaca saat halaman dibuka dan ditulis ulang saat "Simpan Hasil Seleksi", jadi ambang tetap sama di sesi berikutnya. Migrasi `database/migrations/20261102_rincian_biaya.sql` |
| 24 | **Biaya masuk hanya satu angka bulat** (2026-11-02) | CMS → Rincian Biaya SPMB kini punya **daftar item bebas** (nama + nominal, tambah/hapus, maks 15 baris) yang tersimpan sebagai JSON di `pengaturan_sekolah.rincian_biaya`; totalnya otomatis menjadi Biaya Masuk. Contoh sekolah: Formulir 200.000 + Fortasi/MPLS 150.000 + SPP 350.000 + Infaq 3 Tahun 350.000 + Kontribusi IPM 3 Tahun 200.000 = **1.250.000**, sisa setelah formulir **1.050.000** = 2 × 525.000. Rincian yang sama tampil di kartu biaya halaman publik dan di panel baru **Rincian Biaya & Sisa Pembayaran** pada portal siswa |
| 25 | **Siswa lulus & sudah dapat kelas tidak melihat sisa tagihannya** (2026-11-02) | Gerbang tagihan `tagihan_pendaftaran_aktif()` diperluas: LULUS **dan punya penempatan kelas** sudah cukup (sebelumnya wajib menunggu tombol "Umumkan Sekarang" ditekan). Portal siswa menampilkan panel sisa pembayaran — total biaya masuk, item rincian, yang sudah dibayar, sisa, dan opsi lunas/2 angsuran beserta jatuh temponya |
| 26 | **Hasil seleksi siswa terbuka begitu saja di dalam tab PPDB** (2026-11-02) | Tab `ppdb.php?tab=hasil` dirombak menjadi menu sendiri **Hasil Seleksi** (`app/siswa/hasil.php`) dengan "amplop": status, nilai, dan kelas **tidak pernah** dirender sebelum siswa menekan tombol *Buka Hasil Seleksi* (POST + CSRF yang sekali tulis, `pendaftar.hasil_dibuka_at` — migrasi `20261102_hasil_dibuka.sql`). Setelah dibuka: konfeti satu kali + ucapan Islami ("Alhamdulillah, Selamat, Kamu Dinyatakan LULUS", «Rabbi zidnii 'ilman» QS. Thaha: 114) untuk yang lulus, kartu tenang bernada QS. Al-Baqarah: 216 untuk yang belum. Tautan lama `?tab=hasil` dialihkan 302 agar pesan WA & markah browser tidak mati. Menu di sidebar diberi badge merah **Baru** selama amplop belum dibuka |
| 27 | **Rincian biaya tampil sebelum siswa tahu dirinya lulus** (2026-11-02) | Tagihan kini mengikuti momen pembukaan hasil: `_bootstrap.php` menambahkan `$hasilTerbuka`/`$tagihanAktif` dan `keuangan.php` mengunci panel **Rincian Biaya & Sisa Pembayaran** (kartu "Terkunci" + badge *Tagihan Menunggu Hasil Dibuka*, tombol bayar & unggah bukti `UANG_MASUK` ditolak server) sampai amplop Hasil Seleksi dibuka. Dari kartu LULUS ada tombol **Buka Rincian Biaya & Pembayaran** yang mengarahkan siswa ke Keuangan. Fungsi `tagihan_pendaftaran_aktif()` tidak diubah — gerbang baru (`hasil_boleh_dibuka()` / `hasil_sudah_dibuka()`) hanya dipakai portal siswa, jadi antrean operator/bendahara dan token Midtrans tetap seperti semula |
| 28 | **Tagihan siswa hanya berupa kartu, sulit dibaca sekilas** (2026-11-02) | Tab Tagihan `app/siswa/keuangan.php` kini punya tabel **Daftar Tagihan &amp; Pembayaran** — `No. · Pembayaran · Jumlah Bayar · Status Bayar · Tanggal Bayar · Kanal Bayar` — satu baris per kewajiban bayar (Biaya Formulir + tiap angsuran), lengkap dengan tanggal `verified_at` dan kanal (`payment_method`/`metode`, fallback bukti unggah → "Transfer Manual"). Status memakai ikon + warna (LUNAS hijau, MENUNGGU VERIFIKASI amber, BELUM LUNAS / LEWAT JATUH TEMPO merah, TERKUNCI abu-abu tanpa nominal). Daftar "Jadwal Angsuran" yang sebelumnya mengulang informasi ini dihapus dari kartu tagihan; di layar <640 px tabel bertukar jadi susun tumpuk tanpa gulir samping |
| 29 | **Hanya satu berkas yang bisa ditolak per pemeriksaan** (2026-11-02) | `berkas_keputusan()` dahulu menolak keputusan kedua dan seterusnya karena tahap berpindah ke `BERKAS_REVISI` begitu berkas pertama ditolak. Guard kini menerima `BERKAS_DISUBMIT` **dan** `BERKAS_REVISI`. Pemberitahuan ke orang tua dipecah: `berkas_wa_revisi()` mengirim notifikasi portal + WA berisi daftar berkas & alasan, dipanggil otomatis saat seluruh berkas sudah diputus (`berkas_evaluasi_tahap()`) **atau** lewat tombol **Kirim Pesan Revisi via WA** di ruang kerja berkas bila operator ingin memberi tahu lebih awal tanpa menunggu berkas lain dikirim ulang. Tombol dibatasi 5×/jam per pendaftar (`rate_limit_cek`) karena kiriman Fonnte nyata |
| 30 | **Tombol "Minta Perbaikan" Data terasa mati** (2026-11-02) | Catatannya wajib di server tetapi kotak teksnya tidak `required`, jadi klik bisa gagal tanpa aba-aba. Kini tombol menolak dikirim sebelum catatan ditulis + konfirmasi, dan di portal siswa (`app/siswa/data-siswa.php`) status `DITOLAK` menampilkan banner **Panitia meminta perbaikan data** berisi catatan (multi-baris), tiga langkah perbaikan, dan tombol yang berganti label menjadi **Kirim Ulang Data untuk Verifikasi** — jalur datanya (`siswa_ajukan_verifikasi_data()`) memang sudah mengizinkan `DITOLAK` kembali dikirim ke `MENUNGGU_VERIFIKASI` |
| 31 | **Dua menu untuk satu urusan** (2026-11-02) | **Jadwal Ujian** dan **Peserta Ujian** digabung menjadi satu menu sidebar dan satu halaman: bagian Peserta Ujian (`#peserta`) dirender di bawah pengaturan jadwal, lengkap dengan *Atur Peserta Otomatis*, tambah manual, hapus peserta, dan kirim WA jadwal per jadwal Terjadwal. `app/operator/peserta-ujian.php` tetap ada sebagai pengalih 302 ke `jadwal.php#peserta` sehingga tautan lama & markah tidak mati; sidebar operator 15 → 14 item |
| 32 | **Pengumuman hasil menunggu kepala sekolah** (2026-11-02) | Gerbang persetujuan dilepas dari `app/operator/pengumuman.php`: banner "Menunggu persetujuan" & tombol `disabled` dihapus, `umumkan_sekarang`/`jadwalkan` tidak lagi memeriksa sidik persetujuan, dan `persetujuan_hasil_sah()` dihapus karena tidak dipakai lagi. Menu **Persetujuan Hasil** di portal kepsek tetap ada sebagai catatan resmi (kata "mengunci/terkunci" di halaman itu diganti). Pesan WA pengumuman kini menyertakan tautan **`app/siswa/hasil.php`** untuk mengecek hasil. Halaman pengumuman juga belum pernah mengisi `$USER_LOGIN` dari akun yang login — sidebar/topbar-nya menampilkan nama demo dari `includes/dummy.php`; kini ikut diambil dari `current_user()` seperti halaman operator lain |
| 33 | **Sidebar melompat ke atas tiap pindah halaman** (2026-11-02) | Skrip di `includes/partials/sidebar.php` menyimpan posisi gulir `.nav-penuh`/`.nav-rel` ke `sessionStorage` dan memulihkannya, lalu menggeser **seminimal mungkin** bila menu aktif tidak ikut terlihat (12 px dari tepi). Menekan "Pengaturan Sistem" kini membuat scrollbar standby di menu itu, dan halaman yang menampilkannya tanpa scroll tidak digerakkan sama sekali |
| 34 | **Landing page & halaman masuk** (2026-11-02) | Kartu biaya `app/spmb/index.php` tidak lagi menulis "Saldo Rekening" dan tombol **Ajukan Skema Angsuran** dihapus (skema angsuran tetap dihitung `angsuran_skema()` dan muncul di tab Tagihan). `app/auth/registrasi.php` tidak lagi menawarkan "Masuk Portal" — setelah registrasi yang dibutuhkan hanya melengkapi portal siswa. Di `app/spmb/bayar.php`, teks "hubungi panitia 08xxxx" menjadi **Hubungi panitia — klik disini** yang menaut `wa.me` nomor dari CMS (tanpa nomor = teks biasa, bukan nomor palsu) |
| 35 | **Ikon media sosial footer hanya hiasan mati** (2026-11-02) | Empat kolom baru di `pengaturan_sekolah` (`sosmed_instagram`, `sosmed_tiktok`, `sosmed_youtube`, `website_sekolah` — migrasi `database/migrations/20261102_sosmed_sekolah.sql`) + blok **Media Sosial & Website Sekolah** di Pengaturan Sistem. Operator cukup menempel link; `tautan_normal()` menormalkan ke `https://…` dan menolak yang bukan alamat situs. Footer publik merender ikon **hanya** untuk kolom yang terisi (`rel="noopener me"`, tab baru) — yang kosong tidak tampil, dan ikon Facebook yang tidak punya kolom dihapus |

| 36 | **Kolom "Koreksi Data" di portal siswa** (2026-11-02) | Bagian **Koreksi Data** (form ajukan + riwayat + batal) dihapus dari `app/siswa/data-siswa.php` berikut dua handler POST-nya (`ajukan_koreksi`, `batalkan_koreksi`); `$koreksi`/`$koreksiBidang` tidak lagi dimuat di `app/siswa/_bootstrap.php`. Tiga pesan yang menunjuk ke UI itu ditulis ulang ("hubungi panitia — formulir dibuka kembali lewat Minta Perbaikan") di `includes/queries/siswa.php` dan `includes/queries/pendaftar.php`. Lapisan query `koreksi.php` + tab **Koreksi Data** operator saat itu sengaja **dipertahankan** supaya baris lama tetap bisa diputus — tab tersebut akhirnya ikut dihapus pada 2026-11-03 (lihat baris 40) |

| 37 | **Penamaan menu portal siswa** (2026-11-03) | Istilah "PPDB" dilepas dari portal siswa. Menu **PPDB & Seleksi** → **Jadwal & Kartu Ujian** (ikon `event`) dan judul halaman `app/siswa/ppdb.php` ikut nama menu; menu **Notifikasi & Pengumuman** → **Informasi**. Hanya label — URL, `?tab=`, kunci `$activeMenu` (`ppdb`, `notifikasi`) dan tautan lama di WA/notifikasi tetap hidup. Enam teks yang menyebut nama menu lama ikut diperbarui: dashboard siswa, Pengaturan Akun (kartu pintasan + label "Notifikasi"), Hasil Seleksi (2 tombol), Data Siswa, Informasi, dan catatan di `app/operator/jadwal.php` |

| 38 | **Urutan tab Jadwal & Kartu Ujian** (2026-11-03) | Tab **Jadwal Tes** dipindah ke paling kiri dan sekaligus menjadi tab bawaan (URL tanpa `?tab=` dan `?tab=` tak dikenal mendarat di sana); **Formulir & Kelengkapan** di kanan. Tidak ada URL baru — `?tab=formulir` tetap membuka formulir. Panel "Tahap saat ini" posisinya di atas tab, sehingga tautan "Lihat Status" dari dashboard/informasi tetap menampilkan tahap siswa apa pun tab bawaannya |

| 39 | **Tombol pintasan "Jadwal & Kartu Ujian" di halaman hasil** (2026-11-03) | Dua tombol di `app/siswa/hasil.php` dihapus (kartu LULUS dan kartu TIDAK_LULUS). Halaman hasil kini hanya menawarkan langkah berikutnya: **Buka Akademik** / **Buka Keuangan** untuk yang lulus, **Info Pendaftaran** untuk yang belum. Baris tombol LULUS dibungkus `if (!$tagihan || $penempatan)` agar tidak menyisakan `<div>` kosong bila dua-duanya tidak berlaku. Menu di sidebar tetap jalan — `app/siswa/ppdb.php` tidak berubah |
| 40 | **Fitur Koreksi Data ditutup sampai lapisan query** (2026-11-03) | Tab **Koreksi Data** di `app/operator/verifikasi.php` dihapus (`$TABS` tinggal berkas/data), beserta handler POST `koreksi_status`, blok "Pengajuan Koreksi Data" di ruang kerja satu pendaftar, baris "Koreksi aktif" pada kartu Ringkasan, kolom **Koreksi** di antrean data, serta kolom **Koreksi** + chip "N aktif" + baris "N pengajuan koreksi aktif" di `app/operator/pendaftar.php` (kartu keempat jadi **Perlu Perbaikan** dengan tautan filter `DITOLAK`). Berkas `includes/queries/koreksi.php` dan `require_once`-nya di `includes/queries.php` dihapus; subquery `koreksi_aktif` di `pendaftar_data_antrean()` ikut hilang. Notifikasi lama bertipe `KOREKSI_DATA` tetap tampil dan kini menunjuk `verifikasi.php?tab=data`. `?tab=koreksi` lama tidak error — jatuh ke tab berkas. Tabel `pengajuan_koreksi` dan migrasinya **tidak** diapa-apakan |
| 41 | **Sisa tulisan "PPDB" di ruang kerja operator** (2026-11-03) | Item cek Kesiapan Rilis "Jadwal PPDB & Syarat Ketentuan" → **Jadwal SPMB & Syarat Ketentuan**; teks galatnya "Tanggal buka/tutup pendaftaran SPMB atau teks S&K belum lengkap…". Nama kolom CMS (`tanggal_buka_ppdb`, `tanggal_tutup_ppdb`) tidak diubah |
| 42 | **Notifikasi WA ke operator belum punya nomor tujuan** (2026-11-03) | `pengaturan_sekolah.notif_wa_operator` diisi nomor WhatsApp panitia dan `notif_wa_aktif` tetap 1 sehingga item Kesiapan Rilis "Notifikasi WA ke operator" berubah menjadi **Aktif — dikirim ke …**. Nomor kini bisa diganti dari **Petugas & Hak Akses** (panel baru, aksi POST `wa` → `petugas_notif_wa_simpan()`), tidak perlu lagi menggulir formulir Pengaturan Sistem. Nilai nomor disimpan di database saja — tidak masuk ke seed `database/db_spmb_mualimku.sql` karena repo ini publik |
| 43 | **Isi FAQ halaman depan tidak bisa diubah tanpa menyentuh kode** (2026-11-03) | Pertanyaan-jawaban section **Pusat Bantuan** di `app/spmb/index.php` dulu dibaca langsung dari `$FAQ` di `includes/dummy.php`. Kini lewat `faq_daftar()` (kolom JSON `pengaturan_sekolah.faq_list`, lihat section 3b) dengan editor baris di Pengaturan Sistem. Sekalian memperbaiki dua string yang menampilkan `Qur\u2019an` apa adanya — `\u2019` di dalam petik tunggal PHP tidak diproses (langkah alur 3 & FAQ pertama) |
| 44 | **Notifikasi WhatsApp panitia dirampungkan ke satu kanal transaksi** (2026-11-05) | Akun Fonnte gratis hanya mendaftarkan **satu** nomor, jadi WA panitia tidak lagi dipakai untuk semua pemicu. `notif_operator()` (`includes/notifikasi.php`) kini mengirim WhatsApp **hanya** untuk `BUKTI_BAYAR_MASUK`, `PEMBAYARAN_GATEWAY_MASUK`, `PEMBAYARAN_GATEWAY_LUNAS`, dan hanya ke `notif_nomor_bendahara()` (= `notif_wa_bendahara`, fallback `kontak_wa_panitia`). Pemicu `BERKAS_DISUBMIT` / `VERIFIKASI_DATA` tetap tercatat di tabel `notifikasi` dan tampil di menu Notifikasi — hanya kanalnya yang berhenti di dalam aplikasi. Section **"Notifikasi Operator (Fase 3)"** di Pengaturan Sistem dihapus; toggle + nomor bendahara pindah ke blok **Notifikasi Transaksi** di dalam kartu WhatsApp Gateway (Fonnte), section setelahnya bernomor ulang (11→10 Daftar Berkas, 12→11 Petugas). Kolom `notif_wa_operator` **pensiun**: tidak lagi dibaca maupun ditulis (nilainya dibiarkan di database, tidak dihapus). Panel di **Petugas & Hak Akses** ikut dialihkan — judulnya kini *Notifikasi WhatsApp Transaksi*, field `notif_wa_bendahara`, dan menulis kolom yang sama lewat `petugas_notif_wa_simpan()`. Klaim lama "saklar `notif_wa_aktif` bersifat global" diluruskan: pengingat angsuran orang tua memakai `pengingat_angsuran_wa` sendiri dan kirim jadwal/pengumuman ke wali memakai jalur lain. Input `notif_email` (yang memang `disabled`) ikut hilang dari layar; kolomnya tetap ditulis ulang dengan nilai yang sudah ada sehingga tidak terkosongkan. Item Kesiapan Rilis "Notifikasi WA ke operator" → **Notifikasi WA uang masuk (bendahara)**, check-nya kini mengikuti nomor bendahara/panitia sebenarnya |
| 45 | **Impor Nilai CSV tidak terpakai dan menyimpan nilai yang salah** (2026-11-05) | Kartu "Impor Nilai CSV" di `app/operator/seleksi.php` dihapus beserta kedua aksi POST (`preview_csv`, `impor_csv`) dan helper `seleksi_impor_nilai()` di `includes/queries/seleksi.php` — satu-satunya jalan masuk nilai sekarang tabel input manual, dan Nilai Akhir/keputusan tetap dihitung otomatis dari ambang. Ini sekaligus menutup butir audit "nilai di luar 0–100 di-clip diam-diam jadi 100/0": sel input manual sudah dibatasi `min`/`max` dan divalidasi ulang di server. Tidak ada migrasi — tabel `seleksi` tidak berubah strukturnya. Kartu panduan in-app "Seleksi & Impor Nilai (Fase 7)" → **Input Hasil Seleksi (Fase 7)** |
| 46 | **Judul kolom nilai memakai istilah teknis lama (CBT/BTQ/Wawancara)** (2026-11-05) | Judul ketiga kolom di `app/operator/seleksi.php` sekarang diambil dari nama kegiatan jadwal tes (`seleksi_label_kolom()` + `seleksi_singkat_nama()`), disingkat otomatis dan nama lengkapnya tersedia di `title` — lihat catatan di §8. Paragraf penjelasan, subtitle halaman, dan teks ambang ikut memakai label yang sama. Ditambah **pencarian** No. Pendaftaran / Nama Calon Siswa di sisi klien yang hanya menyembunyikan baris, sehingga nilai yang sudah diketik tetap terkirim saat Simpan. Tidak ada perubahan struktur tabel `seleksi` |
| 47 | **Daftar nilai urut nomor pendaftaran sehingga sulit mencari nama** (2026-11-05) | `pendaftar_untuk_nilai()` di `includes/queries/pendaftar.php` sekarang `ORDER BY p.nama_siswa, p.no_pendaftaran` — baris Input Hasil Seleksi tersusun abjad nama (kolasi `utf8mb4_unicode_ci`, jadi huruf kecil tetap di posisi yang benar). Chip filter A–Z yang sempat dibuat beberapa jam sebelumnya **dicabut** karena urutan abjad sudah membuat daftar mudah dibaca; kotak pencarian dipertahankan |
| 48 | **Unggah bukti bayar tidak memunculkan WA di nomor bendahara padahal token sudah diisi** (2026-11-06) | Bukan kerusakan kode: urutan baca token adalah **`.env` → DB**, dan `.env` lokal masih berisi token lama (perangkat WA sekolah yang lama) yang di Fonnte berstatus `disconnect` — jadi `wa_kirim()` selalu ditolak `request invalid on disconnected device` (terbukti dari baris `wa_log` GAGAL), sementara token yang diketik operator di Pengaturan Sistem (perangkat lain yang ter-connect) tidak pernah dipakai. Yang diubah: (1) `FONNTE_TOKEN` di `.env` **dikosongkan** (berkas lokal, tak di-commit) sehingga token CMS yang berlaku — uji `/device` kini `connect`, kuota tersisa ±1000, kedaluwarsa 6 Desember 2026; (2) kotak sumber token di kartu WhatsApp Gateway dari **hijau** ("Mode .env aktif", terbaca aman) menjadi **kuning** dan menyebut kedua sidik jari + kata "diabaikan" saat isinya berbeda; (3) baris **Kirim terakhir** di blok Notifikasi Transaksi menampilkan percobaan uang masuk terakhir dari `wa_log` (tujuan, tipe, waktu, alasan Fonnte) sehingga kegagalan tidak lagi senyap; (4) hint Nomor WA Bendahara ditegaskan bahwa isian itu **hanya tujuan** — nomor saja tidak mengirim apa pun tanpa perangkat Fonnte yang ter-connect; (5) item Kesiapan Rilis "nomor pengirim WA = nomor panitia" tidak lagi hijau bila perangkat `disconnect` (`$devCache['ok']` kini ikut diperiksa). Efek samping yang perlu diputuskan: sejak token CMS berlaku, **semua** WA keluar dari perangkat itu, termasuk instruksi bayar ke orang tua, sementara `kontak_wa_panitia` masih menunjuk nomor perangkat lama — Kesiapan Rilis menandainya kuning sampai salah satunya disamakan. Nomor perangkat sengaja tidak ditulis di dokumen ini (repo publik). Tidak ada perubahan struktur database |
| 49 | **Cek status publik menuntut dua isian, dan tautan bayar baru minta digit lagi** (2026-11-06) | `app/spmb/cek-status.php` kini satu kolom isian: **nomor WhatsApp penuh** langsung menampilkan status (nomor WA tidak punya pola yang bisa ditebak), sedangkan **nomor pendaftaran** tetap wajib 4 digit terakhir WhatsApp karena `REG-YYYY-NNNN` berjalan — kotak 4 digit muncul otomatis lewat JavaScript dan tetap muncul tanpa JavaScript karena server yang menampilkan ulang. Satu nomor WA yang dipakai beberapa pendaftaran memunculkan daftar **Pilih pendaftaran**, dan id kandidat disimpan di sesi agar tidak bisa dipilih dari luar. Tombol **Buka Halaman Pembayaran** menggantikan formulir "Pulihkan Tautan": id pendaftar yang sudah terbukti (sesi `$_SESSION['cek_status']['verif']`, umur 30 menit) → `bayar_tautan_baru()` merotasi `pay_token_hash` → redirect langsung ke `app/spmb/bayar.php?t=…`, tanpa mengetik digit ulang dan tanpa menampilkan token di halaman; dibatasi 8/jam per pendaftar (`tautan_bayar`). Helper lama `pulihkan_pay_token()` dihapus, `cek_status_cari()` dapat parameter `$id` keempat + pencocokan WA tiga bentuk (`+62…`/`62…`/`08…`) lewat `IN(...)` supaya indeks terpakai, dan ada `cek_status_satu($id)`. Halaman `app/spmb/bayar.php` tidak disentuh. Teks ikutan: pesan WA registrasi (`includes/fonnte.php`), kartu panduan in-app, `docs/ALUR-PENDAFTARAN.md`, `docs/UJI-MANUAL.md` §C & E16–E17/X12 |

---

| 50 | **Siswa baru pertama login langsung disambut "pembayaran ditolak" dari uji sebelumnya** (2026-11-06) | Bukan bug pengiriman: akun siswa dibuat bendahara **setelah** rentetan keputusan pembayaran terjadi, sementara `notifikasi_siswa` menyimpan peristiwa itu tanpa peduli akun sudah dipakai atau belum. Sekarang ada garis awal: kolom baru `users.notif_mulai_at` (NULL → ditulis `NOW()` pada kunjungan pertama, sekali saja, `database/migrations/20261106_notifikasi_baseline.sql`) dan helper `notif_siswa_batas($pendaftar_id)` (`includes/queries/notifikasi-siswa.php`, hasil di-`static cache` per request). `notif_siswa_daftar()` **dan** `notif_siswa_belum_dibaca()` menyaring `created_at >= batas` sehingga halaman Informasi, badge sidebar/topbar, dan `app/api/notif-count.php` konsisten: peristiwa sebelum login pertama tidak muncul sama sekali dan badge 0. Barisnya **tidak** dihapus maupun ditandai dibaca — riwayat audit tetap utuh dan keputusan bendahara yang lama masih terlihat di papan operator. Penulisan batas dilakukan di `app/siswa/_bootstrap.php` sebelum `angsuran_kirim_pengingat()` supaya pengingat yang lahir pada kunjungan pertama itu sendiri tidak ikut tersembunyi. Yang sengaja TIDAK disaring: kartu "Informasi Pendaftaran"/kabar di dashboard (dihitung dari status data saat ini, bukan peristiwa) dan Pengumuman Panitia. Konsekuensi yang diterima: riwayat lama tidak bisa lagi dilihat siswa di portal — itu memang permintaan. Uji: `docs/UJI-MANUAL.md` W19–W23 |
| 51 | **Pendaftar baru & pengajuan data/berkas tidak terdengar sama sekali oleh panitia** (2026-11-06) | Sejak 2026-11-05 WA panitia dipangkas jadi kanal transaksi saja (baris 44) karena akun Fonnte gratis mendaftarkan satu nomor — padahal nomor itu **adalah** nomor operator yang sama, jadi alasan pemangkasan tidak berlaku di sini. Sekarang `notif_operator()` kembali mengirim WA untuk kabar operasional lewat daftar baru `notif_pemicu_wa()`: `PENDAFTAR_BARU`, `VERIFIKASI_DATA`, `BERKAS_DISUBMIT` bergabung dengan tiga pemicu uang masuk, semuanya ke `notif_nomor_bendahara()`. `registrasi_baru()` (`includes/queries/pendaftar.php`) memanggil `notif_operator('PENDAFTAR_BARU', …)` **setelah** `RELEASE_LOCK` dan hanya di jalur baris benar-benar baru — cabang `reused` dan cabang "nomor WA + nama sudah terdaftar" keluar lebih dulu, jadi retry/double-submit tidak menghasilkan kabar ganda. Isi pesan: nama, No. Pendaftaran, asal sekolah, dan tautan `app/operator/pendaftar.php`; tipe barunya ikut dapat tombol "Buka" di papan Notifikasi. Tiga pemicu operasional dibatasi **3 pesan/jam per pendaftar** (`rate_limit_cek`/`rate_limit_catat` dengan kunci `tipe:pendaftarId` — kolom `rate_limit.ip_address` dipakai sebagai penyimpan kunci ber-`prefix "k:"`), uang masuk sengaja **tidak** dibatasi karena tiap transfer adalah kejadian berbeda; papan `notifikasi` in-app tetap mencatat **semua** kejadian walau kiriman WA-nya ditahan. Saklar `notif_wa_aktif` tetap satu-satunya gerbang. Penamaan ikut disesuaikan karena tujuannya bukan lagi bendahara saja: blok Pengaturan Sistem **Notifikasi Transaksi → Notifikasi WhatsApp Panitia**, field **Nomor WA Panitia & Bendahara (dipisah koma)**, label saklar "Kirim notifikasi panitia via WhatsApp", judul panel di Petugas & Hak Akses, item Kesiapan Rilis, dan kartu panduan F3. `pendaftar_manual()` (pendaftar yang dibuat operator) sengaja tidak mengirim WA — panitia sedang berada di layar itu. Uji: `docs/UJI-MANUAL.md` W24–W28 |
| 52 | **Calon tidak bisa dijadwalkan pada kegiatan kedua, dan tombol WA per kartu tidak menulis lonceng portal** (2026-11-06) | Dua hal yang ternyata satu akar. **Skema**: `peserta_ujian` mengunci `UNIQUE (pendaftar_id)` sehingga satu calon cuma boleh memegang satu kartu *seumur hidup* — *Atur Peserta Otomatis* di kartu kegiatan kedua selalu menghasilkan 0 peserta padahal sisa kapasitas masih ada, dan operator harus menghapus baris kandidatnya lebih dulu. `database/migrations/20261106_peserta_ujian_multi_jadwal.sql` (idempoten lewat `information_schema` + `PREPARE`) membuat `uq_peserta_jadwal (pendaftar_id, jadwal_id)` **lebih dulu** — kalau tidak, FK pada `pendaftar_id` kehilangan indeks pendukung — lalu melepas `uq_peserta_pendaftar`; seed `database/db_spmb_mualimku.sql` ikut disesuaikan. **Logika**: pengecekan "belum terjadwal" di `peserta_atur_otomatis()`, `peserta_tambah_manual()` dan daftar kandidat kini berlaku **per kartu**; syarat tahap dipusatkan ke `peserta_tahap_siap_jadwal()` (empat tahap — versi sempit dulu membuat tombol "Tambah manual" hilang sama sekali di data yang sudah diumumkan), `UPDATE tahap` diberi syarat `AND tahap = 'BERKAS_TERVERIFIKASI'` supaya jadwal susulan tidak memundurkan calon bernilai, dan `peserta_hapus()` hanya memundahkan tahap bila calon itu tinggal tanpa jadwal mana pun. Penanda "(sudah: …)" di dropdown dibangun dari kartu **Terjadwal** saja — kartu Draft/Selesai memang tidak dikelola dari halaman ini. Tile "Belum dijadwalkan" (dulu selisih kapasitas sehingga menyesatkan) menjadi **"Belum punya jadwal"**. **Notifikasi**: `jadwal_kirim_notifikasi()` per kartu membaca pesertanya dari `peserta_ujian` tetapi penerimanya dari `seleksi.jadwal_id` — sumber berbeda, sering 0 penerima — dan hanya mengirim WA. Sekarang keduanya dari kartu yang sama lewat `jadwal_penerima_kartu()`, dan pengirimannya diserahkan ke `jadwal_kirim_ke_wali()` fungsi baru yang **juga** dipakai `jadwal_umumkan_semua()`; tombol per kartu ikut menulis `notifikasi_siswa` (tipe `JADWAL_UJIAN`, `ref_id` sidik jari kegiatan) + `wa_log`, dan melewati penerima yang sudah `TERKIRIM`. Konsekuensi yang diterima: wali bisa memegang dua lonceng jadwal (pengumuman gabungan dan per kegiatan) karena sidik jarinya memang peristiwa berbeda. Portal siswa menyesuaikan: `peserta_kartu_siswa()` memilih kegiatan **terdekat yang belum lewat** (`(j.tanggal < CURDATE()), ABS(DATEDIFF(j.tanggal, CURDATE()))`) dan Agenda dibangun dari `jadwal_agenda_siswa()` — hanya kegiatan milik siswa itu, jatuh ke `jadwal_terjadwal()` bila ia belum ditempatkan di kartu mana pun. Uji: `docs/UJI-MANUAL.md` O12–O17 & P2/P2b/P5 (harness CLI 46 assert + render portal, token Fonnte dipalsukan); `cek-statis` 17 LULUS, `cek-proyek` 8 PASS/SIAP. **Migrasi ini harus dijalankan di hosting** sebelum operator menempatkan calon pada kartu kedua |
| 53 | **Kalimat WA ke wali siswa hanya bisa diubah dengan menyunting kode** (2026-11-06) | Tujuh pesan keluar (instruksi bayar, akun & password, jadwal tes, hasil seleksi, pengingat biaya, bukti bayar ditolak, berkas ditolak) tadinya dirangkai dengan string PHP di `includes/fonnte.php`, `queries/jadwal.php`, `queries/pengumuman.php`, `queries/pembayaran.php` dan `queries/dokumen.php`. Kini semuanya lewat katalog template `wa_teks_katalog()` di berkas baru `includes/queries/wa-teks.php` + `wa_teks_isi($kunci, $vars)`, dan operator bisa menggantinya per pesan di **Pengaturan Sistem → Teks Pesan WhatsApp ke Calon Murid** (section 7b; kolom JSON baru `pengaturan_sekolah.wa_teks`, migrasi `20261106_wa_teks_cms.sql`, seed ikut dilengkapi). Aturan simpan: kosong / sama dengan bawaan = **tidak menulis apa pun**, sehingga kalimat bawaan aplikasi masih ikut ter-upgrade; yang tersimpan hanya suntingan sungguhan. Validasi `wa_teks_galat()` menolak teks > 1200 karakter, placeholder wajib yang hilang, dan placeholder tak dikenal (typo `{namaa}` dulu akan terkirim mentah ke orang tua). `wa_teks_rapikan()` membuang baris yang seluruh isinya placeholder kosong (tahun ajaran belum diisi, metode transfer nonaktif) dan merapatkan `\n\n\n`. Sekalian menjawab permintaan spesifik: pesan **hasil seleksi** jadi satu kalimat umum — "Assalamualaikum. / Alhamdulillah, Pengumuman Kelulusan seleksi SPMB {sekolah} sudah RILIS. / … tautan hasil … / … cek-status … / Panitia SPMB" — tanpa nama, No. REG, dan status LULUS/TIDAK LULUS; rincian itu tetap hanya muncul di lonceng portal siswa (`notifikasi_siswa` tipe `HASIL_SELEKSI` tetap personal). Lonceng jadwal & berkas memakai template yang sama dengan WA-nya sehingga keduanya tidak bisa lagi berbeda kalimat. Uji: harness CLI `uji-wa-cms.php` (render GET 71 assert; POST valid menyimpan override dan **seluruh kolom lain tidak bergeser** — bukti urutan parameter UPDATE besar itu aman; POST tidak valid tidak menulis apa pun; baris `pengaturan_sekolah` selalu dipulihkan lewat cadangan + `register_shutdown_function`), `uji-wa-teks.php` 27 assert; `cek-statis` 17 LULUS, `cek-proyek` 8 PASS (262 kolom) / SIAP. **Migrasi `wa_teks` wajib dijalankan di hosting** — tanpa kolom itu, seluruh formulir Pengaturan Sistem gagal tersimpan (`1054 Unknown column`) |

## Sisa Pekerjaan

### ❌ Tidak berfungsi (3)

1. Input nilai tab 3 dashboard — tanpa `name`/`<form>`/tombol simpan
2. Toast pesan galat di Notifikasi (tipe `flash` salah)
3. Checkbox Aktif di Jenis Dokumen (hardcode `checked`)

### ⚠️ Berfungsi sebagian (8)

1. WA massal pengumuman — tanpa dedupe
2. Ticker (running text) — salah kunci baca
3. Field CMS "tulis-saja" — tersimpan, tak pernah dibaca
4. Retensi toggle — hanya mengunci CLI
5. Notifikasi email — kolom + titik sambungan tanpa input (kiriman belum ada)
6. Filter status rekap export — tak jalan
7. Export `pembayaran` — tanpa tombol
8. Kartu Peserta — tak ada di halaman operator

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
