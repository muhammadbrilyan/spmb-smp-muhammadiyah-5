# Alur Pendaftaran SPMB — SMP Muhammadiyah 5 Kota Tangerang

Dokumen ini merangkum alur end-to-end SPMB (Fase 0–11), model status `pendaftar.tahap`, tabel/kolom yang ditambahkan per fase, dan titik integrasi eksternal.

## Alur Utama (Mermaid)

```mermaid
flowchart TD
    A[Registrasi Publik] --> B[MENUNGGU_PEMBAYARAN]
    B -->|Unggah bukti / Snap lunas| C[MENUNGGU_VERIFIKASI_BAYAR]
    C -->|Bendahara Setujui + Kirim Akun| D[BAYAR_TERVERIFIKASI]
    D -->|Akun aktif + kredensial WA| E[AKUN_TERKIRIM]
    E -->|Siswa lengkapi berkas| F[MELENGKAPI_BERKAS]
    F -->|Submit berkas wajib lengkap| G[BERKAS_DISUBMIT]
    G -->|Ada berkas ditolak| H[BERKAS_REVISI]
    H -->|Re-upload| G
    G -->|Semua berkas lolos| I[BERKAS_TERVERIFIKASI]
    I -->|Atur peserta otomatis| J[JADWAL_DITETAPKAN]
    J -->|Operator simpan nilai| K[NILAI_TERINPUT]
    K -->|Umumkan / jadwalkan hasil| L[DIUMUMKAN]
    L --> M[Penempatan Kelas + WA pengumuman]
```

## Model Status `pendaftar.tahap`

| # | Nilai | Arti | Pemicu perubahan |
|---|-------|------|------------------|
| 1 | `MENUNGGU_PEMBAYARAN` | Menunggu unggah bukti / bayar gateway | Registrasi selesai |
| 2 | `MENUNGGU_VERIFIKASI_BAYAR` | Bukti masuk, menunggu bendahara | Unggah bukti / callback gateway |
| 3 | `BAYAR_TERVERIFIKASI` | Pembayaran disetujui | Verifikasi bendahara (F4) |
| 4 | `AKUN_TERKIRIM` | Akun siswa aktif, kredensial terkirim | Verifikasi & kirim akun 1-klik (F4) |
| 5 | `MELENGKAPI_BERKAS` | Siswa mengunggah berkas persyaratan | Login portal siswa |
| 6 | `BERKAS_DISUBMIT` | Berkas disubmit, menunggu panitia | Submit berkas wajib lengkap (F5) |
| 7 | `BERKAS_REVISI` | Ada berkas ditolak, hanya berkas itu dibuka | Keputusan tolak panitia (F5) |
| 8 | `BERKAS_TERVERIFIKASI` | Semua berkas lolos | Setujui semua berkas (F5) |
| 9 | `JADWAL_DITETAPKAN` | Masuk peserta ujian + nomor peserta | Atur peserta otomatis/manual (F6) |
| 10 | `NILAI_TERINPUT` | Nilai CBT/BTQ/Wawancara tersimpan | Simpan tabel input manual (F7) |
| 11 | `DIUMUMKAN` | Hasil resmi diumumkan | Umumkan sekarang / jadwal lewat (F8) |

## Tabel & Kolom Baru per Fase

### Fase 0 — Prasyarat
- `pendaftar.tahap` ENUM (11 nilai, lihat tabel di atas), `pendaftar.pay_token`
- `login_attempts` (rate-limit login 5 gagal / 15 menit / IP)

### Fase 1 — S&K & Registrasi
- `pengaturan_sekolah.syarat_ketentuan` LONGTEXT, `syarat_versi` INT
- `pendaftar.syarat_versi`, `pendaftar.sak_diterima_pada`

### Fase 2 — Pembayaran
- `pengaturan_sekolah.bayar_gateway_aktif`, `bayar_transfer_aktif`, `bayar_qris_aktif`, `qris_image_url`, `qris_merchant`, `kirim_akun_otomatis`
- `pembayaran` (jenis, nominal, status, bukti, pay_token)

### Fase 3 — Notifikasi Operator
- `notifikasi` (tipe, pendaftar_id, pesan, is_read)
- `wa_log` (tujuan, tipe, status, respons)
- `pengaturan_sekolah.notif_wa_bendahara`, `notif_wa_aktif`, `notif_email` (titik sambungan)
- `app/api/notif-count.php` (polling sidebar 60 dtk)
- Rute tujuan `notif_operator()` (daftar pemicu di `notif_pemicu_wa()`):
  **uang masuk** — `BUKTI_BAYAR_MASUK`, `PEMBAYARAN_GATEWAY_MASUK`,
  `PEMBAYARAN_GATEWAY_LUNAS` — dan **kabar operasional panitia** sejak 2026-11-06 —
  `PENDAFTAR_BARU` (dipanggil `registrasi_baru()` di jalur baris baru),
  `VERIFIKASI_DATA`, `BERKAS_DISUBMIT` — semuanya ke `notif_nomor_bendahara()`,
  fallback `kontak_wa_panitia` bila kosong; dinormalisasi `wa_normalize()` lalu
  dideduplikasi. Kabar operasional dibatasi **3 pesan/jam per pendaftar**
  (`rate_limit_cek('notif_wa_operasional', 3, 60, tipe:idPendaftar)`) karena tombolnya
  bisa diklik berulang, sedangkan uang masuk tidak dibatasi; baris `notifikasi`
  tetap tercatat untuk setiap kejadian. Kolom `notif_wa_operator` tetap pensiun
  (satu daftar tujuan dipakai bersama panitia & bendahara — akun Fonnte gratis
  hanya mendaftarkan satu nomor, dan nomor itu nomor operator)
- Migrasi: `database/migrations/20261101_notif_wa_bendahara.sql`
- Lonceng **siswa** (`notifikasi_siswa`, per penerima) punya garis awal
  `users.notif_mulai_at`: peristiwa panitia yang tercatat sebelum akun pernah
  dibuka (mis. pembayaran ditolak saat akun baru dibuat) tidak ditampilkan di
  portal — dilihat dari kunjungan pertama. Barisnya tetap tersimpan; hanya
  `notif_siswa_daftar()` / `notif_siswa_belum_dibaca()` yang menyaring.
  Migrasi: `database/migrations/20261106_notifikasi_baseline.sql`

### Fase 4 — Verifikasi Bayar + Kirim Akun
- `pendaftar.tahap` nilai `BAYAR_TERVERIFIKASI` & `AKUN_TERKIRIM`
- Idempoten: `verifikasi_kirim_akun()` — klik ulang tidak dobel
- Pemicu: `BUKTI_BAYAR_MASUK`, `PEMBAYARAN_GATEWAY_LUNAS`
- Halaman: `app/bendahara/pembayaran.php` (antrean setujui/tolak, saringan `?s=`)
  dan `app/bendahara/index.php` (dashboard angka) — dahulu keduanya disatukan di `index.php`.
  Sejak 2026-11-06 ada `app/bendahara/riwayat.php` (menu **Riwayat Pembayaran**, baca saja):
  rincian tagihan + seluruh transaksi satu calon, termasuk siapa yang memverifikasi. Tabel
  tagihannya memakai `daftar_tagihan_baris()` — fungsi yang sama dengan portal siswa — sehingga
  wali dan bendahara tidak mungkin membaca angka berbeda; bedanya, bendahara tidak terkena
  gerbang "terkunci sampai hasil dibuka" karena setoran harus tetap bisa dicocokkan

### Fase 5 — Berkas Dinamis
- `jenis_dokumen` (nama, kode, wajib, format, maks_kb, is_aktif; seed 6 jenis)
- `dokumen` kolom: `catatan_verifikasi`, `verified_by`, `verified_at`; `jenis` VARCHAR(30)
- `dokumen_siswa()`, `dokumen_upload()`, `dokumen_submit()`, `berkas_keputusan()`, `berkas_evaluasi_tahap()`
- Halaman: `app/operator/verifikasi.php` tab Berkas (dahulu `berkas.php`), `app/operator/jenis-dokumen.php`

### Fase 6 — Jadwal Ujian & Peserta
- `jadwal_tes.ruangan` VARCHAR(100), `kapasitas` INT (0 = tanpa batas)
- `peserta_ujian` (pendaftar_id + jadwal_id **UNIQUE bersama** — satu calon boleh
  ikut beberapa kegiatan, nomor_peserta UNIQUE, ruangan). Indeks `uq_peserta_pendaftar`
  (satu kartu seumur hidup) dilepas oleh `database/migrations/20261106_peserta_ujian_multi_jadwal.sql`.
- `peserta_atur_otomatis()` (transaksi DB, urut no_pendaftaran, penuhi kapasitas) —
  yang dilewati hanya calon yang **sudah ada di kartu ini**, jadi calon yang absen di
  satu kegiatan bisa dijaring ulang pada jadwal susulan. Syarat tahap terpusat di
  `peserta_tahap_siap_jadwal()`; `tahap` hanya naik dari `BERKAS_TERVERIFIKASI`
  (`AND tahap = 'BERKAS_TERVERIFIKASI'`) agar calon bernilai tidak dipundahkan.
- `jadwal_kirim_ke_wali()` — **satu pengirim** untuk tombol *Kirim WA Jadwal* per kartu
  dan *Umumkan Semua Jadwal*: satu pesan WA + satu `notifikasi_siswa` (tipe
  `JADWAL_UJIAN`, `ref_id` = sidik jari kegiatan) per wali, `wa_log` tercatat, dan
  penerima yang `wa_status = TERKIRIM` untuk sidik jari itu dilewati.
- `peserta_kartu_siswa()` — Kartu Peserta + CSS print di portal siswa; pilihannya
  **kegiatan terdekat yang belum lewat** (bila semua sudah lewat, yang paling akhir),
  bukan lagi satu-satunya baris yang ada
- Blok detail pada kartu peserta kini berupa **tabel Agenda Tes** yang berisi
  **seluruh kartu kegiatan berstatus Terjadwal** (`jadwal_terjadwal()`) — sama
  seperti isi WA *Umumkan Semua Jadwal* — jadi jadwal baru langsung terlihat di
  kartu tanpa menunggu calon ditempatkan pada tiap kartu; kartu Draft tidak
  pernah tampil. Kolom Tanggal · Kegiatan · Waktu · Lokasi/Ruangan, ikut
  tercetak oleh `cetakKartu()`. Bila belum ada satu pun kartu Terjadwal, Agenda
  jatuh ke kartu ujian calon itu saja. (Sejak 2026-11-06; sebelumnya Agenda
  disaring per penempatan calon lewat `jadwal_agenda_siswa()` — fungsi itu sudah
  dihapus karena membuat kegiatan yang sudah diumumkan panitia tidak muncul.)
- Halaman: `app/operator/jadwal.php` — sejak 2026-11-02 jadwal dan peserta berada
  di satu halaman/menu **Jadwal Ujian**; bagian peserta ada di bawah daftar jadwal
  (`#peserta`). `app/operator/peserta-ujian.php` hanya pengalih 302

### Fase 7 — Seleksi
- `pendaftar_untuk_nilai()` difilter `tahap IN (JADWAL_DITETAPKAN, NILAI_TERINPUT)`
- `seleksi_label_kolom()` + `seleksi_singkat_nama()` (2026-11-05) — judul 3 kolom
  nilai diambil dari `jadwal_tes.nama_kegiatan` (3 pertama, urut tanggal & jam);
  slot nilai tetap `nilai_cbt` / `nilai_btq` / `nilai_wawancara`
- Pencarian no. pendaftaran / nama di sisi klien (hanya menyembunyikan baris;
  input tetap berada di dalam form) — daftar itu sendiri sudah `ORDER BY
  nama_siswa, no_pendaftaran` sejak 2026-11-05, jadi chip filter abjad dicabut
- `seleksi_simpan()` → set `tahap=NILAI_TERINPUT` (struktur nilai TIDAK berubah)
- ~~`seleksi_impor_nilai()` — CSV upsert~~ **dihapus 2026-11-05** (impor nilai CSV tidak dipakai; input manual saja)
- `lulus_belum_ditempatkan_jumlah()` — peringatan sebelum pengumuman

### Fase 8 — Pengumuman
- `pengaturan_sekolah.hasil_diumumkan_pada` DATETIME NULL
- `pengumuman_status()`, `pengumuman_umumkan_sekarang()`, `pengumuman_jadwalkan()`, `pengumuman_batalkan_jadwal()`
- `pengumuman_wa_batch()` — 10 nomor per request (AJAX), `wa_log` tipe `PENGUMUMAN`
- Peringatan: LULUS tanpa kelas → wajib konfirmasi eksplisit
- Cek-status publik: **satu kolom isian** — nomor WhatsApp penuh langsung menampilkan status,
  nomor pendaftaran tetap **wajib 4 digit WA** (pencarian nama dihapus; nomor pendaftaran berjalan
  sehingga tetap dijaga). Revisi 2026-11-06.
- Hasil seleksi & penempatan disembunyikan publik/portal sampai diumumkan
- Revisi 2026-11-02: persetujuan kepala sekolah (`app/kepsek/penetapan.php`) menjadi
  **catatan resmi**, bukan gerbang — operator menerbitkan langsung; gerbang yang tersisa
  hanya CSRF + konfirmasi LULUS tanpa kelas. WA pengumuman kini memuat tautan hasil
  `app/siswa/hasil.php` (absolut via `url_abs()`)
- Halaman: `app/operator/pengumuman.php`, API `app/api/pengumuman-wa.php`

### Fase 9 — Panduan & Dokumentasi
- `app/operator/panduan.php` — SOP per peran (operator, bendahara, kepsek)
- `.env.example` — template kredensial (DB, Fonnte, Midtrans)
- `docs/ALUR-PENDAFTARAN.md` — dokumen ini

### Fase 10 — Gateway Opsional + Hardening
- **Bagian A (opsional)**: Midtrans `DEFAULT 0` + toggle `bayar_gateway_aktif` di Pengaturan
  (paksa-0 server-side saat key belum diatur). Tautan/gateway memakai `gateway_tersedia()`.
- **Bagian B (akun)**: `tools/set-password.php` (CLI-only) menggantikan `hak akses.txt`
  yang **dihapus dari repository**; kolom `users.wajib_ganti_password` + gate
  `require_role()` (staf dipaksa ke Ganti Password). Upload bukti bayar: wajib CSRF +
  `pay_token` 64-hex + cek metode/tahap + hapus bukti lama + rate tiap percobaan.
- **Bagian C**: `rate_limit_cek/catat(..., $kunci)` — rate lintas IP per no_pendaftaran di
  `app/spmb/cek-status.php` (dan untuk tombol tautan bayar per pendaftar); pesan "tidak ditemukan"
  & "4 digit salah" **seragam** (anti-enumerasi); `pengumuman_status()` **read-only**
  (finalisasi tahap di jalur operator).
- **Bagian D**: `url_abs()` + env `APP_URL` untuk tautan WhatsApp; **kepsek tidak lagi**
  boleh mengunduh berkas pribadi di `app/api/dokumen.php`.

### Fase 11 — Tutup Sisa Fase 10 + Kesiapan Produksi

- **Bagian A (WA, akun, token)**:
  - `pendaftar.pay_token_hash CHAR(64) UNIQUE` — kolom `pay_token` polos tidak lagi
    disimpan/disimpan ulang di DB (hanya SHA-256); registrasi idempoten **merotasi**
    pay_token sehingga tautan bayar lama mati. Helper tahap:
    `tahap_urutan()/tahap_index()/tahap_minimal()` (`includes/functions.php`).
  - `app/api/snap-token.php`: publik hanya produk **FORMULIR**; uang masuk hanya
    dari portal siswa dengan tahap ≥ `BERKAS_TERVERIFIKASI` (dan belum VERIFIED/LUNAS).
  - `app/staf/ganti-password.php`: min 10 karakter, tolak = username / = lama,
    rate 5 percobaan / 15 menit (per IP + per user), `session_regenerate_id(true)` sukses.
  - `wa_pesan_instruksi()` (`includes/fonnte.php`): instruksi WA hanya memuat metode
    yang **aktif** di pengaturan, langkah akhir diseragamkan ("Setelah pembayaran
    terverifikasi, akun dan password dikirim lewat WhatsApp.") — kartu sukses registrasi
    memakai sumber yang sama.
  - Rate registrasi WA `registrasi_wa` 3 kali / nomor / 24 jam.
  - Cek-status: kunci rate per-nomor hanya untuk format `REG-YYYY-NNNN` valid,
    hanya dicatat saat **gagal**; pembersih `rate_limit_bersihkan()` (baris >2 hari,
    ±1% request acak, LIMIT 1000) dipanggil dari `includes/config.php`.
  - WA penolakan verifikasi dokumen menautkan ke cek-status (bukan halaman bayar).
  - Seed utama: akun staf `'!LOCKED'` (reset via CLI).
- **Bagian B (instrumen rilis)**:
  - `tools/buat-paket.php` — zip rilis CLI-only, mengecualikan `.env`, `.git`, `dist`,
    isi `storage/dokumen` (non-demo), `storage/backup`, dan file rahasia; verifikasi
    pasca-build otomatis.
  - `tools/cek-proyek.php` — 8 pemeriksaan (versi PHP, ekstensi, `.env`, koneksi+skema,
    izin storage, php.ini, akun `!LOCKED`/hash lemah); exit 1 bila GAGAL.
  - `tools/backup-db.php` (mysqldump + gzip, password lewat env `MYSQL_PWD`,
    rotasi 14 hari) & `tools/backup-berkas.php` (zip `storage/dokumen`, rotasi 14 hari).
  - `docs/DEPLOY.md`: cadangan terjadwal, rilis/pembaruan, pemulihan bencana.
  - `.gitattributes` (LF) + `.editorconfig` (UTF-8 tanpa BOM, indent 4).
  - `database/seed_demo.sql` — 9 pendaftar demo (11/11 tahap tercakup), users siswa
    demo `'!LOCKED'`, orang tua, pembayaran, 26 dokumen, 3 peserta ujian, 2 seleksi;
    idempoten; pasangan `seed_demo_hapus.sql`.
- **Bagian C (kesiapan)**: `app/operator/kesiapan.php` — checklist kesiapan 18 item
  (read-only): APP_URL, placeholder rekening/WA panitia, jadwal+S&K, akun `!LOCKED`,
  hash lemah, skema `pay_token_hash`, metode bayar, kunci Midtrans, token Fonnte,
  izin storage, umur cadangan DB, notif WA operator; skor % + kartu antrean operasional
  per tahap; menu sidebar **Kesiapan Rilis** (operator/admin).

## Alur Lengkap (Fase per Fase)

### Fase 0 — Prasyarat
Kolom `tahap`, `pay_token`, tabel `login_attempts`. Rate-limit login.

### Fase 1 — S&K & Registrasi
Registrasi publik → setujui S&K (versi tercatat) → bayar formulir → `MENUNGGU_PEMBAYARAN`.

### Fase 2 — Pembayaran
Gateway Midtrans Snap (aktif default) / transfer bank manual / QRIS manual. Callback → `MENUNGGU_VERIFIKASI_BAYAR`.

### Fase 3 — Notifikasi Operator
`notif_operator()` → `notifikasi` + `wa_log`. Sidebar badge + polling 60 dtk. Halaman Notifikasi. Toggle `notif_wa_aktif` menyalakan seluruh kirim WA; nomor **Bendahara** (`notif_wa_bendahara`, fallback `kontak_wa_panitia`) ikut menerima salinan khusus bukti bayar masuk agar bendahara tahu ada transfer walau daftar nomor operator kosong. Nomor yang sama di kedua daftar hanya dikirimi sekali.

### Fase 4 — Verifikasi Bayar + Kirim Akun
Bendahara: Setujui (`VERIFIED+BAYAR_TERVERIFIKASI`), Tolak (wajib alasan, kembali MENUNGGU_PEMBAYARAN, WA alasan). Tombol "Verifikasi & Kirim Akun" 1 klik idempoten. Gateway auto-kirim (default nonaktif).

### Fase 5 — Berkas Dinamis
`jenis_dokumen` CRUD. Siswa unggah → validasi format/size/MIME. Submit hanya bila wajib lengkap → `BERKAS_DISUBMIT` + kunci + notif. Verifikasi per berkas → semua lolos `BERKAS_TERVERIFIKASI`, ada ditolak `BERKAS_REVISI` + WA + hanya berkas ditolak dibuka.

### Fase 6 — Jadwal Ujian OFFLINE
`jadwal_tes` + ruangan/kapasitas/jam. `peserta_ujian` (nomor_peserta unik; `pendaftar_id` boleh muncul di beberapa `jadwal_id` sejak 2026-11-06). "Atur Peserta Otomatis" urut no_pendaftaran penuhi kapasitas (transaksi DB), menyaring per kartu. `tahap=JADWAL_DITETAPKAN` + WA. Kartu Peserta (CSS print).

### Fase 7 — Seleksi
Hanya tampilkan `JADWAL_DITETAPKAN`/`NILAI_TERINPUT`. Struktur nilai TIDAK berubah. Setelah nilai → `NILAI_TERINPUT`. Nilai hanya diisi manual lewat tabel (impor CSV dihapus 2026-11-05); Nilai Akhir & keputusan dihitung otomatis dari `ambang_kelulusan`. Judul kolom nilai mengikuti nama kegiatan jadwal tes (`seleksi_label_kolom()`, disingkat otomatis) sejak 2026-11-05; barisnya urut abjad nama dan ada pencarian no. pendaftaran/nama di sisi klien. Hasil LULUS/TIDAK_LULUS tetap operator. Peringatan lulus tanpa kelas.

### Fase 8 — Pengumuman
`hasil_diumumkan_pada` (DATETIME NULL). Sembunyikan hasil sampai waktu. Halaman Pengumuman + "Umumkan Sekarang"/"Jadwalkan" (aktif bila tak ada lulus tanpa kelas / konfirmasi eksplisit). `tahap=DIUMUMKAN` + WA bertahap 10/request (AJAX progres) + `wa_log` + "Kirim ulang yang gagal". Cek-status publik: satu kolom isian — WhatsApp penuh langsung tampil, nomor pendaftaran tetap + 4 digit WA.

### Fase 9 — Panduan & Dokumentasi
SOP per peran. `.env.example`. Dokumen ini. Uji e2e manual lengkap.

### Fase 10 — Gateway Opsional + Hardening
Pembayaran online bersifat **opsional** (toggle + paksa-0 server-side). Reset password
via `php tools/set-password.php <username>` (CLI) → paksa ganti password. Unggah bukti
bayar publik: CSRF + `pay_token` + metode/tahap aktif. Cek-status: pesan seragam +
rate per no_pendaftaran. Tautan WA absolut (`url_abs`/`APP_URL`). Kepsek keluar dari
gate unduh berkas pribadi.

### Fase 11 — Tutup Sisa Fase 10 + Kesiapan Produksi
Token bayar disimpan sebagai **hash UNIQUE** (`pay_token_hash`) — registrasi idempoten
merotasi token sehingga tautan lama mati; token polos hanya tampil di 3 titik terbatas
(sukses registrasi, pesan WA, hasil pemulihan cek-status). Snap-token uang masuk hanya
lewat portal siswa ≥ BERKAS_TERVERIFIKASI (publik hanya FORMULIR). Ganti password
diperketat (min 10, tolak=username/lama, rate 5/15 menit IP+user, regenerasi sesi).
Instruksi WA hanya memuat metode aktif + kalimat penutup seragam; registrasi WA
dibatasi 3/nomor/24 jam. Cek-status: kunci per-nomor hanya utk format valid & hanya
saat gagal + pembersih rate_limit otomatis. Tools rilis CLI-only: `buat-paket`,
`cek-proyek` (8 pemeriksaan), `backup-db`, `backup-berkas` (rotasi 14 hari);
paket rilis bebas `.env`/rahasia. Seed demo lengkap 11/11 tahap + `seed_demo_hapus.sql`.
Halaman **Kesiapan Rilis** (`app/operator/kesiapan.php`): checklist kesiapan, skor %,
kartu antrean operasional — read-only.

## Integritas & Keamanan

- **CSRF**: semua form POST dilindungi `csrf_cek()` / `csrf_field()`.
- **Prepared statement**: semua query via PDO prepared statement.
- **Escape output**: semua output via `e()`.
- **Rate limit**: login (5/15mnt/IP), cek-status (10/15mnt/IP **+** 5/15mnt/no_pendaftaran),
  tombol tautan bayar (8/jam/pendaftar), unggah bukti (10/menit/IP, tiap percobaan dicatat).
- **Gate dokumen**: akses file berkas via `app/api/dokumen.php` — login + kepemilikan;
  **hanya operator/admin/bendahara** (kepsek tidak lagi ikut, Fase 10).
- **Akun**: `hak akses.txt` dihapus dari disk; reset password via CLI `tools/set-password.php`
  (min 10 karakter, set `wajib_ganti_password=1`); staf wajib ganti password sebelum halaman lain.
- **Unggah bukti bayar**: CSRF wajib, `pay_token` regex `^[a-f0-9]{64}$`, metode/tahap dicek server-side,
  file lama dihapus hanya bila path di `storage/dokumen/` & nama sesuai pola.
- **Pengumuman**: `pengumuman_status()` hanya membaca (finalisasi tahap dijalankan eksplisit oleh operator).
- **Pesan anti-enumerasi**: cek-status memakai satu pesan sama untuk "tidak ditemukan" & "4 digit salah";
  kunci rate per-nomor hanya dicatat untuk format `REG-YYYY-NNNN` valid dan hanya saat percobaan **gagal**
  (pencarian sukses tidak menambah hitungan). Nomor WhatsApp penuh tidak punya pola yang bisa ditebak,
  jadi jalur itu boleh langsung menampilkan status; satu nomor yang dipakai beberapa pendaftaran
  menampilkan daftar pilihan, dan id kandidat disimpan di sesi agar tidak bisa dipilih dari luar.
- **Sesi cek-status**: `$_SESSION['cek_status']` menyimpan id pendaftar yang **sudah terbukti**
  (`verif`) dan id kandidat (`kandidat`), umur 30 menit. Tombol "Buka Halaman Pembayaran" hanya
  melayani id di `verif`, lalu `bayar_tautan_baru()` merotasi `pay_token_hash` dan pengunjung
  di-redirect ke `app/spmb/bayar.php?t=…` — tanpa isian tambahan.
- **Idempotensi**: kirim akun (klik ulang tidak dobel), pengumuman (finalisasi tahap idempoten),
  registrasi ulang (reuse reg_token **+ rotasi pay_token** — tautan bayar lama mati).
- **Transaksi DB**: penempatan otomatis, atur peserta otomatis, unggah bukti bayar (gagal → tanpa perubahan).
- **Token bayar**: hanya `pay_token_hash` (SHA-256, UNIQUE) tersimpan di DB; `pay_token` polos selalu NULL.
  Titik tampil token polos dibatasi 3: halaman sukses registrasi, pesan WA registrasi, hasil pemulihan cek-status.
- **Rate limit Fase 11**: ganti-password 5/15 menit (per IP + per user), registrasi-WA 3/nomor/24 jam;
  `rate_limit_bersihkan()` menghapus baris >2 hari (±1% request acak, maks 1000 baris).
- **Gate API**: `app/api/snap-token.php` — publik hanya produk FORMULIR; uang masuk hanya dari portal
  siswa tahap ≥ BERKAS_TERVERIFIKASI dan pembayaran belum VERIFIED/LUNAS.
- **Alat rilis**: `tools/buat-paket.php`, `tools/cek-proyek.php`, `tools/backup-db.php`,
  `tools/backup-berkas.php` — semuanya **CLI-only** (akses web 404); zip rilis diverifikasi bebas
  `.env`/rahasia; cadangan DB & berkas otomatis rotasi 14 hari.
- **Kesiapan produksi**: `app/operator/kesiapan.php` (operator/admin) — checklist read-only 18 item
  + skor + antrean operasional; pemeriksaan teknis lanjutan via `php tools/cek-proyek.php`.
- **Arsip pendaftaran (soft archive)**: operator/admin menandai pendaftar selesai/batal lewat
  `pendaftar_arsip()` (`arsip_at`/`arsip_alasan`/`arsip_oleh`) — **tidak ada hapus permanen** karena
  7 FK cascade ke pembayaran, dokumen, koreksi, peserta ujian, seleksi & penempatan kelas.
  Akun siswa ikut `is_active=0` sementara dan dipulihkan oleh `pendaftar_pulih()`. Semua antrean kerja,
  statistik, export CSV, counter publik, cek-status, dan tautan bayar menyaring `arsip_at IS NULL`;
  data lama tetap terbaca lewat filter **Tampilkan = Terarsip**. Rate limit 10/jam per operator.

## Kredensial Default (GANTI SEBELUM GO-LIVE!)

Seed SQL utama (Fase 11): akun **staf terkunci** (`'!LOCKED'`) — tidak bisa
login sampai diatur via CLI. Akun siswa demo hanya di `database/seed_demo.sql`
(UAT/opsional, juga terkunci).

| Role | Username | Password seed | Set password via |
|------|----------|---------------|------------------|
| Operator | `operator` | `!LOCKED` (tidak bisa login) | `php tools/set-password.php operator` |
| Bendahara | `bendahara` | `!LOCKED` (tidak bisa login) | `php tools/set-password.php bendahara` |
| Kepsek | `kepsek` | `!LOCKED` (tidak bisa login) | `php tools/set-password.php kepsek` |
| Siswa (demo, seed_demo.sql) | `siswa` | `!LOCKED` (tidak bisa login) | `php tools/set-password.php siswa` |

Setelah reset, akun staf akan **dipaksa Ganti Password** pada login berikutnya.
Detail deploy: `docs/DEPLOY.md`.

## Environment Variables

Lihat `.env.example`. Variabel: `DB_*`, `FONNTE_TOKEN`, `MIDTRANS_SERVER_KEY`, `MIDTRANS_CLIENT_KEY`,
`MIDTRANS_SANDBOX`, `APP_URL` (URL publik untuk tautan WhatsApp; kosongkan di localhost).

## Migration Files

| Fase | File |
|------|------|
| 0 | `database/migrations/20261012_fase0_prasyarat.sql` |
| 1 | `database/migrations/20261012_fase1_sak_registrasi.sql` |
| 2 | `database/migrations/20261012_fase2_pembayaran.sql` |
| 3 | `database/migrations/20261012_fase3_notifikasi.sql` |
| 4 | `database/migrations/20261012_fase4_verifikasi_akun.sql` |
| 5 | `database/migrations/20261012_fase5_berkas_dinamis.sql` |
| 6 | `database/migrations/20261013_fase6_peserta_ujian.sql` |
| 8 | `database/migrations/20261013_fase8_pengumuman.sql` |
| 10 | `database/migrations/20261015_fase10_gateway_opsional.sql` |
| 10 | `database/migrations/20261015_fase10_keamanan.sql` |
| 11 | `database/migrations/20261016_fase11_pay_token_hash.sql` |
| 12 | `database/migrations/20261017_fase12_retensi.sql` |
| 13 | `database/migrations/20261026_fase13_koreksi_data.sql` (tabel `pengajuan_koreksi` — UI-nya sudah pensiun: pengajuan siswa dihapus 2026-11-02, tab operator 2026-11-03; tabel dibiarkan sebagai arsip) |
| — | `database/migrations/20261028_arsip_pendaftar.sql` (kolom `arsip_at`/`arsip_alasan`/`arsip_oleh` — pengarsipan soft-delete pendaftar) |
| — | `database/migrations/20261028_beasiswa_cms.sql` · `20261030_nonaktifkan_jenis_dokumen.sql` · `20261030_tahap2_notifikasi_siswa_pengumuman_umum.sql` |
| — | `database/migrations/20261031_pengingat_angsuran.sql` · `20261031_persetujuan_hasil.sql` · `20261031_tahap3_angsuran.sql` |
| — | `database/migrations/20261101_notif_wa_bendahara.sql` (kolom `notif_wa_bendahara` — tanpanya seluruh simpan Pengaturan Sistem batal) |
| — | `database/migrations/20261102_rincian_biaya.sql` (kolom `rincian_biaya` JSON + `ambang_kelulusan`) |
| — | `database/migrations/20261102_hasil_dibuka.sql` (kolom `pendaftar.hasil_dibuka_at` — gerbang amplop Hasil Seleksi di portal siswa; lihat `docs/UJI-MANUAL.md` bagian T) |
| — | `database/migrations/20261102_sosmed_sekolah.sql` (kolom `sosmed_instagram`/`sosmed_tiktok`/`sosmed_youtube`/`website_sekolah` — input link di Pengaturan Sistem + ikon footer publik) |
| — | `database/migrations/20261103_faq_cms.sql` (kolom `faq_list` JSON — pertanyaan section Pusat Bantuan di halaman depan kini disunting dari Pengaturan Sistem, bukan dari `includes/dummy.php`) |
| — | `database/migrations/20261106_notifikasi_baseline.sql` (kolom `users.notif_mulai_at` — garis awal lonceng portal siswa; peristiwa sebelum masuk pertama tidak ditampilkan, lihat `docs/UJI-MANUAL.md` W19–W23) |
| — | `database/migrations/20261106_peserta_ujian_multi_jadwal.sql` (indeks `uq_peserta_jadwal (pendaftar_id, jadwal_id)` menggantikan `uq_peserta_pendaftar` — satu calon boleh ikut beberapa kegiatan; **wajib dijalankan di hosting** sebelum menempatkan calon pada kartu kedua, lihat `docs/UJI-MANUAL.md` O12) |
| — | `database/migrations/20261106_wa_teks_cms.sql` (kolom `pengaturan_sekolah.wa_teks` JSON — tujuh kalimat WA ke wali siswa bisa disunting dari Pengaturan Sistem; **wajib dijalankan di hosting** bersama kode baru, kalau tidak seluruh formulir Pengaturan Sistem ditolak MySQL (`1054 Unknown column 'wa_teks'`), lihat `docs/UJI-MANUAL.md` W29–W34) |

Seed lengkap: `database/db_spmb_mualimku.sql` (gabungan semua fase).
Dokumentasi uji: `docs/UJI-MANUAL.md`.

---

*Dokumen ini diperbarui saat setiap fase diselesaikan (terakhir: Fase 11 — Tutup Sisa Fase 10 + Kesiapan Produksi).*
