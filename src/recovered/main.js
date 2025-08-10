// Import the consolidated stylesheet
import '../css/main.css';
import './global-popup.js';
import './room-helper.js';

// Mobile menu toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileMenu = document.querySelector('.mobile-menu');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            const isExpanded = mobileMenuToggle.getAttribute('aria-expanded') === 'true';
            
            mobileMenuToggle.setAttribute('aria-expanded', !isExpanded);
            mobileMenu.classList.toggle('active');
            
            // Update icon
            const icon = mobileMenuToggle.querySelector('svg path');
            if (icon) {
                if (mobileMenu.classList.contains('active')) {
                    // Change to close icon (e.g., X)
                    icon.setAttribute('d', 'M6 18L18 6M6 6l12 12'); 
                } else {
                    // Change back to menu icon (e.g., hamburger)
                    icon.setAttribute('d', 'M4 6h16M4 12h16M4 18h16'); 
                }
            }
        });

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!mobileMenuToggle.contains(event.target) && !mobileMenu.contains(event.target)) {
                if (mobileMenu.classList.contains('active')) {
                    mobileMenuToggle.setAttribute('aria-expanded', 'false');
                    mobileMenu.classList.remove('active');
                    
                    const icon = mobileMenuToggle.querySelector('svg path');
                    if (icon) {
                        icon.setAttribute('d', 'M4 6h16M4 12h16M4 18h16'); // Hamburger
                    }
                }
            }
        });
    }
});

/**
 * WhimsicalFrog Core System
 * Provides module management and initialization
 */

console.log('Loading WhimsicalFrog Core...');

// Core system
const WhimsicalFrog = {
    modules: new Map(),
    readyCallbacks: [],
    initialized: false,

    // Register a module
    registerModule: function(name, module) {
        console.log(`📦 Registering module: ${name}`);
        this.modules.set(name, module);
        return this;
    },

    // Add a module (alias for registerModule)
    addModule: function(name, module) {
        return this.registerModule(name, module);
    },

    // Get a module
    getModule: function(name) {
        return this.modules.get(name);
    },

    // Execute callback when ready
    ready: function(callback) {
        if (this.initialized) {
            callback(this);
        } else {
            this.readyCallbacks.push(callback);
        }
        return this;
    },

    // Initialize the system
    init: function() {
        if (this.initialized) return this;

        console.log('🐸 Initializing WhimsicalFrog Core...');
        this.initialized = true;

        // Execute ready callbacks
        this.readyCallbacks.forEach(callback => {
            try {
                callback(this);
            } catch (error) {
                console.error('Error in ready callback:', error);
            }
        });

        console.log('🐸 WhimsicalFrog Core initialized');
        return this;
    }
};

// Expose globally
window.WhimsicalFrog = WhimsicalFrog;

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => WhimsicalFrog.init());
} else {
    WhimsicalFrog.init();
}

console.log('WhimsicalFrog Core loaded');

// --- Main Room Module ---
const mainRoomModule = {
    name: 'MainRoom',
    init: function() {
        // Only run if the MainRoomData global object exists
        if (typeof window.MainRoomData === 'undefined') {
            return; // Not on the main room page
        }

        console.log('🚪 Initializing Main Room module...');
        this.setupUI();
        this.bindEvents();
        console.log('🚪 Main Room module initialized successfully.');
    },

    setupUI: function() {
        // Add fullscreen logout link if needed
        if (window.MainRoomData.isFullScreen) {
            console.log('Fullscreen mode detected. Adding logout link.');
            const logoutLink = document.createElement('a');
            logoutLink.innerHTML = 'Logout';
            logoutLink.href = '/logout.php';
            // Style this class in main.bundle.css
            logoutLink.className = 'logout-fullscreen-link';
            document.body.appendChild(logoutLink);
        }
    },

    bindEvents: function() {
        const doorAreas = document.querySelectorAll('.door-area');
        if (doorAreas.length > 0) {
            console.log(`Binding click events to ${doorAreas.length} doors.`);
            doorAreas.forEach(door => {
                // Use a defined function for the listener to allow for potential removal
                door.addEventListener('click', this.handleDoorClick.bind(this));
            });
        }
    },

    handleDoorClick: function(event) {
        const door = event.currentTarget;
        const url = door.dataset.url;

        if (url) {
            console.log(`Navigating to: ${url}`);
            window.location.href = url;
        } else {
            console.error('Could not find a data-url attribute on the clicked door.', door);
        }
    }
};

// Register and initialize the module when the core system is ready
WhimsicalFrog.ready(function() {
    WhimsicalFrog.registerModule('mainRoom', mainRoomModule);
    // Initialize the module immediately after registering
    mainRoomModule.init();
});

