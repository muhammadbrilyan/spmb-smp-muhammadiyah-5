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

$title = 'Input Hasil Seleksi';

/* Judul kolom nilai ikut nama kegiatan jadwal tes (lihat seleksi_label_kolom). */
$kolom      = seleksi_label_kolom();
$labelNilai = implode(', ', $kolom['label']);

$desc  = 'Input nilai ' . $labelNilai . ' — nilai akhir dan keputusan LULUS/TIDAK_LULUS dihitung otomatis.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* Ambang nilai kelulusan — tersimpan di Pengaturan Sekolah, default 70. */
$AMBANG_DEFAULT = 70.0;
$ambangSimpan   = isset($PENGATURAN['ambang_kelulusan']) && $PENGATURAN['ambang_kelulusan'] !== ''
    ? (float) $PENGATURAN['ambang_kelulusan'] : $AMBANG_DEFAULT;

$galat = [];
$tersimpan = 0;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        header('Location: ' . url('app/operator/seleksi.php'));
        exit;
    }
    $aksiPost = (string) ($_POST['aksi'] ?? 'simpan_tabel');

    /* --- Simpan tabel input manual (satu-satunya jalan masuk nilai) --- */
    $ambang = (float) ($_POST['ambang'] ?? $ambangSimpan);
    if ($ambang < 0 || $ambang > 100) {
        $galat[] = 'Ambang nilai harus antara 0 dan 100.';
    }

    $ids    = (array) ($_POST['pendaftar_id'] ?? []);
    $cbts   = (array) ($_POST['nilai_cbt'] ?? []);
    $btqs   = (array) ($_POST['nilai_btq'] ?? []);
    $waws   = (array) ($_POST['nilai_wawancara'] ?? []);
    $jadwals = (array) ($_POST['jadwal_id'] ?? []);
    $hasils = (array) ($_POST['hasil'] ?? []);

    if (!$galat && $aksiPost === 'simpan_tabel') {
        /* Ambang ikut disimpan agar tetap sama saat halaman dibuka lagi. */
        q_exec('UPDATE pengaturan_sekolah SET ambang_kelulusan = ? WHERE id = 1', [$ambang]);
        foreach ($ids as $i => $pid) {
            $pid = trim((string) $pid);
            if ($pid === '') {
                continue;
            }
            $cbt = max(0, min(100, (float) ($cbts[$i] ?? 0)));
            $btq = max(0, min(100, (float) ($btqs[$i] ?? 0)));
            $waw = max(0, min(100, (float) ($waws[$i] ?? 0)));
            $akhir = round(($cbt + $btq + $waw) / 3, 2);
            $hasil = $hasils[$i] ?? 'PROSES';
            if (!in_array($hasil, ['PROSES', 'LULUS', 'TIDAK_LULUS'], true)) {
                $hasil = 'PROSES';
            }
            /* Baris tanpa nilai sama sekali & PROSES -> lewati (tidak membuat baris kosong). */
            if ($cbt == 0 && $btq == 0 && $waw == 0 && $hasil === 'PROSES') {
                continue;
            }
            $jadwalId = trim((string) ($jadwals[$i] ?? ''));
            if (seleksi_simpan($pid, $jadwalId !== '' ? $jadwalId : null, $cbt, $btq, $waw, $akhir, $hasil)) {
                $tersimpan++;
            }
        }
        flash('sukses', $tersimpan . ' data hasil seleksi berhasil disimpan (ambang kelulusan '
            . rtrim(rtrim(number_format($ambang, 1, ',', ''), '0'), ',') . ').');
        header('Location: ' . url('app/operator/seleksi.php'));
        exit;
    }
}

$pendaftarList = pendaftar_untuk_nilai();
$jmlLulusTanpaKelas = lulus_belum_ditempatkan_jumlah();

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'seleksi';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik', 'SPMB', 'Input Hasil Seleksi'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1200px]">

    <div class="flex items-center gap-2 flex-wrap">
      <!-- Info jumlah calon siswa yang dinilai -->
      <span class="badge badge-gray"><?= count($pendaftarList) ?> calon siap dinilai</span>
    </div>
    <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Input Hasil Seleksi</h1>
    <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
      Hanya pendaftar berstatus <strong>JADWAL_DITETAPKAN</strong> / <strong>NILAI_TERINPUT</strong> yang tampil di sini.
      Isi nilai <?= e($labelNilai) ?> (0–100). Nilai akhir = rata-rata ketiga nilai.
      Keputusan <strong>LULUS</strong> / <strong>TIDAK_LULUS</strong> ditentukan otomatis dari ambang nilai — bisa diubah manual per siswa.
    </p>

    <?php if ($flashSelek = flash()): ?>
      <div class="mt-5 rounded-xl border <?= $flashSelek['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flashSelek['pesan']) ?>
      </div>
    <?php endif; ?>

    <?php if ($jmlLulusTanpaKelas > 0): ?>
      <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm flex items-start gap-2">
        <?= icon('warning', 'text-[18px] mt-0.5') ?>
        <span>
          <strong><?= $jmlLulusTanpaKelas ?> siswa berstatus LULUS belum ditempatkan kelas.</strong>
          Selesaikan penempatan di menu <a href="<?= url('app/operator/penempatan.php') ?>" class="underline font-semibold">Penempatan Kelas</a> sebelum pengumuman resmi.
        </span>
      </div>
    <?php endif; ?>

    <?php if ($galat): ?>
      <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
        <?php foreach ($galat as $g): ?><p><?= e($g) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= url('app/operator/seleksi.php') ?>" id="form-seleksi">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan_tabel">

      <div class="mt-5 card p-4 flex flex-wrap items-end gap-4">
        <div>
          <label class="label" for="ambang">Ambang Kelulusan (nilai akhir minimal)</label>
          <input id="ambang" name="ambang" type="number" min="0" max="100" step="0.5" value="<?= e((string) $ambangSimpan) ?>" class="input w-28" data-ambang>
        </div>
        <p class="hint flex-1 min-w-[200px] mb-1">
          Nilai Akhir terhitung otomatis dari rata-rata <?= e($labelNilai) ?>. Bila mencapai ambang,
          keputusan terisi <strong>Lulus</strong> — masih bisa diubah manual per siswa sebelum disimpan.
          Angka ambang ikut tersimpan saat menekan <strong>Simpan Hasil Seleksi</strong>.
        </p>
      </div>

      <!-- Pencarian (hanya menyembunyikan baris, nilai tetap terkirim) -->
      <div class="mt-4 card p-4">
        <label class="label" for="seleksi-cari">Cari calon siswa</label>
        <div class="relative">
          <span class="ms absolute left-3 top-1/2 -translate-y-1/2 text-[19px] text-ink-400">search</span>
          <input id="seleksi-cari" type="search" class="input pl-10" autocomplete="off" data-cari
                 placeholder="No. Pendaftaran atau Nama Calon Siswa">
        </div>
        <div class="mt-3">
          <span class="badge badge-gray" data-info><?= count($pendaftarList) ?> baris ditampilkan</span>
        </div>
        <p class="hint">Baris tersusun urut abjad nama. Menyaring hanya menyembunyikan baris — semua nilai
          yang sudah diketik tetap ikut tersimpan saat <strong>Simpan Hasil Seleksi</strong> ditekan.</p>
      </div>

      <div class="mt-4 card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
          <table class="w-full min-w-[760px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th">No. Pendaftaran</th>
                <th class="th">Nama Calon Siswa</th>
                <?php for ($k = 0; $k < 3; $k++): ?>
                  <?php $penuh = $kolom['lengkap'][$k] ?? ''; ?>
                  <th class="th text-center" <?= $penuh !== '' ? 'title="' . e('Nama kegiatan jadwal: ' . $penuh . ' (' . $kolom['sumber'][$k] . ')') . '"' : '' ?>><?= e($kolom['label'][$k]) ?></th>
                <?php endfor; ?>
                <th class="th text-center">Nilai Akhir</th>
                <th class="th text-center">Keputusan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php foreach ($pendaftarList as $i => $p): ?>
                <?php
                  $cbt  = (float) ($p['nilai_cbt'] ?? 0);
                  $btq  = (float) ($p['nilai_btq'] ?? 0);
                  $waw  = (float) ($p['nilai_wawancara'] ?? 0);
                  $akhir = $p['nilai_akhir'] !== null && $p['nilai_akhir'] !== '' ? (float) $p['nilai_akhir'] : round(($cbt + $btq + $waw) / 3, 2);
                  $hasilSaatIni = $p['hasil'] ?? 'PROSES';
                ?>
                <tr class="tr-hover" data-baris
                    data-no="<?= e($p['no_pendaftaran']) ?>"
                    data-nama="<?= e($p['nama_siswa']) ?>">
                  <input type="hidden" name="pendaftar_id[]" value="<?= e($p['id']) ?>">
                  <?php /* Jadwal tidak ditampilkan di halaman ini, tetapi jangan sampai
                          * tertimpa NULL saat nilai disimpan. */ ?>
                  <input type="hidden" name="jadwal_id[]" value="<?= e($p['jadwal_id'] ?? '') ?>">
                  <td class="td font-mono text-[13px] text-primary"><?= e($p['no_pendaftaran']) ?></td>
                  <td class="td">
                    <div class="font-semibold text-ink-900"><?= e($p['nama_siswa']) ?></div>
                    <div class="text-[12px] text-ink-500">Jalur <?= e($p['jalur'] ?: 'Reguler') ?> · <?= e($p['status_verifikasi']) ?></div>
                  </td>
                  <td class="td text-center"><input name="nilai_cbt[]" type="number" min="0" max="100" step="0.5" value="<?= $cbt ?: '' ?>" class="input w-20 text-center mx-auto" data-nilai></td>
                  <td class="td text-center"><input name="nilai_btq[]" type="number" min="0" max="100" step="0.5" value="<?= $btq ?: '' ?>" class="input w-20 text-center mx-auto" data-nilai></td>
                  <td class="td text-center"><input name="nilai_wawancara[]" type="number" min="0" max="100" step="0.5" value="<?= $waw ?: '' ?>" class="input w-20 text-center mx-auto" data-nilai></td>
                  <td class="td text-center"><span class="text-base font-extrabold text-ink-900" data-akhir><?= number_format($akhir, 1, ',', '.') ?></span></td>
                  <td class="td text-center">
                    <select name="hasil[]" class="select h-9 w-36 mx-auto" data-hasil>
                      <option value="PROSES" <?= $hasilSaatIni === 'PROSES' ? 'selected' : '' ?>>Proses</option>
                      <option value="LULUS" <?= $hasilSaatIni === 'LULUS' ? 'selected' : '' ?>>Lulus</option>
                      <option value="TIDAK_LULUS" <?= $hasilSaatIni === 'TIDAK_LULUS' ? 'selected' : '' ?>>Tidak Lulus</option>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($pendaftarList): ?>
                <tr data-tidak-cocok style="display:none">
                  <td colspan="7" class="td text-center text-ink-400">Tidak ada calon siswa yang cocok dengan pencarian.</td>
                </tr>
              <?php else: ?>
                <tr><td colspan="7" class="td text-center text-ink-400">Belum ada pendaftar untuk dinilai.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="px-4 py-3.5 border-t border-line bg-white flex flex-wrap items-center justify-between gap-3">
          <p class="text-[13px] text-ink-500">Baris tanpa nilai &amp; berstatus Proses akan dilewati saat disimpan.</p>
          <button type="submit" class="btn btn-primary shadow-drop"><?= icon('save') ?> Simpan Hasil Seleksi</button>
        </div>
        <?php if ($kolom['sumber']): ?>
          <p class="px-4 py-3 text-[12px] text-ink-400 border-t border-line-soft">
            Judul kolom nilai diambil dari jadwal tes
            <?php $pertama = true; foreach ($kolom['sumber'] as $i => $kd): ?><?= $pertama ? ' ' : ', ' ?><?php $pertama = false; ?><strong><?= e($kd) ?></strong> — <?= e($kolom['lengkap'][$i]) ?><?php endforeach; ?>.
            <?php if ($kolom['lebih'] > 0): ?>
              <?= $kolom['lebih'] ?> kegiatan jadwal lainnya tidak memiliki kolom nilai sendiri.
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
    </form>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
  (function () {
    /* Nilai Akhir & keputusan dihitung langsung saat angka diketik — tidak
     * ada tombol hitung ulang. Baris yang masih kosong dibiarkan "Proses". */
    var ambangEl = document.querySelector('[data-ambang]');
    var baris = [].slice.call(document.querySelectorAll('[data-baris]'));
    if (!ambangEl || !baris.length) return;
    function ambang() {
      var v = parseFloat(ambangEl.value);
      return isFinite(v) ? v : 70;
    }
    function ambil(el) {
      var v = parseFloat(el.value);
      return isFinite(v) ? Math.max(0, Math.min(100, v)) : null;
    }
    function hitungBaris(tr) {
      var inputs = tr.querySelectorAll('[data-nilai]');
      var elAkhir = tr.querySelector('[data-akhir]');
      var sel = tr.querySelector('[data-hasil]');
      var jumlah = 0, ada = 0;
      inputs.forEach(function (el) {
        var n = ambil(el);
        if (n !== null) { jumlah += n; ada++; }
      });
      if (!ada) {
        if (elAkhir) elAkhir.textContent = '—';
        if (sel) sel.value = 'PROSES';
        return;
      }
      var akhir = jumlah / inputs.length;
      if (elAkhir) elAkhir.textContent = akhir.toFixed(1).replace('.', ',');
      if (sel) sel.value = akhir >= ambang() ? 'LULUS' : 'TIDAK_LULUS';
    }
    baris.forEach(function (tr) {
      tr.querySelectorAll('[data-nilai]').forEach(function (el) {
        el.addEventListener('input', function () { hitungBaris(tr); });
      });
    });
    ambangEl.addEventListener('input', function () {
      baris.forEach(function (tr) {
        if (tr.querySelector('[data-nilai]').value !== '') hitungBaris(tr);
      });
    });
  })();

  /* Pencarian no. pendaftaran / nama: baris hanya disembunyikan, tetap berada
   * di dalam form sehingga nilainya tidak hilang saat disimpan. */
  (function () {
    var cari  = document.querySelector('[data-cari]');
    var info  = document.querySelector('[data-info]');
    if (!cari || !info) return;

    var semua  = [].slice.call(document.querySelectorAll('[data-baris]'));
    var kosong = document.querySelector('[data-tidak-cocok]');

    function terapkan() {
      var kata = cari.value.trim().toLowerCase();
      var tampil = 0;
      semua.forEach(function (tr) {
        var teks = ((tr.getAttribute('data-no') || '') + ' ' + (tr.getAttribute('data-nama') || '')).toLowerCase();
        var cocok = kata === '' || teks.indexOf(kata) !== -1;
        tr.style.display = cocok ? '' : 'none';
        if (cocok) tampil++;
      });
      if (kosong) kosong.style.display = tampil ? 'none' : '';
      info.textContent = tampil === semua.length
        ? semua.length + ' baris ditampilkan'
        : tampil + ' dari ' + semua.length + ' baris ditampilkan';
    }

    /* Enter di kotak cari jangan ikut mengirim form (Simpan). */
    cari.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
      }
    });
    cari.addEventListener('input', terapkan);
    terapkan();
  })();
</script>
</body>
</html>
