(function() {
    'use strict';

    // This script should be loaded when the global item modal is used.

    // Store current item data, ensuring it's safely initialized.
    window.currentDetailedItem = window.currentDetailedItem || {};

    /**
     * Centralized function to set up event listeners and dynamic content for the modal.
     */
    function initializeEnhancedModalContent() {
        const item = window.currentDetailedItem;
        if (!item) return;

        // Set up centralized image error handling
        const mainImage = document.getElementById('detailedMainImage');
        if (mainImage && typeof window.setupImageErrorHandling === 'function') {
            window.setupImageErrorHandling(mainImage, item.sku);
        }

        // Run enhancement functions
        ensureAdditionalDetailsCollapsed();
        updateModalBadges(item.sku);
        
        // Add click listener for the accordion
        const toggleButton = document.getElementById('additionalInfoToggle');
        if (toggleButton) {
            toggleButton.addEventListener('click', toggleDetailedInfo);
        }
    }
    
    /**
     * Ensures the "Additional Details" section of the modal is collapsed by default.
     */
    function ensureAdditionalDetailsCollapsed() {
        const content = document.getElementById('additionalInfoContent');
        const icon = document.getElementById('additionalInfoIcon');
        
        if (content) {
            content.classList.add('hidden');
        }
        if (icon) {
            icon.classList.remove('rotate-180');
        }
    }

    /**
     * Toggles the visibility of the "Additional Details" section.
     */
    function toggleDetailedInfo() {
        const content = document.getElementById('additionalInfoContent');
        const icon = document.getElementById('additionalInfoIcon');
        
        if (content && icon) {
            content.classList.toggle('hidden');
            icon.classList.toggle('rotate-180');
        }
    }

    /**
     * Fetches badge data and updates the modal UI.
     * @param {string} sku 
     */
    async function updateModalBadges(sku) {
        const badgeContainer = document.getElementById('detailedBadgeContainer');
        if (!badgeContainer) return;

        // Clear any existing badges
        badgeContainer.innerHTML = '';

        try {
            const data = await apiGet(`get_badge_scores.php?sku=${sku}`);
            

            if (data.success && data.badges) {
                const badges = data.badges;
                
                // Order of importance for display
                const badgeOrder = ['SALE', 'BESTSELLER', 'TRENDING', 'LIMITED_STOCK', 'PREMIUM'];
                
                badgeOrder.forEach(badgeKey => {
                    if (badges[badgeKey] && badges[badgeKey].display) {
                        const badgeInfo = badges[badgeKey];
                        const badgeElement = createBadgeElement(badgeInfo.text, badgeInfo.class);
                        badgeContainer.appendChild(badgeElement);
                    }
                });
            }
        } catch (error) {
            console.error('Error fetching modal badges:', error);
        }
    }

    /**
     * Creates a badge element.
     * @param {string} text - The text for the badge.
     * @param {string} badgeClass - The CSS class for styling the badge.
     * @returns {HTMLElement}
     */
    function createBadgeElement(text, badgeClass) {
        const badgeSpan = document.createElement('span');
        badgeSpan.className = `inline-block px-2 py-1 rounded-full text-xs font-bold text-white ${badgeClass}`;
        badgeSpan.textContent = text;
        return badgeSpan;
    }
    
    // -- Show & hide modal helpers --
    function showDetailedModalComponent(sku, itemData = {}) {
        const modal = document.getElementById('detailedItemModal');
        if (!modal) return;

        // Make sure the provided item data is stored for other helpers.
        window.currentDetailedItem = itemData;

        // Remove hidden/display styles applied by server template
        modal.classList.remove('hidden');
        modal.style.display = 'flex';

        // Ensure modal is on top of any overlays
        modal.classList.add('z-popup');

        // Close modal when clicking overlay (attribute set in template)
        modal.addEventListener('click', (e) => {
            if (e.target.dataset.action === 'closeDetailedModalOnOverlay') {
                closeDetailedModalComponent();
            }
        });

        // Optionally run content initializer
        if (typeof initializeEnhancedModalContent === 'function') {
            initializeEnhancedModalContent();
        }
    }

    function closeDetailedModalComponent() {
        const modal = document.getElementById('detailedItemModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.style.display = 'none';
        }
    }

    // -- Expose necessary functions to the global scope --
    window.initializeEnhancedModalContent = initializeEnhancedModalContent;
    window.showDetailedModalComponent = showDetailedModalComponent;
    window.closeDetailedModalComponent = closeDetailedModalComponent;

})();