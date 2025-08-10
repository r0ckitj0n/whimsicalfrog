/**
 * Image Error Handler Module
 * Extracted from central-functions.js for modularization.
 * Attaches helpers to window for backward compatibility but also exports for ES-module future.
 */

(function(global) {
    'use strict';

    /**
     * Handles image loading failures with progressive fallbacks based on SKU.
     * @param {HTMLImageElement} img - The image element that failed to load.
     * @param {string|null} sku - Optional SKU used to attempt alternate image paths.
     */
    function handleImageError(img, sku = null) {
        const currentState = img.dataset.errorHandled || 'none';
        const currentSrc   = img.src;

        if (currentState === 'final') return; // already tried all fallbacks

        if (sku) {
            // Try SKU + A + .png after failing .webp
            if (currentSrc.includes(`${sku}A.webp`)) {
                img.src = `images/items/${sku}A.png`;
                img.dataset.errorHandled = 'png-tried';
                return;
            }

            // Try placeholder after failing .png
            if (currentSrc.includes(`${sku}A.png`)) {
                setPlaceholder(img);
                return;
            }
        }

        setPlaceholder(img);
    }

    function setPlaceholder(img) {
        img.src = 'images/items/placeholder.webp';
        img.dataset.errorHandled = 'final';
        img.onerror = null; // stop further error handling
    }

    /**
     * Simplified image error handler for unknown SKU images.
     * @param {HTMLImageElement} img
     */
    function handleImageErrorSimple(img) {
        if (img.dataset.errorHandled) return;
        setPlaceholder(img);
    }

    /**
     * Attaches an `onerror` listener to an image element that will trigger the proper handler.
     * @param {HTMLImageElement} img
     * @param {string|null} sku
     */
    function setupImageErrorHandling(img, sku = null) {
        img.onerror = function() {
            if (sku) {
                handleImageError(this, sku);
            } else {
                handleImageErrorSimple(this);
            }
        };
    }

    // Expose to global (legacy)
    global.handleImageError        = global.handleImageError        || handleImageError;
    global.handleImageErrorSimple  = global.handleImageErrorSimple  || handleImageErrorSimple;
    global.setupImageErrorHandling = global.setupImageErrorHandling || setupImageErrorHandling;

    // Future ES-module export support
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { handleImageError, handleImageErrorSimple, setupImageErrorHandling };
    } else if (typeof define === 'function' && define.amd) {
        define([], () => ({ handleImageError, handleImageErrorSimple, setupImageErrorHandling }));
    } else {
        global.ImageErrorHandler = { handleImageError, handleImageErrorSimple, setupImageErrorHandling };
    }

    console.log('[ImageErrorHandler] module loaded');

})(typeof window !== 'undefined' ? window : this);
