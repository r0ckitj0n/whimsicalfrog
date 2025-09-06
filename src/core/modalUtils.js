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
  // Defensive styles for legacy overlays that may be clipped by transforms/overflow
  try {
    el.style.position = 'fixed';
    el.style.top = '0';
    el.style.left = '0';
    el.style.right = '0';
    el.style.bottom = '0';
    el.style.width = '100vw';
    el.style.height = '100vh';
    el.style.zIndex = '2147483600';
    el.style.visibility = 'visible';
    el.style.opacity = '1';
    el.style.pointerEvents = 'auto';
  } catch(_) {}
}

// Optional: attach safe global helpers for legacy callers without imports
try {
  window.WFModalUtils = window.WFModalUtils || {};
  window.WFModalUtils.ensureOnBody = ensureOnBody;
  window.WFModalUtils.showModalById = showModalById;
  window.WFModalUtils.hideModalById = hideModalById;
  window.WFModalUtils.forceVisibleStyles = forceVisibleStyles;
} catch(_) {}
