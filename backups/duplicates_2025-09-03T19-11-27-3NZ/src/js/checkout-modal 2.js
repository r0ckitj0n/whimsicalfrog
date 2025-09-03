// Checkout Modal Overlay — reuses global confirmation modal styling
// Creates a site-wide modal for checkout with order summary

(function initCheckoutModal() {
  try {
    if (window.WF_CheckoutModal && window.WF_CheckoutModal.initialized) return;

    const state = {
      overlay: null,
      container: null,
      keydownHandler: null,
    };

    function currency(v) { return `$${(parseFloat(v) || 0).toFixed(2)}`; }

    function createOverlay() {
      const existing = document.getElementById('checkoutModalOverlay');
      if (existing) existing.remove();

      const overlay = document.createElement('div');
      overlay.id = 'checkoutModalOverlay';
      overlay.className = 'confirmation-modal-overlay';

      const modal = document.createElement('div');
      modal.className = 'confirmation-modal checkout-modal animate-slide-in-up';

      overlay.appendChild(modal);
      document.body.appendChild(overlay);

      state.overlay = overlay;
      state.container = modal;

      // Close on overlay click
      overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

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
      state.overlay.classList.add('show');
      try { window.WFModals && window.WFModals.lockScroll && window.WFModals.lockScroll(); } catch(_){ }
    }

    function close() {
      if (!state.overlay) return;
      state.overlay.classList.remove('show');
      try { window.WFModals && window.WFModals.unlockScrollIfNoneOpen && window.WFModals.unlockScrollIfNoneOpen(); } catch(_){ }
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
