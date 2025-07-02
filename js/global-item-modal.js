/**
 * Global Item Details Modal System
 * Unified modal system for displaying detailed item information across shop and room pages
 */

(function() {
    'use strict';

    // Global modal state
    let currentModalItem = null;
    let modalContainer = null;

    /**
     * Initialize the global modal system
     */
    function initGlobalModal() {
        // Create modal container if it doesn't exist
        if (!document.getElementById('globalModalContainer')) {
            modalContainer = document.createElement('div');
            modalContainer.id = 'globalModalContainer';
            document.body.appendChild(modalContainer);
        } else {
            modalContainer = document.getElementById('globalModalContainer');
        }
    }

    /**
     * Show the global item details modal
     * @param {string} sku - The item SKU
     * @param {object} itemData - Optional pre-loaded item data
     */
    async function showGlobalItemModal(sku, itemData = null) {
        try {
            // Initialize modal container
            initGlobalModal();

            let item, images;

            if (itemData) {
                // Use provided data
                item = itemData;
                images = itemData.images || [];
            } else {
                // Fetch item data from API
                const response = await fetch(`/api/get_item_details.php?sku=${sku}`);
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load item details');
                }
                
                item = data.item;
                images = data.images || [];
            }

            // Remove any existing modal
            const existingModal = document.getElementById('detailedItemModal');
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
                    item: item,
                    images: images
                })
            });

            if (!modalResponse.ok) {
                throw new Error('Failed to load modal template');
            }

            const modalHtml = await modalResponse.text();
            
            // Insert the modal into the container
            modalContainer.innerHTML = modalHtml;
            
            // Execute any script tags in the loaded HTML
            const scripts = modalContainer.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                newScript.textContent = script.textContent;
                document.head.appendChild(newScript);
                document.head.removeChild(newScript);
            });
            
            // Store current item data
            currentModalItem = item;
            
            // Wait a moment for scripts to execute, then show the modal
            setTimeout(() => {
                if (typeof window.showDetailedModalComponent !== 'undefined') {
                    window.showDetailedModalComponent(sku, item);
                } else {
                    // Fallback - show modal manually
                    const modal = document.getElementById('detailedItemModal');
                    if (modal) {
                        modal.style.display = 'flex';
                        modal.classList.remove('hidden');
                        
                        // Store original scrollbar width for restoration
                        window.originalScrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
                        
                        // Prevent background scrolling while preserving scrollbar space
                        document.body.style.paddingRight = window.originalScrollbarWidth + 'px';
                        document.body.style.overflow = 'hidden';
                        document.body.classList.add('modal-open');
                        document.documentElement.classList.add('modal-open');
                        
                        // Set up a monitor to maintain the scrollbar space
                        window.scrollbarMonitor = setInterval(() => {
                            if (document.body.style.overflow === 'hidden' && document.body.style.paddingRight !== window.originalScrollbarWidth + 'px') {
                                document.body.style.paddingRight = window.originalScrollbarWidth + 'px';
                            }
                        }, 100);
                    }
                }
            }, 50);
            
        } catch (error) {
            console.error('Error showing global item modal:', error);
            // Show user-friendly error
            if (typeof window.showError === 'function') {
                window.showError('Unable to load item details. Please try again.');
            } else {
                alert('Unable to load item details. Please try again.');
            }
        }
    }

    /**
     * Close the global item modal
     */
    function closeGlobalItemModal() {
        const modal = document.getElementById('detailedItemModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
            document.body.classList.remove('modal-open');
            document.documentElement.classList.remove('modal-open');
            
            // Clear the scrollbar monitor
            if (window.scrollbarMonitor) {
                clearInterval(window.scrollbarMonitor);
                window.scrollbarMonitor = null;
            }
            
            // Reset body styles completely
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            document.body.style.position = '';
            document.body.style.width = '';
            document.body.style.height = '';
            document.body.style.top = '';
            document.body.style.left = '';
            
            // Reset html styles completely
            document.documentElement.style.overflow = '';
            document.documentElement.style.position = '';
            document.documentElement.style.width = '';
            document.documentElement.style.height = '';
        }
        
        // Clear current item data
        currentModalItem = null;
    }

    /**
     * Get current modal item data
     */
    function getCurrentModalItem() {
        return currentModalItem;
    }

    /**
     * Quick add to cart from popup (for room pages)
     * @param {object} item - Item data from popup
     */
    function quickAddToCart(item) {
        // Hide any popup first
        if (typeof window.hidePopupImmediate === 'function') {
            window.hidePopupImmediate();
        }
        
        // Show the detailed modal for quantity/options selection
        showGlobalItemModal(item.sku, item);
    }

    /**
     * Initialize the global modal system when DOM is ready
     */
    function init() {
        // Initialize on DOM ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGlobalModal);
        } else {
            initGlobalModal();
        }
    }

    // Make functions globally available
    window.showGlobalItemModal = showGlobalItemModal;
    window.closeGlobalItemModal = closeGlobalItemModal;
    window.getCurrentModalItem = getCurrentModalItem;
    window.quickAddToCart = quickAddToCart;
    
    // Legacy compatibility - these functions will call the new global system
    window.showItemDetails = showGlobalItemModal;
    window.showDetailedModal = showGlobalItemModal;
    window.closeDetailedModal = closeGlobalItemModal;
    window.openQuantityModal = quickAddToCart;

    // Initialize the system
    init();

    console.log('Global Item Modal system loaded');
})(); 