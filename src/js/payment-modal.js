import apiClient from './api-client.js';

// Payment Modal Overlay — presents the payment step without page navigation
// Reuses global confirmation modal styling and cooperates with login-modal.js

(function initPaymentModal() {
  try {
    if (window.WF_PaymentModal && window.WF_PaymentModal.initialized) return;

    const state = {
      overlay: null,
      container: null,
      keydownHandler: null,
    };

    function currency(v) { return `$${(parseFloat(v) || 0).toFixed(2)}`; }
    function isClientLoggedIn() {
      try { return (document && document.body && document.body.getAttribute('data-is-logged-in') === 'true'); } catch (_) { return false; }
    }

    // --- Shipping helpers: business info, carrier quotes, distance miles ---
    let businessInfo = null; // { business_postal, business_address, business_city, business_state }
    async function ensureBusinessInfo() {
      if (businessInfo) return businessInfo;
      try {
        const res = await apiClient.get('/api/business_settings.php', { action: 'get_business_info' });
        businessInfo = res?.data || res; // endpoint returns success+data
      } catch(_) { businessInfo = {}; }
      return businessInfo;
    }
    async function fetchCarrierRate(carrier) {
      try {
        const bi = await ensureBusinessInfo();
        const fromZip = String(bi?.business_postal || '').trim();
        const addr = await getSelectedShippingAddress();
        const toZip = String(addr?.zip_code || '').trim();
        if (!fromZip || !toZip) return null;
        const items = (cartApi.getItems?.() || []).map(i => ({ sku: (i?.sku||'').toString(), qty: Number(i?.quantity||0), weightOz: Number(i?.weightOz||0)||undefined })).filter(x => x.sku && x.qty>0);
        const resp = await apiClient.post('/api/shipping_rates.php', { items, from: { zip: fromZip }, to: { zip: toZip }, carrier: carrier.toUpperCase(), debug: false });
        const rates = resp?.data?.rates || resp?.rates || [];
        if (!Array.isArray(rates) || rates.length === 0) return null;
        rates.sort((a,b)=>Number(a.amount||0)-Number(b.amount||0));
        return Number(rates[0].amount || 0);
      } catch(_) { return null; }
    }
    async function fetchDrivingMiles() {
      try {
        const bi = await ensureBusinessInfo();
        const from = { address: (bi?.business_address||''), city: (bi?.business_city||''), state: (bi?.business_state||''), zip: (bi?.business_postal||'') };
        const addr = await getSelectedShippingAddress();
        if (!addr) return null;
        const to = { address: (addr.address_line1||''), city: (addr.city||''), state: (addr.state||''), zip: (addr.zip_code||'') };
        const resp = await apiClient.post('/api/distance.php', { from, to });
        const miles = resp?.data?.miles ?? resp?.miles;
        return (miles == null ? null : Number(miles));
      } catch(_) { return null; }
    }
    function pmShowSavings(amount) {
      try {
        const msg = `Free USPS shipping on orders $50+. You save ${currency(amount)}.`;
        pmShowShippingBenefitNote(msg);
      } catch(_) { /* noop */ }
    }

    function ensureOverlay() {
      if (state.overlay && state.container) return;

      const existing = document.getElementById('paymentModalOverlay');
      if (existing) existing.remove();

      const overlay = document.createElement('div');
      overlay.id = 'paymentModalOverlay';
      overlay.className = 'confirmation-modal-overlay checkout-overlay';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');

      const modal = document.createElement('div');
      modal.className = 'confirmation-modal payment-modal animate-slide-in-up';

      overlay.appendChild(modal);
      document.body.appendChild(overlay);

      state.overlay = overlay;
      state.container = modal;

      overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

      if (!state.keydownHandler) {
        state.keydownHandler = (e) => {
          try {
            if (e.key === 'Escape' && state.overlay && state.overlay.classList.contains('show')) {
              close();
            }
          } catch(_) {}
        };
        document.addEventListener('keydown', state.keydownHandler);
      }
    }

    function render() {
      // Full checkout scaffold inside modal (scoped IDs with pm- prefix)
      state.container.innerHTML = `
        <div class="payment-header">
          <h3 class="payment-title">Checkout</h3>
        </div>
        <div class="payment-body">
          <div class="payment-grid">
            <div class="left-col">
              <div class="section-card">
                <h4 class="section-title">Shipping address</h4>
                <div id="pm-addressList" class="space-y-2"></div>
                <div class="mt-2 mt-10">
                  <button id="pm-addAddressToggle" class="modal-button btn-secondary">Add new address</button>
                </div>
                <div id="pm-addAddressForm" class="hidden mt-10">
                  <div class="form-row">
                    <div>
                      <label for="pm-addr_name">Label</label>
                      <input id="pm-addr_name" type="text" placeholder="Home" />
                    </div>
                    <div></div>
                  </div>
                  <div class="form-row">
                    <div>
                      <label for="pm-addr_line1">Address line 1</label>
                      <input id="pm-addr_line1" type="text" />
                    </div>
                    <div>
                      <label for="pm-addr_line2">Address line 2</label>
                      <input id="pm-addr_line2" type="text" />
                    </div>
                  </div>
                  <div class="form-row form-row-3">
                    <div>
                      <label for="pm-addr_city">City</label>
                      <input id="pm-addr_city" type="text" />
                    </div>
                    <div>
                      <label for="pm-addr_state">State</label>
                      <input id="pm-addr_state" type="text" />
                    </div>
                    <div>
                      <label for="pm-addr_zip">ZIP</label>
                      <input id="pm-addr_zip" type="text" />
                    </div>
                  </div>
                  <div class="mt-8 flex items-center gap-8">
                    <input id="pm-addr_default" type="checkbox" />
                    <label for="pm-addr_default">Set as default</label>
                  </div>
                  <div class="mt-10 flex gap-8">
                    <button id="pm-saveAddressBtn" class="modal-button btn-primary">Save address</button>
                    <button id="pm-cancelAddressBtn" class="modal-button btn-secondary">Cancel</button>
                  </div>
                </div>
              </div>

              <div class="section-card mt-14 has-shipping-badges">
                <h4 class="section-title">Shipping method</h4>
                <select id="pm-shippingMethodSelect">
                  <option value="Customer Pickup">Customer Pickup</option>
                  <option value="Local Delivery">Local Delivery</option>
                  <option value="USPS">USPS</option>
                  <option value="FedEx">FedEx</option>
                  <option value="UPS">UPS</option>
                </select>
                <div id="pm-shippingBadges" class="shipping-badges">
                  <span id="pm-pickupBadge" class="hidden shipping-badge pickup" aria-live="polite">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" role="img" aria-label="Customer Pickup">
                      <rect x="0" y="0" width="100" height="100" rx="10" ry="10" fill="transparent" />
                      <text x="50" y="42" text-anchor="middle" font-family="Merienda, Nunito, sans-serif" font-size="18" font-weight="700" fill="currentColor">PICKUP</text>
                      <text x="50" y="64" text-anchor="middle" font-family="Merienda, Nunito, sans-serif" font-size="12" font-weight="700" fill="currentColor">NO FEE</text>
                    </svg>
                    <span class="sr-only">No shipping charge for Customer Pickup</span>
                  </span>
                  <span id="pm-localDeliveryBadge" class="hidden shipping-badge local wf-tooltip" data-tooltip="Local Delivery is offered at the business owner’s discretion and may depend on availability, scheduling, and distance from your location." aria-live="polite">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" role="img" aria-label="Local Delivery $75">
                      <rect x="0" y="0" width="100" height="100" rx="10" ry="10" fill="transparent" />
                      <text x="50" y="42" text-anchor="middle" font-family="Merienda, Nunito, sans-serif" font-size="18" font-weight="700" fill="currentColor">LOCAL</text>
                      <text x="50" y="64" text-anchor="middle" font-family="Merienda, Nunito, sans-serif" font-size="14" font-weight="800" fill="currentColor">$75</text>
                    </svg>
                    <span class="sr-only">Local Delivery fee: $75</span>
                  </span>
                </div>
                <p class="hint mt-8">Select a method. Address is required for delivery and carriers.</p>
              </div>

              <div class="section-card mt-14">
                <h4 class="section-title">Payment</h4>
                <div class="radio-row" role="radiogroup" aria-label="Payment method">
                  <label><input type="radio" name="pm-paymentMethod" value="Square" id="pm-pm-square" /> Square (card)</label>
                  <label><input type="radio" name="pm-paymentMethod" value="Cash" id="pm-pm-cash" /> Cash</label>
                </div>
                <div id="pmSquareNote" class="hint mt-6">Square is currently unavailable.</div>
                <div id="pm-cardContainerWrap" class="hidden mt-10">
                  <div id="pm-card-container"></div>
                  <div id="pm-card-errors" class="error hidden mt-6"></div>
                </div>
              </div>
            </div>

            <div class="right-col">
              <div class="section-card">
                <h4 class="section-title">Order summary</h4>
                <div id="pm-orderItems" class="summary-lines"></div>
                <div class="summary-lines mt-10">
                  <div class="summary-line"><span class="label">Subtotal</span> <span id="pm-orderSubtotal" class="value">$0.00</span></div>
                  <div class="summary-line"><span class="label">Shipping</span> <span id="pm-orderShipping" class="value">$0.00</span></div>
                  <div class="summary-line"><span class="label">Tax</span> <span id="pm-orderTax" class="value">$0.00</span></div>
                  <div class="summary-line summary-total"><span class="label">Total</span> <span id="pm-orderTotal" class="value">$0.00</span></div>
                </div>
              </div>
            </div>
          </div>
          <div id="pm-checkoutError" class="error hidden mt-8"></div>
        </div>
        <div class="confirmation-modal-footer payment-footer">
          <button class="confirmation-modal-button cancel" id="pm-cancelBtn">Cancel</button>
          <button class="confirmation-modal-button confirm" id="pm-placeOrderBtn" disabled>Place order</button>
        </div>
      `;

      setupController();
    }

    function setupController() {
      const cartApi = window.WF_Cart || window.cart || null;
      let userId = (() => {
        try {
          const v = document.body?.dataset?.userId;
          return v ? String(v) : null;
        } catch (_) { return null; }
      })();
      const q = (sel) => state.container.querySelector(sel);

      // Elements (scoped)
      const addressListEl = q('#pm-addressList');
      const addToggleBtn = q('#pm-addAddressToggle');
      const addFormEl = q('#pm-addAddressForm');
      const saveAddrBtn = q('#pm-saveAddressBtn');
      const cancelAddrBtn = q('#pm-cancelAddressBtn');
      const shipMethodSel = q('#pm-shippingMethodSelect');
      const pmSquare = q('#pm-pm-square');
      const pmSquareNote = q('#pmSquareNote');
      const orderItemsEl = q('#pm-orderItems');
      const orderSubtotalEl = q('#pm-orderSubtotal');
      const orderShippingEl = q('#pm-orderShipping');
      const orderTaxEl = q('#pm-orderTax');
      const orderTotalEl = q('#pm-orderTotal');
      const placeOrderBtn = q('#pm-placeOrderBtn');
      const errorEl = q('#pm-checkoutError');
      const cardWrapEl = q('#pm-cardContainerWrap');
      const cardContainerEl = q('#pm-card-container');
      const cardErrorsEl = q('#pm-card-errors');
      const cancelBtn = q('#pm-cancelBtn');
      const pickupBadgeEl = q('#pm-pickupBadge');
      const localDeliveryBadgeEl = q('#pm-localDeliveryBadge');
      // USPS policy note element (always visible; grey by default; brand-secondary when active)
      let pmShippingBenefitNoteEl = null;
      function ensurePmShippingBenefitNote() {
        if (pmShippingBenefitNoteEl && state.container.contains(pmShippingBenefitNoteEl)) return pmShippingBenefitNoteEl;
        const sel = shipMethodSel;
        if (!sel) return null;
        pmShippingBenefitNoteEl = document.createElement('div');
        pmShippingBenefitNoteEl.id = 'pm-shippingBenefitNote';
        pmShippingBenefitNoteEl.className = 'shipping-policy-note';
        pmShippingBenefitNoteEl.textContent = 'Free USPS shipping on orders $50+.';
        try { sel.insertAdjacentElement('afterend', pmShippingBenefitNoteEl); } catch(_) {}
        return pmShippingBenefitNoteEl;
      }
      function pmShowShippingBenefitNote(msg) {
        const el = ensurePmShippingBenefitNote();
        if (!el) return;
        el.textContent = msg || 'Free USPS shipping on orders $50+';
        el.classList.add('is-active');
      }
      function pmHideShippingBenefitNote() {
        const el = ensurePmShippingBenefitNote();
        if (!el) return;
        el.classList.remove('is-active');
      }

      // Ensure shipping method defaults to USPS on load if not already selected
      try {
        if (shipMethodSel && (!shipMethodSel.value || shipMethodSel.selectedIndex < 0)) {
          const uspsOpt = Array.from(shipMethodSel.options || []).find(o => (o.value || o.text).toString().toUpperCase() === 'USPS');
          if (uspsOpt) { shipMethodSel.value = uspsOpt.value; }
          else { shipMethodSel.value = 'USPS'; }
          try { shipMethodSel.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
        }
      } catch (_) {}

      // Address form fields
      const fName = q('#pm-addr_name');
      const fL1 = q('#pm-addr_line1');
      const fL2 = q('#pm-addr_line2');
      const fCity = q('#pm-addr_city');
      const fState = q('#pm-addr_state');
      const fZip = q('#pm-addr_zip');
      const fDefault = q('#pm-addr_default');

      if (cancelBtn) cancelBtn.addEventListener('click', close);

      if (!cartApi) {
        if (errorEl) {
          errorEl.textContent = 'Checkout unavailable. Cart system not ready.';
          errorEl.classList.remove('hidden');
        }
        return;
      }

      // Controller state
      let addresses = [];
      let selectedAddressId = null;
      let pricing = { subtotal: 0, shipping: 0, tax: 0, total: 0 };
      const sq = { enabled: false, applicationId: null, environment: 'sandbox', locationId: null, payments: null, card: null, sdkLoaded: false };

      function isReceiptOpen() {
        try { return window.__wfReceiptOpen === true; } catch (_) { return false; }
      }

      function setError(msg) {
        if (!errorEl) return;
        if (!msg) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }
        else { errorEl.textContent = msg; errorEl.classList.remove('hidden'); }
      }

      function getSelectedPaymentMethod() {
        const el = state.container.querySelector('input[name="pm-paymentMethod"]:checked');
        return el ? el.value : null;
      }

      function ensurePlaceButtonState() {
        const items = cartApi.getItems ? cartApi.getItems() : [];
        const hasItems = Array.isArray(items) && items.length > 0;
        const pm = getSelectedPaymentMethod();
        const method = shipMethodSel?.value || 'USPS';
        const needsAddress = method !== 'Customer Pickup';
        const okAddress = !needsAddress || !!selectedAddressId;
        const hasUser = !!userId;
        if (placeOrderBtn) placeOrderBtn.disabled = !(hasItems && !!pm && okAddress && hasUser);
      }

      // Toggle in-modal shipping badges
      function updateShippingBadges(method) {
        const m = (method || shipMethodSel?.value || '').trim();
        try {
          if (pickupBadgeEl) pickupBadgeEl.classList.toggle('hidden', m !== 'Customer Pickup');
          if (localDeliveryBadgeEl) localDeliveryBadgeEl.classList.toggle('hidden', m !== 'Local Delivery');
        } catch (_) {}
      }

      // Lightweight tooltip enhancer within modal scope
      function initTooltips() {
        try {
          state.container.querySelectorAll('[data-tooltip]').forEach((el) => {
            if (!el.classList.contains('wf-tooltip')) el.classList.add('wf-tooltip');
          });
        } catch (_) {}
      }

      function renderOrderSummary() {
        if (!orderItemsEl || !orderTotalEl) return;
        const items = cartApi.getItems ? cartApi.getItems() : [];
        if (!items.length) {
          orderItemsEl.innerHTML = '<div class="hint">Your cart is empty.</div>';
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
          const desc = descBits.length ? `<div class="hint">${descBits.join(' • ')}</div>` : '';
          return `
            <div class="summary-line align-start">
              <div class="label flex-1">${(it.name || it.sku || '').toString()}${desc}</div>
              <div class="value min-w-140 text-right">${qty} × ${currency(price)}<div class="fw-800">${currency(line)}</div></div>
            </div>
          `;
        }).join('');
        orderItemsEl.innerHTML = html;
        if (orderSubtotalEl) orderSubtotalEl.textContent = currency(pricing.subtotal);
        if (orderShippingEl) orderShippingEl.textContent = currency(pricing.shipping);
        if (orderTaxEl) orderTaxEl.textContent = currency(pricing.tax);
        orderTotalEl.textContent = currency(pricing.total || (cartApi.getTotal ? cartApi.getTotal() : 0));
        ensurePlaceButtonState();
      }

      function renderAddresses() {
        if (!addressListEl) return;
        if (!addresses.length) {
          addressListEl.innerHTML = '<div class="hint">No addresses yet. Add one to enable shipping.</div>';
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
              <input type="radio" name="pm-shippingAddress" class="form-radio mt-1" value="${id}" ${checked ? 'checked' : ''} />
              <div class="text-sm">
                <div class="font-medium">${a.address_name || 'Address'}</div>
                <div class="hint">
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
        addressListEl.querySelectorAll('input[name="pm-shippingAddress"]').forEach((el) => {
          el.addEventListener('change', (e) => {
            selectedAddressId = e.target.value;
            ensurePlaceButtonState();
            // Recompute pricing when address (zip) changes
            updatePricing();
          });
        });
      }

      async function loadAddresses() {
        try {
          // Refresh userId from body in case it was set after login
          try {
            const v = document.body?.dataset?.userId;
            if (v) userId = String(v);
          } catch (_) {}
          // If we appear logged-in but userId not yet populated, retry briefly
          if (!userId && isClientLoggedIn()) {
            if (addressListEl) addressListEl.innerHTML = '<div class="hint">Loading your account…</div>';
            for (let i = 0; i < 6 && !userId; i++) {
              await new Promise(r => setTimeout(r, 250));
              try {
                const v2 = document.body?.dataset?.userIdRaw || document.body?.dataset?.userId;
                if (v2) userId = String(v2);
              } catch (_) {}
            }
            
          }
          if (!userId) {
            // Final fallback: ask server session who we are
            try {
              const who = await apiClient.get('/api/whoami.php');
              const sid = who?.userId;
              if (sid != null && String(sid) !== '') {
                userId = String(sid);
                try {
                  if (document && document.body) {
                    document.body.setAttribute('data-user-id', userId);
                    document.body.setAttribute('data-is-logged-in', 'true');
                  }
                } catch(_) {}
              }
            } catch (e) {
              console.warn('[PaymentModal] loadAddresses: session fallback failed', e);
            }
            if (!userId) {
              console.warn('[PaymentModal] loadAddresses: missing userId. body.dataset=', document.body?.dataset || {});
              if (addressListEl) addressListEl.innerHTML = '<div class="hint">Sign in to manage shipping addresses.</div>';
              // Proactively prompt login if available, then retry
              try {
                if (typeof window.openLoginModal === 'function') {
                  const desiredReturn = window.location.pathname + window.location.search + window.location.hash;
                  window.openLoginModal(desiredReturn, {
                    suppressRedirect: true,
                    onSuccess: async (_info) => {
                      try { if (document && document.body) document.body.setAttribute('data-is-logged-in','true'); } catch(_) {}
                      try {
                        const who2 = await apiClient.get('/api/whoami.php');
                        const sid2 = who2?.userId;
                if (sid2 != null && String(sid2) !== '') {
                  userId = String(sid2);
                  try { document.body?.setAttribute('data-user-id', userId); } catch(_) {}
                  await loadAddresses();
                  await updatePricing();
                  return;
                }
              } catch(_) {}
                      // As a fallback, just retry loading
                      await loadAddresses();
                    }
                  });
                }
              } catch(_) {}
              return;
            }
          }
          // userId can be alphanumeric; just ensure it's non-empty
          if (addressListEl) addressListEl.innerHTML = '<div class="hint">Loading addresses…</div>';
          const url = `/api/customer_addresses.php?action=get_addresses&user_id=${encodeURIComponent(userId)}`;
          const res = await apiClient.get(url);
          addresses = Array.isArray(res?.addresses) ? res.addresses : [];
          const def = addresses.find((a) => String(a.is_default) === '1');
          selectedAddressId = def ? String(def.id) : (addresses[0] ? String(addresses[0].id) : null);
          renderAddresses();
          ensurePlaceButtonState();
        } catch (e) {
          console.error('[PaymentModal] Failed to load addresses', e);
          if (addressListEl) addressListEl.innerHTML = '<div class="error">Failed to load addresses.</div>';
        }
      }

      // Resolve userId robustly: check dataset, brief retry, then server session
      async function resolveUserIdWithFallback() {
        try {
          const v = document.body?.dataset?.userId;
          if (v) userId = String(v);
        } catch(_) {}
        if (userId) return userId;
        if (isClientLoggedIn()) {
          for (let i = 0; i < 6 && !userId; i++) {
            await new Promise(r => setTimeout(r, 150));
            try {
              const v2 = document.body?.dataset?.userIdRaw || document.body?.dataset?.userId;
              if (v2) userId = String(v2);
            } catch(_) {}
          }
          if (!userId) {
            try {
              const who = await apiClient.get('/api/whoami.php');
              const sid = who?.userId;
              if (sid != null && String(sid) !== '') {
                userId = String(sid);
                try {
                  if (document && document.body) {
                    document.body.setAttribute('data-user-id', userId);
                  }
                } catch(_) {}
              }
            } catch (e) {
              console.warn('[PaymentModal] resolveUserIdWithFallback: session call failed', e);
            }
          }
        }
        return userId || null;
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
        if (!data.address_line1 || !data.city || !data.state || !data.zip_code) { setError('Please complete all required address fields.'); return; }
        try {
          setError('');
          if (saveAddrBtn) saveAddrBtn.disabled = true;
          const res = await apiClient.post('/api/customer_addresses.php?action=add_address', data);
          await loadAddresses();
          toggleAddForm(false);
          if (saveAddrBtn) saveAddrBtn.disabled = false;
          if (res?.address_id) selectedAddressId = String(res.address_id);
          ensurePlaceButtonState();
        } catch (e) {
          console.error('[PaymentModal] Failed to save address', e);
          setError(e.message || 'Failed to save address.');
          if (saveAddrBtn) saveAddrBtn.disabled = false;
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
          if (!sq.payments) { sq.payments = window.Square.payments(sq.applicationId, sq.locationId); }
          if (!sq.card) { sq.card = await sq.payments.card(); await sq.card.attach('#pm-card-container'); }
          const isSquare = getSelectedPaymentMethod() === 'Square';
          cardWrapEl.classList.toggle('hidden', !isSquare);
        } catch (err) {
          console.error('[PaymentModal] Square init failed', err);
          if (cardErrorsEl) { cardErrorsEl.textContent = 'Card input failed to initialize.'; cardErrorsEl.classList.remove('hidden'); }
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
          if (pmSquare) pmSquare.disabled = !enabled;
          if (pmSquareNote) pmSquareNote.classList.toggle('hidden', !!enabled);
          const current = getSelectedPaymentMethod();
          if (!current) {
            if (enabled && pmSquare) (pmSquare.checked = true);
            else {
              const cash = state.container.querySelector('input[name="pm-paymentMethod"][value="Cash"]');
              if (cash) cash.checked = true;
            }
          }
          if (enabled && sq.applicationId && sq.locationId) { await ensureSquareSDK(); await ensureSquareCard(); }
        } catch (e) {
          console.warn('[PaymentModal] Square settings unavailable; keeping Square disabled');
          if (pmSquare) pmSquare.disabled = true;
          if (pmSquareNote) pmSquareNote.classList.remove('hidden');
        } finally {
          ensurePlaceButtonState();
        }
      }

      async function updatePricing() {
        if (isReceiptOpen()) {
          console.debug('[PaymentModal] Skipping pricing update while receipt is open');
          return;
        }
        try {
          const items = cartApi.getItems ? cartApi.getItems() : [];
          const clientSubtotal = (Array.isArray(items) ? items : []).reduce((sum, it) => sum + (Number(it?.price)||0) * (Number(it?.quantity)||0), 0);
          // Normalize cart lines to ensure valid sku and positive quantity
          const lines = (Array.isArray(items) ? items : [])
            .map(i => ({
              sku: (i && (i.sku || i.itemId || i.id || '')).toString(),
              quantity: Number(i && i.quantity != null ? i.quantity : 0)
            }))
            .filter(l => !!l.sku && l.quantity > 0);
          // Warn if any items lack a SKU and would be excluded
          if (Array.isArray(items) && items.length > lines.length) {
            const missing = items.filter(i => !i || !(i.sku || i.itemId || i.id)).map(i => ({ raw: i }));
            console.warn('[PaymentModal] Some cart items are missing a SKU and were excluded from pricing payload.', { missingCount: items.length - lines.length, missing });
          }
          const payload = {
            itemIds: lines.map(l => l.sku),
            quantities: lines.map(l => l.quantity),
            shippingMethod: shipMethodSel?.value || 'USPS',
          };
          if (selectedAddressId) {
            const addr = addresses.find(a => String(a.id) === String(selectedAddressId));
            if (addr && addr.zip_code) payload.zip = String(addr.zip_code);
          }
          // Always request backend pricing debug during current checkout debugging
          // This surfaces taxEnabled, taxRate, taxBase, and computed subtotal/shipping.
          payload.debug = true;
          const res = await apiClient.post('/api/checkout_pricing.php', payload);
          console.debug('[PaymentModal] pricing request ->', payload, 'response ->', res);
          if (res && res.success && res.pricing) {
            pricing = res.pricing;
            // Apply client-side fallbacks when server returns $0 but we have priced items
            let dispSubtotal = Number(pricing.subtotal || 0);
            if (dispSubtotal === 0 && clientSubtotal > 0) dispSubtotal = clientSubtotal;
            let dispShipping = Number(pricing.shipping || 0);
            const methodNow = shipMethodSel?.value || pricing.shippingMethod || 'USPS';
            // Local Delivery: enforce eligibility and $2/mile when possible
            if (methodNow === 'Local Delivery') {
              try {
                const miles = await fetchDrivingMiles();
                if (miles == null || miles > 30) {
                  // Ineligible; switch to USPS
                  if (shipMethodSel) shipMethodSel.value = 'USPS';
                  pmHideShippingBenefitNote();
                } else {
                  dispShipping = Math.round(miles * 2 * 100) / 100;
                }
              } catch(_) { dispShipping = 75.00; }
            }
            // Carrier quotes when available
            if (methodNow === 'USPS') {
              const quote = await fetchCarrierRate('USPS');
              // Savings badge when eligible (subtotal >= 50)
              if (dispSubtotal >= 50 && quote != null && quote > 0) { pmShowSavings(quote); }
              else { pmHideShippingBenefitNote(); }
              // For sub-$50 orders, prefer live quote if present
              if (dispSubtotal < 50 && quote != null && quote > 0) dispShipping = quote;
            } else if (methodNow === 'UPS' || methodNow === 'FedEx') {
              const quote = await fetchCarrierRate(methodNow);
              if (quote != null && quote > 0) dispShipping = quote; else if (dispShipping === 0) dispShipping = 20.00;
              pmHideShippingBenefitNote();
            }
            // Determine tax using server’s tax flags if available
            let dispTax = Number(pricing.tax || 0);
            if (res.debug) {
              const rate = Number(res.debug.taxRate || 0);
              const taxShip = !!res.debug.taxShipping;
              const base = dispSubtotal + (taxShip ? dispShipping : 0);
              if (rate > 0) dispTax = Math.round(base * rate * 100) / 100;
            }
            const dispTotal = Math.round((dispSubtotal + dispShipping + dispTax) * 100) / 100;

            if (orderSubtotalEl) orderSubtotalEl.textContent = currency(dispSubtotal);
            if (orderShippingEl) orderShippingEl.textContent = currency(dispShipping);
            if (orderTaxEl) orderTaxEl.textContent = currency(dispTax);
            if (orderTotalEl) orderTotalEl.textContent = currency(dispTotal);
            if (res.debug) {
              console.info('[PaymentModal] pricing debug ->', res.debug);
            }
            // Toggle badges and USPS-only free shipping hint (subtotal >= $50)
            try {
              const methodNow = shipMethodSel?.value || res.pricing.shippingMethod || 'USPS';
              updateShippingBadges(methodNow);
              const eligible = (methodNow === 'USPS' && Number(dispSubtotal) >= 50);
              if (!eligible) { pmHideShippingBenefitNote(); }
            } catch(_) {}
            // Warn if we have items but backend computed subtotal is zero
            const hasItems = Array.isArray(items) && items.length > 0;
            if (hasItems && Number(pricing.subtotal) === 0) {
              console.warn('[PaymentModal] Subtotal returned as $0.00 despite items present. Possible SKU/price mismatch in DB.', {
                skus: payload.itemIds,
                quantities: payload.quantities,
                shippingMethod: payload.shippingMethod,
                debug: res.debug || null
              });
            }
          }
        } catch (err) {
          console.warn('[PaymentModal] Pricing update failed', err);
          setError(`Pricing update failed: ${err?.message || 'Unknown error'}`);
        }
      }

      function buildOrderPayload() {
        const items = cartApi.getItems ? cartApi.getItems() : [];
        const lines = (Array.isArray(items) ? items : [])
          .map(i => ({
            sku: (i && (i.sku || i.itemId || i.id || '')).toString(),
            quantity: Number(i && i.quantity != null ? i.quantity : 0),
            optionColor: i?.optionColor ?? null,
            optionSize: i?.optionSize ?? null,
          }))
          .filter(l => !!l.sku && l.quantity > 0);
        // Warn if any items lack a SKU and would be excluded
        if (Array.isArray(items) && items.length > lines.length) {
          const missing = items.filter(i => !i || !(i.sku || i.itemId || i.id)).map(i => ({ raw: i }));
          console.warn('[PaymentModal] Some cart items are missing a SKU and were excluded from order payload.', { missingCount: items.length - lines.length, missing });
        }
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
            shippingAddress = { id: a.id, address_name: a.address_name, address_line1: a.address_line1, address_line2: a.address_line2, city: a.city, state: a.state, zip_code: a.zip_code, is_default: a.is_default };
          }
        }
        const total = Number(pricing.total || (cartApi.getTotal ? cartApi.getTotal() : 0));
        const payload = { customerId: userId, itemIds, quantities, colors, sizes, paymentMethod, total, shippingMethod, ...(shippingAddress ? { shippingAddress } : {}) };
        payload.debug = true;
        return payload;
      }

      async function placeOrder() {
        try {
          setError('');
          if (placeOrderBtn) { placeOrderBtn.disabled = true; placeOrderBtn.textContent = 'Placing…'; }
          if (!userId) {
            // Require login before order placement; attempt to open login modal if available
            if (typeof window.openLoginModal === 'function') {
              const desiredReturn = window.location.pathname + window.location.search + window.location.hash;
              window.openLoginModal(desiredReturn, {
                suppressRedirect: true,
                onSuccess: () => {
                  try { if (document && document.body) document.body.setAttribute('data-is-logged-in', 'true'); } catch (_) {}
                  // Refresh userId from body in case login response populated it
                  try { userId = document.body?.dataset?.userId || userId; } catch(_) {}
                  // Refresh addresses and pricing after login, then re-enable button for user to click again
                  loadAddresses();
                  ensurePlaceButtonState();
                  updatePricing();
                }
              });
              throw new Error('Please sign in to place your order.');
            } else {
              throw new Error('Please sign in to place your order.');
            }
          }
          const payload = buildOrderPayload();
          if (!payload.itemIds.length) throw new Error('Your cart is empty.');
          if (!payload.paymentMethod) throw new Error('Please choose a payment method.');
          if (payload.shippingMethod !== 'Customer Pickup' && !payload.shippingAddress) throw new Error('Please choose a shipping address.');
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
          console.debug('[PaymentModal] order request ->', payload, 'response ->', res);
          if (res && res.debug) {
            console.info('[PaymentModal] order debug ->', res.debug);
          }
          if (res && res.success && res.orderId) {
            // Open receipt first so the global flag is set before cart events fire
            try { window.WF_ReceiptModal && window.WF_ReceiptModal.open && window.WF_ReceiptModal.open(res.orderId); } catch(_) {}
            // Defer cart clear to the next tick to ensure the receipt flag is active
            try { setTimeout(() => { cartApi.clearCart && cartApi.clearCart(); }, 0); } catch(_) {}
            // Close the payment modal UI
            try { close(); } catch(_) {}
            return;
          }
          const serverMsg = (res && typeof res === 'object' && (res.error || res.message)) ? (res.error || res.message) : (typeof res === 'string' ? res : null);
          throw new Error(serverMsg || 'Failed to create order.');
        } catch (e) {
          console.error('[PaymentModal] Order failed', e);
          setError(e.message || 'Order failed.');
          if (placeOrderBtn) { placeOrderBtn.disabled = false; placeOrderBtn.textContent = 'Place order'; }
        }
      }

      // Wire UI events
      if (addToggleBtn) addToggleBtn.addEventListener('click', () => toggleAddForm());
      if (cancelAddrBtn) cancelAddrBtn.addEventListener('click', () => toggleAddForm(false));
      if (saveAddrBtn) saveAddrBtn.addEventListener('click', () => saveAddress());
      if (shipMethodSel) shipMethodSel.addEventListener('change', async () => {
        updateShippingBadges();
        initTooltips();
        const itemsNow = cartApi.getItems ? cartApi.getItems() : [];
        const subNow = (Array.isArray(itemsNow) ? itemsNow : []).reduce((s, it) => s + (Number(it?.price)||0) * (Number(it?.quantity)||0), 0);
        let shipNow = 0;
        if (shipMethodSel.value === 'Local Delivery') {
          const miles = await fetchDrivingMiles();
          if (miles == null || miles > 30) { shipNow = 0; if (shipMethodSel) shipMethodSel.value = 'USPS'; pmHideShippingBenefitNote(); }
          else { shipNow = Math.round(miles * 2 * 100) / 100; }
        } else if (shipMethodSel.value === 'USPS') {
          if (subNow >= 50) { shipNow = 0; const q = await fetchCarrierRate('USPS'); if (q != null && q > 0) pmShowSavings(q); }
          else { const q = await fetchCarrierRate('USPS'); shipNow = (q != null && q > 0) ? q : (Number(pricing?.shipping) || 0); pmHideShippingBenefitNote(); }
        } else if (shipMethodSel.value === 'FedEx' || shipMethodSel.value === 'UPS') {
          const q = await fetchCarrierRate(shipMethodSel.value);
          shipNow = (q != null && q > 0) ? q : (Number(pricing?.shipping) || 20);
          pmHideShippingBenefitNote();
        }
        const taxRateHint = Number((window.__WF_DEBUG_TAX_RATE ?? 0));
        const taxShip = !!(pricing && pricing.taxShipping);
        const taxNow = taxRateHint > 0 ? Math.round((subNow + (taxShip ? shipNow : 0)) * taxRateHint * 100) / 100 : Number(pricing?.tax || 0);
        if (orderSubtotalEl) orderSubtotalEl.textContent = currency(subNow);
        if (orderShippingEl) orderShippingEl.textContent = currency(shipNow);
        if (orderTaxEl) orderTaxEl.textContent = currency(taxNow);
        if (orderTotalEl) orderTotalEl.textContent = currency(subNow + shipNow + taxNow);
        ensurePlaceButtonState();
        await updatePricing();
      });
      state.container.querySelectorAll('input[name="pm-paymentMethod"]').forEach((el) => {
        el.addEventListener('change', async () => {
          ensurePlaceButtonState();
          if (getSelectedPaymentMethod() === 'Square') { await ensureSquareSDK(); await ensureSquareCard(); }
          else if (cardWrapEl) { cardWrapEl.classList.add('hidden'); }
        });
      });
      if (placeOrderBtn) placeOrderBtn.addEventListener('click', placeOrder);

      // React to cart updates while modal is open (but ignore while receipt is open)
      window.addEventListener('cartUpdated', async () => { if (isReceiptOpen()) return; renderOrderSummary(); await updatePricing(); });

      // React to authentication events: set userId and refresh UI/pricing
      window.addEventListener('wf:login-success', async (e) => {
        try {
          const raw = (e?.detail?.userId != null ? e.detail.userId : document.body?.dataset?.userId);
          const n = Number(raw);
          if (Number.isFinite(n) && n > 0) userId = String(n);
        } catch (_) {}
        if (!userId) {
          await resolveUserIdWithFallback();
        }
        // After login, populate addresses and recompute pricing
        renderOrderSummary();
      updateShippingBadges();
      initTooltips();
      pmShowShippingBenefitNote('Free USPS shipping on orders over $50!');
      loadAddresses();
      ensurePlaceButtonState();
      updatePricing();
      }, { once: false });

      // Note: login changes are handled via the wf:login-success listener above.

      // Initial loads
      renderOrderSummary();
      updateShippingBadges();
      initTooltips();
      ensurePmShippingBenefitNote();
      try {
        const currentSubtotal = Number((window.WF_Cart?.getTotal?.() ?? 0));
        const eligible = (shipMethodSel && shipMethodSel.value === 'USPS' && currentSubtotal >= 50);
        if (eligible) { pmShowShippingBenefitNote('Free USPS shipping on orders $50+.'); }
        else { pmHideShippingBenefitNote(); }
      } catch(_) { pmHideShippingBenefitNote(); }
      checkSquareSettings();
      // Load addresses first to set selectedAddressId, then compute pricing so ZIP-based tax is applied on open
      loadAddresses()
        .catch(() => {})
        .finally(() => { updatePricing(); });
    }

    // CSS class-based scroll lock helpers (WFModals-free)
    function lockScrollCss() {
      try { document.documentElement.classList.add('wf-scroll-locked'); document.body.classList.add('wf-scroll-locked'); } catch(_) {}
    }
    function unlockScrollCss() {
      try { document.documentElement.classList.remove('wf-scroll-locked'); document.body.classList.remove('wf-scroll-locked'); } catch(_) {}
    }

    function openInternal() {
      ensureOverlay();
      render();
      // Ensure modal elements are attached to body
      try { window.WFModalUtils && window.WFModalUtils.ensureOnBody && window.WFModalUtils.ensureOnBody(state.overlay); } catch(_) {}
      // Blur any currently focused element to avoid aria-hidden focus retention issues
      try { const ae = document.activeElement; if (ae && typeof ae.blur === 'function') ae.blur(); } catch(_) {}
      // Proactively close other overlays that could sit above or trap focus
      try { window.WF_CartModal && typeof window.WF_CartModal.close === 'function' && window.WF_CartModal.close(); } catch(_) {}
      try {
        const cartOv = document.getElementById('cartModalOverlay');
        if (cartOv) { cartOv.classList.remove('show'); cartOv.setAttribute('aria-hidden', 'true'); cartOv.setAttribute('inert', ''); }
      } catch(_) {}
      try {
        const roomOv = document.getElementById('roomModalOverlay');
        if (roomOv) { roomOv.classList.remove('show'); roomOv.setAttribute('aria-hidden', 'true'); roomOv.setAttribute('inert', ''); }
      } catch(_) {}
      // Normalize overlay classes (remove legacy under-header, ensure checkout-overlay)
      try { state.overlay.classList.remove('under-header'); } catch(_) {}
      try { state.overlay.classList.add('checkout-overlay'); } catch(_) {}
      // Make overlay visible
      try { state.overlay.classList.add('show'); } catch(_) {}
      try { state.overlay.setAttribute('aria-hidden', 'false'); } catch(_) {}
      // Lock background scroll via CSS class
      try { lockScrollCss(); } catch(_) {}
      // Mark body so the dedicated /payment page can be hidden beneath the modal
      try {
        document.body?.setAttribute('data-checkout-modal-open', '1');
        const mainEl = document.querySelector('main');
        if (mainEl) mainEl.setAttribute('aria-hidden', 'true');
      } catch(_) {}
    }

    function open() {
      // If not logged in, trigger login modal first and then reopen
      if (!isClientLoggedIn() && typeof window.openLoginModal === 'function') {
        const desiredReturn = window.location.pathname + window.location.search + window.location.hash;
        window.openLoginModal(desiredReturn, {
          suppressRedirect: true,
          onSuccess: () => {
            // After successful login, ensure flag is set and then open payment
            try { if (document && document.body) document.body.setAttribute('data-is-logged-in', 'true'); } catch (_) {}
            openInternal();
            // Proactively resolve user and load data without waiting for attribute/event races
            resolveUserIdWithFallback()
              .then(async () => { if (userId) { await loadAddresses(); ensurePlaceButtonState(); await updatePricing(); } })
              .catch(() => {});
          }
        });
        // Also listen for the global login event to capture userId when available
        const onLogin = (e) => {
          try {
            const uid = e?.detail?.userId || document.body?.dataset?.userId;
            if (uid && document && document.body) document.body.setAttribute('data-user-id', String(uid));
          } catch (_) {}
          // If the modal isn't already open, open it now
          try {
            if (!state.overlay || !state.overlay.classList.contains('show')) openInternal();
          } catch (_) {}
        };
        window.addEventListener('wf:login-success', onLogin, { once: true });
        return;
      }
      // Already logged in, just open
      openInternal();
    }

    function close() {
      if (!state.overlay) return;
      state.overlay.classList.remove('show');
      try { state.overlay.setAttribute('aria-hidden', 'true'); } catch(_) {}
      // Unlock background scroll via CSS class
      try { unlockScrollCss(); } catch(_) {}
      // Restore underlying page visibility
      try {
        document.body?.removeAttribute('data-checkout-modal-open');
        const mainEl = document.querySelector('main');
        if (mainEl) mainEl.removeAttribute('aria-hidden');
      } catch(_) {}
    }

    window.WF_PaymentModal = {
      open,
      close,
      initialized: true,
    };

    // Precreate overlay for z-index stacking
    ensureOverlay();
    // If a prior step requested opening checkout as a modal from another page, honor it once
    try {
      const want = localStorage.getItem('wf:openCheckoutModal');
      if (want === '1') {
        localStorage.removeItem('wf:openCheckoutModal');
        setTimeout(() => { try { open(); } catch(_) {} }, 0);
      }
    } catch(_) {}
    console.log('[PaymentModal] initialized');
  } catch (err) {
    console.error('[PaymentModal] init error', err);
  }
})();
