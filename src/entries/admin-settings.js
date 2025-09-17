// Vite entry for Admin Settings UI
// Re-exports and initializes the legacy-compatible admin settings module
// Import full shared site styles first (variables, base, components)
import '../styles/main.css';
// Then admin-specific styles so admin pages are styled without loading public app.js
import '../styles/components/components-base.css';
import '../styles/components/admin-nav.css';
import '../styles/admin-modals.css';
import '../styles/components/modal.css';
import '../styles/admin-settings.css';
import '../styles/admin-settings-extras.css';

// Lightweight, always-on modal helpers for Settings page
const __wfShowModal = (id) => {
  const el = document.getElementById(id);
  if (!el) return false;
  try { el.removeAttribute('hidden'); } catch(_) {}
  el.classList.remove('hidden');
  el.classList.add('show');
  el.setAttribute('aria-hidden', 'false');
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

// Immediately install a minimal delegated click handler so buttons work even before bridge loads
(function installImmediateSettingsClicks(){
  const handler = (e) => {
    try {
      const t = e.target;
      const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
      // Only act on Settings routes. Be generous: detect by DOM hook or URL tokens.
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

      // Overlay click-to-close
      if (t && t.classList && t.classList.contains('admin-modal-overlay')) {
        const id = t.id; if (!id) return;
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfHideModal(id); return;
      }

      // Generic close button inside any admin modal
      if (closest('[data-action="close-admin-modal"]')) {
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        const overlay = closest('.admin-modal-overlay');
        if (overlay && overlay.id) { __wfHideModal(overlay.id); }
        return;
      }

      // Openers (core)
      if (closest('[data-action="open-dashboard-config"]')) {
        // If bridge is active, let it handle preload + open
        if (window.__WF_ADMIN_SETTINGS_BRIDGE_INIT) return;
        e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
        __wfShowModal('dashboardConfigModal'); return;
      }
      if (closest('[data-action="open-square-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); __wfShowModal('squareSettingsModal'); return; }
      if (closest('[data-action="open-email-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); __wfShowModal('emailSettingsModal'); return; }
      if (closest('[data-action="open-email-test"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); if (__wfShowModal('emailSettingsModal')) { const test = document.getElementById('testEmailAddress')||document.getElementById('testRecipient'); if (test) setTimeout(()=>test.focus(), 50); } return; }
      if (closest('[data-action="open-logging-status"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); __wfShowModal('loggingStatusModal'); return; }
      if (closest('[data-action="open-ai-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); __wfShowModal('aiSettingsModal'); return; }
      if (closest('[data-action="open-ai-tools"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); __wfShowModal('aiToolsModal'); return; }
      if (closest('[data-action="open-css-rules"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); __wfShowModal('cssRulesModal'); return; }
      if (closest('[data-action="open-background-manager"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); __wfShowModal('backgroundManagerModal'); return; }
      if (closest('[data-action="open-receipt-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); __wfShowModal('receiptSettingsModal'); return; }
      if (closest('[data-action="open-attributes"]')) {
        if (window.__WF_ADMIN_SETTINGS_BRIDGE_INIT) return;
        e.preventDefault(); if (typeof e.stopPropagation==='function') e.stopPropagation();
        // Ensure modal exists; if not, create a minimal one
        try {
          if (!document.getElementById('attributesModal')) {
            const overlay = document.createElement('div');
            overlay.id = 'attributesModal';
            overlay.className = 'admin-modal-overlay hidden';
            overlay.setAttribute('aria-hidden','true');
            overlay.setAttribute('role','dialog');
            overlay.setAttribute('aria-modal','true');
            overlay.setAttribute('tabindex','-1');
            overlay.innerHTML = `
              <div class="admin-modal">
                <div class="modal-header">
                  <h2 id="attributesTitle" class="admin-card-title">🧩 Gender, Size &amp; Color Management</h2>
                  <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
                  <span class="modal-status-chip" aria-live="polite"></span>
                </div>
                <div class="modal-body">
                  <div class="mb-2 text-sm text-gray-600">Manage product attribute values used across inventory.</div>
                  <iframe id="attributesFrame" title="Attributes Manager" src="about:blank" data-src="/admin/inventory#attributes" class="wf-admin-embed-frame"></iframe>
                </div>
              </div>`;
            document.body.appendChild(overlay);
          }
        } catch(_) {}
        __wfShowModal('attributesModal');
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
      if (closest('[data-action="open-categories"]')) {
        if (window.__WF_ADMIN_SETTINGS_BRIDGE_INIT) return;
        try { console.info('[AdminSettings Entry] open-categories clicked'); } catch(_) {}
        e.preventDefault(); if (typeof e.stopPropagation==='function') e.stopPropagation();
        // Ensure modal exists; if not, create a minimal one
        try {
          if (!document.getElementById('categoriesModal')) {
            try { console.info('[AdminSettings Entry] injecting categoriesModal'); } catch(_) {}
            const overlay = document.createElement('div');
            overlay.id = 'categoriesModal';
            overlay.className = 'admin-modal-overlay hidden';
            overlay.setAttribute('aria-hidden','true');
            overlay.setAttribute('role','dialog');
            overlay.setAttribute('aria-modal','true');
            overlay.setAttribute('tabindex','-1');
            overlay.innerHTML = `
              <div class="admin-modal">
                <div class="modal-header">
                  <h2 id="categoriesModalTitle" class="admin-card-title">📂 Categories</h2>
                  <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
                  <span class="modal-status-chip" aria-live="polite"></span>
                </div>
                <div class="modal-body">
                  <div class="space-y-4">
                    <form id="catAddForm" class="flex gap-2 items-center" onsubmit="return false;">
                      <input type="text" id="catNewName" class="text-sm border border-gray-300 rounded px-2 py-1 flex-1" placeholder="New category name" maxlength="64">
                      <button type="submit" class="btn btn-primary" data-action="cat-add">Add</button>
                    </form>
                    <div id="catResult" class="text-sm text-gray-500"></div>
                    <div class="border border-gray-200 rounded">
                      <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700">
                          <tr>
                            <th class="text-left p-2">Name</th>
                            <th class="text-left p-2">Items</th>
                            <th class="text-left p-2">Actions</th>
                          </tr>
                        </thead>
                        <tbody id="catTableBody" class="divide-y divide-gray-200"></tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>`;
            document.body.appendChild(overlay);
          }
        } catch(_) {}
        __wfShowModal('categoriesModal');
        // Minimal populate fallback
        try {
          try { console.info('[AdminSettings Entry] populating categories'); } catch(_) {}
          const modal = document.getElementById('categoriesModal');
          if (modal) {
            const tbody = modal.querySelector('#catTableBody');
            const result = modal.querySelector('#catResult');
            if (result) result.textContent = 'Loading…';
            fetch('/api/categories.php?action=list', { credentials: 'include', headers: { 'X-Requested-With':'XMLHttpRequest' } })
              .then(r => r.text()).then(t => {
                try {
                  const data = t ? JSON.parse(t) : {};
                  if (!data || data.success !== true) throw new Error((data && data.error) || 'Unexpected response');
                  const cats = (data.data && data.data.categories) ? data.data.categories : [];
                  if (tbody) {
                    tbody.innerHTML = '';
                    cats.forEach(c => {
                      const tr = document.createElement('tr');
                      const esc = (s) => String(s||'').replace(/"/g,'&quot;');
                      tr.innerHTML = `
                        <td class="p-2"><span class="cat-name" data-name="${esc(c.name)}">${c.name}</span></td>
                        <td class="p-2 text-gray-600">${c.item_count ?? 0}</td>
                        <td class="p-2">
                          <button class="btn btn-secondary" data-action="cat-rename" data-name="${esc(c.name)}">Rename</button>
                          <button class="btn btn-secondary text-red-700" data-action="cat-delete" data-name="${esc(c.name)}">Delete</button>
                        </td>`;
                      tbody.appendChild(tr);
                    });
                  }
                  if (result) result.textContent = cats.length ? '' : 'No categories found yet.';
                } catch (err) {
                  if (result) result.textContent = (err && err.message) || 'Failed to load categories';
                }
              }).catch(() => { if (result) result.textContent = 'Failed to load categories'; });
          }
        } catch (_) {}
        return;
      }
      if (closest('[data-action="open-secrets-modal"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); __wfShowModal('secretsModal'); return; }
    } catch (_) {}
  };
  // Register both capture and bubble to be resilient to other listeners
  document.addEventListener('click', handler, true);
  document.addEventListener('click', handler);
})();

// Important: only load the heavy legacy module when explicitly requested.
// Always load the lightweight bridge.
// Load bridge dynamically (deferred) to avoid blocking main thread; allow diagnostic skip
const loadBridge = async () => {
  try {
    await import('../js/admin-settings-bridge.js');
  } catch (e) {
    console.error('[AdminSettings] Failed to load admin-settings-bridge.js', e);
    // Extra diagnostics: try to fetch the module text directly to reveal Vite's error overlay content
    try {
      const devOrigin = (typeof window !== 'undefined' && window.__vite_dev_origin)
        ? String(window.__vite_dev_origin)
        : (typeof import.meta !== 'undefined' && import.meta.url && import.meta.url.startsWith('http'))
          ? new URL(import.meta.url).origin
          : 'http://localhost:5176';
      const url = devOrigin.replace(/\/$/, '') + '/src/js/admin-settings-bridge.js';
      const res = await fetch(url, { credentials: 'omit' });
      const text = await res.text();
      console.error('[AdminSettings] Bridge fetch diagnostics', { status: res.status, url, body: text && text.slice ? text.slice(0, 1000) : text });
    } catch (probeErr) {
      console.warn('[AdminSettings] Bridge fetch diagnostics failed', probeErr);
    }

    // Fallback: attach minimal handlers so core Settings buttons still work without the bridge
    try {
      const showModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return false;
        el.classList.remove('hidden');
        el.classList.add('show');
        el.setAttribute('aria-hidden', 'false');
        return true;
      };
      const hideModal = (id) => {
        const el = document.getElementById(id);
        if (!el) return false;
        el.classList.add('hidden');
        el.classList.remove('show');
        el.setAttribute('aria-hidden', 'true');
        return true;
      };

      document.addEventListener('click', (e) => {
        const t = e.target;
        const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
        // Overlay close by clicking overlay
        if (t && t.classList && t.classList.contains('admin-modal-overlay')) {
          const id = t.id;
          if (id) { e.preventDefault(); if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal(id); }
          return;
        }
        // Open/close known modals
        if (closest('[data-action="open-email-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('emailSettingsModal'); return; }
        if (closest('[data-action="close-email-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('emailSettingsModal'); return; }
        if (closest('[data-action="open-db-maintenance"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('dbMaintenanceModal'); return; }
        if (closest('[data-action="close-db-maintenance"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('dbMaintenanceModal'); return; }
        if (closest('[data-action="open-css-rules"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('cssRulesModal'); return; }
        if (closest('[data-action="close-css-rules"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('cssRulesModal'); return; }
        if (closest('[data-action="open-background-manager"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('backgroundManagerModal'); return; }
        if (closest('[data-action="close-background-manager"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('backgroundManagerModal'); return; }
        if (closest('[data-action="open-receipt-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); showModal('receiptSettingsModal'); return; }
        if (closest('[data-action="close-receipt-settings"]')) { e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation(); hideModal('receiptSettingsModal'); return; }

        // Dashboard Configuration (fallback)
        if (closest('[data-action="open-dashboard-config"]')) {
          e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
          showModal('dashboardConfigModal');
          return;
        }

        // File Explorer minimal support
        if (closest('#fileExplorerBtn') || closest('[data-action="open-file-explorer"]')) {
          e.preventDefault(); if (typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation(); else e.stopPropagation();
          // Try to load legacy module to wire directory functions; otherwise just show modal
          (async () => {
            try { if (typeof window.openFileExplorerModal !== 'function') { await import('../js/admin-settings.js'); } } catch(_) {}
            if (typeof window.openFileExplorerModal === 'function') { window.openFileExplorerModal(); }
            else {
              showModal('fileExplorerModal');
              try { if (typeof window.loadDirectory === 'function') window.loadDirectory(''); } catch(_) {}
            }
          })();
          return;
        }
      });
    } catch(_) {}
  }
};

// If the module exposes an init, call it after DOM is ready (defensive)
if (typeof window !== 'undefined') {
  const params = new URLSearchParams(window.location.search || '');
  const killSwitch = params.get('wf_no_settings_js');
  const wfSection = params.get('wf_section');
  const wantLegacy = params.get('wf_legacy');
  const disableByWindow = (typeof window.WF_DISABLE_ADMIN_SETTINGS_JS !== 'undefined') ? !!window.WF_DISABLE_ADMIN_SETTINGS_JS : false;
  const shouldLoadLegacy = !disableByWindow && (wantLegacy === '1' || (wfSection && wfSection !== ''));

  const noBridge = params.get('wf_no_bridge');
  if (killSwitch === '1' || killSwitch === 'true' || disableByWindow) {
    console.warn('[AdminSettings] Kill-switch active: skipping admin settings JS initialization');
  } else {
    const runInit = () => {
      // Always try to load the lightweight bridge first (deferred)
      try {
        if ('requestIdleCallback' in window) {
          if (noBridge === '1' || noBridge === 'true') {
            console.warn('[AdminSettings] wf_no_bridge=1 -> skipping bridge load');
          } else {
            window.requestIdleCallback(() => loadBridge(), { timeout: 200 });
          }
        } else {
          if (noBridge === '1' || noBridge === 'true') {
            console.warn('[AdminSettings] wf_no_bridge=1 -> skipping bridge load');
          } else {
            setTimeout(loadBridge, 50);
          }
        }
      } catch (_) {
        if (!(noBridge === '1' || noBridge === 'true')) setTimeout(loadBridge, 50);
      }
      // Only attempt to init the legacy module when explicitly requested
      if (!shouldLoadLegacy) {
        console.info('[AdminSettings] Skipping legacy module (light mode or no section). Bridge remains active.');
        return;
      }
      // Lazy-load the legacy module to avoid blocking main thread
      import('../js/admin-settings.js').then(() => {
        try {
          if (typeof window.WF_AdminSettings?.init === 'function') {
            const t0 = (performance && performance.now) ? performance.now() : Date.now();
            window.WF_AdminSettings.init();
            const t1 = (performance && performance.now) ? performance.now() : Date.now();
            console.log(`[AdminSettings] legacy init completed in ${(t1 - t0).toFixed(1)}ms`);
          } else {
            console.warn('[AdminSettings] WF_AdminSettings.init not found after lazy import');
          }
        } catch (e) {
          console.error('WF_AdminSettings.init error', e);
        }
      }).catch((err) => {
        console.error('[AdminSettings] Failed to load legacy module', err);
      });
    };

    const defer = () => {
      if ('requestIdleCallback' in window) {
        try { window.requestIdleCallback(runInit, { timeout: 200 }); return; } catch (_) {}
      }
      setTimeout(runInit, 50);
    };

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', defer, { once: true });
    } else {
      defer();
    }

    // Safety-net: minimal click handler so File Explorer button still opens the modal
    // even if the bridge failed to load for some reason (e.g., Vite 500 while transforming).
    const showModal = (id) => {
      const el = document.getElementById(id);
      if (!el) return false;
      el.classList.remove('hidden');
      el.classList.add('show');
      el.setAttribute('aria-hidden', 'false');
      return true;
    };
    document.addEventListener('click', (e) => {
      const t = e.target;
      const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
      if (closest('#fileExplorerBtn') || closest('[data-action="open-file-explorer"]')) {
        try { e.preventDefault(); } catch(_) {}
        try { if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation(); } catch(_) {}
        // Try to lazy-load legacy module on demand to get full File Explorer wiring
        (async () => {
          try {
            if (typeof window.openFileExplorerModal !== 'function') {
              await import('../js/admin-settings.js');
            }
          } catch (err) {
            console.warn('[AdminSettings] Lazy import on click failed', err);
          }
          if (typeof window.openFileExplorerModal === 'function') {
            window.openFileExplorerModal();
          } else {
            // Fallback: show modal and attempt to load directory if available
            showModal('fileExplorerModal');
            try { if (typeof window.loadDirectory === 'function') window.loadDirectory(''); } catch(_) {}
          }
        })();
      }
    });
  }
}
