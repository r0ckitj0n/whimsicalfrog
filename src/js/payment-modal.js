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

    function ensureOverlay() {
      if (state.overlay && state.container) return;

      const existing = document.getElementById('paymentModalOverlay');
      if (existing) existing.remove();

      const overlay = document.createElement('div');
      overlay.id = 'paymentModalOverlay';
      overlay.className = 'confirmation-modal-overlay under-header checkout-overlay';

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
          <p class="payment-subtitle">Complete your purchase without leaving the page</p>
        </div>
        <div class="payment-body">
          <div class="payment-grid">
            <div class="left-col">
              <div class="section-card">
                <h4 class="section-title">Shipping address</h4>
                <div id="pm-addressList" class="space-y-2"></div>
                <div class="mt-2" style="margin-top:10px;">
                  <button id="pm-addAddressToggle" class="modal-button btn-secondary">Add new address</button>
                </div>
                <div id="pm-addAddressForm" class="hidden" style="margin-top:10px;">
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
                  <div style="margin-top:8px; display:flex; align-items:center; gap:8px;">
                    <input id="pm-addr_default" type="checkbox" />
                    <label for="pm-addr_default">Set as default</label>
                  </div>
                  <div style="margin-top:10px; display:flex; gap:8px;">
                    <button id="pm-saveAddressBtn" class="modal-button btn-primary">Save address</button>
                    <button id="pm-cancelAddressBtn" class="modal-button btn-secondary">Cancel</button>
                  </div>
                </div>
              </div>

              <div class="section-card" style="margin-top:14px;">
                <h4 class="section-title">Shipping method</h4>
                <select id="pm-shippingMethodSelect">
                  <option value="Customer Pickup">Customer Pickup</option>
                  <option value="Local Delivery">Local Delivery</option>
                  <option value="USPS">USPS</option>
                  <option value="FedEx">FedEx</option>
                  <option value="UPS">UPS</option>
                </select>
                <p class="hint" style="margin-top:8px;">Select a method. Address is required for delivery and carriers.</p>
              </div>

              <div class="section-card" style="margin-top:14px;">
                <h4 class="section-title">Payment</h4>
                <div class="radio-row" role="radiogroup" aria-label="Payment method">
                  <label><input type="radio" name="pm-paymentMethod" value="Square" id="pm-pm-square" /> Square (card)</label>
                  <label><input type="radio" name="pm-paymentMethod" value="Cash" id="pm-pm-cash" /> Cash</label>
                </div>
                <div id="pmSquareNote" class="hint" style="margin-top:6px;">Square is currently unavailable.</div>
                <div id="pm-cardContainerWrap" class="hidden" style="margin-top:10px;">
                  <div id="pm-card-container"></div>
                  <div id="pm-card-errors" class="error hidden" style="margin-top:6px;"></div>
                </div>
              </div>
            </div>

            <div class="right-col">
              <div class="section-card">
                <h4 class="section-title">Order summary</h4>
                <div id="pm-orderItems" class="summary-lines"></div>
                <div class="summary-lines" style="margin-top:10px;">
                  <div class="summary-line"><span class="label">Subtotal</span> <span id="pm-orderSubtotal" class="value">$0.00</span></div>
                  <div class="summary-line"><span class="label">Shipping</span> <span id="pm-orderShipping" class="value">$0.00</span></div>
                  <div class="summary-line"><span class="label">Tax</span> <span id="pm-orderTax" class="value">$0.00</span></div>
                  <div class="summary-line summary-total"><span class="label">Total</span> <span id="pm-orderTotal" class="value">$0.00</span></div>
                </div>
              </div>
            </div>
          </div>
          <div id="pm-checkoutError" class="error hidden" style="margin-top:8px;"></div>
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
      const userId = document.body?.dataset?.userId;
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

      // Address form fields
      const fName = q('#pm-addr_name');
      const fL1 = q('#pm-addr_line1');
      const fL2 = q('#pm-addr_line2');
      const fCity = q('#pm-addr_city');
      const fState = q('#pm-addr_state');
      const fZip = q('#pm-addr_zip');
      const fDefault = q('#pm-addr_default');

      if (cancelBtn) cancelBtn.addEventListener('click', close);

      if (!userId || !cartApi) {
        if (errorEl) {
          errorEl.textContent = 'Checkout unavailable. Please refresh the page.';
          errorEl.classList.remove('hidden');
        }
        return;
      }

      // Controller state
      let addresses = [];
      let selectedAddressId = null;
      let pricing = { subtotal: 0, shipping: 0, tax: 0, total: 0 };
      let sq = { enabled: false, applicationId: null, environment: 'sandbox', locationId: null, payments: null, card: null, sdkLoaded: false };

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
        const method = shipMethodSel?.value || 'Customer Pickup';
        const needsAddress = method !== 'Customer Pickup';
        const okAddress = !needsAddress || !!selectedAddressId;
        if (placeOrderBtn) placeOrderBtn.disabled = !(hasItems && !!pm && okAddress);
      }

      function isDebugPricing() {
        try {
          const q = new URLSearchParams(window.location.search || '');
          if (q.get('debugPricing') === '1') return true;
          const v = (localStorage.getItem('wf_debug_pricing') || '').toLowerCase();
          return v === '1' || v === 'true';
        } catch (_) { return false; }
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
            <div class="summary-line" style="align-items:flex-start;">
              <div class="label" style="flex:1;">${(it.name || it.sku || '').toString()}${desc}</div>
              <div class="value" style="min-width:140px; text-align:right;">${qty} × ${currency(price)}<div style="font-weight:800;">${currency(line)}</div></div>
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
            <label class="flex items-start gap-3 p-2 rounded hover:bg-gray-50 cursor-pointer" style="display:flex; gap:10px; align-items:flex-start; padding:6px; border-radius:8px;">
              <input type="radio" name="pm-shippingAddress" class="form-radio mt-1" value="${id}" ${checked ? 'checked' : ''} />
              <div class="text-sm">
                <div class="font-medium">${a.address_name || 'Address'}</div>
                <div class="hint">
                  ${a.address_line1}<br/>
                  ${line2}
                  ${a.city}, ${a.state} ${a.zip_code}
                </div>
                ${a.is_default == 1 ? '<div class="text-xs" style="color:#16a34a;">Default</div>' : ''}
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
            shippingMethod: shipMethodSel?.value || 'Customer Pickup',
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
            if (orderSubtotalEl) orderSubtotalEl.textContent = currency(pricing.subtotal);
            if (orderShippingEl) orderShippingEl.textContent = currency(pricing.shipping);
            if (orderTaxEl) orderTaxEl.textContent = currency(pricing.tax);
            if (orderTotalEl) orderTotalEl.textContent = currency(pricing.total);
            if (res.debug) {
              console.info('[PaymentModal] pricing debug ->', res.debug);
            }
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
        const shippingMethod = shipMethodSel?.value || 'Customer Pickup';
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
          const res = await apiClient.post('/api/add-order.php', payload);
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
          const serverMsg = (res && typeof res === 'object' && res.error) ? res.error : (typeof res === 'string' ? res : null);
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
      if (shipMethodSel) shipMethodSel.addEventListener('change', async () => { ensurePlaceButtonState(); await updatePricing(); });
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

      // Initial loads
      renderOrderSummary();
      checkSquareSettings();
      // Load addresses first to set selectedAddressId, then compute pricing so ZIP-based tax is applied on open
      loadAddresses()
        .catch(() => {})
        .finally(() => { updatePricing(); });
    }

    function openInternal() {
      ensureOverlay();
      render();
      state.overlay.classList.add('show');
      try { window.WFModals && window.WFModals.lockScroll && window.WFModals.lockScroll(); } catch(_){ }
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
          }
        });
        return;
      }
      // Already logged in, just open
      openInternal();
    }

    function close() {
      if (!state.overlay) return;
      state.overlay.classList.remove('show');
      try { window.WFModals && window.WFModals.unlockScrollIfNoneOpen && window.WFModals.unlockScrollIfNoneOpen(); } catch(_){ }
    }

    window.WF_PaymentModal = {
      open,
      close,
      initialized: true,
    };

    // Precreate overlay for z-index stacking
    ensureOverlay();
    console.log('[PaymentModal] initialized');
  } catch (err) {
    console.error('[PaymentModal] init error', err);
  }
})();
