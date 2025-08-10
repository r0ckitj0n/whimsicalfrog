const POSModule = {
    cart: [],
    allItems: [],
    cashCalculatorResolve: null,
    lastSaleData: null,

    init() {
        const posPage = document.querySelector('.pos-register');
        if (!posPage) return;

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

        this.bindEventListeners();
        this.showAllItems();
        this.updateCartDisplay();
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
                    case 'add-to-cart': this.addToCart(e.target.closest('.item-card').dataset.sku); break;
                    case 'remove-from-cart': this.removeFromCart(e.target.closest('.cart-item').dataset.sku); break;
                    case 'increment-quantity': this.updateQuantity(e.target.closest('.cart-item').dataset.sku, 1); break;
                    case 'decrement-quantity': this.updateQuantity(e.target.closest('.cart-item').dataset.sku, -1); break;
                    case 'checkout': this.processCheckout(); break;
                }
            });
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

        const skuSearch = document.getElementById('skuSearch');
        if (skuSearch) {
            skuSearch.addEventListener('input', (e) => this.filterItems(e.target.value));
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
                alert(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
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
                window.location.href = '/admin/dashboard';
            }
        });
    },

    filterItems(query) {
        const grid = document.getElementById('itemsGrid');
        if (!grid) return;

        const lowerCaseQuery = query.toLowerCase().trim();

        if (!lowerCaseQuery) {
            this.showAllItems();
            return;
        }

        const filtered = this.allItems.filter(item =>
            item.name.toLowerCase().includes(lowerCaseQuery) ||
            item.sku.toLowerCase().includes(lowerCaseQuery)
        );

        grid.innerHTML = filtered.length > 0 ? filtered.map(item => this.createItemCard(item)).join('') : `<div class="pos-no-results">No items found for "${query}"</div>`;
    },

    showAllItems() {
        const grid = document.getElementById('itemsGrid');
        if (!grid) return;
        grid.innerHTML = this.allItems.map(item => this.createItemCard(item)).join('');
    },

    createItemCard(item) {
        const imageUrl = item.imageUrl ? `/${item.imageUrl}` : 'https://via.placeholder.com/150';
        return `
            <div class="item-card" data-action="add-to-cart" data-sku="${item.sku}">
                <img src="${imageUrl}" alt="${item.name}" class="item-image" loading="lazy">
                <div class="item-info">
                    <div class="item-name">${item.name}</div>
                    <div class="item-price">$${parseFloat(item.retailPrice).toFixed(2)}</div>
                </div>
            </div>
        `;
    },

    addToCart(sku) {
        const item = this.allItems.find(i => i.sku === sku);
        if (!item) return;

        const cartItem = this.cart.find(i => i.sku === sku);
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
        const cartTotalEl = document.getElementById('posCartTotal');
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
                customerId: 'POS001',
                itemIds: this.cart.map(item => item.sku),
                quantities: this.cart.map(item => item.quantity),
                colors: this.cart.map(() => null),
                sizes: this.cart.map(() => null),
                total, subtotal, taxAmount, taxRate: TAX_RATE,
                paymentMethod, paymentStatus: 'Received',
                shippingMethod: 'Customer Pickup', order_status: 'Delivered'
            };

            const response = await fetch('/api/orders', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orderData)
            });

            const result = await response.json();

            if (result.success) {
                this.showReceiptModal({ ...orderData, orderId: result.orderId, cashReceived, changeAmount, timestamp: new Date() });
            } else {
                throw new Error(result.error || 'Checkout failed');
            }
        } catch (error) {
            console.error('Checkout error:', error);
            this.showPOSModal('Transaction Failed', `❌ Checkout failed: ${error.message}`, 'error');
        }
    },

    showReceiptModal(saleData) {
        this.lastSaleData = saleData;
        this.hidePOSModal();
        const receiptContent = this.generateReceiptContent(saleData);
        const modalHTML = `
            <div class="pos-modal-content pos-modal-small">
                <div class="pos-modal-header pos-modal-header-success">
                    <h3 class="pos-modal-title">🧾 Transaction Complete</h3>
                </div>
                <div class="pos-modal-body pos-modal-body-scroll">${receiptContent}</div>
                <div class="pos-modal-footer">
                    <button class="btn btn-secondary" data-action="print-receipt">🖨️ Print Receipt</button>
                    <button class="btn btn-secondary" data-action="email-receipt">📧 Email Receipt</button>
                    <button class="btn btn-primary" data-action="finish-sale">✅ Finish Sale</button>
                </div>
            </div>`;
        this.showPOSModal('', modalHTML, 'custom');
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
        const receiptContent = this.generateReceiptContent(this.lastSaleData);
        const printWindow = window.open('', 'PRINT', 'height=600,width=800');
        printWindow.document.write(`<html><head><title>Receipt</title></head><body>${receiptContent}</body></html>`);
        printWindow.document.close();
        printWindow.focus();
        printWindow.print();
        printWindow.close();
    },

    async emailReceipt(orderId) {
        const email = prompt('Enter customer email address:');
        if (!email || !/^[\S]+@[\S]+\.[\S]+$/.test(email)) {
            this.showPOSModal('Invalid Email', 'Please enter a valid email address.', 'error');
            return;
        }

        this.showPOSModal('Sending Receipt...', 'Please wait...', 'info');

        try {
            const response = await fetch('/api/receipts/email', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ orderId, customerEmail: email, orderData: this.lastSaleData })
            });
            const data = await response.json();
            if (data.success) {
                this.showPOSModal('Email Sent!', data.message || `Receipt sent to ${email}`, 'success');
            } else {
                throw new Error(data.error || 'Unknown error');
            }
        } catch (error) {
            this.showPOSModal('Email Failed', error.message, 'error');
        }
    },

    finishSale() {
        this.hidePOSModal();
        this.cart = [];
        this.updateCartDisplay();
        const searchInput = document.getElementById('skuSearch');
        if(searchInput) searchInput.value = '';
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
            document.querySelector('#posModal .btn-primary').onclick = () => { this.hidePOSModal(); resolve(true); };
            document.querySelector('#posModal .btn-secondary').onclick = () => { this.hidePOSModal(); resolve(false); };
        });
    },

    showPaymentMethodSelector(total) {
        const message = `
            <h3>Total Due: $${total.toFixed(2)}</h3>
            <div class="payment-methods">
                <button class="payment-btn" data-method="Cash">💵 Cash</button>
                <button class="payment-btn" data-method="Card">💳 Card</button>
                <button class="payment-btn" data-method="Other">📱 Other</button>
            </div>`;
        return new Promise(resolve => {
            this.showPOSModal('Select Payment Method', message, 'info');
            document.querySelectorAll('#posModal .payment-btn').forEach(btn => {
                btn.onclick = () => {
                    this.hidePOSModal();
                    resolve(btn.dataset.method);
                };
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
                <div id="insufficientFunds" style="display: none; color: red;">Insufficient funds</div>
                <button id="acceptCashBtn" class="btn btn-primary" data-action="accept-cash" data-total="${total}" disabled>Accept</button>`;
            this.showPOSModal('Cash Payment', message, 'info');
            this.generateQuickAmountButtons(total);
            document.getElementById('cashReceived').oninput = () => this.calculateChange(total);
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

document.addEventListener('DOMContentLoaded', () => {
    POSModule.init();
});
    cart: [],
    allItems: [],
    lastSaleData: null,

    init() {
        const posRegister = document.querySelector('.pos-register');
        if (!posRegister) return;

        this.loadData();
        this.bindEventListeners(posRegister);
        this.showAllItems();
        this.setupSKUSearch();
    },

    loadData() {
        const dataElement = document.getElementById('pos-data');
        if (dataElement) {
            try {
                this.allItems = JSON.parse(dataElement.textContent);
            } catch (e) {
                console.error('Failed to parse POS data:', e);
            }
        }
    },

    bindEventListeners(container) {
        document.addEventListener('fullscreenchange', () => this.updateFullscreenButton(!!document.fullscreenElement));

        container.addEventListener('click', (e) => {
            const target = e.target.closest('[data-action]');
            if (!target) return;

            const action = target.dataset.action;
            
            switch (action) {
                case 'toggle-fullscreen':
                    this.toggleFullscreen();
                    break;
                case 'exit-pos':
                    window.location.href = '/?page=admin';
                    break;
                case 'browse-items':
                    this.showAllItems();
                    break;
                case 'checkout':
                    this.processCheckout();
                    break;
                case 'update-quantity':
                    this.updateQuantity(parseInt(target.dataset.index), parseInt(target.dataset.change));
                    break;
                case 'add-to-cart':
                    const item = this.allItems.find(i => i.sku === target.dataset.sku);
                    if (item) this.addToCart(item);
                    break;
                case 'print-receipt':
                    this.printReceipt();
                    break;
                case 'email-receipt':
                    this.emailReceipt(target.dataset.orderId);
                    break;
                case 'finish-sale':
                    this.finishSale();
                    break;
            }
        });
    },

    setupSKUSearch() {
        const skuSearch = document.getElementById('skuSearch');
        if (!skuSearch) return;

        skuSearch.addEventListener('input', (e) => {
            const sku = e.target.value.trim().toUpperCase();
            if (sku.length >= 3) {
                this.searchItemsBySku(sku);
            } else {
                this.showAllItems();
            }
        });

        skuSearch.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const sku = e.target.value.trim().toUpperCase();
                const item = this.allItems.find(i => i.sku.toUpperCase() === sku);
                if (item) {
                    this.addToCart(item);
                    e.target.value = '';
                    this.showAllItems();
                } else {
                    alert('Item not found: ' + sku);
                }
            }
        });
    },

    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => alert('Could not enter fullscreen mode.'));
        } else {
            document.exitFullscreen();
        }
    },

    updateFullscreenButton(isFullscreen) {
        const btn = document.querySelector('[data-action="toggle-fullscreen"]');
        if (btn) {
            btn.innerHTML = isFullscreen ? '🪟 Exit Full Screen' : '📺 Full Screen';
        }
    },

    showAllItems() {
        const grid = document.getElementById('itemsGrid');
        if (!grid) return;
        grid.innerHTML = '';
        this.allItems.forEach(item => {
            grid.appendChild(this.createItemCard(item));
        });
    },

    searchItemsBySku(searchTerm) {
        const filtered = this.allItems.filter(item => 
            item.sku.toUpperCase().includes(searchTerm) ||
            item.name.toUpperCase().includes(searchTerm)
        );
        const grid = document.getElementById('itemsGrid');
        grid.innerHTML = '';
        filtered.forEach(item => {
            grid.appendChild(this.createItemCard(item));
        });
    },

    createItemCard(item) {
        const card = document.createElement('div');
        card.className = 'item-card';
        card.dataset.action = 'add-to-cart';
        card.dataset.sku = item.sku;
        const imageUrl = item.imageUrl || '/images/items/placeholder.webp';
        card.innerHTML = `
            <img src="${imageUrl}" alt="${item.name}" class="item-image" onerror="this.src='/images/items/placeholder.webp'">
            <div class="item-name">${item.name}</div>
            <div class="item-sku">${item.sku}</div>
            <div class="item-price">$${parseFloat(item.retailPrice || 0).toFixed(2)}</div>
        `;
        return card;
    },

    addToCart(itemToAdd) {
        const existingItem = this.cart.find(item => item.sku === itemToAdd.sku);
        if (existingItem) {
            existingItem.quantity++;
        } else {
            this.cart.push({ ...itemToAdd, quantity: 1, price: parseFloat(itemToAdd.retailPrice) });
        }
        this.updateCartDisplay();
    },

    updateQuantity(index, change) {
        if (this.cart[index]) {
            this.cart[index].quantity += change;
            if (this.cart[index].quantity <= 0) {
                this.cart.splice(index, 1);
            }
        }
        this.updateCartDisplay();
    },

    updateCartDisplay() {
        const cartItemsEl = document.getElementById('cartItems');
        const cartTotalEl = document.getElementById('posCartTotal');
        const checkoutBtn = document.getElementById('checkoutBtn');

        if (this.cart.length === 0) {
            cartItemsEl.innerHTML = `<div class="empty-cart">Cart is empty</div>`;
            cartTotalEl.textContent = '$0.00';
            checkoutBtn.disabled = true;
            return;
        }

        let cartHTML = '';
        let subtotal = 0;
        this.cart.forEach((item, index) => {
            const itemTotal = item.price * item.quantity;
            subtotal += itemTotal;
            cartHTML += `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                    </div>
                    <div class="cart-item-controls">
                        <button class="qty-btn" data-action="update-quantity" data-index="${index}" data-change="-1">-</button>
                        <span class="qty-display">${item.quantity}</span>
                        <button class="qty-btn" data-action="update-quantity" data-index="${index}" data-change="1">+</button>
                        <span class="cart-item-price">$${itemTotal.toFixed(2)}</span>
                    </div>
                </div>`;
        });

        cartItemsEl.innerHTML = cartHTML;
        const tax = subtotal * 0.0825;
        const total = subtotal + tax;
        cartTotalEl.textContent = `$${total.toFixed(2)}`;
        checkoutBtn.disabled = false;
    },

    async processCheckout() {
        // This is a placeholder for the complex checkout logic involving modals.
        // A full implementation would require a more robust modal system.
        if (this.cart.length === 0) {
            alert('Cart is empty.');
            return;
        }
        alert(`Checkout processed for a total of ${document.getElementById('posCartTotal').textContent}`);
        this.cart = [];
        this.updateCartDisplay();
    },
    
    printReceipt() {
        alert('Printing receipt...');
    },

    emailReceipt(orderId) {
        alert(`Emailing receipt for order ${orderId}...`);
    },

    finishSale() {
        alert('Finishing sale...');
        this.cart = [];
        this.updateCartDisplay();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    POSModule.init();
});
