<?php
/* ==================================================================
 * PESERTA UJIAN (Fase 6) — atur otomatis, tambah manual, hapus,
 * kartu peserta tercetak lewat portal siswa, kirim WA jadwal.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

/* --- Proses POST --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Sesi kedaluwarsa. Silakan coba lagi.');
        header('Location: peserta-ujian.php');
        exit;
    }
    $aksi    = (string) ($_POST['aksi'] ?? '');
    $jadwalId = (string) ($_POST['jadwal_id'] ?? '');
    $balik   = 'peserta-ujian.php' . ($jadwalId !== '' ? '?j=' . rawurlencode($jadwalId) : '');

    if ($aksi === 'atur_otomatis' && $jadwalId !== '') {
        $r = peserta_atur_otomatis($jadwalId);
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
    } elseif ($aksi === 'tambah_manual' && $jadwalId !== '') {
        $r = peserta_tambah_manual($jadwalId, (string) ($_POST['pendaftar_id'] ?? ''));
        flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
    } elseif ($aksi === 'hapus_peserta' && $jadwalId !== '') {
        flash(peserta_hapus((string) ($_POST['peserta_id'] ?? '')) ? 'sukses' : 'error',
            'Peserta dihapus dari jadwal.');
    } elseif ($aksi === 'kirim_wa' && $jadwalId !== '') {
        $r = peserta_kirim_notifikasi_wa($jadwalId);
        if ($r['ok'] + $r['gagal'] === 0) {
            flash('error', 'Tidak ada penerima (butuh data orang tua dengan nomor WA).');
        } else {
            flash($r['gagal'] > 0 ? 'error' : 'sukses',
                $r['ok'] . ' pesan WA terkirim.' . ($r['gagal'] > 0 ? ' ' . $r['gagal'] . ' gagal (token kosong / nomor tidak valid).' : ''));
        }
    }
    header('Location: ' . $balik);
    exit;
}

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title     = 'Peserta Ujian';
$desc      = 'Atur peserta ujian otomatis, tambah manual, dan lihat daftar peserta per jadwal.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'peserta';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik' => 'SPMB', 'Peserta Ujian' => null];
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
$jadwalList = daftar_jadwal_tes_admin();
$pilihId = trim((string) ($_GET['j'] ?? ($jadwalList[0]['id'] ?? '')));
$jadwal  = null;
foreach ($jadwalList as $jl) {
    if ($jl['id'] === $pilihId) {
        $jadwal = $jl;
        break;
    }
}
$peserta  = $jadwal ? peserta_daftar($jadwal['id']) : [];
$siapJadwal = q_all(
    "SELECT p.id, p.no_pendaftaran, p.nama_siswa
       FROM pendaftar p
      WHERE p.tahap = 'BERKAS_TERVERIFIKASI'
        AND NOT EXISTS (SELECT 1 FROM peserta_ujian pu WHERE pu.pendaftar_id = p.id)
   ORDER BY p.no_pendaftaran"
) ?: [];
$labelMode = ['OFFLINE' => 'Offline', 'DARING' => 'Daring', 'VIDEO_CALL' => 'Video Call'];
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <?php if ($flash): ?>
      <div class="mb-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <div>
      <h1 class="text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Peserta Ujian</h1>
      <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
        Pilih jadwal, lalu atur peserta otomatis (pendaftar <strong>BERKAS_TERVERIFIKASI</strong> diurutkan nomor pendaftaran, diisi sampai kapasitas)
        atau tambah manual. Kartu peserta &amp; nomor unik tercetak di portal siswa.
      </p>
    </div>

    <!-- Pilih jadwal -->
    <section class="mt-6 card p-5 sm:p-6">
      <form method="get" action="peserta-ujian.php" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[240px]">
          <label class="label" for="j">Jadwal Tes</label>
          <select id="j" name="j" class="select" onchange="this.form.submit()">
            <?php foreach ($jadwalList as $jl): ?>
              <option value="<?= e($jl['id']) ?>" <?= $jl['id'] === $pilihId ? 'selected' : '' ?>>
                <?= e($jl['kode']) ?> — <?= e($jl['nama_kegiatan']) ?> (<?= e($jl['tanggal']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-outline"><?= icon('search') ?> Tampilkan</button>
      </form>

      <?php if ($jadwal): ?>
        <div class="mt-4 flex flex-wrap items-center gap-2">
          <span class="badge badge-blue"><?= e($labelMode[$jadwal['mode']] ?? $jadwal['mode']) ?></span>
          <span class="badge badge-gray"><?= e(substr($jadwal['jam_mulai'], 0, 5)) ?>–<?= e(substr($jadwal['jam_selesai'], 0, 5)) ?> WIB</span>
          <?php if ($jadwal['ruangan']): ?><span class="badge badge-gray"><?= e($jadwal['ruangan']) ?></span><?php endif; ?>
          <?php if ((int) $jadwal['kapasitas'] > 0): ?>
            <span class="badge <?= count($peserta) >= (int) $jadwal['kapasitas'] ? 'badge-red' : 'badge-amber' ?>">
              Kapasitas <?= count($peserta) ?>/<?= (int) $jadwal['kapasitas'] ?>
            </span>
          <?php else: ?>
            <span class="badge badge-gray"><?= count($peserta) ?> peserta</span>
          <?php endif; ?>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
          <form method="post" action="peserta-ujian.php" class="inline"
                onsubmit="return confirm('Atur peserta otomatis untuk <?= e($jadwal['kode']) ?>? Pendaftar BERKAS_TERVERIFIKASI akan diurutkan & dijadwalkan sampai kapasitas.');">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="atur_otomatis">
            <input type="hidden" name="jadwal_id" value="<?= e($jadwal['id']) ?>">
            <button type="submit" class="btn btn-primary shadow-drop"><?= icon('auto_awesome') ?> Atur Peserta Otomatis</button>
          </form>
          <form method="post" action="peserta-ujian.php" class="inline"
                onsubmit="return confirm('Kirim notifikasi WA jadwal <?= e($jadwal['kode']) ?> ke orang tua semua peserta?');">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="kirim_wa">
            <input type="hidden" name="jadwal_id" value="<?= e($jadwal['id']) ?>">
            <button type="submit" class="btn btn-outline"><?= icon('chat') ?> Kirim WA Jadwal</button>
          </form>
        </div>

        <!-- Tambah manual -->
        <?php if ($siapJadwal): ?>
          <form method="post" action="peserta-ujian.php" class="mt-5 flex flex-wrap items-end gap-3 border-t border-line-soft pt-4">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="tambah_manual">
            <input type="hidden" name="jadwal_id" value="<?= e($jadwal['id']) ?>">
            <div class="flex-1 min-w-[280px]">
              <label class="label" for="pendaftar_id">Tambah Manual (BERKAS_TERVERIFIKASI)</label>
              <select id="pendaftar_id" name="pendaftar_id" class="select" required>
                <option value="">— pilih pendaftar —</option>
                <?php foreach ($siapJadwal as $sj): ?>
                  <option value="<?= e($sj['id']) ?>"><?= e($sj['no_pendaftaran']) ?> — <?= e($sj['nama_siswa']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-outline shrink-0"><?= icon('person_add') ?> Tambah</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <!-- Daftar peserta -->
    <?php if ($jadwal): ?>
      <section class="mt-6 card overflow-hidden">
        <div class="px-5 py-4 border-b border-line">
          <h2 class="font-bold text-ink-900">Daftar Peserta — <?= e($jadwal['kode']) ?></h2>
        </div>
        <div class="overflow-x-auto scroll-thin">
          <table class="w-full min-w-[760px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th">No. Peserta</th>
                <th class="th">No. Pendaftaran</th>
                <th class="th">Nama</th>
                <th class="th">Asal Sekolah</th>
                <th class="th">WA Orang Tua</th>
                <th class="th text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php if (!$peserta): ?>
                <tr><td class="td text-center text-ink-400" colspan="6">Belum ada peserta. Gunakan "Atur Peserta Otomatis" atau tambah manual.</td></tr>
              <?php endif; ?>
              <?php foreach ($peserta as $ps): ?>
                <tr class="tr-hover">
                  <td class="td font-mono text-[13px] font-semibold text-primary"><?= e($ps['nomor_peserta']) ?></td>
                  <td class="td font-mono text-[13px]"><?= e($ps['no_pendaftaran']) ?></td>
                  <td class="td font-semibold text-ink-900"><?= e($ps['nama_siswa']) ?></td>
                  <td class="td text-[13px] text-ink-500"><?= e($ps['asal_sekolah']) ?></td>
                  <td class="td text-[13px]"><?= e($ps['no_whatsapp'] ?: '—') ?></td>
                  <td class="td text-right">
                    <form method="post" action="peserta-ujian.php" class="inline"
                          onsubmit="return confirm('Hapus <?= e($ps['nama_siswa']) ?> dari jadwal <?= e($jadwal['kode']) ?>?');">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="hapus_peserta">
                      <input type="hidden" name="jadwal_id" value="<?= e($jadwal['id']) ?>">
                      <input type="hidden" name="peserta_id" value="<?= e($ps['id']) ?>">
                      <button type="submit" class="btn btn-sm btn-danger"><?= icon('delete', 'text-[15px]') ?> Hapus</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
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
