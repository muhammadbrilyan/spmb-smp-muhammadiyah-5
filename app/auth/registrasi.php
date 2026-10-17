<?php
/* ==================================================================
 * REGISTRASI AWAL PUBLIK (untuk calon siswa/orang tua)
 * Halaman ini SISA form registrasi → dapat nomor pendaftaran otomatis.
 *
 * YANG SERING DIUBAH:
 *   - Judul & deskripsi form    → cari "Registrasi Awal SPMB" di bagian HTML
 *   - Daftar jalur pendaftaran  → dari CMS Pengaturan → Jalur Pendaftaran Aktif
 *   - Teks bawah form           → catatan about verifikasi & link masuk portal
 *   - Logo & link kembali       → tombol "Kembali ke Beranda" di atas form
 * ================================================================== */
require_once __DIR__ . '/../../includes/config.php';

/* Sudah masuk? → tidak perlu registrasi publik. */
if (is_logged_in()) {
    header('Location: ' . role_home(user_role()));
    exit;
}

$galat  = [];
$hasil  = null;          // hasil registrasi sukses
$f = ['nama' => '', 'wa' => '', 'jalur' => '', 'asal' => ''];

$jalurValid = jalur_aktif();

/* Teks S&K dari CMS (bisa diubah operator di Pengaturan). Bila kosong,
 * pakai bawaan lengkap 8 pasal. */
$syaratTeks = $PENGATURAN['syarat_ketentuan'] ?? null;
if (!$syaratTeks) {
    $syaratTeks = "SYARAT & KETENTUAN PENDAFTARAN\nSMP MUHAMMADIYAH 5 KOTA TANGERANG\n\n"
        . "1. Pendaftar adalah siswa kelas 6 SD/MI yang akan melanjutkan ke SMP kelas 7 pada tahun ajaran berikutnya.\n\n"
        . "2. Data yang diisi pada formulir pendaftaran harus benar sesuai dokumen resmi (akta kelahiran, Kartu Keluarga, Ijazah SD/MI).\n\n"
        . "3. Biaya formulir pendaftaran sebesar yang tertera di halaman ini tidak dapat dikembalikan dalam keadaan apa pun.\n\n"
        . "4. Pendaftar wajib mengunggah seluruh berkas persyaratan sesuai daftar yang ditentukan panitia.\n\n"
        . "5. Panitia berhak menolak pendaftar yang memberikan data tidak benar atau berkas tidak lengkap.\n\n"
        . "6. Jadwal ujian, pengumuman hasil, dan pembagian kelas akan diinformasikan melalui website resmi dan/atau WhatsApp orang tua.\n\n"
        . "7. Keputusan panitia bersifat final.\n\n"
        . "8. PRIVASI & RETENSI DATA. Data pribadi siswa dan orang tua hanya digunakan untuk keperluan seleksi dan administrasi pendaftaran. "
        . "Berkas pribadi disimpan terbatas dan dapat dihapus melalui kebijakan retensi yang ditetapkan sekolah. "
        . "Sekolah tidak membagikan data kepada pihak ketiga tanpa dasar hukum atau persetujuan orang tua/wali.";
}
$syaratVersi = (int) ($PENGATURAN['syarat_versi'] ?? 1);
$syaratHtml  = syarat_ke_html($syaratTeks);

/* Idempotency key — satu per render form; retry / double-submit memakai key yang sama. */
$regToken = idem_key_valid($_SESSION['reg_token'] ?? '');
if ($regToken === null) {
    $regToken = idem_key_baru();
    $_SESSION['reg_token'] = $regToken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_cek($_POST['_csrf'] ?? '')) {
        $galat[] = 'Formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.';
    } else {
        /* Honeypot — field tersembunyi, bot yang mengisi akan dianggap spam. */
        if (!empty($_POST['website'])) {
            $galat[] = 'Permintaan ditolak.';
        }

        /* Rate limit: maks 5 registrasi per jam per IP. */
        if (!$galat && !rate_limit_cek('registrasi', 5, 60)) {
            $galat[] = 'Terlalu banyak percobaan. Silakan coba lagi dalam beberapa menit.';
        }

        $f['nama']  = trim((string) ($_POST['nama'] ?? ''));
        $f['wa']    = trim((string) ($_POST['wa'] ?? ''));
        $f['jalur'] = $_POST['jalur'] ?? '';
        $f['asal']  = trim((string) ($_POST['asal'] ?? ''));
        $setujuSk   = !empty($_POST['setuju_sk']);

        /* Token dari form (hidden) — bila kosong pakai session. */
        $postToken = idem_key_valid($_POST['reg_token'] ?? '');
        if ($postToken !== null) {
            $regToken = $postToken;
            $_SESSION['reg_token'] = $regToken;
        }

        if (mb_strlen($f['nama']) < 3) {
            $galat[] = 'Nama lengkap siswa minimal 3 karakter.';
        }
        if (!preg_match('/^\+?[0-9\s-]{9,18}$/', $f['wa'])) {
            $galat[] = 'Nomor WhatsApp orang tua tidak valid (9–18 digit).';
        }
        if (!isset($jalurValid[$f['jalur']])) {
            /* Pakai jalur pertama yang aktif sebagai default bila tidak valid. */
            $f['jalur'] = array_key_first($jalurValid) ?: 'Reguler';
        }
        if ($f['asal'] === '') {
            $galat[] = 'Asal sekolah (SD/MI) wajib diisi.';
        }
        if (!$setujuSk) {
            $galat[] = 'Anda harus menyetujui Syarat & Ketentuan.';
        }

        /* Fase 11 A4: batas 3 registrasi per nomor WhatsApp per 24 jam
         * (kunci lintas IP — hash SHA-256 nomor ternormalisasi). */
        $kunciWa = null;
        if (!$galat) {
            $waNorm = wa_normalize($f['wa']);
            if ($waNorm !== '') {
                $kunciWa = hash('sha256', $waNorm);
                if (!rate_limit_cek('registrasi_wa', 3, 1440, $kunciWa)) {
                    $galat[] = 'Nomor WhatsApp ini sudah mencapai batas 3 pendaftaran hari ini. Gunakan nomor lain atau hubungi panitia.';
                }
            }
        }

        if (!$galat) {
            rate_limit_catat('registrasi');
            $r = registrasi_baru($f['nama'], $f['wa'], $f['jalur'], $f['asal'], $regToken, $setujuSk);
            if ($r['ok']) {
                $hasil = $r;
                /* Catat batas per-WA hanya saat registrasi BARU tersimpan
                 * (retry idempoten dengan key sama tidak dihitung). */
                if ($kunciWa !== null && empty($r['reused'])) {
                    rate_limit_catat('registrasi_wa', $kunciWa);
                }
                /* Token sudah dipakai — bersihkan agar render berikutnya (bila sukses) punya key baru. */
                unset($_SESSION['reg_token']);
                /* Kirim instruksi via WA bila gateway aktif (gagal tetap tidak membatalkan registrasi). */
                if (empty($r['reused'])) {
                    $hasil['wa_terkirim'] = wa_kirim($r['wa'], wa_pesan_instruksi($r))['ok'];
                } else {
                    $hasil['wa_terkirim'] = false;
                }
            } else {
                $galat[] = $r['pesan'];
            }
        }
    }
}

$title = $hasil ? 'Registrasi Berhasil' : 'Registrasi Awal SPMB';
$desc  = 'Formulir registrasi awal penerimaan murid baru ' . APP_SHORT . ' — nomor pendaftaran otomatis.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* Fase 11 A4: metode yang ditampilkan di halaman sukses = yang AKTIF saja
 * (sama dengan pesan WhatsApp instruksi). */
$transferAktif = !empty($PENGATURAN['bayar_transfer_aktif']);
$qrisAktif     = !empty($PENGATURAN['bayar_qris_aktif']);
$gatewayAktif  = gateway_tersedia();

require_once __DIR__ . '/../../includes/partials/head.php';
?>

<div class="min-h-screen">
  <!-- Header minimal -->
  <header class="border-b border-line bg-white">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between gap-3">
      <a href="<?= url('app/spmb/index.php') ?>" class="flex items-center gap-3 min-w-0">
        <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo" class="w-10 h-10 shrink-0">
        <span class="min-w-0 leading-tight">
          <span class="block font-bold text-ink-900 text-[15px] truncate"><?= e(APP_SHORT) ?></span>
          <span class="block text-[11px] text-ink-500">SPMB <?= e($PENGATURAN['tahun_ajaran']) ?> · <?= e($PENGATURAN['gelombang']) ?></span>
        </span>
      </a>
      <a href="<?= url('app/auth/login.php') ?>" class="btn btn-outline btn-sm">Masuk Portal</a>
    </div>
  </header>

  <main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 lg:py-12">

    <?php if ($hasil): ?>
      <!-- ==================== SUKSES ==================== -->
      <div class="card p-6 sm:p-8 shadow-drop">
        <div class="flex items-center gap-3">
          <span class="icon-box bg-[#0E9F6E] text-white w-12 h-12"><?= icon('task_alt', 'text-[24px]') ?></span>
          <div>
            <h1 class="text-2xl font-bold text-ink-900">Registrasi Berhasil!</h1>
            <p class="text-sm text-ink-500">Simpan nomor pendaftaran di bawah ini.</p>
          </div>
        </div>

        <div class="mt-6 rounded-2xl bg-primary-light border border-primary/20 p-5 text-center">
          <p class="text-[13px] text-ink-500">Nomor Pendaftaran Anda</p>
          <p class="mt-1 text-3xl font-extrabold tracking-wide text-primary font-mono"><?= e($hasil['no']) ?></p>
          <p class="mt-2 text-[13px] text-ink-500"><?= e($hasil['nama']) ?> · Jalur <?= e($hasil['jalur']) ?></p>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl border border-line p-4">
            <p class="font-semibold text-sm text-ink-900">1. Bayar Biaya Formulir</p>
            <p class="mt-1.5 text-[13px] text-ink-500 leading-relaxed">
              Nominal: <strong class="text-ink-900"><?= rupiah($PENGATURAN['biaya_formulir']) ?></strong>
              <?php if ($transferAktif): ?>
                <br><?= e($PENGATURAN['nama_bank']) ?>
                <br>No. Rek: <span class="font-mono font-semibold"><?= e($PENGATURAN['no_rekening']) ?></span>
                <br>a.n. <?= e($PENGATURAN['atas_nama_rekening']) ?>
              <?php endif; ?>
              <?php if ($qrisAktif): ?>
                <br>Atau scan QRIS pada halaman pembayaran.
              <?php endif; ?>
              <?php if ($gatewayAktif): ?>
                <br>Atau bayar online (virtual account, e-wallet, kartu) pada halaman pembayaran.
              <?php endif; ?>
            </p>
          </div>
          <div class="rounded-2xl border border-line p-4">
            <p class="font-semibold text-sm text-ink-900">2. Setelah Pembayaran Terverifikasi</p>
            <p class="mt-1.5 text-[13px] text-ink-500 leading-relaxed">
              Akun dan password dikirim lewat WhatsApp ke nomor yang didaftarkan.
              Selanjutnya, masuk portal siswa untuk melengkapi biodata &amp; berkas persyaratan.
            </p>
            <?php if (!empty($hasil['wa_terkirim'])): ?>
              <p class="mt-2 text-[12px] text-[#065F46] font-semibold">✓ Instruksi juga dikirim via WhatsApp.</p>
            <?php else: ?>
              <p class="mt-2 text-[12px] text-[#92400E]">Instruksi WA sedang tidak aktif — catat informasi di halaman ini.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3 justify-end">
          <?php if (!empty($hasil['pay_token'])): ?>
            <a href="<?= url('app/spmb/bayar.php?t=' . urlencode($hasil['pay_token'])) ?>" class="btn btn-primary shadow-drop">
              Bayar Sekarang <?= icon('arrow_forward') ?>
            </a>
          <?php endif; ?>
          <a href="<?= url('app/spmb/cek-status.php') ?>" class="btn btn-outline">Cek Status</a>
          <a href="<?= url('app/spmb/index.php') ?>" class="btn btn-outline">Kembali ke Beranda</a>
        </div>
      </div>

    <?php else: ?>
      <!-- ==================== FORM ==================== -->
      <a href="<?= url('app/spmb/index.php') ?>" class="inline-flex items-center gap-1.5 text-[13px] text-ink-500 hover:text-primary font-medium transition mb-4">
        <?= icon('arrow_back', 'text-[17px]') ?> Kembali ke Beranda
      </a>
      <div class="card p-6 sm:p-8 shadow-drop">
        <!-- JUDUL FORM: ganti teks judul & deskripsi di bawah ini -->
        <h1 class="text-2xl font-bold text-ink-900">Registrasi Awal SPMB</h1>
        <p class="mt-1.5 text-sm text-ink-500">
          Isi data awal untuk mendapatkan nomor pendaftaran otomatis.
          Biaya formulir <strong class="text-ink-900"><?= rupiah($PENGATURAN['biaya_formulir']) ?></strong>
          (diambil dari pengaturan resmi sekolah).
        </p>

        <?php if ($galat): ?>
          <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
            <p class="font-semibold">Periksa isian berikut:</p>
            <ul class="mt-2 space-y-1 list-disc list-inside">
              <?php foreach ($galat as $g): ?><li><?= e($g) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" action="<?= url('app/auth/registrasi.php') ?>" class="mt-6 space-y-4">
          <?= csrf_field() ?>
          <input type="hidden" name="reg_token" value="<?= e($regToken) ?>">
          <!-- Honeypot: jangan diisi, jangan diubah — bot biasanya mengisi semua field -->
          <div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
            <label for="website">Website</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
          </div>
          <div>
            <label class="label" for="nama">Nama Lengkap Siswa <span class="text-red-600">*</span></label>
            <input id="nama" name="nama" class="input" placeholder="Sesuai akta kelahiran" value="<?= e($f['nama']) ?>" required>
          </div>
          <div class="grid sm:grid-cols-2 gap-4">
            <div>
              <label class="label" for="wa">WhatsApp Orang Tua <span class="text-red-600">*</span></label>
              <input id="wa" name="wa" type="tel" class="input font-mono" placeholder="081234567890" value="<?= e($f['wa']) ?>" required>
            </div>
            <div>
              <label class="label" for="jalur">Pilih Jalur <span class="text-red-600">*</span></label>
              <select id="jalur" name="jalur" class="select w-full">
                <?php foreach ($jalurValid as $kunci => $label): ?>
                  <option value="<?= e($kunci) ?>" <?= $f['jalur'] === $kunci ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div>
            <label class="label" for="asal">Asal Sekolah (SD/MI) <span class="text-red-600">*</span></label>
            <input id="asal" name="asal" class="input" placeholder="Contoh: SD Muhammadiyah 3 Tangerang" value="<?= e($f['asal']) ?>" required>
          </div>

          <button type="submit" class="btn btn-primary btn-lg btn-block shadow-drop">
            Daftar &amp; Dapatkan Nomor Pendaftaran <?= icon('arrow_forward') ?>
          </button>

          <p class="flex items-start gap-2 text-xs text-ink-400 leading-relaxed">
            <?= icon('lock', 'text-[15px] mt-0.5') ?>
            Maksimal 1 pendaftaran per nomor WhatsApp + nama. Form biodata terkunci sampai verifikasi panitia.
            Sudah daftar &amp; punya akun? <a class="underline text-primary font-semibold" href="<?= url('app/auth/login.php') ?>">Masuk portal</a>.
          </p>
        </form>
      </div>
    <?php endif; ?>

  </main>
</div>

<!-- ==================== MODAL SYARAT & KETENTUAN ==================== -->
<style>
  /* Gaya khusus modal S&K — warna lembut, nyaman dibaca */
  .sk-hd { background: linear-gradient(135deg, #0056B3 0%, #00448f 100%); }
  .sk-hd .sk-ic { background: rgba(255,255,255,.18); color: #fff; }
  .sk-doc { padding-bottom: 4px; }
  .sk-judul { font-size: 15px; font-weight: 800; color: #0F172A; text-align: center; letter-spacing: .02em; line-height: 1.4; }
  .sk-sub { font-size: 12.5px; font-weight: 600; color: #64748B; text-align: center; margin-top: 3px; }
  .sk-ol { margin-top: 16px; display: flex; flex-direction: column; gap: 10px; }
  .sk-ol > li { display: flex; gap: 10px; align-items: flex-start; background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; padding: 10px 12px; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
  .sk-badge { flex: none; width: 24px; height: 24px; border-radius: 8px; background: #cce0f5; color: #00448f; font-size: 12px; font-weight: 700; display: grid; place-items: center; margin-top: 1px; }
  .sk-teks { font-size: 13px; line-height: 1.65; color: #334155; }
  .sk-prog { height: 4px; background: #E2E8F0; border-radius: 999px; overflow: hidden; }
  .sk-prog > i { display: block; height: 100%; width: 0%; border-radius: 999px; background: linear-gradient(90deg, #0056B3, #0E9F6E); transition: width .15s ease; }
  .sk-ft { background: #F8FAFC; }
  .sk-cb { accent-color: #0056B3; }
  @media (max-width: 640px) {
    .sk-ol > li { padding: 9px 10px; }
    .sk-teks { font-size: 12.5px; }
  }
</style>

<div id="modal-sk" class="fixed inset-0 z-[80] hidden" role="dialog" aria-modal="true" aria-labelledby="sk-title">
  <!-- Overlay -->
  <div id="modal-sk-overlay" class="absolute inset-0 bg-ink-900/55 backdrop-blur-sm"></div>

  <!-- Konten modal -->
  <div class="relative flex items-end sm:items-center justify-center min-h-full p-0 sm:p-4">
    <div id="modal-sk-panel"
         class="bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl w-full max-w-lg max-h-[92vh] flex flex-col overflow-hidden"
         tabindex="-1">
      <!-- Header gradien -->
      <div class="sk-hd shrink-0 px-5 py-4 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
          <span class="sk-ic w-9 h-9 rounded-xl grid place-items-center shrink-0">
            <span class="ms text-[20px]">gavel</span>
          </span>
          <div class="min-w-0">
            <h2 id="sk-title" class="font-bold text-white text-[17px] leading-tight">Syarat &amp; Ketentuan</h2>
            <p class="text-[11.5px] text-white/75 mt-0.5">Pembacaan wajib sebelum mendaftar · Versi <?= e((string) $syaratVersi) ?></p>
          </div>
        </div>
        <button type="button" id="modal-sk-close"
                class="w-8 h-8 grid place-items-center rounded-lg text-white/80 hover:text-white hover:bg-white/15 shrink-0"
                aria-label="Tutup">
          <span class="ms text-[20px]">close</span>
        </button>
      </div>

      <!-- Isi teks S&K — kartu pasal, bisa di-scroll -->
      <div class="flex-1 overflow-y-auto overscroll-contain bg-canvas px-5 py-5">
        <?= $syaratHtml ?>
        <p class="mt-4 text-center text-[11.5px] text-ink-400">
          Versi berlaku: <strong class="text-ink-500"><?= e((string) $syaratVersi) ?></strong>
          · Perubahan teks selanjutnya hanya berlaku untuk pendaftar baru.
        </p>
      </div>

      <!-- Indikator progres baca -->
      <div class="shrink-0 px-5 pt-2">
        <div class="sk-prog" aria-hidden="true"><i id="modal-sk-prog"></i></div>
      </div>

      <!-- Footer: checkbox + tombol -->
      <div class="sk-ft shrink-0 px-5 py-4 border-t border-line space-y-3">
        <label class="flex items-start gap-3 cursor-pointer select-none">
          <input type="checkbox" id="modal-sk-setuju" class="sk-cb mt-0.5 w-5 h-5 rounded border-line shrink-0" disabled>
          <span class="text-sm text-ink-700 leading-snug">Saya telah membaca dan menyetujui <strong>Syarat &amp; Ketentuan</strong> di atas.</span>
        </label>
        <div class="flex gap-3">
          <button type="button" id="modal-sk-batal" class="btn btn-outline flex-1 sm:flex-none">Batal</button>
          <button type="button" id="modal-sk-lanjut" class="btn btn-primary flex-1 sm:flex-none" disabled>
            Lanjut &amp; Daftar <?= icon('arrow_forward') ?>
          </button>
        </div>
        <p id="modal-sk-hint" class="text-xs text-ink-400">Gulir ke bawah untuk membaca seluruh isi.</p>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  var modal   = document.getElementById('modal-sk');
  var panel   = document.getElementById('modal-sk-panel');
  var overlay = document.getElementById('modal-sk-overlay');
  var isi     = panel.querySelector('.flex-1.overflow-y-auto');
  var cb      = document.getElementById('modal-sk-setuju');
  var btnOK   = document.getElementById('modal-sk-lanjut');
  var btnBatal= document.getElementById('modal-sk-batal');
  var btnClose= document.getElementById('modal-sk-close');
  var hint    = document.getElementById('modal-sk-hint');
  var prog    = document.getElementById('modal-sk-prog');
  var form    = document.querySelector('form[action*="registrasi"]');
  var lastFocus = null;

  /* Buka modal — dipanggil saat form di-submit (client-side) */
  function bukaModal() {
    lastFocus = document.activeElement;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    isi.scrollTop = 0;
    cb.checked = false;
    cb.disabled = true;
    btnOK.disabled = true;
    prog.style.width = '0%';
    hint.textContent = 'Gulir ke bawah untuk membaca seluruh isi.';
    hint.className = 'text-xs text-ink-400';
    panel.focus();
    cekScroll();
  }

  /* Tutup modal */
  function tutupModal() {
    modal.classList.add('hidden');
    document.body.style.overflow = '';
    if (lastFocus) lastFocus.focus();
  }

  /* Progres baca + aktifkan checkbox bila sudah sampai bawah */
  function cekScroll() {
    var total   = isi.scrollHeight - isi.clientHeight;
    var persen  = total <= 2 ? 100 : Math.min(100, Math.round(isi.scrollTop / total * 100));
    prog.style.width = persen + '%';
    var bawah = total <= 2 || isi.scrollTop + isi.clientHeight >= isi.scrollHeight - 8;
    if (bawah) {
      cb.disabled = false;
      hint.textContent = 'Anda sudah membaca seluruh isi. Silakan centang persetujuan.';
      hint.className = 'text-xs text-[#065F46] font-medium';
    }
  }

  /* Sinkronkan status tombol Lanjut */
  function sinkron() {
    btnOK.disabled = !cb.checked;
  }

  /* Intercept submit form → tampilkan modal dulu */
  if (form) {
    form.addEventListener('submit', function (e) {
      if (!form.checkValidity()) return;
      e.preventDefault();
      bukaModal();
    });
  }

  isi.addEventListener('scroll', cekScroll, { passive: true });
  window.addEventListener('resize', cekScroll);
  cb.addEventListener('change', sinkron);
  btnBatal.addEventListener('click', tutupModal);
  btnClose.addEventListener('click', tutupModal);
  overlay.addEventListener('click', tutupModal);

  /* Tombol Lanjut: submit form final dengan setuju_sk=1 */
  btnOK.addEventListener('click', function () {
    if (btnOK.disabled) return;
    var input = form.querySelector('input[name="setuju_sk"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'setuju_sk';
      input.value = '1';
      form.appendChild(input);
    }
    tutupModal();
    form.submit();
  });

  /* Kunci fokus di dalam modal (focus trap) + Escape */
  panel.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { tutupModal(); return; }
    if (e.key !== 'Tab') return;
    var fokus = panel.querySelectorAll('button:not([disabled]), input:not([disabled]), [tabindex="0"]');
    if (!fokus.length) return;
    var pertama = fokus[0];
    var terakhir = fokus[fokus.length - 1];
    if (e.shiftKey && document.activeElement === pertama) {
      e.preventDefault(); terakhir.focus();
    } else if (!e.shiftKey && document.activeElement === terakhir) {
      e.preventDefault(); pertama.focus();
    }
  });
})();
</script>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
