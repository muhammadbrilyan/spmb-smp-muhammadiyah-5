# Kebijakan Data & Retensi — SPMB SMP Muhammadiyah 5 Kota Tangerang

> **Status: DRAF untuk ditinjau sekolah / bagian hukum.** Dokumen ini bukan
> nasihat hukum. Paragraf yang ditandai `[TINJAU]` harus disahkan pihak
> sekolah sebelum dipublikasikan sebagai bagian dari Syarat & Ketentuan
> (Pasal 8 — Privasi & Retensi Data) atau halaman terpisah di website.

Versi dokumen: 1.0 (Fase 12) · Zona waktu operasional: Asia/Jakarta.

---

## 1. Identitas & Tujuan

SMP Muhammadiyah 5 Kota Tangerang ("Sekolah") memproses data pribadi calon
siswa dan orang tua/wali melalui Sistem Penerimaan Murid Baru (SPMB) —
aplikasi daring resmi sekolah — **hanya** untuk:

- seleksi pendaftaran murid baru;
- verifikasi berkas persyaratan dan pembayaran formulir;
- komunikasi hasil seleksi (website & WhatsApp);
- arsip administrasi akademik sekolah.

`[TINJAU]` Bentuk badan hukum penyelenggara & penanggung jawab pemrosesan
data (mis. Yayasan / Komite SPMB) perlu dilengkapi sekolah.

## 2. Data yang Dikumpulkan

| Kelompok | Contoh data | Sumber |
|----------|-------------|--------|
| Calon siswa | nama, NISN, jenis kelamin, tempat/tanggal lahir, alamat, asal sekolah, jalur | formulir daring |
| Orang tua/wali | nama, hubungan, nomor WhatsApp, pekerjaan | formulir daring |
| Berkas | Kartu Keluarga, akta lahir, surat keterangan sekolah, pas foto, bukti pembayaran | unggahan berkas |
| Transaksi | nominal, metode (transfer/QRIS/gateway), status verifikasi | sistem pembayaran |
| Teknis | alamat IP (untuk pembatasan percobaan login), waktu akses | log terbatas |

Data disimpan dalam basis data sekolah dan berkas pada penyimpanan server
(`storage/dokumen`) yang **diblokir dari akses publik** dan hanya dapat
diambil melalui halaman resmi dengan pemeriksaan hak akses.

## 3. Retensi & Penghapusan

Kebijakan retensi **dapat diaktifkan dan diatur** oleh operator sekolah
(melalui menu *Retensi Data*). Prinsipnya:

| Kategori | Tindakan setelah batas bulan tercapai | Catatan |
|----------|----------------------------------------|---------|
| Tidak lulus seleksi | Hapus **berkas pribadi fisik** (KK, akta, bukti bayar) + jejaknya di tabel dokumen | Baris pendaftar & hasil seleksi tetap diarsipkan ringkas |
| Pendaftaran ditolak/dibatalkan | Sama seperti di atas | |
| Lulus — arsip | Berkas dipindahkan dari penyimpanan aktif (arsip) | Data akademik siswa baru berlaku kebijakan arsip sekolah |

- Eksekusi selalu tercatat pada tabel `retensi_log` (waktu, nomor pendaftaran, alasan, jumlah berkas, operator).
- Retensi **tidak pernah** menghapus: nilai seleksi, penempatan kelas, data akademik, atau baris pendaftar.
- Eksekusi massal melalui CLI (`tools/retensi.php`) memerlukan retensi diaktifkan terlebih dahulu dan secara bawaan hanya bersifat simulasi (*dry-run*).
- **Arsip Pendaftaran (soft archive)**: operator/admin dapat menandai satu pendaftar sebagai
  terarsip lewat halaman *Data Pendaftar* (alasan wajib dicatat; maks 10 aksi/jam per operator).
  Pendaftar terarsip keluar dari seluruh antrean kerja, statistik, ekspor, penghitung publik,
  halaman cek-status, dan tautan bayar; akun portal siswanya dinonaktifkan sementara
  (`is_active = 0`) dan dipulihkan bersamaan dengan pengarsipannya.
- Aplikasi **tidak menyediakan hapus permanen baris pendaftar** — 7 relasi ber-`ON DELETE CASCADE`
  (orang tua, pembayaran, dokumen, koreksi, peserta ujian, seleksi, penempatan kelas) membuat
  hapus fisik menghilangkan jejak keuangan & dokumen. Yang dihapus hanya berkas fisik lewat retensi.

`[TINJAU]` Angka batas bulan (mis. 12/24 bulan) dan kategori mana yang
diaktifkan adalah kebijakan sekolah — isi pada menu *Retensi Data* setelah
disahkan.

## 4. Hak Orang Tua/Wali

`[TINJAU]` Sekolah menyatakan menghormati hak subjek data untuk: meminta
koreksi data, menanyakan data yang disimpan, dan meminta penghapusan berkas
pribadi di luar jadwal retensi (diajukan tertulis ke panitia SPMB). Batas
waktu pemrosesan permintaan `[TINJAU — mis. 14 hari kerja]`.

## 5. Pihak Ketiga & Transfer Data

Layanan pihak ketiga yang **opsional** hanya dipakai bila dikonfigurasi
sekolah:

| Layanan | Fungsi | Data yang mungkin terkirim |
|---------|--------|----------------------------|
| Fonnte (WhatsApp Gateway) | Notifikasi hasil & kredensial | nomor WA, nama, isi pesan terkait pendaftaran |
| Midtrans (Payment Gateway) | Pembayaran otomatis (opsional, nonaktif secara bawaan) | nominal transaksi, status pembayaran |

Sekolah **tidak menjual** data pribadi kepada pihak ketiga.

## 6. Keamanan

- Basis data diakses aplikasi melalui user MySQL khusus berhak terbatas.
- Kata sandi akun disimpan ter-hash (bcrypt/argon2); reset hanya melalui alat resmi panitia.
- Berkas pribadi disimpan di folder yang diblokir dari akses web langsung; unduhan melalui endpoint PHP dgn pemeriksaan sesi & kepemilikan.
- Batas percobaan login & aksi sensitif (rate limit); pembatasan alamat IP di belakang proxy resmi (Cloudflare) hanya dipercaya bila dikonfigurasi (`TRUSTED_PROXIES`).
- Cadangan database & berkas tersedia bagi panitia — **memuat data pribadi**, jangan dibagikan.

## 7. Perubahan Kebijakan

Setiap perubahan teks Syarat & Ketentuan (termasuk Pasal 8 Privasi) otomatis
menaikkan **versi S&K**. Pendaftar yang telah mendaftar tetap terikat versi
yang mereka setujui saat mendaftar (tersimpan pada catatan persetujuan).

---

### Checklist peninjauan sekolah/hukum

- [ ] Identitas penanggung jawab pemrosesan data (bagian 1) diisi & disahkan.
- [ ] Batas retensi per kategori (bagian 3) ditetapkan — lalu diaktifkan di menu *Retensi Data*.
- [ ] Mekanisme & batas waktu permintaan hak subjek data (bagian 4) disepakati.
- [ ] Penggunaan Fonnte/Midtrans (bagian 5) dikonfirmasi — aktifkan hanya yang disetujui.
- [ ] Teks Pasal 8 S&K di CMS diselaraskan dengan draf ini, lalu versi S&K diperbarui (otomatis).
