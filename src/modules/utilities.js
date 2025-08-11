/**
 * WhimsicalFrog Consolidated Utilities Module
 * Enhanced utility functions recovered and consolidated from legacy systems
 * Vite compatible ES6 module
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
            console.error('[API] Request failed:', error);
            throw error;
        }
    }

    // Convenience methods
    static async get(url, options = {}) {
        return this.request(url, { ...options, method: 'GET' });
    }

    static async post(url, data = null, options = {}) {
        const config = { ...options, method: 'POST' };
        if (data) {
            config.body = JSON.stringify(data);
        }
        return this.request(url, config);
    }

    static async put(url, data = null, options = {}) {
        const config = { ...options, method: 'PUT' };
        if (data) {
            config.body = JSON.stringify(data);
        }
        return this.request(url, config);
    }

    static async delete(url, options = {}) {
        return this.request(url, { ...options, method: 'DELETE' });
    }
}

class DOMUtils {
    /**
     * Safely set innerHTML with loading state
     * @param {HTMLElement} element - Target element
     * @param {string} content - HTML content to set
     * @param {boolean} showLoading - Whether to show loading state first
     */
    static async setContent(element, content, showLoading = false) {
        if (!element) return;

        if (showLoading) {
            element.innerHTML = this.createLoadingSpinner();
            // Small delay to show loading state
            await new Promise(resolve => setTimeout(resolve, 100));
        }

        element.innerHTML = content;
    }

    /**
     * Create a loading spinner element
     * @param {string} message - Loading message
     * @returns {string} - HTML for loading spinner
     */
    static createLoadingSpinner(message = 'Loading...') {
        return `
            <div class="loading-spinner" style="display: flex; align-items: center; justify-content: center; padding: 20px;">
                <div style="width: 20px; height: 20px; border: 2px solid #f3f3f3; border-top: 2px solid #333; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 10px;"></div>
                <span>${this.escapeHtml(message)}</span>
            </div>
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        `;
    }

    /**
     * Create an error message element
     * @param {string} message - Error message
     * @returns {string} - HTML for error message
     */
    static createErrorMessage(message) {
        return `
            <div class="error-message" style="background: #fee; border: 1px solid #fcc; color: #a00; padding: 10px; border-radius: 4px; margin: 10px 0;">
                <strong>Error:</strong> ${this.escapeHtml(message)}
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
            <div class="success-message" style="background: #efe; border: 1px solid #cfc; color: #060; padding: 10px; border-radius: 4px; margin: 10px 0;">
                <strong>Success:</strong> ${this.escapeHtml(message)}
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
        const colors = {
            success: { bg: '#4caf50', text: 'white' },
            error: { bg: '#f44336', text: 'white' },
            info: { bg: '#2196f3', text: 'white' },
            warning: { bg: '#ff9800', text: 'white' }
        };

        const color = colors[type] || colors.info;

        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${color.bg};
            color: ${color.text};
            padding: 12px 20px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            font-size: 14px;
            max-width: 300px;
            word-wrap: break-word;
            transition: opacity 0.3s ease, transform 0.3s ease;
            transform: translateX(100%);
            opacity: 0;
        `;
        toast.textContent = message;

        document.body.appendChild(toast);

        // Animate in
        setTimeout(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        }, 10);

        // Animate out and remove
        setTimeout(() => {
            toast.style.transform = 'translateX(100%)';
            toast.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, duration);

        return toast;
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
        if (isNaN(value)) return '$0.00';
        return `$${parseFloat(value).toFixed(2)}`;
    }

    /**
     * Escape HTML to prevent XSS
     * @param {string} text - Text to escape
     * @returns {string} - Escaped text
     */
    static escapeHtml(text) {
        if (!text) return '';
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
    static confirm(message, title = 'Confirm') {
        return new Promise((resolve) => {
            // Create modal overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10001;
                display: flex;
                align-items: center;
                justify-content: center;
            `;

            // Create dialog
            const dialog = document.createElement('div');
            dialog.style.cssText = `
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                max-width: 400px;
                width: 90%;
                text-align: center;
            `;

            dialog.innerHTML = `
                <h3 style="margin: 0 0 15px 0; color: #333;">${this.escapeHtml(title)}</h3>
                <p style="margin: 0 0 20px 0; color: #666;">${this.escapeHtml(message)}</p>
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button class="confirm-btn" style="background: #2196f3; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Confirm</button>
                    <button class="cancel-btn" style="background: #999; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Cancel</button>
                </div>
            `;

            overlay.appendChild(dialog);
            document.body.appendChild(overlay);

            // Event handlers
            const cleanup = () => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            };

            dialog.querySelector('.confirm-btn').addEventListener('click', () => {
                cleanup();
                resolve(true);
            });

            dialog.querySelector('.cancel-btn').addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    cleanup();
                    resolve(false);
                }
            });

            // Focus confirm button
            setTimeout(() => {
                dialog.querySelector('.confirm-btn').focus();
            }, 100);
        });
    }

    /**
     * Throttle function calls
     * @param {Function} func - Function to throttle
     * @param {number} limit - Time limit in ms
     * @returns {Function} - Throttled function
     */
    static throttle(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Deep clone an object
     * @param {Object} obj - Object to clone
     * @returns {Object} - Cloned object
     */
    static deepClone(obj) {
        if (obj === null || typeof obj !== 'object') return obj;
        if (obj instanceof Date) return new Date(obj.getTime());
        if (obj instanceof Array) return obj.map(item => this.deepClone(item));
        if (typeof obj === 'object') {
            const clonedObj = {};
            for (const key in obj) {
                if (obj.hasOwnProperty(key)) {
                    clonedObj[key] = this.deepClone(obj[key]);
                }
            }
            return clonedObj;
        }
    }
}

// Consolidated Utilities class that combines everything
class WhimsicalFrogUtils {
    constructor() {
        this.api = ApiClient;
        this.dom = DOMUtils;
        this.init();
    }

    init() {
        console.log('[Utils] Consolidated utilities initialized');
        
        // Make convenience methods available on the instance
        this.toast = DOMUtils.showToast;
        this.confirm = DOMUtils.confirm;
        this.debounce = DOMUtils.debounce;
        this.throttle = DOMUtils.throttle;
        this.formatCurrency = DOMUtils.formatCurrency;
        this.escapeHtml = DOMUtils.escapeHtml;
        this.deepClone = DOMUtils.deepClone;
    }

    // Convenience API methods
    async get(url, options = {}) {
        return ApiClient.get(url, options);
    }

    async post(url, data = null, options = {}) {
        return ApiClient.post(url, data, options);
    }

    async put(url, data = null, options = {}) {
        return ApiClient.put(url, data, options);
    }

    async delete(url, options = {}) {
        return ApiClient.delete(url, options);
    }

    // Show different types of toast notifications
    showSuccess(message, duration = 3000) {
        return DOMUtils.showToast(message, 'success', duration);
    }

    showError(message, duration = 5000) {
        return DOMUtils.showToast(message, 'error', duration);
    }

    showInfo(message, duration = 3000) {
        return DOMUtils.showToast(message, 'info', duration);
    }

    showWarning(message, duration = 4000) {
        return DOMUtils.showToast(message, 'warning', duration);
    }
}

// Export for ES6 modules
export default WhimsicalFrogUtils;
export { ApiClient, DOMUtils };

// Global compatibility
if (typeof window !== 'undefined') {
    window.WhimsicalFrogUtils = WhimsicalFrogUtils;
    window.ApiClient = ApiClient;
    window.DOMUtils = DOMUtils;
    
    // Convenience global functions
    window.apiGet = (url, options = {}) => ApiClient.get(url, options);
    window.apiPost = (url, data = null, options = {}) => ApiClient.post(url, data, options);
    window.apiPut = (url, data = null, options = {}) => ApiClient.put(url, data, options);
    window.apiDelete = (url, options = {}) => ApiClient.delete(url, options);
    
    window.showToast = DOMUtils.showToast;
    window.confirm = DOMUtils.confirm;
    window.debounce = DOMUtils.debounce;
    window.throttle = DOMUtils.throttle;
    window.formatCurrency = DOMUtils.formatCurrency;
    window.escapeHtml = DOMUtils.escapeHtml;
}
