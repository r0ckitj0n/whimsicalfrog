// Admin Settings - Modal Managers
// Handles all modal-related functionality

// Modal utility functions
export const ModalManager = {
  show(id) {
    const el = document.getElementById(id);
    if (!el) return false;
    // Ensure overlays are direct children of <body> to avoid ancestor scroll/transform issues
    try {
      if (el.parentElement && el.parentElement !== document.body) {
        document.body.appendChild(el);
      }
    } catch(_) {}
    try { el.removeAttribute('hidden'); } catch(_) {}
    el.classList.remove('hidden');
    el.classList.add('show');
    try {
      const hasResponsivePanel = !!el.querySelector('.admin-modal.admin-modal--responsive');
      if (!hasResponsivePanel) {
        el.classList.add('wf-modal--body-scroll');
      } else {
        el.classList.remove('wf-modal--body-scroll');
      }
    } catch(_) { try { el.classList.add('wf-modal--body-scroll'); } catch(_) {} }
    el.setAttribute('aria-hidden', 'false');
    // Lock underlying page scroll for cross-browser support
    try {
      document.documentElement.classList.add('modal-open');
      document.body.classList.add('modal-open', 'wf-no-scroll');
    } catch(_) {}
    return true;
  },

  hide(id) {
    const el = document.getElementById(id);
    if (!el) return false;
    try { el.setAttribute('hidden', ''); } catch(_) {}
    el.classList.add('hidden');
    el.classList.remove('show', 'wf-modal--body-scroll');
    el.setAttribute('aria-hidden', 'true');
    // If no other admin overlays are visible, unlock page scroll
    try {
      const anyOpen = document.querySelector('.admin-modal-overlay.show');
      if (!anyOpen) {
        document.documentElement.classList.remove('modal-open');
        document.body.classList.remove('modal-open', 'wf-no-scroll');
      }
    } catch(_) {}
    return true;
  },

  // Initialize modal close handlers
  init() {
    document.addEventListener('click', (e) => {
      const t = e.target;
      const closest = (sel) => (t && t.closest ? t.closest(sel) : null);

      // Handle modal overlay clicks
      if (t && t.classList && t.classList.contains('admin-modal-overlay')) {
        const id = t.id;
        if (id) {
          e.preventDefault();
          if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
          this.hide(id);
        }
        return;
      }

      // Handle close buttons
      if (closest('[data-action="close-admin-modal"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        const overlay = closest('.admin-modal-overlay');
        if (overlay && overlay.id) { this.hide(overlay.id); }
        return;
      }

      // Handle specific modal close actions
      if (closest('[data-action="close-business-info"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        this.hide('businessInfoModal');
        return;
      }

      if (closest('[data-action="close-email-settings"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        this.hide('emailSettingsModal');
        return;
      }

      if (closest('[data-action="close-square-settings"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        this.hide('squareSettingsModal');
        return;
      }

      if (closest('[data-action="close-ai-settings"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        this.hide('aiSettingsModal');
        return;
      }

      if (closest('[data-action="close-ai-tools"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        this.hide('aiToolsModal');
        return;
      }

      if (closest('[data-action="close-css-rules"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        this.hide('cssRulesModal');
        return;
      }

      if (closest('[data-action="close-logging-status"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        this.hide('loggingStatusModal');
        return;
      }

      if (closest('[data-action="close-secrets-modal"]')) {
        e.preventDefault();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
        this.hide('secretsModal');
        return;
      }
    });
  }
};

// Make functions available globally for backward compatibility
if (typeof window !== 'undefined') {
  window.showModal = (id) => ModalManager.show(id);
  window.hideModal = (id) => ModalManager.hide(id);
}

// Initialize modal system
ModalManager.init();
