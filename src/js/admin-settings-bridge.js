// Admin Settings Bridge
// Lightweight initializer to migrate inline scripts to Vite-managed code
// - Loads Email settings (prefers BusinessSettingsAPI; falls back to legacy endpoint)
// - Wires basic UI behaviors (toggle SMTP section)
//
// Important: avoid top-level imports that may trigger Vite transform errors during dev.
// We use dynamic imports within functions so a single problematic module doesn't block
// the entire Settings page from wiring up its UI.

function byId(id){ return document.getElementById(id); }
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
        || path.includes('/admin') && (new URLSearchParams(window.location.search).get('section') === 'settings')
      ))
    );
    if (!isSettings) { try { console.info('[AdminSettingsBridge] skip: not settings route'); } catch(_) {} return; }
    try { console.info('[AdminSettingsBridge] active on settings route'); } catch(_) {}
    // Defer email section initialization until the user opens the Email Settings modal
    let __emailInitDone = false;
    const _initEmailIfNeeded = async () => {
      if (__emailInitDone) return;
      try {
        await initEmailSection();
      } catch (e) { try { console.warn('[AdminSettingsBridge] initEmailSection failed (deferred)', e); } catch(_) {} }
      __emailInitDone = true;
    };

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
          h2.textContent = id === 'dashboardConfigModal' ? '📊 Dashboard Configuration' : 'Settings';
          const x = document.createElement('button');
          x.type = 'button'; x.className = 'admin-modal-close'; x.setAttribute('aria-label','Close');
          // Match the wired handler for dashboard config; otherwise use generic close-admin-modal
          if (id === 'dashboardConfigModal') x.setAttribute('data-action', 'close-dashboard-config');
          else x.setAttribute('data-action', 'close-admin-modal');
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
      ];
      managed.forEach((id) => {
        const el = getModalEl(id);
        if (el) {
          el.classList.add('hidden');
          el.classList.remove('show');
          el.setAttribute('aria-hidden', 'true');
        }
      });
    } catch(_) {}

    // ------------------------------
    // Dashboard Config Fallback (Lightweight)
    // ------------------------------
    async function dashApi(path, payload) {
      let url = typeof path === 'string' ? path : '/api/dashboard_sections.php?action=get_sections';
      const baseHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
      // Dev fallback: include admin token if session-based admin is not detected
      const devAdminToken = 'whimsical_admin_2024';
      try {
        // Only append for our dashboard_sections endpoint
        if (/\/api\/dashboard_sections\.php/.test(url)) {
          if (payload && typeof payload === 'object' && !payload.admin_token) {
            payload.admin_token = devAdminToken;
          }
          if (!payload && url.indexOf('admin_token=') === -1) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + 'admin_token=' + encodeURIComponent(devAdminToken);
          }
        }
      } catch(_) {}
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
        const sections = data?.data?.sections || data?.sections || [];
        try { console.info('[DashboardConfigFallback] get_sections result', data); } catch(_) {}
        const lists = renderDashboardLists(el);
        if (!lists.activeUl) return;
        // Available from API (if present) or infer from section_info
        let avail = data?.data?.available_sections || data?.available_sections || {};
        if (!avail || Object.keys(avail).length === 0) {
          // Seed with known defaults to ensure the UI is usable even if API omits them
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
        Object.keys(avail).forEach((key) => {
          // Skip items already active (including hydrated ones)
          if (activeKeys.has(key)) return;
          const item = { key, title: avail[key]?.title || key };
          lists.availUl.appendChild(lists.makeLi(item, false));
        });
        // Empty-state hint if both lists are empty
        if (!lists.activeUl.children.length && !lists.availUl.children.length) {
          const hint = document.createElement('div');
          hint.className = 'text-sm text-gray-500 mt-2';
          hint.textContent = 'No dashboard sections available yet.';
          body.appendChild(hint);
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
      if (closest('[data-action="open-business-info"]')) { try { console.info('[AdminSettingsBridge] click open-business-info'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('businessInfoModal', (t.closest('button, a, [tabindex]')||t)); showModal('businessInfoModal'); return; }
      if (closest('[data-action="close-business-info"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('businessInfoModal'); return; }

      // Square Settings
      if (closest('[data-action="open-square-settings"]')) { try { console.info('[AdminSettingsBridge] click open-square-settings'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('squareSettingsModal', (t.closest('button, a, [tabindex]')||t)); showModal('squareSettingsModal'); return; }
      if (closest('[data-action="close-square-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('squareSettingsModal'); return; }

      // Email Settings
      if (closest('[data-action="open-email-settings"]')) { try { console.info('[AdminSettingsBridge] click open-email-settings'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('emailSettingsModal', (t.closest('button, a, [tabindex]')||t)); showModal('emailSettingsModal'); return; }
      if (closest('[data-action="close-email-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('emailSettingsModal'); return; }
      // Open Email Test: open email modal and focus test input if present
      if (closest('[data-action="open-email-test"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('emailSettingsModal', (t.closest('button, a, [tabindex]')||t)); if (showModal('emailSettingsModal')) { const test = document.getElementById('testEmailAddress'); if (test) setTimeout(() => test.focus(), 50); } return; }

      // Logging Status
      if (closest('[data-action="open-logging-status"]')) { try { console.info('[AdminSettingsBridge] click open-logging-status'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('loggingStatusModal', (t.closest('button, a, [tabindex]')||t)); showModal('loggingStatusModal'); return; }
      if (closest('[data-action="close-logging-status"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('loggingStatusModal'); return; }

      // AI Settings
      if (closest('[data-action="open-ai-settings"]')) { try { console.info('[AdminSettingsBridge] click open-ai-settings'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('aiSettingsModal', (t.closest('button, a, [tabindex]')||t)); showModal('aiSettingsModal'); return; }
      if (closest('[data-action="close-ai-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('aiSettingsModal'); return; }

      // AI Tools
      if (closest('[data-action="open-ai-tools"]')) { try { console.info('[AdminSettingsBridge] click open-ai-tools'); } catch(_) {} e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); lastTriggerById.set('aiToolsModal', (t.closest('button, a, [tabindex]')||t)); showModal('aiToolsModal'); return; }
      if (closest('[data-action="close-ai-tools"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('aiToolsModal'); return; }

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
        if (showModal('dashboardConfigModal')) {
          // Always populate a responsive, non-freezing fallback UI immediately
          try {
            const el = document.getElementById('dashboardConfigModal');
            if (el) {
              const body = el.querySelector('.modal-body');
              if (body) {
                body.innerHTML = '';
                const msg = document.createElement('div');
                msg.className = 'text-gray-700';
                msg.innerHTML = '<p>Basic Dashboard Configuration is active. Click "Load Advanced Configurator" to enable drag-and-drop if available.</p>';
                const actions = document.createElement('div');
                actions.className = 'mt-3 flex gap-2 justify-end';
                const loadBtn = document.createElement('button');
                loadBtn.type = 'button';
                loadBtn.className = 'btn';
                loadBtn.textContent = 'Load Advanced Configurator';
                loadBtn.addEventListener('click', () => {
                  try { console.info('[AdminSettingsBridge] loading legacy dashboard configurator'); } catch(_) {}
                  ensureLegacyLoaded().then(() => {
                    try {
                      if (typeof window.openDashboardConfigModal === 'function') {
                        window.openDashboardConfigModal({ modalId: 'dashboardConfigModal' });
                      } else {
                        alert('Advanced configurator not available.');
                      }
                    } catch (err) {
                      console.error('[AdminSettingsBridge] legacy configurator init failed', err);
                      alert('Failed to initialize advanced configurator.');
                    }
                  });
                });
                const closeBtn = document.createElement('button');
                closeBtn.type = 'button';
                closeBtn.className = 'btn btn-secondary';
                closeBtn.textContent = 'Close';
                closeBtn.setAttribute('data-action','close-dashboard-config');
                actions.appendChild(loadBtn);
                actions.appendChild(closeBtn);
                body.appendChild(msg);
                body.appendChild(actions);
                // Populate the lightweight lists immediately
                const listsWrap = document.createElement('div');
                listsWrap.className = 'mt-4';
                listsWrap.innerHTML = `
                  <div class="flex gap-4">
                    <div class="flex-1">
                      <h3 class="text-base font-semibold mb-2">Active Sections</h3>
                      <ul id="dashboardActiveSections" class="list-disc pl-5 text-sm text-gray-800"></ul>
                    </div>
                    <div class="flex-1">
                      <h3 class="text-base font-semibold mb-2">Available Sections</h3>
                      <ul id="dashboardAvailableSections" class="list-disc pl-5 text-sm text-gray-800"></ul>
                    </div>
                  </div>
                  <div class="mt-3 flex justify-between items-center">
                    <div id="dashboardConfigResult" class="text-sm text-gray-500"></div>
                    <button type="button" class="btn btn-primary" data-action="dashboard-config-save">Save</button>
                  </div>
                `;
                body.appendChild(listsWrap);
                populateDashboardFallback('dashboardConfigModal');
              }
            }
          } catch(_) {}
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
        const li = btn && btn.closest('li');
        const modal = document.getElementById('dashboardConfigModal');
        const availUl = modal && modal.querySelector('#dashboardAvailableSections');
        if (li && availUl) {
          const key = li.dataset.key || '';
          // If an item with this key already exists in Available, remove duplicate before appending
          const existing = availUl.querySelector(`li[data-key="${CSS && CSS.escape ? CSS.escape(key) : key}"]`);
          if (existing) existing.remove();
          availUl.appendChild(li);
          btn.textContent = 'Add';
          btn.setAttribute('data-action', 'dashboard-add-section');
        }
        return;
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
