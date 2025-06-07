class ShoppingCart {
    constructor() {
        console.log('Initializing ShoppingCart...');
        this.items = JSON.parse(localStorage.getItem('cart')) || [];
        console.log('Current cart items:', this.items);
        this.updateCartCount();
    }

    // Helper method to dispatch cart update event
    dispatchCartUpdate() {
        window.dispatchEvent(new Event('cartUpdated'));
    }

    addItem(product) {
        console.log('Adding item to cart:', product);
        const existingItem = this.items.find(item => item.id === product.id);
        
        if (existingItem) {
            existingItem.quantity += 1;
            console.log('Updated existing item quantity:', existingItem);
        } else {
            this.items.push({
                id: product.id,
                name: product.name,
                price: product.price,
                image: product.image,
                quantity: 1
            });
            console.log('Added new item to cart');
        }
        
        this.saveCart();
        this.updateCartCount();
        this.showNotification('Item added to cart');
        this.dispatchCartUpdate();
    }

    removeItem(productId) {
        console.log('Removing item from cart:', productId);
        this.items = this.items.filter(item => item.id !== productId);
        this.saveCart();
        this.updateCartCount();
        this.showNotification('Item removed from cart');
        this.dispatchCartUpdate();
    }

    updateQuantity(productId, quantity) {
        console.log('Updating quantity for item:', productId, 'to:', quantity);
        const item = this.items.find(item => item.id === productId);
        if (item) {
            item.quantity = Math.max(0, quantity);
            if (item.quantity === 0) {
                this.removeItem(productId);
            } else {
                this.saveCart();
                this.updateCartCount();
                this.dispatchCartUpdate();
            }
        }
    }

    getTotal() {
        return this.items.reduce((total, item) => total + (item.price * item.quantity), 0);
    }

    getItemCount() {
        return this.items.reduce((count, item) => count + item.quantity, 0);
    }

    clearCart() {
        console.log('Clearing cart');
        this.items = [];
        this.saveCart();
        this.updateCartCount();
    }

    saveCart() {
        console.log('Saving cart to localStorage:', this.items);
        localStorage.setItem('cart', JSON.stringify(this.items));
        this.updateCartCount();
    }

    updateCartCount() {
        const cartCount = document.getElementById('cartCount');
        const cartTotal = document.getElementById('cartTotal');
        if (cartCount && cartTotal) {
            const count = this.getItemCount();
            const total = this.getTotal();
            console.log('Updating cart count to:', count, 'and total to:', total);
            
            // Update count
            cartCount.textContent = count + ' items';
            cartCount.style.display = count > 0 ? 'inline' : 'none';
            
            // Update total
            cartTotal.textContent = `$${total.toFixed(2)}`;
            cartTotal.style.display = count > 0 ? 'inline' : 'none';
        } else {
            console.warn('Cart count or total element not found');
        }
    }

    showNotification(message) {
        console.log('Showing notification:', message);
        const notification = document.createElement('div');
        notification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded shadow-lg z-50';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 2000);
    }

    renderCart() {
        console.log('Rendering cart');
        const cartContainer = document.getElementById('cartItems');
        if (!cartContainer) {
            console.warn('Cart container not found');
            return;
        }

        if (this.items.length === 0) {
            cartContainer.innerHTML = '<p class="text-gray-500 text-center py-4">Your cart is empty</p>';
            return;
        }

        let html = `
            <div class="space-y-4">
                ${this.items.map(item => `
                    <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow">
                        <div class="flex items-center space-x-4">
                            <img src="${item.image}" alt="${item.name}" class="w-16 h-16 object-cover rounded">
                            <div>
                                <h3 class="font-semibold">${item.name}</h3>
                                <p class="text-gray-600">$${item.price.toFixed(2)}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="cart.updateQuantity('${item.id}', ${item.quantity - 1})" 
                                    class="px-2 py-1 bg-gray-200 rounded">-</button>
                            <span>${item.quantity}</span>
                            <button onclick="cart.updateQuantity('${item.id}', ${item.quantity + 1})" 
                                    class="px-2 py-1 bg-gray-200 rounded">+</button>
                            <button onclick="cart.removeItem('${item.id}')" 
                                    class="ml-4 text-red-500 hover:text-red-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <div class="flex justify-between text-lg font-semibold">
                    <span>Total:</span>
                    <span>$${this.getTotal().toFixed(2)}</span>
                </div>
                <button onclick="cart.checkout()" 
                        class="w-full mt-4 bg-[#6B8E23] hover:bg-[#556B2F] text-white font-bold py-2 px-4 rounded">
                    Proceed to Checkout
                </button>
            </div>
        `;

        cartContainer.innerHTML = html;
    }

    async checkout() {
        // Show payment method modal
        if (!document.getElementById('paymentMethodModal')) {
            this.createPaymentMethodModal();
        }
        document.getElementById('paymentMethodModal').classList.remove('hidden');
    }

    createPaymentMethodModal() {
        const modal = document.createElement('div');
        modal.id = 'paymentMethodModal';
        modal.className = 'fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-xs relative">
                <button id="closePaymentMethodModal" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700">&times;</button>
                <h2 class="text-lg font-bold mb-4">Select Payment Method</h2>
                <form id="paymentMethodForm">
                    <div class="mb-4">
                        <label class="block mb-2 font-medium">Payment Method</label>
                        <select id="paymentMethodSelect" class="w-full border-gray-300 rounded-md">
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="Credit Card" disabled>Credit Card (Coming Soon)</option>
                        </select>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">Continue</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
        document.getElementById('closePaymentMethodModal').onclick = () => modal.classList.add('hidden');
        document.getElementById('paymentMethodForm').onsubmit = (e) => {
            e.preventDefault();
            const paymentMethod = document.getElementById('paymentMethodSelect').value;
            modal.classList.add('hidden');
            this.submitCheckout(paymentMethod);
        };
    }

    async submitCheckout(paymentMethod) {
        console.log('Proceeding to checkout...');
        const user = JSON.parse(sessionStorage.getItem('user'));
        if (!user) {
            localStorage.setItem('pendingCheckout', 'true');
            window.location.href = '/?page=login';
            return;
        }
        if (this.items.length === 0) {
            this.showNotification('Your cart is empty!');
            return;
        }
        const customerId = user.id;
        const productIds = this.items.map(item => item.id);
        const quantities = this.items.map(item => item.quantity);
        const status = 'Pending';
        const date = new Date().toISOString().slice(0, 10);
        const apiBase = 'https://whimsicalfrog.us';
        try {
            const response = await fetch(apiBase + '/api/add-order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ customerId, productIds, quantities, status, date, paymentMethod })
            });
            const data = await response.json();
            if (data.success) {
                this.showNotification('Order placed successfully!');
                this.clearCart();
                setTimeout(() => { window.location.href = '/?page=orders'; }, 1500);
            } else {
                const msg = data.error || 'Unknown error';
                this.showNotification('Order creation failed: ' + msg);
            }
        } catch (error) {
            this.showNotification('Checkout failed: ' + error.message);
        }
    }
}

// Initialize cart only if it hasn't been initialized yet
if (typeof window.cart === 'undefined') {
    console.log('Loading cart.js...');
    window.cart = new ShoppingCart();
    console.log('Cart initialized globally');
} else {
    console.log('Cart already initialized');
} 