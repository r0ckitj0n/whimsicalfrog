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
    console.warn('[eventManager] Delegated listeners already attached; skipping duplicate attachment');
    return;
  }
  document.body.setAttribute('data-wf-room-delegated-listeners', 'true');
  console.log('[eventManager] Delegated listeners attached');

  // Use mouseover/mouseout (bubbling) with guards – works reliably in iframes
  document.addEventListener('mouseover', e => {
    // cancel any pending hide since pointer is back over icon or popup
    const { cancelHide } = getPopupApi();
    if (typeof cancelHide === 'function') cancelHide();
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
    const { show } = getPopupApi();
    console.log('[eventManager] popup function available:', typeof show);
    if (typeof show === 'function' && data) {
      if (window.__wfCurrentPopupAnchor !== icon) {
        window.__wfCurrentPopupAnchor = icon;
        console.log('[eventManager] calling popup function with:', icon, data);
        show(icon, data);
        attachPopupPersistence(icon);
      }
    } else {
      console.warn('[eventManager] cannot show popup - function:', typeof show, 'data:', !!data);
    }
  });

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
    if (typeof scheduleHide === 'function') scheduleHide(250);
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
  // If delegated listeners are active, skip per-icon bindings to avoid duplication
  if (document.body && document.body.hasAttribute('data-wf-room-delegated-listeners')) {
    console.log('[eventManager] Delegated listeners active; skipping per-icon fallback');
    return;
  }
  const icons = document.querySelectorAll('.item-icon, .room-product-icon');
  console.warn('[eventManager] Delegation inactive; attaching legacy per-icon listeners to', icons.length, 'icons');
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
      if (typeof scheduleHide === 'function') scheduleHide(250);
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

  const clearHide = () => {
    const { cancelHide } = getPopupApi();
    if (typeof cancelHide === 'function') cancelHide();
  };

  const scheduleHide = () => {
    const { scheduleHide } = getPopupApi();
    if (typeof scheduleHide === 'function') scheduleHide(250);
  };

  const _detach = () => {
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
