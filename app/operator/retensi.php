<?php
/**
 * Fase 12 E2 — Retensi Data & Privasi (operator).
 *
 * Menghapus BUKAN baris pendaftar, melainkan berkas pribadi fisik
 * (KK, akta, bukti transfer) di storage/dokumen beserta jejaknya di
 * tabel dokumen — setelah konfirmasi ganda (ketik no_pendaftaran).
 * Setiap eksekusi tercatat di retensi_log. Cron CLI: tools/retensi.php.
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
        'ket'    => 'Pendaftar yang dinyatakan tidak lulus seleksi.',
    ],
    'batal' => [
        'judul'  => 'Ditolak atau batal',
        'bulan'  => $P['bulan_batal'],
        'ket'    => 'Pendaftar yang ditolak saat verifikasi atau berhenti di tengah proses pendaftaran.',
    ],
    'lulus_arsip' => [
        'judul'  => 'Sudah lulus',
        'bulan'  => $P['bulan_lulus_arsip'],
        'ket'    => 'Pendaftar yang sudah lulus — berkas pribadinya tidak diperlukan lagi.',
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
            <?= $P['aktif'] ? 'Penghapusan aktif' : 'Penghapusan nonaktif' ?>
          </span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Retensi Data &amp; Privasi</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Atur kapan <strong>berkas pribadi</strong> pendaftar lama (KK, akta, bukti transfer) dihapus.
          Data pendaftar, hasil seleksi, dan penempatan kelas tetap tersimpan.
        </p>
      </div>
      <div class="flex items-center gap-2.5 shrink-0">
        <a href="<?= url('app/operator/kesiapan.php') ?>" class="btn btn-outline">
          <?= icon('task_alt') ?> Kesiapan
        </a>
      </div>
    </div>

    <!-- ============ CARA PAKAI ============ -->
    <section class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-3">
      <?php
      $langkah = [
          ['1', 'Tentukan batas waktu', 'Isi berapa bulan berkas disimpan untuk tiap kategori di bagian 1.'],
          ['2', 'Simpan &amp; aktifkan', 'Centang “Aktifkan penghapusan otomatis”, lalu tekan Simpan.'],
          ['3', 'Hapus berkas', 'Periksa daftar kandidat di bagian 2, lalu hapus per pendaftar.'],
      ];
      foreach ($langkah as $l): ?>
        <div class="card p-4 flex items-start gap-3">
          <span class="w-7 h-7 rounded-full bg-primary/15 text-primary text-[13px] font-bold flex items-center justify-center shrink-0"><?= $l[0] ?></span>
          <div class="min-w-0">
            <p class="text-[13px] font-semibold text-ink-900"><?= $l[1] ?></p>
            <p class="mt-0.5 text-[12px] leading-relaxed text-ink-500"><?= $l[2] ?></p>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- ============ RINGKASAN ============ -->
    <section class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Pendaftar di sistem</p>
        <p class="mt-1 text-2xl font-bold text-ink-900"><?= $totalPend ?></p>
      </div>
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Berkas sudah bersih</p>
        <p class="mt-1 text-2xl font-bold text-ink-900"><?= $bersihCount ?> <span class="text-sm font-normal text-ink-500">pendaftar</span></p>
      </div>
      <div class="card p-5">
        <p class="text-[12px] uppercase tracking-wide text-ink-500">Penghapusan tercatat</p>
        <p class="mt-1 text-2xl font-bold text-ink-900"><?= (int) (q_col('SELECT COUNT(*) FROM retensi_log') ?? 0) ?></p>
      </div>
    </section>

    <!-- ============ PENGATURAN ============ -->
    <section class="mt-6 card p-5 sm:p-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-semibold text-ink-900">1. Atur batas waktu penyimpanan</h2>
        <span class="badge <?= $P['aktif'] ? 'badge-green' : 'badge-gray' ?>">
          <?= $P['aktif'] ? 'Otomatis aktif' : 'Otomatis nonaktif' ?>
        </span>
      </div>
      <p class="mt-1 text-[13px] text-ink-500">
        Isi berapa bulan berkas disimpan sejak tanggal pendaftaran.
        <strong>Kosongkan</strong> bila kategori itu belum ingin dihapus otomatis.
      </p>

      <form method="post" class="mt-4" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="aksi" value="simpan_pengaturan">

        <label class="flex items-start gap-3 rounded-xl border border-line bg-canvas p-3">
          <input type="checkbox" name="retensi_aktif" value="1" class="mt-0.5 rounded border-line w-4 h-4"
                 <?= $P['aktif'] ? 'checked' : '' ?>>
          <span class="min-w-0">
            <span class="block text-sm font-semibold text-ink-900">Aktifkan penghapusan otomatis</span>
            <span class="block text-[12px] text-ink-500">Pembersihan berkala hanya berjalan untuk kategori yang batas bulannya diisi.</span>
          </span>
        </label>

        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
          <label class="block rounded-xl border border-line p-3">
            <span class="block text-[13px] font-semibold text-ink-900">Tidak lulus seleksi</span>
            <span class="mt-2 flex items-center gap-2">
              <input type="number" name="retensi_bulan_tidak_lulus" min="1" max="240" placeholder="—"
                     value="<?= e($P['bulan_tidak_lulus'] !== null ? (string) $P['bulan_tidak_lulus'] : '') ?>"
                     class="w-24 rounded-xl border border-line bg-white px-3 py-2 text-sm">
              <span class="text-[13px] text-ink-500">bulan</span>
            </span>
            <span class="mt-1 block text-[12px] text-ink-500">Contoh: 24 = dua tahun setelah mendaftar.</span>
          </label>
          <label class="block rounded-xl border border-line p-3">
            <span class="block text-[13px] font-semibold text-ink-900">Ditolak atau batal</span>
            <span class="mt-2 flex items-center gap-2">
              <input type="number" name="retensi_bulan_batal" min="1" max="240" placeholder="—"
                     value="<?= e($P['bulan_batal'] !== null ? (string) $P['bulan_batal'] : '') ?>"
                     class="w-24 rounded-xl border border-line bg-white px-3 py-2 text-sm">
              <span class="text-[13px] text-ink-500">bulan</span>
            </span>
            <span class="mt-1 block text-[12px] text-ink-500">Contoh: 12 = satu tahun setelah mendaftar.</span>
          </label>
          <label class="block rounded-xl border border-line p-3">
            <span class="block text-[13px] font-semibold text-ink-900">Sudah lulus</span>
            <span class="mt-2 flex items-center gap-2">
              <input type="number" name="retensi_bulan_lulus_arsip" min="1" max="240" placeholder="—"
                     value="<?= e($P['bulan_lulus_arsip'] !== null ? (string) $P['bulan_lulus_arsip'] : '') ?>"
                     class="w-24 rounded-xl border border-line bg-white px-3 py-2 text-sm">
              <span class="text-[13px] text-ink-500">bulan</span>
            </span>
            <span class="mt-1 block text-[12px] text-ink-500">Berkas dihapus, data kelulusan tetap ada.</span>
          </label>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
          <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan pengaturan</button>
          <span class="text-[12px] text-ink-500">Perubahan berlaku setelah tombol ditekan.</span>
        </div>
      </form>

      <details class="mt-4 text-[12px] text-ink-500">
        <summary class="cursor-pointer select-none">Catatan untuk teknisi server</summary>
        <p class="mt-2">
          Pembersihan massal berkala dijalankan dari server dengan
          <code class="font-mono text-[11px] bg-canvas px-1.5 py-0.5 rounded border border-line">php tools/retensi.php --jalankan</code>
          (tanpa argumen = pratinjau). Retensi harus aktif agar perintah itu benar-benar menghapus berkas.
        </p>
      </details>
    </section>

    <!-- ============ PRATINJAU & HAPUS ============ -->
    <section class="mt-6">
      <h2 class="text-base font-semibold text-ink-900">2. Periksa &amp; hapus berkas</h2>
      <p class="mt-1 text-[13px] text-ink-500">
        Daftar di bawah adalah pendaftar yang sudah melewati batas waktu pada bagian 1.
      </p>

      <div class="mt-3 space-y-4">
        <?php foreach ($kategori as $k => $kat): ?>
          <div class="card p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0">
                <h3 class="text-[15px] font-semibold text-ink-900"><?= e($kat['judul']) ?></h3>
                <p class="mt-0.5 text-[13px] text-ink-500"><?= e($kat['ket']) ?></p>
              </div>
              <?php if ($kat['bulan'] === null): ?>
                <span class="badge badge-gray shrink-0">Batas belum diisi</span>
              <?php elseif ($kat['kandidat']): ?>
                <span class="badge badge-amber shrink-0"><?= count($kat['kandidat']) ?> pendaftar · <?= $kat['total_berkas'] ?> berkas siap dihapus</span>
              <?php else: ?>
                <span class="badge badge-green shrink-0"><?= icon('check_circle', 'text-[13px]') ?> Tidak ada berkas menunggu</span>
              <?php endif; ?>
            </div>

            <?php if ($kat['bulan'] !== null): ?>
              <p class="mt-1.5 text-[12px] text-ink-500">Batas: <?= (int) $kat['bulan'] ?> bulan sejak mendaftar.</p>
            <?php endif; ?>

            <?php if ($kat['bulan'] === null): ?>
              <p class="mt-3 rounded-xl border border-line bg-canvas px-3 py-2.5 text-[13px] text-ink-500">
                Isi batas bulan untuk kategori ini di bagian 1 agar daftar kandidat muncul.
              </p>
            <?php elseif (!$kat['kandidat']): ?>
              <p class="mt-3 rounded-xl border border-line bg-canvas px-3 py-2.5 text-[13px] text-ink-500">
                Semua berkas pada kategori ini sudah bersih atau belum melewati batas.
              </p>
            <?php else: ?>
              <div class="mt-3 space-y-2.5">
                <?php foreach ($kat['kandidat'] as $c): ?>
                  <div class="rounded-xl border border-line bg-white p-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                      <div class="min-w-0">
                        <p class="text-sm font-semibold text-ink-900"><?= e($c['nama_siswa']) ?></p>
                        <p class="mt-0.5 text-[12px] text-ink-500">
                          <span class="font-mono"><?= e($c['no_pendaftaran']) ?></span>
                          · mendaftar <?= e(substr((string) $c['created_at'], 0, 10)) ?>
                        </p>
                      </div>
                      <span class="badge badge-gray shrink-0"><?= (int) $c['jumlah_berkas'] ?> berkas</span>
                    </div>
                    <details class="mt-2">
                      <summary class="inline-flex items-center gap-1.5 cursor-pointer select-none text-[13px] font-semibold text-[#991B1B]">
                        <?= icon('delete', 'text-[16px]') ?> Hapus berkas pribadi
                      </summary>
                      <form method="post" class="mt-2 rounded-xl border border-[#FECACA] bg-[#FEF2F2] p-3" autocomplete="off"
                            onsubmit="return confirm('Hapus berkas pribadi <?= e($c['no_pendaftaran']) ?>? Tindakan ini tidak bisa dibatalkan.');">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="aksi" value="hapus_berkas">
                        <input type="hidden" name="pendaftar_id" value="<?= e($c['id']) ?>">
                        <label class="block">
                          <span class="block text-[12px] font-medium text-[#991B1B]">
                            Ketik <span class="font-mono"><?= e($c['no_pendaftaran']) ?></span> untuk konfirmasi
                          </span>
                          <input type="text" name="konfirmasi" required placeholder="<?= e($c['no_pendaftaran']) ?>"
                                 class="mt-1 w-[150px] rounded-xl border border-[#FECACA] bg-white px-3 py-2 text-[13px] font-mono">
                        </label>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                          <button type="submit" class="btn btn-danger btn-sm"><?= icon('delete') ?> Hapus sekarang</button>
                          <span class="text-[12px] text-[#991B1B]">Berkas yang dihapus tidak bisa dikembalikan.</span>
                        </div>
                      </form>
                    </details>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ============ RIWAYAT ============ -->
    <section class="mt-6 card p-5 sm:p-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-semibold text-ink-900">Riwayat penghapusan</h2>
        <span class="badge badge-gray">20 terakhir</span>
      </div>
      <?php if ($riwayat): ?>
        <div class="mt-3 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-[12px] uppercase tracking-wide text-ink-500 border-b border-line">
                <th class="py-2 pr-3">Waktu</th>
                <th class="py-2 pr-3">Pendaftar</th>
                <th class="py-2 pr-3">Sumber</th>
                <th class="py-2 pr-3 text-right">Berkas</th>
                <th class="py-2 pr-3">Oleh</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($riwayat as $r): ?>
                <?php
                $ts = strtotime((string) $r['dibuat_pada']);
                $waktu = $ts ? date('d/m/Y H:i', $ts) : (string) $r['dibuat_pada'];
                $sumber = [
                    'manual_operator'          => 'Dihapus operator',
                    'manual_detail_verifikasi' => 'Dihapus saat verifikasi',
                    'cli_tidak_lulus'          => 'Pembersihan terjadwal',
                    'cli_batal'                => 'Pembersihan terjadwal',
                    'cli_lulus_arsip'          => 'Pembersihan terjadwal',
                ][(string) $r['alasan']] ?? (string) $r['alasan'];
                ?>
                <tr class="border-b border-line/60">
                  <td class="py-2 pr-3 text-ink-500"><?= e($waktu) ?></td>
                  <td class="py-2 pr-3 font-mono text-[12px]"><?= e((string) $r['no_pendaftaran']) ?></td>
                  <td class="py-2 pr-3"><?= e($sumber) ?></td>
                  <td class="py-2 pr-3 text-right"><?= (int) $r['jumlah_berkas'] ?></td>
                  <td class="py-2 pr-3"><?= e((string) ($r['operator_nama'] ?? 'Sistem')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <p class="mt-3 text-[13px] text-ink-500">Belum ada berkas yang dihapus. Riwayat akan muncul di sini setelah penghapusan pertama.</p>
      <?php endif; ?>
    </section>

    <!-- ============ CATATAN ============ -->
    <section class="mt-6 card p-5 sm:p-6">
      <h2 class="text-base font-semibold text-ink-900">Yang dihapus dan yang tetap disimpan</h2>
      <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="rounded-xl border border-[#FECACA] bg-[#FEF2F2] p-3">
          <p class="flex items-center gap-2 text-[13px] font-semibold text-[#991B1B]"><?= icon('delete', 'text-[17px]') ?> Dihapus</p>
          <ul class="mt-2 space-y-1 text-[13px] text-[#991B1B]">
            <li>• File KK, akta, dan berkas syarat lainnya</li>
            <li>• File bukti transfer</li>
          </ul>
        </div>
        <div class="rounded-xl border border-[#A7F3D0] bg-[#ECFDF5] p-3">
          <p class="flex items-center gap-2 text-[13px] font-semibold text-[#065F46]"><?= icon('verified', 'text-[17px]') ?> Tetap disimpan</p>
          <ul class="mt-2 space-y-1 text-[13px] text-[#065F46]">
            <li>• Data pendaftar dan nomor pendaftaran</li>
            <li>• Hasil seleksi, kelas, dan catatan pembayaran</li>
            <li>• Riwayat penghapusan (siapa, kapan, berapa berkas)</li>
          </ul>
        </div>
      </div>
      <p class="mt-3 text-[13px] text-ink-500">
        Kebijakan lengkap:
        <a class="text-primary underline" href="<?= url('docs/KEBIJAKAN-DATA.md') ?>" target="_blank" rel="noopener">docs/KEBIJAKAN-DATA.md</a>.
        Syarat &amp; Ketentuan pendaftaran dapat diubah di
        <a class="text-primary underline" href="<?= url('app/operator/pengaturan.php') ?>">Pengaturan Sistem</a>.
      </p>
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
