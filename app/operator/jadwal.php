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
                    . ($r['gagal'] > 0 ? ' ' . $r['gagal'] . ' gagal — ' . ($r['detail'][0] ?? '') : ''));
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

$hitungStat = ['DRAFT' => 0, 'TERJADWAL' => 0, 'SELESAI' => 0];
foreach ($jadwalList as $j) {
    $kStat = $j['status'] ?? 'DRAFT';
    if (isset($hitungStat[$kStat])) {
        $hitungStat[$kStat]++;
    }
}

/* Simpanan gagal ditampilkan lagi di modal — cari kode jadwalnya untuk judul. */
$kodeGagal = '';
if ($galat) {
    foreach ($jadwalList as $j) {
        if ((string) $j['id'] === (string) ($_POST['id'] ?? '')) {
            $kodeGagal = $j['kode'];
            break;
        }
    }
}

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'jadwal';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik', 'SPMB', 'Kelola Jadwal Tes'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1000px]">

    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-ink-900">Kelola Jadwal Tes</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Jadwal tes CBT, wawancara BTQ, dan seleksi minat bakat. Kode dibuat otomatis (TES-01, TES-02, dan seterusnya)
          saat jadwal baru disimpan.
        </p>
      </div>
      <button type="button" class="btn btn-primary shadow-drop shrink-0"
              data-modal-open="modal-jadwal"
              data-md-aksi="tambah" data-md-id=""
              data-md-nama="" data-md-tanggal=""
              data-md-jam1="08:00" data-md-jam2="10:00"
              data-md-mode="OFFLINE" data-md-status="DRAFT"
              data-md-ruangan="" data-md-kapasitas="0" data-md-peserta="0">
        <?= icon('add') ?> Tambah Jadwal
      </button>
    </div>

    <?php if ($galat): ?>
      <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
        <p class="font-semibold">Periksa isian berikut:</p>
        <ul class="mt-2 space-y-1 list-disc list-inside">
          <?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <!-- Ringkasan: satu angka per status -->
    <div class="mt-5 grid gap-3 grid-cols-3">
      <?php foreach ($labelStat as $k => $lab): ?>
        <div class="rounded-xl border border-line bg-white px-3 py-2.5">
          <p class="flex items-center gap-1.5 text-[12px] text-ink-500"><?= dot($toneStat[$k]) ?> <?= e($lab) ?></p>
          <p class="mt-0.5 text-2xl font-bold leading-tight text-ink-900"><?= (int) $hitungStat[$k] ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Daftar jadwal -->
    <section class="mt-6 card overflow-hidden">
      <div class="px-5 pt-5 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold text-ink-900">Daftar Jadwal <span class="text-ink-400 font-normal">(<?= count($jadwalList) ?>)</span></h2>
        <p class="text-[12px] text-ink-500">Urut dari tanggal terdekat</p>
      </div>

      <?php if (!$jadwalList): ?>
        <div class="px-5 py-10 text-center">
          <p class="text-ink-400"><?= icon('event_busy', 'text-[28px]') ?></p>
          <p class="mt-2 text-sm font-semibold text-ink-700">Belum ada jadwal tes</p>
          <p class="mt-1 text-[13px] text-ink-500">Tekan <strong>Tambah Jadwal</strong> di atas untuk membuat jadwal pertama.</p>
        </div>
      <?php else: ?>
        <ul class="mt-2 divide-y divide-line-soft">
          <?php foreach ($jadwalList as $j): ?>
            <?php
            /* "3 Nov" dari bulan_pendek() dipecah jadi angka + nama bulan. */
            $bagiTgl = explode(' ', bulan_pendek($j['tanggal']));
            $ruanganJ = trim((string) ($j['ruangan'] ?? ''));
            $kapJ     = (int) ($j['kapasitas'] ?? 0);
            $pesJ     = (int) ($j['peserta_dijadwalkan'] ?? 0);
            ?>
            <li class="px-5 py-4 flex flex-wrap items-start gap-3 hover:bg-canvas">
              <div class="w-12 shrink-0 rounded-xl border border-line bg-canvas py-2 text-center">
                <p class="text-[16px] font-bold leading-tight text-ink-900"><?= e($bagiTgl[0] ?? '') ?></p>
                <p class="text-[11px] leading-tight text-ink-500"><?= e($bagiTgl[1] ?? '') ?></p>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold leading-snug text-ink-900"><?= e($j['nama_kegiatan']) ?></p>
                  <?= badge($labelStat[$j['status']] ?? $j['status'], $toneStat[$j['status']] ?? 'gray') ?>
                  <span class="badge font-mono text-[11px] bg-canvas text-ink-500 border-line"><?= e($j['kode']) ?></span>
                </div>
                <p class="mt-1 text-[13px] leading-snug text-ink-500">
                  <?= e(bulan($j['tanggal'])) ?> · <?= e(substr($j['jam_mulai'], 0, 5)) ?>–<?= e(substr($j['jam_selesai'], 0, 5)) ?> WIB ·
                  <?= e($labelMode[$j['mode']] ?? $j['mode']) ?><?php if ($ruanganJ !== ''): ?> · <?= e($ruanganJ) ?><?php endif; ?>
                </p>
                <p class="mt-1 text-[12px] leading-snug text-ink-500">
                  <?= icon('group', 'text-[14px] align-middle') ?> <?= $pesJ ?> peserta terjadwal<?= $kapJ > 0 ? ' · kapasitas ' . $kapJ : ' · kapasitas bebas' ?>
                </p>
              </div>

              <div class="flex items-center gap-2 shrink-0 ml-auto">
                <button type="button" class="btn btn-sm btn-outline"
                        data-modal-open="modal-jadwal"
                        data-md-aksi="ubah"
                        data-md-id="<?= e($j['id']) ?>"
                        data-md-kode="<?= e($j['kode']) ?>"
                        data-md-nama="<?= e($j['nama_kegiatan']) ?>"
                        data-md-tanggal="<?= e($j['tanggal']) ?>"
                        data-md-jam1="<?= e(substr($j['jam_mulai'], 0, 5)) ?>"
                        data-md-jam2="<?= e(substr($j['jam_selesai'], 0, 5)) ?>"
                        data-md-mode="<?= e($j['mode']) ?>"
                        data-md-status="<?= e($j['status']) ?>"
                        data-md-ruangan="<?= e($ruanganJ) ?>"
                        data-md-kapasitas="<?= $kapJ ?>"
                        data-md-peserta="<?= $pesJ ?>">
                  <?= icon('edit', 'text-[15px]') ?> Ubah
                </button>
                <form method="post" action="<?= url('app/operator/jadwal.php') ?>"
                      onsubmit="return confirm('Kirim notifikasi WA jadwal <?= e($j['kode']) ?> ke semua wali siswa terjadwal?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="kirim_notifikasi">
                  <input type="hidden" name="id" value="<?= e($j['id']) ?>">
                  <button type="submit" class="icon-box border border-line text-ink-500 hover:text-emerald-600 hover:border-emerald-300" aria-label="Kirim Notifikasi WA" title="Kirim notifikasi WA ke wali siswa terjadwal"><?= icon('chat', 'text-[17px]') ?></button>
                </form>
                <form method="post" action="<?= url('app/operator/jadwal.php') ?>" onsubmit="return confirm('Hapus jadwal <?= e($j['kode']) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus">
                  <input type="hidden" name="id" value="<?= e($j['id']) ?>">
                  <button type="submit" class="icon-box border border-line text-ink-500 hover:text-red-600 hover:border-red-300" aria-label="Hapus" title="Hapus jadwal"><?= icon('delete', 'text-[17px]') ?></button>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="border-t border-line bg-canvas px-5 py-3 space-y-1">
        <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('info', 'text-[16px] shrink-0') ?>
          <span>Jumlah peserta terisi otomatis dari halaman <a class="underline" href="<?= url('app/operator/peserta-ujian.php') ?>">Peserta Ujian</a>;
          menyimpan jadwal dari sini tidak mengubahnya.</span></p>
        <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('shield_person', 'text-[16px] shrink-0') ?>
          <span>Jadwal yang sudah dipakai pada data seleksi tidak bisa dihapus.</span></p>
      </div>
    </section>

  </main>
</div>

<!-- Modal tambah / ubah (satu modal untuk keduanya) -->
<div id="modal-jadwal" class="modal-root hidden fixed inset-0 z-[60]" role="dialog" aria-modal="true">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
    <form method="post" action="<?= url('app/operator/jadwal.php') ?>"
          class="animate-pop flex flex-col w-full max-w-lg bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden"
          style="max-height:calc(100vh - 2rem)">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" data-md-fill="aksi" value="tambah">
      <input type="hidden" name="id" data-md-fill="id" value="">
      <input type="hidden" name="peserta_dijadwalkan" data-md-fill="peserta" value="0">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line shrink-0">
        <div>
          <h3 id="jd-judul" class="font-bold text-ink-900 text-lg leading-tight">Tambah Jadwal Tes</h3>
          <p id="jd-sub" class="text-[13px] text-ink-500">Kode jadwal dibuat otomatis setelah disimpan.</p>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5 space-y-4 overflow-y-auto" style="min-height:0">
        <div>
          <label class="label" for="jd-nama">Nama Kegiatan <span class="text-red-600">*</span></label>
          <input id="jd-nama" name="nama_kegiatan" type="text" class="input" data-md-fill="nama"
                 placeholder="Tes CBT Gelombang 1" maxlength="120" required>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label" for="jd-tanggal">Tanggal <span class="text-red-600">*</span></label>
            <input id="jd-tanggal" name="tanggal" type="date" class="input" data-md-fill="tanggal" required>
          </div>
          <div>
            <label class="label" for="jd-status">Status</label>
            <select id="jd-status" name="status" class="select" data-md-fill="status">
              <?php foreach ($labelStat as $k => $lab): ?>
                <option value="<?= e($k) ?>"><?= e($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label" for="jd-jam1">Jam Mulai <span class="text-red-600">*</span></label>
            <input id="jd-jam1" name="jam_mulai" type="time" class="input" data-md-fill="jam1" value="08:00" required>
          </div>
          <div>
            <label class="label" for="jd-jam2">Jam Selesai <span class="text-red-600">*</span></label>
            <input id="jd-jam2" name="jam_selesai" type="time" class="input" data-md-fill="jam2" value="10:00" required>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="label" for="jd-mode">Metode</label>
            <select id="jd-mode" name="mode" class="select" data-md-fill="mode">
              <?php foreach ($labelMode as $k => $lab): ?>
                <option value="<?= e($k) ?>"><?= e($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="label" for="jd-ruangan">Ruangan</label>
            <input id="jd-ruangan" name="ruangan" type="text" class="input" data-md-fill="ruangan"
                   placeholder="Lab Komputer 1" maxlength="100">
          </div>
        </div>
        <div>
          <label class="label" for="jd-kapasitas">Kapasitas</label>
          <input id="jd-kapasitas" name="kapasitas" type="number" class="input" data-md-fill="kapasitas"
                 value="0" min="0" max="1000">
          <p class="hint">0 = tanpa batas peserta.</p>
        </div>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end shrink-0">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan</button>
      </div>
    </form>
  </div>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php if ($galat): ?>
<script>
/* Simpanan gagal: modal dibuka kembali berisi kiriman terakhir supaya operator
 * tidak perlu mencari tombol Ubah-nya lagi. */
window.JADWAL_ISI = <?= json_encode([
    'aksi'    => $_POST['aksi'] ?? 'tambah',
    'id'      => (string) ($_POST['id'] ?? ''),
    'kode'    => $kodeGagal,
    'nama'    => $_POST['nama_kegiatan'] ?? '',
    'tanggal' => $_POST['tanggal'] ?? '',
    'jam1'    => $_POST['jam_mulai'] ?? '',
    'jam2'    => $_POST['jam_selesai'] ?? '',
    'mode'    => $_POST['mode'] ?? 'OFFLINE',
    'status'  => $_POST['status'] ?? 'DRAFT',
    'ruangan' => $_POST['ruangan'] ?? '',
    'kapasitas' => $_POST['kapasitas'] ?? '0',
    'peserta' => $_POST['peserta_dijadwalkan'] ?? '0',
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
/* Satu modal untuk Tambah dan Ubah: selain mengisi data-md-*, script ini
 * memasang judul & subjudul modal dan membuka ulang isian saat validasi gagal. */
(function () {
  var modal = document.getElementById('modal-jadwal');
  if (!modal) return;
  var kunci = ['id', 'nama', 'tanggal', 'jam1', 'jam2', 'mode', 'status', 'ruangan', 'kapasitas', 'peserta', 'aksi'];

  function isi(data) {
    kunci.forEach(function (k) {
      var el = modal.querySelector('[data-md-fill="' + k + '"]');
      if (el) el.value = data[k] != null ? String(data[k]) : '';
    });
    var ubah = data.aksi === 'ubah';
    document.getElementById('jd-judul').textContent = ubah ? 'Ubah Jadwal Tes' : 'Tambah Jadwal Tes';
    document.getElementById('jd-sub').textContent = ubah
      ? 'Kode ' + (data.kode || '—') + ' tetap dan tidak ikut berubah.'
      : 'Kode jadwal dibuat otomatis setelah disimpan.';
  }

  document.querySelectorAll('[data-modal-open="modal-jadwal"]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var data = {};
      kunci.concat(['kode']).forEach(function (k) {
        data[k] = btn.getAttribute('data-md-' + k) || '';
      });
      isi(data);
    });
  });

  if (window.JADWAL_ISI) {
    isi(window.JADWAL_ISI);
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }
})();
</script>
</body>
</html>
