<?php
/**
 * FOOTER HALAMAN PUBLIK — tampil di landing page & halaman login.
 * Dipanggil via: require footer-public.php
 *
 * YANG SERING DIUBAH:
 *   - Teks profil sekolah  → paragraf di kolom kiri
 *   - Kontak (alamat/WA/email) → dari CMS Pengaturan (otomatis)
 *   - Ikon media sosial    → dari CMS Pengaturan → Media Sosial; kosong = tidak tampil
 *   - Tautan pojok kanan bawah → ganti teks & href di bagian bawah file ini
 */
?>
<footer class="bg-white border-t border-line mt-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="grid gap-10 md:grid-cols-12">
      <div class="md:col-span-5">
        <div class="flex items-center gap-3">
          <img src="<?= e($PENGATURAN['logo_url']) ?>" alt="Logo" class="w-11 h-11">
          <div>
            <div class="font-bold text-ink-900 leading-tight"><?= e($PENGATURAN['nama_sekolah']) ?></div>
            <div class="text-xs text-ink-500">Kota Tangerang · Banten</div>
          </div>
        </div>
        <p class="mt-4 text-sm text-ink-500 leading-relaxed max-w-md">
          Lembaga pendidikan Islam di bawah naungan Dikdasmen PDM Kota Tangerang yang berorientasi
          tahfidz dan sains teknologi. Terakreditasi <strong class="text-ink-700">A (Unggul)</strong> dan
          berkomitmen mencetak peserta didik berkarakter mulia, cerdas, dan kompetitif.
        </p>
        <?php
        /* Ikon media sosial dipakai hanya bila operator menempelkan tautannya
         * di Pengaturan Sistem → Identitas & Kontak → Media Sosial.
         * Kolom kosong = ikon tidak dirender, bukan tombol mati ke "#". */
        $sosmedFooter = [
            'Instagram'            => ['url' => trim((string) ($PENGATURAN['sosmed_instagram'] ?? '')), 'ikon' => 'photo_camera'],
            'TikTok'               => ['url' => trim((string) ($PENGATURAN['sosmed_tiktok'] ?? '')),    'ikon' => 'music_note'],
            'YouTube'              => ['url' => trim((string) ($PENGATURAN['sosmed_youtube'] ?? '')),   'ikon' => 'smart_display'],
            'Website Profil Sekolah' => ['url' => trim((string) ($PENGATURAN['website_sekolah'] ?? '')), 'ikon' => 'language'],
        ];
        $sosmedTerisi = array_filter($sosmedFooter, static function ($s) { return $s['url'] !== ''; });
        ?>
        <?php if ($sosmedTerisi): ?>
          <div class="mt-5 flex items-center gap-2">
            <?php foreach ($sosmedTerisi as $namaJaringan => $s): ?>
              <a href="<?= e($s['url']) ?>" target="_blank" rel="noopener me" title="<?= e($namaJaringan) ?>"
                 aria-label="<?= e($namaJaringan) ?> <?= e($PENGATURAN['nama_sekolah']) ?>"
                 class="icon-box border border-line text-ink-500 hover:text-primary hover:border-primary"><?= icon($s['ikon']) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="md:col-span-3">
        <h4 class="text-sm font-bold text-ink-900 uppercase tracking-wide">Kontak Cepat SPMB</h4>
        <?php $waFooterPanitia = preg_replace('/\D+/', '', (string) ($PENGATURAN['kontak_wa_panitia'] ?? '')); ?>
        <ul class="mt-4 space-y-3 text-sm text-ink-500">
          <li class="flex gap-2"><?= icon('call', 'text-[18px] text-primary') ?><span>Panitia SPMB<br>
            <?php if ($waFooterPanitia !== ''): ?>
              <a class="text-ink-700 font-medium" href="https://wa.me/<?= e($waFooterPanitia) ?>" target="_blank" rel="noopener"><?= e($PENGATURAN['kontak_wa_panitia']) ?></a>
            <?php else: ?>
              <span class="text-ink-700 font-medium">Nomor panitia belum diisi</span>
            <?php endif; ?>
          </span></li>
          <li class="flex gap-2"><?= icon('mail', 'text-[18px] text-primary') ?><span><?= e($PENGATURAN['email_sekolah']) ?></span></li>
          <li class="flex gap-2"><?= icon('schedule', 'text-[18px] text-primary') ?><span>Senin – Jumat, 08.00 – 15.00 WIB</span></li>
        </ul>
      </div>

      <div class="md:col-span-4">
        <h4 class="text-sm font-bold text-ink-900 uppercase tracking-wide">Sekretariat Panitia SPMB</h4>
        <ul class="mt-4 space-y-3 text-sm text-ink-500">
          <li class="flex gap-2"><?= icon('location_on', 'text-[18px] text-primary') ?><span><?= e($PENGATURAN['alamat_sekolah']) ?></span></li>
          <li class="flex gap-2"><?= icon('account_balance', 'text-[18px] text-primary') ?><span><?= e($PENGATURAN['nama_bank']) ?> · <?= e($PENGATURAN['no_rekening']) ?><br>a.n. <?= e($PENGATURAN['atas_nama_rekening']) ?></span></li>
          <li class="flex gap-2"><?= icon('event', 'text-[18px] text-primary') ?><span>Periode Pendaftaran<br>1 Oktober – 30 November <?= date('Y') ?></span></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="border-t border-line">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-ink-400">
      <p>&copy; <?= date('Y') ?> <?= e($PENGATURAN['nama_sekolah']) ?> · Pendidikan Dasar & Menengah PDM Kota Tangerang</p>
      <div class="flex items-center gap-4">
        <a href="#" class="hover:text-primary">Kebijakan Privasi</a>
        <a href="#" class="hover:text-primary">Layanan Kontak</a>
        <a href="<?= url('app/auth/login.php') ?>" class="hover:text-primary font-semibold">Masuk Portal</a>
      </div>
    </div>
  </div>
</footer>
