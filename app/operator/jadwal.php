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

$title = 'Jadwal Ujian';
$desc  = 'Kelola jadwal tes seleksi dan penempatan pesertanya dalam satu halaman.';
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

    if ($aksi === 'umumkan_semua') {
        $r = jadwal_umumkan_semua();
        if ($r['jadwal'] === 0) {
            flash('error', 'Belum ada jadwal berstatus Terjadwal yang bisa diumumkan.');
        } elseif ($r['penerima'] === 0) {
            flash('error', 'Belum ada pendaftar terjadwal dengan nomor WhatsApp orang tua.');
        } else {
            $pesan = $r['ok'] . ' wali siswa menerima satu pesan berisi ' . $r['jadwal'] . ' jadwal'
                . ($r['in_app'] > 0 ? '. ' . $r['in_app'] . ' notifikasi portal dibuat' : '.');
            if ($r['lewati'] > 0) {
                $pesan .= ' ' . $r['lewati'] . ' penerima sudah pernah menerima susunan jadwal ini.';
            }
            if ($r['gagal'] > 0) {
                $pesan .= ' ' . $r['gagal'] . ' gagal — ' . ($r['detail'][0] ?? '');
            }
            flash($r['ok'] > 0 || $r['lewati'] > 0 ? 'sukses' : 'error', $pesan);
        }
        header('Location: ' . url('app/operator/jadwal.php'));
        exit;
    }

    /* ---- Peserta ujian: satu halaman dengan jadwal, jadi kembali ke #peserta ---- */
    if (in_array($aksi, ['atur_otomatis', 'tambah_manual', 'hapus_peserta', 'kirim_wa'], true)) {
        $jadwalId = trim((string) ($_POST['jadwal_id'] ?? ''));
        if ($jadwalId === '') {
            flash('error', 'Jadwal tujuan tidak ditemukan. Muat ulang halaman lalu coba lagi.');
        } elseif ($aksi === 'atur_otomatis') {
            $r = peserta_atur_otomatis($jadwalId);
            flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        } elseif ($aksi === 'tambah_manual') {
            $r = peserta_tambah_manual($jadwalId, (string) ($_POST['pendaftar_id'] ?? ''));
            flash($r['ok'] ? 'sukses' : 'error', $r['pesan']);
        } elseif ($aksi === 'hapus_peserta') {
            flash(peserta_hapus((string) ($_POST['peserta_id'] ?? '')) ? 'sukses' : 'error',
                'Peserta dihapus dari jadwal.');
        } else {
            $r = peserta_kirim_notifikasi_wa($jadwalId);
            if ($r['ok'] + $r['gagal'] === 0) {
                flash('error', 'Tidak ada penerima (butuh data orang tua dengan nomor WA).');
            } else {
                flash($r['gagal'] > 0 ? 'error' : 'sukses',
                    $r['ok'] . ' pesan WA terkirim.' . ($r['gagal'] > 0 ? ' ' . $r['gagal'] . ' gagal (token kosong / nomor tidak valid).' : ''));
            }
        }
        header('Location: ' . url('app/operator/jadwal.php') . '#peserta');
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

/* Bahan pengumuman gabungan: semua jadwal Terjadwal + jumlah penerima WA. */
$jadwalTerbit   = jadwal_terjadwal();
$jumlahPenerima = count(jadwal_penerima_pengumuman());
$contohPesan    = $jadwalTerbit ? jadwal_pesan_pengumuman('Nama Siswa', 'REG-2026-0001', $jadwalTerbit) : '';

/* ---- Data Peserta Ujian (bagian bawah halaman yang sama) ----
 * Hanya jadwal TERJADWAL yang diisi peserta: status itulah yang sampai ke
 * calon siswa, sehingga Draft/Selesai hanya disebut sebagai catatan. */
$jadwalAktif = array_values(array_filter($jadwalList, fn($j) => ($j['status'] ?? '') === 'TERJADWAL'));
$jadwalLain  = array_values(array_filter($jadwalList, fn($j) => ($j['status'] ?? '') !== 'TERJADWAL'));
$pesertaPerJadwal = [];
foreach ($jadwalAktif as $j) {
    $pesertaPerJadwal[$j['id']] = peserta_daftar($j['id']);
}
/* Pendaftar yang boleh dijadwalkan: berkas terverifikasi, belum punya jadwal,
 * belum diarsipkan (sama seperti syarat di peserta_atur_otomatis). */
$siapJadwal = q_all(
    "SELECT p.id, p.no_pendaftaran, p.nama_siswa
       FROM pendaftar p
      WHERE p.tahap = 'BERKAS_TERVERIFIKASI'
        AND p.arsip_at IS NULL
        AND NOT EXISTS (SELECT 1 FROM peserta_ujian pu WHERE pu.pendaftar_id = p.id)
   ORDER BY p.no_pendaftaran"
) ?: [];
$totalPeserta = (int) (q_col('SELECT COUNT(*) FROM peserta_ujian') ?? 0);

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
$breadcrumbs = ['Portal Akademik', 'SPMB', 'Jadwal Ujian'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1000px]">

    <div class="flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <h1 class="text-2xl font-bold tracking-tight text-ink-900">Jadwal Ujian</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          Atur jadwal tes CBT, wawancara BTQ, dan seleksi minat bakat di bagian ini, lalu tempatkan pesertanya
          pada bagian <a class="underline font-semibold" href="#peserta">Peserta Ujian</a> di bawah.
          Kode dibuat otomatis (TES-01, TES-02, dan seterusnya) saat jadwal baru disimpan.
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

    <!-- Umumkan sekali: satu pesan berisi seluruh jadwal Terjadwal -->
    <section class="mt-5 rounded-2xl border border-line bg-white p-5">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 max-w-2xl">
          <h2 class="flex items-center gap-2 font-bold text-ink-900">
            <?= icon('campaign', 'text-[19px] text-emerald-800') ?> Umumkan Jadwal ke Wali Siswa
          </h2>
          <p class="mt-1.5 text-[13px] leading-relaxed text-ink-500">
            Cukup sekali kirim. Setiap pendaftar yang sudah dijadwalkan menerima <strong>satu</strong> pesan
            WhatsApp berisi <strong>seluruh</strong> jadwal berstatus Terjadwal, dan satu notifikasi muncul
            di portal siswa.
          </p>
          <p class="mt-2.5 flex flex-wrap items-center gap-3 text-[12px] text-ink-500">
            <span><?= icon('event_note', 'text-[15px] align-middle') ?> <?= count($jadwalTerbit) ?> jadwal terjadwal</span>
            <span><?= icon('group', 'text-[15px] align-middle') ?> <?= $jumlahPenerima ?> penerima WhatsApp</span>
          </p>
        </div>
        <?php if ($jadwalTerbit && $jumlahPenerima): ?>
          <button type="button" class="btn btn-green shadow-drop shrink-0" data-modal-open="modal-umumkan">
            <?= icon('send') ?> Umumkan Semua Jadwal
          </button>
        <?php endif; ?>
      </div>

      <?php if (!$jadwalTerbit): ?>
        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-[13px] text-amber-800">
          Belum ada jadwal berstatus Terjadwal. Ubah status jadwal di atas menjadi <strong>Terjadwal</strong>
          lebih dulu agar bisa diumumkan.
        </p>
      <?php elseif (!$jumlahPenerima): ?>
        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-[13px] text-amber-800">
          Belum ada pendaftar terjadwal yang punya nomor WhatsApp orang tua. Atur peserta lewat
          <a class="underline font-semibold" href="#peserta">Peserta Ujian</a> di bawah.
        </p>
      <?php endif; ?>
    </section>

    <!-- Daftar jadwal -->
    <section id="daftar-jadwal" class="mt-6 card overflow-hidden scroll-mt-24">
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
          <span>Jumlah peserta terisi otomatis dari bagian <a class="underline" href="#peserta">Peserta Ujian</a> di bawah;
          menyimpan jadwal dari sini tidak mengubahnya.</span></p>
        <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('shield_person', 'text-[16px] shrink-0') ?>
          <span>Jadwal yang sudah dipakai pada data seleksi tidak bisa dihapus.</span></p>
      </div>
    </section>

    <?php /* ================= PESERTA UJIAN — satu halaman, di bawah pengaturan jadwal ================= */ ?>
    <section id="peserta" class="mt-8 scroll-mt-24 border-t border-line pt-6">
      <h2 class="text-xl font-bold tracking-tight text-ink-900">Peserta Ujian</h2>
      <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
        Setiap jadwal berstatus <strong>Terjadwal</strong> di atas punya bagian pesertanya sendiri. Pendaftar yang
        berkasnya terverifikasi dapat ditempatkan otomatis — urut nomor pendaftaran dan berhenti di kapasitas —
        atau ditambahkan manual satu per satu.
      </p>

      <div class="mt-4 grid gap-3 grid-cols-3">
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
        <div class="mt-5 card p-8 text-center">
          <p class="text-ink-400"><?= icon('event_busy', 'text-[28px]') ?></p>
          <p class="mt-2 text-sm font-semibold text-ink-700">Belum ada jadwal berstatus Terjadwal</p>
          <p class="mt-1 text-[13px] text-ink-500 max-w-md mx-auto">
            Peserta hanya diatur pada jadwal yang akan dikirim ke calon siswa. Buat jadwal di atas, lalu ubah
            statusnya menjadi <strong>Terjadwal</strong> melalui tombol <strong>Ubah</strong>.
          </p>
          <a href="#daftar-jadwal" class="btn btn-primary mt-5"><?= icon('event') ?> Ke Daftar Jadwal</a>
        </div>
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
        <div class="mt-5 card overflow-hidden">
          <div class="px-5 pt-5 pb-4 border-b border-line">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h3 class="font-bold text-ink-900 leading-snug"><?= e($j['nama_kegiatan']) ?></h3>
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
              <form method="post" action="<?= url('app/operator/jadwal.php') ?>"
                    onsubmit="return confirm('Atur peserta otomatis untuk <?= e($j['kode']) ?>? Pendaftar yang berkasnya terverifikasi akan diurutkan & dijadwalkan sampai kapasitas<?= $kap > 0 ? ' (' . $kap . ')' : ' bebas' ?>.');">
                <?= csrf_field() ?>
                <input type="hidden" name="aksi" value="atur_otomatis">
                <input type="hidden" name="jadwal_id" value="<?= e($j['id']) ?>">
                <button type="submit" class="btn btn-primary btn-sm shadow-drop" <?= $penuh ? 'disabled' : '' ?>>
                  <?= icon('auto_awesome') ?> Atur Peserta Otomatis
                </button>
              </form>
              <form method="post" action="<?= url('app/operator/jadwal.php') ?>"
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
              <form method="post" action="<?= url('app/operator/jadwal.php') ?>"
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
                  <form method="post" action="<?= url('app/operator/jadwal.php') ?>" class="shrink-0"
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
        </div>
      <?php endforeach; ?>

      <?php if ($jadwalLain): ?>
        <div class="mt-5 rounded-2xl border border-line bg-white px-5 py-4">
          <p class="text-[13px] leading-relaxed text-ink-500 flex gap-2">
            <?= icon('info', 'text-[16px] shrink-0') ?>
            <span><?= count($jadwalLain) ?> jadwal berstatus Draft/Selesai tidak diisi peserta karena tidak dikirim
            ke calon siswa:
              <?php $pernah = false; foreach ($jadwalLain as $jl): ?><?php $njl = peserta_hitung($jl['id']); ?><?= $pernah ? ', ' : '' ?><strong><?= e($jl['kode']) ?></strong> (<?= e($jl['status'] === 'DRAFT' ? 'Draft' : 'Selesai') ?><?= $njl > 0 ? ', ' . $njl . ' peserta' : '' ?>)<?php $pernah = true; ?><?php endforeach; ?>.
            Ubah statusnya menjadi Terjadwal di daftar jadwal di atas bila perlu diisi peserta.</span>
          </p>
        </div>
      <?php endif; ?>

      <div class="mt-5 rounded-2xl border border-line bg-white px-5 py-4 space-y-1">
        <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('chat', 'text-[16px] shrink-0') ?>
          <span><strong>Kirim WA Jadwal</strong> mengirim jadwal kegiatan itu saja. Untuk memberi tahu
          <strong>seluruh</strong> jadwal sekaligus dalam satu pesan, gunakan tombol
          <strong>Umumkan Semua Jadwal</strong> di atas.</span></p>
        <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('badge', 'text-[16px] shrink-0') ?>
          <span>Nomor peserta &amp; kartu peserta tercetak otomatis; siswa melihatnya di
          <strong>Jadwal &amp; Kartu Ujian</strong> pada portal siswa.</span></p>
        <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('history', 'text-[16px] shrink-0') ?>
          <span>Menghapus peserta mengembalikan pendaftarnya ke tahap berkas terverifikasi sehingga bisa dijadwalkan ulang.</span></p>
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

<!-- Konfirmasi pengumuman gabungan: tampilkan isian pesan sebelum dikirim -->
<div id="modal-umumkan" class="modal-root hidden fixed inset-0 z-[60]" role="dialog" aria-modal="true">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
    <form method="post" action="<?= url('app/operator/jadwal.php') ?>"
          class="animate-pop flex flex-col w-full max-w-xl bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden"
          style="max-height:calc(100vh - 2rem)">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="umumkan_semua">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line shrink-0">
        <div>
          <h3 class="font-bold text-ink-900 text-lg leading-tight">Umumkan Semua Jadwal</h3>
          <p class="text-[13px] text-ink-500">
            <?= count($jadwalTerbit) ?> jadwal · <?= $jumlahPenerima ?> penerima · satu pesan per wali siswa
          </p>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5 space-y-4 overflow-y-auto" style="min-height:0">
        <div class="rounded-xl border border-line bg-canvas p-3.5">
          <p class="text-[12px] font-semibold uppercase tracking-wide text-ink-500">Isi pesan (nama & nomor menyesuaikan tiap pendaftar)</p>
          <div class="mt-2 whitespace-pre-line text-[12px] leading-relaxed text-ink-700"><?= e($contohPesan) ?></div>
        </div>
        <p class="text-[12px] leading-relaxed text-ink-500 flex gap-2">
          <?= icon('info', 'text-[16px] shrink-0') ?>
          <span>Pesan yang sama tersimpan sebagai notifikasi di portal siswa. Susunan jadwal yang sudah
          pernah diumumkan tidak dikirim ulang; bila jadwal diubah, pengumuman berikutnya dianggap baru.</span>
        </p>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end shrink-0">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-green"><?= icon('send') ?> Kirim Sekarang</button>
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
