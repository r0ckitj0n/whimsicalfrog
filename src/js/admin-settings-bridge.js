// Admin Settings Bridge
// Lightweight initializer to migrate inline scripts to Vite-managed code
// - Loads Email settings (prefers BusinessSettingsAPI; falls back to legacy endpoint)
// - Wires basic UI behaviors (toggle SMTP section)
//
// Important: avoid top-level imports that may trigger Vite transform errors during dev.
// We use dynamic imports within functions so a single problematic module doesn't block
// the entire Settings page from wiring up its UI.

function byId(id){ return document.getElementById(id); }

// ------------------------------
// Health & Diagnostics
// ------------------------------
async function fetchBackgroundHealth(){
  const res = await fetch('/api/health_backgrounds.php', { credentials: 'include', headers: { 'X-Requested-With':'XMLHttpRequest' } });
  if (!res.ok) throw new Error('Backgrounds health request failed');
  const j = await res.json().catch(() => null); if (!j || j.success !== true) throw new Error(j?.error || 'Unexpected backgrounds response');
  return j.data || {};
}
async function fetchItemsHealth(){
  const res = await fetch('/api/health_items.php', { credentials: 'include', headers: { 'X-Requested-With':'XMLHttpRequest' } });
  if (!res.ok) throw new Error('Items health request failed');
  const j = await res.json().catch(() => null); if (!j || j.success !== true) throw new Error(j?.error || 'Unexpected items response');
  return j.data || {};
}
function renderHealth(bg, items){
  try {
    const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = String(v ?? '0'); };
    const fillList = (id, arr, renderFn) => {
      const ul = document.getElementById(id); if (!ul) return; ul.innerHTML='';
      (arr || []).forEach((row) => { const li = document.createElement('li'); li.innerHTML = renderFn(row); ul.appendChild(li); });
    };
    const missingActive = Array.isArray(bg?.missingActive) ? bg.missingActive : [];
    const missingFiles = Array.isArray(bg?.missingFiles) ? bg.missingFiles : [];
    setText('bgMissingActiveCount', missingActive.length);
    setText('bgMissingFilesCount', missingFiles.length);
    const linkBgMgr = (room) => `<a class="text-blue-600 hover:underline" href="/admin/dashboard?wf_hint=background&room=${encodeURIComponent(String(room))}#background" target="_blank" rel="noopener">Background Manager</a>`;
    const linkRoomCfg = (room) => `<a class="text-blue-600 hover:underline" href="/admin/?section=room-config-manager&room=${encodeURIComponent(String(room))}" target="_blank" rel="noopener">Room Settings</a>`;
    const fixBtn = (room) => `<button type="button" class="btn btn-secondary ml-2" data-action="bg-fix-room" data-room="${String(room)}" title="Open Background Manager">Fix</button>`;
    fillList('bgMissingActiveList', missingActive, (room) => `Room <code>${String(room)}</code> · ${linkBgMgr(room)} · ${linkRoomCfg(room)} ${fixBtn(room)}`);
    fillList('bgMissingFilesList', missingFiles, (room) => `Room <code>${String(room)}</code> · ${linkBgMgr(room)} · ${linkRoomCfg(room)} ${fixBtn(room)}`);

    const noPrimary = Array.isArray(items?.noPrimary) ? items.noPrimary : [];
    const missItemFiles = Array.isArray(items?.missingFiles) ? items.missingFiles : [];
    setText('itemsNoPrimaryCount', noPrimary.length);
    setText('itemsMissingFilesCount', missItemFiles.length);
    const esc = (s) => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const itemLinks = (sku) => `
      <a class="text-blue-600 hover:underline" href="/admin/inventory?view=${encodeURIComponent(String(sku||''))}" target="_blank" rel="noopener">View</a>
      <span class="text-gray-400">|</span>
      <a class="text-blue-600 hover:underline" href="/admin/inventory?edit=${encodeURIComponent(String(sku||''))}" target="_blank" rel="noopener">Edit</a>
      <button type="button" class="btn btn-secondary ml-2" data-action="item-fix" data-sku="${esc(sku||'')}">Fix</button>`;
    fillList('itemsNoPrimaryList', noPrimary, (r) => `${esc(r.sku||'')} — ${esc(r.name||'')} <span class="ml-2">${itemLinks(r.sku)}</span>`);
    fillList('itemsMissingFilesList', missItemFiles, (r) => `${esc(r.sku||'')} — ${esc(r.name||'')} <span class="text-gray-500">(${esc(r.image_path||'')})</span> <span class="ml-2">${itemLinks(r.sku)}</span>`);
  } catch (e) { /* noop */ }
}
async function loadHealthIntoModal(){
  const status = document.getElementById('healthStatus'); if (status) status.textContent = 'Loading…';
  try {
    const [bg, items] = await Promise.all([fetchBackgroundHealth(), fetchItemsHealth()]);
    renderHealth(bg, items);
    if (status) status.textContent = 'Up to date';
  } catch (e) {
    if (status) status.textContent = e?.message || 'Failed to load health data';
  }
  if (fixItemBtn) {
    e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
    const sku = fixItemBtn.getAttribute('data-sku') || '';
    const url = `/admin/inventory?edit=${encodeURIComponent(sku)}`;
    try { window.open(url, '_blank', 'noopener'); } catch(_) { window.location.href = url; }
    return;
  }
}

// Delegated handlers for health modal
document.addEventListener('click', async (e) => {
  const t = e.target;
  const openBtn = t && t.closest ? t.closest('[data-action="open-health-diagnostics"]') : null;
  const refreshBtn = t && t.closest ? t.closest('[data-action="health-refresh"]') : null;
  const fixRoomBtn = t && t.closest ? t.closest('[data-action="bg-fix-room"]') : null;
  const fixItemBtn = t && t.closest ? t.closest('[data-action="item-fix"]') : null;
  if (openBtn) {
    e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
    try { if (typeof __wfShowModal === 'function') __wfShowModal('healthModal'); else { const el = document.getElementById('healthModal'); if (el) { el.classList.remove('hidden'); el.classList.add('show'); el.setAttribute('aria-hidden','false'); } } } catch(_) {}
    await loadHealthIntoModal();
    return;
  }
  if (refreshBtn) {
    e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
    await loadHealthIntoModal();
    return;
  }
  if (fixRoomBtn) {
    e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
    const room = fixRoomBtn.getAttribute('data-room') || '0';
    const url = `/admin/dashboard?wf_hint=background&room=${encodeURIComponent(room)}#background`;
    try { window.open(url, '_blank', 'noopener'); } catch(_) { window.location.href = url; }
    return;
  }
});

// ------------------------------
// Help & Hints controls (Settings)
// ------------------------------
document.addEventListener('click', (e) => {
  const t = e.target;
  const enableSess = t && t.closest ? t.closest('[data-action="hints-enable-session"]') : null;
  const enablePersist = t && t.closest ? t.closest('[data-action="hints-enable-persist"]') : null;
  const disableTips = t && t.closest ? t.closest('[data-action="hints-disable"]') : null;
  const restoreSess = t && t.closest ? t.closest('[data-action="hints-restore-banners-session"]') : null;
  const restorePersist = t && t.closest ? t.closest('[data-action="hints-restore-banners-persist"]') : null;

  if (enableSess) {
    e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
    try { if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('wf_tooltips_session_enabled', 'true'); } catch(_) {}
    try { if (typeof window.showNotification === 'function') window.showNotification('Tooltips enabled for this session.', 'success', { title: 'Help & Hints' }); } catch(_) {}
    return;
  }
  if (enablePersist) {
    e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
    try { if (typeof localStorage !== 'undefined') localStorage.setItem('wf_tooltips_enabled', 'true'); } catch(_) {}
    // Optionally also set session to avoid refresh requirement
    try { if (typeof sessionStorage !== 'undefined') sessionStorage.setItem('wf_tooltips_session_enabled', 'true'); } catch(_) {}
    try { if (typeof window.showNotification === 'function') window.showNotification('Tooltips enabled (persistent).', 'success', { title: 'Help & Hints' }); } catch(_) {}
    return;
  }
  if (disableTips) {
    e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
    try { if (typeof localStorage !== 'undefined') localStorage.setItem('wf_tooltips_enabled', 'false'); } catch(_) {}
    try { if (typeof sessionStorage !== 'undefined') sessionStorage.removeItem('wf_tooltips_session_enabled'); } catch(_) {}
    try { if (typeof document !== 'undefined') document.querySelectorAll('.wf-tooltip').forEach((el) => el.remove()); } catch(_) {}
    try { if (typeof window.showNotification === 'function') window.showNotification('Tooltips disabled.', 'info', { title: 'Help & Hints' }); } catch(_) {}
    return;
  }
  if (restoreSess || restorePersist) {
    e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
    const clearPrefix = (store) => {
      try { Object.keys(store).forEach((k) => { if (k && k.startsWith('wf_bg_hint_dismissed')) { store.removeItem(k); } }); } catch(_) {}
    };
    if (restorePersist) {
      try { if (typeof localStorage !== 'undefined') clearPrefix(localStorage); } catch(_) {}
      try { if (typeof sessionStorage !== 'undefined') clearPrefix(sessionStorage); } catch(_) {}
    } else {
      try { if (typeof sessionStorage !== 'undefined') clearPrefix(sessionStorage); } catch(_) {}
    }
    try { if (typeof window.showNotification === 'function') window.showNotification('Dismissed banners restored' + (restorePersist ? ' (persistent)' : ' (this session)'), 'success', { title: 'Help & Hints' }); } catch(_) {}
    return;
  }
});

// Bind attribute handlers once at module load
try { wireAttributeHandlers(); } catch(_) {}

// ------------------------------
// Categories helpers
// ------------------------------
async function catApi(path, payload) {
  const url = typeof path === 'string' ? path : '/api/categories.php?action=list';
  const opts = payload
    ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload), credentials: 'include' }
    : { method: 'GET', headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'include' };
  const res = await fetch(url, opts);
  const status = res.status; const text = await res.text();
  if (status < 200 || status >= 300) throw new Error(`HTTP ${status}: ${text.slice(0,200)}`);
  const data = text ? JSON.parse(text) : {};
  if (!data || data.success !== true) throw new Error((data && data.error) || 'Unexpected response');
  return data;
}
async function populateCategories() {
  const modal = document.getElementById('categoriesModal'); if (!modal) return;
  const tbody = modal.querySelector('#catTableBody'); const result = modal.querySelector('#catResult');
  if (result) result.textContent = 'Loading…';
  try {
    const data = await catApi('/api/categories.php?action=list');
    const cats = (data && data.data && Array.isArray(data.data.categories)) ? data.data.categories : [];
    if (tbody) tbody.innerHTML = '';
    cats.forEach((c) => {
      const tr = document.createElement('tr');
      const name = String(c.name || '').replace(/"/g, '&quot;');
      tr.innerHTML = `
        <td class="p-2"><span class="cat-name" data-name="${name}">${name}</span></td>
        <td class="p-2 text-gray-600">${(c.item_count != null ? c.item_count : 0)}</td>
        <td class="p-2">
          <button class="btn btn-secondary" data-action="cat-rename" data-name="${name}">Rename</button>
          <button class="btn btn-secondary text-red-700" data-action="cat-delete" data-name="${name}">Delete</button>
        </td>`;
      if (tbody) tbody.appendChild(tr);
    });
    if (result) result.textContent = cats.length ? '' : 'No categories found yet.';
  } catch (e) {
    if (result) result.textContent = e?.message || 'Failed to load categories';
  }
}

// ------------------------------
// Attributes helpers
// ------------------------------
async function attrApi(path, payload) {
  const url = typeof path === 'string' ? path : '/api/attributes.php?action=list';
  const opts = payload
    ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With':'XMLHttpRequest' }, body: JSON.stringify(payload), credentials: 'include' }
    : { method: 'GET', headers: { 'X-Requested-With':'XMLHttpRequest' }, credentials: 'include' };
  const res = await fetch(url, opts);
  const status = res.status; const text = await res.text();
  if (status < 200 || status >= 300) throw new Error(`HTTP ${status}: ${text.slice(0,200)}`);
  const data = text ? JSON.parse(text) : {};
  if (!data || data.success !== true) throw new Error((data && data.error) || 'Unexpected response');
  return data;
}
function renderAttrList(ul, arr, type){
  if (!ul) return; ul.innerHTML = '';
  const values = Array.isArray(arr) ? arr : [];
  const colEl = ul && ul.closest ? ul.closest('.attr-col') : null;
  const header = colEl ? colEl.querySelector('h3') : null;
  const setCount = (n) => { if (header) header.textContent = `${header.textContent.replace(/\s*\(.*\)$/, '')} (${n})`; };
  if (values.length === 0) {
    const li = document.createElement('li');
    li.className = 'text-sm text-gray-500 italic px-2 py-1';
    li.textContent = 'No values yet';
    ul.appendChild(li);
    setCount(0);
    return;
  }
  values.forEach(({value}) => {
    const li = document.createElement('li');
    li.className = 'flex items-center justify-between gap-2 px-2 py-1 border border-gray-200 rounded bg-white';
    li.setAttribute('draggable','true');
    const label = document.createElement('span'); label.className = 'text-sm'; label.textContent = value; label.setAttribute('data-value', value);
    const right = document.createElement('span'); right.className = 'flex items-center gap-2';
    const renameBtn = document.createElement('button'); renameBtn.type='button'; renameBtn.className='btn btn-secondary'; renameBtn.textContent='Rename'; renameBtn.setAttribute('data-action','attr-rename'); renameBtn.setAttribute('data-type', type); renameBtn.setAttribute('data-value', value);
    const delBtn = document.createElement('button'); delBtn.type='button'; delBtn.className='btn btn-secondary text-red-700'; delBtn.textContent='Delete'; delBtn.setAttribute('data-action','attr-delete'); delBtn.setAttribute('data-type', type); delBtn.setAttribute('data-value', value);
    right.appendChild(renameBtn); right.appendChild(delBtn);
    li.appendChild(label); li.appendChild(right); ul.appendChild(li);
  });
  setCount(values.length);
}
async function populateAttributes(){
  const modal = document.getElementById('attributesModal'); if (!modal) return;
  const res = modal.querySelector('#attributesResult'); if (res) res.textContent='Loading…';
  try{
    const data = await attrApi('/api/attributes.php?action=list');
    const a = (data && data.data && data.data.attributes) ? data.data.attributes : { gender:[], size:[], color:[] };
    renderAttrList(document.getElementById('attrListGender'), a.gender, 'gender');
    renderAttrList(document.getElementById('attrListSize'), a.size, 'size');
    renderAttrList(document.getElementById('attrListColor'), a.color, 'color');
    if (res) res.textContent='';
    // enable drag-and-drop ordering after render
    try { enableAttrDnD(); } catch(_) {}
  } catch(e){ if (res) res.textContent = (e && e.message) ? e.message : 'Failed to load attributes'; }
}

// ------------------------------
// Attributes wiring (delegated)
// ------------------------------
function listIdToType(id){ if (!id) return null; if (/Gender$/i.test(id)) return 'gender'; if (/Size$/i.test(id)) return 'size'; if (/Color$/i.test(id)) return 'color'; return null; }
function getListValues(ul){
  const vals = [];
  if (!ul) return vals;
  ul.querySelectorAll('span[data-value]').forEach((el) => { vals.push(el.getAttribute('data-value') || ''); });
  return vals;
}
function enableAttrDnD(){
  const uls = document.querySelectorAll('#attrListGender, #attrListSize, #attrListColor');
  uls.forEach((ul) => {
    if (!ul || ul.__wfDndBound) return; ul.__wfDndBound = true;
    let dragEl = null;
    ul.addEventListener('dragstart', (e) => {
      const li = e.target.closest('li'); if (!li) return;
      dragEl = li; li.classList.add('dragging');
      try { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', ''); } catch(_) {}
    });
    ul.addEventListener('dragend', () => { if (dragEl) dragEl.classList.remove('dragging'); dragEl = null; });
    ul.addEventListener('dragover', (e) => {
      e.preventDefault();
      const after = (() => {
        const items = [...ul.querySelectorAll('li:not(.dragging)')];
        return items.find((li) => {
          const rect = li.getBoundingClientRect();
          return e.clientY < rect.top + rect.height / 2;
        });
      })();
      const dragging = ul.querySelector('li.dragging');
      if (!dragging) return;
      if (after) ul.insertBefore(dragging, after); else ul.appendChild(dragging);
    });
  });
}

function wireAttributeHandlers(){
  if (window.__WF_ATTR_HANDLERS_BOUND) return; window.__WF_ATTR_HANDLERS_BOUND = true;
  // Submit handler for add-forms
  document.addEventListener('submit', async (e) => {
    const form = e.target && e.target.closest ? e.target.closest('form[data-action="attr-add-form"]') : null;
    if (!form) return;
    e.preventDefault(); e.stopPropagation();
    const type = (form.getAttribute('data-type') || '').toLowerCase();
    const input = form.querySelector('input[type="text"], .attr-input');
    const value = (input && input.value || '').trim();
    if (!type || !value) { showToast('validation', 'Value Required', 'Please enter a value.'); return; }
    try {
      await attrApi('/api/attributes.php?action=add', { type, value });
      if (input) input.value = '';
      await populateAttributes();
      showToast('success', 'Added', `Added ${type}: ${value}`);
    } catch (err) {
      showToast('error', 'Add failed', err?.message || 'Unable to add attribute');
    }
  }, true);

  // Clicks for rename/delete/save order
  document.addEventListener('click', async (e) => {
    const t = e.target;
    const renameBtn = t && t.closest ? t.closest('[data-action="attr-rename"]') : null;
    const delBtn = t && t.closest ? t.closest('[data-action="attr-delete"]') : null;
    const saveOrder = t && t.closest ? t.closest('[data-action="attr-save-order"]') : null;

    if (renameBtn) {
      e.preventDefault(); e.stopPropagation();
      const type = (renameBtn.getAttribute('data-type') || '').toLowerCase();
      const oldVal = renameBtn.getAttribute('data-value') || (renameBtn.closest('li')?.querySelector('span[data-value]')?.getAttribute('data-value')) || '';
      const next = window.prompt(`Rename ${type}`, oldVal);
      if (!next || next.trim() === oldVal) return;
      try { await attrApi('/api/attributes.php?action=rename', { type, old_value: oldVal, new_value: next.trim() }); await populateAttributes(); showToast('success', 'Renamed', `${oldVal} → ${next}`); } catch(err){ showToast('error','Rename failed', err?.message || ''); }
      return;
    }
    if (delBtn) {
      e.preventDefault(); e.stopPropagation();
      const type = (delBtn.getAttribute('data-type') || '').toLowerCase();
      const val = delBtn.getAttribute('data-value') || (delBtn.closest('li')?.querySelector('span[data-value]')?.getAttribute('data-value')) || '';
      if (!window.confirm(`Delete ${type}: ${val}?`)) return;
      try { await attrApi('/api/attributes.php?action=delete', { type, value: val }); await populateAttributes(); showToast('success', 'Deleted', `${val}`); } catch(err){ showToast('error','Delete failed', err?.message || ''); }
      return;
    }
    if (saveOrder) {
      e.preventDefault(); e.stopPropagation();
      const lists = [
        document.getElementById('attrListGender'),
        document.getElementById('attrListSize'),
        document.getElementById('attrListColor'),
      ];
      try {
        for (const ul of lists) {
          if (!ul) continue; const type = listIdToType(ul.id); if (!type) continue;
          const values = getListValues(ul);
          await attrApi('/api/attributes.php?action=reorder', { type, values });
        }
        showToast('success', 'Order Saved', 'Attribute order updated.');
      } catch (err) {
        showToast('error', 'Save order failed', err?.message || '');
      }
      return;
    }
  });
}
function setVal(id, v){ const el = byId(id); if (el) el.value = v ?? ''; }
function setChecked(id, v){ const el = byId(id); if (el) el.checked = !!v; }

function normalizeEmailConfigFromSettings(settings) {
  // Map DB settings object to the UI fields used by the page
  const s = settings || {};
  const bool = (v) => v === true || v === '1' || v === 1 || v === 'true' || v === 'on';
  const num = (v) => (v === undefined || v === null || v === '' ? '' : Number(v));
  return {
    fromEmail: s.from_email || s.fromEmail || '',
    fromName: s.from_name || s.fromName || '',
    adminEmail: s.admin_email || s.adminEmail || '',
    bccEmail: s.bcc_email || s.bccEmail || '',
    replyTo: s.reply_to || s.replyTo || '',
    testRecipient: s.test_recipient || s.testRecipient || '',
    smtpEnabled: bool(s.smtp_enabled ?? s.smtpEnabled),
    smtpHost: s.smtp_host || s.smtpHost || '',
    smtpPort: num(s.smtp_port ?? s.smtpPort),
    smtpUsername: s.smtp_username || s.smtpUsername || '',
    // smtpPassword never filled from API
    smtpEncryption: (s.smtp_encryption || s.smtpEncryption || '').toString().toLowerCase(),
    smtpAuth: bool(s.smtp_auth ?? s.smtpAuth),
    smtpTimeout: num(s.smtp_timeout ?? s.smtpTimeout),
    smtpDebug: bool(s.smtp_debug ?? s.smtpDebug),
  };
}

async function loadEmailConfig() {
  // Prefer BusinessSettings API category 'email'
  try {
    const mod = await import('../modules/business-settings-api.js');
    const BusinessSettingsAPI = mod?.default || mod?.BusinessSettingsAPI;
    if (BusinessSettingsAPI && typeof BusinessSettingsAPI.getByCategory === 'function') {
      const data = await BusinessSettingsAPI.getByCategory('email');
      // Handle shapes:
      //  - { success:true, settings:[{setting_key,setting_value,...}, ...] }
      //  - { success:true, settings:{ key:value, ... } }
      //  - direct map { key:value }
      let settings = {};
      const raw = data && (data.settings ?? data.data ?? data);
      if (Array.isArray(raw)) {
        const map = {};
        for (const row of raw) {
          if (row && row.setting_key !== undefined && row.setting_value !== undefined) {
            map[row.setting_key] = row.setting_value;
          }
        }
        settings = map;
      } else if (raw && typeof raw === 'object') {
        settings = raw;
      }
      // No data is non-fatal; return normalized empty map
      return normalizeEmailConfigFromSettings(settings || {});
    }
  } catch (e) {
    const msg = e && e.message ? e.message : String(e);
    console.info('[AdminSettingsBridge] BusinessSettingsAPI email fetch unavailable; using legacy endpoint. Reason:', msg);
  }
  // Fallback to legacy endpoint to preserve behavior (use direct fetch to avoid ApiClient import)
  try {
    const res = await fetch('/api/get_email_config.php', { credentials: 'include', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const legacy = await res.json().catch(() => ({}));
    if (legacy && legacy.success && legacy.config) return legacy.config;
    return legacy?.config || {};
  } catch (e) {
    console.error('[AdminSettingsBridge] Legacy email config fetch failed', e);
    return {};
  }
}

function applyEmailConfig(cfg){
  setVal('fromEmail', cfg.fromEmail);
  setVal('fromName', cfg.fromName);
  setVal('adminEmail', cfg.adminEmail);
  setVal('bccEmail', cfg.bccEmail);
  setVal('replyToEmail', cfg.replyTo);
  // prefer specific test input id if present
  if (document.getElementById('testRecipient')) setVal('testRecipient', cfg.testRecipient);
  setChecked('smtpEnabled', cfg.smtpEnabled);
  setVal('smtpHost', cfg.smtpHost);
  if (cfg.smtpPort !== undefined && cfg.smtpPort !== null && cfg.smtpPort !== '') setVal('smtpPort', String(cfg.smtpPort));
  setVal('smtpUsername', cfg.smtpUsername);
  setVal('smtpEncryption', cfg.smtpEncryption);
  setChecked('smtpAuth', cfg.smtpAuth);
  if (cfg.smtpTimeout !== undefined && cfg.smtpTimeout !== null && cfg.smtpTimeout !== '') setVal('smtpTimeout', String(cfg.smtpTimeout));
  setChecked('smtpDebug', cfg.smtpDebug);
  // Toggle SMTP settings visibility
  const en = byId('smtpEnabled'); const ss = byId('smtpSettings');
  if (ss && en) {
    if (en.checked) ss.classList.remove('hidden');
    else ss.classList.add('hidden');
  }
}

function wireToggles(){
  const en = byId('smtpEnabled'); const ss = byId('smtpSettings');
  if (en && ss) {
    en.addEventListener('change', () => {
      if (en.checked) ss.classList.remove('hidden');
      else ss.classList.add('hidden');
    });
  }
}

function wireTestEmail(defaults){
  const btn = document.querySelector('[data-action="email-send-test"]');
  const input = byId('testEmailAddress') || byId('testRecipient');
  if (!btn || !input) return;
  const isValidEmail = (v) => /.+@.+\..+/.test(v);
  btn.addEventListener('click', async () => {
    let to = (input.value || '').trim();
    if (!to && defaults && defaults.testRecipient) {
      to = String(defaults.testRecipient).trim();
      if (to) input.value = to;
    }
    if (!isValidEmail(to)) {
      showToast('error', 'Invalid Email', 'Enter a valid test email address.');
      input.focus();
      return;
    }
    btn.disabled = true;
    const orig = btn.textContent;
    btn.textContent = 'Sending…';
    try {
      const res = await fetch('/api/email_test.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ to })
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok && data && data.success) {
        showToast('success', 'Test Email Sent', `A test email was sent to ${to}.`);
      } else {
        const err = data && data.error ? data.error : `HTTP ${res.status}`;
        showToast('error', 'Test Email Failed', err);
      }
    } catch (e) {
      showToast('error', 'Network Error', e?.message || 'Unable to send test email.');
    } finally {
      btn.disabled = false;
      btn.textContent = orig;
    }
  });
}

function collectEmailSettings() {
  const get = (id) => (byId(id) ? byId(id).value.trim() : '');
  const getBool = (id) => !!(byId(id) && byId(id).checked);
  const getNum = (id) => {
    const v = get(id);
    if (v === '') return '';
    const n = Number(v);
    return Number.isFinite(n) ? n : '';
  };

  const payload = {
    from_email: get('fromEmail'),
    from_name: get('fromName'),
    admin_email: get('adminEmail'),
    bcc_email: get('bccEmail'),
    reply_to: get('replyToEmail'),
    test_recipient: get('testRecipient') || get('testEmailAddress'),
    smtp_enabled: getBool('smtpEnabled') ? 1 : 0,
    smtp_host: get('smtpHost'),
    smtp_port: getNum('smtpPort'),
    smtp_encryption: get('smtpEncryption'),
  };
  // Only include secrets if user provided new values
  const smtpUsername = get('smtpUsername');
  if (smtpUsername !== '') payload.smtp_username = smtpUsername;
  const smtpPassword = get('smtpPassword');
  if (smtpPassword !== '') payload.smtp_password = smtpPassword;
  // Optional advanced fields
  if (byId('smtpAuth')) payload.smtp_auth = getBool('smtpAuth') ? 1 : 0;
  const timeoutVal = getNum('smtpTimeout');
  if (timeoutVal !== '') payload.smtp_timeout = timeoutVal;
  if (byId('smtpDebug')) payload.smtp_debug = getBool('smtpDebug') ? 1 : 0;

  return payload;
}

function showToast(type, title, message) {
  if (typeof window.showNotification === 'function') {
    window.showNotification({ type, title, message });
  } else {
    const prefix = type === 'error' ? '[Error]' : type === 'success' ? '[Success]' : '[Info]';
    console.log(prefix, title || '', message || '');
    if (type === 'error') alert(`${title || 'Error'}\n${message || ''}`);
  }
}

function wireSaveHandler(){
  const form = byId('emailConfigForm');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    try {
      e.preventDefault();
    } catch(_) {}
    const settings = collectEmailSettings();
    // Basic validation
    if (!settings.from_email) {
      showToast('error', 'Missing From Email', 'Please enter a From Email address.');
      return;
    }
    if (settings.smtp_enabled) {
      if (!settings.smtp_host) {
        showToast('error', 'Missing SMTP Host', 'Please enter SMTP Host or disable SMTP.');
        return;
      }
      if (settings.smtp_port === '') {
        showToast('error', 'Missing SMTP Port', 'Please select an SMTP Port.');
        return;
      }
    }

    // ------------------------------
    // Business Info helpers
    // ------------------------------
    function collectBusinessInfo(){
      const get = (id) => { const el = document.getElementById(id); return el ? (el.value || '').trim() : ''; };
      return {
        business_name: get('bizName'),
        business_email: get('bizEmail'),
        business_phone: get('bizPhone'),
        business_hours: get('bizHours'),
        business_address: get('bizAddress'),
        business_address2: get('bizAddress2'),
        business_city: get('bizCity'),
        business_state: get('bizState'),
        business_postal: get('bizPostal'),
        business_country: get('bizCountry'),
        business_website: get('bizWebsite'),
        business_logo_url: get('bizLogoUrl'),
        business_tagline: get('bizTagline'),
        business_description: get('bizDescription'),
        business_support_email: get('bizSupportEmail'),
        business_support_phone: get('bizSupportPhone'),
        business_facebook: get('bizFacebook'),
        business_instagram: get('bizInstagram'),
        business_twitter: get('bizTwitter'),
        business_tiktok: get('bizTikTok'),
        business_youtube: get('bizYouTube'),
        business_linkedin: get('bizLinkedIn'),
        business_terms_url: get('bizTermsUrl'),
        business_privacy_url: get('bizPrivacyUrl'),
        business_tax_id: get('bizTaxId'),
        business_timezone: get('bizTimezone'),
        business_currency: get('bizCurrency'),
        business_locale: get('bizLocale'),
        // Branding
        business_brand_primary: get('brandPrimary'),
        business_brand_secondary: get('brandSecondary'),
        business_brand_accent: get('brandAccent'),
        business_brand_background: get('brandBackground'),
        business_brand_text: get('brandText'),
        // Footer
        business_footer_note: get('footerNote'),
        business_footer_html: get('footerHtml'),
        // Policies
        business_policy_return: get('returnPolicy'),
        business_policy_shipping: get('shippingPolicy'),
        business_policy_warranty: get('warrantyPolicy'),
        business_policy_url: get('policyUrl'),
        // Brand Fonts
        business_brand_font_primary: get('brandFontPrimary'),
        business_brand_font_secondary: get('brandFontSecondary'),
        // Custom CSS variables (raw text)
        business_css_vars: get('customCssVars'),
      };
    }
    function applyBusinessInfo(map){
      const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v ?? ''; };
      const s = map || {};
      set('bizName', s.business_name || s.name || '');
      set('bizEmail', s.business_email || s.email || '');
      set('bizPhone', s.business_phone || s.phone || '');
      set('bizHours', s.business_hours || s.hours || '');
      set('bizAddress', s.business_address || s.address || '');
      set('bizAddress2', s.business_address2 || '');
      set('bizCity', s.business_city || '');
      set('bizState', s.business_state || '');
      set('bizPostal', s.business_postal || '');
      set('bizCountry', s.business_country || '');
      set('bizWebsite', s.business_website || s.website || '');
      set('bizLogoUrl', s.business_logo_url || s.logo_url || '');
      set('bizTagline', s.business_tagline || s.tagline || '');
      set('bizDescription', s.business_description || s.description || '');
      set('bizSupportEmail', s.business_support_email || '');
      set('bizSupportPhone', s.business_support_phone || '');
      set('bizFacebook', s.business_facebook || '');
      set('bizInstagram', s.business_instagram || '');
      set('bizTwitter', s.business_twitter || '');
      set('bizTikTok', s.business_tiktok || '');
      set('bizYouTube', s.business_youtube || '');
      set('bizLinkedIn', s.business_linkedin || '');
      set('bizTermsUrl', s.business_terms_url || '');
      set('bizPrivacyUrl', s.business_privacy_url || '');
      set('bizTaxId', s.business_tax_id || '');
      set('bizTimezone', s.business_timezone || '');
      set('bizCurrency', s.business_currency || '');
      set('bizLocale', s.business_locale || '');
      // Branding
      set('brandPrimary', s.business_brand_primary || '#0ea5e9');
      set('brandSecondary', s.business_brand_secondary || '#6366f1');
      set('brandAccent', s.business_brand_accent || '#22c55e');
      set('brandBackground', s.business_brand_background || '#ffffff');
      set('brandText', s.business_brand_text || '#111827');
      // Footer
      set('footerNote', s.business_footer_note || '');
      set('footerHtml', s.business_footer_html || '');
      // Policies
      set('returnPolicy', s.business_policy_return || '');
      set('shippingPolicy', s.business_policy_shipping || '');
      set('warrantyPolicy', s.business_policy_warranty || '');
      set('policyUrl', s.business_policy_url || '');
      // Brand Fonts
      set('brandFontPrimary', s.business_brand_font_primary || '');
      set('brandFontSecondary', s.business_brand_font_secondary || '');
      // Custom CSS variables
      set('customCssVars', s.business_css_vars || '');
    }

    // Apply branding CSS variables live to :root
    function applyBusinessCssToRoot(map){
      try {
        const s = map || {};
        const root = document.documentElement;
        const setVar = (k, v) => { if (typeof v === 'string' && v !== '') root.style.setProperty(k, v); };
        // Colors
        setVar('--brand-primary', s.business_brand_primary || '');
        setVar('--brand-secondary', s.business_brand_secondary || '');
        setVar('--brand-accent', s.business_brand_accent || '');
        setVar('--brand-bg', s.business_brand_background || '');
        setVar('--brand-text', s.business_brand_text || '');
        // Fonts
        setVar('--brand-font-primary', s.business_brand_font_primary || '');
        setVar('--brand-font-secondary', s.business_brand_font_secondary || '');
        // Custom CSS vars (one per line, format: --key: value;)
        const raw = s.business_css_vars || '';
        if (raw) {
          raw.split(/\r?\n/).forEach((line) => {
            const t = String(line || '').trim();
            if (!t || t.startsWith('//') || t.startsWith('#')) return;
            const m = t.match(/^--[A-Za-z0-9_-]+\s*:\s*[^;]+;?$/);
            if (m) {
              const idx = t.indexOf(':');
              const key = t.slice(0, idx).trim();
              const val = t.slice(idx + 1).replace(/;\s*$/, '').trim();
              if (key && val) root.style.setProperty(key, val);
            }
          });
        }
      } catch (_) { /* noop */ }
    }
    
    // Live branding preview from inputs (without saving)
    function collectBrandingInputs(){
      const get = (id) => { const el = document.getElementById(id); return el ? (el.value || '').trim() : ''; };
      return {
        business_brand_primary: get('brandPrimary'),
        business_brand_secondary: get('brandSecondary'),
        business_brand_accent: get('brandAccent'),
        business_brand_background: get('brandBackground'),
        business_brand_text: get('brandText'),
        business_brand_font_primary: get('brandFontPrimary'),
        business_brand_font_secondary: get('brandFontSecondary'),
        business_css_vars: get('customCssVars'),
      };
    }
    function wireBrandingLivePreview(){
      const ids = ['brandPrimary','brandSecondary','brandAccent','brandBackground','brandText','brandFontPrimary','brandFontSecondary','customCssVars'];
      const onChange = () => { try { applyBusinessCssToRoot(collectBrandingInputs()); } catch(_) {} };
      ids.forEach((id) => { const el = document.getElementById(id); if (el && !el.__wfLiveBound) { el.addEventListener('input', onChange); el.addEventListener('change', onChange); el.__wfLiveBound = true; } });
      // Initial apply
      onChange();
    }
    function loadBusinessInfo(){
      try {
        return import('../modules/business-settings-api.js').then((mod) => {
          const API = mod?.default || mod?.BusinessSettingsAPI;
          if (!API || typeof API.getByCategory !== 'function') return;
          return API.getByCategory('business').then((data) => {
            const raw = data && (data.settings ?? data.data ?? data);
            let map = {};
            if (Array.isArray(raw)) {
              const m = {}; raw.forEach((row) => { if (row && row.setting_key != null) m[row.setting_key] = row.setting_value; }); map = m;
            } else if (raw && typeof raw === 'object') { map = raw; }
            applyBusinessInfo(map || {});
            applyBusinessCssToRoot(map || {});
          }).catch(()=>{});
        }).catch(()=>{});
      } catch(_) { /* no-op */ }
    }
    function saveBusinessInfo(){
      const status = document.getElementById('businessInfoStatus'); if (status) status.textContent = 'Saving…';
      const payload = collectBusinessInfo();
      // Basic validation
      const errs = [];
      const isHex = (s) => /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(s);
      const isUrl = (s) => /^(https?:)\/\//i.test(s);
      const isEmail = (s) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(s);
      // Colors
      ['business_brand_primary','business_brand_secondary','business_brand_accent','business_brand_background','business_brand_text'].forEach((k) => { const v = payload[k]; if (v && !isHex(v)) errs.push(`${k} must be a hex color like #1a2b3c`); });
      // URLs
      ['business_website','business_logo_url','business_terms_url','business_privacy_url','business_policy_url','business_facebook','business_instagram','business_twitter','business_tiktok','business_youtube','business_linkedin'].forEach((k) => { const v = payload[k]; if (v && !isUrl(v)) errs.push(`${k} must start with http:// or https://`); });
      // Emails
      ['business_email','business_support_email'].forEach((k) => { const v = payload[k]; if (v && !isEmail(v)) errs.push(`${k} looks invalid`); });
      if (errs.length) { if (status) status.textContent = `Please fix: ${errs.join('; ')}`; return; }
      try {
        import('../modules/business-settings-api.js').then((mod) => {
          const API = mod?.default || mod?.BusinessSettingsAPI;
          if (!API || typeof API.upsert !== 'function') { if (status) status.textContent = 'Save failed: API unavailable'; return; }
          API.upsert('business', payload).then(() => {
            if (status) status.textContent = 'Saved.';
            applyBusinessCssToRoot(payload);
          }).catch((err) => {
            if (status) status.textContent = err?.message || 'Save failed';
          });
        }).catch(() => { if (status) status.textContent = 'Save failed: module error'; });
      } catch(_) { if (status) status.textContent = 'Save failed'; }
    }

    // ------------------------------
    // Email History (dedicated UI)
    // ------------------------------
    const emailHistoryState = { page: 1, limit: 20, totalPages: 1, mode: 'list', query: '', from: '', to: '', status: '', type: '', sort: 'sent_at_desc', lastEntries: [], drawerOpen: false, drawerIndex: null, drawerRow: null, drawerJsonPretty: true };
    let emailDrawerEscHandler = null;
    const getEmailTestEndpoint = () => { try { return localStorage.getItem('wf_email_test_endpoint') || ''; } catch(_) { return ''; } };
    const setEmailTestEndpoint = (url) => { try { localStorage.setItem('wf_email_test_endpoint', String(url||'')); } catch(_) {} };
    function setEmailHistoryStatus(msg){ const el = document.getElementById('emailHistoryStatus'); if (el) el.textContent = msg || ''; }
    function setEmailHistoryPageLabel(){ const p = document.getElementById('emailHistoryPage'); if (p) p.textContent = `Page ${emailHistoryState.page}`; }
    function renderEmailHistoryRows(entries){
      const list = document.getElementById('emailHistoryList'); if (!list) return;
      list.innerHTML = '';
      const fmt = (s) => { try { return new Date(s).toLocaleString(); } catch(_) { return s; } };
      emailHistoryState.lastEntries = Array.isArray(entries) ? entries : [];
      (entries || []).forEach((row, idx) => {
        const container = document.createElement('div');
        container.className = 'p-2 text-sm';
        const to = row.to_email || row.to || '';
        const sub = row.email_subject || row.subject || '';
        const type = row.email_type || '';
        const status = row.status || '';
        const ts = row.sent_at || row.created_at || row.timestamp || '';
        const err = row.error_message || '';
        const from = row.from_email || '';
        container.innerHTML = `<div class="flex items-start justify-between gap-2">
          <div>
            <div class="font-medium">${sub || '(no subject)'} <span class="text-xs text-gray-500">${type}</span></div>
            <div class="text-xs text-gray-600">to: ${to}</div>
          </div>
          <div class="text-xs text-gray-500 whitespace-nowrap">${fmt(ts)} • ${status}</div>
        </div>
        <div class="mt-1 flex items-center gap-3">
          <button type="button" data-action="email-history-toggle" data-index="${idx}" class="text-xs text-blue-600 hover:underline">View details</button>
          <button type="button" data-action="email-history-toggle-raw" data-index="${idx}" class="text-xs text-blue-600 hover:underline">View raw JSON</button>
          <button type="button" data-action="email-history-copy-raw" data-index="${idx}" class="text-xs text-blue-600 hover:underline">Copy JSON</button>
          <button type="button" data-action="email-history-open-drawer" data-index="${idx}" class="text-xs text-blue-600 hover:underline">Open drawer</button>
        </div>
        <div class="email-history-details hidden mt-2 text-xs text-gray-700">
          <div>From: <span class="font-mono">${from}</span></div>
          <div>To: <span class="font-mono">${to}</span></div>
          <div>Subject: <span class="font-mono">${sub}</span></div>
          <div>Type: <span class="font-mono">${type}</span></div>
          <div>Status: <span class="font-mono">${status}</span></div>
          <div>Sent At: <span class="font-mono">${fmt(ts)}</span></div>
          ${err ? `<div class="text-red-600">Error: <span class="font-mono">${err}</span></div>` : ''}
        </div>`;
        // Append raw JSON block safely
        const pre = document.createElement('pre');
        pre.className = 'email-history-raw hidden mt-2 text-xs bg-gray-50 p-2 rounded overflow-auto';
        try { pre.textContent = JSON.stringify(row, null, 2); } catch(_) { pre.textContent = String(row); }
        container.appendChild(pre);
        list.appendChild(container);
      });
      // If a drawer is open, attempt to repopulate with the current row data
      try { if (emailHistoryState.drawerOpen) { const idx = emailHistoryState.drawerIndex; let row = null; if (Array.isArray(emailHistoryState.lastEntries) && idx != null) { row = emailHistoryState.lastEntries[idx]; } row = row || emailHistoryState.drawerRow; if (row) openEmailDrawer(row, { animate: false, index: idx }); } } catch(_) {}
    }
    function openEmailDrawer(row, opts){
      const drawer = document.getElementById('emailHistoryDrawer'); const content = document.getElementById('emailHistoryDrawerContent'); const overlay = document.getElementById('emailHistoryDrawerOverlay'); if (!drawer || !content) return;
      const fmt = (s) => { try { return new Date(s).toLocaleString(); } catch(_) { return s; } };
      const sub = row.email_subject || row.subject || '';
      const to  = row.to_email || row.to || '';
      const type= row.email_type || '';
      const status = row.status || '';
      const ts = row.sent_at || row.created_at || row.timestamp || '';
      const err = row.error_message || '';
      const from = row.from_email || '';
      // Fill meta
      const ms = document.getElementById('ehdSubject'); if (ms) ms.textContent = sub || '';
      const mt = document.getElementById('ehdTo'); if (mt) mt.textContent = to || '';
      const mty= document.getElementById('ehdType'); if (mty) mty.textContent = type || '';
      // Build details + raw
      const detailsHtml = `
        <div class="email-history-details mt-1 text-xs text-gray-700">
          <div>From: <span class="font-mono">${from}</span></div>
          <div>To: <span class="font-mono">${to}</span></div>
          <div>Subject: <span class="font-mono">${sub}</span></div>
          <div>Type: <span class="font-mono">${type}</span></div>
          <div>Status: <span class="font-mono">${status}</span></div>
          <div>Sent At: <span class="font-mono">${fmt(ts)}</span></div>
          ${err ? `<div class="text-red-600">Error: <span class="font-mono">${err}</span></div>` : ''}
        </div>`;
      emailHistoryState.drawerJsonPretty = true;
      let rawText = ''; try { rawText = JSON.stringify(row, null, 2); } catch(_) { rawText = String(row); }
      content.innerHTML = detailsHtml + `<pre id="ehdJson" class="mt-2 text-xs bg-gray-50 p-2 rounded overflow-auto">${rawText}</pre>`;
      // Prefill endpoint
      try { const ep = document.getElementById('ehdEndpoint'); if (ep) { const saved = getEmailTestEndpoint(); if (saved) ep.value = saved; } } catch(_) {}
      // Open using CSS classes
      drawer.classList.remove('hidden');
      drawer.classList.add('is-open');
      drawer.setAttribute('aria-hidden','false');
      if (overlay) { overlay.classList.remove('hidden'); overlay.classList.add('is-open'); overlay.setAttribute('aria-hidden','false'); }
      emailHistoryState.drawerOpen = true;
      if (opts && typeof opts.index === 'number') emailHistoryState.drawerIndex = opts.index; else emailHistoryState.drawerIndex = null;
      emailHistoryState.drawerRow = row;
      // ESC to close
      try {
        if (!emailDrawerEscHandler) {
          emailDrawerEscHandler = function(ev){ if ((ev && (ev.key === 'Escape' || ev.key === 'Esc'))) { try { ev.preventDefault(); } catch(_){} closeEmailDrawer(); } };
          document.addEventListener('keydown', emailDrawerEscHandler, true);
        }
      } catch(_) {}
    }
    function closeEmailDrawer(){
      const drawer = document.getElementById('emailHistoryDrawer'); const overlay = document.getElementById('emailHistoryDrawerOverlay'); if (!drawer) return;
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden','true');
      setTimeout(() => { drawer.classList.add('hidden'); }, 160);
      if (overlay) { overlay.classList.remove('is-open'); overlay.setAttribute('aria-hidden','true'); setTimeout(() => { overlay.classList.add('hidden'); }, 160); }
      emailHistoryState.drawerOpen = false; emailHistoryState.drawerIndex = null; // keep drawerRow for potential reopen if needed
      // remove ESC handler
      try { if (emailDrawerEscHandler) { document.removeEventListener('keydown', emailDrawerEscHandler, true); emailDrawerEscHandler = null; } } catch(_) {}
    }
    function loadEmailHistory(page){
      const p = Math.max(1, page || emailHistoryState.page);
      emailHistoryState.page = p;
      setEmailHistoryStatus('Loading…');
      const qp = new URLSearchParams({ action: 'get_log', type: 'email_logs', page: String(p), limit: String(emailHistoryState.limit) });
      if (emailHistoryState.from) qp.set('from', emailHistoryState.from);
      if (emailHistoryState.to) qp.set('to', emailHistoryState.to);
      if (emailHistoryState.status) qp.set('status', emailHistoryState.status);
      if (emailHistoryState.type) qp.set('email_type', emailHistoryState.type);
      if (emailHistoryState.sort) qp.set('sort', emailHistoryState.sort);
      fetch(`/api/website_logs.php?${qp.toString()}`)
        .then(r => r.json().catch(()=>({})))
        .then(j => {
          if (!j?.success) { setEmailHistoryStatus(j?.error || 'Failed to load'); return; }
          renderEmailHistoryRows(j.entries || []);
          emailHistoryState.totalPages = Math.max(1, j.total_pages || 1);
          setEmailHistoryPageLabel();
          setEmailHistoryStatus('');
        })
        .catch(err => { setEmailHistoryStatus(err?.message || 'Failed to load'); });
    }
    function searchEmailHistory(query){
      emailHistoryState.mode = 'search';
      emailHistoryState.query = query || '';
      setEmailHistoryStatus('Searching…');
      {
        const qp = new URLSearchParams({ action: 'search_logs', type: 'email_logs', query: emailHistoryState.query });
        if (emailHistoryState.from) qp.set('from', emailHistoryState.from);
        if (emailHistoryState.to) qp.set('to', emailHistoryState.to);
        if (emailHistoryState.status) qp.set('status', emailHistoryState.status);
        if (emailHistoryState.type) qp.set('email_type', emailHistoryState.type);
        if (emailHistoryState.sort) qp.set('sort', emailHistoryState.sort);
        fetch(`/api/website_logs.php?${qp.toString()}`)
        .then(r => r.json().catch(()=>({})))
        .then(j => {
          if (!j?.success) { setEmailHistoryStatus(j?.error || 'Search failed'); return; }
          renderEmailHistoryRows(j.results || []);
          document.getElementById('emailHistoryPage')?.classList.add('opacity-50');
          setEmailHistoryStatus('');
        })
        .catch(err => { setEmailHistoryStatus(err?.message || 'Search failed'); });
      }
    }
    function resetEmailHistory(){ emailHistoryState.mode = 'list'; emailHistoryState.page = 1; emailHistoryState.query = ''; document.getElementById('emailHistoryPage')?.classList.remove('opacity-50'); loadEmailHistory(1); }

    try {
      // Lazy-load API module to avoid blocking bridge if its transform fails in dev
      const mod = await import('../modules/business-settings-api.js');
      const BusinessSettingsAPI = mod?.default || mod?.BusinessSettingsAPI;
      if (!BusinessSettingsAPI || typeof BusinessSettingsAPI.upsert !== 'function') {
        throw new Error('BusinessSettingsAPI unavailable');
      }
      await BusinessSettingsAPI.upsert('email', settings);
      showToast('success', 'Email Settings Saved', 'Your email configuration has been updated.');
    } catch (err) {
      console.error('[AdminSettingsBridge] Failed to save email settings', err);
      showToast('error', 'Save Failed', err?.message || 'Could not save settings.');
    }
  });
}

async function initEmailSection(){
  const cfg = await loadEmailConfig();
  applyEmailConfig(cfg);
  wireToggles();
  wireSaveHandler();
  wireTestEmail(cfg);
}

function onReady(fn){
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
  else fn();
}

export function init(){
  if (typeof window !== 'undefined') {
    if (window.__WF_ADMIN_SETTINGS_BRIDGE_INIT) return; // idempotent guard
    window.__WF_ADMIN_SETTINGS_BRIDGE_INIT = true;
    try { console.info('[AdminSettingsBridge] init start'); } catch(_) {}
  }
  onReady(() => {
    // Only run on admin settings page
    const body = document.body;
    const path = (body?.dataset?.path || location.pathname || '').toLowerCase();
    const isSettings = (
      body?.dataset?.page === 'admin/settings'
      || (body?.dataset?.isAdmin === 'true' && (
        path.includes('/admin/settings')
        || (path.includes('/admin') && (new URLSearchParams(window.location.search).get('section') === 'settings'))
      ))
    );
    if (!isSettings) { try { console.info('[AdminSettingsBridge] skip: not settings route'); } catch(_) {} return; }
    try { console.info('[AdminSettingsBridge] active on settings route'); } catch(_) {}
    // No dedupe/failsafe: allow errors to surface if duplicates exist
    // Defer email section initialization until the user opens the Email Settings modal
    let __emailInitDone = false;
    const _initEmailIfNeeded = async () => {
      if (__emailInitDone) return;
      try {
        await initEmailSection();
      } catch (e) { try { console.warn('[AdminSettingsBridge] initEmailSection failed (deferred)', e); } catch(_) {} }
      __emailInitDone = true;
    };
    // Ensure styles are present immediately
    ensureModalStyles();

    // Inject once: styles that polish Categories & Attributes modals
    function ensureModalStyles() {
      if (document.getElementById('wf-settings-modal-inject')) return;
      const css = `
        /* Categories modal polish */
        #categoriesModal table { border-collapse: separate; border-spacing: 0; width: 100%; }
        #categoriesModal thead th { font-weight: 600; padding: 8px 10px; background: #f3f4f6; color: #111827; }
        #categoriesModal thead th:nth-child(2) { text-align: right; }
        #categoriesModal tbody td { padding: 8px 10px; }
        #categoriesModal tbody td:nth-child(2) { text-align: right; }
        #categoriesModal tbody tr:nth-child(odd) { background: #ffffff; }
        #categoriesModal tbody tr:nth-child(even) { background: #f9fafb; }
        #categoriesModal .btn { white-space: nowrap; }
        /* Attributes modal layout */
        #attributesModal .admin-modal, #attributesModal .admin-modal-content { max-width: 1100px; width: 92vw; }
        #attributesModal .grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; }
        #attributesModal .attr-col { padding: 6px; }
        #attributesModal .attr-list { max-height: 360px; overflow-y: auto; }
        @media (max-width: 640px) {
          #attributesModal .grid { grid-template-columns: 1fr; }
        }
      `;
      const style = document.createElement('style');
      style.id = 'wf-settings-modal-inject';
      style.type = 'text/css';
      style.appendChild(document.createTextNode(css));
      document.head.appendChild(style);
    }

    // Do not force 'under-header' positioning; allow overlays to cover full viewport

    // Helper show/hide for modals
    const getModalEl = (id) => {
      try {
        const safeId = CSS && CSS.escape ? CSS.escape(id) : id;
        // Prefer the last occurrence if duplicate IDs exist (legacy placeholders earlier in DOM)
        const list = document.querySelectorAll(`#${safeId}`);
        if (list && list.length) return list[list.length - 1];
      } catch(_) {}
      return document.getElementById(id);
    };

    // Lift any header squelch/guards that may be hiding overlays pre-initialization
    const liftGuards = () => {
      try { document.documentElement.removeAttribute('data-early-settings-squelch'); } catch(_) {}
      try { document.documentElement.removeAttribute('data-wf-squelch'); } catch(_) {}
      try { const st = document.getElementById('wf-early-settings-squelch'); if (st) st.remove(); } catch(_) {}
      try {
        const styles = Array.from(document.getElementsByTagName('style'));
        const target = styles.find((el) => el.getAttribute && el.getAttribute('data-wf-squelch-style') === '1');
        if (target) target.remove();
      } catch(_) {}
    };
    // Track open overlays and last trigger elements
    const openStack = [];
    const lastTriggerById = new Map();

    const getFocusable = (root) => {
      if (!root) return [];
      const sel = [
        'a[href]',
        'area[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        'iframe',
        '[tabindex]:not([tabindex="-1"])',
        '[contenteditable="true"]'
      ].join(',');
      return Array.from(root.querySelectorAll(sel)).filter(el => el.offsetParent !== null || el === document.activeElement);
    };

    const focusTrapHandlers = new Map();

    const trapFocus = (overlayEl) => {
      const handler = (e) => {
        if (e.key !== 'Tab') return;
        const modalPanel = overlayEl.querySelector('.admin-modal, .modal, .admin-modal-content');
        const focusables = getFocusable(modalPanel || overlayEl);
        if (!focusables.length) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (e.shiftKey) {
          if (document.activeElement === first || !overlayEl.contains(document.activeElement)) {
            e.preventDefault();
            last.focus();
          }
        } else {
          if (document.activeElement === last) {
            e.preventDefault();
            first.focus();
          }
        }
      };
      overlayEl.addEventListener('keydown', handler);
      focusTrapHandlers.set(overlayEl, handler);
    };

    const releaseFocusTrap = (overlayEl) => {
      const handler = focusTrapHandlers.get(overlayEl);
      if (handler) {
        overlayEl.removeEventListener('keydown', handler);
        focusTrapHandlers.delete(overlayEl);
      }
    };

    const ensureInitialFocus = (overlayEl) => {
      try {
        const modalPanel = overlayEl.querySelector('.admin-modal, .modal, .admin-modal-content');
        const focusables = getFocusable(modalPanel || overlayEl);
        if (focusables.length) {
          focusables[0].focus();
        } else {
          overlayEl.setAttribute('tabindex', '-1');
          overlayEl.focus();
        }
      } catch (_) {}
    };

    const onDocKeydown = (e) => {
      if (e.key === 'Escape' && openStack.length) {
        const topId = openStack[openStack.length - 1];
        e.preventDefault();
        hideModal(topId);
      }
    };

    const installEscHandler = () => {
      try { document.addEventListener('keydown', onDocKeydown); } catch(_) {}
    };
    const removeEscHandler = () => {
      try { document.removeEventListener('keydown', onDocKeydown); } catch(_) {}
    };

    const forceVisible = (el) => {
      try {
        // Fully un-hide: remove hidden attribute and hidden class; add show and aria-hidden=false
        try { el.removeAttribute('hidden'); } catch(_) {}
        el.classList.remove('hidden');
        el.classList.add('show');
        el.setAttribute('aria-hidden', 'false');
        // Ensure full-viewport coverage regardless of external CSS
        try { el.classList.remove('under-header'); } catch(_) {}
        try { el.classList.add('wf-modal-force-visible'); } catch(_) {}
        try { document.documentElement.classList.add('modal-open'); } catch(_) {}
        try { document.body.classList.add('modal-open'); } catch(_) {}
        try {
          // Normalize inner panel via a CSS class
          const panel = el.querySelector('.admin-modal, .modal, .admin-modal-content');
          if (panel) panel.classList.add('wf-admin-panel-visible');
        } catch(_) {}
        // Focus management
        installEscHandler();
        ensureInitialFocus(el);
        trapFocus(el);
        try { console.info('[AdminSettingsBridge] forceVisible ->', el.id); } catch(_) {}
      } catch(_) {}
    };
    const verifyVisibleSoon = (el) => {
      try {
        requestAnimationFrame(() => {
          const cs = window.getComputedStyle ? getComputedStyle(el) : null;
          if (!cs) return;
          if (cs.display === 'none' || cs.visibility === 'hidden' || Number(cs.opacity) < 0.99) {
            forceVisible(el);
          }
        });
      } catch(_) {}
    };
    const showModal = (id) => {
      let el = getModalEl(id);
      if (!el) {
        // Special case: Avoid auto-creating a fallback for Dashboard Config to prevent duplicate overlays
        if (id === 'dashboardConfigModal') {
          try { console.info('[AdminSettingsBridge] waiting for dashboardConfigModal markup…'); } catch(_) {}
          // Try a microtask/RAF re-query in case markup is injected later in DOM
          try {
            requestAnimationFrame(() => {
              const later = getModalEl(id);
              if (later) { forceVisible(later); verifyVisibleSoon(later); }
            });
          } catch(_) {}
          return false;
        }
        // Create a minimal fallback overlay so the button still works even if markup wasn't included
        try {
          const overlay = document.createElement('div');
          overlay.id = id;
          overlay.className = 'admin-modal-overlay hidden';
          overlay.setAttribute('role', 'dialog');
          overlay.setAttribute('aria-modal', 'true');
          overlay.setAttribute('aria-hidden', 'true');
          const panel = document.createElement('div');
          panel.className = 'admin-modal';
          const header = document.createElement('div');
          header.className = 'modal-header';
          const h2 = document.createElement('h2');
          h2.className = 'admin-card-title';
          h2.textContent = 'Settings';
          const x = document.createElement('button');
          x.type = 'button'; x.className = 'admin-modal-close'; x.setAttribute('aria-label','Close');
          x.setAttribute('data-action', 'close-admin-modal');
          x.textContent = '×';
          header.appendChild(h2); header.appendChild(x);
          const body = document.createElement('div');
          body.className = 'modal-body';
          body.textContent = 'Loading…';
          panel.appendChild(header); panel.appendChild(body);
          overlay.appendChild(panel);
          document.body.appendChild(overlay);
          el = overlay;
        } catch(_) { return false; }
      }
      liftGuards();
      try {
        // Prevent clipping by any transformed ancestor: ensure overlay is a child of <body>
        if (el.parentNode && el.parentNode !== document.body) {
          document.body.appendChild(el);
        }
      } catch(_) {}
      // Push to open stack
      if (!openStack.includes(id)) openStack.push(id);
      forceVisible(el);
      verifyVisibleSoon(el);
      try { if (typeof window.updateModalScrollLock === 'function') window.updateModalScrollLock(); } catch(_) {}
      try { console.info('[AdminSettingsBridge] showModal', id); } catch(_) {}
      return true;
    };
    const hideModal = (id) => {
      const el = getModalEl(id);
      if (!el) return false;
      // Fully hide: add hidden attribute and hidden class; remove show and set aria-hidden=true
      try { el.setAttribute('hidden', ''); } catch(_) {}
      el.classList.add('hidden');
      el.classList.remove('show');
      el.setAttribute('aria-hidden', 'true');
      // Remove from stack
      const idx = openStack.lastIndexOf(id);
      if (idx !== -1) openStack.splice(idx, 1);
      // Release focus trap
      releaseFocusTrap(el);
      try { document.documentElement.classList.remove('modal-open'); } catch(_) {}
      try { document.body.classList.remove('modal-open'); } catch(_) {}
      try { if (typeof window.updateModalScrollLock === 'function') window.updateModalScrollLock(); } catch(_) {}
      // Return focus to last trigger for this modal if available
      try {
        const trigger = lastTriggerById.get(id);
        if (trigger && document.contains(trigger)) {
          trigger.focus();
        }
      } catch(_) {}
      // Remove ESC handler if no overlays remain
      if (!openStack.length) removeEscHandler();
      try { console.info('[AdminSettingsBridge] hideModal', id); } catch(_) {}
      return true;
    };

    // Safety sweep: ensure all managed overlays start hidden to avoid multi-open on load
    try {
      const managed = [
        'businessInfoModal',
        'squareSettingsModal',
        'emailSettingsModal',
        'loggingStatusModal',
        'aiSettingsModal',
        'aiToolsModal',
        'secretsModal',
        'cssRulesModal',
        // Newly wired overlays
        'backgroundManagerModal',
        'receiptSettingsModal',
        'dbMaintenanceModal',
        'dashboardConfigModal',
        // File Explorer (new)
        'fileExplorerModal',
        // New iframe-embedded tools
        'roomSettingsModal',
        'roomCategoryLinksModal',
        'templateManagerModal',
        'emailHistoryModal',
        'accountSettingsModal',
      ];
      managed.forEach((id) => {
        const el = getModalEl(id);
        if (el) {
          try { el.setAttribute('hidden', ''); } catch(_) {}
          el.classList.add('hidden');
          el.classList.remove('show');
          el.setAttribute('aria-hidden', 'true');
        }
      });
    } catch(_) {}

    // If the browser restored focus into a hidden overlay, blur it to avoid aria-hidden focus error
    try {
      const active = document.activeElement;
      if (active && active !== document.body) {
        const hiddenOverlay = active.closest && active.closest('.admin-modal-overlay.hidden[aria-hidden="true"]');
        if (hiddenOverlay) {
          active.blur();
        }
      }
    } catch(_) {}

    // ------------------------------
    // Categories & Attributes (open + CRUD)
    // ------------------------------
    document.addEventListener('click', async (e) => {
      const t = e.target;
      const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
      // Open Categories (preload then show)
      if (closest('[data-action="open-categories"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        lastTriggerById.set('categoriesModal', (t.closest('button, a, [tabindex]')||t));
        try { await populateCategories(); } catch(err) { console.error('[AdminSettingsBridge] populateCategories failed', err); }
        showModal('categoriesModal');
        // Ensure polish styles are present
        ensureModalStyles();
        return;
      }
      // Open Attributes (preload then show)
      if (closest('[data-action="open-attributes"]')) {
        if (window.__WF_ADMIN_SETTINGS_BRIDGE_INIT) return;
        e.preventDefault(); if (typeof e.stopPropagation==='function') e.stopPropagation();
        // Ensure modal exists; if not, create a minimal one
        try {
          if (!document.getElementById('attributesModal')) {
            const overlay = document.createElement('div');
            overlay.id = 'attributesModal';
            overlay.className = 'admin-modal-overlay hidden';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-hidden', 'true');
            const panel = document.createElement('div');
            panel.className = 'admin-modal';
            const header = document.createElement('div');
            header.className = 'modal-header';
            const h2 = document.createElement('h2');
            h2.className = 'admin-card-title';
            h2.textContent = 'Attributes';
            const x = document.createElement('button');
            x.type = 'button'; x.className = 'admin-modal-close'; x.setAttribute('aria-label','Close');
            x.setAttribute('data-action', 'close-admin-modal');
            x.textContent = '×';
            header.appendChild(h2); header.appendChild(x);
            const body = document.createElement('div');
            body.className = 'modal-body';
            body.textContent = 'Loading…';
            panel.appendChild(header); panel.appendChild(body);
            overlay.appendChild(panel);
            document.body.appendChild(overlay);
          }
        } catch(_) {}
        __wfShowModal('attributesModal');
        // Populate attribute lists when opening
        try { if (typeof populateAttributes === 'function') { populateAttributes(); } } catch(_) {}
        // Lazy-load the iframe
        try {
          const frame = document.getElementById('attributesFrame');
          if (frame && frame.getAttribute('src') === 'about:blank') {
            const ds = frame.getAttribute('data-src');
            if (ds) frame.setAttribute('src', ds);
          }
        } catch(_) {}
        return;
      }
      // Open Room Settings (iframe)
      if (closest('[data-action="open-room-settings"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        lastTriggerById.set('roomSettingsModal', (t.closest('button, a, [tabindex]')||t));
        showModal('roomSettingsModal');
        try { const f = document.getElementById('roomSettingsFrame'); if (f && f.getAttribute('src') === 'about:blank') { const ds = f.getAttribute('data-src'); if (ds) f.setAttribute('src', ds); } } catch(_) {}
        return;
      }
      // Open Room-Category Links (iframe)
      if (closest('[data-action="open-room-category-links"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        lastTriggerById.set('roomCategoryLinksModal', (t.closest('button, a, [tabindex]')||t));
        showModal('roomCategoryLinksModal');
        try { const f = document.getElementById('roomCategoryLinksFrame'); if (f && f.getAttribute('src') === 'about:blank') { const ds = f.getAttribute('data-src'); if (ds) f.setAttribute('src', ds); } } catch(_) {}
        return;
      }
      // Open Template Manager (iframe)
      if (closest('[data-action="open-template-manager"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        lastTriggerById.set('templateManagerModal', (t.closest('button, a, [tabindex]')||t));
        showModal('templateManagerModal');
        try { const f = document.getElementById('templateManagerFrame'); if (f && f.getAttribute('src') === 'about:blank') { const ds = f.getAttribute('data-src'); if (ds) f.setAttribute('src', ds); } } catch(_) {}
        return;
      }
      // Categories CRUD inside modal
      if (closest('#categoriesModal [data-action="cat-add"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const modal = document.getElementById('categoriesModal'); if (!modal) return;
        const input = modal.querySelector('#catNewName'); const result = modal.querySelector('#catResult');
        const name = (input && input.value) ? input.value.trim() : '';
        if (!name) { if (result) result.textContent = 'Please enter a name.'; return; }
        if (result) result.textContent = 'Adding…';
        try { await catApi('/api/categories.php?action=add', { action: 'add', name }); if (input) input.value = ''; await populateCategories(); if (result) result.textContent = 'Added.'; }
        catch (err) { if (result) result.textContent = err?.message || 'Failed to add.'; }
        return;
      }
      if (closest('#categoriesModal [data-action="cat-rename"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const btn = closest('[data-action="cat-rename"]'); const oldName = btn?.getAttribute('data-name')||'';
        const newName = prompt('Rename category', oldName) || '';
        if (!newName || newName.trim() === '' || newName === oldName) return;
        const modal = document.getElementById('categoriesModal'); const result = modal ? modal.querySelector('#catResult') : null;
        if (result) result.textContent = 'Renaming…';
        try { await catApi('/api/categories.php?action=rename', { action: 'rename', old_name: oldName, new_name: newName.trim(), update_items: true }); await populateCategories(); if (result) result.textContent = 'Renamed.'; }
        catch (err) { if (result) result.textContent = err?.message || 'Failed to rename.'; }
        return;
      }
      if (closest('#categoriesModal [data-action="cat-delete"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const btn = closest('[data-action="cat-delete"]'); const name = btn?.getAttribute('data-name')||'';
        const reassign = prompt(`Delete "${name}". Optionally enter a category to reassign items to (leave blank to cancel if in use).`, '');
        if (reassign === null) return; // cancelled
        const payload = { action: 'delete', name };
        if (typeof reassign === 'string' && reassign.trim() !== '') payload.reassign_to = reassign.trim();
        const modal = document.getElementById('categoriesModal'); const result = modal ? modal.querySelector('#catResult') : null;
        if (result) result.textContent = 'Deleting…';
        try { await catApi('/api/categories.php?action=delete', payload); await populateCategories(); if (result) result.textContent = 'Deleted.'; }
        catch (err) { if (result) result.textContent = err?.message || 'Failed to delete.'; }
        return;
      }
    }, true);

    // ------------------------------
    // Dashboard Config Fallback (Lightweight)
    // ------------------------------
    async function dashApi(path, payload) {
      const url = typeof path === 'string' ? path : '/api/dashboard_sections.php?action=get_sections';
      const baseHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
      const opts = payload
        ? { method: 'POST', headers: { ...baseHeaders, 'Content-Type': 'application/json' }, body: JSON.stringify(payload), credentials: 'include' }
        : { method: 'GET', headers: baseHeaders, credentials: 'include' };
      const res = await fetch(url, opts).catch((e) => { throw new Error(e?.message || 'Network error'); });
      const status = res.status;
      const text = await res.text().catch(() => '');
      if (status < 200 || status >= 300) {
        throw new Error(`HTTP ${status}: ${text.slice(0, 200)}`);
      }
      let data;
      try { data = text ? JSON.parse(text) : {}; } catch (e) {
        throw new Error(`Non-JSON response: ${text.slice(0, 200)}`);
      }
      // If the response is empty but 200 OK from our dashboard endpoint, treat as implicit success.
      if ((!data || Object.keys(data).length === 0) && /\/api\/dashboard_sections\.php/.test(url)) {
        data = { success: true };
      }
      // Require success:true otherwise
      if (!data || data.success !== true) {
        const errMsg = data && data.error ? String(data.error) : `Unexpected response: ${text.slice(0, 200) || '(empty)'}`;
        throw new Error(errMsg);
      }
      return data;
    }

    function renderDashboardLists(container) {
      const activeUl = container.querySelector('#dashboardActiveSections');
      const availUl = container.querySelector('#dashboardAvailableSections');
      if (!activeUl || !availUl) return { activeUl: null, availUl: null };
      activeUl.innerHTML = '';
      availUl.innerHTML = '';
      const makeLi = (item, isActive) => {
        const li = document.createElement('li');
        li.dataset.key = item.section_key || item.key || item.id || '';
        const title = item.display_title || item.title || item.section_info?.title || li.dataset.key;
        // Use consistent utility classes for both lists
        li.className = 'wf-dash-item flex items-center justify-between gap-2 px-2 py-1 border border-gray-200 rounded';
        const label = document.createElement('span');
        label.className = 'wf-dash-item-title text-sm text-gray-800';
        label.textContent = title;
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-secondary';
        btn.textContent = isActive ? 'Remove' : 'Add';
        btn.setAttribute('data-action', isActive ? 'dashboard-remove-section' : 'dashboard-add-section');
        btn.setAttribute('data-key', li.dataset.key);
        const btnWrap = document.createElement('span');
        btnWrap.appendChild(btn);
        li.appendChild(label);
        li.appendChild(btnWrap);
        return li;
      };
      return { activeUl, availUl, makeLi };
    }

    async function populateDashboardFallback(modalId = 'dashboardConfigModal') {
      const el = getModalEl(modalId);
      if (!el) return;
      const body = el.querySelector('.modal-body');
      if (!body) return;
      try {
        body.classList.add('is-busy');
        const data = await dashApi('/api/dashboard_sections.php?action=get_sections');
        const sections = Array.isArray(data?.data?.sections) ? data.data.sections : (Array.isArray(data?.sections) ? data.sections : []);
        try { console.info('[DashboardConfigFallback] get_sections result', { count: sections.length, keys: Object.keys(data?.data?.available_sections || data?.available_sections || {}) }); } catch(_) {}
        const lists = renderDashboardLists(el);
        if (!lists.activeUl) return;
        // Available strictly from API (no defaults; surface issues visibly)
        const avail = data?.data?.available_sections || data?.available_sections || {};
        // Active sections first, enriched by avail map for consistent titles
        const activeKeys = new Set();
        const pushActive = (obj) => {
          const key = (obj.section_key || obj.key || '').trim();
          if (!key || activeKeys.has(key)) return;
          activeKeys.add(key);
          const enriched = {
            ...obj,
            section_key: key,
            key,
            title: (avail[key]?.title) || obj.display_title || obj.title || key,
            is_active: 1,
          };
          lists.activeUl.appendChild(lists.makeLi(enriched, true));
        };
        if (Array.isArray(sections) && sections.length) {
          sections.forEach((s) => {
            if (s && (s.is_active === 1 || s.is_active === true)) {
              pushActive(s);
            }
          });
        } else {
          // Hydrate from local snapshot if server returns no sections
          try {
            const raw = localStorage.getItem('wf.dashboard.sections');
            const activeSnap = raw ? JSON.parse(raw) : [];
            if (Array.isArray(activeSnap) && activeSnap.length) {
              activeSnap.forEach(item => pushActive({
                key: item.key || item.section_key,
                section_key: item.section_key || item.key,
                title: item.title || item.section_key || item.key,
              }));
            }
          } catch(_) {}
        }
        const availKeys = Object.keys(avail || {});
        availKeys.forEach((key) => {
          // Skip items already active (including hydrated ones)
          if (activeKeys.has(key)) return;
          const item = { key, title: avail[key]?.title || key };
          lists.availUl.appendChild(lists.makeLi(item, false));
        });
        // Empty-state hint if nothing returned
        if (!lists.activeUl.children.length && !lists.availUl.children.length) {
          const hint = document.createElement('div');
          hint.className = 'text-sm text-gray-500 mt-2';
          hint.textContent = 'No sections returned from the API.';
          body.appendChild(hint);
          const status = el.querySelector('#dashboardConfigResult');
          if (status) status.textContent = 'No sections available.';
        }
        // Small diagnostics
        try { console.info('[DashboardConfigFallback] populated', { active: lists.activeUl.children.length, avail: lists.availUl.children.length }); } catch(_) {}

        // Save handler: gather active list order
        const saveBtn = el.querySelector('[data-action="dashboard-config-save"]');
        if (saveBtn) {
          saveBtn.onclick = async () => {
            const result = el.querySelector('#dashboardConfigResult');
            const setResult = (msg, ok) => { if (result) { result.textContent = msg; result.classList.toggle('wf-ok', !!ok); result.classList.toggle('wf-error', !ok); } };
            try {
              // Gather Active with de-duplication by section_key
              const seen = new Set();
              const items = Array.from(lists.activeUl.querySelectorAll('li')).filter(li => {
                const k = (li.dataset.key || '').trim();
                if (!k || seen.has(k)) return false;
                seen.add(k); return true;
              });
              if (!items.length) { setResult('Add at least one section before saving.', false); return; }
              const payload = {
                action: 'update_sections',
                sections: items.map((li, idx) => ({
                  section_key: (li.dataset.key || '').trim(),
                  display_order: idx + 1,
                  is_active: 1,
                  show_title: 1,
                  show_description: 1,
                  custom_title: null,
                  custom_description: null,
                  width_class: 'half-width'
                }))
              };
              saveBtn.disabled = true;
              setResult('Saving…', true);
              try { console.info('[DashboardConfigFallback] update_sections payload', payload); } catch(_) {}
              const resp = await dashApi('/api/dashboard_sections.php?action=update_sections', payload);
              try { console.info('[DashboardConfigFallback] update_sections response', resp); } catch(_) {}
              setResult('Saved.', true);
              try { showToast('success', 'Dashboard Saved', 'Dashboard configuration updated.'); } catch(_) {}
              // Persist a local snapshot as a resilience layer BEFORE re-fetching
              try {
                const snapshot = payload.sections.map(s => ({
                  key: s.section_key,
                  section_key: s.section_key,
                  title: s.custom_title || s.section_key,
                  is_active: 1
                }));
                localStorage.setItem('wf.dashboard.sections', JSON.stringify(snapshot));
              } catch(_) {}
              // Close the modal on success per user preference
              try { hideModal(modalId); } catch(_) {}
              return;
            } catch (err) {
              console.error('[DashboardConfigFallback] save failed', err);
              setResult(err?.message || 'Unable to save.', false);
              try { showToast('error', 'Save Failed', err?.message || 'Unable to save.'); } catch(_) {}
              // Run diagnostics to help identify root cause (DB connect/table)
              try {
                const d = await dashApi('/api/dashboard_sections.php?action=diagnostics');
                const diag = d?.data?.diagnostics || d?.diagnostics || {};
                const bits = [];
                if (typeof diag.db_connect !== 'undefined') bits.push(`DB:${diag.db_connect ? 'ok' : 'fail'}`);
                if (typeof diag.table_exists !== 'undefined') bits.push(`Table:${diag.table_exists ? 'ok' : 'missing'}`);
                if (bits.length) setResult(`Save failed. ${bits.join(' / ')}`, false);
              } catch(_) {}
            } finally {
              saveBtn.disabled = false;
            }
          };
        }
      } catch (err) {
        console.error('[DashboardConfigFallback] load failed', err);
        // Attempt a softer fallback: try fetching available sections only
        try {
          const alt = await dashApi('/api/dashboard_sections.php?action=get_available_sections');
          const lists = renderDashboardLists(el);
          if (lists.activeUl) {
            // Hydrate active from local snapshot if available
            try {
              const raw = localStorage.getItem('wf.dashboard.sections');
              const activeSnap = raw ? JSON.parse(raw) : [];
              if (Array.isArray(activeSnap) && activeSnap.length) {
                activeSnap.forEach(item => lists.activeUl.appendChild(lists.makeLi(item, true)));
              }
            } catch(_) {}
            let avail = alt?.data?.available_sections || alt?.available_sections || {};
            if (!avail || Object.keys(avail).length === 0) {
              // Seed defaults if still empty
              avail = {
                metrics: { title: '📊 Quick Metrics' },
                recent_orders: { title: '📋 Recent Orders' },
                low_stock: { title: '⚠️ Low Stock Alerts' },
                inventory_summary: { title: '📦 Inventory Summary' },
                customer_summary: { title: '👥 Customer Overview' },
                marketing_tools: { title: '📈 Marketing Tools' },
                order_fulfillment: { title: '🚚 Order Fulfillment' },
                reports_summary: { title: '📊 Reports Summary' },
              };
            }
            Object.keys(avail).forEach((key) => {
              const item = { key, title: avail[key]?.title || key };
              lists.availUl.appendChild(lists.makeLi(item, false));
            });
            // Optionally show a subtle note (omit to reduce noise)
          }
        } catch (err2) {
          console.error('[DashboardConfigFallback] fallback load failed', err2);
          // As a last resort, render seeded defaults so the UI is still functional
          const lists = renderDashboardLists(el);
          if (lists.activeUl) {
            // Hydrate active from local snapshot if available first
            try {
              const raw = localStorage.getItem('wf.dashboard.sections');
              const activeSnap = raw ? JSON.parse(raw) : [];
              if (Array.isArray(activeSnap) && activeSnap.length) {
                activeSnap.forEach(item => {
                  const it = {
                    key: item.key || item.section_key,
                    section_key: item.section_key || item.key,
                    title: item.title || item.section_key || item.key,
                    is_active: 1
                  };
                  lists.activeUl.appendChild(lists.makeLi(it, true));
                });
              }
            } catch(_) {}
            const seeded = {
              metrics: { title: '📊 Quick Metrics' },
              recent_orders: { title: '📋 Recent Orders' },
              low_stock: { title: '⚠️ Low Stock Alerts' },
              inventory_summary: { title: '📦 Inventory Summary' },
              customer_summary: { title: '👥 Customer Overview' },
              marketing_tools: { title: '📈 Marketing Tools' },
              order_fulfillment: { title: '🚚 Order Fulfillment' },
              reports_summary: { title: '📊 Reports Summary' },
            };
            Object.keys(seeded).forEach((key) => {
              const item = { key, title: seeded[key]?.title || key };
              lists.availUl.appendChild(lists.makeLi(item, false));
            });
            // Keep UI clean; no visible error line
          }
          // Also run diagnostics to reveal DB status in console for quick triage
          try {
            const d = await dashApi('/api/dashboard_sections.php?action=diagnostics');
            console.info('[DashboardConfigFallback] diagnostics', d);
          } catch(_) {}
        }
      } finally {
        body.classList.remove('is-busy');
      }
    }

    // Lightweight feature loaders
    async function loadBackgroundManager(modalId = 'backgroundManagerModal'){
      try {
        const el = getModalEl(modalId);
        await import('../modules/background-manager.js').then(m => { if (m && typeof m.init === 'function') m.init(el); });
      } catch (e) {
        console.warn('[AdminSettingsBridge] background-manager unavailable', e);
      }
    }
    async function loadCssRulesManager(modalId = 'cssRulesModal'){
      try {
        const el = getModalEl(modalId);
        await import('../modules/css-rules-manager.js').then(m => { if (m && typeof m.init === 'function') m.init(el); });
      } catch (e) {
        console.error('[AdminSettingsBridge] Failed to init CSS Rules Manager', e);
      }
    }

// Ensure the heavy legacy module is loaded when needed (idempotent)
async function ensureLegacyLoaded() {
  if (window.__WF_ADMIN_SETTINGS_LEGACY_LOADED) return true;
  try {
    await import('../js/admin-settings.js');
    window.__WF_ADMIN_SETTINGS_LEGACY_LOADED = true;
    return true;
  } catch (e) {
    console.error('[AdminSettingsBridge] Failed to load legacy admin-settings.js', e);
    return false;
  }
}
    

    // Delegated clicks for opening/closing modals and routing
    document.addEventListener('click', (e) => {
      const t = e.target;
      const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
      const managedOverlays = new Set([
        'businessInfoModal',
        'squareSettingsModal',
        'emailSettingsModal',
        'loggingStatusModal',
        'aiSettingsModal',
        'aiToolsModal',
        'secretsModal',
        'cssRulesModal',
        'backgroundManagerModal',
        'receiptSettingsModal',
        'dbMaintenanceModal',
        'dashboardConfigModal',
        'fileExplorerModal',
        'roomSettingsModal',
        'roomCategoryLinksModal',
        'templateManagerModal',
        'emailHistoryModal',
        'accountSettingsModal',
      ]);

      // Overlay click closes only when the actual overlay backdrop is clicked (not inner content)
      if (t && t.classList && (t.classList.contains('admin-modal-overlay') || t.classList.contains('modal-overlay'))) {
        const overlayId = t.id;
        if (overlayId && managedOverlays.has(overlayId)) {
          e.preventDefault();
          if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
          else e.stopPropagation();
          hideModal(overlayId);
          return;
        }
      }

      // Generic close button inside any managed modal
      if (closest('[data-action="close-admin-modal"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const overlay = closest('.admin-modal-overlay');
        if (overlay && managedOverlays.has(overlay.id)) hideModal(overlay.id);
        return;
      }

      // Generic pattern: any data-action that starts with "close-" should close the nearest managed overlay
      // This future-proofs new modals that adopt specific close actions without needing explicit wiring here
      const closeActionEl = closest('[data-action]');
      if (closeActionEl) {
        const act = closeActionEl.getAttribute('data-action') || '';
        if (/^close-[a-z0-9_-]+$/i.test(act)) {
          e.preventDefault();
          if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
          const overlay = closest('.admin-modal-overlay');
          if (overlay && managedOverlays.has(overlay.id)) {
            hideModal(overlay.id);
            return;
          }
        }
      }

      // Business Info
      if (closest('[data-action="open-business-info"]')) { try { console.info('[AdminSettingsBridge] click open-business-info'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('businessInfoModal', (t.closest('button, a, [tabindex]')||t)); loadBusinessInfo(); showModal('businessInfoModal'); setTimeout(()=>{ try { wireBrandingLivePreview(); } catch(_) {} }, 0); return; }
      if (closest('[data-action="close-business-info"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('businessInfoModal'); return; }
      if (closest('#businessInfoModal [data-action="business-save"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); saveBusinessInfo(); return; }
      // Brand Vars tooltip
      if (closest('#businessInfoModal [data-action="open-brand-vars-help"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const el = document.getElementById('brandVarsHelp'); if (el) { el.classList.remove('hidden'); const btn = closest('[data-action="open-brand-vars-help"]'); if (btn) btn.setAttribute('aria-expanded','true'); } return; }
      if (closest('#businessInfoModal [data-action="close-brand-vars-help"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const el = document.getElementById('brandVarsHelp'); if (el) { el.classList.add('hidden'); const btn = document.querySelector('#businessInfoModal [data-action="open-brand-vars-help"]'); if (btn) btn.setAttribute('aria-expanded','false'); } return; }
      if (closest('#businessInfoModal [data-action="business-reset-branding"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        // Reset inputs to sensible defaults
        try {
          const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
          set('brandPrimary', '#0ea5e9');
          set('brandSecondary', '#6366f1');
          set('brandAccent', '#22c55e');
          set('brandBackground', '#ffffff');
          set('brandText', '#111827');
          set('brandFontPrimary', '');
          set('brandFontSecondary', '');
          set('customCssVars', '');
          // Apply immediately
          applyBusinessCssToRoot({
            business_brand_primary: '#0ea5e9',
            business_brand_secondary: '#6366f1',
            business_brand_accent: '#22c55e',
            business_brand_background: '#ffffff',
            business_brand_text: '#111827',
            business_brand_font_primary: '',
            business_brand_font_secondary: '',
            business_css_vars: ''
          });
          const status = document.getElementById('businessInfoStatus'); if (status) { status.textContent = 'Branding reset (not yet saved)'; setTimeout(()=>{ if (status.textContent==='Branding reset (not yet saved)') status.textContent=''; }, 2000); }
        } catch(_) {}
        return;
      }

      // Square Settings
      if (closest('[data-action="open-square-settings"]')) { try { console.info('[AdminSettingsBridge] click open-square-settings'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('squareSettingsModal', (t.closest('button, a, [tabindex]')||t)); showModal('squareSettingsModal'); return; }
      if (closest('[data-action="close-square-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('squareSettingsModal'); return; }

      // Email Settings
      if (closest('[data-action="open-email-settings"]')) { try { console.info('[AdminSettingsBridge] click open-email-settings'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('emailSettingsModal', (t.closest('button, a, [tabindex]')||t)); try { initEmailSection().catch(()=>{}); } catch(_) {} showModal('emailSettingsModal'); return; }
      if (closest('[data-action="close-email-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('emailSettingsModal'); return; }
      // Open Email Test: open email modal and focus test input if present
      if (closest('[data-action="open-email-test"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('emailSettingsModal', (t.closest('button, a, [tabindex]')||t)); try { initEmailSection().catch(()=>{}); } catch(_) {} if (showModal('emailSettingsModal')) { const test = document.getElementById('testEmailAddress') || document.getElementById('testRecipient'); if (test) setTimeout(() => test.focus(), 50); } return; }
      // Secrets
      if (closest('[data-action="open-secrets-modal"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('secretsModal', (t.closest('button, a, [tabindex]')||t)); showModal('secretsModal'); return; }
      if (closest('#secretsModal [data-action="secrets-save"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const result = document.getElementById('secretsResult'); if (result) result.textContent = 'Saving…';
        const csrfEl = document.getElementById('secretsCsrf'); const csrf = csrfEl ? csrfEl.value : '';
        const payload = (document.getElementById('secretsPayload')?.value || '').trim();
        const url = '/api/secrets.php?action=save_batch&csrf=' + encodeURIComponent(csrf);
        fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ payload }) })
          .then(r => r.json().catch(()=>({})))
          .then(j => { if (result) result.textContent = j?.success ? (`Saved ${j.saved||0}, deleted ${j.deleted||0}`) : (j?.error || 'Save failed'); })
          .catch(err => { if (result) result.textContent = err?.message || 'Save failed'; });
        return;
      }
      if (closest('#secretsModal [data-action="secrets-rotate"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const result = document.getElementById('secretsResult'); if (result) result.textContent = 'Rotating keys…';
        const csrfEl = document.getElementById('secretsCsrf'); const csrf = csrfEl ? csrfEl.value : '';
        const url = '/api/secrets.php?action=rotate_keys&csrf=' + encodeURIComponent(csrf);
        fetch(url, { method: 'POST' })
          .then(r => r.json().catch(()=>({})))
          .then(j => { if (result) result.textContent = j?.success ? (`Re-encrypted ${j.re_encrypted||0}, failed ${j.failed||0}`) : (j?.error || 'Rotate failed'); })
          .catch(err => { if (result) result.textContent = err?.message || 'Rotate failed'; });
        return;
      }
      if (closest('#secretsModal [data-action="secrets-export"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const result = document.getElementById('secretsResult'); if (result) result.textContent = 'Exporting…';
        fetch('/api/secrets.php?action=export')
          .then(r => r.json().catch(()=>({})))
          .then(j => { if (result) result.textContent = j?.success ? (`Keys: ${Array.isArray(j.keys)? j.keys.join(', ') : ''}`) : (j?.error || 'Export failed'); })
          .catch(err => { if (result) result.textContent = err?.message || 'Export failed'; });
        return;
      }
      // Email History (dedicated UI)
      if (closest('[data-action="open-email-history"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('emailHistoryModal', (t.closest('button, a, [tabindex]')||t)); showModal('emailHistoryModal'); resetEmailHistory(); loadEmailTypeOptions(); return; }

      // Logging Status
      if (closest('[data-action="open-logging-status"]')) { try { console.info('[AdminSettingsBridge] click open-logging-status'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('loggingStatusModal', (t.closest('button, a, [tabindex]')||t)); showModal('loggingStatusModal'); return; }
      if (closest('[data-action="close-logging-status"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('loggingStatusModal'); return; }

      // Email History controls
      if (closest('#emailHistoryModal [data-action="email-history-refresh"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); if (emailHistoryState.mode === 'search') { searchEmailHistory(emailHistoryState.query); } else { loadEmailHistory(emailHistoryState.page); } return; }
      if (closest('#emailHistoryModal [data-action="email-history-search"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const q = (document.getElementById('emailHistorySearch')?.value || '').trim(); if (q) { searchEmailHistory(q); } else { resetEmailHistory(); } return; }
      if (closest('#emailHistoryModal [data-action="email-history-prev"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); if (emailHistoryState.mode !== 'search') { if (emailHistoryState.page > 1) { loadEmailHistory(emailHistoryState.page - 1); } } return; }
      if (closest('#emailHistoryModal [data-action="email-history-next"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); if (emailHistoryState.mode !== 'search') { loadEmailHistory(emailHistoryState.page + 1); } return; }
      if (closest('#emailHistoryModal [data-action="email-history-download"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); try { const qp = new URLSearchParams({ action: 'download_log', type: 'email_logs' }); if (emailHistoryState.from) qp.set('from', emailHistoryState.from); if (emailHistoryState.to) qp.set('to', emailHistoryState.to); if (emailHistoryState.status) qp.set('status', emailHistoryState.status); if (emailHistoryState.type) qp.set('email_type', emailHistoryState.type); if (emailHistoryState.sort) qp.set('sort', emailHistoryState.sort); window.open(`/api/website_logs.php?${qp.toString()}`, '_blank'); } catch(_) {} return; }
      if (closest('#emailHistoryModal [data-action="email-history-apply-filters"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); emailHistoryState.from = (document.getElementById('emailHistoryFrom')?.value || '').trim(); emailHistoryState.to = (document.getElementById('emailHistoryTo')?.value || '').trim(); emailHistoryState.status = (document.getElementById('emailHistoryStatusFilter')?.value || '').trim(); emailHistoryState.type = (document.getElementById('emailHistoryType')?.value || '').trim(); emailHistoryState.sort = (document.getElementById('emailHistorySort')?.value || 'sent_at_desc'); emailHistoryState.mode = 'list'; emailHistoryState.page = 1; loadEmailHistory(1); return; }
      if (closest('#emailHistoryModal [data-action="email-history-clear-filters"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const f = document.getElementById('emailHistoryFrom'); if (f) f.value=''; const t2 = document.getElementById('emailHistoryTo'); if (t2) t2.value=''; const st = document.getElementById('emailHistoryStatusFilter'); if (st) st.value=''; const et = document.getElementById('emailHistoryType'); if (et) et.value=''; const so = document.getElementById('emailHistorySort'); if (so) so.value='sent_at_desc'; emailHistoryState.from = ''; emailHistoryState.to = ''; emailHistoryState.status = ''; emailHistoryState.type = ''; emailHistoryState.sort = 'sent_at_desc'; resetEmailHistory(); return; }
      // (apply-filters handled above)
      if (closest('#emailHistoryModal [data-action="email-history-toggle-raw"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const btn = closest('[data-action="email-history-toggle-raw"]'); const container = btn && btn.closest('div'); const root = btn && btn.closest('.p-2.text-sm') || btn && btn.closest('.p-2'); const raw = root && root.querySelector('.email-history-raw'); if (raw && raw.classList) { raw.classList.toggle('hidden'); btn.textContent = raw.classList.contains('hidden') ? 'View raw JSON' : 'Hide raw JSON'; } return; }
      if (closest('#emailHistoryModal [data-action="email-history-open-drawer"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const btn = closest('[data-action="email-history-open-drawer"]'); const root = btn && (btn.closest('.p-2.text-sm') || btn.closest('.p-2')); if (root) { try { const idx = Number(btn.getAttribute('data-index')||''); const row = (Array.isArray(emailHistoryState.lastEntries) && !isNaN(idx)) ? emailHistoryState.lastEntries[idx] : null; if (row) openEmailDrawer(row, { index: idx, animate: true }); } catch(_) {} } return; }
      if (closest('#emailHistoryModal [data-action="email-history-close-drawer"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); closeEmailDrawer(); return; }
      // Overlay click to close
      if (t && t.id === 'emailHistoryDrawerOverlay') { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); closeEmailDrawer(); return; }
      // Copy cURL (POST) of selected row
      if (closest('#emailHistoryModal [data-action="email-history-copy-curl"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); try { const row = emailHistoryState.drawerRow || null; if (!row) return; let jsonText = ''; try { jsonText = JSON.stringify(row, null, 2); } catch(_) { jsonText = String(row); } const endpoint = getEmailTestEndpoint() || 'https://your-endpoint.example/ingest'; const escaped = jsonText.replace(/'/g, "'\\''"); const cmd = "curl -sS -X POST '" + endpoint + "' -H 'Content-Type: application/json' --data '" + escaped + "'"; const status = document.getElementById('emailHistoryStatus'); if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(cmd).then(()=>{ if (status) status.textContent = 'Copied cURL'; setTimeout(()=>{ if (status && status.textContent==='Copied cURL') status.textContent=''; }, 1200); }); } else { const ta=document.createElement('textarea'); ta.value=cmd; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); if (status) status.textContent='Copied cURL'; setTimeout(()=>{ if (status && status.textContent==='Copied cURL') status.textContent=''; }, 1200); } } catch(_) {} return; }
      // Save endpoint
      if (closest('#emailHistoryModal [data-action="email-history-save-endpoint"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); const input = document.getElementById('ehdEndpoint'); const status = document.getElementById('emailHistoryStatus'); try { const v = (input && input.value ? input.value.trim() : ''); if (!/^https?:\/\//i.test(v)) { if (status) status.textContent = 'Enter a valid http(s) URL'; if (input) input.focus(); return; } setEmailTestEndpoint(v); if (status) status.textContent = 'Endpoint saved'; setTimeout(()=>{ if (status && status.textContent==='Endpoint saved') status.textContent=''; }, 1200); } catch(_) {} return; }
      // Open endpoint in new tab
      if (closest('#emailHistoryModal [data-action="email-history-open-test"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); const status = document.getElementById('emailHistoryStatus'); const input = document.getElementById('ehdEndpoint'); try { const url = getEmailTestEndpoint(); if (url) { window.open(url, '_blank'); if (status) status.textContent = 'Opened endpoint in new tab'; setTimeout(()=>{ if (status && status.textContent==='Opened endpoint in new tab') status.textContent=''; }, 1200); } else { if (status) status.textContent = 'Set the endpoint first'; if (input) input.focus(); setTimeout(()=>{ if (status && status.textContent==='Set the endpoint first') status.textContent=''; }, 1500); } } catch(_) {} return; }
      // Toggle Pretty/Minify JSON
      if (closest('#emailHistoryModal [data-action="email-history-toggle-json"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); try { const row = emailHistoryState.drawerRow || null; const pre = document.getElementById('ehdJson'); const btn = closest('[data-action="email-history-toggle-json"]'); if (!row || !pre || !btn) return; const pretty = !emailHistoryState.drawerJsonPretty; let txt = ''; try { txt = JSON.stringify(row, pretty ? null : undefined, pretty ? 2 : undefined); } catch(_) { txt = String(row); } pre.textContent = txt; emailHistoryState.drawerJsonPretty = pretty; btn.textContent = pretty ? 'Minify JSON' : 'Pretty JSON'; } catch(_) {} return; }
      // Copy Headers (Subject, To, From, Type, Status, Sent)
      if (closest('#emailHistoryModal [data-action="email-history-copy-headers"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); try { const row = emailHistoryState.drawerRow || null; if (!row) return; const fmt = (s) => { try { return new Date(s).toLocaleString(); } catch(_) { return s; } }; const lines = [];
        const from = row.from_email || ''; const to = row.to_email || row.to || ''; const sub = row.email_subject || row.subject || ''; const type = row.email_type || ''; const status = row.status || ''; const ts = row.sent_at || row.created_at || row.timestamp || '';
        if (from) lines.push('From: ' + from);
        if (to) lines.push('To: ' + to);
        if (sub) lines.push('Subject: ' + sub);
        if (type) lines.push('Type: ' + type);
        if (status) lines.push('Status: ' + status);
        if (ts) lines.push('Sent-At: ' + fmt(ts));
        const headers = lines.join('\n'); const statusEl = document.getElementById('emailHistoryStatus');
        if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(headers).then(()=>{ if (statusEl) statusEl.textContent='Copied headers'; setTimeout(()=>{ if (statusEl && statusEl.textContent==='Copied headers') statusEl.textContent=''; }, 1200); }); }
        else { const ta=document.createElement('textarea'); ta.value=headers; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); if (statusEl) statusEl.textContent='Copied headers'; setTimeout(()=>{ if (statusEl && statusEl.textContent==='Copied headers') statusEl.textContent=''; }, 1200); }
      } catch(_) {} return; }
      if (closest('#emailHistoryModal [data-action="email-history-copy-subject"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const v = document.getElementById('ehdSubject')?.textContent || ''; const status = document.getElementById('emailHistoryStatus'); try { if (v) { (navigator.clipboard?.writeText ? navigator.clipboard.writeText(v) : Promise.reject()).then(()=>{ if (status) status.textContent = 'Copied subject'; setTimeout(()=>{ if (status && status.textContent==='Copied subject') status.textContent=''; }, 1200); }).catch(()=>{ const ta=document.createElement('textarea'); ta.value=v; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); if (status) status.textContent='Copied subject'; setTimeout(()=>{ if (status && status.textContent==='Copied subject') status.textContent=''; }, 1200); }); } } catch(_) {} return; }
      if (closest('#emailHistoryModal [data-action="email-history-copy-to"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const v = document.getElementById('ehdTo')?.textContent || ''; const status = document.getElementById('emailHistoryStatus'); try { if (v) { (navigator.clipboard?.writeText ? navigator.clipboard.writeText(v) : Promise.reject()).then(()=>{ if (status) status.textContent = 'Copied to'; setTimeout(()=>{ if (status && status.textContent==='Copied to') status.textContent=''; }, 1200); }).catch(()=>{ const ta=document.createElement('textarea'); ta.value=v; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); if (status) status.textContent='Copied to'; setTimeout(()=>{ if (status && status.textContent==='Copied to') status.textContent=''; }, 1200); }); } } catch(_) {} return; }
      if (closest('#emailHistoryModal [data-action="email-history-copy-type"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const v = document.getElementById('ehdType')?.textContent || ''; const status = document.getElementById('emailHistoryStatus'); try { if (v) { (navigator.clipboard?.writeText ? navigator.clipboard.writeText(v) : Promise.reject()).then(()=>{ if (status) status.textContent = 'Copied type'; setTimeout(()=>{ if (status && status.textContent==='Copied type') status.textContent=''; }, 1200); }).catch(()=>{ const ta=document.createElement('textarea'); ta.value=v; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); if (status) status.textContent='Copied type'; setTimeout(()=>{ if (status && status.textContent==='Copied type') status.textContent=''; }, 1200); }); } } catch(_) {} return; }
      if (closest('#emailHistoryModal [data-action="email-history-copy-raw"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const btn = closest('[data-action="email-history-copy-raw"]'); const root = btn && (btn.closest('.p-2.text-sm') || btn.closest('.p-2')); const raw = root && root.querySelector('.email-history-raw'); const status = document.getElementById('emailHistoryStatus'); try { const txt = raw ? raw.textContent : ''; if (txt) { if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt).then(()=>{ if (status) status.textContent = 'Copied JSON to clipboard'; setTimeout(()=>{ if (status && status.textContent==='Copied JSON to clipboard') status.textContent=''; }, 1500); }); } else { const ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); if (status) status.textContent = 'Copied JSON to clipboard'; setTimeout(()=>{ if (status && status.textContent==='Copied JSON to clipboard') status.textContent=''; }, 1500); } } } catch(_) {} return; }
      if (closest('#emailHistoryModal [data-action="email-history-toggle"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); const btn = closest('[data-action="email-history-toggle"]'); const details = btn && btn.closest('div') && btn.closest('div').nextElementSibling; if (details && details.classList) { details.classList.toggle('hidden'); btn.textContent = details.classList.contains('hidden') ? 'View details' : 'Hide details'; } return; }

      // AI Settings
      if (closest('[data-action="open-ai-settings"]')) { try { console.info('[AdminSettingsBridge] click open-ai-settings'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('aiSettingsModal', (t.closest('button, a, [tabindex]')||t)); showModal('aiSettingsModal'); try { const f = document.getElementById('aiSettingsFrame'); if (f && f.getAttribute('src') === 'about:blank') { const ds = f.getAttribute('data-src'); if (ds) f.setAttribute('src', ds); } } catch(_) {} return; }
      if (closest('[data-action="close-ai-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('aiSettingsModal'); return; }

      // AI Tools
      if (closest('[data-action="open-ai-tools"]')) { try { console.info('[AdminSettingsBridge] click open-ai-tools'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('aiToolsModal', (t.closest('button, a, [tabindex]')||t)); showModal('aiToolsModal'); try { const f = document.getElementById('aiToolsFrame'); if (f && f.getAttribute('src') === 'about:blank') { const ds = f.getAttribute('data-src'); if (ds) f.setAttribute('src', ds); } } catch(_) {} return; }
      if (closest('[data-action="close-ai-tools"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('aiToolsModal'); return; }

      // Account Settings (iframe)
      if (closest('[data-action="open-account-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('accountSettingsModal', (t.closest('button, a, [tabindex]')||t)); showModal('accountSettingsModal'); try { const f = document.getElementById('accountSettingsFrame'); if (f && f.getAttribute('src') === 'about:blank') { const ds = f.getAttribute('data-src'); if (ds) f.setAttribute('src', ds); } } catch(_) {} return; }

      // CSS Rules
      if (closest('[data-action="open-css-rules"]')) { try { console.info('[AdminSettingsBridge] click open-css-rules'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('cssRulesModal', (t.closest('button, a, [tabindex]')||t)); if (showModal('cssRulesModal')) { loadCssRulesManager('cssRulesModal'); } return; }
      if (closest('[data-action="close-css-rules"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('cssRulesModal'); return; }

      // Background Manager
      if (closest('[data-action="open-background-manager"]')) { try { console.info('[AdminSettingsBridge] click open-background-manager'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('backgroundManagerModal', (t.closest('button, a, [tabindex]')||t)); if (showModal('backgroundManagerModal')) { loadBackgroundManager('backgroundManagerModal'); } return; }
      if (closest('[data-action="close-background-manager"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('backgroundManagerModal'); return; }

      // Receipt Settings
      if (closest('[data-action="open-receipt-settings"]')) { try { console.info('[AdminSettingsBridge] click open-receipt-settings'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('receiptSettingsModal', (t.closest('button, a, [tabindex]')||t)); showModal('receiptSettingsModal'); return; }
      if (closest('[data-action="close-receipt-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('receiptSettingsModal'); return; }

      // Dashboard Configuration
      if (closest('[data-action="open-dashboard-config"]')) {
        try { console.info('[AdminSettingsBridge] click open-dashboard-config'); } catch(_) {}
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        lastTriggerById.set('dashboardConfigModal', (t.closest('button, a, [tabindex]')||t));
        try {
          const el = document.getElementById('dashboardConfigModal');
          if (!el) return; // require markup to exist
          const body = el.querySelector('.modal-body');
          if (body) {
            const status = body.querySelector('#dashboardConfigResult') || document.createElement('div');
            if (!status.id) { status.id = 'dashboardConfigResult'; body.prepend(status); }
            status.textContent = 'Loading…';
          }
          // Show immediately, then populate (gives immediate visual feedback while loading)
          showModal('dashboardConfigModal');
          console.info('[AdminSettingsBridge] calling populateDashboardFallback');
          populateDashboardFallback('dashboardConfigModal')
            .then(() => { console.info('[DashboardConfigFallback] populated OK'); })
            .catch((err) => { console.error('[AdminSettingsBridge] dashboard populate failed', err); });
        } catch (err) {
          console.error('[AdminSettingsBridge] dashboard open failed', err);
          showModal('dashboardConfigModal');
        }
        return;
      }
      if (closest('[data-action="close-dashboard-config"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('dashboardConfigModal'); return; }

      // Dashboard Fallback: Add/Remove section buttons (delegated)
      if (closest('[data-action="dashboard-add-section"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const btn = closest('[data-action="dashboard-add-section"]');
        const li = btn && btn.closest('li');
        const modal = document.getElementById('dashboardConfigModal');
        const activeUl = modal && modal.querySelector('#dashboardActiveSections');
        if (li && activeUl) {
          const key = li.dataset.key || '';
          // Prevent duplicates in Active
          if (activeUl.querySelector(`li[data-key="${CSS && CSS.escape ? CSS.escape(key) : key}"]`)) return;
          activeUl.appendChild(li);
          btn.textContent = 'Remove';
          btn.setAttribute('data-action', 'dashboard-remove-section');
        }
        return;
      }
      if (closest('[data-action="dashboard-remove-section"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const btn = closest('[data-action="dashboard-remove-section"]');
      }

      // Database Maintenance
      if (closest('[data-action="open-db-maintenance"]')) { try { console.info('[AdminSettingsBridge] click open-db-maintenance'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('dbMaintenanceModal'); return; }
      if (closest('[data-action="close-db-maintenance"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('dbMaintenanceModal'); return; }
      if (closest('[data-action="open-database-tables"]')) { e.preventDefault(); e.stopPropagation(); if (!showModal('databaseTablesModal')) { window.location.href = '/admin.php?section=database_tables'; } return; }

      // File Explorer: support legacy button id and data-action
      if (closest('#fileExplorerBtn') || closest('[data-action="open-file-explorer"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        lastTriggerById.set('fileExplorerModal', (t.closest('button, a, [tabindex]')||t));
        // Load legacy module to wire up explorer handlers, then open
        ensureLegacyLoaded().then(() => {
          if (typeof window.openFileExplorerModal === 'function') {
            window.openFileExplorerModal();
          } else {
            // Fallback: just show the modal
            showModal('fileExplorerModal');
          }
        });
        return;
      }

      // Account Settings fallback navigation if no modal
      if (closest('[data-action="open-account-settings"]')) {
        e.preventDefault();
        e.stopPropagation();
        if (!showModal('accountSettingsModal')) {
          window.location.href = '/admin.php?section=account_settings';
        }
        return;
      }

      // Secrets Manager fallback navigation if no modal
      if (closest('[data-action="open-secrets-modal"]')) {
        e.preventDefault();
        e.stopPropagation();
        if (!showModal('secretsModal')) {
          window.location.href = '/admin.php?section=secrets';
        }
        return;
      }

      // Email History: route to dashboard/email history anchor for now
      if (closest('[data-action="open-email-history"]')) {
        e.preventDefault();
        e.stopPropagation();
        window.location.href = '/admin.php?section=dashboard#email-history';
        return;
      }
    });
  });
}

// Auto-init when imported
init();
