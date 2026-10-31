<?php
/**
 * PENGATURAN AKUN — portal siswa (Fase 13).
 *
 * Ganti password, informasi kredensial, kontak terdaftar, dan keluar.
 * Profil singkat sudah ditampilkan topbar/sidebar sehingga halaman ini
 * berfokus pada keamanan akun, bukan mengulang kartu profil.
 */
$title = 'Pengaturan Akun';
$desc  = 'Ubah password, lihat informasi akun, dan kelola sesi portal siswa.';
require __DIR__ . '/_bootstrap.php';

/* ==================================================================
 * PROSES POST — GANTI PASSWORD
 * ================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
        header('Location: ' . url('app/siswa/akun.php'));
        exit;
    }

    $aksi = (string) ($_POST['aksi'] ?? '');
    if ($aksi !== 'ganti_password') {
        flash('error', 'Aksi tidak dikenali.');
        header('Location: ' . url('app/siswa/akun.php'));
        exit;
    }

    $galat = [];
    if (!rate_limit_cek('ganti_password_siswa', 5, 15)
        || !rate_limit_cek('ganti_password_siswa_user', 5, 15, (string) ($me['id'] ?? ''))) {
        $galat[] = 'Terlalu banyak percobaan ganti password. Coba lagi beberapa menit lagi.';
    } else {
        rate_limit_catat('ganti_password_siswa');
        rate_limit_catat('ganti_password_siswa_user', (string) ($me['id'] ?? ''));

        $lama   = (string) ($_POST['password_lama'] ?? '');
        $baru   = (string) ($_POST['password_baru'] ?? '');
        $ulangi = (string) ($_POST['password_ulangi'] ?? '');

        if ($lama === '') {
            $galat[] = 'Password lama wajib diisi.';
        }
        if (strlen($baru) < 10) {
            $galat[] = 'Password baru minimal 10 karakter.';
        }
        if ($baru !== '' && $baru === (string) ($me['username'] ?? '')) {
            $galat[] = 'Password baru tidak boleh sama dengan username.';
        }
        if ($baru !== $ulangi) {
            $galat[] = 'Konfirmasi password baru tidak sama.';
        }

        if (!$galat) {
            $akun = q_all('SELECT id, password_hash FROM users WHERE id = ? AND role = ?', [$me['id'], 'siswa']);
            $akun = $akun[0] ?? null;
            if (!$akun || !password_verify($lama, (string) $akun['password_hash'])) {
                $galat[] = 'Password lama salah.';
            } elseif (password_verify($baru, (string) $akun['password_hash'])) {
                $galat[] = 'Password baru tidak boleh sama dengan password lama.';
            } else {
                $ok = q_exec(
                    'UPDATE users SET password_hash = ?, wajib_ganti_password = 0 WHERE id = ?',
                    [password_hash($baru, PASSWORD_DEFAULT), $akun['id']]
                );
                if ($ok) {
                    session_regenerate_id(true);
                    flash('sukses', 'Password berhasil diganti. Gunakan password baru pada login berikutnya.');
                    header('Location: ' . url('app/siswa/akun.php'));
                    exit;
                }
                $galat[] = 'Gagal menyimpan password baru.';
            }
        }
    }

    if ($galat) {
        flash('error', implode(' ', $galat));
    }
    header('Location: ' . url('app/siswa/akun.php'));
    exit;
}

$flash = flash();
$akunInfo = q_all(
    'SELECT username, role, nama_lengkap, jabatan, is_active, created_at, updated_at
       FROM users WHERE id = ? LIMIT 1',
    [$me['id']]
);
$akunInfo = $akunInfo[0] ?? null;

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'akun';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Siswa' => url('app/siswa/index.php'), 'Pengaturan Akun' => null];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[980px]">

    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-blue">Akun Siswa</span>
          <?php if ($akunInfo): ?>
            <span class="badge badge-<?= (int) $akunInfo['is_active'] === 1 ? 'green' : 'red' ?>">
              <?= (int) $akunInfo['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
            </span>
          <?php endif; ?>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Pengaturan Akun</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-2xl">
          Kelola keamanan akun dan periksa kredensial yang terdaftar. Data profil lengkap ada pada menu Data Siswa.
        </p>
      </div>
      <a href="<?= url('app/siswa/index.php') ?>" class="btn btn-outline shrink-0"><?= icon('arrow_back') ?> Dashboard</a>
    </div>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <div class="mt-6 grid gap-5 lg:grid-cols-5">

      <!-- Informasi akun -->
      <section class="card p-5 sm:p-6 lg:col-span-2">
        <h2 class="font-bold text-ink-900">Informasi Akun</h2>
        <dl class="mt-4 space-y-3 text-[13px]">
          <div>
            <dt class="text-ink-400">Username</dt>
            <dd class="font-mono font-semibold text-ink-900 break-all"><?= e($me['username']) ?></dd>
          </div>
          <div>
            <dt class="text-ink-400">Peran</dt>
            <dd class="font-semibold text-ink-900">Siswa / Calon Peserta Didik</dd>
          </div>
          <?php if ($siswa): ?>
            <div>
              <dt class="text-ink-400">No. pendaftaran</dt>
              <dd class="font-mono font-semibold text-ink-900"><?= e($siswa['no_pendaftaran']) ?></dd>
            </div>
            <div>
              <dt class="text-ink-400">Nama terdaftar</dt>
              <dd class="font-semibold text-ink-900"><?= e($siswa['nama_siswa']) ?></dd>
            </div>
            <div>
              <dt class="text-ink-400">WhatsApp orang tua / wali</dt>
              <dd class="font-semibold text-ink-900"><?= e($siswa['no_whatsapp'] ?: 'Belum diisi') ?></dd>
            </div>
            <div>
              <dt class="text-ink-400">Status akun</dt>
              <dd class="mt-0.5">
                <?php $akun = $labelAkun[$siswa['status_akun']] ?? [$siswa['status_akun'], 'gray']; ?>
                <?= badge($akun[0], $akun[1]) ?>
              </dd>
            </div>
          <?php endif; ?>
          <?php if (!empty($akunInfo['created_at'])): ?>
            <div>
              <dt class="text-ink-400">Akun dibuat</dt>
              <dd class="text-ink-700"><?= e(bulan($akunInfo['created_at'])) ?></dd>
            </div>
          <?php endif; ?>
        </dl>

        <div class="mt-5 rounded-xl bg-canvas border border-line px-4 py-3 text-[12px] text-ink-500">
          Nama, NISN, alamat, dan nomor WhatsApp tidak diubah dari halaman ini. Gunakan
          <a class="font-semibold text-primary hover:underline" href="<?= url('app/siswa/data-siswa.php') ?>">Data Siswa</a>
          agar tercatat pada formulir pendaftaran.
        </div>
      </section>

      <!-- Keamanan -->
      <section class="card p-5 sm:p-6 lg:col-span-3">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <h2 class="font-bold text-ink-900">Ganti Password</h2>
            <p class="mt-1 text-[13px] text-ink-500">Minimal 10 karakter, tidak sama dengan username atau password lama.</p>
          </div>
          <span class="icon-box bg-primary-light text-primary"><?= icon('lock', 'text-[19px]') ?></span>
        </div>

        <form method="post" action="<?= url('app/siswa/akun.php') ?>" class="mt-5 grid gap-4" autocomplete="off">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="ganti_password">
          <div>
            <label class="label" for="password_lama">Password Lama <span class="text-red-600">*</span></label>
            <input id="password_lama" name="password_lama" type="password" class="input" required autocomplete="current-password">
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label class="label" for="password_baru">Password Baru <span class="text-red-600">*</span></label>
              <input id="password_baru" name="password_baru" type="password" class="input" required minlength="10" autocomplete="new-password">
            </div>
            <div>
              <label class="label" for="password_ulangi">Ulangi Password Baru <span class="text-red-600">*</span></label>
              <input id="password_ulangi" name="password_ulangi" type="password" class="input" required minlength="10" autocomplete="new-password">
            </div>
          </div>
          <div class="flex items-center justify-between gap-3 flex-wrap">
            <p class="text-[12px] text-ink-400">Setelah berhasil, Anda tetap masuk pada sesi ini.</p>
            <button type="submit" class="btn btn-primary shadow-drop"><?= icon('save') ?> Simpan Password</button>
          </div>
        </form>

        <div class="mt-5 border-t border-line-soft pt-4">
          <h3 class="text-sm font-semibold text-ink-900">Sesi &amp; Keamanan</h3>
          <p class="mt-1 text-[13px] text-ink-500">
            Jangan bagikan password kepada siapa pun, termasuk yang mengaku panitia. Panitia tidak pernah meminta password akun.
          </p>
          <div class="mt-3 flex flex-wrap items-center gap-3">
            <form method="post" action="<?= url('app/auth/logout.php') ?>"
                  onsubmit="return confirm('Keluar dari portal siswa?');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-danger btn-sm"><?= icon('logout', 'text-[16px]') ?> Keluar dari Akun</button>
            </form>
            <a href="<?= url('app/spmb/index.php') ?>" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
              <?= icon('help', 'text-[16px]') ?> Bantuan SPMB
            </a>
          </div>
        </div>
      </section>
    </div>

    <?php if ($siswa): ?>
      <section class="mt-5 card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900 text-sm">Akses Cepat</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <a href="<?= url('app/siswa/data-siswa.php') ?>" class="rounded-xl border border-line p-3.5 hover:bg-canvas transition">
            <span class="ms text-[20px] text-primary">badge</span>
            <p class="mt-1 text-[13px] font-semibold text-ink-900">Data Siswa</p>
          </a>
          <a href="<?= url('app/siswa/keuangan.php') ?>" class="rounded-xl border border-line p-3.5 hover:bg-canvas transition">
            <span class="ms text-[20px] text-primary">account_balance_wallet</span>
            <p class="mt-1 text-[13px] font-semibold text-ink-900">Keuangan</p>
          </a>
          <a href="<?= url('app/siswa/ppdb.php') ?>" class="rounded-xl border border-line p-3.5 hover:bg-canvas transition">
            <span class="ms text-[20px] text-primary">how_to_reg</span>
            <p class="mt-1 text-[13px] font-semibold text-ink-900">PPDB &amp; Seleksi</p>
          </a>
          <a href="<?= url('app/siswa/notifikasi.php') ?>" class="rounded-xl border border-line p-3.5 hover:bg-canvas transition">
            <span class="ms text-[20px] text-primary">notifications</span>
            <p class="mt-1 text-[13px] font-semibold text-ink-900">Notifikasi</p>
          </a>
        </div>
      </section>
    <?php endif; ?>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script>window.APP_FLASH = <?= json_encode($flash, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
