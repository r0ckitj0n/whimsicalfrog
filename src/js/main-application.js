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
        const form = document.getElementById('loginForm');
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = form.querySelector('#username').value;
            const password = form.querySelector('#password').value;
            const errorMessage = form.querySelector('#errorMessage');

            if (errorMessage) errorMessage.classList.add('hidden');

            try {
                // Note: This relies on an api client on the core object.
                const data = await this.WF.api.post('/functions/process_login.php', { username, password });
                sessionStorage.setItem('user', JSON.stringify(data.user || data));

                if (data.redirectUrl) {
                    window.location.href = data.redirectUrl;
                } else if (localStorage.getItem('pendingCheckout') === 'true') {
                    localStorage.removeItem('pendingCheckout');
                    window.location.href = '/cart';
                } else {
                    window.location.href = '/dashboard';
                }
            } catch (err) {
                if (errorMessage) {
                    errorMessage.textContent = err.message || 'Invalid username or password.';
                    errorMessage.classList.remove('hidden');
                }
                console.error('Login failed:', err);
            }
        });
    },

    async loadModalBackground(roomType) {
        if (!roomType) {
            this.WF.log('[MainApplication] No roomType provided for modal background.', 'warn');
            return;
        }

        try {
            const data = await this.WF.api.get(`/api/get_background.php?room_type=${roomType}`);
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