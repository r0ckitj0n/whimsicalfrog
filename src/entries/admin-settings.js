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
import '../js/admin-settings-bridge.js';

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
  }
}
