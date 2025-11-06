// Lazy modal factory: Background Manager (embeds dashboard #background)
// Lazy modal factory: Room Map Manager
import { ApiClient } from '../core/api-client.js';
const __wfEnsureRoomMapEditorModal = () => {
  let el = document.getElementById('roomMapManagerModal');
  // ALWAYS force recreation to pick up template changes
  if (el) {
    el.remove();
    el = null;
  }
  el = document.createElement('div');
  el.id = 'roomMapManagerModal';
  el.className = 'admin-modal-overlay wf-modal--content-scroll hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'roomMapManagerTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--room-map admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="roomMapManagerTitle" class="admin-card-title">🗺️ Room Map Manager (New Design)</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body rme-modal-body wf-modal-body--fill">
        <iframe id="roomMapManagerFrame" title="Room Map Manager" class="wf-admin-embed-frame wf-embed--fill" data-autosize="1" data-src="/sections/tools/room_map_editor.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try { wireOverlay(el); } catch(_) {}
  return el;
};

 

const __wfAI_fetchAndPopulateModels = async (provider, force, source) => {
  try {
    const prov = provider || 'jons_ai';
    const sel = document.getElementById(`${prov}_model`);
    if (!sel) return false;
    const prev = sel.value;
    sel.innerHTML = '<option value="">Loading models…</option>';
    const src = (typeof source === 'string' && source) ? source : (() => { try { const s = document.getElementById(`${prov}_model_source`); return s ? s.value : ''; } catch(_) { return ''; } })();
    const url = '/api/ai_settings.php?action=list_models&provider=' + encodeURIComponent(prov) + (src === 'openrouter' ? '&source=openrouter' : '') + (force ? '&force=1' : '') + '&_=' + Date.now();
    const isLocal = (() => { try { const h = window.location.hostname; return h === 'localhost' || h === '127.0.0.1'; } catch(_) { return false; } })();
    let data = null;
    try {
      if (window.WhimsicalFrog && window.WhimsicalFrog.api && typeof window.WhimsicalFrog.api.get === 'function') {
        data = await window.WhimsicalFrog.api.get(url);
      } else if (window.ApiClient && typeof window.ApiClient.request === 'function') {
        data = await window.ApiClient.request(url, { method: 'GET', headers: isLocal ? { 'X-WF-Dev-Admin': '1' } : {} });
      } else {
        data = await ApiClient.request(url, { method: 'GET', headers: isLocal ? { 'X-WF-Dev-Admin': '1' } : {} });
      }
    } catch (e) {
      data = null;
    }
    const models = (data && data.success && Array.isArray(data.models)) ? data.models : [];
    if (!models.length) {
      __wfAI_populateModelDropdown(prov, prev || '');
      return false;
    }
    sel.innerHTML = '';
    for (const m of models) {
      const opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = (m.name && m.description) ? `${m.name} - ${m.description}` : (m.name || m.id);
      if (prev && prev === m.id) opt.selected = true;
      sel.appendChild(opt);
    }
    if (prev && !sel.querySelector(`option[value="${prev}"]`)) sel.selectedIndex = 0;
    return true;
  } catch(_) { return false; }
};

const __wfAI_describePersonality = (num) => {
  const v = Number(num);
  if (!isFinite(v)) return '';
  if (v <= 0.33) return 'Reserved';
  if (v >= 0.75) return 'Adventurous';
  return 'Balanced';
};

const __wfAI_updatePersonalityDisplay = () => {
  try {
    const t = document.getElementById('aiTemperature');
    const tv = document.getElementById('aiTemperatureValue');
    if (!t || !tv) return;
    const desc = __wfAI_describePersonality(t.value);
    tv.textContent = `${Number(t.value).toFixed(2)} • ${desc}`;
  } catch(_) {}
};

// Lazy modal factory: Action Icons Manager (configure icon legend)
const __wfEnsureActionIconsManagerModal = () => {
  let el = document.getElementById('actionIconsManagerModal');
  if (el) {
    try {
      // Use single-scroll parent with body fill and iframe fill (parent-scroll like Area Mappings)
      el.classList.add('over-header','wf-modal-autowide','wf-modal-single-scroll','wf-modal-closable');
      el.classList.remove('wf-modal--content-scroll');
      const panel = el.querySelector('.admin-modal');
      if (panel) {
        panel.classList.add('admin-modal--actions-in-header','admin-modal--responsive');
        panel.classList.remove('admin-modal--sm','admin-modal--md','admin-modal--lg','admin-modal--lg-narrow','admin-modal--xl','admin-modal--full','admin-modal--square-200','admin-modal--square-260','admin-modal--square-300');
      }
      const body = el.querySelector('.modal-body');
      if (body) { body.classList.add('wf-modal-body--fill'); body.classList.remove('wf-modal-body--autoheight'); }
      const frame = el.querySelector('#actionIconsManagerFrame');
      if (frame) frame.classList.add('wf-embed--fill');
      try { if (typeof markOverlayResponsive === 'function') markOverlayResponsive(el); } catch(_) {}
      try { if (typeof attachSameOriginFallback === 'function' && frame) attachSameOriginFallback(frame, el); } catch(_) {}
    } catch(_) {}
    return el;
  }
  el = document.createElement('div');
  el.id = 'actionIconsManagerModal';
  el.className = 'admin-modal-overlay over-header wf-modal-autowide wf-modal-single-scroll wf-modal-closable hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'actionIconsManagerTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="actionIconsManagerTitle" class="admin-card-title">🧰 Button Manager</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--fill">
        <iframe id="actionIconsManagerFrame" title="Button Manager" class="wf-admin-embed-frame wf-embed--fill" data-autosize="1" data-src="/sections/tools/action_icons_manager.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try {
    wireOverlay(el);
    const body = el.querySelector('.modal-body');
    if (body) { body.classList.add('wf-modal-body--fill'); body.classList.remove('wf-modal-body--autoheight'); }
    const frame = el.querySelector('#actionIconsManagerFrame');
    if (frame) { frame.classList.add('wf-embed--fill'); }
    try { if (typeof markOverlayResponsive === 'function') markOverlayResponsive(el); } catch(_) {}
    try { if (typeof attachSameOriginFallback === 'function' && frame) attachSameOriginFallback(frame, el); } catch(_) {}
  } catch(_) {}
  return el;
};

// Lazy modal factory: Reports & Documentation Browser
const __wfEnsureReportsBrowserModal = () => {
  let el = document.getElementById('reportsBrowserModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'reportsBrowserModal';
  el.className = 'admin-modal-overlay wf-modal--content-scroll hidden over-header';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'reportsBrowserTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="reportsBrowserTitle" class="admin-card-title">Reports &amp; Documentation Browser</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body admin-modal-body--lg">
        <iframe id="reportsBrowserFrame" title="Reports &amp; Documentation Browser" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-autosize="1" data-src="/sections/tools/reports_browser.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

// Lazy modal factory: Admin Modal Markup Guide (Markdown viewer)
const __wfEnsureModalMarkupGuideModal = () => {
  let el = document.getElementById('modalMarkupGuideModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'modalMarkupGuideModal';
  el.className = 'admin-modal-overlay wf-modal--content-scroll hidden over-header';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'modalMarkupGuideTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="modalMarkupGuideTitle" class="admin-card-title">Admin Modal Markup Guide</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="modalMarkupGuideFrame" title="Admin Modal Markup Guide" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-autosize="1" data-src="/sections/tools/md_viewer.php?modal=1&amp;file=documentation/ADMIN_MODAL_MARKUP_GUIDE.md" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

// Lazy modal factory: Area-Item Mapper
const __wfEnsureAreaItemMapperModal = () => {
  let el = document.getElementById('areaItemMapperModal');
  // ALWAYS force recreation to pick up template/style changes
  if (el) { try { el.remove(); } catch(_) {}; el = null; }
  el = document.createElement('div');
  el.id = 'areaItemMapperModal';
  el.className = 'admin-modal-overlay over-header wf-modal-autowide wf-modal-mincols-3 wf-modal-single-scroll wf-modal-closable hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'areaItemMapperTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--actions-in-header admin-modal--responsive">
      <div class="modal-header">
        <h2 id="areaItemMapperTitle" class="admin-card-title">🧭 Area Mappings</h2>
        <div class="modal-header-actions">
          <span id="areaItemMapperStatus" class="text-sm text-gray-600" aria-live="polite"></span>
          <button type="button" id="areaItemMapperSave" class="btn btn-primary btn-sm">Save</button>
        </div>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--fill">
        <iframe id="areaItemMapperFrame" title="Area Mappings" class="wf-admin-embed-frame wf-embed--fill" data-autosize="1" data-measure-selector="#admin-section-content,.wf-grid-autofit-360,.aim-tab-panel,.admin-card" data-src="/sections/tools/area_item_mapper.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try {
    wireOverlay(el);
    const body = el.querySelector('.modal-body');
    if (body) { body.classList.add('wf-modal-body--fill'); body.classList.remove('wf-modal-body--autoheight'); }
    const frame = el.querySelector('#areaItemMapperFrame');
    if (frame) frame.classList.add('wf-embed--fill');
    const saveBtn = el.querySelector('#areaItemMapperSave');
    if (saveBtn) {
      saveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const f = el.querySelector('#areaItemMapperFrame');
        // Disable while saving begins
        try { saveBtn.disabled = true; saveBtn.dataset.prevLabel = saveBtn.textContent || ''; saveBtn.textContent = 'Saving…'; } catch(_) {}
        try { if (f && f.contentWindow) f.contentWindow.postMessage({ source:'wf-aim-parent', type:'save' }, '*'); } catch(_) {}
      });
    }
    if (!window.__wfAIMStatusListener) {
      window.addEventListener('message', (ev) => {
        try {
          const d = ev && ev.data; if (!d || d.source !== 'wf-aim') return;
          const s = document.getElementById('areaItemMapperStatus');
          const btn = document.getElementById('areaItemMapperSave');
          if (d.type === 'busy') {
            const busy = !!d.busy;
            if (btn) {
              try {
                btn.disabled = busy;
                if (busy) { btn.dataset.prevLabel = btn.textContent || ''; btn.textContent = 'Saving…'; }
                else { if (btn.dataset.prevLabel) { btn.textContent = btn.dataset.prevLabel; delete btn.dataset.prevLabel; } else { btn.textContent = 'Save'; } }
              } catch(_) {}
            }
            if (s && busy) { try { s.textContent = 'Saving…'; s.classList.remove('text-red-700'); s.classList.add('text-green-700'); } catch(_) {} }
            return;
          }
          if (d.type === 'status') {
            if (s) {
              s.textContent = d.message || '';
              s.classList.remove('text-green-700','text-red-700');
              s.classList.add(d.ok ? 'text-green-700' : 'text-red-700');
            }
            if (btn) {
              try { btn.disabled = false; if (btn.dataset.prevLabel) { btn.textContent = btn.dataset.prevLabel; delete btn.dataset.prevLabel; } else { btn.textContent = 'Save'; } } catch(_) {}
            }
            return;
          }
        } catch (_) {}
      });
      window.__wfAIMStatusListener = true;
    }
  } catch(_) {}
  return el;
};

// Lazy modal factory: Email Settings (iframe to standalone settings page)
const __wfEnsureEmailSettingsModal = () => {
  let el = document.getElementById('emailSettingsModal');
  // ALWAYS force recreation to pick up template/style changes
  if (el) { try { el.remove(); } catch(_) {} el = null; }
  el = document.createElement('div');
  el.id = 'emailSettingsModal';
  el.className = 'admin-modal-overlay wf-modal--content-scroll hidden';
  el.setAttribute('data-modal', 'emailSettingsModal');
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'emailSettingsTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--xl admin-modal--responsive admin-modal--actions-in-header" id="wf-panel-auto-35">
      <div class="modal-header">
        <h2 id="emailSettingsTitle" class="admin-card-title">✉️ Email Settings</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--autoheight">
        <iframe id="emailSettingsFrame" title="Email Settings" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-autosize="1" data-resize-on-load="1" data-src="/sections/tools/email_settings.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try { wireOverlay(el); } catch(_) {}
  const iframe = el.querySelector('#emailSettingsFrame');
  if (iframe) {
    iframe.addEventListener('load', () => {
      try {
        if (window.__wfEmbedAutosize && typeof window.__wfEmbedAutosize.resize === 'function') {
          window.__wfEmbedAutosize.resize(iframe);
        }
      } catch (_) {}
    });
  }
  return el;
};

// Lazy modal factory: Receipt Messages (iframe to manager page)
const __wfEnsureReceiptMessagesModal = () => {
  let el = document.getElementById('receiptMessagesModal');
  if (el) {
    try {
      // Ensure overlay uses final autosize helpers
      el.classList.remove('wf-modal--content-scroll');
      el.classList.add('wf-modal-viewport-fill','wf-modal-single-scroll');
      const panel = el.querySelector('.admin-modal');
      if (panel) {
        panel.classList.remove('admin-modal--lg','admin-modal--lg-narrow');
        panel.classList.add('admin-modal--xl','admin-modal--actions-in-header');
      }
      // Remove legacy scroll helper from body if present
      const body = el.querySelector('.modal-body');
      if (body) body.classList.remove('wf-modal-body--scroll');
    } catch(_) {}
    return el;
  }
  el = document.createElement('div');
  el.id = 'receiptMessagesModal';
  // Autosize overlay to viewport and prefer a single inner scroll
  el.className = 'admin-modal-overlay wf-modal-viewport-fill wf-modal-single-scroll hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'receiptMessagesTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--xl admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="receiptMessagesTitle" class="admin-card-title">🧾 Receipt Messages</h2>
        <div class="modal-header-actions">
          <span id="receiptMessagesStatus" class="text-sm text-gray-600" aria-live="polite"></span>
          <button type="button" id="receiptMessagesSave" class="btn btn-primary btn-sm">Save</button>
        </div>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--fill">
        <iframe id="receiptMessagesFrame" title="Receipt Messages" class="wf-admin-embed-frame wf-embed--fill" data-autosize="1" data-src="/sections/tools/receipt_messages_manager.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try {
    // No extra scroll helper; CSS handles iframe fill and overlay sizing
    const saveBtn = el.querySelector('#receiptMessagesSave');
    if (saveBtn) {
      saveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const f = el.querySelector('#receiptMessagesFrame');
        try { if (f && f.contentWindow) f.contentWindow.postMessage({ source:'wf-rm-parent', type:'save' }, '*'); } catch(_) {}
      });
    }
  } catch(_) {}
  try {
    if (!window.__wfRMStatusListener) {
      window.addEventListener('message', (ev) => {
        try {
          const d = ev && ev.data; if (!d || d.source !== 'wf-rm' || d.type !== 'status') return;
          const s = document.getElementById('receiptMessagesStatus');
          if (s) {
            s.textContent = d.message || '';
            s.classList.remove('text-green-700','text-red-700');
            s.classList.add(d.ok ? 'text-green-700' : 'text-red-700');
          }
        } catch (_) {}
      });
      window.__wfRMStatusListener = true;
    }
  } catch(_) {}
  return el;
};

// Lazy modal factory: Cart Button Texts (iframe to manager page)
const __wfEnsureCartButtonTextsModal = () => {
  let el = document.getElementById('cartButtonTextsModal');
  if (el) {
    try {
      // Normalize overlay classes to shared autosizing pattern
      el.classList.add('over-header','wf-modal-autowide','wf-modal-single-scroll','wf-modal-closable');
      el.classList.remove('wf-modal--content-scroll');
      const panel = el.querySelector('.admin-modal');
      if (panel) {
        panel.classList.remove('admin-modal--lg','admin-modal--lg-narrow','admin-modal--md','admin-modal--xl','admin-modal--full','admin-modal--sm','admin-modal--xs','admin-modal--square-200','admin-modal--square-260');
        panel.classList.add('admin-modal--square-300');
      }
    } catch(_) {}
    return el;
  }
  el = document.createElement('div');
  el.id = 'cartButtonTextsModal';
  el.className = 'admin-modal-overlay over-header wf-modal-autowide wf-modal-single-scroll wf-modal-closable hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'cartButtonTextsTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--square-300 admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="cartButtonTextsTitle" class="admin-card-title">🛒 Cart Button Texts</h2>
          <div class="modal-header-actions">
            <span id="cartButtonTextsStatus" class="text-sm text-gray-600" aria-live="polite"></span>
            <button type="button" id="cartButtonTextsSave" class="btn btn-primary btn-sm">Save</button>
          </div>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
      <div class="modal-body wf-modal-body--fill">
        <iframe id="cartButtonTextsFrame" title="Cart Button Texts" class="wf-admin-embed-frame wf-embed--fill" data-autosize="1" data-src="/sections/tools/cart_button_texts.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try {
    const body = el.querySelector('.modal-body');
    if (body) body.classList.add('wf-modal-body--fill');
    const frame = el.querySelector('#cartButtonTextsFrame');
    if (frame) frame.classList.add('wf-embed--fill');
    const saveBtn = el.querySelector('#cartButtonTextsSave');
    if (saveBtn) {
      saveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const f = el.querySelector('#cartButtonTextsFrame');
        try { if (f && f.contentWindow) f.contentWindow.postMessage({ source:'wf-cbt-parent', type:'save' }, '*'); } catch(_) {}
      });
    }
  } catch(_) {}
  try {
    if (!window.__wfCBTStatusListener) {
      window.addEventListener('message', (ev) => {
        try {
          const d = ev && ev.data; if (!d || d.source !== 'wf-cbt' || d.type !== 'status') return;
          const s = document.getElementById('cartButtonTextsStatus');
          if (s) {
            s.textContent = d.message || '';
            s.classList.remove('text-green-700','text-red-700');
            s.classList.add(d.ok ? 'text-green-700' : 'text-red-700');
          }
        } catch (_) {}
      });
      window.__wfCBTStatusListener = true;
    }
  } catch(_) {}
  return el;
};

// Lazy modal factory: Shop Encouragement Phrases (iframe to manager page)
const __wfEnsureShopEncouragementsModal = () => {
  let el = document.getElementById('shopEncouragementsModal');
  if (el) {
    // Ensure small size even if an older instance was created
    try {
      const panel = el.querySelector('.admin-modal');
      if (panel) {
        panel.classList.remove('admin-modal--lg','admin-modal--lg-narrow','admin-modal--md','admin-modal--xl','admin-modal--full','admin-modal--sm','admin-modal--xs','admin-modal--square-200','admin-modal--square-260');
        panel.classList.add('admin-modal--square-300','admin-modal--actions-in-header');
      }
    } catch(_) {}
    return el;
  }
  el = document.createElement('div');
  el.id = 'shopEncouragementsModal';
  el.className = 'admin-modal-overlay wf-modal--content-scroll hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'shopEncouragementsTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--square-300 admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="shopEncouragementsTitle" class="admin-card-title">🏷️ Shop Encouragement Phrases</h2>
          <div class="modal-header-actions">
            <span id="shopEncouragementsStatus" class="text-sm text-gray-600" aria-live="polite"></span>
            <button type="button" id="shopEncouragementsSave" class="btn btn-primary btn-sm">Save</button>
          </div>
          <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
        </div>
      <div class="modal-body wf-modal-body--fill">
        <iframe id="shopEncouragementsFrame" title="Shop Encouragement Phrases" class="wf-admin-embed-frame wf-embed--fill" data-autosize="1" data-src="/sections/tools/shop_encouragement_phrases.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try {
    const body = el.querySelector('.modal-body');
    if (body) body.classList.add('wf-modal-body--fill');
    const frame = el.querySelector('#shopEncouragementsFrame');
    if (frame) frame.classList.add('wf-embed--fill');
    const saveBtn = el.querySelector('#shopEncouragementsSave');
    if (saveBtn) {
      saveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const f = el.querySelector('#shopEncouragementsFrame');
        try { if (f && f.contentWindow) f.contentWindow.postMessage({ source:'wf-sep-parent', type:'save' }, '*'); } catch(_) {}
      });
    }
  } catch(_) {}
  try {
    if (!window.__wfSEPStatusListener) {
      window.addEventListener('message', (ev) => {
        try {
          const d = ev && ev.data; if (!d || d.source !== 'wf-sep' || d.type !== 'status') return;
          const s = document.getElementById('shopEncouragementsStatus');
          if (s) {
            s.textContent = d.message || '';
            s.classList.remove('text-green-700','text-red-700');
            s.classList.add(d.ok ? 'text-green-700' : 'text-red-700');
          }
        } catch (_) {}
      });
      window.__wfSEPStatusListener = true;
    }
  } catch(_) {}
  return el;
};
// Lazy modal factory: Template Manager (reports/doc browser)
const __wfEnsureTemplateManagerModal = () => {
  let el = document.getElementById('templateManagerModal');
  if (el) {
    try {
      const panel = el.querySelector('.admin-modal');
      if (panel) panel.classList.add('admin-modal--actions-in-header');
    } catch(_) {}
    return el;
  }
  el = document.createElement('div');
  el.id = 'templateManagerModal';
  el.className = 'admin-modal-overlay wf-modal--content-scroll hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'templateManagerTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--responsive admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="templateManagerTitle" class="admin-card-title">📧 Email Templates</h2>
        <div class="modal-header-actions">
          <span id="templateManagerStatus" class="text-sm text-gray-600" aria-live="polite"></span>
          <button type="button" id="templateManagerSave" class="btn btn-primary btn-sm">Save</button>
        </div>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--autoheight">
        <iframe id="templateManagerFrame" title="Email Templates" class="wf-admin-embed-frame" data-autosize="1" data-src="/sections/tools/template_manager.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try {
    const saveBtn = el.querySelector('#templateManagerSave');
    if (saveBtn) {
      saveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const f = el.querySelector('#templateManagerFrame');
        try { if (f && f.contentWindow) f.contentWindow.postMessage({ source:'wf-tm-parent', type:'save' }, '*'); } catch(_) {}
      });
    }
  } catch(_) {}
  try { wireOverlay(el); } catch(_) {}
  try {
    if (!window.__wfTMStatusListener) {
      window.addEventListener('message', (ev) => {
        try {
          const d = ev && ev.data; if (!d || d.source !== 'wf-tm' || d.type !== 'status') return;
          const s = document.getElementById('templateManagerStatus');
          if (s) {
            s.textContent = d.message || '';
            s.classList.remove('text-green-700','text-red-700');
            s.classList.add(d.ok ? 'text-green-700' : 'text-red-700');
          }
        } catch (_) {}
      });
      window.__wfTMStatusListener = true;
    }
  } catch(_) {}
  return el;
};

// Lazy modal factory: Automation Manager (direct, no proxy)
const __wfEnsureAutomationModal = () => {
  let el = document.getElementById('automationModal');
  if (el) {
    try {
      const panel = el.querySelector('.admin-modal');
      if (panel) panel.classList.add('admin-modal--lg','admin-modal--actions-in-header');
      const body = el.querySelector('.modal-body');
      if (body) body.classList.add('wf-modal-body--fill');
      const frame = el.querySelector('#automationFrame');
      if (frame) frame.classList.add('wf-embed--fill');
    } catch(_) {}
    return el;
  }
  el = document.createElement('div');
  el.id = 'automationModal';
  el.className = 'admin-modal-overlay wf-modal--content-scroll hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'automationTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--responsive admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="automationTitle" class="admin-card-title">⚙️ Automation</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--autoheight">
        <iframe id="automationFrame" title="Automation" class="wf-admin-embed-frame" data-autosize="1" data-src="/sections/tools/automation_manager.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try { wireOverlay(el); } catch(_) {}
  return el;
};

// Lazy modal factory: Email History (uses Reports/Docs browser as a proxy)
const __wfEnsureEmailHistoryModal = () => {
  let el = document.getElementById('emailHistoryModal');
  if (el) {
    // If a legacy/static modal exists in DOM, ensure it uses the iframe-based UI
    const existingIframe = el.querySelector('#emailHistoryFrame');
    if (!existingIframe) {
      const body = el.querySelector('.modal-body');
      if (body) {
        body.innerHTML = '<iframe id="emailHistoryFrame" title="Email History" class="wf-admin-embed-frame" data-autosize="1" data-src="/sections/tools/email_history.php?modal=1" referrerpolicy="no-referrer"></iframe>';
      }
    }
    // Normalize panel size classes to smaller MD variant
    try {
      const panel = el.querySelector('.admin-modal');
      if (panel) {
        panel.classList.remove('admin-modal--lg','admin-modal--lg-narrow','admin-modal--xl','admin-modal--full','admin-modal--md','admin-modal--xs');
        panel.classList.add('admin-modal--sm');
      }
    } catch(_) {}
    return el;
  }
  el = document.createElement('div');
  el.id = 'emailHistoryModal';
  el.className = 'admin-modal-overlay wf-modal--content-scroll hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'emailHistoryTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--responsive admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="emailHistoryTitle" class="admin-card-title">📬 Email History</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body wf-modal-body--autoheight">
        <iframe id="emailHistoryFrame" title="Email History" class="wf-admin-embed-frame" data-autosize="1" data-src="/sections/tools/email_history.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try { wireOverlay(el); } catch(_) {}
  return el;
};
// Admin Settings Entry Point - Lightweight
// This replaces the large admin-settings.js with a lightweight coordinator
// that loads modules on-demand for better performance

// Import main styles
import '../styles/main.css';
import '../styles/components/components-base.css';
import '../styles/components/admin-nav.css';
// Ensure help chip overrides load after nav styles in this entry
import '../styles/admin-help-bubble.css';
import '../styles/admin-modals.css';
import '../styles/components/modal.css';
import '../styles/admin-settings.css';
import '../styles/admin-settings-extras.css';

// Import utilities
import '../modules/utilities.js';

// Import the coordinator module lazily; allow disabling via wf_diag_no_settings_init=1
(function(){
  try {
    const p = new URLSearchParams(window.location.search || '');
    const noInit = p.get('wf_diag_no_settings_init') === '1';
    if (noInit) {
      console.warn('[AdminSettings] Coordinator disabled via wf_diag_no_settings_init=1');
      return;
    }
    let loaded = false;
    const load = () => { if (loaded) return; loaded = true; try { import('../modules/admin-settings-coordinator.js').catch(()=>{}); } catch(_) {} };
    const scopeContains = (el) => { try { const scope = document.querySelector('.settings-page') || document.body; return scope && scope.contains(el); } catch(_) { return true; } };
    const onClick = (e) => { try { if (e && scopeContains(e.target)) load(); } catch(_) {} };
    const onKey = (e) => { try { if (e && (e.key === 'Enter' || e.key === ' ')) load(); } catch(_) {} };
    try { document.addEventListener('click', onClick, true); } catch(_) {}
    try { document.addEventListener('keydown', onKey, true); } catch(_) {}
    try {
      const idle = (fn) => (window.requestIdleCallback ? window.requestIdleCallback(fn, { timeout: 2000 }) : setTimeout(fn, 1500));
      idle(load);
    } catch(_) {}
  } catch(_) { /* noop */ }
})();

// Standardized embed autosize controller (parent side)
import { initEmbedAutosizeParent, attachSameOriginFallback, markOverlayResponsive, initOverlayAutoWire, wireOverlay } from '../modules/embed-autosize-parent.js';

// Initialize global message listener and auto-wire overlays once (unless diagnostics disable it)
(function(){
  try {
    const p = new URLSearchParams(window.location.search || '');
    const noAuto = p.get('wf_diag_no_autosize') === '1';
    if (noAuto) {
      console.warn('[AdminSettings] Autosize parent disabled via wf_diag_no_autosize=1');
      return;
    }
  } catch(_) {}
  try { initEmbedAutosizeParent(); } catch(_) {}
  try { initOverlayAutoWire(); } catch(_) {}
})();

// Immediately install lightweight modal helpers
const __wfShowModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return false;
  try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
  try {
    const panel = el.querySelector('.admin-modal');
    const body = panel ? panel.querySelector('.modal-body') : null;
    markOverlayResponsive(el);
    if (body) body.classList.remove('wf-modal-body--fill');
  } catch(_) {}
  if (typeof window.showModal === 'function') {
    window.showModal(id);
  }
  // Ensure visual state regardless of which show path was used
  // Only add content-scroll helper for non-responsive overlays
  try {
    const panel = el.querySelector('.admin-modal');
    const isResponsive = panel && panel.classList && panel.classList.contains('admin-modal--responsive');
    if (!isResponsive && !el.classList.contains('wf-modal--content-scroll')) el.classList.add('wf-modal--content-scroll');
    if (isResponsive) el.classList.remove('wf-modal--content-scroll');
  } catch(_) {}
  try { el.removeAttribute('hidden'); } catch(_) {}
  try { el.classList.remove('hidden'); } catch(_) {}
  try { el.classList.add('show'); } catch(_) {}
  try { el.setAttribute('aria-hidden', 'false'); } catch(_) {}
  // Post-show layout helpers kept for wide/auto-grid behavior
  try {
    const apply = () => {
      try {
        const noAuto = (el.classList && el.classList.contains('wf-no-autosize')) || el.hasAttribute('data-no-autosize');
        if (noAuto) return;
        const panel = el.querySelector('.admin-modal');
        if (!panel) return;
        const isResponsive = panel.classList.contains('admin-modal--responsive');
        const body = panel.querySelector('.modal-body');
        if (!body) return;
        // If responsive modal with autosize iframe, prefer autoheight by default to avoid initial scrollbars
        if (isResponsive) {
          try {
            const frame = body.querySelector('iframe[data-autosize], .wf-admin-embed-frame[data-autosize]');
            if (frame) {
              body.classList.add('wf-modal-body--autoheight');
              body.classList.remove('wf-modal-body--scroll');
              frame.classList.remove('wf-embed--fill');
              body.classList.remove('wf-modal-body--fill');
              // Remove legacy auto panel helper if present
              try { panel.classList.remove('wf-modal-auto'); } catch(_) {}
              // Overlay-level helper can force scroll; disable it for responsive modals
              try { el.classList.remove('wf-modal--body-scroll'); } catch(_) {}
              // Standardized parent fallback + responsive mark
              try { markOverlayResponsive(el); } catch(_) {}
              try { attachSameOriginFallback(frame, el); } catch(_) {}
            }
          } catch(_) {}
        }
        try {
          const onlyChild = body.children && body.children.length === 1 ? body.children[0] : null;
          const isIframe = onlyChild && (onlyChild.tagName === 'IFRAME' || onlyChild.classList.contains('wf-admin-embed-frame'));
          if (isIframe && !isResponsive) {
            if (!body.classList.contains('wf-modal-body--fill')) body.classList.add('wf-modal-body--fill');
            if (!onlyChild.classList.contains('wf-embed--fill')) onlyChild.classList.add('wf-embed--fill');
          }
        } catch(_) {}
        const findFlattenEl = (container) => {
          try {
            const innerModal = container.querySelector(':scope > .admin-modal.admin-modal-content');
            if (innerModal) return innerModal;
            const direct = container.querySelectorAll(':scope > .rounded.border.p-3, :scope > .admin-card, :scope > .wf-modal-section, :scope > .modal-section, :scope > .card');
            const viaForm = (!direct.length) ? container.querySelectorAll(':scope > form > .rounded.border.p-3, :scope > form > .admin-card, :scope > form > .wf-modal-section, :scope > form > .modal-section, :scope > form > .card') : [];
            const list = direct.length ? direct : viaForm;
            if (list.length === 1) {
              const s = list[0];
              const cls = s.className || '';
              if (!/\bgrid\b/.test(cls) && !/\bgrid-cols-([2-9]|1[0-9])\b/.test(cls) && !/\bmd:grid-cols-([2-9]|1[0-9])\b/.test(cls)) {
                return s;
              }
            }
          } catch(_) {}
          return null;
        };
        const flattenTarget = findFlattenEl(body);
        if (flattenTarget) { body.classList.add('wf-modal-body--flat'); try { flattenTarget.classList.add('wf-modal-flatten-target'); } catch(_) {} }
        const singleIframe = (body.children && body.children.length === 1 && body.querySelector('iframe')) ? true : false;
        const over = body.scrollHeight > (body.clientHeight + 4);
        if (over && !singleIframe && !body.classList.contains('wf-modal-body--flat')) {
          body.classList.add('wf-modal-body--autogrid');
        }
        const wireAll = () => {
          try {
            const panel = el.querySelector('.admin-modal');
            const bodyEl = panel ? panel.querySelector('.modal-body') : null;
            try { markOverlayResponsive(el); } catch(_) {}
            try { if (bodyEl && bodyEl.classList) bodyEl.classList.remove('wf-modal-body--fill'); } catch(_) {}
            const list = el.querySelectorAll('iframe, .wf-admin-embed-frame');
            let any = false;
            list.forEach((f) => {
              try { if (f && f.dataset && f.dataset.wfWired === '1') return; } catch(_) {}
              any = true;
              try { if (f && !f.hasAttribute('data-autosize')) f.setAttribute('data-autosize','1'); } catch(_) {}
              try { if (f) f.removeAttribute('data-wf-use-msg-sizing'); } catch(_) {}
              try { if (f && f.classList) f.classList.remove('wf-embed--fill'); } catch(_) {}
              try { if (f && f.dataset) f.dataset.wfWired = '1'; } catch(_) {}
            });
            if (any) { /* already marked above */ }
          } catch(_) {}
        };
        wireAll();
        try {
          if (!el.__wfEmbedMO) {
            const mo = new MutationObserver((muts) => {
              try {
                for (const m of muts) {
                  try {
                    if (!m || !m.addedNodes) continue;
                    m.addedNodes.forEach((n) => {
                      try {
                        if (!n || n.nodeType !== 1) return;
                        const frames = (n.matches && n.matches('iframe, .wf-admin-embed-frame')) ? [n] : (n.querySelectorAll ? n.querySelectorAll('iframe, .wf-admin-embed-frame') : []);
                        if (!frames || frames.length === 0) return;
                        frames.forEach((f) => {
                          try { if (f && f.dataset && f.dataset.wfWired === '1') return; } catch(_) {}
                          try { if (f && !f.hasAttribute('data-autosize')) f.setAttribute('data-autosize','1'); } catch(_) {}
                          try { if (f) f.removeAttribute('data-wf-use-msg-sizing'); } catch(_) {}
                          try { if (f && f.classList) f.classList.remove('wf-embed--fill'); } catch(_) {}
                          try { if (f && f.dataset) f.dataset.wfWired = '1'; } catch(_) {}
                        });
                        try { markOverlayResponsive(el); } catch(_) {}
                        try { const panel2 = el.querySelector('.admin-modal'); const body2 = panel2 ? panel2.querySelector('.modal-body') : null; if (body2 && body2.classList) body2.classList.remove('wf-modal-body--fill'); } catch(_) {}
                      } catch(_) {}
                    });
                  } catch(_) {}
                }
              } catch(_) {}
            });
            mo.observe(el, { childList: true, subtree: true });
            el.__wfEmbedMO = mo;
          }
        } catch(_) {}
      } catch(_) {}
    };
    if ('requestAnimationFrame' in window) requestAnimationFrame(() => apply()); else setTimeout(apply, 0);
  } catch(_) {}
  return true;
};

// Expose helpers globally for callers that expect them
try { window.__wfShowModal = __wfShowModal; } catch(_) {}

const __wfHideModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return false;
  if (typeof window.hideModal === 'function') {
    window.hideModal(id);
  } else {
    try { el.setAttribute('hidden', ''); } catch(_) {}
    el.classList.add('hidden');
    el.classList.remove('show');
    el.setAttribute('aria-hidden', 'true');
  }
  return true;
};

// Lightweight generic status modal (for transient operations like provider tests)
const __wfEnsureStatusModal = () => {
  let el = document.getElementById('wfStatusModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'wfStatusModal';
  el.className = 'admin-modal-overlay wf-modal--content-scroll hidden over-header';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'wfStatusTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--sm admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="wfStatusTitle" class="admin-card-title">Status</h2>
        <button type="button" class="admin-modal-close wf-admin-nav-button" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <div id="wfStatusBody" class="text-sm text-gray-700">Working…</div>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

const __wfShowStatusModal = (title, message) => {
  const m = __wfEnsureStatusModal();
  try { m.querySelector('#wfStatusTitle').textContent = title || 'Status'; } catch(_) {}
  try { m.querySelector('#wfStatusBody').textContent = message || ''; } catch(_) {}
  __wfShowModal('wfStatusModal');
};

const __wfUpdateStatusModal = (message) => {
  try {
    const b = document.getElementById('wfStatusBody');
    if (b) b.textContent = message || '';
  } catch(_) {}
};

// Inject minimal CSS fallback into admin iframes so variant-only buttons still layout
(function registerIframeBtnFallback(){
  const CSS = `
    /* Button/size normalization inside iframes */
    body :is(.btn-primary, .btn-secondary, .btn-danger, .btn-success, .btn-warning, .btn-info, .btn-light, .btn-outline, .btn-link):not(.btn) {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: var(--space-2, 0.5rem);
      font-family: var(--font-primary, inherit);
      font-weight: 500;
      text-decoration: none;
      white-space: nowrap;
      cursor: pointer;
    }
    body :is(.btn-xs, .btn-sm, .btn-lg):not(.btn) {
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    /* Auto-grid within iframe content to reduce internal scroll */
    .wf-embed-autogrid {
      display: grid !important;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important;
      gap: 12px !important;
      align-content: start !important;
    }
    .wf-embed-autogrid > form { display: contents !important; }
    .wf-embed-autogrid > fieldset,
    .wf-embed-autogrid > .admin-card,
    .wf-embed-autogrid > .rounded,
    .wf-embed-autogrid > .modal-section,
    .wf-embed-autogrid > .wf-modal-section,
    .wf-embed-autogrid > .rounded.border.p-3 {
      min-width: 0 !important;
    }
    @media (min-width: 1536px) {
      .wf-embed-autogrid { grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)) !important; }
    }

    /* Flatten one-column iframe content by removing inner card chrome */
    .wf-embed-flat { display: block !important; }
    .wf-embed-flat > .rounded.border.p-3,
    .wf-embed-flat > .admin-card,
    .wf-embed-flat > .wf-modal-section,
    .wf-embed-flat > .modal-section,
    .wf-embed-flat > form > .rounded.border.p-3,
    .wf-embed-flat > form > .admin-card,
    .wf-embed-flat > form > .wf-modal-section,
    .wf-embed-flat > form > .modal-section,
    .wf-embed-flat .rounded.border.p-3.wf-embed-flatten-target {
      background: transparent !important;
      border: 0 !important;
      box-shadow: none !important;
      padding: 0 !important;
    }
    /* Also flatten any inner admin-modal chrome under flat root */
    .wf-embed-flat .admin-modal.admin-modal-content { background: transparent !important; border: 0 !important; box-shadow: none !important; padding: 0 !important; }
    .wf-embed-flat .admin-modal.admin-modal-content > .modal-header { background: transparent !important; border: 0 !important; box-shadow: none !important; padding-left: 0 !important; padding-right: 0 !important; margin: 0 0 8px 0 !important; }
    .wf-embed-flat .admin-modal.admin-modal-content > .modal-header .admin-card-title { display: none !important; }
  `;
  function inject(doc){
    try {
      if (!doc) return;
      {
        let style = doc.getElementById('wf-admin-btn-fallback');
        if (!style) {
          style = doc.createElement('style');
          style.id = 'wf-admin-btn-fallback';
          (doc.head || doc.documentElement || doc.body).appendChild(style);
        }
        try { style.textContent = CSS; } catch(_) {}
      }
      // Apply flatten for one-column content or autogrid if overflow
      try {
        const pickRoot = (d) => {
          return d.querySelector('.admin-marketing-page, .wf-modal-section-root, .admin-card-container, main, .content, .container') || d.body;
        };
        const findFlattenEl = (root) => {
          try {
            // 1) If the root has a direct inner admin-modal, flatten that panel
            const directInnerModal = root.querySelector(':scope > .admin-modal.admin-modal-content');
            if (directInnerModal) return directInnerModal;
            // 2) Otherwise, if there's exactly one single-column wrapper, flatten the root content
            const direct = root.querySelectorAll(':scope > .rounded.border.p-3, :scope > .admin-card, :scope > .wf-modal-section, :scope > .modal-section, :scope > .card');
            const viaForm = (!direct.length) ? root.querySelectorAll(':scope > form > .rounded.border.p-3, :scope > form > .admin-card, :scope > form > .wf-modal-section, :scope > form > .modal-section, :scope > form > .card') : [];
            const list = direct.length ? direct : viaForm;
            if (list.length === 1) {
              const el = list[0];
              const cls = el.className || '';
              if (!/\bgrid\b/.test(cls) && !/\bgrid-cols-([2-9]|1[0-9])\b/.test(cls) && !/\bmd:grid-cols-([2-9]|1[0-9])\b/.test(cls)) {
                return el;
              }
            }
          } catch(_) {}
          return null;
        };
        const applyLayout = (d) => {
          try {
            const root = pickRoot(d);
            if (!root) return;
            const target = findFlattenEl(root);
            if (target) { root.classList.add('wf-embed-flat'); target.classList.add('wf-embed-flatten-target'); return; }
            const docEl = d.documentElement || d.body;
            const over = (docEl && (docEl.scrollHeight > (docEl.clientHeight + 4))) || (d.body && (d.body.scrollHeight > (d.body.clientHeight + 4)));
            if (over && !root.classList.contains('wf-embed-flat')) root.classList.add('wf-embed-autogrid');
          } catch(_) {}
        };
        if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', () => applyLayout(doc), { once: true }); } else { applyLayout(doc); }
        // Re-check after a tick for late content
        setTimeout(() => { try { applyLayout(doc); } catch(_){} }, 100);
        // Re-check on resize within iframe
        try { doc.defaultView && doc.defaultView.addEventListener('resize', () => applyLayout(doc)); } catch(_){}
      } catch(_) {}
    } catch(_) {}
  }
  function attachToIframe(ifr){
    try {
      if (!ifr || ifr.__wfBtnFb) return;
      ifr.__wfBtnFb = true;
      ifr.addEventListener('load', () => {
        try { inject(ifr.contentDocument || ifr.contentWindow?.document); } catch(_) {}
      });
      // If already loaded and same-origin, inject immediately
      try { inject(ifr.contentDocument || ifr.contentWindow?.document); } catch(_) {}
    } catch(_) {}
  }
  function scan(root){
    try {
      (root || document).querySelectorAll('iframe.wf-admin-embed-frame, .admin-modal iframe, iframe[id$="Frame"]').forEach(attachToIframe);
    } catch(_) {}
  }
  try {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => scan(document), { once: true });
    } else {
      scan(document);
    }
  } catch(_) {}
  try {
    const target = document.body || document;
    const mo = new MutationObserver((muts)=>{
      for (const m of muts){
        if (!m.addedNodes) continue;
        m.addedNodes.forEach((n)=>{
          if (n && n.nodeType === 1){
            if (n.tagName === 'IFRAME') attachToIframe(n);
            scan(n);
          }
        });
      }
    });
    mo.observe(target, { subtree: true, childList: true });
    window.__wfIframeBtnFallbackObserver = mo;
  } catch(_) {}
})();

// Listen for requests from embedded marketing tools to open AI Provider
try {
  if (!window.__wfAIProviderOpenListener) {
    window.__wfAIProviderOpenListener = true;
    window.addEventListener('message', (ev) => {
      try {
        const d = ev && ev.data; if (!d || d.source !== 'wf-ai') return;
        if (d.type === 'open-provider') {
          const modal = document.getElementById('aiSettingsModal');
          if (modal) {
            try { if (modal.parentElement && modal.parentElement !== document.body) document.body.appendChild(modal); } catch(_) {}
            try { modal.classList.add('over-header'); } catch(_) {}
            try { modal.classList.add('topmost'); } catch(_) {}
            __wfShowModal('aiSettingsModal');
            try { if (typeof window !== 'undefined' && typeof window.loadAISettings === 'function') window.loadAISettings(); else if (typeof __wfAI_loadSettingsAndRender === 'function') __wfAI_loadSettingsAndRender(); } catch(_) {}
            try { if (typeof window !== 'undefined' && typeof window.loadAIProviders === 'function') window.loadAIProviders(); } catch(_) {}
          }
          return;
        }
        if (d.type === 'open-tool') {
          const overlay = document.getElementById('aiUnifiedChildModal');
          if (!overlay) return;
          try { if (overlay.parentElement && overlay.parentElement !== document.body) document.body.appendChild(overlay); } catch(_) {}
          try { overlay.classList.add('over-header'); } catch(_) {}
          try { const titleEl = overlay.querySelector('#aiUnifiedChildTitle'); if (titleEl && d.title) titleEl.textContent = String(d.title); } catch(_) {}
          try {
            const frame = overlay.querySelector('#aiUnifiedChildFrame');
            if (frame) {
              if (!frame.hasAttribute('data-autosize')) frame.setAttribute('data-autosize','1');
              frame.removeAttribute('data-wf-use-msg-sizing');
              frame.setAttribute('src', String(d.url || 'about:blank'));
              try { if (typeof attachSameOriginFallback === 'function') attachSameOriginFallback(frame, overlay); } catch(_) {}
            }
          } catch(_) {}
          try { __wfShowModal('aiUnifiedChildModal'); } catch(_) {}
          return;
        }
      } catch(_) {}
    });
  }
} catch(_) {}

// Parent-side iframe autosize: legacy listener (DISABLED when primary autosize module is present)
try {
  if (!window.__wfEmbedSizeListener && !window.__wfEmbedAutosizePrimary) {
    window.__wfEmbedSizeListener = true;
    window.addEventListener('message', (ev) => {
      try {
        // Accept same-origin, same-host (different port) in dev, and browser-reported null/empty origins
        const okOrigin = (() => {
          try {
            if (!ev || !('origin' in ev) || !ev.origin || ev.origin === 'null') return true;
            if (ev.origin === window.location.origin) return true;
            const childHost = new URL(ev.origin).hostname;
            const parentHost = window.location.hostname;
            return !!childHost && !!parentHost && childHost === parentHost;
          } catch(_) { return false; }
        })();
        if (!okOrigin) return;
        const d = ev && ev.data; if (!d || d.source !== 'wf-embed-size') return;
        // Find the active autosize iframe and its overlay reliably
        let frame = document.querySelector('.admin-modal-overlay:not([hidden]) iframe[data-autosize]');
        if (!frame) frame = document.querySelector('.admin-modal-overlay.show:not(.hidden) iframe[data-autosize]');
        if (!frame) frame = document.querySelector('iframe[data-autosize]');
        if (!frame) return;
        const overlay = frame.closest('.admin-modal-overlay') || document.querySelector('.admin-modal-overlay');
        if (!overlay) return;
        const panel = overlay.querySelector('.admin-modal');
        if (!panel || !panel.classList.contains('admin-modal--responsive')) return;
        const body = overlay.querySelector('.modal-body');
        if (!body || !frame) return;
        const header = overlay.querySelector('.modal-header');
        const maxVh = 0.95; // sync with CSS 95vh
        const maxPanel = Math.floor(window.innerHeight * maxVh);
        const headerH = header ? header.offsetHeight : 0;
        let padY = 0; try { const cs = getComputedStyle(body); padY = (parseFloat(cs.paddingTop)||0)+(parseFloat(cs.paddingBottom)||0); } catch(_) {}
        const available = Math.max(120, maxPanel - headerH - padY);
        // Message-based intrinsic content height from child
        const contentH = Math.max(0, Number(d.height || 0));
        const safety = 0; // no ratchet
        let desired = Math.max(80, Math.min(contentH + safety, available));
        // Mark message sizing active and stop fallback observer
        try { frame.dataset.wfUseMsgSizing = '1'; } catch(_) {}
        try { if (frame.__wfEmbedRO && typeof frame.__wfEmbedRO.disconnect === 'function') frame.__wfEmbedRO.disconnect(); } catch(_) {}
        // Guard: do not shrink below current rendered height; child can under-report early
        try {
          const currentH = Math.round(frame.getBoundingClientRect().height);
          if (currentH) desired = Math.max(desired, currentH);
        } catch(_) {}
        // Epsilon guard to avoid needless updates/oscillations
        // Ignore suspiciously tiny reports when we already have a larger size
        try {
          const last = Number(frame.getAttribute('data-wf-last-height') || '0');
          if (contentH && contentH < 120 && last > 0) desired = Math.max(desired, last);
        } catch(_) {}
        // Use a dynamic stylesheet rule per iframe id (avoid inline styles)
        const idRaw = frame.getAttribute('id') || 'wf-embed-current';
        const esc = (s) => {
          try { return (window.CSS && CSS.escape) ? CSS.escape(s) : String(s).replace(/[^\w-]/g, '\\$&'); } catch(_) { return String(s).replace(/[^\w-]/g, '\\$&'); }
        };
        const styleId = 'wf-embed-dynamic-heights';
        let styleTag = document.getElementById(styleId);
        if (!styleTag) { styleTag = document.createElement('style'); styleTag.id = styleId; document.head.appendChild(styleTag); }
        // Epsilon guard to avoid needless updates/oscillations
        try {
          const last = Number(frame.getAttribute('data-wf-last-height') || '0');
          if (last && Math.abs(desired - last) < 1) return;
        } catch(_) {}
        const css = `#${esc(idRaw)}{height:${Math.round(desired)}px !important;}`;
        // Maintain only the active rule for simplicity
        try { styleTag.textContent = css; } catch(_) { styleTag.innerText = css; }
        try { frame.setAttribute('data-wf-last-height', String(Math.round(desired))); } catch(_) {}
        // Toggle modal-body autoheight vs scroll depending on overflow
        const fits = (contentH + safety) <= available;
        try {
          body.classList.toggle('wf-modal-body--autoheight', fits);
          body.classList.toggle('wf-modal-body--scroll', !fits);
          // Clear conflicting fill classes to prevent overrides
          frame.classList.remove('wf-embed--fill');
          body.classList.remove('wf-modal-body--fill');
          // Remove seed height class if present
          frame.classList.remove('wf-embed-h-s', 'wf-embed-h-m', 'wf-embed-h-l', 'wf-embed-h-xl', 'wf-embed-h-xxl');
          try { if (window.__WF_DEBUG) console.debug('[wf-embed-size] parent-message', { h: contentH, available, desired, fits }); } catch(_) {}
        } catch(_) {}
      } catch(_) {}
    });
  }
} catch(_) {}

// Prime Colors & Fonts modal inputs using current CSS variables
const __wfInitColorsFontsModal = () => {
  try {
    const css = (name, fallback) => {
      try {
        const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
      } catch(_) { return fallback; }
    };
    const ensureHex = (v, fallback) => {
      if (!v) return fallback;
      const t = v.trim();
      if (/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(t)) return t;
      // Try rgb/rgba(…)
      const m = t.match(/rgba?\((\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
      if (m) {
        const r = (parseInt(m[1],10)||0).toString(16).padStart(2,'0');
        const g = (parseInt(m[2],10)||0).toString(16).padStart(2,'0');
        const b = (parseInt(m[3],10)||0).toString(16).padStart(2,'0');
        return `#${r}${g}${b}`;
      }
      return fallback;
    };

    const map = {
      brandPrimary: ensureHex(css('--brand-primary', '#0ea5e9'), '#0ea5e9'),
      brandSecondary: ensureHex(css('--brand-secondary', '#6366f1'), '#6366f1'),
      brandAccent: ensureHex(css('--brand-accent', '#22c55e'), '#22c55e'),
      brandBackground: ensureHex(css('--brand-bg', '#ffffff'), '#ffffff'),
      brandText: ensureHex(css('--brand-text', '#111827'), '#111827'),
      publicHeaderBg: ensureHex(css('--public-header-bg', css('--brand-primary', '#0ea5e9')), '#0ea5e9'),
      publicHeaderText: ensureHex(css('--public-header-text', '#ffffff'), '#ffffff'),
      publicModalBg: ensureHex(css('--public-modal-bg', '#ffffff'), '#ffffff'),
      publicModalText: ensureHex(css('--public-modal-text', '#111827'), '#111827'),
      publicPageBg: ensureHex(css('--site-page-bg', css('--brand-bg', '#ffffff')), '#ffffff'),
      publicPageText: ensureHex(css('--site-page-text', css('--brand-text', '#111827')), '#111827'),
    };

    Object.entries(map).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el && el.type === 'color' && (!el.value || el.value === '#000000')) {
        try { el.value = val; } catch(_) {}
      }
    });

    // Font preview labels are handled by Initialization.applyBusinessCssToRoot()
  } catch (_) {}
};

const __wfGetTriggerLabel = (node) => {
  if (!node) return '';
  const el = node.closest('button, a, [data-action]') || node;
  let t = '';
  try { t = el.getAttribute('data-modal-title') || el.getAttribute('data-title') || el.getAttribute('aria-label') || ''; } catch(_) {}
  if (!t) {
    try { t = (el.textContent || '').replace(/\s+/g, ' ').trim(); } catch(_) { t = ''; }
  }
  return t || '';
};

const __wfSetModalHeaderFromTrigger = (trigger, modal) => {
  if (!modal) return;
  const titleEl = modal.querySelector('.modal-header .admin-card-title');
  if (!titleEl) return;
  const lbl = __wfGetTriggerLabel(trigger);
  if (lbl) titleEl.textContent = lbl;
};

// --- Fallback AI Settings renderer (self-contained) ---
const __wfAI_fallbackModels = {
  openai: [
    { id: 'gpt-4o', name: 'GPT-4o', description: 'Latest and most capable model' },
    { id: 'gpt-4o-mini', name: 'GPT-4o Mini', description: 'Cheaper, fast variant of 4o' },
    { id: 'o3-mini', name: 'o3-mini', description: 'Reasoning-optimized, lightweight' },
    { id: 'gpt-4-turbo', name: 'GPT-4 Turbo', description: 'Fast and capable' },
    { id: 'gpt-4', name: 'GPT-4', description: 'Highly capable model' },
    { id: 'gpt-3.5-turbo', name: 'GPT-3.5 Turbo', description: 'Fast and affordable' }
  ],
  anthropic: [
    { id: 'claude-3-5-sonnet-20241022', name: 'Claude 3.5 Sonnet', description: 'Most intelligent model' },
    { id: 'claude-3-5-haiku-20241022', name: 'Claude 3.5 Haiku', description: 'Fastest model' },
    { id: 'claude-3-opus-20240229', name: 'Claude 3 Opus', description: 'Most capable for reasoning' },
    { id: 'claude-3-sonnet-20240229', name: 'Claude 3 Sonnet', description: 'Balanced performance' },
    { id: 'claude-3-haiku-20240307', name: 'Claude 3 Haiku', description: 'Fast and affordable' }
  ],
  google: [
    { id: 'gemini-1.5-pro', name: 'Gemini 1.5 Pro', description: 'Most capable Gemini model' },
    { id: 'gemini-1.5-flash', name: 'Gemini 1.5 Flash', description: 'Fast and efficient' },
    { id: 'gemini-pro', name: 'Gemini Pro', description: 'Balanced performance' },
    { id: 'gemini-pro-vision', name: 'Gemini Pro Vision', description: 'Multimodal capabilities' }
  ],
  meta: [
    { id: 'meta-llama/llama-3.1-405b-instruct', name: 'Llama 3.1 405B', description: 'Most capable model' },
    { id: 'meta-llama/llama-3.1-70b-instruct', name: 'Llama 3.1 70B', description: 'Balanced performance' },
    { id: 'meta-llama/llama-3.1-8b-instruct', name: 'Llama 3.1 8B', description: 'Fast and affordable' }
  ]
};

const __wfAI_populateModelDropdown = (provider, selectedValue) => {
  try {
    const sel = document.getElementById(`${provider}_model`);
    if (!sel) return;
    const models = __wfAI_fallbackModels[provider] || [];
    sel.innerHTML = '';
    if (!models.length) { sel.innerHTML = '<option value="">No models available</option>'; return; }
    for (const m of models) {
      const opt = document.createElement('option');
      opt.value = m.id;
      opt.textContent = `${m.name} - ${m.description}`;
      opt.title = m.description;
      if (selectedValue && selectedValue === m.id) opt.selected = true;
      sel.appendChild(opt);
    }
    if (selectedValue && !sel.querySelector(`option[value="${selectedValue}"]`)) sel.selectedIndex = 0;
  } catch(_) {}
};

const __wfAI_renderProviderUI = (provider, settings) => {
  const container = document.getElementById('aiProviderSettings');
  if (!container) return;
  const prov = provider || 'jons_ai';
  const s = settings || {};
  let html = '';
  if (prov === 'openai') {
    html = [
      '<div class="grid gap-4 md:grid-cols-2">',
      '  <div>',
      '    <label for="openai_api_key" class="block text-sm font-medium mb-1">OpenAI API Key</label>',
      '    <input id="openai_api_key" type="password" class="form-input w-full" placeholder="sk-..." autocomplete="off" />',
      '  </div>',
      '  <div>',
      '    <label for="openai_model_source" class="block text-sm font-medium mb-1">Model Source</label>',
      '    <select id="openai_model_source" class="form-select w-full"><option value="local">Local (Static)</option><option value="openrouter">OpenRouter (Live)</option></select>',
      '  </div>',
      '  <div>',
      '    <label for="openai_model" class="block text-sm font-medium mb-1">OpenAI Model</label>',
      '    <select id="openai_model" class="form-select w-full"></select>',
      '  </div>',
      '</div>'
    ].join('');
  } else if (prov === 'anthropic') {
    html = [
      '<div class="grid gap-4 md:grid-cols-2">',
      '  <div>',
      '    <label for="anthropic_api_key" class="block text-sm font-medium mb-1">Anthropic API Key</label>',
      '    <input id="anthropic_api_key" type="password" class="form-input w-full" placeholder="anthropic-key" autocomplete="off" />',
      '  </div>',
      '  <div>',
      '    <label for="anthropic_model_source" class="block text-sm font-medium mb-1">Model Source</label>',
      '    <select id="anthropic_model_source" class="form-select w-full"><option value="local">Local (Static)</option><option value="openrouter">OpenRouter (Live)</option></select>',
      '  </div>',
      '  <div>',
      '    <label for="anthropic_model" class="block text-sm font-medium mb-1">Anthropic Model</label>',
      '    <select id="anthropic_model" class="form-select w-full"></select>',
      '  </div>',
      '</div>'
    ].join('');
  } else if (prov === 'google') {
    html = [
      '<div class="grid gap-4 md:grid-cols-2">',
      '  <div>',
      '    <label for="google_api_key" class="block text-sm font-medium mb-1">Google API Key</label>',
      '    <input id="google_api_key" type="password" class="form-input w-full" placeholder="AIza..." autocomplete="off" />',
      '  </div>',
      '  <div>',
      '    <label for="google_model_source" class="block text-sm font-medium mb-1">Model Source</label>',
      '    <select id="google_model_source" class="form-select w-full"><option value="local">Local (Static)</option><option value="openrouter">OpenRouter (Live)</option></select>',
      '  </div>',
      '  <div>',
      '    <label for="google_model" class="block text-sm font-medium mb-1">Google Model</label>',
      '    <select id="google_model" class="form-select w-full"></select>',
      '  </div>',
      '</div>'
    ].join('');
  } else if (prov === 'meta') {
    html = [
      '<div class="grid gap-4 md:grid-cols-2">',
      '  <div>',
      '    <label for="meta_api_key" class="block text-sm font-medium mb-1">Meta API Key</label>',
      '    <input id="meta_api_key" type="password" class="form-input w-full" placeholder="..." autocomplete="off" />',
      '  </div>',
      '  <div>',
      '    <label for="meta_model_source" class="block text-sm font-medium mb-1">Model Source</label>',
      '    <select id="meta_model_source" class="form-select w-full"><option value="local">Local (Static)</option><option value="openrouter">OpenRouter (Live)</option></select>',
      '  </div>',
      '  <div>',
      '    <label for="meta_model" class="block text-sm font-medium mb-1">Meta Model</label>',
      '    <select id="meta_model" class="form-select w-full"></select>',
      '  </div>',
      '</div>'
    ].join('');
  } else {
    html = '<div class="text-sm text-gray-500">Using local AI. No keys required.</div>';
  }
  container.innerHTML = html;
  // Initialize Model Source selector (persist to localStorage)
  try {
    const srcSel = document.getElementById(`${prov}_model_source`);
    if (srcSel) {
      const key = `wf_ai_model_source_${prov}`;
      try {
        const saved = localStorage.getItem(key);
        if (saved) srcSel.value = saved;
      } catch(_) {}
      try {
        srcSel.addEventListener('change', () => {
          try { localStorage.setItem(key, srcSel.value); } catch(_) {}
          __wfAI_fetchAndPopulateModels(prov, true, srcSel.value);
        });
      } catch(_) {}
    }
  } catch(_) {}

  // Populate fallback models
  __wfAI_populateModelDropdown(prov, (s && s[`${prov}_model`]) || '');

  try {
    const srcSel = document.getElementById(`${prov}_model_source`);
    __wfAI_fetchAndPopulateModels(prov, true, srcSel ? srcSel.value : '');
  } catch(_) {}

  try {
    const modelSel = document.getElementById(`${prov}_model`);
    const srcSel = document.getElementById(`${prov}_model_source`);
    if (modelSel && !modelSel.__wfLiveModelsWired) {
      const trigger = () => { __wfAI_fetchAndPopulateModels(prov, true, (srcSel ? srcSel.value : '')); };
      try { modelSel.addEventListener('focus', trigger); } catch(_) {}
      try { modelSel.addEventListener('mousedown', trigger); } catch(_) {}
      modelSel.__wfLiveModelsWired = true;
    }
  } catch(_) {}

  // Add "Saved" badge if secret present (from settings presence flags)
  try {
    const addBadge = (forId) => {
      const lbl = container.querySelector(`label[for="${forId}"]`);
      if (!lbl) return;
      const badge = document.createElement('span');
      badge.textContent = 'Saved';
      badge.className = 'ml-2 inline-block px-2 py-0.5 text-xs rounded bg-green-100 text-green-700 align-middle';
      lbl.appendChild(badge);
    };
    if (prov === 'openai' && s.openai_key_present) addBadge('openai_api_key');
    if (prov === 'anthropic' && s.anthropic_key_present) addBadge('anthropic_api_key');
    if (prov === 'google' && s.google_key_present) addBadge('google_api_key');
    if (prov === 'meta' && s.meta_key_present) addBadge('meta_api_key');
  } catch(_) {}

  try {
    const setKeyUi = (id) => {
      const inp = container.querySelector(`#${id}`);
      if (!inp) return;
      const has = (
        (prov === 'openai' && s.openai_key_present) ||
        (prov === 'anthropic' && s.anthropic_key_present) ||
        (prov === 'google' && s.google_key_present) ||
        (prov === 'meta' && s.meta_key_present)
      );
      if (has) {
        inp.placeholder = 'Saved — leave blank to keep';
      }
      inp.setAttribute('title', 'We never show your secret. Leave blank to keep the current one; paste a new one to replace it.');
    };
    const setModelUi = (id) => {
      const sel = container.querySelector(`#${id}`);
      if (sel) sel.setAttribute('title', 'Pick your preferred brain flavor for this provider.');
    };
    const setSourceUi = (forId) => {
      const lbl = container.querySelector(`label[for="${forId}"]`);
      if (lbl) lbl.setAttribute('title', 'Local = our trusty offline cheat sheet. OpenRouter = live buffet of fresh models.');
    };
    if (prov === 'openai') { setKeyUi('openai_api_key'); setModelUi('openai_model'); }
    if (prov === 'anthropic') { setKeyUi('anthropic_api_key'); setModelUi('anthropic_model'); }
    if (prov === 'google') { setKeyUi('google_api_key'); setModelUi('google_model'); }
    if (prov === 'meta') { setKeyUi('meta_api_key'); setModelUi('meta_model'); }
    // Source tooltips on labels
    if (prov === 'openai') setSourceUi('openai_model_source');
    if (prov === 'anthropic') setSourceUi('anthropic_model_source');
    if (prov === 'google') setSourceUi('google_model_source');
    if (prov === 'meta') setSourceUi('meta_model_source');
  } catch(_) {}

  try { __wfAI_applyTooltips(prov); } catch(_) {}
};

const __wfAI_applyTooltips = (_provider) => {
  try {
    const pSel = document.getElementById('aiProvider');
    if (pSel) pSel.setAttribute('title', 'Pick your AI overlord. Choose wisely.');
  } catch(_) {}
  try {
    const t = document.getElementById('aiTemperature');
    const lbl = document.querySelector('label[for="aiTemperature"]');
    if (lbl) {
      lbl.textContent = 'Personality';
      const hint = document.createElement('span');
      hint.className = 'ml-2 text-xs text-gray-500';
      hint.textContent = '(Reserved ↔ Adventurous)';
      lbl.appendChild(hint);
    }
    if (t) t.setAttribute('title', 'Reserved = careful librarian; Adventurous = caffeinated poet. Choose your vibe.');
  } catch(_) {}
  try {
    const mt = document.getElementById('aiMaxTokens');
    if (mt) mt.setAttribute('title', 'How long the model can ramble before we cut it off. Bigger costs more.');
  } catch(_) {}
  try {
    const to = document.getElementById('aiTimeout');
    if (to) to.setAttribute('title', "How long we wait before declaring the AI took a nap.");
  } catch(_) {}
  try {
    const fbl = document.getElementById('fallbackToLocal');
    if (fbl) fbl.setAttribute('title', 'If the cloud is cranky, ask the local gremlin to help.');
  } catch(_) {}
};

const __wfAI_loadSettingsAndRender = async () => {
  try {
    // Prefer full module if present
    if (typeof window !== 'undefined' && typeof window.loadAISettings === 'function') {
      window.loadAISettings();
      return;
    }
  } catch(_) {}
  try {
    // Attempt to fetch settings for selected provider; if blocked, render defaults
    const sel = document.getElementById('aiProvider');
    const provider = (sel && sel.value) || 'jons_ai';
    let settings = {};
    try {
      const url = '/api/ai_settings.php?action=get_settings&_=' + Date.now();
      const isLocal = (() => { try { const h = window.location.hostname; return h === 'localhost' || h === '127.0.0.1'; } catch(_) { return false; } })();
      const j = await ApiClient.request(url, { method: 'GET', headers: isLocal ? { 'X-WF-Dev-Admin': '1' } : {} });
      if (j && j.success) {
        settings = j.settings || {};
        try { console.log('[AI Settings] get_settings response:', settings); } catch(_) {}
      }
    } catch(_) { settings = {}; }
    // Apply server defaults to form controls
    try {
      const prov = settings.ai_provider || provider || 'jons_ai';
      if (sel) sel.value = prov;
      const t = document.getElementById('aiTemperature');
      const tv = document.getElementById('aiTemperatureValue');
      if (t) {
        t.value = (typeof settings.ai_temperature === 'number' ? settings.ai_temperature : 0.7);
        if (tv) {
          try { __wfAI_updatePersonalityDisplay(); } catch(_) { tv.textContent = String(t.value); }
        }
        if (!t.__wfPersonalityWired) {
          try { t.addEventListener('input', __wfAI_updatePersonalityDisplay); } catch(_) {}
          try { t.addEventListener('change', __wfAI_updatePersonalityDisplay); } catch(_) {}
          t.__wfPersonalityWired = true;
        }
      }
      const mt = document.getElementById('aiMaxTokens');
      if (mt) mt.value = (typeof settings.ai_max_tokens === 'number' ? settings.ai_max_tokens : 1000);
      const to = document.getElementById('aiTimeout');
      if (to) to.value = (typeof settings.ai_timeout === 'number' ? settings.ai_timeout : 30);
      const fbl = document.getElementById('fallbackToLocal');
      if (fbl) fbl.checked = !!settings.fallback_to_local;
      // Legacy radio support
      try {
        const sp = document.querySelector('input[name="ai_provider"][value="' + prov + '"]');
        if (sp) sp.checked = true;
      } catch(_) {}
      __wfAI_renderProviderUI(prov, settings);
    } catch(_) {
      __wfAI_renderProviderUI(provider, settings);
    }
    if (sel && !sel.__wfAIChangeWired) {
      sel.__wfAIChangeWired = true;
      sel.addEventListener('change', () => __wfAI_renderProviderUI(sel.value || 'jons_ai', settings));
    }
  } catch(_) {
    const sel = document.getElementById('aiProvider');
    const provider = (sel && sel.value) || 'jons_ai';
    __wfAI_renderProviderUI(provider, {});
    if (sel && !sel.__wfAIChangeWired) {
      sel.__wfAIChangeWired = true;
      sel.addEventListener('change', () => __wfAI_renderProviderUI(sel.value || 'jons_ai', {}));
    }
  }
};

// Install immediate click handlers for critical functionality
(function installImmediateSettingsClicks(){
  const handler = (e) => {
    try {
      const t = e.target;
      const closest = (sel) => (t && t.closest ? t.closest(sel) : null);

      // Only act on Settings routes
      const body = document.body;
      const pathLower = ((body && body.dataset && body.dataset.path) || window.location.pathname + window.location.search || '').toLowerCase();
      const isSettings = (
        document.getElementById('adminSettingsRoot') !== null
        || (body && body.dataset && body.dataset.page === 'admin/settings')
        || (body && body.dataset && body.dataset.isAdmin === 'true' && (
            pathLower.includes('/admin/settings')
            || pathLower.includes('section=settings')
            || pathLower.includes('admin_settings')
        ))
      );
      if (!isSettings) return;

      // Handle overlay close
      if (t && t.classList && t.classList.contains('admin-modal-overlay')) {
        const id = t.id; if (!id) return;
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfHideModal(id);
        return;
      }

      // Toggle Action Buttons mode (Icons <-> Text Labels)
      if (closest('[data-action="toggle-action-icons"], #actionIconsToggleBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfToggleActionIcons();
        return;
      }

      // Open Action Icons Manager
      if (closest('[data-action="open-action-icons-manager"], #actionIconsManagerBtn')) {
        const canEnsure = (typeof __wfEnsureActionIconsManagerModal === 'function');
        if (!canEnsure) {
          // Do not block other handlers; allow global fallbacks to process this click
          return;
        }
        let m = null;
        try { m = __wfEnsureActionIconsManagerModal(); } catch(_) { m = null; }
        if (m) {
          e.preventDefault();
          if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
          try { __wfSetModalHeaderFromTrigger(closest('[data-action="open-action-icons-manager"], #actionIconsManagerBtn'), m); } catch(_) {}
          const f = m.querySelector('#actionIconsManagerFrame');
          try { if (f && !f.getAttribute('src')) f.setAttribute('src', f.getAttribute('data-src') || '/sections/tools/action_icons_manager.php?modal=1'); } catch(_) {}
          __wfShowModal('actionIconsManagerModal');
          return;
        }
        // If ensure failed, let other handlers (fallbacks) run
        return;
      }

      // Open Reports & Documentation Browser (iframe)
      if (closest('[data-action="open-reports-browser"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureReportsBrowserModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-reports-browser"]'), document.getElementById('reportsBrowserModal'));
        const iframe = document.getElementById('reportsBrowserFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        __wfShowModal('reportsBrowserModal');
        return;
      }

      // Open Admin Modal Markup Guide (via Markdown viewer) (explicit button)
      if (closest('[data-action="open-modal-markup-guide"], #modalMarkupGuideBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureModalMarkupGuideModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-modal-markup-guide"], #modalMarkupGuideBtn'), document.getElementById('modalMarkupGuideModal'));
        const iframe = document.getElementById('modalMarkupGuideFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        __wfShowModal('modalMarkupGuideModal');
        return;
      }

      // Intercept any documentation/*.md link within Admin Settings and open in Markdown viewer modal
      const a = closest('a[href]');
      if (a) {
        try {
          const rawHref = a.getAttribute('href') || '';
          const hrefLower = rawHref.toLowerCase();
          const isMd = /\.md($|[?#])/i.test(rawHref);
          const inDocs = hrefLower.includes('/documentation/') || hrefLower.startsWith('documentation/');
          if (isMd && inDocs) {
            e.preventDefault();
            if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
            __wfEnsureModalMarkupGuideModal();
            __wfSetModalHeaderFromTrigger(a, document.getElementById('modalMarkupGuideModal'));
            __wfShowModal('modalMarkupGuideModal');
            const iframe = document.getElementById('modalMarkupGuideFrame');
            if (iframe) {
              let fileParam = rawHref.split('#')[0].split('?')[0];
              try {
                const u = new URL(rawHref, location.href);
                fileParam = u.pathname || fileParam;
              } catch(_) {}
              if (fileParam.startsWith('/')) fileParam = fileParam.slice(1);
              const idx = fileParam.toLowerCase().indexOf('documentation/');
              if (idx > -1) fileParam = fileParam.slice(idx);
              const viewerSrc = '/sections/tools/md_viewer.php?modal=1&file=' + encodeURIComponent(fileParam);
              iframe.src = viewerSrc;
            }
            return;
          }
        } catch(_) {}
      }

      // Open Email Settings modal (iframe)
      if (closest('[data-action="open-email-settings"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureEmailSettingsModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-email-settings"]'), document.getElementById('emailSettingsModal'));
        const iframe = document.getElementById('emailSettingsFrame');
        if (iframe) {
          const base = (iframe.dataset && iframe.dataset.src) ? iframe.dataset.src : (iframe.src || '/sections/tools/email_settings.php?modal=1');
          const sep = base.includes('?') ? '&' : '?';
          iframe.src = base + sep + '_=' + Date.now();
        }
        __wfShowModal('emailSettingsModal');
        return;
      }

      // Open Receipt Messages modal (iframe)
      if (closest('[data-action="open-receipt-messages"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureReceiptMessagesModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-receipt-messages"]'), document.getElementById('receiptMessagesModal'));
        const iframe = document.getElementById('receiptMessagesFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        __wfShowModal('receiptMessagesModal');
        return;
      }

      // Open Cart Button Texts modal (iframe)
      if (closest('[data-action="open-cart-button-texts"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureCartButtonTextsModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-cart-button-texts"]'), document.getElementById('cartButtonTextsModal'));
        const iframe = document.getElementById('cartButtonTextsFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        __wfShowModal('cartButtonTextsModal');
        return;
      }

      // Open Shop Encouragement Phrases modal (iframe)
      if (closest('[data-action="open-shop-encouragements"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureShopEncouragementsModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-shop-encouragements"]'), document.getElementById('shopEncouragementsModal'));
        const iframe = document.getElementById('shopEncouragementsFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        __wfShowModal('shopEncouragementsModal');
        return;
      }

      // Open Template Manager modal
      if (closest('[data-action="open-template-manager"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = __wfEnsureTemplateManagerModal();
        // Ensure overlay is at top-level and above header
        try {
          if (el && el.parentElement && el.parentElement !== document.body) {
            document.body.appendChild(el);
          }
          // Add helper classes; CSS should define these behaviors
          el.classList.add('over-header');
          el.classList.add('pointer-events-auto');
          // z-index and pointer-events are controlled via CSS classes rather than inline styles
        } catch (_) {}
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-template-manager"]'), el);
        const iframe = document.getElementById('templateManagerFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        __wfShowModal('templateManagerModal');
        return;
      }

      // Open Email History modal
      if (closest('[data-action="open-email-history"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureEmailHistoryModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-email-history"]'), document.getElementById('emailHistoryModal'));
        const iframe = document.getElementById('emailHistoryFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          const base = iframe.dataset.src;
          iframe.src = base;
        }
        __wfShowModal('emailHistoryModal');
        return;
      }

      // Open Customer Messages modal
      if (closest('[data-action="open-customer-messages"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-customer-messages"]'), document.getElementById('customerMessagesModal'));
        __wfShowModal('customerMessagesModal');
        return;
      }

      // Open AI Item Suggestions (proxy into marketing iframe)
      if (closest('[data-action="open-ai-suggestions"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-ai-suggestions"]'), document.getElementById('marketingSuggestionsProxyModal'));
        const iframe = document.getElementById('marketingSuggestionsProxyFrame');
        if (iframe) {
          const base = (iframe.dataset && iframe.dataset.src) ? iframe.dataset.src : iframe.src || '';
          iframe.src = base;
        }
        __wfShowModal('marketingSuggestionsProxyModal');
        return;
      }

      // Open AI Content Generator (proxy)
      if (closest('[data-action="open-content-generator"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-content-generator"]'), document.getElementById('contentGeneratorProxyModal'));
        const iframe = document.getElementById('contentGeneratorProxyFrame');
        if (iframe) {
          const base = (iframe.dataset && iframe.dataset.src) ? iframe.dataset.src : iframe.src || '';
          iframe.src = base;
        }
        __wfShowModal('contentGeneratorProxyModal');
        return;
      }

      // Open Newsletters (proxy)
      if (closest('[data-action="open-newsletters"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-newsletters"]'), document.getElementById('newslettersProxyModal'));
        const iframe = document.getElementById('newslettersProxyFrame');
        if (iframe) {
          const base = (iframe.dataset && iframe.dataset.src) ? iframe.dataset.src : iframe.src || '';
          iframe.src = base;
        }
        __wfShowModal('newslettersProxyModal');
        return;
      }

      // Open Automation (direct)
      if (closest('[data-action="open-automation"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = __wfEnsureAutomationModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-automation"]'), el);
        const iframe = document.getElementById('automationFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          const base = iframe.dataset.src;
          iframe.src = base;
        }
        __wfShowModal('automationModal');
        return;
      }

      // Open Discounts (proxy)
      if (closest('[data-action="open-discounts"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-discounts"]'), document.getElementById('discountsProxyModal'));
        const iframe = document.getElementById('discountsProxyFrame');
        if (iframe) {
          const base = (iframe.dataset && iframe.dataset.src) ? iframe.dataset.src : iframe.src || '';
          iframe.src = base;
        }
        __wfShowModal('discountsProxyModal');
        return;
      }

      // Open Coupons (proxy)
      if (closest('[data-action="open-coupons"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-coupons"]'), document.getElementById('couponsProxyModal'));
        const iframe = document.getElementById('couponsProxyFrame');
        if (iframe) {
          const base = (iframe.dataset && iframe.dataset.src) ? iframe.dataset.src : iframe.src || '';
          iframe.src = base;
        }
        __wfShowModal('couponsProxyModal');
        return;
      }

      // Open Shipping Settings modal (static form)
      if (closest('[data-action="open-shipping-settings"], #shippingSettingsBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-shipping-settings"], #shippingSettingsBtn'), document.getElementById('shippingSettingsModal'));
        __wfShowModal('shippingSettingsModal');
        return;
      }

      // Address Diagnostics now lives inside Shipping Settings modal
      if (closest('[data-action="open-address-diagnostics"], #addressDiagBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-address-diagnostics"], #addressDiagBtn'), document.getElementById('shippingSettingsModal'));
        __wfShowModal('shippingSettingsModal');
        return;
      }

      // Open Health & Diagnostics modal
      if (closest('[data-action="open-health-diagnostics"], #healthDiagnosticsBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('healthModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfSetModalHeaderFromTrigger(closest('[data-action="open-health-diagnostics"], #healthDiagnosticsBtn'), el);
          __wfShowModal('healthModal');
        }
        return;
      }

      // Open Secrets Manager modal
      if (closest('[data-action="open-secrets-modal"], #secretsManagerBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('secretsModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfSetModalHeaderFromTrigger(closest('[data-action="open-secrets-modal"], #secretsManagerBtn'), el);
          __wfShowModal('secretsModal');
        }
        return;
      }

      // Open Categories Management modal (iframe)
      if (closest('[data-action="open-categories"], #categoriesBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('categoriesModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfSetModalHeaderFromTrigger(closest('[data-action="open-categories"], #categoriesBtn'), el);
          // Ensure overlay policy classes
          try { el.classList.add('wf-modal-autowide'); } catch(_) {}
          try { el.classList.add('wf-modal-single-scroll'); } catch(_) {}
          const f = document.getElementById('categoriesFrame') || el.querySelector('iframe');
          if (f) {
            try { f.setAttribute('data-autosize','1'); } catch(_) {}
            try { f.setAttribute('scrolling','no'); } catch(_) {}
            if (!f.hasAttribute('data-measure-selector')) {
              try { f.setAttribute('data-measure-selector', '#categoryManagementRoot,.admin-card,.admin-table'); } catch(_) {}
            }
            const base = f.getAttribute('data-src') || '/sections/admin_categories.php?modal=1';
            const sep = base.indexOf('?') === -1 ? '?' : '&';
            const ds = `${base}${sep}_=${Date.now()}`;
            try { f.removeAttribute('src'); } catch(_) {}
            setTimeout(() => { try { f.setAttribute('src', ds); } catch(_) {} }, 0);
            try { f.addEventListener('load', () => { try { if (window.__wfEmbedAutosize && typeof window.__wfEmbedAutosize.resize === 'function') window.__wfEmbedAutosize.resize(f); } catch(_) {} }, { once: true }); } catch(_) {}
            try { markOverlayResponsive(el); } catch(_) {}
            try { attachSameOriginFallback(f, el); } catch(_) {}
          }
          __wfShowModal('categoriesModal');
          // Recompute after paint to capture header height and paddings
          try {
            const rerun = () => { try { markOverlayResponsive(el); } catch(_) {} };
            try { requestAnimationFrame(rerun); } catch(_) { setTimeout(rerun, 0); }
            setTimeout(rerun, 200);
          } catch(_) {}
        }
        return;
      }

      // Open Attributes Management modal (iframe)
      if (closest('[data-action="open-attributes"], #attributesBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('attributesModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfSetModalHeaderFromTrigger(closest('[data-action="open-attributes"], #attributesBtn'), el);
          const f = document.getElementById('attributesFrame') || el.querySelector('iframe');
          if (f) {
            try { f.setAttribute('data-autosize','1'); } catch(_) {}
            try { f.setAttribute('scrolling','no'); } catch(_) {}
            const base = f.getAttribute('data-src') || '/components/embeds/attributes_manager.php?modal=1';
            const sep = base.indexOf('?') === -1 ? '?' : '&';
            const ds = `${base}${sep}_=${Date.now()}`;
            try { f.removeAttribute('src'); } catch(_) {}
            setTimeout(() => { try { f.setAttribute('src', ds); } catch(_) {} }, 0);
            // Ensure a resize pass on load so width/height are applied immediately
            try {
              f.addEventListener('load', () => {
                try {
                  if (window.__wfEmbedAutosize && typeof window.__wfEmbedAutosize.resize === 'function') {
                    window.__wfEmbedAutosize.resize(f);
                  }
                } catch(_) {}
              }, { once: true });
            } catch(_) {}
            // Hide iframe's own scrollbars where supported
            try { f.setAttribute('scrolling', 'no'); } catch(_) {}
          }
          __wfShowModal('attributesModal');
          // Enable standardized autosize
          try { if (f && f.classList) f.classList.remove('wf-admin-embed-frame--tall','wf-embed--fill'); } catch(_) {}
          try { if (f && !f.hasAttribute('data-autosize')) f.setAttribute('data-autosize','1'); } catch(_) {}
          // Recompute after paint to capture header height and paddings
          try {
            const ov = el;
            const rerun = () => { try { markOverlayResponsive(ov); } catch(_) {} };
            try { requestAnimationFrame(rerun); } catch(_) { setTimeout(rerun, 0); }
            setTimeout(rerun, 200);
          } catch(_) {}
        }
        return;
      }

      // Customer Messages modal (native)
      if (closest('[data-action="open-customer-messages"], #customerMessagesBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-customer-messages"], #customerMessagesBtn'), document.getElementById('customerMessagesModal'));
        __wfShowModal('customerMessagesModal');
        return;
      }

      // Shopping Cart Settings modal
      if (closest('[data-action="open-shopping-cart"], #shoppingCartBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-shopping-cart"], #shoppingCartBtn'), document.getElementById('shoppingCartModal'));
        __wfShowModal('shoppingCartModal');
        return;
      }

      // Size/Color Redesign Tool modal (iframe)
      if (closest('[data-action="open-size-color-redesign"], #sizeColorRedesignBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('sizeColorRedesignModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfSetModalHeaderFromTrigger(closest('[data-action="open-size-color-redesign"], #sizeColorRedesignBtn'), el);
          const f = document.getElementById('sizeColorRedesignFrame');
          if (f) {
            const base = f.getAttribute('data-src') || '/sections/tools/size_color_redesign.php?modal=1';
            const sep = base.indexOf('?') === -1 ? '?' : '&';
            const ds = `${base}${sep}_=${Date.now()}`;
            try { f.removeAttribute('src'); } catch(_) {}
            // Small timeout to ensure reload even if URL is same except cache-buster
            setTimeout(() => { try { f.setAttribute('src', ds); } catch(_) {} }, 0);
            // Ensure autosize is enabled and fallback attached when switching to Tools
            try { if (f && !f.hasAttribute('data-autosize')) f.setAttribute('data-autosize','1'); } catch(_) {}
            // Clear message-based sizing flag so fallback can take effect if needed
            try { if (f) f.removeAttribute('data-wf-use-msg-sizing'); } catch(_) {}
            try {
              const overlay = document.getElementById('sizeColorRedesignModal');
              if (overlay) {
                markOverlayResponsive(overlay);
                attachSameOriginFallback(f, overlay);
              }
            } catch(_) {}
          }
          __wfShowModal('sizeColorRedesignModal');
          // Enable standardized autosize
          try { const fx = document.getElementById('sizeColorRedesignFrame') || el.querySelector('iframe'); if (fx && !fx.hasAttribute('data-autosize')) fx.setAttribute('data-autosize','1'); } catch(_) {}
          try { markOverlayResponsive(el); } catch(_) {}
          try { const fx = document.getElementById('sizeColorRedesignFrame') || el.querySelector('iframe'); if (fx) attachSameOriginFallback(fx, el); } catch(_) {}
          // Single-scroll is enforced by CSS helper wf-modal-single-scroll on the overlay
        }
        return;
      }

      

      

      

      // Dashboard Configuration modal (native)
      if (closest('[data-action="open-dashboard-config"], #dashboardConfigBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-dashboard-config"], #dashboardConfigBtn'), document.getElementById('dashboardConfigModal'));
        __wfShowModal('dashboardConfigModal');
        return;
      }

      // AI Tools (legacy) → route to unified modal Tools tab
      if (closest('[data-action="open-ai-tools"], #aiToolsBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        try { __wfOpenUnifiedAIModal('tools', closest('[data-action="open-ai-tools"], #aiToolsBtn')); } catch(_) { __wfOpenUnifiedAIModal('tools'); }
        return;
      }

      // AI Settings: open dedicated Provider modal
      if (closest('[data-action="open-ai-settings"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('aiSettingsModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          try { el.classList.add('over-header'); } catch(_) {}
          __wfSetModalHeaderFromTrigger(closest('[data-action="open-ai-settings"]'), el);
          try { __wfHideModal('aiUnifiedModal'); } catch(_) {}
          __wfShowModal('aiSettingsModal');
          try { if (typeof window !== 'undefined' && typeof window.loadAISettings === 'function') window.loadAISettings(); else if (typeof __wfAI_loadSettingsAndRender === 'function') __wfAI_loadSettingsAndRender(); } catch(_) {}
          try { if (typeof window !== 'undefined' && typeof window.loadAIProviders === 'function') window.loadAIProviders(); } catch(_) {}
        }
        return;
      }

      // Artificial Intelligence (Unified) modal opener (Tools-first)
      if (closest('[data-action="open-ai-unified"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfOpenUnifiedAIModal('tools', closest('[data-action="open-ai-unified"]'));
        return;
      }

      // Unified AI modal legacy tab switching (no-op if tabs removed)
      if (closest('[data-action="ai-unified-tab"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const btn = closest('[data-action="ai-unified-tab"]');
        const tab = btn && btn.getAttribute('data-tab') ? btn.getAttribute('data-tab') : 'settings';
        __wfAIModalSetView(tab);
        return;
      }

      // Unified AI modal header view toggles
      if (closest('[data-action="ai-view-tools"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfAIModalSetView('tools');
        return;
      }
      if (closest('[data-action="ai-view-settings"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfAIModalSetView('settings');
        return;
      }

      // Save AI Settings
      if (closest('[data-action="save-ai-settings"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const q = (id) => document.getElementById(id);
        const sel = q('aiProvider');
        const provider = (sel && sel.value) || 'jons_ai'; // sole source-of-truth
        const settings = {
          ai_provider: provider,
          openai_api_key: (q('openai_api_key') && q('openai_api_key').value) || '',
          openai_model: (q('openai_model') && q('openai_model').value) || 'gpt-3.5-turbo',
          anthropic_api_key: (q('anthropic_api_key') && q('anthropic_api_key').value) || '',
          anthropic_model: (q('anthropic_model') && q('anthropic_model').value) || 'claude-3-haiku-20240307',
          google_api_key: (q('google_api_key') && q('google_api_key').value) || '',
          google_model: (q('google_model') && q('google_model').value) || 'gemini-pro',
          meta_api_key: (q('meta_api_key') && q('meta_api_key').value) || '',
          meta_model: (q('meta_model') && q('meta_model').value) || 'meta-llama/llama-3.1-70b-instruct',
          ai_temperature: parseFloat((q('ai_temperature') && q('ai_temperature').value) || (q('aiTemperature') && q('aiTemperature').value) || 0.7),
          ai_max_tokens: parseInt((q('ai_max_tokens') && q('ai_max_tokens').value) || (q('aiMaxTokens') && q('aiMaxTokens').value) || 1000, 10),
          ai_timeout: parseInt((q('ai_timeout') && q('ai_timeout').value) || (q('aiTimeout') && q('aiTimeout').value) || 30, 10),
          fallback_to_local: !!((q('fallback_to_local') && q('fallback_to_local').checked) || (q('fallbackToLocal') && q('fallbackToLocal').checked)),
          ai_brand_voice: (q('ai_brand_voice') && q('ai_brand_voice').value) || '',
          ai_content_tone: (q('ai_content_tone') && q('ai_content_tone').value) || 'professional',
          ai_cost_temperature: parseFloat((q('ai_cost_temperature') && q('ai_cost_temperature').value) || 0.7),
          ai_price_temperature: parseFloat((q('ai_price_temperature') && q('ai_price_temperature').value) || 0.7),
          ai_cost_multiplier_base: parseFloat((q('ai_cost_multiplier_base') && q('ai_cost_multiplier_base').value) || 1.0),
          ai_price_multiplier_base: parseFloat((q('ai_price_multiplier_base') && q('ai_price_multiplier_base').value) || 1.0),
          ai_conservative_mode: !!(q('ai_conservative_mode') && q('ai_conservative_mode').checked),
          ai_market_research_weight: parseFloat((q('ai_market_research_weight') && q('ai_market_research_weight').value) || 0.3),
          ai_cost_plus_weight: parseFloat((q('ai_cost_plus_weight') && q('ai_cost_plus_weight').value) || 0.4),
          ai_value_based_weight: parseFloat((q('ai_value_based_weight') && q('ai_value_based_weight').value) || 0.3)
        };
        const notify = (title, msg, type) => { try { if (typeof window.showNotification === 'function') window.showNotification(title, msg, type); } catch(_) {} };
        try { console.log('[AI Settings] Saving payload:', settings); } catch(_) {}
        notify('Saving AI Settings', 'Saving…', 'info');
        const doPost = async () => {
          try {
            const isLocal = (() => { try { const h = window.location.hostname; return h === 'localhost' || h === '127.0.0.1'; } catch(_) { return false; } })();
            const options = isLocal ? { headers: { 'X-WF-Dev-Admin': '1' } } : {};
            const r = await ApiClient.post('/api/ai_settings.php?action=update_settings', settings, options);
            if (r && r.success) {
              notify('AI Settings Saved', 'AI settings saved successfully!', 'success');
              // Immediately reflect the newly selected provider in UI
              try {
                const sel = document.getElementById('aiProvider');
                if (sel) sel.value = settings.ai_provider;
              } catch(_) {}
              try { __wfAI_loadSettingsAndRender(); } catch(_) {}
            } else {
              notify('AI Settings Error', (r && (r.error || r.message)) || 'Failed to save AI settings', 'error');
            }
          } catch (err) {
            const msg = (err && err.message) ? String(err.message) : '';
            if (/\b403\b/.test(msg)) {
              notify('AI Settings Error', 'Admin access required to save AI settings. Please log in and try again.', 'error');
            } else {
              notify('AI Settings Error', msg || 'Request failed', 'error');
            }
          }
        };
        doPost();
        return;
      }

      // Test AI Provider
      if (closest('[data-action="test-ai-provider"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const sel = document.getElementById('aiProvider');
        const provider = (sel && sel.value) || 'jons_ai'; // sole source-of-truth
        __wfShowStatusModal('AI Provider Test', 'Testing ' + provider + '…');
        const url = '/api/ai_settings.php?action=test_provider&provider=' + encodeURIComponent(provider);
        try { console.log('[AI Settings] Testing provider:', provider, 'GET', url); } catch(_) {}
        const doGet = async () => {
          try {
            const isLocal = (() => { try { const h = window.location.hostname; return h === 'localhost' || h === '127.0.0.1'; } catch(_) { return false; } })();
            const r = await ApiClient.request(url, { method: 'GET', headers: isLocal ? { 'X-WF-Dev-Admin': '1' } : {} });
            if (r && r.success) {
              __wfUpdateStatusModal('✅ ' + provider + ' provider test successful!');
            } else {
              __wfUpdateStatusModal('❌ ' + provider + ' provider test failed' + (r && r.message ? ': ' + r.message : ''));
            }
          } catch (err) {
            __wfUpdateStatusModal('❌ Test failed: ' + ((err && err.message) || 'Request error'));
          }
        };
        doGet();
        return;
      }

      // Square Settings modal (native form)
      if (closest('[data-action="open-square-settings"], #squareSettingsBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-square-settings"], #squareSettingsBtn'), document.getElementById('squareSettingsModal'));
        __wfShowModal('squareSettingsModal');
        return;
      }

      // Let global Account Settings modal handler manage [data-action="open-account-settings"] clicks

      // Handle close buttons
      if (closest('[data-action="close-admin-modal"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const overlay = closest('.admin-modal-overlay');
        if (overlay && overlay.id) { __wfHideModal(overlay.id); }
        return;
      }

      // Handle core modal openers
      if (closest('[data-action="open-admin-help-modal"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('adminHelpDocsModal');
        return;
      }

      // Open CSS Catalog modal
      if (closest('[data-action="open-css-catalog"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureCssCatalogModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-css-catalog"]'), document.getElementById('cssCatalogModal'));
        __wfShowModal('cssCatalogModal');
        const iframe = document.getElementById('cssCatalogFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Room Map Manager modal (ensure + prime)
      if (closest('[data-action="open-room-map-manager"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureRoomMapEditorModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action=\"open-room-map-manager\"]'), document.getElementById('roomMapManagerModal'));
        const iframe = document.getElementById('roomMapManagerFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.setAttribute('src', iframe.dataset.src);
        }
        __wfShowModal('roomMapManagerModal');
        return;
      }

      // Open Area-Item Mapper modal (ensure + prime)
      if (closest('[data-action="open-area-item-mapper"]')) {
        const canEnsure = (typeof __wfEnsureAreaItemMapperModal === 'function');
        if (!canEnsure) {
          // Do not block other handlers; allow global fallbacks to process this click
          return;
        }
        let ensured = false;
        try { __wfEnsureAreaItemMapperModal(); ensured = true; } catch(_) { ensured = false; }
        if (ensured) {
          e.preventDefault();
          if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
          __wfSetModalHeaderFromTrigger(closest('[data-action=\"open-area-item-mapper\"]'), document.getElementById('areaItemMapperModal'));
          const iframe = document.getElementById('areaItemMapperFrame');
          if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
            iframe.dataset.loading = '1';
            iframe.dataset.loaded = '0';
            iframe.addEventListener('load', () => { try { iframe.dataset.loaded = '1'; iframe.dataset.loading = '0'; } catch(_) {} }, { once: true });
            iframe.src = iframe.dataset.src;
          }
          __wfShowModal('areaItemMapperModal');
          // Ensure standardized autosize
          try { const ov = document.getElementById('areaItemMapperModal'); if (ov) markOverlayResponsive(ov); } catch(_) {}
          try { const ov = document.getElementById('areaItemMapperModal'); if (iframe && ov) attachSameOriginFallback(iframe, ov); } catch(_) {}
          try {
            if (iframe && window.__wfEmbedAutosize && typeof window.__wfEmbedAutosize.resize === 'function') {
              window.__wfEmbedAutosize.resize(iframe);
              setTimeout(() => { try { window.__wfEmbedAutosize.resize(iframe); } catch(_) {} }, 250);
            }
          } catch(_) {}
          try {
            const ov = document.getElementById('areaItemMapperModal');
            const rerun = () => { try { markOverlayResponsive(ov); } catch(_) {} };
            try { requestAnimationFrame(rerun); } catch(_) { setTimeout(rerun, 0); }
            setTimeout(rerun, 200);
          } catch(_) {}
          return;
        }
        // If ensure failed, let other handlers (fallbacks) run
        return;
      }
    } catch (_) {}
  };
  // Register both capture and bubble to be resilient
  document.addEventListener('click', handler, true);
  document.addEventListener('click', handler);
})();

function __wfSelectAITab(tab) {
  try {
    const settingsPanel = document.getElementById('aiUnifiedSettingsPanel');
    const toolsPanel = document.getElementById('aiUnifiedToolsPanel');
    const btnSettings = document.getElementById('aiTabSettings');
    const btnTools = document.getElementById('aiTabTools');
    const isTools = String(tab || 'tools') === 'tools';
    if (settingsPanel && toolsPanel) {
      if (isTools) { toolsPanel.classList.remove('hidden'); settingsPanel.classList.add('hidden'); }
      else { settingsPanel.classList.remove('hidden'); toolsPanel.classList.add('hidden'); }
    }
    if (btnSettings && btnTools) {
      btnSettings.classList.remove('is-active');
      btnTools.classList.remove('is-active');
      if (isTools) btnTools.classList.add('is-active'); else btnSettings.classList.add('is-active');
      try { btnSettings.setAttribute('aria-selected', !isTools ? 'true' : 'false'); } catch(_) {}
      try { btnTools.setAttribute('aria-selected', isTools ? 'true' : 'false'); } catch(_) {}
    }
    if (isTools) {
      const f = document.getElementById('aiUnifiedToolsFrame');
      if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
        const ds = f.getAttribute('data-src') || '/sections/ai_tools.php?modal=1';
        f.setAttribute('src', ds);
      }
      // Ensure autosize is enabled and fallback attached when switching to Tools
      try { if (f && !f.hasAttribute('data-autosize')) f.setAttribute('data-autosize','1'); } catch(_) {}
      // Clear message-based sizing flag so fallback can take effect if needed
      try { if (f) f.removeAttribute('data-wf-use-msg-sizing'); } catch(_) {}
      try {
        const overlay = document.getElementById('aiUnifiedModal');
        if (overlay) {
          markOverlayResponsive(overlay);
          attachSameOriginFallback(f, overlay);
        }
      } catch(_) {}
    } else {
      try { if (typeof window !== 'undefined' && typeof window.loadAISettings === 'function') window.loadAISettings(); else if (typeof __wfAI_loadSettingsAndRender === 'function') __wfAI_loadSettingsAndRender(); } catch(_) {}
      try { if (typeof window !== 'undefined' && typeof window.loadAIProviders === 'function') window.loadAIProviders(); } catch(_) {}
    }
  } catch(_) {}
}

// ... (rest of the code remains the same)
function __wfOpenUnifiedAIModal(initialTab, triggerEl) {
  try {
    const el = document.getElementById('aiUnifiedModal');
    if (!el) return;
    try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
    try { if (typeof __wfSetModalHeaderFromTrigger === 'function') __wfSetModalHeaderFromTrigger(triggerEl || null, el); } catch(_) {}
    // Prepare iframe and sizing BEFORE showing to avoid width/height flash
    try { const f = document.getElementById('aiUnifiedToolsFrame'); if (f && !f.hasAttribute('data-autosize')) f.setAttribute('data-autosize','1'); } catch(_) {}
    try { markOverlayResponsive(el); } catch(_) {}
    __wfSelectAITab(String(initialTab || 'tools'));
    try { const f = document.getElementById('aiUnifiedToolsFrame'); if (f) { try { f.removeAttribute('data-wf-use-msg-sizing'); } catch(_) {}; attachSameOriginFallback(f, el); } } catch(_) {}
    __wfShowModal('aiUnifiedModal');
  } catch(_) {}
}

// Export for potential use by other modules
export { __wfShowModal, __wfHideModal };
