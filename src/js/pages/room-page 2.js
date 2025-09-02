// Room Page Module
// Handles room-specific initialization previously inlined in sections/room_template.php

(function initRoomPage() {
  try {
    const body = document.body;
    if (!body) return;

    const page = (body.dataset && body.dataset.page) || (window.WF_PAGE_INFO && window.WF_PAGE_INFO.page) || '';
    const m = /^room(\d+)$/.exec(String(page || '').toLowerCase());
    if (!m) {
      // Not a room page; do nothing
      return;
    }

    console.log('[RoomPage] Initializing for', page);

    const roomNumber = m[1];
    const roomType = `room${roomNumber}`;

    // Expose for systems that rely on globals (e.g., room-coordinate-manager.js)
    window.ROOM_NUMBER = roomNumber;
    window.ROOM_TYPE = roomType;

    // Initialize global CSS if available
    if (typeof window.loadGlobalCSS === 'function') {
      try { window.loadGlobalCSS(); } catch (e) { console.warn('[RoomPage] loadGlobalCSS failed', e); }
    } else if (typeof window.loadGlobalCSSVariables === 'function') {
      // Fallback to variables initializer seen in admin; harmless if present
      try { window.loadGlobalCSSVariables(); } catch (e) { console.warn('[RoomPage] loadGlobalCSSVariables failed', e); }
    } else {
      console.log('[RoomPage] No global CSS initializer found; proceeding');
    }

    // Initialize simple room coordinates if available
    if (typeof window.simpleCoordinateSystem === 'function') {
      try { window.simpleCoordinateSystem(roomType); } catch (e) { console.warn('[RoomPage] simpleCoordinateSystem failed', e); }
    }

    // Helper: parse product data from icon element
    function parseItemFromIcon(icon) {
      const json = icon && icon.getAttribute('data-item-json');
      if (!json) return null;
      try { return JSON.parse(json); } catch (e) { console.warn('[RoomPage] Failed to parse item JSON', e); return null; }
    }

    // Bind interactions for product icons
    function bindIconInteractions() {
      const icons = document.querySelectorAll('.item-icon, .room-product-icon');
      if (!icons.length) return;

      icons.forEach((icon) => {
        icon.addEventListener('click', (e) => {
          // Allow images/buttons inside to trigger too
          const targetIcon = e.currentTarget;
          const item = parseItemFromIcon(targetIcon);
          if (!item) return;
          // Track current product globally for compatibility
          window._roomCurrentProduct = item;

          // Prefer unified/global popup if present
          if (typeof window.showGlobalPopup === 'function') {
            try {
              window.showGlobalPopup(targetIcon, item);
              return;
            } catch (err) {
              console.warn('[RoomPage] showGlobalPopup failed, falling back', err);
            }
          }

          // Fallback to global item modal / quantity modal
          if (typeof window.openQuantityModal === 'function') {
            try { window.openQuantityModal(item); return; } catch (err) { console.warn('[RoomPage] openQuantityModal failed', err); }
          }

          console.warn('[RoomPage] No popup or modal system available');
        });
      });
    }

    // Click-outside handler to navigate back to main room
    function bindClickOutside() {
      const container = document.querySelector('#universalRoomPage .room-container');
      if (!container) return;

      document.body.addEventListener('click', (e) => {
        const backBtn = document.querySelector('.back-to-main-button, .back-button');
        if (backBtn && (e.target === backBtn || backBtn.contains(e.target))) return; // let default link work

        const popup = document.getElementById('productPopup');
        if (popup && popup.classList.contains('show')) return; // ignore when popup visible

        if (!container.contains(e.target)) {
          window.location.href = '/?page=room_main';
        }
      });
    }

    // Optional: support for legacy window.showItemDetails used by popup buttons
    window.showItemDetails = async function showItemDetails() {
      const current = window._roomCurrentProduct;
      if (!current) return;
      const sku = current.sku || current.id;
      if (!sku) return;
      try {
        const res = await fetch(`/api/get_item_details.php?sku=${encodeURIComponent(sku)}`);
        const data = await res.json();
        if (!data.success || !data.item) throw new Error(data.error || 'Unknown error');

        // Remove existing detailed modal if any
        const existing = document.getElementById('detailedItemModal');
        if (existing) existing.remove();

        // Minimal detailed modal shell; defer styling to existing CSS
        const container = document.createElement('div');
        container.innerHTML = `
          <div id="detailedItemModal" class="modal-overlay hidden">
            <div class="bg-white rounded-lg max-w-6xl w-full max-h-[95vh] overflow-y-auto shadow-2xl">
              <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center z-10">
                <h2 class="text-2xl font-bold text-gray-800">${data.item.name}</h2>
                <button class="detailed-modal-close-btn text-gray-500 hover:text-gray-700 text-3xl font-bold">&times;</button>
              </div>
              <div class="p-6">
                <div class="text-3xl font-bold text-green-600 mb-2">$${parseFloat(data.item.retailPrice).toFixed(2)}</div>
                ${data.item.description ? `<p class="text-gray-700 text-lg leading-relaxed">${String(data.item.description).replace(/\n/g,'<br>')}</p>` : ''}
              </div>
            </div>
          </div>`;
        document.body.appendChild(container.firstElementChild);

        const modal = document.getElementById('detailedItemModal');
        modal.classList.remove('hidden');
        if (window.WFModals && typeof window.WFModals.lockScroll === 'function') {
          try { window.WFModals.lockScroll(); } catch {}
        }
        modal.querySelector('.detailed-modal-close-btn')?.addEventListener('click', () => {
          modal.remove();
          if (window.WFModals && typeof window.WFModals.unlockScroll === 'function') {
            try { window.WFModals.unlockScroll(); } catch {}
          }
        });
      } catch (err) {
        console.error('[RoomPage] Failed to load item details', err);
        if (typeof window.showError === 'function') {
          try { window.showError('Unable to load item details. Please try again.'); } catch {}
        }
      }
    };

    // Legacy compatibility: allow direct SKU-based detail opening
    window.showProductDetails = async function showProductDetails(sku) {
      if (!sku) return;
      try {
        const res = await fetch(`/api/get_item_details.php?sku=${encodeURIComponent(sku)}`);
        const data = await res.json();
        if (!data.success || !data.item) throw new Error(data.error || 'Unknown error');

        const existing = document.getElementById('detailedItemModal');
        if (existing) existing.remove();

        const container = document.createElement('div');
        container.innerHTML = `
          <div id="detailedItemModal" class="modal-overlay hidden">
            <div class="bg-white rounded-lg max-w-6xl w-full max-h-[95vh] overflow-y-auto shadow-2xl">
              <div class="sticky top-0 bg-white border-b px-6 py-4 flex justify-between items-center z-10">
                <h2 class="text-2xl font-bold text-gray-800">${data.item.name}</h2>
                <button class="detailed-modal-close-btn text-gray-500 hover:text-gray-700 text-3xl font-bold">&times;</button>
              </div>
              <div class="p-6">
                <div class="text-3xl font-bold text-green-600 mb-2">$${parseFloat(data.item.retailPrice).toFixed(2)}</div>
                ${data.item.description ? `<p class=\"text-gray-700 text-lg leading-relaxed\">${String(data.item.description).replace(/\\n/g,'<br>')}</p>` : ''}
              </div>
            </div>
          </div>`;
        document.body.appendChild(container.firstElementChild);

        const modal = document.getElementById('detailedItemModal');
        modal.classList.remove('hidden');
        if (window.WFModals && typeof window.WFModals.lockScroll === 'function') {
          try { window.WFModals.lockScroll(); } catch {}
        }
        modal.querySelector('.detailed-modal-close-btn')?.addEventListener('click', () => {
          modal.remove();
          if (window.WFModals && typeof window.WFModals.unlockScroll === 'function') {
            try { window.WFModals.unlockScroll(); } catch {}
          }
        });
      } catch (err) {
        console.error('[RoomPage] Failed to load product details', err);
        if (typeof window.showError === 'function') {
          try { window.showError('Unable to load product details. Please try again.'); } catch {}
        }
      }
    };

    // Initialize
    bindIconInteractions();
    bindClickOutside();

    console.log('[RoomPage] Initialized successfully for', roomType);
  } catch (e) {
    console.error('[RoomPage] Initialization error', e);
  }
})();
