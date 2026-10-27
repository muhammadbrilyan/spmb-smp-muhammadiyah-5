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
$desc  = 'Input nilai CBT, BTQ, dan wawancara "” keputusan LULUS/TIDAK_LULUS .';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* Ambang nilai kelulusan (bisa diubah per simpan). */
$AMBANG_DEFAULT = 70.0;

$galat = [];
$tersimpan = 0;
$pratinjau = null; /* hasil parse CSV (Fase 7) */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        header('Location: ' . url('app/operator/seleksi.php'));
        exit;
    }
    $aksiPost = (string) ($_POST['aksi'] ?? 'simpan_tabel');

    /* --- Fase 7: pratinjau impor CSV --- */
    if ($aksiPost === 'preview_csv') {
        $file = $_FILES['csv_nilai'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Pilih berkas CSV nilai (maksimal 1 MB).');
            header('Location: ' . url('app/operator/seleksi.php'));
            exit;
        }
        if ($file['size'] > 1024 * 1024) {
            flash('error', 'Ukuran CSV maksimal 1 MB.');
            header('Location: ' . url('app/operator/seleksi.php'));
            exit;
        }
        $isi = file_get_contents($file['tmp_name']);
        if ($isi === false) {
            flash('error', 'Berkas CSV tidak dapat dibaca.');
            header('Location: ' . url('app/operator/seleksi.php'));
            exit;
        }
        /* Buang BOM UTF-8 bila ada. */
        if (strncmp($isi, "\xEF\xBB\xBF", 3) === 0) {
            $isi = substr($isi, 3);
        }
        $barisCsv = preg_split('/\r\n|\r|\n/', trim($isi));
        $pratinjau = [];
        $nomor = 0;
        foreach ($barisCsv as $ln => $barisRaw) {
            if (trim($barisRaw) === '') {
                continue;
            }
            $kolom = str_getcsv($barisRaw, ';');
            if (count($kolom) < 4) {
                $kolom = str_getcsv($barisRaw, ',');
            }
            if ($ln === 0 && strtolower(trim($kolom[0] ?? '')) === 'no_pendaftaran') {
                continue; /* baris header */
            }
            $nomor++;
            if ($nomor > 500) {
                $galat[] = 'CSV dibatasi 500 baris data.';
                break;
            }
            $no   = trim((string) ($kolom[0] ?? ''));
            $cbt  = is_numeric($kolom[1] ?? null) ? max(0, min(100, (float) $kolom[1])) : null;
            $btq  = is_numeric($kolom[2] ?? null) ? max(0, min(100, (float) $kolom[2])) : null;
            $waw  = is_numeric($kolom[3] ?? null) ? max(0, min(100, (float) $kolom[3])) : null;
            $jk   = trim((string) ($kolom[4] ?? '')); /* kode jadwal (opsional) */

            $status = 'siap';
            $ket    = '';
            if ($no === '') {
                $status = 'galat';
                $ket    = 'Nomor pendaftaran kosong.';
            } elseif ($cbt === null || $btq === null || $waw === null) {
                $status = 'galat';
                $ket    = 'Nilai CBT/BTQ/Wawancara harus angka 0–100.';
            } else {
                $p = q_all(
                    "SELECT p.id, p.tahap, s.hasil
                       FROM pendaftar p
                  LEFT JOIN seleksi s ON s.pendaftar_id = p.id
                      WHERE p.no_pendaftaran = ? LIMIT 1",
                    [$no]
                );
                if (!$p) {
                    $status = 'galat';
                    $ket    = 'No. pendaftaran tidak ditemukan.';
                } elseif (!in_array($p[0]['tahap'], ['JADWAL_DITETAPKAN', 'NILAI_TERINPUT'], true)) {
                    $status = 'galat';
                    $ket    = 'Tahap tidak siap dinilai (' . $p[0]['tahap'] . ').';
                } else {
                    $jadwalIdCsv = null;
                    if ($jk !== '') {
                        $jj = q_col('SELECT id FROM jadwal_tes WHERE kode = ?', [$jk]);
                        if (!$jj) {
                            $status = 'galat';
                            $ket    = 'Kode jadwal "' . $jk . '" tidak ditemukan.';
                        } else {
                            $jadwalIdCsv = $jj;
                        }
                    }
                    if ($status === 'siap') {
                        $pratinjau[] = [
                            'pendaftar_id' => $p[0]['id'],
                            'no'           => $no,
                            'cbt'          => $cbt,
                            'btq'          => $btq,
                            'waw'          => $waw,
                            'jadwal_id'    => $jadwalIdCsv,
                            'hasil_lama'   => $p[0]['hasil'] ?? 'PROSES',
                        ];
                        continue;
                    }
                }
            }
            $pratinjau[] = ['galat' => true, 'no' => $no, 'ket' => $ket];
        }
        if ($galat) {
            flash('error', implode(' ', $galat));
            header('Location: ' . url('app/operator/seleksi.php'));
            exit;
        }
        if (!$pratinjau) {
            flash('error', 'CSV tidak berisi baris data (format: no_pendaftaran;cbt;btq;wawancara[;kode_jadwal]).');
            header('Location: ' . url('app/operator/seleksi.php'));
            exit;
        }
    }

    /* --- Fase 7: eksekusi impor (dari hasil pratinjau) --- */
    if ($aksiPost === 'impor_csv') {
        $noArr    = (array) ($_POST['imp_no'] ?? []);
        $cbtArr   = (array) ($_POST['imp_cbt'] ?? []);
        $btqArr   = (array) ($_POST['imp_btq'] ?? []);
        $wawArr   = (array) ($_POST['imp_waw'] ?? []);
        $jidArr   = (array) ($_POST['imp_jid'] ?? []);
        $okImpor  = 0;
        $gagalImpor = 0;
        foreach ($noArr as $i => $no) {
            $no = trim((string) $no);
            if ($no === '' || !is_numeric($cbtArr[$i] ?? null) || !is_numeric($btqArr[$i] ?? null) || !is_numeric($wawArr[$i] ?? null)) {
                $gagalImpor++;
                continue;
            }
            $pid = q_col('SELECT id FROM pendaftar WHERE no_pendaftaran = ? AND tahap IN ("JADWAL_DITETAPKAN","NILAI_TERINPUT")', [$no]);
            if (!$pid) {
                $gagalImpor++;
                continue;
            }
            $jid = trim((string) ($jidArr[$i] ?? ''));
            if (seleksi_impor_nilai(
                $pid,
                $jid !== '' ? $jid : null,
                max(0, min(100, (float) $cbtArr[$i])),
                max(0, min(100, (float) $btqArr[$i])),
                max(0, min(100, (float) $wawArr[$i]))
            )) {
                $okImpor++;
            } else {
                $gagalImpor++;
            }
        }
        flash($okImpor > 0 ? 'sukses' : 'error',
            $okImpor . ' baris nilai diimpor.' . ($gagalImpor > 0 ? ' ' . $gagalImpor . ' baris dilewati/gagal.' : ''));
        header('Location: ' . url('app/operator/seleksi.php'));
        exit;
    }

    /* --- Simpan tabel input manual (struktur nilai tidak berubah) --- */
    $ambang = (float) ($_POST['ambang'] ?? $AMBANG_DEFAULT);
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
        flash('sukses', $tersimpan . ' data hasil seleksi berhasil disimpan.');
        header('Location: ' . url('app/operator/seleksi.php'));
        exit;
    }
}

$pendaftarList = pendaftar_untuk_nilai();
$jadwalList    = daftar_jadwal_tes_admin();
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
      Isi nilai CBT, BTQ, dan wawancara (0–100). Nilai akhir = rata-rata ketiga nilai.
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

    <!-- Impor CSV (Fase 7) -->
    <section class="mt-6 card p-5 sm:p-6">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-bold text-ink-900">Impor Nilai CSV</h2>
        <span class="badge badge-gray">Opsional</span>
      </div>
      <p class="mt-1 text-[13px] text-ink-500">
        Format kolom (pisah <code class="text-[12px]">;</code> atau <code class="text-[12px]">,</code>):
        <code class="text-[12px]">no_pendaftaran;cbt;btq;wawancara;kode_jadwal</code> — kolom kode jadwal opsional.
        Keputusan LULUS/TIDAK_LULUS yang sudah ada <strong>tidak diubah</strong> oleh impor.
      </p>
      <form method="post" action="<?= url('app/operator/seleksi.php') ?>" enctype="multipart/form-data" class="mt-4 flex flex-wrap items-end gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="preview_csv">
        <div class="flex-1 min-w-[240px]">
          <label class="label" for="csv_nilai">Berkas CSV (maks 1 MB, 500 baris)</label>
          <input id="csv_nilai" name="csv_nilai" type="file" accept=".csv,text/csv" class="input h-10 text-[13px] file:mr-3" required>
        </div>
        <button type="submit" class="btn btn-outline shrink-0"><?= icon('upload_file') ?> Pratinjau</button>
      </form>

      <?php if ($pratinjau !== null): $jmlSiap = count(array_filter($pratinjau, fn($r) => empty($r['galat']))); ?>
        <div class="mt-5 border-t border-line-soft pt-4">
          <h3 class="font-semibold text-ink-900">Pratinjau Impor — <?= count($pratinjau) ?> baris (<?= $jmlSiap ?> siap diimpor)</h3>
          <div class="mt-3 overflow-x-auto scroll-thin">
            <table class="w-full min-w-[640px] border-collapse">
              <thead class="thead">
                <tr>
                  <th class="th">No. Pendaftaran</th>
                  <th class="th text-center">CBT</th>
                  <th class="th text-center">BTQ</th>
                  <th class="th text-center">Wawancara</th>
                  <th class="th">Keputusan Saat Ini</th>
                  <th class="th">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-line-soft">
                <?php foreach ($pratinjau as $r): ?>
                  <tr class="tr-hover">
                    <td class="td font-mono text-[13px]"><?= e($r['no'] ?: '—') ?></td>
                    <?php if (!empty($r['galat'])): ?>
                      <td class="td text-center">—</td>
                      <td class="td text-center">—</td>
                      <td class="td text-center">—</td>
                      <td class="td">—</td>
                      <td class="td text-[13px] text-red-700"><?= e($r['ket']) ?></td>
                    <?php else: ?>
                      <td class="td text-center"><?= e((string) $r['cbt']) ?></td>
                      <td class="td text-center"><?= e((string) $r['btq']) ?></td>
                      <td class="td text-center"><?= e((string) $r['waw']) ?></td>
                      <td class="td text-[13px]"><?= e($r['hasil_lama']) ?></td>
                      <td class="td"><?= badge('Siap', 'green') ?></td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <?php if ($jmlSiap > 0): ?>
            <form method="post" action="<?= url('app/operator/seleksi.php') ?>" class="mt-4 flex flex-wrap items-center justify-end gap-3 border-t border-line-soft pt-4"
                  onsubmit="return confirm('Impor <?= $jmlSiap ?> baris nilai CSV? Keputusan LULUS/TIDAK_LULUS yang sudah ada tidak berubah.');">
              <?= csrf_field() ?>
              <input type="hidden" name="aksi" value="impor_csv">
              <?php foreach ($pratinjau as $r): if (!empty($r['galat'])) continue; ?>
                <input type="hidden" name="imp_no[]" value="<?= e($r['no']) ?>">
                <input type="hidden" name="imp_cbt[]" value="<?= e((string) $r['cbt']) ?>">
                <input type="hidden" name="imp_btq[]" value="<?= e((string) $r['btq']) ?>">
                <input type="hidden" name="imp_waw[]" value="<?= e((string) $r['waw']) ?>">
                <input type="hidden" name="imp_jid[]" value="<?= e((string) ($r['jadwal_id'] ?? '')) ?>">
              <?php endforeach; ?>
              <a href="<?= url('app/operator/seleksi.php') ?>" class="btn btn-outline">Batal</a>
              <button type="submit" class="btn btn-primary shadow-drop"><?= icon('download') ?> Impor <?= $jmlSiap ?> Baris</button>
            </form>
          <?php else: ?>
            <p class="mt-4 text-[13px] text-red-700">Tidak ada baris siap diimpor. Perbaiki CSV lalu unggah ulang.</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>

    <form method="post" action="<?= url('app/operator/seleksi.php') ?>" id="form-seleksi">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="simpan_tabel">

      <div class="mt-5 card p-4 flex flex-wrap items-center gap-4">
        <div>
          <label class="label" for="ambang">Ambang Kelulusan (nilai akhir minimal)</label>
          <input id="ambang" name="ambang" type="number" min="0" max="100" step="0.5" value="<?= e($GLOBALS['seleksi_ambang'] ?? $AMBANG_DEFAULT) ?>" class="input w-28" data-ambang>
        </div>
        <p class="hint flex-1 min-w-[200px] mb-1">Ubah ambang lalu tekan "Hitung Ulang Otomatis" bila ingin menentukan ulang keputusan berdasarkan ambang baru.</p>
        <button type="button" class="btn btn-outline" id="btn-hitung"><?= icon('calculate', 'text-[18px]') ?> Hitung Ulang Otomatis</button>
      </div>

      <div class="mt-4 card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
          <table class="w-full min-w-[1000px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th">No. Pendaftaran</th>
                <th class="th">Nama Calon Siswa</th>
                <th class="th text-center">CBT</th>
                <th class="th text-center">BTQ</th>
                <th class="th text-center">Wawancara</th>
                <th class="th text-center">Akhir</th>
                <th class="th">Jadwal Tes</th>
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
                <tr class="tr-hover" data-baris>
                  <input type="hidden" name="pendaftar_id[]" value="<?= e($p['id']) ?>">
                  <td class="td font-mono text-[13px] text-primary"><?= e($p['no_pendaftaran']) ?></td>
                  <td class="td">
                    <div class="font-semibold text-ink-900"><?= e($p['nama_siswa']) ?></div>
                    <div class="text-[12px] text-ink-500">Jalur <?= e($p['jalur'] ?: 'Reguler') ?> '· <?= e($p['status_verifikasi']) ?></div>
                  </td>
                  <td class="td text-center"><input name="nilai_cbt[]" type="number" min="0" max="100" step="0.5" value="<?= $cbt ?: '' ?>" class="input w-20 text-center mx-auto" data-nilai></td>
                  <td class="td text-center"><input name="nilai_btq[]" type="number" min="0" max="100" step="0.5" value="<?= $btq ?: '' ?>" class="input w-20 text-center mx-auto" data-nilai></td>
                  <td class="td text-center"><input name="nilai_wawancara[]" type="number" min="0" max="100" step="0.5" value="<?= $waw ?: '' ?>" class="input w-20 text-center mx-auto" data-nilai></td>
                  <td class="td text-center"><span class="text-base font-extrabold text-ink-900" data-akhir><?= number_format($akhir, 1, ',', '.') ?></span></td>
                  <td class="td">
                    <select name="jadwal_id[]" class="select h-9 w-full min-w-[160px]">
                      <option value="">"” tanpa jadwal "”</option>
                      <?php foreach ($jadwalList as $j): ?>
                        <option value="<?= e($j['id']) ?>" <?= ($p['jadwal_id'] ?? '') === $j['id'] ? 'selected' : '' ?>>
                          <?= e($j['kode']) ?> '· <?= e($j['nama_kegiatan']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td class="td text-center">
                    <select name="hasil[]" class="select h-9 w-36 mx-auto" data-hasil>
                      <option value="PROSES" <?= $hasilSaatIni === 'PROSES' ? 'selected' : '' ?>>Proses</option>
                      <option value="LULUS" <?= $hasilSaatIni === 'LULUS' ? 'selected' : '' ?>>Lulus</option>
                      <option value="TIDAK_LULUS" <?= $hasilSaatIni === 'TIDAK_LULUS' ? 'selected' : '' ?>>Tidak Lulus</option>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$pendaftarList): ?>
                <tr><td colspan="8" class="td text-center text-ink-400">Belum ada pendaftar untuk dinilai.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="px-4 py-3.5 border-t border-line bg-white flex flex-wrap items-center justify-between gap-3">
          <p class="text-[13px] text-ink-500">Baris tanpa nilai &amp; berstatus Proses akan dilewati saat disimpan.</p>
          <button type="submit" class="btn btn-primary shadow-drop"><?= icon('save') ?> Simpan Hasil Seleksi</button>
        </div>
      </div>
    </form>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
  (function () {
    var btn = document.getElementById('btn-hitung');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var ambangEl = document.querySelector('[data-ambang]');
      var ambang = parseFloat(ambangEl ? ambangEl.value : '70') || 70;
      document.querySelectorAll('[data-baris]').forEach(function (tr) {
        var inputs = tr.querySelectorAll('[data-nilai]');
        var cbt = parseFloat(inputs[0] && inputs[0].value) || 0;
        var btq = parseFloat(inputs[1] && inputs[1].value) || 0;
        var waw = parseFloat(inputs[2] && inputs[2].value) || 0;
        var akhir = (cbt + btq + waw) / 3;
        var elAkhir = tr.querySelector('[data-akhir]');
        if (elAkhir) elAkhir.textContent = akhir.toFixed(1).replace('.', ',');
        var sel = tr.querySelector('[data-hasil]');
        if (sel && (cbt || btq || waw)) {
          sel.value = akhir >= ambang ? 'LULUS' : 'TIDAK_LULUS';
        }
      });
      showToast('Keputusan dihitung ulang dari ambang nilai.', 'sukses');
    });
  })();
</script>
</body>
</html>
