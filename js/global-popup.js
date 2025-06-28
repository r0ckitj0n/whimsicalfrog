/**
 * Global Popup System
 * Provides unified popup functionality across all pages
 */

// Global popup state
window.globalPopupState = {
    currentProduct: null,
    popupTimeout: null,
    popupOpen: false,
    isShowingPopup: false,
    lastShowTime: 0
};

/**
 * Show product popup
 * @param {HTMLElement} element - The element that triggered the popup
 * @param {Object} product - Product data object
 */
window.showGlobalPopup = function(element, product) {
    const now = Date.now();
    
    // Debounce rapid calls
    if (now - window.globalPopupState.lastShowTime < 50) {
        return;
    }
    window.globalPopupState.lastShowTime = now;
    
    // Prevent rapid re-triggering of same popup
    if (window.globalPopupState.currentProduct && 
        window.globalPopupState.currentProduct.sku === product.sku && 
        window.globalPopupState.isShowingPopup) {
        clearTimeout(window.globalPopupState.popupTimeout);
        return;
    }
    
    clearTimeout(window.globalPopupState.popupTimeout);
    window.globalPopupState.currentProduct = product;
    window.globalPopupState.isShowingPopup = true;
    window.globalPopupState.popupOpen = true;

    const popup = document.getElementById('productPopup');
    if (!popup) {
        console.error('Global popup element not found');
        return;
    }

    // Update popup content
    updateGlobalPopupContent(popup, product);
    
    // Position popup relative to the element
    positionGlobalPopup(popup, element);
    
    // Show popup with transition
    popup.classList.add('show');
    
    // Set up event handlers
    setupGlobalPopupHandlers(popup, product);
};

/**
 * Hide popup with delay for mouse movement
 */
window.hideGlobalPopup = function() {
    clearTimeout(window.globalPopupState.popupTimeout);
    
    window.globalPopupState.popupTimeout = setTimeout(() => {
        hideGlobalPopupImmediate();
    }, 150);
};

/**
 * Hide popup immediately
 */
window.hideGlobalPopupImmediate = function() {
    const popup = document.getElementById('productPopup');
    if (popup && popup.classList.contains('show')) {
        popup.classList.remove('show');
        window.globalPopupState.currentProduct = null;
        window.globalPopupState.popupOpen = false;
        window.globalPopupState.isShowingPopup = false;
    }
};

/**
 * Update popup content with product data
 * @param {HTMLElement} popup - The popup element
 * @param {Object} product - Product data object
 */
function updateGlobalPopupContent(popup, product) {
    const popupImage = popup.querySelector('#popupImage');
    const popupCategory = popup.querySelector('#popupCategory');
    const popupTitle = popup.querySelector('#popupTitle');
    const popupSku = popup.querySelector('#popupSku');
    const popupStock = popup.querySelector('#popupStock');
    const popupDescription = popup.querySelector('#popupDescription');
    const popupCurrentPrice = popup.querySelector('#popupCurrentPrice');
    const popupOriginalPrice = popup.querySelector('#popupOriginalPrice');
    const popupSavings = popup.querySelector('#popupSavings');
    const popupSaleBadge = popup.querySelector('#popupSaleBadge');
    const popupStockBadge = popup.querySelector('#popupStockBadge');

    // Get the image URL with fallback
    const imageUrl = product.primaryImageUrl || product.imageUrl || `images/items/${product.sku}A.png`;

    // Update image
    if (popupImage) {
        popupImage.src = imageUrl;
        popupImage.alt = product.name || product.productName || 'Product';
        popupImage.onerror = function() {
            this.src = 'images/items/placeholder.webp';
            this.onerror = null;
        };
    }

    // Update text content
    if (popupCategory) {
        popupCategory.textContent = product.category || 'Product';
    }
    
    if (popupTitle) {
        popupTitle.textContent = product.name || product.productName || 'Product Name';
    }
    
    if (popupSku) {
        popupSku.textContent = `SKU: ${product.sku}`;
    }
    
    // Update stock information
    if (popupStock) {
        const stockLevel = product.stockLevel || product.stock || 0;
        popupStock.className = 'popup-stock-info';
        
        if (stockLevel > 0) {
            if (stockLevel <= 5) {
                popupStock.className += ' limited-stock';
                popupStock.textContent = `Only ${stockLevel} left`;
                if (popupStockBadge) {
                    popupStockBadge.classList.remove('hidden');
                }
            } else {
                popupStock.className += ' in-stock';
                popupStock.textContent = `${stockLevel} in stock`;
                if (popupStockBadge) {
                    popupStockBadge.classList.add('hidden');
                }
            }
        } else {
            popupStock.className += ' out-of-stock';
            popupStock.textContent = 'Out of stock';
            if (popupStockBadge) {
                popupStockBadge.classList.add('hidden');
            }
        }
    }
    
    if (popupDescription) {
        popupDescription.textContent = product.description || product.productDescription || 'No description available';
    }

    // Handle pricing and sales
    const basePrice = parseFloat(product.retailPrice || product.price || 0);
    
    if (popupCurrentPrice) {
        popupCurrentPrice.textContent = `$${basePrice.toFixed(2)}`;
    }
    
    // Check for sales if sales checker is available
    if (typeof window.checkAndDisplaySalePrice === 'function') {
        window.checkAndDisplaySalePrice(product, popupCurrentPrice, null, 'popup').then(() => {
            // Sales check completed
        });
    }
    
    // Reset sale elements
    if (popupOriginalPrice) popupOriginalPrice.classList.add('hidden');
    if (popupSavings) popupSavings.classList.add('hidden');
    if (popupSaleBadge) popupSaleBadge.classList.add('hidden');
}

/**
 * Position popup relative to trigger element
 * @param {HTMLElement} popup - The popup element
 * @param {HTMLElement} element - The trigger element
 */
function positionGlobalPopup(popup, element) {
    if (!popup || !element) return;
    
    // Make popup visible but transparent to measure dimensions
    popup.style.opacity = '0';
    popup.style.display = 'block';
    
    // Get element and popup dimensions
    const elementRect = element.getBoundingClientRect();
    const popupRect = popup.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Calculate position - try to position to the right of element first
    let left = elementRect.right + 10;
    let top = elementRect.top + (elementRect.height / 2) - (popupRect.height / 2);
    
    // Adjust for viewport boundaries - horizontal
    if (left + popupRect.width > viewportWidth - 10) {
        // Try left side
        left = elementRect.left - popupRect.width - 10;
        if (left < 10) {
            // Center horizontally if neither side fits
            left = elementRect.left + (elementRect.width / 2) - (popupRect.width / 2);
            if (left < 10) left = 10;
            if (left + popupRect.width > viewportWidth - 10) {
                left = viewportWidth - popupRect.width - 10;
            }
            // Position below element
            top = elementRect.bottom + 10;
        }
    }
    
    // Adjust for viewport boundaries - vertical
    if (top < 10) {
        top = 10;
    } else if (top + popupRect.height > viewportHeight - 10) {
        top = viewportHeight - popupRect.height - 10;
        if (top < 10) {
            top = 10;
        }
    }
    
    // Apply position and restore visibility
    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
    popup.style.opacity = '';
    popup.style.display = '';
}

/**
 * Set up event handlers for popup
 * @param {HTMLElement} popup - The popup element
 * @param {Object} product - Product data object
 */
function setupGlobalPopupHandlers(popup, product) {
    const popupAddBtn = popup.querySelector('#popupAddBtn');
    const popupDetailsBtn = popup.querySelector('#popupDetailsBtn');
    const popupContent = popup.querySelector('.popup-content');

    // Add to cart button
    if (popupAddBtn) {
        popupAddBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideGlobalPopupImmediate();
            
            // Use the global modal system
            if (typeof window.showGlobalItemModal === 'function') {
                window.showGlobalItemModal(product.sku);
            } else {
                console.error('Global modal system not available');
            }
        };
        
        // Disable if out of stock
        const stockLevel = product.stockLevel || product.stock || 0;
        if (stockLevel <= 0) {
            popupAddBtn.disabled = true;
            popupAddBtn.textContent = 'Out of Stock';
        } else {
            popupAddBtn.disabled = false;
            popupAddBtn.innerHTML = `
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-1.5 6M7 13h10m-10 0v6a1 1 0 001 1h8a1 1 0 001-1v-6m-9 0h9"></path>
                </svg>
                Add to Cart
            `;
        }
    }

    // Details button
    if (popupDetailsBtn) {
        popupDetailsBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideGlobalPopupImmediate();
            
            if (typeof window.showProductDetails === 'function') {
                window.showProductDetails(product.sku);
            } else {
                console.log('Product details function not available for:', product.sku);
            }
        };
    }

    // Click on popup content for details (excluding buttons)
    if (popupContent) {
        popupContent.onclick = function(e) {
            // Don't trigger if clicking on buttons
            if (e.target.closest('.popup-add-btn, .popup-details-btn')) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            hideGlobalPopupImmediate();
            
            if (typeof window.showProductDetails === 'function') {
                window.showProductDetails(product.sku);
            } else {
                console.log('Product details function not available for:', product.sku);
            }
        };
    }
}

/**
 * Initialize global popup system
 */
function initializeGlobalPopup() {
    const popup = document.getElementById('productPopup');
    if (!popup) {
        console.warn('Global popup element not found');
        return;
    }

    // Keep popup visible when hovering over it
    popup.addEventListener('mouseenter', () => {
        clearTimeout(window.globalPopupState.popupTimeout);
        window.globalPopupState.isShowingPopup = true;
        window.globalPopupState.popupOpen = true;
    });

    popup.addEventListener('mouseleave', () => {
        window.hideGlobalPopup();
    });

    // Close popup when clicking outside
    document.addEventListener('click', function(e) {
        if (popup.classList.contains('show') && 
            !popup.contains(e.target) && 
            !e.target.closest('.product-icon')) {
            hideGlobalPopupImmediate();
        }
    });
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeGlobalPopup);
} else {
    initializeGlobalPopup();
}

// Backward compatibility aliases
window.showPopup = window.showGlobalPopup;
window.hidePopup = window.hideGlobalPopup;
window.hidePopupImmediate = window.hideGlobalPopupImmediate; 