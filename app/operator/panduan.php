<?php
/* ==================================================================
 * PANDUAN & SOP — halaman panduan operasional untuk staff.
 *
 * YANG SERING DIUBAH:
 *   - $panduan → array daftar panduan. Tambah/hapus item untuk ubah konten.
 *     Format item: icon (nama ikon), judul, role (siapa yang lihat), langkah (array teks)
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin', 'bendahara', 'kepsek']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title = 'Panduan & SOP';
$desc  = 'Panduan operasional SPMB untuk operator, bendahara, dan kepala sekolah.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'panduan';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik', 'SPMB', 'Panduan & SOP'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';

/* Daftar panduan per role. Tambah/hapus item di array $panduan untuk ubah konten panduan. */
$panduan = [
    [
        'icon' => 'assignment',
        'judul' => 'Manajemen Kelas & Kuota',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Buka menu <strong>Manajemen Kelas</strong>.',
            'Klik <em>Tambah Kelas</em>, isi kode (huruf/angka, mis. 7A), nama kelas, wali kelas, dan kuota.',
            'Kode kelas harus unik — sistem menolak duplikat.',
            'Ubah kuota langsung dari kolom <em>Kuota</em> pada tabel, lalu klik Simpan.',
            'Kelas yang sudah dipakai pendaftar tidak bisa dihapus — kosongkan dulu atau abaikan.',
        ],
    ],
    [
        'icon' => 'how_to_reg',
        'judul' => 'Pendaftaran & Verifikasi',
        'role'  => ['operator', 'admin'],
        'langkah' => [
            'Pendaftar mengisi form publik di landing atau operator isi manual via <strong>Pendaftaran Baru</strong>.',
            'Buka tab <strong>Verifikasi</strong> di dashboard — daftar pendaftar MENUNGGU_VERIFIKASI.',
            'Periksa berkas yang diunggah (KTP, KK, SKL, dsb.) di kolom Dokumen.',
            'Klik <em>Verifikasi</em> untuk menyetujui atau <em>Tolak</em> bila berkas tidak lengkap.',
            'Setelah TERVERIFIKASI, pendaftar masuk tahap seleksi.',
        ],
    ],
    [
        'icon' => 'notifications', 'badge' => 'F3',
        'judul' => 'Notifikasi Panitia (Fase 3)',
        'role'  => ['operator', 'admin', 'bendahara'],
        'langkah' => [
            'Setiap pemicu tercatat di menu <strong>Notifikasi</strong> — bukti bayar diunggah, pembayaran gateway terverifikasi/lunas, pendaftar baru, berkas disubmit, data diajukan verifikasi. Lencana sidebar menampilkan jumlah belum dibaca dan polling tiap 60 detik.',
            'Yang berbunyi di WhatsApp panitia: uang masuk (bukti transfer/QRIS &amp; gateway), <strong>calon siswa baru mendaftar</strong>, <strong>data diajukan</strong> — termasuk kirim ulang data yang tadi ditolak — dan <strong>berkas dikirim</strong>. Kabar operasional itu dibatasi 3 pesan/jam per pendaftar supaya klik berulang tidak menjebol kuota Fonnte.',
            'Pengaturannya ada di <strong>Pengaturan → WhatsApp Gateway (Fonnte) → blok Notifikasi WhatsApp Panitia</strong>: nomor tujuan (pisah koma) dan saklar kirim. Satu daftar nomor dipakai bersama untuk kabar uang masuk dan kabar operasional; bisa diganti cepat dari <strong>Petugas &amp; Hak Akses</strong>.',
            'Bila nomor panitia kosong, kiriman jatuh ke <em>Kontak WhatsApp Panitia</em> di bagian Identitas. Kegagalan kirim dicatat di tabel <code>wa_log</code> dan tidak mengganggu proses utama — hasil percobaan kirim terakhir tampil di blok yang sama beserta alasan dari Fonnte.',
            'Nomor tujuan saja tidak cukup: WA berangkat dari perangkat Fonnte yang ter-connect, jadi token di kartu yang sama harus aktif. Token di <code>.env</code> (FONNTE_TOKEN) lebih utama daripada token yang diketik di halaman ini — bila keduanya beda, nilai dari database diabaikan.',
            'Email (kolom <em>notif_email</em>) hanya titik sambungan — belum dikirim sampai diimplementasikan.',
            'Lonceng <strong>portal siswa</strong> berbeda: riwayat yang dibuat sebelum akun siswa itu pertama kali dibuka (mis. keputusan pembayaran saat akun baru saja dikirim) tidak ditampilkan — daftar mereka dimulai dari masuk pertama. Barisnya tetap ada di <code>notifikasi_siswa</code> dan papan panitia ini tidak terpotong, jadi kalau orang tua bertanya "kok tidak ada kabar", itu bukan pesan hilang: cukup minta mereka membuka menu <em>Informasi</em>, atau kirim ulang pesannya dari halaman terkait.',
        ],
    ],
    [
        'icon' => 'group_add',
        'judul' => 'Generate Akun Siswa',
        'role'  => ['operator', 'admin'],
        'langkah' => [
            'Buka tab <strong>Akun</strong> di dashboard operator.',
            'Pastikan token Fonnte sudah diisi di <strong>Pengaturan → WhatsApp Gateway</strong> agar kredensial terkirim otomatis.',
            'Klik <em>Generate</em> per siswa atau <em>Generate Semua</em> untuk batch.',
            'Username dibuat otomatis (user-siswa-XXXX), password acak di-hash bcrypt.',
            'Kredensial dikirim via WA ke orang tua. Bila WA gagal, kredensial ditampilkan sekali di halaman hasil — catat/salin sebelum tinggalkan halaman.',
            '<em>Kirim Ulang</em> me-reset password dan mengirim ulang pesan.',
        ],
    ],
    [
        'icon' => 'payments',
        'judul' => 'Verifikasi Pembayaran (Bendahara)',
        'role'  => ['bendahara', 'admin', 'kepsek'],
        'langkah' => [
            'Login sebagai Bendahara → buka <strong>Verifikasi Pembayaran</strong>.',
            'Daftar tagihan PENDING tampil dengan unggahan bukti transfer.',
            'Klik <em>Verifikasi</em> untuk menyetujui atau <em>Tolak</em> bila bukti tidak valid.',
            'Nominal tagihan diambil dari Pengaturan → Rincian Biaya SPMB (biaya formulir & ' . e(label_biaya_pendaftaran()) . ').',
            'Tagihan setelah lulus adalah <strong>sisa</strong> ' . e(label_biaya_pendaftaran()) . ' — Biaya Masuk dikurangi Biaya Formulir yang sudah dibayar saat registrasi. Boleh dibayar lunas atau 2 angsuran: status menjadi ANGSURAN_1 setelah dana pertama menutup setengah sisa, dan LUNAS setelah totalnya menutup seluruh sisa.',
            'Tombol <em>Kirim Pengingat Jatuh Tempo</em> mengirim notifikasi in-app (dan WhatsApp bila diaktifkan di Pengaturan) ke siswa yang angsurannya mendekati/melewati jatuh tempo.',
            'Rekap pembayaran dicetak dari tombol <em>Cetak Rekap Keuangan</em> (siap PDF) atau diunduh sebagai CSV lewat <em>Export CSV</em>.',
        ],
    ],
    [
        'icon' => 'verified_user', 'badge' => 'F4',
        'judul' => 'Verifikasi Pembayaran & Kirim Akun (Fase 4)',
        'role'  => ['bendahara', 'admin', 'kepsek'],
        'langkah' => [
            'Buka <strong>Verifikasi Pembayaran</strong> — daftar bukti MENUNGGU_VERIFIKASI_BAYAR.',
            '<em>Setujui</em> → status BAYAR_TERVERIFIKASI (tombol hijau). <em>Tolak</em> → wajib isi alasan, status kembali MENUNGGU_PEMBAYARAN + WA alasan ke orang tua.',
            'Tombol <em>Verifikasi &amp; Kirim Akun</em> melakukan verifikasi + generate akun + kirim kredensial dalam satu klik (idempoten — klik ulang tidak dobel).',
            'Gateway auto-kirim akun (<em>kirim_akun_otomatis</em>) nonaktif default — hanya berlaku bila payment gateway (Midtrans) diaktifkan; keduanya opsional.',
            'Pemicu notifikasi: BUKTI_BAYAR_MASUK / PEMBAYARAN_GATEWAY_LUNAS / BERKAS_DISUBMIT.',
        ],
    ],
    [
        'icon' => 'folder_shared', 'badge' => 'F5',
        'judul' => 'Berkas Dinamis (Fase 5)',
        'role'  => ['operator', 'admin'],
        'langkah' => [
            'Kelola daftar jenis berkas di <strong>Pengaturan → Daftar Berkas</strong> (tambah/ubah/nonaktif; set format & maksimum unggah per jenis).',
            'Siswa mengunggah berkas di portal — validasi format/ukuran/MIME dilakukan server.',
            'Submit hanya bisa bila semua berkas <em>wajib</em> lengkap → pendaftar masuk BERKAS_DISUBMIT + notifikasi operator.',
            'Buka <strong>Verifikasi Pendaftar</strong> tab Berkas Persyaratan — antrean BERKAS_DISUBMIT / BERKAS_REVISI.',
            'Putuskan per berkas: Setujui / Tolak (alasan wajib). Semua lolos → BERKAS_TERVERIFIKASI; ada ditolak → BERKAS_REVISI + WA ke orang tua (hanya berkas ditolak yang dibuka kembali).',
            'WA penolakan menautkan penerima ke halaman <strong>Cek Status Pendaftaran</strong> (bukan halaman bayar) — di sana ada tombol <em>Buka Halaman Pembayaran</em> untuk mendapat tautan bayar baru tanpa mengetik ulang apa pun.',
        ],
    ],
    [
        'icon' => 'assignment',
        'judul' => 'Jadwal Tes & Input Seleksi',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Buka <strong>Jadwal Ujian</strong> — tambah jadwal (kode auto TES-NN), set metode & status TERJADWAL.',
            'Klik ikon <em>chat</em> pada baris jadwal untuk kirim notifikasi WA jadwal ke seluruh wali siswa terjadwal.',
            'Buka <strong>Input Seleksi</strong> — isi nilai CBT, BTQ, Wawancara per pendaftar.',
            'Nilai akhir dihitung otomatis; ambang kelulusan default 70 (bisa diubah per form).',
            'Pilih keputusan: PROSES (menunggu), LULUS, atau TIDAK_LULUS.',
            'Ganti ambang atau nilai lalu <em>Simpan</em> — Nilai Akhir dan keputusan langsung terhitung ulang.',
        ],
    ],
    [
        'icon' => 'event_seat', 'badge' => 'F6',
        'judul' => 'Peserta Ujian (Fase 6)',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Buka <strong>Jadwal Ujian</strong> — isi Ruangan & Kapasitas per jadwal (kapasitas 0 = tanpa batas).',
            'Bagian <strong>Peserta Ujian</strong> ada di halaman yang sama, di bawah daftar jadwal — klik <em>Atur Peserta Otomatis</em> (urut no_pendaftaran, penuhi kapasitas, transaksi DB).',
            'Yang dilewati otomatis hanyalah calon yang <strong>sudah ada di kartu itu</strong>. Satu calon bisa ikut beberapa kegiatan, jadi yang absen di Tes Baca & Tulis bisa dijaring ulang pada jadwal susulan tanpa menghapus barisnya lebih dulu.',
            'Nomor peserta dibuat otomatis (KODE-001 dst) — unik, dan tiap kegiatan punya nomornya sendiri.',
            'Pengaturan otomatis menaikkan tahap BERKAS_TERVERIFIKASI → JADWAL_DITETAPKAN; calon yang sudah bernilai atau sudah diumumkan tidak dipundahkan tahapnya.',
            'Tambah manual: pilih calon siap jadwal yang belum ada di kartu ini — yang sudah ikut kegiatan lain ditandai <em>(sudah: KODE)</em>. Hapus: tahap kembali BERKAS_TERVERIFIKASI hanya bila ia sudah tidak punya jadwal sama sekali.',
            '<em>Kirim WA Jadwal</em> pada satu kartu mengirim pesan sekaligus <strong>notifikasi lonceng portal</strong> untuk wali calon di kartu itu saja; klik ganda pada kartu yang sama dilewati. <em>Umumkan Semua Jadwal</em> memakai pengirim yang sama untuk seluruh kegiatan Terjadwal dalam satu pesan.',
            'Siswa melihat <em>Kartu Peserta</em> (kegiatan terdekat yang belum lewat) dan tabel <em>Agenda Tes</em> berisi kegiatan miliknya sendiri di portal (cetak via tombol Print).',
        ],
    ],
    [
        'icon' => 'fact_check', 'badge' => 'F7',
        'judul' => 'Input Hasil Seleksi (Fase 7)',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Hanya pendaftar JADWAL_DITETAPKAN / NILAI_TERINPUT yang tampil di <strong>Input Seleksi</strong>.',
            'Judul kolom nilai mengikuti <em>nama kegiatan pada jadwal tes</em> (3 kegiatan pertama, urut tanggal & jam) dan otomatis disingkat; arahkan kursor ke judul untuk nama lengkapnya.',
            'Nilai diisi manual per baris; Nilai Akhir dan keputusan LULUS/TIDAK_LULUS terhitung otomatis dari ambang kelulusan.',
            'Baris tersusun <strong>urut abjad nama</strong>. Kotak cari menyaring No. Pendaftaran / Nama Calon Siswa — hanya menyembunyikan baris, seluruh nilai tetap ikut tersimpan saat Simpan.',
            'Keputusan per siswa masih bisa diubah manual sebelum disimpan.',
            'Simpan tabel → tahap otomatis NILAI_TERINPUT.',
            'Peringatan tampil bila ada LULUS tanpa penempatan kelas.',
        ],
    ],
    [
        'icon' => 'campaign', 'badge' => 'F8',
        'judul' => 'Pengumuman Hasil (Fase 8)',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Hasil seleksi & penempatan disembunyikan dari publik/portal sampai diumumkan.',
            'Buka <strong>Pengumuman Hasil</strong> — pilih <em>Umumkan Sekarang</em> atau <em>Jadwalkan</em> (waktu server database).',
            'Umumkan Sekarang dengan siswa LULUS tanpa kelas → wajib centang konfirmasi eksplisit.',
            'Setelah diumumkan: tahap pendaftar → DIUMUMKAN, hasil tampil di cek-status publik & portal siswa.',
            'Kirim WA pengumuman batch 10 nomor per request — pantau progres di halaman. Tekan <em>Kirim Ulang yang Gagal</em> bila ada kegagalan.',
            'Cek-status publik: satu kolom isian — nomor WhatsApp penuh langsung tampil, nomor pendaftaran tetap wajib 4 digit terakhir WA (pencarian nama dihapus).',
        ],
    ],
    [
        'icon' => 'move_to_inbox', 'badge' => 'AKHIR',
        'judul' => 'Penempatan Kelas',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Buka <strong>Penempatan Kelas</strong> — kartu keterisian menampilkan sisa kursi tiap rombel.',
            'Siswa berstatus LULUS yang belum ditempatkan muncul urut nilai tertinggi.',
            'Pilih kelas tujuan per siswa lalu klik <em>Tempatkan</em>, atau klik <em>Tempatkan Semua</em> untuk alokasi otomatis (greedy per nilai).',
            'Kelas penuh → opsi disabled dan submit ditolak.',
            'Gunakan <em>Pindah</em> untuk memindahkan siswa ke rombel lain, atau ikon hapus untuk membatalkan penempatan.',
        ],
    ],
    [
        'icon' => 'settings_suggest', 'badge' => 'CMS',
        'judul' => 'Pengaturan Sistem & WA Gateway',
        'role'  => ['operator', 'admin'],
        'langkah' => [
            'Buka <strong>Pengaturan</strong> di menu operator.',
            'Seksi 1–6 mengatur konten landing (ticker, hero, statistik, biaya) — termasuk blok <strong>Tanya Jawab (FAQ) Landing</strong> yang mengisi section Pusat Bantuan di halaman depan.',
            'Seksi 7 <strong>WhatsApp Gateway</strong>: isi token Fonnte (dari dashboard Fonnte → Settings → Token) dan blok <strong>Notifikasi WhatsApp Panitia</strong> di bawahnya — nomor tujuan yang menerima kabar uang masuk, pendaftar baru, pengajuan data, dan berkas, serta saklar kirim.',
            'Seksi 7b <strong>Teks Pesan WhatsApp ke Calon Murid</strong>: kalimat tujuh pesan keluar (instruksi bayar, akun &amp; password, jadwal tes, hasil seleksi, pengingat biaya, bukti bayar ditolak, berkas ditolak) bisa diubah di sini — tidak perlu menyunting kode. Biarkan kotak kosong (atau isi persis kalimat bawaannya) untuk tetap memakai kalimat aplikasi; <code>{placeholder}</code> yang bertanda <em>(wajib)</em> harus ada, dan teks maksimal 1.200 karakter. Perubahan hanya berlaku untuk pesan yang dikirim sesudah tombol Simpan.',
            'Tanpa token, fitur generate akun & notifikasi jadwal tetap jalan tapi WA tidak terkirim — kredensial ditampilkan manual di halaman hasil.',
            'Seksi 8 <strong>Payment Gateway (Midtrans)</strong>: opsional — tanpa API key, sistem tetap jalan penuh dengan transfer bank manual / QRIS.',
            'Seksi 9: metode bayar — transfer &amp; QRIS selalu tersedia, gateway online hanya bila dikonfigurasi; plus teks S&amp;K. Seksi 10: Daftar Berkas. Seksi 11: Petugas &amp; Hak Akses (di sana nomor WA bendahara juga bisa diganti cepat).',
        ],
    ],
    [
        'icon' => 'download', 'badge' => 'Ekspor',
        'judul' => 'Export Rekap CSV',
        'role'  => ['operator', 'admin', 'bendahara'],
        'langkah' => [
            'Dari dashboard operator, klik <em>Export Rekap SPMB</em> atau <em>Export Rekap Seleksi</em>.',
            'Atau akses langsung: <code>export.php?rekap=pendaftar|seleksi|pembayaran</code>.',
            'File CSV memakai BOM UTF-8 + separator <code>;</code> — aman dibuka di Excel Indonesia tanpa ubah encoding.',
        ],
    ],
    [
        'icon' => 'task_alt', 'badge' => 'F11',
        'judul' => 'Kesiapan Rilis & Kesiapan Operasional (Fase 11)',
        'role'  => ['operator', 'admin'],
        'langkah' => [
            'Buka menu <strong>Kesiapan Rilis</strong> di sidebar (hanya operator/admin) — halaman baca-saja, tidak mengubah apa pun.',
            'Skor kesiapan menampilkan persentase item siap; status: <em>SIAP</em> / <em>SIAP DENGAN CATATAN</em> / <em>BELUM SIAP</em> (ada item gagal).',
            '13 item checklist: URL publik (APP_URL), rekening & WA panitia (deteksi nomor contoh), jadwal+S&amp;K, akun staf terkunci, hash password lemah, skema terbaru, metode bayar, kunci Midtrans, token Fonnte, izin folder, umur cadangan DB, notif WA uang masuk ke bendahara.',
            'Kartu <strong>Antrean Operasional</strong>: berkas disubmit, revisi siswa, bukti bayar menunggu, siap kirim akun — angka mengikuti data per tahap.',
            'Pemeriksaan teknis lanjutan (versi PHP, ekstensi, php.ini, koneksi) tetap lewat terminal: <code>php tools/cek-proyek.php</code> — keluar dengan kode 1 bila ada GAGAL.',
            'Sebelum rilis publik: pastikan tanpa item <em>Gagal</em>, jalankan cek-proyek, lalu ikuti <code>docs/UJI-MANUAL.md</code> &amp; <code>docs/DEPLOY.md</code>.',
        ],
    ],
    [
        'icon' => 'inventory_2', 'badge' => 'F11',
        'judul' => 'Instrumen Rilis: Cadangan, Paket & Seed Demo',
        'role'  => ['operator', 'admin', 'bendahara'],
        'langkah' => [
            'Cadangan database: <code>php tools/backup-db.php</code> → file gzip di <code>storage/backup/db/</code> (rotasi otomatis 14 hari). Password DB lewat environment — tidak pernah dicetak ke layar.',
            'Cadangan berkas: <code>php tools/backup-berkas.php</code> → zip <code>storage/dokumen</code> ke <code>storage/backup/</code> (rotasi 14 hari).',
            'Jadwalkan harian (Windows <code>schtasks</code> / cron Linux) — contoh perintah &amp; skrip pemulihan ada di <code>docs/DEPLOY.md</code> bagian Cadangan.',
            'Paket rilis: <code>php tools/buat-paket.php</code> → zip di <code>dist/</code> yang sudah diverifikasi bebas <code>.env</code>, <code>.git</code>, dan folder data pribadi — aman diunggah ke hosting.',
            'Data demo UAT lengkap (11 tahap, berkas, ujian): <code>mysql -u root db_spmb_mualimku &lt; database/seed_demo.sql</code> — idempoten; hapus dengan <code>seed_demo_hapus.sql</code>.',
            'Semua tools di atas <strong>CLI-only</strong> — dibuka lewat browser akan 404.',
        ],
    ],
    [
        'icon' => 'school', 'badge' => 'Portal Siswa',
        'judul' => 'Portal Siswa',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Siswa login → portal menampilkan status verifikasi, seleksi, dan penempatan.',
            'Biodata bisa diedit selama belum TERVERIFIKASI/DITOLAK.',
            'Siswa mengunggah berkas persyaratan & bukti transfer lewat portal masing-masing.',
            'Operator memantau kelengkapan berkas dari tab Verifikasi.',
            'Kartu Peserta Ujian tampil setelah jadwal ditetapkan (siap cetak).',
            'Hasil seleksi & penempatan tampil setelah pengumuman resmi (Fase 8).',
        ],
    ],
];
?>
<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1000px]">

    <div class="flex items-center gap-2 flex-wrap">
      <span class="badge badge-blue">Panduan</span>
      <span class="badge badge-gray">Untuk <?= e($me['role'] === 'bendahara' ? 'Bendahara' : ($me['role'] === 'kepsek' ? 'Kepala Sekolah' : 'Operator')) ?></span>
    </div>
    <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Panduan & SOP SPMB</h1>
    <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
      Langkah kerja operasional SPMB <?= e($PENGATURAN['nama_sekolah'] ?? 'SMP Muhammadiyah 5 Kota Tangerang') ?> — disusun sesuai alur PRD.
    </p>

    <div class="mt-6 space-y-5">
      <?php foreach ($panduan as $p):
        /* Sembunyikan bagian yang tidak relevan untuk role bendahara. */
        if ($me['role'] === 'bendahara' && !in_array('bendahara', $p['role'], true)) {
            continue;
        }
        if ($me['role'] === 'kepsek' && !in_array('kepsek', $p['role'], true)) {
            continue;
        }
        /* Jangkar per judul agar dashboard lain bisa menautkan bagian spesifik,
         * mis. app/operator/panduan.php#verifikasi-pembayaran-bendahara. */
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($p['judul'])), '-');
      ?>
        <section id="<?= e($slug) ?>" class="card p-5 sm:p-6 scroll-mt-24">
          <div class="flex items-start gap-3">
            <span class="icon-box bg-canvas border border-line text-ink-700 shrink-0"><?= icon($p['icon']) ?></span>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-bold text-ink-900"><?= e($p['judul']) ?></h2>
                <?php if (!empty($p['badge'])): ?><span class="badge badge-gray"><?= e($p['badge']) ?></span><?php endif; ?>
              </div>
              <ol class="mt-3 space-y-2 list-decimal list-inside text-sm text-ink-700">
                <?php foreach ($p['langkah'] as $l): ?>
                  <li class="leading-relaxed"><?= $l /* sudah aman, konten statis dari dev */ ?></li>
                <?php endforeach; ?>
              </ol>
            </div>
          </div>
        </section>
      <?php endforeach; ?>
    </div>

    <section class="mt-6 card p-5 sm:p-6 border-l-4 border-l-primary">
      <h2 class="font-bold text-ink-900">Catatan Penting</h2>
      <ul class="mt-3 space-y-1.5 text-sm text-ink-700 list-disc list-inside">
        <li>Semua aksi POST dilindungi CSRF — jangan refresh halaman hasil ekspor/submit tanpa sengaja (bisa tampil "Formulir kedaluwarsa").</li>
        <li>Username siswa dibuat otomatis & unik. Password di-hash bcrypt — tidak bisa dilihat ulang; reset terbaik via CLI: <code>php tools/set-password.php &lt;username&gt;</code> (min 10 karakter, konfirmasi 2×).</li>
        <li><strong>Alamat masuk dipisah</strong> — pihak sekolah cukup satu alamat: <a href="<?= url('admin/') ?>" target="_blank" rel="noopener"><code><?= e(url('admin/')) ?></code></a> (Login Pihak Sekolah; otomatis lanjut ke dashboard bila sudah login), murid/orang tua lewat tombol <em>Login Siswa</em> di halaman SPMB. Bagikan alamat <code>/admin</code> hanya ke panitia — alamat pendek hanya memudahkan diingat, BUKAN pembatas akses: setiap halaman staf tetap dijaga <code>require_role()</code>.</li>
        <li><strong>GANTI PASSWORD DEFAULT sebelum go-live</strong> — akun operator/bendahara/kepsek WAJIB diganti. Buka menu <a href="<?= url('app/staf/ganti-password.php') ?>">Ganti Password</a> di sidebar. Setelah reset via CLI, staf akan otomatis dipaksa ganti password saat login berikutnya. Password lama/daftarnya tidak lagi disimpan di file <code>hak akses.txt</code> (sudah dihapus).</li>
        <li><strong>Seed SQL mengunci akun staf</strong> (Fase 11): hash <code>'!LOCKED'</code> — tidak bisa login apa pun password lama. Setelah impor database, jalankan <code>php tools/set-password.php &lt;username&gt;</code> untuk operator/bendahara/kepsek (dan <code>siswa</code> bila memakai <code>database/seed_demo.sql</code>) sebelum login pertama. <strong>Hosting tanpa SSH (cPanel):</strong> buat penanda <code>storage/.izinkan-set-password</code> via File Manager, buka <code>/tools/set-password-web.php</code>, selesaikan, lalu hapus penanda + berkas tool (Fase 12).</li>
        <li><strong>Token bayar disimpan ter-hash</strong> (Fase 11): kolom <code>pay_token_hash</code> (SHA-256, UNIQUE); registrasi ulang dengan data sama <strong>merotasi</strong> token sehingga tautan bayar lama mati. Token mentah hanya tampil 3 tempat: halaman sukses registrasi, pesan WA registrasi, dan hasil tombol <em>Buka Halaman Pembayaran</em> di cek-status — pengunjung langsung dialihkan, tokennya tidak pernah ditampilkan di halaman. Karena token baru menggantikan yang lama, tautan bayar di pesan WA ikut mati setelah tombol itu dipakai.</li>
        <li><strong>Rate anti-penyalahgunaan</strong> (Fase 11): ganti password maks 5 percobaan / 15 menit (per IP &amp; per akun); kirim WA registrasi maks 3 / nomor / 24 jam; cek-status dihitung per IP (10 / 15 menit) dan lintas IP per nomor pendaftaran (5 / 15 menit, format <code>REG-YYYY-NNNN</code> saja yang dihitung, hanya saat gagal); tombol tautan bayar maks 8 / jam per pendaftar dan hanya untuk hasil pencarian yang terbukti di sesi ini.</li>
        <li><strong>Isi pesan WA instruksi bayar otomatis mengikuti metode aktif</strong> — jika transfer/QRIS/gateway dimatikan di Pengaturan, langkahnya ikut hilang; selama teksnya belum disunting, kalimat penutupnya: "Setelah pembayaran terverifikasi, akun dan password dikirim lewat WhatsApp." Seluruh kalimat WA ke wali (tujuh pesan) bisa diubah di <strong>Pengaturan → Teks Pesan WhatsApp ke Calon Murid</strong> (seksi 7b) — baris yang dibiarkan sama dengan bawaan tetap memakai kalimat aplikasi.</li>
        <li><strong>Cadangan data wajib dijadwalkan</strong> sebelum go-live: <code>php tools/backup-db.php</code> &amp; <code>php tools/backup-berkas.php</code> (retensi 14 hari) — lihat skrip <code>schtasks</code>/cron di <code>docs/DEPLOY.md</code>. Tool rilis (buat-paket, backup, cek-proyek, cek-statis, retensi, uji-*) bersifat CLI-only (akses web = 404); satu-satunya tool ber-web adalah <code>set-password-web.php</code> yang hanya aktif selama penanda <code>.izinkan-set-password</code> ada (Fase 12).</li>
        <li>API key Fonnte & Midtrans dibaca dari file <code>.env</code> di root proyek (di luar tree publik); bila kosong, fallback ke kolom CMS. <strong>Midtrans opsional</strong> — tanpa key, pembayaran tetap jalan via transfer manual / QRIS. <code>APP_URL</code> di <code>.env</code> dipakai untuk tautan WhatsApp absolut (kosongkan saat localhost).</li>
        <li>Berkas pribadi (KK, akta, bukti transfer) disimpan di <code>storage/dokumen/</code> dan hanya bisa diakses lewat gate PHP yang mengecek login & kepemilikan — hanya operator/admin/bendahara (kepsek melihat hasil & pengumuman lewat halamannya sendiri).</li>
        <li><strong>Retensi berkas pribadi</strong> (Fase 12): aktifkan &amp; atur batas bulan di menu <a href="<?= url('app/operator/retensi.php') ?>">Retensi Data</a>; eksekusi (web/CLI) hanya menghapus berkas fisik + jejak dokumen — baris pendaftar &amp; hasil seleksi tetap ada, semua tercatat di <code>retensi_log</code>. Draf kebijakan utk tinjauan sekolah/hukum: <code>docs/KEBIJAKAN-DATA.md</code>.</li>
        <li>Rate limiting login (max 5 gagal / 15 menit / IP) disimpan di tabel <code>login_attempts</code> — tidak bisa dilewati dengan membuang cookie.</li>
        <li><strong>MySQL XAMPP sering crash</strong> — setelah restart, jalankan <code>mysql -u root &lt; database/fix-grants.sql</code> agar user <code>spmb_app</code> tetap punya hak akses.</li>
        <li><strong>Petugas &amp; Hak Akses</strong>: menu grup Pengaturan Sistem untuk mengubah nama/jabatan petugas, mengganti username, menambah petugas baru (operator/bendahara/kepsek), serta mereset password petugas yang lupa. Password hasil reset bersifat <strong>sementara</strong> — petugas wajib menggantinya pada login pertama. Akun yang sedang dipakai login sendiri hanya bisa diubah nama &amp; jabatannya, dan minimal satu akun operator harus tetap aktif. Bila <em>belum ada</em> akun yang bisa login (semua masih <code>!LOCKED</code>), gunakan <code>php tools/set-password.php &lt;username&gt;</code>.</li>
        <li>Pendaftar yang DITOLAK otomatis tidak muncul di rekap seleksi & ekspor.</li>
        <li>Hubungi admin sistem bila ada kendala teknis atau perlu reset database ke seed awal.</li>
      </ul>
    </section>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
</script>
</body>
</html>
