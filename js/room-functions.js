/**
 * Centralized Room Functions
 * Shared functionality for all room pages to eliminate code duplication
 */

// Global room state variables
window.roomState = {
    currentProduct: null,
    popupTimeout: null,
    popupOpen: false,
    isShowingPopup: false,
    lastShowTime: 0,
    roomNumber: null,
    roomType: null
};

/**
 * Initialize room functionality
 * Call this from each room page with room-specific data
 */
window.initializeRoom = function(roomNumber, roomType) {
    window.roomState.roomNumber = roomNumber;
    window.roomState.roomType = roomType;
    
    // Initialize global cart modal event listeners
    if (typeof window.initializeModalEventListeners === 'function') {
        window.initializeModalEventListeners();
    }
    
    // Set up document click listener for popup closing
    document.addEventListener('click', function(e) {
        const popup = document.getElementById('productPopup');
        
        // Close popup if it's open and click is outside it
        if (popup && popup.classList.contains('show') && !popup.contains(e.target) && !e.target.closest('.product-icon')) {
            hidePopupImmediate();
        }
    });
    
    console.log(`Room ${roomNumber} (${roomType}) initialized with centralized functions`);
};

/**
 * Universal popup system for all rooms
 */
window.showPopup = function(element, product) {
    const now = Date.now();
    
    // Reduce debounce time for better responsiveness
    if (now - window.roomState.lastShowTime < 50) {
        return;
    }
    window.roomState.lastShowTime = now;
    
    // Prevent rapid re-triggering of same popup (anti-flashing protection)
    if (window.roomState.currentProduct && window.roomState.currentProduct.sku === product.sku && window.roomState.isShowingPopup) {
        clearTimeout(window.roomState.popupTimeout);
        return;
    }
    
    clearTimeout(window.roomState.popupTimeout);
    window.roomState.currentProduct = product;
    window.roomState.isShowingPopup = true;
    window.roomState.popupOpen = true;

    const popup = document.getElementById('productPopup');
    const popupImage = document.getElementById('popupImage');
    const popupCategory = document.getElementById('popupCategory');
    const popupTitle = document.getElementById('popupTitle');
    const popupDescription = document.getElementById('popupDescription');
    const popupPrice = document.getElementById('popupPrice');
    const popupAddBtn = document.getElementById('popupAddBtn');

    if (!popup) {
        console.error('Product popup not found on this page');
        return;
    }

    // Get the image URL - use SKU-based system with fallback
    const imageUrl = product.primaryImageUrl || `images/items/${product.sku}A.png`;

    // Populate popup content
    if (popupImage) {
        popupImage.src = imageUrl;
        popupImage.onerror = function() {
            this.src = 'images/items/placeholder.png';
            this.onerror = null;
        };
    }
    
    if (popupCategory) popupCategory.textContent = product.category || 'Category';
    if (popupTitle) popupTitle.textContent = product.name || product.productName || 'Product';
    if (popupDescription) {
        const description = product.description || product.productDescription || 'No description available';
        popupDescription.textContent = description;
    }

    // Handle pricing with sales integration
    if (popupPrice && typeof window.checkAndDisplaySalePrice === 'function') {
        window.checkAndDisplaySalePrice(product, popupPrice, null, 'popup');
    } else if (popupPrice) {
        const price = parseFloat(product.retailPrice || product.price || 0);
        popupPrice.textContent = '$' + price.toFixed(2);
    }

    // Set up add to cart button
    if (popupAddBtn) {
        popupAddBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            hidePopupImmediate();
            
            if (typeof window.addToCartWithModal === 'function') {
                const sku = product.sku;
                const name = product.name || product.productName;
                const price = parseFloat(product.retailPrice || product.price);
                const image = imageUrl;
                
                window.addToCartWithModal(sku, name, price, image);
            } else {
                console.error('addToCartWithModal function not available');
            }
        };
    }

    // Position popup relative to the clicked element
    positionPopup(popup, element);
    
    // Show popup with transition
    popup.classList.add('show');
};

/**
 * Hide popup with delay for mouse movement
 */
window.hidePopup = function() {
    // Clear any existing timeout
    clearTimeout(window.roomState.popupTimeout);
    
    // Add a small delay before hiding to allow moving mouse to popup
    window.roomState.popupTimeout = setTimeout(() => {
        hidePopupImmediate();
    }, 150); // Reduced delay for better responsiveness
};

/**
 * Hide popup immediately
 */
window.hidePopupImmediate = function() {
    const popup = document.getElementById('productPopup');
    if (popup && popup.classList.contains('show')) {
        popup.classList.remove('show');
        window.roomState.currentProduct = null;
        window.roomState.popupOpen = false;
        window.roomState.isShowingPopup = false;
    }
};

/**
 * Position popup intelligently relative to element
 */
function positionPopup(popup, element) {
    if (!popup || !element) return;
    
    // Make popup visible but transparent to measure dimensions
    popup.style.opacity = '0';
    popup.style.display = 'block';
    
    // Get element and popup dimensions
    const elementRect = element.getBoundingClientRect();
    const popupRect = popup.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Calculate position
    let left = elementRect.left + (elementRect.width / 2) - (popupRect.width / 2);
    let top = elementRect.bottom + 10;
    
    // Adjust for viewport boundaries
    if (left < 10) left = 10;
    if (left + popupRect.width > viewportWidth - 10) {
        left = viewportWidth - popupRect.width - 10;
    }
    
    if (top + popupRect.height > viewportHeight - 10) {
        top = elementRect.top - popupRect.height - 10;
    }
    
    // Apply position and restore visibility
    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
    popup.style.opacity = '';
    popup.style.display = '';
}

/**
 * Universal quantity modal opener for all rooms
 */
window.openQuantityModal = function(product) {
    // Hide any existing popup first
    hidePopupImmediate();
    
    // Use global modal function from cart.js
    if (typeof window.addToCartWithModal === 'function') {
        const sku = product.sku;
        const name = product.name || product.productName;
        const price = parseFloat(product.retailPrice || product.price);
        const image = product.primaryImageUrl || `images/items/${product.sku}A.png`;
        
        window.addToCartWithModal(sku, name, price, image);
        return;
    }
    
    console.error('Global cart functions not available - falling back to basic modal');
    
    // Basic fallback modal (should rarely be used)
    const quantityModal = document.getElementById('quantityModal');
    if (!quantityModal) {
        console.error('Quantity modal not found!');
        return;
    }
    
    // Store product for later use
    window.currentModalProduct = product;
    
    // Basic modal population
    const modalProductImage = document.getElementById('modalProductImage');
    const modalProductName = document.getElementById('modalProductName');
    const modalProductPrice = document.getElementById('modalProductPrice');
    const quantityInput = document.getElementById('quantityInput');
    
    if (modalProductImage) {
        modalProductImage.src = product.primaryImageUrl || `images/items/${product.sku}A.png`;
        modalProductImage.onerror = function() {
            this.src = 'images/items/placeholder.png';
            this.onerror = null;
        };
    }
    
    if (modalProductName) modalProductName.textContent = product.name || product.productName || 'Product';
    if (modalProductPrice) modalProductPrice.textContent = '$' + parseFloat(product.retailPrice || product.price || 0).toFixed(2);
    if (quantityInput) quantityInput.value = 1;
    
    // Show modal
    quantityModal.classList.remove('hidden');
};

/**
 * Universal detailed modal opener for all rooms
 */
window.showItemDetails = function(sku) {
    // Use the existing detailed modal system
    if (typeof window.showProductDetails === 'function') {
        window.showProductDetails(sku);
    } else {
        console.error('showProductDetails function not available');
    }
};

/**
 * Setup popup persistence when hovering over popup itself
 */
window.setupPopupPersistence = function() {
    const popup = document.getElementById('productPopup');
    if (!popup) return;
    
    // Keep popup visible when hovering over it
    popup.addEventListener('mouseenter', () => {
        clearTimeout(window.roomState.popupTimeout);
        window.roomState.isShowingPopup = true;
        window.roomState.popupOpen = true;
    });
    
    popup.addEventListener('mouseleave', () => {
        hidePopup();
    });
};

/**
 * Initialize room on DOM ready
 */
document.addEventListener('DOMContentLoaded', function() {
    // Setup popup persistence
    setupPopupPersistence();
    
    console.log('Room functions initialized and ready');
}); 