<?php
/**
 * PENGUMUMAN UMUM — operator (Tahap 2).
 *
 * CRUD papan informasi bebas (bukan hasil seleksi): tulis draft, edit,
 * terbitkan (otomatis membuat notifikasi siswa), tarik kembali, atau hapus.
 * Pengumuman terbit tampil di portal siswa (halaman Pengumuman).
 */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

$me = current_user();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang halaman.');
        header('Location: ' . url('app/operator/pengumuman-umum.php'));
        exit;
    }

    $aksi = (string) ($_POST['aksi'] ?? '');
    $id   = trim((string) ($_POST['id'] ?? ''));

    if ($aksi === 'simpan') {
        $r = pengumuman_umum_simpan(
            $id,
            (string) ($_POST['judul'] ?? ''),
            (string) ($_POST['isi'] ?? ''),
            isset($_POST['penting']),
            (string) ($me['id'] ?? '')
        );
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        header('Location: ' . url('app/operator/pengumuman-umum.php' . ($r['ok'] && $id === '' ? '?edit=' . rawurlencode($r['id']) : '')));
        exit;
    }

    if ($aksi === 'terbitkan') {
        $r = pengumuman_umum_terbitkan($id);
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        header('Location: ' . url('app/operator/pengumuman-umum.php'));
        exit;
    }

    if ($aksi === 'tarik') {
        $r = pengumuman_umum_tarik($id);
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        header('Location: ' . url('app/operator/pengumuman-umum.php'));
        exit;
    }

    if ($aksi === 'hapus') {
        $r = pengumuman_umum_hapus($id);
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        header('Location: ' . url('app/operator/pengumuman-umum.php'));
        exit;
    }

    flash('error', 'Aksi tidak dikenali.');
    header('Location: ' . url('app/operator/pengumuman-umum.php'));
    exit;
}

$flash  = flash();
$editId = trim((string) ($_GET['edit'] ?? ''));
$edit   = $editId !== '' ? pengumuman_umum_satu($editId) : null;
$daftar = pengumuman_umum_semua();

$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'] ?? 'Operator SPMB',
    'inisial' => $me['inisial'],
];

$title     = 'Pengumuman Umum';
$desc      = 'Tulis, terbitkan, dan kelola pengumuman bebas untuk seluruh pendaftar.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'pengumuman-umum';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik' => null, 'SPMB' => null, 'Pengumuman Umum' => null];
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Papan Informasi</span>
          <span class="badge badge-gray"><?= count($daftar) ?> pengumuman</span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Pengumuman Umum</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Pengumuman yang diterbitkan di sini tampil di portal siswa dan otomatis mengirim notifikasi
          ke seluruh pendaftar aktif.
        </p>
      </div>
      <a href="<?= url('app/operator/pengumuman.php') ?>" class="btn btn-outline shrink-0"><?= icon('campaign') ?> Pengumuman Hasil</a>
    </div>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">

      <!-- Form tulis / ubah -->
      <section class="lg:col-span-2">
        <div class="card p-5 sm:p-6">
          <h2 class="font-bold text-ink-900"><?= $edit ? 'Ubah Pengumuman' : 'Tulis Pengumuman Baru' ?></h2>
          <p class="mt-1 text-[12px] text-ink-500">
            <?= $edit ? 'Perubahan pada pengumuman terbit langsung memperbarui notifikasi siswa (status baca tetap).' : 'Simpan sebagai draft dulu, lalu terbitkan bila sudah siap.' ?>
          </p>

          <form method="post" action="<?= url('app/operator/pengumuman-umum.php') ?>" class="mt-4 space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

            <div>
              <label class="label" for="pu-judul">Judul</label>
              <input id="pu-judul" name="judul" type="text" class="input w-full" maxlength="160" required
                     value="<?= e($edit['judul'] ?? '') ?>" placeholder="Contoh: Jadwal Pengambilan Seragam">
            </div>

            <div>
              <label class="label" for="pu-isi">Isi pengumuman</label>
              <textarea id="pu-isi" name="isi" class="textarea w-full" rows="8" required
                        placeholder="Tulis informasi lengkap: apa, kapan, di mana, dan apa yang perlu dilakukan siswa."><?= e($edit['isi'] ?? '') ?></textarea>
            </div>

            <label class="flex items-start gap-2.5 text-[13px] text-ink-700 cursor-pointer">
              <input type="checkbox" name="penting" value="1" class="mt-0.5" <?= !empty($edit['penting']) ? 'checked' : '' ?>>
              <span>Tandai <strong>Penting</strong> (disorot di papan pengumuman siswa).</span>
            </label>

            <div class="flex items-center gap-2">
              <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan</button>
              <?php if ($edit): ?>
                <a href="<?= url('app/operator/pengumuman-umum.php') ?>" class="btn btn-ghost">Batal</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <div class="card p-5 mt-5 bg-canvas">
          <p class="text-[12px] text-ink-500">
            <strong class="text-ink-700">Alur:</strong> tulis → simpan draft → terbitkan. Menerbitkan mengirim
            notifikasi ke semua pendaftar aktif; menarik kembali akan menghapus notifikasi tersebut.
          </p>
        </div>
      </section>

      <!-- Daftar pengumuman -->
      <section class="lg:col-span-3 space-y-3">
        <?php if (!$daftar): ?>
          <div class="card p-8 text-center">
            <span class="icon-box bg-canvas text-ink-400 mx-auto"><?= icon('campaign', 'text-[26px]') ?></span>
            <h2 class="mt-3 font-bold text-ink-900">Belum ada pengumuman</h2>
            <p class="mt-1 text-sm text-ink-500">Tulis pengumuman pertama pada formulir di samping.</p>
          </div>
        <?php endif; ?>

        <?php foreach ($daftar as $p):
          $terbit = $p['status'] === 'TERBIT';
        ?>
          <article class="card p-5">
            <div class="flex items-start justify-between gap-3 flex-wrap">
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="badge badge-<?= $terbit ? 'green' : 'gray' ?>"><?= $terbit ? dot('green') . ' Terbit' : 'Draft' ?></span>
                  <?php if (!empty($p['penting'])): ?><span class="badge badge-amber">Penting</span><?php endif; ?>
                  <span class="text-[11px] text-ink-400">
                    Dibuat <?= e(date('d M Y H:i', strtotime($p['created_at']))) ?>
                    <?php if (!empty($p['pembuat'])): ?>· <?= e($p['pembuat']) ?><?php endif; ?>
                  </span>
                </div>
                <h2 class="mt-2 font-bold text-ink-900"><?= e($p['judul']) ?></h2>
                <p class="mt-1 text-[13px] text-ink-600 whitespace-pre-line"><?= e(pengumuman_umum_ringkas($p['isi'], 220)) ?></p>
                <?php if ($terbit && !empty($p['terbit_at'])): ?>
                  <p class="mt-2 text-[11px] text-ink-400">Terbit <?= e(bulan($p['terbit_at'])) ?></p>
                <?php endif; ?>
              </div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
              <a href="<?= url('app/operator/pengumuman-umum.php?edit=' . rawurlencode($p['id'])) ?>" class="btn btn-outline btn-sm">
                <?= icon('edit') ?> Ubah
              </a>
              <?php if ($terbit): ?>
                <form method="post" action="<?= url('app/operator/pengumuman-umum.php') ?>"
                      onsubmit="return confirm('Tarik pengumuman ini? Pengumuman hilang dari portal siswa dan notifikasinya dihapus.');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="tarik">
                  <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                  <button type="submit" class="btn btn-outline btn-sm"><?= icon('event_busy') ?> Tarik</button>
                </form>
              <?php else: ?>
                <form method="post" action="<?= url('app/operator/pengumuman-umum.php') ?>"
                      onsubmit="return confirm('Terbitkan pengumuman ini dan kirim notifikasi ke semua pendaftar aktif?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="terbitkan">
                  <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                  <button type="submit" class="btn btn-green btn-sm"><?= icon('send') ?> Terbitkan</button>
                </form>
              <?php endif; ?>
              <form method="post" action="<?= url('app/operator/pengumuman-umum.php') ?>"
                    onsubmit="return confirm('Hapus permanen pengumuman ini beserta notifikasinya?');">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="hapus">
                <input type="hidden" name="id" value="<?= e($p['id']) ?>">
                <button type="submit" class="btn btn-ghost btn-sm text-red-600"><?= icon('delete') ?> Hapus</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
    </div>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<?php if ($flash): ?>
<script>window.APP_FLASH = <?= json_encode(['tipe' => $flash['tipe'], 'pesan' => $flash['pesan']], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
