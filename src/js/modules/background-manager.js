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
    if (Array.isArray(res?.data?.backgrounds)) return res.data.backgrounds;
    if (Array.isArray(res?.backgrounds)) return res.backgrounds; // fallback if endpoint changes
    return [];
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
  const resolveUrl = (bg) => {
    const pick = bg.webp_filename || bg.png_filename || bg.image_filename || '';
    if (!pick) return '';
    const val = String(pick).trim();
    if (!val) return '';
    if (/^https?:\/\//i.test(val)) return val;
    if (val.startsWith('/images/')) return val;
    if (val.startsWith('images/')) return '/' + val;
    if (val.startsWith('backgrounds/')) return '/images/' + val;
    return '/images/backgrounds/' + val;
  };
  items.forEach((bg) => {
    const isActive = String(bg.is_active) === '1';
    const card = h('div', { class: 'wf-bg-card' }, [
      h('img', { src: resolveUrl(bg), alt: bg.background_name || 'Background', class: 'wf-bg-thumb' }),
      h('div', { class: 'wf-bg-name' }, [
        h('span', {}, bg.background_name || String(bg.id || 'Background')),
        isActive ? h('span', { class: 'wf-bg-active-chip', title: 'Active' }, 'Active') : null,
      ]),
      h('div', { class: 'wf-bg-actions' }, [
        h('button', { class: 'btn btn-primary', 'data-bg-id': String(bg.id || ''), 'data-room': String(roomNumber) }, isActive ? 'Reapply' : 'Set Active'),
        h('button', { class: 'btn btn-secondary', 'data-action': 'rename-bg', 'data-id': String(bg.id || ''), 'data-name': String(bg.background_name || '') }, 'Rename'),
        h('button', { class: 'btn btn-danger', 'data-action': 'delete-bg', 'data-id': String(bg.id || '') }, 'Delete')
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
  .wf-bg-room-select{display:flex;align-items:center;gap:8px;margin-bottom:10px}
  .wf-bg-room-select select{border:1px solid #e5e7eb;background:#fff;border-radius:8px;padding:6px 10px;min-width:220px}
  `;
  const style = document.createElement('style');
  style.id = 'wf-bg-manager-style';
  style.textContent = css;
  document.head.appendChild(style);
}

// Load list of rooms from DB (primary assignments)
async function fetchRoomsFromDB() {
  try {
    const rooms = await ApiClient.get('/api/get_rooms.php');
    if (Array.isArray(rooms)) return rooms;
    return [];
  } catch (_) { return []; }
}

function displayRoomLabel(roomId, roomName) {
  if (String(roomId).toUpperCase() === 'A') {
    return roomName ? `${roomName}` : 'Landing';
  }
  if (String(roomId) === '0') {
    return roomName ? `${roomName}` : 'Main';
  }
  if (String(roomId).toUpperCase() === 'S') {
    return roomName ? `${roomName}` : 'Shop';
  }
  if (String(roomId).toUpperCase() === 'X') {
    return roomName ? `${roomName}` : 'Settings';
  }
  // Fallback
  return roomName ? `${roomName}` : `Room ${roomId}`;
}

function normalizeApiRoomValue(roomId) {
  return String(roomId).toUpperCase();
}

export function init(modalEl) {
  try { injectStylesOnce(); } catch(_) {}
  const container = modalEl?.querySelector('.modal-body') || modalEl;
  if (!container) return;

  // UI scaffolding
  const title = h('div', { class: 'text-gray-700 mb-2' }, 'Manage backgrounds per room. Select a room to view and apply backgrounds.');
  const selectorWrap = h('div', { class: 'wf-bg-room-select' });
  const selectorLabel = h('label', { for: 'wfBgRoomSelect' }, 'Room:');
  const selector = h('select', { id: 'wfBgRoomSelect' });
  const listWrap = h('div', { class: 'wf-bg-list' }, 'Loading backgrounds…');
  container.innerHTML = '';
  container.appendChild(title);
  selectorWrap.appendChild(selectorLabel);
  selectorWrap.appendChild(selector);
  container.appendChild(selectorWrap);
  const uploadForm = h('form', { id: 'wfBgUploadForm', class: 'wf-bg-upload' }, [
    h('input', { type: 'text', id: 'wfBgUploadName', placeholder: 'Background name', class: 'input' }),
    h('input', { type: 'file', id: 'wfBgUploadFile', accept: 'image/*', class: 'input' }),
    h('button', { type: 'submit', class: 'btn btn-primary' }, 'Upload')
  ]);
  container.appendChild(uploadForm);
  container.appendChild(listWrap);

  let currentRoom = '0';
  let dbRooms = [];

  async function loadRoom(rn) {
    currentRoom = String(rn);
    listWrap.textContent = 'Loading backgrounds…';
    const apiRoom = normalizeApiRoomValue(rn);
    const items = await fetchRoomBackgrounds(apiRoom);
    renderList(listWrap, items, apiRoom);
  }

  selector.addEventListener('change', (ev) => {
    const val = ev.target && ev.target.value;
    if (val == null) return;
    loadRoom(val);
  });
  container.addEventListener('click', async (ev) => {
    const renameBtn = ev.target && ev.target.closest('button[data-action="rename-bg"]');
    if (renameBtn) {
      const id = renameBtn.getAttribute('data-id');
      const currentName = renameBtn.getAttribute('data-name') || '';
      const name = window.prompt('Rename background', currentName);
      if (name && name.trim()) {
        try {
          const res = await ApiClient.post('/api/backgrounds.php', { action: 'rename', id, background_name: name.trim() });
          if (res && res.success) {
            if (typeof window.showNotification === 'function') window.showNotification({ type: 'success', title: 'Renamed', message: 'Background renamed.' });
            loadRoom(currentRoom);
          }
        } catch (_) {
          if (typeof window.showNotification === 'function') window.showNotification({ type: 'error', title: 'Rename Failed', message: 'Unable to rename background.' });
        }
      }
      return;
    }
    const deleteBtn = ev.target && ev.target.closest('button[data-action="delete-bg"]');
    if (deleteBtn) {
      const id = deleteBtn.getAttribute('data-id');
      const okConfirm = window.confirm('Delete this background?');
      if (!okConfirm) return;
      try {
        const res = await ApiClient.delete('/api/backgrounds.php', { body: JSON.stringify({ background_id: id }) });
        if (res && res.success) {
          if (typeof window.showNotification === 'function') window.showNotification({ type: 'success', title: 'Deleted', message: 'Background deleted.' });
          loadRoom(currentRoom);
        }
      } catch (_) {
        if (typeof window.showNotification === 'function') window.showNotification({ type: 'error', title: 'Delete Failed', message: 'Unable to delete background.' });
      }
      return;
    }
    const btn = ev.target && ev.target.closest('button[data-bg-id]');
    if (!btn) return;
    const id = btn.getAttribute('data-bg-id');
    const roomRaw = btn.getAttribute('data-room') || String(currentRoom);
    const room = normalizeApiRoomValue(roomRaw);
    const orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Applying…';
    const ok = await applyBackground(room, id);
    btn.disabled = false; btn.textContent = orig;
    if (ok) {
      if (typeof window.showNotification === 'function') {
        window.showNotification({ type: 'success', title: 'Background Updated', message: `Applied to room ${room}.` });
      }
      loadRoom(room);
    } else {
      if (typeof window.showNotification === 'function') {
        window.showNotification({ type: 'error', title: 'Update Failed', message: 'Unable to set background.' });
      }
    }
  });

  uploadForm.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const fileEl = uploadForm.querySelector('#wfBgUploadFile');
    const nameEl = uploadForm.querySelector('#wfBgUploadName');
    const file = fileEl && fileEl.files && fileEl.files[0];
    if (!file) return;
    const btn = uploadForm.querySelector('button[type="submit"]');
    const orig = btn.textContent;
    btn.disabled = true; btn.textContent = 'Uploading…';
    try {
      const fd = new FormData();
      fd.append('room', normalizeApiRoomValue(currentRoom));
      fd.append('background_image', file);
      if (nameEl && nameEl.value) fd.append('background_name', nameEl.value);
      const res = await ApiClient.upload('/api/upload_background.php', fd);
      if (res && res.success) {
        if (typeof window.showNotification === 'function') window.showNotification({ type: 'success', title: 'Uploaded', message: 'Background uploaded.' });
        if (fileEl) fileEl.value = '';
        if (nameEl) nameEl.value = '';
        loadRoom(currentRoom);
      }
    } catch (_) {
      if (typeof window.showNotification === 'function') window.showNotification({ type: 'error', title: 'Upload Failed', message: 'Unable to upload background.' });
    } finally {
      btn.disabled = false; btn.textContent = orig;
    }
  });

  
  (async () => {
    dbRooms = await fetchRoomsFromDB();
    const presentA = dbRooms.some(r => String(r.id).toUpperCase() === 'A');
    const present0 = dbRooms.some(r => String(r.id) === '0');
    const presentS = dbRooms.some(r => String(r.id).toUpperCase() === 'S');
    const presentX = dbRooms.some(r => String(r.id).toUpperCase() === 'X');
    const options = [];
    if (!presentA) options.push({ id: 'A', name: 'Landing' });
    if (!present0) options.push({ id: '0', name: 'Main' });
    if (!presentS) options.push({ id: 'S', name: 'Shop' });
    if (!presentX) options.push({ id: 'X', name: 'Settings' });
    const sorted = dbRooms.slice().sort((a, b) => {
      const ai = String(a.id).toUpperCase();
      const bi = String(b.id).toUpperCase();
      const an = /^\d+$/.test(ai) ? parseInt(ai, 10) : Number.POSITIVE_INFINITY;
      const bn = /^\d+$/.test(bi) ? parseInt(bi, 10) : Number.POSITIVE_INFINITY;
      if (an !== bn) return an - bn;
      return ai.localeCompare(bi);
    });
    const finalRooms = [...options, ...sorted];
    selector.innerHTML = '';
    finalRooms.forEach(r => {
      const opt = document.createElement('option');
      opt.value = String(r.id);
      opt.textContent = displayRoomLabel(r.id, r.name);
      selector.appendChild(opt);
    });
    let initial = '0';
    const has0 = finalRooms.some(r => String(r.id) === '0');
    const hasA = finalRooms.some(r => String(r.id).toUpperCase() === 'A');
    if (!has0) initial = hasA ? 'A' : (finalRooms[0] ? String(finalRooms[0].id) : '0');
    selector.value = initial;
    loadRoom(initial);
  })();
}

export default { init };
