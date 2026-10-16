# Uji Manual — Fase 10 & 11 (Gateway, Hardening, Kesiapan Produksi)

Checklist uji manual SPMB Muhammadiyah 5. Jalankan berurutan; centang bila **PASS**.
Server uji: `php -S localhost:8099 -t <root proyek>` — ganti host/port sesuai kebutuhan.

> **Sebelum mulai**: migrasi Fase 10 harus sudah dieksekusi
> (`database/migrations/20261015_fase10_gateway_opsional.sql` +
> `20261015_fase10_keamanan.sql`) **dan migrasi Fase 11**
> (`database/migrations/20261016_fase11_pay_token_hash.sql`), serta seed diimpor
> ulang bila DB masih mentah. Data demo opsional (11 tahap, berkas, ujian):
> `mysql -u root db_spmb_mualimku < database/seed_demo.sql`.

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

## E. Fase 11 — WA, Token Bayar & Cek-Status (A1–A6)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| E1 | `app/api/snap-token.php` dari **publik** (belum login) utk uang masuk | Ditolak — hanya produk **FORMULIR** yang bisa diminta publik |
| E2 | Portal siswa tahap `< BERKAS_TERVERIFIKASI` minta snap-token uang masuk | Ditolak — uang masuk hanya setelah berkas terverifikasi |
| E3 | Siswa BERKAS_TERVERIFIKASI + pembayaran belum VERIFIED → snap-token | Diterima (jalur portal terautentikasi) |
| E4 | Ganti Password: password baru < 10 / = username / = lama | Ditolak bertahap, masing-masing dengan pesannya |
| E5 | Ganti Password 6× (gagal/sukses campur) dari IP & user sama | Percobaan ke-6 diblokir (5 / 15 menit, per IP **dan** per user) |
| E6 | Setelah ganti password sukses | `session_regenerate_id(true)` — ID sesi berubah, sesi tetap login |
| E7 | Pesan WA instruksi bayar (registrasi) saat transfer aktif & gateway mati | Hanya langkah transfer (+QRIS bila aktif); akhir pesan persis: "Setelah pembayaran terverifikasi, akun dan password dikirim lewat WhatsApp." |
| E8 | Kartu sukses registrasi | Langkah **identik** dengan pesan WA (satu sumber `wa_pesan_instruksi`) |
| E9 | Registrasi sukses → kirim WA ke nomor sama 3× / 24 jam (token baru tiap kali) | Percobaan ke-4 ditolak (rate `registrasi_wa` per nomor) |
| E10 | Cek-status dgn format nomor tak valid (mis. `ABC`) | Pesan **seragam**; kunci per-nomor **tidak dicatat** (hanya `REG-YYYY-NNNN` valid) |
| E11 | Cek-status **berhasil** (kombinasi benar) diulang 6× | Percobaan tetap dihitung normal — pencarian sukses tidak menambah hitungan; rate lama 10/15mnt/IP tetap berlaku |
| E12 | Setelah `php tools/cek-proyek.php` / biarkan >2 hari | Baris `rate_limit` usang terbersih otomatis (±1% request, hapus >2 hari, maks 1000) |
| E13 | Registrasi **kedua** dengan data sama saat sebelumnya sukses | Regristrasi idempoten: reuse reg_token → **pay_token dirotasi**, tautan bayar lama **404** |
| E14 | Cek `SELECT pay_token, pay_token_hash FROM pendaftar` | `pay_token` **NULL** untuk semua baris; pencarian hanya lewat `SHA2(?,256)` pada `pay_token_hash` (UNIQUE) |
| E15 | Halaman tautan bayar lama (`bayar.php?t=<token lama>`) | 404 / tidak mengarah ke pembayaran valid |
| E16 | Pesan WA penolakan verifikasi dokumen | Tautan menuju **cek-status** (`app/spmb/cek-status.php`), bukan halaman bayar — ada tombol "Pulihkan Tautan" |
| E17 | Telusuri sumber tampilan token bayar | Token polos hanya muncul di 3 tempat: sukses registrasi, pesan WA registrasi, hasil pemulihan cek-status |

## F. Fase 11 — Instrumen Rilis (B1–B6)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| F1 | `php tools/buat-paket.php` (atau akses via web `/tools/buat-paket.php`) | CLI: zip dibuat di `dist/` + daftar isi; **web: 404** |
| F2 | Periksa isi zip hasil F1 | `.env`, `.git/`, `dist/`, `hak akses.txt`, isi `storage/dokumen` (non-demo), `storage/backup` **tidak ada**; `.env.example` ikut |
| F3 | `php tools/cek-proyek.php` | 8 pemeriksaan; exit **1** bila ada GAGAL, **0** bila hanya PASS/WARN |
| F4 | Ganti sementara satu `password_hash` jadi hash md5 → jalankan F3 | **GAGAL** (akun hash lemah) + exit 1 — kembalikan setelahnya |
| F5 | `php tools/backup-db.php` | File `storage/backup/db/spmb-<ts>.sql.gz` valid (`CREATE TABLE`/`INSERT` ada saat dibuka) |
| F6 | `php tools/backup-berkas.php` | Zip `storage/backup/berkas-<ts>.zip` berisi `storage/dokumen` |
| F7 | Buat 2 backup lama berbeda tanggal → jalankan F5/F6 lagi | File >14 hari otomatis terhapus (rotasi retensi 14 hari) |
| F8 | Jalankan F5 tanpa kredensial DB valid | Gagal **jelas** tanpa mencetak password ke layar/log (password lewat env `MYSQL_PWD`) |
| F9 | `git check-attr text eol -- file` + `.editorconfig` | `text=auto`/`eol=lf` aktif; CRLF hanya `*.bat` |
| F10 | `mysql -u root db_spmb_mualimku < database/seed_demo.sql` **dua kali** | Idempoten — run ke-2 tanpa duplikat; total **11/11 tahap** (`tahap_unik=11`), siswa demo `'!LOCKED'` |
| F11 | `mysql -u root db_spmb_mualimku < database/seed_demo_hapus.sql` lalu F10 ulang | Bersih total lalu seed kembali sukses |
| F12 | `php tools/cek-statis.php` (Fase 12 D) | 12 pemeriksaan statis (syntax, BOM, CRLF, gitignore, git index, rahasia hardcode, eval, eksekusi, CSRF, mime, .htaccess/.user.ini); exit **1** bila ada GAGAL |

## G. Fase 11 — Halaman Kesiapan (C)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| G1 | Operator → sidebar **Kesiapan Rilis** → buka halaman | 200; skor %, status (SIAP/SIAP DENGAN CATATAN/BELUM SIAP), 13 item checklist, kartu antrean |
| G2 | Tanpa login → `app/operator/kesiapan.php` | 302 ke login (berikut `next=`) |
| G3 | Role selain operator/admin (mis. kepsek) → halaman sama | 403 / dialihkan (require_role) |
| G4 | Amati item terkait demo: nomor WA panitia | Badge **Perhatian** bila masih nomor contoh (`628123456789`) — status ikut data, bukan hiasan |
| G5 | Jadikan kolom `fonnte_token` kosong sementara → reload | Item Fonnte jadi Perhatian; tanpa token kirim WA mati |
| G6 | Periksa angka antrean vs SQL | Kartu antrean = jumlah `COUNT(*)` per tahap (BERKAS_DISUBMIT, BERKAS_REVISI, MENUNGGU_VERIFIKASI_BAYAR, BAYAR_TERVERIFIKASI) |
| G7 | Halaman ini | **Read-only** — tidak ada POST/tombol yang mengubah data |

## Skenario Penyalahgunaan (wajib lolos)

| # | Serangan yang disimulasikan | Diharapkan |
|---|----------------------------|-----------|
| X1 | Brute-force 4 digit WA via cek-status (6× cepat, IP beda-gonta-ganti) | Percobaan ke-6 per nomor **tetap diblokir** (kunci lintas IP, Fase 11 A5) |
| X2 | Enumerasi format nomor (`ABC`, `123`, `REG-26-1`) | Pesan seragam + kunci per-nomor **tidak dicatat** (tidak jadi alat DoS sendiri) |
| X3 | Kunci massal nomor orang lain via cek-status | Hanya format `REG-YYYY-NNNN` valid yang menghitung; pencarian **sukses tidak dihitung** — orang tua sah tak terkunci |
| X4 | Registrasi WA flood ke 1 nomor korban (4× dgn token beda) | Percobaan ke-4 ditolak (3 / 24 jam per nomor) |
| X5 | Pakai ulang tautan bayar lama setelah registrasi idempoten ulang | Token lama **mati** (rotasi) → 404; token baru hanya di 3 tempat tampil |
| X6 | Tebak/cari token bayar di DB & sumber web | Kolom `pay_token` selalu NULL; pencarian via hash SHA-256 UNIQUE; token polos hanya 3 titik tampil-terbatas |
| X7 | Panggil snap-token uang masuk utk pendaftar belum lolos berkas | Ditolak — tahap < BERKAS_TERVERIFIKASI |
| X8 | Serang ganti-password (6× cepat, 1 IP / 1 akun) | Diblokir 5 / 15 menit per IP **dan** per user |
| X9 | Buka `/tools/*.php` via browser | **404** — semua tools (buat-paket, backup, cek-proyek, set-password) CLI-only |
| X10 | Cari rahasia di paket rilis / repo | `.env` absen dari zip & repo; `hak akses.txt` tiada; password hanya hash bcrypt/`!LOCKED` |
| X11 | Edit `pay_token_hash` jadi duplikat | Ditolak DB (constraint UNIQUE) |
| X12 | Pembatasan teks penolakan verifikasi diklik korban | Mendarat di cek-status (pemulihan aman), bukan halaman bayar |

---

## Lint & cek proyek (sekali jalan)

```bat
:: lint seluruh proyek (harus 0 gagal)
for /r %f in (*.php) do php -l "%f" | findstr /i "error" || echo OK

:: cek kesiapan 8 pemeriksaan (exit 1 bila GAGAL)
php tools/cek-proyek.php
```

- [ ] `csrf_cek()` dipakai & **hasilnya dipakai** di semua API POST baru
- [ ] Prepared statement (`prepare(`) di query baru — tanpa interpolasi input
- [ ] Tidak ada rahasia di repo: `hak akses.txt` tiada, `password` hanya hash, `.env` tak ter-commit
- [ ] Tautan WhatsApp/API memakai `url_abs()` (bukan `url()`)

---

## Reset data uji

- Hapus file uji di `storage/dokumen/` (sisakan `.gitkeep`; file demo `dok-demo-*`/`bukti-demo-*` boleh
  disisakan — memang ikut repo).
- Pendaftar uji: kembalikan `tahap`/`bukti_transfer_url` sesuai seed, atau re-import
  `database/db_spmb_mualimku.sql`.
- Data demo lengkap: hapus dulu `mysql -u root db_spmb_mualimku < database/seed_demo_hapus.sql`,
  lalu seed ulang dengan `seed_demo.sql`.
- Bersihkan tabel `rate_limit` + `login_attempts` bila penuh percobaan uji:
  `DELETE FROM rate_limit; DELETE FROM login_attempts;`.
- Token bayar uji yang sudah dirotasi tidak bisa dipulihkan — buat pendaftar baru bila perlu alur bayar utuh.
