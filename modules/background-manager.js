// Background Manager module (Vite-managed)
// Provides a lightweight UI to preview and set global backgrounds.
// This is an initial implementation that can be expanded.

function h(tag, attrs = {}, children = []) {
  const el = document.createElement(tag);
  Object.entries(attrs || {}).forEach(([k, v]) => {
    if (k === 'class') el.className = v;
    else if (k === 'style' && typeof v === 'object') Object.assign(el.style, v);
    else el.setAttribute(k, v);
  });
  (Array.isArray(children) ? children : [children]).forEach((c) => {
    if (c == null) return;
    if (typeof c === 'string') el.appendChild(document.createTextNode(c));
    else el.appendChild(c);
  });
  return el;
}

async function fetchBackgrounds() {
  try {
    const res = await fetch('/api/backgrounds/list.php');
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const data = await res.json().catch(() => ({}));
    // Expect {success, items:[{id, name, url, previewUrl}]}
    return data?.items || [];
  } catch (_) {
    // Fallback: show a couple of known assets from /images/backgrounds
    return [
      { id: 'background-home', name: 'Home', url: '/images/backgrounds/background-home.webp', previewUrl: '/images/backgrounds/background-home.webp' },
    ];
  }
}

async function setActiveBackground(id) {
  try {
    const res = await fetch('/api/backgrounds/set_active.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id })
    });
    const data = await res.json().catch(() => ({}));
    return !!(data && data.success);
  } catch (_) { return false; }
}

function renderList(container, items) {
  container.innerHTML = '';
  if (!items.length) {
    container.appendChild(h('div', { class: 'text-gray-600' }, 'No backgrounds found.'));
    return;
  }
  const grid = h('div', { class: 'wf-bg-grid' });
  items.forEach((bg) => {
    const card = h('div', { class: 'wf-bg-card' }, [
      h('img', { src: bg.previewUrl || bg.url, alt: bg.name || 'Background', class: 'wf-bg-thumb' }),
      h('div', { class: 'wf-bg-name' }, bg.name || String(bg.id || 'Background')),
      h('div', { class: 'wf-bg-actions' }, [
        h('button', { class: 'btn btn-primary', 'data-bg-id': String(bg.id || '') }, 'Set As Active'),
      ]),
    ]);
    grid.appendChild(card);
  });
  container.appendChild(grid);
}

function injectStylesOnce() {
  if (document.getElementById('wf-bg-manager-style')) return;
  const css = `
  .wf-bg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px}
  .wf-bg-card{border:1px solid #e5e7eb;border-radius:10px;padding:10px;background:#fff;display:flex;flex-direction:column;gap:8px}
  .wf-bg-thumb{width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb}
  .wf-bg-name{font-weight:600;color:#374151}
  .wf-bg-actions{display:flex;gap:8px}
  `;
  const style = document.createElement('style');
  style.id = 'wf-bg-manager-style';
  style.textContent = css;
  document.head.appendChild(style);
}

export function init(modalEl) {
  try { injectStylesOnce(); } catch(_) {}
  const container = modalEl?.querySelector('.modal-body') || modalEl;
  if (!container) return;

  const header = h('div', { class: 'text-gray-700 mb-2' }, 'Select a background to apply site-wide.');
  const listWrap = h('div', { class: 'wf-bg-list' }, 'Loading backgrounds…');
  container.innerHTML = '';
  container.appendChild(header);
  container.appendChild(listWrap);

  fetchBackgrounds().then((items) => {
    renderList(listWrap, items);
  });

  container.addEventListener('click', async (ev) => {
    const btn = ev.target && ev.target.closest('button[data-bg-id]');
    if (!btn) return;
    const id = btn.getAttribute('data-bg-id');
    const orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Applying…';
    const ok = await setActiveBackground(id);
    btn.disabled = false; btn.textContent = orig;
    if (ok) {
      if (typeof window.showNotification === 'function') {
        window.showNotification({ type: 'success', title: 'Background Updated', message: 'New background set.' });
      }
    } else {
      if (typeof window.showNotification === 'function') {
        window.showNotification({ type: 'error', title: 'Update Failed', message: 'Unable to set background.' });
      }
    }
  });
}

export default { init };
