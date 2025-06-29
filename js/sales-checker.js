// Global Sales Checker and Utility Functions
// This file provides centralized functionality for all pages

// Sales checking functions
async function checkItemSale(itemSku) {
    try {
        const response = await fetch(`/api/sales.php?action=get_active_sales&item_sku=${itemSku}`);
        const data = await response.json();
        
        if (data.success && data.sale) {
            return {
                isOnSale: true,
                discountPercentage: parseFloat(data.sale.discount_percentage),
                salePrice: null, // Will be calculated based on original price
                originalPrice: null // Will be set by calling function
            };
        }
        return { isOnSale: false };
    } catch (error) {
        console.log('Error checking sale for', itemSku, error);
        return { isOnSale: false };
    }
}

function calculateSalePrice(originalPrice, discountPercentage) {
    return originalPrice * (1 - discountPercentage / 100);
}

// Global function to update total in modal
window.updateModalTotal = function() {
    const quantityInput = document.getElementById('quantityInput');
    const modalQuantity = document.getElementById('modalQuantity');
    const modalTotal = document.getElementById('modalTotal');
    
    if (!quantityInput || !modalQuantity || !modalTotal || !window.currentModalItem) {
        return;
    }
    
    const quantity = parseInt(quantityInput.value) || 1;
    const price = window.currentModalItem.salePrice || parseFloat(window.currentModalItem.retailPrice || window.currentModalItem.price);
    const total = quantity * price;
    
    modalQuantity.textContent = quantity;
    modalTotal.textContent = '$' + total.toFixed(2);
};

// Global function to close cart modal
window.closeCartModal = function() {
    const quantityModal = document.getElementById('quantityModal');
    const quantityInput = document.getElementById('quantityInput');
    
    if (quantityModal) {
        quantityModal.classList.add('hidden');
    }
    if (quantityInput) {
        quantityInput.value = 1;
    }
    
    window.currentModalItem = null;
};

// Enhanced checkAndDisplaySalePrice function
async function checkAndDisplaySalePrice(item, priceElement, unitPriceElement = null, context = 'popup') {
    if (!item || !priceElement) return;
    
    try {
        const saleData = await checkItemSale(item.sku);
        
        if (saleData.isOnSale) {
            const originalPrice = parseFloat(item.retailPrice || item.price);
            const salePrice = calculateSalePrice(originalPrice, saleData.discountPercentage);
            
            // Format sale price display
            const saleHTML = `
                <span style="text-decoration: line-through; color: #999; font-size: 0.9em;">$${originalPrice.toFixed(2)}</span>
                <span style="color: #dc2626; font-weight: bold; margin-left: 5px;">$${salePrice.toFixed(2)}</span>
                <span style="color: #dc2626; font-size: 0.8em; margin-left: 5px;">(${saleData.discountPercentage}% off)</span>
            `;
            
            priceElement.innerHTML = saleHTML;
            
            if (unitPriceElement) {
                unitPriceElement.innerHTML = saleHTML;
            }
            
            // Update item object with sale price for cart
            item.salePrice = salePrice;
            item.originalPrice = originalPrice;
            item.isOnSale = true;
            item.discountPercentage = saleData.discountPercentage;
        } else {
            // No sale, display regular price
            const price = parseFloat(item.retailPrice || item.price);
            priceElement.textContent = `$${price.toFixed(2)}`;
            
            if (unitPriceElement) {
                unitPriceElement.textContent = `$${price.toFixed(2)}`;
            }
            
            item.isOnSale = false;
        }
    } catch (error) {
        console.log('No sale data available for', item.sku);
        // Display regular price on error
        const price = parseFloat(item.retailPrice || item.price);
        priceElement.textContent = `$${price.toFixed(2)}`;
        
        if (unitPriceElement) {
            unitPriceElement.textContent = `$${price.toFixed(2)}`;
        }
    }
}

// SIMPLIFIED HOVER SYSTEM - Standard and Reliable
let hoverTimeout = null;
let hideTimeout = null;

// Popup functions now use the global system
function showPopup(element, item) {
    // Delegate to global popup system
    window.showGlobalPopup(element, item);
}

function hidePopup() {
    if (typeof window.hideGlobalPopup === 'function') {
        window.hideGlobalPopup();
    }
}

// Keep popup visible when hovering over it
function keepPopupVisible() {
    if (hideTimeout) clearTimeout(hideTimeout);
}

// Update popup content
function updatePopupContent(popup, item) {
    const popupImage = popup.querySelector('.popup-image');
    const popupName = popup.querySelector('.popup-name');
    const popupPrice = popup.querySelector('.popup-price');
    const popupDescription = popup.querySelector('.popup-description');
    
    if (popupImage) {
        // Use actual image data from item object with fallbacks
        let imageSrc = 'images/items/placeholder.webp';
        
        // Try to get the best available image
        if (item.primaryImageUrl) {
            imageSrc = item.primaryImageUrl;
        }
        // Fallback to standard image property
        else if (item.image) {
            imageSrc = item.image;
        }
        else if (item.imageUrl) {
            imageSrc = item.imageUrl;
        }
        // Generate SKU-based path
        else if (item.sku) {
            // Try WebP first, then PNG
            imageSrc = `images/items/${item.sku}A.webp`;
        }
        
        popupImage.src = imageSrc;
        popupImage.onerror = function() {
            // If WebP fails and we have a SKU, try PNG
            if (item.sku && this.src.includes('.webp')) {
                this.src = `images/items/${item.sku}A.png`;
                return;
            }
            
            // If PNG fails, try the base SKU without A
            else if (item.sku && this.src.includes('A.png')) {
                this.src = `images/items/${item.sku}.webp`;
                return;
            }
            
            // Final fallback
            this.src = 'images/items/placeholder.webp';
        };
    }
    
    // Update item name
    if (popupName) {
        popupName.textContent = item.name || item.itemName || 'Item';
    }
    
    // Update price with sale checking
    checkAndDisplaySalePrice(item, popupPrice);
    
    // Update description
    if (popupDescription) {
        popupDescription.textContent = item.description || '';
    }
    
    // Update "View Details" button to open item modal
    const viewDetailsBtn = popup.querySelector('.btn-secondary');
    if (viewDetailsBtn) {
        viewDetailsBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideGlobalPopup();
            
            // Open item modal instead of item details
            window.showGlobalItemModal(item.sku);
        };
    }
    
    // Update "Add to Cart" button for quick add
    const addToCartBtn = popup.querySelector('.btn-primary');
    if (addToCartBtn) {
        const sku = item.sku;
        const name = item.name || item.itemName;
        const price = parseFloat(item.retailPrice || item.price || 0);
        const image = item.primaryImageUrl || item.image || item.imageUrl || `images/items/${item.sku}A.png`;
        
        addToCartBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Hide popup
            hideGlobalPopup();
            
            // Add to cart with quantity 1
            if (window.cart && typeof window.cart.addItem === 'function') {
                window.cart.addItem({
                    sku: sku,
                    name: name,
                    price: price,
                    image: image
                }, 1);
            }
        };
    }
    
    // Update "View Details" link to show item details
    const viewDetailsLink = popup.querySelector('a[href*="javascript:"]');
    if (viewDetailsLink) {
        viewDetailsLink.onclick = function(e) {
            e.preventDefault();
            // Hide popup and show item details
            hideGlobalPopup();
            
            if (typeof window.showItemDetails === 'function') {
                window.showItemDetails(item.sku);
            } else {
                console.log('Item details function not available');
            }
        };
    }
}

// Improved popup positioning
function positionPopupSimple(element, popup) {
    const rect = element.getBoundingClientRect();
    
    // Show popup temporarily to get actual dimensions
    popup.style.display = 'block';
    popup.style.visibility = 'hidden'; // Hide visually but allow measurement
    const popupRect = popup.getBoundingClientRect();
    const popupWidth = popupRect.width;
    const popupHeight = popupRect.height;
    popup.style.visibility = 'visible'; // Make visible again
    
    // Get viewport dimensions with safety margins
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const margin = 10; // Safety margin from edges
    
    // Calculate preferred position (to the right of element)
    let left = rect.right + margin;
    let top = rect.top - 50; // Offset up slightly to center on element
    
    // Horizontal positioning logic
    if (left + popupWidth + margin > viewportWidth) {
        // Try positioning to the left of element
        left = rect.left - popupWidth - margin;
        
        // If still doesn't fit, position at right edge of viewport
        if (left < margin) {
            left = viewportWidth - popupWidth - margin;
        }
    }
    
    // Ensure popup doesn't go off left edge
    if (left < margin) {
        left = margin;
    }
    
    // Vertical positioning logic
    // First, try to center the popup vertically on the element
    const elementCenter = rect.top + (rect.height / 2);
    top = elementCenter - (popupHeight / 2);
    
    // If popup would go off top of screen, move it down
    if (top < margin) {
        top = margin;
    }
    
    // If popup would go off bottom of screen, move it up
    if (top + popupHeight + margin > viewportHeight) {
        top = viewportHeight - popupHeight - margin;
        
        // If still doesn't fit (popup is taller than viewport), position at top with margin
        if (top < margin) {
            top = margin;
        }
    }
    
    // Apply final positioning
    popup.style.position = 'fixed';
    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
    popup.style.zIndex = '1000';
    
    console.log(`Popup positioned at: left=${left}, top=${top}, width=${popupWidth}, height=${popupHeight}`);
}

// Function to add sale badges to item cards (for shop page)
function addSaleBadgeToCard(skuOrCard, discountPercentageOrCard) {
    let itemCard, discountPercentage;
    
    if (typeof skuOrCard === 'string') {
        // Called with (sku, itemCard) pattern
        const sku = skuOrCard;
        itemCard = discountPercentageOrCard;
        // Get discount from sale data
        checkItemSale(sku).then(saleData => {
            if (saleData) {
                addSaleBadgeToCardWithDiscount(itemCard, saleData.discountPercentage);
            }
        });
        return;
    } else {
        // Called with (itemCard, discountPercentage) pattern
        itemCard = skuOrCard;
        discountPercentage = discountPercentageOrCard;
    }
    
    addSaleBadgeToCardWithDiscount(itemCard, discountPercentage);
}

function addSaleBadgeToCardWithDiscount(itemCard, discountPercentage) {
    if (!itemCard || !itemCard.querySelector) {
        console.error('Invalid item card element provided to addSaleBadgeToCard');
        return;
    }
    
    // Remove existing sale badge if present
    const existingBadge = itemCard.querySelector('.sale-badge');
    if (existingBadge) {
        existingBadge.remove();
    }
    
    // Create new sale badge
    const saleBadge = document.createElement('div');
    saleBadge.className = 'sale-badge';
    saleBadge.innerHTML = `
        <span class="sale-text">SALE</span>
        <span class="sale-percentage">${Math.round(discountPercentage)}% OFF</span>
    `;
    
    // Add sale badge styles
    saleBadge.style.cssText = `
        position: absolute;
        top: 8px;
        right: 8px;
        background: linear-gradient(135deg, #ff4444, #cc0000);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: bold;
        z-index: 10;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        text-align: center;
        line-height: 1.2;
    `;
    
    // Ensure item card has relative positioning
    itemCard.style.position = 'relative';
    itemCard.appendChild(saleBadge);
}

// Shop page functionality
document.addEventListener('DOMContentLoaded', function() {
    // Only run shop-specific code on shop page
    if (window.location.search.includes('page=shop')) {
        // Check for sales on all items
        const itemCards = document.querySelectorAll('[data-sku]');
        
        itemCards.forEach(async (card) => {
            const sku = card.getAttribute('data-sku');
            if (sku) {
                const saleData = await checkItemSale(sku);
                if (saleData) {
                    addSaleBadgeToCard(card, saleData.discountPercentage);
                }
            }
        });
    }
    
    // Room page functionality - Set up hover listeners
    if (window.location.search.includes('page=room')) {
        // Wait a bit for room elements to load, then set up hover
        setTimeout(setupRoomHover, 500);
    }
});

// Keep popup visible when hovering over it
function keepPopupVisible() {
    clearTimeout(hideTimeout);
}

// Setup hover listeners for room pages
function setupRoomHover() {
    const popup = document.getElementById('productPopup');
    if (!popup) return;
    
    // Set up hover listeners on popup to keep it visible
    popup.addEventListener('mouseenter', keepPopupVisible);
    popup.addEventListener('mouseleave', hidePopup);
    
    console.log('Room hover system initialized with standard settings');
}

// Make functions globally available
window.checkItemSale = checkItemSale;
window.calculateSalePrice = calculateSalePrice;
window.checkAndDisplaySalePrice = checkAndDisplaySalePrice;
window.addSaleBadgeToCard = addSaleBadgeToCard;
window.showPopup = showPopup;
window.hidePopup = hidePopup;
window.globalShowPopup = showPopup;
window.globalHidePopup = hidePopup; 