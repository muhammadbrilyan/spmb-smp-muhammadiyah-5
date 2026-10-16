# Panduan Deploy — SPMB MUALIMKU

Panduan pemasangan & rilis aplikasi SPMB/CMS SMP Muhammadiyah 5 Kota Tangerang.
Bagian **Daftar Periksa Produksi** (lengkap) ada di bagian bawah.

**Syarat minimum: PHP 8.1 atau lebih baru** (Fase 12 C5) dengan ekstensi
`pdo_mysql`, `mbstring`, `curl`, `fileinfo`, `gd`, `zip`, `json`, `session`
(cek lengkap: `php tools/cek-proyek.php`).

---

## 1. Impor Database & Akun Staf

```bat
:: Impor skema + seed utama (tanpa data demo)
mysql -u root db_spmb_mualimku < database/db_spmb_mualimku.sql

:: OPSIONAL — data demo/UAT (hanya untuk pengembangan, JANGAN di produksi)
mysql -u root db_spmb_mualimku < database/seed_demo.sql
```

**PENTING (Fase 11):** seed utama sengaja **tidak memuat password login yang
bisa dipakai**:

- Akun **staf** (`operator`, `bendahara`, `kepsek`) di-seed dengan hash
  `'!LOCKED'` — **tidak bisa login** sampai Anda mengaturnya sendiri.
- Akun **siswa demo** (username `siswa`) hanya ada di `database/seed_demo.sql`
  dan juga terkunci.

Setelah impor, jalankan **untuk setiap akun staf**:

```bat
php tools/set-password.php operator
php tools/set-password.php bendahara
php tools/set-password.php kepsek
:: (bila memakai seed_demo)
php tools/set-password.php siswa
```

Masukkan password baru (min 10 karakter, konfirmasi 2×). Akun akan ditandai
**wajib ganti password** — pengguna dipaksa mengganti password saat login
pertama. Tool ini hanya bisa dijalankan lewat CLI (bukan lewat browser).

Migrasi SQL tambahan per fase: jalankan berurutan file di
`database/migrations/` (idempoten — aman dijalankan ulang).

---

## 2. Daftar Periksa Produksi (ringkas)

1. Hosting dengan **HTTPS** aktif (sertifikat Let's Encrypt / sejenisnya).
2. Salin `.env.example` → `.env`, lalu isi:
   - `APP_URL=https://domain-anda` (dipakai tautan WhatsApp).
   - Kredensial DB (`DB_*`) — buat user MySQL khusus terbatas.
   - `FONNTE_TOKEN` (token Fonnte untuk WhatsApp).
   - Kunci Midtrans **hanya bila** sekolah sudah punya akun gateway.
3. Buat user MySQL & hak akses: `mysql -u root < database/fix-grants.sql`.
4. Impor SQL & migrasi (lihat bagian 1), lalu `set-password.php` semua staf.
5. Login sebagai operator → **Pengaturan**: ubah S&K, daftar berkas
   persyaratan, rekening bank, gambar QRIS, nomor WA notifikasi.
6. Pengaturan PHP produksi — `display_errors=Off`, `log_errors=On`,
   `upload_max_filesize=3M`, `post_max_size=8M`:
   - **PHP-FPM/CGI (lazim di cPanel)**: file `.user.ini` di root proyek sudah
     berisi nilai di atas (berlaku otomatis); alternatif: ubah lewat menu
     **MultiPHP INI Editor** di cPanel.
   - **mod_php**: lewat `.htaccess` (sudah ada di dalam `<IfModule mod_php.c>`
     — direktif `php_flag`/`php_value` TIDAK boleh berdiri di luar IfModule
     karena memicu error 500 di PHP-FPM).
7. Izin folder: `storage/dokumen`, `storage/backup` dapat ditulis web server.
8. Setelah HTTPS jalan: aktifkan HSTS di konfigurasi web server.
9. Jadwalkan cadangan harian (lihat bagian 3).
10. Verifikasi akhir: `php tools/cek-proyek.php` harus **tanpa GAGAL**,
    lalu uji manual mengikuti `docs/UJI-MANUAL.md`.

**LARANGAN:** jangan membagikan file zip rilis yang memuat `.env` atau folder
`.git` — gunakan `php tools/buat-paket.php` (Fase 11 B1) yang sudah mengecualikan
keduanya.

---

## 3. Cadangan Database & Berkas

Dua tool CLI (Fase 11 B3) menulis ke `storage/backup/` — folder ini
**diblokir dari web** oleh `storage/.htaccess`.

```bat
:: Cadangan database -> storage/backup/db/spmb-<waktu>.sql.gz
php tools/backup-db.php

:: Cadangan dokumen pendaftar (KK, akta, bukti transfer)
php tools/backup-berkas.php
```

- Retensi otomatis **14 hari** (file lebih tua dihapus setiap kali tool dijalankan).
- Password database tidak pernah muncul di argumen proses (`MYSQL_PWD` via env)
  dan tidak pernah dicetak.
- Keduanya CLI-only; file hasil adalah data sensitif — **jangan dibagikan**,
  simpan juga salinan ke lokasi terpisah (disk eksternal / penyimpanan awan
  berenkripsi) minimal mingguan.

**Jadwal harian — Windows (Task Scheduler):**

```bat
schtasks /create /tn "SPMB Cadangan DB" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\mualimku\tools\backup-db.php" /sc daily /st 23:00
schtasks /create /tn "SPMB Cadangan Berkas" /tr "C:\xampp\php\php.exe C:\xampp\htdocs\mualimku\tools\backup-berkas.php" /sc daily /st 23:10
```

**Jadwal harian — Linux (cron):**

```cron
0 23 * * * /usr/bin/php /var/www/html/tools/backup-db.php >> /var/log/spmb-backup.log 2>&1
10 23 * * * /usr/bin/php /var/www/html/tools/backup-berkas.php >> /var/log/spmb-backup.log 2>&1
```

**Hosting bersama cPanel (mis. Domainesia) — dua jalur:**

1. **Cron Jobs cPanel** (menu "Cron Jobs") menjalankan `tools/backup-db.php`.
   - Tool **tidak bergantung** pada `mysqldump`/`proc_open`: bila keduanya
     dinonaktifkan hosting, otomatis memakai **dump PDO murni** (jalur shared
     hosting) — hasilnya tetap `.sql.gz` yang bisa diimpor phpMyAdmin.
   - **PHP CLI ≠ PHP web.** Path PHP CLI sering berbeda (mis.
     `/usr/local/bin/ea-php81`, `/usr/local/bin/php`, dsb — **jangan anggap
     pasti**). Cara memeriksanya: bila menu **Terminal** tersedia di cPanel,
     jalankan `which php` atau `command -v php`; alternatif lain, tanyakan
     support hosting. Contoh format cron (sesuaikan path hasil pemeriksaan):
     ```
     0 23 * * * /usr/local/bin/ea-php81 /home/USER/public_html/tools/backup-db.php
     ```
   - Pastikan `storage/backup/db/` bisa ditulis user hosting.
2. **Cadangan manual tanpa tool** (cadangan terakhir bila cron bermasalah):
   - **phpMyAdmin → Export** (format SQL, metode "quick") — unduh file
     `.sql` ke komputer Anda; perhatikan **batas ukuran unggah/unduh** hosting.
   - **cPanel → Backup / Full Backup** — unduh cadangan penuh akun (termasuk
     database & file) ke lokal; simpan terenkripsi di luar server.

---

## 4. Rilis & Pembaruan

1. **Uji dulu isi paket** — buat pohon umpan sementara, pastikan tidak ada file
   terlarang yang bocor (exit ≠ 0 bila bocor):
   ```bat
   php tools/buat-paket.php --uji
   ```
2. Buat paket dari mesin pengembangan — **bukan** dengan meng-zip folder kerja:
   ```bat
   php tools/buat-paket.php v1.2
   ```
   hasilnya `dist/mualimku-v1.2.zip` yang sudah diverifikasi bebas
   `.env`, `.git/`, `storage/backup/`, `node_modules/`, seed demo, dan dokumen pribadi.
3. Di server: ekstrak di atas instalasi lama (timpa file kode), **jangan**
   timpa `.env`.
4. Jalankan migrasi baru yang belum pernah jalan
   (urut nama file di `database/migrations/` — idempoten).
5. Verifikasi: `php tools/cek-proyek.php` harus tanpa GAGAL.
6. Uji cepat: halaman publik `index.php`, `app/spmb/cek-status.php`, login staf.

---

## 5. Pemulihan dari Cadangan

```bat
:: 1) Restore database (contoh file per 16 Okt 2026 jam 23:00)
gzip -d storage\backup\db\spmb-20261016-230000.sql.gz
mysql -u root db_spmb_mualimku < storage\backup\db\spmb-20261016-230000.sql

:: 2) Restore dokumen pendaftar: ekstrak isi
::    storage/backup/berkas-<waktu>.zip ke storage/dokumen/
```

Setelah pemulihan: jalankan `php tools/cek-proyek.php` dan uji login staf.

---

*Dokumen ini dilengkapi pada Bagian B4 Fase 11.*
