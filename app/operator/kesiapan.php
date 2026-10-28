<?php
/**
 * Fase 11 Bagian C — Halaman Kesiapan Rilis & Operasional (operator/admin).
 *
 * Checklist kesiapan: konfigurasi, keamanan akun, kanal pembayaran/WA,
 * skema database, izin & cadangan — plus kartu antrean operasional.
 * Pemeriksaan teknis lanjutan (8 pemeriksaan PHP/ekstensi) tetap lewat
 * CLI: php tools/cek-proyek.php (tidak dijalankan dari web).
 *
 * Read-only: halaman ini TIDAK pernah mengubah data.
 */
require_once __DIR__ . '/../../includes/config.php';

require_role(['operator', 'admin']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title = 'Kesiapan Rilis & Operasional';
$desc  = 'Checklist kesiapan sistem SPMB: konfigurasi, keamanan akun, kanal WA/pembayaran, skema, izin & cadangan data.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* ==================================================================
 * PENGUMPULAN DATA (read-only)
 * ================================================================== */
$appUrl = (string) (env_val('APP_URL') ?: '');
$P      = $GLOBALS['PENGATURAN'];

$stafKunci = (int) (q_col(
    "SELECT COUNT(*) FROM users WHERE role IN ('operator','admin','bendahara','kepsek')
        AND password_hash = '!LOCKED'"
) ?? 0);
$hashLemah = (int) (q_col(
    'SELECT COUNT(*) FROM users
      WHERE password_hash IS NOT NULL
        AND password_hash <> "!LOCKED"
        AND password_hash NOT LIKE "$2y$%"
        AND password_hash NOT LIKE "$argon2%"'
) ?? 0);

$migrasiOk = false;
try {
    $migrasiOk = (int) (q_col(
        "SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name = 'pendaftar' AND column_name = 'pay_token_hash'"
    ) ?? 0) === 1;
} catch (Throwable $e) {
    $migrasiOk = false;
}

$izinOk = true;
$izinMasalah = [];
foreach (['dokumen', 'backup'] as $f) {
    $p = storage_path($f);
    if (!is_dir($p) || !is_writable($p)) {
        $izinOk = false;
        $izinMasalah[] = 'storage/' . $f;
    }
}

/* Cadangan terbaru (jam). */
$cadanganJam = null;
$cadanganFile = array_merge(
    glob(storage_path('backup') . '/db/*.sql.gz') ?: [],
    glob(storage_path('backup') . '/*.sql.gz') ?: []
);
if ($cadanganFile) {
    $mt = array_map('filemtime', $cadanganFile);
    $cadanganJam = (time() - max($mt)) / 3600;
}

/* Antrean operasional (angka murni — untuk kartu info). */
$antre = [
    'berkas'      => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE tahap = 'BERKAS_DISUBMIT' AND arsip_at IS NULL") ?? 0),
    'revisi'      => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE tahap = 'BERKAS_REVISI' AND arsip_at IS NULL") ?? 0),
    'bayar'       => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE tahap = 'MENUNGGU_VERIFIKASI_BAYAR' AND arsip_at IS NULL") ?? 0),
    'kirim_akun'  => (int) (q_col("SELECT COUNT(*) FROM pendaftar WHERE tahap = 'BAYAR_TERVERIFIKASI' AND arsip_at IS NULL") ?? 0),
    'total'       => (int) (q_col('SELECT COUNT(*) FROM pendaftar WHERE arsip_at IS NULL') ?? 0),
];

/* ==================================================================
 * DAFTAR ITEM KESIAPAN — status: ok | hati | gagal
 * ================================================================== */
$items = [];
$items[] = [
    'grup' => 'Konfigurasi', 'status' => $appUrl === '' ? 'hati' : (stripos($appUrl, 'https://') === 0 ? 'ok' : 'hati'),
    'judul' => 'URL publik aplikasi (APP_URL)',
    'ket'   => $appUrl === ''
        ? 'APP_URL belum diisi di .env — tautan WhatsApp memakai host otomatis. Untuk produksi isi domain lengkap (https://...).'
        : (stripos($appUrl, 'https://') === 0
            ? 'Terisi & aman: ' . $appUrl
            : 'Terisi tetapi belum https:// — aktifkan SSL di hosting sebelum rilis.'),
];

$noRec = trim((string) ($P['no_rekening'] ?? ''));
$recBawaan = in_array($noRec, ['', '1234567890'], true);
$items[] = [
    'grup' => 'Konfigurasi', 'status' => $recBawaan ? 'hati' : 'ok',
    'judul' => 'Rekening bank penerima',
    'ket'   => $recBawaan
        ? 'Nomor rekening masih bawaan contoh — ubah di Pengaturan Sistem sebelum publik.'
        : $P['nama_bank'] . ' • ' . $noRec . ' a.n. ' . ($P['atas_nama_rekening'] ?? '-'),
];

$waPanitia = preg_replace('/\D+/', '', (string) ($P['kontak_wa_panitia'] ?? ''));
$waBawaan = ($waPanitia === '' || $waPanitia === '628123456789');
$items[] = [
    'grup' => 'Konfigurasi', 'status' => $waBawaan ? 'hati' : 'ok',
    'judul' => 'Nomor WA panitia',
    'ket'   => $waBawaan
        ? 'Masih nomor contoh — ganti dengan nomor panitia asli di Pengaturan Sistem.'
        : 'Terisi: ' . ($P['kontak_wa_panitia'] ?? '-'),
];

$tglOk = !empty($P['tanggal_buka_ppdb']) && !empty($P['tanggal_tutup_ppdb']);
$skOk  = trim((string) ($P['syarat_ketentuan'] ?? '')) !== '';
$items[] = [
    'grup' => 'Konfigurasi', 'status' => ($tglOk && $skOk) ? 'ok' : 'hati',
    'judul' => 'Jadwal PPDB & Syarat Ketentuan',
    'ket'   => ($tglOk && $skOk)
        ? 'Buka ' . date('d/m/Y', strtotime((string) $P['tanggal_buka_ppdb']))
            . ' — tutup ' . date('d/m/Y', strtotime((string) $P['tanggal_tutup_ppdb']))
            . ', S&K versi ' . (int) ($P['syarat_versi'] ?? 1) . '.'
        : 'Tanggal buka/tutup PPDB atau teks S&K belum lengkap — lengkapi di Pengaturan Sistem.',
];

$items[] = [
    'grup' => 'Keamanan', 'status' => $stafKunci === 0 ? 'ok' : 'hati',
    'judul' => 'Akun staf sudah memiliki password',
    'ket'   => $stafKunci === 0
        ? 'Seluruh akun operator/bendahara/kepsek sudah di-set.'
        : $stafKunci . ' akun staf masih terkunci (!LOCKED) — jalankan: php tools/set-password.php &lt;username&gt;',
];

$items[] = [
    'grup' => 'Keamanan', 'status' => $hashLemah === 0 ? 'ok' : 'gagal',
    'judul' => 'Hash password (bcrypt)',
    'ket'   => $hashLemah === 0
        ? 'Semua akun memakai bcrypt/argon2.'
        : $hashLemah . ' akun memakai hash lemah — reset segera lewat php tools/set-password.php.',
];

$items[] = [
    'grup' => 'Keamanan', 'status' => $migrasiOk ? 'ok' : 'gagal',
    'judul' => 'Skema & migrasi terbaru',
    'ket'   => $migrasiOk
        ? 'Kolom Fase 11 (pay_token_hash) ada — migrasi terakhir terjalankan.'
        : 'Kolom pay_token_hash tidak ditemukan — jalankan berurutan file di database/migrations/.',
];

/* Proxy (Fase 12 C3): header terbawa tapi tidak ada proxy tepercaya = semua
 * pengunjung terkunci rate-limit pada satu IP. */
$adaHeaderProxy = isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_CF_CONNECTING_IP']);
$adaTrusted = trim((string) env_val('TRUSTED_PROXIES')) !== '';
$items[] = [
    'grup' => 'Keamanan', 'status' => ($adaHeaderProxy && !$adaTrusted) ? 'hati' : 'ok',
    'judul' => 'Proxy tepercaya (TRUSTED_PROXIES)',
    'ket'   => ($adaHeaderProxy && !$adaTrusted)
        ? 'Request membawa X-Forwarded-For / CF-Connecting-IP tetapi TRUSTED_PROXIES kosong di .env — semua pengunjung di belakang proxy terlihat satu IP dan saling mengunci rate limit. Isi daftar CIDR proxy di .env (Cloudflare: salin dari halaman resmi Cloudflare).'
        : ($adaTrusted
            ? 'Daftar proxy tepercaya terisi — header hanya dipercaya dari anggotanya.'
            : 'Tidak ada header proxy terbawa & TRUSTED_PROXIES kosong — perilaku aman default.'),
];

$metode = [];
if (!empty($P['bayar_transfer_aktif'])) { $metode[] = 'Transfer bank'; }
if (!empty($P['bayar_gateway_aktif']))  { $metode[] = 'Gateway online'; }
if (!empty($P['bayar_qris_aktif']))     { $metode[] = 'QRIS'; }
$items[] = [
    'grup' => 'Kanal', 'status' => $metode ? 'ok' : 'gagal',
    'judul' => 'Metode pembayaran aktif',
    'ket'   => $metode
        ? 'Aktif: ' . implode(', ', $metode) . '.'
        : 'Tidak ada metode pembayaran aktif — minimal aktifkan satu di Pengaturan Sistem.',
];

$gatewayAktif = !empty($P['bayar_gateway_aktif']);
$keyAda = trim((string) ($P['midtrans_server_key'] ?? '')) !== '';
$items[] = [
    'grup' => 'Kanal', 'status' => (!$gatewayAktif || $keyAda) ? 'ok' : 'gagal',
    'judul' => 'Gateway pembayaran (Midtrans)',
    'ket'   => $gatewayAktif
        ? ($keyAda
            ? 'Gateway aktif & kunci API terisi (mode ' . (!empty($P['midtrans_sandbox']) ? 'sandbox' : 'production') . ').'
            : 'Gateway AKTIF tetapi kunci API belum diisi — bayar online akan gagal.')
        : 'Nonaktif (boleh) — pendaftar memakai transfer manual/QRIS.',
];

/* [PERBAIKAN] Semula hanya membaca kolom DB ($P['fonnte_token']), padahal
 * urutan baca aplikasi adalah .env (FONNTE_TOKEN) lalu fallback DB. Akibatnya
 * token yang hanya diisi di .env dianggap "belum diisi" padahal gateway
 * sebenarnya jalan. Kini memakai fonnte_token() — sumber yang sama dengan
 * wa_kirim(). */
$fonnteAda = fonnte_token() !== null;
$items[] = [
    'grup' => 'Kanal', 'status' => $fonnteAda ? 'ok' : 'hati',
    'judul' => 'Gateway WhatsApp (Fonnte)',
    'ket'   => $fonnteAda
        ? 'Token Fonnte terisi — kirim kredensial & notifikasi WA siap.'
        : 'Token Fonnte belum diisi — kirim WA kredensial/notifikasi tidak berjalan. Isi di Pengaturan Sistem → WhatsApp Gateway.',
];

$izinDetail = $izinOk
    ? 'storage/dokumen & storage/backup bisa ditulis.'
    : 'Tidak bisa ditulis: ' . implode(', ', $izinMasalah) . ' — perbaiki izin folder di server.';
$items[] = [
    'grup' => 'Sistem', 'status' => $izinOk ? 'ok' : 'gagal',
    'judul' => 'Izin tulis folder data',
    'ket'   => $izinDetail,
];

/* Lokasi storage (Fase 12 C6): idealnya DI LUAR web root. */
$akarWeb = realpath(dirname(__DIR__, 2));
$absStorage = realpath(STORAGE_DIR);
$storageDiDalam = $absStorage !== false && $akarWeb !== false
    && strpos($absStorage . '/', $akarWeb . '/') === 0;
$items[] = [
    'grup' => 'Sistem', 'status' => $storageDiDalam ? 'hati' : 'ok',
    'judul' => 'Lokasi folder storage (di luar web root)',
    'ket'   => $storageDiDalam
        ? 'Folder storage masih berada DI DALAM root web — pengguna internet bisa mencoba mengakses isunya langsung. Di produksi: pindahkan folder ke luar public_html, lalu setel STORAGE_DIR=<path-absolut> di .env (lihat docs/DEPLOY.md).'
        : 'Folder storage berada di luar root web (STORAGE_DIR: ' . STORAGE_DIR . ') — unggahan & cadangan tidak terekspos langsung ke web.',
];

$cadStatus = $cadanganJam === null ? 'hati' : ($cadanganJam <= 48 ? 'ok' : 'hati');
$items[] = [
    'grup' => 'Sistem', 'status' => $cadStatus,
    'judul' => 'Cadangan database terakhir',
    'ket'   => $cadanganJam === null
        ? 'Belum pernah ada cadangan — jalankan: php tools/backup-db.php (jadwalkan harian, lihat docs/DEPLOY.md).'
        : ($cadanganJam <= 48
            ? 'Terakhir ' . round($cadanganJam, 1) . ' jam lalu — masih segar.'
            : 'Terakhir ' . round($cadanganJam, 1) . ' jam lalu — lebih dari 48 jam, jalankan ulang backup.'),
];

$waAktifNotif = !empty($P['notif_wa_aktif']) && trim((string) ($P['notif_wa_operator'] ?? '')) !== '';
$items[] = [
    'grup' => 'Kanal', 'status' => $waAktifNotif ? 'ok' : 'hati',
    'judul' => 'Notifikasi WA ke operator',
    'ket'   => $waAktifNotif
        ? 'Aktif — notifikasi masuk dikirim ke ' . $P['notif_wa_operator'] . '.'
        : 'Belum aktif — isi nomor WA operator & aktifkan di Pengaturan Sistem (opsional).',
];

/* Retensi berkas pribadi (Fase 12 E7). */
$retensiAktif = (int) ($P['retensi_aktif'] ?? 0) === 1;
$pendTuaBerkas = 0;
try {
    $pendTuaBerkas = (int) (q_col(
        "SELECT COUNT(DISTINCT p.id)
           FROM pendaftar p
           JOIN dokumen d ON d.pendaftar_id = p.id
                        AND d.file_url IS NOT NULL AND d.file_url <> ''
          WHERE p.created_at <= (NOW() - INTERVAL 12 MONTH)"
    ) ?? 0);
} catch (Throwable $e) {
    $pendTuaBerkas = -1;
}
$retensiPerlu = !$retensiAktif && $pendTuaBerkas > 0;
$items[] = [
    'grup' => 'Sistem', 'status' => $retensiPerlu ? 'hati' : 'ok',
    'judul' => 'Retensi berkas pribadi',
    'ket'   => $retensiPerlu
        ? 'Retensi nonaktif tetapi ada ' . $pendTuaBerkas . ' pendaftar >12 bulan yang berkas pribadinya masih tersimpan — tetapkan & aktifkan kebijakan retensi di menu Retensi Data (lihat docs/KEBIJAKAN-DATA.md).'
        : ($retensiAktif
            ? 'Retensi aktif — eksekusi berkala lewat tools/retensi.php (jadwalkan cron bulanan).'
            : 'Retensi nonaktif dan belum ada pendaftar >12 bulan menyimpan berkas — belum mendesak.'),
];

/* Alat darurat set-password (Fase 13 butir 1) — harus sudah lenyap sebelum publikasi. */
$penandaSpw = storage_path('.izinkan-set-password');
$toolSpw    = dirname(__DIR__, 2) . '/tools/set-password-web.php';
$spwSisa    = (is_file($penandaSpw) ? 1 : 0) + (is_file($toolSpw) ? 1 : 0);
$items[] = [
    'grup' => 'Sistem', 'status' => $spwSisa > 0 ? 'gagal' : 'ok',
    'judul' => 'Alat set-password web dihapus',
    'ket'   => $spwSisa > 0
        ? 'Masih ada ' . $spwSisa . ' dari 2 berkas aktif (penanda storage/.izinkan-set-password & tools/set-password-web.php) — siapa pun yang menemukan URL-nya bisa memakainya selama kode instalasi belum diganti. HAPUS keduanya via File Manager sebelum domain dipublikasikan.'
        : 'Penanda & alat web sudah tidak ada (hanya alat CLI set-password.php yang tetap ada).',
];

/* Skor. */
$totalItem  = count($items);
$jumlahOk   = count(array_filter($items, function ($i) {
    return $i['status'] === 'ok';
}));
$jumlahGagal = count(array_filter($items, function ($i) {
    return $i['status'] === 'gagal';
}));
$jumlahHati = $totalItem - $jumlahOk - $jumlahGagal;
$persen = $totalItem > 0 ? (int) round($jumlahOk / $totalItem * 100) : 0;
$statusUmum = $jumlahGagal > 0 ? 'BELUM SIAP' : ($jumlahHati > 0 ? 'SIAP DENGAN CATATAN' : 'SIAP');
$statusTone = $jumlahGagal > 0 ? 'red' : ($jumlahHati > 0 ? 'amber' : 'green');

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'kesiapan';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik' => null, 'SPMB' => null, 'Kesiapan Rilis' => null];
$pageActions = '
  <a href="' . url('app/operator/panduan.php') . '" class="btn btn-outline hidden md:inline-flex">' . icon('support_agent') . ' Panduan &amp; SOP</a>
  <a href="' . url('app/operator/pengaturan.php') . '" class="btn btn-primary hidden md:inline-flex">' . icon('settings') . ' Buka Pengaturan</a>
';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1440px]">

    <!-- ====================== JUDUL ====================== -->
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-<?= $statusTone === 'green' ? 'green' : ($statusTone === 'amber' ? 'amber' : 'red') ?>"><?= dot($statusTone) ?> Status: <?= e($statusUmum) ?></span>
          <span class="badge badge-gray"><?= e($P['gelombang']) ?> • <?= e($P['tahun_ajaran']) ?></span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Kesiapan Rilis &amp; Operasional</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Checklist <?= $totalItem ?> item: konfigurasi, keamanan akun, kanal pembayaran &amp; WhatsApp, skema database, izin, dan cadangan data.
          Halaman ini <strong>hanya membaca</strong> — tidak mengubah apa pun.
        </p>
      </div>
    </div>

    <!-- ====================== SKOR ====================== -->
    <section class="mt-6 grid gap-4 lg:grid-cols-3">
      <article class="card p-6 lg:col-span-1">
        <p class="text-[13px] font-medium text-ink-500">Skor Kesiapan</p>
        <p class="mt-2 text-5xl font-extrabold tracking-tight <?= $jumlahGagal > 0 ? 'text-[#991B1B]' : ($jumlahHati > 0 ? 'text-[#92400E]' : 'text-[#065F46]') ?>">
          <?= $persen ?><span class="text-2xl font-bold">%</span>
        </p>
        <div class="mt-4 h-2 rounded-full bg-canvas overflow-hidden">
          <div class="h-full rounded-full <?= $jumlahGagal > 0 ? 'bg-red-500' : ($jumlahHati > 0 ? 'bg-amber-500' : 'bg-emerald-500') ?>" style="width: <?= $persen ?>%"></div>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
          <div class="rounded-xl bg-[#ECFDF5] py-2.5">
            <p class="text-lg font-extrabold text-[#065F46]"><?= $jumlahOk ?></p>
            <p class="text-[11px] font-medium text-[#065F46]/70">Siap</p>
          </div>
          <div class="rounded-xl bg-[#FFFBEB] py-2.5">
            <p class="text-lg font-extrabold text-[#92400E]"><?= $jumlahHati ?></p>
            <p class="text-[11px] font-medium text-[#92400E]/70">Perhatian</p>
          </div>
          <div class="rounded-xl bg-[#FEF2F2] py-2.5">
            <p class="text-lg font-extrabold text-[#991B1B]"><?= $jumlahGagal ?></p>
            <p class="text-[11px] font-medium text-[#991B1B]/70">Gagal</p>
          </div>
        </div>
        <p class="mt-4 text-xs text-ink-500 leading-relaxed">
          Pemeriksaan teknis lanjutan (versi PHP, ekstensi, php.ini) lewat terminal:
          <code class="font-mono text-[11px] bg-canvas px-1.5 py-0.5 rounded border border-line">php tools/cek-proyek.php</code>
        </p>
      </article>

      <!-- ANTREAN OPERASIONAL -->
      <article class="card p-6 lg:col-span-2">
        <div class="flex items-center justify-between">
          <p class="text-[13px] font-medium text-ink-500">Antrean Operasional</p>
          <span class="text-xs text-ink-400">Total <?= (int) $antre['total'] ?> pendaftar</span>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
          <a href="<?= url('app/operator/berkas.php') ?>" class="group rounded-2xl border border-line p-4 flex items-start justify-between hover:border-primary transition">
            <div>
              <p class="text-sm font-semibold text-ink-900 group-hover:text-primary">Berkas menunggu verifikasi</p>
              <p class="mt-1 text-xs text-ink-500">Operator membuka Verifikasi Berkas</p>
            </div>
            <span class="text-2xl font-extrabold <?= $antre['berkas'] > 0 ? 'text-[#EA580C]' : 'text-ink-400' ?>"><?= (int) $antre['berkas'] ?></span>
          </a>
          <a href="<?= url('app/operator/berkas.php') ?>" class="group rounded-2xl border border-line p-4 flex items-start justify-between hover:border-primary transition">
            <div>
              <p class="text-sm font-semibold text-ink-900 group-hover:text-primary">Revisi berkas (siswa)</p>
              <p class="mt-1 text-xs text-ink-500">Menunggu unggah ulang oleh siswa</p>
            </div>
            <span class="text-2xl font-extrabold <?= $antre['revisi'] > 0 ? 'text-ink-600' : 'text-ink-400' ?>"><?= (int) $antre['revisi'] ?></span>
          </a>
          <div class="rounded-2xl border border-line p-4 flex items-start justify-between">
            <div>
              <p class="text-sm font-semibold text-ink-900">Bukti bayar menunggu verifikasi</p>
              <p class="mt-1 text-xs text-ink-500">Diverifikasi bendahara</p>
            </div>
            <span class="text-2xl font-extrabold <?= $antre['bayar'] > 0 ? 'text-[#EA580C]' : 'text-ink-400' ?>"><?= (int) $antre['bayar'] ?></span>
          </div>
          <div class="rounded-2xl border border-line p-4 flex items-start justify-between">
            <div>
              <p class="text-sm font-semibold text-ink-900">Siap dikirim akun siswa</p>
              <p class="mt-1 text-xs text-ink-500">Bendahara: Verifikasi &amp; Kirim Akun</p>
            </div>
            <span class="text-2xl font-extrabold <?= $antre['kirim_akun'] > 0 ? 'text-primary' : 'text-ink-400' ?>"><?= (int) $antre['kirim_akun'] ?></span>
          </div>
        </div>
      </article>
    </section>

    <!-- ====================== CHECKLIST ====================== -->
    <section class="mt-6">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-bold text-ink-900">Daftar Periksa</h2>
        <span class="text-xs text-ink-400"><?= $totalItem ?> item • diperbarui otomatis saat halaman dibuka</span>
      </div>

      <?php
      $grupTampil = [];
      foreach ($items as $it) {
          $grupTampil[$it['grup']][] = $it;
      }
      foreach ($grupTampil as $grup => $daftar): ?>
        <p class="mt-4 mb-2 text-[12px] font-semibold uppercase tracking-wider text-ink-400"><?= e($grup) ?></p>
        <div class="grid gap-3 md:grid-cols-2">
          <?php foreach ($daftar as $it):
              $tone = $it['status'] === 'ok' ? 'green' : ($it['status'] === 'hati' ? 'amber' : 'red');
              $label = $it['status'] === 'ok' ? 'Siap' : ($it['status'] === 'hati' ? 'Perhatian' : 'Gagal');
          ?>
            <div class="card p-4 flex items-start gap-3">
              <span class="mt-0.5 inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[11px] font-semibold shrink-0
                <?= $tone === 'green' ? 'bg-[#ECFDF5] text-[#065F46] border-[#A7F3D0]' : ($tone === 'amber' ? 'bg-[#FFFBEB] text-[#92400E] border-[#FDE68A]' : 'bg-[#FEF2F2] text-[#991B1B] border-[#FECACA]') ?>">
                <?= dot($tone) ?> <?= $label ?>
              </span>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-ink-900"><?= $it['judul'] ?></p>
                <p class="mt-1 text-[13px] leading-relaxed text-ink-500"><?= $it['ket'] ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- ====================== CATATAN ====================== -->
    <section class="mt-6 card p-5 bg-canvas">
      <div class="flex items-start gap-3">
        <span class="icon-box bg-[#EFF6FF] text-[#1E40AF] shrink-0"><?= icon('info', 'text-[20px]') ?></span>
        <div class="text-[13px] leading-relaxed text-ink-500 space-y-1.5">
          <p><strong class="text-ink-900">Sebelum rilis publik:</strong> semua item minimal berstatus <em>Perhatian</em> (tanpa <em>Gagal</em>), lalu jalankan
            <code class="font-mono text-[11px] bg-white px-1.5 py-0.5 rounded border border-line">php tools/cek-proyek.php</code> dan uji manual
            mengikuti <code class="font-mono text-[11px] bg-white px-1.5 py-0.5 rounded border border-line">docs/UJI-MANUAL.md</code>.</p>
          <p>Panduan lengkap (HTTPS, .env, jadwal cadangan, rilis) ada di <a class="text-primary underline" href="docs/DEPLOY.md" target="_blank" rel="noopener">docs/DEPLOY.md</a>,
            ringkasan alur di <a class="text-primary underline" href="app/operator/panduan.php">Panduan &amp; SOP</a>.</p>
        </div>
      </div>
    </section>

  </main>
</div>
