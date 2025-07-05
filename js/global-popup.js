/**
 * Global Popup System
 * Provides unified popup functionality across all pages
 */

console.log('Loading global-popup.js...');

// IMMEDIATELY define working functions to prevent timing issues
window.showGlobalPopup = function(element, product) {
    console.log('Early showGlobalPopup called with:', element, product);
    // Store the call for later processing
    window.pendingPopupCall = { element, product };
    
    // If the main function is ready, call it
    if (window.showGlobalPopupMain) {
        return window.showGlobalPopupMain(element, product);
    }
};

window.hideGlobalPopup = function() {
    console.log('Early hideGlobalPopup called');
    // If the main function is ready, call it
    if (window.hideGlobalPopupMain) {
        return window.hideGlobalPopupMain();
    }
};

// Ensure functions are immediately available
console.log('IMMEDIATE CHECK - Working functions defined:');
console.log('- showGlobalPopup type:', typeof window.showGlobalPopup);
console.log('- hideGlobalPopup type:', typeof window.hideGlobalPopup);

// Initialize global variables like original cart.js
window.globalPopupTimeout = null;
window.isShowingPopup = false;
window.popupOpen = false;
window.currentProduct = null;

// Global popup state
window.globalPopupState = {
    currentProduct: null,
    popupTimeout: null,
    popupOpen: false,
    isShowingPopup: false,
    lastShowTime: 0
};

/**
 * Load sales lingo for popup
 * @param {Object} product - Product data object
 */
async function loadSalesLingo(product) {
    try {
        // Determine category based on product data
        let lingoCategory = 'medium'; // Default to medium phrases
        const stockLevel = parseInt(product.stockLevel || product.stock || 0);
        
        // Prefer high-impact messages for limited stock items
        if (stockLevel <= 5 && stockLevel > 0) {
            lingoCategory = 'urgency';
        } else if (stockLevel > 0) {
            // Randomly choose between categories for in-stock items
            const categories = ['medium', 'value', 'short'];
            lingoCategory = categories[Math.floor(Math.random() * categories.length)];
        }
        
        // Fetch sales messages
        const response = await fetch(`/api/popup_sales_lingo.php?action=get_random&category=${lingoCategory}&limit=3&min_priority=2`);
        const data = await response.json();
        
        if (data.success && data.messages && data.messages.length > 0) {
            return data.messages;
        }
        
        // Fallback messages if API fails
        return [
            {category: 'medium', message: '🔥 Customer favorite - you\'ll love it!', priority: 2},
            {category: 'medium', message: '✨ Handcrafted with love and attention!', priority: 2}
        ];
        
    } catch (error) {
        console.error('Error loading sales lingo:', error);
        // Return fallback messages
        return [
            {category: 'medium', message: '🎨 Custom made just for you!', priority: 2},
            {category: 'medium', message: '💯 Satisfaction guaranteed!', priority: 2}
        ];
    }
}

/**
 * Intelligently breaks long text at natural points for better display
 * @param {string} text - The text to potentially break
 * @param {number} maxLength - Maximum characters before considering a break
 * @returns {string} Text with HTML line breaks inserted at natural points
 */
function addIntelligentLineBreaks(text, maxLength = 25) {
    if (text.length <= maxLength) {
        return text;
    }
    
    const words = text.split(' ');
    if (words.length <= 2) {
        return text; // Don't break very short phrases
    }
    
    // Find the best break point (closest to middle)
    const targetBreak = text.length / 2;
    let bestBreakIndex = 0;
    let bestDistance = Infinity;
    
    let currentLength = 0;
    for (let i = 0; i < words.length - 1; i++) {
        currentLength += words[i].length + 1; // +1 for space
        const distance = Math.abs(currentLength - targetBreak);
        
        if (distance < bestDistance) {
            bestDistance = distance;
            bestBreakIndex = i;
        }
    }
    
    // Split at the best point
    const firstPart = words.slice(0, bestBreakIndex + 1).join(' ');
    const secondPart = words.slice(bestBreakIndex + 1).join(' ');
    
    return `${firstPart}<br/>${secondPart}`;
}

/**
 * Update popup with sales lingo
 * @param {HTMLElement} popup - The popup element
 * @param {Array} salesMessages - Array of sales lingo messages
 */
function updatePopupSalesLingo(messages) {
    const topLingo = document.getElementById('popupTopLingo');
    const bottomLingo = document.getElementById('popupBottomLingo');
    
    if (!topLingo || !bottomLingo) return;
    
    // Clear existing content
    topLingo.innerHTML = '';
    bottomLingo.innerHTML = '';
    
    if (!messages || messages.length === 0) {
        topLingo.classList.add('hidden');
        bottomLingo.classList.add('hidden');
        return;
    }
    
    // Calculate total text length
    const totalLength = messages.reduce((sum, msg) => sum + msg.message.length, 0);
    const targetTopLength = totalLength / 2;
    
    // Find the best split point
    let topMessages = [];
    let bottomMessages = [];
    let currentTopLength = 0;
    
    for (let i = 0; i < messages.length; i++) {
        const msgLength = messages[i].message.length;
        
        // If adding this message to top would be closer to target than not adding it
        if (Math.abs((currentTopLength + msgLength) - targetTopLength) < Math.abs(currentTopLength - targetTopLength)) {
            topMessages.push(messages[i]);
            currentTopLength += msgLength;
        } else {
            // Put remaining messages in bottom
            bottomMessages = messages.slice(i);
            break;
        }
    }
    
    // Ensure we have at least one message somewhere
    if (topMessages.length === 0 && bottomMessages.length === 0) {
        topMessages = [messages[0]];
        bottomMessages = messages.slice(1);
    }
    
    // Display top messages
    if (topMessages.length > 0) {
        // If multiple messages in top, combine them with separators
        let topText = topMessages.map(msg => msg.message).join(' • ');
        
        // Add intelligent line breaks for long text
        topText = addIntelligentLineBreaks(topText);
        
        topLingo.innerHTML = topText;
        topLingo.classList.remove('hidden');
        
        // Use highest priority for styling
        const maxPriority = Math.max(...topMessages.map(msg => msg.priority));
        if (maxPriority >= 3) {
            topLingo.style.background = 'linear-gradient(135deg, #dc2626, #ef4444)';
        } else if (maxPriority >= 2) {
            topLingo.style.background = 'linear-gradient(135deg, #f59e0b, #f97316)';
        } else {
            topLingo.style.background = 'linear-gradient(135deg, #10b981, #14b8a6)';
        }
    } else {
        topLingo.classList.add('hidden');
    }
    
    // Display bottom messages
    if (bottomMessages.length > 0) {
        let bottomHTML = '';
        
        bottomMessages.forEach(msg => {
            const priorityClass = msg.priority >= 3 ? 'priority-high' : 
                                msg.priority >= 2 ? 'priority-medium' : 'priority-low';
            bottomHTML += `<div class="lingo-bullet ${priorityClass}" style="font-size: 11px; padding: 3px 6px; margin: 2px 0; border-radius: 4px; background: rgba(255,255,255,0.9); color: #374151; font-weight: 500;">${msg.message}</div>`;
        });
        
        bottomLingo.innerHTML = bottomHTML;
        bottomLingo.classList.remove('hidden');
    } else {
        bottomLingo.classList.add('hidden');
    }
}

/**
 * Show product popup - MAIN IMPLEMENTATION
 * @param {HTMLElement} element - The element that triggered the popup
 * @param {Object} product - Product data object
 */
window.showGlobalPopupMain = function(element, product) {
    console.log('showGlobalPopup called with:', element, product);
    
    // IMMEDIATELY clear any existing timeout and hide current popup
    clearTimeout(window.globalPopupTimeout);
    
    const popup = document.getElementById('productPopup');
    if (!popup) {
        console.error('Popup element not found!');
        return;
    }
    
    // Force immediate hide of any existing popup to release focus
    if (window.isShowingPopup || popup.classList.contains('show')) {
        popup.classList.remove('show');
        popup.style.display = 'none';
        popup.style.visibility = 'hidden';
        popup.style.opacity = '0';
        window.currentProduct = null;
        window.popupOpen = false;
        window.isShowingPopup = false;
    }
    
    // Short delay to ensure clean state before showing new popup
    setTimeout(async () => {
        // Double check that this is still the intended popup
        window.isShowingPopup = true;
        window.popupOpen = true;
        window.currentProduct = product;
        
        // Store current SKU for detail modal access
        window.currentPopupSku = product.sku;
        popup.setAttribute('data-sku', product.sku);
        
        // Update popup content using correct selectors from the HTML
        const popupImage = popup.querySelector('#popupImage');
        const popupTitle = popup.querySelector('#popupTitle');
        const popupCategory = popup.querySelector('#popupCategory');
        const popupSku = popup.querySelector('#popupSku');
        const popupStock = popup.querySelector('#popupStock');
        const popupCurrentPrice = popup.querySelector('#popupCurrentPrice');
        const popupDescription = popup.querySelector('#popupDescription');
        const popupAddBtn = popup.querySelector('#popupAddBtn');
        const popupDetailsBtn = popup.querySelector('#popupDetailsBtn');
        
        console.log('Found popup elements:', {
            image: !!popupImage,
            title: !!popupTitle,
            price: !!popupCurrentPrice,
            description: !!popupDescription,
            addBtn: !!popupAddBtn,
            detailsBtn: !!popupDetailsBtn
        });
        
        if (popupImage) {
            // Use the image from product data if available, or construct path
            const imagePath = product.primaryImageUrl || product.image || `images/items/${product.sku}A.webp`;
            popupImage.src = imagePath;
            popupImage.onerror = function() {
                // Try .png if .webp fails
                this.src = `images/items/${product.sku}A.png`;
                this.onerror = function() {
                    // Finally fall back to placeholder
                    this.src = 'images/placeholder.png';
                    this.onerror = null;
                };
            };
            popupImage.alt = product.name || product.productName || 'Product';
        }
        
        if (popupTitle) {
            popupTitle.textContent = product.name || product.productName || 'Product';
        }
        
        if (popupCategory) {
            popupCategory.textContent = product.category || 'Product';
        }
        
        if (popupSku) {
            popupSku.textContent = `SKU: ${product.sku}`;
        }
        
        if (popupStock) {
            const stockLevel = parseInt(product.stockLevel || product.stock || 0);
            if (stockLevel <= 0) {
                popupStock.textContent = 'Out of Stock';
                popupStock.className = 'popup-stock out-of-stock';
            } else if (stockLevel <= 5) {
                popupStock.textContent = `${stockLevel} Left`;
                popupStock.className = 'popup-stock limited-stock';
            } else {
                popupStock.textContent = 'In Stock';
                popupStock.className = 'popup-stock in-stock';
            }
        }
        
        if (popupCurrentPrice) {
            // Check for sales and update pricing
            if (typeof window.checkAndDisplaySalePrice === 'function') {
                window.checkAndDisplaySalePrice(product, popupCurrentPrice);
            } else {
                popupCurrentPrice.textContent = `$${parseFloat(product.retailPrice || product.price || 0).toFixed(2)}`;
            }
        }
        
        if (popupDescription) {
            popupDescription.textContent = product.description || '';
        }
        
        // Load and display sales lingo
        try {
            const salesMessages = await loadSalesLingo(product);
            updatePopupSalesLingo(salesMessages);
        } catch (error) {
            console.error('Error loading sales lingo for popup:', error);
        }
        
        // Position and show popup
        positionPopup(element, popup);
        
        // Set up add to cart button handler
        if (popupAddBtn) {
            popupAddBtn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                hidePopupImmediate();
                
                if (typeof window.showGlobalItemModal === 'function') {
                    window.showGlobalItemModal(product.sku);
                } else if (typeof window.showItemDetailsModal === 'function') {
                    window.showItemDetailsModal(product.sku);
                }
            };
        }
        
        // Set up details button handler (already has onclick in HTML)
        if (popupDetailsBtn) {
            // Handler is already set in the HTML: onclick="showItemDetailsModal(window.currentPopupSku)"
            console.log('Details button found and ready');
        }
        
        // Set up popup content click handler to open detailed modal
        const popupContent = popup.querySelector('.popup-content');
        if (popupContent) {
            popupContent.onclick = function(e) {
                // Don't interfere with button clicks
                if (e.target === popupAddBtn || popupAddBtn.contains(e.target) ||
                    e.target === popupDetailsBtn || popupDetailsBtn.contains(e.target)) {
                    return;
                }
                
                e.preventDefault();
                e.stopPropagation();
                hidePopupImmediate();
                
                if (typeof window.showGlobalItemModal === 'function') {
                    console.log('Opening detailed modal from popup click for:', product.sku);
                    window.showGlobalItemModal(product.sku);
                } else if (typeof window.showItemDetailsModal === 'function') {
                    window.showItemDetailsModal(product.sku);
                } else {
                    console.error('No modal function available from popup click');
                }
            };
        }
        
        console.log('Popup should now be visible with show class:', popup.classList.contains('show'));
    }, 50); // Short delay to ensure clean state transition
};

/**
 * Original cart.js positioning function
 */
window.positionPopup = function(element, popup) {
    console.log('Positioning popup...', element, popup);
    
    const rect = element.getBoundingClientRect();
    const scrollY = window.pageYOffset || document.documentElement.scrollTop;
    const scrollX = window.pageXOffset || document.documentElement.scrollLeft;
    
    // Use viewport coordinates, accounting for scroll
    let left = rect.left + rect.width + 10;
    let top = rect.top + scrollY - 50;

    console.log('Initial position calculations:', { 
        left, 
        top, 
        rectLeft: rect.left, 
        rectTop: rect.top,
        scrollY,
        elementTop: rect.top + scrollY
    });

    // Show popup temporarily to get actual dimensions
    popup.style.display = 'block';
    popup.style.visibility = 'visible';
    popup.style.opacity = '1';
    popup.classList.add('show');

    const popupRect = popup.getBoundingClientRect();
    const popupWidth = popupRect.width;
    const popupHeight = popupRect.height;
    
    console.log('Popup dimensions:', { width: popupWidth, height: popupHeight });

    // For rooms, use viewport positioning since the room is fullscreen
    // Convert back to viewport coordinates for popup positioning
    left = rect.left + rect.width + 10;
    top = rect.top - 50;

    // Adjust if popup would go off screen horizontally
    if (left + popupWidth > window.innerWidth) {
        left = rect.left - popupWidth - 10;
        console.log('Adjusted left for screen bounds:', left);
    }
    
    // Adjust if popup would go off screen vertically (top)
    if (top < 0) {
        top = rect.top + rect.height + 10;
        console.log('Adjusted top for screen bounds:', top);
    }
    
    // Adjust if popup would go off screen vertically (bottom)
    if (top + popupHeight > window.innerHeight) {
        const topAbove = rect.top - popupHeight - 10;
        if (topAbove >= 0) {
            top = topAbove;
        } else {
            top = window.innerHeight - popupHeight - 20;
            if (top < 0) {
                top = 10;
            }
        }
        console.log('Adjusted top for bottom bounds:', top);
    }

    // Set final position with force visibility
    popup.style.left = left + 'px';
    popup.style.top = top + 'px';
    popup.style.position = 'fixed';
    popup.style.zIndex = '9999';
    popup.style.display = 'block !important';
    popup.style.visibility = 'visible !important';
    popup.style.opacity = '1 !important';
    popup.style.transform = 'translateY(0) !important';
    popup.style.pointerEvents = 'auto';
    popup.classList.add('show');
    
    // Force a layout recalculation
    popup.offsetHeight;
    
    console.log('Final popup position:', { 
        left, 
        top, 
        display: popup.style.display, 
        visibility: popup.style.visibility, 
        opacity: popup.style.opacity, 
        hasShowClass: popup.classList.contains('show'),
        computed: getComputedStyle(popup).display,
        zIndex: popup.style.zIndex
    });
    
    // Double-check that the popup is visible in the DOM
    const finalRect = popup.getBoundingClientRect();
    console.log('Popup getBoundingClientRect:', finalRect);
    
    if (finalRect.width === 0 || finalRect.height === 0) {
        console.error('Popup has zero dimensions!');
    }
    
    if (finalRect.left < -window.innerWidth || finalRect.top < -window.innerHeight || finalRect.left > window.innerWidth || finalRect.top > window.innerHeight) {
        console.warn('Popup might be positioned off-screen:', finalRect);
    }
};

/**
 * Hide popup with delay for mouse movement - MAIN IMPLEMENTATION
 */
window.hideGlobalPopupMain = function() {
    console.log('hideGlobalPopup called');
    clearTimeout(window.globalPopupTimeout);
    
    // Reduced delay for faster response
    window.globalPopupTimeout = setTimeout(() => {
        hidePopupImmediate();
    }, 150);
};

/**
 * Hide popup immediately - original cart.js style
 */
window.hidePopupImmediate = function() {
    console.log('hidePopupImmediate called');
    const popup = document.getElementById('productPopup');
    if (popup) {
        popup.classList.remove('show');
        popup.style.display = 'none';
        popup.style.visibility = 'hidden';
        popup.style.opacity = '0';
        
        // Clear ALL state variables immediately
        window.currentProduct = null;
        window.popupOpen = false;
        window.isShowingPopup = false;
        
        // Clear any pending timeouts
        clearTimeout(window.globalPopupTimeout);
        
        console.log('Popup hidden');
    }
};

// Alias for compatibility
window.hideGlobalPopupImmediate = window.hidePopupImmediate;

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

    // Update image with fallback logic
    if (popupImage) {
        // Try .webp first, then .png, then placeholder
        if (!imageUrl || imageUrl === '' || imageUrl === 'undefined') {
            popupImage.src = `images/items/${product.sku}A.webp`;
        } else {
            popupImage.src = imageUrl;
        }
        
        popupImage.alt = product.name || product.productName || 'Product';
        popupImage.onerror = function() {
            if (!this.src.includes('.webp') && !this.src.includes('placeholder')) {
                // Try .webp version
                this.src = `images/items/${product.sku}A.webp`;
                this.onerror = function() {
                    // Try .png version
                    this.src = `images/items/${product.sku}A.png`;
                    this.onerror = function() {
                        // Finally use placeholder
                        this.src = 'images/items/placeholder.webp';
                        this.onerror = null;
                    };
                };
            } else if (this.src.includes('.webp') && !this.src.includes('placeholder')) {
                // If .webp failed, try .png
                this.src = `images/items/${product.sku}A.png`;
                this.onerror = function() {
                    this.src = 'images/items/placeholder.webp';
                    this.onerror = null;
                };
            } else {
                // Final fallback
                this.src = 'images/items/placeholder.webp';
                this.onerror = null;
            }
        };
    }

    // Update text content
    if (popupCategory) {
        popupCategory.textContent = product.category || 'Product';
    }
    
    if (popupTitle) {
        const productName = product.name || product.productName || product.title || 'Product Name';
        popupTitle.textContent = productName;
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
    
    // Smart positioning - prefer right side, then left, then below
    let left = elementRect.right + 15; // Slightly more space from element
    let top = elementRect.top + (elementRect.height / 2) - (popupRect.height / 2);
    
    // Adjust for viewport boundaries - horizontal
    if (left + popupRect.width > viewportWidth - 20) {
        // Try left side
        left = elementRect.left - popupRect.width - 15;
        if (left < 20) {
            // Position below element if sides don't fit
            left = elementRect.left + (elementRect.width / 2) - (popupRect.width / 2);
            top = elementRect.bottom + 10;
            
            // Center horizontally if needed
            if (left < 20) left = 20;
            if (left + popupRect.width > viewportWidth - 20) {
                left = viewportWidth - popupRect.width - 20;
            }
        }
    }
    
    // Adjust for viewport boundaries - vertical
    if (top < 20) {
        top = 20;
    } else if (top + popupRect.height > viewportHeight - 20) {
        // Try positioning above element
        const topAbove = elementRect.top - popupRect.height - 10;
        if (topAbove >= 20) {
            top = topAbove;
        } else {
            top = viewportHeight - popupRect.height - 20;
            if (top < 20) top = 20;
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
    const popupContent = popup.querySelector('.popup-content');

    // Add to cart button - opens item details modal like yesterday
    if (popupAddBtn) {
        popupAddBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideGlobalPopupImmediate();
            
            // Open item details modal like yesterday
            console.log('Add to Cart clicked - trying to open item details modal for:', product.sku);
            console.log('showItemDetailsModal function available:', typeof window.showItemDetailsModal);
            
            if (typeof window.showItemDetailsModal === 'function') {
                console.log('Calling window.showItemDetailsModal');
                window.showItemDetailsModal(product.sku);
            } else {
                console.error('showItemDetailsModal function not available! Available functions:');
                console.log('Available window functions:', Object.keys(window).filter(key => key.includes('show')));
                
                // Try fallback
                if (typeof showItemDetailsModal === 'function') {
                    console.log('Using local showItemDetailsModal function');
                    showItemDetailsModal(product.sku);
                } else {
                    console.error('No showItemDetailsModal function found at all!');
                }
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

    // Click on popup content for details (excluding buttons) - opens item details modal like yesterday
    if (popupContent) {
        popupContent.style.cursor = 'pointer';
        popupContent.onclick = function(e) {
            // Don't trigger if clicking on buttons
            if (e.target.closest('.popup-add-btn')) {
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            hideGlobalPopupImmediate();
            
            // Open item details modal like yesterday
            console.log('Popup content clicked - trying to open item details modal for:', product.sku);
            console.log('showItemDetailsModal function available:', typeof window.showItemDetailsModal);
            
            if (typeof window.showItemDetailsModal === 'function') {
                console.log('Calling window.showItemDetailsModal');
                window.showItemDetailsModal(product.sku);
            } else {
                console.error('showItemDetailsModal function not available! Available functions:');
                console.log('Available window functions:', Object.keys(window).filter(key => key.includes('show')));
                
                // Try fallback
                if (typeof showItemDetailsModal === 'function') {
                    console.log('Using local showItemDetailsModal function');
                    showItemDetailsModal(product.sku);
                } else {
                    console.error('No showItemDetailsModal function found at all!');
                }
            }
        };
    }
}

/**
 * Initialize global popup system - original cart.js style
 */
function initializeGlobalPopup() {
    console.log('Initializing global popup system...');
    const popup = document.getElementById('productPopup');
    if (!popup) {
        console.warn('Global popup element not found');
        return;
    }
    console.log('Global popup element found:', popup);

    // Keep popup visible when hovering over it
    popup.addEventListener('mouseenter', () => {
        clearTimeout(window.globalPopupTimeout);
        window.isShowingPopup = true;
        window.popupOpen = true;
    });

    popup.addEventListener('mouseleave', () => {
        window.hideGlobalPopup();
    });

    // Close popup when clicking outside
    document.addEventListener('click', function(e) {
        if (popup.classList.contains('show') && 
            !popup.contains(e.target) && 
            !e.target.closest('.item-icon')) {
            window.hidePopupImmediate();
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

// Now update the wrapper functions to use the main implementations
window.showGlobalPopup = function(element, product) {
    return window.showGlobalPopupMain(element, product);
};

window.hideGlobalPopup = function() {
    return window.hideGlobalPopupMain();
};

console.log('Global popup functions updated to use main implementations:');
console.log('- showGlobalPopup:', typeof window.showGlobalPopup);
console.log('- hideGlobalPopup:', typeof window.hideGlobalPopup);
console.log('- showGlobalPopupMain:', typeof window.showGlobalPopupMain);
console.log('- hideGlobalPopupMain:', typeof window.hideGlobalPopupMain);
console.log('- initializeGlobalPopup executed');

// Process any pending call that came in early
if (window.pendingPopupCall) {
    console.log('Processing pending popup call:', window.pendingPopupCall);
    window.showGlobalPopup(window.pendingPopupCall.element, window.pendingPopupCall.product);
    window.pendingPopupCall = null;
}

// Force immediate availability check
setTimeout(() => {
    console.log('POST-LOAD CHECK - Functions available:');
    console.log('- window.showGlobalPopup:', typeof window.showGlobalPopup);
    console.log('- window.hideGlobalPopup:', typeof window.hideGlobalPopup);
    if (typeof window.showGlobalPopup !== 'function') {
        console.error('CRITICAL: showGlobalPopup function not available after load!');
    }
    if (typeof window.hideGlobalPopup !== 'function') {
        console.error('CRITICAL: hideGlobalPopup function not available after load!');
    }
}, 100);

// Add a test function to manually verify popup works
window.testPopup = function() {
    console.log('Testing popup manually...');
    const popup = document.getElementById('productPopup');
    if (popup) {
        const testProduct = {
            sku: 'TEST-001',
            name: 'Test Product',
            category: 'Test',
            retailPrice: '19.99',
            description: 'This is a test popup',
            stockLevel: 5
        };
        
        // Create a fake element to position from
        const fakeElement = document.createElement('div');
        fakeElement.style.position = 'fixed';
        fakeElement.style.left = '100px';
        fakeElement.style.top = '100px';
        fakeElement.style.width = '20px';
        fakeElement.style.height = '20px';
        document.body.appendChild(fakeElement);
        
        window.showGlobalPopup(fakeElement, testProduct);
        
        // Clean up after 3 seconds
        setTimeout(() => {
            document.body.removeChild(fakeElement);
            window.hideGlobalPopup();
        }, 3000);
    } else {
        console.error('Popup element not found for test');
    }
};

console.log('Test function available: window.testPopup()'); 
/**
 * showPopup - Centralized from multiple files
 */
function showPopup(element, item) {
    // Delegate to global popup system
    window.showGlobalPopup(element, item);
}
