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

// Admin navbar underline slider (runs independently of tooltip bootstrap)
(function initAdminNavUnderline(){
  try {
    const isAdminRoute = (() => {
      try { return /^\/admin(\/|$)/i.test(location.pathname) || /admin_router\.php$/i.test(location.pathname); } catch(_) { return false; }
    })();
    if (!isAdminRoute) return;

    const container = document.querySelector('.admin-tab-navigation');
    if (!container || !container.classList.contains('admin-tabs--underline')) return;
    const nav = container.querySelector('.wf-nav-center');
    if (!nav) return;

    // Create underline element if missing
    let underline = nav.querySelector('.admin-tabs-underline');
    if (!underline) {
      underline = document.createElement('div');
      underline.className = 'admin-tabs-underline';
      nav.appendChild(underline);
    }

    const tabs = Array.from(nav.querySelectorAll('.admin-nav-tab[href]'));
    if (!tabs.length) return;

    // Determine active tab: prefer .active; fallback to URL match
    let activeTab = nav.querySelector('.admin-nav-tab.active');
    if (!activeTab) {
      const path = location.pathname.replace(/\/*$/, '');
      const found = tabs.find(a => {
        try {
          const href = a.getAttribute('href') || '';
          if (!href) return false;
          const url = new URL(href, location.origin);
          return url.pathname.replace(/\/*$/, '') === path;
        } catch(_) { return false; }
      });
      if (found) activeTab = found;
    }

    let styleEl;
    function ensureStyleEl(){
      if (!styleEl) {
        styleEl = document.getElementById('wf-admin-tabs-underline-style');
        if (!styleEl) {
          styleEl = document.createElement('style');
          styleEl.id = 'wf-admin-tabs-underline-style';
          document.head.appendChild(styleEl);
        }
      }
      return styleEl;
    }
    function applyUnderlineRule(widthPx, leftPx){
      const se = ensureStyleEl();
      const w = Math.max(0, Math.round(widthPx)) + 'px';
      const l = Math.max(0, Math.round(leftPx)) + 'px';
      se.textContent = '.admin-tab-navigation .admin-tabs-underline{width:'+w+';transform:translateX('+l+')}';
    }
    function positionUnderline(el) {
      try {
        if (!el || !underline) return;
        const navRect = nav.getBoundingClientRect();
        const rect = el.getBoundingClientRect();
        const left = rect.left - navRect.left;
        const width = rect.width;
        applyUnderlineRule(width, left);
      } catch(_) {}
    }

    // Initial position
    const initial = activeTab || tabs[0];
    positionUnderline(initial);

    // Hover to preview
    tabs.forEach(tab => {
      tab.addEventListener('mouseenter', () => positionUnderline(tab));
      tab.addEventListener('focus', () => positionUnderline(tab));
      tab.addEventListener('mouseleave', () => positionUnderline(activeTab || initial));
      tab.addEventListener('blur', () => positionUnderline(activeTab || initial));
      tab.addEventListener('click', () => {
        activeTab = tab;
        try { tabs.forEach(t => t.classList.toggle('active', t === tab)); } catch(_) {}
        positionUnderline(activeTab);
      });
    });

    // Keep positioned on resize and when fonts load
    const onResize = () => positionUnderline(activeTab || initial);
    window.addEventListener('resize', onResize);
    if (document.fonts && typeof document.fonts.addEventListener === 'function') {
      try { document.fonts.addEventListener('loadingdone', onResize); } catch(_) {}
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', onResize, { once: true });
    } else {
      setTimeout(onResize, 0);
    }
  } catch(_) { /* noop */ }
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
          const dataSrc = frame.getAttribute('data-src');
          if (dataSrc) {
            frame.src = dataSrc;
          } else {
            frame.src = '/help.php';
          }
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
