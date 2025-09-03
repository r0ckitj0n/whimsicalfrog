// Vite entry for Admin Settings UI
// Re-exports and initializes the legacy-compatible admin settings module

import '../js/admin-settings.js';
import '../js/admin-settings-bridge.js';

// If the module exposes an init, call it after DOM is ready (defensive)
if (typeof window !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof window.WF_AdminSettings?.init === 'function') {
        try { window.WF_AdminSettings.init(); } catch (e) { console.error('WF_AdminSettings.init error', e); }
      }
    });
  } else {
    if (typeof window.WF_AdminSettings?.init === 'function') {
      try { window.WF_AdminSettings.init(); } catch (e) { console.error('WF_AdminSettings.init error', e); }
    }
  }
}
