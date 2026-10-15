<?php
require_once __DIR__ . '/../../includes/config.php';

/* Hak akses: hanya role operator/admin yang boleh membuka dashboard ini. */
require_role(['operator', 'admin']);

$me = current_user();
$USER_LOGIN = [
    'nama'    => $me['nama'],
    'role'    => $me['role'],
    'jabatan' => $me['jabatan'],
    'inisial' => $me['inisial'],
];

$title = 'Dashboard Operasional & Seleksi SPMB';
$desc  = 'Dashboard operasional operator SPMB: manajemen akun & WA gateway, penjadwalan tes, input hasil seleksi, dan penempatan rombel.';
$bodyClass = 'bg-canvas text-ink-700 antialiased';

/* Sumber data: database db_spmb_mualimku (fallback dummy bila DB mati). */
$pendaftar = daftar_pendaftar();
$jadwal    = daftar_jadwal_tes();
$hasil     = daftar_hasil_seleksi();
$stat      = stat_operator();
$totalPend = count($pendaftar);

require_once __DIR__ . '/../../includes/partials/head.php';

$activeMenu = 'ppdb';
require __DIR__ . '/../../includes/partials/sidebar.php';

$breadcrumbs = ['Portal Akademik' => null, 'SPMB' => null, 'Operasional Operator SPMB' => null];
$pageActions = '
  <a href="' . url('app/operator/export.php?rekap=pendaftar') . '" class="btn btn-outline hidden md:inline-flex">' . icon('download') . ' Export Rekap SPMB</a>
  <a href="' . url('app/operator/penempatan.php') . '" class="btn btn-primary hidden md:inline-flex">' . icon('class') . ' Penempatan Kelas</a>
';
require __DIR__ . '/../../includes/partials/topbar.php';
?>

<div class="lg:pl-[264px]">

  <main class="px-4 sm:px-6 lg:px-8 py-6 lg:py-8 max-w-[1440px]">

    <!-- ====================== JUDUL HALAMAN ====================== -->
    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="badge badge-green"><?= dot('green') ?> WhatsApp Server: Terhubung (42ms)</span>
          <span class="badge badge-gray"><?= e($PENGATURAN['gelombang']) ?></span>
        </div>
        <h1 class="mt-3 text-2xl sm:text-[28px] font-bold tracking-tight text-ink-900">Dashboard Operasional &amp; Seleksi SPMB</h1>
        <p class="mt-1.5 text-sm text-ink-500 max-w-3xl">
          <?= e(APP_SHORT) ?> • Otomatisasi akun, pengiriman kredensial WA, penjadwalan tes, dan alokasi rombel <?= e($PENGATURAN['tahun_ajaran']) ?>.
        </p>
      </div>

      <div class="flex flex-wrap gap-2.5 shrink-0">
        <button class="btn btn-outline" data-demo="Tahap UI — blast WA massal belum terhubung gateway.">
          <?= icon('send') ?> Kirim Blast WA Massal
        </button>
        <a href="<?= url('app/operator/export.php?rekap=seleksi') ?>" class="btn btn-primary shadow-drop"><?= icon('download') ?> Export Rekap Seleksi</a>
        </button>
      </div>
    </div>

    <!-- ====================== KARTU STATistik ====================== -->
    <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Akun Siswa Terbit</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['akun_aktif'] ?><span class="text-base font-semibold text-ink-400"> / <?= (int) $stat['akun_total'] ?></span></p>
          </div>
          <span class="icon-box bg-primary-light text-primary"><?= icon('badge', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center justify-between text-xs">
          <span class="inline-flex items-center gap-1.5 text-[#065F46] font-semibold"><?= dot('green') ?> <?= number_format((float) $stat['akun_persen'], 1, ',', '.') ?>% Akun Aktif</span>
          <span class="text-ink-400">Kredensial Auto-Gen</span>
        </div>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Tes Terjadwal</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['peserta'] ?><span class="text-base font-semibold text-ink-400"> Calon Siswa</span></p>
          </div>
          <span class="icon-box bg-[#FFF7ED] text-[#EA580C]"><?= icon('event', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center justify-between text-xs">
          <span class="text-ink-600"><strong class="text-ink-900"><?= (int) $stat['offline'] ?></strong> Offline Kampus</span>
          <span class="text-ink-600"><strong class="text-ink-900"><?= (int) $stat['daring'] ?></strong> Daring VC</span>
        </div>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Hasil Seleksi</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['dinilai'] ?><span class="text-base font-semibold text-ink-400"> Telah Dinilai</span></p>
          </div>
          <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E]"><?= icon('verified', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4 pt-3 border-t border-line-soft flex items-center gap-2 text-xs">
          <?= badge($stat['lulus'] . ' Lulus', 'green') ?>
          <?= badge($stat['proses'] . ' Proses', 'amber') ?>
        </div>
      </article>

      <article class="card p-5">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-[13px] font-medium text-ink-500">Alokasi Rombel</p>
            <p class="mt-2 text-3xl font-extrabold text-ink-900"><?= (int) $stat['terisi'] ?><span class="text-base font-semibold text-ink-400"> / <?= (int) $stat['kuota'] ?> Kuota</span></p>
          </div>
          <span class="icon-box bg-[#EFF6FF] text-[#2563EB]"><?= icon('meeting_room', 'text-[20px]') ?></span>
        </div>
        <div class="mt-4">
          <div class="h-1.5 rounded-full bg-line-soft overflow-hidden">
            <div class="h-full rounded-full bg-primary" style="width:<?= (float) $stat['persen_terisi'] ?>%"></div>
          </div>
          <div class="mt-2 flex items-center justify-between text-xs">
            <span class="text-ink-500"><?= (int) $stat['rombel'] ?> Rombel (@<?= (int) round($stat['kuota'] / max(1, (int) $stat['rombel'])) ?> Kursi)</span>
            <span class="font-semibold text-primary"><?= number_format((float) $stat['persen_terisi'], 1, ',', '.') ?>% Terisi</span>
          </div>
        </div>
      </article>

    </section>

    <!-- ====================== TAB UTAMA ====================== -->
    <section class="mt-6 card p-2">
      <div class="flex gap-1 overflow-x-auto scroll-thin" role="tablist">
        <button role="tab" class="flex-1 min-w-[190px] flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-primary bg-white shadow-card transition" data-tab-target="akun" data-active="true">
          <span class="ms text-[19px]">send</span> 1. Manajemen Akun &amp; WA Gateway
        </button>
        <button role="tab" class="flex-1 min-w-[190px] flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-ink-500 hover:text-ink-900 transition" data-tab-target="jadwal" data-active="false">
          <span class="ms text-[19px]">event</span> 2. Penjadwalan Tes Seleksi
        </button>
        <button role="tab" class="flex-1 min-w-[190px] flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-ink-500 hover:text-ink-900 transition" data-tab-target="hasil" data-active="false">
          <span class="ms text-[19px]">edit_note</span> 3. Input Hasil Seleksi
        </button>
        <button role="tab" class="flex-1 min-w-[190px] flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-ink-500 hover:text-ink-900 transition" data-tab-target="rombel" data-active="false">
          <span class="ms text-[19px]">meeting_room</span> 4. Penempatan Kelas (Rombel)
        </button>
      </div>
    </section>

    <!-- ====================== PANEL 1: AKUN & WA ====================== -->
    <section data-tab-panel="akun" class="mt-5">

      <!-- Status server WA -->
      <div class="grid gap-4 lg:grid-cols-3">
        <?php $waAktif = !empty($PENGATURAN['fonnte_token']); ?>
        <div class="lg:col-span-2 rounded-2xl border <?= $waAktif ? 'border-[#A7F3D0] bg-[#ECFDF5]' : 'border-[#FDE68A] bg-[#FFFBEB]' ?> p-5 flex flex-col sm:flex-row sm:items-center gap-4">
          <span class="icon-box <?= $waAktif ? 'bg-[#0E9F6E]' : 'bg-[#D97706]' ?> text-white w-11 h-11 shrink-0"><?= icon('sms', 'text-[22px]') ?></span>
          <div class="flex-1 min-w-0">
            <p class="font-bold <?= $waAktif ? 'text-[#065F46]' : 'text-[#92400E]' ?>"><?= $waAktif ? 'WhatsApp Gateway Aktif' : 'WhatsApp Gateway Belum Dikonfigurasi' ?></p>
            <p class="text-xs <?= $waAktif ? 'text-[#065F46]/80' : 'text-[#92400E]/80' ?> mt-0.5 break-words">
              <?php if ($waAktif): ?>
                Endpoint: <span class="font-mono">api.fonnte.com/send</span> • Token tersimpan di CMS
              <?php else: ?>
                Atur token Fonnte di <a class="underline font-semibold" href="<?= url('app/operator/pengaturan.php') ?>">Pengaturan Sistem</a> → WhatsApp Gateway.
              <?php endif; ?>
            </p>
          </div>
          <span class="badge <?= $waAktif ? 'badge-green bg-white!' : 'badge-amber bg-white!' ?> shrink-0"><?= $waAktif ? dot('green') . ' Fonnte siap' : dot('amber') . ' Perlu token' ?></span>
        </div>

        <div class="rounded-2xl border border-line bg-white p-5">
          <div class="flex items-center justify-between gap-4">
            <div>
              <p class="text-[12px] text-ink-500">Kuota Pesan Bulan Ini</p>
              <p class="mt-1 text-xl font-extrabold text-ink-900">1.250 <span class="text-sm font-semibold text-ink-400">/ 5.000</span></p>
            </div>
            <div class="text-right">
              <p class="text-[12px] text-ink-500">Tingkat Pengiriman</p>
              <p class="mt-1 text-xl font-extrabold text-[#0E9F6E]">99.2% <span class="text-sm font-semibold">Sukses</span></p>
            </div>
          </div>
          <div class="mt-3 h-1.5 rounded-full bg-line-soft overflow-hidden">
            <div class="h-full rounded-full bg-accent" style="width:25%"></div>
          </div>
        </div>
      </div>

      <!-- Toolbar -->
      <div class="mt-5 flex flex-col md:flex-row md:items-center gap-3">
        <div class="relative flex-1 max-w-md">
          <span class="ms absolute left-3 top-1/2 -translate-y-1/2 text-[18px] text-ink-400">search</span>
          <input id="table-search" class="input pl-10" type="search" placeholder="Cari No. REG, Nama Siswa, Sekolah...">
        </div>
        <select class="select md:w-52" data-filter-status="data-status">
          <option value="">Semua Status Akun</option>
          <option value="Aktif">Aktif</option>
          <option value="Belum Dibuat">Belum Dibuat</option>
          <option value="Gagal">Gagal</option>
        </select>
        <div class="flex-1"></div>
        <form method="post" action="<?= url('app/operator/akun.php') ?>" class="inline">
          <?= csrf_field() ?>
          <input type="hidden" name="aksi" value="generate_semua">
          <button type="submit" class="btn btn-outline" onclick="return confirm('Generate akun untuk semua pendaftar yang belum punya akun?');">
            <?= icon('key') ?> Generate Semua Akun
          </button>
        </form>
        <button type="button" id="btn-generate-terpilih" class="btn btn-green">
          <?= icon('send') ?> Generate &amp; Kirim WA Terpilih
        </button>
        <details class="relative">
          <summary class="btn btn-outline list-none cursor-pointer"><?= icon('broadcast_on_home', 'text-[18px]') ?> Blast WA</summary>
          <form method="post" action="<?= url('app/operator/akun.php') ?>" class="absolute right-0 z-30 mt-2 w-96 max-w-[90vw] card p-4 shadow-modal">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="blast">
            <label class="label" for="pesan_blast">Pesan Blast ke Seluruh Akun Aktif</label>
            <textarea id="pesan_blast" name="pesan_blast" class="textarea" rows="5" placeholder="Contoh: Pengumuman jadwal tes CBT akan disampaikan H-3..." required></textarea>
            <button type="submit" class="btn btn-primary mt-3 w-full" onclick="return confirm('Kirim pesan ini ke semua pendaftar dengan akun aktif?');">Kirim Blast</button>
          </form>
        </details>
      </div>

      <!-- Tabel -->
      <div class="mt-4 card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
          <table id="data-table" class="w-full min-w-[900px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th w-12"><input id="check-all" type="checkbox" class="rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4"></th>
                <th class="th">No. Pendaftaran</th>
                <th class="th">Nama Siswa &amp; Asal Sekolah</th>
                <th class="th">No. WA Orang Tua</th>
                <th class="th">Status Akun</th>
                <th class="th">Status Kredensial</th>
                <th class="th text-right">Aksi Operator</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php foreach ($pendaftar as $p): ?>
                <?php $toneAkun = $p['akun'] === 'Aktif' ? 'green' : ($p['akun'] === 'Gagal' ? 'red' : 'amber'); ?>
                <tr class="tr-hover" data-status="<?= e($p['akun']) ?>">
                  <td class="td"><input type="checkbox" class="row-check rounded border-line-mid text-primary focus:ring-primary/30 w-4 h-4" data-id="<?= e($p['id']) ?>"></td>
                  <td class="td">
                    <button class="font-mono text-[13px] font-semibold text-primary hover:underline text-left"
                            data-modal-open="modal-detail" data-modal-title="<?= e($p['reg']) ?>" data-modal-name="<?= e($p['nama']) ?>">
                      <?= e($p['reg']) ?>
                    </button>
                    <div class="text-[11px] text-ink-400">NISN <?= e($p['nisn']) ?></div>
                  </td>
                  <td class="td">
                    <div class="font-semibold text-ink-900"><?= e($p['nama']) ?></div>
                    <div class="text-[12px] text-ink-500"><?= e($p['sekolah']) ?> • Jalur <?= e($p['jalur']) ?></div>
                  </td>
                  <td class="td"><span class="font-mono text-[13px]"><?= e($p['wa']) ?></span></td>
                  <td class="td"><?= badge($p['akun'], $toneAkun) ?></td>
                  <td class="td">
                    <span class="inline-flex items-center gap-1.5 text-[13px] <?= $p['kred_tone'] === 'green' ? 'text-[#065F46]' : ($p['kred_tone'] === 'red' ? 'text-[#991B1B]' : 'text-[#92400E]') ?>">
                      <span class="ms text-[16px]"><?= $p['kred_tone'] === 'green' ? 'done_all' : ($p['kred_tone'] === 'red' ? 'error' : 'schedule') ?></span>
                      <?= e($p['kred']) ?>
                    </span>
                  </td>
                  <td class="td text-right">
                    <div class="inline-flex items-center gap-2 justify-end">
                      <button class="icon-box border border-line text-ink-500 hover:text-primary hover:border-primary"
                              data-modal-open="modal-detail" data-modal-title="<?= e($p['reg']) ?>" data-modal-name="<?= e($p['nama']) ?>"
                              aria-label="Lihat detail"><?= icon('visibility', 'text-[18px]') ?></button>
                      <form method="post" action="<?= url('app/operator/akun.php') ?>" class="inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="aksi" value="<?= $p['akun'] === 'Aktif' ? 'kirim_ulang' : 'generate' ?>">
                        <input type="hidden" name="ids[]" value="<?= e($p['id']) ?>">
                        <?php if ($p['akun'] === 'Aktif'): ?>
                          <button type="submit" class="btn btn-sm btn-outline" title="Reset password & kirim kredensial baru"><?= icon('sync', 'text-[15px]') ?> Kirim Ulang</button>
                        <?php else: ?>
                          <button type="submit" class="btn btn-sm btn-green" onclick="return confirm('Generate akun & kirim kredensial ke <?= e($p['wa']) ?>?');"><?= icon('send', 'text-[15px]') ?> Generate &amp; Kirim</button>
                        <?php endif; ?>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Footer tabel -->
        <div class="px-4 py-3.5 border-t border-line bg-white flex flex-col sm:flex-row items-center justify-between gap-3">
          <p class="text-[13px] text-ink-500">Menampilkan <strong class="text-ink-900">1–<?= $totalPend ?></strong> dari <strong class="text-ink-900"><?= $totalPend ?></strong> calon siswa</p>
          <div class="flex items-center gap-1.5">
            <button class="btn btn-sm btn-outline" disabled><?= icon('chevron_left', 'text-[16px]') ?> Sebelumnya</button>
            <button class="btn btn-sm bg-primary text-white border border-primary">1</button>
            <button class="btn btn-sm btn-outline">2</button>
            <button class="btn btn-sm btn-outline">3</button>
            <span class="px-1 text-ink-400">…</span>
            <button class="btn btn-sm btn-outline">36</button>
            <button class="btn btn-sm btn-outline">Selanjutnya <?= icon('chevron_right', 'text-[16px]') ?></button>
          </div>
        </div>
      </div>

      <!-- Catatan SOP -->
      <div class="mt-4 rounded-2xl bg-primary-light border border-primary/15 p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
        <div class="flex items-start gap-3">
          <span class="icon-box bg-primary text-white shrink-0"><?= icon('info') ?></span>
          <div>
            <p class="font-semibold text-ink-900 text-sm">Prosedur Verifikasi Dokumen &amp; Berkas Ujian CBT</p>
            <p class="text-[13px] text-ink-500">Calon siswa yang terverifikasi otomatis menerima kartu ujian digital dan notifikasi WhatsApp orang tua/wali.</p>
          </div>
        </div>
        <a href="#" class="btn btn-sm btn-outline shrink-0" data-demo="Tahap UI — panduan SOP SPMB.">Panduan SOP SPMB <?= icon('arrow_forward', 'text-[16px]') ?></a>
      </div>

      <!-- Form bulk generate (terisi lewat JS tombol "Generate & Kirim WA Terpilih") -->
      <form id="form-bulk-akun" method="post" action="<?= url('app/operator/akun.php') ?>" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="generate">
      </form>

    </section>

    <!-- ====================== PANEL 2: JADWAL TES ====================== -->
    <section data-tab-panel="jadwal" class="mt-5 hidden">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <p class="text-sm text-ink-500">Kelola jadwal tes CBT, wawancara BTQ, dan seleksi minat bakat (offline / video call).</p>
        <a href="<?= url('app/operator/jadwal.php') ?>" class="btn btn-primary"><?= icon('event_available') ?> Kelola Jadwal Tes</a>
      </div>

      <div class="mt-4 card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
          <table class="w-full min-w-[820px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th">Kode</th>
                <th class="th">Nama Kegiatan</th>
                <th class="th">Tanggal &amp; Waktu</th>
                <th class="th">Metode</th>
                <th class="th">Peserta</th>
                <th class="th">Status</th>
                <th class="th text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php foreach ($jadwal as $j): ?>
                <tr class="tr-hover">
                  <td class="td font-mono text-[13px] text-ink-900"><?= e($j['kode']) ?></td>
                  <td class="td font-semibold text-ink-900"><?= e($j['nama']) ?></td>
                  <td class="td"><?= bulan($j['tgl']) ?><div class="text-[12px] text-ink-400"><?= e($j['jam']) ?> WIB</div></td>
                  <td class="td">
                    <span class="badge <?= $j['mode'] === 'Video Call' ? 'badge-blue' : 'badge-gray' ?>"><?= e($j['mode']) ?></span>
                  </td>
                  <td class="td"><span class="font-semibold text-ink-900"><?= (int) $j['peserta'] ?></span> siswa</td>
                  <td class="td"><?= badge($j['status'], $j['tone']) ?></td>
                  <td class="td text-right">
                    <div class="inline-flex gap-2 justify-end">
                      <button class="icon-box border border-line text-ink-500 hover:text-primary" aria-label="Edit" data-demo="Tahap UI — edit jadwal <?= e($j['kode']) ?>."><?= icon('edit', 'text-[17px]') ?></button>
                      <button class="icon-box border border-line text-ink-500 hover:text-primary" aria-label="Kirim WA" data-demo="Tahap UI — notifikasi jadwal dikirim ke wali."><?= icon('send', 'text-[17px]') ?></button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- ====================== PANEL 3: HASIL SELEKSI ====================== -->
    <section data-tab-panel="hasil" class="mt-5 hidden">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <p class="text-sm text-ink-500">Input nilai CBT, BTQ, dan wawancara — status <strong>LULUS</strong> / <strong>TIDAK_LULUS</strong> ditentukan otomatis dari nilai akhir.</p>
        <a href="<?= url('app/operator/seleksi.php') ?>" class="btn btn-primary"><?= icon('edit') ?> Input Hasil Seleksi</a>
      </div>

      <div class="mt-4 card overflow-hidden">
        <div class="overflow-x-auto scroll-thin">
          <table class="w-full min-w-[880px] border-collapse">
            <thead class="thead">
              <tr>
                <th class="th">No. Pendaftaran</th>
                <th class="th">Nama Calon Siswa</th>
                <th class="th text-center">Nilai CBT</th>
                <th class="th text-center">Nilai BTQ</th>
                <th class="th text-center">Wawancara</th>
                <th class="th text-center">Nilai Akhir</th>
                <th class="th text-center">Keputusan</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line-soft">
              <?php foreach ($hasil as $h): ?>
                <?php $tone = $h['status'] === 'LULUS' ? 'green' : ($h['status'] === 'PROSES' ? 'amber' : 'red'); ?>
                <tr class="tr-hover">
                  <td class="td font-mono text-[13px] text-primary"><?= e($h['reg']) ?></td>
                  <td class="td font-semibold text-ink-900"><?= e($h['nama']) ?></td>
                  <td class="td text-center">
                    <input class="input w-20 text-center mx-auto" type="number" value="<?= (int) $h['cbt'] ?>">
                  </td>
                  <td class="td text-center">
                    <input class="input w-20 text-center mx-auto" type="number" value="<?= (int) $h['btq'] ?>">
                  </td>
                  <td class="td text-center">
                    <input class="input w-20 text-center mx-auto" type="number" value="<?= (int) $h['waw'] ?>">
                  </td>
                  <td class="td text-center"><span class="text-base font-extrabold text-ink-900"><?= number_format($h['nilai'], 1, ',', '.') ?></span></td>
                  <td class="td text-center"><?= badge($h['status'], $tone) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="px-4 py-3.5 border-t border-line text-[13px] text-ink-500">
          Menampilkan <strong class="text-ink-900">1–<?= count($hasil) ?></strong> dari <strong class="text-ink-900"><?= count($hasil) ?></strong> calon siswa yang telah dinilai
        </div>
      </div>
    </section>

    <!-- ====================== PANEL 4: ROMBEL ====================== -->
    <section data-tab-panel="rombel" class="mt-5 hidden">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <p class="text-sm text-ink-500">Penempatan kelas dinamis. Total kuota otomatis dihitung dari seluruh rombel aktif (<strong><?= (int) $PENGATURAN['kuota_total'] ?> siswa</strong>).</p>
        <a href="<?= url('app/operator/kelas.php') ?>" class="btn btn-primary"><?= icon('add') ?> Kelola Rombel &amp; Kuota</a>
      </div>

      <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($ROMBEL as $r):
          $persen = round($r['terisi'] / $r['kuota'] * 100);
          $sisa   = $r['kuota'] - $r['terisi'];
          $tone   = $persen >= 90 ? 'bg-red-500' : ($persen >= 75 ? 'bg-amber-500' : 'bg-primary');
          $badge  = $persen >= 90 ? ['Hampir Penuh', 'red'] : ($persen >= 75 ? ['Terisi', 'amber'] : ['Tersedia', 'green']);
        ?>
          <article class="card p-5">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-start gap-3 min-w-0">
                <span class="w-11 h-11 rounded-xl bg-primary-light text-primary grid place-items-center font-bold shrink-0"><?= e($r['kode']) ?></span>
                <div class="min-w-0">
                  <h3 class="font-bold text-ink-900 text-sm leading-snug"><?= e($r['nama']) ?></h3>
                  <p class="text-[12px] text-ink-500 mt-0.5">Wali Kelas: <?= e($r['wali']) ?></p>
                </div>
              </div>
              <?= badge($badge[0], $badge[1]) ?>
            </div>

            <div class="mt-4 flex items-end justify-between">
              <div>
                <span class="text-2xl font-extrabold text-ink-900"><?= (int) $r['terisi'] ?></span>
                <span class="text-sm font-semibold text-ink-400"> / <?= (int) $r['kuota'] ?> Siswa</span>
              </div>
              <span class="text-sm font-semibold <?= $persen >= 90 ? 'text-red-600' : 'text-primary' ?>">Sisa <?= $sisa ?> Kursi</span>
            </div>

            <div class="mt-3 h-2 rounded-full bg-line-soft overflow-hidden">
              <div class="h-full rounded-full <?= $tone ?>" style="width:<?= $persen ?>%"></div>
            </div>

            <div class="mt-4 pt-4 border-t border-line-soft flex items-center justify-between">
              <span class="text-xs text-ink-500">Persentase Keterisian: <strong class="text-ink-900"><?= number_format($persen, 1, ',', '.') ?>%</strong></span>
              <div class="flex gap-1.5">
                <button class="icon-box border border-line text-ink-500 hover:text-primary" aria-label="Edit rombel" data-demo="Tahap UI — edit rombel <?= e($r['kode']) ?>."><?= icon('edit', 'text-[17px]') ?></button>
                <button class="icon-box border border-line text-ink-500 hover:text-red-600" aria-label="Hapus rombel" data-demo="Tahap UI — hapus rombel <?= e($r['kode']) ?>."><?= icon('delete', 'text-[17px]') ?></button>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="mt-4 rounded-2xl bg-white border border-line p-4 flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-3">
          <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E]"><?= icon('groups') ?></span>
          <p class="text-sm text-ink-600">Total <strong class="text-ink-900"><?= (int) $stat['terisi'] ?> siswa</strong> telah ditempatkan dari <strong class="text-ink-900"><?= (int) $stat['kuota'] ?> kursi</strong> tersedia</p>
        </div>
        <span class="badge badge-blue">Keterisian Total: <?= number_format((float) $stat['persen_terisi'], 1, ',', '.') ?>%</span>
      </div>
    </section>

  </main>
</div>

<!-- ====================== MODAL DETAIL PENDAFTAR ====================== -->
<div id="modal-detail" class="modal-root hidden fixed inset-0 z-[60]">
  <div class="absolute inset-0 bg-ink-900/40 backdrop-blur-[2px] animate-fade" data-modal-close></div>
  <div class="relative h-full flex items-start sm:items-center justify-center p-4 sm:p-6 overflow-y-auto scroll-thin">
    <div class="animate-pop w-full max-w-2xl bg-white rounded-2xl border border-line-mid shadow-modal overflow-hidden my-auto">

      <div class="flex items-start justify-between gap-4 px-5 py-4 border-b border-line">
        <div class="flex items-start gap-3 min-w-0">
          <span class="icon-box bg-primary-light text-primary shrink-0"><?= icon('badge') ?></span>
          <div class="min-w-0">
            <h3 class="font-bold text-ink-900 text-lg leading-tight">Detail Calon Siswa <span id="modal-reg" class="font-mono text-primary text-sm">#REG</span></h3>
            <p class="text-[13px] text-ink-500 truncate">Nama: <strong id="modal-nama" class="text-ink-700">—</strong> • Jalur Prestasi Tahfidz</p>
          </div>
        </div>
        <button class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0" data-modal-close aria-label="Tutup"><?= icon('close') ?></button>
      </div>

      <div class="p-5 grid sm:grid-cols-2 gap-4">
        <div class="rounded-xl border border-line p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Biodata Singkat</p>
          <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between gap-3"><dt class="text-ink-500">NISN</dt><dd class="font-semibold text-ink-900">0129482099</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Asal Sekolah</dt><dd class="font-semibold text-ink-900 text-right">MI Nurul Huda</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">Wali</dt><dd class="font-semibold text-ink-900">Bambang Hermanto</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-ink-500">WhatsApp</dt><dd class="font-mono text-ink-900">0812-8823-9102</dd></div>
          </dl>
        </div>

        <div class="rounded-xl border border-line p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Status Administrasi</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <?= badge('Berkas Lengkap', 'green') ?>
            <?= badge('Formulir Terverifikasi', 'green') ?>
            <?= badge('Menunggu Jadwal Tes', 'amber') ?>
          </div>
          <div class="mt-4 rounded-lg bg-canvas border border-line p-3">
            <p class="text-[12px] text-ink-500">Uang masuk</p>
            <p class="font-bold text-ink-900"><?= rupiah($PENGATURAN['biaya_formulir']) ?> <span class="badge badge-green align-middle ml-1">Lunas</span></p>
          </div>
        </div>

        <div class="sm:col-span-2 rounded-xl border border-line p-4">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-ink-400">Riwayat Proses</p>
          <ol class="mt-3 space-y-3">
            <li class="flex gap-3">
              <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E] w-7 h-7"><?= icon('check', 'text-[16px]') ?></span>
              <div class="text-sm"><p class="font-medium text-ink-900">Registrasi awal dibuat</p><p class="text-xs text-ink-400">12 Jan 2025, 09:30 WIB</p></div>
            </li>
            <li class="flex gap-3">
              <span class="icon-box bg-[#ECFDF5] text-[#0E9F6E] w-7 h-7"><?= icon('check', 'text-[16px]') ?></span>
              <div class="text-sm"><p class="font-medium text-ink-900">Bukti transfer diverifikasi bendahara</p><p class="text-xs text-ink-400">13 Jan 2025, 08:12 WIB</p></div>
            </li>
            <li class="flex gap-3">
              <span class="icon-box bg-[#FFFBEB] text-[#B45309] w-7 h-7"><?= icon('schedule', 'text-[16px]') ?></span>
              <div class="text-sm"><p class="font-medium text-ink-900">Penjadwalan tes CBT &amp; BTQ</p><p class="text-xs text-ink-400">Dijadwalkan 18 Okt 2025 · 08:00 WIB</p></div>
            </li>
          </ol>
        </div>
      </div>

      <div class="px-5 py-4 border-t border-line bg-canvas flex flex-wrap gap-3 justify-end">
        <button class="btn btn-danger" data-modal-close><?= icon('close') ?> Tolak Berkas</button>
        <button class="btn btn-outline" data-modal-close>Batal</button>
        <button class="btn btn-green" data-modal-close data-demo="Tahap UI — berkas disetujui & kredensial dikirim."><?= icon('task_alt') ?> Setujui &amp; Kirim Kredensial</button>
      </div>

    </div>
  </div>
</div>

<!-- Aksi massal (muncul saat ada baris terpilih) -->
<div id="bulk-bar" class="hidden fixed bottom-5 left-1/2 -translate-x-1/2 z-40 lg:left-[calc(50%+132px)]">
  <div class="animate-pop flex items-center gap-4 rounded-2xl bg-ink-900 text-white px-5 py-3 shadow-modal">
    <span class="text-sm"><strong id="bulk-count">0</strong> baris dipilih</span>
    <span class="w-px h-5 bg-white/20"></span>
    <button type="button" id="btn-bulk-wa" class="btn btn-sm bg-accent hover:bg-[#0E9F6E] text-white"><?= icon('send', 'text-[15px]') ?> Generate &amp; Kirim WA</button>
    <button class="btn btn-sm bg-white/10 hover:bg-white/20 text-white" onclick="document.getElementById('check-all').click()"><?= icon('close', 'text-[15px]') ?> Batal</button>
  </div>
</div>

<div id="toast-host" class="fixed bottom-5 right-5 z-[70] flex flex-col gap-2 items-end no-print"></div>
<script src="<?= asset('assets/js/app.js') ?>"></script>
<script>
  (function () {
    function kirimTerpilih() {
      var form  = document.getElementById('form-bulk-akun');
      var cek   = Array.prototype.slice.call(document.querySelectorAll('.row-check:checked'));
      if (!cek.length) { showToast('Pilih dulu minimal satu baris.', 'error'); return; }
      if (!confirm('Generate akun (atau reset password) & kirim kredensial ke ' + cek.length + ' pendaftar terpilih?')) return;
      form.querySelectorAll('input[name="ids[]"]').forEach(function (n) { n.remove(); });
      cek.forEach(function (c) {
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'ids[]'; i.value = c.dataset.id || '';
        form.appendChild(i);
      });
      form.submit();
    }
    var btn = document.getElementById('btn-generate-terpilih');
    if (btn) btn.addEventListener('click', kirimTerpilih);
    var btn2 = document.getElementById('btn-bulk-wa');
    if (btn2) btn2.addEventListener('click', kirimTerpilih);
  })();
</script>
</body>
</html>
