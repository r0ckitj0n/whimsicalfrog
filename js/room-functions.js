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
 * Universal quantity modal opener for all rooms - now uses detailed modal system
 */
window.openQuantityModal = async function(product) {
    // Hide any existing popup first
    hidePopupImmediate();
    
    const sku = product.sku;
    
    try {
        // Use the detailed modal system like the shop page
        const response = await fetch(`/api/get_item_details.php?sku=${sku}`);
        const data = await response.json();
        
        if (data.success && data.item) {
            // Remove any existing detailed modal
            const existingModal = document.getElementById('detailedProductModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Get the modal HTML from the API
            const modalResponse = await fetch('/api/render_detailed_modal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    item: data.item,
                    images: data.images || []
                })
            });
            
            const modalHtml = await modalResponse.text();
            
            // Insert the modal into a container
            let modalContainer = document.getElementById('detailedModalContainer');
            if (!modalContainer) {
                modalContainer = document.createElement('div');
                modalContainer.id = 'detailedModalContainer';
                document.body.appendChild(modalContainer);
            }
            modalContainer.innerHTML = modalHtml;
            
            // Execute any script tags in the loaded HTML
            const scripts = modalContainer.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                newScript.textContent = script.textContent;
                document.head.appendChild(newScript);
                document.head.removeChild(newScript);
            });
            
            // Wait a moment for scripts to execute, then show the modal
            setTimeout(() => {
                if (typeof window.showDetailedModalComponent !== 'undefined') {
                    window.showDetailedModalComponent(sku, data.item);
                } else {
                    // Fallback - show modal manually
                    const modal = document.getElementById('detailedProductModal');
                    if (modal) {
                        modal.style.display = 'flex';
                        modal.classList.remove('hidden');
                        document.body.classList.add('modal-open');
                        document.documentElement.classList.add('modal-open');
                    }
                }
            }, 50);
            
        } else {
            console.error('Failed to load product details:', data.message);
            // Fallback to old system if API fails
            fallbackToSimpleModal(product);
        }
    } catch (error) {
        console.error('Error loading detailed modal:', error);
        // Fallback to old system if there's an error
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