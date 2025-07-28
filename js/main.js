/**
 * WhimsicalFrog Core System
 * Provides module management and initialization
 */

console.log('Loading WhimsicalFrog Core...');

// Core system
const WhimsicalFrog = {
    modules: new Map(),
    readyCallbacks: [],
    initialized: false,

    // Register a module
    registerModule: function(name, module) {
        console.log(`📦 Registering module: ${name}`);
        this.modules.set(name, module);
        return this;
    },

    // Add a module (alias for registerModule)
    addModule: function(name, module) {
        return this.registerModule(name, module);
    },

    // Get a module
    getModule: function(name) {
        return this.modules.get(name);
    },

    // Execute callback when ready
    ready: function(callback) {
        if (this.initialized) {
            callback(this);
        } else {
            this.readyCallbacks.push(callback);
        }
        return this;
    },

    // Initialize the system
    init: function() {
        if (this.initialized) return this;

        console.log('🐸 Initializing WhimsicalFrog Core...');
        this.initialized = true;

        // Execute ready callbacks
        this.readyCallbacks.forEach(callback => {
            try {
                callback(this);
            } catch (error) {
                console.error('Error in ready callback:', error);
            }
        });

        console.log('🐸 WhimsicalFrog Core initialized');
        return this;
    }
};

// Expose globally
window.WhimsicalFrog = WhimsicalFrog;

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => WhimsicalFrog.init());
} else {
    WhimsicalFrog.init();
}

console.log('WhimsicalFrog Core loaded');
