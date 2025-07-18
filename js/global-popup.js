/**
 * WhimsicalFrog Unified Popup System
 * Consolidated popup management with enhanced features
 * Replaces both global-popup.js and modules/popup-system.js
 */

console.log('Loading WhimsicalFrog unified popup system...');

// Enhanced popup state management
const popupState = {
    currentProduct: null,
    isVisible: false,
    hideTimeout: null,
    popupElement: null,
    initialized: false,
    isInRoomModal: false
};

// Enhanced popup system class
class UnifiedPopupSystem {
    constructor() {
        this.init();
    }

    init() {
        if (popupState.initialized) return;
        
        // Find or create popup element
        this.setupPopupElement();
        
        // Setup enhanced event listeners
        this.setupEventListeners();
        
        // Register global functions
        this.registerGlobalFunctions();
        
        popupState.initialized = true;
        console.log('✅ WhimsicalFrog Unified Popup System initialized');
    }

    setupPopupElement() {
        popupState.popupElement = document.getElementById('productPopup') || 
                                 document.getElementById('itemPopup');
        
        if (!popupState.popupElement) {
            console.log('Creating popup element...');
            this.createPopupElement();
        }
    }

    createPopupElement() {
        const popupHTML = `
            <div id="itemPopup" class="item-popup">
                <div class="popup-content">
                    <div class="popup-header">
                        <div class="popup-image-container u-position-relative">
                            <img id="popupImage" class="popup-image" src="" alt="Product Image">
                            <!- Marketing Badge overlaying image ->
                            <div id="popupMarketingBadge" class="popup-marketing-badge hidden u-position-absolute u-top-6px u-right-6px u-z-index-10">
                                <span class="marketing-badge">
                                    <span id="popupMarketingText"></span>
                                </span>
                            </div>
                        </div>
                        <div class="popup-info">
                            <h3 id="popupTitle" class="popup-title"></h3>
                            <div id="popupMainSalesPitch" class="popup-main-sales-pitch"></div>
                            <p id="popupCategory" class="popup-category"></p>
                            <p id="popupSku" class="popup-sku"></p>
                            <div id="popupStock" class="popup-stock-info"></div>
                            <div id="popupCurrentPrice" class="popup-price"></div>
                        </div>
                    </div>
                    <div class="popup-body">
                        <p id="popupDescription" class="popup-description"></p>
                    </div>
                    <div class="popup-footer">
                        <button id="popupAddBtn" class="popup-btn popup-btn-primary">Add to Cart</button>

                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', popupHTML);
        popupState.popupElement = document.getElementById('itemPopup');
    }

    setupEventListeners() {
        const popup = popupState.popupElement;
        if (!popup) return;
        
        // Enhanced hover persistence
        popup.addEventListener('mouseenter', () => {
            this.clearHideTimeout();
            popupState.isVisible = true;
        });
        
        popup.addEventListener('mouseleave', () => {
            this.hide();
        });
        
        // Click outside to close
        document.addEventListener('click', (e) => {
            if (popupState.isVisible && 
                !popup.contains(e.target) && 
                !e.target.closest('.item-icon')) {
                this.hideImmediate();
            }
        });
        
        // Make entire popup clickable to open item details
        const popupContent = popup.querySelector('.popup-content') || popup.querySelector('.popup-content-enhanced');
        if (popupContent) {
            popupContent.addEventListener('click', (e) => {
                // Check if the click was on a button - if so, let the button handle it
                if (e.target.closest('#popupAddBtn') || 
                    e.target.closest('#popupDetailsBtn') ||
                    e.target.closest('.popup-add-btn') ||
                    e.target.closest('.popup-add-btn-enhanced') ||
                    e.target.closest('.popup-details-btn-enhanced')) {
                    return; // Let the button handlers take care of this
                }
                
                e.preventDefault();
                e.stopPropagation();
                this.handleViewDetails();
            });
            
            // Add visual feedback for clickability (CSS already handles this, but ensure it's set)
            popupContent.style.cursor = 'pointer';
        }
        
        // Button event listeners
        const addBtn = popup.querySelector('#popupAddBtn');
        
        if (addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleAddToCart();
            });
        }
    }

    // Main popup display function  
    show(element, item) {
        console.log('Showing popup for:', element, item);
        
        if (!popupState.initialized) {
            console.warn('Popup system not initialized, initializing now...');
            this.init();
        }
        
        const popup = popupState.popupElement;
        if (!popup) {
            console.error('Popup element not found!');
            return;
        }
        
        // Clear any existing timeouts
        this.clearHideTimeout();
        
        // Hide any existing popup first
        this.hideImmediate();
        
        // Clear previous product state before setting new one
        this.clearProductState();
        
        // Set state
        popupState.currentProduct = item;
        popupState.isVisible = true;
        popupState.isInRoomModal = document.querySelector('.room-modal-overlay') !== null;
        
        // Set global state for backward compatibility
        window.isShowingPopup = true;
        window.popupOpen = true;
        window.currentItem = item;
        
        // Update popup content
        this.updateContent(item);
        
        // Position and show popup
        this.positionPopup(element, popup);
        
        console.log('Popup should now be visible');
    }

    // Update popup content
    updateContent(item) {
        const popup = popupState.popupElement;
        if (!popup) return;
        
        // Reset badge visibility at the start
        const marketingBadge = popup.querySelector('#popupMarketingBadge');
        if (marketingBadge) {
            marketingBadge.classList.add('hidden');
        }
        
        const popupImage = popup.querySelector('#popupImage');
        const popupTitle = popup.querySelector('#popupTitle');
        const popupMainSalesPitch = popup.querySelector('#popupMainSalesPitch');
        const popupCategory = popup.querySelector('#popupCategory');
        const popupSku = popup.querySelector('#popupSku');
        const popupStock = popup.querySelector('#popupStock');
        const popupCurrentPrice = popup.querySelector('#popupCurrentPrice');
        const popupDescription = popup.querySelector('#popupDescription');
        const popupSalesPitch = popup.querySelector('#popupSalesPitch');
        
        // Update image
        if (popupImage) {
            popupImage.src = `images/items/${item.sku}A.webp`;
            
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
        
        // Update text content
        if (popupTitle) {
            popupTitle.textContent = item.name || item.productName || 'Item';
        }
        
        if (popupCategory) {
            popupCategory.textContent = item.category || 'Item';
        }
        
        if (popupSku) {
            popupSku.textContent = `SKU: ${item.sku}`;
        }
        
        // Update stock info and badges
        if (popupStock) {
            const stockLevel = parseInt(item.stockLevel || item.stock || 0);
            if (stockLevel <= 0) {
                popupStock.textContent = 'Out of Stock';
                popupStock.className = 'popup-stock-info out-of-stock';
            } else if (stockLevel <= 5) {
                popupStock.textContent = `${stockLevel} Left`;
                popupStock.className = 'popup-stock-info limited-stock';
            } else {
                popupStock.textContent = 'In Stock';
                popupStock.className = 'popup-stock-info in-stock';
            }
        }
        
        if (popupCurrentPrice) {
            // Check for sales and update pricing
            if (typeof window.checkAndDisplaySalePrice === 'function') {
                window.checkAndDisplaySalePrice(item, popupCurrentPrice);
            } else {
                popupCurrentPrice.textContent = `$${parseFloat(item.retailPrice || item.price || 0).toFixed(2)}`;
            }
        }
        
        if (popupDescription) {
            popupDescription.textContent = item.description || '';
        }
        
        // Load and display marketing data (badge and sales pitch line)
        if (item.sku) {
            this.loadMarketingData(item.sku, null, popupMainSalesPitch);
        }
    }
    


    // Load marketing data for badge and sales pitch line
    async loadMarketingData(sku, salesPitchElement, mainSalesPitchElement) {
        try {
            const data = await apiGet(`get_marketing_data.php?sku=${sku}`);
            
            
            if (data.success && data.exists && data.marketing_data) {
                const marketing = data.marketing_data;
                // Display marketing badge and main sales pitch
                this.displayMarketingBadge(marketing);
                this.displayMainSalesPitch(marketing, mainSalesPitchElement);
            } else {
                // Show generic marketing content as fallback
                this.displayGenericMarketingContent(mainSalesPitchElement);
            }
        } catch (error) {
            console.log('Marketing data not available:', error);
            // Show generic marketing content as fallback
            this.displayGenericMarketingContent(mainSalesPitchElement);
        }
    }
    
    // Display main sales pitch below title (single line)
    displayMainSalesPitch(marketing, element) {
        if (!element) return;
        
        let mainPitch = '';
        
        // Try to get the best single sales pitch
        if (marketing.selling_points && marketing.selling_points.length > 0) {
            // Use the first selling point as the main sales pitch
            mainPitch = marketing.selling_points[0];
        } else if (marketing.competitive_advantages && marketing.competitive_advantages.length > 0) {
            // Fallback to competitive advantage
            mainPitch = marketing.competitive_advantages[0];
        } else if (marketing.customer_benefits && marketing.customer_benefits.length > 0) {
            // Fallback to customer benefit
            mainPitch = marketing.customer_benefits[0];
        }
        
        if (mainPitch) {
            // Display full main sales pitch (no truncation)
            element.innerHTML = `<div class="popup-main-pitch">${mainPitch}</div>`;
        } else {
            element.innerHTML = '';
        }
    }
    
    // Display marketing badge (if available)
    displayMarketingBadge(marketing) {
        const popup = popupState.popupElement;
        if (!popup) return;
        const marketingBadge = popup.querySelector('#popupMarketingBadge');
        const marketingTextEl = popup.querySelector('#popupMarketingText');
        if (!marketingBadge || !marketingTextEl) return;

        // Determine badge text and optional type
        let badgeText = '';
        if (marketing.badges && marketing.badges.length > 0) {
            badgeText = marketing.badges[0].text || marketing.badges[0];
        } else if (marketing.primary_badge) {
            badgeText = marketing.primary_badge;
        }

        if (badgeText) {
            marketingTextEl.textContent = badgeText;
            marketingBadge.classList.remove('hidden');
            marketingBadge.style.display = 'block';
        } else {
            marketingBadge.classList.add('hidden');
            marketingBadge.style.display = 'none';
        }
    }



    // Display generic marketing content when no marketing data available
    displayGenericMarketingContent(mainSalesPitchElement) {
        // DISABLE OLD POPUP MARKETING SYSTEM
        // This has been replaced by the unified badge scoring system
        console.log('🚫 Old popup generic marketing system disabled - using unified system instead');
        
        // Show generic marketing line only (no badges)
        if (mainSalesPitchElement) {
            const genericMessages = [
                '✨ Experience premium quality and exceptional style!',
                '🌟 Discover the perfect addition to your collection!',
                '💎 Elevate your look with this must-have piece!',
                '🔥 Join the style revolution with this trendy item!',
                '⭐ Transform your wardrobe with superior craftsmanship!'
            ];
            
            const randomMessage = genericMessages[Math.floor(Math.random() * genericMessages.length)];
            mainSalesPitchElement.innerHTML = `<div class="popup-main-pitch">${randomMessage}</div>`;
        }
    }

    // Hide popup with delay
    hide(delay = 250) {
        if (!popupState.isVisible) return;
        
        this.clearHideTimeout();
        
        popupState.hideTimeout = setTimeout(() => {
            this.hideImmediate();
        }, delay);
    }

    // Hide popup immediately
    hideImmediate() {
        // Remove overlay dimming reduction
        try {
            if (window.parent && window.parent !== window) {
                const overlay = window.parent.document.querySelector('.room-modal-overlay');
                if (overlay) overlay.classList.remove('popup-active');
            }
        } catch(e) {}

        this.clearHideTimeout();
        
        const popup = popupState.popupElement;
        if (popup) {
            popup.classList.remove('show', 'in-room-modal', 'visible', 'positioned');
            popup.classList.add('hidden');
        }
        
        // Reset state
        popupState.isVisible = false;
        
        // Reset global state for backward compatibility
        window.popupOpen = false;
        window.isShowingPopup = false;
        
        console.log('Popup hidden immediately');
    }
    
    // Clear product state - called only when we're sure it's safe
    clearProductState() {
        popupState.currentProduct = null;
        window.currentItem = null;
    }

    // Clear hide timeout
    clearHideTimeout() {
        if (popupState.hideTimeout) {
            clearTimeout(popupState.hideTimeout);
            popupState.hideTimeout = null;
        }
    }

    // Handle add to cart button
    handleAddToCart() {
        console.log('🔧 handleAddToCart called, currentProduct:', popupState.currentProduct);
        
        // First check if we have currentProduct, if not try to get it from global state
        const productToUse = popupState.currentProduct || window.currentItem;
        
        if (!productToUse) {
            console.error('🔧 No current product in popup state or global state!');
            return;
        }
        
        console.log('🔧 About to hide popup and open modal for SKU:', productToUse.sku);
        
        // Store the product SKU before hiding popup to prevent timing issues
        const skuToOpen = productToUse.sku;
        
        this.hideImmediate();
        
        // Try to open item modal
        let modalFn = window.showGlobalItemModal;
        if (typeof modalFn !== 'function' && window.parent && window.parent !== window) {
            modalFn = window.parent.showGlobalItemModal;
        }
        // Extra fallback to unified global modal namespace
        if (typeof modalFn !== 'function' && window.WhimsicalFrog && window.WhimsicalFrog.GlobalModal && typeof window.WhimsicalFrog.GlobalModal.show === 'function') {
            modalFn = window.WhimsicalFrog.GlobalModal.show;
        }
        if (typeof modalFn !== 'function' && window.parent && window.parent.WhimsicalFrog && window.parent.WhimsicalFrog.GlobalModal && typeof window.parent.WhimsicalFrog.GlobalModal.show === 'function') {
            modalFn = window.parent.WhimsicalFrog.GlobalModal.show;
        }

        if (typeof modalFn === 'function') {
            console.log('🔧 showGlobalItemModal function available, calling with SKU:', skuToOpen);
            modalFn(skuToOpen, productToUse);
            this.clearProductState();
        } else {
            console.error('🔧 showGlobalItemModal function not available in current or parent context!');
        }
    }

    async handleViewDetails() {
        console.log('🔧 handleViewDetails called, currentProduct:', popupState.currentProduct);
        if (!popupState.currentProduct) return;

        console.log(`🔧 About to hide popup and open details modal for SKU: ${popupState.currentProduct.sku}`);
        this.hideImmediate();

        const functionPath = 'WhimsicalFrog.GlobalModal.show';

        // First try within the current window (iframe or main)
        let isReady = await window.waitForFunction(functionPath, window);
        if (isReady) {
            window.WhimsicalFrog.GlobalModal.show(popupState.currentProduct.sku, popupState.currentProduct);
            return;
        }

        // Fallback: try parent window (when running inside iframe)
        if (window.parent && window.parent !== window) {
            isReady = await window.waitForFunction(functionPath, window.parent);
            if (isReady) {
                window.parent.WhimsicalFrog.GlobalModal.show(popupState.currentProduct.sku, popupState.currentProduct);
                return;
            }
        }

        console.error(`🔧 ${functionPath} function not available in current or parent context!`);
        if (typeof window.showGlobalNotification === 'function') {
            window.showGlobalNotification('Could not open item details. Please try again.', 'error');
        }
    }

    // Position popup function with improved positioning logic
    positionPopup(element, popup) {
        console.log('Positioning popup...', element, popup);
        // Detect and mark that we are inside a room-modal iframe (heuristic: parent has .room-modal-overlay)
        try {
            if (window.parent && window.parent !== window && window.parent.document.querySelector('.room-modal-overlay')) {
                popupState.isInRoomModal = true;
            }
        } catch(e) { /* cross-origin safe guard */ }
        
        const rect = element.getBoundingClientRect();
        
        // Determine z-index from CSS variables: popups layer or just above room-modal overlay
        const isInRoomModal = popupState.isInRoomModal;
        const rootStyles = getComputedStyle(document.documentElement);
        // Read CSS custom properties correctly (two leading dashes)
        const popupDefaultZ = parseInt(rootStyles.getPropertyValue('--popup-z-index').trim() || '2600', 10);
        const roomModalZ = parseInt(rootStyles.getPropertyValue('--z-room-modals').trim() || '2400', 10);
        const zIndex = isInRoomModal ? roomModalZ + 1 : popupDefaultZ;
        // Toggle class to leverage room-modal-specific CSS rules
        if (isInRoomModal) {
            popup.classList.add('in-room-modal');
        } else {
            popup.classList.remove('in-room-modal');
        }

        // Show popup temporarily to get actual dimensions using CSS classes
        // Ensure we never exceed configured max width
        let cssMax = rootStyles.getPropertyValue('--popup-max-width').trim() || '450px';
        if (isInRoomModal) {
            // Allow natural content width for room-modal popups
            cssMax = 'none';
            popup.style.width = 'max-content';
            popup.style.minWidth = '0';
            popup.style.maxWidth = 'none';
        } else {
            popup.style.maxWidth = cssMax;
        }
        popup.classList.remove('hidden');
        popup.classList.add('measuring');
        // Ensure inline z-index override even if custom property previously set
        popup.style.zIndex = zIndex;
        popup.style.zIndex = zIndex;
        
        const popupRect = popup.getBoundingClientRect();
        const popupWidth = Math.min(popupRect.width, parseInt(cssMax));
        const popupHeight = popupRect.height;
        
        // Get viewport dimensions with safety margins
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const margin = 10; // Safety margin from edges
        
        // Calculate preferred position - try to center popup on element horizontally
        let left = rect.left + (rect.width / 2) - (popupWidth / 2);
        let top = rect.top - popupHeight - margin; // Above element by default
        
        // Horizontal positioning logic
        // Ensure popup doesn't go off left edge
        if (left < margin) {
            left = margin;
        }
        
        // Ensure popup doesn't go off right edge
        if (left + popupWidth + margin > viewportWidth) {
            left = viewportWidth - popupWidth - margin;
        }
        
        // Vertical positioning logic
        // If popup would go off top of screen, position below element
        if (top < margin) {
            top = rect.bottom + margin;
        }
        
        // If popup would go off bottom of screen, move it up
        if (top + popupHeight + margin > viewportHeight) {
            top = viewportHeight - popupHeight - margin;
            
            // If still doesn't fit (popup is taller than viewport), position at top with margin
            if (top < margin) {
                top = margin;
            }
        }
        
        // Final positioning - ensure we don't go negative
        left = Math.max(margin, left);
        top = Math.max(margin, top);
        
        // Ensure we don't exceed viewport bounds
        left = Math.min(left, viewportWidth - popupWidth - margin);
        top = Math.min(top, viewportHeight - popupHeight - margin);
        
        // Set final position using custom properties and classes
        popup.style.setProperty('--popup-left', left + 'px');
        popup.style.setProperty('--popup-top', top + 'px');
        
        // Clear any conflicting inline styles that might override CSS classes
        popup.style.removeProperty('display');
        popup.style.removeProperty('visibility');
        popup.style.removeProperty('opacity');
        popup.style.removeProperty('pointer-events');
        popup.style.removeProperty('transform');
        
        popup.classList.remove('measuring');
        popup.classList.add('positioned', 'visible', 'show');
        
        console.log('Popup positioned at:', { left, top }, 'Element rect:', rect, 'Popup size:', { width: popupWidth, height: popupHeight });
    }

    // Register global functions for backward compatibility
    registerGlobalFunctions() {
        // Main popup functions
        window.showGlobalPopup = (element, item) => this.show(element, item);
        window.hideGlobalPopup = (delay = 250) => this.hide(delay);
        window.hideGlobalPopupImmediate = () => this.hideImmediate();
        
        // Additional utility functions
        window.clearPopupTimeout = () => this.clearHideTimeout();
        
        // Legacy compatibility aliases
        window.showPopup = window.showGlobalPopup;
        window.hidePopup = window.hideGlobalPopup;
        window.hidePopupImmediate = window.hideGlobalPopupImmediate;
        
        console.log('✅ Global popup functions registered');
    }

    // Get current popup state
    getState() {
        return {
            isVisible: popupState.isVisible,
            currentProduct: popupState.currentProduct,
            isInRoomModal: popupState.isInRoomModal,
            initialized: popupState.initialized
        };
    }
}

// Initialize unified popup system
const unifiedPopupSystem = new UnifiedPopupSystem();

// Initialize global variables for backward compatibility
window.globalPopupTimeout = null;
window.isShowingPopup = false;
window.popupOpen = false;
window.currentItem = null;

// Final system ready message
console.log('🎉 WhimsicalFrog Unified Popup System ready!');
