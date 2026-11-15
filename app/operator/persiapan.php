<?php
/**
 * Pusat Persiapan Tahun Ajaran (operator).
 *
 * Satu halaman untuk pekerjaan antar-musim TANPA CLI/koding:
 *   - saklar manual buka/tutup pendaftaran publik,
 *   - ringkasan musim (pendaftar, antrean, rombel, cadangan),
 *   - arsip massal pendaftar musim lama (dengan cadangan otomatis),
 *   - gandakan rombel ke tahun ajaran baru,
 *   - cadangan database sekali klik,
 *   - checklist "siap buka SPMB".
 */
require_once __DIR__ . '/../../includes/config.php';

require_role(['operator']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title = 'Persiapan Tahun Ajaran';
$desc  = 'Pusat persiapan pergantian tahun ajaran SPMB: saklar pendaftaran, arsip musim lama, rombel baru, cadangan, dan checklist.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$HALAMAN = url('app/operator/persiapan.php');

/* ==================================================================
 * Proses (POST) — sebelum output HTML
 * ================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');

    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        header('Location: ' . $HALAMAN);
        exit;
    }

    if ($aksi === 'saklar') {
        if (!rate_limit_cek('persiapan_saklar', 30, 60)) {
            flash('error', 'Terlalu banyak perubahan. Coba lagi beberapa menit lagi.');
            header('Location: ' . $HALAMAN);
            exit;
        }
        rate_limit_catat('persiapan_saklar');
        $dibuka = (int) ($_POST['dibuka'] ?? 0) === 1 ? 1 : 0;
        q_exec('UPDATE pengaturan_sekolah SET pendaftaran_dibuka = ? WHERE id = 1', [$dibuka]);
        flash('sukses', $dibuka
            ? 'Pendaftaran DIBUKA — formulir publik kembali menerima pendaftaran.'
            : 'Pendaftaran DITUTUP — formulir publik tidak lagi menerima pendaftaran baru.');
        header('Location: ' . $HALAMAN);
        exit;
    }

    if ($aksi === 'backup') {
        if (!rate_limit_cek('persiapan_backup', 4, 60)) {
            flash('error', 'Batas cadangan per jam tercapai. Coba lagi nanti.');
            header('Location: ' . $HALAMAN);
            exit;
        }
        rate_limit_catat('persiapan_backup');
        $r = persiapan_backup_db();
        flash($r['ok'] ? 'sukses' : 'error', $r['ok']
            ? $r['pesan'] . ' (tabel: ' . (int) $r['tabel'] . ', ukuran: ' . round($r['ukuran'] / 1024, 1) . ' KB).'
            : $r['pesan']);
        header('Location: ' . $HALAMAN);
        exit;
    }

    if ($aksi === 'arsip_tahun') {
        if (!rate_limit_cek('persiapan_arsip', 5, 60)) {
            flash('error', 'Batas arsip massal per jam tercapai. Coba lagi nanti.');
            header('Location: ' . $HALAMAN);
            exit;
        }

        $tahun      = preg_replace('/\D+/', '', (string) ($_POST['tahun'] ?? ''));
        $alasan     = trim((string) ($_POST['alasan'] ?? ''));
        $konfirmasi = trim((string) ($_POST['konfirmasi'] ?? ''));

        if (strlen($tahun) !== 4 || (int) $tahun < 2000 || (int) $tahun > (int) date('Y')) {
            flash('error', 'Tahun arsip tidak valid.');
        } elseif ($konfirmasi !== 'ARSIP ' . $tahun) {
            flash('error', 'Konfirmasi tidak cocok — ketik ARSIP ' . $tahun . ' dengan persis.');
        } elseif (mb_strlen($alasan) < 5) {
            flash('error', 'Alasan pengarsipan wajib diisi (minimal 5 karakter).');
        } else {
            rate_limit_catat('persiapan_arsip');

            /* Cadangan wajib berhasil SEBELUM data disentuh. */
            if (!empty($_POST['dengan_cadangan'])) {
                $b = persiapan_backup_db();
                if (!$b['ok']) {
                    flash('error', 'Arsip dibatalkan — cadangan gagal: ' . $b['pesan']);
                    header('Location: ' . $HALAMAN . '?pratinjau=' . $tahun);
                    exit;
                }
            }

            $r = pendaftar_arsip_tahun($tahun, $alasan, $me['id'] ?? null);
            if ($r['ok']) {
                flash('sukses', $r['pesan'] . ' Akun portal yang terkait: ' . (int) $r['akun']
                    . ' dinonaktifkan. Data dan pembayaran tetap utuh — dapat dipulihkan per pendaftar dari Data Pendaftar.');
            } else {
                flash('error', $r['pesan']);
            }
        }
        header('Location: ' . $HALAMAN);
        exit;
    }

    if ($aksi === 'gandakan') {
        if (!rate_limit_cek('persiapan_gandakan', 10, 60)) {
            flash('error', 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.');
            header('Location: ' . $HALAMAN);
            exit;
        }
        rate_limit_catat('persiapan_gandakan');
        $rows = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];
        $r = kelas_gandakan($rows, !empty($_POST['nonaktifkan_lama']));
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        header('Location: ' . $HALAMAN);
        exit;
    }

    flash('error', 'Aksi tidak dikenal.');
    header('Location: ' . $HALAMAN);
    exit;
}

/* ==================================================================
 * Data tampilan
 * ================================================================== */
$R         = persiapan_ringkasan();
$musim     = persiapan_tahun_musim();
$kodeMusim = persiapan_tahun_kode_musim();
$akhiranMusim = substr((string) $musim, -2);
$rombel    = persiapan_rombel_aktif();

$arsipPerTahun = q_all(
    "SELECT SUBSTRING(no_pendaftaran, 5, 4) AS tahun, COUNT(*) AS jumlah
       FROM pendaftar
      WHERE arsip_at IS NOT NULL AND no_pendaftaran REGEXP '^REG-[0-9]{4}-'
      GROUP BY tahun
      ORDER BY tahun"
) ?: [];
$petaArsip = [];
foreach ($arsipPerTahun as $a) {
    $petaArsip[(string) $a['tahun']] = (int) $a['jumlah'];
}

$tahunGabung = [];
foreach ($R['per_tahun'] as $t) {
    $y = (string) $t['tahun'];
    $tahunGabung[$y] = ['aktif' => (int) $t['jumlah'], 'arsip' => $petaArsip[$y] ?? 0];
}
foreach ($petaArsip as $y => $n) {
    if (!isset($tahunGabung[$y])) {
        $tahunGabung[$y] = ['aktif' => 0, 'arsip' => $n];
    }
}
ksort($tahunGabung);

$lamaAktif = 0;
$lamaTahun = [];
foreach ($tahunGabung as $y => $t) {
    if ((int) $y < $kodeMusim && $t['aktif'] > 0) {
        $lamaAktif += $t['aktif'];
        $lamaTahun[] = $y;
    }
}

$pratinjau = preg_replace('/\D+/', '', (string) ($_GET['pratinjau'] ?? ''));
$kandidat  = null;
if (strlen($pratinjau) === 4) {
    $kandidat = pendaftar_arsip_tahun_kandidat($pratinjau);
}

$backup     = $R['backup'];
$backupJam  = $backup ? (time() - $backup['waktu']) / 3600 : null;
$antreanLama = $R['menunggu_verif'] + $R['menunggu_bayar'] + $R['menunggu_dok'];

$tglOk = !empty($PENGATURAN['tahun_ajaran'])
    && !empty($PENGATURAN['gelombang'])
    && !empty($PENGATURAN['tanggal_buka_ppdb'])
    && !empty($PENGATURAN['tanggal_tutup_ppdb'])
    && strtotime((string) $PENGATURAN['tanggal_buka_ppdb']) <= strtotime((string) $PENGATURAN['tanggal_tutup_ppdb']);

$syaratOk = trim((string) ($PENGATURAN['syarat_ketentuan'] ?? '')) !== '';
$fonnteOk = fonnte_token() !== null && trim((string) ($PENGATURAN['kontak_wa_panitia'] ?? '')) !== '';

$items = [
    [
        'status' => pendaftaran_terbuka() ? 'ok' : 'hati',
        'judul'  => 'Saklar pendaftaran',
        'ket'    => pendaftaran_terbuka()
            ? 'Pendaftaran sedang DIBUKA. Tutup kembali bila musim ini belum siap menerima pendaftar.'
            : 'Pendaftaran sedang DITUTUP. Buka saklar ini sebagai langkah terakhir setelah checklist lain hijau.',
    ],
    [
        'status' => $tglOk ? 'ok' : 'hati',
        'judul'  => 'Tahun ajaran, gelombang & tanggal',
        'ket'    => $tglOk
            ? 'Tahun ajaran ' . e((string) $PENGATURAN['tahun_ajaran']) . ', ' . e((string) $PENGATURAN['gelombang'])
                . ', ' . e(bulan((string) $PENGATURAN['tanggal_buka_ppdb'])) . ' – ' . e(bulan((string) $PENGATURAN['tanggal_tutup_ppdb'])) . '.'
            : 'Lengkapi tahun ajaran, nama gelombang, tanggal buka, dan tanggal tutup di Pengaturan Sistem.',
    ],
    [
        'status' => $lamaAktif === 0 ? 'ok' : 'hati',
        'judul'  => 'Pendaftar musim lama sudah diarsipkan',
        'ket'    => $lamaAktif === 0
            ? 'Tidak ada pendaftar aktif dari tahun sebelum musim ' . e((string) $PENGATURAN['tahun_ajaran']) . '.'
            : $lamaAktif . ' pendaftar aktif masih berkode tahun ' . e(implode(', ', $lamaTahun))
                . '. Gunakan kartu Arsip Musim Lama di bawah — data tidak dihapus, hanya diarsipkan.',
    ],
    [
        'status' => $antreanLama === 0 ? 'ok' : 'hati',
        'judul'  => 'Antrean verifikasi & pembayaran selesai',
        'ket'    => $antreanLama === 0
            ? 'Tidak ada berkas, data, atau bukti bayar yang menunggu keputusan panitia.'
            : $R['menunggu_verif'] . ' data, ' . $R['menunggu_dok'] . ' berkas, dan '
                . $R['menunggu_bayar'] . ' bukti bayar masih menunggu. Selesaikan sebelum menutup musim.',
    ],
    [
        'status' => ($R['kelas_aktif'] > 0 && $R['kuota'] > 0) ? 'ok' : 'hati',
        'judul'  => 'Rombel & kuota tahun baru',
        'ket'    => $R['kelas_aktif'] > 0
            ? $R['kelas_aktif'] . ' rombel aktif, total kuota ' . $R['kuota'] . ' kursi, '
                . $R['terisi'] . ' penempatan tercatat.'
            : 'Belum ada rombel aktif. Gandakan rombel lama atau tambah dari Manajemen Kelas.',
    ],
    [
        'status' => $syaratOk ? 'ok' : 'gagal',
        'judul'  => 'Syarat & Ketentuan pendaftaran',
        'ket'    => $syaratOk
            ? 'S&K terisi (versi ' . (int) ($PENGATURAN['syarat_versi'] ?? 1) . ') dan akan ditampilkan pada formulir pendaftaran.'
            : 'S&K kosong — formulir publik tidak bisa dirender dengan benar. Isi di Pengaturan Sistem.',
    ],
    [
        'status' => $fonnteOk ? 'ok' : 'hati',
        'judul'  => 'WhatsApp gateway & nomor panitia',
        'ket'    => $fonnteOk
            ? 'Token Fonnte dan nomor WhatsApp panitia terisi — instruksi & pengumuman bisa dikirim.'
            : 'Token Fonnte atau nomor WhatsApp panitia belum diisi. Periksa di Pengaturan Sistem / Kesiapan Rilis.',
    ],
    [
        'status' => ($backupJam !== null && $backupJam <= 48) ? 'ok' : 'hati',
        'judul'  => 'Cadangan database terbaru',
        'ket'    => $backup
            ? 'Terakhir ' . round($backupJam, 1) . ' jam lalu (' . e($backup['file']) . ', '
                . round($backup['ukuran'] / 1024, 1) . ' KB).'
            : 'Belum ada cadangan. Buat cadangan sebelum mengarsipkan atau mengubah data.',
    ],
];

$jumlahHati = count(array_filter($items, static fn($i) => $i['status'] === 'hati'));
$jumlahGagal = count(array_filter($items, static fn($i) => $i['status'] === 'gagal'));

$csrf = csrf_token();

/* ------------------------------------------------------------------ */
require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'persiapan';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik', 'Persiapan Tahun Ajaran'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <!-- ====================== JUDUL ====================== -->
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge <?= $jumlahGagal ? 'badge-red' : ($jumlahHati ? 'badge-amber' : 'badge-green') ?>">
            <?= dot($jumlahGagal ? 'red' : ($jumlahHati ? 'amber' : 'green')) ?>
            <?= $jumlahGagal ? 'Ada yang harus diperbaiki' : ($jumlahHati ? $jumlahHati . ' hal perlu perhatian' : 'Siap buka SPMB') ?>
          </span>
          <span class="badge badge-gray"><?= e((string) $PENGATURAN['tahun_ajaran']) ?> · <?= e((string) $PENGATURAN['gelombang']) ?></span>
          <span class="badge <?= pendaftaran_terbuka() ? 'badge-green' : 'badge-gray' ?>">
            <?= pendaftaran_terbuka() ? 'Pendaftaran dibuka' : 'Pendaftaran ditutup' ?>
          </span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Persiapan Tahun Ajaran</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Semua pekerjaan antar-musim dalam satu halaman: tutup musim lama, siapkan rombel baru,
          buat cadangan, lalu buka pendaftaran. Tidak perlu menyentuh kode atau terminal.
        </p>
      </div>
    </div>

    <!-- ====================== SAKLAR ====================== -->
    <section class="mt-6 card p-5 sm:p-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
          <span class="icon-box <?= pendaftaran_terbuka() ? 'bg-[#ECFDF5] text-[#065F46]' : 'bg-[#F1F5F9] text-ink-500' ?> shrink-0">
            <?= icon(pendaftaran_terbuka() ? 'lock_open' : 'lock', 'text-[22px]') ?>
          </span>
          <div>
            <h2 class="text-base font-semibold text-ink-900">Saklar pendaftaran publik</h2>
            <p class="mt-1 text-[13px] text-ink-500 max-w-2xl">
              Menentukan apakah formulir pendaftaran di beranda menerima isian baru.
              Tanggal buka/tutup di CMS tetap tampil sebagai informasi jadwal, tetapi tidak lagi
              menutup formulir secara otomatis — gunakan saklar ini.
            </p>
          </div>
        </div>
        <form method="post" action="<?= e($HALAMAN) ?>" autocomplete="off" class="shrink-0"
              onsubmit="return confirm('<?= pendaftaran_terbuka() ? 'Tutup pendaftaran? Pendaftar baru tidak bisa mengisi formulir.' : 'Buka pendaftaran sekarang? Formulir publik langsung menerima pendaftaran.' ?>');">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="aksi" value="saklar">
          <input type="hidden" name="dibuka" value="<?= pendaftaran_terbuka() ? '0' : '1' ?>">
          <button type="submit" class="btn <?= pendaftaran_terbuka() ? 'btn-danger' : 'btn-primary' ?>">
            <?= icon(pendaftaran_terbuka() ? 'lock' : 'lock_open') ?>
            <?= pendaftaran_terbuka() ? 'Tutup Pendaftaran' : 'Buka Pendaftaran' ?>
          </button>
        </form>
      </div>
    </section>

    <!-- ====================== RINGKASAN ====================== -->
    <section class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Pendaftar aktif</p>
        <p class="mt-1 text-2xl font-bold text-ink-900"><?= (int) $R['aktif'] ?></p>
        <p class="mt-1 text-[12px] text-ink-500"><?= (int) $R['arsip'] ?> sudah diarsipkan</p>
      </div>
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Antrean panitia</p>
        <p class="mt-1 text-2xl font-bold <?= $antreanLama ? 'text-[#B45309]' : 'text-ink-900' ?>"><?= $antreanLama ?></p>
        <p class="mt-1 text-[12px] text-ink-500">data · berkas · bukti bayar</p>
      </div>
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Rombel aktif</p>
        <p class="mt-1 text-2xl font-bold text-ink-900"><?= (int) $R['kelas_aktif'] ?></p>
        <p class="mt-1 text-[12px] text-ink-500">kuota <?= (int) $R['kuota'] ?> · terisi <?= (int) $R['terisi'] ?></p>
      </div>
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Cadangan terakhir</p>
        <p class="mt-1 text-2xl font-bold <?= ($backupJam !== null && $backupJam <= 48) ? 'text-ink-900' : 'text-[#B45309]' ?>">
          <?= $backupJam === null ? '—' : round($backupJam, 1) . ' jam' ?>
        </p>
        <p class="mt-1 text-[12px] text-ink-500 truncate" title="<?= e((string) ($backup['file'] ?? '')) ?>">
          <?= $backup ? e($backup['file']) : 'belum pernah dibuat' ?>
        </p>
      </div>
    </section>

    <!-- ====================== CHECKLIST ====================== -->
    <section class="mt-6">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-bold text-ink-900">Checklist siap buka SPMB</h2>
        <span class="text-xs text-ink-400">Diperbarui otomatis saat halaman dibuka</span>
      </div>
      <div class="grid gap-3 md:grid-cols-2">
        <?php foreach ($items as $it):
            $tone = $it['status'] === 'ok' ? 'green' : ($it['status'] === 'hati' ? 'amber' : 'red');
            $label = $it['status'] === 'ok' ? 'Siap' : ($it['status'] === 'hati' ? 'Perhatian' : 'Gagal');
        ?>
          <div class="card p-4 flex items-start gap-3">
            <span class="mt-0.5 inline-flex items-center gap-1.5 rounded-full border px-2 py-1 text-[11px] font-semibold shrink-0
              <?= $tone === 'green' ? 'bg-[#ECFDF5] text-[#065F46] border-[#A7F3D0]' : ($tone === 'amber' ? 'bg-[#FFFBEB] text-[#92400E] border-[#FDE68A]' : 'bg-[#FEF2F2] text-[#991B1B] border-[#FECACA]') ?>">
              <?= dot($tone) ?> <?= $label ?>
            </span>
            <div class="min-w-0">
              <p class="text-sm font-semibold text-ink-900"><?= e($it['judul']) ?></p>
              <p class="mt-1 text-[13px] leading-relaxed text-ink-500"><?= $it['ket'] ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ====================== ARSIP MUSIM LAMA ====================== -->
    <section id="arsip" class="mt-6 card p-5 sm:p-6 scroll-mt-24">
      <div class="flex items-start gap-3">
        <span class="icon-box bg-[#EFF6FF] text-[#1E40AF] shrink-0"><?= icon('inventory_2', 'text-[22px]') ?></span>
        <div class="min-w-0">
          <h2 class="text-base font-semibold text-ink-900">Arsip musim lama</h2>
          <p class="mt-1 text-[13px] text-ink-500 leading-relaxed max-w-3xl">
            Mengarsipkan <strong>tidak menghapus</strong> pendaftar, pembayaran, nilai, atau dokumen —
            hanya mengeluarkannya dari antrean & statistik musim berjalan dan menonaktifkan akun portalnya.
            Pendaftar tetap bisa dipulihkan satu per satu dari Data Pendaftar.
          </p>
        </div>
      </div>

      <?php if (!$tahunGabung): ?>
        <p class="mt-4 text-[13px] text-ink-500">Belum ada pendaftar tercatat.</p>
      <?php else: ?>
        <div class="mt-4 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-[12px] uppercase tracking-wide text-ink-500 border-b border-line">
                <th class="py-2 pr-3">Tahun kode</th>
                <th class="py-2 pr-3 text-right">Aktif</th>
                <th class="py-2 pr-3 text-right">Terarsip</th>
                <th class="py-2 pr-3">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tahunGabung as $y => $t): ?>
                <tr class="border-b border-line/60">
                  <td class="py-2.5 pr-3 font-mono">REG-<?= e((string) $y) ?>-…</td>
                  <td class="py-2.5 pr-3 text-right <?= $t['aktif'] ? 'font-semibold text-ink-900' : 'text-ink-400' ?>"><?= $t['aktif'] ?></td>
                  <td class="py-2.5 pr-3 text-right text-ink-500"><?= $t['arsip'] ?></td>
                  <td class="py-2.5 pr-3">
                    <?php if ($t['aktif'] > 0): ?>
                      <a href="<?= e($HALAMAN) ?>?pratinjau=<?= e((string) $y) ?>#arsip" class="text-primary font-medium underline">
                        Pratinjau &amp; arsipkan
                      </a>
                    <?php else: ?>
                      <span class="text-ink-400">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if ($kandidat !== null): ?>
        <div class="mt-5 rounded-2xl border border-line bg-canvas p-4">
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <h3 class="font-semibold text-ink-900">Pratinjau arsip <?= e($pratinjau) ?></h3>
            <span class="badge badge-amber"><?= count($kandidat) ?> pendaftar aktif</span>
          </div>
          <?php if ($kandidat): ?>
            <div class="mt-3 max-h-64 overflow-y-auto scroll-thin rounded-xl border border-line bg-white">
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-left text-[11px] uppercase tracking-wide text-ink-500 border-b border-line sticky top-0 bg-white">
                    <th class="py-2 px-3">No. Pendaftaran</th>
                    <th class="py-2 px-3">Nama</th>
                    <th class="py-2 px-3">Terdaftar</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($kandidat, 0, 100) as $c): ?>
                    <tr class="border-b border-line/50">
                      <td class="py-2 px-3 font-mono text-[12px]"><?= e((string) $c['no_pendaftaran']) ?></td>
                      <td class="py-2 px-3"><?= e((string) $c['nama_siswa']) ?></td>
                      <td class="py-2 px-3 text-ink-500"><?= e(substr((string) $c['created_at'], 0, 10)) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if (count($kandidat) > 100): ?>
              <p class="mt-2 text-[12px] text-ink-500">Menampilkan 100 pertama dari <?= count($kandidat) ?>.</p>
            <?php endif; ?>

            <form method="post" action="<?= e($HALAMAN) ?>" class="mt-4 space-y-3" autocomplete="off"
                  onsubmit="return confirm('Arsipkan <?= count($kandidat) ?> pendaftar tahun <?= e($pratinjau) ?>? Pastikan cadangan sudah dibuat.');">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="aksi" value="arsip_tahun">
              <input type="hidden" name="tahun" value="<?= e($pratinjau) ?>">
              <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                  <span class="text-[13px] font-medium text-ink-700">Alasan pengarsipan</span>
                  <input type="text" name="alasan" required minlength="5" maxlength="255"
                         value="Pergantian tahun ajaran menuju <?= e((string) $PENGATURAN['tahun_ajaran']) ?>"
                         class="mt-1.5 w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
                </label>
                <label class="block">
                  <span class="text-[13px] font-medium text-ink-700">Ketik konfirmasi <span class="font-mono">ARSIP <?= e($pratinjau) ?></span></span>
                  <input type="text" name="konfirmasi" required placeholder="ARSIP <?= e($pratinjau) ?>"
                         class="mt-1.5 w-full rounded-xl border border-line bg-white px-3 py-2 text-sm font-mono">
                </label>
              </div>
              <label class="flex items-center gap-2.5 text-sm text-ink-800">
                <input type="checkbox" name="dengan_cadangan" value="1" checked class="rounded border-line w-4 h-4">
                Buat cadangan database otomatis sebelum mengarsipkan (disarankan)
              </label>
              <button type="submit" class="btn btn-danger"><?= icon('inventory_2') ?> Arsipkan <?= count($kandidat) ?> pendaftar</button>
            </form>
          <?php else: ?>
            <p class="mt-3 text-[13px] text-ink-500">Tidak ada pendaftar aktif pada tahun ini.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>

    <!-- ====================== GANDAKAN ROMBEL ====================== -->
    <section class="mt-6 card p-5 sm:p-6">
      <div class="flex items-start gap-3">
        <span class="icon-box bg-[#ECFDF5] text-[#065F46] shrink-0"><?= icon('class', 'text-[22px]') ?></span>
        <div>
          <h2 class="text-base font-semibold text-ink-900">Gandakan rombel untuk tahun ajaran baru</h2>
          <p class="mt-1 text-[13px] text-ink-500 leading-relaxed max-w-3xl">
            Kode baru sudah terisi otomatis dari kode lama + tahun ajaran (contoh 71 → 71-<?= e($akhiranMusim) ?>);
            nama, wali, dan kuota ikut disalin. Rombel lama otomatis dinonaktifkan — cukup periksa lalu tekan
            <strong>Gandakan Semua</strong>. Kosongkan kode satu baris bila rombel itu tidak perlu dibuat,
            dan ubah kode bila sekolah memakai pola lain.
          </p>
        </div>
      </div>

      <?php if (!$rombel): ?>
        <p class="mt-4 text-[13px] text-ink-500">
          Belum ada rombel aktif. Tambahkan dulu di <a class="text-primary underline" href="<?= url('app/operator/kelas.php') ?>">Manajemen Kelas</a>.
        </p>
      <?php else: ?>
        <form method="post" action="<?= e($HALAMAN) ?>" class="mt-4" autocomplete="off"
              onsubmit="return confirm('Buat rombel baru dari kode yang terisi dan nonaktifkan rombel lama yang ikut digandakan?');">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="aksi" value="gandakan">
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-[12px] uppercase tracking-wide text-ink-500 border-b border-line">
                  <th class="py-2 pr-3">Rombel lama</th>
                  <th class="py-2 pr-3">Kode baru <span class="text-red-600">*</span></th>
                  <th class="py-2 pr-3">Nama rombel</th>
                  <th class="py-2 pr-3">Wali kelas</th>
                  <th class="py-2 pr-3 w-24">Kuota</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rombel as $i => $k): ?>
                  <tr class="border-b border-line/60 align-top">
                    <td class="py-3 pr-3">
                      <p class="font-mono font-semibold text-ink-900"><?= e((string) $k['kode']) ?></p>
                      <p class="text-[12px] text-ink-500"><?= e((string) $k['nama_kelas']) ?> · <?= (int) $k['terisi'] ?> terisi</p>
                      <input type="hidden" name="rows[<?= (int) $i ?>][dari_id]" value="<?= e((string) $k['id']) ?>">
                    </td>
                    <td class="py-3 pr-3">
                      <input type="text" name="rows[<?= (int) $i ?>][kode]" maxlength="10"
                             value="<?= e(mb_substr((string) $k['kode'], 0, 7) . '-' . $akhiranMusim) ?>"
                             class="w-24 rounded-xl border border-line bg-white px-3 py-2 text-sm font-mono uppercase">
                    </td>
                    <td class="py-3 pr-3">
                      <input type="text" name="rows[<?= (int) $i ?>][nama_kelas]" maxlength="100"
                             value="<?= e((string) $k['nama_kelas']) ?>"
                             class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
                    </td>
                    <td class="py-3 pr-3">
                      <input type="text" name="rows[<?= (int) $i ?>][wali_kelas]" maxlength="100"
                             value="<?= e((string) $k['wali_kelas']) ?>"
                             class="w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
                    </td>
                    <td class="py-3 pr-3">
                      <input type="number" name="rows[<?= (int) $i ?>][kuota]" min="1" max="500"
                             value="<?= (int) $k['kuota'] ?>"
                             class="w-24 rounded-xl border border-line bg-white px-3 py-2 text-sm">
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <label class="mt-4 flex items-center gap-2.5 text-sm text-ink-800">
            <input type="checkbox" name="nonaktifkan_lama" value="1" checked class="rounded border-line w-4 h-4">
            Nonaktifkan rombel lama yang ikut digandakan (riwayat penempatan tetap tersimpan)
          </label>
          <button type="submit" class="btn btn-primary mt-4"><?= icon('content_copy') ?> Gandakan Semua</button>
        </form>
      <?php endif; ?>
    </section>

    <!-- ====================== CADANGAN ====================== -->
    <section class="mt-6 card p-5 sm:p-6">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
          <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon('database', 'text-[22px]') ?></span>
          <div>
            <h2 class="text-base font-semibold text-ink-900">Cadangan database</h2>
            <p class="mt-1 text-[13px] text-ink-500 max-w-2xl">
              Cadangan dibuat langsung dari halaman ini (tanpa terminal) ke
              <span class="font-mono text-[12px]">storage/backup/db/</span>. Lakukan sebelum arsip massal
              atau perubahan besar. Simpan salinan berkasnya di luar server secara berkala.
            </p>
            <?php if ($backup): ?>
              <p class="mt-2 text-[12px] text-ink-500">
                Terakhir: <strong><?= e(date('d-m-Y H:i', $backup['waktu'])) ?></strong>
                · <?= e($backup['file']) ?> · <?= round($backup['ukuran'] / 1024, 1) ?> KB
              </p>
            <?php else: ?>
              <p class="mt-2 text-[12px] text-[#92400E]">Belum ada cadangan tersimpan.</p>
            <?php endif; ?>
          </div>
        </div>
        <form method="post" action="<?= e($HALAMAN) ?>" autocomplete="off" class="shrink-0">
          <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="aksi" value="backup">
          <button type="submit" class="btn btn-primary"><?= icon('backup') ?> Buat cadangan sekarang</button>
        </form>
      </div>
    </section>

    <!-- ====================== CATATAN ====================== -->
    <section class="mt-6 card p-5 bg-canvas">
      <div class="flex items-start gap-3">
        <span class="icon-box bg-[#EFF6FF] text-[#1E40AF] shrink-0"><?= icon('info', 'text-[20px]') ?></span>
        <div class="text-[13px] leading-relaxed text-ink-500 space-y-1.5">
          <p><strong class="text-ink-900">Urutan yang disarankan:</strong> selesaikan antrean &amp; ekspor laporan →
            buat cadangan → arsipkan musim lama → gandakan rombel → perbarui tahun ajaran, tanggal, dan S&amp;K →
            buka saklar pendaftaran.</p>
          <p>Tahun pada nomor pendaftaran mengikuti tahun kalender saat mendaftar, bukan tahun ajaran.
            Karena itu pendaftar musim 2027/2028 umumnya berkode <span class="font-mono">REG-2026-…</span>.</p>
          <p>Prosedur lengkap &amp; pemulihan: <a class="text-primary underline" href="<?= url('docs/DEPLOY.md') ?>" target="_blank" rel="noopener">docs/DEPLOY.md §3c</a>
            · <a class="text-primary underline" href="<?= url('app/operator/panduan.php') ?>">Panduan &amp; SOP</a>.</p>
        </div>
      </div>
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
