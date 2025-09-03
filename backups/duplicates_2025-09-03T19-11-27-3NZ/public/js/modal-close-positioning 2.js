/**
 * Modal Close Button Positioning System
 * Automatically positions modal close buttons based on CSS variables
 */

// Initialize modal close button positioning
function initializeModalClosePositioning() {
    // Get the current position setting from CSS variables
    const position = getComputedStyle(document.documentElement)
        .getPropertyValue('--modal-close-position')
        .trim();
    
    // Apply position classes to all modal close buttons
    const closeButtons = document.querySelectorAll('.modal-close');
    closeButtons.forEach(button => {
        // Remove any existing position classes
        button.classList.remove(
            'position-top-left',
            'position-top-center', 
            'position-bottom-right',
            'position-bottom-left'
        );
        
        // Apply the appropriate position class
        if (position && position !== 'top-right') {
            button.classList.add(`position-${position}`);
        }
    });
}

// Apply positioning when DOM is loaded
document.addEventListener('DOMContentLoaded', initializeModalClosePositioning);

// Re-apply positioning when CSS variables change (for admin settings)
function updateModalClosePositioning() {
    initializeModalClosePositioning();
}

// Observer to watch for new modal close buttons being added
const modalObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1) { // Element node
                // Check if the added node contains modal close buttons
                const closeButtons = node.querySelectorAll ? node.querySelectorAll('.modal-close') : [];
                if (closeButtons.length > 0) {
                    initializeModalClosePositioning();
                }
                // Also check if the node itself is a modal close button
                if (node.classList && node.classList.contains('modal-close')) {
                    initializeModalClosePositioning();
                }
            }
        });
    });
});

// Start observing the document for changes
modalObserver.observe(document.body, {
    childList: true,
    subtree: true
});

// Export for use in other scripts
window.updateModalClosePositioning = updateModalClosePositioning;