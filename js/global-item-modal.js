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
        console.log('🔧 showGlobalItemModal called with SKU:', sku, 'itemData:', itemData);
        try {
            // Initialize modal container
            initGlobalModal();
            console.log('🔧 Modal container initialized');

            let item, images;

            if (itemData) {
                // Use provided data
                item = itemData;
                images = itemData.images || [];
                console.log('🔧 Using provided item data:', item);
            } else {
                // Fetch item data from API
                console.log('🔧 Fetching item data from API for SKU:', sku);
                const response = await fetch(`/api/get_item_details.php?sku=${sku}`);
                console.log('🔧 Item details API response status:', response.status, response.statusText);
                
                if (!response.ok) {
                    throw new Error(`API request failed: ${response.status} ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('🔧 Item details API response data:', data);
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load item details');
                }
                
                item = data.item;
                images = data.images || [];
                console.log('🔧 Item data loaded:', item);
                console.log('🔧 Images loaded:', images.length, 'images');
            }

            // Remove any existing modal
            const existingModal = document.getElementById('detailedItemModal');
            if (existingModal) {
                console.log('🔧 Removing existing modal');
                existingModal.remove();
            }

            // Get the modal HTML from the API
            console.log('🔧 Fetching modal HTML from render API');
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

            console.log('🔧 Modal render API response status:', modalResponse.status, modalResponse.statusText);

            if (!modalResponse.ok) {
                throw new Error(`Modal render API failed: ${modalResponse.status} ${modalResponse.statusText}`);
            }

            const modalHtml = await modalResponse.text();
            console.log('🔧 Modal HTML received, length:', modalHtml.length);
            console.log('🔧 Modal HTML preview:', modalHtml.substring(0, 200) + '...');
            
            // Insert the modal into the container
            modalContainer.innerHTML = modalHtml;
            console.log('🔧 Modal HTML inserted into container');
            
            // Check if modal element was created
            const insertedModal = document.getElementById('detailedItemModal');
            console.log('🔧 Modal element found after insertion:', !!insertedModal);
            
            // All inline scripts have been removed from the modal component.
            // The required logic is now in `js/detailed-item-modal.js`,
            // which will be loaded dynamically below.
            
            // Store current item data
            currentModalItem = item;
            window.currentDetailedItem = item; // Make it available to the modal script
            console.log('🔧 Current modal item stored');
            
            // Dynamically load and then execute the modal's specific JS
            loadScript('js/detailed-item-modal.js', 'detailed-item-modal-script')
                .then(() => {
                    console.log('🔧 Detailed item modal script loaded.');
                    // Wait a moment for scripts to execute, then show the modal
                    setTimeout(() => {
                        console.log('🔧 Attempting to show modal...');
                        if (typeof window.showDetailedModalComponent !== 'undefined') {
                            console.log('🔧 Using showDetailedModalComponent function');
                            window.showDetailedModalComponent(sku, item);
                        } else {
                            // Fallback to show modal manually
                            const modal = document.getElementById('detailedItemModal');
                            if (modal) {
                                modal.classList.remove('hidden');
                                modal.style.display = 'flex';
                            }
                        }
                        
                        // Initialize enhanced modal content after modal is shown
                        setTimeout(() => {
                            if (typeof window.initializeEnhancedModalContent === 'function') {
                                window.initializeEnhancedModalContent();
                            }
                        }, 100);
                    }, 50);
                })
                .catch(error => {
                    console.error('🔧 Failed to load detailed item modal script:', error);
                });
            
        } catch (error) {
            console.error('🔧 Error in showGlobalItemModal:', error);
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
            modal.remove(); // Use remove() for simplicity
        }
        
        // Clear current item data
        currentModalItem = null;
    }

    /**
     * Closes the modal only if the overlay itself is clicked.
     * @param {Event} event - The click event.
     */
    function closeDetailedModalOnOverlay(event) {
        if (event.target.id === 'detailedItemModal') {
            closeGlobalItemModal();
        }
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
        initGlobalModal();

        // Expose public functions
        window.WhimsicalFrog = window.WhimsicalFrog || {};
        window.WhimsicalFrog.GlobalModal = {
            show: showGlobalItemModal,
            close: closeGlobalItemModal,
            closeOnOverlay: closeDetailedModalOnOverlay,
            getCurrentItem: getCurrentModalItem,
            quickAddToCart: quickAddToCart
        };
    }

    /**
     * Dynamically loads a script and returns a promise.
     * @param {string} src - The script source URL.
     * @param {string} id - The ID to give the script element.
     * @returns {Promise}
     */
    function loadScript(src, id) {
        return new Promise((resolve, reject) => {
            if (document.getElementById(id)) {
                resolve();
                return;
            }
            const script = document.createElement('script');
            script.src = src;
            script.id = id;
            script.onload = () => resolve();
            script.onerror = () => reject(new Error(`Script load error for ${src}`));
            document.body.appendChild(script);
        });
    }

    // Initialize on load or immediately if DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Legacy compatibility - these functions will call the new global system
    window.showItemDetails = showGlobalItemModal;
    window.showDetailedModal = showGlobalItemModal;
    window.closeDetailedModal = closeGlobalItemModal;
    window.closeDetailedModalOnOverlay = closeDetailedModalOnOverlay;
    window.openQuantityModal = quickAddToCart;

    console.log('Global Item Modal system loaded');
})(); 