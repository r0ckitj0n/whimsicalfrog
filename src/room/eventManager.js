// src/room/eventManager.js
// ES-module for room icon hover/click events and popup wiring.

/** Extract product data from various legacy sources on an icon element. */
function extractProductData(icon) {
  if (icon.dataset.product) {
    try {
      return JSON.parse(icon.dataset.product);
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
      stock: parseInt(icon.dataset.stock || '0', 10),
      category: icon.dataset.category || ''
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

// Shared hide timer for cross-icon persistence (cleared on mouseover)
let hideTimer = null;

/** Delegated hover / click handlers attached to document once. */
export function attachDelegatedItemEvents() {
  if (document.body.hasAttribute('data-wf-room-delegated-listeners')) return;
  document.body.setAttribute('data-wf-room-delegated-listeners', 'true');

  // Use mouseover/mouseout (bubbling) with guards – works reliably in iframes
  document.addEventListener('mouseover', e => {
    // cancel any pending hide since pointer is back over icon or popup
    if (hideTimer) {
      clearTimeout(hideTimer);
      hideTimer = null;
      console.log('[eventManager] hideTimer cleared due to new mouseover');
    }
    // Guard: ensure event.target is an Element that supports closest()
    const targetEl = e.target;
    if (!targetEl || typeof targetEl.closest !== 'function') {
      return;
    }
    console.log('[eventManager] mouseover event detected on:', targetEl);
    const icon = targetEl.closest('.item-icon, .room-product-icon');
    if (!icon) {
      console.log('[eventManager] no matching icon found for hover');
      return;
    }
    console.log('[eventManager] found icon element:', icon, 'classes:', icon.className);
    const data = extractProductData(icon);
    console.log('[eventManager] extracted product data:', data);
    const popupFn = window.showGlobalPopup || (parent && parent.showGlobalPopup);
    console.log('[eventManager] popup function available:', typeof popupFn);
    if (typeof popupFn === 'function' && data) {
      if (window.__wfCurrentPopupAnchor !== icon) {
        window.__wfCurrentPopupAnchor = icon;
        console.log('[eventManager] calling popup function with:', icon, data);
        popupFn(icon, data);
        attachPopupPersistence(icon);
      }
    } else {
      console.warn('[eventManager] cannot show popup - function:', typeof popupFn, 'data:', !!data);
    }
  });


  document.addEventListener('click', e => {
    const targetEl = e.target;
    if (!targetEl || typeof targetEl.closest !== 'function') return;
    const icon = targetEl.closest('.item-icon, .room-product-icon');
    if (!icon) return;
    e.preventDefault();
    const data = extractProductData(icon);
    const detailsFn =
      (parent && parent.showGlobalItemModal) ||
      window.showGlobalItemModal ||
      window.showItemDetailsModal ||
      window.showItemDetails ||
      (parent && (parent.showItemDetailsModal || parent.showItemDetails));
    if (typeof detailsFn === 'function' && data) detailsFn(data.sku, data);
  });
}

/** For legacy per-icon listeners (called after coordinate positioning). */
export function setupPopupEventsAfterPositioning() {
  console.log('[eventManager] setupPopupEventsAfterPositioning() called');
  const icons = document.querySelectorAll('.item-icon, .room-product-icon');
  console.log('[eventManager] icons found for direct listeners:', icons.length);
  icons.forEach(icon => {
    // ensure icon is interactive
    icon.classList.add('clickable-icon');
    const data = extractProductData(icon);
    if (!data) return;
    icon.addEventListener('mouseenter', () => {
      const fn = window.showGlobalPopup || (parent && parent.showGlobalPopup);
      if (typeof fn === 'function') fn(icon, data);
    });
    icon.addEventListener('mouseleave', () => {
      const fn = window.hideGlobalPopup || (parent && parent.hideGlobalPopup);
      if (typeof fn === 'function') fn();
    });
    icon.addEventListener('click', e => {
      e.preventDefault();
      const fn =
        (parent && parent.showGlobalItemModal) ||
        window.showGlobalItemModal ||
        window.showItemDetailsModal ||
        window.showItemDetails ||
        (parent && (parent.showItemDetailsModal || parent.showItemDetails));
      if (typeof fn === 'function') fn(data.sku, data);
    });
  });
}

/** Attach mouseenter/leave on icon and popup to keep it visible while hovering either. */
function attachPopupPersistence(icon) {
  const popup = document.querySelector('.item-popup');
  if (!popup || popup.__wfBound) return;
  popup.__wfBound = true;

  let hideTimerId = null;

  const clearHide = () => {
    if (hideTimerId) {
      clearTimeout(hideTimerId);
      hideTimerId = null;
    }
  };

  const scheduleHide = () => {
    clearHide();
    hideTimerId = setTimeout(() => {
      if (icon.matches(':hover') || popup.matches(':hover')) return; // user returned
      const hideFn = window.hideGlobalPopup || (parent && parent.hideGlobalPopup);
      if (typeof hideFn === 'function') hideFn();
      detach();
    }, 150);
  };

  const detach = () => {
    icon.removeEventListener('mouseenter', clearHide);
    icon.removeEventListener('mouseleave', scheduleHide);
    popup.removeEventListener('mouseenter', clearHide);
    popup.removeEventListener('mouseleave', scheduleHide);
    popup.__wfBound = false;
  };

  // Bind events
  icon.addEventListener('mouseenter', clearHide);
  icon.addEventListener('mouseleave', scheduleHide);
  popup.addEventListener('mouseenter', clearHide);
  popup.addEventListener('mouseleave', scheduleHide);
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
