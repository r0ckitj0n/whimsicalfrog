/**
 * Central Functions
 * Commonly used utility functions to avoid code duplication
 */

/**
 * Centralized image error handling function
 * Handles image loading failures with appropriate fallbacks
 * @param {HTMLImageElement} img - The image element that failed to load
 * @param {string} sku - Optional SKU for trying alternative image paths
 */
window.handleImageError = function(img, sku = null) {
    const currentState = img.dataset.errorHandled || 'none';
    const currentSrc = img.src;

    if (currentState === 'final') {
        return; // Already at the final fallback
    }

    if (sku) {
        // Try SKU + A + .png
        if (currentSrc.includes(`${sku}A.webp`)) {
            img.src = `images/items/${sku}A.png`;
            img.dataset.errorHandled = 'png-tried';
            return;
        }

        // Try placeholder
        if (currentSrc.includes(`${sku}A.png`)) {
            img.src = 'images/items/placeholder.webp';
            img.dataset.errorHandled = 'final';
            img.onerror = null; // Final attempt, remove handler
            return;
        }
    }

    // Generic fallback if SKU logic fails or no SKU is provided
    img.src = 'images/items/placeholder.webp';
    img.dataset.errorHandled = 'final';
    img.onerror = null;
};

/**
 * Simplified image error handler for when no SKU is available
 * @param {HTMLImageElement} img - The image element that failed to load
 */
window.handleImageErrorSimple = function(img) {
    if (img.dataset.errorHandled) {
        return;
    }
    
    img.src = 'images/items/placeholder.webp';
    img.dataset.errorHandled = 'final';
    img.onerror = null;
};

/**
 * Set up image error handling for an element
 * @param {HTMLImageElement} img - The image element to set up error handling for
 * @param {string} sku - Optional SKU for trying alternative image paths
 */
window.setupImageErrorHandling = function(img, sku = null) {
    img.onerror = function() {
        if (sku) {
            window.handleImageError(this, sku);
        } else {
            window.handleImageErrorSimple(this);
        }
    };
};

// Global fetch wrapper to include credentials for session-based authentication
(function() {
    const originalFetch = window.fetch;
    window.fetch = function(resource, init = {}) {
        init.credentials = init.credentials || 'include';
        return originalFetch(resource, init);
    };
})();

console.log('Central functions loaded successfully');

// Centralized modal overlay click-to-close behavior
// Closes static modal overlays when clicking outside modal content
// and prevents clicks inside content from closing
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.hasAttribute('data-wf-modal-overlay-setup')) {
        return;
    }
    document.body.setAttribute('data-wf-modal-overlay-setup', 'true');

    document.querySelectorAll('.modal-overlay, .admin-modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                overlay.style.display = 'none';
            }
        });
        var content = overlay.querySelector('.admin-modal-content, .modal-content');
        if (content) {
            content.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });
});

// Dynamic image height setup based on data-height attributes
window.setupImageHeights = function() {
    document.querySelectorAll('[data-height]').forEach(function(el) {
        var h = el.getAttribute('data-height');
        if (h) {
            el.style.height = h;
        }
    });
};

// Run image height setup on DOM load
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.hasAttribute('data-wf-image-height-setup')) {
        return;
    }
    document.body.setAttribute('data-wf-image-height-setup', 'true');
    window.setupImageHeights();
}); 

/**
 * Waits for a function to be available on a given scope (e.g., window or window.parent)
 * Can check for nested properties like 'WhimsicalFrog.GlobalModal.show'
 * @param {string} functionPath - The path of the function to wait for (e.g., 'WhimsicalFrog.GlobalModal.show').
 * @param {object} scope - The scope to check for the function on (e.g., window).
 * @param {number} timeout - The maximum time to wait in milliseconds.
 * @returns {Promise<boolean>} - A promise that resolves to true if the function becomes available, false otherwise.
 */
window.waitForFunction = function(functionPath, scope, timeout = 2000) {
    return new Promise((resolve) => {
        let attempts = 0;
        const intervalTime = 100;
        const maxAttempts = timeout / intervalTime;

        const checkFunction = () => {
            const pathParts = functionPath.split('.');
            let current = scope;
            for (let i = 0; i < pathParts.length; i++) {
                if (current === null || typeof current === 'undefined' || typeof current[pathParts[i]] === 'undefined') {
                    return false;
                }
                current = current[pathParts[i]];
            }
            return typeof current === 'function';
        };

        const interval = setInterval(() => {
            if (checkFunction()) {
                clearInterval(interval);
                resolve(true);
            } else if (attempts >= maxAttempts) {
                clearInterval(interval);
                resolve(false);
            }
            attempts++;
        }, intervalTime);
    });
};

// List of section-based theme classes for admin headers (exclude lighter tones)
var ADMIN_HEADER_THEME_CLASSES = (typeof ADMIN_HEADER_THEME_CLASSES !== 'undefined') ? ADMIN_HEADER_THEME_CLASSES : ['content-section','visual-section','business-section','technical-section'];

// Color settings page sections and admin modal headers using section-specific classes
document.addEventListener('DOMContentLoaded', function() {
    if (document.body.hasAttribute('data-wf-admin-header-themed')) {
        return;
    }
    document.body.setAttribute('data-wf-admin-header-themed', 'true');

    if (!document.body.classList.contains('admin-page')) return;
    const sectionClasses = ADMIN_HEADER_THEME_CLASSES; // use named header theme classes
    // Apply random section class to settings page sections
    document.querySelectorAll('.settings-section').forEach(section => {
        const cls = sectionClasses[Math.floor(Math.random() * sectionClasses.length)];
        section.classList.add(cls);
    });
    // Apply random section class to admin modals and mark header for styling
    document.querySelectorAll('.admin-modal-content').forEach(content => {
        const cls = sectionClasses[Math.floor(Math.random() * sectionClasses.length)];
        content.classList.add(cls);
        const header = content.querySelector('.admin-modal-header');
        if (header) header.classList.add('section-header');
    });
}); 

// Centralized action handler registry
var centralFunctions = {
  openEditModal: (el, params) => openEditModal(params.type, params.id),
  openDeleteModal: (el, params) => openDeleteModal(params.type, params.id, params.name),
  performAction: (el, params) => performAction(params.action),
  runCommand: (el, params) => runCommand(params.command),
  loadRoomConfig: () => loadRoomConfig(),
  resetForm: () => resetForm(),
  closeDetailedModalOnOverlay: (el, params, e) => window.WhimsicalFrog.GlobalModal.closeOnOverlay(e),
  closeDetailedModal: () => closeDetailedModal(),
  openImageViewer: (el, params) => openImageViewer(params.src, params.name),
  closeImageViewer: () => closeImageViewer(),
  previousImage: () => previousImage(),
  nextImage: () => nextImage(),
  switchDetailedImage: (el, params) => switchDetailedImage(params.url),
  adjustDetailedQuantity: (el, params) => adjustDetailedQuantity(params.delta),
  addDetailedToCart: (el, params) => addDetailedToCart(params.sku),
  toggleDetailedInfo: () => toggleDetailedInfo(),
  changeSlide: (el, params) => changeSlide(params.carouselId, params.direction),
  goToSlide: (el, params) => goToSlide(params.carouselId, params.index),
  showGlobalPopup: (el, params) => showGlobalPopup(el, params.itemData),
  hideGlobalPopup: () => hideGlobalPopup(),
  openQuantityModal: (el, params) => openQuantityModal(params.itemData),
  editCostItem: (el, params) => editCostItem(params.type, params.id),
  deleteCostItem: (el, params) => deleteCostItem(params.type, params.id, params.name),
  hideCustomAlertBox: () => document.getElementById('customAlertBox').style.display = 'none',
  closeProductModal: () => closeProductModal(),
  handleFormFocus: (el) => el.style.borderColor = getComputedStyle(document.documentElement).getPropertyValue('-form_input_border_focus'),
  handleFormBlur: (el) => el.style.borderColor = getComputedStyle(document.documentElement).getPropertyValue('-brand_primary'),
  showTab: (el, params) => showTab(params.tabName),
  windowPrint: () => window.print(),
  confirmAndPerform: (el, params) => { if (confirm(params.message)) performAction(params.action); },
  executeQuery: () => executeQuery(),
  clearQuery: () => clearQuery(),
  loadTables: () => loadTables(),
  describeTable: () => describeTable(),
  quickQuery: (el, params) => quickQuery(params.sql)
};

// Event delegation for centralized handlers

document.addEventListener('DOMContentLoaded', function() {
  if (document.body.hasAttribute('data-wf-central-listeners-attached')) {
      return;
  }
  document.body.setAttribute('data-wf-central-listeners-attached', 'true');

  document.body.addEventListener('click', function(e) {
    const target = e.target.closest('[data-action]');
    if (!target) return;
    e.preventDefault();
    let params = {};
    try { params = target.dataset.params ? JSON.parse(target.dataset.params) : {}; } catch (err) {}
    const fn = centralFunctions[target.dataset.action];
    if (fn) fn(target, params, e);
  });

  document.body.addEventListener('change', function(e) {
    const target = e.target.closest('[data-change-action]');
    if (!target) return;
    let params = {};
    try { params = target.dataset.params ? JSON.parse(target.dataset.params) : {}; } catch (err) {}
    const fn = centralFunctions[target.dataset.changeAction];
    if (fn) fn(target, params, e);
  });

  document.body.addEventListener('focusin', function(e) {
    const target = e.target.closest('[data-focus-action]');
    if (!target) return;
    const fn = centralFunctions[target.dataset.focusAction];
    if (fn) fn(target, {}, e);
  });

  document.body.addEventListener('focusout', function(e) {
    const target = e.target.closest('[data-blur-action]');
    if (!target) return;
    const fn = centralFunctions[target.dataset.blurAction];
    if (fn) fn(target, {}, e);
  });
  // Delegate mouseover for data-mouseover-action
  document.body.addEventListener('mouseover', function(e) {
    const target = e.target.closest('[data-mouseover-action]');
    if (!target) return;
    let params = {};
    try { params = target.dataset.params ? JSON.parse(target.dataset.params) : {}; } catch (err) {}
    const fn = centralFunctions[target.dataset.mouseoverAction];
    if (fn) fn(target, params, e);
  });
  // Delegate mouseout for data-mouseout-action
  document.body.addEventListener('mouseout', function(e) {
    const target = e.target.closest('[data-mouseout-action]');
    if (!target) return;
    const fn = centralFunctions[target.dataset.mouseoutAction];
    if (fn) fn(target, {}, e);
  });
});
// End of centralized action handlers 