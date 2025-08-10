// Global Sales Checker and Utility Functions
// This file provides centralized functionality for all pages

// Sales checking functions
async function checkItemSale(itemSku) {
    try {
        const response = await apiGet(`sales.php?action=get_active_sales&item_sku=${itemSku}`);
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

// Enhanced checkAndDisplaySalePrice function
async function checkAndDisplaySalePrice(item, priceElement, unitPriceElement = null, context = 'popup') {
    if (!item || !priceElement) return;
    
    try {
        const saleData = await checkItemSale(item.sku);
        
        if (saleData.isOnSale && saleData.discountPercentage) {
            const originalPrice = parseFloat(item.retailPrice || item.price);
            const validDiscountPercentage = parseFloat(saleData.discountPercentage);
            
            // Validate the discount percentage
            if (isNaN(validDiscountPercentage) || validDiscountPercentage <= 0) {
                console.error('Invalid discount percentage in sale data:', saleData.discountPercentage);
                // Fall back to regular price display
                const price = parseFloat(item.retailPrice || item.price);
                priceElement.textContent = `$${price.toFixed(2)}`;
                if (unitPriceElement) {
                    unitPriceElement.textContent = `$${price.toFixed(2)}`;
                }
                return;
            }
            
            const salePrice = calculateSalePrice(originalPrice, validDiscountPercentage);
            
            // Format sale price display
            const saleHTML = `
                <span class="u-text-decoration-line-through u-color-999 u-font-size-0-9em">$${originalPrice.toFixed(2)}</span>
                <span class="u-color-dc2626 u-font-weight-bold u-margin-left-5px">$${salePrice.toFixed(2)}</span>
                <span class="u-color-dc2626 u-font-size-0-8em u-margin-left-5px">(${Math.round(validDiscountPercentage)}% off)</span>
            `;
            
            priceElement.innerHTML = saleHTML;
            
            if (unitPriceElement) {
                unitPriceElement.innerHTML = saleHTML;
            }
            
            // Update item object with sale price for cart
            item.salePrice = salePrice;
            item.originalPrice = originalPrice;
            item.isOnSale = true;
            item.discountPercentage = validDiscountPercentage;
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
// showPopup function moved to js/global-popup.js for centralization

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
        
        // Use centralized image error handling
        if (typeof window.setupImageErrorHandling === 'function') {
            window.setupImageErrorHandling(popupImage, item.sku);
        } else {
            // Fallback if central functions not loaded yet
            popupImage.onerror = function() {
                this.src = 'images/items/placeholder.webp';
                this.onerror = null;
            };
        }
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
    
    // Show popup temporarily to get actual dimensions using CSS classes
    popup.classList.add('measuring');
    const popupRect = popup.getBoundingClientRect();
    const popupWidth = popupRect.width;
    const popupHeight = popupRect.height;
    popup.classList.remove('measuring');
    
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
    
    // Apply final positioning using CSS custom properties
    popup.style.setProperty('-popup-left', left + 'px');
    popup.style.setProperty('-popup-top', top + 'px');
    popup.classList.add('positioned', 'visible');
    
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
            if (saleData && saleData.isOnSale && saleData.discountPercentage) {
                addSaleBadgeToCardWithDiscount(itemCard, saleData.discountPercentage);
            }
        });
        return;
    } else {
        // Called with (itemCard, discountPercentage) pattern
        itemCard = skuOrCard;
        discountPercentage = discountPercentageOrCard;
    }
    
    // Only proceed if we have valid data
    if (itemCard && discountPercentage) {
        addSaleBadgeToCardWithDiscount(itemCard, discountPercentage);
    }
}

function addSaleBadgeToCardWithDiscount(itemCard, discountPercentage) {
    if (!itemCard || !itemCard.querySelector) {
        console.error('Invalid item card element provided to addSaleBadgeToCard');
        return;
    }
    
    // Validate discount percentage
    const validDiscountPercentage = parseFloat(discountPercentage);
    if (isNaN(validDiscountPercentage) || validDiscountPercentage <= 0) {
        console.error('Invalid discount percentage provided to addSaleBadgeToCardWithDiscount:', discountPercentage);
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
        <span class="sale-percentage">${Math.round(validDiscountPercentage)}% OFF</span>
    `;
    
    // No need to set styles - using CSS classes instead
    itemCard.appendChild(saleBadge);
}

// Shop page functionality
document.addEventListener('DOMContentLoaded', function() {
    // Only run shop-specific code on shop page
    // Updated to support clean URLs instead of legacy query parameters
    const currentPage = window.WF_PAGE_INFO?.page || (
        window.location.pathname === '/' ? 'landing' : 
        window.location.pathname.replace(/^\//, '').split('/')[0]
    ) || 'landing';
    if (currentPage === 'shop' || window.location.pathname.includes('/shop')) {
        // Check for sales on all items
        const itemCards = document.querySelectorAll('[data-sku]');
        
        itemCards.forEach(async (card) => {
            const sku = card.getAttribute('data-sku');
            if (sku) {
                const saleData = await checkItemSale(sku);
                if (saleData && saleData.isOnSale && saleData.discountPercentage) {
                    addSaleBadgeToCard(card, saleData.discountPercentage);
                }
            }
        });
    }
    
    // Room page functionality - Set up hover listeners
    // Updated to support clean URLs instead of legacy query parameters
    if (currentPage.startsWith('room') || window.location.pathname.includes('/room')) {
        // Wait a bit for room elements to load, then set up hover
        setTimeout(setupRoomHover, 500);
    }
});


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
// Note: showPopup and hidePopup are provided by global-popup.js
// window.showPopup and window.hidePopup are set up there 