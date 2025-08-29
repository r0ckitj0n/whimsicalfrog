/**
 * WhimsicalFrog Unified JavaScript System
 * Single entry point for all JavaScript functionality
 */

(function() {
    'use strict';

    // If the global flag WF_BUNDLE_LOADED is set, it means we are running from
    // the pre-built bundle (js/bundle.js). In that case, all required modules
    // are already present, so we should skip the dynamic loader to avoid
    // double-loading and "Identifier has already been declared" errors.
    if (window.WF_BUNDLE_LOADED) {
        console.log('[WF-Loader] Bundle detected – skipping dynamic module loading');
        return;
    }

    // Configuration
    const WF_CONFIG = {
        debug: true,
        modules: [
            'js/whimsical-frog-core.js',
            'js/modules/cart-system.js'
        ],
        legacyModules: [
            'js/central-functions.js', // Load centralized utilities first
            'js/global-notifications.js', // Unified notification system
            'js/sales-checker.js',
            'js/global-popup.js',
            'js/global-item-modal.js',
            'js/search.js',
            'js/analytics.js',
            'js/image-viewer.js',
            'js/global-modals.js'
        ],
        conditionalModules: {
            'admin': ['js/modal-close-positioning.js'],
            'room_main': [] // room-main.js is already loaded in index.php
        }
    };

    // Module loader
    const ModuleLoader = {
        loaded: [],
        loading: [],
        failed: [],

        // Load a single module
        async loadModule(src) {
            if (this.loaded.includes(src)) {
                return Promise.resolve();
            }

            if (this.loading.includes(src)) {
                return this.waitForModule(src);
            }

            this.loading.push(src);

            return new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = src + '?v=' + Date.now();
                script.async = false;
                
                script.onload = () => {
                    this.loaded.push(src);
                    this.loading.splice(this.loading.indexOf(src), 1);
                    if (WF_CONFIG.debug) {
                        console.log(`[WF-Loader] Loaded: ${src}`);
                    }
                    resolve();
                };
                
                script.onerror = () => {
                    this.failed.push(src);
                    this.loading.splice(this.loading.indexOf(src), 1);
                    if (WF_CONFIG.debug) {
                        console.error(`[WF-Loader] Failed to load: ${src}`);
                    }
                    reject(new Error(`Failed to load module: ${src}`));
                };
                
                document.head.appendChild(script);
            });
        },

        // Wait for a module that's already loading
        waitForModule(src) {
            return new Promise((resolve, reject) => {
                const checkLoaded = () => {
                    if (this.loaded.includes(src)) {
                        resolve();
                    } else if (this.failed.includes(src)) {
                        reject(new Error(`Module failed to load: ${src}`));
                    } else {
                        setTimeout(checkLoaded, 50);
                    }
                };
                checkLoaded();
            });
        },

        // Load all modules in order
        async loadAllModules() {
            try {
                console.log('[WF-Loader] Starting unified JavaScript system...');
                
                // Load core modules first
                for (const module of WF_CONFIG.modules) {
                    await this.loadModule(module);
                }
                
                // Wait for core system to initialize
                await this.waitForCoreInitialization();
                
                // Load legacy modules
                for (const module of WF_CONFIG.legacyModules) {
                    try {
                        await this.loadModule(module);
                    } catch (error) {
                        console.warn(`[WF-Loader] Legacy module failed: ${module}`, error);
                    }
                }
                
                // Load conditional modules based on current page
                const currentPage = new URLSearchParams(window.location.search).get('page') || 'landing';
                await this.loadConditionalModules(currentPage);
                
                console.log('[WF-Loader] All modules loaded successfully');
                
                // Initialize page-specific functionality
                this.initializePageSpecific();
                
            } catch (error) {
                console.error('[WF-Loader] Critical error loading modules:', error);
                throw error;
            }
        },

        // Wait for core system to initialize
        waitForCoreInitialization() {
            return new Promise((resolve) => {
                if (window.WhimsicalFrog && window.WhimsicalFrog.Core.initialized) {
                    resolve();
                } else {
                    const checkInitialized = () => {
                        if (window.WhimsicalFrog && window.WhimsicalFrog.Core.initialized) {
                            resolve();
                        } else {
                            setTimeout(checkInitialized, 50);
                        }
                    };
                    checkInitialized();
                }
            });
        },

        // Load conditional modules based on page type
        async loadConditionalModules(currentPage) {
            // Determine page type for conditional loading
            let pageType = 'default';
            
            if (currentPage.startsWith('admin')) {
                pageType = 'admin';
            } else if (currentPage === 'room_main') {
                pageType = 'room_main';
            }
            
            // Load conditional modules for this page type
            if (WF_CONFIG.conditionalModules[pageType]) {
                console.log(`[WF-Loader] Loading conditional modules for: ${pageType}`);
                
                for (const module of WF_CONFIG.conditionalModules[pageType]) {
                    try {
                        await this.loadModule(module);
                        console.log(`[WF-Loader] Conditional module loaded: ${module}`);
                    } catch (error) {
                        console.warn(`[WF-Loader] Conditional module failed: ${module}`, error);
                    }
                }
            }
        },

        // Initialize page-specific functionality
        initializePageSpecific() {
            const currentPage = new URLSearchParams(window.location.search).get('page') || 'landing';
            
            console.log(`[WF-Loader] Initializing page-specific functionality for: ${currentPage}`);
            
            // Room pages
            if (currentPage.startsWith('room') && currentPage !== 'room_main') {
                this.initializeRoomPage(currentPage);
            }
            
            // Main room page
            if (currentPage === 'room_main') {
                this.initializeMainRoomPage();
            }
            
            // Admin pages
            if (currentPage.startsWith('admin')) {
                this.initializeAdminPage();
            }
            
            // Shop page
            if (currentPage === 'shop') {
                this.initializeShopPage();
            }
            
            // Cart page
            if (currentPage === 'cart') {
                this.initializeCartPage();
            }
        },

        // Initialize room page functionality
        initializeRoomPage(roomNumber) {
            console.log(`[WF-Loader] Initializing room page: ${roomNumber}`);
            
            // Wait for WhimsicalFrog to be ready
            if (window.WhimsicalFrog) {
                WhimsicalFrog.ready(() => {
                    // Set room state
                    WhimsicalFrog.getState().currentRoom = roomNumber;
                    
                    // Initialize room-specific functionality
                    if (window.initializeRoom) {
                        window.initializeRoom(roomNumber.replace('room', ''), 'room');
                    }
                    
                    console.log(`[WF-Loader] Room ${roomNumber} initialized`);
                });
            }
        },

        // Initialize main room page
        initializeMainRoomPage() {
            console.log('[WF-Loader] Initializing main room page');
            
            // Main room script is already loaded via conditional modules
            // No need to load it again here
            console.log('[WF-Loader] Main room functionality ready');
        },

        // Initialize admin page
        initializeAdminPage() {
            console.log('[WF-Loader] Initializing admin page');
            
            // Load admin-specific modules
            // These would be loaded as needed
        },

        // Initialize shop page
        initializeShopPage() {
            console.log('[WF-Loader] Initializing shop page');
            
            // Shop-specific initialization
            if (window.WhimsicalFrog) {
                WhimsicalFrog.ready(() => {
                    console.log('[WF-Loader] Shop page initialized');
                });
            }
        },

        // Initialize cart page
        initializeCartPage() {
            console.log('[WF-Loader] Initializing cart page');
            
            // Cart-specific initialization
            if (window.WhimsicalFrog) {
                WhimsicalFrog.ready(() => {
                    console.log('[WF-Loader] Cart page initialized');
                });
            }
        }
    };

    // Auto-start the loading process
    function startUnifiedSystem() {
        console.log('[WF-Loader] Starting WhimsicalFrog unified system...');
        
        ModuleLoader.loadAllModules().then(() => {
            console.log('[WF-Loader] ✅ WhimsicalFrog unified system ready!');
            
            // Emit global ready event
            document.dispatchEvent(new CustomEvent('whimsicalfrog:ready'));
            
        }).catch((error) => {
            console.error('[WF-Loader] ❌ Critical error starting system:', error);
            
            // Show error notification to user
            const errorDiv = document.createElement('div');
            errorDiv.classList.add(
                'u-position-fixed', 'u-top-20px', 'u-left-20px',
                'u-background-dc2626', 'u-color-white', 'u-padding-12px',
                'u-border-radius-8px', 'u-z-index-9999', 'u-max-width-300px',
                'u-font-family-Merienda-cursive'
            );
            errorDiv.innerHTML = `
                <strong>System Error</strong><br>
                JavaScript loading failed. Please refresh the page.
            `;
            document.body.appendChild(errorDiv);
        });
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startUnifiedSystem);
    } else {
        startUnifiedSystem();
    }

    // Expose loader for debugging
    window.WF_ModuleLoader = ModuleLoader;

    console.log('[WF-Loader] WhimsicalFrog unified system loader initialized');
})(); 