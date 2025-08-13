// WhimsicalFrog – Global Popup ES module
// Extracted from legacy js/global-popup.js for Vite build.
// Only the public API (show/hide) and minimal implementation retained.

class UnifiedPopupSystem {
  constructor() {
    this.popupEl = null;
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
    if (!this.popupEl || this.popupEl.dataset.interactionsBound) return;
    this.popupEl.dataset.interactionsBound = '1';
    // Click anywhere inside popup opens item modal
    this.popupEl.addEventListener('click', () => {
      if (typeof window.showGlobalItemModal === 'function' && this.currentItem) {
        window.showGlobalItemModal(this.currentItem.sku, this.currentItem);
      }
    });
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
    setText('#popupCategory', item.category || '');
    setText('#popupSku', item.sku ? `SKU: ${item.sku}` : '');
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

    // Position: below anchor
    const rect = anchorEl.getBoundingClientRect();
    let top = rect.bottom + 6 + window.scrollY;
    let left = rect.left + window.scrollX;
    // If anchor is inside an iframe, adjust coordinates by iframe offset
    if (anchorEl.ownerDocument !== document) {
      try {
        const iframe = anchorEl.ownerDocument.defaultView.frameElement;
        if (iframe) {
          const iframeRect = iframe.getBoundingClientRect();
          top = iframeRect.top + rect.bottom + 6; // rect values are relative to iframe viewport
          left = iframeRect.left + rect.left;
          console.log('[globalPopup] adjusted for iframe:', { top, left });
        }
      } catch (err) {
        console.warn('[globalPopup] failed iframe adjustment', err);
      }
    }
    this._applyPosition(top, left);

    // Show invisibly to measure size then clamp
    this.popupEl.classList.remove('hidden');
    this.popupEl.classList.add('measuring');

    requestAnimationFrame(() => {
      const r = this.popupEl.getBoundingClientRect();
      let newLeft = r.left;
      let newTop = r.top;
      if (r.right > window.innerWidth) newLeft = window.innerWidth - r.width - 8;
      if (newLeft < 0) newLeft = 8;
      if (r.bottom > window.innerHeight) newTop = window.innerHeight - r.height - 8;
      if (newTop < 0) newTop = 8;
      this._applyPosition(newTop, newLeft);

      // Reveal popup now that it is positioned
      this.popupEl.classList.remove('measuring');
      this.popupEl.classList.add('visible');
    });
    console.log('[globalPopup] positioned popup at:', { top, left });

    // Hide any other stale popups
    document.querySelectorAll('.item-popup.visible').forEach(el => {
      if (el !== this.popupEl) el.classList.add('hidden');
    });
    this.currentItem = item;
    // one-time click handler to open item modal
    if (!this.popupEl.dataset.detailBound) {
      this.popupEl.dataset.detailBound = '1';
      this.popupEl.addEventListener('click', () => {
        if (typeof window.showGlobalItemModal === 'function' && this.currentItem) {
          window.showGlobalItemModal(this.currentItem.sku, this.currentItem);
        }
      });
    }

    this.popupEl.classList.remove('hidden');
    this.popupEl.classList.add('visible');
    console.log('[globalPopup] popup shown');
  }

  hide() {
    console.log('[globalPopup] hide called');
    if (this.popupEl) {
      this.popupEl.classList.remove('visible');
      this.popupEl.classList.add('hidden');
      console.log('[globalPopup] popup hidden');
    } else {
      console.warn('[globalPopup] no popup element to hide');
    }
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
window.hideGlobalPopup = () => unifiedPopup.hide();

function propagateToIframes() {
  document.querySelectorAll('iframe').forEach(ifr => {
    try {
      const win = ifr.contentWindow;
      if (win && win.location && win.location.origin === window.location.origin) {
        win.showGlobalPopup = window.showGlobalPopup;
        win.hideGlobalPopup = window.hideGlobalPopup;
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
