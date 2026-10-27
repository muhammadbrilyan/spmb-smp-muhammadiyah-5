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

$title = 'Generate Akun & Kirim Kredensial';
$desc  = 'Generate akun siswa dan pengiriman kredensial WhatsApp via Fonnte.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* ==================================================================
 * Proses POST "” sebelum output apa pun
 * ================================================================== */
$hasilProses = [];   // ringkasan per-pendaftar
$galat = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . url('app/operator/index.php'));
    exit;
}
if (!csrf_cek($_POST['_csrf'] ?? '')) {
    flash('error', 'Formulir kedaluwarsa. Muat ulang lalu coba lagi.');
    header('Location: ' . url('app/operator/index.php'));
    exit;
}

$aksi    = $_POST['aksi'] ?? '';
$targets = array_filter(array_map('trim', (array) ($_POST['ids'] ?? [])));
$pesanBlast = trim((string) ($_POST['pesan_blast'] ?? ''));

if ($aksi === 'blast') {
    /* Blast WA massal ke seluruh pendaftar dengan akun aktif + WA orang tua. */
    $baris = q_all(
        "SELECT p.id, p.no_pendaftaran, p.nama_siswa, o.no_whatsapp
           FROM pendaftar p
           JOIN orang_tua o ON o.pendaftar_id = p.id
          WHERE p.status_akun = 'AKTIF'
          ORDER BY p.no_pendaftaran"
    ) ?: [];
    if ($pesanBlast === '') {
        $galat = 'Pesan blast tidak boleh kosong.';
    } elseif (!$baris) {
        $galat = 'Tidak ada pendaftar dengan akun aktif untuk blast.';
    } else {
        foreach ($baris as $b) {
            $r = wa_kirim($b['no_whatsapp'], $pesanBlast);
            $hasilProses[] = [
                'reg'    => $b['no_pendaftaran'],
                'nama'   => $b['nama_siswa'],
                'wa'     => $b['no_whatsapp'],
                'ok'     => $r['ok'],
                'pesan'  => $r['pesan'],
                'kred'   => null,
            ];
        }
    }
} elseif ($aksi === 'generate_semua') {
    /* Generate semua pendaftar yang belum punya akun & tidak ditolak. */
    $baris = q_all(
        "SELECT id FROM pendaftar
          WHERE user_id IS NULL AND status_akun != 'GAGAL' AND status_verifikasi != 'DITOLAK'
          ORDER BY no_pendaftaran"
    ) ?: [];
    if (!$baris) {
        $galat = 'Semua pendaftar yang berhak sudah memiliki akun.';
    } else {
        foreach ($baris as $b) {
            $hasilProses[] = proses_generate_satu($b['id']);
        }
    }
} elseif ($aksi === 'generate' || $aksi === 'kirim_ulang') {
    if (!$targets) {
        $galat = 'Tidak ada pendaftar yang dipilih.';
    } else {
        foreach ($targets as $id) {
            $hasilProses[] = ($aksi === 'generate')
                ? proses_generate_satu($id)
                : proses_kirim_ulang($id);
        }
    }
} else {
    $galat = 'Aksi tidak dikenal.';
}

/**
 * Generate akun (bila perlu) lalu kirim WA kredensial.
 * Fungsi lokal "” dipakai aksi generate/generate_semua.
 */
function proses_generate_satu($pendaftarId)
{
    $detail = pendaftar_detail_akun($pendaftarId);
    if (!$detail) {
        return ['reg' => '-', 'nama' => '-', 'wa' => '-', 'ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.', 'kred' => null];
    }
    $kred = null;
    if ($detail['user_id']) {
        /* Sudah punya akun "” kirim ulang berarti reset password. */
        $reset = akun_reset_password($detail['user_id']);
        if (!$reset['ok']) {
            return ['reg' => $detail['no_pendaftaran'], 'nama' => $detail['nama_siswa'], 'wa' => $detail['no_whatsapp'] ?? '-', 'ok' => false, 'pesan' => $reset['pesan'], 'kred' => null];
        }
        $kred = [
            'nama'     => $detail['nama_siswa'],
            'reg'      => $detail['no_pendaftaran'],
            'username' => $detail['username'],
            'password' => $reset['password'],
        ];
    } else {
        $baru = akun_buat($pendaftarId);
        if (!$baru['ok']) {
            return ['reg' => $detail['no_pendaftaran'], 'nama' => $detail['nama_siswa'], 'wa' => $detail['no_whatsapp'] ?? '-', 'ok' => false, 'pesan' => $baru['pesan'], 'kred' => null];
        }
        $kred = [
            'nama'     => $baru['nama'],
            'reg'      => $baru['reg'],
            'username' => $baru['username'],
            'password' => $baru['password'],
        ];
    }

    $pesan = wa_pesan_kredensial($kred + ['tahun' => $GLOBALS['PENGATURAN']['tahun_ajaran'] ?? '', 'spp' => $GLOBALS['PENGATURAN']['biaya_spp'] ?? null]);
    $r = wa_kirim($detail['no_whatsapp'], $pesan);
    if ($r['ok']) {
        akun_tandai_terkirim($pendaftarId);
    }
    return [
        'reg'   => $detail['no_pendaftaran'],
        'nama'  => $detail['nama_siswa'],
        'wa'    => $detail['no_whatsapp'] ?? '-',
        'ok'    => $r['ok'],
        'pesan' => $r['ok'] ? 'Akun dibuat & kredensial terkirim.' : 'Akun dibuat, tapi kirim WA gagal: ' . $r['pesan'],
        'kred'  => $kred,   // selalu tampilkan "” operator bisa salurkan manual bila WA gagal
    ];
}

/** Kirim ulang = reset password + kirim kredensial baru. */
function proses_kirim_ulang($pendaftarId)
{
    $detail = pendaftar_detail_akun($pendaftarId);
    if (!$detail) {
        return ['reg' => '-', 'nama' => '-', 'wa' => '-', 'ok' => false, 'pesan' => 'Pendaftar tidak ditemukan.', 'kred' => null];
    }
    if (!$detail['user_id']) {
        return ['reg' => $detail['no_pendaftaran'], 'nama' => $detail['nama_siswa'], 'wa' => $detail['no_whatsapp'] ?? '-', 'ok' => false, 'pesan' => 'Belum ada akun "” gunakan Generate terlebih dahulu.', 'kred' => null];
    }
    $reset = akun_reset_password($detail['user_id']);
    if (!$reset['ok']) {
        return ['reg' => $detail['no_pendaftaran'], 'nama' => $detail['nama_siswa'], 'wa' => $detail['no_whatsapp'] ?? '-', 'ok' => false, 'pesan' => $reset['pesan'], 'kred' => null];
    }
    $kred = [
        'nama'     => $detail['nama_siswa'],
        'reg'      => $detail['no_pendaftaran'],
        'username' => $detail['username'],
        'password' => $reset['password'],
    ];
    $pesan = wa_pesan_kredensial($kred + ['tahun' => $GLOBALS['PENGATURAN']['tahun_ajaran'] ?? '']);
    $r = wa_kirim($detail['no_whatsapp'], $pesan);
    if ($r['ok']) {
        akun_tandai_terkirim($pendaftarId);
    }
    return [
        'reg'   => $detail['no_pendaftaran'],
        'nama'  => $detail['nama_siswa'],
        'wa'    => $detail['no_whatsapp'] ?? '-',
        'ok'    => $r['ok'],
        'pesan' => $r['ok'] ? 'Kredensial baru terkirim.' : 'Reset password berhasil, tapi kirim WA gagal: ' . $r['pesan'],
        'kred'  => $kred,
    ];
}

require_once __DIR__ . '/../../includes/partials/head.php';
$activeMenu = 'pendaftar';
require __DIR__ . '/../../includes/partials/sidebar.php';
$breadcrumbs = ['Portal Akademik', 'SPMB', 'Generate Akun & WA'];
$pageActions = '';
require __DIR__ . '/../../includes/partials/topbar.php';

$jmlOk    = count(array_filter($hasilProses, fn($h) => $h['ok']));
$jmlGagal = count($hasilProses) - $jmlOk;
?>

<div class="lg:pl-[264px]">
  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1100px]">

    <div class="flex items-center gap-2 flex-wrap">
      <!-- Status token Fonnte: hijau = aktif, kuning = belum diatur -->
      <span class="badge <?= fonnte_token() ? 'badge-green' : 'badge-amber' ?>">
        <?= fonnte_token() ? dot('green') . ' Gateway aktif' : dot('amber') . ' Token belum diatur' ?>
      </span>
    </div>
    <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Hasil Generate Akun &amp; Kirim WA</h1>

    <?php if ($galat): ?>
      <div class="mt-5 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] p-4 text-sm text-[#991B1B]" role="alert">
        <?= e($galat) ?>
      </div>
    <?php endif; ?>

    <?php if ($hasilProses): ?>
      <div class="mt-5 grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-[#A7F3D0] bg-[#ECFDF5] px-4 py-3">
          <p class="text-[12px] text-[#065F46]/80">Berhasil</p>
          <p class="text-2xl font-extrabold text-[#065F46]"><?= $jmlOk ?></p>
        </div>
        <div class="rounded-2xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-3">
          <p class="text-[12px] text-[#991B1B]/80">Gagal / Perlu Tindakan</p>
          <p class="text-2xl font-extrabold text-[#991B1B]"><?= $jmlGagal ?></p>
        </div>
      </div>

      <div class="mt-5 card p-5 sm:p-6">
        <h2 class="font-bold text-ink-900">Rincian Per Pendaftar</h2>
        <p class="hint mt-1">Kredensial hanya ditampilkan sekali di halaman ini. Salin sebelum meninggalkan halaman.</p>
        <div class="mt-4 space-y-3">
          <?php foreach ($hasilProses as $h): ?>
            <div class="rounded-2xl border <?= $h['ok'] ? 'border-[#A7F3D0] bg-[#ECFDF5]/40' : 'border-[#FECACA] bg-[#FEF2F2]/40' ?> p-4">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                  <p class="font-semibold text-ink-900"><?= e($h['nama']) ?> <span class="font-mono text-[13px] text-ink-500">(<?= e($h['reg']) ?>)</span></p>
                  <p class="text-[13px] text-ink-500 mt-0.5">WA: <span class="font-mono"><?= e($h['wa']) ?></span></p>
                </div>
                <span class="badge <?= $h['ok'] ? 'badge-green' : 'badge-red' ?>"><?= $h['ok'] ? 'Sukses' : 'Gagal' ?></span>
              </div>
              <p class="mt-2 text-[13px] <?= $h['ok'] ? 'text-[#065F46]' : 'text-[#991B1B]' ?>"><?= e($h['pesan']) ?></p>
              <?php if (!empty($h['kred'])): ?>
                <div class="mt-3 rounded-xl bg-white border border-line p-3">
                  <p class="text-[12px] font-semibold text-ink-500 uppercase tracking-wide">Kredensial (salin manual bila WA gagal)</p>
                  <p class="mt-1.5 text-sm">Username: <span class="font-mono font-bold"><?= e($h['kred']['username']) ?></span> '· Password: <span class="font-mono font-bold select-all"><?= e($h['kred']['password']) ?></span></p>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="mt-6">
      <a href="<?= url('app/operator/index.php') ?>#tab=akun" class="btn btn-outline"><?= icon('arrow_back', 'text-[18px]') ?> Kembali ke Dashboard Operator</a>
    </div>

  </main>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  window.APP_FLASH = <?= json_encode($_SESSION['flash'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
</script>
</body>
</html>
