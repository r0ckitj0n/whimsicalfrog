/**
 * Room Main Page Initialization
 * Handles background loading, door positioning, and modal integration
 */

console.log('Loading room-main.js...');

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
                mainRoomSection.style.setProperty('--room-bg-url', `url('${bgUrl}')`);
                console.log('🖼️ Main room background loaded:', bgUrl);
            }
        }
    }

    setupDoors() {
        const doorElements = document.querySelectorAll('.door-area');
        console.log(`🚪 Found ${doorElements.length} door elements`);
        
        // Ensure doors are clickable and properly positioned
        doorElements.forEach((door, _index) => {
            door.style.cursor = 'pointer';
            door.style.pointerEvents = 'auto';
            
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
        // Set body to prevent scrolling in fullscreen mode
        document.body.style.overflow = 'hidden';
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
            document.body.style.overflow = '';
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
