/**
 * POS main module (renamed from admin-pos.js)
 */
import { buildAdminUrl } from '../core/admin-url-builder.js';
import { ApiClient } from '../core/api-client.js';

const POSModule = {
    cart: [],
    allItems: [],
    cashCalculatorResolve: null,
    lastSaleData: null,

    async init() {
        const posPage = document.querySelector('.pos-register');
        if (!posPage) {
            try { console.warn('[POS] .pos-register not found; POS module not initialized'); } catch(_) {}
            return;
        }
        

        const posDataEl = document.getElementById('pos-data');
        if (posDataEl) {
            try {
                this.allItems = JSON.parse(posDataEl.textContent);
                
            } catch (e) {
                console.error('Failed to parse POS data:', e);
                this.showPOSModal('Error', 'Could not load item data. Please refresh.', 'error');
                return;
            }
        }

        // Cache filter elements if present (standalone POS has categoryFilter)
        this.skuInput = document.getElementById('skuSearch');
        this.categorySelect = document.getElementById('categoryFilter');

        this.bindEventListeners();
        this.showAllItems();
        this.updateCartDisplay();
        
        // Provide a lightweight cart bridge for the detailed item modal on POS.
        // The global detailed modal calls WF_Cart.addItem(payload); here we route it to the POS cart.
        try {
            // Ensure global exists
            window.WF_Cart = window.WF_Cart || {};
            // Replace/add real handlers bound to this POS module
            window.WF_Cart.addItem = async ({ sku, quantity = 1, price = 0, name = '', image = '' }) => {
                try {
                    const qty = Math.max(1, Number(quantity) || 1);
                    const existing = this.cart.find(i => String(i.sku) === String(sku));
                    if (existing) {
                        existing.quantity += qty;
                        if (Number.isFinite(Number(price)) && Number(price) > 0) {
                            existing.price = Number(price);
                        }
                    } else {
                        this.cart.push({ sku, name, price: Number(price) || 0, quantity: qty, image });
                    }
                    this.updateCartDisplay();
                    return { success: true };
                } catch (err) {
                    console.warn('[POS] WF_Cart.addItem failed', err);
                    throw err;
                }
            };
            window.WF_Cart.updateCartDisplay = () => { try { this.updateCartDisplay(); } catch(_) {} };
            window.WF_Cart.clear = () => { try { this.cart = []; this.updateCartDisplay(); } catch(_) {} };

            // Drain any pending adds queued before POS init and on subsequent events
            const drainPendingAdds = async () => {
                if (!Array.isArray(window.__POS_pendingAdds) || !window.__POS_pendingAdds.length) return;
                try {
                    const queued = window.__POS_pendingAdds.slice();
                    window.__POS_pendingAdds.length = 0;
                    for (const payload of queued) {
                        await window.WF_Cart.addItem(payload);
                    }
                    try { this.updateCartDisplay(); } catch(_) {}
                } catch (_) { /* noop */ }
            };
            await drainPendingAdds();
            try { document.addEventListener('pos:pendingAdd', () => { drainPendingAdds(); }); } catch(_) {}
        } catch(_) {}

        // Ensure checkout button triggers even if event delegation misses
        try {
            const btn = document.getElementById('checkoutBtn');
            if (btn && !btn.dataset.posBound) {
                btn.addEventListener('click', (ev) => {
                    try { ev.preventDefault(); } catch(_) {}
                    this.processCheckout();
                });
                btn.dataset.posBound = '1';
            }
        } catch(_) {}
    },

    bindEventListeners() {
        const posRegister = document.querySelector('.pos-register');
        if (posRegister) {
            posRegister.addEventListener('click', (e) => {
                const action = e.target.closest('[data-action]')?.dataset.action;
                if (!action) return;

                switch (action) {
                    case 'toggle-fullscreen': this.toggleFullscreen(); break;
                    case 'exit-pos': this.handleExit(); break;
                    case 'browse-items': this.showAllItems(); break;
                    case 'remove-from-cart': this.removeFromCart(e.target.closest('.cart-item').dataset.sku); break;
                    case 'increment-quantity': this.updateQuantity(e.target.closest('.cart-item').dataset.sku, 1); break;
                    case 'decrement-quantity': this.updateQuantity(e.target.closest('.cart-item').dataset.sku, -1); break;
                    case 'checkout': this.processCheckout(); break;
                }
            });

            // Open detailed modal when clicking an item card (outside explicit buttons)
            posRegister.addEventListener('click', (e) => {
                const card = e.target.closest && e.target.closest('.item-card');
                if (!card) return;
                // Ignore clicks on embedded controls if we add any later
                if (e.target.closest('[data-action]')) return;
                const sku = card.getAttribute('data-sku');
                if (!sku) return;
                const stockAttr = card.getAttribute('data-stock');
                const stock = Number.parseInt(stockAttr || '0', 10);
                if (Number.isFinite(stock) && stock <= 0) {
                    this.showPOSModal('Out of stock', 'This item currently has no available stock.', 'info');
                    return;
                }
                const name = card.getAttribute('data-name') || card.querySelector('.item-name')?.textContent || '';
                const price = parseFloat(card.getAttribute('data-price') || card.querySelector('.item-price')?.textContent?.replace(/[^0-9.]/g,'') || '0') || 0;
                const image = card.getAttribute('data-image') || card.querySelector('img')?.getAttribute('src') || '';
                const item = { sku, name, price, retailPrice: price, currentPrice: price, image, stockLevel: stock, stock };
                this.openDetailedModal(String(sku).toUpperCase(), item);
            });

            // Clicking a cart item removes it (in addition to the × button)
            const cartEl = posRegister.querySelector('.pos-cart');
            if (cartEl) {
                cartEl.addEventListener('click', (e) => {
                    const withinControls = e.target.closest && e.target.closest('[data-action]');
                    if (withinControls) return; // respect +/- and remove button behaviors
                    const row = e.target.closest && e.target.closest('.cart-item');
                    if (!row) return;
                    const sku = row.getAttribute('data-sku');
                    if (!sku) return;
                    this.removeFromCart(sku);
                });
            }
        }

        document.body.addEventListener('click', (e) => {
            const action = e.target.closest('[data-action]')?.dataset.action;
            if (!action) return;

            switch(action) {
                case 'print-receipt': this.printReceipt(); break;
                case 'email-receipt': this.lastSaleData && this.emailReceipt(this.lastSaleData.orderId); break;
                case 'finish-sale': this.finishSale(); break;
                case 'accept-cash': this.acceptCashPayment(parseFloat(e.target.dataset.total)); break;
                case 'set-cash-amount': this.setCashAmount(parseFloat(e.target.dataset.amount)); break;
                case 'close-modal': this.hidePOSModal(); break;
            }
        });

        // SKU search filtering (combined with category when present)
        if (this.skuInput) {
            this.skuInput.addEventListener('input', () => this.applyFilters());
        }
        // Category dropdown filtering (standalone POS)
        if (this.categorySelect) {
            this.categorySelect.addEventListener('change', () => this.applyFilters());
        }

        document.addEventListener('keydown', (e) => {
            if (document.querySelector('.pos-modal-overlay')) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    this.hidePOSModal();
                }
                return;
            }

            if (e.key === 'F1') {
                e.preventDefault();
                document.getElementById('skuSearch')?.focus();
            }
            if (e.key === 'F2') {
                e.preventDefault();
                this.showAllItems();
                const searchInput = document.getElementById('skuSearch');
                if(searchInput) searchInput.value = '';
            }
            if (e.key === 'F9') {
                e.preventDefault();
                const checkoutBtn = document.getElementById('checkoutBtn');
                if (checkoutBtn && !checkoutBtn.disabled) {
                    this.processCheckout();
                }
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                this.handleExit();
            }
        });
    },

    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                if (typeof window.showAlertModal === 'function') {
                    window.showAlertModal({
                        title: 'Fullscreen Error',
                        message: `Error attempting to enable full-screen mode: ${err.message} (${err.name})`,
                        icon: '⚠️',
                        iconType: 'warning',
                        confirmText: 'OK'
                    });
                } else {
                    alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                }
            });
        } else {
            document.exitFullscreen();
        }
    },

    handleExit() {
        this.showPOSConfirm(
            'Exit POS',
            'Are you sure you want to exit the Point of Sale system?',
            'Yes, Exit',
            'Stay Here'
        ).then(confirmed => {
            if (confirmed) {
                window.location.href = buildAdminUrl('dashboard');
            }
        });
    },

    applyFilters() {
        const grid = document.getElementById('itemsGrid');
        if (!grid) return;
        const q = (this.skuInput && this.skuInput.value ? this.skuInput.value : '').toLowerCase().trim();
        const catRaw = (this.categorySelect && this.categorySelect.value) ? this.categorySelect.value : '';
        const cat = String(catRaw).toLowerCase().trim();
        const filtered = (this.allItems || []).filter(item => {
            const matchesText = !q || (String(item.name||'').toLowerCase().includes(q) || String(item.sku||'').toLowerCase().includes(q));
            const itemCat = String(item.category || '').toLowerCase().trim();
            const matchesCat = !cat || (itemCat === cat);
            return matchesText && matchesCat;
        });
        grid.innerHTML = filtered.length > 0 ? filtered.map(item => this.createItemCard(item)).join('') : `<div class="pos-no-results">No items match your filters.</div>`;
        
    },

    showAllItems() {
        const grid = document.getElementById('itemsGrid');
        if (!grid) { return; }
        const visible = (this.allItems || []);
        grid.innerHTML = visible.map(item => this.createItemCard(item)).join('');
        
    },

    createItemCard(item) {
        const rawImage = item.imageUrl ? String(item.imageUrl) : '';
        const price = parseFloat(item.retailPrice ?? item.price ?? 0) || 0;
        const cat = item.category ? String(item.category) : '';
        let stock = Number(item.stock);
        if (!Number.isFinite(stock)) {
            stock = Number(item.stockLevel);
        }
        if (!Number.isFinite(stock)) stock = 0;
        
        const imgTag = rawImage
            ? `<img src="${this.escapeAttr(rawImage)}" alt="${this.escapeAttr(item.name)}" class="item-image" loading="lazy">`
            : '';
        const missingBadge = !rawImage
            ? `<div class="pos-badge pos-badge-missing-image" title="Missing image for this item">Missing image</div>`
            : '';
        const oosBadge = (Number.isFinite(stock) && stock <= 0)
            ? `<div class="pos-badge pos-badge-oos" title="Out of stock">Out of stock</div>`
            : '';
        return `
            <div class="item-card" data-sku="${item.sku}" data-name="${this.escapeAttr(item.name)}" data-price="${price}" ${cat ? `data-category="${this.escapeAttr(cat)}"` : ''} data-stock="${stock}">
                ${imgTag}
                ${missingBadge}
                ${oosBadge}
                <div class="item-info">
                    <div class="item-name">${item.name}</div>
                    <div class="item-price">$${price.toFixed(2)}</div>
                </div>
            </div>
        `;
    },

    escapeAttr(v) { try { return String(v ?? '').replace(/"/g, '&quot;'); } catch(_) { return ''; } },

    openDetailedModal(sku, item) {
        // Try canonical global item modal handlers in order, fallback to direct add
        if (window.WhimsicalFrog && window.WhimsicalFrog.GlobalModal && typeof window.WhimsicalFrog.GlobalModal.show === 'function') {
            window.WhimsicalFrog.GlobalModal.show(sku, item);
            return;
        }
        if (typeof window.showGlobalItemModal === 'function') {
            window.showGlobalItemModal(sku, item);
            return;
        }
        if (typeof window.showDetailedModal === 'function') {
            window.showDetailedModal(sku, item);
            return;
        }
        // No fallback: surface as an error so the issue can be fixed
        this.showPOSModal('Detailed Modal Unavailable', 'The detailed item modal is not available on this page. Please ensure the global modal stack is loaded.', 'error');
    },

    addToCart(sku) {
        const skuNorm = String(sku || '').toUpperCase();
        const item = this.allItems.find(i => String(i.sku || '').toUpperCase() === skuNorm);
        if (!item) return;

        const cartItem = this.cart.find(i => String(i.sku || '').toUpperCase() === skuNorm);
        if (cartItem) {
            cartItem.quantity++;
        } else {
            this.cart.push({
                sku: item.sku,
                name: item.name,
                price: parseFloat(item.retailPrice),
                quantity: 1
            });
        }
        this.updateCartDisplay();
    },

    removeFromCart(sku) {
        this.cart = this.cart.filter(i => i.sku !== sku);
        this.updateCartDisplay();
    },

    updateQuantity(sku, change) {
        const cartItem = this.cart.find(i => i.sku === sku);
        if (cartItem) {
            cartItem.quantity += change;
            if (cartItem.quantity <= 0) {
                this.removeFromCart(sku);
            }
        }
        this.updateCartDisplay();
    },

    updateCartDisplay() {
        const cartItemsContainer = document.getElementById('cartItems');
        const cartTotalEl = document.getElementById('posCartTotal') || document.getElementById('cartTotal');
        const checkoutBtn = document.getElementById('checkoutBtn');

        if (!cartItemsContainer || !cartTotalEl || !checkoutBtn) return;

        if (this.cart.length === 0) {
            cartItemsContainer.innerHTML = `
                <div class="empty-cart">
                    Cart is empty<br>
                    <small>Scan or search for items to add them</small>
                </div>`;
            cartTotalEl.textContent = '$0.00';
            checkoutBtn.disabled = true;
        } else {
            cartItemsContainer.innerHTML = this.cart.map(item => `
                <div class="cart-item" data-sku="${item.sku}">
                    <div class="cart-item-details">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">$${item.price.toFixed(2)}</div>
                    </div>
                    <div class="cart-item-actions">
                        <button class="quantity-btn" data-action="decrement-quantity">-</button>
                        <span class="quantity">${item.quantity}</span>
                        <button class="quantity-btn" data-action="increment-quantity">+</button>
                        <button class="remove-btn" data-action="remove-from-cart">×</button>
                    </div>
                </div>
            `).join('');

            const total = this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            cartTotalEl.textContent = `$${total.toFixed(2)}`;
            checkoutBtn.disabled = false;
        }
    },

    async processCheckout() {
        if (this.cart.length === 0) {
            this.showPOSModal('Empty Cart', 'Please add items to the cart before completing a sale.', 'warning');
            return;
        }

        const TAX_RATE = 0.0825;
        const subtotal = this.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const taxAmount = subtotal * TAX_RATE;
        const total = subtotal + taxAmount;

        const paymentMethod = await this.showPaymentMethodSelector(total);
        if (!paymentMethod) return;

        let cashReceived = 0, changeAmount = 0;
        if (paymentMethod === 'Cash') {
            const cashResult = await this.showCashCalculator(total);
            if (!cashResult) return;
            cashReceived = cashResult.received;
            changeAmount = cashResult.change;
        }

        const confirmed = await this.showPOSConfirm(
            'Complete Sale',
            this.generateConfirmationContent({subtotal, taxAmount, total, paymentMethod, cashReceived, changeAmount, TAX_RATE}),
            'Process Sale',
            'Cancel'
        );
        if (!confirmed) return;

        this.showPOSModal('Processing Sale', 'Please wait...', 'processing');

        try {
            const orderData = {
                // Attribute all POS sales to the POS user account
                customerId: 'pos@whimsicalfrog.us',
                itemIds: this.cart.map(item => item.sku),
                quantities: this.cart.map(item => item.quantity),
                colors: this.cart.map(() => null),
                sizes: this.cart.map(() => null),
                total, subtotal, taxAmount, taxRate: TAX_RATE,
                paymentMethod, paymentStatus: 'Received',
                shippingMethod: 'Customer Pickup', order_status: 'Delivered'
            };

            // Use the canonical add-order endpoint
            const result = await ApiClient.post('add_order.php', orderData);
            const ok = !!(result && (result.success || (result.data && result.data.success)));
            const oid = result && (result.orderId || (result.data && result.data.orderId));
            if (ok && oid) {
                this.openReceiptPage(oid);
            } else {
                const msg = (result && (result.error || result.message)) || (result && result.data && (result.data.error || result.data.message)) || 'Checkout failed';
                throw new Error(msg);
            }
        } catch (error) {
            console.error('Checkout error:', error);
            this.showPOSModal('Transaction Failed', `❌ Checkout failed: ${error.message}`, 'error');
        }
    },

    async openReceiptPage(orderId) {
        try {
            this.hidePOSModal();
        } catch(_) {}
        this.lastSaleData = { orderId };
        const url = `/receipt.php?orderId=${encodeURIComponent(orderId)}&bare=1`;
        try {
            const html = await ApiClient.get(url);
            const content = `
                <div class="pos-modal-content pos-modal-large">
                    <div class="pos-modal-header pos-modal-header-success">
                        <h3 class="pos-modal-title">Receipt ${orderId}</h3>
                        <div class="pos-modal-actions">
                            <button class="btn btn-secondary" data-action="print-receipt">Print</button>
                            <button class="btn btn-secondary" data-action="email-receipt">Email</button>
                            <button class="pos-modal-close" data-action="finish-sale">×</button>
                        </div>
                    </div>
                    <div class="pos-modal-body pos-receipt-body">${html}</div>
                </div>`;
            this.showPOSModal('', content, 'custom');
        } catch (_) {
            try { window.open(url, '_blank', 'noopener'); } catch(_) { window.location.href = url; }
        }
    },

    generateReceiptContent(saleData) {
        const itemsHTML = saleData.itemIds.map((sku, index) => {
            const item = this.cart.find(i => i.sku === sku);
            const itemTotal = item.price * saleData.quantities[index];
            return `
                <div>
                    <div>
                        <div>${item.name}</div>
                        <div>SKU: ${sku}</div>
                        <div>${saleData.quantities[index]} x $${item.price.toFixed(2)}</div>
                    </div>
                    <div>$${itemTotal.toFixed(2)}</div>
                </div>
            `;
        }).join('');

        return `
            <div>
                <div><strong>Order ID:</strong> ${saleData.orderId}</div>
                <div><strong>Date:</strong> ${new Date(saleData.timestamp).toLocaleString()}</div>
                <div>${itemsHTML}</div>
                <div>
                    <span>Subtotal:</span>
                    <span>$${saleData.subtotal.toFixed(2)}</span>
                </div>
                <div>
                    <span>Total:</span>
                    <span>$${saleData.total.toFixed(2)}</span>
                </div>
            </div>
        `;
    },

    printReceipt() {
        const id = this.lastSaleData && this.lastSaleData.orderId ? String(this.lastSaleData.orderId) : '';
        if (!id) return;
        const url = `/receipt.php?orderId=${encodeURIComponent(id)}&bare=1`;
        try { window.open(url, '_blank', 'noopener'); } catch(_) { window.location.href = url; }
    },

    async emailReceipt(orderId) {
        const email = (typeof window.showPromptModal === 'function')
            ? await window.showPromptModal({
                title: 'Email Receipt',
                message: 'Enter customer email address:',
                placeholder: 'name@example.com',
                inputType: 'email',
                confirmText: 'Send',
                cancelText: 'Cancel',
                icon: '✉️',
                iconType: 'info'
              })
            : prompt('Enter customer email address:');
        if (!email || !/^[\S]+@[\S]+\.[\S]+$/.test(email)) {
            this.showPOSModal('Invalid Email', 'Please enter a valid email address.', 'error');
            return;
        }

        this.showPOSModal('Sending Receipt...', 'Please wait...', 'info');

        try {
            const data = await ApiClient.post('/api/receipts/email', { orderId, customerEmail: email, orderData: this.lastSaleData });
            if (data && data.success) {
                this.showPOSModal('Email Sent!', data.message || `Receipt sent to ${email}`, 'success');
            } else {
                throw new Error(data.error || 'Unknown error');
            }
        } catch (error) {
            this.showPOSModal('Email Failed', error.message, 'error');
        }
    },

    finishSale() {
        try { this.hidePOSModal(); } catch(_) {}
        try { window.location.reload(); return; } catch(_) {}
        // Fallback if reload fails
        this.cart = [];
        this.updateCartDisplay();
        const searchInput = document.getElementById('skuSearch');
        if (searchInput) searchInput.value = '';
        this.showAllItems();
    },

    showPOSModal(title, message, type = 'info') {
        this.hidePOSModal();
        const modal = document.createElement('div');
        modal.id = 'posModal';
        modal.className = 'pos-modal-overlay';
        let modalContent;

        if (type === 'custom') {
            modalContent = message;
        } else {
            modalContent = `
                <div class="pos-modal-content pos-modal-small">
                    <div class="pos-modal-header pos-modal-header-${type}">
                        <h3 class="pos-modal-title">${title}</h3>
                        <button class="pos-modal-close" data-action="close-modal">×</button>
                    </div>
                    <div class="pos-modal-body">${message}</div>
                </div>`;
        }
        modal.innerHTML = `<div class="pos-modal-backdrop"></div>${modalContent}`;
        document.body.appendChild(modal);
    },

    hidePOSModal() {
        const modal = document.getElementById('posModal');
        if (modal) modal.remove();
        if (this.cashCalculatorResolve) {
            this.cashCalculatorResolve(null);
            this.cashCalculatorResolve = null;
        }
    },

    showPOSConfirm(title, message, confirmText = 'OK', cancelText = 'Cancel') {
        if (typeof window.showConfirmationModal === 'function') {
            return window.showConfirmationModal({
                title,
                message,
                confirmText,
                cancelText,
                icon: '⚠️',
                iconType: 'warning',
                confirmStyle: 'confirm'
            });
        }
        // Fallback to local POS-styled modal if global modal isn't available
        return new Promise(resolve => {
            const modalHTML = `
                <div class="pos-modal-content pos-modal-small">
                    <div class="pos-modal-header"><h3 class="pos-modal-title">${title}</h3></div>
                    <div class="pos-modal-body">${message}</div>
                    <div class="pos-modal-footer">
                        <button class="btn btn-secondary">${cancelText}</button>
                        <button class="btn btn-primary">${confirmText}</button>
                    </div>
                </div>`;
            this.showPOSModal('', modalHTML, 'custom');
            const primaryBtn = document.querySelector('#posModal .btn-primary');
            const secondaryBtn = document.querySelector('#posModal .btn-secondary');
            if (primaryBtn) primaryBtn.addEventListener('click', () => { this.hidePOSModal(); resolve(true); });
            if (secondaryBtn) secondaryBtn.addEventListener('click', () => { this.hidePOSModal(); resolve(false); });
        });
    },

    showPaymentMethodSelector(total) {
        const message = `
            <h3>Total Due: $${total.toFixed(2)}</h3>
            <div class="payment-methods">
                <button class="payment-btn payment-method-btn cash" data-method="Cash" title="Cash" aria-label="Cash"><span class="btn-icon btn-icon--cash" aria-hidden="true"></span> <span>Cash</span></button>
                <button class="payment-btn payment-method-btn card" data-method="Card" title="Card" aria-label="Card"><span class="btn-icon btn-icon--card" aria-hidden="true"></span> <span>Card</span></button>
                <button class="payment-btn payment-method-btn other" data-method="Other" title="Other" aria-label="Other"><span class="btn-icon btn-icon--mobile" aria-hidden="true"></span> <span>Other</span></button>
            </div>`;
        return new Promise(resolve => {
            this.showPOSModal('Select Payment Method', message, 'info');
            document.querySelectorAll('#posModal .payment-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    this.hidePOSModal();
                    resolve(btn.dataset.method);
                });
            });
        });
    },

    showCashCalculator(total) {
        return new Promise(resolve => {
            this.cashCalculatorResolve = resolve;
            const message = `
                <h3>Total Due: $${total.toFixed(2)}</h3>
                <input type="number" id="cashReceived" placeholder="0.00" class="pos-cash-input">
                <div id="quickAmountButtons"></div>
                <div id="changeDue">Change: $0.00</div>
                <div id="insufficientFunds" class="form-error hidden">Insufficient funds</div>
                <button id="acceptCashBtn" class="btn btn-primary" data-action="accept-cash" data-total="${total}" disabled>Accept</button>`;
            this.showPOSModal('Cash Payment', message, 'info');
            this.generateQuickAmountButtons(total);
            const cashInput = document.getElementById('cashReceived');
            if (cashInput) cashInput.addEventListener('input', () => this.calculateChange(total));
        });
    },

    generateQuickAmountButtons(total) {
        const container = document.getElementById('quickAmountButtons');
        if (!container) return;
        const suggestions = [{ label: 'Exact', amount: total }];
        const nextDollar = Math.ceil(total);
        if (nextDollar !== total) suggestions.push({ label: `$${nextDollar}`, amount: nextDollar });
        [20, 50, 100].forEach(val => {
            if (total < val) suggestions.push({ label: `$${val}`, amount: val });
        });
        const uniqueSuggestions = [...new Map(suggestions.map(item => [item.amount, item])).values()].slice(0, 4);
        container.innerHTML = uniqueSuggestions.map(s => `<button class="quick-amount-btn" data-action="set-cash-amount" data-amount="${s.amount}">${s.label}</button>`).join('');
    },

    calculateChange(total) {
        const cashReceived = parseFloat(document.getElementById('cashReceived').value) || 0;
        const change = cashReceived - total;
        document.getElementById('changeDue').textContent = `Change: $${Math.max(0, change).toFixed(2)}`;
        const acceptBtn = document.getElementById('acceptCashBtn');
        acceptBtn.disabled = cashReceived < total;
        const insufficientEl = document.getElementById('insufficientFunds');
        if (insufficientEl) {
            if (cashReceived < total) {
                insufficientEl.classList.remove('hidden');
            } else {
                insufficientEl.classList.add('hidden');
            }
        }
    },

    setCashAmount(amount) {
        const cashInput = document.getElementById('cashReceived');
        cashInput.value = amount.toFixed(2);
        cashInput.dispatchEvent(new Event('input'));
    },

    acceptCashPayment(total) {
        const cashReceived = parseFloat(document.getElementById('cashReceived').value);
        if (cashReceived >= total && this.cashCalculatorResolve) {
            this.cashCalculatorResolve({ received: cashReceived, change: cashReceived - total });
            this.cashCalculatorResolve = null;
            this.hidePOSModal();
        }
    },

    generateConfirmationContent(data) {
        let details = `<strong>Method:</strong> ${data.paymentMethod}`;
        if (data.paymentMethod === 'Cash') {
            details += `<br><strong>Cash Received:</strong> $${data.cashReceived.toFixed(2)}`;
            details += `<br><strong>Change Due:</strong> $${data.changeAmount.toFixed(2)}`;
        }
        return `
            <div>Subtotal: <strong>$${data.subtotal.toFixed(2)}</strong></div>
            <div>Sales Tax (${(data.TAX_RATE * 100).toFixed(2)}%): $${data.taxAmount.toFixed(2)}</div>
            <hr>
            <div>Total: <strong>$${data.total.toFixed(2)}</strong></div>
            <hr>
            <div>${details}</div>`;
    }
};

// Initialize immediately if DOM is already ready; otherwise wait for DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => POSModule.init());
} else {
    POSModule.init();
}

try { window.POS_openReceiptInModal = (orderId) => POSModule.openReceiptPage(orderId); } catch(_) {}
