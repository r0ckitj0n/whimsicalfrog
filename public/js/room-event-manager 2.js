/**
 * WhimsicalFrog Room Event Handling and Setup
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

console.log('Loading room-event-manager.js...');

// Room event management system
window.RoomEvents = window.RoomEvents || {};

    
    // Function to setup popup events after positioning
    function setupPopupEventsAfterPositioning() {// Get all product icons
        const productIcons = document.querySelectorAll('.item-icon');productIcons.forEach((icon, index) => {
            // Make sure the element is interactive via class
            icon.classList.add('interactive-pointer');
            
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
                            try {if (typeof window.showGlobalPopup === 'function') {window.showGlobalPopup(this, productData);
                                } else {
                                    console.error('showGlobalPopup function not available. Type:', typeof window.showGlobalPopup);
                                }
                            } catch (error) {
                                console.error('Error in mouseenter event:', error);
                            }
                        });
                        
                        newIcon.addEventListener('mouseleave', function(e) {
                            try {if (typeof window.hideGlobalPopup === 'function') {window.hideGlobalPopup();
                                } else {
                                    console.error('hideGlobalPopup function not available');
                                }
                            } catch (error) {
                                console.error('Error in mouseleave event:', error);
                            }
                        });
                        
                        newIcon.addEventListener('click', function(e) {if (typeof window.showItemDetailsModal === 'function') {window.showItemDetailsModal(productData.sku);
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

console.log('room-event-manager.js loaded successfully');
