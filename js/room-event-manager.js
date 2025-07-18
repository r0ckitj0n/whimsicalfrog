/**
 * WhimsicalFrog Room Event Handling and Setup
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

console.log('Loading room-event-manager.js...');

// Room event management system
window.RoomEvents = window.RoomEvents || {};

    
    // Function to setup popup events after positioning
    function setupPopupEventsAfterPositioning() { // Get all product icons
        // Accept both legacy .item-icon and new .room-product-icon
    const productIcons = document.querySelectorAll('.item-icon, .room-product-icon');productIcons.forEach((icon, index) => {
            // Make sure the element is interactive
            icon.classList.add('clickable-icon');
            
            // Get the product data from the inline event attribute
            const onMouseEnterAttr = icon.getAttribute('onmouseenter');
            if (onMouseEnterAttr) {
                // Extract the product data from the onmouseenter attribute
                const match = onMouseEnterAttr.match(/showGlobalPopup\(this,\s*(.+)\)/);
                if (match) {
                    try {
                        // Decode HTML entities and parse JSON
                        const jsonString = match[1].replace(/&quot;/g, '"').replace(/&#039;/g, "'");
                        const productData = JSON.parse(jsonString);// Remove existing event listeners by cloning the element
                        const newIcon = icon.cloneNode(true);
                        icon.parentNode.replaceChild(newIcon, icon);
                        
                        // Add fresh event listeners
                        newIcon.addEventListener('mouseenter', function(e) {
                            try {if (typeof (window.showGlobalPopup || (parent && parent.showGlobalPopup)) === 'function') {(window.showGlobalPopup || (parent && parent.showGlobalPopup))(this, productData);
                                } else {
                                    console.error('showGlobalPopup function not available. Type:', typeof (window.showGlobalPopup || (parent && parent.showGlobalPopup)));
                                }
                            } catch (error) {
                                console.error('Error in mouseenter event:', error);
                            }
                        });
                        
                        newIcon.addEventListener('mouseleave', function(e) {
                            try {if (typeof (window.hideGlobalPopup || (parent && parent.hideGlobalPopup)) === 'function') {(window.hideGlobalPopup || (parent && parent.hideGlobalPopup))();
                                } else {
                                    console.error('hideGlobalPopup function not available');
                                }
                            } catch (error) {
                                console.error('Error in mouseleave event:', error);
                            }
                        });
                        
                        newIcon.addEventListener('click', function(e) {if (typeof (window.showItemDetailsModal || (parent && parent.showItemDetailsModal)) === 'function') {(window.showItemDetailsModal || (parent && parent.showItemDetailsModal))(productData.sku);
                            } else {
                                console.error('showItemDetailsModal function not available on click');
                            }
                        });
                        
                    } catch (error) {
                        console.error(`Error parsing product data for icon ${index}:`, error);
                    }
                }
            }
        });
    }

// Expose globally for other modules
window.setupPopupEventsAfterPositioning = setupPopupEventsAfterPositioning;

// -------------------- NEW CENTRALIZED DELEGATED LISTENERS --------------------
// Ensure events still fire even if per-icon listeners were not attached (fallback)
function attachDelegatedItemEvents(){
    if (document.body.hasAttribute('data-wf-room-delegated-listeners')) return;
    document.body.setAttribute('data-wf-room-delegated-listeners','true');

    // Utility to parse product data from inline attribute or dataset
    function extractProductData(icon){
        // 1) Try full JSON from data-product
        if (icon.dataset.product){
            try{
                return JSON.parse(icon.dataset.product);
            }catch(e){
                console.warn('[extractProductData] Invalid JSON in data-product:', e);
            }
        }

        // 2) Try to assemble from individual data-* attributes (preferred over legacy inline attr)
        if (icon.dataset.sku){
            return {
                sku: icon.dataset.sku,
                name: icon.dataset.name || '',
                price: parseFloat(icon.dataset.price || icon.dataset.cost || '0'),
                description: icon.dataset.description || '',
                stock: parseInt(icon.dataset.stock || '0',10),
                category: icon.dataset.category || ''
            };
        }

        // 3) Fallback – parse legacy onmouseenter inline attribute
        const attr = icon.getAttribute('onmouseenter');
        if (attr){
            const match = attr.match(/showGlobalPopup\(this,\s*(.+)\)/);
            if (match){
                try{
                    const jsonString = match[1]
                        .replace(/&quot;/g, '"')
                        .replace(/&#039;/g, "'");
                    return JSON.parse(jsonString);
                }catch(e){
                    console.warn('[extractProductData] Failed to parse inline JSON:', e);
                }
            }
        }

        // If we reach here, no valid product data
        return null;
    }

    // Hover events
    document.addEventListener('mouseover', function(e){
        const icon = e.target.closest('.item-icon, .room-product-icon');
        if(!icon) return;
        const productData = extractProductData(icon);
        const popupFn = window.showGlobalPopup || (parent && parent.showGlobalPopup);
        if (typeof popupFn === 'function' && productData){
            popupFn(icon, productData);
        }
    });
    document.addEventListener('mouseout', function(e){
        const icon = e.target.closest('.item-icon, .room-product-icon');
        if(!icon) return;
        const hideFn = window.hideGlobalPopup || (parent && parent.hideGlobalPopup);
        console.log('[DelegatedHover] mouseout from', icon);
        if (typeof hideFn === 'function') hideFn();
    });

    // Click events
    document.addEventListener('click', function(e){
        const icon = e.target.closest('.item-icon, .room-product-icon');
        if(!icon) return;
        e.preventDefault();
        const productData = extractProductData(icon);
        const detailsFn = (parent && parent.showGlobalItemModal) || window.showGlobalItemModal || window.showItemDetailsModal || window.showItemDetails || (parent && (parent.showItemDetailsModal || parent.showItemDetails));
        if (typeof detailsFn === 'function' && productData){
            detailsFn(productData.sku, productData);
        }
    });
}

// Immediately attach in current document
attachDelegatedItemEvents();

// Expose for iframes
window.attachDelegatedItemEvents = attachDelegatedItemEvents;

console.log('room-event-manager.js loaded successfully');
