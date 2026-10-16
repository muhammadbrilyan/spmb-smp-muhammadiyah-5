<?php
/**
 * Fase 12 E2 — Retensi Data & Privasi (operator/admin).
 *
 * Menghapus BUKAN baris pendaftar, melainkan berkas pribadi fisik
 * (KK, akta, bukti transfer) di storage/dokumen beserta jejaknya di
 * tabel dokumen — setelah konfirmasi ganda (ketik no_pendaftaran).
 * Setiap eksekusi tercatat di retensi_log. Cron CLI: tools/retensi.php.
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

$title = 'Retensi Data & Privasi';
$desc  = 'Pengaturan & eksekusi retensi berkas pribadi pendaftar (hapus fisik + jejak dokumen, baris pendaftar tetap ada).';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* ==================================================================
 * Proses (POST) — sebelum output HTML
 * ================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $aksi = (string) ($_POST['aksi'] ?? '');

    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.');
        header('Location: ' . url('app/operator/retensi.php'));
        exit;
    }

    if ($aksi === 'simpan_pengaturan') {
        if (!rate_limit_cek('retensi_simpan', 30, 60)) {
            flash('error', 'Terlalu banyak permintaan. Coba lagi beberapa menit lagi.');
            header('Location: ' . url('app/operator/retensi.php'));
            exit;
        }
        $aktif = empty($_POST['retensi_aktif']) ? 0 : 1;
        $bulan = function ($k) {
            $v = trim((string) ($_POST[$k] ?? ''));
            if ($v === '') {
                return null;
            }
            if (!ctype_digit($v) || (int) $v < 1 || (int) $v > 240) {
                return 'INVALID';
            }
            return (int) $v;
        };
        $b1 = $bulan('retensi_bulan_tidak_lulus');
        $b2 = $bulan('retensi_bulan_batal');
        $b3 = $bulan('retensi_bulan_lulus_arsip');

        if ($b1 === 'INVALID' || $b2 === 'INVALID' || $b3 === 'INVALID') {
            flash('error', 'Jumlah bulan harus angka 1–240 atau dikosongkan.');
        } elseif ($aktif === 1 && $b1 === null && $b2 === null && $b3 === null) {
            flash('error', 'Retensi aktif tetapi belum ada satu pun batas bulan yang diisi.');
        } else {
            q_exec(
                'UPDATE pengaturan_sekolah SET retensi_aktif = ?,
                        retensi_bulan_tidak_lulus = ?, retensi_bulan_batal = ?,
                        retensi_bulan_lulus_arsip = ?
                  WHERE id = 1',
                [$aktif, $b1, $b2, $b3]
            );
            flash('sukses', 'Pengaturan retensi disimpan.');
        }
        header('Location: ' . url('app/operator/retensi.php'));
        exit;
    }

    if ($aksi === 'hapus_berkas') {
        $pendaftarId = (string) ($_POST['pendaftar_id'] ?? '');
        $konfirmasi  = trim((string) ($_POST['konfirmasi'] ?? ''));

        if (!rate_limit_cek('retensi_hapus', 5, 60, 'prtn:' . $pendaftarId)) {
            flash('error', 'Batas eksekusi retensi per pendaftar terlampaui (maks 5/jam). Coba lagi nanti.');
            header('Location: ' . url('app/operator/retensi.php'));
            exit;
        }

        $cek = q_all('SELECT no_pendaftaran FROM pendaftar WHERE id = ?', [$pendaftarId]);
        if (!$cek) {
            flash('error', 'Pendaftar tidak ditemukan.');
            header('Location: ' . url('app/operator/retensi.php'));
            exit;
        }
        $no = (string) $cek[0]['no_pendaftaran'];

        /* Konfirmasi ganda: ketik no_pendaftaran dengan persis. */
        if ($konfirmasi !== $no) {
            flash('error', 'Konfirmasi tidak cocok — ketik no. pendaftaran (' . e($no) . ') dengan persis.');
            header('Location: ' . url('app/operator/retensi.php'));
            exit;
        }

        rate_limit_catat('retensi_hapus', 'prtn:' . $pendaftarId);
        $hasil = retensi_hapus_berkas($pendaftarId, 'manual_operator', $me['id']);
        if ($hasil['ok']) {
            $pesan = 'Berkas pribadi ' . $no . ' dihapus: ' . $hasil['jumlah'] . ' berkas';
            if (!empty($hasil['gagal'])) {
                $pesan .= ' (' . $hasil['gagal'] . ' gagal/dilewati)';
            }
            if (isset($hasil['jejak']) && !$hasil['jejak']) {
                $pesan .= ' — PERINGATAN: jejak di retensi_log GAGAL tercatat, periksa error_log.';
            }
            flash('sukses', $pesan . '.');
        } else {
            flash('error', $hasil['pesan'] ?? 'Gagal menjalankan retensi.');
        }
        header('Location: ' . url('app/operator/retensi.php'));
        exit;
    }

    flash('error', 'Aksi tidak dikenal.');
    header('Location: ' . url('app/operator/retensi.php'));
    exit;
}

/* ==================================================================
 * Data tampilan
 * ================================================================== */
$P = retensi_pengaturan();

$kategori = [
    'tidak_lulus' => [
        'judul'  => 'Tidak lulus seleksi',
        'bulan'  => $P['bulan_tidak_lulus'],
        'ket'    => 'Pendaftar berstatus TIDAK_LULUS yang pendaftarannya lebih tua dari batas bulan.',
    ],
    'batal' => [
        'judul'  => 'Pendaftaran ditolak/dibatalkan',
        'bulan'  => $P['bulan_batal'],
        'ket'    => 'Pendaftar berstatus verifikasi DITOLAK yang pendaftarannya lebih tua dari batas bulan.',
    ],
    'lulus_arsip' => [
        'judul'  => 'Lulus — arsip berkas',
        'bulan'  => $P['bulan_lulus_arsip'],
        'ket'    => 'Pendaftar LULUS yang pendaftarannya lebih tua dari batas bulan — berkas dipindah dari penyimpanan aktif.',
    ],
];
foreach ($kategori as $k => &$kat) {
    $kat['kandidat'] = retensi_kandidat($k, $kat['bulan']);
    $kat['total_berkas'] = array_sum(array_column($kat['kandidat'], 'jumlah_berkas'));
}
unset($kat);

$riwayat     = retensi_riwayat(20);
$bersihCount = retensi_sudah_bersih();
$totalPend   = (int) (q_col('SELECT COUNT(*) FROM pendaftar') ?? 0);

$csrf = csrf_token();

/* ------------------------------------------------------------------ */
require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'retensi';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik', 'Retensi Data & Privasi'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue"><?= icon('inventory_2', 'text-[13px]') ?> Privasi Data</span>
          <span class="badge <?= $P['aktif'] ? 'badge-green' : 'badge-gray' ?>">
            <?= $P['aktif'] ? 'Retensi AKTIF' : 'Retensi nonaktif' ?>
          </span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Retensi Data &amp; Privasi</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Retensi hanya menghapus <strong>berkas pribadi fisik</strong> (KK, akta, bukti transfer)
          dan jejaknya di tabel <span class="font-mono">dokumen</span> — baris pendaftar tetap tersimpan
          sebagai arsip ringkas. Setiap eksekusi tercatat di <span class="font-mono">retensi_log</span>.
        </p>
      </div>
      <div class="flex items-center gap-2.5 shrink-0">
        <a href="<?= url('app/operator/kesiapan.php') ?>" class="btn btn-outline">
          <?= icon('task_alt') ?> Kesiapan
        </a>
      </div>
    </div>

    <!-- ============ RINGKASAN ============ -->
    <section class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Pendaftar total</p>
        <p class="mt-1 text-2xl font-bold text-ink-900"><?= $totalPend ?></p>
      </div>
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Berkas sudah bersih</p>
        <p class="mt-1 text-2xl font-bold text-ink-900"><?= $bersihCount ?> <span class="text-sm font-normal text-ink-500">pendaftar</span></p>
      </div>
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Eksekusi tercatat</p>
        <p class="mt-1 text-2xl font-bold text-ink-900"><?= (int) (q_col('SELECT COUNT(*) FROM retensi_log') ?? 0) ?></p>
      </div>
    </section>

    <!-- ============ PENGATURAN ============ -->
    <section class="mt-6 card p-5 bg-canvas">
      <h2 class="text-base font-semibold text-ink-900">Pengaturan retensi</h2>
      <p class="mt-1 text-[13px] text-ink-500">
        Centang aktif &amp; isi minimal satu batas bulan. Tool CLI
        <code class="font-mono text-[11px] bg-white px-1.5 py-0.5 rounded border border-line">php tools/retensi.php</code>
        menjalankan dry-run; tambah <code class="font-mono text-[11px] bg-white px-1.5 py-0.5 rounded border border-line">--jalankan</code>
        untuk eksekusi nyata (cron disarankan bulanan).
      </p>
      <form method="post" class="mt-4" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="aksi" value="simpan_pengaturan">
        <label class="flex items-center gap-2.5 text-sm text-ink-800">
          <input type="checkbox" name="retensi_aktif" value="1" class="rounded border-line w-4 h-4"
                 <?= $P['aktif'] ? 'checked' : '' ?>>
          Aktifkan retensi berkas pribadi
        </label>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
          <label class="block">
            <span class="text-[13px] font-medium text-ink-700">Tidak lulus — hapus berkas setelah (bulan)</span>
            <input type="number" name="retensi_bulan_tidak_lulus" min="1" max="240" placeholder="kosongkan = tidak berlaku"
                   value="<?= e($P['bulan_tidak_lulus'] !== null ? (string) $P['bulan_tidak_lulus'] : '') ?>"
                   class="mt-1.5 w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
          </label>
          <label class="block">
            <span class="text-[13px] font-medium text-ink-700">Ditolak/batal — hapus berkas setelah (bulan)</span>
            <input type="number" name="retensi_bulan_batal" min="1" max="240" placeholder="kosongkan = tidak berlaku"
                   value="<?= e($P['bulan_batal'] !== null ? (string) $P['bulan_batal'] : '') ?>"
                   class="mt-1.5 w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
          </label>
          <label class="block">
            <span class="text-[13px] font-medium text-ink-700">Lulus — arsipkan berkas setelah (bulan)</span>
            <input type="number" name="retensi_bulan_lulus_arsip" min="1" max="240" placeholder="kosongkan = tidak berlaku"
                   value="<?= e($P['bulan_lulus_arsip'] !== null ? (string) $P['bulan_lulus_arsip'] : '') ?>"
                   class="mt-1.5 w-full rounded-xl border border-line bg-white px-3 py-2 text-sm">
          </label>
        </div>
        <button type="submit" class="btn btn-primary mt-4"><?= icon('save') ?> Simpan pengaturan</button>
      </form>
    </section>

    <!-- ============ PRATINJAU & HAPUS ============ -->
    <?php foreach ($kategori as $k => $kat): ?>
      <section class="mt-6 card p-5 bg-canvas">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="text-base font-semibold text-ink-900"><?= e($kat['judul']) ?></h2>
            <p class="mt-1 text-[13px] text-ink-500"><?= e($kat['ket']) ?></p>
          </div>
          <?php if ($kat['bulan'] === null): ?>
            <span class="badge badge-gray shrink-0">Batas belum diisi</span>
          <?php else: ?>
            <span class="badge badge-amber shrink-0">≥ <?= (int) $kat['bulan'] ?> bulan · <?= count($kat['kandidat']) ?> pendaftar · <?= $kat['total_berkas'] ?> berkas</span>
          <?php endif; ?>
        </div>

        <?php if ($kat['bulan'] !== null && $kat['kandidat']): ?>
          <div class="mt-4 overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-[12px] uppercase tracking-wide text-ink-500 border-b border-line">
                  <th class="py-2 pr-3">No. Pendaftaran</th>
                  <th class="py-2 pr-3">Nama</th>
                  <th class="py-2 pr-3">Tanggal Daftar</th>
                  <th class="py-2 pr-3 text-right">Berkas</th>
                  <th class="py-2 pr-3">Konfirmasi &amp; hapus</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($kat['kandidat'] as $c): ?>
                  <tr class="border-b border-line/60 align-top">
                    <td class="py-2.5 pr-3 font-mono text-[12px]"><?= e($c['no_pendaftaran']) ?></td>
                    <td class="py-2.5 pr-3"><?= e($c['nama_siswa']) ?></td>
                    <td class="py-2.5 pr-3 text-ink-500"><?= e(substr((string) $c['created_at'], 0, 10)) ?></td>
                    <td class="py-2.5 pr-3 text-right"><?= (int) $c['jumlah_berkas'] ?></td>
                    <td class="py-2.5 pr-3">
                      <form method="post" class="flex items-center gap-2 flex-wrap" autocomplete="off"
                            onsubmit="return confirm('Hapus berkas pribadi <?= e($c['no_pendaftaran']) ?>? Tindakan ini tidak bisa dibatalkan.');">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="aksi" value="hapus_berkas">
                        <input type="hidden" name="pendaftar_id" value="<?= e($c['id']) ?>">
                        <input type="text" name="konfirmasi" required
                               placeholder="ketik <?= e($c['no_pendaftaran']) ?>"
                               class="rounded-xl border border-line bg-white px-2.5 py-1.5 text-[12px] font-mono w-[150px]">
                        <button type="submit" class="btn btn-danger btn-sm">Hapus berkas</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php elseif ($kat['bulan'] !== null): ?>
          <p class="mt-3 text-[13px] text-ink-500">Tidak ada kandidat — semua berkas pribadi pada kategori ini sudah bersih atau belum melewati batas.</p>
        <?php else: ?>
          <p class="mt-3 text-[13px] text-ink-500">Isi batas bulan pada pengaturan di atas untuk melihat pratinjau kandidat.</p>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <!-- ============ RIWAYAT ============ -->
    <section class="mt-6 card p-5 bg-canvas">
      <h2 class="text-base font-semibold text-ink-900">Riwayat eksekusi (retensi_log)</h2>
      <?php if ($riwayat): ?>
        <div class="mt-3 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-[12px] uppercase tracking-wide text-ink-500 border-b border-line">
                <th class="py-2 pr-3">Waktu</th>
                <th class="py-2 pr-3">No. Pendaftaran</th>
                <th class="py-2 pr-3">Alasan</th>
                <th class="py-2 pr-3 text-right">Berkas</th>
                <th class="py-2 pr-3">Operator</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($riwayat as $r): ?>
                <tr class="border-b border-line/60">
                  <td class="py-2 pr-3 text-ink-500"><?= e((string) $r['dibuat_pada']) ?></td>
                  <td class="py-2 pr-3 font-mono text-[12px]"><?= e((string) $r['no_pendaftaran']) ?></td>
                  <td class="py-2 pr-3"><?= e((string) $r['alasan']) ?></td>
                  <td class="py-2 pr-3 text-right"><?= (int) $r['jumlah_berkas'] ?></td>
                  <td class="py-2 pr-3"><?= e((string) ($r['operator_nama'] ?? 'CLI')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="mt-2 text-[13px] text-ink-500">Belum ada eksekusi retensi tercatat.</p>
      <?php endif; ?>
    </section>

    <!-- ============ CATATAN ============ -->
    <section class="mt-6 card p-5 bg-canvas">
      <div class="flex items-start gap-3">
        <span class="icon-box bg-[#EFF6FF] text-[#1E40AF] shrink-0"><?= icon('policy', 'text-[20px]') ?></span>
        <div class="text-[13px] leading-relaxed text-ink-500 space-y-1.5">
          <p>Draf kebijakan retensi &amp; privasi untuk ditinjau sekolah/hukum ada di
            <a class="text-primary underline" href="docs/KEBIJAKAN-DATA.md" target="_blank" rel="noopener">docs/KEBIJAKAN-DATA.md</a>.
            S&amp;K pendaftaran (versi otomatis naik saat bagian privasi diubah) ada di
            <a class="text-primary underline" href="app/operator/pengaturan.php" target="_blank" rel="noopener">Pengaturan Sistem</a>.</p>
          <p>Retensi tidak pernah menghapus: nilai seleksi, penempatan kelas, data akademik, atau baris pendaftar.</p>
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
