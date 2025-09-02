// Shared under-header modal initializer for all admin pages
// - Computes header height (prefers --wf-header-height var, falls back to measured header element)
// - Applies CSS var --admin-header-height
// - Adds .under-header to all modal overlay types

(function initUnderHeaderModals(){
  if (typeof window === 'undefined' || typeof document === 'undefined') return;
  if (window.__WF_UNDER_HEADER_MODALS_INIT) return; // idempotent
  window.__WF_UNDER_HEADER_MODALS_INIT = true;

  const isAdminPage = () => {
    const body = document.body;
    const ds = (body && body.dataset) || {};
    const path = (location && location.pathname) || '';
    return (ds.page && ds.page.startsWith('admin')) || /\/(admin)(\b|\/|\?|#)/.test(path);
  };
  if (!isAdminPage()) return;

  const findHeaderEl = () => document.querySelector([
    '.admin-header', '.site-header', '#siteHeader', '#header', '#wfHeader', '.wf-header', '.header', 'header'
  ].join(', '));

  const cssVar = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  const parsePx = (val) => {
    if (!val) return NaN;
    const m = /([\d.]+)px/.exec(val);
    return m ? Math.ceil(parseFloat(m[1])) : NaN;
  };

  const apply = () => {
    let h = parsePx(cssVar('--wf-header-height'));
    if (!Number.isFinite(h) || h <= 0) {
      const header = findHeaderEl();
      h = header ? Math.ceil(header.getBoundingClientRect().height) : 64;
    }
    if (!Number.isFinite(h) || h <= 0) h = 64;
    // Avoid setting CSS variables directly in JS; rely on CSS fallbacks
    document.querySelectorAll('.admin-modal-overlay, .modal-overlay, .room-modal-overlay, [id$="Modal"]').forEach(el => {
      el.classList.add('under-header');
    });
  };

  const onReady = (fn) => {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
    else fn();
  };

  onReady(() => {
    let rafId = 0;
    const onResize = () => { cancelAnimationFrame(rafId); rafId = requestAnimationFrame(apply); };
    apply();
    window.addEventListener('resize', onResize);
  });
})();
