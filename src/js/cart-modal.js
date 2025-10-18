// Global Cart Modal Overlay
// Creates a site-wide modal for the cart and wires header cart link to open it
import '../styles/cart-modal.css';
import { ApiClient } from '../core/api-client.js';
import { normalizeAssetUrl, attachStrictImageGuards, removeBrokenImage } from '../core/asset-utils.js';

function initCartModal() {
  try {
    // Avoid double init
    if (window.WF_CartModal && window.WF_CartModal.initialized) return;

    const state = {
      overlay: null,
      container: null,
      layoutHost: null,
      itemsEl: null,
      upsellWrap: null,
      upsellList: null,
      keydownHandler: null,
      lockDepth: 0,
      prevHtmlOverflow: '',
      prevBodyOverflow: '',
      resizeBound: false,
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
        <div class="bg-white rounded-lg shadow-xl w-full modal-shell">
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
          <div id="cartModalItems" class="modal-body flex-1 overflow-y-auto cart_column_layout cart_column_direction cart-scrollbar">
            <div class="p-6 text-center text-gray-500">Loading cart...</div>
            <div id="cartUpsells" class="border-t border-gray-200 hidden">
              <div class="p-4">
                <div class="cart-upsell-heading">You may also like</div>
                <div id="cartUpsellsList" class="cart-upsell-track"></div>
              </div>
            </div>
          </div>
          <div id="cartModalFooter" class="cart-modal-footer"></div>
        </div>
      `;

      overlay.appendChild(modal);
      document.body.appendChild(overlay);

      state.overlay = overlay;
      state.container = modal;
      try { state.layoutHost = modal.querySelector(':scope > div'); } catch(_) { state.layoutHost = null; }
      state.itemsEl = overlay.querySelector('#cartModalItems');
      state.upsellWrap = overlay.querySelector('#cartUpsells');
      state.upsellList = overlay.querySelector('#cartUpsellsList');

      // Let clicks inside the modal bubble so global delegated handlers (e.g., remove item) work.
      // Underlying page is already shielded by the overlay covering the viewport.

      // Close on overlay click (outside modal content)
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) close();
      });

      // Delegated item removal inside modal
      overlay.addEventListener('click', (e) => {
        const btn = e.target && e.target.closest && (e.target.closest('.remove-from-cart') || e.target.closest('.cart-item-remove'));
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
          // Update the modal immediately if open
          try { window.dispatchEvent(new Event('cartUpdated')); } catch(_) {}
        }
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
        try { maybeRenderUpsells(); } catch(_) {}
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
    // Capture phase to prevent default navigation to /cart before our handler runs
    document.addEventListener('click', (e) => {
      const btn = e.target && e.target.closest && e.target.closest('a.cart-link, a[href*="/cart"], .cart-toggle, .cart-button, [data-action="open-cart"], #cartButton, #cartLink');
      if (!btn) return;
      // Allow new tab/window behavior for anchor clicks
      if ((btn.tagName === 'A') && (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1)) return;
      e.preventDefault();
      e.stopPropagation();
      open();
    }, true);

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
          state.upsellWrap = null;
          state.upsellList = null;
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

    const UPSSELL_CACHE_TTL_MS = 60 * 1000;
    const upsellApiCache = new Map();
    let lastUpsellMetadata = null;

    function makeCacheKey(skus, limit){
      try {
        const ordered = Array.isArray(skus) ? Array.from(new Set(skus.map(s => String(s || '').toUpperCase()).filter(Boolean))).sort() : [];
        return ordered.join('|') + '::' + (limit || 0);
      } catch (_) {
        return String(limit || 0);
      }
    }

    async function fetchUpsellsFromApi(skus, limit = 6){
      try {
        const key = makeCacheKey(skus, limit);
        if (key && upsellApiCache.has(key)) {
          const cached = upsellApiCache.get(key);
          if (cached && (Date.now() - cached.ts) < UPSSELL_CACHE_TTL_MS) {
            return cached.items;
          }
        }
        const payload = await ApiClient.post('cart_upsells.php', {
          skus: Array.isArray(skus) ? skus : [],
          limit: limit > 0 ? limit : 6,
        });
        const items = (payload && payload.data && Array.isArray(payload.data.upsells)) ? payload.data.upsells : [];
        lastUpsellMetadata = (payload && payload.data && payload.data.metadata) ? payload.data.metadata : null;
        if (key) {
          upsellApiCache.set(key, { ts: Date.now(), items });
        }
        return items;
      } catch (err) {
        console.warn('[CartModal] Upsell API failed', err);
        return [];
      }
    }

    function localResolveUpsells(skus){
      try {
        const rules = (typeof window.__WF_UPSELL_RULES === 'object' && window.__WF_UPSELL_RULES) ? window.__WF_UPSELL_RULES : null;
        if (!rules) return [];
        // Schema A: { map: { SKU1:[A,B], _default:[X] }, products: { A:{sku,name,price,image}, ... } }
        // Schema B: { SKU1:[A,B], _default:[X], products:{...} }
        const map = (rules.map && typeof rules.map === 'object') ? rules.map : rules;
        const products = (rules.products && typeof rules.products === 'object') ? rules.products : {};
        const seen = new Set(skus.map(s => String(s))); // in-cart SKUs
        const recSet = new Set();
        skus.forEach(sku => {
          const list = map[String(sku)] || [];
          (Array.isArray(list) ? list : []).forEach(r => { const x = String(r); if (x && !seen.has(x)) recSet.add(x); });
        });
        const def = map._default || map.default || [];
        (Array.isArray(def) ? def : []).forEach(r => { const x = String(r); if (x && !seen.has(x)) recSet.add(x); });
        const out = [];
        recSet.forEach(sku => {
          const meta = products[sku] || {};
          out.push({
            sku,
            name: meta.name || meta.title || sku,
            price: Number(meta.price || meta.retailPrice || 0) || 0,
            image: meta.image || meta.thumbnail || ''
          });
        });
        return out;
      } catch(_) { return []; }
    }

    function metadataFallbackUpsells(){
      try {
        const meta = lastUpsellMetadata || {};
        const seen = new Set();
        const collected = [];
        const pushSku = (sku) => {
          const normalized = String(sku || '').toUpperCase();
          if (!normalized || seen.has(normalized)) return;
          seen.add(normalized);
          const match = (window.__WF_UPSELL_RULES && window.__WF_UPSELL_RULES.products && window.__WF_UPSELL_RULES.products[normalized]) || null;
          if (match) {
            collected.push({
              sku: normalized,
              name: match.name || normalized,
              price: Number(match.price || match.retailPrice || 0) || 0,
              image: match.image || match.thumbnail || ''
            });
            return;
          }
          collected.push({ sku: normalized, name: normalized, price: 0, image: '' });
        };
        if (Array.isArray(meta.category_leaders)) {
          meta.category_leaders.slice(0, 3).forEach(pushSku);
        }
        if (Array.isArray(meta.category_secondaries)) {
          meta.category_secondaries.slice(0, 2).forEach(pushSku);
        }
        if (meta.site_top) pushSku(meta.site_top);
        if (meta.site_second) pushSku(meta.site_second);
        return collected;
      } catch(_) {
        return [];
      }
    }

    function maybeRenderUpsells(){
      try {
        const show = !!window.__WF_CART_SHOW_UPSELLS;
        const strictNoFallbacks = (typeof window.__WF_STRICT_NO_FALLBACKS === 'boolean') ? window.__WF_STRICT_NO_FALLBACKS : true;
        const wrap = state.upsellWrap;
        const list = state.upsellList;
        if (!wrap || !list) return;

        const stopScrollReveal = (opts = {}) => {
          const { reset = false } = opts;
          try {
            if (wrap._revealHandler && wrap._revealHost) {
              wrap._revealHost.removeEventListener('scroll', wrap._revealHandler);
            }
          } catch(_) {}
          delete wrap._revealHandler;
          delete wrap._revealHost;
          if (reset) {
            delete wrap.dataset.revealed;
          }
        };

        const ensureAttachedToScrollHost = () => {
          try {
            const itemsHost = state.itemsEl;
            if (itemsHost && !itemsHost.contains(wrap)) {
              itemsHost.appendChild(wrap);
            }
          } catch(_) {}
        };

        const revealUpsellsNow = () => {
          ensureAttachedToScrollHost();
          wrap.classList.remove('hidden');
          wrap.dataset.revealed = '1';
          stopScrollReveal();
        };

        const setupScrollReveal = () => {
          const host = state.itemsEl;
          if (!host) {
            revealUpsellsNow();
            return;
          }
          ensureAttachedToScrollHost();
          const scrollNeeded = host.scrollHeight > host.clientHeight + 4;
          if (!scrollNeeded) {
            revealUpsellsNow();
            return;
          }
          if (wrap.dataset.revealed === '1') {
            revealUpsellsNow();
            return;
          }
          if (wrap._revealHost && wrap._revealHost !== host) {
            stopScrollReveal();
          }
          if (!wrap._revealHandler) {
            const handler = () => {
              try {
                const nearBottom = (host.scrollTop + host.clientHeight) >= (host.scrollHeight - 12);
                if (nearBottom) {
                  revealUpsellsNow();
                } else if (wrap.dataset.revealed !== '1') {
                  wrap.classList.add('hidden');
                }
              } catch(_) {}
            };
            host.addEventListener('scroll', handler, { passive: true });
            wrap._revealHandler = handler;
            wrap._revealHost = host;
          }
          if (wrap._revealHandler) {
            wrap._revealHandler();
          }
        };

        if (!show) {
          stopScrollReveal({ reset: true });
          wrap.classList.add('hidden');
          list.innerHTML='';
          return;
        }

        const items = (window.WF_Cart && typeof window.WF_Cart.getItems === 'function') ? window.WF_Cart.getItems() : [];
        const skus = Array.isArray(items) ? items.map(i => String(i.sku||'')).filter(Boolean) : [];
        const externalProvider = (typeof window.WF_getUpsells === 'function')
          ? (args) => Promise.resolve(window.WF_getUpsells(args))
          : (args) => fetchUpsellsFromApi(args, 4);

        const normalizeUpsellItems = (items) => {
          if (!Array.isArray(items)) return [];
          return items.map((r) => {
            if (!r) return null;
            const image = normalizeAssetUrl(r.image || r.thumbnail || '');
            if (!image) return null;
            return {
              sku: (r.sku || '').toString(),
              name: (r.name || r.title || r.sku || '').toString(),
              price: Number(r.price || r.retailPrice || 0) || 0,
              image
            };
          }).filter(Boolean);
        };

        const renderUpsellList = (normalized) => {
          if (!normalized.length) {
            stopScrollReveal({ reset: true });
            ensureAttachedToScrollHost();
            wrap.classList.add('hidden');
            list.innerHTML = '';
            return false;
          }
          attachStrictImageGuards(list, 'img.cart-upsell-thumb');
          list.innerHTML = normalized.map((r) => `
            <div class="cart-upsell-entry">
              <img src="${r.image}" alt="${r.name}" class="cart-upsell-thumb" loading="lazy"/>
              <div class="cart-upsell-meta">
                <div class="cart-upsell-name" title="${r.name}">${r.name}</div>
                <div class="cart-upsell-price">$${r.price.toFixed(2)}</div>
              </div>
              <button type="button" class="btn btn-xs" data-action="upsell-add" data-sku="${r.sku}" data-name="${r.name}" data-price="${r.price}">Add</button>
            </div>
          `).join('');
          try {
            list.querySelectorAll('img.cart-upsell-thumb').forEach((img) => {
              if (img.complete && (!img.naturalWidth || img.naturalWidth === 0)) {
                removeBrokenImage(img);
              } else {
                img.addEventListener('error', () => removeBrokenImage(img), { once: true });
              }
            });
          } catch(_) {}
          return true;
        };

        externalProvider(skus).then((recs) => {
          let arr = Array.isArray(recs) ? recs : [];
          if (!arr.length && !strictNoFallbacks) {
            arr = localResolveUpsells(skus);
          }
          if (!arr.length && !strictNoFallbacks) {
            arr = metadataFallbackUpsells();
          }
          const normalized = normalizeUpsellItems(arr);
          const limited = normalized.slice(0, 4);
          if (!renderUpsellList(limited)) return;
          delete wrap.dataset.revealed;
          setupScrollReveal();
        }).catch(() => {
          let fallbackArr = [];
          if (!strictNoFallbacks) {
            fallbackArr = localResolveUpsells(skus);
            if (!fallbackArr.length) {
              fallbackArr = metadataFallbackUpsells();
            }
          }
          const normalized = normalizeUpsellItems(fallbackArr);
          const limited = normalized.slice(0, 4);
          if (!renderUpsellList(limited)) return;
          delete wrap.dataset.revealed;
          setupScrollReveal();
        });
      } catch(_) {}
    }

    // Delegate upsell add
    document.addEventListener('click', (e) => {
      const btn = e.target && e.target.closest && e.target.closest('[data-action="upsell-add"]');
      if (!btn) return;
      e.preventDefault(); e.stopPropagation();
      try {
        const sku = btn.getAttribute('data-sku');
        const name = btn.getAttribute('data-name');
        const price = Number(btn.getAttribute('data-price') || '0') || 0;
        if (window.WF_Cart && typeof window.WF_Cart.addItem === 'function' && sku) {
          window.WF_Cart.addItem({ sku, name, price }, 1);
          // Refresh upsells after add
          setTimeout(() => { try { maybeRenderUpsells(); } catch(_) {} }, 60);
        }
      } catch(_) {}
    });

    console.log('[CartModal] initialized');
  } catch (err) {
    console.error('[CartModal] init error', err);
  }
}

// Defer initialization until DOM is ready so document.body exists
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    try { initCartModal(); } catch (e) { console.error('[CartModal] deferred init failed', e); }
  });
} else {
  try { initCartModal(); } catch (e) { console.error('[CartModal] init failed', e); }
}
