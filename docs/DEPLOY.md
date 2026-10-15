# Panduan Deploy — SPMB MUALIMKU

Panduan pemasangan & rilis aplikasi SPMB/CMS SMP Muhammadiyah 5 Kota Tangerang.
Bagian **Daftar Periksa Produksi** (lengkap) ada di bagian bawah.

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
6. `php.ini` produksi:
   `upload_max_filesize = 3M`, `post_max_size = 8M`, `display_errors = Off`.
7. Izin folder: `storage/dokumen`, `storage/backup` dapat ditulis web server.
8. Setelah HTTPS jalan: aktifkan HSTS di konfigurasi web server.
9. Jadwalkan cadangan harian (lihat bagian 3).
10. Uji akhir mengikuti `docs/UJI-MANUAL.md`.

**LARANGAN:** jangan membagikan file zip rilis yang memuat `.env` atau folder
`.git` — gunakan `php tools/buat-paket.php` (Fase 11 B1) yang sudah mengecualikan
keduanya.

---

## 3. Cadangan Database & Berkas

(Lihat bagian lengkap — Fase 11 B3.)

---

*Dokumen ini dilengkapi pada Bagian B4 Fase 11.*
