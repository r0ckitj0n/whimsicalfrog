// src/commerce/cartSystem.js
// Modern ES-module version of the WhimsicalFrog cart system.
// Exports a singleton `cart` object and bridges it to `window.cart` for legacy code.

 

const STORAGE_KEY = 'whimsical_frog_cart';

const state = {
  items: [],
  total: 0,
  count: 0,
  notifications: true
};

// ---------- Internal helpers ----------
function persist() {
  try {
    const data = { items: state.items, total: state.total, count: state.count, t: Date.now() };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  } catch (e) {
    console.error('[Cart] Failed to save cart:', e);
  }
}

function recalc() {
  state.total = state.items.reduce((sum, i) => sum + i.price * i.quantity, 0);
  state.count = state.items.reduce((sum, i) => sum + i.quantity, 0);
}

function notify(type, msg, title = '', duration = 5000) {
  const sys = window.wfNotifications || {};
  const fn = typeof sys[type] === 'function' ? sys[type] : window[`show${type.charAt(0).toUpperCase() + type.slice(1)}`];
  if (typeof fn === 'function') fn(msg, { title, duration });
}

// ---------- Public API ----------
export const cart = {
  /* lifecycle */
  load() {
    try {
      const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
      if (Array.isArray(data.items)) state.items = data.items; else state.items = [];
      recalc();
    } catch {
      state.items = [];
    }
  },
  save: persist,

  /* CRUD operations */
  add(itemIn, qty = 1) {
    const existing = state.items.find(i => i.sku === itemIn.sku);
    if (existing) {
      existing.quantity += qty;
    } else {
      state.items.push({ ...itemIn, quantity: qty });
    }
    recalc();
    persist();
    notify('success', `Added ${itemIn.name || itemIn.sku} (x${qty})`, '✅ Added to Cart');
  },
  remove(sku) {
    const idx = state.items.findIndex(i => i.sku === sku);
    if (idx !== -1) {
      const [removed] = state.items.splice(idx, 1);
      recalc();
      persist();
      notify('info', `${removed.name || sku} removed`, 'Cart');
    }
  },
  updateQuantity(sku, qty) {
    if (qty <= 0) return this.remove(sku);
    const itm = state.items.find(i => i.sku === sku);
    if (itm) {
      itm.quantity = qty;
      recalc();
      persist();
    }
  },
  clear() {
    state.items = [];
    recalc();
    persist();
  },

  /* getters */
  getItems: () => [...state.items],
  getTotal: () => state.total,
  getCount: () => state.count,
  getState: () => ({ ...state })
};

// auto-load stored cart on module import
cart.load();

// Legacy global bridge
autoExpose();
function autoExpose() {
  // preserve existing window.cart reference if already defined (e.g., by bundle)
  if (!window.cart) window.cart = cart;
}
