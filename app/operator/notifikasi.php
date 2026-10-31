<?php
/* ==================================================================
 * NOTIFIKASI OPERATOR (Fase 3).
 *   - Daftar notifikasi in-app (tabel notifikasi).
 *   - Tandai dibaca per item / semua sekaligus.
 *   - Tautan ke halaman terkait (verifikasi pembayaran, dll.).
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';

require_role(['operator', 'admin', 'bendahara', 'kepsek']);

/* --- Proses POST (PRG) --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('galat', 'Sesi kedaluwarsa. Silakan coba lagi.');
        header('Location: notifikasi.php');
        exit;
    }
    $aksi = (string) ($_POST['aksi'] ?? '');
    if ($aksi === 'tandai_semua') {
        q_exec('UPDATE notifikasi SET dibaca_at = NOW() WHERE dibaca_at IS NULL');
        flash('sukses', 'Semua notifikasi ditandai sudah dibaca.');
    } elseif ($aksi === 'tandai_dibaca') {
        $id = (string) ($_POST['id'] ?? '');
        if (preg_match('/^ntf-[a-f0-9]{8}$/', $id)) {
            q_exec('UPDATE notifikasi SET dibaca_at = NOW() WHERE id = ? AND dibaca_at IS NULL', [$id]);
            flash('sukses', 'Notifikasi ditandai sudah dibaca.');
        }
    }
    header('Location: notifikasi.php');
    exit;
}

/* --- Ambil data --- */
$hal    = max(1, (int) ($_GET['h'] ?? 1));
$perH   = 20;
$offset = ($hal - 1) * $perH;

$total = (int) q_col('SELECT COUNT(*) FROM notifikasi');
$jumlah = notif_belum_dibaca();

/* Sisipkan no. pendaftaran untuk tautan. */
$rows = q_all(
    'SELECT n.id, n.tipe, n.pesan, n.dibaca_at, n.created_at, p.no_pendaftaran
       FROM notifikasi n
       LEFT JOIN pendaftar p ON p.id = n.pendaftar_id
   ORDER BY n.created_at DESC
      LIMIT ' . $perH . ' OFFSET ' . $offset
) ?: [];

/* Tautan per tipe — tambah baris di sini saat tipe baru muncul. */
function notif_tautan($tipe)
{
    switch ($tipe) {
        case 'BUKTI_BAYAR_MASUK':
        case 'PEMBAYARAN_GATEWAY_LUNAS':
        case 'PEMBAYARAN_GATEWAY_MASUK':
        case 'PENOLAKAN_BAYAR':
            return url('app/bendahara/index.php');
        case 'BERKAS_DISUBMIT':
        case 'BERKAS_REVISI':
            return url('app/operator/berkas.php');
        case 'VERIFIKASI_DATA':
        case 'KOREKSI_DATA':
            return url('app/operator/verifikasi-data.php');
        default:
            return url('app/operator/notifikasi.php');
    }
}

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title    = 'Notifikasi';
$desc     = 'Daftar notifikasi operasional SPMB: pembayaran masuk, submit berkas, dan pemberitahuan lain.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'notifikasi';
$badgeRole  = strtoupper($me['role']);
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik' => null, 'SPMB' => null, 'Notifikasi' => null];
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
$halTotal = max(1, (int) ceil($total / $perH));
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1000px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue"><?= dot('blue') ?> <?= (int) $jumlah ?> belum dibaca</span>
          <span class="badge badge-gray"><?= $total ?> total</span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Notifikasi</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Pemberitahuan otomatis sistem: bukti pembayaran masuk, pembayaran gateway lunas, submit berkas, dan lainnya.
        </p>
      </div>
      <div class="flex gap-2.5 shrink-0">
        <?php if ($jumlah > 0): ?>
          <form method="post" action="notifikasi.php">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tandai_semua">
            <button type="submit" class="btn btn-outline">
              <?= icon('done_all') ?> Tandai Semua Dibaca
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($flash)): ?>
      <div class="mt-5 rounded-xl border border-<?= $flash['tipe'] === 'sukses' ? 'green-200 bg-green-50 text-green-800' : 'red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <section class="mt-6 space-y-3">
      <?php if (!$rows): ?>
        <div class="card p-8 text-center text-sm text-ink-500">
          Belum ada notifikasi.
        </div>
      <?php endif; ?>

      <?php foreach ($rows as $r):
        $sudah = $r['dibaca_at'] !== null;
        $tautan = notif_tautan($r['tipe']);
      ?>
        <article class="card p-4 sm:p-5 <?= $sudah ? 'opacity-70' : '' ?>">
          <div class="flex flex-col sm:flex-row sm:items-start gap-3 sm:gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="badge <?= $sudah ? 'badge-gray' : 'badge-blue' ?>">
                  <?= $sudah ? dot('gray') : dot('blue') ?> <?= e($r['tipe']) ?>
                </span>
                <?php if (!empty($r['no_pendaftaran'])): ?>
                  <span class="badge badge-gray"><?= e($r['no_pendaftaran']) ?></span>
                <?php endif; ?>
                <span class="text-[12px] text-ink-400"><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></span>
              </div>
              <p class="mt-2 text-sm text-ink-700 whitespace-pre-line"><?= e($r['pesan']) ?></p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <a href="<?= e($tautan) ?>" class="btn btn-outline btn-sm">
                <?= icon('open_in_new') ?> Buka
              </a>
              <?php if (!$sudah): ?>
                <form method="post" action="notifikasi.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="tandai_dibaca">
                  <input type="hidden" name="id" value="<?= e($r['id']) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm" title="Tandai sudah dibaca">
                    <?= icon('done') ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <?php if ($halTotal > 1): ?>
      <nav class="mt-6 flex items-center justify-between text-sm">
        <?php
        $qs = function ($h) { return 'notifikasi.php?h=' . $h; };
        ?>
        <a href="<?= $hal > 1 ? e($qs($hal - 1)) : '#' ?>"
           class="btn btn-outline btn-sm <?= $hal <= 1 ? 'pointer-events-none opacity-40' : '' ?>">
          <?= icon('chevron_left') ?> Sebelumnya
        </a>
        <span class="text-ink-500">Halaman <?= (int) $hal ?> / <?= $halTotal ?></span>
        <a href="<?= $hal < $halTotal ? e($qs($hal + 1)) : '#' ?>"
           class="btn btn-outline btn-sm <?= $hal >= $halTotal ? 'pointer-events-none opacity-40' : '' ?>">
          Berikutnya <?= icon('chevron_right') ?>
        </a>
      </nav>
    <?php endif; ?>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<?php if ($flash): ?>
<script>window.APP_FLASH = <?= json_encode(['tipe' => $flash['tipe'], 'pesan' => $flash['pesan']], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
