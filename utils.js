/**
 * Utility Functions
 * Common utility functions used throughout the application.
 */

/**
 * Debounce function
 * Delays invoking a function until after `wait` milliseconds have elapsed since the last time it was invoked.
 * @param {Function} func The function to debounce.
 * @param {number} wait The number of milliseconds to delay.
 * @returns {Function} The new debounced function.
 */
export function debounce(func, wait) {
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
 * Throttle function
 * Creates a throttled function that only invokes `func` at most once per every `limit` milliseconds.
 * @param {Function} func The function to throttle.
 * @param {number} limit The minimum time between invocations.
 * @returns {Function} The new throttled function.
 */
export function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}

/**
 * Simple event emitter
 * @returns {{on: Function, emit: Function, off: Function}} An event emitter object.
 */
export function createEventEmitter() {
    const events = {};
    return {
        on(event, callback) {
            if (!events[event]) {
                events[event] = [];
            }
            events[event].push(callback);
        },
        emit(event, data) {
            if (events[event]) {
                events[event].forEach(callback => callback(data));
            }
        },
        off(event, callback) {
            if (events[event]) {
                events[event] = events[event].filter(cb => cb !== callback);
            }
        },
    };
}


console.log('Utils loaded');
