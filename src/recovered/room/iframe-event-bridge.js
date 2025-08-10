// Whimsical Frog – iframe-event-bridge.js
// Ensures delegated hover/click listeners for product icons are attached INSIDE a room-modal iframe.
// This script should be loaded inside the iframe document (load_room_content.php).
// Strategy:
// 1. If eventManager already bundled and its global attachDelegatedItemEvents() is available, just call it.
// 2. Otherwise dynamically import the source module that defines and registers the listeners.

(function () {
  // Minimal in-iframe delegated listeners as ultimate fallback
  function attachBasicDelegatedListeners() {
    if (document.body.hasAttribute('data-wf-room-basic-listeners')) return;
    document.body.setAttribute('data-wf-room-basic-listeners', 'true');
    console.debug('[iframe-event-bridge] Installing basic delegated listeners');

    const getData = (icon) => {
      try {
        if (icon.dataset.product) return JSON.parse(icon.dataset.product);
      } catch (e) {}
      return {
        sku: icon.dataset.sku,
        name: icon.dataset.name,
        price: parseFloat(icon.dataset.price || '0'),
        description: icon.dataset.description || '',
        image: icon.dataset.image || (icon.querySelector('img') ? icon.querySelector('img').src : ''),
        category: icon.dataset.category || '',
        stock: icon.dataset.stock || '',
        originalPrice: icon.dataset.originalPrice || icon.dataset.retailPrice || '',
      };
    };

    document.addEventListener('mouseover', (e) => {
      console.debug('[iframe-event-bridge] mouseover detected');
      const icon = e.target.closest('.item-icon, .room-product-icon');
      if (!icon) return;
      const data = getData(icon);
      const fn = window.showGlobalPopup || (parent && parent.showGlobalPopup);
      if (typeof fn === 'function') {
        console.debug('[iframe-event-bridge] calling showGlobalPopup');
        fn(icon, data);
      } else {
        console.warn('[iframe-event-bridge] showGlobalPopup function not found');
      }
    });
    // Removed immediate hide on mouseout so user can move cursor into popup. Popup will hide on its own mouseleave.
    
    document.addEventListener('click', (e) => {
      const icon = e.target.closest('.item-icon, .room-product-icon');
      if (!icon) return;
      e.preventDefault();
      const data = getData(icon);
      const fn = (parent && (parent.showGlobalItemModal || parent.showItemDetailsModal || parent.showItemDetails)) || window.showGlobalItemModal || window.showItemDetailsModal || window.showItemDetails;
      if (typeof fn === 'function') fn(data.sku, data);
    });
  }
  // If the function is already present (e.g., included via dist/app.js in dev server), just run it.
  if (typeof window.attachDelegatedItemEvents === 'function') {
    try {
      window.attachDelegatedItemEvents();
      console.debug('[iframe-event-bridge] Existing attachDelegatedItemEvents executed.');
    } catch (err) {
      console.error('[iframe-event-bridge] Error executing existing attachDelegatedItemEvents:', err);
    }
    return;
  }

  // Otherwise attempt dynamic import of the eventManager source. This works in modern browsers.
  const managerPaths = [
    '/src/room/eventManager.js', // Vite /src path in dev/prod builds
    '/js/room/eventManager.js'  // legacy built location
  ];
  let importPromise = Promise.reject();
  for (const p of managerPaths) {
    importPromise = importPromise.catch(() => import(p));
  }
  importPromise
    .then(() => {
      if (typeof window.attachDelegatedItemEvents === 'function') {
        window.attachDelegatedItemEvents();
        console.debug('[iframe-event-bridge] eventManager module loaded and listeners attached.');
      } else {
        console.warn('[iframe-event-bridge] eventManager loaded but attachDelegatedItemEvents not found.');
      }
    })
    .catch((err) => {
      console.error('[iframe-event-bridge] Failed to load eventManager.js:', err);
      // Fallback: attach minimal delegated listeners directly inside this iframe
      attachBasicDelegatedListeners();
      console.error('[iframe-event-bridge] Failed to load eventManager.js:', err);
    });
})();
