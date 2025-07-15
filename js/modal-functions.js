/**
 * WhimsicalFrog Modal Management Functions
 * Centralized JavaScript functions to eliminate duplication
 * Generated: 2025-07-01 23:42:24
 */

// Modal Management Dependencies
// Requires: modal-manager.js, global-notifications.js



// Function to update detailed modal content
function updateDetailedModalContent(item, images) {
    // Update basic info
    const titleElement = document.querySelector('#detailedItemModal h2');
    if (titleElement) titleElement.textContent = item.name;
    
    const skuElement = document.querySelector('#detailedItemModal .text-xs');
    if (skuElement) skuElement.textContent = `${item.category || 'Product'} • SKU: ${item.sku}`;
    
    const priceElement = document.getElementById('detailedCurrentPrice');
    if (priceElement) priceElement.textContent = `$${parseFloat(item.retailPrice || 0).toFixed(2)}`;
    
    // Update main image
    const mainImage = document.getElementById('detailedMainImage');
    if (mainImage) {
        const imageUrl = images.length > 0 ? images[0].image_path : `images/items/${item.sku}A.webp`;
        mainImage.src = imageUrl;
        mainImage.alt = item.name;
        
        // Add error handling for image loading
        mainImage.onerror = function() {
            if (!this.src.includes('placeholder')) {
                this.src = 'images/items/placeholder.webp';
            }
        }
    }
    
    // Update stock status
    const stockBadge = document.querySelector('#detailedItemModal .bg-green-100, #detailedItemModal .bg-red-100');
    if (stockBadge && stockBadge.querySelector('svg')) {
        const stockLevel = parseInt(item.stockLevel || 0);
        if (stockLevel > 0) {
            stockBadge.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800';
            stockBadge.innerHTML = `
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                </svg>
                In Stock (${stockLevel} available)
            `;
        } else {
            stockBadge.className = 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800';
            stockBadge.innerHTML = `
                <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
                Out of Stock
            `;
        }
    }
    
    // Set quantity max value
    const quantityInput = document.getElementById('detailedQuantity');
    if (quantityInput) {
        quantityInput.max = item.stockLevel || 1;
        quantityInput.value = 1;
    }
}



// Modal close functions (matching the detailed modal component)
function closeDetailedModal() {
    const modal = document.getElementById('detailedItemModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.classList.remove('modal-open-overflow-hidden'); // Restore scrolling
    }
}



function closeDetailedModalOnOverlay(event) {
    if (event.target === event.currentTarget) {
        closeDetailedModal();
    }
}

