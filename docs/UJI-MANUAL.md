# Uji Manual — Fase 10 & 11 (Gateway, Hardening, Kesiapan Produksi)

Checklist uji manual SPMB Muhammadiyah 5. Jalankan berurutan; centang bila **PASS**.
Server uji: `php -S localhost:8099 -t <root proyek>` — ganti host/port sesuai kebutuhan.

> **Sebelum mulai**: migrasi Fase 10 harus sudah dieksekusi
> (`database/migrations/20261015_fase10_gateway_opsional.sql` +
> `20261015_fase10_keamanan.sql`) **dan migrasi Fase 11**
> (`database/migrations/20261016_fase11_pay_token_hash.sql`) serta Fase 13
> (`20261026_fase13_koreksi_data.sql`) + arsip (`20261028_arsip_pendaftar.sql`), serta seed diimpor
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
| B11 | Unggah bukti sah (transfer **atau** QRIS) di `app/spmb/bayar.php?t=…` | Popup "Bukti pembayaranmu sudah terkirim" berisi nama + No. Pendaftaran, tiga langkah berikutnya, imbauan tidak mengirim berulang, tautan Cek Status, tombol **Mengerti**. Setelah popup ditutup (tombol, klik latar, atau Esc) halaman dimuat ulang dan banner kuning "Bukti pembayaranmu sedang diperiksa" tampil |
| B12 | Perangkat aktifkan `prefers-reduced-motion` | Popup tetap tampil penuh, hanya tanpa efek muncul/hilang |

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
| E18 | Bendahara menolak bukti **formulir** (`verifikasi_bayar_tolak`) | WA ke orang tua berisi alasan + tautan **langsung** `app/spmb/bayar.php?t=<token baru>` — token lama mati karena dirotasi; halaman bayar menampilkan banner merah "Bukti pembayaran sebelumnya ditolak panitia" + alasan dan form unggah ulang tetap aktif. Non-formulir atau rotasi gagal → tetap fallback ke cek-status (E16) |

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
| F12 | `php tools/cek-statis.php` (Fase 12 D + Fase 13 butir 8) | **17 pemeriksaan** statis (syntax, BOM, CRLF, gitignore, git index, rahasia hardcode, eval, eksekusi, CSRF, mime, .htaccess/.user.ini, mojibake, csrf dibuang, midtrans_aktif izin, REMOTE_ADDR, ADD IF NOT EXISTS); exit **1** bila ada GAGAL |
| F13 | Buat penanda `storage/.izinkan-set-password` berisi **kode acak ≥8 karakter** → buka `/tools/set-password-web.php` (Fase 12 F.2 + Fase 13 butir 1) | Tanpa penanda: **404**; dgn penanda: **hanya form kode instalasi** (daftar akun tak tampil sebelum kode benar); POST sukses → password tersimpan, **penanda & berkas tool terhapus otomatis**, akses berikutnya 404; `/tools/cek-proyek.php` via browser → **403** (`.htaccess`) |
| F14 | Menu **Retensi Data** + `php tools/retensi.php` (Fase 12 E + Fase 13 butir 2–3) | Pratinjau kategori & pengaturan tersimpan; hapus berkas butuh **ketik no. pendaftaran** (konfirmasi salah ditolak); cakup **dokumen + bukti transfer `pembayaran`** (file fisik & `bukti_transfer_url` di-NULL); kategori *batal* = DITOLAK **atau** terhenti di tahap awal; riwayat tampil operator (`u.nama_lengkap`); CLI default **dry-run**, `--jalankan` tolak bila retensi nonaktif, eksekusi tercatat di `retensi_log` |
| F15 | `php tools/uji-klien-ip.php` (Fase 12 C3) | 12 kasus `klien_ip()` (tanpa proxy, XFF sah, pemalsuan, rantai, IPv6, header sampah); exit **1** bila ada GAGAL |

## G. Fase 11 — Halaman Kesiapan (C)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| G1 | Operator → sidebar **Kesiapan Rilis** → buka halaman | 200; skor %, status (SIAP/SIAP DENGAN CATATAN/BELUM SIAP), 18 item checklist, kartu antrean |
| G2 | Tanpa login → `app/operator/kesiapan.php` | 302 ke login (berikut `next=`) |
| G3 | Role selain operator/admin (mis. kepsek) → halaman sama | 403 / dialihkan (require_role) |
| G4 | Amati item terkait demo: nomor WA panitia | Badge **Perhatian** bila masih nomor contoh (`628123456789`) — status ikut data, bukan hiasan |
| G5 | Jadikan kolom `fonnte_token` kosong sementara → reload | Item Fonnte jadi Perhatian; tanpa token kirim WA mati |
| G6 | Periksa angka antrean vs SQL | Kartu antrean = jumlah `COUNT(*)` per tahap (BERKAS_DISUBMIT, BERKAS_REVISI, MENUNGGU_VERIFIKASI_BAYAR, BAYAR_TERVERIFIKASI) |
| G7 | Halaman ini | **Read-only** — tidak ada POST/tombol yang mengubah data |

## H. Fase 13 — Arsip Pendaftaran (soft archive)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| H1 | Operator → Data Pendaftar → tombol **Arsipkan** pada sebuah baris | Modal konfirmasi muncul di halaman yang sama; **alasan wajib diisi** (min. 5 karakter) sebelum tombol bisa dipakai |
| H2 | Arsipkan pendaftar, cek SQL | `arsip_at = NOW()`, `arsip_alasan` & `arsip_oleh` terisi; baris **tidak** dihapus — pembayaran, dokumen, koreksi tetap utuh |
| H3 | Cek `users.is_active` akun siswa pendaftar itu | **0** (login portal siswa ditolak sementara) |
| H4 | Reload dashboard operator, antrean berkas & verifikasi data, daftar bendahara, kartu statistik, halaman kesiapan | Pendaftar terarsip **hilang** dari semua antrean dan angka; statistik tidak menghitungnya |
| H5 | Export CSV pendaftar + counter publik (`app/spmb/index.php`) + `cek-status.php` dengan nomor terarsip | Terarsip tidak ikut ekspor/tidak ditemukan publik |
| H6 | Filter **Tampilkan = Terarsip** pada Data Pendaftar | Hanya baris terarsip (badge "Terarsip" + alasan + petugas + waktu), urut waktu pengarsipan; tombol jadi **Pulihkan** |
| H7 | Buka `verifikasi.php?tab=data&id=<pendaftar terarsip>` lalu coba ubah status | Ditolak: "Pendaftar ini sudah diarsipkan — pulihkan dulu…" |
| H8 | Klik **Pulihkan** (konfirmasi) | `arsip_at/alasan/oleh` kembali NULL, `is_active = 1`, pendaftar muncul lagi di semua antrean |
| H9 | Registrasi baru dengan data Identik pendaftar terarsip | Allowed — cek duplikat mengabaikan baris terarsip (`arsip_at IS NULL`) |
| H10 | Arsipkan/pulihkan 11× dalam 1 jam oleh operator sama | Rate limit **10/jam per user** aktif (pesan flash error) |
| H11 | Tautan bayar publik (`bayar.php?t=<token>` milik terarsip) | Tidak aktif — `pendaftar_by_pay_token()` menyaring `arsip_at IS NULL` |
| H12 | POST arsip/pulihkan tanpa `_csrf` valid | Ditolak, redirect tanpa perubahan data |

## I. WhatsApp Gateway (Fonnte) — token & uji koneksi

| # | Langkah | Diharapkan |
|---|---------|-----------|
| I1 | Pengaturan Sistem → simpan formulir **tanpa mengetik token** | Token lama **tidak terhapus**; flash berbunyi "token … teruji: Token aktif — Device …, kuota …, kedaluwarsa …" |
| I2 | Tempel token **salah** → simpan | Nilai tersimpan, flash **error**: "Token ditolak Fonnte (token invalid)" — bukan sukses hening |
| I3 | `FONNTE_TOKEN` terisi di `.env` | Kotak hijau "Mode .env aktif" + sidik jari `••••xxxx (n karakter)`; badge "Token aktif (dari .env)" |
| I4 | Centang **Hapus token dari database** → simpan (dengan `.env` kosong) | `pengaturan_sekolah.fonnte_token` = NULL; badge jadi "Belum diatur"; kirim WA menolak "Token Fonnte belum diatur" |
| I5 | Intip `view-source` halaman pengaturan | Input `fonnte_token` / `midtrans_server_key` / `midtrans_client_key` **tanpa atribut `value`** (rahasia tidak tercetak ke HTML) |
| I6 | Kirim kredensial akun siswa (`app/operator/akun.php`) | Terkirim; `wa_log.respons` tidak lagi berisi `invalid/empty body value` (penyebab lama: `typing=off`) |
| I7 | Uji manual tanpa mengirim WA: `POST https://api.fonnte.com/device` header `Authorization: <token>` | `{"status":true,…}` utk token benar; `{"reason":"token invalid","status":false}` utk token salah |

## J. Petugas & Hak Akses (`app/operator/petugas.php`)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| J1 | Operator → sidebar **Pengaturan Sistem → Petugas & Hak Akses** | 200; tabel memuat hanya akun non-siswa, baris milik sendiri ber-badge **Anda**, kolom Password menampilkan *Belum disetel* / *Wajib diganti* / *Tersetel* sesuai data |
| J2 | Ubah **nama** & jabatan bendahara lewat modal Ubah | Flash sukses; nama baru langsung dipakai di header/topbar bendahara setelah ia login (sesi lama tidak ikut berubah) |
| J3 | Ganti username kepsek menjadi `kepsek-baru` | Flash menyebut username baru; login memakai username lama **ditolak**, `kepsek-baru` diterima |
| J4 | Username tidak sah: `AB`, `Huruf Besar`, atau sudah dipakai | Ditolak dengan pesan spesifik (format 4-30 huruf kecil / sudah dipakai); data lama tidak berubah |
| J5 | Reset password akun lain (min 10 karakter, konfirmasi sama) | Hash bcrypt baru tersimpan + `wajib_ganti_password = 1`; password tidak pernah tampil lagi di layar |
| J6 | Petugas login dengan password sementara | **Dialihkan paksa** ke `app/staf/ganti-password.php` sebelum halaman apa pun terbuka; flag lepas setelah sukses ganti |
| J7 | Tambah petugas baru (role Bendahara, jabatan dikosongkan) | Akun aktif baru; jabatan otomatis mengikuti label role; `wajib_ganti_password = 1` |
| J8 | Nonaktifkan akun petugas lain, lalu aktifkan kembali | Yang bersangkutan tidak bisa login (`is_active = 0`, akun tak ditemukan saat login); baris & datanya tetap ada |
| J9 | Reset password / ganti username akun **sendiri** | Ditolak — tombol tidak ditampilkan dan POST langsung ditolak; arahkan ke halaman Ganti Password |
| J10 | Nonaktifkan satu-satunya akun operator aktif | Ditolak: "Akun ini satu-satunya operator aktif. Buat atau aktifkan operator lain lebih dulu." |
| J11 | Role bendahara/kepsek membuka URL `app/operator/petugas.php` | Dialihkan ke portal netral (require_role operator/admin) |
| J12 | Kesiapan Rilis saat masih ada akun `!LOCKED` | Item "Akun staf sudah memiliki password" berwarna Perhatian dan menautkan halaman Petugas & Hak Akses |

## K. Persetujuan Penetapan Hasil (`app/kepsek/penetapan.php`)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| K1 | Login kepsek → sidebar **Monitoring → Persetujuan Hasil** | 200; 4 kartu ringkasan (total aktif, lulus, tidak lulus, masih proses) + tabel daftar hasil + sidik 12 karakter pertama |
| K2 | Operator membuka `app/operator/pengumuman.php` sebelum ada persetujuan | Banner "Menunggu persetujuan kepala sekolah"; tombol **Umumkan Sekarang** & **Simpan Jadwal** `disabled`; POST langsung ditolak |
| K3 | Kepsek menekan **Setujui Daftar Ini** → modal konfirmasi | Modal ber-`modal-root` tampil; pernyataan telaah wajib dicentang; aksi `setujui` terkirim |
| K4 | Centang lalu simpan | Badge berubah jadi **Disetujui** + nama & waktu; kolom `hasil_disetujui_*` terisi, sidik = sidik daftar saat ini |
| K5 | Operator memuat ulang halaman pengumuman | Banner hijau "Daftar hasil disetujui kepala sekolah"; tombol aktif; **Umumkan Sekarang** berhasil (`hasil_diumumkan_pada` terisi, tahap naik ke DIUMUMKAN) |
| K6 | Setelah terbit, kepsek menekan **Tandatangani Rilis** | Badge **Ditandatangani**; blok tanda tangan pada hasil cetak menampilkan catatan "Sudah ditandatangani digital · tanggal" |
| K7 | **Sebelum** ada rilis, coba tekan/batch-POST `ttd` | Tombol `disabled` dan POST ditolak: "Belum ada rilis yang ditandatangani…" |
| K8 | Operator mengubah nilai/status satu pendaftar setelah persetujuan | Kedua catatan jadi badge **Batal** + penjelasan "daftar hasil berubah"; `persetujuan_hasil_sah()` false; penerbitan/jadwal ditolak lagi sampai ada persetujuan baru |
| K9 | Kepsek memilih **Cabut Persetujuan** | Modal konfirmasi merah → kolom dikosongkan, penerbitan terkunci kembali |
| K10 | Pendaftar diarsipkan/dipulihkan setelah persetujuan | Sidik ikut berubah (sidik hanya atas `arsip_at IS NULL`) → catatan menjadi **Batal** — persetujuan harus diminta ulang atas daftar yang benar-benar akan terbit |
| K11 | Kepsek mencetak halaman | Kop laporan + tabel + blok tanda tangan tercetak; filter, tombol aksi, sidebar & topbar tidak ikut (`no-print`) |

## L. Pengaturan Sistem / CMS (`app/operator/pengaturan.php`)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| L1 | Login operator → **Pengaturan Sistem** → isi alamat sekolah → **Simpan** | Toast hijau "Pengaturan tersimpan…"; muat ulang halaman, textarea masih berisi alamat yang diketik (bukan alamat contoh `Jl. Perintis Kemerdekaan…`) |
| L2 | Buka landing publik / footer / kartu hasil | Alamat yang baru tersimpan ikut berubah — nilai `$PENGATURAN` = isi database |
| L3 | Kosongkan satu kolom (mis. Email) lalu **Simpan** | Kolom itu jadi **NULL** di DB, bukan terisi ulang nilai contoh dari `includes/dummy.php`; formulir menampilkannya kosong |
| L4 | Isi ulang kolom tersebut → Simpan | Nilai kembali tersimpan; kolom lain tidak ikut berubah (logo, banner, QRIS, jalur, S&K tetap) |
| L5 | Ubah **Biaya Masuk** atau **Biaya Formulir** di kartu Rincian Biaya SPMB | Baris rincian & nominal Angsuran 1/2 langsung ikut berubah di layar tanpa Simpan (lihat bagian Q) |
| L6 | **Simpan** padahal DB tertinggal satu migrasi (buang kolom contoh: `ALTER TABLE pengaturan_sekolah DROP COLUMN pengingat_angsuran_wa`) | Toast merah menyebut "Struktur basis data belum lengkap (… Unknown column …) — jalankan database/migrations/"; **tidak ada** perubahan yang tersimpan separuh |
| L7 | `php tools/cek-proyek.php` sesudah langkah L6 | Pemeriksaan 5 **GAGAL**: `1 kolom ada di seed tapi belum ada di DB: pengaturan_sekolah.pengingat_angsuran_wa`; pulihkan dengan `mysql -u root < database/migrations/20261031_pengingat_angsuran.sql` → 8 PASS |
| L8 | Simpan dengan token Fonnte kosong di form (tidak mengetik ulang) | Token tersimpan **tidak terhapus**; penghapusan hanya lewat checkbox "Hapus token dari database" |

## M. Bendahara — Dashboard & Verifikasi Pembayaran

| # | Langkah | Diharapkan |
|---|---------|-----------|
| M1 | Login bendahara → sidebar **Keuangan SPMB** | Tiga menu terpisah: **Dashboard Bendahara**, **Verifikasi Pembayaran**, **Rekap Keuangan** (hanya satu yang tersorot aktif per halaman) |
| M2 | Buka **Dashboard Bendahara** (`app/bendahara/index.php`) | 200; 4 kartu statistik (pendaftar, dana terverifikasi, menunggu, ditolak) + bar **Progres Pemeriksaan Bukti** + panel **Perlu Tindakan** (manual vs gateway + umur bukti terlama) + tabel **Perolehan per Jenis** + grafik **Aliran Dana 14 hari** + **Keputusan Terbaru**; **tidak ada** tabel antrean/action bendahara di sini |
| M3 | Klik kartu "Menunggu Verifikasi" → **Perlu Tindakan**, atau tombol **Verifikasi Pembayaran** di judul | Mendarat di `app/bendahara/pembayaran.php`; hanya bukti `PENDING` yang tampil; chip jumlah ikut (mis. `Menunggu 3`) |
| M4 | Buka **Verifikasi Pembayaran** saat antrean kosong | Baris kosong berbunyi "Antrean kosong — semua bukti pembayaran sudah diputus."; ganti chip → "Tidak ada transaksi pada saringan ini." |
| M5 | Pindah chip **Ditolak** / **Sudah Terverifikasi** / **Semua Transaksi** | URL `?s=REJECTED`, `?s=MASUK`, `?s=SEMUA`; `MASUK` menggabungkan `VERIFIED`+`LUNAS`+`ANGSURAN_1/2`; pencarian & paginasi 25/baris tetap jalan |
| M6 | Setujui satu bukti dari saringan apa pun (mis. `?s=MASUK`) | POST ke `app/bendahara/verifikasi.php` membawa `saringan`; setelah PRG kembali ke saringan yang sama (`pembayaran.php?s=MASUK`) dengan flash sukses — bukan ke dashboard |
| M7 | Tolak bukti lewat modal (alasan wajib) | Modal `modal-tolak` ikut mengirim `saringan`; alasan kosong ditolak; WA alasan tercatat |
| M8 | Ketik nilai `saringan` asing (mis. `BOGA'x`) pada POST | Disaring daftar putih `[PENDING, REJECTED, MASUK, SEMUA]` → kembali ke `pembayaran.php` tanpa parameter, data tidak berubah |
| M9 | Operator membuka `app/bendahara/pembayaran.php` | Tetap ditolak (`require_role(['bendahara','admin'])`) → flash "tidak memiliki hak akses" ke `app/portal/index.php`; **admin** sekarang boleh membuka (setara dengan halaman keuangan lain) |
| M10 | Notifikasi `BUKTI_BAYAR_MASUK` di `app/operator/notifikasi.php` | Bagi operator/kepsek/bendahara yang bukan pemilik halaman, tombol **Buka** tidak dipasang — diganti chip "Ruang Kerja Bendahara" (judul tooltip); hanya bendahara/admin yang melihat tombol **Buka** → `app/bendahara/pembayaran.php` |
| M11 | Tombol **Kirim Pengingat Jatuh Tempo** (ada di dashboard & panel antrean) | Keduanya POST ke `index.php`; flash hasil berjalan; tetap tinggal di halaman asal |
| M12 | Operator mem-POST langsung ke `app/bendahara/verifikasi.php` (URL endpoint diketahui) | Ditolak — gerbang endpoint disamakan dengan halamannya (`bendahara`/`admin`), dahulu `operator` ikut diizinkan sehingga keputusan bayar bisa ditulis dari luar halaman bendahara |
| M13 | Calon pendaftar unggah bukti transfer/QRIS di `app/spmb/bayar.php?t=…` (atau siswa unggah di `app/siswa/keuangan.php`) saat `notif_wa_aktif=1` | Nomor di **Nomor WA Bendahara** (`notif_wa_bendahara`, Pengaturan Sistem → Notifikasi Operator) menerima WA berisi nama, No. Pendaftaran, metode, nominal, dan tautan `app/bendahara/pembayaran.php`; tercatat di `wa_log` bertipe `BUKTI_BAYAR_MASUK` walau **Nomor WA Operator** kosong |
| M14 | **Nomor WA Bendahara** kosong | Salinan bukti bayar jatuh ke **Kontak WhatsApp Panitia SPMB** (`kontak_wa_panitia`) — tidak ada pemicu yang hilang diam-diam |
| M15 | Nomor yang sama ada di daftar **Nomor WA Operator** dan **Nomor WA Bendahara** | Hanya satu pesan terkirim — tujuan dinormalisasi (`wa_normalize`) lalu dideduplikasi, tidak ada pesan dobel ke ponsel yang sama |
| M16 | Pemicu bukan-bayar (mis. `BERKAS_DISUBMIT`) dengan hanya nomor bendahara yang terisi | Nomor bendahara TIDAK dikirimi — rute tambahan khusus tipe pembayaran (`BUKTI_BAYAR_MASUK`, `PEMBAYARAN_GATEWAY_MASUK`, `PEMBAYARAN_GATEWAY_LUNAS`); pemicu lain hanya lewat `notif_wa_operator` |
| M17 | `notif_wa_aktif=0` | Tidak ada WA sama sekali (in-app notifikasi tetap tersimpan) — toggle tetap jadi sakelar induk |
| M18 | CMS: toggle aktif, **Nomor WA Operator** dan **Nomor WA Bendahara** kosong, Kontak WhatsApp Panitia terisi → Simpan | Tersimpan (dahulu gagal dengan "daftar nomor tujuan masih kosong"); ditolak hanya bila ketiga nomor kosong. Format tiap daftar nomor divalidasi terpisah, pesalan galat menyebut "operator" atau "bendahara" |
| M19 | Menerbitkan skema lama ke hosting | Wajib `mysql -u root < database/migrations/20261101_notif_wa_bendahara.sql` lebih dulu; tanpanya seluruh simpan Pengaturan Sistem batal (`1054 Unknown column 'notif_wa_bendahara'`) — terlihat sebagai GAGAL pada pemeriksaan 5 `tools/cek-proyek.php` |
| M20 | Pembayaran selesai lewat gateway Midtrans (callback `PEMBAYARAN_GATEWAY_LUNAS` / `PEMBAYARAN_GATEWAY_MASUK`) saat **Nomor WA Operator** kosong | Nomor **WA Bendahara** tetap menerima salinan (pesan sudah berisi tautan `app/bendahara/pembayaran.php`) — dahulu tidak terkirim sama sekali karena rute bendahara hanya mengenali `BUKTI_BAYAR_MASUK` |

## N. Kelola Jadwal Tes (`app/operator/jadwal.php`)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| N1 | Operator → sidebar **SPMB → Kelola Jadwal** | 200; tanpa tabel inline-edit 9 kolom lagi — daftar baca-saja (kartu tanggal, nama, status, kode, tanggal·jam·metode·ruangan, peserta & kapasitas) + 3 chip ringkasan (Draft / Terjadwal / Selesai) |
| N2 | Lihat di layar lebar apa pun, lalu perkecil jendela sampai ~500 px | **Tidak ada scrollbar horizontal**: `overflow-x-auto` dan `min-w-[960px]` sudah dihapus, tiap baris `flex-wrap` sehingga blok aksi turun ke baris berikut |
| N3 | Tekan **Tambah Jadwal** di kanan atas | Modal `modal-jadwal` terbuka kosong dengan bawaan 08:00–10:00, Metode Offline, Status Draft, Kapasitas 0; judul "Tambah Jadwal Tes", sub "Kode jadwal dibuat otomatis setelah disimpan" |
| N4 | Tekan **Ubah** pada baris TES-05 | Modal yang sama terisi persis data baris itu — termasuk `<select>` Metode & Status (app.js kini mengisi `SELECT` lewat `.value`, bukan `textContent`, supaya `<option>` tidak terhapus); sub berubah jadi "Kode TES-05 tetap dan tidak ikut berubah" |
| N5 | Tekan **Ubah** dua kali berturut-turut pada baris yang sama, lalu pada baris lain | Opsi dropdown tetap 3 dan nilai ikut baris terakhir — pembuktian bahwa pengisian ulang modal idempoten |
| N6 | Kosongkan Nama Kegiatan lalu simpan | Validasi server menolak; halaman dimuat ulang dengan banner merah **dan** modal terbuka lagi terisi kiriman terakhir (Judul "Ubah Jadwal Tes" bila asalnya dari Ubah, lengkap dengan kode jadwalnya) |
| N7 | Simpan Ubah pada jadwal yang `peserta_dijadwalkan`-nya terisi dari halaman Peserta Ujian | Jumlah peserta tidak hilang (field disembunyikan sebagai `<input type="hidden">` yang nilainya ikut terbawa). Uji pembanding: POST `ubah` tanpa field itu menulis ulang jadi 0 — inilah yang dicegah |
| N8 | Tekan ikon WA pada satu baris | `confirm()` muncul, POST `kirim_notifikasi` jalan, flash hasil, tetap di halaman yang sama. Penerima dibaca dari **`peserta_ujian` dan `seleksi.jadwal_id`** (dahulu hanya `seleksi`, sehingga tombol ini tidak pernah punya penerima); Lokasi memakai **Ruangan** yang diisi operator, kolom lama `lokasi` hanya cadangan |
| N9 | Tekan ikon Hapus pada jadwal yang dipakai `seleksi` | Flash error "Jadwal tidak bisa dihapus karena sudah dipakai seleksi." — data tetap ada |
| N10 | hapus semua jadwal (daftar kosong) | Empty state: ikon `event_busy` + "Belum ada jadwal tes" + petunjuk menekan **Tambah Jadwal**; chip ringkasan menampilkan 0/0/0 |
| N11 | Setelah `assets/js/app.js` / `assets/css/*.css` diubah, muat halaman biasa (tanpa hard reload) | `asset()` menempel `?v=<filemtime>` sehingga URL berubah dan browser mengambil berkas baru — dahulu hanya 4 tempat di kode yang menulis penanda versi manual, sisanya memakai salinan lama (dropdown Metode/Status di modal jadwal tampak kosong persis karena ini) |
| N12 | Lihat seksi **Umumkan Jadwal ke Wali Siswa** (di bawah chip ringkasan) | Menunjukkan "X jadwal terjadwal · Y penerima WhatsApp"; tombol hanya muncul bila keduanya > 0. Tanpa jadwal Terjadwal → peringatan "Ubah status jadwal menjadi Terjadwal lebih dulu"; tanpa penerima → tautan ke **Peserta Ujian** |
| N13 | Tekan **Umumkan Semua Jadwal** | Modal `modal-umumkan` terbuka berisi pratinjuan persis isi pesan (satu nomor per kegiatan: nama + kode, tanggal Indonesia · jam–jam WIB · metode · ruangan) dan hitungan penerima. **Batal** tidak mengirim apa pun |
| N14 | Tekan **Kirim Sekarang** | Satu WA per wali siswa berisi SELURUH jadwal (bukan satu WA per kegiatan) + satu baris `notifikasi_siswa` tipe `JADWAL_UJIAN` (judul "Jadwal Tes SPMB (N kegiatan)", tautan `app/siswa/ppdb.php?tab=jadwal`) + baris `wa_log` tipe `JADWAL_UJIAN`. Flash: "N wali siswa menerima satu pesan berisi X jadwal" |
| N15 | Tekan **Kirim Sekarang** dua kali berturut-turut tanpa mengubah jadwal | Kirim kedua = `lewati` semua penerima (ref_id = sidik jari susunan jadwal). Pendaftar yang WA-nya tercatat GAGAL saja yang dicoba ulang, tanpa menduplikasi baris notifikasinya |
| N16 | Ubah tanggal salah satu jadwal, lalu kirim lagi | Sidik jari berubah → pengumuman dianggap baru dan terkirim lagi. Di portal siswa, pesan tampil utuh dengan baris-barisnya (`whitespace-pre-line` di `app/siswa/notifikasi.php`) dan lonceng bertambah 1 |

## O. Peserta Ujian & Navbar Portal Siswa (rombak 2026-11-01)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| O1 | Operator → sidebar **Seleksi & Penerimaan → Peserta Ujian** | 200; **tidak ada dropdown pilihan jadwal lagi** dan URL tidak memakai `?j=`. Semua jadwal berstatus Terjadwal tampil sekaligus sebagai kartu, diurutkan tanggal + jam |
| O2 | Bandingkan isi kartu dengan status di Kelola Jadwal | Jadwal Draft/Selesai **tidak** punya kartu; hanya disebut pada catatan "N jadwal berstatus Draft/Selesai tidak dikelola di sini…" lengkap dengan kode, status, dan jumlah peserta yang sudah terlanjur ada |
| O3 | Lihat satu kartu | Judul kegiatan + badge kode; baris meta tanggal Indonesia · jam–jam WIB · metode · ruangan; angka "peserta/kapasitas" di kanan; bar kapasitas terisi; baris sisa kuota — dan peringatan kuning "N peserta belum punya nomor WA orang tua" hanya bila memang ada |
| O4 | Penuhi kapasitas satu jadwal (atur otomatis pada jadwal berkapasitas kecil) | Badge **Kapasitas penuh** muncul, tombol *Atur Peserta Otomatis* dan *Tambah* jadi `disabled` (`.btn:disabled` = pudar & tidak bisa diklik), *Kirim WA Jadwal* tetap aktif |
| O5 | Jadwal Terjadwal yang pesertanya masih 0 | *Kirim WA Jadwal* `disabled`; badan kartu menampilkan empty state "Belum ada peserta pada jadwal ini" |
| O6 | Tekan **Atur Peserta Otomatis** / **Kirim WA Jadwal** pada salah satu kartu | `confirm()` menyebut kode jadwal itu saja; setelah PRG, halaman kembali ke daftar penuh (bukan ke satu jadwal terpilih) dan flash tetap terbaca |
| O7 | Tambah manual pada dua kartu sekaligus | Tiap kartu punya `<select>` sendiri dengan id unik `pm-<id jadwal>`; pilihan yang sama (pendaftar BERKAS_TERVERIFIKASI yang belum punya jadwal mana pun, terarsip dikecualikan) dan otomatis berkurang setelah dipakai |
| O8 | Perkecil jendela sampai ~400 px | Daftar peserta memakai `<ul>` vertikal (nomor peserta rata kiri, nama + no. pendaftaran + asal sekolah, nomor WA, ikon hapus) — **tanpa scrollbar horizontal**, pengganti tabel `min-w-[760px]` lama |
| O9 | Buka portal siswa (peran `siswa`) | Topbar sama persis dengan topbar peran sekolah: hanya chip tahun ajaran + lonceng + `?` + avatar. **Tidak ada** breadcrumb "Portal Siswa › …" dan **tidak ada** chip "Gateway WA dikonfigurasi — % terkirim bulan ini" |
| O10 | Lihat sidebar siswa | Satu grup **Portal Saya** berisi Dashboard, Notifikasi & Pengumuman, Data Siswa, Keuangan, PPDB & Seleksi, Pengaturan Akun. Menu *Akademik* hanya muncul bila siswa itu sudah dinyatakan diterima. Tidak ada tombol CTA "Info SPMB" (ikon `?` di topbar tetap mengarah ke Info SPMB) dan kaki sidebar hanya berisi **Keluar** — tautan *Ganti Password* dibuang karena `app/staf/ganti-password.php` menolak peran siswa; ganti sandal ada di **Pengaturan Akun** |
| O11 | Login sebagai operator/bendahara/kepsek setelah perubahan O9–O10 | Sidebar & kaki mereka tidak berubah (CTA Pendaftaran Manual operator, *Ganti Password* tetap ada untuk peran sekolah) |

## P. Tabel Agenda pada Kartu Peserta (`app/siswa/ppdb.php?tab=jadwal`)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| P1 | Siswa yang sudah punya kartu peserta → tab **Jadwal Tes** | Desain kartu tidak berubah (logo + nama sekolah, No. Pendaftaran, Nama Peserta, kotak NOMOR PESERTA, catatan hadir 15 menit). Hanya blok detail `Kegiatan / Tanggal / Jam / Lokasi` yang **diganti tabel Agenda Tes** bergaris dengan kolom **Tanggal · Kegiatan · Waktu · Lokasi / Ruangan** — tanpa kolom No dan tanpa kolom foto |
| P2 | Bandingkan isi tabel dengan pesan WA "Jadwal Tes SPMB" yang diterima wali siswa | Jumlah baris dan isinya SAMA — keduanya dibaca dari `jadwal_terjadwal()` (hanya status Terjadwal). Kolom Kegiatan menampilkan nama + kode, Waktu menampilkan jam + "WIB · metode", Lokasi memakai `jadwal_lokasi_teks()` (Ruangan lebih dulu, lalu Lokasi, lalu bawaan per metode) |
| P3 | Perhatikan kolom Tanggal & Waktu pada layar sempit | Tanggal "18 Okt" + tahun di baris kedua, jam "08:00–11:00" + "WIB · Offline" di baris kedua — tabel `w-full border-collapse` tanpa `min-w-`, sehingga tidak ada gulir samping (`scrollWidth == clientWidth`) |
| P4 | Siswa yang kartunya tercatat pada jadwal yang statusnya belum Terjadwal | Tabel tetap terisi satu baris: kegiatan milik kartu itu sendiri (`$agendaBaris = $agendaTes ?: [$kartuUjian]`), jadi kartu tidak pernah kehilangan informasi intinya |
| P5 | Lebih dari satu baris agenda | Muncul keterangan "Nomor peserta di atas berlaku pada kegiatan …; seluruh kegiatan lain tetap wajib diikuti sesuai barisnya masing-masing" — karena satu pendaftar hanya memegang satu nomor peserta (`uq_peserta_pendaftar`) |
| P6 | Tekan **Cetak** pada kartu | Tabel agenda ikut tercetak: `cetakKartu()` menyalin `#kartu-peserta` ke `#print-area`, dan aturan baru `#print-area .overflow-x-auto { overflow: visible }` mencegah kolomnya terpotong di kertas |

## Q. Rincian Biaya SPMB & Promo (rombak 2026-11-01)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| Q1 | Operator → **Pengaturan Sistem** → kartu ke-4 | Judul kartu **Rincian Biaya SPMB** (bukan "Struktur Tarif & Biaya SPMB"); ada **daftar item biaya** (nama + nominal) dan label **Biaya Masuk** (bukan "Estimasi Biaya Pendaftaran") |
| Q2 | Lihat blok **Tagihan Setelah Lulus & Dapat Kelas** | Tiga baris: Biaya Masuk → − Biaya Formulir (sudah dibayar) → **= Sisa Biaya Masuk** dengan angka dari DB (mis. 1.250.000 − 200.000 = **1.050.000**) dan dua kartu Angsuran 1/2 otomatis 525.000 + tanggal jatuh tempo |
| Q3 | Ubah Biaya Formulir jadi 250.000, atau ubah salah satu nominal item rincian (jangan Simpan) | Rincian & angsuran ikut berubah seketika (mis. sisa 1.750.000, angsuran 875.000/875.000) — rumus JS sama dengan `angsuran_skema()` |
| Q4 | Buat Biaya Formulir ≥ Biaya Masuk | Sisa & kedua angsuran jadi **Rp 0** dan muncul catatan merah "Biaya Formulir sama atau lebih besar dari Biaya Masuk — tidak ada sisa yang ditagih." |
| Q5 | Isi Biaya Masuk 1.000.001 dengan Biaya Formulir 200.000 (jangan Simpan) | Sisa 800.001 → Angsuran 1 = **400.001** dan Angsuran 2 = **400.000**; pembulatan selisih satu rupiah selalu jatuh ke angsuran 1 (`round()` membulatkan ke atas) |
| Q6 | Muat landing publik → kartu "Rincian SOP & Biaya Pendaftaran" | Biaya Masuk penuh, lalu daftar **Terdiri dari** (setiap item rincian + nominal), kemudian rantai potongan: − Biaya Formulir, **= Sisa setelah lulus**, dan kalimat "…2 angsuran Rp 525.000 per tahap" |
| Q7 | Portal siswa (siswa LULUS yang hasil sudah diumumkan **atau** sudah punya penempatan kelas) → tab Keuangan | Panel **Rincian Biaya & Sisa Pembayaran**: daftar item, Total Biaya Masuk, − Sudah dibayar & diverifikasi, **= Sisa yang harus dibayar**, plus kalimat opsi lunas / 2 angsuran dan jatuh tempo angsuran 1. "Total Tagihan" = Biaya Formulir + sisa Biaya Masuk |
| Q8 | Dashboard & rekap cetak Bendahara | Baris "Biaya Pendaftaran" = biaya masuk penuh, ada baris **Sisa ditagih** = dasar angsuran; catatan kaki rekap berbunyi "… — sisa yang ditagih setelah lulus: Rp 1.050.000 (angsuran 2× Rp 525.000 per tahap)." |
| Q9 | Bayar lewat Midtrans (sandbox) untuk siswa lulus | Nominal yang ditagihkan = sisa angsuran berikutnya dari skema baru, bukan biaya masuk penuh (`app/api/snap-token.php` membaca `angsuran_skema()`) |
| Q10 | Kosongkan **Teks Promo (opsional)** → Simpan, lalu buka halaman pendaftaran | Tidak ada badge "Promo Tersedia" dan tidak ada blok "Program Promo" sama sekali (perilaku lama "Beasiswa" sudah digantikan; kolom DB tetap `teks_beasiswa`) |
| Q11 | Isi Teks Promo satu baris → Simpan | Badge hijau **Promo Tersedia** di sudut kartu + blok **Program Promo** berisi teks tersebut (baris baru tampil sebagai `<br>`) |
| Q12 | Isi Teks Promo lebih dari 300 karakter | Pesan galat: "Teks promo maksimal 300 karakter (saat ini N)." |

## R. Manajemen Kelas (`app/operator/kelas.php`, rombak 2026-11-01)

| # | Langkah | Diharapkan |
|---|---------|-----------|
| R1 | Lihat kartu **Tambah Kelas Baru** | Dua kolom per baris (Kode Kelas · Nama Kelas · Kuota · Wali Kelas) masing-masing dengan hint, lalu satu baris aksi: checkbox **Aktif** (label kini benar-benar mengikat ke checkbox) + tombol **Tambah Kelas** rata kanan. Tidak ada lagi grid 6 kolom yang membuat field penyot |
| R2 | Tambah kelas tanpa mencentang Aktif | Kelas tersimpan `is_aktif = 0` dan tidak menambah "Total Kuota Aktif" di header |
| R3 | Lihat **Daftar Rombel** | **Tidak ada tombol Simpan per baris** lagi; kolom Aksi hanya berisi ikon hapus. Satu tombol **Simpan Semua** di kanan atas kartu |
| R4 | Ubah kuota satu baris, lalu ubah wali kelas baris lain (tanpa menekan apa pun) | Chip kuning **"Belum disimpan"** muncul di samping tombol Simpan Semua |
| R5 | Tekan **Simpan Semua** | Satu POST (`aksi=simpan_massal`) mengirim seluruh baris; flash hijau "N kelas tersimpan." dan muat ulang menampilkan semua perubahan sekaligus. Chip hilang |
| R6 | Buat dua baris memakai kode yang sama (mis. baris 1 diisi kode milik baris 3) lalu Simpan Semua | Muncul daftar "Periksa isian berikut:" dengan pesan berawalan kode — `7C — Kode kelas "7C" sudah dipakai.` dan `7C — kode yang sama dipakai pada lebih dari satu baris.` — pesan yang sama tidak ditulis dua kali |
| R7 | Setelah galat pada R6, periksa database | **Tidak ada satu baris pun yang berubah** — simpan massal bersifat all-or-nothing, dan tabel menampilkan nilai lama dari DB |
| R8 | Tutup tab saat chip "Belum disimpan" masih tampil | Browser menanyakan konfirmasi keluar (`beforeunload`) |
| R9 | Muat halaman tanpa flash tertinggal | Toast tidak menampilkan pesan sukses lama — `kelas.php` mengambil flash sekali lewat `flash()` sehingga pesan tidak terputar ulang |

## S. Daftar Item Biaya & Input Hasil Seleksi (rombak 2026-11-02)

Sebelum uji: jalankan `mysql -u root < database/migrations/20261102_rincian_biaya.sql`
(kolom `rincian_biaya` dan `ambang_kelulusan`).

| # | Langkah | Diharapkan |
|---|---------|-----------|
| S1 | Pengaturan Sistem → blok **Rincian Item Biaya Masuk** | Baris berisi nama item + nominal + tombol hapus, tombol **Tambah Item**, dan baris **Total = Biaya Masuk** di bawahnya |
| S2 | Isi contoh sekolah: Formulir Pendaftaran 200.000 · Fortasi/MPLS 150.000 · SPP 350.000 · Infaq 3 Tahun 350.000 · Kontribusi IPM 3 Tahun 200.000 | Total otomatis **1.250.000**, kolom Biaya Masuk ikut terisi dan menjadi *read-only* (abu-abu), blok tagihan menampilkan sisa **1.050.000** = 2 × 525.000 |
| S3 | Tekan **Simpan** | `pengaturan_sekolah.rincian_biaya` berisi JSON `[{"l":"Formulir Pendaftaran","n":200000}, …]`, `estimasi_uang_masuk` = 1250000.00, `angsuran_*_nominal` = 525000.00. Muat ulang → kelima baris tampil kembali |
| S4 | Kosongkan seluruh baris rincian → Simpan | `rincian_biaya` kembali NULL, kolom Biaya Masuk **bisa diketik manual** dan hint berubah menjadi "Belum ada rincian — Biaya Masuk masih diketik manual." |
| S5 | Isi nama item tanpa nominal (atau sebaliknya) → Simpan | Galat "Nominal "…" harus lebih dari 0." / "Rincian biaya baris N belum ada nama itemnya." dan **tidak ada** perubahan tersimpan |
| S6 | Tambah lebih dari 15 item | Galat "Rincian biaya maksimal 15 item." |
| S7 | Operator → **Input Hasil Seleksi** | Tabel hanya berisi No. Pendaftaran · Nama · CBT · BTQ · Wawancara · Nilai Akhir · Keputusan. **Tidak ada kolom Jadwal Tes** dan **tidak ada tombol Hitung Ulang Otomatis** |
| S8 | Ketik CBT 80, BTQ 60, Wawancara 55 | Nilai Akhir seketika **65,0** dan Keputusan otomatis **Tidak Lulus** (ambang 70) |
| S9 | Ubah Ambang Kelulusan menjadi 65 (tanpa tombol apa pun) | Baris yang sudah bernilai dihitung ulang → Keputusan menjadi **Lulus**; baris yang masih kosong tetap "—" / Proses |
| S10 | Kosongkan kembali ketiga nilai satu baris | Nilai Akhir menjadi **—** dan Keputusan kembali ke **Proses** (baris seperti ini dilewati saat simpan) |
| S11 | Tekan **Simpan Hasil Seleksi** dengan ambang 70 → muat ulang halaman | Flash menyebut "…(ambang kelulusan 70)." dan kolom `ambang_kelulusan` = 70.00; nilai yang tersimpan di tabel `seleksi` tidak berubah |
| S12 | Bandingkan `seleksi.jadwal_id` sebelum & sesudah simpan nilai | Tetap sama — jadwal tidak dihapus oleh halaman ini meskipun kolomnya tidak ditampilkan |
| S13 | Siswa LULUS yang sudah punya penempatan kelas tetapi hasil **belum** diumumkan → portal siswa → Keuangan | Panel **Rincian Biaya & Sisa Pembayaran** sudah tampil beserta kelasnya (mis. "Anda diterima di kelas 7C"), sisa = total biaya masuk − yang sudah diverifikasi |

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
| X9 | Buka `/tools/*.php` via browser | Tool CLI yang ada → **403** (`tools/.htaccess`, Fase 13 butir 1); `set-password-web.php` → **404** selama penanda `storage/.izinkan-set-password` tidak ada; dengan penanda hanya form kode instalasi |
| X10 | Cari rahasia di paket rilis / repo | `.env` absen dari zip & repo; `hak akses.txt` tiada; password hanya hash bcrypt/`!LOCKED` |
| X11 | Edit `pay_token_hash` jadi duplikat | Ditolak DB (constraint UNIQUE) |
| X12 | Pembatasan teks penolakan verifikasi diklik korban | Mendarat di cek-status (pemulihan aman), bukan halaman bayar |
| X13 | Operator memaksa POST `umumkan_sekarang`/`jadwalkan` tanpa persetujuan (form dibuka lewat DevTools) | Ditolak server (`persetujuan_hasil_sah()` false) — `hasil_diumumkan_pada` tetap NULL |
| X14 | Admin memaksa POST `setujui`/`ttd` di halaman kepsek | Ditolak dua lapis: layar `require_role(['kepsek','admin'])` tapi tombol nonaktif + `persetujuan_simpan()` menolak id bukan akun kepsek aktif |
| X15 | Persetujuan lama dipakai menerbitkan daftar hasil yang sudah diubah | Sidik tidak cocok → catatan **Batal** → penerbitan terkunci lagi |
| X16 | POST `aksi` sembarang / tanpa `nyata=1` ke `penetapan.php` | "Aksi tidak dikenal." / "Centang pernyataan telaah…" — tidak ada kolom yang berubah |

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
