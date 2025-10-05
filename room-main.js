// Import CSS with error handling (will fail gracefully in dev mode)
import('../styles/room-main.css').catch(cssError => {
    console.warn('[RoomMain] CSS import failed, continuing without styles:', cssError);
}).then(() => {
    console.log('[RoomMain] CSS loaded or skipped gracefully');
});

console.log('Loading room-main.js...');

// Runtime injected styles for room main
const RM_STYLE_ID = 'wf-room-main-runtime';
function getRmStyleEl(){
    let el = document.getElementById(RM_STYLE_ID);
    if (!el){ el = document.createElement('style'); el.id = RM_STYLE_ID; document.head.appendChild(el); }
    return el;
}
const bgCache = new Map(); // url -> cls
function ensureRmBgClass(url){
    if (!url) return null;
    if (bgCache.has(url)) return bgCache.get(url);
    const idx = bgCache.size + 1;
    const cls = `rm-bg-${idx}`;
    getRmStyleEl().appendChild(document.createTextNode(`#mainRoomPage.${cls}{--room-bg-url:url('${url}');background-image:url('${url}');}`));
    bgCache.set(url, cls);
    return cls;
}
let _ensuredUtilityCss = false;
function ensureUtilityCss(){
    // No-op: utilities are provided via Vite CSS import (../styles/room-main.css)
    _ensuredUtilityCss = true;
}

class RoomMainManager {
    constructor() {
        this.initialized = false;
        this.init();
    }

    init() {
        if (this.initialized) return;

        console.log('🐸 Room Main: Initializing...');

        // Wait for DOM to be ready, then setup
        const setupWhenReady = () => {
            this.setup();
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupWhenReady);
        } else {
            setupWhenReady();
        }

        this.initialized = true;
    }

    setup() {
        console.log('🐸 Room Main: Setting up...');

        this.setupBackground();
        this.setupDoors();
        this.setupNavigation();
        this.setupBodyStyles();
        this.setupModalAutoOpen();
        this.setupCleanup();

        console.log('🐸 Room Main: Initialization complete');
    }

    setupBackground() {
        console.log('🐸 Room Main: Setting up background...');
        const mainRoomSection = document.getElementById('mainRoomPage');
        console.log('🐸 Room Main: mainRoomSection element:', mainRoomSection);
        if (mainRoomSection) {
            // Override the data-bg-url with the correct value
            const correctBgUrl = '/images/backgrounds/background-room-main.webp';
            mainRoomSection.setAttribute('data-bg-url', correctBgUrl);
            const bgUrl = mainRoomSection.getAttribute('data-bg-url');
            console.log('🐸 Room Main: bgUrl from element (corrected):', bgUrl);
            if (bgUrl) {
                const cls = ensureRmBgClass(bgUrl);
                if (mainRoomSection.dataset.bgClass && mainRoomSection.dataset.bgClass !== cls){
                    mainRoomSection.classList.remove(mainRoomSection.dataset.bgClass);
                }
                if (cls){
                    mainRoomSection.classList.add(cls);
                    mainRoomSection.dataset.bgClass = cls;
                }
                mainRoomSection.classList.add('room-bg-loaded');
                console.log('🖼️ Main room background loaded:', bgUrl);
            } else {
                console.log('🐸 Room Main: No bgUrl found on element');
            }
        } else {
            console.log('🐸 Room Main: No mainRoomSection element found');
        }
    }

    setupDoors() {
        const doorElements = document.querySelectorAll('.door-area');
        console.log(`🚪 Found ${doorElements.length} door elements`);

        // Helper: wait for RoomModalManager to exist
        const waitForModalManager = async (timeoutMs = 4000) => {
            const start = Date.now();
            while (!window.roomModalManager) {
                await new Promise(r => setTimeout(r, 50));
                if (Date.now() - start > timeoutMs) break;
            }
            return window.roomModalManager || null;
        };

        // Ensure doors are clickable and properly positioned
        doorElements.forEach((door, _index) => {
            ensureUtilityCss();
            door.classList.add('door-interactive');

            // Add accessibility
            door.setAttribute('tabindex', '0');
            door.setAttribute('role', 'button');
            door.setAttribute('aria-label', `Open ${door.dataset.category || 'room'}`);

            // Add keyboard support
            door.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    door.click();
                }
            });

            // Add explicit click handler to open the modal (bypasses delegated listener if needed)
            door.addEventListener('click', async (e) => {
                try {
                    e.preventDefault();
                    e.stopPropagation();
                    const rn = door.getAttribute('data-room') || door.getAttribute('data-room-number');
                    if (!rn) return;
                    const mgr = await waitForModalManager();
                    if (mgr && typeof mgr.openRoom === 'function') {
                        mgr.openRoom(rn);
                    } else if (window.WF_RoomModal && typeof window.WF_RoomModal.openRoom === 'function') {
                        window.WF_RoomModal.openRoom(rn);
                    } else if (window.RoomModalManager && typeof window.RoomModalManager === 'function') {
                        try { (window.__roomModalSingleton = window.__roomModalSingleton || new window.RoomModalManager()).openRoom(rn); } catch(_) {}
                    }
                } catch (_) { /* no-op */ }
            }, { capture: true });
        });

        console.log('🚪 Door positioning and accessibility configured');
    }

    setupNavigation() {
        const mainNav = document.querySelector('nav.main-nav');
        if (mainNav) {
            mainNav.classList.add('site-header');
            console.log('🧭 Navigation configured for room overlay');
        }
    }

    setupBodyStyles() {
        // Set body to prevent scrolling in fullscreen mode via class
        ensureUtilityCss();
        document.body.classList.add('wf-no-scroll');
        console.log('🚪 Body styles configured for fullscreen mode');
    }

    setupModalAutoOpen() {
        // Auto-open modal if URL specifies a room
        const urlParams = new URLSearchParams(window.location.search);
        const modalRoom = urlParams.get('modal_room');

        if (modalRoom) {
            // Wait for room modal manager to be available
            const checkModalManager = () => {
                if (window.roomModalManager) {
                    console.log(`🚪 Auto-opening modal for room: ${modalRoom}`);
                    window.roomModalManager.show(modalRoom);
                } else {
                    setTimeout(checkModalManager, 100);
                }
            };
            setTimeout(checkModalManager, 500);
        }
    }

    setupCleanup() {
        // Clean up when leaving main room
        window.addEventListener('beforeunload', () => {
            document.body.classList.remove('wf-no-scroll');
        });
    }
}

// Initialize room main manager when this script loads
const roomMainManager = new RoomMainManager();

// Expose globally for debugging
window.roomMainManager = roomMainManager;

// Register with WhimsicalFrog module system if available
try {
    if (window.WhimsicalFrog && typeof WhimsicalFrog.ready === 'function') {
        WhimsicalFrog.ready(wf => {
            wf.addModule('RoomMainManager', roomMainManager);
        });
    }
} catch (error) {
    console.warn('[RoomMain] Failed to register with WhimsicalFrog:', error);
}

console.log('Room Main Manager loaded and initialized');
