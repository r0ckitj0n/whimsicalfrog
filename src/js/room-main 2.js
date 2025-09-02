/**
 * Room Main Page Initialization
 * Handles background loading, door positioning, and modal integration
 */

import '../styles/room-main.css';

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
        
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setup());
        } else {
            this.setup();
        }
        
        this.initialized = true;
    }

    setup() {
        this.setupBackground();
        this.setupDoors();
        this.setupNavigation();
        this.setupBodyStyles();
        this.setupModalAutoOpen();
        this.setupCleanup();
        
        console.log('🐸 Room Main: Initialization complete');
    }

    setupBackground() {
        const mainRoomSection = document.getElementById('mainRoomPage');
        if (mainRoomSection) {
            const bgUrl = mainRoomSection.getAttribute('data-bg-url');
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
            }
        }
    }

    setupDoors() {
        const doorElements = document.querySelectorAll('.door-area');
        console.log(`🚪 Found ${doorElements.length} door elements`);
        
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
if (window.WhimsicalFrog && typeof WhimsicalFrog.ready === 'function') {
    WhimsicalFrog.ready(wf => {
        wf.addModule('RoomMainManager', roomMainManager);
    });
}

console.log('Room Main Manager loaded and initialized');
