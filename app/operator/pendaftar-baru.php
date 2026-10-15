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

$title = 'Pendaftaran Manual';
$desc  = 'Input pendaftar baru lengkap oleh operator — tanpa batas 1x daftar.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

$galat = [];
$hasilNo = null;
$f = [
    'nama' => '', 'nisn' => '', 'jk' => 'L', 'ttl' => '', 'tgl_lahir' => '',
    'alamat' => '', 'asal' => '', 'jalur' => 'Reguler', 'luar' => 0,
    'nama_ot' => '', 'wa' => '', 'alamat_ot' => '', 'pekerjaan_ot' => '',
    'verifikasi_manual' => 0,
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
        header('Location: ' . url('app/operator/pendaftar-baru.php'));
        exit;
    }
    foreach (array_keys($f) as $k) {
        if (array_key_exists($k, $_POST)) {
            $f[$k] = is_int($f[$k]) ? (int) $_POST[$k] : trim((string) $_POST[$k]);
        }
    }
    $f['jk'] = $f['jk'] === 'P' ? 'P' : 'L';
    $f['nisn'] = preg_replace('/\D+/', '', $f['nisn']);
    $f['wa'] = preg_replace('/\D+/', '', $f['wa']);
    if (strpos($f['wa'], '0') === 0) {
        $f['wa'] = '62' . substr($f['wa'], 1);
    }
    if (!isset($f['jalur']) || !in_array($f['jalur'], ['Reguler', 'Tahfidz', 'Prestasi', 'Afirmasi'], true)) {
        $f['jalur'] = 'Reguler';
    }

    if (mb_strlen($f['nama']) < 3) $galat[] = 'Nama siswa minimal 3 karakter.';
    if ($f['nisn'] !== '' && strlen($f['nisn']) !== 10) $galat[] = 'NISN harus 10 digit atau kosong.';
    if ($f['alamat'] === '') $galat[] = 'Alamat siswa wajib diisi.';
    if ($f['asal'] === '') $galat[] = 'Asal sekolah wajib diisi.';
    if (mb_strlen($f['nama_ot']) < 3) $galat[] = 'Nama orang tua minimal 3 karakter.';
    if (!preg_match('/^62[0-9]{8,13}$/', $f['wa'])) $galat[] = 'Nomor WhatsApp orang tua tidak valid (awali 0, sistem otomatis konversi).';
    if ($f['tgl_lahir'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['tgl_lahir'])) $galat[] = 'Format tanggal lahir tidak valid.';

    if (!$galat) {
        $r = pendaftar_manual($f + ['alamat_ot' => $f['alamat_ot'], 'pekerjaan_ot' => $f['pekerjaan_ot'], 'luar' => $f['luar'], 'verifikasi_manual' => $f['verifikasi_manual']]);
        if ($r['ok']) {
            $hasilNo = $r['no'];
            flash('sukses', 'Pendaftar ' . $f['nama'] . ' berhasil ditambahkan dengan no. ' . $r['no'] . '.');
            /* reset form, tapi pertahankan hasil */
            $f = array_map(fn($v) => is_int($v) ? 0 : '', $f);
            $f['jk'] = 'L'; $f['jalur'] = 'Reguler';
        } else {
            $galat[] = $r['pesan'];
        }
    }
}

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'ppdb';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik', 'SPMB', 'Pendaftaran Manual'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[900px]">

    <div class="flex items-center gap-2 flex-wrap">
      <span class="badge badge-blue">Operator</span>
      <span class="badge badge-gray">Tanpa batas 1× daftar</span>
    </div>
    <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Pendaftaran Manual</h1>
    <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
      Input pendaftar baru lengkap (siswa + orang tua). No. pendaftaran dibuat otomatis.
      Biaya formulir <strong><?= rupiah($PENGATURAN['biaya_formulir']) ?></strong> otomatis ditagih (PENDING).
    </p>

    <?php if ($galat): ?>
      <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
        <p class="font-semibold">Periksa isian berikut:</p>
        <ul class="mt-2 space-y-1 list-disc list-inside">
          <?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($hasilNo): ?>
      <div class="mt-5 rounded-2xl border border-[#A7F3D0] bg-[#ECFDF5] p-4 text-sm text-[#065F46]" role="status">
        <p class="font-semibold">Pendaftar berhasil ditambahkan.</p>
        <p class="mt-1">No. Pendaftaran: <span class="font-mono font-bold"><?= e($hasilNo) ?></span> — lanjutkan input berikutnya atau <a class="underline font-semibold" href="<?= url('app/operator/index.php') ?>">kembali ke dashboard</a>.</p>
      </div>
    <?php endif; ?>

    <form method="post" action="<?= url('app/operator/pendaftar-baru.php') ?>" class="mt-6 space-y-6">
      <?= csrf_field() ?>

      <!-- SISWA -->
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Data Siswa</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div class="sm:col-span-2 lg:col-span-3">
            <label class="label" for="nama">Nama Lengkap <span class="text-red-600">*</span></label>
            <input id="nama" name="nama" class="input" value="<?= e($f['nama']) ?>" required>
          </div>
          <div>
            <label class="label" for="nisn">NISN (10 digit)</label>
            <input id="nisn" name="nisn" class="input font-mono" inputmode="numeric" maxlength="10" value="<?= e($f['nisn']) ?>">
          </div>
          <div>
            <label class="label" for="jk">Jenis Kelamin <span class="text-red-600">*</span></label>
            <select id="jk" name="jk" class="select">
              <option value="L" <?= $f['jk'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= $f['jk'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
          <div>
            <label class="label" for="jalur">Jalur <span class="text-red-600">*</span></label>
            <select id="jalur" name="jalur" class="select">
              <option value="Reguler" <?= $f['jalur'] === 'Reguler' ? 'selected' : '' ?>>Reguler</option>
              <option value="Tahfidz" <?= $f['jalur'] === 'Tahfidz' ? 'selected' : '' ?>>Tahfidz</option>
              <option value="Prestasi" <?= $f['jalur'] === 'Prestasi' ? 'selected' : '' ?>>Prestasi</option>
              <option value="Afirmasi" <?= $f['jalur'] === 'Afirmasi' ? 'selected' : '' ?>>Afirmasi</option>
            </select>
          </div>
          <div>
            <label class="label" for="ttl">Tempat Lahir</label>
            <input id="ttl" name="ttl" class="input" value="<?= e($f['ttl']) ?>">
          </div>
          <div>
            <label class="label" for="tgl_lahir">Tanggal Lahir</label>
            <input id="tgl_lahir" name="tgl_lahir" type="date" class="input" value="<?= e($f['tgl_lahir']) ?>">
          </div>
          <div class="sm:col-span-2">
            <label class="label" for="alamat">Alamat <span class="text-red-600">*</span></label>
            <textarea id="alamat" name="alamat" class="textarea" rows="2" required><?= e($f['alamat']) ?></textarea>
          </div>
          <div class="sm:col-span-2">
            <label class="label" for="asal">Asal Sekolah (SD/MI) <span class="text-red-600">*</span></label>
            <input id="asal" name="asal" class="input" value="<?= e($f['asal']) ?>" required>
          </div>
          <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-canvas border border-line px-4 py-3">
            <div>
              <p class="text-sm font-semibold text-ink-900">Opsi Tambahan</p>
              <p class="text-[13px] text-ink-500">Luar daerah → penempatan mengikuti ketentuan. Verifikasi manual → status langsung TERVERIFIKASI.</p>
            </div>
            <div class="flex flex-wrap gap-4">
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="luar" value="1" <?= $f['luar'] ? 'checked' : '' ?> class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
                <span class="text-sm">Luar Daerah</span>
              </label>
              <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="verifikasi_manual" value="1" <?= $f['verifikasi_manual'] ? 'checked' : '' ?> class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4">
                <span class="text-sm">Verifikasi Langsung</span>
              </label>
            </div>
          </div>
        </div>
      </section>

      <!-- ORANG TUA -->
      <section class="card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Data Orang Tua / Wali</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label class="label" for="nama_ot">Nama Orang Tua <span class="text-red-600">*</span></label>
            <input id="nama_ot" name="nama_ot" class="input" value="<?= e($f['nama_ot']) ?>" required>
          </div>
          <div>
            <label class="label" for="wa">No. WhatsApp <span class="text-red-600">*</span></label>
            <input id="wa" name="wa" type="tel" class="input font-mono" placeholder="081234567890" value="<?= e($f['wa']) ?>" required>
          </div>
          <div>
            <label class="label" for="pekerjaan_ot">Pekerjaan</label>
            <input id="pekerjaan_ot" name="pekerjaan_ot" class="input" value="<?= e($f['pekerjaan_ot']) ?>">
          </div>
          <div>
            <label class="label" for="alamat_ot">Alamat Orang Tua</label>
            <input id="alamat_ot" name="alamat_ot" class="input" value="<?= e($f['alamat_ot']) ?>">
          </div>
        </div>
      </section>

      <div class="flex flex-wrap justify-end gap-3">
        <a href="<?= url('app/operator/index.php') ?>" class="btn btn-outline">Batal</a>
        <button type="submit" class="btn btn-primary shadow-drop"><?= icon('save') ?> Simpan Pendaftar</button>
      </div>
    </form>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
</script>
</body>
</html>
