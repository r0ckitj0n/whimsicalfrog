// WhimsicalFrog – Global Popup ES module
// Extracted from legacy js/global-popup.js for Vite build.
// Only the public API (show/hide) and minimal implementation retained.

const BIND_VERSION = 'v2';

class UnifiedPopupSystem {
  constructor() {
    this.popupEl = null;
    this.hideTimer = null;
    this.isPointerOverPopup = false;
    this.resizeObserver = null;
    this._anchorViewportRect = null;
    this._badgeCache = new Map();
    this.init();
  }

  init() {
    if (this.popupEl) return;
    // Ensure popup can receive clicks
    const styleEl = document.createElement('style');
    styleEl.innerHTML = `
      .item-popup{pointer-events:auto !important;}
      /* Force correct coordinates and appearance even if legacy bundle overrides */
      .item-popup.positioned{left:auto !important;top:auto !important;}
      /* Neutral background without translucent shade */
      .item-popup{background:transparent !important; box-shadow:none !important;}
    `;
    document.head.appendChild(styleEl);
    if (this.popupEl) return;
    // Remove any duplicate popups from previous renders
    document.querySelectorAll('.item-popup').forEach(el => {
      if (el.id !== 'itemPopup') el.remove();
    });
    // Locate existing popup markup from PHP-rendered template
    this.popupEl = document.getElementById('itemPopup');
    if (!this.popupEl) {
      // Popup not yet in DOM – wait for template insertion
      document.addEventListener('DOMContentLoaded', () => {
        this.popupEl = document.getElementById('itemPopup');
        if (this.popupEl) this.bindPopupInteractions();
      });
      return; // No popup element right now
    }

    // Popup element found – set up event bindings
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
    // Click anywhere inside popup opens item modal
    this.popupEl.addEventListener('click', () => {
      // Prevent aria-hidden focus warning by removing focus before hiding
      try { if (document.activeElement && typeof document.activeElement.blur === 'function') document.activeElement.blur(); } catch (_) {}
      if (typeof window.hideGlobalPopupImmediate === 'function') {
        window.hideGlobalPopupImmediate();
      }
      const sku = this.currentItem?.sku;
      if (typeof window.showGlobalItemModal === 'function' && sku) {
        console.log('[globalPopup] opening detailed modal from popup click for', sku);
        window.showGlobalItemModal(sku, this.currentItem);
      } else if (window.WhimsicalFrog?.GlobalModal?.show && sku) {
        console.log('[globalPopup] opening detailed modal via WF.GlobalModal for', sku);
        window.WhimsicalFrog.GlobalModal.show(sku, this.currentItem);
      }
    });

    // Sticky hover: cancel hide when hovering popup; schedule when leaving
    this.popupEl.addEventListener('mouseenter', () => {
      this.isPointerOverPopup = true;
      this.cancelHide();
    });
    this.popupEl.addEventListener('mouseleave', () => {
      this.isPointerOverPopup = false;
      this.scheduleHide();
    });

    // Explicit: Add to Cart button triggers detailed item modal
    const addBtn = this.popupEl.querySelector('#popupAddBtn');
    if (addBtn) {
      addBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        // Prevent aria-hidden focus warning and close popup before opening modal
        try { if (document.activeElement && typeof document.activeElement.blur === 'function') document.activeElement.blur(); } catch (_) {}
        if (typeof window.hideGlobalPopupImmediate === 'function') {
          window.hideGlobalPopupImmediate();
        }
        const sku = this.currentItem?.sku;
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
      img.style.cursor = 'pointer';
      img.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
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
    const css = `.item-popup.${cls}{position:absolute;top:${Math.round(t)}px !important;left:${Math.round(l)}px !important;}`;
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
  }

  show(anchorEl, item) {
    // Cancel any pending hide when showing
    this.cancelHide();
    // Prevent redundant show calls for the same anchor – avoids flashing
    if (this.popupEl && this.popupEl.classList.contains('visible') && anchorEl === this._lastAnchor) {
      return; // already showing for this element
    }
    this._lastAnchor = anchorEl;
    console.log('[globalPopup] show called with:', anchorEl, item);
    this.init();
    if (!this.popupEl) {
      console.warn('[globalPopup] popup element not yet available');
      return;
    }
    if (!anchorEl || !item) {
      console.warn('[globalPopup] missing anchor or item data:', { anchorEl: !!anchorEl, item: !!item });
      return;
    }
    // Save current item for click handling
    this.currentItem = item;
    // Basic content update
    const setText = (selector, text) => {
      const el = this.popupEl.querySelector(selector);
      if (el) el.textContent = text ?? '';
    };
    setText('#popupTitle', item.name || item.productName || item.sku);
    setText('#popupDescription', item.description || '');

    // Stock badge
    const stockBadge = this.popupEl.querySelector('#popupStockBadge');
    const stockInfo = this.popupEl.querySelector('#popupStock');
    if ('stock' in item && stockInfo) {
      const stock = Number(item.stock ?? item.stockLevel ?? 0);
      stockInfo.textContent = stock > 0 ? `${stock} in stock` : 'Out of stock';
      if (stockBadge) stockBadge.classList.toggle('hidden', stock > 3);
    }

    // Price
    const currentPriceEl = this.popupEl.querySelector('#popupCurrentPrice');
    const originalPriceEl = this.popupEl.querySelector('#popupOriginalPrice');
    const savingsEl = this.popupEl.querySelector('#popupSavings');
    if (currentPriceEl) currentPriceEl.textContent = `$${(item.price ?? item.currentPrice ?? 0).toFixed(2)}`;

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
    if (sku) this._loadAndRenderBadges(sku);

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

    // Position: below anchor
    const rect = anchorEl.getBoundingClientRect();
    let top = rect.bottom + 6 + window.scrollY;
    let left = rect.left + window.scrollX;
    // Anchor rect relative to the top-window viewport (account for iframe)
    let anchorViewportRect = { top: rect.top, bottom: rect.bottom, left: rect.left, right: rect.right };
    // If anchor is inside an iframe, adjust coordinates by iframe offset
    if (anchorEl.ownerDocument !== document) {
      try {
        const iframe = anchorEl.ownerDocument.defaultView.frameElement;
        if (iframe) {
          const iframeRect = iframe.getBoundingClientRect();
          top = iframeRect.top + rect.bottom + 6; // rect values are relative to iframe viewport
          left = iframeRect.left + rect.left;
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
    this._applyPosition(top, left);
    // Persist for reclamping on dynamic size changes
    this._anchorViewportRect = anchorViewportRect;

    // Show invisibly to measure size then clamp
    // Add measuring first, then remove hidden to avoid any chance of a paint between operations
    this.popupEl.classList.add('measuring');
    this.popupEl.classList.remove('hidden');

    requestAnimationFrame(() => {
      this._clampAndApply(this._anchorViewportRect);

      // Reveal popup now that it is positioned
      this.popupEl.classList.remove('measuring');
      this.popupEl.classList.add('visible');
      this.popupEl.setAttribute('aria-hidden', 'false');
      console.log('[globalPopup] popup shown');

      // Observe size changes (e.g., image loads) and re-clamp to keep fully visible
      this._ensureResizeObserver();
    });
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
        const res = await fetch(`/api/get_badge_scores.php?sku=${encodeURIComponent(sku)}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
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
  scheduleHide(delay = 120) {
    this.cancelHide();
    this.hideTimer = setTimeout(() => {
      if (!this.isPointerOverPopup) this.hideImmediate();
    }, delay);
    console.log('[globalPopup] hide scheduled in', delay, 'ms');
  }

  cancelHide() {
    if (this.hideTimer) {
      clearTimeout(this.hideTimer);
      this.hideTimer = null;
    }
  }

  hideImmediate() {
    console.log('[globalPopup] hide immediate');
    if (this.popupEl) {
      this.popupEl.classList.remove('visible');
      this.popupEl.classList.add('hidden');
      this.popupEl.classList.remove('in-room-modal');
      this.popupEl.setAttribute('aria-hidden', 'true');
      console.log('[globalPopup] popup hidden');
    } else {
      console.warn('[globalPopup] no popup element to hide');
    }
    // Cleanup observers/state
    if (this.resizeObserver) {
      try { this.resizeObserver.disconnect(); } catch (_) {}
    }
    this.resizeObserver = null;
    this._anchorViewportRect = null;
  }

  _clampAndApply(anchorViewportRect) {
    if (!this.popupEl || !anchorViewportRect) return;
    const r = this.popupEl.getBoundingClientRect();
    const margin = 8;
    const spaceBelow = window.innerHeight - anchorViewportRect.bottom - margin;
    const spaceAbove = anchorViewportRect.top - margin;
    let targetTopV;
    if (spaceBelow >= r.height) {
      targetTopV = anchorViewportRect.bottom + 6;
    } else if (spaceAbove >= r.height) {
      targetTopV = anchorViewportRect.top - r.height - 6;
    } else {
      const preferBelow = spaceBelow >= spaceAbove;
      if (preferBelow) {
        targetTopV = Math.max(margin, Math.min(anchorViewportRect.bottom + 6, window.innerHeight - r.height - margin));
      } else {
        targetTopV = Math.max(margin, Math.min(anchorViewportRect.top - r.height - 6, window.innerHeight - r.height - margin));
      }
    }
    let targetLeftV = Math.min(Math.max(anchorViewportRect.left, margin), window.innerWidth - r.width - margin);
    targetLeftV = Math.min(Math.max(targetLeftV, margin), window.innerWidth - r.width - margin);
    targetTopV = Math.min(Math.max(targetTopV, margin), window.innerHeight - r.height - margin);
    const docLeft = Math.round(targetLeftV + window.scrollX);
    const docTop = Math.round(targetTopV + window.scrollY);
    this._applyPosition(docTop, docLeft);
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
window.hideGlobalPopupImmediate = () => unifiedPopup.hideImmediate();
// Explicit schedule/cancel APIs for delegated handlers
window.scheduleHideGlobalPopup = (delay) => unifiedPopup.scheduleHide(delay);
window.cancelHideGlobalPopup = () => unifiedPopup.cancelHide();

function propagateToIframes() {
  document.querySelectorAll('iframe').forEach(ifr => {
    try {
      const win = ifr.contentWindow;
      if (win && win.location && win.location.origin === window.location.origin) {
        win.showGlobalPopup = window.showGlobalPopup;
        win.hideGlobalPopup = window.hideGlobalPopup;
        win.hideGlobalPopupImmediate = window.hideGlobalPopupImmediate;
        win.scheduleHideGlobalPopup = window.scheduleHideGlobalPopup;
        win.cancelHideGlobalPopup = window.cancelHideGlobalPopup;
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
