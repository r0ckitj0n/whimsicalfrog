/**
 * WhimsicalFrog Cart System Module
 * Modular cart management with notifications - Vite compatible
 * Recovered and consolidated from legacy files
 */

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
        this.state.initialized = true;
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
        let addedQuantity = parseInt(item.quantity) || 1;
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

        // Emit cart updated event
        if (window.WhimsicalFrog) {
            window.WhimsicalFrog.emit('cart:updated', { item, action: 'add' });
        }

        return true;
    }

    // Show add to cart notifications
    showAddToCartNotifications(item, addedQuantity, totalQuantity, isNewItem) {
        const itemName = item.name || item.sku || 'Item';
        const price = parseFloat(item.price) || 0;
        
        // Main notification
        let message = `${itemName} `;
        if (isNewItem) {
            message += `added to cart (${totalQuantity})`;
        } else {
            message += `quantity updated (${totalQuantity})`;
        }

        this.showNotification(message, 'success');
        
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
        // Try to use existing notification system first
        if (window.WhimsicalFrog && window.WhimsicalFrog.showNotification) {
            window.WhimsicalFrog.showNotification(message, type, duration);
            return;
        }

        // Fallback notification system
        const notification = document.createElement('div');
        notification.className = `cart-notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
            color: white;
            padding: 12px 20px;
            border-radius: 4px;
            z-index: 10000;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transform: translateX(100%);
            transition: transform 0.3s ease;
        `;

        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Remove after duration
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
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
        // Update cart counter
        const counters = document.querySelectorAll('.cart-count, .cart-counter, #cart-count');
        counters.forEach(counter => {
            counter.textContent = this.state.count;
            counter.style.display = this.state.count > 0 ? 'inline' : 'none';
        });

        // Update cart total
        const totals = document.querySelectorAll('.cart-total, #cart-total');
        totals.forEach(total => {
            total.textContent = `$${this.state.total.toFixed(2)}`;
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

        // Remove buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('.remove-from-cart, [data-action="remove-from-cart"]')) {
                e.preventDefault();
                const sku = e.target.dataset.sku || e.target.closest('[data-sku]')?.dataset.sku;
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

    // Public API methods
    getItems() { return [...this.state.items]; }
    getTotal() { return this.state.total; }
    getCount() { return this.state.count; }
    getState() { return { ...this.state }; }
    setNotifications(enabled) { this.state.notifications = !!enabled; }
}

// Export for ES6 modules
export default CartSystem;

// Also expose globally for compatibility
if (typeof window !== 'undefined') {
    window.CartSystem = CartSystem;
}
