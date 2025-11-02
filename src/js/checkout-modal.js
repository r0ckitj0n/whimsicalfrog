// Checkout Modal Overlay — reuses global confirmation modal styling
// Creates a site-wide modal for checkout with order summary

(function initCheckoutModal() {
  try {
    if (window.WF_CheckoutModal && window.WF_CheckoutModal.initialized) return;

    const state = {
      overlay: null,
      container: null,
      keydownHandler: null,
      previouslyFocusedElement: null,
    };

    function currency(v) { return `$${(parseFloat(v) || 0).toFixed(2)}`; }

    function createOverlay() {
      const existing = document.getElementById('checkoutModalOverlay');
      if (existing) existing.remove();

      const overlay = document.createElement('div');
      overlay.id = 'checkoutModalOverlay';
      overlay.className = 'confirmation-modal-overlay';
      try { overlay.setAttribute('role', 'dialog'); overlay.setAttribute('aria-modal', 'true'); overlay.setAttribute('tabindex', '-1'); } catch(_) {}

      const modal = document.createElement('div');
      modal.className = 'confirmation-modal checkout-modal animate-slide-in-up';

      overlay.appendChild(modal);
      document.body.appendChild(overlay);

      state.overlay = overlay;
      state.container = modal;

      // Close on overlay click
      overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

      // aria-labelledby is set after render in open()

      // Focus trap
      if (!overlay._wfFocusTrap) {
        overlay._wfFocusTrap = (e) => {
          if (e.key !== 'Tab') return;
          if (!state.overlay || !state.overlay.classList.contains('show')) return;
          const scope = state.container || state.overlay;
          const nodes = scope.querySelectorAll('a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])');
          const focusables = Array.from(nodes).filter(el => !el.hasAttribute('disabled') && el.tabIndex !== -1 && el.offsetParent !== null);
          if (!focusables.length) return;
          const first = focusables[0];
          const last = focusables[focusables.length - 1];
          const active = document.activeElement;
          if (e.shiftKey) {
            if (active === first || !scope.contains(active)) { last.focus(); e.preventDefault(); }
          } else {
            if (active === last || !scope.contains(active)) { first.focus(); e.preventDefault(); }
          }
        };
        overlay.addEventListener('keydown', overlay._wfFocusTrap, true);
      }

      // ESC to close
      if (!state.keydownHandler) {
        state.keydownHandler = (e) => {
          try {
            if (e.key === 'Escape' && state.overlay && state.overlay.classList.contains('show')) {
              close();
            }
          } catch(_) {}
        };
        document.addEventListener('keydown', state.keydownHandler);
      }
    }

    function render() {
      const cart = window.WF_Cart;
      const items = (cart && typeof cart.getItems === 'function') ? cart.getItems() : [];
      const total = (cart && typeof cart.getTotal === 'function') ? cart.getTotal() : 0;

      const itemsHtml = items.length ? items.map(it => {
        const qty = it.quantity || 1;
        const price = parseFloat(it.price || 0);
        const line = qty * price;
        const name = it.name || it.sku || 'Item';
        return `
          <div class="checkout-line">
            <div class="checkout-line-main">
              <span class="checkout-qty">${qty}×</span>
              <span class="checkout-name">${name}</span>
            </div>
            <div class="checkout-line-total">${currency(line)}</div>
          </div>
        `;
      }).join('') : '<div class="text-gray-500">Your cart is empty.</div>';

      state.container.innerHTML = `
        <div class="confirmation-modal-header">
          <div class="confirmation-modal-icon info">ℹ️</div>
          <h3 class="confirmation-modal-title">Checkout</h3>
          <p class="confirmation-modal-subtitle">Review your order and continue</p>
        </div>
        <div class="confirmation-modal-body">
          <div class="confirmation-modal-details">
            ${itemsHtml}
            <div class="checkout-subtotal">
              <span>Subtotal</span>
              <strong>${currency(total)}</strong>
            </div>
          </div>
        </div>
        <div class="confirmation-modal-footer">
          <button class="confirmation-modal-button cancel" id="checkout-cancel">Cancel</button>
          <button class="confirmation-modal-button confirm" id="checkout-continue" ${items.length ? '' : 'disabled'}>Continue</button>
        </div>
      `;

      // Wire buttons
      const cancelBtn = state.container.querySelector('#checkout-cancel');
      const continueBtn = state.container.querySelector('#checkout-continue');
      if (cancelBtn) cancelBtn.addEventListener('click', close);
      if (continueBtn) continueBtn.addEventListener('click', async () => {
        // Navigate to protected payment step; server enforces login and redirect
        try { localStorage.setItem('pendingCheckout', 'true'); } catch(_) {}
        close();
        window.location.href = '/payment';
      });
    }

    function open() {
      if (!state.overlay) createOverlay();
      render();
      try { window.WFModalUtils && window.WFModalUtils.ensureOnBody && window.WFModalUtils.ensureOnBody(state.overlay); } catch(_) {}
      try { state.previouslyFocusedElement = document.activeElement; } catch(_) {}
      // Ensure aria-labelledby references the actual title rendered
      try {
        const t = state.container && state.container.querySelector && state.container.querySelector('.confirmation-modal-title');
        if (t && !t.id) t.id = 'checkoutModalTitle';
        if (t && t.id) state.overlay.setAttribute('aria-labelledby', t.id);
      } catch(_) {}
      if (typeof window.showModal === 'function') {
        window.showModal('checkoutModalOverlay');
      } else {
        state.overlay.classList.add('show');
        try { state.overlay.setAttribute('aria-hidden', 'false'); } catch(_) {}
        try { window.WFModals && window.WFModals.lockScroll && window.WFModals.lockScroll(); } catch(_){ }
      }
      try {
        const scope = state.container || state.overlay;
        const target = scope.querySelector('#checkout-continue, #checkout-cancel, button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const focusIt = () => { try { (target && typeof target.focus === 'function') ? target.focus() : (state.overlay && state.overlay.focus && state.overlay.focus()); } catch(_) {} };
        focusIt();
        try { requestAnimationFrame(() => { focusIt(); requestAnimationFrame(focusIt); }); } catch(_) {}
        setTimeout(focusIt, 150);
      } catch(_) {}
    }

    function close() {
      if (!state.overlay) return;
      if (typeof window.hideModal === 'function') {
        window.hideModal('checkoutModalOverlay');
      } else {
        state.overlay.classList.remove('show');
        try { state.overlay.setAttribute('aria-hidden', 'true'); } catch(_) {}
        try { window.WFModals && window.WFModals.unlockScrollIfNoneOpen && window.WFModals.unlockScrollIfNoneOpen(); } catch(_){ }
      }
      try { if (state.previouslyFocusedElement) state.previouslyFocusedElement.focus(); } catch(_) {}
      try { state.previouslyFocusedElement = null; } catch(_) {}
    }

    window.WF_CheckoutModal = {
      open,
      close,
      initialized: true,
    };

    // Precreate overlay for z-index stacking
    createOverlay();
    console.log('[CheckoutModal] initialized');
  } catch (err) {
    console.error('[CheckoutModal] init error', err);
  }
})();
