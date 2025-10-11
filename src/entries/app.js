// Vite entry: app.js
// Import global CSS so Vite serves and bundles styles in dev and prod
import '../styles/main.css';
// Install global delegated hover/click listeners for item icons across pages
import '../room/event-manager.js';

// Fallback CSS neutralizer in case legacy bundle overrides coordinates or pointer-events
try {
  const __wfFallbackStyle = document.createElement('style');
  __wfFallbackStyle.id = 'wf-fallback-globalpopup-style';
  __wfFallbackStyle.innerHTML = `
    .item-popup{pointer-events:auto !important;}
    .item-popup.positioned{left:auto !important; top:auto !important;}
  `;
  document.head.appendChild(__wfFallbackStyle);
} catch (_) {}

// Page router: conditionally load modules for each page
(async () => {
  try {
    const page = document.body?.getAttribute('data-page') || '';
    const path = (location.pathname || '').toLowerCase();

    // Always-on core utilities (safe to load everywhere)
    try { await import('../core/image-error-handler.js'); } catch (_) {}
    try { await import('../core/ui-helpers.js'); } catch (_) {}
    // Global item popup (used by room modal hovers)
    try { await import('../ui/global-popup.js'); } catch (_) {}

    // Fallback: enforce popup persistence when modern popup is not available
    (function installPopupFallbackPersistence(){
      const ENABLE_POPUP_FALLBACK = false; // keep code for reference, disabled by default
      // Skip entirely when flag disabled or modern popup is present
      if (!ENABLE_POPUP_FALLBACK || window.__WF_MODERN_POPUP) return;
      if (window.__WF_POPUP_FALLBACK_INSTALLED) return;
      window.__WF_POPUP_FALLBACK_INSTALLED = true;
      const log = (...args) => { try { console.log('[ViteEntry/Fallback]', ...args); } catch(_) {} };

      // Handlers are defined here so we can uninstall later if needed
      const cancel = () => { try { window.cancelHideGlobalPopup && window.cancelHideGlobalPopup(); } catch(_) {} };
      const schedule = () => { try { window.scheduleHideGlobalPopup && window.scheduleHideGlobalPopup(500); } catch(_) {} };
      const delayHide = () => { try { window.scheduleHideGlobalPopup && window.scheduleHideGlobalPopup(500); } catch(_) {} };

      const bindOnce = () => {
        const popup = document.getElementById('itemPopup') || document.querySelector('.item-popup');
        if (!popup) return false;
        if (popup.__wfFallbackBound) return true;
        popup.__wfFallbackBound = true;
        popup.addEventListener('mouseenter', cancel);
        popup.addEventListener('mouseleave', schedule);
        // Also mark positioned in case the runtime relies on it to neutralize legacy absolute CSS
        try { popup.classList.add('positioned'); } catch(_) {}
        log('Popup fallback persistence bound');
        return true;
      };

      const bindIframeBoundaries = () => {
        document.querySelectorAll('.room-modal-overlay iframe, iframe[data-room], .room-modal-overlay [data-role="room-frame"]').forEach(ifr => {
          if (ifr.__wfPopupBoundaryFallback) return;
          ifr.addEventListener('mouseover', cancel);
          ifr.addEventListener('mouseout', delayHide);
          ifr.__wfPopupBoundaryFallback = true;
          log('Iframe boundary fallback bound', ifr);
        });
      };

      const uninstall = () => {
        try {
          const popup = document.getElementById('itemPopup') || document.querySelector('.item-popup');
          if (popup && popup.__wfFallbackBound) {
            popup.removeEventListener('mouseenter', cancel);
            popup.removeEventListener('mouseleave', schedule);
            popup.__wfFallbackBound = false;
            log('Popup fallback persistence uninstalled');
          }
          document.querySelectorAll('.room-modal-overlay iframe, iframe[data-room], .room-modal-overlay [data-role="room-frame"]').forEach(ifr => {
            if (ifr.__wfPopupBoundaryFallback) {
              ifr.removeEventListener('mouseover', cancel);
              ifr.removeEventListener('mouseout', delayHide);
              ifr.__wfPopupBoundaryFallback = false;
            }
          });
        } catch (_) {}
      };

      // If modern popup becomes ready later, uninstall fallback
      try { window.addEventListener('wf:modern-popup-ready', uninstall, { once: true }); } catch(_) {}

      // Try immediately, then on DOM ready, then periodically for dynamically inserted DOM
      const tryBind = () => { const ok = bindOnce(); bindIframeBoundaries(); return ok; };
      if (!tryBind()) {
        document.addEventListener('DOMContentLoaded', tryBind, { once: true });
        window.addEventListener('load', tryBind, { once: true });
        // Poll briefly to catch late-inserted popups/iframes during modal open
        let tries = 0; const maxTries = 40; // ~4s total
        const t = setInterval(() => { if (tryBind() || ++tries >= maxTries) clearInterval(t); }, 100);
      }
    })();
    // Global item details modal (opened from popup or product icon clicks)
    try { await import('../js/detailed-item-modal.js'); } catch (_) {}

    // Ensure cart modal is available on all pages (header cart button should open a modal)
    try { await import('../js/cart-modal.js'); } catch (_) {}
    // Ensure payment modal is available so Checkout opens a modal not a page
    try { await import('../js/payment-modal.js'); } catch (_) {}

    // Ensure cart system and WF_Cart adapter are available on ALL pages (room_main needs it)
    try {
      if (!window.WF_Cart) {
        const mod = await import('../commerce/cart-system.js');
        const cart = mod?.cart || window.cart;
        if (cart && !window.WF_Cart) {
          const currency = (v) => `$${(Number(v) || 0).toFixed(2)}`;
          const updateUI = () => {
            try {
              const count = cart.getCount ? cart.getCount() : 0;
              const total = cart.getTotal ? cart.getTotal() : 0;
              document.querySelectorAll('.cart-count, .cart-counter, #cart-count').forEach(el => {
                try { el.textContent = String(count); el.classList.toggle('hidden', count === 0); } catch(_) {}
              });
              document.querySelectorAll('#cartCount').forEach(el => { try { el.textContent = `${count} ${count === 1 ? 'item' : 'items'}`; } catch(_) {} });
              document.querySelectorAll('#cartTotal').forEach(el => { try { el.textContent = `$${Number(total).toFixed(2)}`; } catch(_) {} });
              try { window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { state: { count, total, items: cart.getItems ? cart.getItems() : [] } } })); } catch(_) {}
            } catch(_) {}
          };
          const renderCartDOM = () => {
            try {
              const itemsEl = document.getElementById('cartModalItems');
              const footerEl = document.getElementById('cartModalFooter');
              if (!itemsEl) return;
              const items = cart.getItems ? cart.getItems() : [];
              const total = cart.getTotal ? cart.getTotal() : 0;
              if (!items.length) {
                itemsEl.innerHTML = '<div class="p-6 text-center text-gray-600">Your cart is empty.</div>';
                if (footerEl) footerEl.innerHTML = `
                  <div class="cart-footer-bar">
                    <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(total)}</strong></div>
                    <a class="cart-checkout-btn is-disabled" aria-disabled="true">Checkout</a>
                  </div>
                `;
                return;
              }
              const itemsHtml = items.map((item) => {
                const lineTotal = (Number(item.price) || 0) * (Number(item.quantity) || 0);
                const img = item.image ? `<img src="${item.image}" alt="${(item.name||item.sku)||''}" class="cart-item-image"/>` : '';
                const optionBits = [];
                if (item.optionGender) optionBits.push(item.optionGender);
                if (item.optionSize) optionBits.push(item.optionSize);
                if (item.optionColor) optionBits.push(item.optionColor);
                const optionsHtml = optionBits.length ? `<div class="cart-item-options text-sm text-gray-500">${optionBits.join(' • ')}</div>` : '';
                return `
                  <div class="cart-item" data-sku="${item.sku}">
                    ${img}
                    <div class="cart-item-details">
                      <div class="cart-item-title">${item.name || item.sku}</div>
                      ${optionsHtml}
                      <div class="cart-item-price">${currency(item.price)}</div>
                    </div>
                    <div class="cart-item-quantity">
                      <input type="number" min="0" class="cart-quantity-input" data-sku="${item.sku}" value="${item.quantity}" />
                    </div>
                    <div class="cart-item-remove remove-from-cart" data-sku="${item.sku}" aria-label="Remove item" title="Remove">
                      <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-trash" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                        <path d="M10 11v6"></path>
                        <path d="M14 11v6"></path>
                        <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path>
                      </svg>
                    </div>
                    <div class="cart-item-line-total">${currency(lineTotal)}</div>
                  </div>
                `;
              }).join('');
              itemsEl.innerHTML = itemsHtml;
              if (footerEl) {
                const disabledClass = (cart.getCount && cart.getCount() > 0) ? '' : 'is-disabled';
                const checkoutHref = (cart.getCount && cart.getCount() > 0) ? ' href="/payment"' : '';
                footerEl.innerHTML = `
                  <div class="cart-footer-bar">
                    <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(total)}</strong></div>
                    <a class="cart-checkout-btn ${disabledClass}"${checkoutHref}>Checkout</a>
                  </div>
                `;
              }
            } catch (e) {
              console.warn('[ViteEntry] renderCartDOM failed', e);
            }
          };
          window.WF_Cart = {
            addItem: (item) => { try { console.log('[ViteEntry] WF_Cart.addItem()', { sku: item?.sku, qty: item?.quantity }); cart.add(item, item?.quantity || 1); updateUI(); try { renderCartDOM(); } catch(_) {} } catch (e) { console.warn('[ViteEntry] WF_Cart.addItem adapter failed', e); } },
            removeItem: (sku) => { try { console.log('[ViteEntry] WF_Cart.removeItem()', { sku }); cart.remove && cart.remove(sku); updateUI(); renderCartDOM(); } catch (e) { console.warn('[ViteEntry] WF_Cart.removeItem adapter failed', e); } },
            updateItem: (sku, qty) => { try { console.log('[ViteEntry] WF_Cart.updateItem()', { sku, qty }); cart.updateQuantity && cart.updateQuantity(sku, qty); updateUI(); renderCartDOM(); } catch (e) { console.warn('[ViteEntry] WF_Cart.updateItem adapter failed', e); } },
            clearCart: () => { try { console.log('[ViteEntry] WF_Cart.clearCart()'); cart.clear && cart.clear(); updateUI(); renderCartDOM(); } catch (e) { console.warn('[ViteEntry] WF_Cart.clearCart adapter failed', e); } },
            getItems: () => { try { return cart.getItems ? cart.getItems() : []; } catch (_) { return []; } },
            getTotal: () => { try { return cart.getTotal ? cart.getTotal() : 0; } catch (_) { return 0; } },
            getCount: () => { try { return cart.getCount ? cart.getCount() : 0; } catch (_) { return 0; } },
            renderCart: () => { try { renderCartDOM(); } catch(_) {} },
            refreshFromStorage: () => { try { cart.load && cart.load(); cart.save && cart.save(); updateUI(); renderCartDOM(); return { items: cart.getItems?.()||[], total: cart.getTotal?.()||0, count: cart.getCount?.()||0 }; } catch(_) { return { items: [], total: 0, count: 0 }; } },
          };
          console.log('[ViteEntry] WF_Cart adapter installed globally (bridged to commerce/cart-system)');
          try { updateUI(); renderCartDOM(); } catch(_) {}
        }
      }
    } catch (_) {}


    // Always-on modal capture to prevent navigation and open modal for doors
    try {
      const { default: RoomModalManager } = await import('../modules/room-modal-manager.js');
      if (!window.__roomModalManager) {
        window.__roomModalManager = new RoomModalManager();
      }
    } catch (_) {}

    // Landing page: background + coordinate-based elements (e.g., welcome sign)
    if (page === 'landing' || document.getElementById('landingPage')) {
      try { await import('../core/dynamic-background.js'); } catch (_) {}
      // Landing specific behaviors (welcome sign positioning, etc.)
      try { await import('../js/landing-page.js'); } catch (_) {}
    }

    // (Removed element-conditional import; modal manager is now always loaded.)

    // Note: We intentionally avoid initializing RoomCoordinator here to prevent
    // scripted navigation. RoomModalManager handles door clicks and opens modals.

    // Shop page: cart + sales + shop interactions
    if (page === 'shop' || document.getElementById('shopPage')) {
      // Load ESM cart singleton (exports `cart` and auto-exposes window.cart)
      try {
        const mod = await import('../commerce/cart-system.js');
        // Bridge to legacy WF_Cart API if missing
        try {
          if (!window.WF_Cart) {
            const cart = mod?.cart || window.cart;
            const currency = (v) => `$${(Number(v) || 0).toFixed(2)}`;
            const updateUI = () => {
              try {
                const count = cart.getCount ? cart.getCount() : 0;
                const total = cart.getTotal ? cart.getTotal() : 0;
                // Generic numeric badges
                document.querySelectorAll('.cart-count, .cart-counter, #cart-count').forEach(el => {
                  try { el.textContent = String(count); el.classList.toggle('hidden', count === 0); } catch(_) {}
                });
                // Header labels
                document.querySelectorAll('#cartCount').forEach(el => {
                  try { el.textContent = `${count} ${count === 1 ? 'item' : 'items'}`; } catch(_) {}
                });
                document.querySelectorAll('#cartTotal').forEach(el => {
                  try { el.textContent = `$${Number(total).toFixed(2)}`; } catch(_) {}
                });
                // Fire a DOM event for any listeners (cart modal, checkout page, etc.)
                try { window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { state: { count, total, items: cart.getItems ? cart.getItems() : [] } } })); } catch(_) {}
              } catch(_) {}
            };
            const renderCartDOM = () => {
              try {
                const itemsEl = document.getElementById('cartModalItems');
                const footerEl = document.getElementById('cartModalFooter');
                if (!itemsEl) return;
                const items = cart.getItems ? cart.getItems() : [];
                const total = cart.getTotal ? cart.getTotal() : 0;
                if (!items.length) {
                  itemsEl.innerHTML = '<div class="p-6 text-center text-gray-600">Your cart is empty.</div>';
                  if (footerEl) footerEl.innerHTML = `
                    <div class="cart-footer-bar">
                      <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(total)}</strong></div>
                      <a class="cart-checkout-btn is-disabled" aria-disabled="true">Checkout</a>
                    </div>
                  `;
                  return;
                }
                const itemsHtml = items.map((item) => {
                  const lineTotal = (Number(item.price) || 0) * (Number(item.quantity) || 0);
                  const img = item.image ? `<img src="${item.image}" alt="${(item.name||item.sku)||''}" class="cart-item-image"/>` : '';
                  const optionBits = [];
                  if (item.optionGender) optionBits.push(item.optionGender);
                  if (item.optionSize) optionBits.push(item.optionSize);
                  if (item.optionColor) optionBits.push(item.optionColor);
                  const optionsHtml = optionBits.length ? `<div class="cart-item-options text-sm text-gray-500">${optionBits.join(' • ')}</div>` : '';
                  return `
                    <div class="cart-item" data-sku="${item.sku}">
                      ${img}
                      <div class="cart-item-details">
                        <div class="cart-item-title">${item.name || item.sku}</div>
                        ${optionsHtml}
                        <div class="cart-item-price">${currency(item.price)}</div>
                      </div>
                      <div class="cart-item-quantity">
                        <input type="number" min="0" class="cart-quantity-input" data-sku="${item.sku}" value="${item.quantity}" />
                      </div>
                      <div class="cart-item-remove remove-from-cart" data-sku="${item.sku}" aria-label="Remove item" title="Remove">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-trash" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                          <polyline points="3 6 5 6 21 6"></polyline>
                          <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                          <path d="M10 11v6"></path>
                          <path d="M14 11v6"></path>
                          <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path>
                        </svg>
                      </div>
                      <div class="cart-item-line-total">${currency(lineTotal)}</div>
                    </div>
                  `;
                }).join('');
                itemsEl.innerHTML = itemsHtml;
                if (footerEl) {
                  const disabledClass = (cart.getCount && cart.getCount() > 0) ? '' : 'is-disabled';
                  const checkoutHref = (cart.getCount && cart.getCount() > 0) ? ' href="/payment"' : '';
                  footerEl.innerHTML = `
                    <div class="cart-footer-bar">
                      <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(total)}</strong></div>
                      <a class="cart-checkout-btn ${disabledClass}"${checkoutHref}>Checkout</a>
                    </div>
                  `;
                }
              } catch (e) {
                console.warn('[ViteEntry] renderCartDOM failed', e);
              }
            };
            if (cart) {
              window.WF_Cart = {
                addItem: (item) => {
                  try {
                    console.log('[ViteEntry] WF_Cart.addItem()', { sku: item?.sku, qty: item?.quantity });
                    cart.add(item, item?.quantity || 1);
                    updateUI();
                    try { renderCartDOM(); } catch(_) {}
                  } catch (e) { console.warn('[ViteEntry] WF_Cart.addItem adapter failed', e); }
                },
                removeItem: (sku) => { try { console.log('[ViteEntry] WF_Cart.removeItem()', { sku }); cart.remove && cart.remove(sku); updateUI(); renderCartDOM(); } catch (e) { console.warn('[ViteEntry] WF_Cart.removeItem adapter failed', e); } },
                updateItem: (sku, qty) => { try { console.log('[ViteEntry] WF_Cart.updateItem()', { sku, qty }); cart.updateQuantity && cart.updateQuantity(sku, qty); updateUI(); renderCartDOM(); } catch (e) { console.warn('[ViteEntry] WF_Cart.updateItem adapter failed', e); } },
                clearCart: () => { try { console.log('[ViteEntry] WF_Cart.clearCart()'); cart.clear && cart.clear(); updateUI(); renderCartDOM(); } catch (e) { console.warn('[ViteEntry] WF_Cart.clearCart adapter failed', e); } },
                getItems: () => { try { return cart.getItems ? cart.getItems() : []; } catch (_) { return []; } },
                getTotal: () => { try { return cart.getTotal ? cart.getTotal() : 0; } catch (_) { return 0; } },
                getCount: () => { try { return cart.getCount ? cart.getCount() : 0; } catch (_) { return 0; } },
                renderCart: () => { try { renderCartDOM(); } catch(_) {} },
                refreshFromStorage: () => { try { cart.load && cart.load(); cart.save && cart.save(); updateUI(); renderCartDOM(); return { items: cart.getItems?.()||[], total: cart.getTotal?.()||0, count: cart.getCount?.()||0 }; } catch(_) { return { items: [], total: 0, count: 0 }; } },
              };
              console.log('[ViteEntry] WF_Cart adapter installed (bridged to commerce/cart-system)');
              // Initial UI sync on install
              try { updateUI(); renderCartDOM(); } catch(_) {}
            }
          }
        } catch (_) { /* noop */ }
      } catch (_) {}
      try { await import('../commerce/sales-checker.js'); } catch (_) {}
      // (Loaded globally above)
      try { await import('../js/shop.js'); } catch (_) {}
    }

    // Admin settings bridged features often needed in admin routes
    if (page.startsWith('admin/')) {
      try { await import('../js/admin-settings-bridge.js'); } catch (_) {}
    } else {
      // Fallback: when routed via admin_router.php?section=settings and data-page is missing
      const params = new URLSearchParams(location.search || '');
      const isAdminRouterSettings = path.includes('/sections/admin_router.php') && (params.get('section') === 'settings');
      if (isAdminRouterSettings) {
        try { await import('../js/admin-settings-bridge.js'); } catch (_) {}
      }
    }

    console.log('[Vite] app.js entry loaded for page:', page || path);
  } catch (e) {
    console.warn('[Vite] app.js router error', e);
  }
})();

// Global delegated handler: remove-from-cart buttons
// Works for any dynamically rendered cart list using the same class and data-sku
try {
  document.addEventListener('click', (e) => {
    const btn = e.target && e.target.closest && (e.target.closest('.remove-from-cart') || e.target.closest('.cart-item-remove') || e.target.closest('[data-action="remove-from-cart"]'));
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    let sku = btn.getAttribute('data-sku')
      || (btn.dataset && btn.dataset.sku)
      || (btn.parentElement && btn.parentElement.getAttribute && btn.parentElement.getAttribute('data-sku'));
    if (!sku) {
      try {
        const itemEl = btn.closest('.cart-item');
        if (itemEl) sku = itemEl.getAttribute('data-sku');
      } catch(_) {}
    }
    if (sku && window.WF_Cart && typeof window.WF_Cart.removeItem === 'function') {
      try { window.WF_Cart.removeItem(sku); } catch(_) {}
      try { window.WF_Cart.renderCart && window.WF_Cart.renderCart(); } catch(_) {}
    }
  }, true);
} catch (_) {}
