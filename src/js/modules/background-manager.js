// Background Manager module (Vite-managed)
// Manage room backgrounds (rooms 0..5): list, preview, and apply active per room.

import { ApiClient } from '../../core/api-client.js';

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

async function fetchRoomBackgrounds(roomNumber) {
  try {
    const res = await ApiClient.get('/api/backgrounds.php', { room: String(roomNumber) });
    // Expect { success, backgrounds: [ { id, background_name, image_filename, webp_filename, is_active } ] }
    return Array.isArray(res?.backgrounds) ? res.backgrounds : [];
  } catch (_) {
    return [];
  }
}

async function applyBackground(roomNumber, backgroundId) {
  try {
    const data = await ApiClient.post('/api/backgrounds.php', { action: 'apply', room: String(roomNumber), background_id: String(backgroundId) });
    return !!(data && data.success);
  } catch (_) {
    return false;
  }
}

function renderList(container, items, roomNumber) {
  container.innerHTML = '';
  if (!items.length) {
    container.appendChild(h('div', { class: 'text-gray-600' }, 'No backgrounds found.' ));
    return;
  }
  const grid = h('div', { class: 'wf-bg-grid' });
  items.forEach((bg) => {
    const isActive = String(bg.is_active) === '1';
    const card = h('div', { class: 'wf-bg-card' }, [
      h('img', { src: bg.webp_filename ? ('/images/' + bg.webp_filename) : ('/images/' + bg.image_filename), alt: bg.background_name || 'Background', class: 'wf-bg-thumb' }),
      h('div', { class: 'wf-bg-name' }, [
        h('span', {}, bg.background_name || String(bg.id || 'Background')),
        isActive ? h('span', { class: 'wf-bg-active-chip', title: 'Active' }, 'Active') : null,
      ]),
      h('div', { class: 'wf-bg-actions' }, [
        h('button', { class: 'btn btn-primary', 'data-bg-id': String(bg.id || ''), 'data-room': String(roomNumber) }, isActive ? 'Reapply' : 'Set Active'),
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
  .wf-bg-active-chip{margin-left:8px;font-size:12px;color:#065f46;background:#d1fae5;border:1px solid #10b981;border-radius:10px;padding:2px 6px}
  .wf-bg-room-tabs{display:flex;gap:6px;margin-bottom:10px}
  .wf-bg-room-tabs button{border:1px solid #e5e7eb;background:#f9fafb;border-radius:8px;padding:6px 10px}
  .wf-bg-room-tabs button[aria-pressed="true"]{background:#e0f2fe;border-color:#38bdf8}
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

  // UI scaffolding
  const title = h('div', { class: 'text-gray-700 mb-2' }, 'Manage backgrounds per room. Select a room to view and apply backgrounds.');
  const tabs = h('div', { class: 'wf-bg-room-tabs', role: 'tablist' });
  const listWrap = h('div', { class: 'wf-bg-list' }, 'Loading backgrounds…');
  container.innerHTML = '';
  container.appendChild(title);
  container.appendChild(tabs);
  container.appendChild(listWrap);

  let currentRoom = 0; // 0..5
  const rooms = [0,1,2,3,4,5];

  function renderTabs() {
    tabs.innerHTML = '';
    rooms.forEach((rn) => {
      const btn = h('button', { type: 'button', 'data-room': String(rn), 'aria-pressed': String(rn === currentRoom) }, rn === 0 ? 'Landing (0)' : `Room ${rn}`);
      tabs.appendChild(btn);
    });
  }

  async function loadRoom(rn) {
    currentRoom = rn;
    renderTabs();
    listWrap.textContent = 'Loading backgrounds…';
    const items = await fetchRoomBackgrounds(rn);
    renderList(listWrap, items, rn);
  }

  tabs.addEventListener('click', (ev) => {
    const b = ev.target && ev.target.closest('button[data-room]');
    if (!b) return;
    const rn = parseInt(b.getAttribute('data-room'), 10);
    if (!isNaN(rn)) loadRoom(rn);
  });

  container.addEventListener('click', async (ev) => {
    const btn = ev.target && ev.target.closest('button[data-bg-id]');
    if (!btn) return;
    const id = btn.getAttribute('data-bg-id');
    const room = parseInt(btn.getAttribute('data-room') || String(currentRoom), 10);
    const orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Applying…';
    const ok = await applyBackground(room, id);
    btn.disabled = false; btn.textContent = orig;
    if (ok) {
      if (typeof window.showNotification === 'function') {
        window.showNotification({ type: 'success', title: 'Background Updated', message: `Applied to room ${room}.` });
      }
      // Refresh list to reflect active state
      loadRoom(room);
    } else {
      if (typeof window.showNotification === 'function') {
        window.showNotification({ type: 'error', title: 'Update Failed', message: 'Unable to set background.' });
      }
    }
  });

  // Initial load
  loadRoom(currentRoom);
}

export default { init };
