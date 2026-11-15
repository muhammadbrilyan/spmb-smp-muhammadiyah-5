# Panduan Deploy — SPMB MUALIMKU

Panduan pemasangan & rilis aplikasi SPMB/CMS SMP Muhammadiyah 5 Kota Tangerang
— disusun untuk **hosting bersama cPanel (mis. Domainesia) TANPA akses SSH**.
Varian lokal XAMPP/Linux ikut disinggung di catatan.

**Syarat minimum: PHP 8.1 atau lebih baru** (Fase 12 C5) dengan ekstensi
`pdo_mysql`, `mbstring`, `curl`, `fileinfo`, `gd`, `zip`, `json`, `session`
(cek lengkap: `php tools/cek-proyek.php`) + MySQL 5.7/8 atau MariaDB 10.x.
Tanpa Composer/npm — Tailwind sudah di-build statis.

---

## 1. Pemasangan di cPanel (Langkah demi Langkah)

### 1.1 Database & user (MySQL Database Wizard)

1. Buka **MySQL® Database Wizard** di cPanel.
2. Buat database baru, mis. `spmb` — cPanel menambahkan awalan akun, hasilnya
   jadi `USERNAME_spmb`. **Catat nama lengkapnya.**
3. Buat user khusus + password kuat. **Catat password** (hanya sekali).
4. Saat diminta hak akses: beri **ALL PRIVILEGES** dulu (impor & migrasi
   butuh CREATE). Setelah aplikasi jalan (langkah 1.8), kembalikan ke
   **SELECT, INSERT, UPDATE, DELETE** saja melalui wizard yang sama
   (ubah grant user).

### 1.2 Impor skema database (phpMyAdmin)

1. Buka **phpMyAdmin** → pilih database `USERNAME_spmb` → tab **Import**.
2. Pilih `database/db_spmb_mualimku.sql` (±100 KB — jauh di bawah batas
   unggah hosting) → **Go**.
3. Verifikasi: tabel `pengaturan_sekolah`, `pendaftar`, `users`, dst. muncul.
4. Migrasi tambahan per fase (idempoten, aman dijalankan ulang) — **dua cara**:
   - **Cara disukai (browser, tanpa SSH):** jalankan
     `tools/migrasi-web.php` seperti di bagian **1.6a** di bawah. Nama database
     diambil dari `.env` aplikasi, jadi tidak ada baris `USE` yang perlu disunting.
   - **Cara manual:** buka tiap file di `database/migrations/` secara berurutan
     (nama file = urut waktu) di phpMyAdmin → tab SQL → Run. **Ganti dulu baris
     `USE db_spmb_mualimku;` di kepala file dengan nama database hosting**
     (`USERNAME_spmb`) atau hapus
     baris itu dan pastikan database-nya sedang dipilih di panel kiri — kalau
     tidak, MySQL menjawab `Unknown database` dan migrasi tidak berjalan sama
     sekali.
5. **Jalankan langkah 4 setiap kali menarik kode baru.** Kolom yang tertinggal
   membuat halaman gagal menyimpan secara menyeluruh, bukan sebagian — satu
   `UPDATE` 49 kolom dibatalkan MySQL (`1054 Unknown column`) sehingga seluruh
   perubahan operator hangus. Dengan akses CLI, `php tools/cek-proyek.php`
   pemeriksaan 5 membandingkan semua kolom seed terhadap database (262 kolom)
   dan menyebut nama kolom yang belum ada.
   **Perhatian:** pemeriksaan 5 hanya membaca **kolom**, bukan indeks.
   `20261106_peserta_ujian_multi_jadwal.sql` (satu calon boleh ikut beberapa
   kegiatan) bisa jadi tertinggal tanpa ada halaman yang tampak error —
   gejalanya *Atur Peserta Otomatis* selalu menghasilkan 0 peserta pada kartu
   kedua. Pastikan file itu ikut dijalankan di hosting; cek dengan
   `SHOW INDEX FROM peserta_ujian` — yang harus ada `uq_peserta_jadwal
   (pendaftar_id, jadwal_id)`, bukan `uq_peserta_pendaftar`.

**PENTING (Fase 11):** seed utama sengaja **tidak memuat password login yang
bisa dipakai** — akun staf (`operator`, `bendahara`) di-seed dgn
hash `'!LOCKED'` dan harus diatur sendiri (langkah 1.6).

### 1.3 Upload aplikasi (File Manager)

1. **File Manager** → buka `public_html` ( atau folder website utama).
2. **Upload** paket rilis `dist/mualimku-<versi>.zip`
   (dibuat dengan `php tools/buat-paket.php <versi>` — TIDAK PERNAH
   meng-zip folder kerja langsung; paket sudah bebas `.env`, `.git/`,
   cadangan, dokumen pribadi).
3. Kembali ke File Manager → klik kanan zip → **Extract**.
4. Bila aplikasi dipakai di subfolder, ekstrak ke `public_html/mualimku/`.

### 1.4 Konfigurasi `.env`

Salin `.env.example` menjadi `.env` (File Manager → Show Hidden Files ON →
Edit) lalu isi:

| Kunci | Nilai |
|-------|-------|
| `APP_NAME` | `SMP Muhammadiyah 5 Kota Tangerang` — **wajib**; penanda identitas: loader menolak `.env` di luar root proyek yang tidak memuat kunci ini (Fase 13 butir 6) |
| `DB_HOST` | `localhost` (lazim di cPanel) |
| `DB_PORT` | `3306` |
| `DB_NAME` / `DB_USER` / `DB_PASS` | nama & user lengkap (dgn awalan) + password |
| `APP_URL` | `https://domain-anda` (wajib HTTPS — dipakai tautan WhatsApp) |
| `FONNTE_TOKEN` | token Fonnte (bila pakai WA gateway) |
| `MIDTRANS_*` | hanya bila sekolah punya akun Midtrans |
| `TRUSTED_PROXIES` | isi CIDR Cloudflare **hanya bila** lewat Cloudflare — salin daftar dari halaman resmi Cloudflare (https://www.cloudflare.com/ips/); kosong = abaikan semua header proxy |
| `TRUSTED_PROXY_HEADER` | `CF-Connecting-IP` bila memakai Cloudflare, selain itu biarkan kosong |
| `STORAGE_DIR` | lihat langkah 1.5 (path absolut folder storage di luar web) |

Jangan commit `.env` ke mana pun; `.env` sudah masuk `.gitignore` dan
dikecualikan dari paket rilis.

### 1.5 Pindahkan `.env` & `storage/` ke luar web root (disarankan)

1. Di home account (satu tingkat di atas `public_html`), buat folder
   `spmb-data/` lalu pindahkan folder `storage/` ke sana (File Manager:
   Move, atau Download+Upload bila antar-root tidak bisa).
2. Set permissions `spmb-data/storage` & subfoldernya (`dokumen`, `backup`)
   ke **755** (atau 775 bila user hosting menuntut demikian) agar bisa
   ditulis web server.
3. Di `.env` isi `STORAGE_DIR=/home/USERNAME/spmb-data/storage`.
4. Salin `.env` ke **satu tingkat di atas root proyek** — bila aplikasi
   ada di `public_html/mualimku` maka tujuannya `public_html/.env`;
   bila aplikasi langsung di `public_html`, tujuannya
   `/home/USERNAME/.env` (bukan `spmb-data/`). Aplikasi mencari `.env`
   di lokasi itu LEBIH DAHULU sebelum `.env` di root proyek
   (Fase 12 C6) — **pastikan baris `APP_NAME=...` tetap ada** di dalamnya
   (Fase 13 butir 6: tanpa penanda itu file dilewati). Anda boleh
   menghapus `.env` di root proyek setelah itu.
5. Halaman **Kesiapan Rilis** (operator) menampilkan status lokasi storage —
   harus berstatus baik (di luar web root).

### 1.6 Set password staf — TANPA SSH

Seed mengunci semua akun staf. Lewat browser:

1. File Manager → buat file `.izinkan-set-password` di dalam folder
   `storage/` (nama persis, tanpa ekstensi) yang **berisi kode acak
   minimal 8 karakter** (mis. hasil password generator). Tanpa kode ini
   alat tidak menampilkan apa pun (Fase 13 — cegah pengambilalihan akun).
2. Buka `https://domain-anda/tools/set-password-web.php` → ketik **kode
   instalasi** tadi → pilih akun (`operator`, `bendahara`) →
   isi password baru (min 10 karakter, konfirmasi 2×) → Simpan.
3. Ulangi untuk tiap akun staf. Setelah tiap sukses, penanda lama terhapus
   dan (bila masih ada akun terkunci) penanda **baru** berisi kode acak baru
   ditampilkan sekali di layar — catat untuk langkah berikutnya.
4. Setelah akun terkunci terakhir, penanda terhapus dan alat **menghapus
   dirinya sendiri**. Bila penghapusan diri gagal (izin file), berkas alat
   boleh ditinggal — tanpa `storage/.izinkan-set-password` ia 404. Halaman
   Kesiapan hanya menandai GAGAL selama **penanda** `storage/.izinkan-*`
   masih ada (hapus via File Manager), bukan karena keberadaan berkas alat.
5. Login tiap staf — sistem memaksa **ganti password** saat login pertama.

Alternatif bila ada akses SSH/terminal cPanel:
`php tools/set-password.php <username>` (CLI-only).

Khusus **setelah** minimal satu akun operator bisa login, semua perubahan
berikutnya cukup lewat menu **Pengaturan Sistem → Petugas & Hak Akses**
(nama/jabatan, username, reset password petugas lain, tambah petugas baru,
nonaktifkan akun) — penanda `.izinkan-set-password` dan alat web tidak
dibutuhkan lagi. Alat itu tetap jalur pemulihan untuk keadaan genting:
seluruh akun staf masih `!LOCKED` atau operator kehilangan akses.

### 1.6a Jalankan migrasi database — TANPA SSH (`tools/migrasi-web.php`)

Alat sekali-pakai dengan penjagaan yang sama seperti alat set password
(§1.6): penanda di `storage/` + kode acak + CSRF + batas percobaan. Fungsinya
menjalankan berkas di `database/migrations/` memakai **koneksi aplikasi**,
sehingga baris `USE db_spmb_mualimku;` tidak perlu diedit dan tidak ada
kemungkinan migrasi diam-diam berjalan di skema yang salah.

1. Unduh kode baru (Git / Upload) lebih dulu — alat ini hanya menjalankan
   berkas migrasi yang ikut ada di folder `database/migrations/`.
2. File Manager → buat berkas `.izinkan-migrasi` di dalam folder `storage/`
   (nama persis, tanpa ekstensi) berisi **kode acak minimal 8 karakter**.
   Bila §1.5 sudah dijalankan, `storage/` yang benar ada **di luar root web**
   (`/home/USERNAME/spmb-data/storage`) — penanda di root proyek tidak akan
   terlihat oleh alat.
3. Buka `https://domain-anda/tools/migrasi-web.php` → ketik kode instalasi →
   halaman menampilkan tabel **apa yang diperiksa** (kolom/index per berkas)
   dan statusnya: `sudah terpasang` / `belum terpasang`.
4. Tekan **Jalankan migrasi tertunda**. Berkas yang sudah terpasang dilewati
   apa adanya; berkas yang belum dijalankan pernyataan `information_schema` +
   `PREPARE`-nya satu per satu, lalu hasilnya **diperiksa ulang** sebelum
   dianggap sukses.
5. Bila muncul pesan **"Akun database ini tidak punya hak ALTER/CREATE"** —
   itu normal: isi blok *Akun database lain (opsional)* dengan user/password
   **pemilik database** (yang dibuat di §1.1) lalu tekan lagi. Kredensial itu
   hanya dipakai di dalam request tersebut: tidak disimpan di sesi, tidak
   ditulis ke berkas, dan tidak dicetak balik ke layar.
6. Setelah semua target terverifikasi, penanda `.izinkan-migrasi` **terhapus
   sendiri** dan alat kembali menghasilkan 404. Berkas alatnya sendiri dibiarkan
   ada (aman tanpa penanda) supaya batch berikutnya cukup mengulang langkah 2.
   Penanda di-`.gitignore` (`storage/.izinkan-*`) dan dikecualikan dari
   `tools/buat-paket.php`, jadi tidak mungkin ikut terkirim ke server; bila
   masih tersisa saat rilis, menu **Kesiapan Rilis** menandainya GAGAL.
7. Berkas alat ini **tidak boleh** ikut ter-commit berisi target baru tanpa
   pemeriksaan: tiap entri `$TARGET` wajib membawa SQL `information_schema`
   sendiri, karena itulah yang membuat penekanan tombol dua kali tidak berbahaya.

Batch yang sedang berlaku di `$TARGET`:
`20261106_wa_teks_cms.sql` (kolom `pengaturan_sekolah.wa_teks`) dan
`20261106_peserta_ujian_multi_jadwal.sql` (`uq_peserta_jadwal` dipasang,
`uq_peserta_pendaftar` dilepas). Verifikasi manual tetap sama seperti di §1.2:

```sql
SHOW COLUMNS FROM pengaturan_sekolah LIKE 'wa_teks';
SHOW INDEX FROM peserta_ujian WHERE Key_name IN ('uq_peserta_jadwal','uq_peserta_pendaftar');
```

### 1.7 PHP & HTTPS

1. **MultiPHP INI Editor** → pilih domain → PHP **8.1 atau lebih baru**.
   File `.user.ini` di root proyek sudah menyetel `display_errors=Off`,
   `log_errors=On`, `upload_max_filesize=3M`, `post_max_size=8M`
   (berlaku untuk PHP-FPM/CGI).
2. **SSL/TLS Status** → pilih domain → **Run AutoSSL** (Let's Encrypt).
   Setelah sertifikat aktif, pastikan `APP_URL` memakai `https://`.
3. Bila memakai mod_php, pengaturan `php_flag` hanya ada di dalam
   `<IfModule mod_php.c>` (sudah benar di `.htaccess` — jangan mengeluarkannya,
   bisa error 500 di PHP-FPM).

### 1.8 Pemeriksaan akhir

1. Login operator → **Kesiapan Rilis**: semua item minimal *Perhatian*,
   tidak ada *Gagal* — termasuk item retensi, proxy, & lokasi storage.
2. **Pengaturan Sistem**: lengkapi profil sekolah, rekening/QRIS, nomor WA,
   daftar berkas persyaratan, S&K (Pasal 8 Privasi — tinjau sekolah/hukum,
   draf: `docs/KEBIJAKAN-DATA.md`). Nomor WA tujuan notifikasi panitia
   (`notif_wa_bendahara`) hanya tersimpan di database — ia **tidak** ikut seed,
   jadi isi ulang di server. Satu daftar nomor ini dipakai untuk kabar uang masuk
   (bukti transfer/QRIS diunggah & pembayaran gateway terverifikasi) **dan** kabar
   operasional panitia (pendaftar baru, data diajukan verifikasi, berkas
   dikirim/dikirim ulang) — akun Fonnte gratis mendaftarkan satu nomor, dan nomor
   itu adalah nomor operator. Isinya disunting di **kartu WhatsApp Gateway →
   blok Notifikasi WhatsApp Panitia**, atau cepat lewat **Petugas & Hak Akses →
   Notifikasi WhatsApp Panitia** (kolom yang sama: `notif_wa_bendahara`). Kabar
   operasional dibatasi 3 pesan/jam per pendaftar; uang masuk tidak dibatasi.
   Saklar `notif_wa_aktif`
   **tidak** memengaruhi pengingat angsuran orang tua (`pengingat_angsuran_wa`)
   maupun kirim jadwal/pengumuman ke wali. Kolom lama `notif_wa_operator` sudah
   pensiun — tidak dibaca atau ditulis lagi, jadi tidak perlu diisi di server.
   Sejak 2026-11-03 isi section **Pusat Bantuan
   (FAQ)** halaman depan juga disunting dari blok *Tanya Jawab (FAQ) Landing*
   (kolom `pengaturan_sekolah.faq_list`) — seed mengosongkan kolom itu, jadi
   selama belum disentuh halaman masih menampilkan contoh bawaan dari
   `includes/dummy.php`; tulis pertanyaan versi sekolah sebelum publik.
   Sejak 2026-11-06 **kalimat WA yang berangkat ke wali calon murid** juga
   bisa disunting tanpa menyentuh kode: **Pengaturan Sistem → Teks Pesan
   WhatsApp ke Calon Murid** (7 pesan, kolom JSON `pengaturan_sekolah.wa_teks`).
   Kolom itu NULL = aplikasi memakai kalimat bawaannya, jadi tidak wajib diisi;
   yang tersimpan hanya teks yang benar-benar berbeda dari bawaan. Placeholder
   wajib (mis. `{tautan_hasil}` pada pesan hasil seleksi) divalidasi saat Simpan.
   **Migrasi `database/migrations/20261106_wa_teks_cms.sql` wajib dijalankan di
   hosting** — tanpa kolom `wa_teks`, seluruh formulir Pengaturan Sistem gagal
   tersimpan (`1054 Unknown column`).
3. Uji manual mengikuti `docs/UJI-MANUAL.md` (registrasi, unggah, bayar,
   verifikasi, pengumuman, cek-status, tools CLI bila ada).
4. Bila ada akses CLI/terminal: jalankan `php tools/cek-proyek.php` dan
   `php tools/cek-statis.php` — keduanya **tanpa GAGAL**.
5. **Alamat masuk** — pihak sekolah: `https://domain-anda/admin` (folder
   `admin/index.php` hanya memuat `app/auth/login-sekolah.php`; alamat lama
   tetap berfungsi). Murid/orang tua: tombol **Login Siswa** di halaman SPMB
   publik. Bagikan `/admin` hanya ke panitia — alamatnya **tidak** ditautkan
   dari halaman login murid. Pemisahan alamat bukan pembatas
   akses, yang menegakkan hak akses tetap `require_role()` di tiap halaman.

---

## 2. Daftar Periksa Produksi (ringkas)

1. HTTPS aktif (AutoSSL/Let's Encrypt).
2. `.env` terisi (DB_*, APP_URL, FONNTE/MIDTRANS bila dipakai) —
   disimpan di luar `public_html` bila memungkinkan.
3. Database & user dibuat via wizard; setelah impor, grant dikurangi ke
   SELECT/INSERT/UPDATE/DELETE.
4. Impor SQL utama + migrasi berurutan; semua staf punya password sendiri
   (`!LOCKED` sudah tidak ada).
5. `storage/` di luar web root (`STORAGE_DIR` disetel) & bisa ditulis.
6. `.user.ini` / MultiPHP: PHP ≥ 8.1, display_errors Off.
7. HSTS aktif setelah HTTPS (opsional: uncomment di `.htaccess`).
8. Cron harian backup (bagian 3) + cron bulanan retensi (opsional, bagian 3b).
9. Kesiapan Rilis tanpa *Gagal*; uji manual lulus.
10. **LARANGAN:** jangan bagikan zip rilis yang memuat `.env`/`.git`/cadangan/
    dokumen pribadi — selalu pakai `php tools/buat-paket.php`.

---

## 3. Cadangan Database & Berkas

Dua tool CLI menulis ke `storage/backup/` (diblokir dari web oleh
`storage/.htaccess`):

```bat
php tools/backup-db.php        :: -> storage/backup/db/spmb-<waktu>.sql.gz
php tools/backup-berkas.php    :: zip dokumen pendaftar
```

- Retensi otomatis **14 hari**; password DB tidak pernah dicetak
  (`MYSQL_PWD` via env; bila `proc_open`/`mysqldump` tak tersedia, tool
  otomatis memakai **dump PDO murni** — aman di shared hosting).
- File hasil memuat data sensitif — jangan dibagikan; simpan salinan luar
  server (cloud terenkripsi / disk eksternal) minimal mingguan.

**Cron Jobs cPanel (tanpa SSH):**

1. Menu **Cron Jobs** → pilih *Daily* / *Monthly* → **Add**.
2. **PHP CLI ≠ PHP web** — path PHP CLI sering berbeda (mis.
   `/usr/local/bin/ea-php81` atau `/usr/local/bin/php`). Bila menu
   **Terminal** tersedia: `which php`. Bila tidak, tanyakan support hosting.
3. Contoh baris cron (sesuaikan username, path PHP, dan versi):

```
0 23 * * *  /usr/local/bin/ea-php81 /home/USERNAME/public_html/tools/backup-db.php
10 23 * * * /usr/local/bin/ea-php81 /home/USERNAME/public_html/tools/backup-berkas.php
0 1 1 * *   /usr/local/bin/ea-php81 /home/USERNAME/public_html/tools/retensi.php --jalankan
```

4. Pastikan `storage/backup/` & `storage/dokumen/` bisa ditulis user.

**Cadangan manual (cadangan terakhir bila cron bermasalah):**

- **phpMyAdmin → Export** (SQL, metode quick) → unduh ke komputer.
- **cPanel → Backup → Full Backup** → unduh cadangan penuh akun; simpan
  terenkripsi di luar server.

### 3b. Retensi berkas pribadi (opsional, Fase 12 E)

- Aktifkan & atur batas bulan di halaman operator **Retensi Data**
  (konfirmasi ganda sebelum eksekusi; jejak di `retensi_log`).
- Cron di atas menjalankan `tools/retensi.php --jalankan` bulanan —
  tanpa argumen `--jalankan` tool hanya simulasi (dry-run).
- Draf kebijakan utk tinjauan sekolah/hukum: `docs/KEBIJAKAN-DATA.md`.

### 3c. Pergantian tahun ajaran (Pusat Persiapan)

Tabel `pendaftar` **tidak** menyimpan kolom tahun; tahun hanya ada di nomor
`REG-YYYY-NNNN` dan setelan global `pengaturan_sekolah.tahun_ajaran`. Karena
itu pergantian tahun **tidak otomatis** — data tahun lama harus diarsipkan
secara sengaja. **Jangan hapus baris pendaftar**: 7 tabel anak memakai
`ON DELETE CASCADE`, sehingga pembayaran, dokumen, dan riwayat seleksi ikut
hilang. Skrip `database/reset_data_dummy.sql` hanya untuk data uji.

**Jalur utama — tanpa CLI.** Operator membuka sidebar
**Persiapan Tahun Ajaran** (`app/operator/persiapan.php`). Satu halaman berisi:

- **Saklar pendaftaran** — tombol **Buka/Tutup Pendaftaran** yang langsung
  berlaku pada formulir publik dan landing page. Tanggal buka/tutup di
  Pengaturan Sistem tetap informasi jadwal, bukan penentu status. Saklar
  memerlukan migrasi `20261115_pendaftaran_dibuka.sql` (sudah terdaftar di
  `tools/migrasi-web.php`).
- **Ringkasan musim** — pendaftar aktif per tahun, antrean yang belum selesai,
  rombel aktif & kuota, serta cadangan terakhir.
- **Daftar periksa "Siap buka SPMB"** — status hijau/kuning/merah untuk saklar,
  setelan tahun/gelombang/tanggal, arsip musim lama, antrean, rombel, S&K,
  Fonnte, dan cadangan ≤48 jam.

Urutan yang disarankan:

1. **Selesaikan musim lama** — semua pembayaran diputus, berkas final, dan
   rekap keuangan/daftar siswa sudah diekspor (`app/bendahara/rekap.php`,
   CSV Data Pendaftar).
2. **Cadangkan** — tombol **Buat Cadangan Sekarang** di halaman Persiapan
   (tersimpan sebagai `storage/backup/db/spmb-manual-<waktu>.sql.gz`), atau
   `php tools/backup-db.php` bila tersedia SSH.
3. **Tutup pendaftaran** lewat saklar agar tidak ada pendaftar baru yang masuk
   di tengah pengarsipan.
4. **Arsipkan tahun lama** — pada tabel per tahun tekan **Pratinjau**, ketik
   `ARSIP YYYY`, isi alasan, biarkan **cadangan otomatis** tercentang, lalu
   **Arsipkan**. Hanya baris `REG-YYYY-*` yang belum terarsip yang disentuh;
   akun portal siswa terkait dinonaktifkan.
5. **Verifikasi** — pendaftar lama hilang dari antrean/statistik/cek-status,
   sedangkan baris pendaftar, pembayaran, dan dokumen tetap ada (Data Pendaftar
   → filter **Terarsip**). Pemulihan per pendaftar: tombol **Pulihkan**.
6. **Rombel** — di bagian **Rombel tahun baru**, kode sudah terisi otomatis
   `kode-tahun` (71 → 71-27) dan opsi *nonaktifkan rombel lama* sudah tercentang;
   periksa lalu tekan **Gandakan Semua**. Kosongkan kode satu baris bila rombel
   itu tidak perlu dibuat, atau ubah kodenya bila sekolah memakai pola lain.
   Kuota pendaftaran = `SUM(kuota)` kelas `is_aktif = 1`, jadi jangan biarkan
   rombel lama dan baru aktif bersamaan.
7. **Setelan baru** — ubah tahun ajaran, gelombang, tanggal, biaya, teks WA,
   dan **naikkan versi S&K** di Pengaturan Sistem agar persetujuan angkatan
   lama tidak dianggap berlaku.
8. **Buka kembali** lewat saklar setelah daftar periksa tidak lagi merah.

**Fallback CLI** (bila halaman tidak dapat dipakai): lihat sebaran tahun dengan
`php tools/arsip-tahun.php`, simulasi dengan `--tahun=YYYY`, lalu eksekusi
`--terapkan --oleh=<username>`. Tool ini membuat cadangan
`storage/backup/db/spmb-sebelum-arsip-<tahun>-<waktu>.sql.gz` lebih dulu dan
menyentuh hanya baris yang belum terarsip, jadi menjalankan ulang aman
(0 kandidat). Opsi `--alasan="..."` (5–255 karakter) dan `--tanpa-cadangan`
(tidak disarankan). Rombel tetap perlu digandakan/nonaktifkan dari CMS.

Catatan: arsip memakai definisi tahun dari `REG-YYYY`, yaitu **tahun kalender**
saat nomor dibuat — bukan `tahun_ajaran`. Bila pendaftaran 2027/2028 dibuka
pada Januari 2027, nomornya `REG-2027`. Bila dua angkatan harus aktif
bersamaan atau riwayat perlu difilter per tahun ajaran secara persis, perlu
kolom tahun di `pendaftar` (belum ada).

---

## 4. Rilis & Pembaruan

1. Uji isi paket di mesin pengembangan (exit ≠ 0 bila ada file terlarang):
   `php tools/buat-paket.php --uji`
2. Buat paket: `php tools/buat-paket.php v1.2` → `dist/mualimku-v1.2.zip`.
3. Di server: ekstrak di atas instalasi lama (timpa file kode) —
   **jangan** timpa `.env`.
4. Jalankan migrasi baru (urut nama file — idempoten). Tanpa SSH:
   `tools/migrasi-web.php` seperti bagian **1.6a** (buat penanda
   `storage/.izinkan-migrasi` dulu, dan tambahkan nama berkas batch baru di
   `$TARGET`). Dengan phpMyAdmin: tab SQL per berkas, jangan lupa baris `USE`.
5. Verifikasi: `php tools/cek-proyek.php` + `php tools/cek-statis.php`
   tanpa GAGAL (bila ada CLI); uji cepat: landing page,
   `app/spmb/cek-status.php`, login staf.
6. Bila tahun ajaran berganti: buka **Persiapan Tahun Ajaran** dan ikuti
   prosedur **§3c** (saklar, arsip massal, rombel baru, daftar periksa), lalu
   ubah tahun ajaran & tanggal SPMB di **Pengaturan Sistem** (nilai bawaan
   seed sudah 2027/2028, Fase 12 F.1).

---

## 5. Pemulihan dari Cadangan

**Via phpMyAdmin (tanpa SSH):**

1. phpMyAdmin → pilih database → tab **Import** → pilih file `.sql`
   hasil dekompresi cadangan → Go. (Bila perlu, ekstrak `.sql.gz` dulu di
   komputer Anda — phpMyAdmin tidak selalu menerima `.gz`.)
2. File Manager → ekstrak `berkas-<waktu>.zip` ke folder `storage/dokumen`.

**Via CLI (bila tersedia):**

```bat
gzip -d storage\backup\db\spmb-20261016-230000.sql.gz
mysql -u root db_spmb_mualimku < storage\backup\db\spmb-20261016-230000.sql
```

Setelah pemulihan: `php tools/cek-proyek.php` (bila ada CLI) dan uji login
staf + cek-status pendaftar.

---

*Panduan ini disesuaikan Fase 12 (cPanel/Domainesia tanpa SSH, retensi data,
set-password-web, storage di luar web root).*
