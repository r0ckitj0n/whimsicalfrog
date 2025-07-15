/**
 * WhimsicalFrog Core JavaScript System
 * Unified dependency management and initialization
 */

(function() {
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
    function registerModule(name, module) {
        if (WF_CORE.modules[name]) {
            log(`Module ${name} already registered, overwriting`, 'warn');
        }
        
        WF_CORE.modules[name] = {
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
        try {
            const initOrder = resolveDependencies();
            log(`Initializing modules in order: ${initOrder.join(', ')}`);

            for (const moduleName of initOrder) {
                const module = WF_CORE.modules[moduleName];
                
                if (module.init && typeof module.init === 'function') {
                    log(`Initializing module: ${moduleName}`);
                    await module.init(WF_CORE);
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
        
        WF_CORE.initialized = true;
        
        // Emit initialization complete event
        eventBus.emit('core:initialized', WF_CORE);
        
        log('WhimsicalFrog Core initialization complete');
    }

    // Expose core system globally
    window.WhimsicalFrog = {
        Core: WF_CORE,
        registerModule,
        init,
        log,
        utils,
        api,
        eventBus,
        
        // State accessors
        getState: () => WF_CORE.state,
        getConfig: () => WF_CORE.config,
        getModule: (name) => WF_CORE.modules[name],
        
        // Convenience functions
        ready: (callback) => {
            if (WF_CORE.initialized) {
                callback(WF_CORE);
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
})();

/**
 * WhimsicalFrog Cart System Module
 * Unified cart management with notifications
 */
(function() {
    'use strict';

    if (!window.WhimsicalFrog) {
        console.error('WhimsicalFrog Core not found. Cart module cannot be registered.');
        return;
    }

    const cartModule = {
        name: 'CartSystem',
        dependencies: [],
        state: {
            items: [],
            total: 0,
            count: 0,
            notifications: true
        },

        init: function(WF) {
            this.WF = WF;
            this.loadCart();
            // Expose a global cart object for backward compatibility and easy access
            window.cart = {
                addItem: this.addItem.bind(this),
                removeItem: this.removeItem.bind(this),
                updateQuantity: this.updateQuantity.bind(this),
                getItems: () => this.state.items,
                getTotal: () => this.state.total,
                getCount: () => this.state.count,
                clearCart: this.clearCart.bind(this)
            };
            WF.log('CartSystem initialized.');
        },

        loadCart: function() {
            try {
                const saved = localStorage.getItem('whimsical_frog_cart');
                if (saved) {
                    const data = JSON.parse(saved);
                    this.state.items = data.items || [];
                    this.recalculateTotal();
                }
            } catch (error) {
                this.WF.log(`Error loading cart: ${error.message}`, 'error');
                this.state.items = [];
            }
        },

        saveCart: function() {
            try {
                const data = {
                    items: this.state.items,
                    total: this.state.total,
                    count: this.state.count,
                    timestamp: Date.now()
                };
                localStorage.setItem('whimsical_frog_cart', JSON.stringify(data));
            } catch (error) {
                this.WF.log(`Error saving cart: ${error.message}`, 'error');
            }
        },

        recalculateTotal: function() {
            this.state.count = this.state.items.reduce((sum, item) => sum + item.quantity, 0);
            this.state.total = this.state.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            this.WF.eventBus.emit('cartUpdated', { ...this.state });
        },

        addItem: function(item) {
            const existingItem = this.state.items.find(i => i.id === item.id);
            if (existingItem) {
                existingItem.quantity += item.quantity;
            } else {
                this.state.items.push(item);
            }
            this.recalculateTotal();
            this.saveCart();
        },

        removeItem: function(itemId) {
            this.state.items = this.state.items.filter(i => i.id !== itemId);
            this.recalculateTotal();
            this.saveCart();
        },

        updateQuantity: function(itemId, quantity) {
            const item = this.state.items.find(i => i.id === itemId);
            if (item) {
                item.quantity = Math.max(0, quantity);
                if (item.quantity === 0) {
                    this.removeItem(itemId);
                } else {
                    this.recalculateTotal();
                    this.saveCart();
                }
            }
        },

        clearCart: function() {
            this.state.items = [];
            this.recalculateTotal();
            this.saveCart();
        }
    };

    window.WhimsicalFrog.registerModule(cartModule.name, cartModule);
})();

/**
 * WhimsicalFrog Main Application Module
 * Handles core UI logic like navigation, login, and cart display.
 */
(function() {
    'use strict';

    if (!window.WhimsicalFrog) {
        console.error('WhimsicalFrog Core not found. Main App module cannot be registered.');
        return;
    }

    const mainAppModule = {
        name: 'MainApplication',
        dependencies: ['CartSystem'],
        
        init: function(WF) {
            this.WF = WF;
            this.ensureSingleNavigation();
            this.setupEventListeners();
            this.handleLoginForm();
            this.WF.log('Main Application module initialized.');
        },

        ensureSingleNavigation: function() {
            const navElements = document.querySelectorAll('nav.main-nav');
            if (navElements.length > 1) {
                this.WF.log(`Found ${navElements.length} navigation elements, removing duplicates...`);
                for (let i = 1; i < navElements.length; i++) {
                    navElements[i].remove();
                }
            }
        },

        updateMainCartCounter: function() {
            const cartCountEl = document.getElementById('cartCount');
            if (window.cart && cartCountEl) {
                const count = window.cart.getCount();
                cartCountEl.textContent = count + ' items';
            }
        },

        setupEventListeners: function() {
            this.WF.eventBus.on('cartUpdated', () => this.updateMainCartCounter());
            
            // Initial cart count update
            this.WF.ready(() => {
                 this.updateMainCartCounter();
            });
        },

        loadModalBackground: async function(roomType) {
            if (!roomType) {
                this.WF.log('No roomType provided for modal background.', 'warn');
                return;
            }
            try {
                const response = await this.WF.api.get(`/api/get_background.php?room_type=${roomType}`);
                if (response.success && response.background) {
                    const background = response.background;
                    const supportsWebP = document.documentElement.classList.contains('webp');
                    const imageUrl = supportsWebP && background.webp_filename ?
                        `images/${background.webp_filename}` :
                        `images/${background.image_filename}`;

                    const modalOverlay = document.querySelector('.room-modal-overlay');
                    if (modalOverlay) {
                        modalOverlay.style.setProperty('--dynamic-bg-url', `url('${imageUrl}')`);
                        this.WF.log(`Modal background loaded for: ${roomType}`);
                    } else {
                        this.WF.log('Room modal overlay not found.', 'warn');
                    }
                }
            } catch (error) {
                this.WF.log(`Error loading modal background for ${roomType}: ${error.message}`, 'error');
            }
        },

        resetToPageBackground: function() {
            const modalOverlay = document.querySelector('.room-modal-overlay');
            if (modalOverlay) {
                modalOverlay.style.removeProperty('--dynamic-bg-url');
            }
            this.WF.log('Modal background reset.');
        },

        handleLoginForm: function() {
            const loginForm = document.getElementById('loginForm');
            if (!loginForm) return;

            loginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;
                const errorMessage = document.getElementById('errorMessage');
                errorMessage.classList.add('hidden');

                try {
                    const data = await this.WF.api.post('/process_login.php', { username, password });
                    
                    sessionStorage.setItem('user', JSON.stringify(data.user || data));

                    if (data.redirectUrl) {
                        window.location.href = data.redirectUrl;
                    } else if (localStorage.getItem('pendingCheckout') === 'true') {
                        localStorage.removeItem('pendingCheckout');
                        window.location.href = '/?page=cart';
                    } else {
                        window.location.href = data.role === 'Admin' ? '/?page=admin' : '/?page=room_main';
                    }
                } catch (error) {
                    errorMessage.textContent = error.message;
                    errorMessage.classList.remove('hidden');
                }
            });
        }
    };

    window.WhimsicalFrog.registerModule(mainAppModule.name, mainAppModule);

})();