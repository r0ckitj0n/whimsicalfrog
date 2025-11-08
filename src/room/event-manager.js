// src/room/eventManager.js
// ES-module for room icon hover/click events and popup wiring.
const DEBUG_EVENTS = (() => {
  try {
    const p = new URLSearchParams(window.location.search || '');
    const fromParam = p.get('wf_diag_events');
    const fromLS = localStorage.getItem('wf_diag_events');
    if (fromParam != null) return fromParam === '1';
    if (fromLS != null) return fromLS === '1';
  } catch(_) {}
  return false;
})();

/** Extract product data from various legacy sources on an icon element. */
function extractProductData(icon) {
  if (icon.dataset.product) {
    try {
      const parsed = JSON.parse(icon.dataset.product);
      // Prefer dataset stockLevel/stock over JSON (dataset reflects current inventory)
      const stockFromDs = parseInt((icon.dataset.stockLevel ?? icon.dataset.stock ?? ''), 10);
      if (!Number.isNaN(stockFromDs)) parsed.stock = stockFromDs;
      if (parsed.price == null && (icon.dataset.price || icon.dataset.cost)) {
        parsed.price = parseFloat(icon.dataset.price || icon.dataset.cost || '0');
      }
      if (!parsed.image && icon.dataset.image) parsed.image = icon.dataset.image;
      if (!parsed.category && icon.dataset.category) parsed.category = icon.dataset.category;
      return parsed;
    } catch (e) {
      console.warn('[eventManager] invalid JSON in data-product', e);
    }
  }
  if (icon.dataset.sku) {
    return {
      sku: icon.dataset.sku,
      name: icon.dataset.name || '',
      price: parseFloat(icon.dataset.price || icon.dataset.cost || '0'),
      description: icon.dataset.description || '',
      stock: parseInt((icon.dataset.stockLevel ?? icon.dataset.stock ?? '0'), 10),
      category: icon.dataset.category || '',
      image: icon.dataset.image || ''
    };
  }
  const attr = icon.getAttribute('onmouseenter');
  if (attr) {
    const match = attr.match(/showGlobalPopup\(this,\s*(.+)\)/);
    if (match) {
      try {
        const jsonString = match[1]
          .replace(/&quot;/g, '"')
          .replace(/&#039;/g, "'");
        return JSON.parse(jsonString);
      } catch (e) {
        console.warn('[eventManager] Failed to parse inline JSON', e);
      }
    }
  }
  return null;
}

// Use global popup scheduling API where available
function getPopupApi() {
  return {
    show: window.showGlobalPopup || (parent && parent.showGlobalPopup),
    hide: window.hideGlobalPopup || (parent && parent.hideGlobalPopup),
    scheduleHide: window.scheduleHideGlobalPopup || (parent && parent.scheduleHideGlobalPopup),
    cancelHide: window.cancelHideGlobalPopup || (parent && parent.cancelHideGlobalPopup),
  };
}

/** Delegated hover / click handlers attached to document once. */
export function attachDelegatedItemEvents() {
  if (document.body.hasAttribute('data-wf-room-delegated-listeners')) {
    if (DEBUG_EVENTS) console.warn('[eventManager] Delegated listeners already attached; skipping duplicate attachment');
    return;
  }
  document.body.setAttribute('data-wf-room-delegated-listeners', 'true');
  if (DEBUG_EVENTS) console.log('[eventManager] Delegated listeners attached');

  // Use mouseover/mouseout (bubbling) with guards – works reliably in iframes
  document.addEventListener('mouseover', e => {
    // Guard: ensure event.target is an Element that supports closest()
    const targetEl = e.target;
    if (!targetEl || typeof targetEl.closest !== 'function') {
      return;
    }
    const overPopup = targetEl.closest('.item-popup');
    const icon = targetEl.closest('.item-icon, .room-product-icon');
    // Only cancel hide if pointer is over an icon or the popup itself
    if (overPopup || icon) {
      const { cancelHide } = getPopupApi();
      if (typeof cancelHide === 'function') cancelHide();
    }
    // If not over an icon, do nothing here; mouseout handler controls hide timing.
    if (!icon) {
      if (DEBUG_EVENTS) console.log('[eventManager] mouseover on non-icon');
      return;
    }
    if (DEBUG_EVENTS) console.log('[eventManager] found icon element:', icon, 'classes:', icon.className);
    const data = extractProductData(icon);
    if (DEBUG_EVENTS) console.log('[eventManager] extracted product data:', data);
    const { show } = getPopupApi();
    if (DEBUG_EVENTS) console.log('[eventManager] popup function available:', typeof show);
    if (typeof show === 'function' && data) {
      if (DEBUG_EVENTS) console.log('[eventManager] calling popup function with:', icon, data);
      show(icon, data);
      attachPopupPersistence(icon);
      // Optional debug-only fallback reveal attempts
      if (DEBUG_EVENTS) {
        const forceReveal = (attempt) => {
          try {
            const p = document.getElementById('itemPopup');
            if (!p) return;
            const suppressed = p.classList.contains('suppress-auto-show') || p.dataset.wfGpSuppress === '1';
            const posClass = p.dataset.wfGpPosClass;
            const detailed = document.getElementById('detailedItemModal');
            const detailedVisible = !!(detailed && detailed.getAttribute('aria-hidden') !== 'true' && !(detailed.classList && detailed.classList.contains('hidden')));
            if (suppressed || !posClass || detailedVisible) return;
            if (!p.classList.contains('visible')) {
              try { p.classList.remove('hidden', 'measuring'); } catch(_) {}
              try { p.classList.add('visible'); } catch(_) {}
              try { p.setAttribute('aria-hidden', 'false'); } catch(_) {}
              try { p.classList.add('force-visible'); } catch(_) {}
              try { console.log('[eventManager] fallback reveal applied (attempt', attempt, ') classes:', p.className); } catch(_) {}
            }
          } catch (_) {}
        };
        setTimeout(() => forceReveal(1), 0);
      }
    } else {
      if (DEBUG_EVENTS) console.warn('[eventManager] cannot show popup - function:', typeof show, 'data:', !!data);
    }
  });

  // Removed popup click fallbacks; popup handles its own open logic.

  // Schedule hide when leaving icons or popup
  document.addEventListener('mouseout', e => {
    const targetEl = e.target;
    if (!targetEl || typeof targetEl.closest !== 'function') return;
    const related = e.relatedTarget;
    const leftIcon = targetEl.closest('.item-icon, .room-product-icon');
    const leftPopup = targetEl.closest('.item-popup');
    if (!leftIcon && !leftPopup) return;
    // If moving into another icon or the popup itself, ignore
    if (related && (related.closest?.('.item-icon, .room-product-icon') || related.closest?.('.item-popup'))) return;
    const { scheduleHide } = getPopupApi();
    if (typeof scheduleHide === 'function') scheduleHide(500);
  });


  document.addEventListener('click', async e => {
    const targetEl = e.target;
    if (!targetEl || typeof targetEl.closest !== 'function') return;
    // No popup fallback here; rely on popup element handlers
    // Support clicks on standard icons and any element carrying product data
    const icon = targetEl.closest('.item-icon, .room-product-icon, [data-sku], [data-product]');
    if (!icon) return;
    e.preventDefault();
    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
    const data = extractProductData(icon);
    let detailsFn =
      (parent && parent.showGlobalItemModal) ||
      window.showGlobalItemModal ||
      window.showItemDetailsModal ||
      window.showItemDetails ||
      (parent && (parent.showItemDetailsModal || parent.showItemDetails));
    if (typeof detailsFn !== 'function') {
      try { await import('../js/detailed-item-modal.js'); } catch(_) {}
      detailsFn = window.showGlobalItemModal || window.showItemDetailsModal || window.showItemDetails;
    }
    if (typeof detailsFn === 'function' && data) {
      try {
        window.hideGlobalPopupImmediate && window.hideGlobalPopupImmediate();
        if (typeof parent !== 'undefined' && parent !== window && parent.hideGlobalPopupImmediate) parent.hideGlobalPopupImmediate();
      } catch(_) {}
      detailsFn(data.sku, data);
    }
  });
}

/** For legacy per-icon listeners (called after coordinate positioning). */
export function setupPopupEventsAfterPositioning() {
  if (DEBUG_EVENTS) console.log('[eventManager] setupPopupEventsAfterPositioning() called');
  // If delegated listeners are active, skip per-icon bindings to avoid duplication
  if (document.body && document.body.hasAttribute('data-wf-room-delegated-listeners')) {
    if (DEBUG_EVENTS) console.log('[eventManager] Delegated listeners active; skipping per-icon fallback');
    return;
  }
  const icons = document.querySelectorAll('.item-icon, .room-product-icon');
  if (DEBUG_EVENTS) console.warn('[eventManager] Delegation inactive; attaching legacy per-icon listeners to', icons.length, 'icons');
  icons.forEach(icon => {
    // ensure icon is interactive
    icon.classList.add('clickable-icon');
    const data = extractProductData(icon);
    if (!data) return;
    icon.addEventListener('mouseenter', () => {
      const { show, cancelHide } = getPopupApi();
      if (typeof cancelHide === 'function') cancelHide();
      if (typeof show === 'function') show(icon, data);
    });
    icon.addEventListener('mouseleave', () => {
      const { scheduleHide } = getPopupApi();
      if (typeof scheduleHide === 'function') scheduleHide(500);
    });
    icon.addEventListener('click', async e => {
      e.preventDefault();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
      let fn =
        (parent && parent.showGlobalItemModal) ||
        window.showGlobalItemModal ||
        window.showItemDetailsModal ||
        window.showItemDetails ||
        (parent && (parent.showItemDetailsModal || parent.showItemDetails));
      if (typeof fn !== 'function') {
        try { await import('../js/detailed-item-modal.js'); } catch(_) {}
        fn = window.showGlobalItemModal || window.showItemDetailsModal || window.showItemDetails;
      }
      if (typeof fn === 'function') {
        try { window.hideGlobalPopupImmediate && window.hideGlobalPopupImmediate(); } catch(_) {}
        fn(data.sku, data);
      }
    });
  });
}

/** Attach mouseenter/leave on icon and popup to keep it visible while hovering either. */
function attachPopupPersistence(icon) {
  const popup = document.querySelector('.item-popup');
  if (!popup || popup.__wfBound) return;
  popup.__wfBound = true;

  const clearHide = () => {
    const { cancelHide } = getPopupApi();
    if (typeof cancelHide === 'function') cancelHide();
  };

  const scheduleHide = () => {
    const { scheduleHide } = getPopupApi();
    if (typeof scheduleHide === 'function') scheduleHide(500);
  };

  const _detach = () => {
    icon.removeEventListener('mouseenter', clearHide);
    icon.removeEventListener('mouseleave', scheduleHide);
    popup.removeEventListener('mouseenter', clearHide);
    popup.__wfBound = false;
  };

  // Bind events
  icon.addEventListener('mouseenter', clearHide);
  icon.addEventListener('mouseleave', () => {
    const { scheduleHide } = getPopupApi();
    if (typeof scheduleHide === 'function') scheduleHide(500);
  });
  popup.addEventListener('mouseenter', clearHide);
  popup.addEventListener('mouseleave', () => {
    const { scheduleHide } = getPopupApi();
    if (typeof scheduleHide === 'function') scheduleHide(500);
  });
}

// Immediately attach delegated listeners on import
attachDelegatedItemEvents();
// Fallback: after initial render, ensure each icon has listeners in case delegation failed (e.g., icons with pointer-events:none)
setTimeout(() => {
  try {
    setupPopupEventsAfterPositioning();
  } catch (err) {
    console.warn('[eventManager] setupPopupEventsAfterPositioning failed', err);
  }
}, 800);

// Bridge to global for legacy code / iframes
window.attachDelegatedItemEvents = attachDelegatedItemEvents;
window.setupPopupEventsAfterPositioning = setupPopupEventsAfterPositioning;
