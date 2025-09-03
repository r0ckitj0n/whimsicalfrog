/**
 * Main application entry point.
 * This file is responsible for initializing the core framework and all primary modules.
 */

// Import CSS to be processed by Vite
import '../styles/main.css';

// TEMP: detect duplicate module evaluations during dev or unexpected reloads
try {
    window.__WF_APP_LOADS = (window.__WF_APP_LOADS || 0) + 1;
    console.log('[App] app.js evaluation count:', window.__WF_APP_LOADS, 'url:', (import.meta && import.meta.url));
    if (window.__WF_APP_LOADS > 1) {
        console.warn('[App] Duplicate app.js evaluation detected');
    }
} catch (_) {}

// reload-tracer removed (dev-only)
import './whimsical-frog-core-unified.js';
import CartSystem from '../modules/cart-system.js';
import RoomModalManager from '../modules/room-modal-manager.js';
import SearchSystem from '../modules/search-system.js';
import SalesSystem from '../modules/sales-system.js';
import WhimsicalFrogUtils from '../modules/utilities.js';
import _ShopPage from './shop.js';

// Critical missing modules for core functionality
import './dynamic-background-loader.js';  // Background loading
import './room-coordinate-manager.js';    // Room map coordinates
import './api-client.js';                 // API communication
import './api-aliases.js';                // Legacy API globals
import './wait-for-function.js';          // Wait for function utility
import './modal-manager.js';              // General modal management (WFModals)
import './global-item-modal.js';
import './global-modals.js';
import './cart-modal.js';                 // Site-wide cart modal overlay
import '../ui/globalPopup.js';              // Modal system
import './global-notifications.js';       // Notification system
import './landing-page.js';               // Landing page functionality
import './room-main.js';                  // Room main page
import './login-modal.js';                // Login modal and flow
import './header-auth-sync.js';           // Keep header UI in sync after login
import './payment-modal.js';              // Payment modal for in-place checkout
import './receipt-modal.js';              // Receipt modal for post-checkout
import './header-offset.js';              // Compute --wf-header-height for precise top offset
import _MainApplication from './main-application.js';
//
import './contact.js';                    // Contact page AJAX submit
import './reveal-company-modal.js';       // Single-button modal reveal for company info
import './room-icons-init.js';            // Initialize CSS vars for room icons from data-*

// Feature flags
// Admin settings module can be disabled via query param for isolation: ?disable_settings_module=1
let ENABLE_ADMIN_SETTINGS_PAGE = true;
try {
    const params = new URLSearchParams(window.location.search || '');
    if (params.get('disable_settings_module') === '1') {
        ENABLE_ADMIN_SETTINGS_PAGE = false;
        console.warn('[App] Admin settings module disabled via query flag (?disable_settings_module=1)');
    }
} catch (e) {}

console.log('app.js loaded');
// Public-only modules: load only on non-admin pages
(function loadPublicOnlyModules(){
    function isAdmin() {
        try {
            const ds = document.body?.dataset || {};
            const pageToken = (ds && ds.page) || '';
            return (ds && ds.isAdmin === 'true') || (typeof pageToken === 'string' && pageToken.startsWith('admin'));
        } catch (_) { return false; }
    }
    function doLoad() {
        try {
            if (isAdmin()) {
                console.log('[App] Admin page; skipping public-only modules (carousel, newsletter, ai-processing-modal, image-fallback)');
                return;
            }
            import('../modules/image-carousel.js')
                .then(() => console.log('[App] image-carousel loaded'))
                .catch(err => console.warn('[App] image-carousel failed', err));
            import('../modules/footer-newsletter.js')
                .then(() => console.log('[App] footer-newsletter loaded'))
                .catch(err => console.warn('[App] footer-newsletter failed', err));
            import('../modules/ai-processing-modal.js')
                .then(() => console.log('[App] ai-processing-modal loaded'))
                .catch(err => console.warn('[App] ai-processing-modal failed', err));
            import('../modules/image-fallback.js')
                .then(() => console.log('[App] image-fallback loaded'))
                .catch(err => console.warn('[App] image-fallback failed', err));
            import('./analytics.js')
                .then(() => console.log('[App] analytics loaded'))
                .catch(err => console.warn('[App] analytics failed', err));
        } catch (e) {
            console.warn('[App] Error while loading public-only modules', e);
        }
    }
    if (document.body) doLoad();
    else document.addEventListener('DOMContentLoaded', doLoad, { once: true });
})();

// Measure admin tabs height and expose as CSS var for precise scroll sizing
(function setAdminTabsHeightToken(){
    const VAR_NAME = '--admin-tabs-height';
    function isAdmin() {
        try {
            const ds = document.body?.dataset || {};
            const pageToken = (ds && ds.page) || '';
            return (ds && ds.isAdmin === 'true') || (typeof pageToken === 'string' && pageToken.startsWith('admin'));
        } catch (_) { return false; }
    }
    function measure() {
        try {
            if (!isAdmin()) return;
            // Typical selector from sections/admin.php
            const nav = document.querySelector('.admin-tab-navigation');
            let h = 0;
            if (nav && nav.getBoundingClientRect) {
                h = Math.ceil(nav.getBoundingClientRect().height || 0);
            }
            // If no tabs (e.g., POS), keep small top gap
            if (!h) h = 8; // minimal spacer
            document.documentElement.style.setProperty(VAR_NAME, `${h}px`);
        } catch(_) {}
    }
    function onReady(fn){
        if (document.readyState === 'complete' || document.readyState === 'interactive') fn();
        else document.addEventListener('DOMContentLoaded', fn, { once: true });
    }
    onReady(() => {
        measure();
        // Recompute on resize or font load/layout shifts
        let t;
        window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(measure, 100); });
        // Also on module-loaded tick to catch late CSS
        setTimeout(measure, 250);
    });
})();

// Mark admin root on <html> to control root scrolling via CSS (avoids :has dependency)
(function markAdminRoot(){
    function isAdmin() {
        try {
            const ds = document.body?.dataset || {};
            const pageToken = (ds && ds.page) || '';
            return (ds && ds.isAdmin === 'true') || (typeof pageToken === 'string' && pageToken.startsWith('admin'));
        } catch (_) { return false; }
    }
    function run() {
        try {
            if (isAdmin()) {
                document.documentElement.classList.add('wf-admin-root');
            } else {
                document.documentElement.classList.remove('wf-admin-root');
            }
        } catch(_) {}
    }
    if (document.body) run();
    else document.addEventListener('DOMContentLoaded', run, { once: true });
})();

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
    
    // Initialize room modal manager only on non-admin pages (root fix)
    try {
        const ds = document.body?.dataset || {};
        const pageToken = (ds && ds.page) || '';
        const isAdmin = (ds && ds.isAdmin === 'true') || (typeof pageToken === 'string' && pageToken.startsWith('admin'));
        if (!isAdmin) {
            console.log('[App] Creating RoomModalManager instance...');
            const roomModalManager = new RoomModalManager();
            window.WF_RoomModal = roomModalManager;
            window.roomModalManager = roomModalManager;  // Also expose as lowercase for compatibility
            if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
                WhimsicalFrog.registerModule('room-modal-manager', roomModalManager);
            }
            console.log('[App] RoomModalManager created and exposed globally:', roomModalManager);
            // Load legacy detailed-item-modal only on non-admin pages
            import('../../js/detailed-item-modal.js')
                .then(() => console.log('[App] detailed-item-modal loaded (public only)'))
                .catch(err => console.warn('[App] Failed to load detailed-item-modal (public only)', err));
        } else {
            console.log('[App] Admin page detected; skipping RoomModalManager and detailed-item-modal.');
        }
    } catch (e) {
        console.warn('[App] Failed to determine admin status for RoomModalManager init; proceeding to skip on error.', e);
    }
    
    // Initialize search system only on non-admin pages
    try {
        const ds = document.body?.dataset || {};
        const pageToken = (ds && ds.page) || '';
        const isAdmin = (ds && ds.isAdmin === 'true') || (typeof pageToken === 'string' && pageToken.startsWith('admin'));
        if (!isAdmin) {
            const searchSystem = new SearchSystem();
            window.WF_Search = searchSystem;
            if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
                WhimsicalFrog.registerModule('search-system', searchSystem);
            }
        } else {
            console.log('[App] Admin page detected; skipping SearchSystem instantiation.');
        }
    } catch (e) {
        console.warn('[App] Failed to determine admin status for SearchSystem init; proceeding to skip on error.', e);
    }
    
    // Initialize sales system only on non-admin pages
    try {
        const ds = document.body?.dataset || {};
        const pageToken = (ds && ds.page) || '';
        const isAdmin = (ds && ds.isAdmin === 'true') || (typeof pageToken === 'string' && pageToken.startsWith('admin'));
        if (!isAdmin) {
            const salesSystem = new SalesSystem();
            window.WF_Sales = salesSystem;
            if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
                WhimsicalFrog.registerModule('sales-system', salesSystem);
            }
        } else {
            console.log('[App] Admin page detected; skipping SalesSystem initialization.');
        }
    } catch (e) {
        console.warn('[App] Failed to determine admin status for SalesSystem init; proceeding to skip on error.', e);
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
    if (window.__WF_PUBLIC_IMPORTS_DONE) {
        console.log('[App] Public page imports already configured; skipping');
        return;
    }
    window.__WF_PUBLIC_IMPORTS_DONE = true;
    function run() {
        try {
            const body = document.body;
            const ds = body ? body.dataset : {};
            const pageRaw = (ds && ds.page) || (window.WF_PAGE_INFO && window.WF_PAGE_INFO.page) || '';
            // Candidate A: explicit data-page token
            const candidateA = (typeof pageRaw === 'string' && !pageRaw.includes('/')) ? pageRaw : '';
            // Candidate B: last segment from path
            const pathRaw = (ds && ds.path) || window.location.pathname || '';
            const last = pathRaw.split('?')[0].split('#')[0].split('/').filter(Boolean).pop() || '';
            const candidateB = (last || '').replace(/\.php$/i, '');

            const resolved = candidateA || candidateB;
            console.log('[App] Public page detection:', { pageRaw, candidateA, pathRaw, candidateB: resolved ? candidateB : '' });

            // Force-load payment page if DOM hooks exist, regardless of page token
            const hasPaymentDom = document.getElementById('paymentPage') || document.getElementById('checkoutRoot');
            if (hasPaymentDom) {
                console.log('[App] Payment DOM detected; force-loading payment module');
                import('./pages/payment-page.js').catch(err => console.error('[App] Failed to load payment-page.js', err));
                return;
            }

            const loaders = {
                'cart': () => import('./pages/cart-page.js'),
                'login': () => import('./pages/login-page.js'),
                'register': () => import('./pages/register-page.js'),
                'account_settings': () => import('./pages/account-settings-page.js'),
                'account-settings': () => import('./pages/account-settings-page.js'),
                'payment': () => import('./pages/payment-page.js'),
            };

            // Resolve final page by preferring a candidate that maps to a loader
            const page = loaders[candidateA] ? candidateA : (loaders[candidateB] ? candidateB : (candidateA || candidateB || ''));
            console.log('[App] Public page resolved to:', page);
            const isRoom = typeof page === 'string' && /^room\d+$/.test(page);
            if (isRoom) {
                import('./pages/room-page.js')
                    .then(() => console.log(`[App] Public module loaded for page: ${page}`))
                    .catch(err => console.error(`[App] Failed to load public module for page: ${page}`, err));
                return;
            }
            const load = loaders[page];
            if (typeof load === 'function') {
                load()
                    .then(() => console.log(`[App] Public module loaded for page: ${page}`))
                    .catch(err => console.error(`[App] Failed to load public module for page: ${page}`, err));
            } else if (document.getElementById('paymentPage')) {
                console.warn('[App] Payment DOM detected but slug not resolved; loading payment-page.js via DOM fallback', { page, pageRaw, candidateA, candidateB, pathRaw });
                import('./pages/payment-page.js')
                    .then(() => console.log('[App] Public module loaded via DOM fallback: payment'))
                    .catch(err => console.error('[App] Failed to load payment module via DOM fallback', err));
            } else {
                console.log('[App] No public per-page module to load for page:', page);
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
    if (window.__WF_ADMIN_IMPORTS_DONE) {
        console.log('[App] Admin page imports already configured; skipping');
        return;
    }
    window.__WF_ADMIN_IMPORTS_DONE = true;
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
                // Route through wrapper to support fine-grained isolation flags
                'settings': () => ENABLE_ADMIN_SETTINGS_PAGE ? import('./admin-settings-wrapper.js') : Promise.resolve().then(() => ({ default: null })),
                'categories': () => import('./admin-categories.js'),
                'db-status': () => import('./admin-db-status.js'),
                'db-web-manager': () => import('./admin-db-web-manager.js'),
                'room-config-manager': () => import('./admin-room-config-manager.js'),
                'cost-breakdown-manager': () => import('./admin-cost-breakdown-manager.js'),
                'secrets': () => import('./admin-secrets.js'),
            };

            const load = loaders[section] || (() => import('./admin-dashboard.js'));
            load()
                .then(async (mod) => {
                    if (section === 'settings' && !ENABLE_ADMIN_SETTINGS_PAGE) {
                        console.warn('[App] Admin settings module disabled by flag; loading CSS only');
                        try {
                            await import('../styles/admin-settings.css');
                            console.log('[App] Admin settings CSS loaded (JS skipped)');
                        } catch (e) {
                            console.warn('[App] Failed to load admin-settings.css', e);
                        }
                        return;
                    }
                    console.log(`[App] Admin module loaded for section: ${section}`);
                })
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
