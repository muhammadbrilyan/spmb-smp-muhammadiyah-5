<?php
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title = 'Manajemen Kelas & Kuota';
$desc  = 'CRUD rombel kelas dan alokasi kuota per kelas.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* ---------- Proses POST (sebelum output HTML) ---------- */
$galat = [];
$flashx = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        header('Location: ' . url('app/operator/kelas.php'));
        exit;
    }

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah' || $aksi === 'ubah') {
        $id      = trim((string) ($_POST['id'] ?? ''));
        $kode    = strtoupper(trim((string) ($_POST['kode'] ?? '')));
        $nama    = trim((string) ($_POST['nama_kelas'] ?? ''));
        $wali    = trim((string) ($_POST['wali_kelas'] ?? ''));
        $kuota   = (int) ($_POST['kuota'] ?? 0);
        $aktif   = !empty($_POST['is_aktif']) ? 1 : 0;

        if ($kode === '' || !preg_match('/^[A-Z0-9]{1,10}$/', $kode)) {
            $galat[] = 'Kode kelas wajib diisi (huruf/angka, maks 10 karakter), contoh: 7A.';
        }
        if ($nama === '') {
            $galat[] = 'Nama kelas wajib diisi.';
        }
        if ($kuota < 1 || $kuota > 500) {
            $galat[] = 'Kuota harus antara 1 dan 500.';
        }
        $kecuali = $aksi === 'ubah' ? $id : null;
        if (!$galat && kelas_kode_dipakai($kode, $kecuali)) {
            $galat[] = 'Kode kelas "' . e($kode) . '" sudah dipakai.';
        }

        if (!$galat) {
            if ($aksi === 'tambah') {
                $baru = kelas_tambah($kode, $nama, $wali, $kuota, $aktif);
                if ($baru) {
                    flash('sukses', 'Kelas ' . $kode . ' berhasil ditambahkan.');
                } else {
                    flash('error', 'Gagal menambah kelas ke database.');
                }
            } else {
                if (kelas_ubah($id, $kode, $nama, $wali, $kuota, $aktif)) {
                    flash('sukses', 'Kelas ' . $kode . ' berhasil diperbarui.');
                } else {
                    flash('error', 'Gagal memperbarui kelas.');
                }
            }
            header('Location: ' . url('app/operator/kelas.php'));
            exit;
        }
        $flashx = $galat;
    } elseif ($aksi === 'hapus') {
        $id = trim((string) ($_POST['id'] ?? ''));
        if (kelas_hapus($id)) {
            flash('sukses', 'Kelas berhasil dihapus.');
        } else {
            flash('error', 'Kelas tidak bisa dihapus karena masih ada pendaftar tertempat. Kosongkan penempatan dulu.');
        }
        header('Location: ' . url('app/operator/kelas.php'));
        exit;
    }
}

$kelasList = daftar_kelas_admin();
$totalKuota = 0;
$totalTerisi = 0;
foreach ($kelasList as $k) {
    if ($k['is_aktif']) {
        $totalKuota  += (int) $k['kuota'];
        $totalTerisi += (int) $k['terisi'];
    }
}

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'kelas';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik', 'SPMB', 'Manajemen Kelas'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Info jumlah rombel -->
          <span class="badge badge-gray"><?= count($kelasList) ?> rombel</span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Manajemen Kelas &amp; Kuota</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Tambah, ubah, atau hapus rombel kelas beserta kuotanya. Total kuota pendaftaran = jumlah seluruh kuota kelas aktif.
        </p>
      </div>
      <div class="shrink-0 rounded-2xl border border-line bg-white shadow-card px-5 py-4 text-right">
        <p class="text-[13px] text-ink-500">Total Kuota Aktif</p>
        <p class="text-3xl font-extrabold text-ink-900"><?= number_format($totalKuota) ?></p>
        <p class="text-xs text-ink-400 mt-1">Terisi <?= number_format($totalTerisi) ?> · Sisa <?= number_format(max(0, $totalKuota - $totalTerisi)) ?></p>
      </div>
    </div>

    <?php if ($flashx): ?>
      <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
        <p class="font-semibold">Periksa isian berikut:</p>
        <ul class="mt-2 space-y-1 list-disc list-inside">
          <?php foreach ($flashx as $g): ?><li><?= $g ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Form tambah -->
    <section class="mt-6 card p-5 sm:p-6">
      <h2 class="font-bold text-ink-900">Tambah Kelas Baru</h2>
      <form method="post" action="<?= url('app/operator/kelas.php') ?>" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-6 items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <div>
          <label class="label" for="kode">Kode <span class="text-red-600">*</span></label>
          <input id="kode" name="kode" class="input font-mono uppercase" placeholder="7D" maxlength="10" required>
        </div>
        <div class="lg:col-span-2">
          <label class="label" for="nama_kelas">Nama Kelas <span class="text-red-600">*</span></label>
          <input id="nama_kelas" name="nama_kelas" class="input" placeholder="Kelas VII-D (Reguler)" required>
        </div>
        <div>
          <label class="label" for="wali_kelas">Wali Kelas</label>
          <input id="wali_kelas" name="wali_kelas" class="input" placeholder="Nama wali">
        </div>
        <div>
          <label class="label" for="kuota">Kuota <span class="text-red-600">*</span></label>
          <input id="kuota" name="kuota" type="number" min="1" max="500" value="30" class="input" required>
        </div>
        <div class="flex items-center gap-2 pb-2">
          <input type="checkbox" name="is_aktif" value="1" checked class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
          <label for="is_aktif" class="text-sm text-ink-700">Aktif</label>
          <button type="submit" class="btn btn-primary ml-auto shadow-drop"><?= icon('add') ?> Tambah</button>
        </div>
      </form>
    </section>

    <!-- Tabel kelas -->
    <section class="mt-6 card p-5 sm:p-6">
      <h2 class="font-bold text-ink-900">Daftar Rombel</h2>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[860px]">
          <thead class="thead">
            <tr>
              <th class="th">Kode</th>
              <th class="th">Nama Kelas</th>
              <th class="th">Wali Kelas</th>
              <th class="th text-right">Kuota</th>
              <th class="th text-right">Terisi</th>
              <th class="th">Progres</th>
              <th class="th">Status</th>
              <th class="th text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $noBaris = 0; ?>
            <?php foreach ($kelasList as $k): $persen = $k['kuota'] > 0 ? round($k['terisi'] / $k['kuota'] * 100) : 0; ?>
              <?php
              /* [PERBAIKAN] <form> tidak boleh jadi anak <tr> (lihat catatan di
               * app/operator/jadwal.php). Form Simpan & Hapus kini berada di dalam
               * <td> aksi; input yang tersebar di sel lain memakai form="id". */
              $noBaris++;
              $fEdit  = 'kf-' . $noBaris;
              $fHapus = 'kh-' . $noBaris;
              ?>
              <tr class="tr-hover">
                <td class="td"><input form="<?= e($fEdit) ?>" name="kode" value="<?= e($k['kode']) ?>" class="input h-8 font-mono uppercase w-20" maxlength="10" required></td>
                <td class="td"><input form="<?= e($fEdit) ?>" name="nama_kelas" value="<?= e($k['nama_kelas']) ?>" class="input h-8 w-full min-w-[180px]" required></td>
                <td class="td"><input form="<?= e($fEdit) ?>" name="wali_kelas" value="<?= e($k['wali_kelas']) ?>" class="input h-8 w-full min-w-[140px]"></td>
                <td class="td text-right"><input form="<?= e($fEdit) ?>" name="kuota" type="number" min="1" max="500" value="<?= (int) $k['kuota'] ?>" class="input h-8 w-20 text-right ml-auto" required></td>
                <td class="td text-right font-semibold"><?= number_format((int) $k['terisi']) ?></td>
                <td class="td min-w-[120px]">
                  <div class="flex items-center gap-2">
                    <div class="flex-1 h-2 rounded-full bg-line-soft overflow-hidden">
                      <div class="h-full rounded-full <?= $persen >= 100 ? 'bg-[#0E9F6E]' : 'bg-primary' ?>" style="width: <?= min($persen, 100) ?>%"></div>
                    </div>
                    <span class="text-xs font-semibold text-ink-500 w-10 text-right"><?= $persen ?>%</span>
                  </div>
                </td>
                <td class="td">
                  <label class="inline-flex items-center gap-1.5 text-[13px]">
                    <input form="<?= e($fEdit) ?>" type="checkbox" name="is_aktif" value="1" <?= $k['is_aktif'] ? 'checked' : '' ?> class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
                    <?= $k['is_aktif'] ? 'Aktif' : 'Nonaktif' ?>
                  </label>
                </td>
                <td class="td text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <form id="<?= e($fEdit) ?>" method="post" action="<?= url('app/operator/kelas.php') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="ubah">
                      <input type="hidden" name="id" value="<?= e($k['id']) ?>">
                      <button type="submit" class="btn btn-sm btn-outline"><?= icon('save', 'text-[15px]') ?> Simpan</button>
                    </form>
                    <form id="<?= e($fHapus) ?>" method="post" action="<?= url('app/operator/kelas.php') ?>" onsubmit="return confirm('Hapus kelas <?= e($k['kode']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id" value="<?= e($k['id']) ?>">
                      <button type="submit" class="icon-box border border-line text-ink-500 hover:text-red-600 hover:border-red-300" aria-label="Hapus"><?= icon('delete', 'text-[17px]') ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$kelasList): ?>
              <tr><td colspan="8" class="td text-center text-ink-400">Belum ada kelas. Tambahkan lewat form di atas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <p class="hint mt-3">Kelas dengan pendaftar tertempat tidak bisa dihapus — alihkan penempatan dulu.</p>
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
