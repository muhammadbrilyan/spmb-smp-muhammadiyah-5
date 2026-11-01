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

/** Pesan galat untuk satu isian kelas (dipakai form tambah, ubah, dan simpan massal). */
function kelas_pesan_galat($kode, $nama, $kuota, $kecualiId = null)
{
    $pesan = [];
    if ($kode === '' || !preg_match('/^[A-Z0-9]{1,10}$/', $kode)) {
        $pesan[] = 'Kode kelas wajib diisi (huruf/angka, maks 10 karakter), contoh: 7A.';
    }
    if ($nama === '') {
        $pesan[] = 'Nama kelas wajib diisi.';
    }
    if ($kuota < 1 || $kuota > 500) {
        $pesan[] = 'Kuota harus antara 1 dan 500.';
    }
    if (!$pesan && kelas_kode_dipakai($kode, $kecualiId)) {
        $pesan[] = 'Kode kelas "' . e($kode) . '" sudah dipakai.';
    }
    return $pesan;
}

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

        $galat = kelas_pesan_galat($kode, $nama, $kuota, $aksi === 'ubah' ? $id : null);

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
    } elseif ($aksi === 'simpan_massal') {
        /* Seluruh baris Daftar Rombel dikirim sekali tekan. Bila ada satu baris
         * yang tidak valid, tidak ada yang disimpan — operator memperbaiki
         * baris tersebut lalu menekan Simpan Semua lagi. */
        $kirim = is_array($_POST['rows'] ?? null) ? $_POST['rows'] : [];
        $siap  = [];
        foreach ($kirim as $r) {
            if (!is_array($r)) {
                continue;
            }
            $id = trim((string) ($r['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $siap[$id] = [
                'kode'  => strtoupper(trim((string) ($r['kode'] ?? ''))),
                'nama'  => trim((string) ($r['nama_kelas'] ?? '')),
                'wali'  => trim((string) ($r['wali_kelas'] ?? '')),
                'kuota' => (int) ($r['kuota'] ?? 0),
                'aktif' => !empty($r['is_aktif']) ? 1 : 0,
            ];
        }

        $jumlahKode = array_count_values(array_column($siap, 'kode'));
        $pesan      = [];
        foreach ($siap as $id => $d) {
            $penanda = $d['kode'] !== '' ? $d['kode'] : '(baris tanpa kode)';
            foreach (kelas_pesan_galat($d['kode'], $d['nama'], $d['kuota'], $id) as $g) {
                $pesan[] = $penanda . ' — ' . $g;
            }
        }
        foreach ($jumlahKode as $kode => $n) {
            if ($kode !== '' && $n > 1) {
                $pesan[] = $kode . ' — kode yang sama dipakai pada lebih dari satu baris.';
            }
        }
        $pesan = array_values(array_unique($pesan));

        if ($pesan) {
            $flashx = $pesan;
        } else {
            if (!$siap) {
                flash('error', 'Tidak ada data kelas yang dikirim.');
            } else {
                $berhasil = 0;
                foreach ($siap as $id => $d) {
                    if (kelas_ubah($id, $d['kode'], $d['nama'], $d['wali'], $d['kuota'], $d['aktif'])) {
                        $berhasil++;
                    }
                }
                if ($berhasil === count($siap)) {
                    flash('sukses', count($siap) . ' kelas tersimpan.');
                } else {
                    flash('error', 'Sebagian kelas gagal tersimpan (' . $berhasil . ' dari ' . count($siap) . ').');
                }
            }
            header('Location: ' . url('app/operator/kelas.php'));
            exit;
        }
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

$flashToast = flash(); /* diambil sekali agar pesan sukses tidak tampil lagi */

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
      <p class="mt-1 text-[13px] text-ink-500">
        Isi kode, nama, dan kuota kelas. Kelas yang tidak dicentang <em>Aktif</em> tetap tersimpan
        tetapi tidak dihitung sebagai kuota pendaftaran.
      </p>
      <form method="post" action="<?= url('app/operator/kelas.php') ?>" class="mt-4 grid gap-4 sm:grid-cols-2">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <div>
          <label class="label" for="kode">Kode Kelas <span class="text-red-600">*</span></label>
          <input id="kode" name="kode" class="input font-mono uppercase" placeholder="7D" maxlength="10" required>
          <p class="hint">Huruf dan angka, maksimal 10 karakter.</p>
        </div>
        <div>
          <label class="label" for="nama_kelas">Nama Kelas <span class="text-red-600">*</span></label>
          <input id="nama_kelas" name="nama_kelas" class="input" placeholder="Kelas VII-D (Reguler)" required>
          <p class="hint">Nama lengkap yang tampil di kartu penempatan siswa.</p>
        </div>
        <div>
          <label class="label" for="kuota">Kuota <span class="text-red-600">*</span></label>
          <input id="kuota" name="kuota" type="number" min="1" max="500" value="30" class="input" required>
          <p class="hint">Jumlah siswa maksimal kelas ini (1–500).</p>
        </div>
        <div>
          <label class="label" for="wali_kelas">Wali Kelas</label>
          <input id="wali_kelas" name="wali_kelas" class="input" placeholder="Nama wali (opsional)">
        </div>
        <div class="sm:col-span-2 flex flex-wrap items-center gap-3">
          <label class="inline-flex items-center gap-2 text-sm text-ink-700" for="is_aktif">
            <input id="is_aktif" type="checkbox" name="is_aktif" value="1" checked class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
            Aktif
          </label>
          <button type="submit" class="btn btn-primary shadow-drop ml-auto"><?= icon('add') ?> Tambah Kelas</button>
        </div>
      </form>
    </section>

    <!-- Tabel kelas -->
    <?php /* Seluruh baris menjadi satu formulir (aksi=simpan_massal). Kolom
           * "Aksi" hanya berisi tombol hapus — menyimpan tidak lagi per baris.
           * Atribut form pada tiap input menautkannya ke <form id="rombel-all">
           * yang berada di luar tabel, sehingga struktur <tr> tetap valid. */ ?>
    <section class="mt-6 card p-5 sm:p-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 class="font-bold text-ink-900">Daftar Rombel</h2>
          <p class="mt-1 text-[13px] text-ink-500">
            Ubah beberapa kelas sekaligus, lalu tekan <strong>Simpan Semua</strong>.
          </p>
        </div>
        <div class="flex items-center gap-3">
          <span id="rombel-berubah" class="badge badge-amber" hidden>Belum disimpan</span>
          <button type="submit" form="rombel-all" data-simpan-all class="btn btn-primary shadow-drop" <?= !$kelasList ? 'disabled' : '' ?>>
            <?= icon('save', 'text-[15px]') ?> Simpan Semua
          </button>
        </div>
      </div>

      <form id="rombel-all" method="post" action="<?= url('app/operator/kelas.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan_massal">
      </form>

      <div class="mt-4 overflow-x-auto" id="rombel-tabel">
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
              <?php $noBaris++; ?>
              <tr class="tr-hover">
                <?php /* Semua input memakai nama rows[<n>][...] agar satu kiriman
                       * membawa seluruh daftar dan dapat diproses sekali loop. */ ?>
                <td class="td"><input form="rombel-all" name="rows[<?= $noBaris ?>][kode]" value="<?= e($k['kode']) ?>" class="input h-8 font-mono uppercase w-20" maxlength="10" required></td>
                <td class="td"><input form="rombel-all" name="rows[<?= $noBaris ?>][nama_kelas]" value="<?= e($k['nama_kelas']) ?>" class="input h-8 w-full min-w-[180px]" required></td>
                <td class="td"><input form="rombel-all" name="rows[<?= $noBaris ?>][wali_kelas]" value="<?= e($k['wali_kelas']) ?>" class="input h-8 w-full min-w-[140px]"></td>
                <td class="td text-right"><input form="rombel-all" name="rows[<?= $noBaris ?>][kuota]" type="number" min="1" max="500" value="<?= (int) $k['kuota'] ?>" class="input h-8 w-20 text-right ml-auto" required></td>
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
                    <input form="rombel-all" type="checkbox" name="rows[<?= $noBaris ?>][is_aktif]" value="1" <?= $k['is_aktif'] ? 'checked' : '' ?> class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
                    <?= $k['is_aktif'] ? 'Aktif' : 'Nonaktif' ?>
                  </label>
                </td>
                <td class="td text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-1.5">
                    <input form="rombel-all" type="hidden" name="rows[<?= $noBaris ?>][id]" value="<?= e($k['id']) ?>">
                    <form method="post" action="<?= url('app/operator/kelas.php') ?>" onsubmit="return confirm('Hapus kelas <?= e($k['kode']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus">
                      <input type="hidden" name="id" value="<?= e($k['id']) ?>">
                      <button type="submit" class="icon-box border border-line text-ink-500 hover:text-red-600 hover:border-red-300" aria-label="Hapus kelas <?= e($k['kode']) ?>"><?= icon('delete', 'text-[17px]') ?></button>
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
  window.APP_FLASH = <?= json_encode($flashToast, JSON_UNESCAPED_UNICODE) ?>;
  (function () {
    /* Input pada tabel tidak berada di dalam <form>, jadi perubahan ditangkap
     * dari pembungkus tabel untuk menandai adanya edits yang belum disimpan. */
    var ruang = document.getElementById('rombel-tabel');
    var chip  = document.getElementById('rombel-berubah');
    var tombol = document.querySelectorAll('[data-simpan-all]');
    if (!ruang || !chip) return;
    function tandai() {
      chip.hidden = false;
      tombol.forEach(function (b) { b.disabled = false; });
    }
    ruang.addEventListener('input', tandai);
    ruang.addEventListener('change', tandai);
    document.querySelectorAll('form').forEach(function (f) {
      f.addEventListener('submit', function () { chip.hidden = true; });
    });
    window.addEventListener('beforeunload', function (ev) {
      if (!chip.hidden) {
        ev.preventDefault();
        ev.returnValue = '';
      }
    });
  })();
</script>
</body>
</html>
