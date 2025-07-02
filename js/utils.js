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

class PrintUtils {
    /**
     * Print a receipt with centralized functionality
     * @param {string} orderId - Order ID for tracking
     * @param {number} orderTotal - Order total for analytics
     * @param {Object} options - Print options
     */
    static async printReceipt(orderId, orderTotal = 0, options = {}) {
        const defaults = {
            showNotifications: true,
            trackAnalytics: true,
            preparationDelay: 500,
            successDelay: 1000
        };
        
        const config = { ...defaults, ...options };
        
        try {
            // Log print action for analytics
            if (config.trackAnalytics && window.analytics && typeof window.analytics.track === 'function') {
                window.analytics.track('receipt-printed', {
                    orderId: orderId,
                    orderTotal: orderTotal,
                    timestamp: new Date().toISOString(),
                    source: 'print-utils'
                });
            }

            // Show print preparation message
            if (config.showNotifications && window.showInfo && typeof window.showInfo === 'function') {
                window.showInfo('Preparing receipt for printing...', { duration: config.preparationDelay + 500 });
            }

            // Small delay to let notification show, then print
            setTimeout(() => {
                window.print();
            }, config.preparationDelay);

            // Track successful print dialog opening
            setTimeout(() => {
                if (config.showNotifications && window.showSuccess && typeof window.showSuccess === 'function') {
                    window.showSuccess('Receipt sent to printer! 🖨️', { duration: 3000 });
                }
            }, config.successDelay);

            return { success: true, orderId: orderId };

        } catch (error) {
            console.error('Print receipt error:', error);
            
            // Fallback to simple print if centralized functions fail
            window.print();
            
            // Show error message if notification system is available
            if (config.showNotifications && window.showError && typeof window.showError === 'function') {
                window.showError('Print function encountered an issue but should still work.', { duration: 3000 });
            }
            
            return { success: false, error: error.message, orderId: orderId };
        }
    }

    /**
     * Print any document with preparation
     * @param {Object} options - Print options
     */
    static async printDocument(options = {}) {
        const defaults = {
            showNotifications: true,
            preparationMessage: 'Preparing document for printing...',
            successMessage: 'Document sent to printer! 🖨️',
            preparationDelay: 300
        };
        
        const config = { ...defaults, ...options };
        
        try {
            // Show print preparation message
            if (config.showNotifications && window.showInfo && typeof window.showInfo === 'function') {
                window.showInfo(config.preparationMessage, { duration: config.preparationDelay + 500 });
            }

            // Small delay to let notification show, then print
            setTimeout(() => {
                window.print();
            }, config.preparationDelay);

            // Show success message
            setTimeout(() => {
                if (config.showNotifications && window.showSuccess && typeof window.showSuccess === 'function') {
                    window.showSuccess(config.successMessage, { duration: 3000 });
                }
            }, config.preparationDelay + 700);

            return { success: true };

        } catch (error) {
            console.error('Print document error:', error);
            
            // Fallback to simple print
            window.print();
            
            return { success: false, error: error.message };
        }
    }

    /**
     * Setup keyboard shortcuts for printing
     * @param {Function} printFunction - Function to call when Ctrl+P is pressed
     */
    static setupPrintShortcuts(printFunction) {
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                if (typeof printFunction === 'function') {
                    printFunction();
                } else {
                    PrintUtils.printDocument();
                }
            }
        });
    }

    /**
     * Initialize print functionality with system checks
     * @param {string} pageType - Type of page being initialized
     * @param {string} identifier - Page identifier (like order ID)
     */
    static initialize(pageType = 'document', identifier = '') {
        console.log(`Print functionality initialized for ${pageType}${identifier ? ': ' + identifier : ''}`);
        
        // Check if centralized notification system is available
        if (typeof window.showInfo === 'function') {
            console.log('✅ Centralized notification system detected');
        } else {
            console.log('⚠️ Centralized notification system not available - using fallback');
        }
        
        // Check if analytics system is available
        if (window.analytics && typeof window.analytics.track === 'function') {
            console.log('✅ Analytics system detected');
        } else {
            console.log('ℹ️ Analytics system not available');
        }

        return {
            notificationSystem: typeof window.showInfo === 'function',
            analyticsSystem: window.analytics && typeof window.analytics.track === 'function'
        };
    }
}

// Make utilities available globally
window.ApiClient = ApiClient;
window.DOMUtils = DOMUtils;
window.PrintUtils = PrintUtils;

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
window.printReceipt = (orderId, orderTotal) => PrintUtils.printReceipt(orderId, orderTotal);
window.printDocument = (options) => PrintUtils.printDocument(options);

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