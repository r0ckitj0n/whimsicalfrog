/**
 * WhimsicalFrog Main Application Module
 * Initializes core UI components and functionality.
 */
import WF from './whimsical-frog-core.js';

const MainApplication = {
    init() {
        this.WF = WF; // Attach core framework for access
        this.ensureSingleNavigation();
        this.updateMainCartCounter();
        this.setupEventListeners();
        this.handleLoginForm();
        this.WF.log('Main Application module initialized.');
    },

    ensureSingleNavigation() {
        const navs = document.querySelectorAll('nav.main-nav');
        if (navs.length > 1) {
            this.WF.log(`Found ${navs.length} navigation elements, removing duplicates...`);
            navs.forEach((el, idx) => { if (idx > 0) el.remove(); });
        }
    },

    updateMainCartCounter() {
        const el = document.getElementById('cartCount');
        // Note: This relies on a global `window.cart` object, which should be refactored.
        if (window.cart && el) {
            el.textContent = `${window.cart.getCount()} items`;
        }
    },

    setupEventListeners() {
        // Note: This uses a custom eventBus on the core object.
        if (this.WF.eventBus) {
            this.WF.eventBus.on('cartUpdated', () => this.updateMainCartCounter());
        }
        // Initialize counter once the framework is ready
        this.WF.ready(() => this.updateMainCartCounter());
    },

    handleLoginForm() {
        // If the dedicated login modal script is present, it already hooks the inline login form
        // and shows branded notifications. Avoid double-binding to prevent duplicate submissions
        // and JSON parsing conflicts.
        if (window.openLoginModal) {
            this.WF.log('Login modal detected; skipping duplicate inline login handler.');
            return;
        }

        const form = document.getElementById('loginForm');
        if (!form) return;

        // Guard against multiple attachments
        if (form.dataset.wfLoginHandler === 'true') return;
        form.dataset.wfLoginHandler = 'true';

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = form.querySelector('#username').value;
            const password = form.querySelector('#password').value;
            const errorMessage = form.querySelector('#errorMessage');

            if (errorMessage) errorMessage.classList.add('hidden');

            try {
                // Use direct fetch with safe JSON handling to avoid parse errors on empty bodies
                const res = await fetch('/functions/process_login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                if (!res.ok) {
                    const err = await this.safeJson(res);
                    throw new Error(err?.error || 'Login failed.');
                }
                const data = await this.safeJsonOk(res);
                sessionStorage.setItem('user', JSON.stringify((data && (data.user || data)) || {}));

                // Determine redirect target
                let target;
                if (data && data.redirectUrl) {
                    target = data.redirectUrl;
                } else if (localStorage.getItem('pendingCheckout') === 'true') {
                    localStorage.removeItem('pendingCheckout');
                    target = '/cart';
                } else {
                    target = '/dashboard';
                }

                // Branded success popup and brief delay for visibility
                if (window.showSuccess) window.showSuccess('Login successful. Redirecting…');
                setTimeout(() => { window.location.href = target; }, 700);
            } catch (err) {
                if (errorMessage) {
                    errorMessage.textContent = err.message || 'Invalid username or password.';
                    errorMessage.classList.remove('hidden');
                }
                console.error('Login failed:', err);
            }
        });
    },

    // Helpers: tolerant JSON parsing
    async safeJson(res) {
        try { return await res.json(); } catch (_) { return null; }
    },
    async safeJsonOk(res) {
        try {
            const ct = (res.headers && res.headers.get && res.headers.get('content-type')) || '';
            const text = await res.text();
            const trimmed = (text || '').trim();
            if (!ct.includes('application/json')) return {};
            if (!trimmed) return {};
            try { return JSON.parse(trimmed); } catch (_) { return {}; }
        } catch (_) {
            return {};
        }
    },

    async loadModalBackground(roomType) {
        if (!roomType) {
            this.WF.log('[MainApplication] No room provided for modal background.', 'warn');
            return;
        }

        try {
            // roomType may be 'roomN' or N; normalize to number for new API param
            const rn = String(roomType).match(/^room(\d+)$/i) ? String(roomType).replace(/^room/i, '') : String(roomType);
            const data = await this.WF.api.get(`/api/get_background.php?room=${encodeURIComponent(rn)}`);
            if (data && data.success && data.background) {
                const { webp_path, png_path } = data.background;
                const supportsWebP = document.documentElement.classList.contains('webp');
                let filename = supportsWebP ? webp_path : png_path;

                if (!filename.startsWith('backgrounds/')) {
                    filename = `backgrounds/${filename}`;
                }

                const imageUrl = `/images/${filename}?v=${Date.now()}`;

                // Runtime-injected background class helper (scoped to this module)
                const STYLE_ID = 'wf-modal-dynbg-classes';
                function getStyleEl(){
                    let el = document.getElementById(STYLE_ID);
                    if (!el){ el = document.createElement('style'); el.id = STYLE_ID; document.head.appendChild(el); }
                    return el;
                }
                const map = (window.__wfModalBgClassMap ||= new Map());
                function ensureBgClass(url){
                    if (!url) return null;
                    if (map.has(url)) return map.get(url);
                    const idx = map.size + 1;
                    const cls = `modalbg-${idx}`;
                    getStyleEl().appendChild(document.createTextNode(`.room-overlay-wrapper.${cls}, .room-modal-body.${cls}{--dynamic-bg-url:url('${url}');background-image:url('${url}');}`));
                    map.set(url, cls);
                    return cls;
                }

                // Pick the correct container inside the overlay for the background
                const overlay = document.querySelector('.room-modal-overlay');
                const container = overlay && (overlay.querySelector('.room-overlay-wrapper') || overlay.querySelector('.room-modal-body'));
                if (container) {
                    const bgCls = ensureBgClass(imageUrl);
                    if (container.dataset.bgClass && container.dataset.bgClass !== bgCls) {
                        container.classList.remove(container.dataset.bgClass);
                    }
                    if (bgCls) {
                        container.classList.add(bgCls);
                        container.dataset.bgClass = bgCls;
                    }
                } else {
                    this.WF.log('[MainApplication] Modal background container not found.', 'warn');
                }
            } else {
                this.WF.log(`[MainApplication] No background found for roomType: ${roomType}`, 'info');
            }
        } catch (error) {
            this.WF.log(`[MainApplication] Error loading modal background: ${error}`, 'error');
        }
    }
};

// Initialize the main application logic once the core framework is ready.
WF.ready(() => MainApplication.init());

export default MainApplication;