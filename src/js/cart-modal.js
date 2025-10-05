// Global Cart Modal Overlay
// Creates a site-wide modal for the cart and wires header cart link to open it
import '../styles/cart-modal.css';

(function initCartModal() {
  try {
    // Avoid double init
    if (window.WF_CartModal && window.WF_CartModal.initialized) return;

    const state = {
      overlay: null,
      container: null,
      itemsEl: null,
      keydownHandler: null,
      lockDepth: 0,
      prevHtmlOverflow: '',
      prevBodyOverflow: ''
    };

    function createOverlay() {
      // Remove any existing overlay just in case
      const existing = document.getElementById('cartModalOverlay');
      if (existing) existing.remove();

      // Use the confirmation-modal-overlay class family so existing CSS/JS handles z-index/scroll
      const overlay = document.createElement('div');
      overlay.id = 'cartModalOverlay';
      overlay.className = 'confirmation-modal-overlay';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');

      // Modal container
      const modal = document.createElement('div');
      modal.className = 'confirmation-modal cart-modal animate-slide-in-up';

      // Inner content (cart-specific header classes to avoid room-modal overrides)
      modal.innerHTML = `
        <div class="bg-white rounded-lg shadow-xl overflow-hidden w-full max-w-3xl max-h-[90vh] flex flex-col">
          <div class="flex-shrink-0 bg-white border-b border-gray-200 cart-header">
            <div class="cart-modal-header-bar">
              <h1 class="cart-modal-title">Shopping Cart</h1>
              <button type="button" class="cart-modal-close-btn" data-action="close-cart-modal" aria-label="Close cart">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                <span>Close</span>
              </button>
            </div>
          </div>
          <div id="cartModalItems" class="flex-1 overflow-y-auto cart_column_layout cart_column_direction cart-scrollbar">
            <div class="p-6 text-center text-gray-500">Loading cart...</div>
          </div>
          <div id="cartModalFooter" class="cart-modal-footer"></div>
        </div>
      `;

      overlay.appendChild(modal);
      document.body.appendChild(overlay);

      state.overlay = overlay;
      state.container = modal;
      state.itemsEl = overlay.querySelector('#cartModalItems');

      // Let clicks inside the modal bubble so global delegated handlers (e.g., remove item) work.
      // Underlying page is already shielded by the overlay covering the viewport.

      // Close on overlay click (outside modal content)
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
      });

      // Close button
      overlay.querySelector('[data-action="close-cart-modal"]').addEventListener('click', () => close());

      // Bind ESC once (global), referencing state.overlay so it always targets current overlay
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

    // CSS class-based scroll lock (WFModals-free)
    function lockScrollCss() {
      try {
        if (state.lockDepth === 0) {
          document.documentElement.classList.add('wf-scroll-locked');
          document.body.classList.add('wf-scroll-locked');
        }
        state.lockDepth++;
      } catch(_) {}
    }
    function unlockScrollCss() {
      try {
        state.lockDepth = Math.max(0, state.lockDepth - 1);
        if (state.lockDepth === 0) {
          document.documentElement.classList.remove('wf-scroll-locked');
          document.body.classList.remove('wf-scroll-locked');
        }
      } catch(_) {}
    }

    function renderIfOpen() {
      if (state.overlay && state.overlay.classList.contains('show')) {
        try {
          if (window.WF_Cart && typeof window.WF_Cart.renderCart === 'function') {
            window.WF_Cart.renderCart();
          }
        } catch (err) {
          console.error('[CartModal] Failed to render cart in modal:', err);
        }
      }
    }

    function open() {
      // If overlay is missing OR has old header structure, rebuild it
      if (!state.overlay || !state.overlay.querySelector('.cart-modal-header-bar')) {
        if (state.overlay) {
          try { state.overlay.remove(); } catch(_) {}
          state.overlay = null;
        }
        createOverlay();
      }
      try { window.WFModalUtils && window.WFModalUtils.ensureOnBody && window.WFModalUtils.ensureOnBody(state.overlay); } catch(_) {}
      state.overlay.classList.add('show');
      try { state.overlay.setAttribute('aria-hidden', 'false'); } catch(_) {}
      // Lock background scroll via CSS class
      try { lockScrollCss(); } catch(_) {}
      // Always resync from storage upon open to avoid stale in-memory state
      try { window.WF_Cart?.refreshFromStorage?.(); } catch(_) {}
      // Render into the modal container
      renderIfOpen();
      // And render again shortly to catch any late init events
      setTimeout(() => { try { window.WF_Cart?.renderCart?.(); } catch(_) {} }, 120);
    }

    function close() {
      if (!state.overlay) return;
      // Proactively release focus from any focused control inside the overlay
      try {
        const ae = document.activeElement;
        if (ae && typeof ae.blur === 'function') ae.blur();
      } catch(_) {}
      state.overlay.classList.remove('show');
      try { state.overlay.setAttribute('aria-hidden', 'true'); } catch(_) {}
      // Unlock background scroll via CSS class
      try { unlockScrollCss(); } catch(_) {}
    }

    // Public API
    window.WF_CartModal = {
      open,
      close,
      initialized: true,
    };
    window.openCartModal = open;
    window.closeCartModal = close;

    // Intercept header cart button/link clicks (support multiple selector variants)
    document.addEventListener('click', (e) => {
      const btn = e.target && e.target.closest && e.target.closest('a.cart-link, .cart-toggle, .cart-button, [data-action="open-cart"], #cartButton');
      if (!btn) return;
      // Allow new tab/window behavior for anchor clicks
      if ((btn.tagName === 'A') && (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1)) return;
      e.preventDefault();
      e.stopPropagation();
      open();
    });

    // Intercept Checkout button clicks inside the cart modal to go directly to payment (login-gated)
    document.addEventListener('click', (e) => {
      const btn = e.target && e.target.closest && e.target.closest('.cart-checkout-btn');
      if (!btn) return;
      // Allow new tab/window behavior
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
      // If disabled, do nothing
      const isDisabled = (
        btn.classList.contains('is-disabled') ||
        btn.classList.contains('disabled') ||
        btn.hasAttribute('disabled') ||
        btn.getAttribute('aria-disabled') === 'true'
      );
      if (isDisabled) return;
      e.preventDefault();
      e.stopPropagation();
      // Release focus first to avoid aria-hidden block warnings
      try {
        const ae = document.activeElement;
        if (ae && typeof ae.blur === 'function') ae.blur();
      } catch(_) {}
      // Close the cart overlay before proceeding so stacking/context is clear
      try { close(); } catch(_){ }

      const proceed = () => {
        // Preferred: open in-place payment modal if available (handles login gating internally)
        const disableInline = (typeof window.__WF_DISABLE_INLINE_CHECKOUT !== 'undefined') ? !!window.__WF_DISABLE_INLINE_CHECKOUT : false;
        if (!disableInline && window.WF_PaymentModal && typeof window.WF_PaymentModal.open === 'function') {
          try { localStorage.setItem('pendingCheckout', 'true'); } catch(_) {}
          window.WF_PaymentModal.open();
          return;
        }

        // Fallback: navigate to the dedicated payment page
        const target = btn.getAttribute('href') || '/payment';
        try { localStorage.setItem('pendingCheckout', 'true'); } catch(_) {}

        const isLoggedIn = (document && document.body && document.body.getAttribute('data-is-logged-in') === 'true');
        if (!isLoggedIn && typeof window.openLoginModal === 'function') {
          const desiredReturn = window.location.pathname + window.location.search + window.location.hash;
          window.openLoginModal(desiredReturn, {
            suppressRedirect: true,
            onSuccess: () => {
              try { if (document && document.body) document.body.setAttribute('data-is-logged-in', 'true'); } catch(_) {}
              window.location.href = target;
            }
          });
          return;
        }
        window.location.href = target;
      };

      // Defer slightly to allow the cart overlay to finish closing and release focus
      setTimeout(proceed, 40);
    });

    // Re-render when cart updates
    window.addEventListener('cartUpdated', renderIfOpen);

    // Also refresh and render on focus/visibility/page show (covers auth redirects and BFCache)
    const refreshIfOpen = () => {
      try { window.WF_Cart?.refreshFromStorage?.(); } catch(_) {}
      renderIfOpen();
    };
    window.addEventListener('focus', () => setTimeout(refreshIfOpen, 50));
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') setTimeout(refreshIfOpen, 50);
    });
    window.addEventListener('pageshow', () => setTimeout(refreshIfOpen, 50));

    // Refresh modal after login success (modal may be open during auth)
    // Rebuild overlay and force re-render to avoid stale "Loading" state after auth
    window.addEventListener('wf:login-success', () => {
      try {
        // Force a state resync from storage first
        try { window.WF_Cart?.refreshFromStorage?.(); } catch(_) {}
        // Nudge header counters regardless
        try { window.WF_Cart?.updateCartDisplay?.(); } catch(_) {}

        if (state.overlay && state.overlay.classList.contains('show')) {
          // Fully rebuild overlay DOM to mimic manual reopen behavior
          try { close(); } catch(_) {}
          try { state.overlay.remove(); } catch(_) {}
          state.overlay = null;
          state.container = null;
          state.itemsEl = null;
          // Recreate immediately, then open after a short delay so CSS/classes settle
          createOverlay();
          setTimeout(() => {
            try {
              open();
              // After open, force a render in case the cartUpdated init raced
              setTimeout(() => {
                try { window.WF_Cart?.refreshFromStorage?.(); } catch(_) {}
                try { window.WF_Cart?.renderCart?.(); } catch(_) {}
              }, 140);
            } catch(_) {}
          }, 140);
        } else {
          // If not open, ensure next open renders latest cart
          // and attempt a render in case overlay exists but is hidden
          setTimeout(() => {
            try { window.WF_Cart?.refreshFromStorage?.(); } catch(_) {}
            try { window.WF_Cart?.renderCart?.(); } catch(_) {}
          }, 80);
        }
      } catch(_) {}
    });

    // Initialize overlay immediately so it is present for z-index stacking and quick open
    createOverlay();

    console.log('[CartModal] initialized');
  } catch (err) {
    console.error('[CartModal] init error', err);
  }
})();
