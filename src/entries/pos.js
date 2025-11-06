// Vite entry: pos.js (renamed from admin-pos.js)
import '../styles/components/modal.css';
import '../styles/components/detailed-item-modal.css';
import '../styles/site-base.css';
import '../styles/z-index.css';
import '../styles/components/qty-button.css';
import '../styles/components/room-modal.css';
import '../styles/components/popup.css';
import '../styles/main.css';
// Ensure a cart API exists before the detailed modal binds its handlers.
// This stub queues addItem calls until the real POS module initializes.
try {
  if (!window.WF_Cart) {
    window.__POS_pendingAdds = [];
    window.WF_Cart = {
      addItem: async (payload) => {
        try { window.__POS_pendingAdds.push(payload); } catch(_) {}
        // Notify listeners that a pending add was queued
        try { document.dispatchEvent(new CustomEvent('pos:pendingAdd')); } catch(_) {}
        return { success: true };
      },
      updateCartDisplay: () => {},
      clear: () => { try { window.__POS_pendingAdds.length = 0; } catch(_) {} },
    };
    // Legacy bridges some scripts use
    try {
      window.cart = window.cart || {};
      window.cart.add = (payload, qty = 1) => {
        try {
          const p = payload || {};
          const normalized = { sku: p.sku, quantity: qty || p.quantity || 1, price: p.price || 0, name: p.name || '', image: p.image || '' };
          window.__POS_pendingAdds.push(normalized);
        } catch(_) {}
        try { document.dispatchEvent(new CustomEvent('pos:pendingAdd')); } catch(_) {}
      };
      window.cart.addItem = ({ sku, quantity = 1, price = 0, name = '', image = '' }) => {
        try { window.__POS_pendingAdds.push({ sku, quantity, price, name, image }); } catch(_) {}
        try { document.dispatchEvent(new CustomEvent('pos:pendingAdd')); } catch(_) {}
      };
      window.addToCart = (sku, qty = 1) => {
        try { window.__POS_pendingAdds.push({ sku, quantity: qty, price: 0, name: '', image: '' }); } catch(_) {}
        try { document.dispatchEvent(new CustomEvent('pos:pendingAdd')); } catch(_) {}
      };
    } catch(_) {}
  }
} catch(_) {}

// Load the detailed item modal stack (uses WF_Cart.addItem when adding)
import('../js/detailed-item-modal.js');
// Load the main POS module (defines the real cart integration and drains queue)
import('../js/pos.js');
