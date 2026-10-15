# Uji Manual — Fase 10 (Gateway Opsional + Hardening)

Checklist uji manual SPMB Muhammadiyah 5. Jalankan berurutan; centang bila **PASS**.
Server uji: `php -S localhost:8099 -t <root proyek>` — ganti host/port sesuai kebutuhan.

> **Sebelum mulai**: migrasi Fase 10 harus sudah dieksekusi
> (`database/migrations/20261015_fase10_gateway_opsional.sql` +
> `20261015_fase10_keamanan.sql`), dan seed diimpor ulang bila DB masih mentah.

---

## A. Gateway Opsional (Midtrans)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| A1 | Login operator → Pengaturan → Seksi **Payment Gateway** | Badge/status jelas: aktif hanya bila API key terisi |
| A2 | Lepas semua key Midtrans di `.env`, simpan pengaturan | `bayar_gateway_aktif` **dipaksa 0** server-side, tidak bisa disimpan aktif |
| A3 | Buka halaman bayar publik (`app/spmb/bayar.php?t=<pay_token>`) | Tombol bayar online (Snap) **tidak ada/tidak aktif**; transfer & QRIS tetap tampil |
| A4 | Isi ulang key Midtrans → centang gateway → simpan | Gateway bisa diaktifkan; tombol Snap muncul di halaman bayar |
| A5 | API `/app/api/bayar-snap` (atau tombol Snap) saat gateway mati | Respons 503 netral (tanpa kebocoran info) |

## B. Akun & Unggah Bukti

| # | Langkah | Diharapkan |
|---|---------|-----------|
| B1 | `php tools/set-password.php` (tanpa argumen, web akses `/tools/set-password.php`) | CLI: minta username; **web: 404** |
| B2 | `php tools/set-password.php operator` lalu ketik password baru < 10 | Ditolak "Minimal 10 karakter", ulang sampai valid, konfirmasi 2× |
| B3 | Setelah reset, login `operator` | **Terpaksa** ke Ganti Password sebelum halaman lain; sukses ganti → lepas |
| B4 | Cek `hak akses.txt` di root proyek | File **tidak ada** |
| B5 | Unggah bukti bayar di `bayar.php` **tanpa** `_csrf` (curl manual) | 403, file tidak tersimpan |
| B6 | Unggah dengan `pay_token` bukan 64-hex | 403/400, file tidak tersimpan |
| B7 | Unggah sah (CSRF + token benar) saat pendaftar sudah `VERIFIED` | Ditolak, **tidak ada UPDATE** |
| B8 | Unggah sah dua kali (metode beda) | File lama **terhapus**, file baru tersimpan, `bukti_transfer_url` menunjuk yang baru |
| B9 | Klik unggah 11× cepat | Rate limit 10/menit/IP aktif |
| B10 | `hasil uji` → pastikan `storage/dokumen/` bersih (hanya `.gitkeep`) | Tidak ada file sisa |

## C. Rate Lintas IP & Pesan Seragam

| # | Langkah | Diharapkan |
|---|---------|-----------|
| C1 | `cek-status.php` dengan nomor **salah** | Pesan **sama persis** dengan nomor benar + 4 digit salah: "Nomor pendaftaran atau 4 digit WhatsApp tidak cocok…" |
| C2 | Ulangi cek-status 6× dari IP sama (nomor sama) | Percobaan ke-6 **tetap diblokir meski IP beda** (limit per no_pendaftaran, kunci lintas IP) |
| C3 | "Pulihkan tautan bayar" 3× (IP beda, nomor sama) | Percobaan ke-4 diblokir (3/jam per nomor) |
| C4 | Unggah bukti 11× (IP sama) | Batas 10/menit tercapai (tiap percobaan dicatat, bukan hanya yang lolos) |

**Catatan trade-off (Fase 11 A5):** kunci rate per-nomor (`cek_status_no` /
`pulihkan_bayar_no`) berlaku **lintas IP** — orang lain yang mengetahui nomor
pendaftaran seseorang bisa mengunci pengecekan nomor itu selama 15 menit (3
menit pemulihan: 1 jam) dengan sengaja mengulanginya. Ini disengaja sebagai
perlindungan anti brute-force 4 digit WA; batas pemulihan jendela lebih panjang
dipilih relatif terhadap nilai token bayar. Kunci per-nomor hanya dicatat untuk
format `REG-YYYY-NNNN` valid, dan pencarian sukses tidak menambah hitungan
sehingga orang tua sah tidak terkunci karena sering memeriksa. Pembersihan
`rate_limit` (baris >2 hari) berjalan acak ±1% request (LIMIT 1000).

## D. Pengumuman, WA & Gate Dokumen

| # | Langkah | Diharapkan |
|---|---------|-----------|
| D1 | Panggil `pengumuman_status()` saat ada nilai terpilih | **Tidak** mengubah `pendaftar.tahap` (read-only); tahap berubah hanya lewat tombol operator |
| D2 | "Umumkan Sekarang" dari halaman operator | `tahap=DIUMUMKAN` terisi (finalisasi eksplisit) + WA terkirim bertahap |
| D3 | Cek teks WA instruksi (bayar) & pengumuman | Tautan **absolut** `http(s)://…/app/spmb/…` bila `APP_URL`/host tersedia |
| D4 | `APP_URL=https://contoh.test` di `.env` → `url_abs('x')` | `https://contoh.test/x` |
| D5 | **Kepsek** login → akses `app/api/dokumen.php?f=<file>` | **403** (Fase 10) |
| D6 | Operator & bendahara → URL yang sama | 200 (berkas diunduh) |
| D7 | Siswa lain → file siswa lain | 403 "berkas bukan milik Anda" |

---

## Lint & grep bukti (sekali jalan)

```bat
:: lint seluruh proyek (harus 0 gagal)
for /r %f in (*.php) do php -l "%f" | findstr /i "error" || echo OK
```

- [ ] `csrf_cek()` dipakai & **hasilnya dipakai** di semua API POST baru
- [ ] Prepared statement (`prepare(`) di query baru — tanpa interpolasi input
- [ ] Tidak ada rahasia di repo: `hak akses.txt` tiada, `password` hanya hash
- [ ] Tautan WhatsApp/API memakai `url_abs()` (bukan `url()`)

---

## Reset data uji

- Hapus file uji di `storage/dokumen/` (sisakan `.gitkeep`).
- Pendaftar uji: kembalikan `tahap`/`bukti_transfer_url` sesuai seed, atau re-import
  `database/db_spmb_mualimku.sql`.
- Bersihkan tabel `rate_limit` + `login_attempts` bila penuh percobaan uji.
