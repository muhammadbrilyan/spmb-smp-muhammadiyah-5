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
4. Migrasi tambahan per fase (idempoten, aman dijalankan ulang): buka tiap
   file di `database/migrations/` secara berurutan (nama file = urut waktu)
   di phpMyAdmin → tab SQL → Run.

**PENTING (Fase 11):** seed utama sengaja **tidak memuat password login yang
bisa dipakai** — akun staf (`operator`, `bendahara`, `kepsek`) di-seed dgn
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
   instalasi** tadi → pilih akun (`operator`, `bendahara`, `kepsek`) →
   isi password baru (min 10 karakter, konfirmasi 2×) → Simpan.
3. Ulangi untuk tiap akun staf. Setelah tiap sukses, penanda lama terhapus
   dan (bila masih ada akun terkunci) penanda **baru** berisi kode acak baru
   ditampilkan sekali di layar — catat untuk langkah berikutnya.
4. Setelah akun terkunci terakhir, penanda terhapus dan alat **menghapus
   dirinya sendiri**. Bila penghapusan diri gagal (izin file), segera hapus
   `tools/set-password-web.php` via File Manager — halaman Kesiapan akan
   menandai GAGAL selama penanda atau alat ini masih ada.
5. Login tiap staf — sistem memaksa **ganti password** saat login pertama.

Alternatif bila ada akses SSH/terminal cPanel:
`php tools/set-password.php <username>` (CLI-only).

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
   draf: `docs/KEBIJAKAN-DATA.md`).
3. Uji manual mengikuti `docs/UJI-MANUAL.md` (registrasi, unggah, bayar,
   verifikasi, pengumuman, cek-status, tools CLI bila ada).
4. Bila ada akses CLI/terminal: jalankan `php tools/cek-proyek.php` dan
   `php tools/cek-statis.php` — keduanya **tanpa GAGAL**.

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

---

## 4. Rilis & Pembaruan

1. Uji isi paket di mesin pengembangan (exit ≠ 0 bila ada file terlarang):
   `php tools/buat-paket.php --uji`
2. Buat paket: `php tools/buat-paket.php v1.2` → `dist/mualimku-v1.2.zip`.
3. Di server: ekstrak di atas instalasi lama (timpa file kode) —
   **jangan** timpa `.env`.
4. Jalankan migrasi baru (phpMyAdmin, urut nama file — idempoten).
5. Verifikasi: `php tools/cek-proyek.php` + `php tools/cek-statis.php`
   tanpa GAGAL (bila ada CLI); uji cepat: landing page,
   `app/spmb/cek-status.php`, login staf.
6. Bila tahun ajaran berganti: ubah tahun ajaran & tanggal PPDB di
   **Pengaturan Sistem** (nilai bawaan seed sudah 2027/2028, Fase 12 F.1).

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
