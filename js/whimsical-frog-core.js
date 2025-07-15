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
            ...module,
            initialized: false,
            dependencies: module.dependencies || [],
            priority: module.priority || 0
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