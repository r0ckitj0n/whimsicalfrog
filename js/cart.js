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
        const quantity = product.quantity || 1; // Use provided quantity or default to 1
        const existingItem = this.items.find(item => item.id === product.id);
        
        if (existingItem) {
            existingItem.quantity += quantity;
            console.log('Updated existing item quantity:', existingItem);
        } else {
            this.items.push({
                id: product.id,
                name: product.name,
                price: product.price,
                image: product.image,
                quantity: quantity
            });
            console.log('Added new item to cart');
        }
        
        this.saveCart();
        this.updateCartCount();
        this.showNotification(`${quantity > 1 ? quantity + ' items' : 'Item'} added to cart`);
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
        notification.className = 'fixed top-4 right-4 text-white px-6 py-3 rounded-lg shadow-lg z-50 font-medium';
        notification.style.backgroundColor = '#87ac3a'; // Your brand green color
        notification.style.border = '2px solid #6b8e23'; // Slightly darker green border
        notification.textContent = message;
        document.body.appendChild(notification);
        
        // Add a subtle fade-in animation
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-10px)';
        notification.style.transition = 'all 0.3s ease-in-out';
        
        // Trigger the fade-in
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateY(0)';
        }, 10);
        
        // Remove after 4 seconds (much more readable)
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-10px)';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 4000);
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

        const subtotal = this.getTotal();
        const salesTax = +(subtotal * 0.08).toFixed(2);
        const total = +(subtotal + salesTax).toFixed(2);

        // Debug cart items and their image paths
        console.log('Cart items with image paths:', this.items.map(item => ({
            id: item.id,
            name: item.name,
            image: item.image
        })));

        let html = `
            <div class="space-y-4">
                ${this.items.map(item => `
                    <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow">
                        <div class="flex items-center space-x-4">
                            <img src="${item.image}" alt="${item.name}" class="w-16 h-16 object-cover rounded" onerror="console.error('Failed to load image:', '${item.image}'); this.src='images/products/placeholder.png';">
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
                    <span>Subtotal:</span>
                    <span>$${subtotal.toFixed(2)}</span>
                </div>
                <div class="flex justify-between text-lg font-semibold">
                    <span>Sales Tax (8%):</span>
                    <span>$${salesTax.toFixed(2)}</span>
                </div>
                <div class="flex justify-between text-lg font-semibold">
                    <span>Total:</span>
                    <span>$${total.toFixed(2)}</span>
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
        // Check if user is logged in before allowing checkout
        let user = null;
        const userRaw = sessionStorage.getItem('user');
        if (userRaw) {
            try { user = JSON.parse(userRaw); } catch(e) { console.warn('Invalid user JSON in sessionStorage'); }
        }
        
        if (!user) {
            // User not logged in - redirect to login page
            localStorage.setItem('pendingCheckout', 'true');
            this.showNotification('Please log in to complete your checkout');
            setTimeout(() => {
                window.location.href = '/?page=login';
            }, 1500);
            return;
        }

        // User is logged in - proceed with checkout
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
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-xs relative" style="padding-top: 60px;">
                <a href="/?page=main_room" class="back-button text-[#556B2F]" style="position:absolute;top:16px;left:16px;background:rgba(107,142,35,0.9);color:white;padding:8px 14px;border-radius:25px;text-decoration:none;font-weight:bold;transition:all 0.3s ease;z-index:1000;cursor:pointer;pointer-events:auto;">
                    ← Back to Main Room
                </a>
                <button id="closePaymentMethodModal" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700">&times;</button>
                <h2 class="text-lg font-bold mb-4">Payment & Shipping</h2>
                <form id="paymentMethodForm">
                    <div class="mb-4">
                        <label class="block mb-2 font-medium">Payment Method</label>
                        <select id="paymentMethodSelect" class="w-full border-gray-300 rounded-md">
                            <option value="Cash">Cash</option>
                            <option value="Check">Check</option>
                            <option value="Credit Card" disabled>Credit Card (Coming Soon)</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block mb-2 font-medium">Shipping Method</label>
                        <select id="shippingMethodSelect" class="w-full border-gray-300 rounded-md">
                            <option value="Customer Pickup" selected>🏪 Customer Pickup - Pick up at store location</option>
                            <option value="Local Delivery">🚚 Local Delivery - Within our delivery area</option>
                            <option value="USPS">📮 USPS - United States Postal Service</option>
                            <option value="FedEx">📦 FedEx - Federal Express shipping</option>
                            <option value="UPS">🚛 UPS - United Parcel Service</option>
                        </select>
                        <div class="mt-2 text-xs text-gray-600">
                            💡 <strong>Tip:</strong> Customer Pickup saves on shipping costs and is available during store hours.
                        </div>
                    </div>
                    <div id="shippingInfoNotice" class="mb-4 p-3 bg-blue-50 border-l-4 border-blue-400 text-sm" style="display: none;">
                        <p class="font-medium text-blue-800 mb-2">📋 Important Shipping Information</p>
                        <p class="text-blue-700">
                            Shipping charges are calculated individually based on your order size, weight, and destination. 
                            As a small family-operated business, we'll determine the most cost-effective shipping method for you 
                            and notify you via text or email with the exact shipping/handling charge before finalizing your order. 
                            Your payment will only be processed after you approve the total cost. We handle all orders quickly 
                            and efficiently with personal attention to detail.
                        </p>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">Continue</button>
                    </div>
                </form>
            </div>
        `;
        document.body.appendChild(modal);
        document.getElementById('closePaymentMethodModal').onclick = () => modal.classList.add('hidden');
        
        // Add event listener for shipping method changes
        const shippingSelect = document.getElementById('shippingMethodSelect');
        const shippingInfoNotice = document.getElementById('shippingInfoNotice');
        
        // Function to toggle shipping info visibility
        const toggleShippingInfo = () => {
            if (shippingSelect.value === 'Customer Pickup') {
                shippingInfoNotice.style.display = 'none';
            } else {
                shippingInfoNotice.style.display = 'block';
            }
        };
        
        // Set initial state and add change listener
        toggleShippingInfo();
        shippingSelect.addEventListener('change', toggleShippingInfo);
        
        document.getElementById('paymentMethodForm').onsubmit = (e) => {
            e.preventDefault();
            const paymentMethod = document.getElementById('paymentMethodSelect').value;
            const shippingMethod = document.getElementById('shippingMethodSelect').value;
            modal.classList.add('hidden');
            this.submitCheckout(paymentMethod, shippingMethod);
        };
    }

    async submitCheckout(paymentMethod, shippingMethod) {
        console.log('Proceeding to checkout...');
        let user = null;
        const userRaw = sessionStorage.getItem('user');
        if (userRaw) {
            try { user = JSON.parse(userRaw); } catch(e) { console.warn('Invalid user JSON in sessionStorage'); }
        }
        if (!user) {
            localStorage.setItem('pendingCheckout', 'true');
            window.location.href = '/?page=login';
            return;
        }
        if (this.items.length === 0) {
            this.showNotification('Your cart is empty!');
            return;
        }
        const customerId = user.userId ? user.userId : user.id;
        const productIds = this.items.map(item => item.id);
        const quantities = this.items.map(item => item.quantity);
        const status = 'Pending';
        const date = new Date().toISOString().slice(0, 10);
        const apiEndpoint = window.location.origin + '/api/add-order.php';
        const subtotal = this.getTotal();
        const salesTax = +(subtotal * 0.08).toFixed(2);
        const total = +(subtotal + salesTax).toFixed(2);
        try {
            const response = await fetch(apiEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ customerId, productIds, quantities, status, date, paymentMethod, shippingMethod, subtotal, salesTax, total })
            });
            const data = await response.json();
            if (data.success) {
                this.showNotification('Order placed successfully!');
                this.clearCart();
                setTimeout(() => { window.location.href = '/?page=receipt&orderId=' + data.orderId; }, 1000);
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