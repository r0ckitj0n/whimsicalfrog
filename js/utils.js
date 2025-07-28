/**
 * Utility Functions
 * Common utility functions used throughout the application
 */

console.log('Loading utils.js...');

// Basic utility functions
window.utils = {
    // Debounce function
    debounce: function(func, wait) {
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
    
    // Throttle function
    throttle: function(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },
    
    // Simple event emitter
    createEventEmitter: function() {
        const events = {};
        return {
            on: function(event, callback) {
                if (!events[event]) events[event] = [];
                events[event].push(callback);
            },
            emit: function(event, data) {
                if (events[event]) {
                    events[event].forEach(callback => callback(data));
                }
            },
            off: function(event, callback) {
                if (events[event]) {
                    events[event] = events[event].filter(cb => cb !== callback);
                }
            }
        };
    }
};

console.log('Utils loaded');
