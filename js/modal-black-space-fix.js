/**
 * Fix for black space below item details modal
 * This script removes the black background from the modal overlay
 */

(function() {
    console.log('🔧 Modal black space fix initializing...');
    
    // Function to fix modal styling
    function fixModalStyling() {
        const modal = document.getElementById('detailedItemModal');
        if (!modal) return;
        
        // Remove Tailwind classes that add black background
        modal.classList.remove('bg-black', 'bg-opacity-50');
        
        // Apply custom inline styles to override any remaining issues
        modal.style.background = 'rgba(0, 0, 0, 0.5)';
        modal.style.padding = '0';
        modal.style.overflow = 'hidden';
        
        // Ensure the modal fills viewport exactly
        modal.style.height = '100vh';
        modal.style.maxHeight = '100vh';
        
        // Find the white container and adjust it
        const container = modal.querySelector('.detailed-item-modal-container, .bg-white');
        if (container) {
            container.style.maxHeight = '95vh';
            container.style.height = 'auto';
            container.style.margin = 'auto';
        }
        
        console.log('✅ Modal styling fixed');
    }
    
    // Watch for when modal is shown
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                const modal = document.getElementById('detailedItemModal');
                if (modal && modal.classList.contains('show')) {
                    setTimeout(fixModalStyling, 10);
                }
            }
        });
    });
    
    // Start observing
    const modal = document.getElementById('detailedItemModal');
    if (modal) {
        observer.observe(modal, {
            attributes: true,
            attributeFilter: ['class']
        });
        
        // Fix immediately if modal is already showing
        if (modal.classList.contains('show')) {
            fixModalStyling();
        }
    }
    
    // Also listen for the custom event
    document.addEventListener('detailedModalShown', fixModalStyling);
    
    // Periodically check and fix (fallback)
    setInterval(() => {
        const modal = document.getElementById('detailedItemModal');
        if (modal && modal.classList.contains('show')) {
            // Only fix if black background is detected
            if (modal.classList.contains('bg-black') || 
                modal.style.background.includes('black') ||
                getComputedStyle(modal).backgroundColor.includes('0, 0, 0')) {
                fixModalStyling();
            }
        }
    }, 500);
    
    console.log('✅ Modal black space fix initialized');
})();
