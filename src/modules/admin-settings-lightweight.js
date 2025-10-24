// Lazy modal factory: Background Manager (embeds dashboard #background)
// Lazy modal factory: Room Map Manager
const __wfEnsureRoomMapEditorModal = () => {
  let el = document.getElementById('roomMapManagerModal');
  // ALWAYS force recreation to pick up template changes
  if (el) {
    console.log('[RoomMapManager] Removing existing modal to force refresh');
    el.remove();
    el = null;
  }
  el = document.createElement('div');
  el.id = 'roomMapManagerModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'roomMapManagerTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--room-map admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="roomMapManagerTitle" class="admin-card-title">🗺️ Room Map Manager (New Design)</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body rme-modal-body">
        <iframe id="roomMapManagerFrame" title="Room Map Manager" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/room_map_editor.php?modal=1&amp;vite=dev" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  console.log('[RoomMapManager] New modal created with iframe');
  return el;
};

// Lazy modal factory: Reports & Documentation Browser
const __wfEnsureReportsBrowserModal = () => {
  let el = document.getElementById('reportsBrowserModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'reportsBrowserModal';
  el.className = 'admin-modal-overlay hidden over-header';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'reportsBrowserTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="reportsBrowserTitle" class="admin-card-title">Reports &amp; Documentation Browser</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body admin-modal-body--lg">
        <iframe id="reportsBrowserFrame" title="Reports &amp; Documentation Browser" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/reports_browser.php?modal=1" referrerpolicy="no-referrer"></iframe>
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
  el.className = 'admin-modal-overlay hidden over-header';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'modalMarkupGuideTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="modalMarkupGuideTitle" class="admin-card-title">Admin Modal Markup Guide</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="modalMarkupGuideFrame" title="Admin Modal Markup Guide" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/md_viewer.php?modal=1&amp;file=documentation/ADMIN_MODAL_MARKUP_GUIDE.md" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

// --- Global Action Icons preference (icon-only buttons in Actions column) ---
const __wfACTION_ICONS_KEY = 'wf_admin_actions_icons';
const __wfUpdateActionIconsToggleLabel = () => {
  try {
    const btn = document.getElementById('actionIconsToggleBtn');
    if (!btn) return;
    const on = (localStorage.getItem(__wfACTION_ICONS_KEY) || '') === '1';
    btn.textContent = on ? 'Action Buttons: Icons' : 'Action Buttons: Text Labels';
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.title = on ? 'Switch to text labels in Actions columns' : 'Switch to icon-only in Actions columns';
  } catch(_) {}
};
// Initialize label immediately
try { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', __wfUpdateActionIconsToggleLabel, { once: true }); else __wfUpdateActionIconsToggleLabel(); } catch(_) {}
// Lazy modal factory: Area-Item Mapper
const __wfEnsureAreaItemMapperModal = () => {
  let el = document.getElementById('areaItemMapperModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'areaItemMapperModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'areaItemMapperTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="areaItemMapperTitle" class="admin-card-title">🧭 Area-Item Mapper</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="areaItemMapperFrame" title="Area-Item Mapper" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/area_item_mapper.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

// Lazy modal factory: Email Settings (iframe to standalone settings page)
const __wfEnsureEmailSettingsModal = () => {
  let el = document.getElementById('emailSettingsModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'emailSettingsModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'emailSettingsTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="emailSettingsTitle" class="admin-card-title">✉️ Email Settings</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="emailSettingsFrame" title="Email Settings" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/email_settings.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  return el;
};

// Lazy modal factory: Receipt Messages (iframe to manager page)
const __wfEnsureReceiptMessagesModal = () => {
  let el = document.getElementById('receiptMessagesModal');
  if (el) {
    try {
      const panel = el.querySelector('.admin-modal');
      if (panel) {
        panel.classList.remove('admin-modal--lg','admin-modal--lg-narrow');
        panel.classList.add('admin-modal--xl','admin-modal--actions-in-header');
      }
    } catch(_) {}
    return el;
  }
  el = document.createElement('div');
  el.id = 'receiptMessagesModal';
  el.className = 'admin-modal-overlay hidden';
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
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="receiptMessagesFrame" title="Receipt Messages" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/receipt_messages_manager.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try {
    const body = el.querySelector('.modal-body');
    if (body) body.classList.add('wf-modal-body--scroll');
    // Keep built-in height class; no embed-fill needed
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
          if (s) { s.textContent = d.message || ''; s.style.color = d.ok ? '#065f46' : '#b91c1c'; }
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
      const panel = el.querySelector('.admin-modal');
      if (panel) {
        panel.classList.remove('admin-modal--lg','admin-modal--lg-narrow','admin-modal--md','admin-modal--xl','admin-modal--full','admin-modal--sm','admin-modal--xs','admin-modal--square-200','admin-modal--square-260');
        panel.classList.add('admin-modal--square-300');
      }
      const body = el.querySelector('.modal-body');
      if (body) { body.style.padding = '0'; body.style.overflow = 'hidden'; body.style.display = 'flex'; body.style.minHeight = '0'; }
      const frame = el.querySelector('#cartButtonTextsFrame');
      if (frame) { frame.style.width = '100%'; frame.style.height = '100%'; frame.style.border = '0'; frame.style.display = 'block'; }
    } catch(_) {}
    return el;
  }
  el = document.createElement('div');
  el.id = 'cartButtonTextsModal';
  el.className = 'admin-modal-overlay hidden';
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
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="cartButtonTextsFrame" title="Cart Button Texts" class="wf-admin-embed-frame" data-src="/sections/tools/cart_button_texts.php?modal=1" referrerpolicy="no-referrer"></iframe>
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
          if (s) { s.textContent = d.message || ''; s.style.color = d.ok ? '#065f46' : '#b91c1c'; }
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
  el.className = 'admin-modal-overlay hidden';
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
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="shopEncouragementsFrame" title="Shop Encouragement Phrases" class="wf-admin-embed-frame" data-src="/sections/tools/shop_encouragement_phrases.php?modal=1" referrerpolicy="no-referrer"></iframe>
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
          if (s) { s.textContent = d.message || ''; s.style.color = d.ok ? '#065f46' : '#b91c1c'; }
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
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'templateManagerTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="templateManagerTitle" class="admin-card-title">📧 Email Templates</h2>
        <div class="modal-header-actions">
          <span id="templateManagerStatus" class="text-sm text-gray-600" aria-live="polite"></span>
          <button type="button" id="templateManagerSave" class="btn btn-primary btn-sm">Save</button>
        </div>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="templateManagerFrame" title="Email Templates" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/template_manager.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
  try {
    const body = el.querySelector('.modal-body');
    if (body) body.classList.add('wf-modal-body--fill');
    const frame = el.querySelector('#templateManagerFrame');
    if (frame) frame.classList.add('wf-embed--fill');
    const saveBtn = el.querySelector('#templateManagerSave');
    if (saveBtn) {
      saveBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const f = el.querySelector('#templateManagerFrame');
        try { if (f && f.contentWindow) f.contentWindow.postMessage({ source:'wf-tm-parent', type:'save' }, '*'); } catch(_) {}
      });
    }
  } catch(_) {}
  try {
    if (!window.__wfTMStatusListener) {
      window.addEventListener('message', (ev) => {
        try {
          const d = ev && ev.data; if (!d || d.source !== 'wf-tm' || d.type !== 'status') return;
          const s = document.getElementById('templateManagerStatus');
          if (s) { s.textContent = d.message || ''; s.style.color = d.ok ? '#065f46' : '#b91c1c'; }
        } catch (_) {}
      });
      window.__wfTMStatusListener = true;
    }
  } catch(_) {}
  return el;
};

// Lazy modal factory: Email History (uses Reports/Docs browser as a proxy)
const __wfEnsureEmailHistoryModal = () => {
  let el = document.getElementById('emailHistoryModal');
  if (el) return el;
  el = document.createElement('div');
  el.id = 'emailHistoryModal';
  el.className = 'admin-modal-overlay hidden';
  el.setAttribute('aria-hidden', 'true');
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'true');
  el.setAttribute('tabindex', '-1');
  el.setAttribute('aria-labelledby', 'emailHistoryTitle');
  el.innerHTML = `
    <div class="admin-modal admin-modal-content admin-modal--lg admin-modal--actions-in-header">
      <div class="modal-header">
        <h2 id="emailHistoryTitle" class="admin-card-title">📬 Email History</h2>
        <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
      </div>
      <div class="modal-body">
        <iframe id="emailHistoryFrame" title="Email History" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/email_history.php?modal=1" referrerpolicy="no-referrer"></iframe>
      </div>
    </div>
  `;
  document.body.appendChild(el);
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

// Import the coordinator module
import '../modules/admin-settings-coordinator.js';

// Immediately install lightweight modal helpers
const __wfShowModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return false;
  el.removeAttribute('hidden'); // Remove HTML hidden attribute
  el.classList.remove('hidden');
  el.classList.add('show');
  el.setAttribute('aria-hidden', 'false');
  if (el.hasAttribute('style')) {
    const attr = el.getAttribute('style');
    if (!attr || /display\s*:/.test(attr)) {
      el.removeAttribute('style');
    }
  }
  return true;
};

const __wfHideModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return false;
  try { el.setAttribute('hidden', ''); } catch(_) {}
  el.classList.add('hidden');
  el.classList.remove('show');
  el.setAttribute('aria-hidden', 'true');
  return true;
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
      '    <label for="meta_model" class="block text-sm font-medium mb-1">Meta Model</label>',
      '    <select id="meta_model" class="form-select w-full"></select>',
      '  </div>',
      '</div>'
    ].join('');
  } else {
    html = '<div class="text-sm text-gray-500">Using local AI. No keys required.</div>';
  }
  container.innerHTML = html;
  // Populate fallback models
  __wfAI_populateModelDropdown(prov, (s && s[`${prov}_model`]) || '');

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
      const j = await window.ApiClient.get('/api/ai_settings.php?action=get_settings');
      if (j && j.success) settings = j.settings || {};
    } catch(_) { settings = {}; }
    __wfAI_renderProviderUI(provider, settings);
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

      // Open Reports & Documentation Browser (iframe)
      if (closest('[data-action="open-reports-browser"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureReportsBrowserModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-reports-browser"]'), document.getElementById('reportsBrowserModal'));
        __wfShowModal('reportsBrowserModal');
        const iframe = document.getElementById('reportsBrowserFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Admin Modal Markup Guide (via Markdown viewer) (explicit button)
      if (closest('[data-action="open-modal-markup-guide"], #modalMarkupGuideBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureModalMarkupGuideModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-modal-markup-guide"], #modalMarkupGuideBtn'), document.getElementById('modalMarkupGuideModal'));
        __wfShowModal('modalMarkupGuideModal');
        const iframe = document.getElementById('modalMarkupGuideFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
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
        __wfShowModal('emailSettingsModal');
        const iframe = document.getElementById('emailSettingsFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Receipt Messages modal (iframe)
      if (closest('[data-action="open-receipt-messages"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureReceiptMessagesModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-receipt-messages"]'), document.getElementById('receiptMessagesModal'));
        __wfShowModal('receiptMessagesModal');
        const iframe = document.getElementById('receiptMessagesFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Cart Button Texts modal (iframe)
      if (closest('[data-action="open-cart-button-texts"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureCartButtonTextsModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-cart-button-texts"]'), document.getElementById('cartButtonTextsModal'));
        __wfShowModal('cartButtonTextsModal');
        const iframe = document.getElementById('cartButtonTextsFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Shop Encouragement Phrases modal (iframe)
      if (closest('[data-action="open-shop-encouragements"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureShopEncouragementsModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-shop-encouragements"]'), document.getElementById('shopEncouragementsModal'));
        __wfShowModal('shopEncouragementsModal');
        const iframe = document.getElementById('shopEncouragementsFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
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
        __wfShowModal('templateManagerModal');
        const iframe = document.getElementById('templateManagerFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Email History modal
      if (closest('[data-action="open-email-history"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureEmailHistoryModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-email-history"]'), document.getElementById('emailHistoryModal'));
        __wfShowModal('emailHistoryModal');
        const iframe = document.getElementById('emailHistoryFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
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
        __wfShowModal('marketingSuggestionsProxyModal');
        const iframe = document.getElementById('marketingSuggestionsProxyFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open AI Content Generator (proxy)
      if (closest('[data-action="open-content-generator"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-content-generator"]'), document.getElementById('contentGeneratorProxyModal'));
        __wfShowModal('contentGeneratorProxyModal');
        const iframe = document.getElementById('contentGeneratorProxyFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Newsletters (proxy)
      if (closest('[data-action="open-newsletters"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-newsletters"]'), document.getElementById('newslettersProxyModal'));
        __wfShowModal('newslettersProxyModal');
        const iframe = document.getElementById('newslettersProxyFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Automation (proxy)
      if (closest('[data-action="open-automation"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-automation"]'), document.getElementById('automationProxyModal'));
        __wfShowModal('automationProxyModal');
        const iframe = document.getElementById('automationProxyFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Discounts (proxy)
      if (closest('[data-action="open-discounts"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-discounts"]'), document.getElementById('discountsProxyModal'));
        __wfShowModal('discountsProxyModal');
        const iframe = document.getElementById('discountsProxyFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
        return;
      }

      // Open Coupons (proxy)
      if (closest('[data-action="open-coupons"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-coupons"]'), document.getElementById('couponsProxyModal'));
        __wfShowModal('couponsProxyModal');
        const iframe = document.getElementById('couponsProxyFrame');
        if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
          iframe.src = iframe.dataset.src;
        }
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

      // Open Address Diagnostics modal (iframe)
      if (closest('[data-action="open-address-diagnostics"], #addressDiagBtn')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-address-diagnostics"], #addressDiagBtn'), document.getElementById('addressDiagnosticsModal'));
        __wfShowModal('addressDiagnosticsModal');
        const iframe = document.getElementById('addressDiagnosticsFrame');
        if (iframe) {
          const needsSrc = (!iframe.src || iframe.src === 'about:blank');
          const ds = (iframe.dataset && iframe.dataset.src) ? iframe.dataset.src : '/sections/tools/address_diagnostics.php?modal=1';
          if (needsSrc) { iframe.src = ds; }
        }
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
          __wfShowModal('categoriesModal');
          const f = el.querySelector('iframe');
          if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
            const ds = f.getAttribute('data-src') || '/sections/admin_categories.php?modal=1';
            f.setAttribute('src', ds);
          }
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
          __wfShowModal('attributesModal');
          const f = document.getElementById('attributesFrame');
          if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
            const ds = f.getAttribute('data-src') || '/components/embeds/attributes_manager.php?modal=1';
            f.setAttribute('src', ds);
          }
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
          __wfShowModal('sizeColorRedesignModal');
          const f = document.getElementById('sizeColorRedesignFrame');
          if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
            const ds = f.getAttribute('data-src') || '/sections/tools/size_color_redesign.php?modal=1';
            f.setAttribute('src', ds);
          }
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

      // AI Tools modal (iframe)
      if (closest('[data-action="open-ai-tools"], #aiToolsBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('aiToolsModal');
        if (el) {
          try { if (el.parentElement && el.parentElement !== document.body) document.body.appendChild(el); } catch(_) {}
          __wfSetModalHeaderFromTrigger(closest('[data-action="open-ai-tools"], #aiToolsBtn'), el);
          __wfShowModal('aiToolsModal');
          const f = document.getElementById('aiToolsFrame');
          if (f && (!f.getAttribute('src') || f.getAttribute('src') === 'about:blank')) {
            const ds = f.getAttribute('data-src') || '/sections/admin_marketing.php?modal=1';
            f.setAttribute('src', ds);
          }
        }
        return;
      }

      // AI Settings modal (iframe)
      if (closest('[data-action="open-ai-settings"], #aiSettingsBtn')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const el = document.getElementById('aiSettingsModal');
        if (el) {
          try {
            if (el.parentElement && el.parentElement !== document.body) {
              document.body.appendChild(el);
            }
            el.classList.add('over-header');
          } catch(_) {}
          __wfSetModalHeaderFromTrigger(closest('[data-action="open-ai-settings"], #aiSettingsBtn'), el);
          __wfShowModal('aiSettingsModal');
          try { if (typeof window !== 'undefined' && typeof window.loadAISettings === 'function') window.loadAISettings(); else __wfAI_loadSettingsAndRender(); } catch(_) { __wfAI_loadSettingsAndRender(); }
          try { if (typeof window !== 'undefined' && typeof window.loadAIProviders === 'function') window.loadAIProviders(); } catch(_) {}
        }
        return;
      }

      // Save AI Settings
      if (closest('[data-action="save-ai-settings"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const q = (id) => document.getElementById(id);
        const sp = document.querySelector('input[name="ai_provider"]:checked');
        const sel = q('aiProvider');
        const provider = (sp && sp.value) || (sel && sel.value) || 'jons_ai';
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
        notify('Saving AI Settings', 'Saving…', 'info');
        const doPost = async () => {
          try {
            const r = await window.ApiClient.post('/api/ai_settings.php?action=update_settings', settings);
            if (r && r.success) {
              notify('AI Settings Saved', 'AI settings saved successfully!', 'success');
              try { __wfAI_loadSettingsAndRender(); } catch(_) {}
            } else {
              notify('AI Settings Error', (r && (r.error || r.message)) || 'Failed to save AI settings', 'error');
            }
          } catch (err) {
            notify('AI Settings Error', (err && err.message) || 'Request failed', 'error');
          }
        };
        doPost();
        return;
      }

      // Test AI Provider
      if (closest('[data-action="test-ai-provider"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const sp = document.querySelector('input[name="ai_provider"]:checked');
        const sel = document.getElementById('aiProvider');
        const provider = (sp && sp.value) || (sel && sel.value) || 'jons_ai';
        const notify = (title, msg, type) => { try { if (typeof window.showNotification === 'function') window.showNotification(title, msg, type); } catch(_) {} };
        notify('Testing AI Provider', 'Testing ' + provider + '…', 'info');
        const url = '/api/ai_settings.php?action=test_provider&provider=' + encodeURIComponent(provider);
        const doGet = async () => {
          try {
            if (typeof window.ApiClient !== 'undefined' && window.ApiClient && typeof window.ApiClient.get === 'function') {
              const r = await window.ApiClient.get(url);
              if (r && r.success) notify('AI Provider Test', '✅ ' + provider + ' provider test successful!', 'success');
              else notify('AI Provider Test', '❌ ' + provider + ' provider test failed' + (r && r.message ? ': ' + r.message : ''), 'error');
            } else {
              const res = await fetch(url, { credentials: 'include' });
              const j = await res.json().catch(() => ({}));
              if (j && j.success) notify('AI Provider Test', '✅ ' + provider + ' provider test successful!', 'success');
              else notify('AI Provider Test', '❌ ' + provider + ' provider test failed' + (j && j.message ? ': ' + j.message : ''), 'error');
            }
          } catch (err) {
            notify('AI Provider Test', '❌ Test failed: ' + ((err && err.message) || 'Request error'), 'error');
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
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-room-map-manager"]'), document.getElementById('roomMapManagerModal'));
        __wfShowModal('roomMapManagerModal');
        const iframe = document.getElementById('roomMapManagerFrame');
        if (iframe && iframe.dataset && iframe.dataset.src) {
          const target = iframe.dataset.src;
          // ALWAYS reload with fresh cache-busting parameter
          const bust = (target.includes('?') ? '&' : '?') + '_v=' + Date.now() + '&_r=' + Math.random();
          iframe.setAttribute('src', target + bust);
        }
        return;
      }

      // Open Area-Item Mapper modal (ensure + prime)
      if (closest('[data-action="open-area-item-mapper"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfEnsureAreaItemMapperModal();
        __wfSetModalHeaderFromTrigger(closest('[data-action="open-area-item-mapper"]'), document.getElementById('areaItemMapperModal'));
        __wfShowModal('areaItemMapperModal');
        const iframe = document.getElementById('areaItemMapperFrame');
        if (iframe && iframe.dataset) {
          if (iframe.dataset.loaded === '1' || iframe.dataset.loading === '1') {
            return;
          }
          const needsSrc = (!iframe.src || iframe.src === 'about:blank');
          const ds = iframe.dataset.src;
          if (needsSrc && ds) {
            iframe.dataset.loading = '1';
            iframe.addEventListener('load', () => { try { iframe.dataset.loaded = '1'; iframe.dataset.loading = '0'; } catch(_) {} }, { once: true });
            iframe.src = ds;
          }
        }
        return;
      }

    } catch (_) {}
  };

  // Register both capture and bubble to be resilient
  document.addEventListener('click', handler, true);
  document.addEventListener('click', handler);
})();

// Export for potential use by other modules
export { __wfShowModal, __wfHideModal };
