// --- Start of js/whimsical-frog-core.js --- 

/**
 * WhimsicalFrog Core JavaScript System
 * Unified dependency management and initialization
 */

(function() {
    // Prevent duplicate loading / re-initialization if Core already present
    if (window.WhimsicalFrog && window.WhimsicalFrog.Core) {
        // Ensure legacy API shims
        if (typeof window.WhimsicalFrog.log !== 'function') {
            window.WhimsicalFrog.log = (...args) => console.log('[WF-Legacy]', ...args);
        }
        if (!window.WF) window.WF = window.WhimsicalFrog;
        if (typeof window.WF.log !== 'function') window.WF.log = window.WhimsicalFrog.log;
        if (!window.wf) window.wf = window.WhimsicalFrog;
        if (!window.WhimsicalFrog.addModule && typeof window.WhimsicalFrog.registerModule === 'function') {
            window.WhimsicalFrog.addModule = window.WhimsicalFrog.registerModule;
        }
        return;
    }
    'use strict';

    // Core system state
    const WF_CORE = {
        version: '1.0.0',
        initialized: false,
        debug: true,
        modules: {},
        config: {
            brand: {
                primary: '#87ac3a',
                primaryDark: '#6b8e23',
                primaryLight: '#a3cc4a'
            },
            api: {
                baseUrl: '/api/',
                timeout: 30000
            },
            ui: {
                animationDuration: 300,
                notificationTimeout: 5000,
                popupDelay: 250
            }
        },
        state: {
            currentPage: null,
            currentRoom: null,
            modalsOpen: 0,
            popupOpen: false,
            cartCount: 0
        }
    };

    // Logging utility
    function log(message, level = 'info') {
        if (!WF_CORE.debug && level === 'debug') return;
        const timestamp = new Date().toISOString().slice(11, 23);
        const prefix = `[WF-Core ${timestamp}]`;
        
        switch (level) {
            case 'error':
                console.error(prefix, message);
                break;
            case 'warn':
                console.warn(prefix, message);
                break;
            case 'debug':
                console.debug(prefix, message);
                break;
            default:
                console.log(prefix, message);
        }
    }

    // Module registration system
    function registerModule(name, moduleDef) {
            // Attach name to module for debugging
            if (moduleDef && typeof moduleDef === 'object') {
                moduleDef.__name = name;
            }
        if (WF_CORE.modules[name]) {
            log(`Module ${name} already registered, overwriting`, 'warn');
        }
        
        WF_CORE.modules[name] = {
            ...moduleDef,
            initialized: false,
            dependencies: moduleDef.dependencies || [],
            priority: moduleDef.priority || 0
        };
        
        log(`Module registered: ${name}`);
    }

    // Dependency resolver
    function resolveDependencies() {
        const moduleNames = Object.keys(WF_CORE.modules);
        const resolved = [];
        const resolving = [];

        function resolve(name) {
            if (resolved.includes(name)) return;
            if (resolving.includes(name)) {
                throw new Error(`Circular dependency detected: ${name}`);
            }

            resolving.push(name);
            const module = WF_CORE.modules[name];
            
            if (module.dependencies) {
                module.dependencies.forEach(dep => {
                    if (!WF_CORE.modules[dep]) {
                        throw new Error(`Missing dependency: ${dep} for module ${name}`);
                    }
                    resolve(dep);
                });
            }

            resolving.splice(resolving.indexOf(name), 1);
            resolved.push(name);
        }

        moduleNames.forEach(resolve);
        return resolved;
    }

    // Module initialization
    async function initializeModules() {
        // Ensure WF.log exists before any module initialization
        if (!window.WF || typeof window.WF.log !== 'function') {
            window.WF = window.WF || window.WhimsicalFrog;
            window.WF.log = log;
        }
        try {
            const initOrder = resolveDependencies();
            log(`Initializing modules in order: ${initOrder.join(', ')}`);

            for (const moduleName of initOrder) {
                const module = WF_CORE.modules[moduleName];
                
                if (module.init && typeof module.init === 'function') {
                    log(`Initializing module: ${moduleName}`);
                    await module.init(window.WhimsicalFrog);
                    module.initialized = true;
                    log(`Module initialized: ${moduleName}`);
                } else {
                    log(`Module ${moduleName} has no init function`, 'warn');
                }
            }
            
            log('All modules initialized successfully');
            return true;
        } catch (error) {
            log(`Module initialization failed: ${error.message}`, 'error');
            throw error;
        }
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
                        log(`Event callback error for ${event}: ${error.message}`, 'error');
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
        // Debounce function
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

        // Format currency
        formatCurrency(amount) {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD'
            }).format(amount);
        },

        // Escape HTML
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        // Generate unique ID
        generateId() {
            return 'wf-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        },

        // Deep merge objects
        deepMerge(target, source) {
            const result = { ...target };
            for (const key in source) {
                if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                    result[key] = this.deepMerge(result[key] || {}, source[key]);
                } else {
                    result[key] = source[key];
                }
            }
            return result;
        }
    };

    // API client
    const api = {
        async request(url, options = {}) {
            const config = {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                ...options
            };

            try {
                const response = await fetch(url, config);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                
                if (data.success === false) {
                    throw new Error(data.error || 'API request failed');
                }
                
                return data;
            } catch (error) {
                log(`API request failed: ${error.message}`, 'error');
                throw error;
            }
        },

        get(url, options = {}) {
            return this.request(url, { ...options, method: 'GET' });
        },

        post(url, data = null, options = {}) {
            return this.request(url, {
                ...options,
                method: 'POST',
                body: data ? JSON.stringify(data) : null
            });
        },

        put(url, data = null, options = {}) {
            return this.request(url, {
                ...options,
                method: 'PUT',
                body: data ? JSON.stringify(data) : null
            });
        },

        delete(url, options = {}) {
            return this.request(url, { ...options, method: 'DELETE' });
        }
    };

    // Core initialization
    async function init() {
        if (WF_CORE.initialized) {
            log('Core already initialized', 'warn');
            return;
        }

        log('Starting WhimsicalFrog Core initialization...');
        
        // Set current page
        const urlParams = new URLSearchParams(window.location.search);
        WF_CORE.state.currentPage = urlParams.get('page') || 'landing';
        
        // Initialize modules
        await initializeModules();
        
        // All modules loaded successfully
        log('All modules initialized successfully');
        
        // Ensure cart system is accessible
        setTimeout(() => {
            if (window.cart) {
                log('✅ Cart system verified and accessible');
                log('Cart methods available:', {
                    addItem: typeof window.cart.addItem,
                    getItems: typeof window.cart.getItems,
                    getTotal: typeof window.cart.getTotal
                });
                
                // Make sure cart is accessible from any potential iframe
                if (typeof window.accessCart === 'function') {
                    log('✅ Enhanced cart access method available');
                } else {
                    log('⚠️ Enhanced cart access method not found');
                }
            } else {
                log('❌ Cart system not found after initialization');
            }
        }, 100);
        
        WF_CORE.initialized = true;
        
        // Emit initialization complete event
        eventBus.emit('core:initialized', window.WhimsicalFrog);
        
        log('WhimsicalFrog Core initialization complete');
    }

    // Expose core system globally
    // Expose core system globally
window.WhimsicalFrog = {
        // Public logging utility for modules
        log: (...args) => log(...args),
         // Global logging utility
 
 
 
        
        Core: WF_CORE,
        registerModule,
        init,
        utils,
        api,
        eventBus,
        
        // State accessors
        getState: () => WF_CORE.state,
        getConfig: () => WF_CORE.config,
        getModule: (name) => WF_CORE.modules[name],
        // Legacy compatibility aliases (deprecated)
        addModule: registerModule,
        
        // Convenience functions
        ready: (callback) => {
            if (WF_CORE.initialized) {
                callback(window.WhimsicalFrog);
            } else {
                eventBus.on('core:initialized', callback);
            }
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM is already loaded, initialize immediately
        setTimeout(init, 0);
    }

    log('WhimsicalFrog Core loaded');
    // Provide global WF alias for backward compatibility
    // Ensure legacy WF alias has logging as well
    if (!window.WF) {
        window.WF = window.WhimsicalFrog;
    }
    // Provide lowercase 'wf' alias for legacy compatibility
    if (!window.wf) {
        window.wf = window.WF;
    }
    // Ensure WF.log exists (and therefore wf.log too)
    if (typeof window.WF.log !== 'function') {
        window.WF.log = log;
    }
    if (typeof window.wf.log !== 'function') {
        window.wf.log = log;
    }
})(); 

// --- End of js/whimsical-frog-core.js --- 

// --- Start of js/utils.js --- 

/**
 * Centralized JavaScript Utilities
 * 
 * This file provides common utility functions used across the frontend
 * to reduce code duplication and ensure consistent behavior.
 */

class ApiClient {
    /**
     * Make an authenticated API request
     * @param {string} url - The API endpoint URL
     * @param {Object} options - Fetch options
     * @returns {Promise<Object>} - The response data
     */
    static async request(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        };

        const config = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...options.headers
            }
        };

        try {
            const response = await fetch(url, config);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (data.success === false) {
                throw new Error(data.error || 'API request failed');
            }
            
            return data;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }

    /**
     * Make a GET request
     * @param {string} url - The API endpoint URL
     * @param {Object} params - URL parameters
     * @returns {Promise<Object>} - The response data
     */
    static async get(url, params = {}) {
        const urlObj = new URL(url, window.location.origin);
        Object.keys(params).forEach(key => {
            if (params[key] !== null && params[key] !== undefined) {
                urlObj.searchParams.append(key, params[key]);
            }
        });
        
        return this.request(urlObj.toString(), { method: 'GET' });
    }

    /**
     * Make a POST request
     * @param {string} url - The API endpoint URL
     * @param {Object} data - Request body data
     * @returns {Promise<Object>} - The response data
     */
    static async post(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    /**
     * Make a PUT request
     * @param {string} url - The API endpoint URL
     * @param {Object} data - Request body data
     * @returns {Promise<Object>} - The response data
     */
    static async put(url, data = {}) {
        return this.request(url, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    /**
     * Make a DELETE request
     * @param {string} url - The API endpoint URL
     * @returns {Promise<Object>} - The response data
     */
    static async delete(url) {
        return this.request(url, { method: 'DELETE' });
    }

    /**
     * Upload a file with FormData
     * @param {string} url - The API endpoint URL
     * @param {FormData} formData - Form data containing file
     * @returns {Promise<Object>} - The response data
     */
    static async upload(url, formData) {
        return this.request(url, {
            method: 'POST',
            body: formData,
            headers: {} // Let browser set Content-Type for FormData
        });
    }
}

class DOMUtils {
    /**
     * Safely set innerHTML with loading state
     * @param {HTMLElement} element - Target element
     * @param {string} content - HTML content to set
     * @param {boolean} showLoading - Whether to show loading state first
     */
    static setContent(element, content, showLoading = false) {
        if (!element) return;
        
        if (showLoading) {
            element.innerHTML = '<div class="text-center text-gray-500 py-4">Loading...</div>';
            // Use setTimeout to allow DOM to update
            setTimeout(() => {
                element.innerHTML = content;
            }, 100);
        } else {
            element.innerHTML = content;
        }
    }

    /**
     * Create a loading spinner element
     * @param {string} message - Loading message
     * @returns {string} - HTML for loading spinner
     */
    static createLoadingSpinner(message = 'Loading...') {
        return `
            <div class="flex items-center justify-center py-4">
                <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500 mr-3"></div>
                <span class="text-gray-600">${message}</span>
            </div>
        `;
    }

    /**
     * Create an error message element
     * @param {string} message - Error message
     * @returns {string} - HTML for error message
     */
    static createErrorMessage(message) {
        return `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    ${message}
                </div>
            </div>
        `;
    }

    /**
     * Create a success message element
     * @param {string} message - Success message
     * @returns {string} - HTML for success message
     */
    static createSuccessMessage(message) {
        return `
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    ${message}
                </div>
            </div>
        `;
    }

    /**
     * Show a temporary toast notification
     * @param {string} message - Message to show
     * @param {string} type - Type: 'success', 'error', 'info'
     * @param {number} duration - Duration in ms
     */
    static showToast(message, type = 'info', duration = 3000) {
        const toastId = 'toast-' + Date.now();
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            info: 'bg-blue-500'
        };
        
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `fixed top-4 right-4 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transform transition-transform duration-300 translate-x-full`;
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 100);
        
        // Animate out and remove
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                if (document.getElementById(toastId)) {
                    document.body.removeChild(toast);
                }
            }, 300);
        }, duration);
    }

    /**
     * Debounce function calls
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in ms
     * @returns {Function} - Debounced function
     */
    static debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Format currency value
     * @param {number} value - Numeric value
     * @returns {string} - Formatted currency string
     */
    static formatCurrency(value) {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(value);
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} - Escaped text
     */
    static escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Create a confirmation dialog
     * @param {string} message - Confirmation message
     * @param {string} title - Dialog title
     * @returns {Promise<boolean>} - True if confirmed
     */
    static async confirm(message, title = 'Confirm') {
        return new Promise((resolve) => {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md mx-4">
                    <h3 class="text-lg font-semibold mb-4">${this.escapeHtml(title)}</h3>
                    <p class="text-gray-600 mb-6">${this.escapeHtml(message)}</p>
                    <div class="flex justify-end space-x-3">
                        <button class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded" data-action="cancel">
                            Cancel
                        </button>
                        <button class="px-4 py-2 bg-red-500 text-white hover:bg-red-600 rounded" data-action="confirm">
                            Confirm
                        </button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            modal.addEventListener('click', (e) => {
                if (e.target.dataset.action === 'confirm') {
                    document.body.removeChild(modal);
                    resolve(true);
                } else if (e.target.dataset.action === 'cancel' || e.target === modal) {
                    document.body.removeChild(modal);
                    resolve(false);
                }
            });
        });
    }
}

// Make utilities available globally
window.ApiClient = ApiClient;
window.DOMUtils = DOMUtils;

// Global API client instance
const apiClient = new ApiClient();

// Convenience functions for quick API calls
window.apiGet = (url, options = {}) => apiClient.get(url, options);
window.apiPost = (url, data = null, options = {}) => apiClient.post(url, data, options);
window.apiPut = (url, data = null, options = {}) => apiClient.put(url, data, options);
window.apiDelete = (url, options = {}) => apiClient.delete(url, options);

// Utility functions for common patterns
window.debounce = DOMUtils.debounce;
window.formatCurrency = DOMUtils.formatCurrency;
window.escapeHtml = DOMUtils.escapeHtml;
window.showToast = DOMUtils.showToast;
window.confirmDialog = DOMUtils.confirm;

// Deprecation warnings for direct fetch usage (development only)
if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        if (args[0] && typeof args[0] === 'string' && args[0].includes('/api/')) {
            console.warn('⚠️ Consider using apiClient instead of direct fetch for API calls:', args[0]);
            console.warn('Example: apiGet("' + args[0] + '") or apiPost("' + args[0] + '", data)');
        }
        return originalFetch.apply(this, args);
    };
} 

// --- End of js/utils.js --- 

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
        let url = typeof input === 'string' ? input : input.url;
        if (/\/api\//.test(url)) {
            console.warn('⚠️  Consider using apiGet/apiPost instead of direct fetch for API calls:', url);
        }
        return nativeFetch(input, init);
    };

    console.log('[api-client] Initialized');
})(window);


// --- End of js/api-client.js --- 

// --- Start of js/central-functions.js --- 

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

// Register as WF Core module (no init needed)
if (window.WhimsicalFrog && typeof window.WhimsicalFrog.registerModule === 'function') {
    window.WhimsicalFrog.registerModule('CentralFunctions', {
        name: 'CentralFunctions',
        init: function() {
            console.log('[CentralFunctions] Module loaded');
        }
    });
}

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

// --- End of js/central-functions.js --- 

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

/**
 * WhimsicalFrog Unified Popup System
 * Consolidated popup management with enhanced features
 * Replaces both global-popup.js and modules/popup-system.js
 */

console.log('Loading WhimsicalFrog unified popup system...');

// Enhanced popup state management
const popupState = {
    currentProduct: null,
    isVisible: false,
    hideTimeout: null,
    popupElement: null,
    initialized: false,
    isInRoomModal: false
};

// Enhanced popup system class
class UnifiedPopupSystem {
    constructor() {
        this.init();
    }

    init() {
        if (popupState.initialized) return;
        
        // Find or create popup element
        this.setupPopupElement();
        
        // Setup enhanced event listeners
        this.setupEventListeners();
        
        // Register global functions
        this.registerGlobalFunctions();
        
        popupState.initialized = true;
        console.log('✅ WhimsicalFrog Unified Popup System initialized');
    }

    setupPopupElement() {
        popupState.popupElement = document.getElementById('productPopup') || 
                                 document.getElementById('itemPopup');
        
        if (!popupState.popupElement) {
            console.log('Creating popup element...');
            this.createPopupElement();
        }
    }

    createPopupElement() {
        const popupHTML = `
            <div id="itemPopup" class="item-popup">
                <div class="popup-content">
                    <div class="popup-header">
                        <div class="popup-image-container u-position-relative">
                            <img id="popupImage" class="popup-image" src="" alt="Product Image">
                            <!- Marketing Badge overlaying image ->
                            <div id="popupMarketingBadge" class="popup-marketing-badge hidden u-position-absolute u-top-6px u-right-6px u-z-index-10">
                                <span class="marketing-badge">
                                    <span id="popupMarketingText"></span>
                                </span>
                            </div>
                        </div>
                        <div class="popup-info">
                            <h3 id="popupTitle" class="popup-title"></h3>
                            <div id="popupMainSalesPitch" class="popup-main-sales-pitch"></div>
                            <p id="popupCategory" class="popup-category"></p>
                            <p id="popupSku" class="popup-sku"></p>
                            <div id="popupStock" class="popup-stock-info"></div>
                            <div id="popupCurrentPrice" class="popup-price"></div>
                        </div>
                    </div>
                    <div class="popup-body">
                        <p id="popupDescription" class="popup-description"></p>
                    </div>
                    <div class="popup-footer">
                        <button id="popupAddBtn" class="popup-btn popup-btn-primary">Add to Cart</button>

                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', popupHTML);
        popupState.popupElement = document.getElementById('itemPopup');
    }

    setupEventListeners() {
        const popup = popupState.popupElement;
        if (!popup) return;
        
        // Enhanced hover persistence
        popup.addEventListener('mouseenter', () => {
            this.clearHideTimeout();
            popupState.isVisible = true;
        });
        
        popup.addEventListener('mouseleave', () => {
            this.hide();
        });
        
        // Click outside to close
        document.addEventListener('click', (e) => {
            if (popupState.isVisible && 
                !popup.contains(e.target) && 
                !e.target.closest('.item-icon')) {
                this.hideImmediate();
            }
        });
        
        // Make entire popup clickable to open item details
        const popupContent = popup.querySelector('.popup-content') || popup.querySelector('.popup-content-enhanced');
        if (popupContent) {
            popupContent.addEventListener('click', (e) => {
                // Check if the click was on a button - if so, let the button handle it
                if (e.target.closest('#popupAddBtn') || 
                    e.target.closest('#popupDetailsBtn') ||
                    e.target.closest('.popup-add-btn') ||
                    e.target.closest('.popup-add-btn-enhanced') ||
                    e.target.closest('.popup-details-btn-enhanced')) {
                    return; // Let the button handlers take care of this
                }
                
                e.preventDefault();
                e.stopPropagation();
                this.handleViewDetails();
            });
            
            // Add visual feedback for clickability (CSS already handles this, but ensure it's set)
            popupContent.style.cursor = 'pointer';
        }
        
        // Button event listeners
        const addBtn = popup.querySelector('#popupAddBtn');
        
        if (addBtn) {
            addBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleAddToCart();
            });
        }
    }

    // Main popup display function  
    show(element, item) {
        console.log('Showing popup for:', element, item);
        
        if (!popupState.initialized) {
            console.warn('Popup system not initialized, initializing now...');
            this.init();
        }
        
        const popup = popupState.popupElement;
        if (!popup) {
            console.error('Popup element not found!');
            return;
        }
        
        // Clear any existing timeouts
        this.clearHideTimeout();
        
        // Hide any existing popup first
        this.hideImmediate();
        
        // Clear previous product state before setting new one
        this.clearProductState();
        
        // Set state
        popupState.currentProduct = item;
        popupState.isVisible = true;
        popupState.isInRoomModal = document.querySelector('.room-modal-overlay') !== null;
        
        // Set global state for backward compatibility
        window.isShowingPopup = true;
        window.popupOpen = true;
        window.currentItem = item;
        
        // Update popup content
        this.updateContent(item);
        
        // Position and show popup
        this.positionPopup(element, popup);
        
        console.log('Popup should now be visible');
    }

    // Update popup content
    updateContent(item) {
        const popup = popupState.popupElement;
        if (!popup) return;
        
        // Reset badge visibility at the start
        const marketingBadge = popup.querySelector('#popupMarketingBadge');
        if (marketingBadge) {
            marketingBadge.classList.add('hidden');
        }
        
        const popupImage = popup.querySelector('#popupImage');
        const popupTitle = popup.querySelector('#popupTitle');
        const popupMainSalesPitch = popup.querySelector('#popupMainSalesPitch');
        const popupCategory = popup.querySelector('#popupCategory');
        const popupSku = popup.querySelector('#popupSku');
        const popupStock = popup.querySelector('#popupStock');
        const popupCurrentPrice = popup.querySelector('#popupCurrentPrice');
        const popupDescription = popup.querySelector('#popupDescription');
        const popupSalesPitch = popup.querySelector('#popupSalesPitch');
        
        // Update image
        if (popupImage) {
            popupImage.src = `images/items/${item.sku}A.webp`;
            
            // Use centralized image error handling
            if (typeof window.setupImageErrorHandling === 'function') {
                window.setupImageErrorHandling(popupImage, item.sku);
            } else {
                // Fallback if central functions not loaded yet
                popupImage.onerror = function() {
                    this.src = 'images/items/placeholder.webp';
                    this.onerror = null;
                };
            }
        }
        
        // Update text content
        if (popupTitle) {
            popupTitle.textContent = item.name || item.productName || 'Item';
        }
        
        if (popupCategory) {
            popupCategory.textContent = item.category || 'Item';
        }
        
        if (popupSku) {
            popupSku.textContent = `SKU: ${item.sku}`;
        }
        
        // Update stock info and badges
        if (popupStock) {
            const stockLevel = parseInt(item.stockLevel || item.stock || 0);
            if (stockLevel <= 0) {
                popupStock.textContent = 'Out of Stock';
                popupStock.className = 'popup-stock-info out-of-stock';
            } else if (stockLevel <= 5) {
                popupStock.textContent = `${stockLevel} Left`;
                popupStock.className = 'popup-stock-info limited-stock';
            } else {
                popupStock.textContent = 'In Stock';
                popupStock.className = 'popup-stock-info in-stock';
            }
        }
        
        if (popupCurrentPrice) {
            // Check for sales and update pricing
            if (typeof window.checkAndDisplaySalePrice === 'function') {
                window.checkAndDisplaySalePrice(item, popupCurrentPrice);
            } else {
                popupCurrentPrice.textContent = `$${parseFloat(item.retailPrice || item.price || 0).toFixed(2)}`;
            }
        }
        
        if (popupDescription) {
            popupDescription.textContent = item.description || '';
        }
        
        // Load and display marketing data (badge and sales pitch line)
        if (item.sku) {
            this.loadMarketingData(item.sku, null, popupMainSalesPitch);
        }
    }
    


    // Load marketing data for badge and sales pitch line
    async loadMarketingData(sku, salesPitchElement, mainSalesPitchElement) {
        try {
            const data = await apiGet(`get_marketing_data.php?sku=${sku}`);
            
            
            if (data.success && data.exists && data.marketing_data) {
                const marketing = data.marketing_data;
                // Display marketing badge and main sales pitch
                this.displayMarketingBadge(marketing);
                this.displayMainSalesPitch(marketing, mainSalesPitchElement);
            } else {
                // Show generic marketing content as fallback
                this.displayGenericMarketingContent(mainSalesPitchElement);
            }
        } catch (error) {
            console.log('Marketing data not available:', error);
            // Show generic marketing content as fallback
            this.displayGenericMarketingContent(mainSalesPitchElement);
        }
    }
    
    // Display main sales pitch below title (single line)
    displayMainSalesPitch(marketing, element) {
        if (!element) return;
        
        let mainPitch = '';
        
        // Try to get the best single sales pitch
        if (marketing.selling_points && marketing.selling_points.length > 0) {
            // Use the first selling point as the main sales pitch
            mainPitch = marketing.selling_points[0];
        } else if (marketing.competitive_advantages && marketing.competitive_advantages.length > 0) {
            // Fallback to competitive advantage
            mainPitch = marketing.competitive_advantages[0];
        } else if (marketing.customer_benefits && marketing.customer_benefits.length > 0) {
            // Fallback to customer benefit
            mainPitch = marketing.customer_benefits[0];
        }
        
        if (mainPitch) {
            // Display full main sales pitch (no truncation)
            element.innerHTML = `<div class="popup-main-pitch">${mainPitch}</div>`;
        } else {
            element.innerHTML = '';
        }
    }
    
    // Display marketing badge (if available)
    displayMarketingBadge(marketing) {
        const popup = popupState.popupElement;
        if (!popup) return;
        const marketingBadge = popup.querySelector('#popupMarketingBadge');
        const marketingTextEl = popup.querySelector('#popupMarketingText');
        if (!marketingBadge || !marketingTextEl) return;

        // Determine badge text and optional type
        let badgeText = '';
        if (marketing.badges && marketing.badges.length > 0) {
            badgeText = marketing.badges[0].text || marketing.badges[0];
        } else if (marketing.primary_badge) {
            badgeText = marketing.primary_badge;
        }

        if (badgeText) {
            marketingTextEl.textContent = badgeText;
            marketingBadge.classList.remove('hidden');
            marketingBadge.style.display = 'block';
        } else {
            marketingBadge.classList.add('hidden');
            marketingBadge.style.display = 'none';
        }
    }



    // Display generic marketing content when no marketing data available
    displayGenericMarketingContent(mainSalesPitchElement) {
        // DISABLE OLD POPUP MARKETING SYSTEM
        // This has been replaced by the unified badge scoring system
        console.log('🚫 Old popup generic marketing system disabled - using unified system instead');
        
        // Show generic marketing line only (no badges)
        if (mainSalesPitchElement) {
            const genericMessages = [
                '✨ Experience premium quality and exceptional style!',
                '🌟 Discover the perfect addition to your collection!',
                '💎 Elevate your look with this must-have piece!',
                '🔥 Join the style revolution with this trendy item!',
                '⭐ Transform your wardrobe with superior craftsmanship!'
            ];
            
            const randomMessage = genericMessages[Math.floor(Math.random() * genericMessages.length)];
            mainSalesPitchElement.innerHTML = `<div class="popup-main-pitch">${randomMessage}</div>`;
        }
    }

    // Hide popup with delay
    hide(delay = 250) {
        if (!popupState.isVisible) return;
        
        this.clearHideTimeout();
        
        popupState.hideTimeout = setTimeout(() => {
            this.hideImmediate();
        }, delay);
    }

    // Hide popup immediately
    hideImmediate() {
        // Remove overlay dimming reduction
        try {
            if (window.parent && window.parent !== window) {
                const overlay = window.parent.document.querySelector('.room-modal-overlay');
                if (overlay) overlay.classList.remove('popup-active');
            }
        } catch(e) {}

        this.clearHideTimeout();
        
        const popup = popupState.popupElement;
        if (popup) {
            popup.classList.remove('show', 'in-room-modal', 'visible', 'positioned');
            popup.classList.add('hidden');
        }
        
        // Reset state
        popupState.isVisible = false;
        
        // Reset global state for backward compatibility
        window.popupOpen = false;
        window.isShowingPopup = false;
        
        console.log('Popup hidden immediately');
    }
    
    // Clear product state - called only when we're sure it's safe
    clearProductState() {
        popupState.currentProduct = null;
        window.currentItem = null;
    }

    // Clear hide timeout
    clearHideTimeout() {
        if (popupState.hideTimeout) {
            clearTimeout(popupState.hideTimeout);
            popupState.hideTimeout = null;
        }
    }

    // Handle add to cart button
    handleAddToCart() {
        console.log('🔧 handleAddToCart called, currentProduct:', popupState.currentProduct);
        
        // First check if we have currentProduct, if not try to get it from global state
        const productToUse = popupState.currentProduct || window.currentItem;
        
        if (!productToUse) {
            console.error('🔧 No current product in popup state or global state!');
            return;
        }
        
        console.log('🔧 About to hide popup and open modal for SKU:', productToUse.sku);
        
        // Store the product SKU before hiding popup to prevent timing issues
        const skuToOpen = productToUse.sku;
        
        this.hideImmediate();
        
        // Try to open item modal
        let modalFn = window.showGlobalItemModal;
        if (typeof modalFn !== 'function' && window.parent && window.parent !== window) {
            modalFn = window.parent.showGlobalItemModal;
        }
        // Extra fallback to unified global modal namespace
        if (typeof modalFn !== 'function' && window.WhimsicalFrog && window.WhimsicalFrog.GlobalModal && typeof window.WhimsicalFrog.GlobalModal.show === 'function') {
            modalFn = window.WhimsicalFrog.GlobalModal.show;
        }
        if (typeof modalFn !== 'function' && window.parent && window.parent.WhimsicalFrog && window.parent.WhimsicalFrog.GlobalModal && typeof window.parent.WhimsicalFrog.GlobalModal.show === 'function') {
            modalFn = window.parent.WhimsicalFrog.GlobalModal.show;
        }

        if (typeof modalFn === 'function') {
            console.log('🔧 showGlobalItemModal function available, calling with SKU:', skuToOpen);
            modalFn(skuToOpen, productToUse);
            this.clearProductState();
        } else {
            console.error('🔧 showGlobalItemModal function not available in current or parent context!');
        }
    }

    async handleViewDetails() {
        console.log('🔧 handleViewDetails called, currentProduct:', popupState.currentProduct);
        if (!popupState.currentProduct) return;

        console.log(`🔧 About to hide popup and open details modal for SKU: ${popupState.currentProduct.sku}`);
        this.hideImmediate();

        const functionPath = 'WhimsicalFrog.GlobalModal.show';

        // First try within the current window (iframe or main)
        let isReady = await window.waitForFunction(functionPath, window);
        if (isReady) {
            window.WhimsicalFrog.GlobalModal.show(popupState.currentProduct.sku, popupState.currentProduct);
            return;
        }

        // Fallback: try parent window (when running inside iframe)
        if (window.parent && window.parent !== window) {
            isReady = await window.waitForFunction(functionPath, window.parent);
            if (isReady) {
                window.parent.WhimsicalFrog.GlobalModal.show(popupState.currentProduct.sku, popupState.currentProduct);
                return;
            }
        }

        console.error(`🔧 ${functionPath} function not available in current or parent context!`);
        if (typeof window.showGlobalNotification === 'function') {
            window.showGlobalNotification('Could not open item details. Please try again.', 'error');
        }
    }

    // Position popup function with improved positioning logic
    positionPopup(element, popup) {
        console.log('Positioning popup...', element, popup);
        // Detect and mark that we are inside a room-modal iframe (heuristic: parent has .room-modal-overlay)
        try {
            if (window.parent && window.parent !== window && window.parent.document.querySelector('.room-modal-overlay')) {
                popupState.isInRoomModal = true;
            }
        } catch(e) { /* cross-origin safe guard */ }
        
        const rect = element.getBoundingClientRect();
        
        // Determine z-index from CSS variables: popups layer or just above room-modal overlay
        const isInRoomModal = popupState.isInRoomModal;
        const rootStyles = getComputedStyle(document.documentElement);
        // Read CSS custom properties correctly (two leading dashes)
        const popupDefaultZ = parseInt(rootStyles.getPropertyValue('--popup-z-index').trim() || '2600', 10);
        const roomModalZ = parseInt(rootStyles.getPropertyValue('--z-room-modals').trim() || '2400', 10);
        const zIndex = isInRoomModal ? roomModalZ + 1 : popupDefaultZ;
        // Toggle class to leverage room-modal-specific CSS rules
        if (isInRoomModal) {
            popup.classList.add('in-room-modal');
        } else {
            popup.classList.remove('in-room-modal');
        }

        // Show popup temporarily to get actual dimensions using CSS classes
        // Ensure we never exceed configured max width
        let cssMax = rootStyles.getPropertyValue('--popup-max-width').trim() || '450px';
        if (isInRoomModal) {
            // Allow natural content width for room-modal popups
            cssMax = 'none';
            popup.style.width = 'max-content';
            popup.style.minWidth = '0';
            popup.style.maxWidth = 'none';
        } else {
            popup.style.maxWidth = cssMax;
        }
        popup.classList.remove('hidden');
        popup.classList.add('measuring');
        // Ensure inline z-index override even if custom property previously set
        popup.style.zIndex = zIndex;
        popup.style.zIndex = zIndex;
        
        const popupRect = popup.getBoundingClientRect();
        const popupWidth = Math.min(popupRect.width, parseInt(cssMax));
        const popupHeight = popupRect.height;
        
        // Get viewport dimensions with safety margins
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const margin = 10; // Safety margin from edges
        
        // Calculate preferred position - try to center popup on element horizontally
        let left = rect.left + (rect.width / 2) - (popupWidth / 2);
        let top = rect.top - popupHeight - margin; // Above element by default
        
        // Horizontal positioning logic
        // Ensure popup doesn't go off left edge
        if (left < margin) {
            left = margin;
        }
        
        // Ensure popup doesn't go off right edge
        if (left + popupWidth + margin > viewportWidth) {
            left = viewportWidth - popupWidth - margin;
        }
        
        // Vertical positioning logic
        // If popup would go off top of screen, position below element
        if (top < margin) {
            top = rect.bottom + margin;
        }
        
        // If popup would go off bottom of screen, move it up
        if (top + popupHeight + margin > viewportHeight) {
            top = viewportHeight - popupHeight - margin;
            
            // If still doesn't fit (popup is taller than viewport), position at top with margin
            if (top < margin) {
                top = margin;
            }
        }
        
        // Final positioning - ensure we don't go negative
        left = Math.max(margin, left);
        top = Math.max(margin, top);
        
        // Ensure we don't exceed viewport bounds
        left = Math.min(left, viewportWidth - popupWidth - margin);
        top = Math.min(top, viewportHeight - popupHeight - margin);
        
        // Set final position using custom properties and classes
        popup.style.setProperty('--popup-left', left + 'px');
        popup.style.setProperty('--popup-top', top + 'px');
        
        // Clear any conflicting inline styles that might override CSS classes
        popup.style.removeProperty('display');
        popup.style.removeProperty('visibility');
        popup.style.removeProperty('opacity');
        popup.style.removeProperty('pointer-events');
        popup.style.removeProperty('transform');
        
        popup.classList.remove('measuring');
        popup.classList.add('positioned', 'visible', 'show');
        
        console.log('Popup positioned at:', { left, top }, 'Element rect:', rect, 'Popup size:', { width: popupWidth, height: popupHeight });
    }

    // Register global functions for backward compatibility
    registerGlobalFunctions() {
        // Main popup functions
        window.showGlobalPopup = (element, item) => this.show(element, item);
        window.hideGlobalPopup = (delay = 250) => this.hide(delay);
        window.hideGlobalPopupImmediate = () => this.hideImmediate();
        
        // Additional utility functions
        window.clearPopupTimeout = () => this.clearHideTimeout();
        
        // Legacy compatibility aliases
        window.showPopup = window.showGlobalPopup;
        window.hidePopup = window.hideGlobalPopup;
        window.hidePopupImmediate = window.hideGlobalPopupImmediate;
        
        console.log('✅ Global popup functions registered');
    }

    // Get current popup state
    getState() {
        return {
            isVisible: popupState.isVisible,
            currentProduct: popupState.currentProduct,
            isInRoomModal: popupState.isInRoomModal,
            initialized: popupState.initialized
        };
    }
}

// Initialize unified popup system
const unifiedPopupSystem = new UnifiedPopupSystem();

// Initialize global variables for backward compatibility
window.globalPopupTimeout = null;
window.isShowingPopup = false;
window.popupOpen = false;
window.currentItem = null;

// Final system ready message
console.log('🎉 WhimsicalFrog Unified Popup System ready!');


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
        
        apiPost(`analytics_tracker.php?action=${action}`, data).catch(error => {
            console.warn('Analytics tracking failed:', error);
        });
    }
    
    sendDataSync(action, data) {
        // For exit tracking, use sendBeacon for reliability
        const formData = new FormData();
        formData.append('action', action);
        formData.append('data', JSON.stringify(data));
        
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/api/analytics_tracker.php', formData);
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

// --- Start of js/sales-checker.js --- 

// Global Sales Checker and Utility Functions
// This file provides centralized functionality for all pages

// Sales checking functions
async function checkItemSale(itemSku) {
    try {
        const response = await apiGet(`sales.php?action=get_active_sales&item_sku=${itemSku}`);
        const data = await response.json();
        
        if (data.success && data.sale) {
            return {
                isOnSale: true,
                discountPercentage: parseFloat(data.sale.discount_percentage),
                salePrice: null, // Will be calculated based on original price
                originalPrice: null // Will be set by calling function
            };
        }
        return { isOnSale: false };
    } catch (error) {
        console.log('Error checking sale for', itemSku, error);
        return { isOnSale: false };
    }
}

function calculateSalePrice(originalPrice, discountPercentage) {
    return originalPrice * (1 - discountPercentage / 100);
}

// Enhanced checkAndDisplaySalePrice function
async function checkAndDisplaySalePrice(item, priceElement, unitPriceElement = null, context = 'popup') {
    if (!item || !priceElement) return;
    
    try {
        const saleData = await checkItemSale(item.sku);
        
        if (saleData.isOnSale && saleData.discountPercentage) {
            const originalPrice = parseFloat(item.retailPrice || item.price);
            const validDiscountPercentage = parseFloat(saleData.discountPercentage);
            
            // Validate the discount percentage
            if (isNaN(validDiscountPercentage) || validDiscountPercentage <= 0) {
                console.error('Invalid discount percentage in sale data:', saleData.discountPercentage);
                // Fall back to regular price display
                const price = parseFloat(item.retailPrice || item.price);
                priceElement.textContent = `$${price.toFixed(2)}`;
                if (unitPriceElement) {
                    unitPriceElement.textContent = `$${price.toFixed(2)}`;
                }
                return;
            }
            
            const salePrice = calculateSalePrice(originalPrice, validDiscountPercentage);
            
            // Format sale price display
            const saleHTML = `
                <span class="u-text-decoration-line-through u-color-999 u-font-size-0-9em">$${originalPrice.toFixed(2)}</span>
                <span class="u-color-dc2626 u-font-weight-bold u-margin-left-5px">$${salePrice.toFixed(2)}</span>
                <span class="u-color-dc2626 u-font-size-0-8em u-margin-left-5px">(${Math.round(validDiscountPercentage)}% off)</span>
            `;
            
            priceElement.innerHTML = saleHTML;
            
            if (unitPriceElement) {
                unitPriceElement.innerHTML = saleHTML;
            }
            
            // Update item object with sale price for cart
            item.salePrice = salePrice;
            item.originalPrice = originalPrice;
            item.isOnSale = true;
            item.discountPercentage = validDiscountPercentage;
        } else {
            // No sale, display regular price
            const price = parseFloat(item.retailPrice || item.price);
            priceElement.textContent = `$${price.toFixed(2)}`;
            
            if (unitPriceElement) {
                unitPriceElement.textContent = `$${price.toFixed(2)}`;
            }
            
            item.isOnSale = false;
        }
    } catch (error) {
        console.log('No sale data available for', item.sku);
        // Display regular price on error
        const price = parseFloat(item.retailPrice || item.price);
        priceElement.textContent = `$${price.toFixed(2)}`;
        
        if (unitPriceElement) {
            unitPriceElement.textContent = `$${price.toFixed(2)}`;
        }
    }
}

// SIMPLIFIED HOVER SYSTEM - Standard and Reliable
let hoverTimeout = null;
let hideTimeout = null;

// Popup functions now use the global system
// showPopup function moved to js/global-popup.js for centralization

function hidePopup() {
    if (typeof window.hideGlobalPopup === 'function') {
        window.hideGlobalPopup();
    }
}

// Keep popup visible when hovering over it
function keepPopupVisible() {
    if (hideTimeout) clearTimeout(hideTimeout);
}

// Update popup content
function updatePopupContent(popup, item) {
    const popupImage = popup.querySelector('.popup-image');
    const popupName = popup.querySelector('.popup-name');
    const popupPrice = popup.querySelector('.popup-price');
    const popupDescription = popup.querySelector('.popup-description');
    
    if (popupImage) {
        // Use actual image data from item object with fallbacks
        let imageSrc = 'images/items/placeholder.webp';
        
        // Try to get the best available image
        if (item.primaryImageUrl) {
            imageSrc = item.primaryImageUrl;
        }
        // Fallback to standard image property
        else if (item.image) {
            imageSrc = item.image;
        }
        else if (item.imageUrl) {
            imageSrc = item.imageUrl;
        }
        // Generate SKU-based path
        else if (item.sku) {
            // Try WebP first, then PNG
            imageSrc = `images/items/${item.sku}A.webp`;
        }
        
        popupImage.src = imageSrc;
        
        // Use centralized image error handling
        if (typeof window.setupImageErrorHandling === 'function') {
            window.setupImageErrorHandling(popupImage, item.sku);
        } else {
            // Fallback if central functions not loaded yet
            popupImage.onerror = function() {
                this.src = 'images/items/placeholder.webp';
                this.onerror = null;
            };
        }
    }
    
    // Update item name
    if (popupName) {
        popupName.textContent = item.name || item.itemName || 'Item';
    }
    
    // Update price with sale checking
    checkAndDisplaySalePrice(item, popupPrice);
    
    // Update description
    if (popupDescription) {
        popupDescription.textContent = item.description || '';
    }
    
    // Update "View Details" button to open item modal
    const viewDetailsBtn = popup.querySelector('.btn-secondary');
    if (viewDetailsBtn) {
        viewDetailsBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            hideGlobalPopup();
            
            // Open item modal instead of item details
            window.showGlobalItemModal(item.sku);
        };
    }
    
    // Update "Add to Cart" button for quick add
    const addToCartBtn = popup.querySelector('.btn-primary');
    if (addToCartBtn) {
        const sku = item.sku;
        const name = item.name || item.itemName;
        const price = parseFloat(item.retailPrice || item.price || 0);
        const image = item.primaryImageUrl || item.image || item.imageUrl || `images/items/${item.sku}A.png`;
        
        addToCartBtn.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Hide popup
            hideGlobalPopup();
            
            // Add to cart with quantity 1
            if (window.cart && typeof window.cart.addItem === 'function') {
                window.cart.addItem({
                    sku: sku,
                    name: name,
                    price: price,
                    image: image
                }, 1);
            }
        };
    }
    
    // Update "View Details" link to show item details
    const viewDetailsLink = popup.querySelector('a[href*="javascript:"]');
    if (viewDetailsLink) {
        viewDetailsLink.onclick = function(e) {
            e.preventDefault();
            // Hide popup and show item details
            hideGlobalPopup();
            
            if (typeof window.showItemDetails === 'function') {
                window.showItemDetails(item.sku);
            } else {
                console.log('Item details function not available');
            }
        };
    }
}

// Improved popup positioning
function positionPopupSimple(element, popup) {
    const rect = element.getBoundingClientRect();
    
    // Show popup temporarily to get actual dimensions using CSS classes
    popup.classList.add('measuring');
    const popupRect = popup.getBoundingClientRect();
    const popupWidth = popupRect.width;
    const popupHeight = popupRect.height;
    popup.classList.remove('measuring');
    
    // Get viewport dimensions with safety margins
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const margin = 10; // Safety margin from edges
    
    // Calculate preferred position (to the right of element)
    let left = rect.right + margin;
    let top = rect.top - 50; // Offset up slightly to center on element
    
    // Horizontal positioning logic
    if (left + popupWidth + margin > viewportWidth) {
        // Try positioning to the left of element
        left = rect.left - popupWidth - margin;
        
        // If still doesn't fit, position at right edge of viewport
        if (left < margin) {
            left = viewportWidth - popupWidth - margin;
        }
    }
    
    // Ensure popup doesn't go off left edge
    if (left < margin) {
        left = margin;
    }
    
    // Vertical positioning logic
    // First, try to center the popup vertically on the element
    const elementCenter = rect.top + (rect.height / 2);
    top = elementCenter - (popupHeight / 2);
    
    // If popup would go off top of screen, move it down
    if (top < margin) {
        top = margin;
    }
    
    // If popup would go off bottom of screen, move it up
    if (top + popupHeight + margin > viewportHeight) {
        top = viewportHeight - popupHeight - margin;
        
        // If still doesn't fit (popup is taller than viewport), position at top with margin
        if (top < margin) {
            top = margin;
        }
    }
    
    // Apply final positioning using CSS custom properties
    popup.style.setProperty('-popup-left', left + 'px');
    popup.style.setProperty('-popup-top', top + 'px');
    popup.classList.add('positioned', 'visible');
    
    console.log(`Popup positioned at: left=${left}, top=${top}, width=${popupWidth}, height=${popupHeight}`);
}

// Function to add sale badges to item cards (for shop page)
function addSaleBadgeToCard(skuOrCard, discountPercentageOrCard) {
    let itemCard, discountPercentage;
    
    if (typeof skuOrCard === 'string') {
        // Called with (sku, itemCard) pattern
        const sku = skuOrCard;
        itemCard = discountPercentageOrCard;
        // Get discount from sale data
        checkItemSale(sku).then(saleData => {
            if (saleData && saleData.isOnSale && saleData.discountPercentage) {
                addSaleBadgeToCardWithDiscount(itemCard, saleData.discountPercentage);
            }
        });
        return;
    } else {
        // Called with (itemCard, discountPercentage) pattern
        itemCard = skuOrCard;
        discountPercentage = discountPercentageOrCard;
    }
    
    // Only proceed if we have valid data
    if (itemCard && discountPercentage) {
        addSaleBadgeToCardWithDiscount(itemCard, discountPercentage);
    }
}

function addSaleBadgeToCardWithDiscount(itemCard, discountPercentage) {
    if (!itemCard || !itemCard.querySelector) {
        console.error('Invalid item card element provided to addSaleBadgeToCard');
        return;
    }
    
    // Validate discount percentage
    const validDiscountPercentage = parseFloat(discountPercentage);
    if (isNaN(validDiscountPercentage) || validDiscountPercentage <= 0) {
        console.error('Invalid discount percentage provided to addSaleBadgeToCardWithDiscount:', discountPercentage);
        return;
    }
    
    // Remove existing sale badge if present
    const existingBadge = itemCard.querySelector('.sale-badge');
    if (existingBadge) {
        existingBadge.remove();
    }
    
    // Create new sale badge
    const saleBadge = document.createElement('div');
    saleBadge.className = 'sale-badge';
    saleBadge.innerHTML = `
        <span class="sale-text">SALE</span>
        <span class="sale-percentage">${Math.round(validDiscountPercentage)}% OFF</span>
    `;
    
    // No need to set styles - using CSS classes instead
    itemCard.appendChild(saleBadge);
}

// Shop page functionality
document.addEventListener('DOMContentLoaded', function() {
    // Only run shop-specific code on shop page
    if (window.location.search.includes('page=shop')) {
        // Check for sales on all items
        const itemCards = document.querySelectorAll('[data-sku]');
        
        itemCards.forEach(async (card) => {
            const sku = card.getAttribute('data-sku');
            if (sku) {
                const saleData = await checkItemSale(sku);
                if (saleData && saleData.isOnSale && saleData.discountPercentage) {
                    addSaleBadgeToCard(card, saleData.discountPercentage);
                }
            }
        });
    }
    
    // Room page functionality - Set up hover listeners
    if (window.location.search.includes('page=room')) {
        // Wait a bit for room elements to load, then set up hover
        setTimeout(setupRoomHover, 500);
    }
});


// Setup hover listeners for room pages
function setupRoomHover() {
    const popup = document.getElementById('productPopup');
    if (!popup) return;
    
    // Set up hover listeners on popup to keep it visible
    popup.addEventListener('mouseenter', keepPopupVisible);
    popup.addEventListener('mouseleave', hidePopup);
    
    console.log('Room hover system initialized with standard settings');
}

// Make functions globally available
window.checkItemSale = checkItemSale;
window.calculateSalePrice = calculateSalePrice;
window.checkAndDisplaySalePrice = checkAndDisplaySalePrice;
window.addSaleBadgeToCard = addSaleBadgeToCard;
// Note: showPopup and hidePopup are provided by global-popup.js
// window.showPopup and window.hidePopup are set up there 

// --- End of js/sales-checker.js --- 

// --- Start of js/search.js --- 

// Search functionality for WhimsicalFrog
class SearchModal {
    constructor() {
        this.modal = null;
        this.searchInput = null;
        this.isSearching = false;
        this.currentSearchTerm = '';
        this.currentResults = []; // Store current search results
        this.init();
    }

    init() {
        // Create the search modal HTML
        this.createModalHTML();
        
        // Get references to elements
        this.modal = document.getElementById('searchModal');
        this.searchInput = document.getElementById('headerSearchInput');
        
        // Bind event listeners
        this.bindEvents();
    }

    createModalHTML() {
        const modalHTML = `
            <!- Search Results Modal ->
            <div id="searchModal">
                <div class="search-modal-content">
                    <div class="search-modal-header">
                        <h2 class="search-modal-title">Search Results</h2>
                        <button class="search-modal-close" onclick="searchModal.close()">&times;</button>
                    </div>
                    <div class="search-modal-body" id="searchModalBody">
                        <!- Search results will be populated here ->
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to the document body
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    bindEvents() {
        if (this.searchInput) {
            // Handle Enter key press
            this.searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    const searchTerm = this.searchInput.value.trim();
                    if (searchTerm) {
                        this.performSearch(searchTerm);
                    }
                }
            });

            // Handle search icon click (if exists)
            const searchIcon = this.searchInput.parentElement.querySelector('svg');
            if (searchIcon) {
                searchIcon.addEventListener('click', () => {
                    const searchTerm = this.searchInput.value.trim();
                    if (searchTerm) {
                        this.performSearch(searchTerm);
                    }
                });
            }
        }

        // Close modal when clicking outside
        if (this.modal) {
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.close();
                }
            });
        }

        // Handle Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen()) {
                this.close();
            }
        });
    }

    async performSearch(searchTerm) {
        if (this.isSearching) return;
        
        this.isSearching = true;
        this.currentSearchTerm = searchTerm;
        
        // Show modal with loading state
        this.showLoading();
        this.open();

        try {
            const response = await apiGet(`search_items.php?q=${encodeURIComponent(searchTerm)}`);
            const data = await response.json();

            if (data.success) {
                this.displayResults(data);
            } else {
                this.displayError(data.message || 'Search failed');
            }
        } catch (error) {
            console.error('Search error:', error);
            this.displayError('An error occurred while searching. Please try again.');
        } finally {
            this.isSearching = false;
        }
    }

    showLoading() {
        const modalBody = document.getElementById('searchModalBody');
        modalBody.innerHTML = `
            <div class="search-loading">
                <div class="spinner"></div>
                <p>Searching for "${this.currentSearchTerm}"...</p>
            </div>
        `;
    }

    displayResults(data) {
        // Store results for later use
        this.currentResults = data.results || [];
        
        const modalBody = document.getElementById('searchModalBody');
        
        if (data.results.length === 0) {
            modalBody.innerHTML = `
                <div class="search-no-results">
                    <div class="search-no-results-icon">🔍</div>
                    <h3 class="search-no-results-title">No results found</h3>
                    <p class="search-no-results-text">
                        We couldn't find any items matching "<strong>${data.search_term}</strong>".
                        <br>Try different keywords or browse our categories.
                    </p>
                </div>
            `;
            return;
        }

        let resultsHTML = `
            <div class="search-results-header">
                <p class="search-results-count">
                    Found <strong>${data.count}</strong> result${data.count !== 1 ? 's' : ''} for 
                    "<span class="search-results-term">${data.search_term}</span>"
                </p>
            </div>
            <div class="search-results-grid">
        `;

        data.results.forEach(item => {
            const stockClass = item.in_stock ? 'in-stock' : 'out-of-stock';
            const isOutOfStock = !item.in_stock;
            const addToCartButton = isOutOfStock 
                ? `<button class="search-add-to-cart-btn disabled" disabled>Out of Stock</button>`
                : `<button class="search-add-to-cart-btn" onclick="searchModal.addToCart('${item.sku}', event)">Add to Cart</button>`;
            
            resultsHTML += `
                <div class="search-result-item">
                    <div class="search-result-clickable" onclick="searchModal.viewItemDetails('${item.sku}')">
                        <img src="${item.image_url}" alt="${item.name}" class="search-result-image" 
                             onerror="this.src='/images/items/placeholder.webp'">
                        <div class="search-result-content">
                            <h3 class="search-result-name">${item.name}</h3>
                            <span class="search-result-category">${item.category}</span>
                            <p class="search-result-description">${item.description || 'No description available'}</p>
                            <div class="search-result-footer">
                                <span class="search-result-price">${item.formatted_price}</span>
                                <span class="search-result-stock ${stockClass}">${item.stock_status}</span>
                            </div>
                        </div>
                    </div>
                    <div class="search-result-actions">
                        ${addToCartButton}
                        <button class="search-view-details-btn" onclick="searchModal.viewItemDetails('${item.sku}')">View Details</button>
                    </div>
                </div>
            `;
        });

        resultsHTML += `</div>`;
        modalBody.innerHTML = resultsHTML;
    }

    displayError(message) {
        const modalBody = document.getElementById('searchModalBody');
        modalBody.innerHTML = `
            <div class="search-error">
                <div class="search-error-icon">⚠️</div>
                <h3 class="search-error-title">Search Error</h3>
                <p class="search-error-text">${message}</p>
            </div>
        `;
    }

    selectItem(sku) {
        // Close the search modal
        this.close();
        
        // Clear the search input
        if (this.searchInput) {
            this.searchInput.value = '';
        }
        
        // Open the quantity modal for the selected item
        if (typeof openQuantityModal === 'function') {
            // Find the item data from the current search results
            const itemElement = document.querySelector(`[onclick="searchModal.selectItem('${sku}')"]`);
            if (itemElement) {
                const itemData = this.extractItemDataFromElement(itemElement, sku);
                openQuantityModal(itemData);
            }
        } else {
            // Fallback: redirect to shop page with the item highlighted
            window.location.href = `/?page=shop&highlight=${sku}`;
        }
    }

    extractItemDataFromElement(element, sku) {
        const name = element.querySelector('.search-result-name').textContent;
        const price = element.querySelector('.search-result-price').textContent.replace('$', '');
        const image = element.querySelector('.search-result-image').src;
        const inStock = element.querySelector('.search-result-stock').classList.contains('in-stock');
        
        return {
            sku: sku,
            name: name,
            price: parseFloat(price),
            image: image,
            inStock: inStock
        };
    }

    open() {
        if (this.modal) {
            this.modal.classList.add('show');
            document.body.classList.add('modal-open');
        }
    }

    close() {
        if (this.modal) {
            this.modal.classList.remove('show');
            document.body.classList.remove('modal-open');
        }
    }

    isOpen() {
        return this.modal && this.modal.classList.contains('show');
    }

    addToCart(sku, event) {
        // Prevent event bubbling to avoid triggering the view details
        event.stopPropagation();
        
        // Find the item data from the search results
        const itemElement = event.target.closest('.search-result-item');
        if (!itemElement) return;
        
        const itemData = this.extractItemDataFromSearchResult(itemElement, sku);
        
        // Add to cart with quantity 1
        if (typeof window.cart !== 'undefined' && window.cart.addItem) {
            window.cart.addItem(itemData.sku, itemData.name, itemData.price, itemData.image, 1);
            
            // Show success message
            this.showAddToCartSuccess(itemData.name);
        } else {
            console.error('Cart system not available');
        }
    }

    viewItemDetails(sku) {
        // Find the item data from stored results
        const itemData = this.currentResults.find(item => item.sku === sku);
        if (!itemData) {
            console.error('Item not found in search results:', sku);
            return;
        }
        
        // Close the search modal
        this.close();
        
        // Clear the search input
        if (this.searchInput) {
            this.searchInput.value = '';
        }
        
        // Check what page we're on and use the appropriate function
        const currentPage = new URLSearchParams(window.location.search).get('page');
        
        if (currentPage === 'shop' && typeof showItemDetails === 'function') {
            // On shop page, use showItemDetails
            showItemDetails(sku);
        } else {
            // On other pages, use the global modal
            showGlobalItemModal(sku, {
                sku: sku,
                name: itemData.name,
                itemName: itemData.name,
                itemId: itemData.sku
            });
        }
    }

    extractItemDataFromSearchResult(element, sku) {
        const name = element.querySelector('.search-result-name').textContent;
        const price = element.querySelector('.search-result-price').textContent.replace('$', '');
        const image = element.querySelector('.search-result-image').src;
        const inStock = element.querySelector('.search-result-stock').classList.contains('in-stock');
        
        return {
            sku: sku,
            name: name,
            price: parseFloat(price),
            image: image,
            inStock: inStock
        };
    }

    showAddToCartSuccess(itemName) {
        // Create a temporary success message
        const successMessage = document.createElement('div');
        successMessage.className = 'search-add-to-cart-success';
        successMessage.innerHTML = `
            <div class="success-icon">✓</div>
            <div class="success-text">Added "${itemName}" to cart!</div>
        `;
        
        // Add to the modal body
        const modalBody = document.getElementById('searchModalBody');
        modalBody.appendChild(successMessage);
        
        // Remove after 3 seconds
        setTimeout(() => {
            if (successMessage.parentNode) {
                successMessage.parentNode.removeChild(successMessage);
            }
        }, 3000);
    }
}

// Initialize search modal when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize search modal if search input exists (not on admin pages)
    if (document.getElementById('headerSearchInput')) {
        window.searchModal = new SearchModal();
        console.log('Search modal initialized');
    }
});

// Make searchModal globally available
window.SearchModal = SearchModal;


// --- End of js/search.js --- 

// --- Start of js/room-css-manager.js --- 

/**
 * WhimsicalFrog Room CSS and Styling Management
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

console.log('Loading room-css-manager.js...');

// Global CSS management for room pages
window.RoomCSS = window.RoomCSS || {};
// loadGlobalCSS function moved to css-initializer.js for centralization

console.log('room-css-manager.js loaded successfully');


// --- End of js/room-css-manager.js --- 

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
    const url = endpoint.startsWith('/') ? endpoint : `/api/${endpoint}`;
    const res = await fetch(url, { credentials: 'same-origin' });
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
        const data = await apiGet(`get_room_coordinates.php?room_type=${window.ROOM_TYPE}`);
        
        
        
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

// --- Start of js/room-event-manager.js --- 

/**
 * WhimsicalFrog Room Event Handling and Setup
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

console.log('Loading room-event-manager.js...');

// Room event management system
window.RoomEvents = window.RoomEvents || {};

    
    // Function to setup popup events after positioning
    function setupPopupEventsAfterPositioning() { // Get all product icons
        // Accept both legacy .item-icon and new .room-product-icon
    const productIcons = document.querySelectorAll('.item-icon, .room-product-icon');productIcons.forEach((icon, index) => {
            // Make sure the element is interactive
            icon.classList.add('clickable-icon');
            
            // Get the product data from the inline event attribute
            const onMouseEnterAttr = icon.getAttribute('onmouseenter');
            if (onMouseEnterAttr) {
                // Extract the product data from the onmouseenter attribute
                const match = onMouseEnterAttr.match(/showGlobalPopup\(this,\s*(.+)\)/);
                if (match) {
                    try {
                        // Decode HTML entities and parse JSON
                        const jsonString = match[1].replace(/&quot;/g, '"').replace(/&#039;/g, "'");
                        const productData = JSON.parse(jsonString);// Remove existing event listeners by cloning the element
                        const newIcon = icon.cloneNode(true);
                        icon.parentNode.replaceChild(newIcon, icon);
                        
                        // Add fresh event listeners
                        newIcon.addEventListener('mouseenter', function(e) {
                            try {if (typeof (window.showGlobalPopup || (parent && parent.showGlobalPopup)) === 'function') {(window.showGlobalPopup || (parent && parent.showGlobalPopup))(this, productData);
                                } else {
                                    console.error('showGlobalPopup function not available. Type:', typeof (window.showGlobalPopup || (parent && parent.showGlobalPopup)));
                                }
                            } catch (error) {
                                console.error('Error in mouseenter event:', error);
                            }
                        });
                        
                        newIcon.addEventListener('mouseleave', function(e) {
                            try {if (typeof (window.hideGlobalPopup || (parent && parent.hideGlobalPopup)) === 'function') {(window.hideGlobalPopup || (parent && parent.hideGlobalPopup))();
                                } else {
                                    console.error('hideGlobalPopup function not available');
                                }
                            } catch (error) {
                                console.error('Error in mouseleave event:', error);
                            }
                        });
                        
                        newIcon.addEventListener('click', function(e) {if (typeof (window.showItemDetailsModal || (parent && parent.showItemDetailsModal)) === 'function') {(window.showItemDetailsModal || (parent && parent.showItemDetailsModal))(productData.sku);
                            } else {
                                console.error('showItemDetailsModal function not available on click');
                            }
                        });
                        
                    } catch (error) {
                        console.error(`Error parsing product data for icon ${index}:`, error);
                    }
                }
            }
        });
    }

// Expose globally for other modules
window.setupPopupEventsAfterPositioning = setupPopupEventsAfterPositioning;

// -------------------- NEW CENTRALIZED DELEGATED LISTENERS --------------------
// Ensure events still fire even if per-icon listeners were not attached (fallback)
function attachDelegatedItemEvents(){
    if (document.body.hasAttribute('data-wf-room-delegated-listeners')) return;
    document.body.setAttribute('data-wf-room-delegated-listeners','true');

    // Utility to parse product data from inline attribute or dataset
    function extractProductData(icon){
        // 1) Try full JSON from data-product
        if (icon.dataset.product){
            try{
                return JSON.parse(icon.dataset.product);
            }catch(e){
                console.warn('[extractProductData] Invalid JSON in data-product:', e);
            }
        }

        // 2) Try to assemble from individual data-* attributes (preferred over legacy inline attr)
        if (icon.dataset.sku){
            return {
                sku: icon.dataset.sku,
                name: icon.dataset.name || '',
                price: parseFloat(icon.dataset.price || icon.dataset.cost || '0'),
                description: icon.dataset.description || '',
                stock: parseInt(icon.dataset.stock || '0',10),
                category: icon.dataset.category || ''
            };
        }

        // 3) Fallback – parse legacy onmouseenter inline attribute
        const attr = icon.getAttribute('onmouseenter');
        if (attr){
            const match = attr.match(/showGlobalPopup\(this,\s*(.+)\)/);
            if (match){
                try{
                    const jsonString = match[1]
                        .replace(/&quot;/g, '"')
                        .replace(/&#039;/g, "'");
                    return JSON.parse(jsonString);
                }catch(e){
                    console.warn('[extractProductData] Failed to parse inline JSON:', e);
                }
            }
        }

        // If we reach here, no valid product data
        return null;
    }

    // Hover events
    document.addEventListener('mouseover', function(e){
        const icon = e.target.closest('.item-icon, .room-product-icon');
        if(!icon) return;
        const productData = extractProductData(icon);
        const popupFn = window.showGlobalPopup || (parent && parent.showGlobalPopup);
        if (typeof popupFn === 'function' && productData){
            popupFn(icon, productData);
        }
    });
    document.addEventListener('mouseout', function(e){
        const icon = e.target.closest('.item-icon, .room-product-icon');
        if(!icon) return;
        const hideFn = window.hideGlobalPopup || (parent && parent.hideGlobalPopup);
        console.log('[DelegatedHover] mouseout from', icon);
        if (typeof hideFn === 'function') hideFn();
    });

    // Click events
    document.addEventListener('click', function(e){
        const icon = e.target.closest('.item-icon, .room-product-icon');
        if(!icon) return;
        e.preventDefault();
        const productData = extractProductData(icon);
        const detailsFn = (parent && parent.showGlobalItemModal) || window.showGlobalItemModal || window.showItemDetailsModal || window.showItemDetails || (parent && (parent.showItemDetailsModal || parent.showItemDetails));
        if (typeof detailsFn === 'function' && productData){
            detailsFn(productData.sku, productData);
        }
    });
}

// Immediately attach in current document
attachDelegatedItemEvents();

// Expose for iframes
window.attachDelegatedItemEvents = attachDelegatedItemEvents;

console.log('room-event-manager.js loaded successfully');


// --- End of js/room-event-manager.js --- 

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
    window.roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
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
        modal.style.zIndex = 3000;

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
                iframe.srcdoc = cachedData.content;
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
            const roomData = await apiGet('get_room_data.php');

            if (roomData.success && Array.isArray(roomData.data.productRooms)) {
                const validRooms = roomData.data.productRooms.filter(r => {
                    const num = parseInt(r && r.room_number, 10);
                    return Number.isFinite(num) && num > 0;
                });
                const preloadPromises = validRooms.map(room => this.preloadSingleRoom(room.room_number));
                await Promise.all(preloadPromises);
                console.log('🚪 All rooms preloaded successfully.');
            } else {
                console.error('🚪 Failed to get room data or invalid format:', roomData.message);
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
            const data = await apiGet(`load_room_content.php?room_number=${roomNumber}&modal=1`);

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

/**
 * WhimsicalFrog Cart System Module
 * Unified cart management with notifications
 */

(function() {
    'use strict';

    // Cart system state
    const cartState = {
        items: [],
        total: 0,
        count: 0,
        initialized: false,
        notifications: true
    };

    // Cart system methods
    const cartMethods = {
        // Load cart from localStorage
        loadCart() {
            try {
                const saved = localStorage.getItem('whimsical_frog_cart');
                if (saved) {
                    const data = JSON.parse(saved);
                    cartState.items = data.items || [];
                    this.recalculateTotal();
                    console.log(`[Cart] Loaded ${cartState.items.length} items`);
                }
            } catch (error) {
                console.error(`[Cart] Error loading cart: ${error.message}`);
                cartState.items = [];
            }
        },

        // Save cart to localStorage
        saveCart() {
            try {
                const data = {
                    items: cartState.items,
                    total: cartState.total,
                    count: cartState.count,
                    timestamp: Date.now()
                };
                localStorage.setItem('whimsical_frog_cart', JSON.stringify(data));
                console.log('[Cart] Saved to localStorage');
            } catch (error) {
                console.error(`[Cart] Error saving cart: ${error.message}`);
            }
        },

        // Add item to cart
        addItem(item) {
            const existingIndex = cartState.items.findIndex(i => i.sku === item.sku);
            const addedQuantity = item.quantity || 1;
            let finalQuantity = addedQuantity;
            let isNewItem = false;
            
            if (existingIndex !== -1) {
                // Update existing item
                cartState.items[existingIndex].quantity += addedQuantity;
                finalQuantity = cartState.items[existingIndex].quantity;
            } else {
                // Add new item
                isNewItem = true;
                cartState.items.push({
                    sku: item.sku,
                    name: item.name,
                    price: item.price,
                    quantity: addedQuantity,
                    image: item.image || `images/items/${item.sku}A.webp`,
                    gender: item.gender,
                    color: item.color,
                    size: item.size
                });
            }
            
            this.recalculateTotal();
            this.updateCartDisplay();
            this.saveCart();
            
            // Show dual notification system (item added + cart status)
            if (cartState.notifications) {
                this.showAddToCartNotifications(item, addedQuantity, finalQuantity, isNewItem);
            }
            
            console.log(`[Cart] Added item: ${item.name} (${item.sku})`);
        },

        // Show dual cart notifications (matches June 30th config)
        showAddToCartNotifications(item, addedQuantity, totalQuantity, isNewItem) {
            try {
                // Build comprehensive notification message for what was added
                let displayName = item.name;
                const detailParts = [];
                
                if (item.gender) detailParts.push(item.gender);
                if (item.color) detailParts.push(item.color);
                if (item.size) detailParts.push(item.size);
                
                if (detailParts.length > 0) {
                    displayName += ` (${detailParts.join(', ')})`;
                }
                
                // Format price and create comprehensive message
                const formattedPrice = '$' + (parseFloat(item.price) || 0).toFixed(2);
                const quantityText = addedQuantity > 1 ? ` (${addedQuantity})` : '';
                
                // Create the main "item added" notification message
                const itemNotificationMessage = `🛒 ${displayName}${quantityText} - ${formattedPrice}`;
                
                // Show the main notification with title
                if (window.wfNotifications && typeof window.wfNotifications.success === 'function') {
                    window.wfNotifications.success(itemNotificationMessage, {
                        title: '✅ Added to Cart',
                        duration: 5000
                    });
                } else if (window.showSuccess) {
                    window.showSuccess(itemNotificationMessage, {
                        title: '✅ Added to Cart',
                        duration: 5000
                    });
                } else {
                    console.log('[Cart] No notification system available, using alert');
                    alert(itemNotificationMessage);
                }
                
                // Show cart status toast after a brief delay (1.5 seconds)
                setTimeout(() => {
                    this.showCartStatusToast();
                }, 1500);
                
            } catch (error) {
                console.error('[Cart] Error showing notifications:', error);
                // Fallback to simple notification
                const fallbackMessage = `${item.name} added to cart!`;
                if (window.alert) {
                    alert(fallbackMessage);
                } else {
                    console.log(`[Cart] ${fallbackMessage}`);
                }
            }
        },

        // Show cart status notification (total items and price)
        showCartStatusToast() {
            const itemCount = cartState.count;
            const total = cartState.total;
            const formattedTotal = '$' + total.toFixed(2);
            
            let statusMessage;
            if (itemCount === 0) {
                statusMessage = 'Cart is empty';
            } else if (itemCount === 1) {
                statusMessage = `🛒 1 item • ${formattedTotal}`;
            } else {
                statusMessage = `🛒 ${itemCount} items • ${formattedTotal}`;
            }
            
            // Show cart status toast with branded notification system
            try {
                if (window.wfNotifications && typeof window.wfNotifications.success === 'function') {
                    window.wfNotifications.success(statusMessage, {
                        title: 'Cart Status',
                        duration: 5000 // Show for 5 seconds
                    });
                } else if (window.showSuccess) {
                    window.showSuccess(statusMessage, {
                        title: 'Cart Status',
                        duration: 5000
                    });
                } else {
                    console.log('[Cart] Cart status:', statusMessage);
                }
            } catch (error) {
                console.error('[Cart] Error showing cart status:', error);
            }
        },

        // Remove item from cart
        removeItem(sku) {
            const index = cartState.items.findIndex(item => item.sku === sku);
            if (index !== -1) {
                const item = cartState.items[index];
                cartState.items.splice(index, 1);
                
                this.recalculateTotal();
                this.updateCartDisplay();
                this.saveCart();
                
                // Show notification
                if (cartState.notifications && window.showInfo) {
                    window.showInfo(`${item.name} removed from cart`);
                }
                
                console.log(`[Cart] Removed item: ${item.name} (${sku})`);
            }
        },

        // Update item quantity
        updateItem(sku, quantity) {
            const index = cartState.items.findIndex(item => item.sku === sku);
            if (index !== -1) {
                if (quantity <= 0) {
                    this.removeItem(sku);
                } else {
                    cartState.items[index].quantity = quantity;
                    
                    this.recalculateTotal();
                    this.updateCartDisplay();
                    this.saveCart();
                    
                    console.log(`[Cart] Updated item: ${sku} quantity to ${quantity}`);
                }
            }
        },

        // Clear cart
        clearCart() {
            cartState.items = [];
            cartState.total = 0;
            cartState.count = 0;
            
            this.updateCartDisplay();
            this.saveCart();
            
            // Show notification
            if (cartState.notifications && window.showInfo) {
                window.showInfo('Cart cleared');
            }
            
            console.log('[Cart] Cart cleared');
        },

        // Recalculate total
        recalculateTotal() {
            cartState.total = cartState.items.reduce((sum, item) => {
                return sum + (item.price * item.quantity);
            }, 0);
            
            cartState.count = cartState.items.reduce((sum, item) => {
                return sum + item.quantity;
            }, 0);
        },

        // Update cart display in UI
        updateCartDisplay() {
            console.log('[Cart] Updating cart display...', { count: cartState.count, total: cartState.total });
            
            // Update cart count (main navigation uses #cartCount)
            const cartCountElement = document.querySelector('#cartCount');
            if (cartCountElement) {
                const countText = cartState.count === 1 ? '1 item' : `${cartState.count} items`;
                cartCountElement.textContent = countText;
                console.log('[Cart] Updated #cartCount:', countText);
            } else {
                console.log('[Cart] #cartCount element not found');
            }

            // Update cart total (main navigation uses #cartTotal)
            const cartTotalElement = document.querySelector('#cartTotal');
            if (cartTotalElement) {
                const totalText = `$${cartState.total.toFixed(2)}`;
                cartTotalElement.textContent = totalText;
                cartTotalElement.classList.toggle('cart-hidden', cartState.count === 0);
                console.log('[Cart] Updated #cartTotal:', totalText);
            } else {
                console.log('[Cart] #cartTotal element not found');
            }
            
            // Also update legacy selectors for compatibility
            const cartBadge = document.querySelector('.cart-badge');
            if (cartBadge) {
                cartBadge.textContent = cartState.count;
                cartBadge.classList.toggle('cart-hidden', cartState.count === 0);
            }

            const cartCounter = document.querySelector('.cart-counter');
            if (cartCounter) {
                cartCounter.textContent = cartState.count;
            }

            const cartTotalLegacy = document.querySelector('.cart-total');
            if (cartTotalLegacy) {
                cartTotalLegacy.textContent = `$${cartState.total.toFixed(2)}`;
            }
            
            console.log('[Cart] Cart display update complete');
        },

        // Get cart items
        getItems() {
            return [...cartState.items];
        },

        // Get cart total
        getTotal() {
            return cartState.total;
        },

        // Get cart count
        getCount() {
            return cartState.count;
        },

        // Get cart state
        getState() {
            return cartState;
        },

        // Set notifications enabled/disabled
        setNotifications(enabled) {
            cartState.notifications = enabled;
            console.log(`[Cart] Notifications ${enabled ? 'enabled' : 'disabled'}`);
        },

        // Show current cart status manually (can be called anytime)
        showCurrentCartStatus() {
            const itemCount = cartState.count;
            const total = cartState.total;
            const formattedTotal = '$' + total.toFixed(2);
            
            let statusMessage;
            let statusTitle;
            
            if (itemCount === 0) {
                statusMessage = 'Your cart is empty';
                statusTitle = 'Cart Status';
            } else if (itemCount === 1) {
                statusMessage = `🛒 1 item • ${formattedTotal}`;
                statusTitle = 'Cart Status'; 
            } else {
                statusMessage = `🛒 ${itemCount} items • ${formattedTotal}`;
                statusTitle = 'Cart Status';
            }
            
            try {
                if (window.wfNotifications && typeof window.wfNotifications.info === 'function') {
                    window.wfNotifications.info(statusMessage, {
                        title: statusTitle,
                        duration: 5000 // Show for 5 seconds when manually called
                    });
                } else if (window.showInfo) {
                    window.showInfo(statusMessage, {
                        title: statusTitle,
                        duration: 5000
                    });
                } else {
                    console.log('[Cart] Cart status:', statusMessage);
                }
            } catch (error) {
                console.error('[Cart] Error showing cart status:', error);
            }
        },

        // Render cart items for the cart page
        async renderCart() {
            let cartContainer = document.getElementById('cartContainer');
            if (!cartContainer) {
                cartContainer = document.getElementById('cartItems');
            }
            
            if (!cartContainer) {
                console.warn('[Cart] Cart container not found');
                return;
            }
            
            console.log('[Cart] Rendering cart with', cartState.items.length, 'items');
            
            // Update cart from localStorage
            this.loadCart();
            
            if (cartState.items.length === 0) {
                cartContainer.innerHTML = '<div class="text-center py-8 text-gray-500">Your cart is empty</div>';
                return;
            }

            // Build cart HTML
            const cartHTML = cartState.items.map(item => {
                const imageUrl = item.image || `/images/items/${item.sku}A.webp`;
                const displayName = item.name || item.sku;
                const unitPrice = parseFloat(item.price) || 0;
                const quantity = parseInt(item.quantity) || 1;
                const lineTotal = unitPrice * quantity;
                
                return `
                <div class="flex items-center justify-between p-4 border-b border-gray-200">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 flex items-center justify-center">
                            <img src="${imageUrl}" alt="${displayName}" class="w-full h-full object-contain" 
                                 onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\\'u-cart-fallback\\'><div class=\\'u-cart-fallback-icon\\'>📷</div><div>No Image</div></div>';">
                        </div>
                        <div>
                            <h3 class="font-medium text-gray-900">${displayName}</h3>
                            <p class="text-xs text-gray-400 font-mono">${item.sku}</p>
                            <p class="text-sm text-gray-500">$${unitPrice.toFixed(2)}</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="window.cart.updateItem('${item.sku}', ${quantity - 1})" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded" ${quantity <= 1 ? 'disabled' : ''}>-</button>
                        <span class="px-3 py-1 bg-gray-100 rounded">${quantity}</span>
                        <button onclick="window.cart.updateItem('${item.sku}', ${quantity + 1})" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-2 py-1 rounded">+</button>
                        <button onclick="window.cart.removeItem('${item.sku}')" class="px-2 py-1 rounded ml-2 bg-red-600 text-white hover:bg-red-700">Remove</button>
                    </div>
                </div>
                `;
            }).join('');

            const total = this.getTotal();
            const cartContentHTML = `
                <div class="flex-1 overflow-y-auto cart-scrollbar">
                    ${cartHTML}
                </div>
                <div class="flex-shrink-0 border-t border-gray-200 bg-gray-50 p-4">
                    <div class="flex justify-between items-center mb-4">
                        <span class="text-lg font-semibold">Total: $${total.toFixed(2)}</span>
                        <button onclick="window.cart.clearCart(); window.location.reload();" class="px-4 py-2 rounded bg-gray-600 text-white hover:bg-gray-700">Clear Cart</button>
                    </div>
                    <button onclick="window.cart.checkout()" class="brand-button w-full py-3 px-6 rounded-lg font-semibold">Proceed to Checkout</button>
                </div>
            `;
            
            cartContainer.innerHTML = cartContentHTML;
        },

        async checkout() {
            // Check if user is logged in
            const userRaw = sessionStorage.getItem('user');
            let user = null;
            if (userRaw) {
                try { user = JSON.parse(userRaw); } catch(e) { console.warn('Invalid user JSON in sessionStorage'); }
            }

            if (!user) {
                // Set flag for pending checkout
                localStorage.setItem('pendingCheckout', 'true');
                
                // Store cart redirect intent in PHP session via AJAX
                try {
                    await apiPost('set_redirect.php', { redirectUrl: '/?page=cart' });
                } catch (e) {
                    console.warn('Could not set server-side redirect');
                }
                
                // Redirect to login page
                window.location.href = '/?page=login';
                return;
            }

            // Create payment method modal
            this.createPaymentMethodModal();
        },

        createPaymentMethodModal() {
            // Remove existing modal if any
            const existingModal = document.getElementById('paymentMethodModal');
            if (existingModal) {
                existingModal.remove();
            }

            const modal = document.createElement('div');
            modal.id = 'paymentMethodModal';
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            
            const paymentMethods = [
                { value: 'credit_card', label: 'Credit Card', icon: '💳' },
                { value: 'paypal', label: 'PayPal', icon: '🅿️' },
                { value: 'check', label: 'Check', icon: '🏦' },
                { value: 'cash', label: 'Cash', icon: '💵' },
                { value: 'venmo', label: 'Venmo', icon: '💸' }
            ];

            const shippingMethods = [
                { value: 'pickup', label: 'Customer Pickup', icon: '🏪' },
                { value: 'local_delivery', label: 'Local Delivery', icon: '🚚' },
                { value: 'usps', label: 'USPS', icon: '📫' },
                { value: 'fedex', label: 'FedEx', icon: '📦' },
                { value: 'ups', label: 'UPS', icon: '🚛' }
            ];

            // Make toggleShippingInfo globally available
            window.toggleShippingInfo = () => {
                const shippingMethod = document.querySelector('input[name="shippingMethod"]:checked')?.value;
                const shippingInfo = document.getElementById('shippingInfo');
                if (shippingMethod && shippingMethod !== 'pickup') {
                    shippingInfo.style.display = 'block';
                } else {
                    shippingInfo.style.display = 'none';
                }
            };

            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <h2 class="text-xl font-bold mb-4">Checkout</h2>
                    
                    <div class="mb-4">
                        <h3 class="font-semibold mb-2">Payment Method</h3>
                        ${paymentMethods.map(method => `
                            <label class="flex items-center mb-2 cursor-pointer">
                                <input type="radio" name="paymentMethod" value="${method.value}" class="mr-2">
                                <span class="mr-2">${method.icon}</span>
                                <span>${method.label}</span>
                            </label>
                        `).join('')}
                    </div>

                    <div class="mb-4">
                        <h3 class="font-semibold mb-2">Shipping Method</h3>
                        ${shippingMethods.map(method => `
                            <label class="flex items-center mb-2 cursor-pointer">
                                <input type="radio" name="shippingMethod" value="${method.value}" class="mr-2" onchange="toggleShippingInfo()">
                                <span class="mr-2">${method.icon}</span>
                                <span>${method.label}</span>
                            </label>
                        `).join('')}
                    </div>

                    <div id="shippingInfo" class="hidden mb-4 p-3 bg-gray-100 rounded">
                        <h4 class="font-semibold mb-2">Shipping Address</h4>
                        
                        <!- Address Selection Options ->
                        <div class="mb-3">
                            <label class="flex items-center mb-2">
                                <input type="radio" name="addressOption" value="profile" class="mr-2" checked onchange="window.cart.toggleAddressFields()">
                                <span>Use my profile address</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="addressOption" value="custom" class="mr-2" onchange="window.cart.toggleAddressFields()">
                                <span>Enter a different delivery address</span>
                            </label>
                        </div>
                        
                        <!- Profile Address Display ->
                        <div id="profileAddressDisplay" class="mb-3 p-3 bg-white border rounded">
                            <div class="text-sm text-gray-600" id="profileAddressText">Loading your address...</div>
                        </div>
                        
                        <!- Custom Address Fields ->
                        <div id="customAddressFields" class="hidden space-y-2">
                            <input type="text" id="customAddressLine1" placeholder="Address Line 1" class="w-full p-2 border rounded">
                            <input type="text" id="customAddressLine2" placeholder="Address Line 2 (Optional)" class="w-full p-2 border rounded">
                            <div class="grid grid-cols-3 gap-2">
                                <input type="text" id="customCity" placeholder="City" class="p-2 border rounded">
                                <input type="text" id="customState" placeholder="State" class="p-2 border rounded">
                                <input type="text" id="customZipCode" placeholder="ZIP Code" class="p-2 border rounded">
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-2">
                        <button type="button" class="flex-1 py-2 px-4 rounded text-white u-cart-btn-cancel">Cancel</button>
                        <button onclick="window.cart.proceedToCheckout()" class="brand-button flex-1 py-2 px-4 rounded">Place Order</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Add event listener for shipping method changes
            modal.querySelectorAll('input[name="shippingMethod"]').forEach(input => {
                input.addEventListener('change', window.toggleShippingInfo);
            });
            
            // Load user profile address when shipping info is shown
            this.loadProfileAddress();
        },

        async proceedToCheckout() {
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked')?.value;
            const shippingMethod = document.querySelector('input[name="shippingMethod"]:checked')?.value;

            if (!paymentMethod || !shippingMethod) {
                if (window.wfNotifications && window.wfNotifications.warning) {
                    window.wfNotifications.warning('Please select both payment and shipping methods.', { duration: 5000 });
                } else {
                    alert('Please select both payment and shipping methods.');
                }
                return;
            }

            // Get user info
            const userRaw = sessionStorage.getItem('user');
            let user = null;
            if (userRaw) {
                try { user = JSON.parse(userRaw); } catch(e) { console.warn('Invalid user JSON in sessionStorage'); }
            }

            if (!user) {
                if (window.wfNotifications && window.wfNotifications.warning) {
                    window.wfNotifications.warning('Please log in to complete your order.', { duration: 5000 });
                } else {
                    alert('Please log in to complete your order.');
                }
                setTimeout(() => {
                    window.location.href = '/?page=login';
                }, 1500);
                return;
            }

            await this.submitCheckout(paymentMethod, shippingMethod);
        },

        async submitCheckout(paymentMethod, shippingMethod) {
            try {
                console.log('Session storage user:', sessionStorage.getItem('user'));
                const customerId = sessionStorage.getItem('user') ? JSON.parse(sessionStorage.getItem('user')).userId : null;
                console.log('Customer ID:', customerId);
                
                if (!customerId) {
                    if (window.wfNotifications && window.wfNotifications.warning) {
                        window.wfNotifications.warning('Please log in to complete your order.', { duration: 5000 });
                    } else {
                        alert('Please log in to complete your order.');
                    }
                    return;
                }

                // Validate cart items have valid SKUs
                const invalidItems = cartState.items.filter(item => !item.sku || item.sku === 'undefined');
                if (invalidItems.length > 0) {
                    console.error('Invalid SKUs found in cart:', cartState.items);
                    if (window.wfNotifications && window.wfNotifications.error) {
                        window.wfNotifications.error('Some items in your cart are invalid. Please refresh the page and try again.', { duration: 5000 });
                    } else {
                        alert('Some items in your cart are invalid. Please refresh the page and try again.');
                    }
                    return;
                }

                // Collect shipping address information if needed
                let shippingAddress = null;
                if (shippingMethod !== 'pickup') {
                    const addressOption = document.querySelector('input[name="addressOption"]:checked')?.value;
                    
                    if (addressOption === 'profile') {
                        // Use profile address
                        if (this.userProfileData) {
                            shippingAddress = {
                                addressLine1: this.userProfileData.addressLine1 || '',
                                addressLine2: this.userProfileData.addressLine2 || '',
                                city: this.userProfileData.city || '',
                                state: this.userProfileData.state || '',
                                zipCode: this.userProfileData.zipCode || ''
                            };
                        }
                    } else if (addressOption === 'custom') {
                        // Use custom address
                        const line1 = document.getElementById('customAddressLine1')?.value?.trim() || '';
                        const line2 = document.getElementById('customAddressLine2')?.value?.trim() || '';
                        const city = document.getElementById('customCity')?.value?.trim() || '';
                        const state = document.getElementById('customState')?.value?.trim() || '';
                        const zipCode = document.getElementById('customZipCode')?.value?.trim() || '';
                        
                        if (!line1 || !city || !state || !zipCode) {
                            if (window.wfNotifications && window.wfNotifications.warning) {
                                window.wfNotifications.warning('Please fill in all required address fields (Address Line 1, City, State, ZIP Code).', { duration: 5000 });
                            } else {
                                alert('Please fill in all required address fields (Address Line 1, City, State, ZIP Code).');
                            }
                            return;
                        }
                        
                        shippingAddress = {
                            addressLine1: line1,
                            addressLine2: line2,
                            city: city,
                            state: state,
                            zipCode: zipCode
                        };
                    }
                    
                    // Validate that we have a shipping address for non-pickup orders
                    if (!shippingAddress || !shippingAddress.addressLine1) {
                        if (window.wfNotifications && window.wfNotifications.warning) {
                            window.wfNotifications.warning('Please provide a shipping address for delivery orders.', { duration: 5000 });
                        } else {
                            alert('Please provide a shipping address for delivery orders.');
                        }
                        return;
                    }
                }

                const itemIds = cartState.items.map(item => item.sku);
                const quantities = cartState.items.map(item => item.quantity);
                const colors = cartState.items.map(item => item.color || null);
                const sizes = cartState.items.map(item => item.size || null);

                const orderData = {
                    customerId: customerId,
                    itemIds: itemIds,
                    quantities: quantities,
                    colors: colors,
                    sizes: sizes,
                    paymentMethod: paymentMethod,
                    shippingMethod: shippingMethod,
                    total: this.getTotal()
                };

                // Add shipping address if provided
                if (shippingAddress) {
                    orderData.shippingAddress = shippingAddress;
                }

                console.log('Order data being sent:', orderData);
                console.log('Cart items:', cartState.items);
                console.log('Cart total:', this.getTotal());

                const response = await apiPost('add-order.php', orderData);

                if (!response.ok) {
                    console.error('Server error:', response.status, response.statusText);
                    const errorText = await response.text();
                    console.error('Error response:', errorText);
                    if (window.wfNotifications && window.wfNotifications.error) {
                        window.wfNotifications.error(`Server error (${response.status}): Please try again or contact support.`, { duration: 5000 });
                    } else {
                        alert(`Server error (${response.status}): Please try again or contact support.`);
                    }
                    return;
                }

                const result = await response.json();

                if (result.success) {
                    // Clear cart
                    this.clearCart();
                    
                    // Remove modal
                    document.getElementById('paymentMethodModal').remove();
                    
                    // Redirect to receipt page
                    window.location.href = `/?page=receipt&orderId=${result.orderId}`;
                } else {
                    if (window.wfNotifications && window.wfNotifications.error) {
                        window.wfNotifications.error('Order failed: ' + result.error, { duration: 5000 });
                    } else {
                        alert('Order failed: ' + result.error);
                    }
                }
            } catch (error) {
                console.error('Checkout error:', error);
                if (window.wfNotifications && window.wfNotifications.error) {
                    window.wfNotifications.error('An error occurred during checkout. Please try again.', { duration: 5000 });
                } else {
                    alert('An error occurred during checkout. Please try again.');
                }
            }
        },

        async loadProfileAddress() {
            try {
                const user = sessionStorage.getItem('user') ? JSON.parse(sessionStorage.getItem('user')) : null;
                if (!user) return;

                // Fetch user profile data
                const userData = await apiGet(`users.php?id=${user.userId}`);
                
                
                if (userData && !userData.error) {
                    const addressParts = [];
                    if (userData.addressLine1) addressParts.push(userData.addressLine1);
                    if (userData.addressLine2) addressParts.push(userData.addressLine2);
                    
                    const cityStateZip = [];
                    if (userData.city) cityStateZip.push(userData.city);
                    if (userData.state) cityStateZip.push(userData.state);
                    if (userData.zipCode) cityStateZip.push(userData.zipCode);
                    
                    if (cityStateZip.length > 0) {
                        addressParts.push(cityStateZip.join(', '));
                    }
                    
                    const profileAddressText = document.getElementById('profileAddressText');
                    if (profileAddressText) {
                        if (addressParts.length > 0) {
                            profileAddressText.innerHTML = addressParts.join('<br>');
                        } else {
                            profileAddressText.innerHTML = '<em>No address on file. Please enter a delivery address below.</em>';
                            // Auto-select custom address option if no profile address
                            const customOption = document.querySelector('input[name="addressOption"][value="custom"]');
                            if (customOption) {
                                customOption.checked = true;
                                this.toggleAddressFields();
                            }
                        }
                    }
                    
                    // Store user data for later use
                    this.userProfileData = userData;
                }
            } catch (error) {
                console.error('Error loading profile address:', error);
                const profileAddressText = document.getElementById('profileAddressText');
                if (profileAddressText) {
                    profileAddressText.innerHTML = '<em>Error loading address. Please enter a delivery address below.</em>';
                }
            }
        },

        toggleAddressFields() {
            const profileOption = document.querySelector('input[name="addressOption"][value="profile"]');
            const customOption = document.querySelector('input[name="addressOption"][value="custom"]');
            const profileDisplay = document.getElementById('profileAddressDisplay');
            const customFields = document.getElementById('customAddressFields');
            
            if (profileOption && profileOption.checked) {
                if (profileDisplay) profileDisplay.style.display = 'block';
                if (customFields) customFields.style.display = 'none';
            } else if (customOption && customOption.checked) {
                if (profileDisplay) profileDisplay.style.display = 'none';
                if (customFields) customFields.style.display = 'block';
            }
        }
    };

    // Register global functions immediately
    function registerGlobalFunctions() {
        // Main cart object
        window.cart = {
            addItem: (item) => cartMethods.addItem(item),
            removeItem: (sku) => cartMethods.removeItem(sku),
            updateItem: (sku, quantity) => cartMethods.updateItem(sku, quantity),
            clearCart: () => cartMethods.clearCart(),
            getItems: () => cartMethods.getItems(),
            getTotal: () => cartMethods.getTotal(),
            getCount: () => cartMethods.getCount(),
            getState: () => cartMethods.getState(),
            setNotifications: (enabled) => cartMethods.setNotifications(enabled),
            showCurrentCartStatus: () => cartMethods.showCurrentCartStatus(),
            showCartStatusToast: () => cartMethods.showCartStatusToast(),
            renderCart: () => cartMethods.renderCart(),
            checkout: () => cartMethods.checkout(),
            createPaymentMethodModal: () => cartMethods.createPaymentMethodModal(),
            proceedToCheckout: () => cartMethods.proceedToCheckout(),
            submitCheckout: (paymentMethod, shippingMethod) => cartMethods.submitCheckout(paymentMethod, shippingMethod),
            loadProfileAddress: () => cartMethods.loadProfileAddress(),
            toggleAddressFields: () => cartMethods.toggleAddressFields(),
            
            // Legacy methods
            items: cartState.items,
            total: cartState.total,
            count: cartState.count
        };

        // Global cart functions
        window.addToCart = (item) => cartMethods.addItem(item);
        window.removeFromCart = (sku) => cartMethods.removeItem(sku);
        window.updateCartItem = (sku, quantity) => cartMethods.updateItem(sku, quantity);
        window.clearCart = () => cartMethods.clearCart();
        window.getCartItems = () => cartMethods.getItems();
        window.getCartTotal = () => cartMethods.getTotal();
        window.getCartCount = () => cartMethods.getCount();

        // Global cart status functions (matching June 30th config)
        window.showCartStatus = () => cartMethods.showCurrentCartStatus();
        window.showCartStatusToast = () => cartMethods.showCartStatusToast();
        
        // Enhanced cart access for iframe contexts
        window.accessCart = function() {
            // Try multiple access patterns
            if (window.cart && typeof window.cart.addItem === 'function') {
                return window.cart;
            }
            
            try {
                if (window.parent && window.parent.cart && typeof window.parent.cart.addItem === 'function') {
                    return window.parent.cart;
                }
            } catch (e) {
                // Cross-origin access denied
            }
            
            try {
                if (window.top && window.top.cart && typeof window.top.cart.addItem === 'function') {
                    return window.top.cart;
                }
            } catch (e) {
                // Cross-origin access denied
            }
            
            return null;
        };
        
        // Expose notification system for iframe access
        window.accessNotifications = function() {
            // Try to get branded notification functions from current or parent window
            const notifications = {};
            
            try {
                // First try to access the branded notification system
                if (window.wfNotifications && typeof window.wfNotifications.success === 'function') {
                    notifications.showSuccess = (message, options = {}) => window.wfNotifications.success(message, options);
                    notifications.showError = (message, options = {}) => window.wfNotifications.error(message, options);
                    notifications.showInfo = (message, options = {}) => window.wfNotifications.info(message, options);
                    notifications.showWarning = (message, options = {}) => window.wfNotifications.warning(message, options);
                } else if (window.parent && window.parent.wfNotifications && typeof window.parent.wfNotifications.success === 'function') {
                    notifications.showSuccess = (message, options = {}) => window.parent.wfNotifications.success(message, options);
                    notifications.showError = (message, options = {}) => window.parent.wfNotifications.error(message, options);
                    notifications.showInfo = (message, options = {}) => window.parent.wfNotifications.info(message, options);
                    notifications.showWarning = (message, options = {}) => window.parent.wfNotifications.warning(message, options);
                }
                
                // Fallback to simple notification functions if branded system not available
                if (!notifications.showSuccess) {
                    if (typeof window.showSuccess === 'function') {
                        notifications.showSuccess = window.showSuccess;
                        notifications.showError = window.showError || window.showNotification;
                        notifications.showInfo = window.showInfo || window.showNotification;
                        notifications.showWarning = window.showWarning || window.showNotification;
                    } else if (window.parent && typeof window.parent.showSuccess === 'function') {
                        notifications.showSuccess = window.parent.showSuccess;
                        notifications.showError = window.parent.showError || window.parent.showNotification;
                        notifications.showInfo = window.parent.showInfo || window.parent.showNotification;
                        notifications.showWarning = window.parent.showWarning || window.parent.showNotification;
                    }
                }
            } catch (e) {
                console.log('[Cart] Notification access failed:', e.message);
            }
            
            return notifications;
        };
        
        console.log('[Cart] Global functions registered');
        console.log('[Cart] Cart object available:', typeof window.cart);
        console.log('[Cart] Cart addItem method:', typeof window.cart.addItem);
    }

    // Initialize cart system immediately
    function initializeCart() {
        console.log('[Cart] Initializing cart system...');
        
        // Load cart from localStorage
        cartMethods.loadCart();
        
        // Register global functions
        registerGlobalFunctions();
        
        // Update cart display
        cartMethods.updateCartDisplay();
        
        cartState.initialized = true;
        console.log('[Cart] Cart system initialized');
    }

    // Cart system module for core registration
    const CartSystem = {
        name: 'cart-system',
        dependencies: [],
        priority: 2,

        async init(core) {
            core.log('Cart system module registered with core');
            // Cart already initialized, just confirm it's working
            if (window.cart && typeof window.cart.addItem === 'function') {
                core.log('✅ Cart system verified and accessible');
                core.log('Cart methods available:', {
                    addItem: typeof window.cart.addItem,
                    getItems: typeof window.cart.getItems,
                    getTotal: typeof window.cart.getTotal
                });
            } else {
                core.log('❌ Cart system not found after initialization');
            }
        }
    };

    // Initialize cart system immediately (don't wait for core)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initializeCart();
            // Register module with core system when it's available
            if (window.WhimsicalFrog) {
                window.WhimsicalFrog.registerModule(CartSystem.name, CartSystem);
            }
        });
    } else {
        initializeCart();
        // Register module with core system when it's available
        if (window.WhimsicalFrog) {
            window.WhimsicalFrog.registerModule(CartSystem.name, CartSystem);
        }
    }

})(); 

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

