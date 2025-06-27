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
 * Universal popup system for all rooms - now uses global system
 */
window.showPopup = function(element, product) {
    if (typeof window.showGlobalPopup === 'function') {
        window.showGlobalPopup(element, product);
    } else {
        console.error('Global popup system not available');
    }
};

/**
 * Hide popup with delay for mouse movement - now uses global system
 */
window.hidePopup = function() {
    if (typeof window.hideGlobalPopup === 'function') {
        window.hideGlobalPopup();
    }
};

/**
 * Hide popup immediately - now uses global system
 */
window.hidePopupImmediate = function() {
    if (typeof window.hideGlobalPopupImmediate === 'function') {
        window.hideGlobalPopupImmediate();
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
 * Universal quantity modal opener for all rooms - now uses global modal system
 */
window.openQuantityModal = function(product) {
    // Hide any existing popup first
    if (typeof hidePopupImmediate === 'function') {
        hidePopupImmediate();
    }
    
    // Use the global modal system
    if (typeof window.showGlobalItemModal === 'function') {
        window.showGlobalItemModal(product.sku);
    } else {
        console.error('Global modal system not available, falling back to simple modal');
        fallbackToSimpleModal(product);
    }
};

/**
 * Fallback to simple modal if detailed modal fails
 */
function fallbackToSimpleModal(product) {
    console.log('Using fallback simple modal for:', product.sku);
    
    // Use the old addToCartWithModal system as fallback
    if (typeof window.addToCartWithModal === 'function') {
        const sku = product.sku;
        const name = product.name || product.productName;
        const price = parseFloat(product.retailPrice || product.price);
        const image = product.primaryImageUrl || `images/items/${product.sku}A.png`;
        
        window.addToCartWithModal(sku, name, price, image);
        return;
    }
    
    console.error('Both detailed modal and fallback systems failed');
}

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