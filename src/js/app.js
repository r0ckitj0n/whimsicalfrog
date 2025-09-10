/**
 * Main application entry point.
 * This file is responsible for initializing the core framework and all primary modules.
 */

// Import CSS to be processed by Vite
import '../styles/main.css';
import '../core/actionRegistry.js';
import './body-background-from-data.js';

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

// Note: Public modules are loaded dynamically in initializeCoreSystemsApp() to avoid
// unnecessary work on admin routes.

console.log('app.js loaded');

// Admin page detection (path-based, resilient before DOMContentLoaded)
function __wfIsAdminPage() {
    try {
        const path = (window.location && window.location.pathname) ? window.location.pathname : '';
        // e.g., /admin/settings, /admin/index.php, /admin
        return /^\/?admin(\/|$)/i.test(path);
    } catch (_) { return false; }
}
const __WF_IS_ADMIN = __wfIsAdminPage();

// Function to initialize all core systems
async function initializeCoreSystemsApp() {
    console.log('[App] Initializing recovered systems...');
    
    // Prevent double initialization
    if (window.WF_SYSTEMS_INITIALIZED) {
        console.log('[App] Systems already initialized, skipping');
        return;
    }
    
    // Load core public modules dynamically
    const [CartSystemMod, RoomModalManagerMod, SearchSystemMod, SalesSystemMod, UtilsMod] = await Promise.all([
        import('../modules/cart-system.js'),
        import('../modules/room-modal-manager.js'),
        import('../modules/search-system.js'),
        import('../modules/sales-system.js'),
        import('../modules/utilities.js'),
    ]);

    const CartSystem = CartSystemMod.default;
    const RoomModalManager = RoomModalManagerMod.default;
    const SearchSystem = SearchSystemMod.default;
    const SalesSystem = SalesSystemMod.default;
    const WhimsicalFrogUtils = UtilsMod.default;

    // Side-effect and UI modules (no exports needed)
    await Promise.all([
        import('./dynamic-background-loader.js'),
        import('./room-coordinate-manager.js'),
        import('./api-client.js'),
        import('./api-aliases.js'),
        import('./wait-for-function.js'),
        import('./modal-manager.js'),
        import('./global-item-modal.js'),
        import('./global-modals.js'),
        import('./cart-modal.js'),
        import('../ui/globalPopup.js'),
        import('./global-notifications.js'),
        import('./room-main.js'),
        import('./analytics.js'),
        import('./login-modal.js'),
        import('./header-auth-sync.js'),
        import('./payment-modal.js'),
        import('./receipt-modal.js'),
        import('./header-offset.js'),
        import('./main-application.js'),
        import('./contact.js'),
        import('./reveal-company-modal.js'),
        import('./room-icons-init.js'),
        import('../modules/image-carousel.js'),
        import('../modules/footer-newsletter.js'),
        import('../modules/ai-processing-modal.js'),
        import('../modules/image-fallback.js'),
    ]).catch(err => console.warn('[App] Non-fatal: some side-effect modules failed to import', err));

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

// Initialize systems via WhimsicalFrog.ready OR immediately if already ready (skip on admin)
if (window.WhimsicalFrog && window.WhimsicalFrog.ready && !__WF_IS_ADMIN) {
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
} else if (!__WF_IS_ADMIN) {
    console.log('[App] WhimsicalFrog not ready, setting up fallback initialization');
    // Immediate fallback
    initializeCoreSystemsApp();
}

if (__WF_IS_ADMIN) {
    console.log('[App] Admin route detected; skipping public core systems initialization');
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
                // Only load landing page positioning on the landing page
                'landing': () => import('./landing-page.js'),
                // Shop page module binds product card and button click handlers to open the global item modal
                'shop': () => import('./shop.js'),
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

            // Ensure auth-related handlers are active on admin pages too
            try { import('./login-modal.js').catch(() => {}); } catch (_) {}
            try { import('./header-auth-sync.js').catch(() => {}); } catch (_) {}
            // Enable dynamic admin tooltips (DB-driven)
            try { import('../modules/tooltip-manager.js').catch(() => {}); } catch (_) {}

            const fullPath = (ds && ds.path) || window.location.pathname || '';
            const cleanPath = fullPath.split('?')[0].split('#')[0];
            const parts = cleanPath.split('/').filter(Boolean); // e.g., ['admin','inventory'] or ['admin','admin.php']
            let section = (parts[0] === 'admin') ? (parts[1] || 'dashboard') : '';
            section = (section || '').toLowerCase();
            // Strip file extensions like .php
            if (section.endsWith('.php')) section = section.replace(/\.php$/, '');

            // Map aliases to canonical section names
            const aliases = {
                // Router file names → canonical sections
                'admin': 'dashboard',
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
                // Ensure legacy filenames route to settings module
                'admin_settings': 'settings',
                'admin-settings': 'settings',
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

            // If navigating via admin router e.g. /admin/admin.php?section=settings, prefer the query param
            try {
                const params = new URLSearchParams(window.location.search || '');
                const qSection = (params.get('section') || '').toLowerCase();
                if (qSection) {
                    section = aliases[qSection] || qSection;
                }
            } catch (_) {}

            // Dynamic import per admin section
            const loaders = {
                'dashboard': () => import('./admin-dashboard.js'),
                'customers': () => import('./admin-customers.js'),
                'inventory': () => import('./admin-inventory.js'),
                'orders': () => import('./admin-orders.js'),
                'pos': () => import('./admin-pos.js'),
                'reports': () => import('./admin-reports.js'),
                'marketing': () => import('./admin-marketing.js'),
                // Use the lightweight admin-settings entry that loads the bridge first
                // and only lazy-loads the heavy legacy module on demand
                'settings': () => import('../entries/admin-settings.js'),
                'categories': () => import('./admin-categories.js'),
                'db-status': () => import('./admin-db-status.js'),
                'db-web-manager': () => import('./admin-db-web-manager.js'),
                'room-config-manager': () => import('./admin-room-config-manager.js'),
                'cost-breakdown-manager': () => import('./admin-cost-breakdown-manager.js'),
                'secrets': () => import('./admin-secrets.js'),
                // New managers
                'room-map-manager': () => import('./admin-room-map-manager.js'),
                'area-item-mapper': () => import('./admin-area-item-mapper.js'),
                'room-map-editor': () => import('./admin-room-map-editor.js'),
            };

            const load = loaders[section] || (() => import('./admin-dashboard.js'));
            // Diagnostic guard: allow disabling specific admin sections via flag/URL to isolate freezes
            try {
                const params = new URLSearchParams(window.location.search || '');
                const disableAdmin = (window.WF_DISABLE_ADMIN_SETTINGS_JS === true) || (params.get('wf_disable_admin') === '1');
                if (section === 'settings' && disableAdmin) {
                    console.warn('[App] Admin settings module disabled by flag (?wf_disable_admin=1 or WF_DISABLE_ADMIN_SETTINGS_JS)');
                    return;
                }
            } catch (_) {}
            // Ensure Settings buttons work immediately, even before the entry loads
            if (section === 'settings' && !window.__WF_SETTINGS_MINI_INSTALLED) {
                try {
                    window.__WF_SETTINGS_MINI_INSTALLED = true;
                    const quickShow = (id) => { const el = document.getElementById(id); if (!el) return false; el.classList.remove('hidden'); el.classList.add('show'); el.setAttribute('aria-hidden','false'); return true; };
                    const quickHide = (id) => { const el = document.getElementById(id); if (!el) return false; el.classList.add('hidden'); el.classList.remove('show'); el.setAttribute('aria-hidden','true'); return true; };
                    document.addEventListener('click', (e) => {
                        try {
                            const t = e.target; const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
                            if (!document.getElementById('adminSettingsRoot')) return; // only act if settings DOM exists
                            if (t && t.classList && t.classList.contains('admin-modal-overlay')) { const id = t.id; if (!id) return; e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickHide(id); return; }
                            if (closest('[data-action="close-admin-modal"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); const overlay = closest('.admin-modal-overlay'); if (overlay && overlay.id) quickHide(overlay.id); return; }
                            if (closest('[data-action="open-business-info"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('businessInfoModal'); return; }
                            if (closest('[data-action="open-square-settings"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('squareSettingsModal'); return; }
                            if (closest('[data-action="open-email-settings"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('emailSettingsModal'); return; }
                            if (closest('[data-action="open-email-test"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); if (quickShow('emailSettingsModal')) { const test = document.getElementById('testEmailAddress')||document.getElementById('testRecipient'); if (test) setTimeout(()=>test.focus(), 50); } return; }
                            if (closest('[data-action="open-logging-status"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('loggingStatusModal'); return; }
                            if (closest('[data-action="open-ai-settings"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('aiSettingsModal'); return; }
                            if (closest('[data-action="open-ai-tools"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('aiToolsModal'); return; }
                            if (closest('[data-action="open-css-rules"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('cssRulesModal'); return; }
                            if (closest('[data-action="open-background-manager"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('backgroundManagerModal'); return; }
                            if (closest('[data-action="open-receipt-settings"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('receiptSettingsModal'); return; }
                            if (closest('[data-action="open-dashboard-config"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('dashboardConfigModal'); return; }
                            if (closest('[data-action="open-secrets-modal"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('secretsModal'); return; }
                        } catch(_) {}
                    });
                } catch (miniErr) { /* non-fatal */ }
            }

            const doLoad = () => {
                load()
                    .then(() => console.log(`[App] Admin module loaded for section: ${section}`))
                    .catch(err => console.error(`[App] Failed to load admin module for section: ${section}`, err));
            };
            if (section === 'settings') {
                try {
                    if ('requestIdleCallback' in window) {
                        window.requestIdleCallback(() => doLoad(), { timeout: 200 });
                    } else {
                        setTimeout(doLoad, 50);
                    }
                } catch (_) {
                    setTimeout(doLoad, 50);
                }
            } else {
                doLoad();
            }
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
