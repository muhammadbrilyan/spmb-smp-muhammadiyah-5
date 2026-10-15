<?php
/**
 * FOOTER HALAMAN PUBLIK — tampil di landing page & halaman login.
 * Dipanggil via: require footer-public.php
 *
 * YANG SERING DIUBAH:
 *   - Teks profil sekolah  → paragraf di kolom kiri
 *   - Kontak (alamat/WA/email) → dari CMS Pengaturan (otomatis)
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
        <div class="mt-5 flex items-center gap-2">
          <a href="#" class="icon-box border border-line text-ink-500 hover:text-primary hover:border-primary" aria-label="Instagram"><?= icon('photo_camera') ?></a>
          <a href="#" class="icon-box border border-line text-ink-500 hover:text-primary hover:border-primary" aria-label="YouTube"><?= icon('smart_display') ?></a>
          <a href="#" class="icon-box border border-line text-ink-500 hover:text-primary hover:border-primary" aria-label="Facebook"><?= icon('public') ?></a>
          <a href="#" class="icon-box border border-line text-ink-500 hover:text-primary hover:border-primary" aria-label="TikTok"><?= icon('music_note') ?></a>
        </div>
      </div>

      <div class="md:col-span-3">
        <h4 class="text-sm font-bold text-ink-900 uppercase tracking-wide">Kontak Cepat SPMB</h4>
        <ul class="mt-4 space-y-3 text-sm text-ink-500">
          <li class="flex gap-2"><?= icon('call', 'text-[18px] text-primary') ?><span>Panitia SPMB<br><a class="text-ink-700 font-medium" href="#">0812-9876-5501</a></span></li>
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
