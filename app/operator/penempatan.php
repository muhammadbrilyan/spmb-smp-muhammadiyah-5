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

$title = 'Penempatan Kelas';
$desc  = 'Tempatkan siswa LULUS ke rombel sesuai kuota.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$galat = [];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        header('Location: ' . url('app/operator/penempatan.php'));
        exit;
    }
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tempatkan') {
        $pid    = trim((string) ($_POST['pendaftar_id'] ?? ''));
        $kid    = trim((string) ($_POST['kelas_id'] ?? ''));
        $catatan = trim((string) ($_POST['catatan'] ?? ''));
        if ($pid === '' || $kid === '') {
            $galat[] = 'Pilih siswa dan kelas terlebih dahulu.';
        } else {
            if (penempatan_simpan($pid, $kid, $catatan)) {
                flash('sukses', 'Siswa berhasil ditempatkan ke kelas.');
            } else {
                flash('error', 'Kelas sudah penuh atau tidak valid. Pilih kelas lain.');
            }
            header('Location: ' . url('app/operator/penempatan.php'));
            exit;
        }
    } elseif ($aksi === 'pindah') {
        $ptmId  = trim((string) ($_POST['penempatan_id'] ?? ''));
        $kid    = trim((string) ($_POST['kelas_id'] ?? ''));
        $catatan = trim((string) ($_POST['catatan'] ?? ''));
        $detail = q_all('SELECT pendaftar_id FROM penempatan_kelas WHERE id = ?', [$ptmId]);
        $pid = $detail[0]['pendaftar_id'] ?? null;
        if (!$pid || $kid === '') {
            $galat[] = 'Data penempatan tidak ditemukan.';
        } else {
            if (penempatan_simpan($pid, $kid, $catatan)) {
                flash('sukses', 'Siswa berhasil dipindah ke kelas baru.');
            } else {
                flash('error', 'Kelas tujuan sudah penuh.');
            }
            header('Location: ' . url('app/operator/penempatan.php'));
            exit;
        }
    } elseif ($aksi === 'otomatis') {
        $r = penempatan_otomatis();
        if ($r['ok'] > 0) {
            flash('sukses', $r['ok'] . ' siswa berhasil ditempatkan otomatis.'
                . ($r['gagal'] > 0 ? ' ' . $r['gagal'] . ' gagal (kelas penuh).' : ''));
        } else {
            flash('error', $r['detail'][0] ?? 'Tidak ada siswa yang bisa ditempatkan otomatis.');
        }
        header('Location: ' . url('app/operator/penempatan.php'));
        exit;
    } elseif ($aksi === 'hapus') {
        $ptmId = trim((string) ($_POST['penempatan_id'] ?? ''));
        $okHapus = penempatan_hapus($ptmId);
        flash($okHapus ? 'sukses' : 'error',
            $okHapus ? 'Penempatan dibatalkan.' : 'Gagal membatalkan penempatan.');
        header('Location: ' . url('app/operator/penempatan.php'));
        exit;
    }
}

$kelasList = penempatan_kelas_opsi();
$belumDitempatkan = penempatan_belum();
$tertempat = penempatan_daftar();

$totalKuota = 0; $totalTerisi = 0;
foreach ($kelasList as $k) { $totalKuota += (int) $k['kuota']; $totalTerisi += (int) $k['terisi']; }

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'penempatan';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik', 'SPMB', 'Penempatan Kelas'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <div class="flex items-center gap-2 flex-wrap">
      <!-- Info jumlah siswa ditempatkan / menunggu -->
      <span class="badge badge-gray"><?= count($tertempat) ?> ditempatkan · <?= count($belumDitempatkan) ?> menunggu</span>
    </div>
    <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Penempatan Kelas</h1>
    <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
      Tempatkan siswa yang berstatus <strong>LULUS</strong> ke rombel. Kuota tiap kelas dibatasi — pindah atau batalkan bila perlu.
    </p>

    <?php if ($galat): ?>
      <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
        <?php foreach ($galat as $g): ?><p><?= e($g) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <!-- Kartu keterisian -->
    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <?php foreach ($kelasList as $k):
        $persen = $k['kuota'] > 0 ? round($k['terisi'] / $k['kuota'] * 100) : 0;
        $sisa   = max(0, (int) $k['kuota'] - (int) $k['terisi']);
        $tone   = $persen >= 100 ? 'bg-red-500' : ($persen >= 80 ? 'bg-amber-500' : 'bg-primary');
      ?>
        <div class="card p-4">
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="text-lg font-extrabold text-ink-900"><?= e($k['kode']) ?></p>
              <p class="text-[13px] text-ink-500 truncate"><?= e($k['nama_kelas']) ?></p>
            </div>
            <span class="badge <?= $sisa > 0 ? 'badge-green' : 'badge-red' ?>"><?= $sisa > 0 ? $sisa . ' kursi' : 'Penuh' ?></span>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <div class="flex-1 h-2.5 rounded-full bg-line-soft overflow-hidden">
              <div class="h-full rounded-full <?= $tone ?>" style="width: <?= min($persen, 100) ?>%"></div>
            </div>
            <span class="text-xs font-bold text-ink-700 w-20 text-right"><?= (int) $k['terisi'] ?>/<?= (int) $k['kuota'] ?></span>
          </div>
          <p class="mt-2 text-[12px] text-ink-400">Wali: <?= e($k['wali_kelas'] ?: '—') ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">

      <!-- Siswa LULUS belum ditempatkan -->
      <section class="card p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="font-bold text-ink-900">Siswa Lulus Belum Ditempatkan</h2>
            <p class="hint mt-1">Urut nilai akhir tertinggi.</p>
          </div>
          <?php if ($belumDitempatkan): ?>
            <form method="post" action="<?= url('app/operator/penempatan.php') ?>"
                  onsubmit="return confirm('Tempatkan semua <?= count($belumDitempatkan) ?> siswa LULUS ke kelas dengan sisa kuota (urut nilai)?');">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="otomatis">
              <button type="submit" class="btn btn-sm btn-primary shadow-drop">
                <?= icon('auto_awesome', 'text-[15px]') ?> Tempatkan Semua (<?= count($belumDitempatkan) ?>)
              </button>
            </form>
          <?php endif; ?>
        </div>
        <?php if (!$belumDitempatkan): ?>
          <p class="mt-4 text-sm text-ink-400">Semua siswa LULUS sudah ditempatkan. ✓</p>
        <?php else: ?>
          <div class="mt-4 space-y-3">
            <?php foreach ($belumDitempatkan as $b): ?>
              <div class="rounded-2xl border border-line p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="font-semibold text-ink-900 truncate"><?= e($b['nama_siswa']) ?></p>
                    <p class="text-[13px] text-ink-500">
                      <span class="font-mono"><?= e($b['no_pendaftaran']) ?></span>
                      · Nilai <?= e(number_format((float) $b['nilai_akhir'], 1)) ?>
                      · Jalur <?= e($b['jalur'] ?: 'Reguler') ?>
                    </p>
                  </div>
                </div>
                <form method="post" action="<?= url('app/operator/penempatan.php') ?>" class="mt-3 flex items-end gap-2">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="tempatkan">
                  <input type="hidden" name="pendaftar_id" value="<?= e($b['id']) ?>">
                  <div class="flex-1 min-w-0">
                    <label class="label" for="kid-<?= e($b['id']) ?>">Kelas Tujuan</label>
                    <select id="kid-<?= e($b['id']) ?>" name="kelas_id" class="select h-9 w-full" required>
                      <option value="">— pilih —</option>
                      <?php foreach ($kelasList as $k): $sisa = (int)$k['kuota'] - (int)$k['terisi']; ?>
                        <option value="<?= e($k['id']) ?>" <?= $sisa <= 0 ? 'disabled' : '' ?>>
                          <?= e($k['kode']) ?> (<?= (int)$k['terisi'] ?>/<?= (int)$k['kuota'] ?>)<?= $sisa <= 0 ? ' — Penuh' : '' ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <button type="submit" class="btn btn-sm btn-primary shrink-0"><?= icon('move_to_inbox', 'text-[15px]') ?> Tempatkan</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Siswa sudah ditempatkan -->
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Siswa Sudah Ditempatkan</h2>
        <?php if (!$tertempat): ?>
          <p class="mt-4 text-sm text-ink-400">Belum ada siswa yang ditempatkan.</p>
        <?php else: ?>
          <div class="mt-4 space-y-3">
            <?php foreach ($tertempat as $t): ?>
              <div class="rounded-2xl border border-line p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                  <div class="min-w-0 flex-1">
                    <p class="font-semibold text-ink-900 truncate"><?= e($t['nama_siswa']) ?></p>
                    <p class="text-[13px] text-ink-500">
                      <span class="font-mono"><?= e($t['no_pendaftaran']) ?></span>
                      · ditempatkan di <strong class="text-ink-900"><?= e($t['kode']) ?></strong>
                      (<?= e($t['nama_kelas']) ?>)
                    </p>
                    <p class="text-[12px] text-ink-400 mt-0.5">Wali: <?= e($t['wali_kelas'] ?: '—') ?> · <?= e(bulan($t['created_at'])) ?></p>
                  </div>
                  <div class="flex gap-2 shrink-0">
                    <button type="button" class="btn btn-sm btn-outline" data-modal-open="modal-pindah"
                            data-ptm="<?= e($t['id']) ?>" data-nama="<?= e($t['nama_siswa']) ?>" data-kode="<?= e($t['kode']) ?>">
                      <?= icon('swap_horiz', 'text-[15px]') ?> Pindah
                    </button>
                    <form method="post" action="<?= url('app/operator/penempatan.php') ?>" onsubmit="return confirm('Batalkan penempatan <?= e($t['nama_siswa']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="penempatan_id" value="<?= e($t['id']) ?>">
                      <button type="submit" class="icon-box border border-line text-ink-500 hover:text-red-600 hover:border-red-300" aria-label="Batalkan"><?= icon('delete', 'text-[17px]') ?></button>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

    </div>
  </main>
</div>

<!-- Modal pindah kelas -->
<div id="modal-pindah" class="fixed inset-0 z-[60] hidden">
  <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-[2px]" data-modal-close></div>
  <div class="relative mx-auto mt-[12vh] w-[min(420px,92vw)] card p-5 sm:p-6 shadow-modal animate-pop">
    <div class="flex items-start justify-between gap-3">
      <h3 class="font-bold text-ink-900">Pindah Kelas — <span id="mp-nama" class="text-primary"></span></h3>
      <button class="icon-box border border-line text-ink-500" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
    </div>
    <form method="post" action="<?= url('app/operator/penempatan.php') ?>" class="mt-4 space-y-4">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="pindah">
      <input type="hidden" name="penempatan_id" id="mp-id" value="">
      <div>
        <label class="label" for="mp-kelas">Kelas Tujuan Baru</label>
        <select id="mp-kelas" name="kelas_id" class="select w-full" required>
          <option value="">— pilih —</option>
          <?php foreach ($kelasList as $k): $sisa = (int)$k['kuota'] - (int)$k['terisi']; ?>
            <option value="<?= e($k['id']) ?>" <?= $sisa <= 0 ? 'disabled' : '' ?>>
              <?= e($k['kode']) ?> (<?= (int)$k['terisi'] ?>/<?= (int)$k['kuota'] ?>)<?= $sisa <= 0 ? ' — Penuh' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="label" for="mp-catatan">Catatan (opsional)</label>
        <input id="mp-catatan" name="catatan" class="input" placeholder="Alasan pemindahan…">
      </div>
      <div class="flex justify-end gap-3">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary shadow-drop"><?= icon('swap_horiz') ?> Pindahkan</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
  document.querySelectorAll('[data-modal-open="modal-pindah"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('mp-id').value = btn.dataset.ptm || '';
      document.getElementById('mp-nama').textContent = btn.dataset.nama || '';
    });
  });
</script>
</body>
</html>
