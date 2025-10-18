/**
 * WhimsicalFrog Cart System Module
 * Modular cart management with notifications - Vite compatible
 * Recovered and consolidated from legacy files
 */

import '../styles/cart-system.css';
import { normalizeAssetUrl, removeBrokenImage } from '../core/asset-utils.js';

class CartSystem {
    constructor() {
        this.state = {
            items: [],
            total: 0,
            count: 0,
            initialized: false,
            notifications: true
        };
        
        this.init();
    }

    init() {
        this.loadCart();
        this.setupEventListeners();
        // Ensure header/UI reflects persisted cart immediately on page load
        this.updateCartDisplay();
        // Mark initialized before notifying so listeners see ready state
        this.state.initialized = true;
        // Notify listeners (e.g., cart modal) that cart is ready so they can render
        try {
            window.dispatchEvent(new CustomEvent('cartUpdated', {
                detail: { action: 'init', state: this.getState() }
            }));
        } catch (_) {}
        console.log('[Cart] System initialized');
    }

    // Load cart from localStorage
    loadCart() {
        try {
            const saved = localStorage.getItem('whimsical_frog_cart');
            if (saved) {
                const data = JSON.parse(saved);
                this.state.items = data.items || [];
                this.recalculateTotal();
                console.log(`[Cart] Loaded ${this.state.items.length} items`);
            }
        } catch (error) {
            console.error(`[Cart] Error loading cart: ${error.message}`);
            this.state.items = [];
        }
    }

    // Save cart to localStorage
    saveCart() {
        try {
            const data = {
                items: this.state.items,
                total: this.state.total,
                count: this.state.count,
                timestamp: Date.now()
            };
            localStorage.setItem('whimsical_frog_cart', JSON.stringify(data));
        } catch (error) {
            console.error(`[Cart] Error saving cart: ${error.message}`);
        }
    }

    // Add item to cart
    addItem(item) {
        if (!item || !item.sku) {
            console.error('[Cart] Invalid item data:', item);
            return false;
        }

        const existingIndex = this.state.items.findIndex(cartItem => cartItem.sku === item.sku);
        const addedQuantity = parseInt(item.quantity) || 1;
        let isNewItem = false;

        if (existingIndex >= 0) {
            this.state.items[existingIndex].quantity += addedQuantity;
        } else {
            this.state.items.push({
                ...item,
                quantity: addedQuantity,
                addedAt: Date.now()
            });
            isNewItem = true;
        }

        this.recalculateTotal();
        this.saveCart();
        this.updateCartDisplay();

        if (this.state.notifications) {
            this.showAddToCartNotifications(item, addedQuantity, 
                this.state.items.find(cartItem => cartItem.sku === item.sku).quantity, isNewItem);
        }

        // Emit cart updated event (both internal bus and DOM event)
        if (window.WhimsicalFrog) {
            window.WhimsicalFrog.emit('cart:updated', { item, action: 'add' });
        }
        try {
            window.dispatchEvent(new CustomEvent('cartUpdated', {
                detail: { item, action: 'add', state: this.getState() }
            }));
        } catch (_) {}

        return true;
    }

    // Show add to cart notifications
    showAddToCartNotifications(item, addedQuantity, totalQuantity, isNewItem) {
        const itemName = item.name || item.sku || 'Item';
        const image = normalizeAssetUrl(item.image);
        
        // Main notification
        let message = `${itemName} `;
        if (isNewItem) {
            message += `added to cart (${totalQuantity})`;
        } else {
            message += `quantity updated (${totalQuantity})`;
        }

        this.showNotification(message, 'success');
        if (image) {
            try {
                const preview = document.querySelector('.cart-notification img');
                if (preview) {
                    preview.src = image;
                    preview.alt = itemName;
                    preview.addEventListener('error', () => removeBrokenImage(preview), { once: true });
                }
            } catch (_) {}
        }
        
        // Cart status after delay
        setTimeout(() => {
            this.showCartStatusToast();
        }, 1500);
    }

    // Show cart status notification
    showCartStatusToast() {
        const itemText = this.state.count === 1 ? 'item' : 'items';
        const message = `Cart: ${this.state.count} ${itemText} • $${this.state.total.toFixed(2)}`;
        this.showNotification(message, 'info', 3000);
    }

    // Generic notification system
    showNotification(message, type = 'info', duration = 2500) {
        // Prefer unified notification system
        if (window.wfNotifications && typeof window.wfNotifications.show === 'function') {
            window.wfNotifications.show(message, type, { duration });
            return;
        }
        // Legacy global helper if present
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type, { duration });
            return;
        }
        if (window.WhimsicalFrog && typeof window.WhimsicalFrog.showNotification === 'function') {
            window.WhimsicalFrog.showNotification(message, type, duration);
            return;
        }

        // Final fallback notification system (minimal styles)
        const notification = document.createElement('div');
        notification.className = 'cart-notification';
        // Map type to semantic classes
        if (type === 'success') notification.classList.add('is-success');
        else if (type === 'error') notification.classList.add('is-error');
        else notification.classList.add('is-info');
        notification.textContent = message;

        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        // Remove after duration
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    }

    // Remove item from cart
    removeItem(sku) {
        const index = this.state.items.findIndex(item => item.sku === sku);
        if (index >= 0) {
            const removedItem = this.state.items.splice(index, 1)[0];
            this.recalculateTotal();
            this.saveCart();
            this.updateCartDisplay();
            
            if (window.WhimsicalFrog) {
                window.WhimsicalFrog.emit('cart:updated', { item: removedItem, action: 'remove' });
            }
            try {
                window.dispatchEvent(new CustomEvent('cartUpdated', {
                    detail: { item: removedItem, action: 'remove', state: this.getState() }
                }));
            } catch (_) {}
            
            return true;
        }
        return false;
    }

    // Update item quantity
    updateItem(sku, quantity) {
        const item = this.state.items.find(item => item.sku === sku);
        if (item) {
            item.quantity = Math.max(0, parseInt(quantity) || 0);
            if (item.quantity === 0) {
                return this.removeItem(sku);
            }
            this.recalculateTotal();
            this.saveCart();
            this.updateCartDisplay();
            
            if (window.WhimsicalFrog) {
                window.WhimsicalFrog.emit('cart:updated', { item, action: 'update' });
            }
            try {
                window.dispatchEvent(new CustomEvent('cartUpdated', {
                    detail: { item, action: 'update', state: this.getState() }
                }));
            } catch (_) {}
            
            return true;
        }
        return false;
    }

    // Clear cart
    clearCart() {
        this.state.items = [];
        this.recalculateTotal();
        this.saveCart();
        this.updateCartDisplay();
        
        if (window.WhimsicalFrog) {
            window.WhimsicalFrog.emit('cart:cleared');
        }
        try {
            window.dispatchEvent(new CustomEvent('cartUpdated', {
                detail: { action: 'clear', state: this.getState() }
            }));
        } catch (_) {}
    }

    // Recalculate total
    recalculateTotal() {
        this.state.total = this.state.items.reduce((sum, item) => {
            return sum + (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0);
        }, 0);
        this.state.count = this.state.items.reduce((sum, item) => {
            return sum + (parseInt(item.quantity) || 0);
        }, 0);
    }

    // Update cart display in UI
    updateCartDisplay() {
        // Update cart counter (generic numeric badges)
        const counters = document.querySelectorAll('.cart-count, .cart-counter, #cart-count');
        counters.forEach(counter => {
            counter.textContent = this.state.count;
            counter.classList.toggle('hidden', this.state.count === 0);
        });

        // Update cart total (generic)
        const totals = document.querySelectorAll('.cart-total, #cart-total');
        totals.forEach(total => {
            total.textContent = `$${this.state.total.toFixed(2)}`;
        });

        // Update header-specific labels (id casing used in PHP header)
        const countLabels = document.querySelectorAll('#cartCount');
        countLabels.forEach(el => {
            const itemText = this.state.count === 1 ? 'item' : 'items';
            el.textContent = `${this.state.count} ${itemText}`;
        });
        const totalLabels = document.querySelectorAll('#cartTotal');
        totalLabels.forEach(el => {
            el.textContent = `$${this.state.total.toFixed(2)}`;
        });

        // Update cart button states
        const cartButtons = document.querySelectorAll('.cart-toggle, .cart-button');
        cartButtons.forEach(button => {
            button.classList.toggle('has-items', this.state.count > 0);
        });
    }

    // Setup event listeners
    setupEventListeners() {
        // Global add to cart buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.add-to-cart, [data-action="add-to-cart"]')) {
                e.preventDefault();
                this.handleAddToCartClick(e.target);
            }
        });

        // Cart quantity updates
        document.addEventListener('change', (e) => {
            if (e.target.matches('.cart-quantity-input')) {
                const sku = e.target.dataset.sku;
                const quantity = parseInt(e.target.value) || 0;
                this.updateItem(sku, quantity);
            }
        });

        // Remove buttons (robust: handle clicks on child elements)
        document.addEventListener('click', (e) => {
            const el = e.target && e.target.closest && e.target.closest('.remove-from-cart, [data-action="remove-from-cart"], .cart-item-remove');
            if (el) {
                e.preventDefault();
                // Stop propagation so product-card or room icon delegated click handlers
                // don't misinterpret this as an item click that opens the Add-to-Cart popup
                try { e.stopPropagation(); e.stopImmediatePropagation && e.stopImmediatePropagation(); } catch (_) {}
                const sku = el.dataset.sku || el.closest('[data-sku]')?.dataset.sku;
                if (sku) {
                    this.removeItem(sku);
                }
            }
        });
    }

    // Handle add to cart button clicks
    handleAddToCartClick(button) {
        const container = button.closest('[data-sku]') || button.closest('.product-card') || button.closest('.product-item');
        
        if (!container) {
            console.warn('[Cart] Could not find product container for add to cart button');
            return;
        }

        const item = {
            sku: container.dataset.sku || button.dataset.sku,
            name: container.dataset.name || container.querySelector('.product-name, .item-name')?.textContent?.trim(),
            price: container.dataset.price || container.querySelector('.product-price, .item-price')?.textContent?.replace(/[^0-9.]/g, ''),
            image: container.dataset.image || container.querySelector('img')?.src,
            quantity: 1
        };

        if (!item.sku) {
            console.error('[Cart] No SKU found for item:', container);
            return;
        }

        this.addItem(item);
    }

    // Render cart contents into the cart page container
    async renderCart() {
        const itemsContainer = document.getElementById('cartModalItems') || document.getElementById('cartItems');
        if (!itemsContainer) return;

        const footerEl = document.getElementById('cartModalFooter');
        const currency = (v) => `$${(parseFloat(v) || 0).toFixed(2)}`;

        if (!this.state.items.length) {
            itemsContainer.innerHTML = '<div class="p-6 text-center text-gray-600">Your cart is empty.</div>';
            if (footerEl) {
                footerEl.innerHTML = `
                  <div class="cart-footer-bar">
                    <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(this.state.total)}</strong></div>
                    <a class="cart-checkout-btn is-disabled" aria-disabled="true">Checkout</a>
                  </div>
                `;
            }
            return;
        }

        const itemsHtml = this.state.items.map(item => {
            const lineTotal = (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0);
            const img = item.image ? `<img src="${item.image}" alt="${item.name || item.sku}" class="cart-item-image"/>` : '';
            const optionBits = [];
            if (item.optionGender) optionBits.push(item.optionGender);
            if (item.optionSize) optionBits.push(item.optionSize);
            if (item.optionColor) optionBits.push(item.optionColor);
            const optionsHtml = optionBits.length ? `<div class="cart-item-options text-sm text-gray-500">${optionBits.join(' • ')}</div>` : '';
            return `
                <div class="cart-item" data-sku="${item.sku}">
                  ${img}
                  <div class="cart-item-details">
                    <div class="cart-item-title">${item.name || item.sku}</div>
                    ${optionsHtml}
                    <div class="cart-item-price">${currency(item.price)}</div>
                  </div>
                  <div class="cart-item-quantity">
                    <input type="number" min="0" class="cart-quantity-input" data-sku="${item.sku}" value="${item.quantity}" />
                  </div>
                  <div class="cart-item-remove remove-from-cart" data-sku="${item.sku}" aria-label="Remove item" title="Remove">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-trash" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                      <polyline points="3 6 5 6 21 6"></polyline>
                      <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path>
                      <path d="M10 11v6"></path>
                      <path d="M14 11v6"></path>
                      <path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"></path>
                    </svg>
                  </div>
                  <div class="cart-item-line-total">${currency(lineTotal)}</div>
                </div>
              `;
        }).join('');

        if (footerEl) {
            // Render items only in the items container; put summary in footer
            itemsContainer.innerHTML = itemsHtml;
            const disabledClass = this.state.count > 0 ? '' : 'is-disabled';
            const checkoutHref = this.state.count > 0 ? ' href="/payment"' : '';
            footerEl.innerHTML = `
              <div class="cart-footer-bar">
                <div class="cart-subtotal"><span>Subtotal</span><strong>${currency(this.state.total)}</strong></div>
                <a class="cart-checkout-btn ${disabledClass}"${checkoutHref}>Checkout</a>
              </div>
            `;
        } else {
            // Fallback: legacy summary appended below items
            const summaryHtml = `
              <div class="cart-summary">
                <div class="summary-row"><span>Subtotal</span><span>${currency(this.state.total)}</span></div>
                <div class="summary-total">Total: <span>${currency(this.state.total)}</span></div>
                <a class="cart-checkout-btn" href="/payment">Checkout</a>
              </div>
            `;
            itemsContainer.innerHTML = itemsHtml + summaryHtml;
        }
    }

    // Public API methods
    getItems() { return [...this.state.items]; }
    getTotal() { return this.state.total; }
    getCount() { return this.state.count; }
    getState() { return { ...this.state }; }
    // Reload from storage and notify listeners (useful after auth or cross-tab changes)
    refreshFromStorage() {
        try { this.loadCart(); } catch (_) {}
        try { this.saveCart(); } catch (_) {}
        try { this.updateCartDisplay(); } catch (_) {}
        try {
            window.dispatchEvent(new CustomEvent('cartUpdated', {
                detail: { action: 'refresh', state: this.getState() }
            }));
        } catch (_) {}
        return this.getState();
    }
    setNotifications(enabled) { this.state.notifications = !!enabled; }
}

// Export for ES6 modules
export default CartSystem;

// Also expose globally for compatibility
if (typeof window !== 'undefined') {
    window.CartSystem = CartSystem;
}
