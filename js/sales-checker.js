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
    
    if (!quantityInput || !modalQuantity || !modalTotal || !window.currentModalProduct) {
        return;
    }
    
    const quantity = parseInt(quantityInput.value) || 1;
    const price = window.currentModalProduct.salePrice || parseFloat(window.currentModalProduct.retailPrice || window.currentModalProduct.price);
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
    
    window.currentModalProduct = null;
};

// Enhanced checkAndDisplaySalePrice function
async function checkAndDisplaySalePrice(product, priceElement, unitPriceElement = null, context = 'popup') {
    if (!product || !priceElement) return;
    
    try {
        const saleData = await checkItemSale(product.sku);
        
        if (saleData.isOnSale) {
            const originalPrice = parseFloat(product.retailPrice || product.price);
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
            
            // Update product object with sale price for cart
            product.salePrice = salePrice;
            product.originalPrice = originalPrice;
            product.isOnSale = true;
            product.discountPercentage = saleData.discountPercentage;
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
}

// SIMPLIFIED HOVER SYSTEM - Standard and Reliable
let hoverTimeout = null;
let hideTimeout = null;

// Popup functions now use the global system
function showPopup(element, product) {
    if (typeof window.showGlobalPopup === 'function') {
        window.showGlobalPopup(element, product);
    } else {
        console.error('Global popup system not available');
    }
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
function updatePopupContent(popup, product) {
    const popupImage = popup.querySelector('.popup-image');
    const popupName = popup.querySelector('.popup-name');
    const popupPrice = popup.querySelector('.popup-price');
    const popupDescription = popup.querySelector('.popup-description');
    
    if (popupImage) {
        // Use actual image data from product object with fallbacks
        let imageSrc = '';
        
        // Priority 1: Use primary image path if available
        if (product.primaryImageUrl) {
            imageSrc = product.primaryImageUrl;
        }
        // Priority 2: Use image or imageUrl field if available
        else if (product.image) {
            imageSrc = product.image;
        }
        else if (product.imageUrl) {
            imageSrc = product.imageUrl;
        }
        // Priority 3: Try common SKU-based patterns
        else if (product.sku) {
            // Try the most common formats first
            imageSrc = `images/items/${product.sku}A.webp`;
        }
        // Priority 4: Fallback to placeholder
        else {
            imageSrc = 'images/items/placeholder.png';
        }
        
        popupImage.src = imageSrc;
        popupImage.onerror = function() {
            // If primary image fails, try alternative formats
            if (product.sku && this.src.includes('.webp')) {
                this.src = `images/items/${product.sku}A.png`;
                this.onerror = function() {
                    this.src = 'images/items/placeholder.png';
                    this.onerror = null;
                };
            } else if (product.sku && this.src.includes('A.png')) {
                this.src = `images/items/${product.sku}.webp`;
                this.onerror = function() {
                    this.src = 'images/items/placeholder.png';
                    this.onerror = null;
                };
            } else {
                this.src = 'images/items/placeholder.png';
                this.onerror = null;
            }
        };
    }
    
    if (popupName) {
        popupName.textContent = product.name || product.productName || 'Product';
    }
    
    if (popupPrice) {
        checkAndDisplaySalePrice(product, popupPrice);
    }
    
    if (popupDescription) {
        popupDescription.textContent = product.description || '';
    }
    
    // Set up Add to Cart button
    const popupAddBtn = popup.querySelector('.popup-add-btn');
    if (popupAddBtn) {
        popupAddBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Hide popup first
            hidePopup();
            
            // Use the global modal system
            if (typeof window.showGlobalItemModal === 'function') {
                window.showGlobalItemModal(product.sku);
            } else {
                console.error('Global modal system not available, falling back to quantity modal');
                
                // Fallback to old system
                const sku = product.sku;
                const name = product.name || product.productName;
                const price = parseFloat(product.retailPrice || product.price || 0);
                const image = product.primaryImageUrl || product.image || product.imageUrl || `images/items/${product.sku}A.png`;
                
                if (typeof window.addToCartWithModal === 'function') {
                    window.addToCartWithModal(sku, name, price, image);
                } else {
                    console.error('No modal system available');
                }
            }
        };
    }
    
    // Set up click-to-view-details on popup content
    const popupContent = popup.querySelector('.popup-content');
    if (popupContent) {
        popupContent.onclick = function(e) {
            // Don't trigger if clicking the Add to Cart button
            if (e.target.classList.contains('popup-add-btn')) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            // Hide popup and show product details
            hidePopup();
            
            if (typeof window.showProductDetails === 'function') {
                window.showProductDetails(product.sku);
            } else {
                console.log('Product details function not available');
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

// Function to add sale badges to product cards (for shop page)
function addSaleBadgeToCard(skuOrCard, discountPercentageOrCard) {
    let productCard, discountPercentage;
    
    // Handle different parameter patterns
    if (typeof skuOrCard === 'string') {
        // Called with (sku, productCard) pattern
        const sku = skuOrCard;
        productCard = discountPercentageOrCard;
        
        // We need to get the discount percentage by checking the sale
        checkItemSale(sku).then(saleData => {
            if (saleData.isOnSale) {
                addSaleBadgeToCardWithDiscount(productCard, saleData.discountPercentage);
            }
        }).catch(error => {
            console.log('Error checking sale for badge:', error);
        });
        return;
    } else {
        // Called with (productCard, discountPercentage) pattern
        productCard = skuOrCard;
        discountPercentage = discountPercentageOrCard;
    }
    
    addSaleBadgeToCardWithDiscount(productCard, discountPercentage);
}

// Helper function to actually add the badge with discount percentage
function addSaleBadgeToCardWithDiscount(productCard, discountPercentage) {
    if (!productCard || !productCard.querySelector) {
        console.error('Invalid product card element provided to addSaleBadgeToCard');
        return;
    }
    
    // Remove existing sale badge if any
    const existingBadge = productCard.querySelector('.sale-badge');
    if (existingBadge) {
        existingBadge.remove();
    }
    
    // Create sale badge
    const saleBadge = document.createElement('div');
    saleBadge.className = 'sale-badge';
    saleBadge.style.cssText = `
        position: absolute;
        top: 10px;
        right: 10px;
        background: #dc2626;
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: bold;
        z-index: 10;
    `;
    saleBadge.textContent = `-${discountPercentage}%`;
    
    // Add badge to card
    productCard.style.position = 'relative';
    productCard.appendChild(saleBadge);
}

// Shop page functionality
document.addEventListener('DOMContentLoaded', function() {
    // Only run shop-specific code on shop page
    if (window.location.search.includes('page=shop')) {
        // Check for sales on all products
        const productCards = document.querySelectorAll('[data-sku]');
        
        productCards.forEach(async (card) => {
            const sku = card.dataset.sku;
            const originalPriceElement = card.querySelector('[data-original-price]');
            
            if (sku && originalPriceElement) {
                try {
                    const saleData = await checkItemSale(sku);
                    
                    if (saleData.isOnSale) {
                        const originalPrice = parseFloat(originalPriceElement.dataset.originalPrice);
                        const salePrice = calculateSalePrice(originalPrice, saleData.discountPercentage);
                        
                        // Update price display
                        originalPriceElement.innerHTML = `
                            <span style="text-decoration: line-through; color: #999;">$${originalPrice.toFixed(2)}</span>
                            <span style="color: #dc2626; font-weight: bold; margin-left: 5px;">$${salePrice.toFixed(2)}</span>
                        `;
                        
                        // Add sale badge
                        addSaleBadgeToCard(card, saleData.discountPercentage);
                    }
                } catch (error) {
                    console.log('Error checking sale for', sku, error);
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