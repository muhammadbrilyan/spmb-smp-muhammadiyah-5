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
        cb.checked = selectAll.checked;
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

  /* ---------------- Filter tabel: pencarian + select status ---------------- */
  const search = document.getElementById('table-search');

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
      row.style.display = cocok ? '' : 'none';
    });
  }

  if (search) search.addEventListener('input', filterTabel);
  document.querySelectorAll('[data-filter-status]').forEach(function (sel) {
    sel.addEventListener('change', filterTabel);
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
