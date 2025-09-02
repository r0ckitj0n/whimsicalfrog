/**
 * Centralized Room Functions
 * Shared functionality for all room pages to eliminate code duplication
 */

// Global room state variables
window.roomState = {
    currentItem: null,
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
        const popup = document.getElementById('itemPopup');
        
        // Close popup if it's open and click is outside it
        if (popup && popup.classList.contains('show') && !popup.contains(e.target) && !e.target.closest('.item-icon')) {
            hidePopupImmediate();
        }
    });
    
    console.log(`Room ${roomNumber} (${roomType}) initialized with centralized functions`);
};

/**
 * Universal popup system for all rooms - now uses global system
 */
window.showPopup = function(element, item) {
    if (typeof window.showGlobalPopup === 'function') {
        window.showGlobalPopup(element, item);
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
    
    // Make popup measurable via class (visibility hidden; display block)
    popup.classList.add('popup-measuring');
    
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
    
    // Apply position via CSS variables and restore visibility
    popup.style.setProperty('--popup-left', left + 'px');
    popup.style.setProperty('--popup-top', top + 'px');
    popup.classList.add('use-popup-vars');
    popup.classList.remove('popup-measuring');
}

/**
 * Universal quantity modal opener for all rooms - now uses global modal system
 */
window.openQuantityModal = function(item) {
    // First try to use the new global modal system
    if (typeof window.showGlobalItemModal === 'function') {
        hideGlobalPopup();
        
        // Use the global detailed modal instead
        window.showGlobalItemModal(item.sku);
    } else {
        // Fallback to simple modal
        fallbackToSimpleModal(item);
    }
};

/**
 * Fallback function for when global modal system isn't available
 */
function fallbackToSimpleModal(item) {
    console.log('Using fallback simple modal for:', item.sku);
    
    // Use the old addToCartWithModal system as fallback
    if (typeof window.addToCartWithModal === 'function') {
        const sku = item.sku;
        const name = item.name || item.productName;
        const price = parseFloat(item.retailPrice || item.price);
        const image = item.primaryImageUrl || `images/items/${item.sku}A.png`;
        
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
    const popup = document.getElementById('itemPopup');
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