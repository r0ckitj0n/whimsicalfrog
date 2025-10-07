// Header bootstrap for auth-related behavior on all pages, including admin
// Minimal side-effect imports to activate delegated handlers.
import '../styles/login-modal.css';
import '../styles/admin-modals.css';
import '../styles/admin-nav.css';
// Import help-bubble last to override nav tab pill styles
import '../styles/admin-help-bubble.css';
import './login-modal.js';
import './header-auth-sync.js';

// Ensure tooltip manager is initialized on admin pages even if other entries don't run yet
(function ensureAdminTooltipsInit(){
  const isAdmin = (() => {
    try { return (/^\/?admin(\/|$)/i.test(location.pathname)) || (/^\/?sections\/admin_router\.php$/i.test(location.pathname)); } catch(_) { return false; }
  })();
  if (!isAdmin) return;
  const init = () => {
    try { import('../modules/tooltip-manager.js').then(mod => { try { (mod && typeof mod.default === 'function') && mod.default(); } catch(_) {} }).catch(() => {}); } catch(_) {}
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else { init(); }
})();

// Global Help & Hints toggle (admin header)
function __wfGetHintsEnabled() {
  try {
    const sessionOn = (typeof sessionStorage !== 'undefined') && sessionStorage.getItem('wf_tooltips_session_enabled') === 'true';
    const localOn = (typeof localStorage !== 'undefined') && localStorage.getItem('wf_tooltips_enabled') !== 'false';
    return sessionOn || localOn;
  } catch (_) { return true; }
}
function __wfSyncHintsToggleButtons() {
  try {
    const enabled = __wfGetHintsEnabled();
    document.querySelectorAll('[data-action="help-toggle-global-tooltips"]').forEach(btn => {
      try { btn.setAttribute('aria-pressed', enabled ? 'true' : 'false'); } catch(_) {}
    });
    document.querySelectorAll('[data-help-toggle-root]').forEach(root => {
      try { root.setAttribute('aria-pressed', enabled ? 'true' : 'false'); } catch(_) {}
    });
  } catch(_) {}
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', __wfSyncHintsToggleButtons, { once: true });
} else {
  __wfSyncHintsToggleButtons();
}
document.addEventListener('click', async (e) => {
  try {
    const btn = e.target && e.target.closest ? e.target.closest('[data-action="help-toggle-global-tooltips"]') : null;
    if (!btn) return;
    e.preventDefault();

    const current = __wfGetHintsEnabled();
    const next = !current;
    try {
      if (typeof localStorage !== 'undefined') localStorage.setItem('wf_tooltips_enabled', next ? 'true' : 'false');
    } catch (_) {}
    try {
      if (typeof sessionStorage !== 'undefined') {
        if (next) sessionStorage.setItem('wf_tooltips_session_enabled', 'true');
        else sessionStorage.removeItem('wf_tooltips_session_enabled');
      }
    } catch (_) {}

    // Try to immediately apply via tooltip manager if available
    try {
      if (typeof window.toggleGlobalTooltips === 'function') {
        // toggleGlobalTooltips toggles based on current storage; call it twice to sync? Instead, avoid calling to prevent flip.
      } else {
        // Lazy-load to ensure manager attaches, but no need to call toggle
        import('../modules/tooltip-manager.js').catch(() => {});
      }
      // Remove visible tooltips when turning off
      if (!next) {
        document.querySelectorAll('.wf-tooltip').forEach(el => el.remove());
      }
    } catch (_) {}

    try { btn.setAttribute('aria-pressed', next ? 'true' : 'false'); } catch (_) {}
    try {
      const root = btn.closest('[data-help-toggle-root]');
      if (root) root.setAttribute('aria-pressed', next ? 'true' : 'false');
    } catch(_) {}
    try { __wfSyncHintsToggleButtons(); } catch(_) {}
    if (typeof window.showNotification === 'function') {
      window.showNotification(next ? 'Help & Hints enabled' : 'Help & Hints disabled', next ? 'success' : 'info', { title: 'Help & Hints' });
    }
  } catch (_) { /* noop */ }
});

// Admin Help Docs modal open/close (lives in components/admin_nav_tabs.php)
document.addEventListener('click', (e) => {
  try {
    const openBtn = e.target && e.target.closest ? e.target.closest('[data-action="open-admin-help-modal"]') : null;
    const closeBtn = e.target && e.target.closest ? e.target.closest('[data-action="close-admin-help-modal"]') : null;
    const overlay = document.getElementById('adminHelpDocsModal');
    const frame = document.getElementById('adminHelpDocsFrame');
    if (openBtn) {
      e.preventDefault();
      if (overlay) {
        try {
          if (overlay.parentNode !== document.body) {
            document.body.appendChild(overlay);
          }
          overlay.classList.remove('hidden');
          // Ensure visibility on admin pages where overlays require .show
          overlay.classList.add('show');
        } catch(_) {}
      }
      if (frame) {
        const srcNow = (frame.getAttribute('src') || '').trim();
        const isBlank = !srcNow || srcNow === 'about:blank' || srcNow.endsWith('about:blank');
        if (isBlank) {
          // Load the new interactive help documentation
          const dataSrc = frame.getAttribute('data-src');
          if (dataSrc) {
            frame.src = dataSrc;
          } else {
            frame.src = '/help.php';
        }
      }
      if (window.WFModals && typeof window.WFModals.lockScroll === 'function') window.WFModals.lockScroll();
      return;
    }
    if (closeBtn) {
      e.preventDefault();
      if (overlay) {
        overlay.classList.add('hidden');
        overlay.classList.remove('show');
      }
      if (window.WFModals && typeof window.WFModals.unlockScrollIfNoneOpen === 'function') window.WFModals.unlockScrollIfNoneOpen();
      return;
    }
    // Click outside to close when clicking the overlay backdrop
    if (overlay && e.target === overlay) {
      overlay.classList.add('hidden');
      overlay.classList.remove('show');
      if (window.WFModals && typeof window.WFModals.unlockScrollIfNoneOpen === 'function') window.WFModals.unlockScrollIfNoneOpen();
      return;
    }
  } catch(_) { /* noop */ }
});
