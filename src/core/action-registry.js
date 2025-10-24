// src/core/actionRegistry.js
// ES module version of legacy central-functions.js action handlers & delegation.

/* global document window */

// Centralized handler object. Feel free to extend.
export const centralFunctions = {
  // Placeholders – real implementations are still global functions elsewhere.
  openEditModal: (el, p) => window.openEditModal?.(p.type, p.id),
  openDeleteModal: (el, p) => window.openDeleteModal?.(p.type, p.id, p.name),
  performAction: (el, p) => window.performAction?.(p.action),
  runCommand: (el, p) => window.runCommand?.(p.command),
  loadRoomConfig: () => window.loadRoomConfig?.(),
  resetForm: () => window.resetForm?.(),
  // Open detailed item modal for room/shop items
  openQuantityModal: async (el, p = {}) => {
    try {
      // Close popup first to avoid overlap/focus issues
      try { window.hideGlobalPopupImmediate && window.hideGlobalPopupImmediate(); } catch(_) {}
      const data = p.itemData || p.item || {};
      const sku = data.sku || el?.dataset?.sku || el?.dataset?.productId;
      // Ensure modal function exists
      if (typeof window.showGlobalItemModal !== 'function') {
        try { await import('../js/detailed-item-modal.js'); } catch(_) {}
      }
      // Prefer parent (top window) if available (e.g., when running inside room iframe)
      const opener = (typeof parent !== 'undefined' && parent !== window && typeof parent.showGlobalItemModal === 'function')
        ? parent.showGlobalItemModal
        : window.showGlobalItemModal;
      if (typeof opener === 'function' && sku) {
        opener(sku, data);
        return;
      }
      if (typeof window.showItemDetailsModal === 'function' && sku) {
        window.showItemDetailsModal(sku, data);
      }
    } catch (_) { /* no-op */ }
  },
  // Generic confirmation handler to replace inline onclick="return confirm(...)"
  confirm: async (el, p = {}) => {
    const message = p.message || el.getAttribute('data-confirm') || 'Are you sure?';
    if (typeof window.showConfirmationModal !== 'function') {
      try {
        if (window.wfNotifications && typeof window.wfNotifications.show === 'function') {
          window.wfNotifications.show('Confirmation UI unavailable. Action canceled.', 'error');
        } else if (typeof window.showNotification === 'function') {
          window.showNotification('Confirmation UI unavailable. Action canceled.', 'error');
        }
      } catch(_) {}
      return;
    }
    const proceed = await window.showConfirmationModal({
      title: p.title || 'Please confirm',
      message,
      confirmText: p.confirmText || 'Confirm',
      confirmStyle: p.confirmStyle || 'confirm',
      icon: p.icon || '⚠️',
      iconType: p.iconType || 'warning'
    });
    if (!proceed) return;

    // Navigate if anchor
    if (el.tagName === 'A' && el.href) {
      if (el.target === '_blank') {
        window.open(el.href, '_blank');
      } else {
        window.location.href = el.href;
      }
      return;
    }

    // Submit associated form if present or specified
    const form = el.form || (p.formId ? document.getElementById(p.formId) : null);
    if (form) {
      form.submit();
      return;
    }

    // Fallback: dispatch a custom event consumers can hook into
    document.dispatchEvent(new CustomEvent('wf:confirm:accepted', { detail: { element: el, params: p } }));
  },
  // ...add more mappings if/when the functions are migrated.
};

function delegate() {
  if (document.body.dataset.wfCentralListenersAttached) return;
  document.body.dataset.wfCentralListenersAttached = 'true';

  const parseParams = el => {
    try {
      return el.dataset.params ? JSON.parse(el.dataset.params) : {};
    } catch (e) { return {}; }
  };

  const add = (event, attr, fnName) => {
    document.body.addEventListener(event, e => {
      const target = e.target.closest(`[${attr}]`);
      if (!target) return;
      if (event === 'click') e.preventDefault();
      const params = parseParams(target);
      const fn = centralFunctions[target.dataset[fnName]];
      fn?.(target, params, e);
    });
  };

  add('click', 'data-action', 'action');
  add('change', 'data-change-action', 'changeAction');
  add('focusin', 'data-focus-action', 'focusAction');
  add('focusout', 'data-blur-action', 'blurAction');
  add('mouseover', 'data-mouseover-action', 'mouseoverAction');
  add('mouseout', 'data-mouseout-action', 'mouseoutAction');
}

document.addEventListener('DOMContentLoaded', delegate);

// Expose for legacy code
if (typeof window !== 'undefined') {
  window.centralFunctions = centralFunctions;
}
