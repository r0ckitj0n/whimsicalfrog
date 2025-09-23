/**
 * WhimsicalFrog Core System
 * Provides module management, API access, event bus, and initialization.
 * This is the central hub for the application's frontend logic.
 */
import apiClient from './api-client.js';

const WhimsicalFrog = {
    modules: new Map(),
    readyCallbacks: [],
    initialized: false,
    api: apiClient, // Attach the imported API client

    /**
     * A simple event bus for inter-module communication.
     */
    eventBus: {
        events: {},
        on(event, listener) {
            if (!this.events[event]) {
                this.events[event] = [];
            }
            this.events[event].push(listener);
        },
        emit(event, data) {
            if (this.events[event]) {
                this.events[event].forEach(listener => listener(data));
            }
        }
    },

    /**
     * A simple logging utility.
     * @param {string} message - The message to log.
     * @param {string} [level='info'] - The log level ('info', 'warn', 'error').
     */
    log(message, level = 'info') {
        const prefix = '🐸 [WF]';
        switch (level) {
            case 'warn':
                console.warn(`${prefix} ${message}`);
                break;
            case 'error':
                console.error(`${prefix} ${message}`);
                break;
            default:
                console.log(`${prefix} ${message}`);
                break;
        }
    },

    /**
     * Registers a module with the core system.
     * @param {string} name - The name of the module.
     * @param {object} module - The module object to register.
     */
    registerModule(name, module) {
        this.log(`Registering module: ${name}`);
        this.modules.set(name, module);
    },

    /**
     * Retrieves a registered module by name.
     * @param {string} name - The name of the module to retrieve.
     * @returns {object|undefined} The module object or undefined if not found.
     */
    getModule(name) {
        return this.modules.get(name);
    },

    /**
     * Executes a callback function once the core system is initialized.
     * If the system is already initialized, the callback is executed immediately.
     * @param {function} callback - The function to execute on ready.
     * @returns {object} The WhimsicalFrog object for chaining.
     */
    ready(callback) {
        if (this.initialized) {
            callback(this);
        } else {
            this.readyCallbacks.push(callback);
        }
        return this;
    },

    /**
     * Initializes the core system, executing all pending ready callbacks.
     * This function is called automatically once the DOM is loaded.
     */
    init() {
        if (this.initialized) return;
        this.initialized = true;
        this.log('Core Initialized');

        this.readyCallbacks.forEach(callback => {
            try {
                callback(this);
            } catch (error) {
                this.log(`Error in ready callback: ${error}`, 'error');
            }
        });
        this.readyCallbacks = []; // Clear callbacks after execution
    }
};

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => WhimsicalFrog.init());
} else {
    // Already loaded
    WhimsicalFrog.init();
}

export default WhimsicalFrog;
