# MUALIMKU — SPMB & CMS SMP Muhammadiyah 5 Kota Tangerang

Aplikasi Sistem Penerimaan Murid Baru (SPMB) dan CMS akademik berbasis
PHP native + PDO + MySQL/MariaDB + Tailwind. Fase 0–12: pendaftaran daring,
pembayaran (transfer/QRIS/gateway opsional), verifikasi berkas, seleksi,
penempatan kelas, pengumuman, notifikasi WhatsApp, dokumen dinamis, dan
instrumen rilis/kesiapan produksi.

## Persyaratan

- **PHP >= 8.1** dengan ekstensi: `pdo_mysql`, `mbstring`, `curl`, `fileinfo`,
  `gd`, `zip`, `json`, `session`.
- MySQL 5.7/8 atau MariaDB 10.x.
- Tanpa dependensi Composer/npm (Tailwind di-build statis).

## Mulai cepat (XAMPP lokal)

```bat
mysql -u root < database/db_spmb_mualimku.sql
php tools/set-password.php operator
php -S localhost:8099
```

## Dokumentasi

| Dokumen | Isi |
|---------|-----|
| `docs/DEPLOY.md` | Pemasangan & rilis (termasuk hosting cPanel/Domainesia) |
| `docs/ALUR-PENDAFTARAN.md` | Alur end-to-end, model tahap, tabel per fase |
| `docs/UJI-MANUAL.md` | Checklist uji manual + skenario penyalahgunaan |
| `docs/KEBIJAKAN-DATA.md` | Draf kebijakan retensi & privasi data |
| `app/operator/panduan.php` | SOP operasional (tampil di aplikasi) |

Jalankan `php tools/cek-proyek.php` (lingkungan) dan
`php tools/cek-statis.php` (kode) sebelum setiap rilis.
