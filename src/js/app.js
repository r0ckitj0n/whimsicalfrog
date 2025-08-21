/**
 * Main application entry point.
 * This file is responsible for initializing the core framework and all primary modules.
 */

// Import CSS to be processed by Vite
import '../styles/main.css';

// reload-tracer removed (dev-only)
import './whimsical-frog-core-unified.js';
import CartSystem from '../modules/cart-system.js';
import RoomModalManager from '../modules/room-modal-manager.js';
import SearchSystem from '../modules/search-system.js';
import SalesSystem from '../modules/sales-system.js';
import WhimsicalFrogUtils from '../modules/utilities.js';
import _MainApplication from './main-application.js';
import _ShopPage from './shop.js';

// Critical missing modules for core functionality
import './dynamic-background-loader.js';  // Background loading
import './room-coordinate-manager.js';    // Room map coordinates
import './api-client.js';                 // API communication
import './api-aliases.js';                // Legacy API globals
import './wait-for-function.js';          // Wait for function utility
import './modal-manager.js';              // General modal management (WFModals)
import './global-item-modal.js';
import '../../js/detailed-item-modal.js'; // Canonical detailed item modal (IIFE registers globals)
import './global-modals.js';
import './cart-modal.js';                 // Site-wide cart modal overlay
import '../ui/globalPopup.js';              // Modal system
import './global-notifications.js';       // Notification system
import './landing-page.js';               // Landing page functionality
import './room-main.js';                  // Room main page
import './analytics.js';
import './login-modal.js';                // Login modal and flow
import './payment-modal.js';              // Payment modal for in-place checkout
import './receipt-modal.js';              // Receipt modal for post-checkout
//
import './contact.js';                    // Contact page AJAX submit
import './reveal-company-modal.js';       // Single-button modal reveal for company info
import './room-icons-init.js';            // Initialize CSS vars for room icons from data-*

console.log('app.js loaded');
import '../modules/image-carousel.js';
import '../modules/footer-newsletter.js';
import '../modules/ai-processing-modal.js';
import '../modules/image-fallback.js';

// Function to initialize all core systems
function initializeCoreSystemsApp() {
    console.log('[App] Initializing recovered systems...');
    
    // Prevent double initialization
    if (window.WF_SYSTEMS_INITIALIZED) {
        console.log('[App] Systems already initialized, skipping');
        return;
    }
    
    // Initialize cart system
    const cartSystem = new CartSystem();
    window.WF_Cart = cartSystem;
    if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
        WhimsicalFrog.registerModule('cart-system', cartSystem);
    }
    
    // Initialize room modal manager
    console.log('[App] Creating RoomModalManager instance...');
    const roomModalManager = new RoomModalManager();
    window.WF_RoomModal = roomModalManager;
    window.roomModalManager = roomModalManager;  // Also expose as lowercase for compatibility
    if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
        WhimsicalFrog.registerModule('room-modal-manager', roomModalManager);
    }
    console.log('[App] RoomModalManager created and exposed globally:', roomModalManager);
    
    // Initialize search system
    const searchSystem = new SearchSystem();
    window.WF_Search = searchSystem;
    if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
        WhimsicalFrog.registerModule('search-system', searchSystem);
    }
    
    // Initialize sales system  
    const salesSystem = new SalesSystem();
    window.WF_Sales = salesSystem;
    if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
        WhimsicalFrog.registerModule('sales-system', salesSystem);
    }
    
    // Initialize utilities
    const utilities = new WhimsicalFrogUtils();
    window.WF_Utils = utilities;
    if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
        WhimsicalFrog.registerModule('utilities', utilities);
    }
    
    // Mark systems as initialized
    window.WF_SYSTEMS_INITIALIZED = true;
    console.log('[App] All core systems initialized successfully');
}

// Debug WhimsicalFrog.ready function
if (window.WhimsicalFrog) {
    console.log('[App] WhimsicalFrog object exists:', window.WhimsicalFrog);
    console.log('[App] WhimsicalFrog.ready function:', window.WhimsicalFrog.ready);
    console.log('[App] WhimsicalFrog.Core:', window.WhimsicalFrog.Core);
    console.log('[App] WhimsicalFrog.Core.initialized:', window.WhimsicalFrog.Core?.initialized);
}

// Initialize systems via WhimsicalFrog.ready OR immediately if already ready
if (window.WhimsicalFrog && window.WhimsicalFrog.ready) {
    console.log('[App] WhimsicalFrog available, attempting ready callback');
    
    // Debug the ready function behavior
    const originalCallback = initializeCoreSystemsApp;
    const debugCallback = function() {
        console.log('[App] 🎉 Ready callback is EXECUTING!');
        originalCallback();
    };
    
    try {
        WhimsicalFrog.ready(debugCallback);
        console.log('[App] Ready callback registered successfully');
        
        // Force immediate execution if already initialized
        if (window.WhimsicalFrog.Core?.initialized) {
            console.log('[App] Core already initialized, callback should have run immediately');
            
            // If it didn't run, force it
            setTimeout(() => {
                if (!window.WF_SYSTEMS_INITIALIZED) {
                    console.log('[App] 🚨 Callback never executed, forcing initialization');
                    initializeCoreSystemsApp();
                }
            }, 200);
        }
    } catch (error) {
        console.error('[App] Error with ready callback:', error);
        initializeCoreSystemsApp();
    }
} else {
    console.log('[App] WhimsicalFrog not ready, setting up fallback initialization');
    // Immediate fallback
    initializeCoreSystemsApp();
}

// The core WF object is exported and initialized automatically.
// Modules that need to run on specific pages will be imported here.
// Their own init logic will determine if they should run.

// Public per-page dynamic imports (non-admin)
(function setupPublicPageImports() {
    function run() {
        try {
            const body = document.body;
            const ds = body ? body.dataset : {};
            const page = (ds && ds.page) || (window.WF_PAGE_INFO && window.WF_PAGE_INFO.page) || '';

            const isRoom = typeof page === 'string' && /^room\d+$/.test(page);
            if (isRoom) {
                import('./pages/room-page.js')
                    .then(() => console.log(`[App] Public module loaded for page: ${page}`))
                    .catch(err => console.error(`[App] Failed to load public module for page: ${page}`, err));
                return;
            }

            const loaders = {
                'cart': () => import('./pages/cart-page.js'),
                'register': () => import('./pages/register-page.js'),
                'account_settings': () => import('./pages/account-settings-page.js'),
                'account-settings': () => import('./pages/account-settings-page.js'),
                'payment': () => import('./pages/payment-page.js'),
            };

            const load = loaders[page];
            if (typeof load === 'function') {
                load()
                    .then(() => console.log(`[App] Public module loaded for page: ${page}`))
                    .catch(err => console.error(`[App] Failed to load public module for page: ${page}`, err));
            }
        } catch (e) {
            console.error('[App] Error setting up public per-page imports', e);
        }
    }

    if (document.body) {
        run();
    } else {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    }
})();

// Per-page dynamic imports (admin-safe)
(function setupPerPageImports() {
    function run() {
        try {
            const body = document.body;
            const ds = body ? body.dataset : {};
            const page = (ds && ds.page) || (window.WF_PAGE_INFO && window.WF_PAGE_INFO.page) || '';
            const isAdmin = (ds && ds.isAdmin === 'true') || (typeof page === 'string' && page.startsWith('admin'));

            if (!isAdmin) return;

            const fullPath = (ds && ds.path) || window.location.pathname || '';
            const cleanPath = fullPath.split('?')[0].split('#')[0];
            const parts = cleanPath.split('/').filter(Boolean); // e.g., ['admin','inventory']
            let section = (parts[0] === 'admin') ? (parts[1] || 'dashboard') : '';
            section = (section || '').toLowerCase();
            // Strip file extensions like .php
            if (section.endsWith('.php')) section = section.replace(/\.php$/, '');

            // Map aliases to canonical section names
            const aliases = {
                'index': 'dashboard',
                'home': 'dashboard',
                'order': 'orders',
                'product': 'inventory',
                'products': 'inventory',
                'customer': 'customers',
                'user': 'customers',
                'users': 'customers',
                'report': 'reports',
                'setting': 'settings',
                // DB status routes
                'db_status': 'db-status',
                'dbstatus': 'db-status',
                // DB/Web manager routes
                'db_web_manager': 'db-web-manager',
                'db-web-manager': 'db-web-manager',
                // Room config manager
                'room_config_manager': 'room-config-manager',
                'room-config-manager': 'room-config-manager',
                // Cost breakdown manager
                'cost_breakdown_manager': 'cost-breakdown-manager',
                'cost-breakdown-manager': 'cost-breakdown-manager',
            };
            if (aliases[section]) section = aliases[section];

            // Dynamic import per admin section
            const loaders = {
                'dashboard': () => import('./admin-dashboard.js'),
                'customers': () => import('./admin-customers.js'),
                'inventory': () => import('./admin-inventory.js'),
                'orders': () => import('./admin-orders.js'),
                'pos': () => import('./admin-pos.js'),
                'reports': () => import('./admin-reports.js'),
                'marketing': () => import('./admin-marketing.js'),
                'settings': () => import('./admin-settings.js'),
                'categories': () => import('./admin-categories.js'),
                'db-status': () => import('./admin-db-status.js'),
                'db-web-manager': () => import('./admin-db-web-manager.js'),
                'room-config-manager': () => import('./admin-room-config-manager.js'),
                'cost-breakdown-manager': () => import('./admin-cost-breakdown-manager.js'),
                'secrets': () => import('./admin-secrets.js'),
            };

            const load = loaders[section] || (() => import('./admin-dashboard.js'));
            load()
                .then(() => console.log(`[App] Admin module loaded for section: ${section}`))
                .catch(err => console.error(`[App] Failed to load admin module for section: ${section}`, err));
        } catch (e) {
            console.error('[App] Error setting up per-page imports', e);
        }
    }

    if (document.body) {
        run();
    } else {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    }
})();
