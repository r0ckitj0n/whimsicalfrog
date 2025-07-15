/**
 * WhimsicalFrog Cart System Module
 * Unified cart management with notifications
 */

(function() {
    'use strict';

    // Cart system state
    const cartState = {
        items: [],
        total: 0,
        count: 0,
        initialized: false,
        notifications: true
    };

    // Cart system methods
    const cartMethods = {
        // Load cart from localStorage
        loadCart() {
            try {
                const saved = localStorage.getItem('whimsical_frog_cart');
                if (saved) {
                    const data = JSON.parse(saved);
                    cartState.items = data.items || [];
                    this.recalculateTotal();
                    console.log(`[Cart] Loaded ${cartState.items.length} items`);
                }
            } catch (error) {
                console.error(`[Cart] Error loading cart: ${error.message}`);
                cartState.items = [];
            }
        },

        // Save cart to localStorage
        saveCart() {
            try {
                const data = {
                    items: cartState.items,
                    total: cartState.total,
                    count: cartState.count,
                    timestamp: Date.now()
                };
                localStorage.setItem('whimsical_frog_cart', JSON.stringify(data));
                console.log('[Cart] Saved to localStorage');
            } catch (error) {
                console.error(`[Cart] Error saving cart: ${error.message}`);
            }
        },

        // Add item to cart
        addItem(item) {
            const existingIndex = cartState.items.findIndex(i => i.sku === item.sku);
            const addedQuantity = item.quantity || 1;
            let finalQuantity = addedQuantity;
            let isNewItem = false;
            
            if (existingIndex !== -1) {
                // Update existing item
                cartState.items[existingIndex].quantity += addedQuantity;
                finalQuantity = cartState.items[existingIndex].quantity;
            } else {
                // Add new item
                isNewItem = true;
                cartState.items.push({
                    sku: item.sku,
                    name: item.name,
                    price: item.price,
                    quantity: addedQuantity,
                    image: item.image || `images/items/${item.sku}A.webp`,
                    gender: item.gender,
                    color: item.color,
                    size: item.size
                });
            }
            
            this.recalculateTotal();
            this.updateCartDisplay();
            this.saveCart();
            
            // Show dual notification system (item added + cart status)
            if (cartState.notifications) {
                this.showAddToCartNotifications(item, addedQuantity, finalQuantity, isNewItem);
            }
            
            console.log(`[Cart] Added item: ${item.name} (${item.sku})`);
        },

        // Show dual cart notifications (matches June 30th config)
        showAddToCartNotifications(item, addedQuantity, totalQuantity, isNewItem) {
            try {
                // Build comprehensive notification message for what was added
                let displayName = item.name;
                const detailParts = [];
                
                if (item.gender) detailParts.push(item.gender);
                if (item.color) detailParts.push(item.color);
                if (item.size) detailParts.push(item.size);
                
                if (detailParts.length > 0) {
                    displayName += ` (${detailParts.join(', ')})`;
                }
                
                // Format price and create comprehensive message
                const formattedPrice = '$' + (parseFloat(item.price) || 0).toFixed(2);
                const quantityText = addedQuantity > 1 ? ` (${addedQuantity})` : '';
                
                // Create the main "item added" notification message
                const itemNotificationMessage = `🛒 ${displayName}${quantityText} - ${formattedPrice}`;
                
                // Show the main notification with title
                if (window.wfNotifications && typeof window.wfNotifications.success === 'function') {
                    window.wfNotifications.success(itemNotificationMessage, {
                        title: '✅ Added to Cart',
                        duration: 5000
                    });
                } else if (window.showSuccess) {
                    window.showSuccess(itemNotificationMessage, {
                        title: '✅ Added to Cart',
                        duration: 5000
                    });
                } else {
                    console.log('[Cart] No notification system available, using alert');
                    alert(itemNotificationMessage);
                }
                
                // Show cart status toast after a brief delay (1.5 seconds)
                setTimeout(() => {
                    this.showCartStatusToast();
                }, 1500);
                
            } catch (error) {
                console.error('[Cart] Error showing notifications:', error);
                // Fallback to simple notification
                const fallbackMessage = `${item.name} added to cart!`;
                if (window.alert) {
                    alert(fallbackMessage);
                } else {
                    console.log(`[Cart] ${fallbackMessage}`);
                }
            }
        },

        // Show cart status notification (total items and price)
        showCartStatusToast() {
            const itemCount = cartState.count;
            const total = cartState.total;
            const formattedTotal = '$' + total.toFixed(2);
            
            let statusMessage;
            if (itemCount === 0) {
                statusMessage = 'Cart is empty';
            } else if (itemCount === 1) {
                statusMessage = `🛒 1 item • ${formattedTotal}`;
            } else {
                statusMessage = `🛒 ${itemCount} items • ${formattedTotal}`;
            }
            
            // Show cart status toast with branded notification system
            try {
                if (window.wfNotifications && typeof window.wfNotifications.success === 'function') {
                    window.wfNotifications.success(statusMessage, {
                        title: 'Cart Status',
                        duration: 5000 // Show for 5 seconds
                    });
                } else if (window.showSuccess) {
                    window.showSuccess(statusMessage, {
                        title: 'Cart Status',
                        duration: 5000
                    });
                } else {
                    console.log('[Cart] Cart status:', statusMessage);
                }
            } catch (error) {
                console.error('[Cart] Error showing cart status:', error);
            }
        },

        // Remove item from cart
        removeItem(sku) {
            const index = cartState.items.findIndex(item => item.sku === sku);
            if (index !== -1) {
                const item = cartState.items[index];
                cartState.items.splice(index, 1);
                
                this.recalculateTotal();
                this.updateCartDisplay();
                this.saveCart();
                
                // Show notification
                if (cartState.notifications && window.showInfo) {
                    window.showInfo(`${item.name} removed from cart`);
                }
                
                console.log(`[Cart] Removed item: ${item.name} (${sku})`);
            }
        },

        // Update item quantity
        updateItem(sku, quantity) {
            const index = cartState.items.findIndex(item => item.sku === sku);
            if (index !== -1) {
                if (quantity <= 0) {
                    this.removeItem(sku);
                } else {
                    cartState.items[index].quantity = quantity;
                    
                    this.recalculateTotal();
                    this.updateCartDisplay();
                    this.saveCart();
                    
                    console.log(`[Cart] Updated item: ${sku} quantity to ${quantity}`);
                }
            }
        },

        // Clear cart
        clearCart() {
            cartState.items = [];
            cartState.total = 0;
            cartState.count = 0;
            
            this.updateCartDisplay();
            this.saveCart();
            
            // Show notification
            if (cartState.notifications && window.showInfo) {
                window.showInfo('Cart cleared');
            }
            
            console.log('[Cart] Cart cleared');
        },

        // Recalculate total
        recalculateTotal() {
            cartState.total = cartState.items.reduce((sum, item) => {
                return sum + (item.price * item.quantity);
            }, 0);
            
            cartState.count = cartState.items.reduce((sum, item) => {
                return sum + item.quantity;
            }, 0);
        },

        // Update cart display in UI
        updateCartDisplay() {
            console.log('[Cart] Updating cart display...', { count: cartState.count, total: cartState.total });
            
            // Update cart count (main navigation uses #cartCount)
            const cartCountElement = document.querySelector('#cartCount');
            if (cartCountElement) {
                const countText = cartState.count === 1 ? '1 item' : `${cartState.count} items`;
                cartCountElement.textContent = countText;
                console.log('[Cart] Updated #cartCount:', countText);
            } else {
                console.log('[Cart] #cartCount element not found');
            }

            // Update cart total (main navigation uses #cartTotal)
            const cartTotalElement = document.querySelector('#cartTotal');
            if (cartTotalElement) {
                const totalText = `$${cartState.total.toFixed(2)}`;
                cartTotalElement.textContent = totalText;
                cartTotalElement.classList.toggle('cart-hidden', cartState.count === 0);
                console.log('[Cart] Updated #cartTotal:', totalText);
            } else {
                console.log('[Cart] #cartTotal element not found');
            }
            
            // Also update legacy selectors for compatibility
            const cartBadge = document.querySelector('.cart-badge');
            if (cartBadge) {
                cartBadge.textContent = cartState.count;
                cartBadge.classList.toggle('cart-hidden', cartState.count === 0);
            }

            const cartCounter = document.querySelector('.cart-counter');
            if (cartCounter) {
                cartCounter.textContent = cartState.count;
            }

            const cartTotalLegacy = document.querySelector('.cart-total');
            if (cartTotalLegacy) {
                cartTotalLegacy.textContent = `$${cartState.total.toFixed(2)}`;
            }
            
            console.log('[Cart] Cart display update complete');
        },

        // Get cart items
        getItems() {
            return [...cartState.items];
        },

        // Get cart total
        getTotal() {
            return cartState.total;
        },

        // Get cart count
        getCount() {
            return cartState.count;
        },

        // Get cart state
        getState() {
            return cartState;
        },

        // Set notifications enabled/disabled
        setNotifications(enabled) {
            cartState.notifications = enabled;
            console.log(`[Cart] Notifications ${enabled ? 'enabled' : 'disabled'}`);
        },

        // Show current cart status manually (can be called anytime)
        showCurrentCartStatus() {
            const itemCount = cartState.count;
            const total = cartState.total;
            const formattedTotal = '$' + total.toFixed(2);
            
            let statusMessage;
            let statusTitle;
            
            if (itemCount === 0) {
                statusMessage = 'Your cart is empty';
                statusTitle = 'Cart Status';
            } else if (itemCount === 1) {
                statusMessage = `🛒 1 item • ${formattedTotal}`;
                statusTitle = 'Cart Status'; 
            } else {
                statusMessage = `🛒 ${itemCount} items • ${formattedTotal}`;
                statusTitle = 'Cart Status';
            }
            
            try {
                if (window.wfNotifications && typeof window.wfNotifications.info === 'function') {
                    window.wfNotifications.info(statusMessage, {
                        title: statusTitle,
                        duration: 5000 // Show for 5 seconds when manually called
                    });
                } else if (window.showInfo) {
                    window.showInfo(statusMessage, {
                        title: statusTitle,
                        duration: 5000
                    });
                } else {
                    console.log('[Cart] Cart status:', statusMessage);
                }
            } catch (error) {
                console.error('[Cart] Error showing cart status:', error);
            }
        },

        // Render cart items for the cart page
        async renderCart() {
            let cartContainer = document.getElementById('cartContainer');
            if (!cartContainer) {
                cartContainer = document.getElementById('cartItems');
            }
            
            if (!cartContainer) {
                console.warn('[Cart] Cart container not found');
                return;
            }
            
            console.log('[Cart] Rendering cart with', cartState.items.length, 'items');
            
            // Update cart from localStorage
            this.loadCart();
            
            if (cartState.items.length === 0) {
                cartContainer.innerHTML = '<div class="text-center py-8 text-gray-500">Your cart is empty</div>';
                return;
            }

            // Build cart HTML
            const cartHTML = cartState.items.map(item => {
                const imageUrl = item.image || `/images/items/${item.sku}A.webp`;
                const displayName = item.name || item.sku;
                const unitPrice = parseFloat(item.price) || 0;
                const quantity = parseInt(item.quantity) || 1;
                const lineTotal = unitPrice * quantity;
                
                return `
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                            <img src="${imageUrl}" alt="${displayName}" class="w-full h-full object-contain" 
                                 onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\\'u-cart-fallback\\'><div class=\\'u-cart-fallback-icon\\'>📷</div><div>No Image</div></div>';">
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">${displayName}</h3>
                            <p class="text-xs text-gray-400 font-mono">${item.sku}</p>
                            <p class="text-sm text-gray-500">$${unitPrice.toFixed(2)}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="window.cart.updateItem('${item.sku}', ${quantity - 1})" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded" ${quantity <= 1 ? 'disabled' : ''}>-</button>
                        <span class="px-3 py-1 bg-gray-100 rounded">${quantity}</span>
                        <button onclick="window.cart.updateItem('${item.sku}', ${quantity + 1})" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded">+</button>
                        <button onclick="window.cart.removeItem('${item.sku}')" class="px-2 py-1 rounded ml-2 bg-red-600 text-white hover:bg-red-700">Remove</button>
                    </div>
                </div>
                `;
            }).join('');

            const total = this.getTotal();
            const cartContentHTML = `
                <div class="flex-1 overflow-y-auto cart-scrollbar">
                    ${cartHTML}
                </div>
                <div class="flex-shrink-0 border-t border-gray-200 bg-gray-50 p-4">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-lg font-semibold">Total: $${total.toFixed(2)}</span>
                        <button onclick="window.cart.clearCart(); window.location.reload();" class="px-4 py-2 rounded bg-gray-600 text-white hover:bg-gray-700">Clear Cart</button>
                    </div>
                    <button onclick="window.cart.checkout()" class="brand-button w-full py-3 px-6 rounded-lg font-semibold">Proceed to Checkout</button>
                </div>
            `;
            
            cartContainer.innerHTML = cartContentHTML;
        },

        async checkout() {
            // Check if user is logged in
            const userRaw = sessionStorage.getItem('user');
            let user = null;
            if (userRaw) {
                try { user = JSON.parse(userRaw); } catch(e) { console.warn('Invalid user JSON in sessionStorage'); }
            }

            if (!user) {
                // Set flag for pending checkout
                localStorage.setItem('pendingCheckout', 'true');
                
                // Store cart redirect intent in PHP session via AJAX
                try {
                    await fetch('/api/set_redirect.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ redirectUrl: '/?page=cart' })
                    });
                } catch (e) {
                    console.warn('Could not set server-side redirect');
                }
                
                // Redirect to login page
                window.location.href = '/?page=login';
                return;
            }

            // Create payment method modal
            this.createPaymentMethodModal();
        },

        createPaymentMethodModal() {
            // Remove existing modal if any
            const existingModal = document.getElementById('paymentMethodModal');
            if (existingModal) {
                existingModal.remove();
            }

            const modal = document.createElement('div');
            modal.id = 'paymentMethodModal';
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            
            const paymentMethods = [
                { value: 'credit_card', label: 'Credit Card', icon: '💳' },
                { value: 'paypal', label: 'PayPal', icon: '🅿️' },
                { value: 'check', label: 'Check', icon: '🏦' },
                { value: 'cash', label: 'Cash', icon: '💵' },
                { value: 'venmo', label: 'Venmo', icon: '💸' }
            ];

            const shippingMethods = [
                { value: 'pickup', label: 'Customer Pickup', icon: '🏪' },
                { value: 'local_delivery', label: 'Local Delivery', icon: '🚚' },
                { value: 'usps', label: 'USPS', icon: '📫' },
                { value: 'fedex', label: 'FedEx', icon: '📦' },
                { value: 'ups', label: 'UPS', icon: '🚛' }
            ];

            // Make toggleShippingInfo globally available
            window.toggleShippingInfo = () => {
                const shippingMethod = document.querySelector('input[name="shippingMethod"]:checked')?.value;
                const shippingInfo = document.getElementById('shippingInfo');
                if (shippingMethod && shippingMethod !== 'pickup') {
                    shippingInfo.style.display = 'block';
                } else {
                    shippingInfo.style.display = 'none';
                }
            };

            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h2 class="text-xl font-bold mb-4">Checkout</h2>
                    
                    <div class="mb-4">
                        <h3 class="font-semibold mb-2">Payment Method</h3>
                        ${paymentMethods.map(method => `
                            <label class="flex items-center mb-2 cursor-pointer">
                                <input type="radio" name="paymentMethod" value="${method.value}" class="mr-2">
                                <span class="mr-2">${method.icon}</span>
                                <span>${method.label}</span>
                            </label>
                        `).join('')}
                    </div>

                    <div class="mb-4">
                        <h3 class="font-semibold mb-2">Shipping Method</h3>
                        ${shippingMethods.map(method => `
                            <label class="flex items-center mb-2 cursor-pointer">
                                <input type="radio" name="shippingMethod" value="${method.value}" class="mr-2" onchange="toggleShippingInfo()">
                                <span class="mr-2">${method.icon}</span>
                                <span>${method.label}</span>
                            </label>
                        `).join('')}
                    </div>

                    <div id="shippingInfo" class="hidden mb-4 p-3 bg-gray-100 rounded">
                        <h4 class="font-semibold mb-2">Shipping Address</h4>
                        
                        <!- Address Selection Options ->
                        <div class="mb-3">
                            <label class="flex items-center mb-2">
                                <input type="radio" name="addressOption" value="profile" class="mr-2" checked onchange="window.cart.toggleAddressFields()">
                                <span>Use my profile address</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="addressOption" value="custom" class="mr-2" onchange="window.cart.toggleAddressFields()">
                                <span>Enter a different delivery address</span>
                            </label>
                        </div>
                        
                        <!- Profile Address Display ->
                        <div id="profileAddressDisplay" class="mb-3 p-3 bg-white border rounded">
                            <div class="text-sm text-gray-600" id="profileAddressText">Loading your address...</div>
                        </div>
                        
                        <!- Custom Address Fields ->
                        <div id="customAddressFields" class="hidden space-y-2">
                            <input type="text" id="customAddressLine1" placeholder="Address Line 1" class="w-full p-2 border rounded">
                            <input type="text" id="customAddressLine2" placeholder="Address Line 2 (Optional)" class="w-full p-2 border rounded">
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" id="customCity" placeholder="City" class="p-2 border rounded">
                                <input type="text" id="customState" placeholder="State" class="p-2 border rounded">
                                <input type="text" id="customZipCode" placeholder="ZIP Code" class="p-2 border rounded">
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-2">
                        <button type="button" class="flex-1 py-2 px-4 rounded text-white u-cart-btn-cancel">Cancel</button>
                        <button onclick="window.cart.proceedToCheckout()" class="brand-button flex-1 py-2 px-4 rounded">Place Order</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Add event listener for shipping method changes
            modal.querySelectorAll('input[name="shippingMethod"]').forEach(input => {
                input.addEventListener('change', window.toggleShippingInfo);
            });
            
            // Load user profile address when shipping info is shown
            this.loadProfileAddress();
        },

        async proceedToCheckout() {
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked')?.value;
            const shippingMethod = document.querySelector('input[name="shippingMethod"]:checked')?.value;

            if (!paymentMethod || !shippingMethod) {
                if (window.wfNotifications && window.wfNotifications.warning) {
                    window.wfNotifications.warning('Please select both payment and shipping methods.', { duration: 5000 });
                } else {
                    alert('Please select both payment and shipping methods.');
                }
                return;
            }

            // Get user info
            const userRaw = sessionStorage.getItem('user');
            let user = null;
            if (userRaw) {
                try { user = JSON.parse(userRaw); } catch(e) { console.warn('Invalid user JSON in sessionStorage'); }
            }

            if (!user) {
                if (window.wfNotifications && window.wfNotifications.warning) {
                    window.wfNotifications.warning('Please log in to complete your order.', { duration: 5000 });
                } else {
                    alert('Please log in to complete your order.');
                }
                setTimeout(() => {
                    window.location.href = '/?page=login';
                }, 1500);
                return;
            }

            await this.submitCheckout(paymentMethod, shippingMethod);
        },

        async submitCheckout(paymentMethod, shippingMethod) {
            try {
                console.log('Session storage user:', sessionStorage.getItem('user'));
                const customerId = sessionStorage.getItem('user') ? JSON.parse(sessionStorage.getItem('user')).userId : null;
                console.log('Customer ID:', customerId);
                
                if (!customerId) {
                    if (window.wfNotifications && window.wfNotifications.warning) {
                        window.wfNotifications.warning('Please log in to complete your order.', { duration: 5000 });
                    } else {
                        alert('Please log in to complete your order.');
                    }
                    return;
                }

                // Validate cart items have valid SKUs
                const invalidItems = cartState.items.filter(item => !item.sku || item.sku === 'undefined');
                if (invalidItems.length > 0) {
                    console.error('Invalid SKUs found in cart:', cartState.items);
                    if (window.wfNotifications && window.wfNotifications.error) {
                        window.wfNotifications.error('Some items in your cart are invalid. Please refresh the page and try again.', { duration: 5000 });
                    } else {
                        alert('Some items in your cart are invalid. Please refresh the page and try again.');
                    }
                    return;
                }

                // Collect shipping address information if needed
                let shippingAddress = null;
                if (shippingMethod !== 'pickup') {
                    const addressOption = document.querySelector('input[name="addressOption"]:checked')?.value;
                    
                    if (addressOption === 'profile') {
                        // Use profile address
                        if (this.userProfileData) {
                            shippingAddress = {
                                addressLine1: this.userProfileData.addressLine1 || '',
                                addressLine2: this.userProfileData.addressLine2 || '',
                                city: this.userProfileData.city || '',
                                state: this.userProfileData.state || '',
                                zipCode: this.userProfileData.zipCode || ''
                            };
                        }
                    } else if (addressOption === 'custom') {
                        // Use custom address
                        const line1 = document.getElementById('customAddressLine1')?.value?.trim() || '';
                        const line2 = document.getElementById('customAddressLine2')?.value?.trim() || '';
                        const city = document.getElementById('customCity')?.value?.trim() || '';
                        const state = document.getElementById('customState')?.value?.trim() || '';
                        const zipCode = document.getElementById('customZipCode')?.value?.trim() || '';
                        
                        if (!line1 || !city || !state || !zipCode) {
                            if (window.wfNotifications && window.wfNotifications.warning) {
                                window.wfNotifications.warning('Please fill in all required address fields (Address Line 1, City, State, ZIP Code).', { duration: 5000 });
                            } else {
                                alert('Please fill in all required address fields (Address Line 1, City, State, ZIP Code).');
                            }
                            return;
                        }
                        
                        shippingAddress = {
                            addressLine1: line1,
                            addressLine2: line2,
                            city: city,
                            state: state,
                            zipCode: zipCode
                        };
                    }
                    
                    // Validate that we have a shipping address for non-pickup orders
                    if (!shippingAddress || !shippingAddress.addressLine1) {
                        if (window.wfNotifications && window.wfNotifications.warning) {
                            window.wfNotifications.warning('Please provide a shipping address for delivery orders.', { duration: 5000 });
                        } else {
                            alert('Please provide a shipping address for delivery orders.');
                        }
                        return;
                    }
                }

                const itemIds = cartState.items.map(item => item.sku);
                const quantities = cartState.items.map(item => item.quantity);
                const colors = cartState.items.map(item => item.color || null);
                const sizes = cartState.items.map(item => item.size || null);

                const orderData = {
                    customerId: customerId,
                    itemIds: itemIds,
                    quantities: quantities,
                    colors: colors,
                    sizes: sizes,
                    paymentMethod: paymentMethod,
                    shippingMethod: shippingMethod,
                    total: this.getTotal()
                };

                // Add shipping address if provided
                if (shippingAddress) {
                    orderData.shippingAddress = shippingAddress;
                }

                console.log('Order data being sent:', orderData);
                console.log('Cart items:', cartState.items);
                console.log('Cart total:', this.getTotal());

                const response = await fetch('/api/add-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(orderData)
                });

                if (!response.ok) {
                    console.error('Server error:', response.status, response.statusText);
                    const errorText = await response.text();
                    console.error('Error response:', errorText);
                    if (window.wfNotifications && window.wfNotifications.error) {
                        window.wfNotifications.error(`Server error (${response.status}): Please try again or contact support.`, { duration: 5000 });
                    } else {
                        alert(`Server error (${response.status}): Please try again or contact support.`);
                    }
                    return;
                }

                const result = await response.json();

                if (result.success) {
                    // Clear cart
                    this.clearCart();
                    
                    // Remove modal
                    document.getElementById('paymentMethodModal').remove();
                    
                    // Redirect to receipt page
                    window.location.href = `/?page=receipt&orderId=${result.orderId}`;
                } else {
                    if (window.wfNotifications && window.wfNotifications.error) {
                        window.wfNotifications.error('Order failed: ' + result.error, { duration: 5000 });
                    } else {
                        alert('Order failed: ' + result.error);
                    }
                }
            } catch (error) {
                console.error('Checkout error:', error);
                if (window.wfNotifications && window.wfNotifications.error) {
                    window.wfNotifications.error('An error occurred during checkout. Please try again.', { duration: 5000 });
                } else {
                    alert('An error occurred during checkout. Please try again.');
                }
            }
        },

        async loadProfileAddress() {
            try {
                const user = sessionStorage.getItem('user') ? JSON.parse(sessionStorage.getItem('user')) : null;
                if (!user) return;

                // Fetch user profile data
                const response = await fetch(`/api/users.php?id=${user.userId}`);
                const userData = await response.json();
                
                if (userData && !userData.error) {
                    const addressParts = [];
                    if (userData.addressLine1) addressParts.push(userData.addressLine1);
                    if (userData.addressLine2) addressParts.push(userData.addressLine2);
                    
                    const cityStateZip = [];
                    if (userData.city) cityStateZip.push(userData.city);
                    if (userData.state) cityStateZip.push(userData.state);
                    if (userData.zipCode) cityStateZip.push(userData.zipCode);
                    
                    if (cityStateZip.length > 0) {
                        addressParts.push(cityStateZip.join(', '));
                    }
                    
                    const profileAddressText = document.getElementById('profileAddressText');
                    if (profileAddressText) {
                        if (addressParts.length > 0) {
                            profileAddressText.innerHTML = addressParts.join('<br>');
                        } else {
                            profileAddressText.innerHTML = '<em>No address on file. Please enter a delivery address below.</em>';
                            // Auto-select custom address option if no profile address
                            const customOption = document.querySelector('input[name="addressOption"][value="custom"]');
                            if (customOption) {
                                customOption.checked = true;
                                this.toggleAddressFields();
                            }
                        }
                    }
                    
                    // Store user data for later use
                    this.userProfileData = userData;
                }
            } catch (error) {
                console.error('Error loading profile address:', error);
                const profileAddressText = document.getElementById('profileAddressText');
                if (profileAddressText) {
                    profileAddressText.innerHTML = '<em>Error loading address. Please enter a delivery address below.</em>';
                }
            }
        },

        toggleAddressFields() {
            const profileOption = document.querySelector('input[name="addressOption"][value="profile"]');
            const customOption = document.querySelector('input[name="addressOption"][value="custom"]');
            const profileDisplay = document.getElementById('profileAddressDisplay');
            const customFields = document.getElementById('customAddressFields');
            
            if (profileOption && profileOption.checked) {
                if (profileDisplay) profileDisplay.style.display = 'block';
                if (customFields) customFields.style.display = 'none';
            } else if (customOption && customOption.checked) {
                if (profileDisplay) profileDisplay.style.display = 'none';
                if (customFields) customFields.style.display = 'block';
            }
        }
    };

    // Register global functions immediately
    function registerGlobalFunctions() {
        // Main cart object
        window.cart = {
            addItem: (item) => cartMethods.addItem(item),
            removeItem: (sku) => cartMethods.removeItem(sku),
            updateItem: (sku, quantity) => cartMethods.updateItem(sku, quantity),
            clearCart: () => cartMethods.clearCart(),
            getItems: () => cartMethods.getItems(),
            getTotal: () => cartMethods.getTotal(),
            getCount: () => cartMethods.getCount(),
            getState: () => cartMethods.getState(),
            setNotifications: (enabled) => cartMethods.setNotifications(enabled),
            showCurrentCartStatus: () => cartMethods.showCurrentCartStatus(),
            showCartStatusToast: () => cartMethods.showCartStatusToast(),
            renderCart: () => cartMethods.renderCart(),
            checkout: () => cartMethods.checkout(),
            createPaymentMethodModal: () => cartMethods.createPaymentMethodModal(),
            proceedToCheckout: () => cartMethods.proceedToCheckout(),
            submitCheckout: (paymentMethod, shippingMethod) => cartMethods.submitCheckout(paymentMethod, shippingMethod),
            loadProfileAddress: () => cartMethods.loadProfileAddress(),
            toggleAddressFields: () => cartMethods.toggleAddressFields(),
            
            // Legacy methods
            items: cartState.items,
            total: cartState.total,
            count: cartState.count
        };

        // Global cart functions
        window.addToCart = (item) => cartMethods.addItem(item);
        window.removeFromCart = (sku) => cartMethods.removeItem(sku);
        window.updateCartItem = (sku, quantity) => cartMethods.updateItem(sku, quantity);
        window.clearCart = () => cartMethods.clearCart();
        window.getCartItems = () => cartMethods.getItems();
        window.getCartTotal = () => cartMethods.getTotal();
        window.getCartCount = () => cartMethods.getCount();

        // Global cart status functions (matching June 30th config)
        window.showCartStatus = () => cartMethods.showCurrentCartStatus();
        window.showCartStatusToast = () => cartMethods.showCartStatusToast();
        
        // Enhanced cart access for iframe contexts
        window.accessCart = function() {
            // Try multiple access patterns
            if (window.cart && typeof window.cart.addItem === 'function') {
                return window.cart;
            }
            
            try {
                if (window.parent && window.parent.cart && typeof window.parent.cart.addItem === 'function') {
                    return window.parent.cart;
                }
            } catch (e) {
                // Cross-origin access denied
            }
            
            try {
                if (window.top && window.top.cart && typeof window.top.cart.addItem === 'function') {
                    return window.top.cart;
                }
            } catch (e) {
                // Cross-origin access denied
            }
            
            return null;
        };
        
        // Expose notification system for iframe access
        window.accessNotifications = function() {
            // Try to get branded notification functions from current or parent window
            const notifications = {};
            
            try {
                // First try to access the branded notification system
                if (window.wfNotifications && typeof window.wfNotifications.success === 'function') {
                    notifications.showSuccess = (message, options = {}) => window.wfNotifications.success(message, options);
                    notifications.showError = (message, options = {}) => window.wfNotifications.error(message, options);
                    notifications.showInfo = (message, options = {}) => window.wfNotifications.info(message, options);
                    notifications.showWarning = (message, options = {}) => window.wfNotifications.warning(message, options);
                } else if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.success === 'function') {
                    notifications.showSuccess = (message, options = {}) => window.parent.wfNotifications.success(message, options);
                    notifications.showError = (message, options = {}) => window.parent.wfNotifications.error(message, options);
                    notifications.showInfo = (message, options = {}) => window.parent.wfNotifications.info(message, options);
                    notifications.showWarning = (message, options = {}) => window.parent.wfNotifications.warning(message, options);
                }
                
                // Fallback to simple notification functions if branded system not available
                if (!notifications.showSuccess) {
                    if (typeof window.showSuccess === 'function') {
                        notifications.showSuccess = window.showSuccess;
                        notifications.showError = window.showError || window.showNotification;
                        notifications.showInfo = window.showInfo || window.showNotification;
                        notifications.showWarning = window.showWarning || window.showNotification;
                    } else if (window.parent && typeof window.parent.showSuccess === 'function') {
                        notifications.showSuccess = window.parent.showSuccess;
                        notifications.showError = window.parent.showError || window.parent.showNotification;
                        notifications.showInfo = window.parent.showInfo || window.parent.showNotification;
                        notifications.showWarning = window.parent.showWarning || window.parent.showNotification;
                    }
                }
            } catch (e) {
                console.log('[Cart] Notification access failed:', e.message);
            }
            
            return notifications;
        };
        
        console.log('[Cart] Global functions registered');
        console.log('[Cart] Cart object available:', typeof window.cart);
        console.log('[Cart] Cart addItem method:', typeof window.cart.addItem);
    }

    // Initialize cart system immediately
    function initializeCart() {
        console.log('[Cart] Initializing cart system...');
        
        // Load cart from localStorage
        cartMethods.loadCart();
        
        // Register global functions
        registerGlobalFunctions();
        
        // Update cart display
        cartMethods.updateCartDisplay();
        
        cartState.initialized = true;
        console.log('[Cart] Cart system initialized');
    }

    // Cart system module for core registration
    const CartSystem = {
        name: 'cart-system',
        dependencies: [],
        priority: 2,

        async init(core) {
            core.log('Cart system module registered with core');
            // Cart already initialized, just confirm it's working
            if (window.cart && typeof window.cart.addItem === 'function') {
                core.log('✅ Cart system verified and accessible');
                core.log('Cart methods available:', {
                    addItem: typeof window.cart.addItem,
                    getItems: typeof window.cart.getItems,
                    getTotal: typeof window.cart.getTotal
                });
            } else {
                core.log('❌ Cart system not found after initialization');
            }
        }
    };

    // Initialize cart system immediately (don't wait for core)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initializeCart();
            // Register module with core system when it's available
            if (window.WhimsicalFrog) {
                window.WhimsicalFrog.registerModule(CartSystem.name, CartSystem);
            }
        });
    } else {
        initializeCart();
        // Register module with core system when it's available
        if (window.WhimsicalFrog) {
            window.WhimsicalFrog.registerModule(CartSystem.name, CartSystem);
        }
    }

})(); 