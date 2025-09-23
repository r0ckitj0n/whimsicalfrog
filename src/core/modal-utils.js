// Shared Modal Utilities (Vite-managed)
// Provides consistent show/hide behavior, ensures overlay is attached to <body>,
// and cooperates with WFModals (global) scroll locking.

export function ensureOnBody(el) {
  try {
    if (!el) return el;
    if (el.parentNode !== document.body) {
      document.body.appendChild(el);
    }
  } catch (_) {}
  return el;
}

export function showModalById(id) {
  try {
    const el = document.getElementById(id);
    if (!el) return false;
    ensureOnBody(el);
    el.classList.add('show');
    if (window.WFModals && typeof window.WFModals.lockScroll === 'function') {
      window.WFModals.lockScroll();
    } else {
      // Fallback lock
      document.documentElement.classList.add('modal-open');
      document.body.classList.add('modal-open');
    }
    // Basic a11y state
    try { el.setAttribute('aria-hidden', 'false'); } catch(_) {}
    return true;
  } catch (_) { return false; }
}

export function hideModalById(id) {
  try {
    const el = document.getElementById(id);
    if (!el) return false;
    el.classList.remove('show');
    try { el.setAttribute('aria-hidden', 'true'); } catch(_) {}
    // Unlock only if no other modals are open
    if (window.WFModals && typeof window.WFModals.unlockScrollIfNoneOpen === 'function') {
      window.WFModals.unlockScrollIfNoneOpen();
    } else {
      // Fallback unlock (non-exact)
      document.documentElement.classList.remove('modal-open');
      document.body.classList.remove('modal-open');
    }
    return true;
  } catch (_) { return false; }
}

export function forceVisibleStyles(el) {
  // Apply defensive overlay style via CSS class instead of inline styles
  try { el.classList.add('wf-modal-force-visible'); } catch (_) {}
}

// Optional: attach safe global helpers for legacy callers without imports
try {
  window.WFModalUtils = window.WFModalUtils || {};
  window.WFModalUtils.ensureOnBody = ensureOnBody;
  window.WFModalUtils.showModalById = showModalById;
  window.WFModalUtils.hideModalById = hideModalById;
  window.WFModalUtils.forceVisibleStyles = forceVisibleStyles;
} catch(_) {}
