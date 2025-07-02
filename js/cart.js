// Centralized cart functions - used throughout the application
// Functions moved here from: register_page.html, sections/admin_pos.php

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
        const detailedModal = document.getElementById('detailedItemModal');
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
        const gender = item.gender || null; // Gender selection support
        
        // Create unique identifier for item+gender+color+size combination
        const existingItem = this.items.find(cartItem => {
            const cartColor = cartItem.color || null;
            const cartSize = cartItem.size || null;
            const cartGender = cartItem.gender || null;
            return cartItem.sku === item.sku && 
                   cartColor === color && 
                   cartSize === size &&
                   cartGender === gender;
        });
        
        let isNewItem = false;
        let finalQuantity = quantity;
        
        if (existingItem) {
            existingItem.quantity += quantity;
            finalQuantity = existingItem.quantity;
        } else {
            isNewItem = true;
            const cartItem = {
                sku: item.sku,
                name: item.name,
                price: parseFloat(item.price) || 0,
                image: item.image,
                quantity: quantity
            };
            
            // Build display name with gender, color, and size
            let displayParts = [item.name];
            
            // Add gender if specified (first in hierarchy)
            if (gender) {
                cartItem.gender = gender;
                displayParts.push(gender);
            }
            
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
        
        // Show notifications
        this.showAddToCartNotifications(item, quantity, finalQuantity, isNewItem);
        
        // Track cart action for analytics
        if (window.analyticsTracker) {
            window.analyticsTracker.trackCartAction('add', item.sku);
        }
    }

    removeItem(itemSku, color = null, size = null, gender = null) {
        // Normalize parameters - convert string 'null' to actual null
        if (color === 'null') color = null;
        if (size === 'null') size = null;
        if (gender === 'null') gender = null;
        
        console.log(`🗑️ Removing item: ${itemSku}, color: ${color}, size: ${size}, gender: ${gender}`);
        console.log(`📊 Cart before removal:`, this.items.length, 'items');
        
        // Find item in array that matches all criteria
        const itemIndex = this.items.findIndex(cartItem => {
            const cartColor = cartItem.color || null;
            const cartSize = cartItem.size || null;
            const cartGender = cartItem.gender || null;
            return cartItem.sku === itemSku && 
                   cartColor === color && 
                   cartSize === size &&
                   cartGender === gender;
        });
        
        if (itemIndex !== -1) {
            this.items.splice(itemIndex, 1);
            console.log(`📊 Cart after removal:`, this.items.length, 'items');
            this.saveCart();
            console.log(`💾 Cart saved to localStorage`);
            
            // Verify localStorage was updated
            const savedCart = JSON.parse(localStorage.getItem('cart') || '[]');
            console.log(`✅ localStorage verification:`, savedCart.length, 'items');
            
            this.updateCartCount();
            this.dispatchCartUpdate();
            
            // Clean up any hidden notifications
            this.cleanupHiddenNotifications();
            
            console.log(`✅ Removed item: ${itemSku}, color: ${color}, size: ${size}, gender: ${gender}`);
        } else {
            console.warn(`❌ Item not found for removal: ${itemSku}, color: ${color}, size: ${size}, gender: ${gender}`);
            console.log(`🔍 Available items:`, this.items.map(item => ({sku: item.sku, color: item.color, size: item.size, gender: item.gender})));
        }
    }

    updateQuantity(itemSku, quantity, color = null, size = null, gender = null) {
        // Normalize parameters - convert string 'null' to actual null
        if (color === 'null') color = null;
        if (size === 'null') size = null;
        if (gender === 'null') gender = null;
        
        // Find item in array that matches all criteria
        const existingItem = this.items.find(cartItem => {
            const cartColor = cartItem.color || null;
            const cartSize = cartItem.size || null;
            const cartGender = cartItem.gender || null;
            return cartItem.sku === itemSku && 
                   cartColor === color && 
                   cartSize === size &&
                   cartGender === gender;
        });
        
        if (existingItem) {
            if (quantity <= 0) {
                this.removeItem(itemSku, color, size, gender);
            } else {
                existingItem.quantity = quantity;
                this.saveCart();
                this.updateCartCount();
                this.dispatchCartUpdate();
            }
        } else {
            console.warn(`Item not found for quantity update: ${itemSku}, color: ${color}, size: ${size}, gender: ${gender}`);
        }
    }

    getTotal() {
        return this.items.reduce((total, item) => {
            return total + (parseFloat(item.price) * item.quantity);
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
        
        // Clean up any hidden notifications
        this.cleanupHiddenNotifications();
        
        // Show cart status (empty cart) with brand green success styling
        setTimeout(() => {
            window.showSuccess('🛒 Cart is now empty', {
                duration: 3000,
                title: 'Cart Cleared'
            });
        }, 500);
    }

    saveCart() {
        localStorage.setItem('cart', JSON.stringify(this.items));
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

    // Add method to clean up any hidden notification elements
    cleanupHiddenNotifications() {
        // Remove any orphaned notification elements that might be consuming resources
        const hiddenNotifications = document.querySelectorAll('[id*="toast"], [class*="toast"], [class*="notification"]:not([class*="wf-notification"])');
        hiddenNotifications.forEach(element => {
            // Only remove if it's actually hidden or has opacity 0
            const styles = window.getComputedStyle(element);
            if (styles.display === 'none' || styles.opacity === '0' || styles.visibility === 'hidden') {
                console.log('Removing hidden notification element:', element);
                element.remove();
            }
        });
        
        // Also check for any lingering cart popup notifications
        const oldPopups = document.querySelectorAll('.cart-popup-notification');
        oldPopups.forEach(popup => {
            const styles = window.getComputedStyle(popup);
            if (styles.opacity === '0' || parseFloat(styles.opacity) < 0.1) {
                console.log('Removing hidden cart popup:', popup);
                popup.remove();
            }
        });
    }

    // Add cart status toast notification
    showCartStatusToast() {
        const itemCount = this.getItemCount();
        const total = this.getTotal();
        const formattedTotal = '$' + total.toFixed(2);
        
        let statusMessage;
        if (itemCount === 0) {
            statusMessage = 'Cart is empty';
        } else if (itemCount === 1) {
            statusMessage = `🛒 1 item • ${formattedTotal}`;
        } else {
            statusMessage = `🛒 ${itemCount} items • ${formattedTotal}`;
        }
        
        // Show cart status toast with a delay after the main notification
        setTimeout(() => {
            window.showSuccess(statusMessage, {
                duration: 5000, // Show for 5 seconds
                title: 'Cart Status'
            });
        }, 1500); // Delay by 1.5 seconds so it appears after the main "item added" toast
    }

    showAddToCartNotifications(item, addedQuantity, totalQuantity, isNewItem) {
        // Clean up any hidden notifications first
        this.cleanupHiddenNotifications();
        
        // Build comprehensive notification message
        let displayName = item.name;
        let detailParts = [];
        
        if (item.gender) detailParts.push(item.gender);
        if (item.color) detailParts.push(item.color);
        if (item.size) detailParts.push(item.size);
        
        if (detailParts.length > 0) {
            displayName += ` (${detailParts.join(', ')})`;
        }
        
        // Format price and create comprehensive message
        const formattedPrice = '$' + (parseFloat(item.price) || 0).toFixed(2);
        const quantityText = addedQuantity > 1 ? ` (${addedQuantity})` : '';
        
        // Create a single, comprehensive notification message
        const notificationMessage = `🛒 ${displayName}${quantityText} - ${formattedPrice}`;
        
        // Show the main notification with title
        window.showSuccess(notificationMessage, {
            title: '✅ Added to Cart',
            duration: 5000
        });
        
        // Show small popup notification near the item (if possible) - but don't duplicate the main notification
        this.showItemPopupNotification(item, addedQuantity, totalQuantity, isNewItem);
        
        // Show cart status toast after a brief delay
        this.showCartStatusToast();
    }
    
    showItemPopupNotification(item, addedQuantity, totalQuantity, isNewItem) {
        // Find the item element that was clicked (search for elements with the SKU)
        const itemElements = document.querySelectorAll(`[data-sku="${item.sku}"], .item-icon[data-sku="${item.sku}"], .product-icon[data-sku="${item.sku}"]`);
        
        if (itemElements.length === 0) {
            console.log('No item element found for popup notification');
            return;
        }
        
        // Use the first found element
        const itemElement = itemElements[0];
        
        // Create notification popup
        const popup = document.createElement('div');
        popup.className = 'cart-popup-notification';
        popup.style.cssText = `
            position: absolute;
            background: #87ac3a;
            color: white;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(135, 172, 58, 0.4);
            border: 2px solid #6b8e23;
            white-space: nowrap;
            pointer-events: none;
            transform: scale(0.8);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        `;
        
        // Create notification text
        const statusText = isNewItem ? 
            `${addedQuantity} added to cart` : 
            `+${addedQuantity} added (${totalQuantity} total)`;
        
        popup.textContent = statusText;
        
        // Position the popup relative to the item element
        const rect = itemElement.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
        
        popup.style.left = (rect.left + scrollLeft + rect.width / 2 - 75) + 'px'; // Approximate center
        popup.style.top = (rect.top + scrollTop - 40) + 'px'; // Above the item
        
        // Add to document body
        document.body.appendChild(popup);
        
        // Animate in
        requestAnimationFrame(() => {
            popup.style.transform = 'scale(1) translateY(-5px)';
            popup.style.opacity = '1';
        });
        
        // Animate out and remove after 2.5 seconds
        setTimeout(() => {
            popup.style.transform = 'scale(0.8) translateY(-10px)';
            popup.style.opacity = '0';
            
            setTimeout(() => {
                if (popup.parentElement) {
                    popup.remove();
                }
            }, 300);
        }, 2500);
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
        
        console.log('🎯 Found cart container:', cartContainer.id, 'with', this.items.length, 'items to render');
        
        // Force reload from localStorage to ensure we have the latest data
        this.items = JSON.parse(localStorage.getItem('cart') || '[]');
        console.log('🔄 Reloaded cart from localStorage:', this.items.length, 'items');

        // Load sales verbiage
        let salesVerbiage = {};
        try {
            const verbiageResponse = await fetch('/api/business_settings.php?action=get_sales_verbiage');
            
            // Check if response is OK
            if (!verbiageResponse.ok) {
                console.warn('Sales verbiage API returned error:', verbiageResponse.status);
            } else {
                const responseText = await verbiageResponse.text();
                
                // Check if response is JSON
                if (responseText.trim().startsWith('{')) {
                    const verbiageData = JSON.parse(responseText);
                    if (verbiageData.success) {
                        salesVerbiage = verbiageData.verbiage;
                        console.log('Sales verbiage loaded successfully');
                    }
                } else {
                    console.warn('Sales verbiage API returned HTML instead of JSON:', responseText.substring(0, 200));
                }
            }
        } catch (error) {
            console.log('Could not load sales verbiage:', error);
            // Continue with empty sales verbiage - cart should still work
        }

        if (this.items.length === 0) {
            cartContainer.innerHTML = '<div class="text-center py-8 text-gray-500">Your cart is empty</div>';
            return;
        }

        // Skip refreshProductData during cart refresh to prevent overwriting cart changes
        // await this.refreshProductData();

        // Process each item to get the correct color-specific image
        const processedItems = await Promise.all(this.items.map(async (item) => {
            let finalImageUrl = item.image || `/images/items/${item.sku}A.webp`;
            
            // If item has a color, try to get the color-specific image
            if (item.color) {
                try {
                    const colorResponse = await fetch(`/api/item_colors.php?action=get_colors&item_sku=${item.sku}`);
                    const colorData = await colorResponse.json();
                    
                    if (colorData.success && colorData.colors) {
                        const selectedColorData = colorData.colors.find(c => c.color_name === item.color);
                        if (selectedColorData && selectedColorData.image_path) {
                            // Handle image path correctly
                            const imagePath = selectedColorData.image_path;
                            const imageUrl = imagePath.startsWith('/images/items/') || imagePath.startsWith('images/items/') 
                                ? imagePath 
                                : `images/items/${imagePath}`;
                            
                            // Test if the color-specific image exists
                            const imageExists = await new Promise(resolve => {
                                const testImage = new Image();
                                testImage.onload = () => resolve(true);
                                testImage.onerror = () => resolve(false);
                                testImage.src = imageUrl;
                            });
                            
                            if (imageExists) {
                                finalImageUrl = imageUrl;
                            }
                        }
                    }
                } catch (error) {
                    console.log(`Could not fetch color data for ${item.sku}:`, error);
                }
            }
            
            return { ...item, finalImageUrl };
        }));

        // Build sales messaging sections
        const headerMessage = salesVerbiage.cart_header_message ? 
            `<div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-4">
                <div class="flex items-center">
                    <span class="text-lg mr-2">🛒</span>
                    <span class="font-semibold">${salesVerbiage.cart_header_message}</span>
                </div>
            </div>` : '';

        const urgencyMessage = salesVerbiage.cart_urgency_message ? 
            `<div class="bg-orange-50 border border-orange-200 text-orange-800 px-4 py-2 rounded-lg mb-4 text-center">
                <span class="font-medium">${salesVerbiage.cart_urgency_message}</span>
            </div>` : '';

        const socialProofMessage = salesVerbiage.cart_social_proof ? 
            `<div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-2 rounded-lg mb-4 text-center">
                <span>${salesVerbiage.cart_social_proof}</span>
            </div>` : '';

        const guaranteeMessage = salesVerbiage.cart_guarantee_message ? 
            `<div class="bg-green-50 border border-green-200 text-green-800 px-4 py-2 rounded-lg mb-4 text-center">
                <span class="font-medium">${salesVerbiage.cart_guarantee_message}</span>
            </div>` : '';

        const cartHTML = processedItems.map(item => {
            return `
            <div class="flex items-center justify-between p-4 border-b border-gray-200">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                        <img src="${item.finalImageUrl}" alt="${item.name}" class="w-full h-full object-contain" 
                             onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#f8f9fa;color:#6c757d;font-size:0.75rem;\\'><div style=\\'font-size:1.5rem;margin-bottom:0.25rem;\\'>📷</div><div>No Image</div></div>';">
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-900">${item.displayName}</h3>
                        <p class="text-xs text-gray-400 font-mono">${item.sku}</p>
                        <p class="text-sm text-gray-500">$${item.price.toFixed(2)}</p>
                        <div class="flex items-center mt-1 space-x-3">
                            ${item.gender ? `<div class="flex items-center">
                                <span class="text-xs text-gray-500">👤 ${item.gender}</span>
                            </div>` : ''}
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
                    <button onclick="updateQuantity('${item.sku}', ${item.quantity - 1}, ${item.color ? `'${item.color}'` : null}, ${item.size ? `'${item.size}'` : null}, ${item.gender ? `'${item.gender}'` : null})" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded">-</button>
                    <span class="px-3 py-1 bg-gray-100 rounded">${item.quantity}</span>
                    <button onclick="updateQuantity('${item.sku}', ${item.quantity + 1}, ${item.color ? `'${item.color}'` : null}, ${item.size ? `'${item.size}'` : null}, ${item.gender ? `'${item.gender}'` : null})" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded">+</button>
                    <button onclick="removeFromCart('${item.sku}', ${item.color ? `'${item.color}'` : null}, ${item.size ? `'${item.size}'` : null}, ${item.gender ? `'${item.gender}'` : null})" class="px-2 py-1 rounded ml-2" style="background-color: #dc2626; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#b91c1c'" onmouseout="this.style.backgroundColor='#dc2626'">Remove</button>
                </div>
            </div>
            `;
        }).join('');

        const footerMessage = salesVerbiage.cart_footer_message ? 
            `<div class="bg-purple-50 border border-purple-200 text-purple-800 px-4 py-3 rounded-lg mb-4 text-center">
                <span class="font-semibold">💎 ${salesVerbiage.cart_footer_message}</span>
            </div>` : '';

        // Create the structured layout for the new cart design
        const cartContentHTML = `
            <div class="flex-shrink-0 p-4 border-b border-gray-200">
                ${headerMessage}
                ${urgencyMessage}
                ${socialProofMessage}
            </div>
            <div class="flex-1 overflow-y-auto">
                ${cartHTML}
            </div>
            <div class="flex-shrink-0 border-t border-gray-200 bg-gray-50 p-4">
                ${guaranteeMessage}
                <div class="flex justify-between items-center mb-4">
                    <span class="text-lg font-semibold">Total: $${this.getTotal().toFixed(2)}</span>
                    <button onclick="
                        // Capture scroll position before clear
                        const cartContainer = document.getElementById('cartContainer') || document.getElementById('cartItems');
                        if (cartContainer) {
                            const scrollableChild = cartContainer.querySelector('.overflow-y-auto');
                            if (scrollableChild) {
                                window.pendingScrollRestore = {
                                    scrollTop: 0, // Reset to top after clear
                                    elementClass: scrollableChild.className
                                };
                            }
                        }
                        cart.clearCart(); 
                        setTimeout(async () => await window.refreshCartDisplay(), 100);
                    " class="px-4 py-2 rounded text-white" style="background-color: #6b7280; color: white !important; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">Clear Cart</button>
                </div>
                ${footerMessage}
                <button onclick="window.cart.checkout()" class="brand-button w-full py-3 px-6 rounded-lg font-semibold" style="background-color: #87ac3a !important; color: #ffffff !important; transition: all 0.2s ease;" onmouseover="this.style.backgroundColor='#6b8e23'" onmouseout="this.style.backgroundColor='#87ac3a'">Proceed to Checkout</button>
            </div>
        `;
        
        cartContainer.innerHTML = cartContentHTML;
        
        // Store scroll restoration function on the cart container for external access
        if (window.pendingScrollRestore) {
            const { scrollTop, elementClass } = window.pendingScrollRestore;
            console.log('📍 Applying pending scroll restore:', scrollTop, 'to element with class:', elementClass);
            
            // Find the scrollable element again
            const scrollableChild = cartContainer.querySelector('.overflow-y-auto, [style*="overflow-y: auto"], [style*="overflow: auto"]');
            if (scrollableChild) {
                setTimeout(() => {
                    scrollableChild.scrollTop = scrollTop;
                    console.log('📍 Applied pending scroll restore to:', scrollTop);
                }, 10);
            }
            
            // Clear the pending restore
            delete window.pendingScrollRestore;
        }
    }

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
                    <button onclick="document.getElementById('paymentMethodModal').remove()" class="flex-1 py-2 px-4 rounded text-white" style="background-color: #6b7280; color: white !important; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#4b5563'" onmouseout="this.style.backgroundColor='#6b7280'">Cancel</button>
                    <button onclick="window.cart.proceedToCheckout()" class="brand-button flex-1 py-2 px-4 rounded" style="background-color: #87ac3a !important; color: #ffffff !important; transition: all 0.2s ease;" onmouseover="this.style.backgroundColor='#6b8e23'" onmouseout="this.style.backgroundColor='#87ac3a'">Place Order</button>
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
            console.log('Session storage user:', sessionStorage.getItem('user'));
            const customerId = sessionStorage.getItem('user') ? JSON.parse(sessionStorage.getItem('user')).userId : null;
            console.log('Customer ID:', customerId);
            
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

            console.log('Order data being sent:', orderData);
            console.log('Cart items:', this.items);
            console.log('Cart total:', this.getTotal());

            const response = await fetch('api/add-order.php', {
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
                this.showErrorNotification(`Server error (${response.status}): Please try again or contact support.`);
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
                this.showErrorNotification('Order failed: ' + result.error);
            }
        } catch (error) {
            console.error('Checkout error:', error);
            this.showErrorNotification('An error occurred during checkout. Please try again.');
        }
    }

    // Method to show current cart status (can be called manually)
    showCurrentCartStatus() {
        // Clean up any hidden notifications first
        this.cleanupHiddenNotifications();
        
        const itemCount = this.getItemCount();
        const total = this.getTotal();
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
        
        window.showSuccess(statusMessage, {
            duration: 5000, // Show for 5 seconds when manually called
            title: statusTitle
        });
    }

    // Method to manually clean up hidden notifications (can be called from console)
    manualCleanup() {
        console.log('Starting manual cleanup of hidden notifications...');
        this.cleanupHiddenNotifications();
        
        // Also clean up any duplicate notification containers
        const containers = document.querySelectorAll('#wf-notification-container');
        if (containers.length > 1) {
            console.log(`Found ${containers.length} notification containers, removing duplicates...`);
            // Keep the first one, remove the rest
            for (let i = 1; i < containers.length; i++) {
                containers[i].remove();
            }
        }
        
        console.log('Manual cleanup completed');
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
        // Note: Cart class will handle the notification automatically via showAddToCartNotifications()
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

// Global function to refresh cart display with scroll position preservation
window.refreshCartDisplay = async function() {
    console.log('🔄 Starting cart display refresh...');
    
    // Log current cart state before refresh
    if (window.cart) {
        console.log('📊 Cart refresh - items:', window.cart.items.length);
    }
    
    // Check for cart containers first
    const cartContainer = document.getElementById('cartContainer');
    const cartItems = document.getElementById('cartItems');
    const hasCartContainer = !!(cartItems || cartContainer);
    const activeContainer = cartItems || cartContainer;
    
    // Capture scroll position before refresh
    let savedScrollTop = 0;
    let scrollableElement = null;
    
    if (activeContainer) {
        // First check for the actual scrollable child div (overflow-y-auto)
        const scrollableChild = activeContainer.querySelector('.overflow-y-auto, [style*="overflow-y: auto"], [style*="overflow: auto"]');
        
        if (scrollableChild && scrollableChild.scrollHeight > scrollableChild.clientHeight) {
            scrollableElement = scrollableChild;
            savedScrollTop = scrollableChild.scrollTop;
            console.log('📍 Captured scrollable child position:', savedScrollTop, 'from element:', scrollableChild.className);
        } else if (activeContainer.scrollHeight > activeContainer.clientHeight) {
            // Check if the container itself is scrollable
            scrollableElement = activeContainer;
            savedScrollTop = activeContainer.scrollTop;
            console.log('📍 Captured container scroll position:', savedScrollTop);
        } else {
            // Look for scrollable parent elements
            let parent = activeContainer.parentElement;
            while (parent && parent !== document.body) {
                if (parent.scrollHeight > parent.clientHeight) {
                    scrollableElement = parent;
                    savedScrollTop = parent.scrollTop;
                    console.log('📍 Captured parent scroll position:', savedScrollTop, 'from element:', parent.className || parent.tagName);
                    break;
                }
                parent = parent.parentElement;
            }
            
            // If no scrollable parent found, use window scroll
            if (!scrollableElement || scrollableElement === activeContainer) {
                scrollableElement = window;
                savedScrollTop = window.pageYOffset || document.documentElement.scrollTop;
                console.log('📍 Captured window scroll position:', savedScrollTop);
            }
        }
    }
    
    console.log('🔍 Container check:', {
        cartContainer: !!cartContainer,
        cartItems: !!cartItems,
        cartExists: !!window.cart,
        itemCount: window.cart ? window.cart.items.length : 'N/A',
        currentPage: window.location.search,
        savedScrollTop: savedScrollTop
    });
    
    // If we're supposed to be on the cart page but don't have a cart container,
    // we might be on the login page due to redirect - skip refresh
    if (window.location.search.includes('page=cart') && !hasCartContainer) {
        console.warn('⚠️ On cart page but no cart container found - likely redirected to login');
        return;
    }
    
    // Re-render the cart if we're on the cart page (async operation)
    if (window.cart && typeof window.cart.renderCart === 'function' && hasCartContainer) {
        try {
            await window.cart.renderCart();
            console.log('✅ Cart rendered successfully');
            
            // Restore scroll position after DOM is updated
            if (scrollableElement && savedScrollTop > 0) {
                // Use requestAnimationFrame to ensure DOM is fully updated
                requestAnimationFrame(() => {
                    requestAnimationFrame(() => {
                        try {
                            if (scrollableElement === window) {
                                window.scrollTo(0, savedScrollTop);
                                console.log('📍 Restored window scroll position to:', savedScrollTop);
                            } else {
                                // If the original scrollable element was a child, find the new one after re-render
                                let targetElement = scrollableElement;
                                
                                if (scrollableElement.classList && scrollableElement.classList.contains('overflow-y-auto')) {
                                    // Re-find the scrollable child after DOM update
                                    const newScrollableChild = activeContainer.querySelector('.overflow-y-auto, [style*="overflow-y: auto"], [style*="overflow: auto"]');
                                    if (newScrollableChild) {
                                        targetElement = newScrollableChild;
                                        console.log('📍 Found new scrollable child element after re-render');
                                    }
                                }
                                
                                targetElement.scrollTop = savedScrollTop;
                                console.log('📍 Restored scroll position to:', savedScrollTop, 'on element:', targetElement.className || targetElement.tagName);
                            }
                        } catch (error) {
                            console.warn('⚠️ Could not restore scroll position:', error);
                        }
                    });
                });
            }
            
            // Double-check the container content after render
            if (activeContainer) {
                console.log('📄 Container content length:', activeContainer.innerHTML.length);
                console.log('📄 Container preview:', activeContainer.innerHTML.substring(0, 100) + '...');
            }
        } catch (error) {
            console.error('❌ Error rendering cart:', error);
        }
    } else if (!hasCartContainer) {
        console.log('ℹ️ No cart container found - not on cart page, skipping cart render');
    } else {
        console.warn('⚠️ Cart or renderCart method not available');
    }
    
    // Update cart count in header/navigation (this should always work)
    if (window.cart && typeof window.cart.updateCartCount === 'function') {
        window.cart.updateCartCount();
        console.log('✅ Cart count updated');
    }
    
    // Dispatch cart update event for any other components listening
    if (window.cart && typeof window.cart.dispatchCartUpdate === 'function') {
        window.cart.dispatchCartUpdate();
        console.log('✅ Cart update event dispatched');
    }
    
    console.log('🔄 Cart display refresh completed');
};

function removeFromCart(sku, color = null, size = null, gender = null) {
    console.log(`🎯 Global removeFromCart called with: ${sku}, ${color}, ${size}, ${gender}`);
    if (cart) {
        // Capture scroll position before removal
        const cartContainer = document.getElementById('cartContainer') || document.getElementById('cartItems');
        if (cartContainer) {
            const scrollableChild = cartContainer.querySelector('.overflow-y-auto, [style*="overflow-y: auto"], [style*="overflow: auto"]');
            if (scrollableChild) {
                window.pendingScrollRestore = {
                    scrollTop: scrollableChild.scrollTop,
                    elementClass: scrollableChild.className
                };
                console.log('📍 Stored scroll position before remove:', scrollableChild.scrollTop);
            }
        }
        
        cart.removeItem(sku, color, size, gender);
        // Refresh cart display after removal
        setTimeout(async () => {
            await window.refreshCartDisplay();
        }, 100); // Small delay to ensure cart update is complete
    }
}

function updateQuantity(sku, newQuantity, color = null, size = null, gender = null) {
    console.log(`🎯 Global updateQuantity called with: ${sku}, ${newQuantity}, ${color}, ${size}, ${gender}`);
    if (cart) {
        // Capture scroll position before quantity update
        const cartContainer = document.getElementById('cartContainer') || document.getElementById('cartItems');
        if (cartContainer) {
            const scrollableChild = cartContainer.querySelector('.overflow-y-auto, [style*="overflow-y: auto"], [style*="overflow: auto"]');
            if (scrollableChild) {
                window.pendingScrollRestore = {
                    scrollTop: scrollableChild.scrollTop,
                    elementClass: scrollableChild.className
                };
                console.log('📍 Stored scroll position before quantity update:', scrollableChild.scrollTop);
            }
        }
        
        cart.updateQuantity(sku, newQuantity, color, size, gender);
        // Refresh cart display after quantity update
        setTimeout(async () => {
            await window.refreshCartDisplay();
        }, 100); // Small delay to ensure cart update is complete
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
            imageUrl = `images/items/${sku}A.webp`; // Try .webp first
        }
        
        modalProductImage.src = imageUrl;
        modalProductImage.onerror = function() {
            if (!imageUrl.includes('.webp')) {
                // If original wasn't .webp, try .webp version
                const webpUrl = imageUrl.replace(/\.(png|jpg|jpeg)$/i, '.webp');
                this.src = webpUrl;
                this.onerror = function() {
                    // Then try .png
                    const pngUrl = `images/items/${sku}A.png`;
                    this.src = pngUrl;
                    this.onerror = function() {
                        this.src = 'images/items/placeholder.webp';
                        this.onerror = null;
                    };
                };
            } else {
                // If original was .webp, try .png directly
                const pngUrl = `images/items/${sku}A.png`;
                this.src = pngUrl;
                this.onerror = function() {
                    this.src = 'images/items/placeholder.webp';
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
        const saleHTML = `
            <span class="sale-price-original">$${parseFloat(price).toFixed(2)}</span>
            <span class="sale-price-current">$${parseFloat(finalPrice).toFixed(2)}</span>
        `;
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
    
    // Add modal-open class to body for z-index hierarchy
    document.body.classList.add('modal-open');
    document.documentElement.classList.add('modal-open');
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
        // Store available colors in currentModalProduct for image switching
        if (window.currentModalProduct) {
            window.currentModalProduct.availableColors = availableColors;
        }
        
        // Create color dropdown
        const colorHTML = `
            <div class="color-selection">
                <label for="colorSelect">Color:</label>
                <div class="color-dropdown-wrapper">
                    <select id="colorSelect" class="color-select" onchange="updateSelectedColor()">
                        <option value="">Choose a color...</option>
                        ${availableColors.map(color => {
                            // Calculate cart quantity for this color
                            const cartQuantity = typeof window.getCartQuantityForColor === 'function' ? 
                                window.getCartQuantityForColor(window.currentModalProduct?.sku, color.color_name) : 0;
                            
                            // Calculate available quantity
                            const availableQuantity = Math.max(0, color.stock_level - cartQuantity);
                            
                            // Build availability text
                            let availabilityText = '';
                            if (availableQuantity > 0) {
                                if (cartQuantity > 0) {
                                    availabilityText = ` (${availableQuantity} available, ${cartQuantity} in cart)`;
                                } else {
                                    availabilityText = ` (${availableQuantity} available)`;
                                }
                            } else {
                                if (cartQuantity > 0) {
                                    availabilityText = ` (Out of stock - ${cartQuantity} in cart)`;
                                } else {
                                    availabilityText = ' (Out of stock)';
                                }
                            }
                            
                            return `
                                <option value="${color.color_name}" data-color-code="${color.color_code || ''}" data-image-path="${color.image_path || ''}" ${selectedColor === color.color_name ? 'selected' : ''} ${availableQuantity <= 0 ? 'disabled' : ''}>
                                    ${color.color_name}${availabilityText}
                                </option>
                            `;
                        }).join('')}
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
            window.currentModalProduct.availableColors = availableColors;
            
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

// Function to handle color selection changes
window.updateSelectedColor = function() {
    const colorSelect = document.getElementById('colorSelect');
    if (!colorSelect || !window.currentModalProduct) return;
    
    const selectedColor = colorSelect.value;
    window.currentModalProduct.selectedColor = selectedColor;
    
    // Update color swatch
    updateColorSwatch();
    
    // Update product image for the selected color
    updateProductImageForColor();
    
    // Refresh size dropdown if needed (some items have color-specific sizes)
    if (typeof window.refreshModalSizeDropdown === 'function') {
        window.refreshModalSizeDropdown();
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
            const modalImage = document.querySelector('#quantityModal .product-image img') || document.querySelector('#modalProductImage');
            if (modalImage) {
                // Handle image path correctly
                const imagePath = selectedColorData.image_path;
                const imageUrl = imagePath.startsWith('/images/items/') || imagePath.startsWith('images/items/') 
                    ? imagePath 
                    : `images/items/${imagePath}`;
                
                // Create a new image to test if it loads successfully
                const testImage = new Image();
                testImage.onload = function() {
                    modalImage.src = imageUrl;
                    // Update the current modal product image for cart
                    window.currentModalProduct.image = imageUrl;
                };
                testImage.onerror = function() {
                    // If color-specific image fails, fall back to default
                    console.log(`Color-specific image not found: ${imageUrl}, keeping current image`);
                };
                testImage.src = imageUrl;
            }
        } else {
            // No specific image for this color, use default
            const modalImage = document.querySelector('#quantityModal .product-image img') || document.querySelector('#modalProductImage');
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
    
    // Remove modal-open class from body for z-index hierarchy
    document.body.classList.remove('modal-open');
    document.documentElement.classList.remove('modal-open');
    
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
            // Add visual feedback
            colorSelect.classList.add('validation-error');
            setTimeout(() => colorSelect.classList.remove('validation-error'), 3000);
            
            if (window.cart && window.cart.showErrorNotification) {
                window.cart.showErrorNotification('Please select a color before adding to cart.');
            } else if (window.cart && window.cart.showNotification) {
                window.cart.showNotification('Please select a color before adding to cart.');
            } else {
                showValidation('Please select a color before adding to cart.');
            }
            return;
        }
        
        // Check if there's enough color inventory available (considering what's already in cart)
        const selectedOption = colorSelect.options[colorSelect.selectedIndex];
        const colorData = window.currentModalProduct.availableColors.find(c => c.color_name === selectedColor);
        if (colorData) {
            const cartQuantity = typeof window.getCartQuantityForColor === 'function' ? 
                window.getCartQuantityForColor(window.currentModalProduct.id, selectedColor) : 0;
            const availableQuantity = Math.max(0, colorData.stock_level - cartQuantity);
            
            if (quantity > availableQuantity) {
                const errorMsg = availableQuantity === 0 ? 
                    'This color is currently out of stock.' :
                    `Only ${availableQuantity} of this color available (considering items already in your cart).`;
                
                if (window.cart && window.cart.showErrorNotification) {
                    window.cart.showErrorNotification(errorMsg);
                } else if (window.cart && window.cart.showNotification) {
                    window.cart.showNotification(errorMsg);
                } else {
                    showValidation(errorMsg);
                }
                return;
            }
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
            // Add visual feedback
            sizeSelect.classList.add('validation-error');
            setTimeout(() => sizeSelect.classList.remove('validation-error'), 3000);
            
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
        
        // Refresh modal options to show updated availability
        setTimeout(() => {
            if (typeof window.refreshModalOptions === 'function') {
                window.refreshModalOptions();
            }
        }, 100);
        
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

// NOTE: showGlobalPopup function is now handled by global-popup.js
// This duplicate function has been removed to prevent conflicts

// NOTE: positionPopup function is now handled by global-popup.js

// NOTE: hideGlobalPopup and hidePopupImmediate functions are now handled by global-popup.js

// NOTE: checkAndDisplaySalePrice function is now handled by sales-checker.js

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
            if (popup && popup.classList.contains('show') && !popup.contains(e.target) && !e.target.closest('.item-icon')) {
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

// Function to calculate how many of each color are already in the cart for a specific item
window.getCartQuantityForColor = function(itemSku, colorName) {
    if (!window.cart || !window.cart.items) return 0;
    
    return window.cart.items.reduce((total, cartItem) => {
        if (cartItem.sku === itemSku) {
            const cartColor = cartItem.color || null;
            
            // Match color (or both null)
            const colorMatch = (colorName === cartColor) || (colorName === null && cartColor === null);
            
            if (colorMatch) {
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

// Function to refresh modal options after cart changes
window.refreshModalOptions = function() {
    if (window.currentModalProduct && window.currentModalProduct.id) {
        // Refresh color dropdown if it exists
        const colorSelect = document.getElementById('colorSelect');
        if (colorSelect && window.currentModalProduct.availableColors) {
            setupColorDropdown(window.currentModalProduct.availableColors, window.currentModalProduct.selectedColor);
        }
        
        // Refresh size dropdown if it exists
        const sizeSelect = document.getElementById('sizeSelect');
        if (sizeSelect && (window.currentModalProduct.generalSizes || window.currentModalProduct.colorSpecificSizes)) {
            setupSizeDropdown(
                window.currentModalProduct.availableSizes,
                window.currentModalProduct.generalSizes,
                window.currentModalProduct.colorSpecificSizes,
                window.currentModalProduct.selectedSize
            );
        }
    }
};

// Initialize the global cart instance
if (typeof window !== 'undefined') {
    window.cart = new ShoppingCart();
    console.log('✅ WhimsicalFrog Shopping Cart initialized');
}

// Global convenience functions for cart status and cleanup
window.showCartStatus = function() {
    if (window.cart) {
        window.cart.showCurrentCartStatus();
    } else {
        console.warn('Cart not initialized');
    }
};

window.cleanupNotifications = function() {
    if (window.cart) {
        window.cart.manualCleanup();
    } else {
        console.warn('Cart not initialized');
    }
};

// Also expose the cart methods globally for easy debugging
window.cartDebug = {
    showStatus: () => window.cart && window.cart.showCurrentCartStatus(),
    cleanup: () => window.cart && window.cart.manualCleanup(),
    getItems: () => window.cart && window.cart.items,
    getTotal: () => window.cart && window.cart.getTotal(),
    getCount: () => window.cart && window.cart.getItemCount(),
    clear: () => window.cart && window.cart.clearCart()
};