// --- Start of js/api-client.js --- 

/*
 * Centralized API client wrapper for WhimsicalFrog
 * Provides apiGet() and apiPost() helpers and encourages consistent error handling.
 */
(function (global) {
    'use strict';

    const API_BASE = '/api/';

    // Preserve original fetch for internal use / fallback
    const nativeFetch = global.fetch.bind(global);

    function buildUrl(path) {
        if (path.startsWith('http://') || path.startsWith('https://')) {
            return path; // absolute URL
        }
        if (path.startsWith('/')) {
            return path; // already root-relative (e.g. /api/foo.php)
        }
        // Relative path like 'get_data.php'
        return API_BASE + path.replace(/^\/?/, '');
    }

    async function apiRequest(method, path, data = null, options = {}) {
        const url = buildUrl(path);
        const config = {
            method,
            headers: {
                'Content-Type': 'application/json',
                ...(options.headers || {})
            },
            credentials: 'same-origin',
            ...options
        };

        if (method !== 'GET' && data !== null) {
            config.body = JSON.stringify(data);
        }

        const response = await nativeFetch(url, config);

        // Attempt to parse JSON; fall back to text
        const contentType = response.headers.get('content-type') || '';
        const parseBody = contentType.includes('application/json')
            ? response.json.bind(response)
            : response.text.bind(response);

        if (!response.ok) {
            const body = await parseBody();
            const message = typeof body === 'string' ? body : JSON.stringify(body);
            throw new Error(`API error ${response.status}: ${message}`);
        }

        return parseBody();
    }

    function apiGet(path, options = {}) {
        return apiRequest('GET', path, null, options);
    }

    function apiPost(path, data = null, options = {}) {
        return apiRequest('POST', path, data, options);
    }

    // For FormData or sendBeacon payloads
    function apiPostForm(path, formData, options = {}) {
        const url = buildUrl(path);
        const config = {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            ...options
        };
        return nativeFetch(url, config).then(r => r.ok ? r.text() : Promise.reject(new Error(`API error ${r.status}`)));
    }

    // Expose helpers globally
    global.apiGet = apiGet;
    global.apiPost = apiPost;
    global.apiPostForm = apiPostForm;

    // Monkey-patch fetch to warn about direct API calls.
    global.fetch = function (input, init = {}) {
        const url = typeof input === 'string' ? input : input.url;
        if (/\/api\//.test(url)) {
            console.warn('⚠️  Consider using apiGet/apiPost instead of direct fetch for API calls:', url);
        }
        return nativeFetch(input, init);
    };

    console.log('[api-client] Initialized');
})(window);


// --- End of js/api-client.js --- 

// --- Start of js/ui-manager.js --- 

/**
 * WhimsicalFrog UI Management and Indicators
 * Centralized JavaScript functions to eliminate duplication
 * Generated: 2025-07-01 23:31:50
 */

// UI Management Dependencies
// Requires: global-notifications.js

                            
                            // Function to force hide auto-save indicators
                            function hideAutoSaveIndicator() {
                                const indicators = document.querySelectorAll('.auto-save-indicator, .progress-bar, .loading-indicator');
                                                indicators.forEach(indicator => {
                    indicator.classList.add('indicator-hidden');
                });
                                
                                // Set timeout to double-check
                                                setTimeout(() => {
                    indicators.forEach(indicator => {
                        indicator.classList.add('indicator-hidden');
                    });
                }, 100);
                            }


// Auto-save indicator functions
function showAutoSaveIndicator() {
    const indicator = document.getElementById('dashboardAutoSaveIndicator');
    if (indicator) {
        indicator.classList.remove('hidden');
        indicator.textContent = '💾 Auto-saving...';
        indicator.className = 'px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm';
    }
}



// --- End of js/ui-manager.js --- 

// --- Start of js/image-viewer.js --- 

/**
 * WhimsicalFrog Global Image Viewer System
 * Provides full-screen image viewing with brand styling
 */

// Image viewer variables
var currentViewerImages = (typeof currentViewerImages !== 'undefined') ? currentViewerImages : [];
var currentViewerIndex = (typeof currentViewerIndex !== 'undefined') ? currentViewerIndex : 0;

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
        viewerCounter.classList.remove('image-viewer-controls-hidden');
        viewerCounter.classList.add('image-viewer-controls-visible');
    } else if (viewerCounter) {
        viewerCounter.classList.remove('image-viewer-controls-visible');
        viewerCounter.classList.add('image-viewer-controls-hidden');
    }
    
    // Update navigation buttons visibility
    const prevBtn = document.getElementById('viewerPrevBtn');
    const nextBtn = document.getElementById('viewerNextBtn');
    if (prevBtn && nextBtn) {
        const showNav = currentViewerImages.length > 1;
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
    viewerModal.classList.add('z-image-viewer');
    
    // Add CSS class to body to manage z-index hierarchy
    document.body.classList.add('modal-open', 'image-viewer-open');
    document.documentElement.classList.add('modal-open');
    
    // Debug logging
    console.log('🖼️ Image viewer opened. Classes added:', {
        bodyClasses: document.body.className,
        viewerModalZIndex: getComputedStyle(viewerModal).zIndex,
        viewerModalClasses: viewerModal.className
    });
    
    // Add keyboard support
    document.addEventListener('keydown', handleImageViewerKeyboard);
}

/**
 * Close the image viewer
 */
function closeImageViewer() {
    const viewerModal = document.getElementById('imageViewerModal');
    if (viewerModal) {
        viewerModal.classList.remove('image-viewer-modal-open');
        viewerModal.classList.add('image-viewer-modal-closed');
        viewerModal.style.display = 'none';
    }
    
    // Remove CSS classes to restore z-index hierarchy
    document.body.classList.remove('modal-open', 'image-viewer-open');
    document.documentElement.classList.remove('modal-open');
    document.body.classList.remove('modal-open-overflow-hidden', 'modal-open-position-fixed');
    document.documentElement.classList.remove('modal-open-overflow-hidden');
    
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
    <div id="imageViewerModal" class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center p-4 image-viewer-modal image-viewer-modal-closed">
        <div class="relative w-full h-full flex items-center justify-center">
            <!- Close button ->
            <button id="viewerCloseBtn" data-action="closeImageViewer"
                    class="absolute top-4 right-4 text-white hover:text-gray-300 text-4xl font-bold z-10 bg-black bg-opacity-50 rounded-full w-12 h-12 flex items-center justify-center transition-colors">
                &times;
            </button>
            
            <!- Previous button ->
            <button id="viewerPrevBtn" data-action="previousImage"
                    class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 text-3xl font-bold z-10 bg-black bg-opacity-50 rounded-full w-12 h-12 flex items-center justify-center transition-colors">
                &#8249;
            </button>
            
            <!- Next button ->
            <button id="viewerNextBtn" data-action="nextImage"
                    class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 text-3xl font-bold z-10 bg-black bg-opacity-50 rounded-full w-12 h-12 flex items-center justify-center transition-colors">
                &#8250;
            </button>
            
            <!- Large image ->
            <img id="viewerImage" src="" alt="" class="max-w-full max-h-full object-contain">
            
            <!- Image info ->
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
    
    // Apply brand styling with CSS class
    tooltip.classList.add('enlarge-tooltip-styled');
    
    container.appendChild(tooltip);
    
    // Show/hide on hover
    container.addEventListener('mouseenter', () => {
        tooltip.classList.add('tooltip-visible');
        tooltip.classList.remove('tooltip-hidden');
    });
    
    container.addEventListener('mouseleave', () => {
        tooltip.classList.remove('tooltip-visible');
        tooltip.classList.add('tooltip-hidden');
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

// --- End of js/image-viewer.js --- 

// --- Start of js/global-notifications.js --- 

/**
 * WhimsicalFrog Unified Notification System
 * Consolidated branded notification system with fallback handling
 * Replaces both global-notifications.js and modules/notification-system.js
 */

class WhimsicalFrogNotifications {
    constructor() {
        this.notifications = new Map();
        this.nextId = 1;
        this.container = null;
        this.initialized = false;
        this.init();
    }

    init() {
        if (this.initialized) return;
        
        // Create notification container if it doesn't exist
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.id = 'wf-notification-container';
            this.container.className = 'wf-notification-container';
            
            // Use CSS variables for container styling with fallbacks
            this.container.style.cssText = `
                position: var(-notification-container-position, fixed);
                top: var(-notification-container-top, 24px);
                right: var(-notification-container-right, 24px);
                z-index: var(-notification-container-zindex, 2147483647);
                pointer-events: none;
                max-width: var(-notification-container-width, 420px);
                width: 100%;
            `;
            document.body.appendChild(this.container);
        }
        
        this.initialized = true;
        console.log('✅ WhimsicalFrog Unified Notification System initialized');
    }

    show(message, type = 'info', options = {}) {
        if (!this.initialized) {
            console.warn('Notification system not initialized, initializing now...');
            this.init();
        }

        const {
            title = null,
            duration = this.getDefaultDuration(type),
            persistent = false,
            actions = null,
            autoHide = true
        } = options;

        const id = this.nextId++;
        const notification = this.createNotification(id, message, type, title, persistent, actions);
        
        this.container.appendChild(notification);
        this.notifications.set(id, notification);

        // Trigger animation
        requestAnimationFrame(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'var(-notification-transform-show, translateX(0) scale(1))';
        });

        // Auto-remove if not persistent and autoHide is enabled
        if (!persistent && autoHide && duration > 0) {
            setTimeout(() => {
                this.remove(id);
            }, duration);
        }

        return id;
    }

    createNotification(id, message, type, title, persistent, actions) {
        const notification = document.createElement('div');
        notification.className = `wf-notification wf-${type}-notification`;
        notification.dataset.id = id;
        notification.dataset.type = type;
        
        // Add click-to-dismiss functionality
        notification.addEventListener('click', (event) => {
            console.log(`Notification ${id} clicked - removing`);
            event.preventDefault();
            event.stopPropagation();
            this.remove(id);
        });

        // Prevent event bubbling on button clicks
        notification.addEventListener('mousedown', (event) => {
            console.log(`Notification ${id} mousedown event`);
        });

        notification.title = 'Click to dismiss';

        // Create HTML content
        notification.innerHTML = `
            <div class="wf-notification-content">
                <div class="wf-notification-icon">
                    ${this.getTypeIcon(type)}
                </div>
                <div class="wf-notification-body">
                    ${title ? `<div class="wf-notification-title">${title}</div>` : ''}
                    <div class="wf-notification-message">
                        ${message}
                    </div>
                    ${actions ? this.createActions(actions) : ''}
                </div>
                ${!persistent ? `
                    <button class="wf-notification-close" onclick="event.stopPropagation(); window.wfNotifications.remove(${id})">&times;</button>
                ` : ''}
            </div>
        `;

        // Add pulse effect for emphasis on certain types
        if (type === 'warning' || type === 'error') {
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.classList.add('pulse');
                    setTimeout(() => {
                        notification.classList.remove('pulse');
                    }, 150);
                }
            }, 200);
        }

        return notification;
    }

    createActions(actions) {
        if (!actions || !Array.isArray(actions)) return '';
        
        return `
            <div class="wf-notification-actions">
                ${actions.map(action => `
                    <button onclick="${action.onClick}" class="wf-notification-action ${action.style === 'primary' ? 'primary' : 'secondary'}">
                        ${action.text}
                    </button>
                `).join('')}
            </div>
        `;
    }

    getTypeConfig(type) {
        const configs = {
            success: {
                background: '#87ac3a',
                border: '#6b8e23',
                color: 'white',
                titleColor: 'white',
                closeColor: 'white',
                shadow: 'rgba(135, 172, 58, 0.3)',
                icon: '✅'
            },
            error: {
                background: 'linear-gradient(135deg, #fee2e2, #fecaca)',
                border: '#ef4444',
                color: '#7f1d1d',
                titleColor: '#dc2626',
                closeColor: '#ef4444',
                shadow: 'rgba(239, 68, 68, 0.2)',
                icon: '❌'
            },
            warning: {
                background: 'linear-gradient(135deg, #fef3c7, #fde68a)',
                border: '#f59e0b',
                color: '#92400e',
                titleColor: '#d97706',
                closeColor: '#f59e0b',
                shadow: 'rgba(245, 158, 11, 0.2)',
                icon: '⚠️'
            },
            info: {
                background: 'linear-gradient(135deg, #dbeafe, #bfdbfe)',
                border: '#3b82f6',
                color: '#1e3a8a',
                titleColor: '#2563eb',
                closeColor: '#3b82f6',
                shadow: 'rgba(59, 130, 246, 0.2)',
                icon: 'ℹ️'
            },
            validation: {
                background: 'linear-gradient(135deg, #fef3c7, #fde68a)',
                border: '#f59e0b',
                color: '#92400e',
                titleColor: '#d97706',
                closeColor: '#f59e0b',
                shadow: 'rgba(245, 158, 11, 0.2)',
                icon: '⚠️'
            }
        };

        return configs[type] || configs.info;
    }

    getDefaultDuration(type) {
        // All notifications now auto-dismiss after 5 seconds (as requested)
        return 5000;
    }

    remove(id) {
        console.log(`Attempting to remove notification ${id}`);
        const notification = this.notifications.get(id);
        if (notification && notification.parentElement) {
            console.log(`Removing notification ${id} from DOM`);
            notification.style.opacity = '0';
            notification.style.transform = 'var(-notification-transform-enter, translateX(100%) scale(0.9))';
            
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                    console.log(`Notification ${id} removed from DOM`);
                }
                this.notifications.delete(id);
                console.log(`Notification ${id} deleted from memory`);
            }, 400);
        } else {
            console.log(`Notification ${id} not found or already removed`);
        }
    }

    removeAll() {
        this.notifications.forEach((notification, id) => {
            this.remove(id);
        });
    }

    // Convenience methods
    success(message, options = {}) {
        return this.show(message, 'success', options);
    }

    error(message, options = {}) {
        return this.show(message, 'error', options);
    }

    warning(message, options = {}) {
        return this.show(message, 'warning', options);
    }

    info(message, options = {}) {
        return this.show(message, 'info', options);
    }

    validation(message, options = {}) {
        return this.show(message, 'validation', options);
    }
    
    // Helper methods for fallback styling
    getFallbackColor(type, property) {
        const fallbacks = {
            success: {
                background: 'linear-gradient(135deg, #87ac3a, #6b8e23)',
                border: '#556B2F',
                text: '#ffffff'
            },
            error: {
                background: 'linear-gradient(135deg, #dc2626, #b91c1c)',
                border: '#991b1b',
                text: '#ffffff'
            },
            warning: {
                background: 'linear-gradient(135deg, #f59e0b, #d97706)',
                border: '#b45309',
                text: '#ffffff'
            },
            info: {
                background: 'linear-gradient(135deg, #3b82f6, #2563eb)',
                border: '#1d4ed8',
                text: '#ffffff'
            },
            validation: {
                background: 'linear-gradient(135deg, #f59e0b, #d97706)',
                border: '#b45309',
                text: '#ffffff'
            }
        };
        
        return fallbacks[type]?.[property] || fallbacks.info[property];
    }
    
    getFallbackShadow(type) {
        const shadows = {
            success: '0 12px 28px rgba(135, 172, 58, 0.35), 0 4px 8px rgba(135, 172, 58, 0.15)',
            error: '0 12px 28px rgba(220, 38, 38, 0.35), 0 4px 8px rgba(220, 38, 38, 0.15)',
            warning: '0 12px 28px rgba(245, 158, 11, 0.35), 0 4px 8px rgba(245, 158, 11, 0.15)',
            info: '0 12px 28px rgba(59, 130, 246, 0.35), 0 4px 8px rgba(59, 130, 246, 0.15)',
            validation: '0 12px 28px rgba(245, 158, 11, 0.35), 0 4px 8px rgba(245, 158, 11, 0.15)'
        };
        
        return shadows[type] || shadows.info;
    }
    
    getTypeIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️',
            validation: '⚠️'
        };
        
        return icons[type] || icons.info;
    }
}

// Initialize unified notification system
window.wfNotifications = new WhimsicalFrogNotifications();

// Consolidated global functions - prevent duplicate registrations
function registerNotificationFunctions() {
    // Only register if not already registered
    if (!window._wfNotificationFunctionsRegistered) {
        // Main notification functions
        window.showNotification = (message, type = 'info', options = {}) => {
            return window.wfNotifications.show(message, type, options);
        };

        window.showSuccess = (message, options = {}) => {
            return window.wfNotifications.success(message, options);
        };

        window.showError = (message, options = {}) => {
            return window.wfNotifications.error(message, options);
        };

        window.showWarning = (message, options = {}) => {
            return window.wfNotifications.warning(message, options);
        };

        window.showInfo = (message, options = {}) => {
            return window.wfNotifications.info(message, options);
        };

        window.showValidation = (message, options = {}) => {
            return window.wfNotifications.validation(message, options);
        };

        window._wfNotificationFunctionsRegistered = true;
        console.log('📢 WhimsicalFrog notification functions registered globally');
    }
}

// Register functions immediately
registerNotificationFunctions();

// Override alert and showToast functions (immediate execution)
if (!window._wfAlertOverridden) {
    window.alert = function(message) {
        // Detect if this is a cart-related message
        if (message.includes('added to your cart') || message.includes('added to cart')) {
            window.wfNotifications.success(message);
        } else {
            window.wfNotifications.info(message);
        }
    };
    window._wfAlertOverridden = true;
}

// Enhanced showToast function for backward compatibility
if (!window.showToast) {
    window.showToast = (typeOrMessage, messageOrType = null, options = {}) => {
        // Handle both (type, message) and (message, type) parameter orders
        let message, type;
        
        if (messageOrType === null) {
            message = typeOrMessage;
            type = 'info';
        } else if (typeof typeOrMessage === 'string' && ['success', 'error', 'warning', 'info'].includes(typeOrMessage)) {
            type = typeOrMessage;
            message = messageOrType;
        } else {
            message = typeOrMessage;
            type = messageOrType || 'info';
        }
        
        return window.wfNotifications.show(message, type, options);
    };
}

// Additional utility functions
window.hideNotification = (id) => window.wfNotifications.remove(id);
window.clearNotifications = () => window.wfNotifications.removeAll();

// Cart notification integration
if (window.cart && typeof window.cart === 'object') {
    window.cart.showNotification = (message) => window.wfNotifications.success(message);
    window.cart.showErrorNotification = (message) => window.wfNotifications.error(message);
    window.cart.showValidationError = (message) => window.wfNotifications.validation(message);
}

// Final system ready message
console.log('🎉 WhimsicalFrog Unified Notification System ready!');


// --- End of js/global-notifications.js --- 

// --- Start of js/notification-messages.js --- 

// Centralized notification messages configuration
// This file can be easily customized by admins without touching core code

window.NotificationMessages = {
    // Success messages
    success: {
        itemSaved: 'Item saved successfully!',
        itemDeleted: 'Item deleted successfully!',
        imageUploaded: 'Image uploaded successfully!',
        priceUpdated: 'Price updated successfully!',
        stockSynced: 'Stock levels synchronized!',
        templateSaved: 'Template saved successfully!',
        aiProcessingComplete: 'AI processing completed! Images have been updated.',
        marketingGenerated: '🎯 AI content generated successfully!',
        costBreakdownApplied: '✅ Cost breakdown applied and saved!',
        settingsSaved: 'Settings saved successfully!'
    },
    
    // Error messages
    error: {
        itemNotFound: 'Item not found. Please refresh the page.',
        uploadFailed: 'Upload failed. Please try again.',
        invalidInput: 'Please check your input and try again.',
        networkError: 'Network error occurred. Please check your connection.',
        aiProcessingFailed: 'AI processing failed. Please try again.',
        insufficientData: 'Insufficient data provided.',
        serverError: 'Server error occurred. Please contact support if this persists.',
        fileTooBig: 'File is too large. Maximum size allowed is 10MB.',
        invalidFileType: 'Invalid file type. Please upload images only.'
    },
    
    // Warning messages
    warning: {
        unsavedChanges: 'You have unsaved changes. Are you sure you want to leave?',
        noItemsSelected: 'Please select at least one item.',
        lowStock: 'Warning: Stock level is low.',
        duplicateEntry: 'This entry already exists.',
        dataIncomplete: 'Some data may be incomplete.'
    },
    
    // Info messages
    info: {
        processing: 'Processing your request...',
        loading: 'Loading data...',
        analyzing: 'Analyzing with AI...',
        saving: 'Saving changes...',
        uploading: 'Uploading files...'
    },
    
    // Validation messages
    validation: {
        required: 'This field is required.',
        emailInvalid: 'Please enter a valid email address.',
        priceInvalid: 'Please enter a valid price.',
        quantityInvalid: 'Please enter a valid quantity.',
        skuRequired: 'SKU is required.',
        nameRequired: 'Name is required.',
        colorRequired: 'Please select a color before adding to cart.',
        paymentRequired: 'Please select a payment method.',
        shippingRequired: 'Please select a shipping method.'
    }
};

// Helper function to get message with fallback
window.getMessage = function(category, key, fallback = 'Operation completed') {
    try {
        return window.NotificationMessages[category]?.[key] || fallback;
    } catch (e) {
        return fallback;
    }
};

// Enhanced notification functions that use the message config
window.showSuccessMessage = function(key, fallback) {
    showSuccess(getMessage('success', key, fallback));
};

window.showErrorMessage = function(key, fallback) {
    showError(getMessage('error', key, fallback));
};

window.showWarningMessage = function(key, fallback) {
    showWarning(getMessage('warning', key, fallback));
};

window.showInfoMessage = function(key, fallback) {
    showInfo(getMessage('info', key, fallback));
};

window.showValidationMessage = function(key, fallback) {
    showValidation(getMessage('validation', key, fallback));
}; 

// --- End of js/notification-messages.js --- 

// --- Start of js/global-popup.js --- 

// Auto-generated barrel after split
export * from './global-popup/index.js';


// --- End of js/global-popup.js --- 

// --- Start of js/global-modals.js --- 

// Global Modal and Confirmation Dialog System

class ConfirmationModal {
    constructor() {
        this.overlay = null;
        this.modal = null;
        this.currentResolve = null;
        this.init();
    }

    init() {
        // Create modal HTML structure
        this.createModalHTML();
        
        // Add event listeners
        this.addEventListeners();
    }

    createModalHTML() {
        // Remove existing modal if it exists
        const existingOverlay = document.getElementById('global-confirmation-modal');
        if (existingOverlay) {
            existingOverlay.remove();
        }

        // Create modal overlay
        this.overlay = document.createElement('div');
        this.overlay.id = 'global-confirmation-modal';
        this.overlay.className = 'confirmation-modal-overlay';

        // Create modal container
        this.modal = document.createElement('div');
        this.modal.className = 'confirmation-modal animate-slide-in-up';

        this.overlay.appendChild(this.modal);
        document.body.appendChild(this.overlay);
    }

    addEventListeners() {
        // Close on overlay click
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close(false);
            }
        });

        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.overlay.classList.contains('show')) {
                this.close(false);
            }
        });
    }

    show(options = {}) {
        return new Promise((resolve) => {
            this.currentResolve = resolve;

            const {
                title = 'Confirm Action',
                message = 'Are you sure you want to proceed?',
                details = null,
                icon = '⚠️',
                iconType = 'warning',
                confirmText = 'Confirm',
                cancelText = 'Cancel',
                confirmStyle = 'confirm',
                subtitle = null
            } = options;

            // Build modal content
            this.modal.innerHTML = `
                <div class="confirmation-modal-header">
                    <div class="confirmation-modal-icon ${iconType}">
                        ${icon}
                    </div>
                    <h3 class="confirmation-modal-title">${title}</h3>
                    ${subtitle ? `<p class="confirmation-modal-subtitle">${subtitle}</p>` : ''}
                </div>
                <div class="confirmation-modal-body">
                    <div class="confirmation-modal-message">${message}</div>
                    ${details ? `<div class="confirmation-modal-details">${details}</div>` : ''}
                </div>
                <div class="confirmation-modal-footer">
                    <button class="confirmation-modal-button cancel" id="modal-cancel">
                        ${cancelText}
                    </button>
                    <button class="confirmation-modal-button ${confirmStyle}" id="modal-confirm">
                        ${confirmText}
                    </button>
                </div>
            `;

            // Add button event listeners
            document.getElementById('modal-cancel').addEventListener('click', () => {
                this.close(false);
            });

            document.getElementById('modal-confirm').addEventListener('click', () => {
                this.close(true);
            });

            // Show modal
            this.overlay.classList.add('show');
            
            // Focus the confirm button
            setTimeout(() => {
                document.getElementById('modal-confirm').focus();
            }, 100);
        });
    }

    close(result) {
        this.overlay.classList.remove('show');
        
        setTimeout(() => {
            if (this.currentResolve) {
                this.currentResolve(result);
                this.currentResolve = null;
            }
        }, 300);
    }
}

// Global confirmation modal instance
let globalConfirmationModal = null;

// Initialize on DOM load
document.addEventListener('DOMContentLoaded', () => {
    globalConfirmationModal = new ConfirmationModal();
});

// Global confirmation function
window.showConfirmationModal = async (options) => {
    if (!globalConfirmationModal) {
        globalConfirmationModal = new ConfirmationModal();
    }
    return await globalConfirmationModal.show(options);
};

// Convenience functions for different types of confirmations
window.confirmAction = async (title, message, confirmText = 'Confirm') => {
    return await showConfirmationModal({
        title,
        message,
        confirmText,
        icon: '⚠️',
        iconType: 'warning'
    });
};

window.confirmDanger = async (title, message, confirmText = 'Delete') => {
    return await showConfirmationModal({
        title,
        message,
        confirmText,
        confirmStyle: 'danger',
        icon: '⚠️',
        iconType: 'danger'
    });
};

window.confirmInfo = async (title, message, confirmText = 'Continue') => {
    return await showConfirmationModal({
        title,
        message,
        confirmText,
        icon: 'ℹ️',
        iconType: 'info'
    });
};

window.confirmSuccess = async (title, message, confirmText = 'Proceed') => {
    return await showConfirmationModal({
        title,
        message,
        confirmText,
        icon: '✅',
        iconType: 'success'
    });
};

// Enhanced confirmation with details
window.confirmWithDetails = async (title, message, details, options = {}) => {
    return await showConfirmationModal({
        title,
        message,
        details,
        ...options
    });
}; 

// --- End of js/global-modals.js --- 

// --- Start of js/modal-functions.js --- 

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



// --- End of js/modal-functions.js --- 

// --- Start of js/modal-close-positioning.js --- 

/**
 * Modal Close Button Positioning System
 * Automatically positions modal close buttons based on CSS variables
 */

// Initialize modal close button positioning
function initializeModalClosePositioning() {
    // Get the current position setting from CSS variables
    const position = getComputedStyle(document.documentElement)
        .getPropertyValue('-modal-close-position')
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

// --- End of js/modal-close-positioning.js --- 

// --- Start of js/analytics.js --- 

/**
 * WhimsicalFrog Analytics Tracker
 * Comprehensive user behavior tracking system
 */

class AnalyticsTracker {
    constructor() {
        this.sessionStartTime = Date.now();
        this.pageStartTime = Date.now();
        this.lastScrollPosition = 0;
        this.maxScrollDepth = 0;
        this.interactions = [];
        this.isTracking = true;
        
        // Initialize tracking
        this.init();
    }
    
    init() {
        // Track initial visit
        this.trackVisit();
        
        // Track page view
        this.trackPageView();
        
        // Set up event listeners
        this.setupEventListeners();
        
        // Track page exit
        this.setupPageExitTracking();
        
        // Send periodic updates
        this.startPeriodicTracking();
    }
    
    trackVisit() {
        const data = {
            landing_page: window.location.href,
            referrer: document.referrer,
            timestamp: Date.now()
        };
        
        this.sendData('track_visit', data);
    }
    
    trackPageView() {
        const data = {
            page_url: window.location.href,
            page_title: document.title,
            page_type: this.getPageType(),
            item_sku: this.getItemSku(),
            timestamp: Date.now()
        };
        
        this.sendData('track_page_view', data);
    }
    
    getPageType() {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || 'landing';
        
        if (page === 'shop') return 'shop';
        if (page.startsWith('room')) return 'product_room';
        if (page === 'cart') return 'cart';
        if (page === 'admin') return 'admin';
        if (page === 'landing') return 'landing';
        
        return 'other';
    }
    
    getItemSku() {
        // Try to extract item SKU from various sources
        const params = new URLSearchParams(window.location.search);
        
        // Check URL parameters
        if (params.get('product')) return params.get('product');
        if (params.get('sku')) return params.get('sku');
        if (params.get('item')) return params.get('item');
        if (params.get('edit')) return params.get('edit');
        
        // Check for item elements on page
        const itemElements = document.querySelectorAll('[data-product-id], [data-sku], [data-item-sku]');
        if (itemElements.length > 0) {
            return itemElements[0].dataset.productId || itemElements[0].dataset.sku || itemElements[0].dataset.itemSku;
        }
        
        return null;
    }
    
    setupEventListeners() {
        // Track clicks
        document.addEventListener('click', (e) => {
            this.trackInteraction('click', e);
        });
        
        // Track form submissions
        document.addEventListener('submit', (e) => {
            this.trackInteraction('form_submit', e);
        });
        
        // Track scroll behavior
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                this.trackScroll();
            }, 100);
        });
        
        // Track search interactions
        const searchInputs = document.querySelectorAll('input[type="search"], input[name*="search"]');
        searchInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                if (e.target.value.length > 2) {
                    this.trackInteraction('search', e);
                }
            });
        });
        
        // Track cart actions
        this.setupCartTracking();
        
        // Track item interactions
        this.setupItemTracking();
    }
    
    setupCartTracking() {
        // Track add to cart buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('add-to-cart-btn') || 
                e.target.closest('.add-to-cart-btn')) {
                
                const button = e.target.classList.contains('add-to-cart-btn') ? 
                              e.target : e.target.closest('.add-to-cart-btn');
                
                const productSku = button.dataset.productId || button.dataset.sku;
                
                this.trackCartAction('add', productSku);
                this.trackInteraction('cart_add', e, { item_sku: productSku });
            }
        });
        
        // Track cart removal
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-from-cart') || 
                e.target.closest('.remove-from-cart')) {
                
                const button = e.target.classList.contains('remove-from-cart') ? 
                              e.target : e.target.closest('.remove-from-cart');
                
                const productSku = button.dataset.productId || button.dataset.sku;
                
                this.trackCartAction('remove', productSku);
                this.trackInteraction('cart_remove', e, { item_sku: productSku });
            }
        });
        
        // Track checkout process
        const checkoutButtons = document.querySelectorAll('[onclick*="checkout"], .checkout-btn');
        checkoutButtons.forEach(button => {
            button.addEventListener('click', () => {
                this.trackInteraction('checkout_start', null);
            });
        });
    }
    
    setupItemTracking() {
        // Track item views with time spent
        const itemElements = document.querySelectorAll('.product-card, .product-item, .item-card, .item-item');
        
        itemElements.forEach(element => {
            let viewStartTime = null;
            
            // Track when item comes into view
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        viewStartTime = Date.now();
                    } else if (viewStartTime) {
                        const viewTime = Date.now() - viewStartTime;
                        const productSku = element.dataset.productId || element.dataset.sku;
                        
                        if (productSku && viewTime > 1000) { // Only track if viewed for more than 1 second
                            this.trackItemView(productSku, viewTime);
                        }
                        viewStartTime = null;
                    }
                });
            }, { threshold: 0.5 });
            
            observer.observe(element);
        });
        
        // Track item clicks
        document.addEventListener('click', (e) => {
            const itemElement = e.target.closest('.product-card, .product-item, .item-card, .item-item');
            if (itemElement) {
                const productSku = itemElement.dataset.productId || itemElement.dataset.sku;
                if (productSku) {
                    this.trackInteraction('click', e, { 
                        item_sku: productSku,
                        element_type: 'item'
                    });
                }
            }
        });
    }
    
    trackInteraction(type, event, additionalData = {}) {
        if (!this.isTracking) return;
        
        let elementInfo = {};
        
        if (event && event.target) {
            elementInfo = {
                element_type: event.target.tagName.toLowerCase(),
                element_id: event.target.id,
                element_text: event.target.textContent?.substring(0, 100) || '',
                element_class: event.target.className
            };
        }
        
        const data = {
            page_url: window.location.href,
            interaction_type: type,
            ...elementInfo,
            interaction_data: {
                timestamp: Date.now(),
                page_x: event?.clientX || 0,
                page_y: event?.clientY || 0,
                ...additionalData
            },
            item_sku: additionalData.item_sku || this.getItemSku()
        };
        
        this.sendData('track_interaction', data);
    }
    
    trackScroll() {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const documentHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrollPercent = Math.round((scrollTop / documentHeight) * 100);
        
        if (scrollPercent > this.maxScrollDepth) {
            this.maxScrollDepth = scrollPercent;
            
            // Track significant scroll milestones
            if (scrollPercent >= 25 && this.maxScrollDepth < 25) {
                this.trackInteraction('scroll', null, { scroll_depth: 25 });
            } else if (scrollPercent >= 50 && this.maxScrollDepth < 50) {
                this.trackInteraction('scroll', null, { scroll_depth: 50 });
            } else if (scrollPercent >= 75 && this.maxScrollDepth < 75) {
                this.trackInteraction('scroll', null, { scroll_depth: 75 });
            } else if (scrollPercent >= 90 && this.maxScrollDepth < 90) {
                this.trackInteraction('scroll', null, { scroll_depth: 90 });
            }
        }
    }
    
    trackItemView(productSku, timeSpent) {
        const data = {
            item_sku: productSku,
            time_on_page: Math.round(timeSpent / 1000) // Convert to seconds
        };
        
        this.sendData('track_item_view', data);
    }
    
    trackCartAction(action, productSku) {
        if (!productSku) return;
        
        const data = {
            item_sku: productSku,
            action: action
        };
        
        this.sendData('track_cart_action', data);
    }
    
    setupPageExitTracking() {
        // Track when user leaves the page
        window.addEventListener('beforeunload', () => {
            this.trackPageExit();
        });
        
        // Track when page becomes hidden (tab switch, minimize, etc.)
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.trackPageExit();
            } else {
                // Page became visible again, restart tracking
                this.pageStartTime = Date.now();
            }
        });
    }
    
    trackPageExit() {
        const timeOnPage = Math.round((Date.now() - this.pageStartTime) / 1000);
        
        const data = {
            page_url: window.location.href,
            time_on_page: timeOnPage,
            scroll_depth: this.maxScrollDepth,
            item_sku: this.getItemSku()
        };
        
        // Use sendBeacon for reliable exit tracking
        this.sendDataSync('track_page_view', data);
    }
    
    startPeriodicTracking() {
        // Send periodic updates every 30 seconds
        setInterval(() => {
            if (!document.hidden) {
                this.trackPageView();
            }
        }, 30000);
    }
    
    sendData(action, data) {
        if (!this.isTracking) return;
        
        apiPost(`/api/analytics_tracker.php?action=${action}`, data).catch(error => {
            console.warn('Analytics tracking failed:', error);
        });
    }
    
    sendDataSync(action, data) {
        // For exit tracking, use sendBeacon for reliability
        const formData = new FormData();
        formData.append('action', action);
        formData.append('data', JSON.stringify(data));
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon(`/api/analytics_tracker.php?action=${action}`, formData);
        } else {
            // Fallback for older browsers
            this.sendData(action, data);
        }
    }
    
    // Public methods for manual tracking
    trackConversion(value = 0, orderId = null) {
        const data = {
            conversion_value: value,
            order_id: orderId,
            page_url: window.location.href
        };
        
        this.trackInteraction('checkout_complete', null, data);
    }
    
    trackCustomEvent(eventName, eventData = {}) {
        this.trackInteraction('custom', null, {
            event_name: eventName,
            ...eventData
        });
    }
    
    // Privacy controls
    enableTracking() {
        this.isTracking = true;
    }
    
    disableTracking() {
        this.isTracking = false;
    }
}

// Initialize analytics when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if user has opted out of tracking
    if (localStorage.getItem('analytics_opt_out') !== 'true') {
        window.analyticsTracker = new AnalyticsTracker();
    }
});

// Utility functions for manual tracking
window.trackConversion = function(value, orderId) {
    if (window.analyticsTracker) {
        window.analyticsTracker.trackConversion(value, orderId);
    }
};

window.trackCustomEvent = function(eventName, eventData) {
    if (window.analyticsTracker) {
        window.analyticsTracker.trackCustomEvent(eventName, eventData);
    }
};

// Privacy controls
window.optOutOfAnalytics = function() {
    localStorage.setItem('analytics_opt_out', 'true');
    if (window.analyticsTracker) {
        window.analyticsTracker.disableTracking();
    }
};

window.optInToAnalytics = function() {
    localStorage.removeItem('analytics_opt_out');
    if (!window.analyticsTracker) {
        window.analyticsTracker = new AnalyticsTracker();
    } else {
        window.analyticsTracker.enableTracking();
    }
}; 

// --- End of js/analytics.js --- 

// --- Start of js/room-coordinate-manager.js --- 

/**
 * WhimsicalFrog Room Coordinate and Area Management
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

console.log('Loading room-coordinate-manager.js...');

// Ensure apiGet helper exists inside iframe context
if (typeof window.apiGet !== 'function') {
  window.apiGet = async function(endpoint) {
    const url = window.location.origin + (endpoint.startsWith('/') ? endpoint : `/api/${endpoint}`);
    const res = await fetch(url);
    if (!res.ok) {
      throw new Error(`Request failed (${res.status})`);
    }
    return res.json();
  };
}

// Room coordinate management system
window.RoomCoordinates = window.RoomCoordinates || {};

// Initialize room coordinates system
function initializeRoomCoordinates() {
    // Only initialize if we have the necessary window variables
    if (!window.ROOM_TYPE || !window.originalImageWidth || !window.originalImageHeight) {
        console.warn('Room coordinate system not initialized - missing required variables');
        return;
    }
    
    // Set up DOM references
    window.roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
    
    if (!window.roomOverlayWrapper) {
        console.warn('Room overlay wrapper not found');
        return;
    }
    
    // Load coordinates from database
    loadRoomCoordinatesFromDatabase();
}

function updateAreaCoordinates() {
    if (!window.roomOverlayWrapper) {
        console.error('Room overlay wrapper not found for scaling.');
        return;
    }
    
    if (!window.baseAreas || !window.baseAreas.length) {
        console.log('No base areas to position');
        return;
    }

    const wrapperWidth = window.roomOverlayWrapper.offsetWidth;
    const wrapperHeight = window.roomOverlayWrapper.offsetHeight;

    const wrapperAspectRatio = wrapperWidth / wrapperHeight;
    const imageAspectRatio = window.originalImageWidth / window.originalImageHeight;

    let renderedImageWidth, renderedImageHeight;
    let offsetX = 0;
    let offsetY = 0;

    if (wrapperAspectRatio > imageAspectRatio) {
        renderedImageHeight = wrapperHeight;
        renderedImageWidth = renderedImageHeight * imageAspectRatio;
        offsetX = (wrapperWidth - renderedImageWidth) / 2;
    } else {
        renderedImageWidth = wrapperWidth;
        renderedImageHeight = renderedImageWidth / imageAspectRatio;
        offsetY = (wrapperHeight - renderedImageHeight) / 2;
    }

    const scaleX = renderedImageWidth / window.originalImageWidth;
    const scaleY = renderedImageHeight / window.originalImageHeight;

    window.baseAreas.forEach(areaData => {
        const areaElement = window.roomOverlayWrapper.querySelector(areaData.selector);
        if (areaElement) {
            areaElement.style.top = (areaData.top * scaleY + offsetY) + 'px';
            areaElement.style.left = (areaData.left * scaleX + offsetX) + 'px';
            areaElement.style.width = (areaData.width * scaleX) + 'px';
            areaElement.style.height = (areaData.height * scaleY) + 'px';
        }
    });
    
    console.log(`Updated ${window.baseAreas.length} room areas for ${window.ROOM_TYPE}`);
    // Re-bind item hover/click events now that areas are placed
    if (typeof window.setupPopupEventsAfterPositioning === 'function') {
        window.setupPopupEventsAfterPositioning();
    }
}

async function loadRoomCoordinatesFromDatabase() {
    try {
        const data = await apiGet(`/api/get_room_coordinates.php?room_type=${window.ROOM_TYPE}`);
        
        
        
        if (data.success && data.coordinates && data.coordinates.length > 0) {
            window.baseAreas = data.coordinates;
            console.log(`Loaded ${data.coordinates.length} coordinates from database for ${window.ROOM_TYPE}`);
            
            // Initialize coordinates after loading
            updateAreaCoordinates();
            
            // Set up resize handler
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(updateAreaCoordinates, 100);
            });
        } else {
            console.error(`No active room map found in database for ${window.ROOM_TYPE}`);
            // Fallback to any existing baseAreas set by room helper
            if (window.baseAreas && window.baseAreas.length > 0) {
                console.log(`Using fallback coordinates for ${window.ROOM_TYPE}`);
                updateAreaCoordinates();
            }
        }
    } catch (error) {
        console.error(`Error loading ${window.ROOM_TYPE} coordinates from database:`, error);
        // Fallback to any existing baseAreas set by room helper
        if (window.baseAreas && window.baseAreas.length > 0) {
            console.log(`Using fallback coordinates for ${window.ROOM_TYPE} due to database error`);
            updateAreaCoordinates();
        }
    }
}

// Make functions available globally
window.updateAreaCoordinates = updateAreaCoordinates;
window.loadRoomCoordinatesFromDatabase = loadRoomCoordinatesFromDatabase;
window.initializeRoomCoordinates = initializeRoomCoordinates;

function waitForWrapperAndUpdate(retries = 10) {
    if (!window.roomOverlayWrapper) {
        window.roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
    }
    if (window.roomOverlayWrapper && window.roomOverlayWrapper.offsetWidth > 0 && window.roomOverlayWrapper.offsetHeight > 0) {
        updateAreaCoordinates();
    } else if (retries > 0) {
        setTimeout(() => waitForWrapperAndUpdate(retries - 1), 200);
    } else {
        console.warn('Room overlay wrapper size not ready after retries.');
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    if (window.ROOM_TYPE) {
        // Add a small delay to ensure room helper variables are set
        setTimeout(initializeRoomCoordinates, 100);
    }
});

console.log('room-coordinate-manager.js loaded successfully');


// --- End of js/room-coordinate-manager.js --- 

// --- Start of js/room-functions.js --- 

/**
 * Centralized Room Functions
 * Shared functionality for all room pages to eliminate code duplication
 */

// Global room state variables
window.roomState = {
    currentItem: null,
    popupTimeout: null,
    popupOpen: false,
    isShowingPopup: false,
    lastShowTime: 0,
    roomNumber: null,
    roomType: null
};

/**
 * Initialize room functionality
 * Call this from each room page with room-specific data
 */
window.initializeRoom = function(roomNumber, roomType) {
    window.roomState.roomNumber = roomNumber;
    window.roomState.roomType = roomType;
    
    // Initialize global cart modal event listeners
    if (typeof window.initializeModalEventListeners === 'function') {
        window.initializeModalEventListeners();
    }
    
    // Set up document click listener for popup closing
    document.addEventListener('click', function(e) {
        const popup = document.getElementById('itemPopup');
        
        // Close popup if it's open and click is outside it
        if (popup && popup.classList.contains('show') && !popup.contains(e.target) && !e.target.closest('.item-icon')) {
            hidePopupImmediate();
        }
    });
    
    console.log(`Room ${roomNumber} (${roomType}) initialized with centralized functions`);
};

/**
 * Universal popup system for all rooms - now uses global system
 */
window.showPopup = function(element, item) {
    if (typeof window.showGlobalPopup === 'function') {
        window.showGlobalPopup(element, item);
    } else {
        console.error('Global popup system not available');
    }
};

/**
 * Hide popup with delay for mouse movement - now uses global system
 */
window.hidePopup = function() {
    if (typeof window.hideGlobalPopup === 'function') {
        window.hideGlobalPopup();
    }
};

/**
 * Hide popup immediately - now uses global system
 */
window.hidePopupImmediate = function() {
    if (typeof window.hideGlobalPopupImmediate === 'function') {
        window.hideGlobalPopupImmediate();
    }
};

/**
 * Position popup intelligently relative to element
 */
function positionPopup(popup, element) {
    if (!popup || !element) return;
    
    // Make popup visible but transparent to measure dimensions
    popup.classList.add('popup-measuring');
    
    // Get element and popup dimensions
    const elementRect = element.getBoundingClientRect();
    const popupRect = popup.getBoundingClientRect();
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Calculate position
    let left = elementRect.left + (elementRect.width / 2) - (popupRect.width / 2);
    let top = elementRect.bottom + 10;
    
    // Adjust for viewport boundaries
    if (left < 10) left = 10;
    if (left + popupRect.width > viewportWidth - 10) {
        left = viewportWidth - popupRect.width - 10;
    }
    
    if (top + popupRect.height > viewportHeight - 10) {
        top = elementRect.top - popupRect.height - 10;
    }
    
    // Apply position and restore visibility
    popup.style.setProperty('-popup-left', left + 'px');
    popup.style.setProperty('-popup-top', top + 'px');
    popup.classList.remove('popup-measuring');
    popup.classList.add('popup-positioned');
}

/**
 * Universal quantity modal opener for all rooms - now uses global modal system
 */
window.openQuantityModal = function(item) {
    // First try to use the new global modal system
    if (typeof window.showGlobalItemModal === 'function') {
        hideGlobalPopup();
        
        // Use the global detailed modal instead
        window.showGlobalItemModal(item.sku);
    } else {
        // Fallback to simple modal
        fallbackToSimpleModal(item);
    }
};

/**
 * Fallback function for when global modal system isn't available
 */
function fallbackToSimpleModal(item) {
    console.log('Using fallback simple modal for:', item.sku);
    
    // Use the old addToCartWithModal system as fallback
    if (typeof window.addToCartWithModal === 'function') {
        const sku = item.sku;
        const name = item.name || item.productName;
        const price = parseFloat(item.retailPrice || item.price);
        const image = item.primaryImageUrl || `images/items/${item.sku}A.png`;
        
        window.addToCartWithModal(sku, name, price, image);
        return;
    }
    
    console.error('Both detailed modal and fallback systems failed');
}

/**
 * Universal detailed modal opener for all rooms
 */
window.showItemDetails = function(sku) {
    // Use the existing detailed modal system
    if (typeof window.showProductDetails === 'function') {
        window.showProductDetails(sku);
    } else {
        console.error('showProductDetails function not available');
    }
};

/**
 * Setup popup persistence when hovering over popup itself
 */
window.setupPopupPersistence = function() {
    const popup = document.getElementById('itemPopup');
    if (!popup) return;
    
    // Keep popup visible when hovering over it
    popup.addEventListener('mouseenter', () => {
        clearTimeout(window.roomState.popupTimeout);
        window.roomState.isShowingPopup = true;
        window.roomState.popupOpen = true;
    });
    
    popup.addEventListener('mouseleave', () => {
        hidePopup();
    });
};

/**
 * Initialize room on DOM ready
 */
document.addEventListener('DOMContentLoaded', function() {
    // Setup popup persistence
    setupPopupPersistence();
    
    console.log('Room functions initialized and ready');
}); 

// --- End of js/room-functions.js --- 

// --- Start of js/room-helper.js --- 

(function() {
  const script = document.currentScript;
  window.roomItems = script.dataset.roomItems ? JSON.parse(script.dataset.roomItems) : [];
  window.roomNumber = script.dataset.roomNumber || '';
  window.roomType = script.dataset.roomType || '';
  window.ROOM_TYPE = window.roomType;
  window.originalImageWidth = 1280;
  window.originalImageHeight = 896;
  window.baseAreas = script.dataset.baseAreas ? JSON.parse(script.dataset.baseAreas) : [];
  console.log('⚙️ room-helper initialized. roomItems:', window.roomItems, 'baseAreas:', window.baseAreas);
  window.roomOverlayWrapper = null;

  function updateItemPositions() {
    if (!window.roomOverlayWrapper || !window.baseAreas) return;
    const wrapperWidth = window.roomOverlayWrapper.offsetWidth;
    const wrapperHeight = window.roomOverlayWrapper.offsetHeight;
    const imageAspectRatio = window.originalImageWidth / window.originalImageHeight;
    let renderedImageWidth, renderedImageHeight, offsetX = 0, offsetY = 0;
    const wrapperAspectRatio = wrapperWidth / wrapperHeight;
    if (wrapperAspectRatio > imageAspectRatio) {
      renderedImageHeight = wrapperHeight;
      renderedImageWidth = renderedImageHeight * imageAspectRatio;
      offsetX = (wrapperWidth - renderedImageWidth) / 2;
    } else {
      renderedImageWidth = wrapperWidth;
      renderedImageHeight = renderedImageWidth / imageAspectRatio;
      offsetY = (wrapperHeight - renderedImageHeight) / 2;
    }
    const scaleX = renderedImageWidth / window.originalImageWidth;
    const scaleY = renderedImageHeight / window.originalImageHeight;
    window.roomItems.forEach((_, index) => {
      const itemElement = document.getElementById('item-icon-' + index);
      const areaData = window.baseAreas[index];
      if (itemElement && areaData) {
        itemElement.style.top = (areaData.top * scaleY + offsetY) + 'px';
        itemElement.style.left = (areaData.left * scaleX + offsetX) + 'px';
        itemElement.style.width = (areaData.width * scaleX) + 'px';
        itemElement.style.height = (areaData.height * scaleY) + 'px';
      }
    });
  }

  function adjustTitleBoxSize() {
    const titleOverlay = document.querySelector('.room-title-overlay');
    if (!titleOverlay) return;
    const title = titleOverlay.querySelector('.room-title');
    const description = titleOverlay.querySelector('.room-description');
    if (!title) return;
    const titleLength = title.textContent.length;
    const descriptionLength = description ? description.textContent.length : 0;
    const totalLength = titleLength + descriptionLength;
    const screenWidth = window.innerWidth;
    const isMobile = screenWidth <= 480;
    const isTablet = screenWidth <= 768;
    let dynamicWidth, dynamicPadding;
    if (isMobile) {
      dynamicWidth = totalLength <= 25 ? '140px' : totalLength <= 40 ? '180px' : totalLength <= 60 ? '220px' : '240px';
      dynamicPadding = totalLength <= 30 ? '6px 10px' : '8px 12px';
    } else if (isTablet) {
      dynamicWidth = totalLength <= 30 ? '160px' : totalLength <= 50 ? '210px' : totalLength <= 70 ? '250px' : '280px';
      dynamicPadding = totalLength <= 30 ? '8px 12px' : '10px 14px';
    } else {
      dynamicWidth = totalLength <= 30 ? '200px' : totalLength <= 50 ? '250px' : totalLength <= 80 ? '300px' : '400px';
      dynamicPadding = totalLength <= 30 ? '10px 14px' : totalLength <= 50 ? '12px 16px' : '14px 18px';
    }
    titleOverlay.style.width = dynamicWidth;
    titleOverlay.style.padding = dynamicPadding;
    let titleFontSize, descriptionFontSize;
    if (isMobile) {
      titleFontSize = titleLength <= 15 ? '1.6rem' : titleLength <= 25 ? '1.3rem' : titleLength <= 35 ? '1.1rem' : '1rem';
      descriptionFontSize = descriptionLength <= 30 ? '0.9rem' : descriptionLength <= 50 ? '0.8rem' : '0.7rem';
    } else if (isTablet) {
      titleFontSize = titleLength <= 15 ? '2rem' : titleLength <= 25 ? '1.7rem' : titleLength <= 35 ? '1.4rem' : '1.2rem';
      descriptionFontSize = descriptionLength <= 30 ? '1.1rem' : descriptionLength <= 50 ? '1rem' : '0.9rem';
    } else {
      titleFontSize = titleLength <= 15 ? '2.5rem' : titleLength <= 25 ? '2.2rem' : titleLength <= 35 ? '1.9rem' : titleLength <= 45 ? '1.6rem' : '1.4rem';
      descriptionFontSize = descriptionLength <= 30 ? '1.3rem' : descriptionLength <= 50 ? '1.2rem' : descriptionLength <= 70 ? '1.1rem' : '1rem';
    }
    title.style.fontSize = titleFontSize;
    title.style.whiteSpace = '';
    title.style.overflow = '';
    title.style.textOverflow = '';
    if (description) {
      description.style.fontSize = descriptionFontSize;
      description.style.whiteSpace = '';
      description.style.overflow = '';
      description.style.textOverflow = '';
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    window.roomOverlayWrapper = document.querySelector('.room-modal-content-wrapper');
    if (window.roomOverlayWrapper && window.baseAreas && window.baseAreas.length > 0) {
      updateItemPositions();
      let resizeTimeout;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
          updateItemPositions();
          adjustTitleBoxSize();
        }, 100);
      });
    }
    adjustTitleBoxSize();
  });

  document.addEventListener('whimsicalfrog:ready', function() {
    if (window.ROOM_TYPE && typeof initializeRoomCoordinates === 'function') {
      initializeRoomCoordinates();
    }
  });
})();

// Ensure clicking main modal image opens image viewer
// FIRST_EDIT: Comment out old custom click listener for detailed modal
/*
document.body.addEventListener('click', function(e) {
  if (e.target && e.target.id === 'detailedMainImage') {
    openImageViewer(e.target.src, e.target.alt);
  }
  // Close detailed item modal when clicking on overlay background
  if (e.target && e.target.id === 'detailedItemModal') {
    // Allow room-modal-manager or central handler
    if (typeof closeDetailedModalOnOverlay === 'function') {
      closeDetailedModalOnOverlay(e);
    }
  }
});
*/
// SECOND_EDIT: Add delegated click handler for detailed modal interactions
document.body.addEventListener('click', function(e) {
  const actionEl = e.target.closest('[data-action="openImageViewer"], [data-action="closeDetailedModalOnOverlay"]');
  if (!actionEl) return;
  const action = actionEl.dataset.action;
  const params = actionEl.dataset.params ? JSON.parse(actionEl.dataset.params) : {};
  if (action === 'openImageViewer' && typeof openImageViewer === 'function') {
    openImageViewer(params.src, params.name);
  }
  if (action === 'closeDetailedModalOnOverlay' && typeof closeDetailedModalOnOverlay === 'function') {
    closeDetailedModalOnOverlay(e);
  }
}); 

// --- End of js/room-helper.js --- 

// --- Start of js/global-item-modal.js --- 

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
                const response = await apiGet(`/api/get_item_details.php?sku=${sku}`);
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
            const modalHtml = await apiPost('render_detailed_modal.php', {
                item: item,
                images: images
            });

            if (!modalHtml || typeof modalHtml !== 'string') {
                throw new Error('Modal render API returned invalid HTML');
            }
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
            loadScript(`js/detailed-item-modal.js?v=${Date.now()}`, 'detailed-item-modal-script')
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
    window.showItemDetailsModal = showGlobalItemModal; // Added alias for legacy support
    window.showDetailedModal = showGlobalItemModal;
    window.closeDetailedModal = closeGlobalItemModal;
    window.closeDetailedModalOnOverlay = closeDetailedModalOnOverlay;
    window.openQuantityModal = quickAddToCart;

    console.log('Global Item Modal system loaded');
})(); 

// --- End of js/global-item-modal.js --- 

// --- Start of js/detailed-item-modal.js --- 

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

// --- End of js/detailed-item-modal.js --- 

// --- Start of js/room-modal-manager.js --- 

/**
 * WhimsicalFrog Room Modal Management
 * Handles responsive modal overlays for room content.
 * Updated: 2025-07-03 (restructured for stability and clarity)
 */

console.log('Loading room-modal-manager.js...');

class RoomModalManager {
    constructor() {
        this.overlay = null;
        this.content = null;
        this.isLoading = false;
        this.currentRoomNumber = null;
        this.roomCache = new Map();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    init() {
        console.log('🚪 RoomModalManager initializing...');
        this.createModalStructure();
        this.setupEventListeners();
        this.preloadRoomContent();
    }

    createModalStructure() {
        if (document.getElementById('roomModalOverlay')) {
            console.log('🚪 Using existing modal overlay found in DOM.');
            this.overlay = document.getElementById('roomModalOverlay');
            this.content = this.overlay.querySelector('.room-modal-container');
            return;
        }

        console.log('🚪 Creating new modal overlay structure.');
        this.overlay = document.createElement('div');
        this.overlay.id = 'roomModalOverlay';
        this.overlay.className = 'room-modal-overlay';

        this.content = document.createElement('div');
        this.content.className = 'room-modal-container';

        const header = document.createElement('div');
        header.className = 'room-modal-header';

        const backButtonContainer = document.createElement('div');
        backButtonContainer.className = 'back-button-container';

        const backButton = document.createElement('button');
        backButton.className = 'room-modal-button';
        backButton.innerHTML = '← Back';
        backButton.onclick = () => this.hide();
        backButtonContainer.appendChild(backButton);

        const titleOverlay = document.createElement('div');
        titleOverlay.className = 'room-title-overlay';
        titleOverlay.id = 'roomTitleOverlay';

        const roomTitle = document.createElement('h1');
        roomTitle.id = 'roomTitle';
        roomTitle.textContent = 'Loading...';

        const roomDescription = document.createElement('div');
        roomDescription.className = 'room-description';
        roomDescription.id = 'roomDescription';
        roomDescription.textContent = '';

        titleOverlay.appendChild(roomTitle);
        titleOverlay.appendChild(roomDescription);
        header.appendChild(backButtonContainer);
        header.appendChild(titleOverlay);

        const loadingSpinner = document.createElement('div');
        loadingSpinner.id = 'roomModalLoading';
        loadingSpinner.className = 'room-modal-loading';
        loadingSpinner.innerHTML = `
            <div class="room-modal-spinner"></div>
            <p class="room-modal-loading-text">Loading room...</p>
        `;

        const iframe = document.createElement('iframe');
        iframe.id = 'roomModalFrame';
        iframe.className = 'room-modal-frame';
        iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');

        this.content.appendChild(header);
        this.content.appendChild(loadingSpinner);
        this.content.appendChild(iframe);
        this.overlay.appendChild(this.content);
        document.body.appendChild(this.overlay);
    }

    setupEventListeners() {
        console.log('🚪 Setting up room modal event listeners...');
        document.body.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-room]');
            if (trigger) {
                event.preventDefault();
                const roomNumber = trigger.dataset.room;
                if (roomNumber) {
                    console.log(`🚪 Room trigger clicked for room: ${roomNumber}`);
                    this.show(roomNumber);
                }
            }
        });

        if (this.overlay) {
            this.overlay.addEventListener('click', (event) => {
                if (event.target === this.overlay) {
                    this.hide();
                }
            });
        }
    }

    show(roomNumber) {
        if (this.isLoading) return;
        this.currentRoomNumber = roomNumber;
        this.isLoading = true;

        console.log('🚪 Showing room modal for room:', roomNumber);

        this.overlay.style.display = 'flex';
        document.body.classList.add('modal-open', 'room-modal-open');
        this.hideLegacyModalElements();

        const pageHeader = document.querySelector('.room-page-header, .site-header');
        if (pageHeader) pageHeader.classList.add('modal-active');

        WhimsicalFrog.ready(wf => {
            const mainApp = wf.getModule('MainApplication');
            if (mainApp) {
                const roomData = this.roomCache.get(String(roomNumber));
                const roomType = (roomData && roomData.metadata && roomData.metadata.room_type) ? roomData.metadata.room_type : `room${roomNumber}`;
                mainApp.loadModalBackground(roomType);
            }
        });

        this.loadRoomContentFast(roomNumber);

        setTimeout(() => {
            this.overlay.classList.add('show');
            this.isLoading = false;
        }, 10);
    }

    hide() {
        if (!this.overlay) return;

        console.log('🚪 Hiding room modal.');
        this.overlay.classList.remove('show');
        document.body.classList.remove('modal-open', 'room-modal-open');
        this.restoreLegacyModalElements();

        const pageHeader = document.querySelector('.room-page-header, .site-header');
        if (pageHeader) pageHeader.classList.remove('modal-active');

        WhimsicalFrog.ready(wf => {
            const mainApp = wf.getModule('MainApplication');
            if (mainApp) {
                mainApp.resetToPageBackground();
            }
        });

        setTimeout(() => {
            const iframe = document.getElementById('roomModalFrame');
            if (iframe) {
                iframe.src = 'about:blank';
            }
            this.currentRoomNumber = null;
            this.overlay.style.display = 'none';
        }, 300);
    }

    async loadRoomContentFast(roomNumber) {
        const loadingSpinner = document.getElementById('roomModalLoading');
        const iframe = document.getElementById('roomModalFrame');
        const roomTitleEl = document.getElementById('roomTitle');
        const roomDescriptionEl = document.getElementById('roomDescription');

        if (!iframe || !loadingSpinner || !roomTitleEl || !roomDescriptionEl) {
            console.error('🚪 Modal content elements not found!');
            this.isLoading = false;
            return;
        }

        loadingSpinner.style.display = 'flex';
        iframe.style.opacity = '0';
        iframe.src = 'about:blank';

        try {
            const cachedData = await this.getRoomData(roomNumber);
            if (cachedData) {
                console.log(`🚪 Loading room ${roomNumber} from cache.`);
                roomTitleEl.textContent = cachedData.metadata.room_name || 'Room';
                roomDescriptionEl.textContent = cachedData.metadata.room_description || '';
                const htmlDoc = `<!DOCTYPE html>
<html>
<head>
  <base href="${window.location.origin}">
  <link rel="stylesheet" href="/css/bundle.css?v=${window.WF_ASSET_VERSION || Date.now()}">
  <link rel="stylesheet" href="/css/room-iframe.css?v=${window.WF_ASSET_VERSION || Date.now()}">
</head>
<body>
${cachedData.content}
</body>
</html>`;
iframe.srcdoc = htmlDoc;
            } else {
                throw new Error('Room content not available in cache.');
            }
        } catch (error) {
            console.error(`🚪 Error loading room ${roomNumber}:`, error);
            roomTitleEl.textContent = 'Error';
            roomDescriptionEl.textContent = 'Could not load room content.';
            loadingSpinner.style.display = 'none';
        }

        iframe.onload = () => {
            // Expose global popup & modal functions into iframe context for seamless interaction
            try {
                const iWin = iframe.contentWindow;

                // Inject main bundle into iframe if missing
                if (!iWin.document.getElementById('wf-bundle')) {
                    const script = iWin.document.createElement('script');
                    script.id = 'wf-bundle';
                    script.type = 'text/javascript';
                    script.src = '/js/bundle.js?v=' + (window.WF_ASSET_VERSION || Date.now());
                    iWin.document.head.appendChild(script);
                    console.log('🚪 Injected bundle.js into iframe');
                }

                // Bridge critical global functions
                const bridgeFns = [
                    'showGlobalPopup',
                    'hideGlobalPopup',
                    'showItemDetailsModal',
                    'showGlobalItemModal'
                ];
                bridgeFns.forEach(fnName => {
                    if (typeof window[fnName] === 'function') {
                        iWin[fnName] = window[fnName];
                    }
                });

                // Copy popup state utilities if they exist
                if (window.unifiedPopupSystem) {
                    iWin.unifiedPopupSystem = window.unifiedPopupSystem;
                }

                // Ensure setupPopupEventsAfterPositioning exists in iframe, then run it
                if (typeof iWin.setupPopupEventsAfterPositioning !== 'function') {
                    if (typeof window.setupPopupEventsAfterPositioning === 'function') {
                        iWin.setupPopupEventsAfterPositioning = window.setupPopupEventsAfterPositioning;
                    }
                }
                if (typeof iWin.setupPopupEventsAfterPositioning === 'function') {
                    iWin.setupPopupEventsAfterPositioning();
                }
                if (typeof iWin.attachDelegatedItemEvents === 'function') {
                    iWin.attachDelegatedItemEvents();
                }
            } catch (bridgeErr) {
                console.warn('⚠️ Unable to bridge popup functions into iframe:', bridgeErr);
            }
            // When iframe content loaded, try to initialize coordinate system inside it
            try {
                const iWin = iframe.contentWindow;
                if (iWin && typeof iWin.initializeRoomCoordinates === 'function') {
                    iWin.initializeRoomCoordinates();
                }
            } catch (coordErr) {
                console.warn('⚠️ Unable to initialize coordinates in iframe:', coordErr);
            }
            loadingSpinner.style.display = 'none';
            iframe.style.opacity = '1';
            console.log(`🚪 Room ${roomNumber} content loaded into iframe.`);
        
            loadingSpinner.style.display = 'none';
            iframe.style.opacity = '1';
            console.log(`🚪 Room ${roomNumber} content loaded into iframe.`);
        };
    }

    async getRoomData(roomNumber) {
        if (this.roomCache.has(String(roomNumber))) {
            return this.roomCache.get(String(roomNumber));
        }
        return this.preloadSingleRoom(roomNumber);
    }

    async preloadRoomContent() {
        console.log('🚪 Preloading all room content...');
        try {
            const rooms = await apiGet('/api/get_rooms.php');

            if (Array.isArray(rooms)) {
                const preloadPromises = rooms.map(room => this.preloadSingleRoom(room.id));
                await Promise.all(preloadPromises);
                console.log('🚪 All rooms preloaded successfully.');
            } else {
                console.error('🚪 Failed to fetch rooms list or invalid format:', rooms);
            }
        } catch (error) {
            console.error('🚪 Error preloading rooms:', error);
        }
    }


    async preloadSingleRoom(roomNumber) {
        const num = parseInt(roomNumber, 10);
        if (!Number.isFinite(num) || num <= 0) {
            console.warn('🚪 Skipping preload for invalid room number:', roomNumber);
            return null;
        }
        if (this.roomCache.has(String(roomNumber))) {
            return this.roomCache.get(String(roomNumber));
        }

        try {
            const data = await apiGet(`/api/load_room_content.php?room_number=${roomNumber}&modal=1`);

            if (data.success) {
                this.roomCache.set(String(roomNumber), {
                    content: data.content,
                    metadata: data.metadata
                });
                console.log(`🚪 Room ${roomNumber} preloaded and cached.`);
                return data;
            } else {
                console.error(`🚪 Failed to preload room ${roomNumber}:`, data.message);
                return null;
            }
        } catch (error) {
            console.error(`🚪 Error preloading room ${roomNumber}:`, error);
            return null;
        }
    }

    hideLegacyModalElements() {
        const legacyModal = document.getElementById('room-container');
        if (legacyModal) legacyModal.style.display = 'none';
    }

    restoreLegacyModalElements() {
        const legacyModal = document.getElementById('room-container');
        if (legacyModal) legacyModal.style.display = ''; // Restore original display
    }
}

// Initialize the modal manager
WhimsicalFrog.ready(wf => {
    wf.addModule('RoomModalManager', new RoomModalManager());
});


// --- End of js/room-modal-manager.js --- 

// --- Start of js/cart-system.js --- 

// Auto-generated barrel after split
export * from './cart-system/index.js';


// --- End of js/cart-system.js --- 

// --- Start of js/main-application.js --- 

/**
 * WhimsicalFrog Main Application Module
 * Lightweight wrapper that depends on CartSystem and handles page-level UI.
 */
(function () {
  'use strict';

  if (!window.WhimsicalFrog || typeof window.WhimsicalFrog.registerModule !== 'function') {
    console.error('[MainApplication] WhimsicalFrog Core not found.');
    return;
  }

  const mainAppModule = {
  name: 'MainApplication',
  dependencies: [],

  init(WF) {
    this.WF = WF;
    this.ensureSingleNavigation();
    this.updateMainCartCounter();
    this.setupEventListeners();
    this.handleLoginForm();
    this.WF.log('Main Application module initialized.');
  },

  ensureSingleNavigation() {
    const navs = document.querySelectorAll('nav.main-nav');
    if (navs.length > 1) {
      this.WF.log(`Found ${navs.length} navigation elements, removing duplicates...`);
      navs.forEach((el, idx) => { if (idx > 0) el.remove(); });
    }
  },

  updateMainCartCounter() {
    const el = document.getElementById('cartCount');
    if (window.cart && el) {
      el.textContent = `${window.cart.getCount()} items`;
    }
  },

  setupEventListeners() {
    this.WF.eventBus.on('cartUpdated', () => this.updateMainCartCounter());
    if (this.WF.ready) this.WF.ready(() => this.updateMainCartCounter());
  },

  handleLoginForm() {
    const form = document.getElementById('loginForm');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const username = document.getElementById('username').value;
      const password = document.getElementById('password').value;
      const errorMessage = document.getElementById('errorMessage');
      if (errorMessage) errorMessage.classList.add('hidden');
      try {
        const data = await this.WF.api.post('/functions/process_login.php', { username, password });
        sessionStorage.setItem('user', JSON.stringify(data.user || data));
        if (data.redirectUrl) {
          window.location.href = data.redirectUrl;
        } else if (localStorage.getItem('pendingCheckout') === 'true') {
          localStorage.removeItem('pendingCheckout');
          window.location.href = '/?page=cart';
        } else {
          window.location.href = data.role === 'Admin' ? '/?page=admin' : '/?page=room_main';
        }
      } catch (err) {
        if (errorMessage) {
          errorMessage.textContent = err.message;
          errorMessage.classList.remove('hidden');
        }
      }
    });
  },

  async loadModalBackground(roomType) {
    if (!roomType) {
      this.WF.log('[MainApplication] No roomType provided for modal background.', 'warn');
      return;
    }
    try {
      const data = await this.WF.api.get(`/api/get_background.php?room_type=${roomType}`);
      if (data && data.success && data.background) {
        const bg = data.background;
        const supportsWebP = document.documentElement.classList.contains('webp');
        let filename = supportsWebP && bg.webp_filename ? bg.webp_filename : bg.image_filename;
        // Ensure filename does not already include the backgrounds/ prefix
        if (!filename.startsWith('backgrounds/')) {
          filename = `backgrounds/${filename}`;
        }
        const imageUrl = `/images/${filename}`;
        const overlay = document.querySelector('.room-modal-overlay');
        if (overlay) {
          overlay.style.setProperty('--dynamic-bg-url', `url('${imageUrl}')`);
          this.WF.log(`[MainApplication] Modal background loaded for ${roomType}`);
        }
      }
    } catch (err) {
      this.WF.log(`[MainApplication] Error loading modal background for ${roomType}: ${err.message}`, 'error');
    }
  },

  resetToPageBackground() {
    const overlay = document.querySelector('.room-modal-overlay');
    if (overlay) {
      overlay.style.removeProperty('--dynamic-bg-url');
      this.WF.log('[MainApplication] Modal background reset to page background.');
    }
  }
};

  // Register module once
  window.WhimsicalFrog.registerModule(mainAppModule.name, mainAppModule);
})();

// --- End of js/main-application.js --- 

