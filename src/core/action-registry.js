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
  // Generic confirmation handler to replace inline onclick="return confirm(...)"
  confirm: (el, p = {}) => {
    const message = p.message || el.getAttribute('data-confirm') || 'Are you sure?';
    const proceed = window.confirm(message);
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
