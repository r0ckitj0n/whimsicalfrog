class ShoppingCart {
    constructor() {
        console.log('Initializing ShoppingCart...');
        this.items = JSON.parse(localStorage.getItem('cart') || '[]');
        console.log('Current cart items:', this.items);
        this.updateCartCount();
    }

    dispatchCartUpdate() {
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated', { detail: { items: this.items } }));
    }

    async refreshProductData() {
        console.log('Refreshing product data from database');
        console.log('Current cart items before refresh:', this.items);
        try {
            // Get all SKUs in cart
            const itemSkus = this.items.map(item => item.sku);
            console.log('Extracted SKUs from cart:', itemSkus);
            
            // Filter out undefined/null SKUs
            const validSkus = itemSkus.filter(sku => sku && sku !== 'undefined');
            console.log('Valid SKUs after filtering:', validSkus);
            
            if (validSkus.length === 0) {
                console.warn('No valid SKUs found in cart');
                return;
            }

            // Fetch current item data from database
            console.log('Sending API request with SKUs:', validSkus);
            const response = await fetch('api/get_items.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ item_ids: validSkus })
            });

            if (!response.ok) {
                console.warn('Failed to fetch item data:', response.status);
                return;
            }

            const items = await response.json();
            console.log('Fetched fresh item data:', items);

            // Filter out items that no longer exist in the database
            const validItems = [];
            this.items.forEach(cartItem => {
                // Handle items with undefined/null SKUs
                if (!cartItem.sku || cartItem.sku === 'undefined') {
                    console.warn(`Removing invalid item from cart: ${cartItem.sku} - invalid SKU`);
                    this.showNotification(`Removed item with invalid SKU: ${cartItem.name || 'Unknown item'}`);
                    return;
                }
                
                const freshItem = items.find(p => p.sku === cartItem.sku);
                if (freshItem) {
                    // Update image path and name with fresh data from database
                    cartItem.image = freshItem.image || cartItem.image;
                    cartItem.name = freshItem.name || cartItem.name;
                    cartItem.price = parseFloat(freshItem.price) || cartItem.price;
                    validItems.push(cartItem);
                    console.log(`Updated cart item ${cartItem.sku} with fresh data:`, {
                        name: cartItem.name,
                        image: cartItem.image,
                        price: cartItem.price
                    });
                } else {
                    console.warn(`Removing invalid item from cart: ${cartItem.sku} - item no longer exists in database`);
                    this.showNotification(`Removed unavailable item: ${cartItem.name || cartItem.sku}`);
                }
            });

            // Update cart with only valid items
            if (validItems.length !== this.items.length) {
                const removedCount = this.items.length - validItems.length;
                this.items = validItems;
                this.saveCart();
                this.updateCartCount();
                console.log(`Cart cleaned: removed ${removedCount} invalid items`);
            }

        } catch (error) {
            console.warn('Error refreshing item data:', error);
        }
    }

    addItem(item) {
        console.log('Adding item to cart:', item);
        const quantity = item.quantity || 1; // Use provided quantity or default to 1
        const existingItem = this.items.find(cartItem => cartItem.sku === item.sku);
        
        if (existingItem) {
            existingItem.quantity += quantity;
            console.log('Updated existing item quantity:', existingItem);
        } else {
            this.items.push({
                sku: item.sku,
                name: item.name,
                price: item.price,
                image: item.image,
                quantity: quantity
            });
            console.log('Added new item to cart');
        }
        
        this.saveCart();
        this.updateCartCount();
        this.dispatchCartUpdate();
        
        // Track cart action for analytics
        if (window.analyticsTracker) {
            window.analyticsTracker.trackCartAction('add', item.sku);
        }
    }

    removeItem(itemSku) {
        console.log('Removing item from cart:', itemSku);
        this.items = this.items.filter(item => item.sku !== itemSku);
        this.saveCart();
        this.updateCartCount();
        this.dispatchCartUpdate();
        
        // Track cart action for analytics
        if (window.analyticsTracker) {
            window.analyticsTracker.trackCartAction('remove', itemSku);
        }
    }

    updateQuantity(itemSku, quantity) {
        console.log('Updating quantity for item:', itemSku, 'to:', quantity);
        const item = this.items.find(item => item.sku === itemSku);
        if (item) {
            item.quantity = Math.max(0, quantity);
            if (item.quantity === 0) {
                this.removeItem(itemSku);
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

    async renderCart() {
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

        // Refresh product data from database to get current image paths
        await this.refreshProductData();

        const subtotal = this.getTotal();
        const salesTax = +(subtotal * 0.08).toFixed(2);
        const total = +(subtotal + salesTax).toFixed(2);

        // Debug cart items and their image paths
        console.log('Cart items with image paths:', this.items.map(item => ({
            sku: item.sku,
            name: item.name,
            image: item.image
        })));

        let html = `
            <div class="space-y-4">
                ${this.items.map(item => `
                    <div class="flex items-center justify-between p-4 bg-white rounded-lg shadow">
                        <div class="flex items-center space-x-4">
                            <img src="${item.image}" alt="${item.name}" class="w-16 h-16 object-cover rounded" onerror="this.src='images/items/placeholder.png'; this.onerror=null;">
                            <div>
                                <h3 class="font-semibold">${item.name}</h3>
                                <p class="text-gray-600">$${item.price.toFixed(2)}</p>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="cart.updateQuantity('${item.sku}', ${item.quantity - 1})" 
                                    class="px-2 py-1 bg-gray-200 rounded">-</button>
                            <span>${item.quantity}</span>
                            <button onclick="cart.updateQuantity('${item.sku}', ${item.quantity + 1})" 
                                    class="px-2 py-1 bg-gray-200 rounded">+</button>
                            <button onclick="cart.removeItem('${item.sku}')" 
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
                        class="w-full mt-4 text-white font-bold py-2 px-4 rounded"
                        style="background-color: #87ac3a !important; color: white !important;"
                        onmouseover="this.style.backgroundColor='#a3cc4a'"
                        onmouseout="this.style.backgroundColor='#87ac3a'">
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
        
        // Track checkout start for analytics
        if (window.analyticsTracker) {
            window.analyticsTracker.trackInteraction('checkout_start', null);
        }
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
                        <button type="submit" class="px-4 py-2 text-white rounded" style="background-color: #87ac3a !important; color: white !important;" onmouseover="this.style.backgroundColor='#a3cc4a'" onmouseout="this.style.backgroundColor='#87ac3a'">Continue</button>
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
        
        // Refresh cart data and filter out invalid items before checkout
        await this.refreshProductData();
        
        if (this.items.length === 0) {
            this.showNotification('All items in your cart are no longer available!');
            return;
        }
        
        const customerId = user.userId ? user.userId : user.id;
        
        // Validate all SKUs before sending
        const itemIds = this.items.map(item => item.sku).filter(sku => sku && sku.trim() !== '');
        const quantities = this.items.map(item => item.quantity);
        
        if (itemIds.length !== this.items.length) {
            console.error('Invalid SKUs found in cart:', this.items);
            this.showNotification('Some items in your cart are invalid. Please refresh the page and try again.');
            return;
        }
        
        console.log('Checkout data:', { customerId, itemIds, quantities, paymentMethod, shippingMethod });
        
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
                body: JSON.stringify({ customerId, itemIds, quantities, status, date, paymentMethod, shippingMethod, subtotal, salesTax, total })
            });
            const data = await response.json();
            if (data.success) {
                this.showNotification('Order placed successfully!');
                
                // Track conversion for analytics
                if (window.analyticsTracker) {
                    window.analyticsTracker.trackConversion(total, data.orderId);
                }
                
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

async function addToCart(sku, name, price, imageUrl = null) {
    try {
        // Use the global cart instance
        if (window.cart) {
            window.cart.addItem({
                sku: sku,
                name: name,
                price: parseFloat(price),
                image: imageUrl || 'images/items/placeholder.png',
                quantity: 1
            });
        } else {
            console.error('Cart not initialized');
        }
    } catch (error) {
        console.error('Error adding item to cart:', error);
        showNotification('Error adding item to cart', 'error');
    }
}

// Make addToCart globally available
window.addToCart = addToCart;

// Cart cleanup utility function
window.cleanupCart = function() {
    if (window.cart) {
        console.log('Cleaning up cart...');
        const originalLength = window.cart.items.length;
        
        // Remove items with undefined/null/invalid SKUs
        window.cart.items = window.cart.items.filter(item => {
            const isValid = item.sku && item.sku !== 'undefined' && item.sku.trim() !== '';
            if (!isValid) {
                console.log('Removing invalid item:', item);
            }
            return isValid;
        });
        
        const removedCount = originalLength - window.cart.items.length;
        if (removedCount > 0) {
            window.cart.saveCart();
            window.cart.updateCartCount();
            console.log(`Removed ${removedCount} invalid items from cart`);
            window.cart.showNotification(`Cleaned up cart: removed ${removedCount} invalid items`);
        } else {
            console.log('No invalid items found in cart');
            window.cart.showNotification('Cart is already clean - no invalid items found');
        }
    }
};

// Emergency cart clear function
window.emergencyClearCart = function() {
    if (window.cart) {
        window.cart.clearCart();
        window.cart.showNotification('Cart cleared successfully!');
        console.log('Emergency cart clear completed');
    }
};

function removeFromCart(sku) {
    if (window.cart) {
        window.cart.removeItem(sku);
        // Force immediate refresh of cart display if we're on the cart page
        if (document.getElementById('cartItems')) {
            setTimeout(() => {
                window.cart.renderCart();
            }, 100);
        }
    }
}

function updateQuantity(sku, newQuantity) {
    if (window.cart) {
        window.cart.updateQuantity(sku, parseInt(newQuantity));
    }
} 