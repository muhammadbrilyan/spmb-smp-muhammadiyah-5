/* app.js — interaksi UI (statis, tanpa backend) */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------------- Sidebar mobile ---------------- */
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('-translate-x-full');
    if (overlay) overlay.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }
  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('-translate-x-full');
    if (overlay) overlay.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }

  document.querySelectorAll('[data-sidebar-open]').forEach(function (el) {
    el.addEventListener('click', openSidebar);
  });
  document.querySelectorAll('[data-sidebar-close]').forEach(function (el) {
    el.addEventListener('click', closeSidebar);
  });
  if (overlay) overlay.addEventListener('click', closeSidebar);

  /* ---------------- Sidebar lipat (desktop) ---------------- */
  const ikonSidebar = document.getElementById('ikon-sidebar');
  function gambarIkonSidebar() {
    if (!ikonSidebar) return;
    ikonSidebar.textContent = document.documentElement.classList.contains('sidebar-lipat')
      ? 'left_panel_open'
      : 'left_panel_close';
  }
  document.querySelectorAll('[data-sidebar-toggle]').forEach(function (el) {
    el.addEventListener('click', function () {
      const lipat = document.documentElement.classList.toggle('sidebar-lipat');
      try { localStorage.setItem('sidebar-lipat', lipat ? '1' : '0'); } catch (e) { /* storage diblokir */ }
      gambarIkonSidebar();
    });
  });
  gambarIkonSidebar();

  /* ---------------- Tab (dashboard) ---------------- */
  const tabButtons = document.querySelectorAll('[data-tab-target]');
  if (tabButtons.length) {
    tabButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        const target = btn.getAttribute('data-tab-target');

        tabButtons.forEach(function (b) {
          const active = b === btn;
          b.setAttribute('data-active', active ? 'true' : 'false');
          b.classList.toggle('bg-white', active);
          b.classList.toggle('shadow-card', active);
          b.classList.toggle('text-primary', active);
          b.classList.toggle('text-ink-500', !active);
        });

        document.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
          panel.classList.toggle('hidden', panel.getAttribute('data-tab-panel') !== target);
        });
      });
    });
  }

  /* ---------------- Modal ---------------- */
  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  }
  function closeModal(modal) {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
  }

  document.querySelectorAll('[data-modal-open]').forEach(function (el) {
    el.addEventListener('click', function () {
      openModal(el.getAttribute('data-modal-open'));
      const title = el.getAttribute('data-modal-title');
      const name = el.getAttribute('data-modal-name');
      const t = document.getElementById('modal-reg');
      const n = document.getElementById('modal-nama');
      if (t && title) t.textContent = title;
      if (n && name) n.textContent = name;

      /* Isi bidang modal dari atribut data-md-<kunci> -> [data-md-fill="<kunci>"] */
      Array.prototype.forEach.call(el.attributes, function (attr) {
        if (attr.name.indexOf('data-md-') !== 0 || attr.name === 'data-md-fill') return;
        const kunci = attr.name.slice('data-md-'.length);
        if (!kunci) return;
        document.querySelectorAll('[data-md-fill="' + kunci + '"]').forEach(function (target) {
          if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') {
            target.value = attr.value;
          } else {
            target.textContent = attr.value;
          }
        });
      });
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(function (el) {
    el.addEventListener('click', function () {
      closeModal(el.closest('.modal-root'));
    });
  });

  document.querySelectorAll('.modal-root').forEach(function (modal) {
    modal.addEventListener('click', function (ev) {
      if (ev.target === modal) closeModal(modal);
    });
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') {
      document.querySelectorAll('.modal-root:not(.hidden)').forEach(closeModal);
      closeSidebar();
    }
  });

  /* ---------------- Select all checkbox ---------------- */
  const selectAll = document.getElementById('check-all');
  if (selectAll) {
    selectAll.addEventListener('change', function () {
      document.querySelectorAll('.row-check').forEach(function (cb) {
        /* Hanya baris yang sedang tampil (halaman aktif + lolos filter),
         * agar tidak memilih baris tersembunyi tanpa disadari. */
        const tr = cb.closest('tr');
        cb.checked = selectAll.checked && (!tr || tr.style.display !== 'none');
      });
      updateBulkBar();
    });
    document.querySelectorAll('.row-check').forEach(function (cb) {
      cb.addEventListener('change', updateBulkBar);
    });
  }

  function updateBulkBar() {
    const bar = document.getElementById('bulk-bar');
    if (!bar) return;
    const n = document.querySelectorAll('.row-check:checked').length;
    const label = document.getElementById('bulk-count');
    if (label) label.textContent = n;
    bar.classList.toggle('hidden', n === 0);
  }

  /* ---------------- Filter tabel + paginasi sisi-klien ----------------
   * Tabel ber-atribut data-paginate="N" dipecah per halaman. Baris yang tidak
   * lolos filter diberi data-cocok="0". Pager: [data-pager-for="<id tabel>"]. */
  const search = document.getElementById('table-search');
  const paging = {};

  function barisTabel(tabel) {
    return Array.prototype.slice.call(tabel.querySelectorAll('tbody tr'));
  }

  function resetPilihan() {
    if (selectAll) selectAll.checked = false;
    document.querySelectorAll('.row-check:checked').forEach(function (cb) { cb.checked = false; });
    updateBulkBar();
  }

  function tombolHalaman(label, aktif, nonaktif, onClick) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-sm ' + (aktif ? 'bg-primary text-white border border-primary' : 'btn-outline');
    b.textContent = label;
    if (nonaktif) b.disabled = true;
    if (onClick) b.addEventListener('click', onClick);
    return b;
  }

  function renderPaginasi(tabel, reset) {
    const id = tabel.id;
    const st = paging[id] || (paging[id] = { ukuran: parseInt(tabel.getAttribute('data-paginate'), 10) || 10, halaman: 1 });
    if (reset) st.halaman = 1;

    const semua = barisTabel(tabel);
    const total = semua.filter(function (r) { return r.getAttribute('data-cocok') !== '0'; }).length;
    const jml = Math.max(1, Math.ceil(total / st.ukuran));
    if (st.halaman > jml) st.halaman = jml;
    const awal = (st.halaman - 1) * st.ukuran;
    const akhir = awal + st.ukuran;

    let idx = 0;
    semua.forEach(function (r) {
      if (r.getAttribute('data-cocok') === '0') { r.style.display = 'none'; return; }
      r.style.display = (idx >= awal && idx < akhir) ? '' : 'none';
      idx++;
    });

    const pager = document.querySelector('[data-pager-for="' + id + '"]');
    if (!pager) return;

    const info = pager.querySelector('[data-pager-info]');
    if (info) {
      info.textContent = total === 0
        ? 'Tidak ada data yang cocok.'
        : 'Menampilkan ' + (awal + 1) + '–' + Math.min(akhir, total) + ' dari ' + total +
          (total !== semua.length ? ' (disaring dari ' + semua.length + ')' : '') + ' data';
    }

    const sel = pager.querySelector('[data-pager-size]');
    if (sel) sel.value = String(st.ukuran);

    const nav = pager.querySelector('[data-pager-nav]');
    if (!nav) return;
    nav.innerHTML = '';
    if (jml <= 1) return;

    function ke(h) { return function () { st.halaman = h; resetPilihan(); renderPaginasi(tabel, false); }; }
    nav.appendChild(tombolHalaman('‹ Sebelumnya', false, st.halaman === 1, ke(st.halaman - 1)));
    let terakhir = 0;
    for (let h = 1; h <= jml; h++) {
      if (h === 1 || h === jml || Math.abs(h - st.halaman) <= 1) {
        if (terakhir && h - terakhir > 1) {
          const t = document.createElement('span');
          t.className = 'px-1 text-ink-400';
          t.textContent = '…';
          nav.appendChild(t);
        }
        nav.appendChild(tombolHalaman(String(h), h === st.halaman, false, h === st.halaman ? null : ke(h)));
        terakhir = h;
      }
    }
    nav.appendChild(tombolHalaman('Berikutnya ›', false, st.halaman === jml, ke(st.halaman + 1)));
  }

  function filterTabel() {
    const q = search ? search.value.toLowerCase().trim() : '';
    const saringan = [];
    document.querySelectorAll('[data-filter-status]').forEach(function (sel) {
      if (sel.value) saringan.push({ attr: sel.getAttribute('data-filter-status'), val: sel.value });
    });

    document.querySelectorAll('#data-table tbody tr').forEach(function (row) {
      let cocok = !q || row.textContent.toLowerCase().indexOf(q) > -1;
      saringan.forEach(function (f) {
        if (cocok && row.getAttribute(f.attr) !== f.val) cocok = false;
      });
      row.setAttribute('data-cocok', cocok ? '1' : '0');
      row.style.display = cocok ? '' : 'none';
    });

    const t = document.getElementById('data-table');
    if (t && t.hasAttribute('data-paginate')) { resetPilihan(); renderPaginasi(t, true); }
  }

  if (search) search.addEventListener('input', filterTabel);
  document.querySelectorAll('[data-filter-status]').forEach(function (sel) {
    sel.addEventListener('change', filterTabel);
  });

  document.querySelectorAll('table[data-paginate]').forEach(function (tabel) {
    const pager = document.querySelector('[data-pager-for="' + tabel.id + '"]');
    const sel = pager ? pager.querySelector('[data-pager-size]') : null;
    if (sel) {
      sel.addEventListener('change', function () {
        paging[tabel.id] = paging[tabel.id] || { halaman: 1 };
        paging[tabel.id].ukuran = parseInt(sel.value, 10) || 10;
        resetPilihan();
        renderPaginasi(tabel, true);
      });
    }
    renderPaginasi(tabel, true);
  });

  /* ---------------- Password toggle (login) ---------------- */
  const pwToggle = document.getElementById('pw-toggle');
  if (pwToggle) {
    pwToggle.addEventListener('click', function () {
      const input = document.getElementById('password');
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      pwToggle.querySelector('.ms').textContent = show ? 'visibility_off' : 'visibility';
    });
  }

  /* ---------------- Isi otomatis kredensial demo ---------------- */
  document.querySelectorAll('[data-demo-fill]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      const user = document.getElementById('username');
      const pass = document.getElementById('password');
      if (user) user.value = chip.getAttribute('data-user') || '';
      if (pass) pass.value = chip.getAttribute('data-pass') || '';
      document.querySelectorAll('[data-demo-fill]').forEach(function (c) {
        c.classList.remove('border-primary', 'bg-primary-light', 'text-primary');
        c.classList.add('border-line', 'bg-white', 'text-ink-700');
      });
      chip.classList.remove('border-line', 'bg-white', 'text-ink-700');
      chip.classList.add('border-primary', 'bg-primary-light', 'text-primary');
      showToast('Kredensial ' + (chip.getAttribute('data-label') || 'demo') + ' dimasukkan.');
    });
  });

  /* ---------------- Form demo (tidak dikirim ke server) ---------------- */
  document.querySelectorAll('form[data-demo]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      showToast(form.getAttribute('data-demo') || 'Tahap UI — data belum tersimpan.');
    });
  });

  /* ---------------- Elemen non-form yang menandai diri sebagai demo ----------------
   * Sebelumnya hanya form[data-demo] yang ditangani, sehingga <button data-demo>
   * dan <a data-demo> KLIKNYA NIHIL TOTAL (tanpa pesan apa pun). Kini keduanya
   * memunculkan toast agar pengguna tahu tombolnya belum terhubung. */
  document.querySelectorAll('button[data-demo], a[data-demo]').forEach(function (el) {
    el.addEventListener('click', function (ev) {
      ev.preventDefault();
      showToast(el.getAttribute('data-demo') || 'Tahap UI — fitur ini belum tersedia.');
    });
  });

  /* ---------------- Toast ---------------- */
  window.showToast = function (message, tone) {
    const host = document.getElementById('toast-host');
    if (!host) return;
    const gagal = tone === 'error';
    const el = document.createElement('div');
    el.className = 'animate-pop flex items-start gap-3 w-full max-w-sm bg-ink-900 text-white text-sm rounded-xl px-4 py-3 shadow-modal';
    el.innerHTML = '<span class="ms text-[18px] ' + (gagal ? 'text-red-400' : 'text-emerald-400') + '">' +
      (gagal ? 'error' : 'check_circle') + '</span><span class="flex-1">' +
      String(message).replace(/</g, '&lt;') + '</span>';
    host.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity .3s, transform .3s';
      el.style.opacity = '0';
      el.style.transform = 'translateY(6px)';
      setTimeout(function () { el.remove(); }, 320);
    }, gagal ? 4200 : 2600);
  };

  /* ---------------- Flash dari server (redirect setelah POST) ---------------- */
  if (window.APP_FLASH && window.APP_FLASH.pesan) {
    window.showToast(window.APP_FLASH.pesan, window.APP_FLASH.tipe);
  }

  /* ---------------- Copy rekening ---------------- */
  document.querySelectorAll('[data-copy]').forEach(function (el) {
    el.addEventListener('click', function () {
      const text = el.getAttribute('data-copy');
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () {
          showToast('Disalin: ' + text);
        });
      } else {
        showToast('Nomor rekening: ' + text);
      }
    });
  });

  /* ---------------- Color picker warna tema (demo) ---------------- */
  const colorPicker = document.getElementById('theme-color');
  if (colorPicker) {
    colorPicker.addEventListener('input', function () {
      document.documentElement.style.setProperty('--primary-live', colorPicker.value);
      const preview = document.getElementById('theme-color-preview');
      if (preview) preview.style.backgroundColor = colorPicker.value;
      const hex = document.getElementById('theme-color-hex');
      if (hex) hex.textContent = colorPicker.value;
    });
  }
});
