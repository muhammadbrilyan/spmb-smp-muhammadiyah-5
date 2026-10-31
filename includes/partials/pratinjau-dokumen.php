<?php
/**
 * Modal pratinjau berkas (gambar/PDF) — dipakai bersama oleh beberapa halaman.
 *
 * Pemicu cukup berupa elemen apa pun dengan atribut:
 *   data-pratinjau-url, data-pratinjau-nama, data-pratinjau-tipe (gambar|pdf),
 *   data-pratinjau-meta (teks kecil opsional).
 * Sertakan partial ini sekali di akhir <body>; aman bila halaman tidak punya
 * pemicu sama sekali.
 */
?>
<div id="modal-pratinjau-dokumen" class="modal-root hidden fixed inset-0 pratinjau-modal" role="dialog" aria-modal="true" aria-labelledby="pratinjau-judul">
  <div class="absolute inset-0 bg-ink-900/50 backdrop-blur-sm animate-fade" data-modal-close></div>
  <div class="pratinjau-lapis">
    <div class="pratinjau-panel card shadow-modal animate-pop">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h3 id="pratinjau-judul" class="font-bold text-sm text-ink-900 truncate">Pratinjau Dokumen</h3>
          <p id="pratinjau-meta" class="mt-0.5 text-[11px] text-ink-500 truncate"></p>
        </div>
        <button type="button" class="icon-box border border-line text-ink-500 hover:text-red-600 shrink-0"
                data-modal-close aria-label="Tutup pratinjau"><?= icon('close') ?></button>
      </div>
      <div id="pratinjau-isi" class="pratinjau-isi"></div>
      <div class="pratinjau-kaki">
        <button type="button" class="btn btn-sm btn-outline" data-modal-close>Tutup</button>
      </div>
    </div>
  </div>
</div>
<script>
/* Delegasi klik: semua [data-pratinjau-url] di halaman memakai modal ini,
   termasuk elemen yang dirender belakangan. Tidak membuka tab/berkas baru. */
(function () {
  var modal = document.getElementById('modal-pratinjau-dokumen');
  if (!modal || modal.getAttribute('data-siap') === '1') return;
  modal.setAttribute('data-siap', '1');

  var judul = document.getElementById('pratinjau-judul');
  var meta  = document.getElementById('pratinjau-meta');
  var isi   = document.getElementById('pratinjau-isi');

  function tutup() {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    isi.innerHTML = '';
  }

  function buka(url, nama, tipe, metaTeks) {
    if (!url) return;
    judul.textContent = nama || 'Pratinjau Dokumen';
    meta.textContent  = metaTeks || '';
    isi.innerHTML     = '';

    if (tipe === 'pdf') {
      var bingkai = document.createElement('iframe');
      bingkai.src = url;
      bingkai.title = nama || 'Pratinjau dokumen';
      bingkai.className = 'pratinjau-pdf';
      isi.appendChild(bingkai);
    } else {
      var gambar = document.createElement('img');
      gambar.src = url;
      gambar.alt = nama || 'Pratinjau dokumen';
      gambar.className = 'pratinjau-gambar';
      isi.appendChild(gambar);
    }

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
    var fokus = modal.querySelector('.icon-box[data-modal-close]');
    if (fokus) fokus.focus();
  }

  document.addEventListener('click', function (ev) {
    var btn = ev.target.closest ? ev.target.closest('[data-pratinjau-url]') : null;
    if (!btn) return;
    ev.preventDefault();
    buka(
      btn.getAttribute('data-pratinjau-url'),
      btn.getAttribute('data-pratinjau-nama'),
      btn.getAttribute('data-pratinjau-tipe'),
      btn.getAttribute('data-pratinjau-meta')
    );
  });

  modal.querySelectorAll('[data-modal-close]').forEach(function (el) {
    el.addEventListener('click', tutup);
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !modal.classList.contains('hidden')) tutup();
  });
})();
</script>
