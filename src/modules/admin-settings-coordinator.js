// Main Admin Settings Module - Coordinator
// This file coordinates all admin settings functionality
// Individual features are broken out into separate modules for better performance

// Import utilities
import '../modules/utilities.js';

// Import all admin settings sub-modules
// Delegated handlers are heavy; defer until first interaction or idle.
(function(){
  try {
    const p = new URLSearchParams(window.location.search || '');
    const noDelegated = p.get('wf_diag_no_delegated') === '1';
    if (noDelegated) {
      console.warn('[AdminSettings] delegated-handlers disabled via wf_diag_no_delegated=1');
      return;
    }
    let loaded = false;
    const loadDH = () => {
      if (loaded) return; loaded = true;
      try { import('./delegated-handlers.js').catch(() => {}); } catch(_) {}
      try { document.removeEventListener('click', onClick, true); } catch(_) {}
      try { document.removeEventListener('keydown', onKey, true); } catch(_) {}
    };
    const onClick = () => loadDH();
    const onKey = (e) => { try { if (e && (e.key === 'Enter' || e.key === ' ')) loadDH(); } catch(_) {} };
    try { document.addEventListener('click', onClick, true); } catch(_) {}
    try { document.addEventListener('keydown', onKey, true); } catch(_) {}
    // Also schedule an idle-time import as a fallback
    try {
      const idle = (fn) => (window.requestIdleCallback ? window.requestIdleCallback(fn, { timeout: 1500 }) : setTimeout(fn, 1200));
      idle(loadDH);
    } catch(_) {}
  } catch(_) { /* noop */ }
})();
import './modal-managers.js';
import './form-handlers.js';
import './api-handlers.js';
import './initialization.js';

// Global flag to indicate admin settings bridge is loaded
window.__WF_ADMIN_SETTINGS_BRIDGE_INIT = true;

// Export the main admin settings object
export const AdminSettings = {
  // Core functionality exposed to global scope
  init() {
    console.log('[AdminSettings] Initializing...');
    // Initialization handled by individual modules
  }
};

export default AdminSettings;
