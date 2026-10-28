<?php
/* ==================================================================
 * PETUGAS & HAK AKSES — kelola akun staf (operator, bendahara, kepsek).
 *
 * Operator dapat: mengubah nama/jabatan/username petugas, menyetel
 * password sementara (wajib diganti saat login pertama), menambah petugas
 * baru, serta menonaktifkan akun. Akun siswa TIDAK dikelola di sini
 * (lihat menu Generate Akun).
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';
require_role(['operator', 'admin']);

$meId = (string) (current_user()['id'] ?? '');

/* ==================================================================
 * Proses POST — setiap aksi berakhir dengan flash + redirect (PRG).
 * ================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        flash('error', 'Formulir kedaluwarsa. Silakan muat ulang halaman lalu coba lagi.');
        header('Location: ' . url('app/operator/petugas.php'));
        exit;
    }

    $aksi  = (string) ($_POST['aksi'] ?? '');
    $id    = (string) ($_POST['id'] ?? '');
    $hasil = ['ok' => false, 'pesan' => 'Aksi tidak dikenal.'];

    /* Pengaman akun sendiri: operator tidak boleh mengunci dirinya sendiri
     * lewat username baru, password sementara, atau penonaktifan akun. */
    $salahDiri = $id !== '' && $id === $meId;

    if ($aksi === 'tambah') {
        $password = (string) ($_POST['password'] ?? '');
        $ulang    = (string) ($_POST['password_ulangi'] ?? '');
        if ($password !== $ulang) {
            $hasil = ['ok' => false, 'pesan' => 'Konfirmasi password tidak sama.'];
        } else {
            $hasil = petugas_buat([
                'nama'     => $_POST['nama'] ?? '',
                'jabatan'  => $_POST['jabatan'] ?? '',
                'username' => $_POST['username'] ?? '',
                'role'     => $_POST['role'] ?? '',
                'password' => $password,
            ]);
        }
    } elseif ($aksi === 'ubah') {
        $target = petugas_detail($id);
        if (!$target) {
            $hasil = ['ok' => false, 'pesan' => 'Akun petugas tidak ditemukan.'];
        } elseif ($salahDiri && trim((string) ($_POST['username'] ?? '')) !== $target['username']) {
            $hasil = ['ok' => false, 'pesan' => 'Username akun yang sedang Anda pakai tidak bisa diubah dari halaman ini. '
                . 'Login memakai akun lain untuk mengubahnya.'];
        } else {
            $hasil = petugas_ubah_identitas($id, $_POST['nama'] ?? '', $_POST['jabatan'] ?? '', $_POST['username'] ?? null);
            if ($hasil['ok'] && $salahDiri) {
                $hasil['pesan'] .= ' Nama di menu berubah setelah Anda login ulang.';
            }
        }
    } elseif ($aksi === 'password') {
        if ($salahDiri) {
            $hasil = ['ok' => false, 'pesan' => 'Untuk akun sendiri, gunakan halaman Ganti Password (isi password lama terlebih dahulu).'];
        } else {
            $password = (string) ($_POST['password_baru'] ?? '');
            $ulang    = (string) ($_POST['password_ulangi'] ?? '');
            $hasil    = $password !== $ulang
                ? ['ok' => false, 'pesan' => 'Konfirmasi password tidak sama.']
                : petugas_set_password($id, $password);
        }
    } elseif ($aksi === 'aktif') {
        $nilai = empty($_POST['nonaktif']) ? 1 : 0;
        if ($nilai === 0 && $salahDiri) {
            $hasil = ['ok' => false, 'pesan' => 'Anda tidak bisa menonaktifkan akun yang sedang dipakai login.'];
        } else {
            $hasil = petugas_set_aktif($id, $nilai);
        }
    }

    flash($hasil['ok'] ? 'sukses' : 'error', $hasil['pesan']);
    header('Location: ' . url('app/operator/petugas.php'));
    exit;
}

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title     = 'Petugas & Hak Akses';
$desc      = 'Kelola akun petugas SPMB: nama, jabatan, username, password, dan status aktif.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'petugas';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Pengaturan Sistem' => null, 'Petugas & Hak Akses' => null];
require __DIR__ . '/../../includes/partials/topbar.php';

$flash    = flash();
$petugas  = petugas_semua();
$roleKatalog = petugas_role_katalog();
$toneRole = ['operator' => 'blue', 'bendahara' => 'green', 'kepsek' => 'amber'];
$gantiDiri = url('app/staf/ganti-password.php');
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <h1 class="text-2xl font-bold tracking-tight text-ink-900">Petugas &amp; Hak Akses</h1>
    <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
      Daftar akun pihak sekolah yang bisa masuk ke <code class="font-mono text-[12px]">admin/</code>. Ubah nama yang tampil
      di menu, setel ulang username/password petugas yang lupa, atau tambahkan petugas baru.
      Akun yang dinonaktifkan tidak bisa login tetapi datanya tetap tersimpan.
    </p>

    <?php if ($flash): ?>
      <div class="mt-5 rounded-xl border <?= $flash['tipe'] === 'sukses' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' ?> px-4 py-3 text-sm">
        <?= e($flash['pesan']) ?>
      </div>
    <?php endif; ?>

    <div class="mt-5 rounded-xl border border-line bg-canvas px-4 py-3 text-[13px] text-ink-600 space-y-1.5">
      <p class="flex gap-2"><?= icon('info', 'text-[17px] shrink-0') ?>
        <span>Password sementara yang disetel di sini <strong>wajib diganti</strong> oleh yang bersangkutan pada login pertama
        (halaman Ganti Password). Password tidak pernah ditampilkan kembali di layar ini.</span></p>
      <p class="flex gap-2"><?= icon('shield_person', 'text-[17px] shrink-0') ?>
        <span>Akun yang sedang Anda pakai sendiri hanya bisa diubah nama dan jabatannya — username, password, dan status aktif
        akun sendiri dikunci dari halaman ini. Minimal satu akun <em>Operator</em> harus tetap aktif.</span></p>
    </div>

    <?php if (!$petugas): ?>
      <div class="mt-6 card p-6 text-sm text-ink-500">
        <?= icon('report_problem', 'text-[20px]') ?> Daftar petugas kosong — halaman ini memerlukan koneksi database
        (periksa <strong>Kesiapan Rilis</strong>).
      </div>
    <?php endif; ?>

    <!-- Tabel akun petugas -->
    <?php if ($petugas): ?>
    <section class="mt-6 card overflow-hidden">
      <div class="px-5 pt-5">
        <h2 class="font-bold text-ink-900">Petugas terdaftar <span class="text-ink-400 font-normal">(<?= count($petugas) ?>)</span></h2>
      </div>
      <div class="mt-3 overflow-x-auto scroll-thin">
        <table class="w-full min-w-[900px] border-collapse">
          <thead class="thead">
            <tr>
              <th class="th">Petugas</th>
              <th class="th">Username</th>
              <th class="th">Role</th>
              <th class="th">Status</th>
              <th class="th">Password</th>
              <th class="th text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line-soft">
            <?php foreach ($petugas as $a): ?>
              <?php $diri = (string) $a['id'] === $meId; ?>
              <tr class="tr-hover <?= $a['is_active'] ? '' : 'opacity-60' ?>">
                <td class="td">
                  <p class="font-semibold text-ink-900"><?= e($a['nama_lengkap'] ?: '—') ?><?= $diri ? ' ' . badge('Anda', 'blue') : '' ?></p>
                  <p class="text-[12px] text-ink-500"><?= e($a['jabatan'] ?: '—') ?></p>
                </td>
                <td class="td font-mono text-[13px]"><?= e($a['username']) ?></td>
                <td class="td"><?= badge($roleKatalog[$a['role']] ?? $a['role'], $toneRole[$a['role']] ?? 'gray') ?></td>
                <td class="td"><?= $a['is_active'] ? badge('Aktif', 'green') : badge('Nonaktif', 'gray') ?></td>
                <td class="td">
                  <?php if ((int) $a['terkunci'] === 1): ?>
                    <?= badge('Belum disetel', 'red') ?>
                  <?php elseif ((int) $a['wajib_ganti_password'] === 1): ?>
                    <?= badge('Wajib diganti', 'amber') ?>
                  <?php else: ?>
                    <?= badge('Tersetel', 'gray') ?>
                  <?php endif; ?>
                </td>
                <td class="td text-right">
                  <div class="inline-flex items-center gap-2 justify-end">
                    <button type="button" class="btn btn-sm btn-outline" data-modal-open="modal-ubah"
                            data-md-id="<?= e($a['id']) ?>"
                            data-md-nama="<?= e($a['nama_lengkap']) ?>"
                            data-md-jabatan="<?= e($a['jabatan']) ?>"
                            data-md-username="<?= e($a['username']) ?>"
                            data-md-diri="<?= $diri ? 1 : 0 ?>">
                      <?= icon('edit', 'text-[15px]') ?> Ubah
                    </button>
                    <?php if ($diri): ?>
                      <a href="<?= e($gantiDiri) ?>" class="btn btn-sm btn-outline"><?= icon('password', 'text-[15px]') ?> Ganti password saya</a>
                    <?php else: ?>
                      <button type="button" class="btn btn-sm btn-outline" data-modal-open="modal-sandi"
                              data-md-id="<?= e($a['id']) ?>"
                              data-md-username="<?= e($a['username']) ?>"
                              data-md-nama="<?= e($a['nama_lengkap']) ?>"><?= icon('key', 'text-[15px]') ?> Reset password</button>
                    <?php endif; ?>
                    <?php if (!$diri || !$a['is_active']): ?>
                    <form method="post" action="<?= url('app/operator/petugas.php') ?>" class="inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="aksi" value="aktif">
                      <input type="hidden" name="id" value="<?= e($a['id']) ?>">
                      <?php if ($a['is_active']): ?>
                        <input type="hidden" name="nonaktif" value="1">
                        <button type="submit" class="btn btn-sm btn-danger"
                                onclick="return confirm('Nonaktifkan akses login <?= e($a['username']) ?>?')">Nonaktifkan</button>
                      <?php else: ?>
                        <button type="submit" class="btn btn-sm btn-green">Aktifkan</button>
                      <?php endif; ?>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <!-- Form tambah petugas -->
    <section class="mt-6 card p-5 sm:p-6">
      <h2 class="font-bold text-ink-900">Tambah Petugas</h2>
      <p class="mt-1 text-[13px] text-ink-500">
        Satu orang boleh punya lebih dari satu akun per role, misalnya bendahara cadangan.
        Role menentukan halaman apa yang bisa dibuka setelah login.
      </p>
      <form method="post" action="<?= url('app/operator/petugas.php') ?>" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <div>
          <label class="label" for="nama">Nama Lengkap <span class="text-red-600">*</span></label>
          <input id="nama" name="nama" type="text" class="input" placeholder="Hj. Siti Rohmah, S.E." maxlength="100" required>
        </div>
        <div>
          <label class="label" for="jabatan">Jabatan (opsional)</label>
          <input id="jabatan" name="jabatan" type="text" class="input" placeholder="Bendahara Sekolah" maxlength="100">
          <p class="hint">Kosong = mengikuti label role.</p>
        </div>
        <div>
          <label class="label" for="role">Role / Hak Akses <span class="text-red-600">*</span></label>
          <select id="role" name="role" class="input" required>
            <?php foreach ($roleKatalog as $kode => $label): ?>
              <option value="<?= e($kode) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="label" for="username">Username <span class="text-red-600">*</span></label>
          <input id="username" name="username" type="text" class="input font-mono" placeholder="bendahara2"
                 maxlength="30" pattern="[a-z0-9._-]{4,30}" required>
          <p class="hint">4-30 karakter: huruf kecil, angka, titik, garis bawah, strip.</p>
        </div>
        <div>
          <label class="label" for="password">Password Sementara <span class="text-red-600">*</span></label>
          <input id="password" name="password" type="password" class="input" minlength="10" maxlength="72" autocomplete="new-password" required>
        </div>
        <div>
          <label class="label" for="password_ulangi">Ulangi Password <span class="text-red-600">*</span></label>
          <input id="password_ulangi" name="password_ulangi" type="password" class="input" minlength="10" maxlength="72" autocomplete="new-password" required>
        </div>
        <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap items-center justify-between gap-3">
          <p class="text-[12px] text-ink-500 flex items-center gap-1.5"><?= icon('lock', 'text-[15px]') ?>
            Petugas dipaksa mengganti password ini pada login pertama.</p>
          <button type="submit" class="btn btn-primary shadow-drop"><?= icon('person_add') ?> Buat Akun Petugas</button>
        </div>
      </form>
    </section>

  </main>
</div>

<!-- Modal ubah identitas -->
<div id="modal-ubah" class="modal-root hidden fixed inset-0 z-[60]" role="dialog" aria-modal="true">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
    <form method="post" action="<?= url('app/operator/petugas.php') ?>"
          class="animate-pop w-full max-w-lg bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="ubah">
      <input type="hidden" name="id" data-md-fill="id">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
        <div>
          <h3 class="font-bold text-ink-900 text-lg leading-tight">Ubah Petugas</h3>
          <p class="text-[13px] text-ink-500">Nama ini yang tampil di header, menu, dan catatan aktivitas.</p>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5 space-y-4">
        <div>
          <label class="label" for="md-nama">Nama Lengkap</label>
          <input id="md-nama" name="nama" type="text" class="input" data-md-fill="nama" maxlength="100" required>
        </div>
        <div>
          <label class="label" for="md-jabatan">Jabatan</label>
          <input id="md-jabatan" name="jabatan" type="text" class="input" data-md-fill="jabatan" maxlength="100">
        </div>
        <div>
          <label class="label" for="md-username">Username</label>
          <input id="md-username" name="username" type="text" class="input font-mono" data-md-fill="username"
                 maxlength="30" pattern="[a-z0-9._-]{4,30}" required>
          <p class="hint" data-username-cat>Ganti username bila petugas lupa akunnya — gunakan huruf kecil.</p>
          <p class="hint text-amber-700 hidden" data-username-kunci>
            Username akun Anda sendiri dikunci di sini — login memakai akun lain untuk mengubahnya.</p>
        </div>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary"><?= icon('save') ?> Simpan</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal reset password -->
<div id="modal-sandi" class="modal-root hidden fixed inset-0 z-[60]" role="dialog" aria-modal="true">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-center justify-center p-4 sm:p-6">
    <form method="post" action="<?= url('app/operator/petugas.php') ?>"
          class="animate-pop w-full max-w-lg bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="aksi" value="password">
      <input type="hidden" name="id" data-md-fill="id">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
        <div>
          <h3 class="font-bold text-ink-900 text-lg leading-tight">Reset Password</h3>
          <p class="text-[13px] text-ink-500">Untuk <strong data-md-text="nama">petugas</strong>
            (<span class="font-mono" data-md-text="username"></span>).</p>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5 space-y-4">
        <div>
          <label class="label" for="pw-baru">Password Sementara</label>
          <input id="pw-baru" name="password_baru" type="password" class="input" minlength="10" maxlength="72" autocomplete="new-password" required>
          <p class="hint">Minimal 10 karakter dan tidak boleh sama dengan username.</p>
        </div>
        <div>
          <label class="label" for="pw-ulangi">Ulangi Password</label>
          <input id="pw-ulangi" name="password_ulangi" type="password" class="input" minlength="10" maxlength="72" autocomplete="new-password" required>
        </div>
        <p class="text-[12px] text-ink-500 flex gap-2"><?= icon('info', 'text-[16px] shrink-0') ?>
          Sampaikan password ini secara langsung (tidak lewat WhatsApp). Petugas wajib menggantinya saat login pertama.</p>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary"><?= icon('key') ?> Setel Password</button>
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
/* app.js sudah mengisi [data-md-fill] dari data-md-* tombol yang diklik.
 * Di sini hanya perlu label teks modal & penguncian username akun sendiri. */
document.querySelectorAll('button[data-modal-open]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var modal = document.getElementById(btn.getAttribute('data-modal-open'));
    if (!modal) return;
    modal.querySelectorAll('[data-md-text]').forEach(function (el) {
      el.textContent = btn.getAttribute('data-md-' + el.getAttribute('data-md-text')) || '';
    });
    if (modal.id !== 'modal-ubah') return;
    var sendiri = btn.getAttribute('data-md-diri') === '1';
    var u = modal.querySelector('[data-md-fill="username"]');
    u.readOnly = sendiri;
    u.classList.toggle('bg-canvas', sendiri);
    modal.querySelector('[data-username-cat]').classList.toggle('hidden', sendiri);
    modal.querySelector('[data-username-kunci]').classList.toggle('hidden', !sendiri);
  });
});
</script>
</body>
</html>
