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

// Important: only load the heavy legacy module when explicitly requested.
// Always load the lightweight bridge.
// Load bridge dynamically to avoid hard-failing the whole entry if Vite errors while serving it
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

  if (killSwitch === '1' || killSwitch === 'true' || disableByWindow) {
    console.warn('[AdminSettings] Kill-switch active: skipping admin settings JS initialization');
  } else {
    const runInit = () => {
      // Always try to load the lightweight bridge first
      loadBridge();
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
