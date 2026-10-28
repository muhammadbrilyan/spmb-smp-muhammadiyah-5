<?php
/* ==================================================================
 * DAFTAR BERKAS (JENIS DOKUMEN) — CRUD operator (Fase 5).
 * Akses dari Pengaturan Sistem. Daftar final menyusul — data awal
 * adalah salinan jenis yang selama ini hard-coded.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
        header('Location: jenis-dokumen.php');
        exit;
    }
    $aksi = (string) ($_POST['aksi'] ?? '');
    if ($aksi === 'simpan') {
        $hasil = jenis_dokumen_simpan([
            'kode'             => (string) ($_POST['kode'] ?? ''),
            'nama'             => (string) ($_POST['nama'] ?? ''),
            'wajib'            => !empty($_POST['wajib']),
            'aktif'            => !empty($_POST['aktif']),
            'format_diizinkan' => (string) ($_POST['format_diizinkan'] ?? ''),
            'maks_kb'          => (int) ($_POST['maks_kb'] ?? 2048),
            'urutan'           => (int) ($_POST['urutan'] ?? 0),
        ]);
        flash($hasil['ok'] ? 'sukses' : 'error', $hasil['ok'] ? 'Jenis berkas tersimpan.' : ($hasil['pesan'] ?? 'Gagal menyimpan.'));
    } elseif ($aksi === 'toggle') {
        $kode = (string) ($_POST['kode'] ?? '');
        $aktifSekarang = (int) (q_col('SELECT aktif FROM jenis_dokumen WHERE kode = ?', [$kode]) ?? -1);
        if ($aktifSekarang === -1) {
            flash('error', 'Jenis berkas tidak ditemukan.');
        } elseif ($aktifSekarang === 0) {
            jenis_dokumen_aktifkan($kode);
            flash('sukses', 'Jenis berkas diaktifkan kembali.');
        } else {
            jenis_dokumen_nonaktifkan($kode);
            flash('sukses', 'Jenis berkas dinonaktifkan (tidak tampil di portal siswa).');
        }
    }
    header('Location: jenis-dokumen.php');
    exit;
}

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title     = 'Daftar Berkas Persyaratan';
$desc      = 'Kelola jenis berkas persyaratan yang diminta dari calon siswa.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'jenis-dokumen';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik' => 'SPMB', 'Daftar Berkas' => null];
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
$semua = jenis_dokumen_semua();
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <a href="<?= url('app/operator/pengaturan.php') ?>" class="text-[13px] text-primary hover:underline inline-flex items-center gap-1"><?= icon('arrow_back', 'text-[16px]') ?> Pengaturan Sistem</a>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-ink-900">Daftar Berkas Persyaratan</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Jenis berkas yang diminta dari calon siswa di portal. Daftar final menyusul — data awal dapat diubah di sini.
          Menonaktifkan jenis tidak menghapus berkas yang sudah terunggah.
        </p>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <!-- Form tambah / edit -->
    <section class="mt-6 card p-5 sm:p-6">
      <h2 class="font-bold text-ink-900">Tambah / Ubah Jenis Berkas</h2>
      <p class="mt-1 text-[13px] text-ink-500">Isi <strong>kode</strong> yang sudah ada (huruf besar) untuk mengubah jenis tersebut.</p>
      <form method="post" action="jenis-dokumen.php" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="simpan">
        <div>
          <label class="label" for="kode">Kode (huruf besar/angka/underscore)</label>
          <input id="kode" name="kode" type="text" class="input font-mono" placeholder="AKTA" maxlength="30" required>
        </div>
        <div>
          <label class="label" for="nama">Nama Tampilan</label>
          <input id="nama" name="nama" type="text" class="input" placeholder="Akta Kelahiran" maxlength="100" required>
        </div>
        <div>
          <label class="label" for="format_diizinkan">Format Diizinkan (koma)</label>
          <input id="format_diizinkan" name="format_diizinkan" type="text" class="input font-mono text-[13px]" placeholder="jpg,jpeg,png,pdf" value="jpg,jpeg,png,pdf">
        </div>
        <div>
          <label class="label" for="maks_kb">Ukuran Maks (KB)</label>
          <input id="maks_kb" name="maks_kb" type="number" class="input" value="2048" min="64" max="10240">
        </div>
        <div>
          <label class="label" for="urutan">Urutan Tampil</label>
          <input id="urutan" name="urutan" type="number" class="input" value="0" min="0" max="9999">
        </div>
        <div class="flex items-end gap-5 pb-1">
          <label class="flex items-center gap-2 text-sm text-ink-700 cursor-pointer">
            <input type="checkbox" name="wajib" value="1" class="w-4 h-4 accent-primary" checked> Wajib
          </label>
          <label class="flex items-center gap-2 text-sm text-ink-700 cursor-pointer">
            <input type="checkbox" name="aktif" value="1" class="w-4 h-4 accent-primary" checked> Aktif
          </label>
        </div>
        <div class="sm:col-span-2 lg:col-span-3 flex justify-end">
          <button type="submit" class="btn btn-primary shadow-drop"><?= icon('save') ?> Simpan Jenis Berkas</button>
        </div>
      </form>
    </section>

    <!-- Tabel daftar -->
    <section class="mt-6 card overflow-hidden">
      <div class="overflow-x-auto scroll-thin">
        <table class="w-full min-w-[860px] border-collapse">
          <thead class="thead">
            <tr>
              <th class="th">Urut</th>
              <th class="th">Kode</th>
              <th class="th">Nama</th>
              <th class="th">Wajib</th>
              <th class="th">Format</th>
              <th class="th text-right">Maks KB</th>
              <th class="th">Status</th>
              <th class="th text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <?php if (!$semua): ?>
              <tr><td class="td text-center text-ink-400" colspan="8">Belum ada jenis berkas.</td></tr>
            <?php endif; ?>
            <?php foreach ($semua as $j): ?>
              <tr class="tr-hover">
                <td class="td text-[13px] text-ink-500"><?= (int) $j['urutan'] ?></td>
                <td class="td font-mono text-[13px] font-semibold"><?= e($j['kode']) ?></td>
                <td class="td font-semibold text-ink-900"><?= e($j['nama']) ?></td>
                <td class="td"><?= $j['wajib'] ? badge('Wajib', 'red') : badge('Opsional', 'gray') ?></td>
                <td class="td font-mono text-[12px] text-ink-500"><?= e($j['format_diizinkan']) ?></td>
                <td class="td text-right text-[13px]"><?= (int) $j['maks_kb'] ?></td>
                <td class="td"><?= $j['aktif'] ? badge('Aktif', 'green') : badge('Nonaktif', 'gray') ?></td>
                <td class="td text-right">
                  <div class="inline-flex items-center gap-2 justify-end">
                    <button class="btn btn-sm btn-outline"
                            data-modal-open="modal-isi-jenis"
                            data-md-kode="<?= e($j['kode']) ?>"
                            data-md-nama="<?= e($j['nama']) ?>"
                            data-md-format="<?= e($j['format_diizinkan']) ?>"
                            data-md-maks="<?= (int) $j['maks_kb'] ?>"
                            data-md-urutan="<?= (int) $j['urutan'] ?>"
                            data-md-wajib="<?= (int) $j['wajib'] ?>">
                      <?= icon('edit', 'text-[15px]') ?> Ubah
                    </button>
                    <form method="post" action="jenis-dokumen.php" class="inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="toggle">
                      <input type="hidden" name="kode" value="<?= e($j['kode']) ?>">
                      <button type="submit" class="btn btn-sm <?= $j['aktif'] ? 'btn-danger' : 'btn-green' ?>">
                        <?= $j['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>
</div>

<!-- Modal isi (edit) -->
<div id="modal-isi-jenis" class="modal-root hidden fixed inset-0 z-[60]" role="dialog" aria-modal="true">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
    <form method="post" action="jenis-dokumen.php"
          class="animate-pop w-full max-w-lg bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan">
      <input type="hidden" name="kode" data-md-fill="kode" value="">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
        <div>
          <h3 class="font-bold text-ink-900 text-lg leading-tight">Ubah Jenis Berkas</h3>
          <p class="text-[13px] text-ink-500">Kode tidak dapat diubah (identitas).</p>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5 space-y-4">
        <div>
          <label class="label">Nama Tampilan</label>
          <input name="nama" type="text" class="input" data-md-fill="nama" maxlength="100" required>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label">Format (koma)</label>
            <input name="format_diizinkan" type="text" class="input font-mono text-[13px]" data-md-fill="format" required>
          </div>
          <div>
            <label class="label">Maks KB</label>
            <input name="maks_kb" type="number" class="input" data-md-fill="maks" min="64" max="10240">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4 items-end">
          <div>
            <label class="label">Urutan Tampil</label>
            <input name="urutan" type="number" class="input" data-md-fill="urutan" min="0" max="9999">
          </div>
          <label class="flex items-center gap-2 text-sm text-ink-700 cursor-pointer pb-2">
            <input type="checkbox" name="wajib" value="1" class="w-4 h-4 accent-primary" data-md-fill-check="wajib"> Wajib
          </label>
        </div>
        <label class="flex items-center gap-2 text-sm text-ink-700 cursor-pointer">
          <input type="checkbox" name="aktif" value="1" class="w-4 h-4 accent-primary" checked> Aktif
        </label>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<?php if ($flash): ?>
<script>window.APP_FLASH = <?= json_encode(['tipe' => $flash['tipe'], 'pesan' => $flash['pesan']], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php endif; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
/* Isi modal edit dari data-md-* (pola sama dengan modal lain). */
(function () {
  document.querySelectorAll('[data-modal-open="modal-isi-jenis"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var modal = document.getElementById('modal-isi-jenis');
      ['kode', 'nama', 'format', 'maks', 'urutan'].forEach(function (k) {
        var el = modal.querySelector('[data-md-fill="' + k + '"]');
        if (el) el.value = btn.getAttribute('data-md-' + k) || '';
      });
      var chk = modal.querySelector('[data-md-fill-check="wajib"]');
      if (chk) chk.checked = btn.getAttribute('data-md-wajib') === '1';
    });
  });
})();
</script>
</body>
</html>
