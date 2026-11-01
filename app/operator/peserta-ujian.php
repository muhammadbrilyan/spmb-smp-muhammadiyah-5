<?php
/* ==================================================================
 * PESERTA UJIAN (Fase 6) — atur otomatis, tambah manual, hapus,
 * kartu peserta tercetak lewat portal siswa, kirim WA jadwal.
 *
 * Hanya jadwal berstatus TERJADWAL yang dikelola di sini: status itulah
 * yang dikirim ke calon siswa (Kelola Jadwal -> Umumkan Semua Jadwal),
 * sehingga Draft/Selesai sengaja tidak ditampilkan agar operator tidak
 * menempatkan peserta pada jadwal yang tidak akan sampai ke siswa.
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
    $aksi     = (string) ($_POST['aksi'] ?? '');
    $jadwalId = (string) ($_POST['jadwal_id'] ?? '');

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
    header('Location: ' . url('app/operator/peserta-ujian.php'));
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

$labelMode = ['OFFLINE' => 'Offline', 'DARING' => 'Daring', 'VIDEO_CALL' => 'Video Call'];

/* Jadwal yang dikelola (Terjadwal) + peserta tiap jadwal, dan pendaftar yang
 * siap ditempatkan. */
$semuaJadwal   = daftar_jadwal_tes_admin();
$jadwalAktif   = array_values(array_filter($semuaJadwal, fn($j) => ($j['status'] ?? '') === 'TERJADWAL'));
$jadwalLain    = array_values(array_filter($semuaJadwal, fn($j) => ($j['status'] ?? '') !== 'TERJADWAL'));
$pesertaPerJadwal = [];
foreach ($jadwalAktif as $j) {
    $pesertaPerJadwal[$j['id']] = peserta_daftar($j['id']);
}

/* Pendaftar yang boleh dijadwalkan: berkas terverifikasi, belum punya jadwal,
 * dan belum diarsipkan (sama seperti syarat di peserta_atur_otomatis). */
$siapJadwal = q_all(
    "SELECT p.id, p.no_pendaftaran, p.nama_siswa
       FROM pendaftar p
      WHERE p.tahap = 'BERKAS_TERVERIFIKASI'
        AND p.arsip_at IS NULL
        AND NOT EXISTS (SELECT 1 FROM peserta_ujian pu WHERE pu.pendaftar_id = p.id)
   ORDER BY p.no_pendaftaran"
) ?: [];
$totalPeserta = (int) (q_col('SELECT COUNT(*) FROM peserta_ujian') ?? 0);

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'peserta';
require __DIR__ . '/../../includes/partials/sidebar.php';
require __DIR__ . '/../../includes/partials/topbar.php';

$flash = flash();
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1000px]">

    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-ink-900">Peserta Ujian</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Semua jadwal berstatus <strong>Terjadwal</strong> tampil di bawah ini beserta pesertanya — tidak perlu
          memilih jadwal dulu. Pendaftar yang berkasnya sudah terverifikasi dapat ditempatkan otomatis
          (urut nomor pendaftaran, berhenti di kapasitas) atau ditambahkan manual.
        </p>
      </div>
      <a href="<?= url('app/operator/jadwal.php') ?>" class="btn btn-outline shrink-0"><?= icon('event') ?> Kelola Jadwal</a>
    </div>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-2xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm" role="status">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <!-- Ringkasan -->
    <div class="mt-5 grid gap-3 grid-cols-3">
      <div class="rounded-xl border border-line bg-white px-3 py-2.5">
        <p class="flex items-center gap-1.5 text-[12px] text-ink-500"><?= dot('amber') ?> Siap dijadwalkan</p>
        <p class="mt-0.5 text-2xl font-bold leading-tight text-ink-900"><?= count($siapJadwal) ?></p>
      </div>
      <div class="rounded-xl border border-line bg-white px-3 py-2.5">
        <p class="flex items-center gap-1.5 text-[12px] text-ink-500"><?= dot('green') ?> Sudah terjadwal</p>
        <p class="mt-0.5 text-2xl font-bold leading-tight text-ink-900"><?= $totalPeserta ?></p>
      </div>
      <div class="rounded-xl border border-line bg-white px-3 py-2.5">
        <p class="flex items-center gap-1.5 text-[12px] text-ink-500"><?= dot('blue') ?> Jadwal aktif</p>
        <p class="mt-0.5 text-2xl font-bold leading-tight text-ink-900"><?= count($jadwalAktif) ?></p>
      </div>
    </div>

    <?php if (!$jadwalAktif): ?>
      <section class="mt-6 card p-8 text-center">
        <p class="text-ink-400"><?= icon('event_busy', 'text-[28px]') ?></p>
        <p class="mt-2 text-sm font-semibold text-ink-700">Belum ada jadwal berstatus Terjadwal</p>
        <p class="mt-1 text-[13px] text-ink-500 max-w-md mx-auto">
          Peserta hanya diatur pada jadwal yang akan dikirim ke calon siswa. Buat jadwal lalu ubah statusnya
          menjadi <strong>Terjadwal</strong> di halaman Kelola Jadwal.
        </p>
        <a href="<?= url('app/operator/jadwal.php') ?>" class="btn btn-primary mt-5"><?= icon('add') ?> Kelola Jadwal</a>
      </section>
    <?php endif; ?>

    <?php foreach ($jadwalAktif as $j): ?>
      <?php
      $peserta = $pesertaPerJadwal[$j['id']];
      $kap     = (int) $j['kapasitas'];
      $n       = count($peserta);
      $penuh   = $kap > 0 && $n >= $kap;
      $persen  = $kap > 0 ? min(100, (int) round($n / $kap * 100)) : ($n > 0 ? 100 : 0);
      $tanpaWa = 0;
      foreach ($peserta as $ps) {
          if (trim((string) ($ps['no_whatsapp'] ?? '')) === '') {
              $tanpaWa++;
          }
      }
      $ruangan = trim((string) ($j['ruangan'] ?? ''));
      ?>
      <section class="mt-6 card overflow-hidden">
        <div class="px-5 pt-5 pb-4 border-b border-line">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-bold text-ink-900 leading-snug"><?= e($j['nama_kegiatan']) ?></h2>
                <span class="badge font-mono text-[11px] bg-canvas text-ink-500 border-line"><?= e($j['kode']) ?></span>
                <?php if ($penuh): ?>
                  <span class="badge badge-red">Kapasitas penuh</span>
                <?php endif; ?>
              </div>
              <p class="mt-1 text-[13px] leading-snug text-ink-500">
                <?= e(bulan($j['tanggal'])) ?> · <?= e(substr($j['jam_mulai'], 0, 5)) ?>–<?= e(substr($j['jam_selesai'], 0, 5)) ?> WIB ·
                <?= e($labelMode[$j['mode']] ?? $j['mode']) ?><?php if ($ruangan !== ''): ?> · <?= e($ruangan) ?><?php endif; ?>
              </p>
            </div>
            <div class="shrink-0 ml-auto text-right">
              <p class="text-[12px] text-ink-500">Peserta</p>
              <p class="text-2xl font-bold leading-tight text-ink-900"><?= $n ?><?= $kap > 0 ? '<span class="text-[13px] font-normal text-ink-400">/' . $kap . '</span>' : '' ?></p>
            </div>
          </div>

          <?php if ($kap > 0): ?>
            <div class="mt-3 h-1.5 rounded-full bg-line-soft overflow-hidden">
              <div class="h-1.5 rounded-full bg-primary" style="width:<?= $persen ?>%"></div>
            </div>
          <?php endif; ?>

          <p class="mt-2 text-[12px] leading-snug text-ink-500">
            <?= icon('group', 'text-[14px] align-middle') ?>
            <?= $kap > 0 ? 'Sisa kuota ' . max(0, $kap - $n) . ' peserta' : 'Kapasitas bebas' ?>
            <?php if ($tanpaWa > 0): ?>
              · <span class="text-amber-700"><?= $tanpaWa ?> peserta belum punya nomor WA orang tua</span>
            <?php endif; ?>
          </p>

          <div class="mt-4 flex flex-wrap gap-2">
            <form method="post" action="<?= url('app/operator/peserta-ujian.php') ?>"
                  onsubmit="return confirm('Atur peserta otomatis untuk <?= e($j['kode']) ?>? Pendaftar yang berkasnya terverifikasi akan diurutkan & dijadwalkan sampai kapasitas<?= $kap > 0 ? ' (' . $kap . ')' : ' bebas' ?>.');">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="atur_otomatis">
              <input type="hidden" name="jadwal_id" value="<?= e($j['id']) ?>">
              <button type="submit" class="btn btn-primary btn-sm shadow-drop" <?= $penuh ? 'disabled' : '' ?>>
                <?= icon('auto_awesome') ?> Atur Peserta Otomatis
              </button>
            </form>
            <form method="post" action="<?= url('app/operator/peserta-ujian.php') ?>"
                  onsubmit="return confirm('Kirim notifikasi WA jadwal <?= e($j['kode']) ?> ke orang tua semua peserta?');">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="kirim_wa">
              <input type="hidden" name="jadwal_id" value="<?= e($j['id']) ?>">
              <button type="submit" class="btn btn-outline btn-sm" <?= $n === 0 ? 'disabled' : '' ?>>
                <?= icon('chat') ?> Kirim WA Jadwal
              </button>
            </form>
          </div>

          <?php if ($siapJadwal): ?>
            <form method="post" action="<?= url('app/operator/peserta-ujian.php') ?>"
                  class="mt-4 flex flex-wrap items-end gap-2 border-t border-line-soft pt-4">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="tambah_manual">
              <input type="hidden" name="jadwal_id" value="<?= e($j['id']) ?>">
              <div class="flex-1 min-w-[240px]">
                <label class="label" for="pm-<?= e($j['id']) ?>">Tambah manual — <?= count($siapJadwal) ?> pendaftar siap</label>
                <select id="pm-<?= e($j['id']) ?>" name="pendaftar_id" class="select" required>
                  <option value="">— pilih pendaftar —</option>
                  <?php foreach ($siapJadwal as $sj): ?>
                    <option value="<?= e($sj['id']) ?>"><?= e($sj['no_pendaftaran']) ?> — <?= e($sj['nama_siswa']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-outline btn-sm shrink-0" <?= $penuh ? 'disabled' : '' ?>>
                <?= icon('person_add') ?> Tambah
              </button>
            </form>
          <?php endif; ?>
        </div>

        <?php if (!$peserta): ?>
          <div class="px-5 py-8 text-center">
            <p class="text-sm font-semibold text-ink-700">Belum ada peserta pada jadwal ini</p>
            <p class="mt-1 text-[13px] text-ink-500">Tekan <strong>Atur Peserta Otomatis</strong> di atas, atau tambahkan manual.</p>
          </div>
        <?php else: ?>
          <ul class="divide-y divide-line-soft">
            <?php foreach ($peserta as $ps): ?>
              <li class="px-5 py-3 flex flex-wrap items-center gap-3 hover:bg-canvas">
                <span class="w-28 shrink-0 font-mono text-[12px] font-semibold text-primary"><?= e($ps['nomor_peserta']) ?></span>
                <div class="flex-1 min-w-[180px]">
                  <p class="text-[14px] font-semibold leading-snug text-ink-900"><?= e($ps['nama_siswa']) ?></p>
                  <p class="text-[12px] leading-snug text-ink-500">
                    <?= e($ps['no_pendaftaran']) ?><?php if ($ps['asal_sekolah'] !== null && $ps['asal_sekolah'] !== ''): ?> · <?= e($ps['asal_sekolah']) ?><?php endif; ?>
                  </p>
                </div>
                <p class="text-[12px] font-mono text-ink-500 shrink-0"><?= e($ps['no_whatsapp'] ?: 'tanpa WA') ?></p>
                <form method="post" action="<?= url('app/operator/peserta-ujian.php') ?>" class="shrink-0"
                      onsubmit="return confirm('Hapus <?= e($ps['nama_siswa']) ?> dari jadwal <?= e($j['kode']) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="aksi" value="hapus_peserta">
                  <input type="hidden" name="jadwal_id" value="<?= e($j['id']) ?>">
                  <input type="hidden" name="peserta_id" value="<?= e($ps['id']) ?>">
                  <button type="submit" class="icon-box border border-line text-ink-500 hover:text-red-600 hover:border-red-300"
                          aria-label="Hapus <?= e($ps['nama_siswa']) ?> dari jadwal" title="Hapus dari jadwal"><?= icon('delete', 'text-[16px]') ?></button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>

    <?php if ($jadwalLain): ?>
      <div class="mt-5 rounded-2xl border border-line bg-white px-5 py-4">
        <p class="text-[13px] leading-relaxed text-ink-500 flex gap-2">
          <?= icon('info', 'text-[16px] shrink-0') ?>
          <span><?= count($jadwalLain) ?> jadwal berstatus Draft/Selesai tidak dikelola di sini karena tidak dikirim
          ke calon siswa:
            <?php $pernah = false; foreach ($jadwalLain as $jl): ?><?php $njl = peserta_hitung($jl['id']); ?><?= $pernah ? ', ' : '' ?><strong><?= e($jl['kode']) ?></strong> (<?= e($jl['status'] === 'DRAFT' ? 'Draft' : 'Selesai') ?><?= $njl > 0 ? ', ' . $njl . ' peserta' : '' ?>)<?php $pernah = true; ?><?php endforeach; ?>.
          Ubah statusnya menjadi Terjadwal di <a class="underline" href="<?= url('app/operator/jadwal.php') ?>">Kelola Jadwal</a> bila perlu diisi peserta.</span>
        </p>
      </div>
    <?php endif; ?>

    <div class="mt-5 rounded-2xl border border-line bg-white px-5 py-4 space-y-1">
      <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('chat', 'text-[16px] shrink-0') ?>
        <span><strong>Kirim WA Jadwal</strong> di atas mengirim jadwal kegiatan itu saja. Untuk memberi tahu
        <strong>seluruh</strong> jadwal sekaligus dalam satu pesan, gunakan
        <a class="underline" href="<?= url('app/operator/jadwal.php') ?>">Umumkan Semua Jadwal</a> di Kelola Jadwal.</span></p>
      <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('badge', 'text-[16px] shrink-0') ?>
        <span>Nomor peserta &amp; kartu peserta tercetak otomatis; siswa melihatnya di
        <strong>PPDB &amp; Seleksi</strong> pada portal siswa.</span></p>
      <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('history', 'text-[16px] shrink-0') ?>
        <span>Menghapus peserta mengembalikan pendaftarnya ke tahap berkas terverifikasi sehingga bisa dijadwalkan ulang.</span></p>
    </div>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
