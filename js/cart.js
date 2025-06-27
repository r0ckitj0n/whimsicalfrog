class ShoppingCart {
    constructor() {
        this.items = JSON.parse(localStorage.getItem('cart') || '[]');
        this.cleanupInvalidItems();
        this.updateCartCount();
    }

    cleanupInvalidItems() {
        // Remove items with invalid prices or quantities
        const originalLength = this.items.length;
        this.items = this.items.filter(item => {
            const hasValidPrice = !isNaN(parseFloat(item.price)) && parseFloat(item.price) >= 0;
            const hasValidQuantity = !isNaN(parseInt(item.quantity)) && parseInt(item.quantity) > 0;
            const hasValidSku = item.sku && item.sku !== 'undefined' && item.sku !== 'null';
            
            if (!hasValidPrice || !hasValidQuantity || !hasValidSku) {
                console.warn(`Removing invalid cart item:`, item);
                return false;
            }
            
            // Fix price to ensure it's a number
            item.price = parseFloat(item.price) || 0;
            item.quantity = parseInt(item.quantity) || 1;
            
            return true;
        });
        
        // Save if we removed any items
        if (this.items.length !== originalLength) {
            this.saveCart();
        }
    }

    dispatchCartUpdate() {
        // Dispatch custom event for cart updates
        const event = new CustomEvent('cartUpdated', {
            detail: {
                items: this.items,
                count: this.getItemCount(),
                total: this.getTotal()
            }
        });
        document.dispatchEvent(event);
        
        // Refresh modal size dropdown if modal is open
        this.refreshModalSizeDropdown();
    }

    refreshModalSizeDropdown() {
        // Check if any modal is open and has size options
        const quantityModal = document.getElementById('quantityModal');
        const detailedModal = document.getElementById('detailedProductModal');
        const sizeSelect = document.getElementById('sizeSelect');
        
        // Check for quantity modal (room pages)
        if (quantityModal && !quantityModal.classList.contains('hidden') && sizeSelect && window.currentModalProduct) {
            // Get current selection
            const currentSelection = sizeSelect.value;
            
            // Rebuild the size dropdown with updated cart quantities
            if (window.currentModalProduct.availableSizes || 
                window.currentModalProduct.generalSizes || 
                window.currentModalProduct.colorSpecificSizes) {
                
                window.setupSizeDropdown(
                    window.currentModalProduct.availableSizes,
                    window.currentModalProduct.generalSizes,
                    window.currentModalProduct.colorSpecificSizes,
                    currentSelection
                );
            }
        }
        
        // Check for detailed modal (global modal system)
        else if (detailedModal && !detailedModal.classList.contains('hidden') && sizeSelect && window.currentModalProduct) {
            // Get current selection
            const currentSelection = sizeSelect.value;
            
            // Rebuild the size dropdown with updated cart quantities
            if (window.currentModalProduct.availableSizes || 
                window.currentModalProduct.generalSizes || 
                window.currentModalProduct.colorSpecificSizes) {
                
                window.setupSizeDropdown(
                    window.currentModalProduct.availableSizes,
                    window.currentModalProduct.generalSizes,
                    window.currentModalProduct.colorSpecificSizes,
                    currentSelection
                );
            }
        }
    }

    async refreshProductData() {
        try {
            // Get all SKUs in cart
            const itemSkus = this.items.map(item => item.sku);
            
            // Filter out undefined/null SKUs
            const validSkus = itemSkus.filter(sku => sku && sku !== 'undefined');
            
            if (validSkus.length === 0) {
                return;
            }

            // Fetch current item data from database
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
                } else {
                    console.warn(`Removing invalid item from cart: ${cartItem.sku} - item no longer exists in database`);
                    this.showNotification(`Removed unavailable item: ${cartItem.name || cartItem.sku}`);
                }
            });

            // Update cart with only valid items
            if (validItems.length !== this.items.length) {
                this.items = validItems;
                this.saveCart();
                this.updateCartCount();
            }

        } catch (error) {
            console.warn('Error refreshing item data:', error);
        }
    }

    addItem(item) {
        const quantity = item.quantity || 1; // Use provided quantity or default to 1
        const color = item.color || null; // Color selection support
        const size = item.size || null; // Size selection support
        
        // Create unique identifier for item+color+size combination
        const existingItem = this.items.find(cartItem => {
            const cartColor = cartItem.color || null;
            const cartSize = cartItem.size || null;
            return cartItem.sku === item.sku && 
                   cartColor === color && 
                   cartSize === size;
        });
        
        if (existingItem) {
            existingItem.quantity += quantity;
        } else {
            const cartItem = {
                sku: item.sku,
                name: item.name,
                price: parseFloat(item.price) || 0,
                image: item.image,
                quantity: quantity
            };
            
            // Build display name with color and size
            let displayParts = [item.name];
            
            // Add color if specified
            if (color) {
                cartItem.color = color;
                cartItem.colorCode = item.colorCode; // Store color code for display
                displayParts.push(color);
            }
            
            // Add size if specified
            if (size) {
                cartItem.size = size;
                cartItem.sizeName = item.sizeName || size;
                cartItem.sizeAdjustment = item.sizeAdjustment || 0;
                displayParts.push(cartItem.sizeName);
            }
            
            cartItem.displayName = displayParts.length > 1 ? 
                `${displayParts[0]} (${displayParts.slice(1).join(', ')})` : 
                displayParts[0];
            
            this.items.push(cartItem);
        }
        
        this.saveCart();
        this.updateCartCount();
        this.dispatchCartUpdate();
        
        // Track cart action for analytics
        if (window.analyticsTracker) {
            window.analyticsTracker.trackCartAction('add', item.sku);
        }
    }

    removeItem(itemSku, color = null, size = null) {
        // Normalize null values (handle 'null' strings from onclick handlers)
        const normalizedColor = (color === 'null' || color === '' || color === undefined) ? null : color;
        const normalizedSize = (size === 'null' || size === '' || size === undefined) ? null : size;
        
        this.items = this.items.filter(item => {
            const itemColor = item.color || null;
            const itemSize = item.size || null;
            return !(item.sku === itemSku && itemColor === normalizedColor && itemSize === normalizedSize);
        });
        this.saveCart();
        this.updateCartCount();
        this.dispatchCartUpdate();
        
        // Track cart action for analytics
        if (window.analyticsTracker) {
            window.analyticsTracker.trackCartAction('remove', itemSku);
        }
    }

    updateQuantity(itemSku, quantity, color = null, size = null) {
        // Normalize null values (handle 'null' strings from onclick handlers)
        const normalizedColor = (color === 'null' || color === '' || color === undefined) ? null : color;
        const normalizedSize = (size === 'null' || size === '' || size === undefined) ? null : size;
        
        const item = this.items.find(cartItem => {
            const itemColor = cartItem.color || null;
            const itemSize = cartItem.size || null;
            return cartItem.sku === itemSku && itemColor === normalizedColor && itemSize === normalizedSize;
        });
        if (item) {
            item.quantity = Math.max(0, quantity);
            if (item.quantity === 0) {
                this.removeItem(itemSku, normalizedColor, normalizedSize);
            } else {
                this.saveCart();
                this.updateCartCount();
                this.dispatchCartUpdate();
            }
        }
    }

    getTotal() {
        return this.items.reduce((total, item) => {
            const price = parseFloat(item.price) || 0;
            const quantity = parseInt(item.quantity) || 0;
            return total + (price * quantity);
        }, 0);
    }

    getItemCount() {
        return this.items.reduce((count, item) => count + item.quantity, 0);
    }

    clearCart() {
        this.items = [];
        this.saveCart();
        this.updateCartCount();
        this.dispatchCartUpdate();
        
        // Re-render the cart view to show empty state
        this.renderCart();
    }

    saveCart() {
        localStorage.setItem('cart', JSON.stringify(this.items));
        this.updateCartCount();
    }

    updateCartCount() {
        const cartCount = document.getElementById('cartCount');
        const cartTotal = document.getElementById('cartTotal');
        if (cartCount && cartTotal) {
            const count = this.getItemCount();
            const total = this.getTotal();
            
            // Update count
            cartCount.textContent = count + ' items';
            cartCount.style.display = count > 0 ? 'inline' : 'none';
            
            // Update total
            cartTotal.textContent = `$${total.toFixed(2)}`;
            cartTotal.style.display = count > 0 ? 'inline' : 'none';
        }
    }

    showNotification(message) {
        return window.showSuccess(message);
    }

    showErrorNotification(message) {
        return window.showError(message);
    }

    showValidationError(message) {
        return window.showValidation(message);
    }

    async loadProfileAddress() {
        try {
            const user = sessionStorage.getItem('user') ? JSON.parse(sessionStorage.getItem('user')) : null;
            if (!user) return;

            // Fetch user profile data
            const response = await fetch(`api/users.php?id=${user.userId}`);
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
    }

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

    async renderCart() {
        let cartContainer = document.getElementById('cartContainer');
        if (!cartContainer) {
            // Try alternative cart container ID used on cart page
            cartContainer = document.getElementById('cartItems');
        }
        
        if (!cartContainer) {
            console.warn('Cart container not found (tried cartContainer and cartItems)');
            return;
        }

        if (this.items.length === 0) {
            cartContainer.innerHTML = '<div class="text-center py-8 text-gray-500">Your cart is empty</div>';
            return;
        }

        // Refresh product data before rendering
        await this.refreshProductData();

        const cartHTML = this.items.map(item => {
            return `
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <div class="flex items-center space-x-4">
                    <img src="${item.image || '/images/items/placeholder.png'}" alt="${item.name}" class="w-16 h-16 object-cover rounded-lg">
                    <div>
                        <h3 class="font-medium text-gray-900">${item.displayName}</h3>
                        <p class="text-sm text-gray-500">$${item.price.toFixed(2)}</p>
                        <div class="flex items-center mt-1 space-x-3">
                            ${item.color ? `<div class="flex items-center">
                                <div class="w-3 h-3 rounded-full border border-gray-300 mr-2" style="background-color: ${item.colorCode || '#ccc'}"></div>
                                <span class="text-xs text-gray-500">${item.color}</span>
                            </div>` : ''}
                            ${item.sizeName ? `<div class="flex items-center">
                                <span class="text-xs text-gray-500">Size: ${item.sizeName}</span>
                                ${item.sizeAdjustment && item.sizeAdjustment !== 0 ? 
                                    `<span class="text-xs text-gray-600 ml-1">(${item.sizeAdjustment > 0 ? '+' : ''}$${item.sizeAdjustment.toFixed(2)})</span>` : ''}
                            </div>` : ''}
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="updateQuantity('${item.sku}', ${item.quantity - 1}, ${item.color ? `'${item.color}'` : 'null'}, ${item.size ? `'${item.size}'` : 'null'})" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded">-</button>
                    <span class="px-3 py-1 bg-gray-100 rounded">${item.quantity}</span>
                    <button onclick="updateQuantity('${item.sku}', ${item.quantity + 1}, ${item.color ? `'${item.color}'` : 'null'}, ${item.size ? `'${item.size}'` : 'null'})" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded">+</button>
                    <button onclick="removeFromCart('${item.sku}', ${item.color ? `'${item.color}'` : 'null'}, ${item.size ? `'${item.size}'` : 'null'})" class="px-2 py-1 rounded ml-2" style="background-color: #dc2626; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#b91c1c'" onmouseout="this.style.backgroundColor='#dc2626'">Remove</button>
                </div>
            </div>
            `;
        }).join('');

        cartContainer.innerHTML = cartHTML + `
            <div class="p-4 border-t border-gray-200 bg-gray-50">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-semibold">Total: $${this.getTotal().toFixed(2)}</span>
                    <button onclick="cart.clearCart()" class="px-4 py-2 rounded text-white" style="background-color: #6b7280; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">Clear Cart</button>
                </div>
                <button onclick="cart.checkout()" class="brand-button w-full py-3 px-6 rounded-lg font-semibold">Proceed to Checkout</button>
            </div>
        `;
    }

    async checkout() {
        // Check if user is logged in
        const userRaw = sessionStorage.getItem('user');
        let user = null;
        if (userRaw) {
            try { user = JSON.parse(userRaw); } catch(e) { console.warn('Invalid user JSON in sessionStorage'); }
        }

        if (!user) {
            // Redirect to login page
            window.location.href = '/?page=login';
            return;
        }

        // Create payment method modal
        this.createPaymentMethodModal();
    }

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

                <div id="shippingInfo" style="display: none;" class="mb-4 p-3 bg-gray-100 rounded">
                    <h4 class="font-semibold mb-2">Shipping Address</h4>
                    
                    <!-- Address Selection Options -->
                    <div class="mb-3">
                        <label class="flex items-center mb-2">
                            <input type="radio" name="addressOption" value="profile" class="mr-2" checked onchange="cart.toggleAddressFields()">
                            <span>Use my profile address</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="addressOption" value="custom" class="mr-2" onchange="cart.toggleAddressFields()">
                            <span>Enter a different delivery address</span>
                        </label>
                    </div>
                    
                    <!-- Profile Address Display -->
                    <div id="profileAddressDisplay" class="mb-3 p-3 bg-white border rounded">
                        <div class="text-sm text-gray-600" id="profileAddressText">Loading your address...</div>
                    </div>
                    
                    <!-- Custom Address Fields -->
                    <div id="customAddressFields" style="display: none;" class="space-y-2">
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
                    <button onclick="document.getElementById('paymentMethodModal').remove()" class="flex-1 py-2 px-4 rounded text-white" style="background-color: #6b7280; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">Cancel</button>
                    <button onclick="cart.proceedToCheckout()" class="brand-button flex-1 py-2 px-4 rounded">Place Order</button>
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
    }

    async proceedToCheckout() {
        const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked')?.value;
        const shippingMethod = document.querySelector('input[name="shippingMethod"]:checked')?.value;

        if (!paymentMethod || !shippingMethod) {
            this.showValidationError('Please select both payment and shipping methods.');
            return;
        }

        // Get user info
        const userRaw = sessionStorage.getItem('user');
        let user = null;
        if (userRaw) {
            try { user = JSON.parse(userRaw); } catch(e) { console.warn('Invalid user JSON in sessionStorage'); }
        }

        if (!user) {
            this.showValidationError('Please log in to complete your order.');
            setTimeout(() => {
                window.location.href = '/?page=login';
            }, 1500);
            return;
        }

        await this.submitCheckout(paymentMethod, shippingMethod);
    }

    async submitCheckout(paymentMethod, shippingMethod) {
        try {
            const customerId = sessionStorage.getItem('user') ? JSON.parse(sessionStorage.getItem('user')).userId : null;
            
            if (!customerId) {
                this.showValidationError('Please log in to complete your order.');
                return;
            }

            // Validate cart items have valid SKUs
            const invalidItems = this.items.filter(item => !item.sku || item.sku === 'undefined');
            if (invalidItems.length > 0) {
                console.error('Invalid SKUs found in cart:', this.items);
                this.showErrorNotification('Some items in your cart are invalid. Please refresh the page and try again.');
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
                        this.showValidationError('Please fill in all required address fields (Address Line 1, City, State, ZIP Code).');
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
                    this.showValidationError('Please provide a shipping address for delivery orders.');
                    return;
                }
            }

            const itemIds = this.items.map(item => item.sku);
            const quantities = this.items.map(item => item.quantity);
            const colors = this.items.map(item => item.color || null);
            const sizes = this.items.map(item => item.size || null);

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

            const response = await fetch('api/add-order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(orderData)
            });

            const result = await response.json();

            if (result.success) {
                // Clear cart
                this.clearCart();
                
                // Remove modal
                document.getElementById('paymentMethodModal').remove();
                
                // Redirect to receipt page
                window.location.href = `/?page=receipt&orderId=${result.orderId}`;
            } else {
                this.showErrorNotification('Order failed: ' + result.error);
            }
        } catch (error) {
            console.error('Checkout error:', error);
            this.showErrorNotification('An error occurred during checkout. Please try again.');
        }
    }
}

// Global cart instance
let cart;

// Initialize cart immediately and attach to window
cart = new ShoppingCart();
window.cart = cart;

// Additional initialization when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Cart is already initialized above, just ensure it's available
    if (!window.cart) {
        window.cart = cart;
    }
});

// Global functions for cart operations
async function addToCart(sku, name, price, imageUrl = null) {
    if (!cart) {
        console.error('Cart not initialized');
        return;
    }
    
    try {
        const validPrice = parseFloat(price) || 0;
        if (validPrice <= 0) {
            console.warn(`Invalid price for item ${sku}: ${price}`);
        }
        
        cart.addItem({
            sku: sku,
            name: name,
            price: validPrice,
            image: imageUrl,
            quantity: 1
        });
        cart.showNotification(`Added ${name} to cart`);
    } catch (error) {
        console.error('Error adding item to cart:', error);
    }
}

// Clean up cart on page load
document.addEventListener('DOMContentLoaded', function() {
    // Ensure cart is available on window object
    if (cart && !window.cart) {
        window.cart = cart;
    }
    
    if (cart) {
        const invalidItems = cart.items.filter(item => !item.sku || item.sku === 'undefined' || item.sku === 'null');
        if (invalidItems.length > 0) {
            invalidItems.forEach(item => {
                cart.showNotification(`Removed invalid item: ${item.name || 'Unknown item'}`);
            });
            
            // Remove invalid items
            cart.items = cart.items.filter(item => item.sku && item.sku !== 'undefined' && item.sku !== 'null');
            cart.saveCart();
        }
    }
});

// Emergency cart cleanup function
function emergencyCartCleanup() {
    localStorage.removeItem('cart');
    if (cart) {
        cart.items = [];
        cart.updateCartCount();
    }
}

function removeFromCart(sku, color = null, size = null) {
    if (cart) {
        cart.removeItem(sku, color, size);
    }
}

function updateQuantity(sku, newQuantity, color = null, size = null) {
    if (cart) {
        cart.updateQuantity(sku, newQuantity, color, size);
    }
}

// Global function to add items to cart with quantity modal
window.addToCartWithModal = async function(sku, name, price, image) {
    // Always go to quantity modal, but check for colors and sizes to populate dropdowns
    await showQuantityModal(sku, name, price, image);
};

// Function to show quantity modal (updated to handle colors and sizes)
window.showQuantityModal = async function(sku, name, price, image, selectedColor = null, selectedSize = null) {
    // Find the quantity modal elements
    const quantityModal = document.getElementById('quantityModal');
    const modalProductImage = document.getElementById('modalProductImage');
    const modalProductName = document.getElementById('modalProductName');
    const modalProductPrice = document.getElementById('modalProductPrice');
    const modalUnitPrice = document.getElementById('modalUnitPrice');
    const modalQuantity = document.getElementById('modalQuantity');
    const modalTotal = document.getElementById('modalTotal');
    const quantityInput = document.getElementById('quantityInput');
    
    if (!quantityModal) {
        console.error('Quantity modal not found on this page');
        return;
    }
    
    // Check for active sales
    let finalPrice = price;
    try {
        if (typeof checkItemSale === 'function') {
            const saleData = await checkItemSale(sku);
            if (saleData && saleData.isOnSale) {
                finalPrice = calculateSalePrice(price, saleData.discountPercentage);
            }
        }
    } catch (error) {
        console.log('No sale data available for', sku);
    }
    
    // Check for color options and populate dropdown
    let availableColors = [];
    try {
        const colorResponse = await fetch(`/api/item_colors.php?action=get_colors&item_sku=${sku}`);
        const colorData = await colorResponse.json();
        if (colorData.success && colorData.colors.length > 0) {
            availableColors = colorData.colors;
        }
    } catch (error) {
        console.log('Error fetching colors for', sku, ':', error);
    }
    
    // Check for size options and populate dropdown
    let availableSizes = [];
    let generalSizes = [];
    let colorSpecificSizes = {};
    try {
        // First get general sizes (not color-specific)
        const generalSizeResponse = await fetch(`/api/item_sizes.php?action=get_sizes&item_sku=${sku}&color_id=null`);
        const generalSizeData = await generalSizeResponse.json();
        if (generalSizeData.success && generalSizeData.sizes.length > 0) {
            generalSizes = generalSizeData.sizes;
            availableSizes = generalSizes;
        }
        
        // If there are colors, check for color-specific sizes
        if (availableColors.length > 0) {
            for (const color of availableColors) {
                const colorSizeResponse = await fetch(`/api/item_sizes.php?action=get_sizes&item_sku=${sku}&color_id=${color.id}`);
                const colorSizeData = await colorSizeResponse.json();
                if (colorSizeData.success && colorSizeData.sizes.length > 0) {
                    colorSpecificSizes[color.id] = colorSizeData.sizes;
                }
            }
        }
    } catch (error) {
        console.log('Error fetching sizes for', sku, ':', error);
    }
    
    // Set current product data including available colors and sizes
    window.currentModalProduct = { 
        id: sku, 
        name: name, 
        price: finalPrice, 
        image: image, 
        originalImage: image, // Store original image for fallback
        originalPrice: price,
        selectedColor: selectedColor,
        selectedSize: selectedSize,
        availableColors: availableColors,
        availableSizes: availableSizes,
        generalSizes: generalSizes,
        colorSpecificSizes: colorSpecificSizes
    };
    
    // Update modal content with proper image handling
    if (modalProductImage) {
        let imageUrl = image;
        if (!imageUrl || imageUrl === '' || imageUrl === 'undefined') {
            imageUrl = `images/items/${sku}A.png`;
        }
        
        modalProductImage.src = imageUrl;
        modalProductImage.onerror = function() {
            if (!imageUrl.includes('.webp')) {
                const webpUrl = imageUrl.replace(/\.(png|jpg|jpeg)$/i, '.webp');
                this.src = webpUrl;
                this.onerror = function() {
                    const pngUrl = `images/items/${sku}A.png`;
                    this.src = pngUrl;
                    this.onerror = function() {
                        this.src = 'images/items/placeholder.png';
                        this.onerror = null;
                    };
                };
            } else {
                const pngUrl = `images/items/${sku}A.png`;
                this.src = pngUrl;
                this.onerror = function() {
                    this.src = 'images/items/placeholder.png';
                    this.onerror = null;
                };
            }
        };
    }
    
    // Update product name 
    if (modalProductName) modalProductName.textContent = name;
    
    // Handle color and size dropdowns
    await setupColorDropdown(availableColors, selectedColor);
    await setupSizeDropdown(availableSizes, generalSizes, colorSpecificSizes, selectedSize);
    
    // Display price with sale formatting if applicable
    if (finalPrice < price) {
        const saleHTML = `<span style="text-decoration: line-through; color: #999; font-size: 0.9em;">$${parseFloat(price).toFixed(2)}</span> <span style="color: #dc2626; font-weight: bold;">$${parseFloat(finalPrice).toFixed(2)}</span>`;
        if (modalProductPrice) modalProductPrice.innerHTML = saleHTML;
        if (modalUnitPrice) modalUnitPrice.innerHTML = saleHTML;
    } else {
        if (modalProductPrice) modalProductPrice.textContent = '$' + parseFloat(finalPrice).toFixed(2);
        if (modalUnitPrice) modalUnitPrice.textContent = '$' + parseFloat(finalPrice).toFixed(2);
    }
    
    if (quantityInput) quantityInput.value = 1;
    
    // Update total
    updateModalTotal();
    
    // Show modal
    quantityModal.classList.remove('hidden');
};

// Function to setup color dropdown in quantity modal
window.setupColorDropdown = async function(availableColors, selectedColor = null) {
    // Find or create color dropdown container
    let colorContainer = document.getElementById('colorDropdownContainer');
    
    if (!colorContainer) {
        // Create color dropdown container and insert it before quantity controls
        const quantityControls = document.querySelector('.quantity-controls');
        if (quantityControls) {
            colorContainer = document.createElement('div');
            colorContainer.id = 'colorDropdownContainer';
            colorContainer.className = 'color-dropdown-container';
            quantityControls.parentNode.insertBefore(colorContainer, quantityControls);
        } else {
            console.error('Could not find quantity controls to insert color dropdown');
            return;
        }
    }
    
    // Clear existing content
    colorContainer.innerHTML = '';
    
    if (availableColors && availableColors.length > 1) {
        // Create color dropdown
        const colorHTML = `
            <div class="color-selection">
                <label for="colorSelect">Color:</label>
                <div class="color-dropdown-wrapper">
                    <select id="colorSelect" class="color-select" onchange="updateSelectedColor()">
                        <option value="">Choose a color...</option>
                        ${availableColors.map(color => `
                            <option value="${color.color_name}" data-color-code="${color.color_code || ''}" ${selectedColor === color.color_name ? 'selected' : ''}>
                                ${color.color_name} ${color.stock_level > 0 ? `(${color.stock_level} available)` : '(Out of stock)'}
                            </option>
                        `).join('')}
                    </select>
                    <div id="colorSwatch" class="color-swatch-display"></div>
                </div>
            </div>
        `;
        
        colorContainer.innerHTML = colorHTML;
        
        // Update color swatch based on selection
        updateColorSwatch();
        
        // Add CSS if not already added
        if (!document.getElementById('colorDropdownStyles')) {
            const style = document.createElement('style');
            style.id = 'colorDropdownStyles';
            style.textContent = `
                .color-dropdown-container {
                    margin-bottom: 15px;
                }
                .color-selection label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 500;
                    color: #374151;
                }
                .color-dropdown-wrapper {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .color-select {
                    flex: 1;
                    padding: 8px 12px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    background: white;
                    font-size: 14px;
                    color: #374151;
                }
                .color-select:focus {
                    outline: none;
                    border-color: #87ac3a;
                    box-shadow: 0 0 0 2px rgba(135, 172, 58, 0.2);
                }
                .color-swatch-display {
                    width: 24px;
                    height: 24px;
                    border-radius: 50%;
                    border: 2px solid #d1d5db;
                    background: #f9fafb;
                    flex-shrink: 0;
                }
            `;
            document.head.appendChild(style);
        }
    } else if (availableColors && availableColors.length === 1) {
        // If there's only one color, automatically select it without showing dropdown
        if (window.currentModalProduct) {
            window.currentModalProduct.selectedColor = availableColors[0].color_name;
            
            // Update product image for the single color if it has a specific image
            updateProductImageForColor();
        }
    }
};

// Function to update color swatch when selection changes
window.updateColorSwatch = function() {
    const colorSelect = document.getElementById('colorSelect');
    const colorSwatch = document.getElementById('colorSwatch');
    
    if (colorSelect && colorSwatch) {
        const selectedOption = colorSelect.options[colorSelect.selectedIndex];
        const colorCode = selectedOption?.getAttribute('data-color-code');
        
        if (colorCode) {
            colorSwatch.style.backgroundColor = colorCode;
            colorSwatch.style.borderColor = '#9ca3af';
        } else {
            colorSwatch.style.backgroundColor = '#f9fafb';
            colorSwatch.style.borderColor = '#d1d5db';
        }
    }
};

// Function to update product image when color is selected
window.updateProductImageForColor = async function() {
    if (!window.currentModalProduct || !window.currentModalProduct.selectedColor) {
        return;
    }
    
    try {
        // Get the selected color data including image path
        const selectedColorData = window.currentModalProduct.availableColors?.find(
            c => c.color_name === window.currentModalProduct.selectedColor
        );
        
        if (selectedColorData && selectedColorData.image_path) {
            // Update the modal image
            const modalImage = document.querySelector('#quantityModal .product-image img');
            if (modalImage) {
                const newImageSrc = `images/items/${selectedColorData.image_path}`;
                
                // Create a new image to test if it loads successfully
                const testImage = new Image();
                testImage.onload = function() {
                    modalImage.src = newImageSrc;
                    // Update the current modal product image for cart
                    window.currentModalProduct.image = newImageSrc;
                };
                testImage.onerror = function() {
                    // If color-specific image fails, fall back to default
                    console.log(`Color-specific image not found: ${newImageSrc}, keeping current image`);
                };
                testImage.src = newImageSrc;
            }
        } else {
            // No specific image for this color, use default
            const modalImage = document.querySelector('#quantityModal .product-image img');
            if (modalImage && window.currentModalProduct.originalImage) {
                modalImage.src = window.currentModalProduct.originalImage;
                window.currentModalProduct.image = window.currentModalProduct.originalImage;
            }
        }
    } catch (error) {
        console.error('Error updating product image for color:', error);
    }
};

// Global function to update modal total
window.updateModalTotal = function() {
    const quantityInput = document.getElementById('quantityInput');
    const modalQuantity = document.getElementById('modalQuantity');
    const modalTotal = document.getElementById('modalTotal');
    const modalUnitPrice = document.getElementById('modalUnitPrice');
    
    if (!window.currentModalProduct || !quantityInput) return;
    
    const quantity = parseInt(quantityInput.value) || 1;
    let unitPrice = window.currentModalProduct.price;
    
    // Add size price adjustment if applicable
    const sizeAdjustment = window.currentModalProduct.sizeAdjustment || 0;
    unitPrice += sizeAdjustment;
    
    const total = quantity * unitPrice;
    
    if (modalQuantity) modalQuantity.textContent = quantity;
    if (modalTotal) modalTotal.textContent = '$' + total.toFixed(2);
    
    // Update unit price display to show size adjustment if applicable
    if (modalUnitPrice) {
        if (sizeAdjustment !== 0) {
            const basePrice = window.currentModalProduct.price;
            const adjustmentText = sizeAdjustment > 0 ? `+$${sizeAdjustment.toFixed(2)}` : `$${sizeAdjustment.toFixed(2)}`;
            modalUnitPrice.innerHTML = `$${basePrice.toFixed(2)} ${adjustmentText} = $${unitPrice.toFixed(2)}`;
        } else {
            modalUnitPrice.textContent = '$' + unitPrice.toFixed(2);
        }
    }
};

// Global function to close modal
window.closeCartModal = function() {
    const quantityModal = document.getElementById('quantityModal');
    const quantityInput = document.getElementById('quantityInput');
    
    if (quantityModal) {
        quantityModal.classList.add('hidden');
    }
    if (quantityInput) {
        quantityInput.value = 1;
    }
    window.currentModalProduct = null;
};

// Global function to confirm add to cart
window.confirmAddToCart = function() {
    if (!window.currentModalProduct) return;
    
    const quantityInput = document.getElementById('quantityInput');
    const quantity = parseInt(quantityInput.value) || 1;
    
    // Check if color selection is required but not selected
    const colorSelect = document.getElementById('colorSelect');
    if (colorSelect && window.currentModalProduct.availableColors && window.currentModalProduct.availableColors.length > 1) {
        const selectedColor = colorSelect.value;
        if (!selectedColor) {
            if (window.cart && window.cart.showErrorNotification) {
                window.cart.showErrorNotification('Please select a color before adding to cart.');
            } else if (window.cart && window.cart.showNotification) {
                window.cart.showNotification('Please select a color before adding to cart.');
            } else {
                showValidation('Please select a color before adding to cart.');
            }
            return;
        }
        // Update the selected color from dropdown
        window.currentModalProduct.selectedColor = selectedColor;
    } else if (window.currentModalProduct.availableColors && window.currentModalProduct.availableColors.length === 1) {
        // If there's only one color, automatically use it
        window.currentModalProduct.selectedColor = window.currentModalProduct.availableColors[0].color_name;
    }
    
    // Check if size selection is required but not selected
    const sizeSelect = document.getElementById('sizeSelect');
    if (sizeSelect && (window.currentModalProduct.generalSizes?.length > 0 || 
        Object.keys(window.currentModalProduct.colorSpecificSizes || {}).length > 0)) {
        const selectedSize = sizeSelect.value;
        if (!selectedSize) {
            if (window.cart && window.cart.showErrorNotification) {
                window.cart.showErrorNotification('Please select a size before adding to cart.');
            } else if (window.cart && window.cart.showNotification) {
                window.cart.showNotification('Please select a size before adding to cart.');
            } else {
                showValidation('Please select a size before adding to cart.');
            }
            return;
        }
        
        // Check if there's enough inventory available (considering what's already in cart)
        const selectedOption = sizeSelect.options[sizeSelect.selectedIndex];
        const availableQuantity = parseInt(selectedOption.getAttribute('data-available')) || 0;
        
        if (quantity > availableQuantity) {
            const errorMsg = availableQuantity === 0 ? 
                'This size is currently out of stock.' :
                `Only ${availableQuantity} of this size available (considering items already in your cart).`;
            
            if (window.cart && window.cart.showErrorNotification) {
                window.cart.showErrorNotification(errorMsg);
            } else if (window.cart && window.cart.showNotification) {
                window.cart.showNotification(errorMsg);
            } else {
                showValidation(errorMsg);
            }
            return;
        }
        
        // Update the selected size from dropdown
        window.currentModalProduct.selectedSize = selectedSize;
    }
    
    // Add to cart using the existing cart system
    if (typeof window.cart !== 'undefined') {
        // Calculate final price with size adjustment
        let finalPrice = window.currentModalProduct.price;
        const sizeAdjustment = window.currentModalProduct.sizeAdjustment || 0;
        finalPrice += sizeAdjustment;
        
        const cartItem = {
            sku: window.currentModalProduct.id,  // Use sku property as expected by cart
            name: window.currentModalProduct.name,
            price: finalPrice, // Use adjusted price
            image: window.currentModalProduct.image,
            quantity: quantity
        };
        
        // Add color if selected
        if (window.currentModalProduct.selectedColor) {
            cartItem.color = window.currentModalProduct.selectedColor;
            
            // Find color code for cart display
            const selectedColorData = window.currentModalProduct.availableColors?.find(
                c => c.color_name === window.currentModalProduct.selectedColor
            );
            if (selectedColorData && selectedColorData.color_code) {
                cartItem.colorCode = selectedColorData.color_code;
            }
        }
        
        // Add size if selected
        if (window.currentModalProduct.selectedSize) {
            cartItem.size = window.currentModalProduct.selectedSize;
            cartItem.sizeName = window.currentModalProduct.selectedSizeName;
            if (sizeAdjustment !== 0) {
                cartItem.sizeAdjustment = sizeAdjustment;
            }
        }
        
        window.cart.addItem(cartItem);
        
        // Show confirmation
        const customAlert = document.getElementById('customAlertBox');
        const customAlertMessage = document.getElementById('customAlertMessage');
        if (customAlert && customAlertMessage) {
            const quantityText = quantity > 1 ? ` (${quantity})` : '';
            const colorText = window.currentModalProduct.selectedColor ? ` - ${window.currentModalProduct.selectedColor}` : '';
            const sizeText = window.currentModalProduct.selectedSizeName ? ` - ${window.currentModalProduct.selectedSizeName}` : '';
            customAlertMessage.textContent = `${window.currentModalProduct.name}${colorText}${sizeText}${quantityText} added to your cart!`;
            customAlert.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                customAlert.style.display = 'none';
            }, 5000);
        }
    } else {
        const colorText = window.currentModalProduct.selectedColor ? ` - ${window.currentModalProduct.selectedColor}` : '';
        const sizeText = window.currentModalProduct.selectedSizeName ? ` - ${window.currentModalProduct.selectedSizeName}` : '';
        showSuccess(`Added ${quantity} ${window.currentModalProduct.name}${colorText}${sizeText} to cart!`);
    }
    
    // Close modal
    closeCartModal();
};

// Global popup management functions
window.globalPopupTimeout = null;
window.isShowingPopup = false;
window.popupOpen = false;
window.currentProduct = null;

// Global function to show product popup
window.showGlobalPopup = function(element, product) {
    // Clear any existing timeout
    clearTimeout(window.globalPopupTimeout);
    
    const popup = document.getElementById('productPopup');
    if (!popup) return;
    
    // Don't show popup if already showing
    if (window.isShowingPopup && window.popupOpen) return;
    
    window.isShowingPopup = true;
    window.popupOpen = true;
    window.currentProduct = product;
    
    // Update popup content
    const popupImage = popup.querySelector('.popup-image');
    const popupName = popup.querySelector('.popup-name');
    const popupPrice = popup.querySelector('.popup-price');
    const popupDescription = popup.querySelector('.popup-description');
    const popupAddBtn = popup.querySelector('.popup-add-btn');
    const popupDetailsBtn = popup.querySelector('.popup-details-btn');
    
    if (popupImage) {
        popupImage.src = `images/items/${product.sku}A.png`;
        popupImage.onerror = function() {
            this.src = 'images/items/placeholder.png';
            this.onerror = null;
        };
    }
    
    if (popupName) {
        popupName.textContent = product.name || product.productName || 'Product';
    }
    
    if (popupPrice) {
        // Check for sales and update pricing
        checkAndDisplaySalePrice(product, popupPrice);
    }
    
    if (popupDescription) {
        popupDescription.textContent = product.description || '';
    }
    
    // Position popup
    positionPopup(element, popup);
    
    // Set up button handlers
    if (popupAddBtn) {
        popupAddBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            popup.classList.remove('show');
            
            const sku = product.sku;
            const name = product.name || product.productName;
            const price = parseFloat(product.retailPrice || product.price);
            const image = `images/items/${product.sku}A.png`;
            
            if (typeof window.addToCartWithModal === 'function') {
                window.addToCartWithModal(sku, name, price, image);
            }
        };
    }
    
    if (popupDetailsBtn) {
        popupDetailsBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            popup.classList.remove('show');
            if (typeof window.showItemDetails === 'function') {
                window.showItemDetails(product.sku);
            }
        };
    }
};

// Global function to position popup
window.positionPopup = function(element, popup) {
    const rect = element.getBoundingClientRect();
    const roomContainer = element.closest('.room-container') || document.body;
    const containerRect = roomContainer.getBoundingClientRect();

    let left = rect.left - containerRect.left + rect.width + 10;
    let top = rect.top - containerRect.top - 50;

    // Show popup temporarily to get actual dimensions
    popup.style.display = 'block';
    popup.style.opacity = '';
    popup.classList.add('show');

    const popupRect = popup.getBoundingClientRect();
    const popupWidth = popupRect.width;
    const popupHeight = popupRect.height;

    // Reset for measurement
    popup.style.display = '';

    // Adjust if popup would go off screen horizontally
    if (left + popupWidth > containerRect.width) {
        left = rect.left - containerRect.left - popupWidth - 10;
    }
    
    // Adjust if popup would go off screen vertically (top)
    if (top < 0) {
        top = rect.top - containerRect.top + rect.height + 10;
    }
    
    // Adjust if popup would go off screen vertically (bottom)
    if (top + popupHeight > containerRect.height) {
        const topAbove = rect.top - containerRect.top - popupHeight - 10;
        if (topAbove >= 0) {
            top = topAbove;
        } else {
            top = containerRect.height - popupHeight - 20;
            if (top < 0) {
                top = 10;
            }
        }
    }

    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
    popup.style.opacity = '';
    popup.classList.add('show');
};

// Global function to hide popup
window.hideGlobalPopup = function() {
    clearTimeout(window.globalPopupTimeout);
    
    window.globalPopupTimeout = setTimeout(() => {
        hidePopupImmediate();
    }, 200);
};

// Global function to hide popup immediately
window.hidePopupImmediate = function() {
    const popup = document.getElementById('productPopup');
    if (popup && popup.classList.contains('show')) {
        popup.classList.remove('show');
        window.currentProduct = null;
        window.popupOpen = false;
        window.isShowingPopup = false;
    }
};

// Global sales checking function
window.checkAndDisplaySalePrice = async function(product, priceElement, unitPriceElement = null, context = 'popup') {
    if (!product || !priceElement) return;
    
    try {
        const response = await fetch(`/api/sales.php?action=get_active_sales&item_sku=${product.sku}`);
        const data = await response.json();
        
        if (data.success && data.sale) {
            const originalPrice = parseFloat(product.retailPrice || product.price);
            const discountPercentage = parseFloat(data.sale.discount_percentage);
            const salePrice = originalPrice * (1 - discountPercentage / 100);
            
            // Format sale price display
            const saleHTML = `
                <span style="text-decoration: line-through; color: #999; font-size: 0.9em;">$${originalPrice.toFixed(2)}</span>
                <span style="color: #dc2626; font-weight: bold; margin-left: 5px;">$${salePrice.toFixed(2)}</span>
                <span style="color: #dc2626; font-size: 0.8em; margin-left: 5px;">(${discountPercentage}% off)</span>
            `;
            
            priceElement.innerHTML = saleHTML;
            
            if (unitPriceElement) {
                unitPriceElement.innerHTML = saleHTML;
            }
            
            // Update product object with sale price for cart
            product.salePrice = salePrice;
            product.originalPrice = originalPrice;
            product.isOnSale = true;
            product.discountPercentage = discountPercentage;
        } else {
            // No sale, display regular price
            const price = parseFloat(product.retailPrice || product.price);
            priceElement.textContent = `$${price.toFixed(2)}`;
            
            if (unitPriceElement) {
                unitPriceElement.textContent = `$${price.toFixed(2)}`;
            }
            
            product.isOnSale = false;
        }
    } catch (error) {
        console.log('No sale data available for', product.sku);
        // Display regular price on error
        const price = parseFloat(product.retailPrice || product.price);
        priceElement.textContent = `$${price.toFixed(2)}`;
        
        if (unitPriceElement) {
            unitPriceElement.textContent = `$${price.toFixed(2)}`;
        }
    }
};

// Global function to initialize modal event listeners
window.initializeModalEventListeners = function() {
    const quantityInput = document.getElementById('quantityInput');
    const decreaseQtyBtn = document.getElementById('decreaseQty');
    const increaseQtyBtn = document.getElementById('increaseQty');
    const closeModalBtn = document.getElementById('closeQuantityModal');
    const cancelModalBtn = document.getElementById('cancelQuantityModal');
    const confirmAddBtn = document.getElementById('confirmAddToCart');

    // Quantity adjustment buttons
    if (decreaseQtyBtn && !decreaseQtyBtn.hasGlobalListener) {
        decreaseQtyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const currentValue = parseInt(quantityInput.value) || 1;
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
                if (typeof window.updateModalTotal === 'function') {
                    window.updateModalTotal();
                }
            }
        });
        decreaseQtyBtn.hasGlobalListener = true;
    }

    if (increaseQtyBtn && !increaseQtyBtn.hasGlobalListener) {
        increaseQtyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const currentValue = parseInt(quantityInput.value) || 1;
            if (currentValue < 999) {
                quantityInput.value = currentValue + 1;
                if (typeof window.updateModalTotal === 'function') {
                    window.updateModalTotal();
                }
            }
        });
        increaseQtyBtn.hasGlobalListener = true;
    }

    // Quantity input change
    if (quantityInput && !quantityInput.hasGlobalListener) {
        quantityInput.addEventListener('input', function() {
            const value = Math.max(1, Math.min(999, parseInt(this.value) || 1));
            this.value = value;
            if (typeof window.updateModalTotal === 'function') {
                window.updateModalTotal();
            }
        });
        quantityInput.hasGlobalListener = true;
    }

    // Modal close functionality
    if (closeModalBtn && !closeModalBtn.hasGlobalListener) {
        closeModalBtn.addEventListener('click', function() {
            if (typeof window.closeCartModal === 'function') {
                window.closeCartModal();
            }
        });
        closeModalBtn.hasGlobalListener = true;
    }
    
    if (cancelModalBtn && !cancelModalBtn.hasGlobalListener) {
        cancelModalBtn.addEventListener('click', function() {
            if (typeof window.closeCartModal === 'function') {
                window.closeCartModal();
            }
        });
        cancelModalBtn.hasGlobalListener = true;
    }

    // Close modal when clicking outside
    const quantityModal = document.getElementById('quantityModal');
    if (quantityModal && !quantityModal.hasGlobalListener) {
        quantityModal.addEventListener('click', function(e) {
            if (e.target === quantityModal) {
                if (typeof window.closeCartModal === 'function') {
                    window.closeCartModal();
                }
            }
        });
        quantityModal.hasGlobalListener = true;
    }

    // Confirm add to cart
    if (confirmAddBtn && !confirmAddBtn.hasGlobalListener) {
        confirmAddBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (typeof window.confirmAddToCart === 'function') {
                window.confirmAddToCart();
            }
        });
        confirmAddBtn.hasGlobalListener = true;
    }
};

// Global function to initialize popup event listeners
window.initializePopupEventListeners = function() {
    const popup = document.getElementById('productPopup');
    if (!popup) return;
    
    // Keep popup visible when hovering over it
    if (!popup.hasGlobalMouseEnter) {
        popup.addEventListener('mouseenter', () => {
            clearTimeout(window.globalPopupTimeout);
            window.isShowingPopup = true;
            window.popupOpen = true;
        });
        popup.hasGlobalMouseEnter = true;
    }

    if (!popup.hasGlobalMouseLeave) {
        popup.addEventListener('mouseleave', () => {
            window.hideGlobalPopup();
        });
        popup.hasGlobalMouseLeave = true;
    }

    // Close popup when clicking outside
    if (!document.hasGlobalPopupClickListener) {
        document.addEventListener('click', function(e) {
            if (popup && popup.classList.contains('show') && !popup.contains(e.target) && !e.target.closest('.product-icon')) {
                window.hidePopupImmediate();
            }
        });
        document.hasGlobalPopupClickListener = true;
    }
};

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Initialize modal and popup event listeners
    window.initializeModalEventListeners();
    window.initializePopupEventListeners();
});

// Function to calculate how many of each size are already in the cart for a specific item
window.getCartQuantityForSize = function(itemSku, colorName, sizeCode) {
    if (!window.cart || !window.cart.items) return 0;
    
    return window.cart.items.reduce((total, cartItem) => {
        if (cartItem.sku === itemSku) {
            const cartColor = cartItem.color || null;
            const cartSize = cartItem.size || null;
            
            // Match color (or both null)
            const colorMatch = (colorName === cartColor) || (colorName === null && cartColor === null);
            
            // Match size
            const sizeMatch = cartSize === sizeCode;
            
            if (colorMatch && sizeMatch) {
                return total + (cartItem.quantity || 0);
            }
        }
        return total;
    }, 0);
};

// Function to get total cart quantity for an item (all colors/sizes combined)
window.getCartQuantityForItem = function(itemSku) {
    if (!window.cart || !window.cart.items) return 0;
    
    return window.cart.items.reduce((total, cartItem) => {
        if (cartItem.sku === itemSku) {
            return total + (cartItem.quantity || 0);
        }
        return total;
    }, 0);
};

// Function to setup size dropdown in quantity modal
window.setupSizeDropdown = async function(availableSizes, generalSizes, colorSpecificSizes, selectedSize = null) {
    // Find or create size dropdown container
    let sizeContainer = document.getElementById('sizeDropdownContainer');
    
    if (!sizeContainer) {
        // Create size dropdown container and insert it after color container or before quantity controls
        const colorContainer = document.getElementById('colorDropdownContainer');
        const quantityControls = document.querySelector('.quantity-controls');
        const insertBefore = quantityControls;
        
        if (insertBefore) {
            sizeContainer = document.createElement('div');
            sizeContainer.id = 'sizeDropdownContainer';
            sizeContainer.className = 'size-dropdown-container';
            insertBefore.parentNode.insertBefore(sizeContainer, insertBefore);
        } else {
            console.error('Could not find location to insert size dropdown');
            return;
        }
    }
    
    // Clear existing content
    sizeContainer.innerHTML = '';
    
    // Determine which sizes to show
    let sizesToShow = availableSizes;
    
    // If color is selected and has specific sizes, use those instead
    if (window.currentModalProduct && window.currentModalProduct.selectedColor) {
        const selectedColorData = window.currentModalProduct.availableColors?.find(
            c => c.color_name === window.currentModalProduct.selectedColor
        );
        if (selectedColorData && colorSpecificSizes[selectedColorData.id]) {
            sizesToShow = colorSpecificSizes[selectedColorData.id];
        }
    }
    
    if (sizesToShow && sizesToShow.length > 0) {
        // Get current selected color for cart quantity calculation
        const currentColor = window.currentModalProduct?.selectedColor || null;
        
        // Create size dropdown
        const sizeHTML = `
            <div class="size-selection">
                <label for="sizeSelect">Size:</label>
                <div class="size-dropdown-wrapper">
                    <select id="sizeSelect" class="size-select" onchange="updateSelectedSize()">
                        <option value="">Choose a size...</option>
                        ${sizesToShow.map(size => {
                            const priceAdjustment = parseFloat(size.price_adjustment || 0);
                            const adjustmentText = priceAdjustment !== 0 ? 
                                ` (${priceAdjustment > 0 ? '+' : ''}$${priceAdjustment.toFixed(2)})` : '';
                            
                            // Calculate actual available quantity (stock - cart quantity)
                            const cartQuantity = window.getCartQuantityForSize(
                                window.currentModalProduct?.sku, 
                                currentColor, 
                                size.size_code
                            );
                            const actualAvailable = Math.max(0, (size.stock_level || 0) - cartQuantity);
                            
                            let stockText;
                            if (cartQuantity > 0) {
                                stockText = actualAvailable > 0 ? 
                                    ` (${actualAvailable} available, ${cartQuantity} in cart)` : 
                                    ` (Out of stock - ${cartQuantity} in cart)`;
                            } else {
                                stockText = actualAvailable > 0 ? 
                                    ` (${actualAvailable} available)` : ' (Out of stock)';
                            }
                            
                            return `
                                <option value="${size.size_code}" 
                                        data-size-name="${size.size_name}" 
                                        data-price-adjustment="${priceAdjustment}"
                                        data-stock="${size.stock_level}"
                                        data-available="${actualAvailable}"
                                        ${selectedSize === size.size_code ? 'selected' : ''}
                                        ${actualAvailable <= 0 ? 'disabled' : ''}>
                                    ${size.size_name}${adjustmentText}${stockText}
                                </option>
                            `;
                        }).join('')}
                    </select>
                </div>
            </div>
        `;
        
        sizeContainer.innerHTML = sizeHTML;
        
        // Add CSS if not already added
        if (!document.getElementById('sizeDropdownStyles')) {
            const style = document.createElement('style');
            style.id = 'sizeDropdownStyles';
            style.textContent = `
                .size-dropdown-container {
                    margin-bottom: 15px;
                }
                .size-selection label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 500;
                    color: #374151;
                }
                .size-dropdown-wrapper {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .size-select {
                    flex: 1;
                    padding: 8px 12px;
                    border: 1px solid #d1d5db;
                    border-radius: 6px;
                    background: white;
                    font-size: 14px;
                    color: #374151;
                }
                .size-select:focus {
                    outline: none;
                    border-color: #87ac3a;
                    box-shadow: 0 0 0 2px rgba(135, 172, 58, 0.2);
                }
                .size-select option:disabled {
                    color: #9ca3af;
                    background-color: #f3f4f6;
                }
            `;
            document.head.appendChild(style);
        }
        
        // Update price if size has adjustment
        updateModalTotal();
    }
};

// Function to update selected size and price
window.updateSelectedSize = function() {
    const sizeSelect = document.getElementById('sizeSelect');
    if (!sizeSelect || !window.currentModalProduct) return;
    
    const selectedOption = sizeSelect.options[sizeSelect.selectedIndex];
    const selectedSize = sizeSelect.value;
    const priceAdjustment = parseFloat(selectedOption?.getAttribute('data-price-adjustment') || 0);
    
    window.currentModalProduct.selectedSize = selectedSize;
    window.currentModalProduct.selectedSizeName = selectedOption?.getAttribute('data-size-name') || '';
    window.currentModalProduct.sizeAdjustment = priceAdjustment;
    
    // Update modal total with size price adjustment
    updateModalTotal();
}; 