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

$title = 'Kelola Jadwal Tes';
$desc  = 'Tambah, ubah, dan hapus jadwal tes seleksi SPMB.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$galat = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        header('Location: ' . url('app/operator/jadwal.php'));
        exit;
    }
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $idHapus = trim((string) ($_POST['id'] ?? ''));
        if (!$idHapus) {
            flash('error', 'Jadwal tidak ditemukan.');
        } else {
            $okHapus = jadwal_hapus($idHapus);
            flash($okHapus ? 'sukses' : 'error',
                $okHapus ? 'Jadwal dihapus.' : 'Jadwal tidak bisa dihapus karena sudah dipakai seleksi.');
        }
        header('Location: ' . url('app/operator/jadwal.php'));
        exit;
    }

    if ($aksi === 'kirim_notifikasi') {
        $idJ = trim((string) ($_POST['id'] ?? ''));
        if (!$idJ) {
            flash('error', 'Jadwal tidak ditemukan.');
        } else {
            $r = jadwal_kirim_notifikasi($idJ);
            if ($r['ok'] > 0) {
                flash('sukses', $r['ok'] . ' notifikasi WA jadwal terkirim.'
                    . ($r['gagal'] > 0 ? ' ' . $r['gagal'] . ' gagal "” ' . ($r['detail'][0] ?? '') : ''));
            } else {
                flash('error', $r['detail'][0] ?? 'Tidak ada penerima atau pengiriman gagal.');
            }
        }
        header('Location: ' . url('app/operator/jadwal.php'));
        exit;
    }

    $id      = trim((string) ($_POST['id'] ?? ''));
    $nama    = trim((string) ($_POST['nama_kegiatan'] ?? ''));
    $tgl     = trim((string) ($_POST['tanggal'] ?? ''));
    $jam1    = trim((string) ($_POST['jam_mulai'] ?? ''));
    $jam2    = trim((string) ($_POST['jam_selesai'] ?? ''));
    $mode    = $_POST['mode'] ?? 'OFFLINE';
    $peserta = (int) ($_POST['peserta_dijadwalkan'] ?? 0);
    $status  = $_POST['status'] ?? 'DRAFT';
    $ruangan = trim((string) ($_POST['ruangan'] ?? ''));
    $kapasitas = (int) ($_POST['kapasitas'] ?? 0);

    if ($nama === '') {
        $galat[] = 'Nama kegiatan wajib diisi.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
        $galat[] = 'Tanggal wajib diisi dengan format YYYY-MM-DD.';
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $jam1) || !preg_match('/^\d{2}:\d{2}$/', $jam2)) {
        $galat[] = 'Jam mulai & selesai wajib diisi.';
    }
    if (!in_array($mode, ['OFFLINE', 'DARING', 'VIDEO_CALL'], true)) {
        $mode = 'OFFLINE';
    }
    if (!in_array($status, ['DRAFT', 'TERJADWAL', 'SELESAI'], true)) {
        $status = 'DRAFT';
    }
    if ($peserta < 0 || $peserta > 1000) {
        $galat[] = 'Jumlah peserta antara 0 dan 1000.';
    }
    if ($kapasitas < 0 || $kapasitas > 1000) {
        $galat[] = 'Kapasitas antara 0 (tanpa batas) dan 1000.';
    }

    if (!$galat) {
        if ($aksi === 'tambah') {
            $baru = jadwal_tambah($nama, $tgl, $jam1, $jam2, $mode, $peserta, $status, $ruangan, $kapasitas);
            flash($baru ? 'sukses' : 'error', $baru ? 'Jadwal tes berhasil ditambahkan.' : 'Gagal menambah jadwal.');
        } elseif ($aksi === 'ubah' && $id) {
            flash(jadwal_ubah($id, $nama, $tgl, $jam1, $jam2, $mode, $peserta, $status, $ruangan, $kapasitas) ? 'sukses' : 'error',
                'Jadwal tes berhasil diperbarui.');
        } else {
            flash('error', 'Aksi tidak dikenal.');
        }
        header('Location: ' . url('app/operator/jadwal.php'));
        exit;
    }
}

$jadwalList = daftar_jadwal_tes_admin();
$labelMode = ['OFFLINE' => 'Offline', 'DARING' => 'Daring', 'VIDEO_CALL' => 'Video Call'];
$labelStat = ['DRAFT' => 'Draft', 'TERJADWAL' => 'Terjadwal', 'SELESAI' => 'Selesai'];
$toneStat  = ['DRAFT' => 'amber', 'TERJADWAL' => 'blue', 'SELESAI' => 'green'];

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'jadwal';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik', 'SPMB', 'Kelola Jadwal Tes'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <div class="flex items-center gap-2 flex-wrap">
      <!-- Info jumlah jadwal -->
      <span class="badge badge-gray"><?= count($jadwalList) ?> jadwal</span>
    </div>
    <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Kelola Jadwal Tes</h1>
    <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">Jadwal tes CBT, wawancara BTQ, dan seleksi minat bakat. Kode jadwal dibuat otomatis (TES-01, TES-02, "¦).</p>

    <?php if ($galat): ?>
      <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
        <p class="font-semibold">Periksa isian berikut:</p>
        <ul class="mt-2 space-y-1 list-disc list-inside">
          <?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Tambah -->
    <section class="mt-6 card p-5 sm:p-6">
      <h2 class="font-bold text-ink-900">Tambah Jadwal Tes</h2>
      <form method="post" action="<?= url('app/operator/jadwal.php') ?>" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-8 items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <div class="lg:col-span-2">
          <label class="label" for="nama_kegiatan">Nama Kegiatan <span class="text-red-600">*</span></label>
          <input id="nama_kegiatan" name="nama_kegiatan" class="input" placeholder="Tes CBT Gelombang 1" required>
        </div>
        <div>
          <label class="label" for="tanggal">Tanggal <span class="text-red-600">*</span></label>
          <input id="tanggal" name="tanggal" type="date" class="input" required>
        </div>
        <div>
          <label class="label" for="jam_mulai">Jam Mulai</label>
          <input id="jam_mulai" name="jam_mulai" type="time" class="input" value="08:00" required>
        </div>
        <div>
          <label class="label" for="jam_selesai">Jam Selesai</label>
          <input id="jam_selesai" name="jam_selesai" type="time" class="input" value="10:00" required>
        </div>
        <div>
          <label class="label" for="mode">Metode</label>
          <select id="mode" name="mode" class="select">
            <option value="OFFLINE">Offline</option>
            <option value="DARING">Daring</option>
            <option value="VIDEO_CALL">Video Call</option>
          </select>
        </div>
        <div>
          <label class="label" for="ruangan">Ruangan</label>
          <input id="ruangan" name="ruangan" class="input" placeholder="Lab Komputer 1" value="">
        </div>
        <div class="flex items-end gap-3">
          <div class="flex-1">
            <label class="label" for="kapasitas">Kapasitas</label>
            <input id="kapasitas" name="kapasitas" type="number" min="0" max="1000" value="0" class="input" title="0 = tanpa batas">
          </div>
          <button type="submit" class="btn btn-primary shadow-drop shrink-0"><?= icon('add') ?> Tambah</button>
        </div>
        <input type="hidden" name="status" value="DRAFT">
        <input type="hidden" name="peserta_dijadwalkan" value="0">
      </form>
    </section>

    <!-- Daftar -->
    <section class="mt-6 card p-5 sm:p-6">
      <h2 class="font-bold text-ink-900">Daftar Jadwal</h2>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[960px]">
          <thead class="thead">
            <tr>
              <th class="th">Kode</th>
              <th class="th">Nama Kegiatan</th>
              <th class="th">Tanggal</th>
              <th class="th">Jam</th>
              <th class="th">Metode</th>
              <th class="th">Ruangan / Kapasitas</th>
              <th class="th text-right">Peserta</th>
              <th class="th">Status</th>
              <th class="th text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php $noBaris = 0; ?>
            <?php foreach ($jadwalList as $j): ?>
              <?php
              /* [PERBAIKAN] <form> TIDAK BOLEH jadi anak <tr> — parser HTML akan
               * mengosongkan form-nya dan membuang field ke luar, sehingga tombol
               * Simpan/Kirim WA/Hapus tidak punya form owner (klik = tidak terkirim).
               * Kini ketiga form berada di dalam <td> aksi, sedangkan input yang
               * tersebar di sel lain memakai atribut form="id" (HTML5). */
              $noBaris++;
              $fEdit = 'jf-' . $noBaris;
              $fWa   = 'jw-' . $noBaris;
              $fHapus = 'jh-' . $noBaris;
              ?>
              <tr class="tr-hover align-top">
                <td class="td font-mono text-[13px]"><?= e($j['kode']) ?></td>
                <td class="td"><input form="<?= e($fEdit) ?>" name="nama_kegiatan" value="<?= e($j['nama_kegiatan']) ?>" class="input h-8 w-full min-w-[180px]" required></td>
                <td class="td"><input form="<?= e($fEdit) ?>" name="tanggal" type="date" value="<?= e($j['tanggal']) ?>" class="input h-8 w-40" required></td>
                <td class="td whitespace-nowrap">
                  <div class="flex items-center gap-1">
                    <input form="<?= e($fEdit) ?>" name="jam_mulai" type="time" value="<?= e(substr($j['jam_mulai'], 0, 5)) ?>" class="input h-8 w-24" required>
                    <span class="text-ink-400">–</span>
                    <input form="<?= e($fEdit) ?>" name="jam_selesai" type="time" value="<?= e(substr($j['jam_selesai'], 0, 5)) ?>" class="input h-8 w-24" required>
                  </div>
                </td>
                <td class="td">
                  <select form="<?= e($fEdit) ?>" name="mode" class="select h-8 w-28">
                    <option value="OFFLINE" <?= $j['mode'] === 'OFFLINE' ? 'selected' : '' ?>>Offline</option>
                    <option value="DARING" <?= $j['mode'] === 'DARING' ? 'selected' : '' ?>>Daring</option>
                    <option value="VIDEO_CALL" <?= $j['mode'] === 'VIDEO_CALL' ? 'selected' : '' ?>>Video Call</option>
                  </select>
                </td>
                <td class="td whitespace-nowrap">
                  <input form="<?= e($fEdit) ?>" name="ruangan" value="<?= e($j['ruangan'] ?? '') ?>" placeholder="Ruangan" class="input h-8 w-32">
                  <input form="<?= e($fEdit) ?>" name="kapasitas" type="number" min="0" max="1000" value="<?= (int) ($j['kapasitas'] ?? 0) ?>" class="input h-8 w-20 mt-1" title="0 = tanpa batas">
                </td>
                <td class="td text-right"><input form="<?= e($fEdit) ?>" name="peserta_dijadwalkan" type="number" min="0" max="1000" value="<?= (int) $j['peserta_dijadwalkan'] ?>" class="input h-8 w-20 text-right ml-auto"></td>
                <td class="td">
                  <select form="<?= e($fEdit) ?>" name="status" class="select h-8 w-32">
                    <?php foreach ($labelStat as $k => $lab): ?>
                      <option value="<?= $k ?>" <?= $j['status'] === $k ? 'selected' : '' ?>><?= $lab ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td class="td text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <form id="<?= e($fEdit) ?>" method="post" action="<?= url('app/operator/jadwal.php') ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="ubah">
                      <input type="hidden" name="id" value="<?= e($j['id']) ?>">
                      <button type="submit" class="btn btn-sm btn-outline"><?= icon('save', 'text-[15px]') ?> Simpan</button>
                    </form>
                    <form id="<?= e($fWa) ?>" method="post" action="<?= url('app/operator/jadwal.php') ?>"
                          onsubmit="return confirm('Kirim notifikasi WA jadwal <?= e($j['kode']) ?> ke semua wali siswa terjadwal?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="kirim_notifikasi">
                      <input type="hidden" name="id" value="<?= e($j['id']) ?>">
                      <button type="submit" class="icon-box border border-line text-ink-500 hover:text-emerald-600 hover:border-emerald-300" aria-label="Kirim Notifikasi WA"><?= icon('chat', 'text-[17px]') ?></button>
                    </form>
                    <form id="<?= e($fHapus) ?>" method="post" action="<?= url('app/operator/jadwal.php') ?>" onsubmit="return confirm('Hapus jadwal <?= e($j['kode']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id" value="<?= e($j['id']) ?>">
                      <button type="submit" class="icon-box border border-line text-ink-500 hover:text-red-600 hover:border-red-300" aria-label="Hapus"><?= icon('delete', 'text-[17px]') ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$jadwalList): ?>
              <tr><td colspan="9" class="td text-center text-ink-400">Belum ada jadwal. Tambahkan lewat form di atas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <p class="hint mt-3">Jadwal yang sudah dipakai pada data seleksi tidak bisa dihapus.</p>
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
