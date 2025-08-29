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
<<<<<<< HEAD
        try {
            // Initialize modal container
            initGlobalModal();
=======
        console.log('🔧 showGlobalItemModal called with SKU:', sku, 'itemData:', itemData);
        try {
            // Initialize modal container
            initGlobalModal();
            console.log('🔧 Modal container initialized');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)

            let item, images;

            if (itemData) {
                // Use provided data
                item = itemData;
                images = itemData.images || [];
<<<<<<< HEAD
            } else {
                // Fetch item data from API
                const response = await fetch(`/api/get_item_details.php?sku=${sku}`);
                const data = await response.json();
=======
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
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                
                if (!data.success) {
                    throw new Error(data.message || 'Failed to load item details');
                }
                
                item = data.item;
                images = data.images || [];
<<<<<<< HEAD
=======
                console.log('🔧 Item data loaded:', item);
                console.log('🔧 Images loaded:', images.length, 'images');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            }

            // Remove any existing modal
            const existingModal = document.getElementById('detailedItemModal');
            if (existingModal) {
<<<<<<< HEAD
=======
                console.log('🔧 Removing existing modal');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                existingModal.remove();
            }

            // Get the modal HTML from the API
<<<<<<< HEAD
=======
            console.log('🔧 Fetching modal HTML from render API');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
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

<<<<<<< HEAD
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
=======
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
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
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
<<<<<<< HEAD
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
=======
            modal.remove(); // Use remove() for simplicity
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
        }
        
        // Clear current item data
        currentModalItem = null;
    }

    /**
<<<<<<< HEAD
=======
     * Closes the modal only if the overlay itself is clicked.
     * @param {Event} event - The click event.
     */
    function closeDetailedModalOnOverlay(event) {
        if (event.target.id === 'detailedItemModal') {
            closeGlobalItemModal();
        }
    }

    /**
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
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
<<<<<<< HEAD
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
    
=======
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

>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    // Legacy compatibility - these functions will call the new global system
    window.showItemDetails = showGlobalItemModal;
    window.showDetailedModal = showGlobalItemModal;
    window.closeDetailedModal = closeGlobalItemModal;
<<<<<<< HEAD
    window.openQuantityModal = quickAddToCart;

    // Initialize the system
    init();

=======
    window.closeDetailedModalOnOverlay = closeDetailedModalOnOverlay;
    window.openQuantityModal = quickAddToCart;

>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    console.log('Global Item Modal system loaded');
})(); 