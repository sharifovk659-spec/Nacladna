/* Nakladna Cloud — Main JS */
'use strict';

// Mobile menu toggle (overlay drawer on mobile)
(function () {
  const btn = document.getElementById('menuToggle');
  const sidebar = document.querySelector('.sidebar');
  if (!btn || !sidebar) return;
  btn.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    document.body.classList.toggle('sidebar-open', sidebar.classList.contains('open'));
  });
  document.addEventListener('click', (e) => {
    if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== btn) {
      sidebar.classList.remove('open');
      document.body.classList.remove('sidebar-open');
    }
  });
})();

// Flash message auto-hide
(function () {
  const alerts = document.querySelectorAll('.alert[data-autohide]');
  alerts.forEach(a => {
    setTimeout(() => {
      a.style.opacity = '0';
      a.style.transition = 'opacity .4s';
      setTimeout(() => a.remove(), 400);
    }, 3500);
  });
})();

// CSRF token helper for fetch
function getCsrf() {
  const el = document.querySelector('meta[name="csrf-token"]') || document.querySelector('input[name="_csrf_token"]');
  return el ? (el.content || el.value) : '';
}

// Debounce (preserve this / event target for input handlers)
function debounce(fn, ms = 300) {
  let t;
  return function (...args) {
    const ctx = this;
    clearTimeout(t);
    t = setTimeout(() => fn.apply(ctx, args), ms);
  };
}

// Search with debounce
document.querySelectorAll('[data-search-url]').forEach(input => {
  const url = input.dataset.searchUrl;
  const target = document.querySelector(input.dataset.searchTarget);
  if (!target) return;
  input.addEventListener('input', debounce(async () => {
    const q = input.value.trim();
    if (!q) { target.innerHTML = ''; return; }
    try {
      const res = await fetch(`${url}?q=${encodeURIComponent(q)}`, {
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const ct = (res.headers.get('content-type') || '').toLowerCase();
      if (ct.includes('application/json')) {
        const data = await res.json();
        if (!res.ok) {
          target.innerHTML = `<div class="muted" style="padding:8px;color:#ef4444;">${(data && data.error) || 'Ошибка поиска'}</div>`;
          return;
        }
        const list = Array.isArray(data) ? data : [];
        if (!list.length) {
          target.innerHTML = '<div class="muted" style="padding:8px;">Ничего не найдено</div>';
          return;
        }
        target.innerHTML = list.map((item) => {
          const name = item.name || item.title || '';
          const sub = item.phone || item.sku || item.unit || '';
          return `<div class="search-hit" data-id="${item.id}"><strong>${name}</strong>${sub ? `<br><small class="muted">${sub}</small>` : ''}</div>`;
        }).join('');
        return;
      }
      const html = await res.text();
      target.innerHTML = html;
    } catch (e) { console.error(e); }
  }, 350));
});

// Confirm dialog
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', (e) => {
    if (!confirm(el.dataset.confirm)) e.preventDefault();
  });
});

// Sidebar mobile styles
const style = document.createElement('style');
style.textContent = `
@media (max-width: 767px) {
  .sidebar {
    display: flex !important;
    transform: translateX(-100%);
    transition: transform .25s ease;
    box-shadow: 4px 0 20px rgba(0,0,0,.12);
  }
  .sidebar.open {
    transform: translateX(0);
  }
}`;
document.head.appendChild(style);
