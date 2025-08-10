/**
 * Event handlers for detailed item modal
 * Handles closing the modal via button click and overlay click
 */

(function() {
    'use strict';
    
    console.log('🔧 Detailed modal event handlers initializing...');
    
    // Function to close the modal
    function closeModal() {
        const modal = document.getElementById('detailedItemModal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('show');
            modal.style.display = 'none';
            console.log('✅ Modal closed');
        }
    }
    
    // Set up event delegation for modal actions
    function setupModalEventHandlers() {
        // Use event delegation on document body
        document.body.addEventListener('click', function(e) {
            // Check if clicked element has a data-action attribute
            const action = e.target.dataset?.action || e.target.closest('[data-action]')?.dataset?.action;
            
            if (action === 'closeDetailedModal') {
                // Close button clicked
                e.preventDefault();
                e.stopPropagation();
                closeModal();
                console.log('✅ Modal closed via button');
            } else if (action === 'closeDetailedModalOnOverlay') {
                // Check if click was on the overlay (not the modal content)
                if (e.target.id === 'detailedItemModal' || e.target.classList.contains('detailed-item-modal')) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeModal();
                    console.log('✅ Modal closed via overlay click');
                }
            }
        });
        
        console.log('✅ Modal event handlers set up');
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupModalEventHandlers);
    } else {
        setupModalEventHandlers();
    }
    
    // Also expose the close function globally for other scripts
    window.closeDetailedModal = closeModal;
    window.closeDetailedModalComponent = closeModal;
    
    console.log('✅ Detailed modal event handlers initialized');
})();
