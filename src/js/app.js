/**
 * Main application entry point.
 * This file is responsible for initializing the core framework and all primary modules.
 */

// Import CSS to be processed by Vite
import '../styles/main.css';
import '../styles/admin-hints.css';
import '../core/action-registry.js';
import './body-background-from-data.js';
import initializeTooltipManager from '../modules/tooltip-manager.js';
// Load curator early so WF_TooltipCurator is available when needed
import '../modules/tooltip-curator.js';
import { buildAdminUrl } from '../core/admin-url-builder.js';
// Install delegated hover popup behavior early to avoid double-binding later
import '../room/event-manager.js';
import RoomModalManager from '../modules/room-modal-manager.js';
import { ApiClient } from '../core/api-client.js';

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
        // e.g., /admin/settings, /admin/index.php, /admin OR the centralized admin router
        return (/^\/?admin(\/|$)/i.test(path)) || (/^\/sections\/admin_router\.php$/i.test(path));
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
    
    // Load core public modules dynamically (modern modal only)
    const [CartSystemMod, SearchSystemMod, SalesSystemMod, UtilsMod] = await Promise.all([
        import('../modules/cart-system.js'),
        import('../modules/search-system.js'),
        import('../modules/sales-system.js'),
        import('../modules/utilities.js'),
    ]);

    // Always use modern room modal manager (statically imported at top)
    const CartSystem = CartSystemMod.default;
    const SearchSystem = SearchSystemMod.default;
    const SalesSystem = SalesSystemMod.default;
    const WhimsicalFrogUtils = UtilsMod.default;

    // Side-effect and UI modules (no exports needed)
    // Keep the absolute minimum on the critical path to reduce first-load latency
    await Promise.all([
        import('./modal-manager.js'),
        import('./global-notifications.js'),
        import('./api-aliases.js'),
    ]).catch(err => console.warn('[App] Non-fatal: minimal side-effect imports failed', err));

    // Defer non-critical UX/analytics modules to idle time to speed initial render
    const defer = (fn) => {
        try {
            if ('requestIdleCallback' in window) {
                // @ts-ignore
                return window.requestIdleCallback(fn, { timeout: 2000 });
            }
        } catch(_) {}
        return setTimeout(fn, 250);
    };
    defer(() => {
        // Defer the rest of the ecosystem, including background utilities and UI enhancers
        Promise.all([
                import('./dynamic-background-loader.js'),
                import('./room-coordinate-manager.js'),
                import('./wait-for-function.js'),
                import('./detailed-item-modal.js'),
                import('./global-modals.js'),
                import('./cart-modal.js'),
                // global-popup is now loaded via entries/app.js to avoid duplicate/stale chunks
                import('../modules/room-coordinator.js').then(m => { try { new m.RoomCoordinator().init(); } catch(_) {} }),
                import('./login-modal.js'),
                import('./header-auth-sync.js'),
                import('./header-offset.js'),
                import('./main-application.js'),
                import('./room-icons-init.js'),
                // Non-critical UX/analytics
                import('./analytics.js'),
                import('./payment-modal.js'),
                import('./receipt-modal.js'),
                import('./account-settings-modal.js'),
                import('./contact.js'),
                import('./reveal-company-modal.js'),
                import('../modules/image-carousel.js'),
                import('../modules/footer-newsletter.js'),
                import('../modules/ai-processing-modal.js'),
            ]).catch(err => console.warn('[App] Deferred modules failed', err));
    });
    
    // Initialize cart system
    const cartSystem = new CartSystem();
    window.WF_Cart = cartSystem;
    if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
        WhimsicalFrog.registerModule('cart-system', cartSystem);
    }
    // Legacy bridge: expose/augment global `window.cart` for legacy callers (e.g., detailed-item-modal.js)
    try {
        const ensureAdapter = () => {
            const target = (window.cart = window.cart || {});
            if (typeof target.add !== 'function') {
                target.add = (payload, qty = 1) => {
                    try {
                        const item = { ...(payload || {}) };
                        if (qty != null) item.quantity = qty;
                        console.log('[App] Legacy cart adapter add()', { sku: item?.sku, qty: item?.quantity });
                        if (window.WF_Cart && typeof window.WF_Cart.addItem === 'function') {
                            window.WF_Cart.addItem(item);
                        }
                    } catch (e) { console.warn('[App] window.cart.add adapter failed', e); }
                };
            }
            if (typeof target.addItem !== 'function') {
                target.addItem = (payload) => { try { window.WF_Cart && typeof window.WF_Cart.addItem === 'function' && window.WF_Cart.addItem(payload); } catch (e) { console.warn('[App] window.cart.addItem adapter failed', e); } };
            }
            if (typeof target.remove !== 'function') {
                target.remove = (sku) => { try { window.WF_Cart && typeof window.WF_Cart.removeItem === 'function' && window.WF_Cart.removeItem(sku); } catch (e) { console.warn('[App] window.cart.remove adapter failed', e); } };
            }
            if (typeof target.updateQuantity !== 'function') {
                target.updateQuantity = (sku, qty) => { try { window.WF_Cart && typeof window.WF_Cart.updateItem === 'function' && window.WF_Cart.updateItem(sku, qty); } catch (e) { console.warn('[App] window.cart.updateQuantity adapter failed', e); } };
            }
            if (typeof target.clear !== 'function') {
                target.clear = () => { try { window.WF_Cart && typeof window.WF_Cart.clearCart === 'function' && window.WF_Cart.clearCart(); } catch (e) { console.warn('[App] window.cart.clear adapter failed', e); } };
            }
            if (typeof target.getItems !== 'function') {
                target.getItems = () => { try { return (window.WF_Cart && typeof window.WF_Cart.getItems === 'function') ? window.WF_Cart.getItems() : []; } catch (_) { return []; } };
            }
            if (typeof target.getTotal !== 'function') {
                target.getTotal = () => { try { return (window.WF_Cart && typeof window.WF_Cart.getTotal === 'function') ? window.WF_Cart.getTotal() : 0; } catch (_) { return 0; } };
            }
            if (typeof target.getCount !== 'function') {
                target.getCount = () => { try { return (window.WF_Cart && typeof window.WF_Cart.getCount === 'function') ? window.WF_Cart.getCount() : 0; } catch (_) { return 0; } };
            }
        };
        ensureAdapter();
        console.log('[App] Legacy cart adapter ensured (window.cart methods bound to WF_Cart)');
    } catch (_) { /* non-fatal */ }
    
    // Initialize room modal manager
    console.log('[App] Creating RoomModalManager instance...');
    const roomModalManager = new RoomModalManager();
    window.WF_RoomModal = roomModalManager;
    window.roomModalManager = roomModalManager;  // Also expose as lowercase for compatibility
    if (window.WhimsicalFrog && WhimsicalFrog.registerModule) {
        WhimsicalFrog.registerModule('room-modal-manager', roomModalManager);
    }
    console.log('[App] RoomModalManager created and exposed globally:', roomModalManager);
    // Prewarm disabled to avoid saturating single-threaded dev server and inflating TTFB
    
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
    
    // Initialize tooltip manager on admin pages
    if (__WF_IS_ADMIN) {
        console.log('[App] Initializing tooltip manager for admin page...');
        initializeTooltipManager();
    }
    
    // Mark systems as initialized
    window.WF_SYSTEMS_INITIALIZED = true;
    console.log('[App] All core systems initialized successfully');

    // Removed API warm-up ping to avoid extra network noise on first load
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
                // Main room page loader (fullscreen doors & background)
                'room_main': () => import('./room-main.js'),
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
            // Admin health checks (toasts for missing backgrounds/item images)
            try { import('./admin-health-checks.js').catch(() => {}); } catch (_) {}
            // Enable dynamic admin tooltips (DB-driven)
            try { import('../modules/tooltip-manager.js').catch(() => {}); } catch (_) {}
            // Curate tooltip copy (helpful + snarky, unique) and upsert via API
            try { import('../modules/tooltip-curator.js').catch(() => {}); } catch (_) {}
            // Ensure notification system exists for toasts on admin
            try { import('./global-notifications.js').catch(() => {}); } catch (_) {}
            // Load admin-specific notification system for persistent toasts with actions
            try { import('./admin-notifications.js').catch(() => {}); } catch (_) {}

            // Optional hint handling (arrivals from Health modal or elsewhere)
            try {
              const params = new URLSearchParams(window.location.search || '');
              const hint = params.get('wf_hint');
              if (hint === 'background') {
                const room = params.get('room');
                const msg = room ? `Configure a background for room ${room}.` : 'Configure a background for the target room.';
                if (typeof window.showNotification === 'function') {
                  window.showNotification(msg, 'info', { title: 'Background Configuration' });
                }
                // If a #background section exists, scroll into view
                try {
                  const hashEl = document.getElementById('background') || document.querySelector('[data-section="background"], [id*="background"]');
                  if (hashEl && typeof hashEl.scrollIntoView === 'function') {
                    setTimeout(() => { try { hashEl.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch(_) {} }, 100);
                  }
                } catch (_) {}

                // Inject a dismissible banner near the background section
                try {
                  const marker = document.getElementById('background') || document.querySelector('[data-section="background"]');
                  const r = room ? String(room) : '';
                  const dismissKey = r ? `wf_bg_hint_dismissed_${r}` : 'wf_bg_hint_dismissed';
                  try {
                    const dismissedSession = (typeof sessionStorage !== 'undefined') && sessionStorage.getItem(dismissKey) === '1';
                    const dismissedPersistent = (typeof localStorage !== 'undefined') && localStorage.getItem(dismissKey) === '1';
                    if (dismissedSession || dismissedPersistent) {
                      // User already dismissed banner; persist across sessions
                      return;
                    }
                  } catch (_) { /* continue */ }
                  if (marker && !document.getElementById('wfBackgroundHintBanner')) {
                    const encRoom = encodeURIComponent(r);
                    const banner = document.createElement('div');
                    banner.id = 'wfBackgroundHintBanner';
                    banner.className = 'wf-admin-hint-banner';
                    banner.innerHTML = `
                      <div class="wf-banner-icon">ℹ️</div>
                      <div class="wf-banner-content">
                        <div class="wf-banner-title">Background Configuration</div>
                        <div class="wf-banner-text">${room ? `You're setting up a background for room <strong>${r}</strong>.` : 'Select or configure a background for the desired room.'}</div>
                        <div class="wf-banner-actions mt-2">
                          <a href="${buildAdminUrl('dashboard', { wf_hint: 'background', room: r }) }#background" class="btn btn-secondary">Open Background Manager</a>
                          <a href="/sections/tools/room_config_manager.php${r ? `?room=${encRoom}` : ''}" class="btn">Room Settings</a>
                          <a href="/api/admin_file_proxy.php?path=documentation/technical/CUSTOMIZATION_GUIDE.md" class="btn" target="_blank" rel="noopener">Learn more</a>
                        </div>
                      </div>
                      <button type="button" class="wf-banner-close" aria-label="Dismiss" title="Dismiss">×</button>
                    `;
                    const closer = () => {
                      try { if (sessionStorage) sessionStorage.setItem(dismissKey, '1'); } catch(_) {}
                      try { if (localStorage) localStorage.setItem(dismissKey, '1'); } catch(_) {}
                      try { banner.remove(); } catch(_) { banner.classList.add('hidden'); }
                    };
                    banner.addEventListener('click', (ev) => { const x = ev.target.closest && ev.target.closest('.wf-banner-close'); if (x) { ev.preventDefault(); closer(); } });
                    marker.parentNode.insertBefore(banner, marker.nextSibling);
                  }
                } catch (_) {}
              }
            } catch (_) {}

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

            // If body dataset declares an admin settings page, honor it regardless of URL path
            try {
              const pageToken = (typeof page === 'string' ? page.toLowerCase() : '');
              if (!section && pageToken && pageToken.indexOf('admin/settings') === 0) {
                section = 'settings';
              }
              // Also accept 'admin-settings' slug or 'admin_settings'
              if (!section && pageToken && (pageToken === 'admin-settings' || pageToken === 'admin_settings')) {
                section = 'settings';
              }
            } catch (_) {}

            // If navigating via admin router e.g. /admin/?section=settings, prefer the query param
            try {
                const params = new URLSearchParams(window.location.search || '');
                const qSection = (params.get('section') || '').toLowerCase();
                if (qSection) {
                    section = aliases[qSection] || qSection;
                }
            } catch (_) {}

            // Fallback: derive section from body dataset.page (e.g., 'admin/orders') if still empty
            if (!section) {
                try {
                    const pageToken = (ds && typeof ds.page === 'string') ? ds.page : '';
                    if (pageToken && pageToken.indexOf('admin/') === 0) {
                        let seg = pageToken.split('/')[1] || '';
                        if (seg) {
                          // Normalize like other paths: strip .php
                          seg = seg.replace(/\.php$/i, '');
                          // Map common legacy names to canonical sections
                          const fallbackAliases = {
                            'admin_orders': 'orders',
                            'orders': 'orders',
                            'admin_customers': 'customers',
                            'admin_inventory': 'inventory',
                          };
                          section = aliases[seg] || fallbackAliases[seg] || seg;
                        }
                    }
                } catch (_) {}
            }

            // Minimal diagnostic log
            try { console.log('[App] Admin section resolved:', section); } catch(_) {}

            // Dynamic import per admin section
            const loaders = {
                'dashboard': () => import('./admin-dashboard.js'),
                'customers': () => import('./admin-customers.js'),
                'inventory': () => import('./admin-inventory.js'),
                'orders': () => import('./admin-orders.js'),
                'pos': () => import('./pos.js'),
                'reports': () => import('./admin-reports.js'),
                'marketing': () => import('./admin-marketing.js'),
                // Use the lightweight admin-settings entry that loads the bridge first
                // and only lazy-loads the heavy legacy module on demand
                'settings': () => import('../entries/admin-settings.js'),
                'categories': () => import('./admin-categories.js'),
                'db-status': () => import('../modules/db-status-coordinator.js').then(m => { new m.DbStatusCoordinator().init(); }),
                'db-web-manager': () => import('./admin-db-web-manager.js'),
                'room-config-manager': () => import('./admin-room-config-manager.js'),
                'cost-breakdown-manager': () => import('../modules/cost-breakdown-coordinator.js').then(m => { new m.CostBreakdownCoordinator().init(); }),
                'secrets': () => import('./admin-secrets.js'),
                // New managers
                'room-map-manager': () => import('./admin-room-map-manager.js'),
                'area-item-mapper': () => import('./admin-area-item-mapper.js'),
                'room-map-editor': () => import('./admin-room-map-editor.js'),
            };

            // Load nested editor enhancer when on Inventory
            if (section === 'inventory') { try { import('./inventory-nested-enhancer.js').catch(() => {}); } catch (_) {} }

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
                    const ensureCssCatalogModal = () => {
                        let el = document.getElementById('cssCatalogModal');
                        if (el) return el;
                        el = document.createElement('div');
                        el.id = 'cssCatalogModal';
                        el.className = 'admin-modal-overlay hidden';
                        el.setAttribute('aria-hidden','true');
                        el.setAttribute('role','dialog');
                        el.setAttribute('aria-modal','true');
                        el.setAttribute('tabindex','-1');
                        el.setAttribute('aria-labelledby','cssCatalogTitle');
                        el.innerHTML = `
                          <div class="admin-modal admin-modal-content w-[80vw] h-[80vh]">
                            <div class="modal-header">
                              <h2 id="cssCatalogTitle" class="admin-card-title">🎨 CSS Catalog</h2>
                              <button type="button" class="admin-modal-close" data-action="close-admin-modal" aria-label="Close">×</button>
                            </div>
                            <div class="modal-body">
                              <iframe id="cssCatalogFrame" title="CSS Catalog" class="wf-admin-embed-frame wf-admin-embed-frame--tall" data-src="/sections/tools/css_catalog.php?modal=1" referrerpolicy="no-referrer"></iframe>
                            </div>
                          </div>`;
                        try { document.body.appendChild(el); } catch(_) {}
                        return el;
                    };
                    document.addEventListener('click', (e) => {
                        try {
                            const t = e.target; const closest = (sel) => (t && t.closest ? t.closest(sel) : null);
                            if (!document.getElementById('adminSettingsRoot')) return; // only act if settings DOM exists
                            if (t && t.classList && t.classList.contains('admin-modal-overlay')) { const id = t.id; if (!id) return; e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickHide(id); return; }
                            if (closest('[data-action="close-admin-modal"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); const overlay = closest('.admin-modal-overlay'); if (overlay && overlay.id) quickHide(overlay.id); return; }
                            if (closest('[data-action="open-business-info"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                if (window.__WF_ADMIN_SETTINGS_BRIDGE_INIT) return; // Let the bridge handle it if loaded

                                quickShow('businessInfoModal');
                                try {
                                    ApiClient.get('/api/business_settings.php?action=get_business_info')
                                        .then(j => {
                                            if (!j || !j.success) return;
                                            const s = j.data || {};
                                            const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v ?? ''; };

                                            set('bizName', s.business_name);
                                            set('bizEmail', s.business_email);
                                            set('bizPhone', s.business_phone);
                                            set('bizHours', s.business_hours);
                                            set('bizAddress', s.business_address);
                                            set('bizAddress2', s.business_address2);
                                            set('bizCity', s.business_city);
                                            set('bizState', s.business_state);
                                            set('bizPostal', s.business_postal);
                                            set('bizCountry', s.business_country);
                                            set('bizWebsite', s.business_website);
                                            set('bizLogoUrl', s.business_logo_url);
                                            set('bizTagline', s.business_tagline);
                                            set('bizDescription', s.business_description);
                                            set('bizSupportEmail', s.business_support_email);
                                            set('bizSupportPhone', s.business_support_phone);
                                            set('bizFacebook', s.business_facebook);
                                            set('bizInstagram', s.business_instagram);
                                            set('bizTwitter', s.business_twitter);
                                            set('bizTikTok', s.business_tiktok);
                                            set('bizYouTube', s.business_youtube);
                                            set('bizLinkedIn', s.business_linkedin);
                                            set('bizTermsUrl', s.business_terms_url);
                                            set('bizPrivacyUrl', s.business_privacy_url);
                                            set('bizTaxId', s.business_tax_id);
                                            set('bizTimezone', s.business_timezone);
                                            set('bizCurrency', s.business_currency);
                                            set('bizLocale', s.business_locale);
                                            set('brandPrimary', s.business_brand_primary || '#0ea5e9');
                                            set('brandSecondary', s.business_brand_secondary || '#6366f1');
                                            set('brandAccent', s.business_brand_accent || '#22c55e');
                                            set('brandBackground', s.business_brand_background || '#ffffff');
                                            set('brandText', s.business_brand_text || '#111827');
                                            set('brandFontPrimary', s.business_brand_font_primary);
                                            set('brandFontSecondary', s.business_brand_font_secondary);
                                            set('footerNote', s.business_footer_note);
                                            set('footerHtml', s.business_footer_html);
                                            set('returnPolicy', s.business_policy_return);
                                            set('shippingPolicy', s.business_policy_shipping);
                                            set('warrantyPolicy', s.business_policy_warranty);
                                            set('policyUrl', s.business_policy_url);
                                        });
                                } catch (err) { console.error('Mini-handler failed to fetch business info:', err); }
                                return;
                            }
                            if (closest('[data-action="business-save-branding"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                const get = (id) => { const el = document.getElementById(id); return el ? el.value : ''; };
                                const payload = {
                                    settings: {
                                        business_brand_primary: get('brandPrimary'),
                                        business_brand_secondary: get('brandSecondary'),
                                        business_brand_accent: get('brandAccent'),
                                        business_brand_background: get('brandBackground'),
                                        business_brand_text: get('brandText'),
                                    },
                                    category: 'branding'
                                };
                                ApiClient.post('/api/business_settings.php', { action: 'upsert_settings', ...payload })
                                  .then(res => {
                                    if (typeof window.showNotification === 'function') {
                                        if (res.success) {
                                            window.showNotification('Branding colors saved!', 'success');
                                        } else {
                                            window.showNotification(`Save failed: ${res.message || 'Unknown error'}`, 'error');
                                        }
                                    }
                                  });
                                return;
                            }
                            if (closest('[data-action="business-reset-branding"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v; };
                                set('brandPrimary', '#0ea5e9');
                                set('brandSecondary', '#6366f1');
                                set('brandAccent', '#22c55e');
                                set('brandBackground', '#ffffff');
                                set('brandText', '#111827');
                                if (typeof window.showNotification === 'function') {
                                    window.showNotification('Branding colors reset to defaults.', 'info');
                                }
                                return;
                            }
                            if (closest('[data-action="open-square-settings"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('squareSettingsModal'); return; }
                            if (closest('[data-action="open-email-settings"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('emailSettingsModal'); return; }
                            if (closest('[data-action="open-email-test"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); if (quickShow('emailSettingsModal')) { const test = document.getElementById('testEmailAddress')||document.getElementById('testRecipient'); if (test) setTimeout(()=>test.focus(), 50); } return; }
                            if (closest('[data-action="open-logging-status"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('loggingStatusModal'); return; }
                            if (closest('[data-action="open-ai-settings"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('aiSettingsModal'); return; }
                            if (closest('[data-action="open-ai-tools"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('aiToolsModal'); return; }
                            if (closest('[data-action="open-db-schema-audit"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                if (quickShow('dbSchemaAuditModal')) {
                                    const iframe = document.getElementById('dbSchemaAuditFrame');
                                    if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
                                        iframe.src = iframe.dataset.src;
                                    }
                                }
                                return;
                            }
                            if (closest('[data-action="open-css-catalog"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                ensureCssCatalogModal();
                                if (quickShow('cssCatalogModal')) {
                                    const iframe = document.getElementById('cssCatalogFrame');
                                    if (iframe && iframe.dataset && iframe.dataset.src && (!iframe.src || iframe.src === 'about:blank')) {
                                        iframe.src = iframe.dataset.src;
                                    }
                                }
                                return;
                            }
                            if (closest('[data-action="open-css-rules"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('cssRulesModal'); return; }
                            if (closest('[data-action="open-background-manager"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('backgroundManagerModal'); return; }
                            if (closest('[data-action="open-receipt-settings"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('receiptSettingsModal'); return; }
                            if (closest('[data-action="open-dashboard-config"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('dashboardConfigModal'); return; }
                            if (closest('[data-action="open-secrets-modal"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); quickShow('secretsModal'); return; }

                            // Open Health & Diagnostics modal
                            if (closest('[data-action="open-health-diagnostics"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                quickShow('healthModal');
                                // Trigger initial refresh
                                try {
                                    const btn = document.querySelector('[data-action="health-refresh"]');
                                    if (btn) btn.click();
                                } catch(_) {}
                                return;
                            }

                            // Health: Refresh backgrounds/items via admin APIs
                            if (closest('[data-action="health-refresh"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = String(v); };
                                const fillList = (id, arr) => {
                                    const ul = document.getElementById(id); if (!ul) return;
                                    ul.innerHTML = '';
                                    (arr || []).forEach(v => { const li = document.createElement('li'); li.textContent = String(v); ul.appendChild(li); });
                                };
                                // Backgrounds
                                ApiClient.get('/api/health_backgrounds.php')
                                   .then(j => {
                                       if (!j || j.success !== true || !j.data) return;
                                       const d = j.data;
                                       setText('bgMissingActiveCount', (d.missingActive||[]).length);
                                       fillList('bgMissingActiveList', d.missingActive||[]);
                                       setText('bgMissingFilesCount', (d.missingFiles||[]).length);
                                       fillList('bgMissingFilesList', d.missingFiles||[]);
                                   }).catch(()=>{});
                                // Items
                                ApiClient.get('/api/health_items.php')
                                   .then(j => {
                                       if (!j || j.success !== true || !j.data) return;
                                       const d = j.data;
                                       const counts = d.counts || {};
                                       setText('itemsNoPrimaryCount', counts.noPrimary || 0);
                                       setText('itemsMissingFilesCount', counts.missingFiles || 0);
                                       // Optional: show up to 20 examples in lists
                                       const listFrom = (rows, labelKeyA, labelKeyB) => (rows||[]).slice(0,20).map(row => {
                                           const a = row[labelKeyA] || row.sku || row.id || '';
                                           const b = row[labelKeyB] || row.name || '';
                                           return b ? `${a} – ${b}` : a;
                                       });
                                       fillList('itemsNoPrimaryList', listFrom(d.noPrimary, 'sku', 'name'));
                                       fillList('itemsMissingFilesList', listFrom(d.missingFiles, 'sku', 'name'));
                                   }).catch(()=>{});
                                const hs = document.getElementById('healthStatus'); if (hs) hs.textContent = 'Refreshed at ' + new Date().toLocaleTimeString();
                                return;
                            }

                            // Open Dev Status Dashboard modal
                            if (closest('[data-action="open-dev-status"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                quickShow('devStatusModal');
                                try {
                                    const frame = document.getElementById('devStatusFrame');
                                    if (frame && frame.dataset && frame.dataset.src && (!frame.src || frame.src === 'about:blank')) {
                                        frame.src = frame.dataset.src;
                                    }
                                } catch(_) {}
                                return;
                            }

                            // Run /health.php and display output
                            if (closest('[data-action="run-health-check"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                const out = document.getElementById('advancedHealthOutput'); if (out) out.textContent = 'Running /health.php...';
                                ApiClient.get('/health.php')
                                  .then(t => { const out = document.getElementById('advancedHealthOutput'); if (out) out.textContent = t; })
                                  .catch(err => { const out = document.getElementById('advancedHealthOutput'); if (out) out.textContent = String(err); });
                                return;
                            }

                            // Scan item images (dev scanner) and display JSON
                            if (closest('[data-action="scan-item-images"]')) {
                                e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation();
                                const out = document.getElementById('advancedHealthOutput'); if (out) out.textContent = 'Scanning item images...';
                                ApiClient.get('/api/dev_scan_images.php')
                                  .then(res => {
                                     const out = document.getElementById('advancedHealthOutput');
                                     if (!out) return;
                                     out.textContent = (typeof res === 'string') ? res : JSON.stringify(res, null, 2);
                                  })
                                  .catch(err => { const out = document.getElementById('advancedHealthOutput'); if (out) out.textContent = String(err); });
                                return;
                            }
                            if (closest('[data-action="open-attributes"]')) { e.preventDefault(); if (e.stopImmediatePropagation) e.stopImmediatePropagation(); else e.stopPropagation(); if (quickShow('attributesModal')) { try { if (typeof window.initAttributesModal === 'function') window.initAttributesModal(document.getElementById('attributesModal')); } catch(_) {} } return; }
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
