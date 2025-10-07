// Public page module: payment
// Streamlined checkout page controller

import apiClient from '../../core/api-client.js';

function ready(fn) {
  if (document.readyState !== 'loading') fn();
  else document.addEventListener('DOMContentLoaded', fn, { once: true });
}

function currency(v) {
  const n = Number(v || 0);
  return `$${n.toFixed(2)}`;
}

function getCartApi() {
  // Use the unified CartSystem only to avoid legacy conflicts
  return window.WF_Cart || null;
}

// --- Business info + distance helpers for Local Delivery eligibility ---
let __wf_business_info = null;
async function ensureBusinessInfo() {
  if (__wf_business_info) return __wf_business_info;
  try {
    const res = await apiClient.get('/api/business_settings.php', { action: 'get_business_info' });
    __wf_business_info = res?.data || res || {};
  } catch (_) {
    __wf_business_info = {};
  }

  // Determine if Local Delivery should be shown based on selected address distance
  async function evaluateLocalDeliveryEligibilityPaymentPage() {
    try {
      if (!shipMethodSel) return;
      const localOpt = Array.from(shipMethodSel.options || []).find(o => (o.value || o.text) === 'Local Delivery');
      if (!localOpt) return;
      const addr = addresses.find(a => String(a.id) === String(selectedAddressId));
      if (!addr) {
        localOpt.disabled = true; localOpt.hidden = true;
        if (shipMethodSel.value === 'Local Delivery') shipMethodSel.value = 'USPS';
        updateShippingBadges();
        return;
      }
      const miles = await fetchDrivingMilesPaymentPage(addr);
      const eligible = (miles != null && miles <= 50);
      console.info('[PaymentPage] Local Delivery eligibility ->', { miles, eligible });
      localOpt.disabled = !eligible; localOpt.hidden = !eligible;
      if (!eligible && shipMethodSel.value === 'Local Delivery') {
        shipMethodSel.value = 'USPS';
        updateShippingBadges('USPS');
      }
    } catch (_) {}
  }
  return __wf_business_info;
}

async function fetchDrivingMilesPaymentPage(selectedAddress) {
  try {
    const bi = await ensureBusinessInfo();
    const from = {
      address: (bi?.business_address || ''),
      city: (bi?.business_city || ''),
      state: (bi?.business_state || ''),
      zip: (bi?.business_postal || ''),
    };
    const a = selectedAddress || null;
    if (!a) return null;
    const to = {
      address: (a.address_line1 || ''),
      city: (a.city || ''),
      state: (a.state || ''),
      zip: (a.zip_code || ''),
    };
    const resp = await apiClient.post('/api/distance.php', { from, to });
    const milesRaw = resp?.data?.miles ?? resp?.miles;
    return (milesRaw == null ? null : Number(milesRaw));
  } catch (_) { return null; }
}

// Wait until the unified cart is initialized and ready
async function waitForCartReady(timeout = 1500) {
  const start = Date.now();
  return new Promise((resolve) => {
    const tick = () => {
      const api = window.WF_Cart;
      const initialized = !!(api && typeof api.getItems === 'function' && typeof api.getState === 'function' && api.getState()?.initialized === true);
      if (initialized) return resolve(api);
      if (Date.now() - start >= timeout) return resolve(api || null);
      setTimeout(tick, 50);
    };
    tick();
  });
}

// Read cart items directly from localStorage as a safety fallback
function getStoredCartItems() {
  try {
    const raw = localStorage.getItem('whimsical_frog_cart');
    if (!raw) return [];
    const data = JSON.parse(raw);
    return Array.isArray(data?.items) ? data.items : [];
  } catch (_) {
    return [];
  }
}

ready(() => {
  const body = document.body;
  const dsPage = body?.dataset?.page || '';
  const pageSlug = (typeof dsPage === 'string' && dsPage.includes('/'))
    ? dsPage.split('/').filter(Boolean).pop()
    : dsPage;
  if (!body || pageSlug !== 'payment') return;

  console.log('[PaymentPage] init');

  // Prefer modal experience for /payment: open the checkout modal on the last visited page
  try {
    // 1) Compute a return URL to go back to when user cancels/finishes
    let lastUrl = '/shop';
    try {
      const ref = document.referrer || '';
      const sameOrigin = ref && ref.startsWith(window.location.origin);
      const fromRef = sameOrigin ? new URL(ref).pathname + new URL(ref).search + new URL(ref).hash : '';
      const stored = sessionStorage.getItem('wf:lastPageBeforeCheckout') || localStorage.getItem('wf:lastPageBeforeCheckout') || '';
      lastUrl = (fromRef && !/\/payment(?:$|\?|#)/.test(fromRef)) ? fromRef : (stored || '/shop');
      sessionStorage.setItem('wf:lastPageBeforeCheckout', lastUrl);
      document.body?.setAttribute('data-checkout-return-url', lastUrl);
    } catch(_) {}

    // 2) Set a flag so the next page load auto-opens the checkout modal, then redirect
    try { localStorage.setItem('wf:openCheckoutModal', '1'); } catch(_) {}
    // Hide this page scaffold via body flag to avoid flash while navigating
    try {
      document.body?.setAttribute('data-checkout-modal-open', '1');
      const mainEl = document.querySelector('main');
      if (mainEl) mainEl.setAttribute('aria-hidden', 'true');
    } catch(_) {}
    window.location.replace(lastUrl);
    return; // stop further init
  } catch(_) {}

  // Proactively clear any lingering room modal overlay so checkout UI is not obstructed
  (function cleanupLingeringOverlays() {
    try {
      const rm = window.WF_RoomModal || window.roomModalManager;
      if (rm && typeof rm.isOpen === 'function' && rm.isOpen()) {
        try { rm.close(); } catch (_) {}
      }
      const overlay = document.getElementById('roomModalOverlay');
      if (overlay) overlay.classList.remove('show');
      // Remove room modal body state
      document.body.classList.remove('room-modal-open');
      // Ensure scroll is unlocked if no other modals are open
      if (window.WFModals && typeof WFModals.unlockScrollIfNoneOpen === 'function') {
        WFModals.unlockScrollIfNoneOpen();
      } else {
        document.body.classList.remove('modal-open');
        document.documentElement.classList.remove('modal-open');
      }
    } catch (e) {
      console.warn('[PaymentPage] Overlay cleanup skipped', e);
    }
  })();

  // Elements
  const addressListEl = document.getElementById('addressList');
  const addToggleBtn = document.getElementById('addAddressToggle');
  const addFormEl = document.getElementById('addAddressForm');
  const saveAddrBtn = document.getElementById('saveAddressBtn');
  const cancelAddrBtn = document.getElementById('cancelAddressBtn');
  const shipMethodSel = document.getElementById('shippingMethodSelect');
  const pmSquare = document.getElementById('pm-square');
  const pmSquareNote = document.getElementById('pmSquareNote');
  const orderItemsEl = document.getElementById('orderItems');
  const orderSubtotalEl = document.getElementById('orderSubtotal');
  const orderShippingEl = document.getElementById('orderShipping');
  const orderTaxEl = document.getElementById('orderTax');
  const orderTotalEl = document.getElementById('orderTotal');
  const placeOrderBtn = document.getElementById('placeOrderBtn');
  const errorEl = document.getElementById('checkoutError');
  const cardWrapEl = document.getElementById('cardContainerWrap');
  const cardContainerEl = document.getElementById('card-container');
  const cardErrorsEl = document.getElementById('card-errors');
  // Badge elements inside the Shipping Method card
  const pickupBadgeEl = document.getElementById('pickupBadge');
  const localDeliveryBadgeEl = document.getElementById('localDeliveryBadge');

  // Persistent shipping policy note under dropdown (grey by default, brand-secondary when active)
  const shippingPolicyNoteEl = document.getElementById('shippingPolicyNote');
  function updateShippingPolicyNote(method) {
    const m = (method || shipMethodSel?.value || '').trim();
    if (!shippingPolicyNoteEl) return;
    try {
      shippingPolicyNoteEl.classList.toggle('is-active', m === 'USPS');
    } catch (_) {}
  }

  function updateShippingBadges(method) {
    const m = method || (shipMethodSel?.value || '').trim();
    try {
      if (pickupBadgeEl) pickupBadgeEl.classList.toggle('hidden', m !== 'Customer Pickup');
      if (localDeliveryBadgeEl) localDeliveryBadgeEl.classList.toggle('hidden', m !== 'Local Delivery');
    } catch (_) {}
  }

  // Lightweight tooltip enhancer for elements with data-tooltip
  function initTooltips() {
    try {
      document.querySelectorAll('[data-tooltip]').forEach((el) => {
        if (!el.classList.contains('wf-tooltip')) el.classList.add('wf-tooltip');
      });
    } catch (_) {}
  }

  // Ensure shipping method defaults to USPS on load if not already selected
  try {
    if (shipMethodSel) {
      const cur = (shipMethodSel.value || '').trim();
      if (!cur || cur === 'Customer Pickup') {
        const uspsOpt = Array.from(shipMethodSel.options || []).find(o => (o.value || o.text).toString().toUpperCase() === 'USPS');
        if (uspsOpt) {
          shipMethodSel.value = uspsOpt.value;
        } else {
          // Fallback: set to literal 'USPS' if option values are labels
          shipMethodSel.value = 'USPS';
        }
        // Trigger change to recalc pricing with the correct default
        try { shipMethodSel.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
      }
    }
  } catch (_) {}

  // Address form fields
  const fName = document.getElementById('addr_name');
  const fL1 = document.getElementById('addr_line1');
  const fL2 = document.getElementById('addr_line2');
  const fCity = document.getElementById('addr_city');
  const fState = document.getElementById('addr_state');
  const fZip = document.getElementById('addr_zip');
  const fDefault = document.getElementById('addr_default');

  let userId = body.dataset.userId || null;
  if (!userId) {
    console.warn('[PaymentPage] No userId yet; awaiting login-success before loading addresses.');
  }

  let cartApi = getCartApi();
  if (!cartApi) {
    console.warn('[PaymentPage] Cart API not ready; waiting for initialization');
    const onCartInit = async () => {
      cartApi = getCartApi();
      if (cartApi) {
        window.removeEventListener('cartUpdated', onCartInit);
        await resyncCart('cart-init');
      }
    };
    // CartSystem emits a 'cartUpdated' event with action: 'init' during its init()
    window.addEventListener('cartUpdated', onCartInit);
    // Fallback: attempt once more after a short delay in case the event was missed
    setTimeout(onCartInit, 400);
    // Additional readiness guard in case init event is missed or delayed
    waitForCartReady(1200).then((api) => { if (api) resyncCart('cart-ready-promise'); });
  }

  let addresses = [];
  let selectedAddressId = null;
  let pricing = { subtotal: 0, shipping: 0, tax: 0, total: 0 };

  // Resolve userId proactively using whoami if it's missing
  async function resolveUserIdWithFallback(reason = 'init') {
    try {
      if (userId) return userId;
      const res = await apiClient.get('/api/whoami.php');
      const sid = res?.userId;
      if (sid != null && String(sid) !== '') {
        userId = String(sid);
        try {
          document.body?.setAttribute('data-user-id', userId);
        } catch (_) {}
        console.debug('[PaymentPage] userId resolved via whoami', { reason, userId });
        return userId;
      }
    } catch (e) {
      console.warn('[PaymentPage] whoami failed to resolve userId', e);
    }
    return null;
  }

  // While the receipt is open, suppress reactive updates on this page
  function isReceiptOpen() {
    try { return window.__wfReceiptOpen === true; } catch (_) { return false; }
  }

  // Square state
  const sq = {
    enabled: false,
    applicationId: null,
    environment: 'sandbox',
    locationId: null,
    payments: null,
    card: null,
    sdkLoaded: false,
  };

  function setError(msg) {
    if (!errorEl) return;
    if (!msg) {
      errorEl.classList.add('hidden');
      errorEl.textContent = '';
    } else {
      errorEl.textContent = msg;
      errorEl.classList.remove('hidden');
    }
  }

  function getSelectedPaymentMethod() {
    const el = document.querySelector('input[name="paymentMethod"]:checked');
    return el ? el.value : null;
  }

  function ensurePlaceButtonState() {
    const api = getCartApi();
    const items = api?.getItems ? api.getItems() : getStoredCartItems();
    const hasItems = Array.isArray(items) && items.length > 0;
    const pm = getSelectedPaymentMethod();
    const method = shipMethodSel?.value || 'USPS';
    // If shipping method requires shipping, ensure address selected
    const needsAddress = method !== 'Customer Pickup';
    const okAddress = !needsAddress || !!selectedAddressId;

    const enabled = hasItems && !!pm && okAddress;
    if (placeOrderBtn) {
      placeOrderBtn.disabled = !enabled;
    }
  }

  // Force-reload cart state from storage and refresh UI/pricing
  async function resyncCart(reason = 'manual') {
    try {
      const api = getCartApi();
      if (api && typeof api.refreshFromStorage === 'function') {
        console.debug('[PaymentPage] Resyncing cart from storage due to:', reason);
        api.refreshFromStorage();
        // Keep module-level reference in sync
        cartApi = api;
      }
    } catch (e) {
      console.warn('[PaymentPage] resyncCart failed', e);
    }
    try { renderOrderSummary(); } catch (_) {}
    try { await updatePricing(); } catch (_) {}
    try { ensurePlaceButtonState(); } catch (_) {}
  }

  function renderOrderSummary() {
    if (!orderItemsEl || !orderTotalEl) return;
    const api = getCartApi();
    // Use cart API if ready; otherwise read from localStorage to avoid empty UI
    const items = (api && typeof api.getItems === 'function') ? api.getItems() : getStoredCartItems();

    if (!items.length) {
      orderItemsEl.innerHTML = '<div class="py-4 text-brand-secondary">Your cart is empty.</div>';
      if (orderSubtotalEl) orderSubtotalEl.textContent = currency(0);
      if (orderShippingEl) orderShippingEl.textContent = currency(0);
      if (orderTaxEl) orderTaxEl.textContent = currency(0);
      orderTotalEl.textContent = currency(0);
      ensurePlaceButtonState();
      return;
    }

    const html = items.map((it) => {
      const qty = Number(it.quantity || 0);
      const price = Number(it.price || 0);
      const line = qty * price;
      const descBits = [];
      if (it.optionSize) descBits.push(it.optionSize);
      if (it.optionColor) descBits.push(it.optionColor);
      const desc = descBits.length ? `<div class="text-xs text-brand-secondary">${descBits.join(' • ')}</div>` : '';
      return `
        <div class="py-3 flex items-start justify-between">
          <div class="pr-3">
            <div class="font-medium">${(it.name || it.sku || '').toString()}</div>
            ${desc}
            <div class="text-xs text-brand-secondary">SKU: ${it.sku}</div>
          </div>
          <div class="text-right min-w-[140px]">
            <div>${qty} × ${currency(price)}</div>
            <div class="font-semibold">${currency(line)}</div>
          </div>
        </div>
      `;
    }).join('');

    orderItemsEl.innerHTML = html;
    // Totals are calculated via backend pricing endpoint
    // Keep last known pricing displayed
    if (orderSubtotalEl) orderSubtotalEl.textContent = currency(pricing.subtotal);
    if (orderShippingEl) orderShippingEl.textContent = currency(pricing.shipping);
    if (orderTaxEl) orderTaxEl.textContent = currency(pricing.tax);
    orderTotalEl.textContent = currency(pricing.total || (api?.getTotal ? api.getTotal() : 0));

    ensurePlaceButtonState();
  }

  function renderAddresses() {
    if (!addressListEl) return;

    if (!addresses.length) {
      addressListEl.innerHTML = '<div class="text-brand-secondary">No addresses yet. Add one to enable shipping.</div>';
      // If user intends to ship, they must add an address
      if (addFormEl) addFormEl.classList.remove('hidden');
      return;
    }

    const html = addresses.map((a) => {
      const id = String(a.id);
      const checked = (selectedAddressId ? String(selectedAddressId) === id : a.is_default == 1);
      if (!selectedAddressId && checked) selectedAddressId = id;
      const line2 = a.address_line2 ? `${a.address_line2}<br/>` : '';
      return `
        <label class="flex items-start gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer">
          <input type="radio" name="shippingAddress" class="form-radio mt-1" value="${id}" ${checked ? 'checked' : ''} />
          <div class="text-sm">
            <div class="font-medium">${a.address_name || 'Address'}</div>
            <div class="text-brand-secondary">
              ${a.address_line1}<br/>
              ${line2}
              ${a.city}, ${a.state} ${a.zip_code}
            </div>
            ${a.is_default == 1 ? '<div class="text-xs text-green-600">Default</div>' : ''}
          </div>
        </label>
      `;
    }).join('');

    addressListEl.innerHTML = html;

    // Wire selection change
    addressListEl.querySelectorAll('input[name="shippingAddress"]').forEach((el) => {
      el.addEventListener('change', (e) => {
        selectedAddressId = e.target.value;
        ensurePlaceButtonState();
        // Re-evaluate Local Delivery eligibility, then recalc pricing
        evaluateLocalDeliveryEligibilityPaymentPage().finally(updatePricing);
      });
    });
  }

  async function loadAddresses() {
    try {
      if (!userId) {
        if (addressListEl) addressListEl.innerHTML = '<div class="text-brand-secondary">Sign in to manage shipping addresses.</div>';
        return;
      }
      if (addressListEl) addressListEl.innerHTML = '<div class="text-brand-secondary">Loading addresses…</div>';
      const url = `/api/customer_addresses.php?action=get_addresses&user_id=${encodeURIComponent(userId)}`;
      const res = await apiClient.get(url);
      addresses = Array.isArray(res?.addresses) ? res.addresses : [];
      // Preselect default
      const def = addresses.find((a) => String(a.is_default) === '1');
      selectedAddressId = def ? String(def.id) : (addresses[0] ? String(addresses[0].id) : null);
      renderAddresses();
      await evaluateLocalDeliveryEligibilityPaymentPage();
      ensurePlaceButtonState();
    } catch (e) {
      console.error('[PaymentPage] Failed to load addresses', e);
      if (addressListEl) addressListEl.innerHTML = '<div class="text-red-600">Failed to load addresses.</div>';
    }
  }

  function toggleAddForm(show) {
    if (!addFormEl) return;
    const shouldShow = typeof show === 'boolean' ? show : addFormEl.classList.contains('hidden');
    addFormEl.classList.toggle('hidden', !shouldShow);
  }

  async function saveAddress() {
    const data = {
      user_id: userId,
      address_name: (fName?.value || '').trim() || 'Address',
      address_line1: (fL1?.value || '').trim(),
      address_line2: (fL2?.value || '').trim(),
      city: (fCity?.value || '').trim(),
      state: (fState?.value || '').trim(),
      zip_code: (fZip?.value || '').trim(),
      is_default: !!(fDefault && fDefault.checked)
    };

    if (!data.address_line1 || !data.city || !data.state || !data.zip_code) {
      setError('Please complete all required address fields.');
      return;
    }

    try {
      setError('');
      saveAddrBtn.disabled = true;
      const res = await apiClient.post('/api/customer_addresses.php?action=add_address', data);
      // Refresh list
      await loadAddresses();
      toggleAddForm(false);
      if (saveAddrBtn) saveAddrBtn.disabled = false;
      // If set default, ensure selection
      if (res?.address_id) selectedAddressId = String(res.address_id);
      ensurePlaceButtonState();
    } catch (e) {
      console.error('[PaymentPage] Failed to save address', e);
      setError(e.message || 'Failed to save address.');
      if (saveAddrBtn) saveAddrBtn.disabled = false;
    }
  }

  async function checkSquareSettings() {
    try {
      const res = await apiClient.get('/api/square_settings.php?action=get_settings');
      const settings = res?.settings || {};
      const enabled = !!(settings && (settings.square_enabled === true || settings.square_enabled === '1' || settings.square_enabled === 1));
      sq.enabled = enabled;
      sq.applicationId = settings.square_application_id || null;
      sq.environment = settings.square_environment || 'sandbox';
      sq.locationId = settings.square_location_id || null;
      if (pmSquare) {
        pmSquare.disabled = !enabled;
      }
      if (pmSquareNote) pmSquareNote.classList.toggle('hidden', !!enabled);

      // Auto-select a default payment method
      const current = getSelectedPaymentMethod();
      if (!current) {
        if (enabled && pmSquare) {
          pmSquare.checked = true;
        } else {
          const cash = document.querySelector('input[name="paymentMethod"][value="Cash"]');
          if (cash) cash.checked = true;
        }
      }

      if (enabled && sq.applicationId && sq.locationId) {
        await ensureSquareSDK();
        await ensureSquareCard();
      }
    } catch (e) {
      console.warn('[PaymentPage] Square settings unavailable; keeping Square disabled');
      if (pmSquare) pmSquare.disabled = true;
      if (pmSquareNote) pmSquareNote.classList.remove('hidden');
    } finally {
      ensurePlaceButtonState();
    }
  }

  async function ensureSquareSDK() {
    if (sq.sdkLoaded) return true;
    await new Promise((resolve, reject) => {
      const existing = document.getElementById('sq-web-payments-sdk');
      if (existing) { sq.sdkLoaded = true; return resolve(true); }
      const s = document.createElement('script');
      s.id = 'sq-web-payments-sdk';
      s.src = 'https://web.squarecdn.com/v1/square.js';
      s.onload = () => { sq.sdkLoaded = true; resolve(true); };
      s.onerror = () => reject(new Error('Failed to load Square SDK'));
      document.head.appendChild(s);
    });
    return true;
  }

  async function ensureSquareCard() {
    try {
      if (!sq.enabled || !sq.applicationId || !sq.locationId) return;
      if (!sq.sdkLoaded || !window.Square) return;
      if (!cardWrapEl || !cardContainerEl) return;
      if (!sq.payments) {
        sq.payments = window.Square.payments(sq.applicationId, sq.locationId);
      }
      if (!sq.card) {
        sq.card = await sq.payments.card();
        await sq.card.attach('#card-container');
      }
      // Show card UI only when Square selected
      const isSquare = getSelectedPaymentMethod() === 'Square';
      cardWrapEl.classList.toggle('hidden', !isSquare);
    } catch (err) {
      console.error('[PaymentPage] Square init failed', err);
      if (cardErrorsEl) {
        cardErrorsEl.textContent = 'Card input failed to initialize.';
        cardErrorsEl.classList.remove('hidden');
      }
    }
  }

  async function updatePricing() {
    if (isReceiptOpen()) {
      console.debug('[PaymentPage] Skipping pricing update while receipt is open');
      return;
    }
    try {
      const api = getCartApi();
      const items = api?.getItems ? api.getItems() : getStoredCartItems();
      // Normalize cart lines -> ensure valid sku and positive quantity
      const lines = (Array.isArray(items) ? items : [])
        .map(i => ({
          sku: (i && (i.sku || i.itemId || i.id || '')).toString(),
          quantity: Number(i && i.quantity != null ? i.quantity : 0)
        }))
        .filter(l => !!l.sku && l.quantity > 0);

      const payload = {
        itemIds: lines.map(l => l.sku),
        quantities: lines.map(l => l.quantity),
        shippingMethod: shipMethodSel?.value || 'USPS',
        debug: true,
      };
      // Optional zip from selected address
      if (selectedAddressId) {
        const addr = addresses.find(a => String(a.id) === String(selectedAddressId));
        if (addr && addr.zip_code) payload.zip = String(addr.zip_code);
      }
      console.debug('[PaymentPage] Pricing request payload', payload);
      const res = await apiClient.post('/api/checkout_pricing.php', payload);
      console.debug('[PaymentPage] Pricing response', res);
      if (res && res.success && res.pricing) {
        pricing = res.pricing;
        if (orderSubtotalEl) orderSubtotalEl.textContent = currency(pricing.subtotal);
        if (orderShippingEl) orderShippingEl.textContent = currency(pricing.shipping);
        if (orderTaxEl) orderTaxEl.textContent = currency(pricing.tax);
        if (orderTotalEl) orderTotalEl.textContent = currency(pricing.total);
        // USPS policy note under selector (persistent; highlight when USPS selected)
        try {
          const methodNow = shipMethodSel?.value || res.pricing.shippingMethod || 'USPS';
          updateShippingBadges(methodNow);
          updateShippingPolicyNote(methodNow);
        } catch (_) {
          // Non-fatal UI hint
        }
        // Sanity check: items exist but subtotal is zero -> likely DB retailPrice is 0 or SKUs mismatch
        const itemsNow = api?.getItems ? api.getItems() : [];
        if (Array.isArray(itemsNow) && itemsNow.length > 0 && Number(pricing.subtotal) === 0) {
          console.warn('[PaymentPage] Backend subtotal is $0.00 despite items present. Verify items.retailPrice in DB for SKUs:', payload.itemIds);
        }
      } else {
        setError((res && (res.error || res.message)) || 'Pricing unavailable.');
      }
    } catch (err) {
      console.warn('[PaymentPage] Pricing update failed', err);
      setError(err?.message || 'Pricing update failed');
    }
  }

  function buildOrderPayload() {
    const api = getCartApi();
    const items = api?.getItems ? api.getItems() : getStoredCartItems();
    const lines = (Array.isArray(items) ? items : [])
      .map(i => ({
        sku: (i && (i.sku || i.itemId || i.id || '')).toString(),
        quantity: Number(i && i.quantity != null ? i.quantity : 0),
        optionColor: i?.optionColor ?? null,
        optionSize: i?.optionSize ?? null,
      }))
      .filter(l => !!l.sku && l.quantity > 0);
    const itemIds = lines.map(l => l.sku);
    const quantities = lines.map(l => l.quantity);
    const colors = lines.map(l => l.optionColor);
    const sizes = lines.map(l => l.optionSize);

    const paymentMethod = getSelectedPaymentMethod();
    const shippingMethod = shipMethodSel?.value || 'USPS';

    let shippingAddress = null;
    if (shippingMethod !== 'Customer Pickup' && selectedAddressId) {
      const a = addresses.find(x => String(x.id) === String(selectedAddressId));
      if (a) {
        shippingAddress = {
          id: a.id,
          address_name: a.address_name,
          address_line1: a.address_line1,
          address_line2: a.address_line2,
          city: a.city,
          state: a.state,
          zip_code: a.zip_code,
          is_default: a.is_default
        };
      }
    }

    const total = Number(pricing.total || (api?.getTotal ? api.getTotal() : 0));

    return {
      customerId: userId,
      itemIds,
      quantities,
      colors,
      sizes,
      paymentMethod,
      total,
      shippingMethod,
      ...(shippingAddress ? { shippingAddress } : {}),
      debug: true,
    };
  }

  async function placeOrder() {
    try {
      setError('');
      placeOrderBtn.disabled = true;
      placeOrderBtn.textContent = 'Placing…';

      const payload = buildOrderPayload();
      console.debug('[PaymentPage] Order payload', payload);

      // Client-side checks
      if (!payload.itemIds.length) throw new Error('Your cart is empty.');
      if (!payload.paymentMethod) throw new Error('Please choose a payment method.');
      if (payload.shippingMethod !== 'Customer Pickup' && !payload.shippingAddress) {
        throw new Error('Please choose a shipping address.');
      }

      // If Square selected, tokenize card first
      if (payload.paymentMethod === 'Square') {
        try {
          await ensureSquareSDK();
          await ensureSquareCard();
          if (!sq.card) throw new Error('Card input unavailable.');
          if (cardErrorsEl) { cardErrorsEl.textContent = ''; cardErrorsEl.classList.add('hidden'); }
          const result = await sq.card.tokenize();
          if (result.status !== 'OK') {
            const msg = (result && result.errors && result.errors[0] && result.errors[0].message) || 'Card tokenization failed';
            throw new Error(msg);
          }
          payload.squareToken = result.token;
        } catch (tokErr) {
          if (cardErrorsEl) { cardErrorsEl.textContent = tokErr.message || 'Card error'; cardErrorsEl.classList.remove('hidden'); }
          throw tokErr;
        }
      }

      const res = await apiClient.post('/api/add_order.php', payload);
      console.debug('[PaymentPage] Order response', res);
      if (res && res.debug) {
        console.info('[PaymentPage] order debug ->', res.debug);
      }
      if (res && res.success && res.orderId) {
        // Open receipt first so the global flag is set before cart events fire
        try { window.WF_ReceiptModal && window.WF_ReceiptModal.open && window.WF_ReceiptModal.open(res.orderId); } catch(_) {}
        // Defer cart clear to the next tick to ensure the receipt flag is active
        try { setTimeout(() => { if (cartApi?.clearCart) cartApi.clearCart(); }, 0); } catch(_) {}
        return;
      }
      throw new Error(res?.error || 'Failed to create order.');
    } catch (e) {
      console.error('[PaymentPage] Order failed', e);
      setError(e.message || 'Order failed.');
      placeOrderBtn.disabled = false;
      placeOrderBtn.textContent = 'Place order';
    }
  }

  // Wire UI events
  if (addToggleBtn) addToggleBtn.addEventListener('click', () => toggleAddForm());
  if (cancelAddrBtn) cancelAddrBtn.addEventListener('click', () => toggleAddForm(false));
  if (saveAddrBtn) saveAddrBtn.addEventListener('click', () => saveAddress());
  if (shipMethodSel) shipMethodSel.addEventListener('change', async () => {
    updateShippingBadges();
    updateShippingPolicyNote();
    initTooltips();
    ensurePlaceButtonState();
    await updatePricing();
  });
  document.querySelectorAll('input[name="paymentMethod"]').forEach((el) => {
    el.addEventListener('change', async () => {
      ensurePlaceButtonState();
      if (getSelectedPaymentMethod() === 'Square') {
        await ensureSquareSDK();
        await ensureSquareCard();
      } else if (cardWrapEl) {
        cardWrapEl.classList.add('hidden');
      }
    });
  });

  if (placeOrderBtn) placeOrderBtn.addEventListener('click', placeOrder);

  // React to cart updates while on page (but ignore while receipt is open)
  const handleCartUpdated = async () => {
    if (isReceiptOpen()) return;
    console.debug('[PaymentPage] cartUpdated event -> refresh summary/pricing');
    renderOrderSummary();
    await updatePricing();
  };
  window.addEventListener('cartUpdated', handleCartUpdated);
  // When receipt modal closes, refresh summary/pricing once
  window.addEventListener('receiptModalClosed', handleCartUpdated);

  // Resync cart state after authentication or common lifecycle events
  window.addEventListener('wf:login-success', async (e) => {
    console.debug('[PaymentPage] wf:login-success received', e?.detail);
    try {
      const newId = e?.detail?.userId || document.body?.dataset?.userId;
      if (newId) userId = String(newId);
    } catch (_) {}
    // Fallback: proactively resolve if still missing
    if (!userId) { await resolveUserIdWithFallback('login-success'); }
    await waitForCartReady(1500);
    await resyncCart('login-success');
    // Schedule a couple of delayed resyncs to catch any late writes/events
    try { setTimeout(() => { resyncCart('login-success-late-200ms'); }, 200); } catch(_) {}
    try { setTimeout(() => { resyncCart('login-success-late-800ms'); }, 800); } catch(_) {}
    loadAddresses();
  });
  window.addEventListener('focus', () => { resyncCart('window-focus'); });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { resyncCart('visibility-visible'); } });
  window.addEventListener('pageshow', (e) => { resyncCart(e && e.persisted ? 'pageshow-bfcache' : 'pageshow'); });

  // Initial loads
  resyncCart('initial');
  checkSquareSettings();
  // Resolve user immediately if the page started already logged in
  resolveUserIdWithFallback('initial').then(() => {
    loadAddresses().finally(() => { updateShippingBadges(); updateShippingPolicyNote(); initTooltips(); updatePricing(); });
  });

  // Observe <body> for login/userId changes to trigger address/pricing reloads
  try {
    const observer = new MutationObserver(async (mutations) => {
      let changed = false;
      for (const m of mutations) {
        if (m.type === 'attributes' && (m.attributeName === 'data-user-id' || m.attributeName === 'data-is-logged-in')) {
          changed = true;
        }
      }
      if (!changed) return;
      const uid = document.body?.dataset?.userId;
      if (uid && String(uid) !== String(userId)) {
        userId = String(uid);
        console.debug('[PaymentPage] userId changed via body attribute', userId);
        await loadAddresses();
        await updatePricing();
      } else if (!uid) {
        // Attempt to recover user id if attribute was not set yet
        const resolved = await resolveUserIdWithFallback('mutation');
        if (resolved) {
          await loadAddresses();
          await updatePricing();
        }
      }
    });
    observer.observe(document.body, { attributes: true, attributeFilter: ['data-user-id', 'data-is-logged-in'] });
  } catch (e) {
    console.warn('[PaymentPage] Failed to attach MutationObserver for login state', e);
  }
})
;
