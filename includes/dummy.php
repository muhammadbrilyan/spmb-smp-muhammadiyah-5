<?php
/**
 * DATA DUMMY (statis) — menggantikan sementara query database.
 * Struktur disesuaikan dengan skema MySQL pada PRD agar mudah disambung nanti.
 */

/* ------------------------------------------------------------------ *
 * 1. pengaturan_sekolah (single row config CMS)
 * ------------------------------------------------------------------ */
$PENGATURAN = [
    'id'                    => 1,
    'nama_sekolah'          => 'SMP Muhammadiyah 5 Kota Tangerang',
    'alamat_sekolah'        => 'Jl. Perintis Kemerdekaan No. 1, Cikokol, Kota Tangerang, Banten 15111',
    'logo_url'              => asset('assets/img/uploads/logo-sekolah.png'),
    'warna_utama'           => '#0056b3',
    'biaya_formulir'        => 200000,
    'nama_bank'             => 'Bank Syariah Indonesia (BSI)',
    'no_rekening'           => '714-239-5501',
    'atas_nama_rekening'    => 'SMP Muhammadiyah 5 Tangerang',
    'kontak_wa_panitia'     => '628123456789',
    'email_sekolah'         => 'spmb@smpmuh5tng.sch.id',
    'tahun_ajaran'          => '2027/2028',
    'gelombang'             => 'Gelombang 1',
    'kuota_total'           => 90,
    'retensi_aktif'         => 0,
    'retensi_bulan_tidak_lulus' => null,
    'retensi_bulan_batal'   => null,
    'retensi_bulan_lulus_arsip' => null,
    'ticker'                => 'Pendaftaran SPMB Gelombang 1 Dibuka: 1 Oktober – 30 November · Kuota Terbatas 90 Siswa (3 Rombel) · Hubungi Layanan SPMB: 0812-9876-5501',
];

/* ------------------------------------------------------------------ *
 * 2. Alur 4 langkah SPMB
 * ------------------------------------------------------------------ */
$LANGKAH = [
    ['no' => '01', 'icon' => 'edit_document', 'judul' => 'Isi Formulir Online',
     'isi'   => 'Lengkapi biodata calon siswa, data wali, dan unggah berkas sesuai daftar persyaratan yang berlaku.',
     'meta'  => 'Estimasi: 5 Menit', 'meta_icon' => 'schedule'],
    ['no' => '02', 'icon' => 'payments', 'judul' => 'Pembayaran & Konfirmasi',
     'isi'   => 'Bayar biaya formulir Rp 200.000 melalui bank/QRIS. Sistem memverifikasi otomatis nomor & rekening.',
     'meta'  => 'Verifikasi Otomatis', 'meta_icon' => 'verified_user'],
    ['no' => '03', 'icon' => 'laptop_mac', 'judul' => 'Tes CBT & Wawancara BTQ',
     'isi'   => 'Mengikuti ujian komputer (CBT), wawancara Al-Qur\u2019an (BTQ), dan seleksi minat bakat di kampus atau daring.',
     'meta'  => 'Di Kampus / Daring (Luar Kota)', 'meta_icon' => 'groups'],
    ['no' => '04', 'icon' => 'school', 'judul' => 'Pengumuman & Daftar Ulang',
     'isi'   => 'Hasil keputusan seleksi diumumkan online dan via WhatsApp. Siswa lulus menyelesaikan daftar ulang.',
     'meta'  => 'Hasil Dikirim Via WhatsApp Orang Tua', 'meta_icon' => 'send'],
];

/* ------------------------------------------------------------------ *
 * 3. FAQ
 * ------------------------------------------------------------------ */
$FAQ = [
    ['q' => 'Apakah sekolah ini benar-benar berbasis tahfidz Qur\u2019an?',
     'a' => 'Ya. SMP Muhammadiyah 5 Kota Tangerang menerapkan pembinaan pemahaman dan pengembangan sarana prasarana hafalan hingga tingkat 10 juz yang dilengkapi sertifikasi BTQ.'],
    ['q' => 'Bagaimana jika calon siswa berasal di luar Kota Tangerang?',
     'a' => 'Pendaftar luar daerah tetap diterima. Proses seleksi dapat diikuti secara daring (video call) dan penempatan kelas mengikuti kuota rombel yang tersedia.'],
    ['q' => 'Apakah uang pangkal dapat diangsur?',
     'a' => 'Bisa. Tersedia skema angsuran 2 tahap (Angsuran 1 saat daftar ulang dan Angsuran 2 pada periode berikutnya). Ajukan skema melalui panitia SPMB.'],
    ['q' => 'Berapa biaya formulir pendaftaran dan ke mana saya transfer?',
     'a' => 'Biaya formulir ditetapkan panitia (default Rp 200.000) dan dibayarkan ke rekening resmi sekolah yang tertera pada halaman Rincian Biaya. Nominal selalu mengikuti pengaturan CMS terbaru.'],
    ['q' => 'Apakah wajib membayar secara online (virtual account / e-wallet)?',
     'a' => 'Tidak. Metode utama adalah transfer bank manual atau QRIS: transfer sesuai rekening resmi sekolah, lalu unggah bukti pembayaran untuk diverifikasi panitia. Pembayaran online hanya pilihan tambahan bila panitia mengaktifkannya.'],
];

/* ------------------------------------------------------------------ *
 * 4. Statistik ringkas landing
 *    [Demo dihapus] Nomor contoh "345+ pendaftar" dll. menyesatkan —
 *    landing page memakai $STATISTIK per-halaman, bukan nilai di sini.
 * ------------------------------------------------------------------ */
$STATISTIK = [];

/* ------------------------------------------------------------------ *
 * 5. Rombel / kelas (tabel `kelas`)
 * ------------------------------------------------------------------ */
$ROMBEL = [
    ['kode' => '7A', 'nama' => 'Kelas VII-A (Tahfidz & Sains Unggulan)', 'wali' => 'Ustadz Fahru Rozi, S.Pd.I', 'kuota' => 30, 'terisi' => 28],
    ['kode' => '7B', 'nama' => 'Kelas VII-B (Bilingual & IT Digital)',   'wali' => 'Siti Rahmawati, M.Pd',       'kuota' => 30, 'terisi' => 24],
    ['kode' => '7C', 'nama' => 'Kelas VII-C (Reguler Inovatif)',          'wali' => 'Drs. Bambang Irawan',        'kuota' => 30, 'terisi' => 20],
];

/* ------------------------------------------------------------------ *
 * 6. Tabel pendaftar untuk dashboard operator
 *    [Demo dihapus 2026-10-28] Enam pendaftar contoh dikosongkan —
 *    fallback hanya dipakai saat DB mati; data kosong lebih jujur
 *    daripada menampilkan pendaftar fiktif.
 * ------------------------------------------------------------------ */
$PENDAFTAR = [];

/* ------------------------------------------------------------------ *
 * 7. Tab 2 — Penjadwalan tes
 * ------------------------------------------------------------------ */
$JADWAL_TES = [
    ['kode' => 'TES-01', 'nama' => 'Ujian CBT & Wawancara BTQ', 'tgl' => '2025-10-18', 'jam' => '08:00 – 11:00', 'mode' => 'Offline Sekolah', 'peserta' => 142, 'status' => 'Terjadwal', 'tone' => 'blue'],
    ['kode' => 'TES-02', 'nama' => 'Wawancara Orang Tua / Wali', 'tgl' => '2025-10-19', 'jam' => '09:00 – 14:00', 'mode' => 'Offline Sekolah', 'peserta' => 96,  'status' => 'Terjadwal', 'tone' => 'blue'],
    ['kode' => 'TES-03', 'nama' => 'Seleksi Prestasi & Tahfidz', 'tgl' => '2025-10-20', 'jam' => '13:00 – 16:00', 'mode' => 'Video Call',     'peserta' => 42,  'status' => 'Draft',     'tone' => 'amber'],
    ['kode' => 'TES-04', 'nama' => 'Tes Kesehatan & Psikotes',   'tgl' => '2025-10-25', 'jam' => '08:00 – 12:00', 'mode' => 'Offline Sekolah', 'peserta' => 184, 'status' => 'Draft',     'tone' => 'amber'],
];

/* ------------------------------------------------------------------ *
 * 8. Tab 3 — Input hasil seleksi
 *    [Demo dihapus 2026-10-28] Ikut dikosongkan bersama $PENDAFTAR.
 * ------------------------------------------------------------------ */
$HASIL_SELEKSI = [];

/* ------------------------------------------------------------------ *
 * 9. HAK AKSES — sumber kredensial
 *    [Fase 10] Berkas "hak akses.txt" DIHAPUS dari repository.
 *    Reset password staf:  php tools/set-password.php <username>
 *    Akun demo di bawah sengaja TANPA password (fail-closed).
 *    Saat terkoneksi DB, bagian ini diganti query ke tabel `users`.
 * ------------------------------------------------------------------ */

$USER_LOGIN = [
    'nama'     => 'Ust. M. Rizky Pratama, S.Pd.',
    'role'     => 'operator',
    'jabatan'  => 'Operator SPMB & SIMS',
    'inisial'  => 'RP',
];

$META_ROLE = [
    'siswa'     => ['label' => 'Siswa',     'nama' => 'Aisyah Nur Salsabila',         'jabatan' => 'Calon Siswa SPMB'],
    'operator'  => ['label' => 'Operator',  'nama' => 'Ust. M. Rizky Pratama, S.Pd.', 'jabatan' => 'Operator SPMB & SIMS'],
    'bendahara' => ['label' => 'Bendahara', 'nama' => 'Hj. Siti Rohmah, S.E.',        'jabatan' => 'Bendahara Sekolah'],
    'kepsek'    => ['label' => 'Kepsek',    'nama' => 'Drs. H. Ahmad Sudrajat, M.Pd','jabatan' => 'Kepala Sekolah'],
];

/* [Fase 10] role_dari_username() dihapus — hanya dipakai parser
 * "hak akses.txt" yang sudah dihapus dari repository. */

/* [Fase 10] "hak akses.txt" DIHAPUS — daftar kredensial tidak boleh ada
 * di repository. Reset password staf:  php tools/set-password.php <username>
 * Akun demo di bawah sengaja TANPA password (fail-closed): saat database
 * aktif, login memakai hash di tabel users; saat database mati, login
 * tidak bisa dilakukan sama sekali. */
$AKUN_DEMO = [];

foreach (['operator', 'bendahara', 'kepsek'] as $role) {
    $AKUN_DEMO[] = [
        'role'     => $role,
        'label'    => $META_ROLE[$role]['label'],
        'username' => $role,
        'password' => null,
        'nama'     => $META_ROLE[$role]['nama'],
        'jabatan'  => $META_ROLE[$role]['jabatan'],
        'sumber'   => 'reset via tools/set-password.php',
    ];
}

/* Akun demo siswa (PRD F-04) — password juga null; akun sesungguhnya
 * ada di seed database/db_spmb_mualimku.sql. */
$AKUN_DEMO[] = [
    'role'     => 'siswa',
    'label'    => $META_ROLE['siswa']['label'],
    'username' => 'siswa',
    'password' => null,
    'nama'     => $META_ROLE['siswa']['nama'],
    'jabatan'  => $META_ROLE['siswa']['jabatan'],
    'sumber'   => 'seed database (PRD F-04)',
];

/** Index: role => akun, dan urutan tampil di halaman login. */
$AKUN_PER_ROLE = [];
foreach ($AKUN_DEMO as $akun) {
    if (!isset($AKUN_PER_ROLE[$akun['role']])) {
        $AKUN_PER_ROLE[$akun['role']] = $akun;
    }
}
$URUTAN_ROLE = ['siswa', 'operator', 'bendahara', 'kepsek'];
