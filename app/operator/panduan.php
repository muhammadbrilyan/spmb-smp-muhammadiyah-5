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
        'judul' => 'Notifikasi Operator (Fase 3)',
        'role'  => ['operator', 'admin', 'bendahara'],
        'langkah' => [
            'Notifikasi otomatis terpicu saat: bukti bayar diunggah, pembayaran gateway lunas, dan berkas disubmit.',
            'Buka menu <strong>Notifikasi</strong> di sidebar — lencana menampilkan jumlah belum dibaca.',
            'Pengaturan notifikasi WA operator ada di <strong>Pengaturan → Notifikasi</strong>: isi nomor (pisah koma), aktifkan toggle.',
            'Email (kolom <em>notif_email</em>) hanya titik sambungan — belum dikirim sampai diimplementasikan.',
            'Sidebar polling setiap 60 detik — tidak perlu refresh manual.',
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
            'Nominal tagihan diambil dari Pengaturan (biaya formulir & uang masuk).',
            'Rekap pembayaran bisa diekspor CSV dari dashboard operator.',
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
            'Buka <strong>Verifikasi Berkas</strong> — antrean BERKAS_DISUBMIT / BERKAS_REVISI.',
            'Putuskan per berkas: Setujui / Tolak (alasan wajib). Semua lolos → BERKAS_TERVERIFIKASI; ada ditolak → BERKAS_REVISI + WA ke orang tua (hanya berkas ditolak yang dibuka kembali).',
            'WA penolakan menautkan penerima ke halaman <strong>Cek Status Pendaftaran</strong> (bukan halaman bayar) — di sana ada tombol <em>Pulihkan Tautan</em> bila perlu tautan bayar baru.',
        ],
    ],
    [
        'icon' => 'assignment',
        'judul' => 'Jadwal Tes & Input Seleksi',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Buka <strong>Kelola Jadwal Tes</strong> — tambah jadwal (kode auto TES-NN), set metode & status TERJADWAL.',
            'Klik ikon <em>chat</em> pada baris jadwal untuk kirim notifikasi WA jadwal ke seluruh wali siswa terjadwal.',
            'Buka <strong>Input Seleksi</strong> — isi nilai CBT, BTQ, Wawancara per pendaftar.',
            'Nilai akhir dihitung otomatis; ambang kelulusan default 70 (bisa diubah per form).',
            'Pilih keputusan: PROSES (menunggu), LULUS, atau TIDAK_LULUS.',
            'Klik <em>Hitung Ulang</em> bila ingin sistem menghitung ulang nilai akhir & ambang.',
        ],
    ],
    [
        'icon' => 'event_seat', 'badge' => 'F6',
        'judul' => 'Peserta Ujian (Fase 6)',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Buka <strong>Kelola Jadwal Tes</strong> — isi Ruangan & Kapasitas per jadwal (kapasitas 0 = tanpa batas).',
            'Buka <strong>Peserta Ujian</strong> — pilih jadwal, klik <em>Atur Peserta Otomatis</em> (urut no_pendaftaran, penuhi kapasitas, transaksi DB).',
            'Nomor peserta dibuat otomatis (KODE-001 dst) — unik, aman setelah hapus.',
            'Pengaturan otomatis memindahkan tahap BERKAS_TERVERIFIKASI → JADWAL_DITETAPKAN + WA jadwal.',
            'Tambah manual: pilih pendaftar BERKAS_TERVERIFIKASI yang belum terjadwal. Hapus: tahap kembali BERKAS_TERVERIFIKASI.',
            'Siswa melihat <em>Kartu Peserta</em> di portal (cetak via tombol Print).',
        ],
    ],
    [
        'icon' => 'fact_check', 'badge' => 'F7',
        'judul' => 'Seleksi & Impor Nilai (Fase 7)',
        'role'  => ['operator', 'admin', 'kepsek'],
        'langkah' => [
            'Hanya pendaftar JADWAL_DITETAPKAN / NILAI_TERINPUT yang tampil di <strong>Input Seleksi</strong>.',
            'Struktur nilai (CBT, BTQ, Wawancara) TIDAK berubah — tetap diisi manual atau impor.',
            'Simpan tabel → tahap otomatis NILAI_TERINPUT.',
            'Impor CSV: kolom <code>no_pendaftaran;cbt;btq;wawancara;kode_jadwal</code> (pemisah ; atau ,). Pratinjau dulu → periksa baris galat → eksekusi Impor.',
            'Impor TIDAK mengubah hasil LULUS/TIDAK_LULUS yang sudah diputuskan operator.',
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
            'Cek-status publik diperketat: wajib no_pendaftaran + 4 digit WA (pencarian nama dihapus).',
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
            'Seksi 1–6 mengatur konten landing (ticker, hero, berita, FAQ, statistik, biaya).',
            'Seksi 7 <strong>WhatsApp Gateway</strong>: isi token Fonnte (dari dashboard Fonnte → Settings → Token).',
            'Tanpa token, fitur generate akun & notifikasi jadwal tetap jalan tapi WA tidak terkirim — kredensial ditampilkan manual di halaman hasil.',
            'Seksi 8 <strong>Payment Gateway (Midtrans)</strong>: opsional — tanpa API key, sistem tetap jalan penuh dengan transfer bank manual / QRIS.',
            'Seksi 9: metode bayar — transfer &amp; QRIS selalu tersedia, gateway online hanya bila dikonfigurasi; plus teks S&amp;K. Seksi 10: Notifikasi. Seksi 11: Daftar Berkas.',
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
            '13 item checklist: URL publik (APP_URL), rekening & WA panitia (deteksi nomor contoh), jadwal+S&amp;K, akun staf terkunci, hash password lemah, skema terbaru, metode bayar, kunci Midtrans, token Fonnte, izin folder, umur cadangan DB, notif WA operator.',
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
      ?>
        <section class="card p-5 sm:p-6">
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
        <li><strong>Token bayar disimpan ter-hash</strong> (Fase 11): kolom <code>pay_token_hash</code> (SHA-256, UNIQUE); registrasi ulang dengan data sama <strong>merotasi</strong> token sehingga tautan bayar lama mati. Token mentah hanya tampil 3 tempat: halaman sukses registrasi, pesan WA registrasi, dan hasil <em>Pulihkan Tautan</em> di cek-status.</li>
        <li><strong>Rate anti-penyalahgunaan</strong> (Fase 11): ganti password maks 5 percobaan / 15 menit (per IP &amp; per akun); kirim WA registrasi maks 3 / nomor / 24 jam; cek-status diperiksa lintas IP per nomor pendaftaran (format <code>REG-YYYY-NNNN</code> saja yang dihitung, dan hanya saat gagal).</li>
        <li><strong>Isi pesan WA instruksi bayar otomatis mengikuti metode aktif</strong> — jika transfer/QRIS/gateway dimatikan di Pengaturan, langkahnya ikut hilang; kalimat penutupnya selalu: "Setelah pembayaran terverifikasi, akun dan password dikirim lewat WhatsApp."</li>
        <li><strong>Cadangan data wajib dijadwalkan</strong> sebelum go-live: <code>php tools/backup-db.php</code> &amp; <code>php tools/backup-berkas.php</code> (retensi 14 hari) — lihat skrip <code>schtasks</code>/cron di <code>docs/DEPLOY.md</code>. Tool rilis (buat-paket, backup, cek-proyek, cek-statis, retensi, uji-*) bersifat CLI-only (akses web = 404); satu-satunya tool ber-web adalah <code>set-password-web.php</code> yang hanya aktif selama penanda <code>.izinkan-set-password</code> ada (Fase 12).</li>
        <li>API key Fonnte & Midtrans dibaca dari file <code>.env</code> di root proyek (di luar tree publik); bila kosong, fallback ke kolom CMS. <strong>Midtrans opsional</strong> — tanpa key, pembayaran tetap jalan via transfer manual / QRIS. <code>APP_URL</code> di <code>.env</code> dipakai untuk tautan WhatsApp absolut (kosongkan saat localhost).</li>
        <li>Berkas pribadi (KK, akta, bukti transfer) disimpan di <code>storage/dokumen/</code> dan hanya bisa diakses lewat gate PHP yang mengecek login & kepemilikan — hanya operator/admin/bendahara (kepsek melihat hasil & pengumuman lewat halamannya sendiri).</li>
        <li><strong>Retensi berkas pribadi</strong> (Fase 12): aktifkan &amp; atur batas bulan di menu <a href="<?= url('app/operator/retensi.php') ?>">Retensi Data</a>; eksekusi (web/CLI) hanya menghapus berkas fisik + jejak dokumen — baris pendaftar &amp; hasil seleksi tetap ada, semua tercatat di <code>retensi_log</code>. Draf kebijakan utk tinjauan sekolah/hukum: <code>docs/KEBIJAKAN-DATA.md</code>.</li>
        <li>Rate limiting login (max 5 gagal / 15 menit / IP) disimpan di tabel <code>login_attempts</code> — tidak bisa dilewati dengan membuang cookie.</li>
        <li><strong>MySQL XAMPP sering crash</strong> — setelah restart, jalankan <code>mysql -u root &lt; database/fix-grants.sql</code> agar user <code>spmb_app</code> tetap punya hak akses.</li>
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
