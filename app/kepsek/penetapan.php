<?php
/* ==================================================================
 * PENETAPAN HASIL SELEKSI — persetujuan Kepala Sekolah.
 *
 * Dua tindakan berbeda, keduanya direkam bersama SIDIK daftar hasil:
 *   - "Setujui"  : membuka tombol Umumkan Sekarang di sisi operator.
 *   - "Tandatangani" : membubuhkan tanda tangan pada rilis yang sudah terbit.
 *
 * Sidik dihitung dari no_pendaftaran + status_seleksi seluruh pendaftar aktif.
 * Bila operator mengubah nilai/status setelah catatan dibuat, sidik tidak cocok
 * dan catatan dianggap BATAL — kepala sekolah tidak mungkin tertempel pada
 * daftar yang isinya sudah berubah tanpa sepengetahuannya.
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['kepsek', 'admin']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

/* Hak membubuhkan catatan tetap milik kepala sekolah; admin hanya dapat melihat. */
$bolehTulis = $me['role'] === 'kepsek';

$flash = flash();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.');
        header('Location: ' . url('app/kepsek/penetapan.php'));
        exit;
    }

    $aksi = (string) ($_POST['aksi'] ?? '');
    if (!in_array($aksi, ['setujui', 'cabut', 'ttd', 'cabut_ttd'], true)) {
        $hasil = ['ok' => false, 'pesan' => 'Aksi tidak dikenal.'];
    } elseif (!$bolehTulis) {
        $hasil = ['ok' => false, 'pesan' => 'Persetujuan dan tanda tangan hanya dapat dibubuhkan oleh akun Kepala Sekolah.'];
    } elseif (($aksi === 'setujui' || $aksi === 'ttd') && ($_POST['nyata'] ?? '') !== '1') {
        $hasil = ['ok' => false, 'pesan' => 'Centang pernyataan telaah lebih dulu sebelum membubuhkan catatan.'];
    } elseif ($aksi === 'ttd' && !pengumuman_status()['diumumkan']) {
        $hasil = ['ok' => false, 'pesan' => 'Belum ada rilis yang ditandatangani — tanda tangan baru bisa dibubuhkan setelah hasil diterbitkan.'];
    } else {
        $hapus = $aksi === 'cabut' || $aksi === 'cabut_ttd';
        $jenis = $aksi === 'ttd' || $aksi === 'cabut_ttd' ? 'ttd' : 'disetujui';
        if ($hapus) {
            $hasil = persetujuan_hapus($jenis)
                ? ['ok' => true, 'pesan' => $jenis === 'ttd' ? 'Tanda tangan dicabut.' : 'Persetujuan dicabut. Hasil kembali terkunci.']
                : ['ok' => false, 'pesan' => 'Gagal mencabut catatan. Coba lagi.'];
        } else {
            $hasil = persetujuan_simpan($jenis, $me['id'] ?? null)
                ? ['ok' => true, 'pesan' => $jenis === 'ttd'
                    ? 'Tanda tangan tersimpan pada rilis yang sedang berlaku.'
                    : 'Daftar hasil disetujui. Operator kini dapat menerbitkan pengumuman.']
                : ['ok' => false, 'pesan' => 'Gagal menyimpan catatan. Pastikan daftar hasil terbaca, lalu coba lagi.'];
        }
    }

    flash($hasil['ok'] ? 'sukses' : 'error', $hasil['pesan']);
    header('Location: ' . url('app/kepsek/penetapan.php'));
    exit;
}

/* ==================================================================
 * Data
 * ================================================================== */
$ringkas = persetujuan_ringkas_hasil();
$setuju  = persetujuan_baca('disetujui');
$ttd     = persetujuan_baca('ttd');
$umum    = pengumuman_status();
$sidikKini = $ringkas['total'] > 0 ? persetujuan_sidik_hasil() : '';

$labelSeleksi = ['PROSES' => ['Dalam Proses', 'amber'], 'LULUS' => ['Lulus', 'green'], 'TIDAK_LULUS' => ['Tidak Lulus', 'red']];

/* Nilai per pendaftaran — dipakai sebagai kolom tambahan pada tabel telaah. */
$nilaiByReg = [];
foreach (daftar_hasil_seleksi() as $h) {
    $nilaiByReg[$h['reg']] = $h;
}

/* Sumber baris = daftar_pendaftar(): sudah menyaring pendaftar terarsip, sama
 * seperti sidik. Menyetujui daftar yang berbeda isi dengan sidik tidak boleh terjadi. */
$baris = [];
foreach (daftar_pendaftar() as $p) {
    $n = $nilaiByReg[$p['reg']] ?? null;
    $baris[] = [
        'reg'     => $p['reg'],
        'nama'    => $p['nama'],
        'sekolah' => $p['sekolah'],
        'jalur'   => $p['jalur'],
        'kelas'   => $p['kelas'],
        'seleksi' => $p['seleksi'],
        'nilai'   => $n['nilai'] ?? null,
    ];
}

$s = (string) ($_GET['s'] ?? '');
if (!isset($labelSeleksi[$s])) {
    $s = '';
}
$tampil = $s === '' ? $baris : array_values(array_filter($baris, function ($b) use ($s) {
    return $b['seleksi'] === $s;
}));

/* Persetujuan tidak ada gunanya bila hasil belum dibacakan. */
$belumAdaHasil = $ringkas['LULUS'] + $ringkas['TIDAK_LULUS'] === 0;
/* Tanda tangan hanya ada atas rilis yang sudah terbit. */
$bolehTtd = !empty($umum['diumumkan']);

$title     = 'Persetujuan Penetapan Hasil';
$desc      = 'Setujui daftar hasil seleksi sebelum diterbitkan dan tanda tangani rilis yang sudah terbit.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'penetapan';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['SPMB' => null, 'Persetujuan Hasil' => null];
$pageActions = '<button type="button" class="btn btn-outline shadow-drop no-print" onclick="window.print()">'
    . icon('print') . ' Cetak / Simpan PDF</button>';
require __DIR__ . '/../../includes/partials/topbar.php';

/** Status satu catatan → badge + keterangan. */
$tanggalCatat = function (?string $kapan) {
    $ts = $kapan ? strtotime($kapan) : false;
    return $ts === false ? 'waktu tidak tercatat' : bulan(date('Y-m-d', $ts)) . ' · ' . date('H:i', $ts) . ' WIB';
};
$nota = function (array $c, string $labelSudah, string $labelBelum) use ($tanggalCatat) {
    if (!$c['ada']) {
        return ['badge' => badge($labelBelum, 'gray'), 'ket' => 'Belum ada catatan pada daftar hasil ini.'];
    }
    $atas = 'Atas nama ' . ($c['siapa'] ?? '(akun terhapus)') . ' — ' . $tanggalCatat($c['kapan']);
    if ($c['batal']) {
        return ['badge' => badge('Batal', 'red'),
                'ket'  => $atas . '. Daftar hasil berubah setelah itu, jadi catatan lama tidak lagi berlaku.'];
    }
    return ['badge' => badge($labelSudah, 'green'), 'ket' => $atas . '.'];
};
$notaSetuju = $nota($setuju, 'Disetujui', 'Belum Disetujui');
$notaTtd    = $nota($ttd, 'Ditandatangani', 'Belum Ditandatangani');
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <?php if ($flash): ?>
      <div class="no-print rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <!-- ====================== KOP LAPORAN ====================== -->
    <div class="mt-4 flex flex-col sm:flex-row items-start gap-4 border-b-2 border-ink-900/10 pb-4">
      <?php if (!empty($PENGATURAN['logo_url'])): ?>
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo <?= e($PENGATURAN['nama_sekolah']) ?>" class="w-12 h-12 shrink-0 object-contain">
      <?php endif; ?>
      <div class="min-w-0 flex-1">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-ink-400">Panitia SPMB</p>
        <p class="mt-0.5 text-[15px] font-bold text-ink-900"><?= e($PENGATURAN['nama_sekolah']) ?></p>
        <p class="text-[12px] text-ink-500"><?= e($PENGATURAN['alamat_sekolah']) ?></p>
      </div>
      <div class="sm:text-right text-[12px] text-ink-500 shrink-0">
        <p><?= e($PENGATURAN['gelombang']) ?> · TA <?= e($PENGATURAN['tahun_ajaran']) ?></p>
        <p>Dicetak: <?= bulan(date('Y-m-d')) ?></p>
        <p>Oleh: <?= e($USER_LOGIN['nama']) ?></p>
      </div>
    </div>

    <div class="mt-6">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="badge badge-blue">Penetapan Hasil</span>
        <?php if (!$bolehTulis): ?>
          <span class="badge badge-gray">Hanya Baca (admin)</span>
        <?php endif; ?>
        <?php if (!empty($umum['diumumkan'])): ?>
          <span class="badge badge-green">Hasil sudah terbit</span>
        <?php elseif (!empty($umum['dijadwalkan_pada'])): ?>
          <span class="badge badge-amber">Terjadwal <?= e($umum['dijadwalkan_pada']) ?></span>
        <?php else: ?>
          <span class="badge badge-gray">Belum diterbitkan</span>
        <?php endif; ?>
      </div>
      <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Persetujuan Penetapan Hasil</h1>
      <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
        Hasil seleksi baru boleh diterbitkan setelah daftar ini disetujui. Persetujuan melekat pada isi daftar —
        begitu nilainya berubah, persetujuan batal dan penguncian berlaku lagi.
      </p>
    </div>

    <!-- Ringkasan angka yang disetujui -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="card p-5">
        <p class="text-[13px] text-ink-500">Total pendaftar aktif</p>
        <p class="mt-2 text-2xl font-extrabold text-ink-900"><?= (int) $ringkas['total'] ?></p>
        <p class="mt-2 text-xs text-ink-400">tidak termasuk yang diarsipkan</p>
      </div>
      <div class="card p-5">
        <p class="text-[13px] text-ink-500">Lulus</p>
        <p class="mt-2 text-2xl font-extrabold text-[#065F46]"><?= (int) $ringkas['LULUS'] ?></p>
        <p class="mt-2 text-xs text-ink-400">
          <?= $ringkas['total'] > 0 ? round($ringkas['LULUS'] / $ringkas['total'] * 100, 1) : 0 ?>% dari pendaftar
        </p>
      </div>
      <div class="card p-5">
        <p class="text-[13px] text-ink-500">Tidak lulus</p>
        <p class="mt-2 text-2xl font-extrabold text-[#991B1B]"><?= (int) $ringkas['TIDAK_LULUS'] ?></p>
        <p class="mt-2 text-xs text-ink-400">seleksi sudah dibacakan</p>
      </div>
      <div class="card p-5">
        <p class="text-[13px] text-ink-500">Masih dalam proses</p>
        <p class="mt-2 text-2xl font-extrabold <?= $ringkas['PROSES'] > 0 ? 'text-[#92400E]' : 'text-ink-400' ?>"><?= (int) $ringkas['PROSES'] ?></p>
        <p class="mt-2 text-xs text-ink-400">
          <?= $ringkas['PROSES'] > 0 ? 'belum ada keputusan akhir' : 'selesai semua' ?>
        </p>
      </div>
    </section>

    <!-- Dua catatan -->
    <section class="mt-6 grid gap-4 lg:grid-cols-2">

      <!-- Persetujuan sebelum terbit -->
      <div class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="font-bold text-ink-900">Persetujuan Daftar Hasil</h2>
            <p class="mt-1 text-[13px] text-ink-500">Membuka tombol "Umumkan Sekarang" di sisi operator.</p>
          </div>
          <?= $notaSetuju['badge'] ?>
        </div>
        <p class="mt-3 text-[12px] text-ink-500"><?= e($notaSetuju['ket']) ?></p>

        <?php if (!empty($umum['diumumkan'])): ?>
          <p class="mt-3 text-[12px] text-ink-400">
            Rilis ini sudah terbit, jadi persetujuan tidak lagi mengunci apa pun. Catatannya tetap disimpan
            sebagai bukti siapa yang menyetujui daftar tersebut.
          </p>
        <?php endif; ?>

        <?php if ($belumAdaHasil): ?>
          <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-800">
            Belum ada keputusan seleksi (LULUS/TIDAK_LULUS). Persetujuan baru berarti setelah operator membacakan hasil.
          </div>
        <?php elseif ($ringkas['PROSES'] > 0): ?>
          <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-800">
            Masih ada <?= (int) $ringkas['PROSES'] ?> pendaftar berstatus "Dalam Proses". Yang disetujui adalah daftar
            berikut, termasuk <?= (int) $ringkas['PROSES'] ?> nama yang belum diputuskan.
          </div>
        <?php endif; ?>

        <?php if ($sidikKini === ''): ?>
          <div class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-[13px] text-red-700">
            Daftar hasil tidak terbaca dari database. Tidak ada yang bisa disetujui sampai koneksi pulih.
          </div>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap gap-2 no-print">
          <?php if (!$setuju['ada'] || $setuju['batal']): ?>
            <button type="button" <?= (!$bolehTulis || $belumAdaHasil || $sidikKini === '') ? 'disabled' : '' ?>
                    data-modal-open="modal-setujui"
                    class="btn btn-primary shadow-drop <?= (!$bolehTulis || $belumAdaHasil || $sidikKini === '') ? 'opacity-50 cursor-not-allowed' : '' ?>">
              <?= icon('how_to_reg') ?> Setujui Daftar Ini
            </button>
          <?php else: ?>
            <button type="button" <?= $bolehTulis ? '' : 'disabled' ?> data-modal-open="modal-cabut"
                    class="btn btn-outline <?= $bolehTulis ? '' : 'opacity-50 cursor-not-allowed' ?>">
              <?= icon('undo') ?> Cabut Persetujuan
            </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Tanda tangan rilis yang sudah terbit -->
      <div class="card p-5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="font-bold text-ink-900">Tanda Tangan Rilis</h2>
            <p class="mt-1 text-[13px] text-ink-500">Membubuhkan tanda tangan pada hasil yang sudah terbit.</p>
          </div>
          <?= $notaTtd['badge'] ?>
        </div>
        <p class="mt-3 text-[12px] text-ink-500"><?= e($notaTtd['ket']) ?></p>

        <?php if (empty($umum['diumumkan'])): ?>
          <p class="mt-3 text-[12px] text-ink-400">
            Belum ada rilis yang bisa ditandatangani. Tanda tangan baru dapat dibubuhkan setelah operator
            menerbitkan hasil seleksi.
          </p>
        <?php endif; ?>

        <div class="mt-4 flex flex-wrap gap-2 no-print">
          <?php if (!$ttd['ada'] || $ttd['batal']): ?>
            <button type="button" <?= (!$bolehTulis || !$bolehTtd || $sidikKini === '') ? 'disabled' : '' ?>
                    data-modal-open="modal-ttd"
                    class="btn btn-primary shadow-drop <?= (!$bolehTulis || !$bolehTtd || $sidikKini === '') ? 'opacity-50 cursor-not-allowed' : '' ?>">
              <?= icon('draw') ?> Tandatangani Rilis
            </button>
          <?php else: ?>
            <button type="button" <?= $bolehTulis ? '' : 'disabled' ?> data-modal-open="modal-cabut-ttd"
                    class="btn btn-outline <?= $bolehTulis ? '' : 'opacity-50 cursor-not-allowed' ?>">
              <?= icon('undo') ?> Cabut Tanda Tangan
            </button>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Kontrol layar -->
    <div class="no-print mt-6 card p-4 flex flex-col sm:flex-row sm:items-end gap-3">
      <form method="get" action="<?= url('app/kepsek/penetapan.php') ?>" class="flex flex-col sm:flex-row sm:items-end gap-3">
        <div>
          <label class="label" for="s">Keputusan</label>
          <select id="s" name="s" class="select w-full sm:w-48">
            <option value="">Semua (<?= count($baris) ?>)</option>
            <?php foreach ($labelSeleksi as $kode => $l): ?>
              <option value="<?= e($kode) ?>" <?= $s === $kode ? 'selected' : '' ?>>
                <?= e($l[0]) ?> (<?= (int) $ringkas[$kode] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-outline"><?= icon('filter_alt') ?> Terapkan</button>
      </form>
      <div class="flex-1"></div>
      <p class="text-[12px] text-ink-400">Sidik daftar: <span class="font-mono"><?= $sidikKini === '' ? '—' : substr($sidikKini, 0, 12) ?></span></p>
    </div>

    <!-- Daftar yang disetujui -->
    <div class="mt-4 card overflow-hidden">
      <div class="overflow-x-auto scroll-thin">
        <table class="w-full min-w-[720px] border-collapse">
          <thead class="thead">
            <tr>
              <th class="th w-10">No</th>
              <th class="th">Pendaftaran</th>
              <th class="th">Calon Siswa</th>
              <th class="th text-right">Nilai Akhir</th>
              <th class="th">Keputusan</th>
              <th class="th">Kelas</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <?php if (!$tampil): ?>
              <tr>
                <td class="td text-center text-ink-400" colspan="6">
                  <?= $ringkas['total'] === 0 ? 'Belum ada pendaftar aktif.' : 'Tidak ada baris pada filter ini.' ?>
                </td>
              </tr>
            <?php endif; ?>
            <?php foreach ($tampil as $i => $b): $l = $labelSeleksi[$b['seleksi']] ?? [$b['seleksi'], 'gray']; ?>
              <tr class="tr-hover">
                <td class="td text-ink-400"><?= $i + 1 ?></td>
                <td class="td font-mono text-[12px] font-semibold text-ink-900 whitespace-nowrap"><?= e($b['reg']) ?></td>
                <td class="td">
                  <div class="font-semibold text-ink-900"><?= e($b['nama']) ?></div>
                  <div class="text-[11px] text-ink-400"><?= e($b['sekolah']) ?><?= $b['jalur'] ? ' · ' . e($b['jalur']) : '' ?></div>
                </td>
                <td class="td text-right font-mono text-[13px] text-ink-700">
                  <?= $b['nilai'] === null ? '—' : number_format((float) $b['nilai'], 2) ?>
                </td>
                <td class="td whitespace-nowrap"><?= badge($l[0], $l[1]) ?></td>
                <td class="td font-mono text-[12px]"><?= e($b['kelas'] ?: '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tanda tangan di atas kertas -->
    <div class="mt-8 flex justify-end">
      <div class="text-center text-sm">
        <p class="text-ink-500"><?= bulan(date('Y-m-d')) ?></p>
        <p class="mt-1 font-medium text-ink-700"><?= e(trim((string) ($me['jabatan'] ?? '')) ?: 'Kepala Sekolah') ?></p>
        <div class="h-16"></div>
        <?php if ($ttd['ada'] && !$ttd['batal']): ?>
          <p class="text-[11px] text-[#065F46]">Sudah ditandatangani digital · <?= e($tanggalCatat($ttd['kapan'])) ?></p>
        <?php endif; ?>
        <p class="font-semibold text-ink-900 underline underline-offset-4"><?= e($USER_LOGIN['nama']) ?></p>
      </div>
    </div>

    <?php if (!$bolehTulis): ?>
      <p class="mt-6 text-[12px] text-ink-400">
        Anda login sebagai admin. Halaman ini hanya dapat dilihat — persetujuan dan tanda tangan dibubuhkan
        memakai akun Kepala Sekolah.
      </p>
    <?php endif; ?>

  </main>
</div>

<!-- ====================== MODAL KONFIRMASI ====================== -->
<?php
/** Satu formulir konfirmasi: kolom tersembunyi + pernyataan + rincian yang dicatat. */
$modal = function (string $id, string $aksi, string $ikon, string $kelasIkon, string $judul, string $sub, string $ket, string $tombol, bool $warn = false) use ($ringkas, $sidikKini) {
    ?>
    <div id="<?= e($id) ?>" class="modal-root hidden fixed inset-0 z-[60]">
      <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
      <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
        <form method="post" action="<?= url('app/kepsek/penetapan.php') ?>"
              class="animate-pop w-full max-w-lg bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="<?= e($aksi) ?>">

          <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
            <div class="flex items-start gap-3">
              <span class="icon-box <?= e($kelasIkon) ?> shrink-0"><?= icon($ikon) ?></span>
              <div>
                <h3 class="font-bold text-ink-900 text-lg leading-tight"><?= e($judul) ?></h3>
                <p class="text-[13px] text-ink-500"><?= e($sub) ?></p>
              </div>
            </div>
            <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
          </div>

          <div class="p-5">
            <?php if ($warn): ?>
              <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-800"><?= e($ket) ?></div>
            <?php else: ?>
              <p class="text-[13px] text-ink-500"><?= e($ket) ?></p>
            <?php endif; ?>

            <ul class="mt-3 space-y-1 text-[12px] text-ink-500 list-disc list-inside">
              <li><?= (int) $ringkas['total'] ?> pendaftar aktif · LULUS <?= (int) $ringkas['LULUS'] ?> · TIDAK LULUS <?= (int) $ringkas['TIDAK_LULUS'] ?> · PROSES <?= (int) $ringkas['PROSES'] ?></li>
              <li>Catatan menempel pada sidik <span class="font-mono"><?= $sidikKini === '' ? '—' : e(substr($sidikKini, 0, 12)) ?></span>; berubahnya isi daftar membatalkan catatan ini.</li>
            </ul>

            <?php if ($aksi === 'setujui'): ?>
              <label class="mt-4 flex items-start gap-2.5 text-[13px] text-ink-700 cursor-pointer">
                <input type="checkbox" name="nyata" value="1" class="mt-0.5" required>
                <span>Saya telah menelaah daftar hasil di atas dan menyetujuinya untuk diterbitkan.</span>
              </label>
            <?php elseif ($aksi === 'ttd'): ?>
              <label class="mt-4 flex items-start gap-2.5 text-[13px] text-ink-700 cursor-pointer">
                <input type="checkbox" name="nyata" value="1" class="mt-0.5" required>
                <span>Saya menandatangani rilis hasil seleksi ini dan bertanggung jawab atas isinya.</span>
              </label>
            <?php endif; ?>
          </div>

          <div class="px-5 py-4 bg-canvas border-t border-line flex items-center justify-end gap-2">
            <button type="button" class="btn btn-sm btn-outline" data-modal-close>Batal</button>
            <button type="submit" class="btn btn-sm <?= $aksi === 'cabut' || $aksi === 'cabut_ttd' ? 'btn-danger' : 'btn-primary' ?>"><?= e($tombol) ?></button>
          </div>
        </form>
      </div>
    </div>
    <?php
};

$adaProses = $ringkas['PROSES'] > 0;

$modal('modal-setujui', 'setujui', 'how_to_reg', 'bg-[#ECFDF5] text-[#047857]',
    'Setujui Daftar Hasil',
    'Operator dapat menerbitkan pengumuman setelah ini.',
    'Persetujuan berlaku untuk daftar dengan isi ini saja. Bila nanti ada nilai atau status yang diubah, '
        . 'persetujuan batal dengan sendirinya dan operator perlu meminta persetujuan baru.',
    'Ya, Setujui', $adaProses);

$modal('modal-ttd', 'ttd', 'draw', 'bg-[#EFF6FF] text-[#1D4ED8]',
    'Tandatangani Rilis',
    'Tanda tangan melekat pada hasil yang sedang berlaku.',
    'Tanda tangan bersifat administratif: ia mencatat bahwa rilis ini sudah Anda setujui untuk dilihat publik, '
        . 'dan ikut batal bila isi daftar berubah.',
    'Ya, Tandatangani', $adaProses);

$modal('modal-cabut', 'cabut', 'lock', 'bg-[#FEF2F2] text-[#991B1B]',
    'Cabut Persetujuan',
    'Pengumuman kembali terkunci.',
    'Tanpa persetujuan, tombol "Umumkan Sekarang" di halaman Pengumuman tidak dapat dipakai operator sampai '
        . 'daftar disetujui ulang.',
    'Cabut Persetujuan');

$modal('modal-cabut-ttd', 'cabut_ttd', 'lock', 'bg-[#FEF2F2] text-[#991B1B]',
    'Cabut Tanda Tangan',
    'Catatan tanda tangan akan dihapus.',
    'Rilis yang sudah terbit tetap tampil di publik — yang hilang hanya catatan tanda tangan kepala sekolah '
        . 'pada daftar ini.',
    'Cabut Tanda Tangan');
?>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
</script>
</body>
</html>
