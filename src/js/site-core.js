/**
 * WhimsicalFrog Core Unified System
 * Consolidated from recovered JavaScript files for Vite compatibility
 * Combines: whimsical-frog-core.js, central-functions.js, and key utilities
 */
import { ApiClient } from '../core/api-client.js';

// Prevent duplicate loading
if (window.WhimsicalFrog && window.WhimsicalFrog.Core) {
    console.log('[WF-Core] Already loaded, skipping initialization');
} else {

(function() {
    'use strict';

    // Core system state
    const WF_CORE = {
        version: '2.0.0',
        initialized: false,
        debug: true,
        modules: {},
        config: {}
    };

    /**
     * Centralized image error handling function
     * Handles image loading failures with appropriate fallbacks
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
                img.onerror = null;
                return;
            }
        }

        // Generic fallback
        img.src = 'images/items/placeholder.webp';
        img.dataset.errorHandled = 'final';
        img.onerror = null;
    };

    /**
     * Simplified image error handler
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
     */
    window.setupImageErrorHandling = function(img, sku = null) {
        if (!img || img.tagName !== 'IMG') {
            return;
        }

        img.onerror = function() {
            window.handleImageError(this, sku);
        };
    };

    // Core logging utility
    function log(message, level = 'info') {
        if (!WF_CORE.debug && level !== 'error') {
            return;
        }

        const timestamp = new Date().toISOString();
        const prefix = `[WF-${level.toUpperCase()}]`;
        
        switch (level) {
            case 'error':
                console.error(`${prefix} ${timestamp}`, message);
                break;
            case 'warn':
                console.warn(`${prefix} ${timestamp}`, message);
                break;
            case 'debug':
                console.debug(`${prefix} ${timestamp}`, message);
                break;
            default:
                console.log(`${prefix} ${timestamp}`, message);
        }
    }

    // Module registration system
    function registerModule(name, moduleDef) {
        if (WF_CORE.modules[name]) {
            log(`Module '${name}' already registered, skipping`, 'warn');
            return false;
        }

        WF_CORE.modules[name] = {
            name,
            ...moduleDef,
            initialized: false
        };

        log(`Module '${name}' registered successfully`);
        return true;
    }

    // Global event system
    const eventBus = {
        events: {},
        
        on(event, callback) {
            if (!this.events[event]) {
                this.events[event] = [];
            }
            this.events[event].push(callback);
        },

        emit(event, data) {
            if (this.events[event]) {
                this.events[event].forEach(callback => {
                    try {
                        callback(data);
                    } catch (error) {
                        log(`Error in event handler for '${event}': ${error.message}`, 'error');
                    }
                });
            }
        },

        off(event, callback) {
            if (this.events[event]) {
                this.events[event] = this.events[event].filter(cb => cb !== callback);
            }
        }
    };

    // Utility functions
    const utils = {
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        generateId() {
            return '_' + Math.random().toString(36).substr(2, 9);
        }
    };

    // API client – delegate to canonical ApiClient to avoid raw fetch
    const api = {
        async request(url, options = {}) {
            try {
                return await ApiClient.request(url, options);
            } catch (error) {
                log(`API request error: ${error.message}`, 'error');
                throw error;
            }
        },
        get(url, params = {}) { return ApiClient.get(url, params); },
        post(url, data = {}, options = {}) { return ApiClient.post(url, data, options); },
        put(url, data = {}, options = {}) { return ApiClient.put(url, data, options); },
        delete(url, options = {}) { return ApiClient.delete(url, options); }
    };

    // Core initialization
    function init() {
        if (WF_CORE.initialized) {
            log('Core already initialized, skipping', 'warn');
            return;
        }

        log('Initializing WhimsicalFrog Core...');
        
        // Initialize modules
        Object.values(WF_CORE.modules).forEach(module => {
            if (module.init && typeof module.init === 'function') {
                try {
                    module.init();
                    module.initialized = true;
                    log(`Module '${module.name}' initialized successfully`);
                } catch (error) {
                    log(`Failed to initialize module '${module.name}': ${error.message}`, 'error');
                }
            }
        });

        WF_CORE.initialized = true;
        log('Core initialization complete');
        // Ensure ready callbacks execute now that initialization is done
        // Emit the public API object so legacy callbacks receive `wf`
        // that contains addModule and other helpers.
        eventBus.emit('core:ready', window.WhimsicalFrog);
    }

    // Expose core system globally BEFORE emitting ready event
    window.WhimsicalFrog = {
        Core: WF_CORE,
        log,
        registerModule,
        on: eventBus.on.bind(eventBus),
        emit: eventBus.emit.bind(eventBus),
        off: eventBus.off.bind(eventBus),
        utils,
        api,
        
        // State accessors
        getState: () => WF_CORE,
        getConfig: () => WF_CORE.config,
        getModule: (name) => WF_CORE.modules[name],
        
        // Legacy compatibility
        addModule: registerModule,
        
        // Convenience functions
        ready(callback) {
            const apiObj = window.WhimsicalFrog;
            if (WF_CORE.initialized) {
                // Pass API object for legacy signatures: ready(wf => wf.addModule(...))
                callback(apiObj);
            } else {
                // Wrap to provide API object regardless of emitter payload
                eventBus.on('core:ready', () => callback(apiObj));
            }
        }
    };

    // Legacy aliases - MUST be set up before 'core:ready' event
    window.WF = window.WhimsicalFrog;
    window.wf = window.WhimsicalFrog;

    // NOW emit ready event after aliases are established
    if (WF_CORE.initialized) {
        eventBus.emit('core:ready', window.WhimsicalFrog);
    }

    // Global fetch wrapper: only add credentials for same-origin requests
    const originalFetch = window.fetch;
    window.fetch = function(resource, init = {}) {
        try {
            const urlStr = (typeof resource === 'string') ? resource : (resource && resource.url ? resource.url : '');
            const urlObj = new URL(urlStr, window.location.origin);
            if (urlObj.origin === window.location.origin) {
                if (!init.credentials) init.credentials = 'same-origin';
            }
        } catch (_) {
            // If parsing fails, leave init as-is
        }
        return originalFetch(resource, init);
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded
        setTimeout(init, 0);
    }

    // Runtime helpers for class-based image heights (no inline styles)
    const WF_IMG_H = { styleEl: null, rules: new Set() };
    function imgHEnsureStyleEl() {
        if (!WF_IMG_H.styleEl) {
            WF_IMG_H.styleEl = document.createElement('style');
            WF_IMG_H.styleEl.id = 'wf-img-height-styles';
            document.head.appendChild(WF_IMG_H.styleEl);
        }
        return WF_IMG_H.styleEl;
    }
    function imgHClassName(h) {
        return `wf-img-h${h}`;
    }
    function imgHEnsureRule(cls, h) {
        if (WF_IMG_H.rules.has(cls)) return;
        const css = `img.${cls} { height: ${h}px; }`;
        imgHEnsureStyleEl().appendChild(document.createTextNode(css));
        WF_IMG_H.rules.add(cls);
    }

    // Setup image heights based on data attributes
    function setupImageHeights() {
        const images = document.querySelectorAll('img[data-height]');
        images.forEach(img => {
            const height = parseInt(img.getAttribute('data-height'), 10);
            if (Number.isFinite(height)) {
                // Remove previous class and any inline height, then apply class-based height
                const prev = img.dataset.wfImgHClass;
                if (prev) img.classList.remove(prev);
                img.style.removeProperty('height');
                const cls = imgHClassName(height);
                imgHEnsureRule(cls, height);
                img.classList.add(cls);
                img.dataset.wfImgHClass = cls;
            }
        });
    }

    // Run image height setup on DOM load
    document.addEventListener('DOMContentLoaded', function() {
        if (!document.body.hasAttribute('data-wf-image-height-setup')) {
            setupImageHeights();
            document.body.setAttribute('data-wf-image-height-setup', 'true');
        }
    });

    log('WhimsicalFrog Core loaded successfully');

})();

}
