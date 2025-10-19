// WhimsicalFrog – Global Popup ES module
// Extracted from legacy js/global-popup.js for Vite build.
// Only the public API (show/hide) and minimal implementation retained.

import { ApiClient } from '../core/api-client.js';
const __WF_CON = (typeof window !== 'undefined' && window.console) ? window.console : null;
const console = {
  log: () => {},
  warn: () => {},
  debug: () => {},
  error: (...args) => { try { __WF_CON && __WF_CON.error && __WF_CON.error(...args); } catch(_) {} }
};

const BIND_VERSION = 'v2';

class UnifiedPopupSystem {
  constructor() {
    this.popupEl = null;
    this.hideTimer = null;
    this.isPointerOverPopup = false;
    this.resizeObserver = null;
    this._anchorViewportRect = null;
    this._badgeCache = new Map();
    this._graceUntil = 0;
    this._showing = false;
    this._mouseMoveHandler = null;
    this._anchorEl = null;
    this._lastSwitchTs = 0;
    this._hideDueAt = 0;
    this._pendingHide = false;
    this.init();
  }

  _ensureVisibilityObserver() {
    if (!this.popupEl) return;
    if (this._visObserver) return;
    try {
      const fix = () => {
        try {
          const hasPos = !!(this.popupEl && this.popupEl.dataset && this.popupEl.dataset.wfGpPosClass);
          const measuring = !!this.popupEl.classList.contains('measuring');
          const suppressed = this.popupEl.classList.contains('suppress-auto-show') || this.popupEl.dataset.wfGpSuppress === '1';
          if (hasPos && !measuring && !suppressed) {
            if (this.popupEl.classList.contains('hidden') || !this.popupEl.classList.contains('visible')) {
              this.popupEl.classList.remove('hidden', 'measuring');
              this.popupEl.classList.add('visible', 'force-visible');
              this.popupEl.setAttribute('aria-hidden', 'false');
              try { console.log('[globalPopup] vis-observer forced visible'); } catch(_) {}
            }
          }
        } catch(_) {}
      };
      const mo = new MutationObserver((muts) => {
        for (const m of muts) {
          if (m.type === 'attributes' && m.attributeName === 'class') { fix(); }
          if (m.type === 'attributes' && m.attributeName === 'data-wf-gp-pos-class') { fix(); }
        }
      });
      mo.observe(this.popupEl, { attributes: true, attributeFilter: ['class', 'data-wf-gp-pos-class'] });
      this._visObserver = mo;
      // Initial correction if already positioned
      fix();
    } catch(_) {}
  }

  // Replace popup element if fallback shim wrapped classList or attached observers
  _sanitizePopupEl() {
    if (!this.popupEl) return;
    const el = this.popupEl;
    const cl = el.classList;
    const hasWrapped = !!(cl && cl.__wfWrapped);
    const hasMo = !!el.__wfClassMo;
    if (hasWrapped || hasMo) {
      const clone = el.cloneNode(true);
      try {
        el.replaceWith(clone);
      } catch (_) {
        if (el.parentNode) el.parentNode.replaceChild(clone, el);
      }
      this.popupEl = clone;
      try { if (clone.__wfClassMo && clone.__wfClassMo.disconnect) clone.__wfClassMo.disconnect(); } catch(_){}
      try { delete clone.__wfClassMo; } catch(_){}
      try { if (clone.classList && clone.classList.__wfWrapped) delete clone.classList.__wfWrapped; } catch(_){}
      // Remove fallback style if present
      try { const s = document.getElementById('wf-fallback-globalpopup-style'); if (s && s.parentNode) s.parentNode.removeChild(s); } catch(_){}
    }

    // Install class-change observer to auto-correct visibility
    try { this._ensureVisibilityObserver(); } catch(_) {}
  }

  init() {
    if (this.popupEl) return;
    // Ensure popup can receive clicks
    const styleEl = document.createElement('style');
    styleEl.innerHTML = `
      .item-popup{pointer-events:auto !important;}
      /* Neutral background without translucent shade */
      .item-popup{background:transparent !important; box-shadow:none !important;}
      /* Ensure popup container does not stretch full width; size to its content */
      .item-popup{display:inline-block !important; width:auto !important; max-width: min(520px, 96vw) !important;}
      .item-popup .popup-content{display:block !important;}
      /* Explicit visibility toggles to beat global utility .hidden rules */
      .item-popup.hidden{opacity:0 !important; visibility:hidden !important; pointer-events:none !important;}
      .item-popup.force-visible{display:block !important; opacity:1 !important; visibility:visible !important; pointer-events:auto !important;}
      .item-popup.visible{display:block !important; opacity:1 !important; visibility:visible !important; pointer-events:auto !important;}
      /* Default hidden unless explicitly shown */
      .item-popup:not(.visible):not(.force-visible):not(.measuring){opacity:0 !important; visibility:hidden !important; pointer-events:none !important;}
      /* If positioned, force visible even if a stray .hidden remains (except during measurement) */
      .item-popup[class*="wf-gp-"]:not(.measuring){display:block !important; opacity:1 !important; visibility:visible !important; pointer-events:auto !important;}
      /* Helper: temporarily allow hit-testing through popup */
      .wf-gp-hitpass{pointer-events:none !important;}
      /* Topmost helper class driven by tokens with a hard fallback */
      #itemPopup.wf-gp-topmost{z-index: var(--z-global-popup, var(--z-index-global-popup, 1250)) !important; position:fixed !important; pointer-events:auto !important;}
      /* Safety: when any modal is open, ensure popup layers above overlays even if in-room-modal flag misses */
      body.modal-open #itemPopup{z-index: var(--z-global-popup, var(--z-index-global-popup, 1250)) !important; position:fixed !important; pointer-events:auto !important;}
      /* When flagged as in-room-modal, also ensure very high z-index */
      #itemPopup.in-room-modal{z-index: var(--z-global-popup, var(--z-index-global-popup, 1250)) !important;}
    `;
    document.head.appendChild(styleEl);
    if (this.popupEl) return;
    // Remove any duplicate popups from previous renders
    document.querySelectorAll('.item-popup').forEach(el => {
      if (el.id !== 'itemPopup') el.remove();
    });
    // Locate existing popup markup from PHP-rendered template (prefer new ID)
    this.popupEl = document.getElementById('wfItemPopup') || document.getElementById('itemPopup');
    if (!this.popupEl) {
      // Popup not yet in DOM – wait for template insertion
      const tryBind = () => {
        this.popupEl = document.getElementById('wfItemPopup') || document.getElementById('itemPopup');
        if (this.popupEl) {
          try { this._sanitizePopupEl(); } catch(_) {}
          this.bindPopupInteractions();
          // If a show was pending, re-invoke now that the element exists
          try {
            if (this._pendingShowArgs) {
              const { anchorEl, item } = this._pendingShowArgs;
              this._pendingShowArgs = null;
              this._showRetryCount = 0;
              this.show(anchorEl, item);
            }
          } catch(_) {}
          return true;
        }
        return false;
      };
      // Bind for DOMContentLoaded (in case we're imported early)
      document.addEventListener('DOMContentLoaded', tryBind, { once: true });
      // If DOM is already parsed, attempt immediately and also observe for late insertion
      if (document.readyState === 'interactive' || document.readyState === 'complete') {
        // Immediate attempt
        if (!tryBind()) {
          this._installPopupObserver && this._installPopupObserver();
        }
      } else {
        // As a fallback, also listen to window load
        window.addEventListener('load', tryBind, { once: true });
      }
      return; // No popup element right now
    }

    // Popup element found – sanitize if shim artifacts are present, then bind
    try { this._sanitizePopupEl(); } catch (_) {}
    this.bindPopupInteractions();

    
  }

  // Bind click handler and other one-time listeners on the popup element
  bindPopupInteractions() {
    if (!this.popupEl) return;
    // Rebind if version changed (supports hot-reloads and code updates)
    if (this.popupEl.dataset.interactionsBound !== BIND_VERSION) {
      this.popupEl.dataset.interactionsBound = BIND_VERSION;
    } else {
      return;
    }
    // Ensure popup is at the end of <body> so it layers above late-inserted overlays
    try {
      if (this.popupEl.parentNode !== document.body || document.body.lastElementChild !== this.popupEl) {
        document.body.appendChild(this.popupEl);
      }
    } catch(_) {}

    // Hover persistence on the popup itself
    try {
      this.popupEl.addEventListener('mouseenter', () => {
        try { this.cancelHide(); } catch(_) {}
        this.isPointerOverPopup = true;
      });
      this.popupEl.addEventListener('mouseleave', () => {
        this.isPointerOverPopup = false;
        try { this.scheduleHide(500); } catch(_) {}
      });
    } catch(_) {}

    // Click anywhere inside popup opens item modal (even if out of stock)
    this.popupEl.addEventListener('click', async (e) => {
      // Prevent any default anchor behavior and stop the event from reaching
      // underlying room links/areas (which could navigate to under_construction)
      try { e.preventDefault(); e.stopPropagation(); } catch(_) {}
      // Prevent aria-hidden focus warning by removing focus before hiding
      try { if (document.activeElement && typeof document.activeElement.blur === 'function') document.activeElement.blur(); } catch (_) {}
      // Allow opening even if out of stock; Add to Cart remains disabled in detailed modal
      if (typeof window.hideGlobalPopupImmediate === 'function') {
        window.hideGlobalPopupImmediate();
      }
      const sku = this.currentItem?.sku;
      console.log('[globalPopup] Popup clicked! SKU:', sku, 'Current item:', this.currentItem);
      try {
        if (typeof window.showGlobalItemModal !== 'function') {
          await import('../js/detailed-item-modal.js');
        }
      } catch (e2) {
        console.warn('[globalPopup] failed to lazy-load item modal', e2);
      }
      if (typeof window.showGlobalItemModal === 'function' && sku) {
        window.showGlobalItemModal(sku, this.currentItem);
      } else if (window.WhimsicalFrog?.GlobalModal?.show && sku) {
        window.WhimsicalFrog.GlobalModal.show(sku, this.currentItem);
      } else {
        console.warn('[globalPopup] No modal function available to open detailed modal');
      }
      this.isPointerOverPopup = true;
      this.cancelHide();
    });
    this.popupEl.addEventListener('mouseenter', () => {
      this.isPointerOverPopup = true;
      this.cancelHide();
    });
    this.popupEl.addEventListener('mouseleave', () => {
      this.isPointerOverPopup = false;
      this.scheduleHide(500);
    });

    // Explicit: Add to Cart button triggers detailed item modal
    const addBtn = this.popupEl.querySelector('#popupAddBtn');
    if (addBtn) {
      addBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        // Prevent aria-hidden focus warning and close popup before opening modal
        try { if (document.activeElement && typeof document.activeElement.blur === 'function') document.activeElement.blur(); } catch (_) {}
        if (typeof window.hideGlobalPopupImmediate === 'function') {
          window.hideGlobalPopupImmediate();
        }
        const sku = this.currentItem?.sku;
        try {
          if (typeof window.showGlobalItemModal !== 'function') {
            await import('../js/detailed-item-modal.js');
          }
        } catch (e2) {
          console.warn('[globalPopup] failed to lazy-load item modal (Add)', e2);
        }
        if (typeof window.showGlobalItemModal === 'function' && sku) {
          console.log('[globalPopup] opening detailed modal from Add button for', sku);
          window.showGlobalItemModal(sku, this.currentItem);
        } else if (window.WhimsicalFrog?.GlobalModal?.show && sku) {
          console.log('[globalPopup] opening detailed modal via WF.GlobalModal (Add) for', sku);
          window.WhimsicalFrog.GlobalModal.show(sku, this.currentItem);
        }
      });
    }

    // Explicit: clicking the image also opens detailed item modal
    const img = this.popupEl.querySelector('#popupImage');
    if (img) {
      try { img.classList.add('cursor-pointer'); } catch (_) {}
      img.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        // Allow opening even if OOS; detailed modal will reflect OOS state
        // Prevent aria-hidden focus warning and close popup before opening modal
        try { if (document.activeElement && typeof document.activeElement.blur === 'function') document.activeElement.blur(); } catch (_) {}
        if (typeof window.hideGlobalPopupImmediate === 'function') {
          window.hideGlobalPopupImmediate();
        }
        const sku = this.currentItem?.sku;
        if (typeof window.showGlobalItemModal === 'function' && sku) {
          console.log('[globalPopup] opening detailed modal from image click for', sku);
          window.showGlobalItemModal(sku, this.currentItem);
        } else if (window.WhimsicalFrog?.GlobalModal?.show && sku) {
          console.log('[globalPopup] opening detailed modal via WF.GlobalModal (image) for', sku);
          window.WhimsicalFrog.GlobalModal.show(sku, this.currentItem);
        }
      });
    }
  }

  // Runtime helpers: class-based positioning (no inline styles)
  static _POS = { styleEl: null, rules: new Set() };
  static _ensureStyleEl() {
    if (!UnifiedPopupSystem._POS.styleEl) {
      const el = document.createElement('style');
      el.id = 'wf-globalpopup-styles';
      document.head.appendChild(el);
      UnifiedPopupSystem._POS.styleEl = el;
    }
    return UnifiedPopupSystem._POS.styleEl;
  }
  static _className(t, l) {
    return `wf-gp-t${Math.round(t)}-l${Math.round(l)}`;
  }
  static _ensureRule(cls, t, l) {
    if (UnifiedPopupSystem._POS.rules.has(cls)) return;
    const css = `.item-popup.${cls}{position:fixed;top:${Math.round(t)}px !important;left:${Math.round(l)}px !important;z-index:var(--z-global-popup, var(--z-index-global-popup, 1250)) !important;}`;
    UnifiedPopupSystem._ensureStyleEl().appendChild(document.createTextNode(css));
    UnifiedPopupSystem._POS.rules.add(cls);
  }
  _applyPosition(top, left) {
    if (!this.popupEl) return;
    const prev = this.popupEl.dataset.wfGpPosClass;
    if (prev) this.popupEl.classList.remove(prev);
    const cls = UnifiedPopupSystem._className(top, left);
    UnifiedPopupSystem._ensureRule(cls, top, left);
    this.popupEl.classList.add(cls);
    this.popupEl.dataset.wfGpPosClass = cls;
    // Mark as positioned to neutralize legacy absolute rules if present
    try { this.popupEl.classList.add('positioned'); } catch (_) {}
  }

  show(anchorEl, item) {
    try { this._sanitizePopupEl(); } catch (_) {}
    // Cancel any pending hide when showing
    this.cancelHide();
    // Prevent redundant show calls for the same anchor – avoids flashing
    if (this.popupEl && (this._showing || this.popupEl.classList.contains('visible')) && anchorEl === this._lastAnchor) {
      return; // already showing for this element
    }
    this._lastAnchor = anchorEl;
    this._anchorEl = anchorEl;
    this._showing = true;
    // Clear any previous suppression from a prior hide
    try {
      if (this.popupEl) {
        this.popupEl.classList.remove('suppress-auto-show');
        try { delete this.popupEl.dataset.wfGpSuppress; } catch(_) {}
      }
    } catch(_) {}
    // Reset pointer state so we can transition between items cleanly
    this.isPointerOverPopup = false;
    // Establish grace period early to avoid immediate hides during initial movement
    try { this._graceUntil = Date.now() + 600; } catch(_) {}
    console.log('[globalPopup] show called with:', anchorEl, item);
    this.init();
    try { this._ensureVisibilityObserver(); } catch(_) {}
    try {
      if (!window.__wfDidPrefetchItemModal) {
        window.__wfDidPrefetchItemModal = true;
        import('../js/detailed-item-modal.js').catch(() => {});
      }
    } catch(_) {}
    if (!this.popupEl) {
      // Try one more immediate lookup (in case footer just appended it)
      this.popupEl = document.getElementById('wfItemPopup') || document.getElementById('itemPopup');
    }
    if (!this.popupEl) {
      // Install an observer and schedule a short retry to avoid losing the hover
      try { this._installPopupObserver(); } catch(_) {}
      this._pendingShowArgs = { anchorEl, item };
      this._showRetryCount = (this._showRetryCount || 0);
      if (this._showRetryCount < 10) {
        this._showRetryCount++;
        setTimeout(() => {
          try { this.show(anchorEl, item); } catch(_) {}
        }, 50);
      } else {
        console.warn('[globalPopup] popup element unavailable after retries');
      }
      return;
    }
    if (!anchorEl || !item) {
      console.warn('[globalPopup] missing anchor or item data:', { anchorEl: !!anchorEl, item: !!item });
      return;
    }
    // Clear any stale OOS state from previous item before computing new stock
    try { this.popupEl.classList.remove('oos'); } catch(_) {}
    // Save current item for click handling
    this.currentItem = item;
    // Basic content update
    const setText = (selector, text) => {
      const el = this.popupEl.querySelector(selector);
      if (el) el.textContent = text ?? '';
    };
    setText('#popupTitle', item.name || item.productName || item.sku);
    setText('#popupDescription', item.description || '');

    // Stock badge and disabled state
    const stockBadge = this.popupEl.querySelector('#popupStockBadge');
    const stockText = this.popupEl.querySelector('#popupStockText');
    const stockInfo = this.popupEl.querySelector('#popupStock');
    const saleBadge = this.popupEl.querySelector('#popupSaleBadge');
    // Compute stock robustly: prefer dataset values, then item fields
    const ds = (anchorEl && anchorEl.dataset) || {};
    const candidates = [ds.stockLevel, ds.stock, item.stockLevel, item.stock];
    let stock = 0;
    for (const c of candidates) {
      const v = (typeof c === 'string') ? parseInt(c, 10) : Number(c);
      if (!Number.isNaN(v) && v >= 0) { stock = v; break; }
    }
    if (stockInfo) {
      stockInfo.textContent = stock > 0 ? `${stock} in stock` : 'Out of stock';
    }
    // Ensure the item we pass to the detailed modal carries effective stock like the shop card
    try {
      if (this.currentItem) {
        if (typeof this.currentItem.stock === 'undefined' || this.currentItem.stock == null) this.currentItem.stock = stock;
        if (typeof this.currentItem.stockLevel === 'undefined' || this.currentItem.stockLevel == null) this.currentItem.stockLevel = stock;
      }
    } catch (_) {}
    // Configure popup badges for OOS vs in-stock
    if (stock <= 0) {
      // Mark popup state for CSS overrides
      try { this.popupEl.classList.add('oos'); } catch(_) {}
      if (saleBadge) { try { saleBadge.classList.add('hidden'); } catch(_) {} }
      // Do not remove stock badge; just hide to allow re-use on next item
      try { if (stockBadge) stockBadge.classList.add('hidden'); } catch(_) {}
      // Also purge any dynamically rendered badges
      try { const badgeContainer = this.popupEl.querySelector('#popupBadgeContainer'); if (badgeContainer) badgeContainer.innerHTML = ''; } catch(_) {}
      if (stockText) stockText.textContent = 'OUT OF STOCK';
      // Hide the small STOCK pill via class
      try { const pill = this.popupEl.querySelector('#popupStockBadge .stock-badge'); if (pill) pill.classList.add('hidden'); } catch(_) {}
    } else {
      try { this.popupEl.classList.remove('oos'); } catch(_) {}
      // Restore default stock badge behavior (limited stock only shows for low stock)
      if (saleBadge) { try { saleBadge.classList.remove('hidden'); } catch(_) {} }
      if (stockBadge) { stockBadge.classList.toggle('hidden', stock > 3); }
      if (stockBadge) stockBadge.classList.remove('is-oos');
      if (stockText && stockText.textContent !== 'LIMITED STOCK') stockText.textContent = 'LIMITED STOCK';
      // Show pill again when in stock
      try { const pill = this.popupEl.querySelector('#popupStockBadge .stock-badge'); if (pill) pill.classList.remove('hidden'); } catch(_) {}
    }
    // Mark Add button disabled when OOS
    const addBtn = this.popupEl.querySelector('#popupAddBtn');
    if (addBtn) {
      const disabled = stock <= 0;
      addBtn.classList.toggle('is-disabled', disabled);
      if (disabled) addBtn.setAttribute('aria-disabled', 'true'); else addBtn.removeAttribute('aria-disabled');
    }

    // Price
    const currentPriceEl = this.popupEl.querySelector('#popupCurrentPrice');
    const originalPriceEl = this.popupEl.querySelector('#popupOriginalPrice');
    const savingsEl = this.popupEl.querySelector('#popupSavings');
    if (currentPriceEl) currentPriceEl.textContent = `$${Number(item.price ?? item.currentPrice ?? 0).toFixed(2)}`;

    if (originalPriceEl && savingsEl) {
      const original = Number(item.originalPrice ?? item.retailPrice ?? item.price ?? 0);
      const current = Number(item.price ?? item.currentPrice ?? original);
      const onSale = original > current;
      originalPriceEl.textContent = `$${original.toFixed(2)}`;
      savingsEl.textContent = `Save $${(original - current).toFixed(2)}`;
      originalPriceEl.classList.toggle('hidden', !onSale);
      savingsEl.classList.toggle('hidden', !onSale);
      const saleBadge = this.popupEl.querySelector('#popupSaleBadge');
      if (saleBadge) saleBadge.classList.toggle('hidden', !onSale);
    }

    // Image
    const imgEl = this.popupEl.querySelector('#popupImage');
    if (imgEl && item.image) imgEl.src = item.image;

    // Badges overlay (async fetch; render when ready)
    this._clearPopupBadges();
    const sku = item?.sku;
    // Do NOT render badges when OOS; we show a single centered overlay instead
    if (sku && stock > 0) this._loadAndRenderBadges(sku);

    // Determine if anchor is inside room modal (or its iframe) to adjust layering
    let inRoomModal = false;
    try {
      if (anchorEl.ownerDocument === document) {
        inRoomModal = !!anchorEl.closest('.room-modal-overlay');
      } else {
        const iframe = anchorEl.ownerDocument?.defaultView?.frameElement;
        if (iframe) {
          inRoomModal = !!iframe.closest('.room-modal-overlay');
        }
      }
    } catch (e) {
      // noop
    }
    if (this.popupEl) {
      this.popupEl.classList.toggle('in-room-modal', !!inRoomModal);
    }

    // Position: below anchor (viewport coordinates for fixed positioning)
    const rect = anchorEl.getBoundingClientRect();
    let top = rect.bottom + 6; // viewport-relative (no scroll offset for fixed)
    let left = rect.left;      // viewport-relative (no scroll offset for fixed)
    // Anchor rect relative to the top-window viewport (account for iframe)
    let anchorViewportRect = { top: rect.top, bottom: rect.bottom, left: rect.left, right: rect.right };
    // If anchor is inside an iframe, adjust coordinates by iframe offset
    if (anchorEl.ownerDocument !== document) {
      try {
        const iframe = anchorEl.ownerDocument.defaultView.frameElement;
        if (iframe) {
          const iframeRect = iframe.getBoundingClientRect();
          // Adjust to top-window viewport by adding iframe's viewport offset
          top = iframeRect.top + rect.bottom + 6; // viewport-relative
          left = iframeRect.left + rect.left;      // viewport-relative
          // Build anchor viewport rect relative to the top window
          anchorViewportRect = {
            top: iframeRect.top + rect.top,
            bottom: iframeRect.top + rect.bottom,
            left: iframeRect.left + rect.left,
            right: iframeRect.left + rect.right,
          };
          console.log('[globalPopup] adjusted for iframe:', { top, left });
        }
      } catch (err) {
        console.warn('[globalPopup] failed iframe adjustment', err);
      }
    }
    // Pre-clamp horizontally to reduce initial off-screen flashes before measurement
    try {
      const margin = 8;
      const maxLeft = Math.max(margin, Math.min(left, window.innerWidth - 340));
      left = maxLeft;
    } catch (_) {}
    this._applyPosition(top, left);
    // Persist for reclamping on dynamic size changes
    this._anchorViewportRect = anchorViewportRect;

    // Ensure popup is the last child of body so equal z-indices resolve in our favor
    try {
      if (this.popupEl.parentNode !== document.body || document.body.lastElementChild !== this.popupEl) {
        document.body.appendChild(this.popupEl);
      }
      // Force topmost via class instead of inline styles (respects lints and theming)
      this.popupEl.classList.add('wf-gp-topmost');
    } catch(_) {}

    // Show invisibly to measure size then clamp
    // Add measuring first, then remove hidden to avoid any chance of a paint between operations
    this.popupEl.classList.add('measuring');
    this.popupEl.classList.remove('hidden');
    // Safety: force visible early; rAF will apply final 'visible' after clamping
    this.popupEl.classList.add('force-visible');

    requestAnimationFrame(() => {
      try {
        try {
          this._clampAndApply(this._anchorViewportRect);
        } catch (e) {
          console.warn('[globalPopup] clamp error', e);
        }

        // Reveal popup now that it is positioned
        try {
          this.popupEl.classList.remove('measuring');
          this.popupEl.classList.add('visible');
          this.popupEl.classList.remove('force-visible');
          this.popupEl.setAttribute('aria-hidden', 'false');
          console.log('[globalPopup] popup shown');
        } catch (e) {
          console.warn('[globalPopup] reveal error', e);
        }

        // Grace period to allow pointer to travel from icon/iframe to popup without hiding
        this._graceUntil = Date.now() + 350;
        this._showing = false;

        // Observe size changes (e.g., image loads) and re-clamp to keep fully visible
        try { this._ensureResizeObserver(); } catch (_) {}
        // Install lightweight mousemove watcher to hide when leaving both icon and popup
        try { this._installMouseMoveWatcher(); } catch (_) {}
      } catch (err) {
        console.warn('[globalPopup] rAF error', err);
        // Best-effort reveal even if something failed
        try {
          this.popupEl.classList.remove('measuring');
          this.popupEl.classList.remove('hidden');
          this.popupEl.classList.add('visible');
          this.popupEl.setAttribute('aria-hidden', 'false');
        } catch (_) {}
      }
    });

    // Backup: if something prevented visibility, force it shortly after
    setTimeout(() => {
      try {
        if (this.popupEl && !this.popupEl.classList.contains('visible')) {
          console.warn('[globalPopup] applying fallback visible');
          this.popupEl.classList.remove('measuring');
          this.popupEl.classList.remove('hidden');
          this.popupEl.classList.add('visible');
          this.popupEl.setAttribute('aria-hidden', 'false');
        }
      } catch (_) {}
    }, 120);
    console.log('[globalPopup] positioned popup at:', { top, left });

    // Hide any other stale popups
    document.querySelectorAll('.item-popup.visible').forEach(el => {
      if (el !== this.popupEl) el.classList.add('hidden');
    });
    this.currentItem = item;

    // Visibility already toggled in rAF callback above to avoid shimmer.
  }

  _clearPopupBadges() {
    try {
      const container = this.popupEl?.querySelector('#popupBadgeContainer');
      if (container) container.innerHTML = '';
    } catch (_) {}
  }

  async _loadAndRenderBadges(sku) {
    try {
      if (!sku) return;
      if (!this._badgeCache.has(sku)) {
        const data = await ApiClient.get(`/api/get_badge_scores.php?sku=${encodeURIComponent(sku)}`);
        if (data && data.success && Array.isArray(data.badges)) {
          this._badgeCache.set(sku, data.badges);
        } else {
          this._badgeCache.set(sku, []);
        }
      }
      this._renderBadgesFromCache(sku);
    } catch (err) {
      console.warn('[globalPopup] badge fetch failed', err);
    }
  }

  _renderBadgesFromCache(sku) {
    const container = this.popupEl?.querySelector('#popupBadgeContainer');
    if (!container) return;
    container.innerHTML = '';
    const badges = this._badgeCache.get(sku) || [];
    badges.forEach(b => {
      const el = document.createElement('div');
      el.className = `popup-badge ${this._positionClass(b.position_name)}`.trim();
      el.textContent = b.content || '';
      container.appendChild(el);
    });
  }

  _positionClass(name) {
    switch (name) {
      case 'top-left': return 'pos-top-left';
      case 'top-right': return 'pos-top-right';
      case 'bottom-left': return 'pos-bottom-left';
      case 'bottom-right': return 'pos-bottom-right';
      default: return 'pos-top-left';
    }
  }

  // Debounced hide: schedule with a short delay to allow pointer to reach the popup
  scheduleHide(delay = 500) {
    const now = Date.now();
    const remainingGrace = this._graceUntil > now ? (this._graceUntil - now) : 0;
    const finalDelay = Math.max(delay, remainingGrace + 50);
    // Throttle correctly:
    // If a hide is already scheduled sooner than or equal to the requested finalDelay, keep it.
    // Only reschedule if the requested finalDelay would hide earlier than the existing one.
    if (this.hideTimer && this._hideDueAt) {
      const remaining = Math.max(0, this._hideDueAt - now);
      if (remaining <= finalDelay) {
        try { console.log('[globalPopup] keep existing hide timer; remaining <= requested', { remaining, requested: finalDelay }); } catch(_) {}
        return;
      }
      // Else: new request is earlier; cancel and reschedule
      try { console.log('[globalPopup] reschedule earlier hide', { fromMs: remaining, toMs: finalDelay }); } catch(_) {}
      clearTimeout(this.hideTimer);
      this.hideTimer = null;
    }
    this._pendingHide = true;
    this.hideTimer = setTimeout(() => {
      this.hideImmediate();
    }, finalDelay);
    this._hideDueAt = now + finalDelay;
    try {
      const stack = (new Error()).stack || '';
      const snippet = stack.split('\n').slice(2, 6).map(s => s.trim()).join(' | ');
      console.log('[globalPopup] hide scheduled', { requestedMs: delay, finalMs: finalDelay, remainingGraceMs: remainingGrace, from: snippet });
    } catch (_) {
      console.log('[globalPopup] hide scheduled in', delay, 'ms');
    }
  }

  cancelHide() {
    if (this.hideTimer) {
      clearTimeout(this.hideTimer);
      this.hideTimer = null;
    }
    this._hideDueAt = 0;
    this._pendingHide = false;
    try { this.popupEl && this.popupEl.classList.remove('wf-gp-hitpass'); } catch(_) {}
  }

  hideImmediate(force = false) {
    try { this._sanitizePopupEl(); } catch (_) {}
    console.log('[globalPopup] hide immediate');
    // Respect grace period and active show to avoid flicker during initial hover
    if (!force) {
      try {
        const now = Date.now();
        if (this._showing || (this._graceUntil && now < this._graceUntil)) {
          const deferMs = Math.max(200, (this._graceUntil || now) - now + 50);
          try { console.log('[globalPopup] defer hideImmediate due to grace/show', { deferMs }); } catch(_) {}
          return this.scheduleHide(deferMs);
        }
      } catch(_) {}
    }
    if (this.popupEl) {
      // Suppress auto-resurrection and remove positioning
      try { this.popupEl.classList.add('suppress-auto-show'); } catch(_) {}
      try { this.popupEl.dataset.wfGpSuppress = '1'; } catch(_) {}
      try {
        const prev = this.popupEl.dataset.wfGpPosClass;
        if (prev) this.popupEl.classList.remove(prev);
        delete this.popupEl.dataset.wfGpPosClass;
      } catch(_) {}
      if (this._visObserver && this._visObserver.disconnect) {
        try { this._visObserver.disconnect(); } catch(_) {}
        this._visObserver = null;
      }
      try { this.popupEl.classList.remove('wf-gp-hitpass'); } catch(_) {}
      try { this.popupEl.classList.remove('wf-gp-topmost'); } catch(_) {}
      this.popupEl.classList.remove('visible');
      this.popupEl.classList.add('hidden');
      this.popupEl.classList.remove('in-room-modal');
      this.popupEl.setAttribute('aria-hidden', 'true');
      console.log('[globalPopup] popup hidden');
    } else {
      console.warn('[globalPopup] no popup element to hide');
    }
    // Cleanup observers/state
    this._teardownMouseMoveWatcher();
    if (this.resizeObserver) {
      try { this.resizeObserver.disconnect(); } catch (_) {}
    }
    this.resizeObserver = null;
    this._anchorViewportRect = null;
    this._showing = false;
    // Allow re-show on same icon: clear last anchor refs
    this._lastAnchor = null;
    this._anchorEl = null;
    try { window.__wfCurrentPopupAnchor = null; } catch(_) {}
    // Document-level mousemove watcher usage disabled to align with backup behavior
  }

  _clampAndApply(anchorViewportRect) {
    if (!this.popupEl || !anchorViewportRect) return;
    const r = this.popupEl.getBoundingClientRect();
    const margin = 8;
    const gap = 6;

    // Try side placement first (right/left) to avoid covering nearby icons
    const spaceRight = window.innerWidth - anchorViewportRect.right - margin;
    const spaceLeft = anchorViewportRect.left - margin;
    const canRight = spaceRight >= r.width;
    const canLeft = spaceLeft >= r.width;
    let targetTopV, targetLeftV;

    if (canRight || canLeft) {
      // Align vertically to top of anchor, with clamping
      targetTopV = Math.max(margin, Math.min(anchorViewportRect.top, window.innerHeight - r.height - margin));
      if (canRight) {
        targetLeftV = Math.min(window.innerWidth - r.width - margin, anchorViewportRect.right + gap);
      } else {
        targetLeftV = Math.max(margin, anchorViewportRect.left - r.width - gap);
      }
    } else {
      // Fall back to vertical placement (below/above) with horizontal clamp
      const spaceBelow = window.innerHeight - anchorViewportRect.bottom - margin;
      const spaceAbove = anchorViewportRect.top - margin;
      if (spaceBelow >= r.height) {
        targetTopV = anchorViewportRect.bottom + gap;
      } else if (spaceAbove >= r.height) {
        targetTopV = anchorViewportRect.top - r.height - gap;
      } else {
        const preferBelow = spaceBelow >= spaceAbove;
        if (preferBelow) {
          targetTopV = Math.max(margin, Math.min(anchorViewportRect.bottom + gap, window.innerHeight - r.height - margin));
        } else {
          targetTopV = Math.max(margin, Math.min(anchorViewportRect.top - r.height - gap, window.innerHeight - r.height - margin));
        }
      }
      targetLeftV = Math.min(Math.max(anchorViewportRect.left, margin), window.innerWidth - r.width - margin);
    }

    // Final clamps
    targetLeftV = Math.min(Math.max(targetLeftV, margin), window.innerWidth - r.width - margin);
    targetTopV = Math.min(Math.max(targetTopV, margin), window.innerHeight - r.height - margin);

    const fixedLeft = Math.round(targetLeftV);
    const fixedTop = Math.round(targetTopV);
    this._applyPosition(fixedTop, fixedLeft);
    try {
      console.debug('[globalPopup] clamp', { anchor: anchorViewportRect, popupW: r.width, popupH: r.height, placed: { top: fixedTop, left: fixedLeft }, mode: (canRight||canLeft) ? 'side' : 'vertical' });
    } catch (_) {}
  }

  _installMouseMoveWatcher() {
    if (this._mouseMoveHandler) return;
    console.debug('[globalPopup] mousemove watcher installed');
    this._mouseMoveHandler = (e) => {
      try {
        const popup = this.popupEl;
        if (!popup) return;
        const pr = popup.getBoundingClientRect();
        const ax = this._anchorEl;
        const ar = ax ? ax.getBoundingClientRect() : null;
        const x = e.clientX, y = e.clientY;
        const pad = 10; // expanded forgiveness to avoid blocking icon transitions
        const inPopup = (x >= pr.left - pad && x <= pr.right + pad && y >= pr.top - pad && y <= pr.bottom + pad);
        const inAnchor = ar ? (x >= ar.left - pad && x <= ar.right + pad && y >= ar.top - pad && y <= ar.bottom + pad) : false;

        // Always probe for an underlying icon (bypass popup hitbox) to allow seamless switching
        const icon = this._findUnderlyingIconAt(x, y);
        if (icon && icon !== this._lastAnchor) {
          try {
            const prevSku = this.currentItem?.sku;
            const nextSku = icon.dataset?.sku;
            console.debug('[globalPopup] switching anchor', { from: prevSku, to: nextSku });
          } catch (_) {}
          const data = this._extractItemFromIcon(icon);
          if (data) {
            // Immediately switch popup to new icon
            this.show(icon, data);
            return;
          }
        }

        if (inPopup || inAnchor) {
          this.isPointerOverPopup = true;
          this.cancelHide();
        } else {
          this.isPointerOverPopup = false;
          // Best practice: schedule hide once on first outside detection; do not extend timer while moving outside
          if (!this.hideTimer) {
            try { console.log('[globalPopup] outside detected -> scheduleHide'); } catch(_) {}
            this.scheduleHide(500);
          }
        }
      } catch (_) {}
    };
    document.addEventListener('mousemove', this._mouseMoveHandler, { passive: true });
  }

  _teardownMouseMoveWatcher() {
    if (this._mouseMoveHandler) {
      try { document.removeEventListener('mousemove', this._mouseMoveHandler); } catch (_) {}
      this._mouseMoveHandler = null;
      try { console.debug('[globalPopup] mousemove watcher removed'); } catch(_) {}
    }
  }

  _findUnderlyingIconAt(x, y) {
    const popup = this.popupEl;
    if (!popup) return null;
    let underlying = null;
    try {
      popup.classList.add('wf-gp-hitpass');
      underlying = document.elementFromPoint(x, y);
    } catch (_) { underlying = null; }
    finally {
      try { popup.classList.remove('wf-gp-hitpass'); } catch(_) {}
    }
    // Direct hit in top document
    const iconTop = underlying && (underlying.closest ? underlying.closest('.item-icon, .room-product-icon') : null);
    if (iconTop) return iconTop;
    // If an iframe is under the point, try to resolve inside it (same-origin only)
    const ifr = (underlying && underlying.tagName === 'IFRAME') ? underlying : null;
    if (ifr) {
      try {
        const rect = ifr.getBoundingClientRect();
        const ix = x - rect.left;
        const iy = y - rect.top;
        const win = ifr.contentWindow;
        const doc = win && win.document;
        if (doc && win.location && win.location.origin === window.location.origin) {
          const innerEl = doc.elementFromPoint(ix, iy);
          const iconInner = innerEl && (innerEl.closest ? innerEl.closest('.item-icon, .room-product-icon') : null);
          if (iconInner) return iconInner;
        }
      } catch (_) { /* cross-origin or denied */ }
    }
    // Also scan any same-origin iframe whose rect contains the point (in case non-iframe element was returned)
    const iframes = Array.from(document.querySelectorAll('iframe'));
    for (const f of iframes) {
      try {
        const r = f.getBoundingClientRect();
        if (x >= r.left && x <= r.right && y >= r.top && y <= r.bottom) {
          const ix = x - r.left;
          const iy = y - r.top;
          const win = f.contentWindow;
          const doc = win && win.document;
          if (doc && win.location && win.location.origin === window.location.origin) {
            const innerEl = doc.elementFromPoint(ix, iy);
            const iconInner = innerEl && (innerEl.closest ? innerEl.closest('.item-icon, .room-product-icon') : null);
            if (iconInner) return iconInner;
          }
        }
      } catch (_) { /* ignore */ }
    }
    return null;
  }

  _extractItemFromIcon(icon) {
    if (!icon || !icon.dataset) return null;
    try {
      if (icon.dataset.product) {
        try { return JSON.parse(icon.dataset.product); } catch (_) {}
      }
      if (icon.dataset.sku) {
        const stockVal = parseInt(icon.dataset.stock || icon.dataset.stockLevel || '0', 10) || 0;
        return {
          sku: icon.dataset.sku,
          name: icon.dataset.name || icon.dataset.productName || icon.dataset.title || '',
          price: parseFloat(icon.dataset.price || icon.dataset.cost || icon.dataset.currentPrice || '0') || 0,
          description: icon.dataset.description || '',
          stock: stockVal,
          stockLevel: stockVal,
          category: icon.dataset.category || '',
          image: icon.dataset.image || ''
        };
      }
      const attr = icon.getAttribute && icon.getAttribute('onmouseenter');
      if (attr) {
        const match = attr.match(/showGlobalPopup\(this,\s*(.+)\)/);
        if (match) {
          try {
            const jsonString = match[1].replace(/&quot;/g, '"').replace(/&#039;/g, "'");
            return JSON.parse(jsonString);
          } catch (_) {}
        }
      }
    } catch (_) {}
    return null;
  }

  _ensureResizeObserver() {
    if (this.resizeObserver || !window.ResizeObserver) return;
    this.resizeObserver = new ResizeObserver(() => {
      // Re-clamp to viewport if size changed (e.g., image loaded)
      this._clampAndApply(this._anchorViewportRect);
    });
    try { this.resizeObserver.observe(this.popupEl); } catch (_) {}
  }
}

const unifiedPopup = new UnifiedPopupSystem();

// Back-compat globals
window.showGlobalPopup = (el, data) => unifiedPopup.show(el, data);

// Utility to purge duplicate popups if any stray ones remain
(function removeDuplicatePopups() {
  const popups = document.querySelectorAll('.item-popup');
  popups.forEach(el => {
    if (el.id !== 'itemPopup') el.remove();
  });
})();
// Export a debounced hide by default to avoid flicker from rapid mouse transitions
window.hideGlobalPopup = () => unifiedPopup.scheduleHide();
// Provide an immediate hide for explicit cases (e.g., modal close, route change)
window.hideGlobalPopupImmediate = () => unifiedPopup.hideImmediate(true);
// Explicit schedule/cancel APIs for delegated handlers
window.scheduleHideGlobalPopup = (delay) => unifiedPopup.scheduleHide(delay);
window.cancelHideGlobalPopup = () => unifiedPopup.cancelHide();

// Signal that the modern popup system is present so any server-injected fallback shim can no-op
try {
  window.__WF_MODERN_POPUP = BIND_VERSION || 'modern';
  // Expose the instance for diagnostics/interop
  window.unifiedPopup = unifiedPopup;
  // Notify any listeners that modern popup is ready
  window.dispatchEvent(new CustomEvent('wf:modern-popup-ready'));
} catch (_) {}

// Cleanup any fallback-shim artifacts and harden APIs against interference
(function hardenAgainstFallbackShim(){
  try {
    // Mark shim disabled globally
    window.__WF_POPUP_SHIM_DISABLED = true;
    // Attempt to restore classList methods if the shim wrapped them
    const popup = document.getElementById('itemPopup') || document.querySelector('.item-popup');
    if (popup && popup.classList) {
      const cl = popup.classList;
      if (cl.__wfWrapped) {
        try {
          cl.add = DOMTokenList.prototype.add.bind(cl);
          cl.remove = DOMTokenList.prototype.remove.bind(cl);
          delete cl.__wfWrapped;
        } catch (_) {}
      }
      // Disconnect shim MutationObserver if present
      if (popup.__wfClassMo && typeof popup.__wfClassMo.disconnect === 'function') {
        try { popup.__wfClassMo.disconnect(); } catch (_) {}
        try { delete popup.__wfClassMo; } catch (_) {}
      }
    }

    // Provide global wrappers that delegate to the class implementation (throttled, robust)
    window.cancelHideGlobalPopup = () => {
      try { console.log('[globalPopup] cancelHide requested'); } catch(_) {}
      try { unifiedPopup.cancelHide(); } catch (_) {}
    };
    window.hideGlobalPopupImmediate = () => {
      try { console.log('[globalPopup] hideImmediate requested'); } catch(_) {}
      try { unifiedPopup.hideImmediate(true); } catch (_) {}
    };
    window.scheduleHideGlobalPopup = (delay) => {
      try { console.log('[globalPopup] scheduleHide requested', delay); } catch(_) {}
      try { unifiedPopup.scheduleHide(delay); } catch (_) {}
    };
  } catch (_) {}
})();

function propagateToIframes() {
  document.querySelectorAll('iframe').forEach(ifr => {
    try {
      const win = ifr.contentWindow;
      if (win && win.location && win.location.origin === window.location.origin) {
        // Bridge popup API into same-origin iframes
        win.showGlobalPopup = window.showGlobalPopup;
        win.hideGlobalPopup = window.hideGlobalPopup;
        win.hideGlobalPopupImmediate = window.hideGlobalPopupImmediate;
        win.scheduleHideGlobalPopup = window.scheduleHideGlobalPopup;
        win.cancelHideGlobalPopup = window.cancelHideGlobalPopup;

        // Also attach boundary listeners on the iframe element in the top window
        // so moving from iframe content to the popup won't prematurely hide it.
        const cancel = () => { try { window.cancelHideGlobalPopup && window.cancelHideGlobalPopup(); } catch(_) {} };
        const maybeSchedule = (e) => {
          try {
            const popup = document.getElementById('itemPopup');
            const rt = e && e.relatedTarget;
            if (popup && (rt === popup || (rt && popup.contains && popup.contains(rt)))) {
              // Moving into the popup -> cancel hide
              cancel();
              return;
            }
            // Otherwise, schedule a delayed hide to allow popup to capture mouseenter
            window.scheduleHideGlobalPopup && window.scheduleHideGlobalPopup(250);
          } catch(_) {}
        };
        // Ensure we don't bind multiple times
        if (!ifr.__wfPopupBoundaryBound) {
          ifr.addEventListener('mouseover', cancel);
          ifr.addEventListener('mouseout', maybeSchedule);
          ifr.__wfPopupBoundaryBound = true;
        }
      }
    } catch (_) {
      /* cross-origin – ignore */
    }
  });
}

// Initial propagation and on future iframe additions
propagateToIframes();
window.addEventListener('load', propagateToIframes);

export { unifiedPopup };
