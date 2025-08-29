/**
 * WhimsicalFrog Global Image Viewer System
 * Provides full-screen image viewing with brand styling
 */

// Image viewer variables
<<<<<<< HEAD
let currentViewerImages = [];
let currentViewerIndex = 0;
=======
var currentViewerImages = (typeof currentViewerImages !== 'undefined') ? currentViewerImages : [];
var currentViewerIndex = (typeof currentViewerIndex !== 'undefined') ? currentViewerIndex : 0;
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)

/**
 * Open the image viewer with given image path and product name
 * @param {string} imagePath - Path to the image
 * @param {string} productName - Name of the product
 * @param {Array} allImages - Optional array of all images for navigation
 */
function openImageViewer(imagePath, productName, allImages = null) {
    console.log('Opening image viewer:', { imagePath, productName, allImages });
    
    // Initialize images array
    currentViewerImages = [];
    currentViewerIndex = 0;
    
    if (allImages && (allImages.length > 0 || (allImages instanceof HTMLCollection && allImages.length > 0))) {
        // Convert HTMLCollection to Array if needed
        const imagesArray = allImages instanceof HTMLCollection ? Array.from(allImages) : allImages;
        
        // Use provided images array
        currentViewerImages = imagesArray.map(img => ({
            src: img.image_path || img.src || img,
            alt: img.alt_text || img.alt || productName
        }));
        
        // Find current image index
        const currentIndex = currentViewerImages.findIndex(img => img.src === imagePath);
        if (currentIndex !== -1) {
            currentViewerIndex = currentIndex;
        }
    } else {
        // Try to get images from the current modal context
        const modal = document.getElementById('detailedItemModal');
        if (modal) {
            const thumbnails = modal.querySelectorAll('.overflow-x-auto img');
            if (thumbnails.length > 0) {
                // Multiple images - build array from thumbnails
                thumbnails.forEach((thumbnail, index) => {
                    currentViewerImages.push({
                        src: thumbnail.src,
                        alt: thumbnail.alt || productName
                    });
                    if (thumbnail.src === imagePath) {
                        currentViewerIndex = index;
                    }
                });
            }
        }
        
        // If no images found, use single image
        if (currentViewerImages.length === 0) {
            currentViewerImages = [{
                src: imagePath,
                alt: productName
            }];
        }
    }
    
    // Create or get the viewer modal
    let viewerModal = document.getElementById('imageViewerModal');
    if (!viewerModal) {
        createImageViewerModal();
        viewerModal = document.getElementById('imageViewerModal');
    }
    
    // Set up the viewer elements
    const viewerImage = document.getElementById('viewerImage');
    const viewerTitle = document.getElementById('viewerImageTitle');
    const viewerCounter = document.getElementById('viewerImageCounter');
    
    if (!viewerImage) {
        console.error('Image viewer elements not found');
        return;
    }
    
    // Update viewer content
    viewerImage.src = currentViewerImages[currentViewerIndex].src;
    viewerImage.alt = currentViewerImages[currentViewerIndex].alt;
    
    if (viewerTitle) {
        viewerTitle.textContent = productName;
    }
    
    if (viewerCounter && currentViewerImages.length > 1) {
        viewerCounter.textContent = `${currentViewerIndex + 1} of ${currentViewerImages.length}`;
<<<<<<< HEAD
        viewerCounter.style.display = 'block';
    } else if (viewerCounter) {
        viewerCounter.style.display = 'none';
=======
        viewerCounter.classList.remove('image-viewer-controls-hidden');
        viewerCounter.classList.add('image-viewer-controls-visible');
    } else if (viewerCounter) {
        viewerCounter.classList.remove('image-viewer-controls-visible');
        viewerCounter.classList.add('image-viewer-controls-hidden');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    }
    
    // Update navigation buttons visibility
    const prevBtn = document.getElementById('viewerPrevBtn');
    const nextBtn = document.getElementById('viewerNextBtn');
    if (prevBtn && nextBtn) {
        const showNav = currentViewerImages.length > 1;
<<<<<<< HEAD
        prevBtn.style.display = showNav ? 'flex' : 'none';
        nextBtn.style.display = showNav ? 'flex' : 'none';
    }
    
    // Show the viewer
    viewerModal.style.display = 'flex';
    document.body.classList.add('modal-open');
    document.documentElement.classList.add('modal-open');
    
=======
        const visibilityClass = showNav ? 'image-viewer-controls-visible' : 'image-viewer-controls-hidden';
        const hideClass = showNav ? 'image-viewer-controls-hidden' : 'image-viewer-controls-visible';
        
        prevBtn.classList.remove(hideClass);
        prevBtn.classList.add(visibilityClass);
        nextBtn.classList.remove(hideClass);
        nextBtn.classList.add(visibilityClass);
    }
    
    // Show the viewer using CSS classes only
    viewerModal.classList.remove('image-viewer-modal-closed');
    viewerModal.classList.add('image-viewer-modal-open');
    // Ensure any lingering hidden class is removed
    viewerModal.classList.remove('hidden');
    viewerModal.style.display = 'flex';
    
    // Force z-index as backup while we debug the CSS class system
    viewerModal.style.zIndex = '2700';
    
    // Add CSS class to body to manage z-index hierarchy
    document.body.classList.add('modal-open', 'image-viewer-open');
    document.documentElement.classList.add('modal-open');
    
    // Debug logging
    console.log('🖼️ Image viewer opened. Classes added:', {
        bodyClasses: document.body.className,
        viewerModalZIndex: viewerModal.style.zIndex,
        viewerModalClasses: viewerModal.className
    });
    
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    // Add keyboard support
    document.addEventListener('keydown', handleImageViewerKeyboard);
}

/**
 * Close the image viewer
 */
function closeImageViewer() {
    const viewerModal = document.getElementById('imageViewerModal');
    if (viewerModal) {
<<<<<<< HEAD
        viewerModal.style.display = 'none';
    }
    
    // Restore scrolling
    document.body.classList.remove('modal-open');
    document.documentElement.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.position = '';
    document.body.style.width = '';
    document.body.style.height = '';
    document.documentElement.style.overflow = '';
=======
        viewerModal.classList.remove('image-viewer-modal-open');
        viewerModal.classList.add('image-viewer-modal-closed');
        viewerModal.style.display = 'none';
    }
    
    // Remove CSS classes to restore z-index hierarchy
    document.body.classList.remove('modal-open', 'image-viewer-open');
    document.documentElement.classList.remove('modal-open');
    document.body.classList.remove('modal-open-overflow-hidden', 'modal-open-position-fixed');
    document.documentElement.classList.remove('modal-open-overflow-hidden');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    
    // Remove keyboard support
    document.removeEventListener('keydown', handleImageViewerKeyboard);
}

/**
 * Navigate to previous image
 */
function previousImage() {
    if (currentViewerImages.length <= 1) return;
    
    currentViewerIndex = (currentViewerIndex - 1 + currentViewerImages.length) % currentViewerImages.length;
    updateViewerImage();
}

/**
 * Navigate to next image
 */
function nextImage() {
    if (currentViewerImages.length <= 1) return;
    
    currentViewerIndex = (currentViewerIndex + 1) % currentViewerImages.length;
    updateViewerImage();
}

/**
 * Update the viewer image and counter
 */
function updateViewerImage() {
    const viewerImage = document.getElementById('viewerImage');
    const viewerCounter = document.getElementById('viewerImageCounter');
    
    if (!viewerImage || !currentViewerImages[currentViewerIndex]) return;
    
    viewerImage.src = currentViewerImages[currentViewerIndex].src;
    viewerImage.alt = currentViewerImages[currentViewerIndex].alt;
    
    if (viewerCounter && currentViewerImages.length > 1) {
        viewerCounter.textContent = `${currentViewerIndex + 1} of ${currentViewerImages.length}`;
    }
}

/**
 * Handle keyboard navigation
 * @param {KeyboardEvent} event 
 */
function handleImageViewerKeyboard(event) {
    switch(event.key) {
        case 'Escape':
            closeImageViewer();
            break;
        case 'ArrowLeft':
            event.preventDefault();
            previousImage();
            break;
        case 'ArrowRight':
            event.preventDefault();
            nextImage();
            break;
    }
}

/**
 * Create the image viewer modal HTML structure
 */
function createImageViewerModal() {
    const modalHTML = `
<<<<<<< HEAD
    <div id="imageViewerModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center p-4" style="display: none; z-index: 1000;">
        <div class="relative w-full h-full flex items-center justify-center">
            <!-- Close button -->
            <button id="viewerCloseBtn" onclick="closeImageViewer()" 
=======
    <div id="imageViewerModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center p-4 image-viewer-modal image-viewer-modal-closed">
        <div class="relative w-full h-full flex items-center justify-center">
            <!- Close button ->
            <button id="viewerCloseBtn" data-action="closeImageViewer"
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    class="absolute top-4 right-4 text-white hover:text-gray-300 text-4xl font-bold z-10 bg-black bg-opacity-50 rounded-full w-12 h-12 flex items-center justify-center transition-colors">
                &times;
            </button>
            
<<<<<<< HEAD
            <!-- Previous button -->
            <button id="viewerPrevBtn" onclick="previousImage()" 
=======
            <!- Previous button ->
            <button id="viewerPrevBtn" data-action="previousImage"
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 text-3xl font-bold z-10 bg-black bg-opacity-50 rounded-full w-12 h-12 flex items-center justify-center transition-colors">
                &#8249;
            </button>
            
<<<<<<< HEAD
            <!-- Next button -->
            <button id="viewerNextBtn" onclick="nextImage()" 
=======
            <!- Next button ->
            <button id="viewerNextBtn" data-action="nextImage"
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                    class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 text-3xl font-bold z-10 bg-black bg-opacity-50 rounded-full w-12 h-12 flex items-center justify-center transition-colors">
                &#8250;
            </button>
            
<<<<<<< HEAD
            <!-- Large image -->
            <img id="viewerImage" src="" alt="" class="max-w-full max-h-full object-contain">
            
            <!-- Image info -->
=======
            <!- Large image ->
            <img id="viewerImage" src="" alt="" class="max-w-full max-h-full object-contain">
            
            <!- Image info ->
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
            <div class="absolute bottom-4 left-1/2 transform -translate-x-1/2 text-white text-center bg-black bg-opacity-50 px-4 py-2 rounded-lg">
                <p id="viewerImageTitle" class="font-medium"></p>
                <p id="viewerImageCounter" class="text-sm opacity-75"></p>
            </div>
        </div>
    </div>`;
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Add click-outside-to-close functionality
    const modal = document.getElementById('imageViewerModal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeImageViewer();
            }
        });
    }
}

/**
 * Create a brand-styled hover tooltip for "Click to enlarge"
 * @param {HTMLElement} container - The container element to add the tooltip to
 */
function addEnlargeTooltip(container) {
    if (!container) return;
    
    // Remove existing tooltip if any
    const existingTooltip = container.querySelector('.enlarge-tooltip');
    if (existingTooltip) {
        existingTooltip.remove();
    }
    
    // Create tooltip element
    const tooltip = document.createElement('div');
    tooltip.className = 'enlarge-tooltip';
    tooltip.textContent = '🔍 Click to enlarge';
    
<<<<<<< HEAD
    // Apply brand styling
    tooltip.style.cssText = `
        position: absolute;
        top: 12px;
        right: 12px;
        background-color: #87ac3a;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 500;
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: 10;
        pointer-events: none;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        white-space: nowrap;
    `;
=======
    // Apply brand styling with CSS class
    tooltip.classList.add('enlarge-tooltip-styled');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    
    container.appendChild(tooltip);
    
    // Show/hide on hover
    container.addEventListener('mouseenter', () => {
<<<<<<< HEAD
        tooltip.style.opacity = '1';
    });
    
    container.addEventListener('mouseleave', () => {
        tooltip.style.opacity = '0';
=======
        tooltip.classList.add('tooltip-visible');
        tooltip.classList.remove('tooltip-hidden');
    });
    
    container.addEventListener('mouseleave', () => {
        tooltip.classList.remove('tooltip-visible');
        tooltip.classList.add('tooltip-hidden');
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    });
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Create the image viewer modal if it doesn't exist
    if (!document.getElementById('imageViewerModal')) {
        createImageViewerModal();
    }
    
    // Add enlarge tooltips to existing clickable images
    const clickableImages = document.querySelectorAll('[onclick*="openImageViewer"], .image-viewer-trigger');
    clickableImages.forEach(img => {
        const container = img.closest('.relative') || img.parentElement;
        if (container && container.style.position !== 'static') {
            addEnlargeTooltip(container);
        }
    });
});

// Make functions globally available
window.openImageViewer = openImageViewer;
window.closeImageViewer = closeImageViewer;
window.previousImage = previousImage;
window.nextImage = nextImage;
window.addEnlargeTooltip = addEnlargeTooltip;

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        openImageViewer,
        closeImageViewer,
        previousImage,
        nextImage,
        addEnlargeTooltip
    };
} 