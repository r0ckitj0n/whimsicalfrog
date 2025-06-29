/**
 * Main Room JavaScript - Centralized functionality for main room door interactions
 * Extracted from inline scripts to improve maintainability
 */

// Main room configuration
const MainRoomConfig = {
    originalImageWidth: 1280,
    originalImageHeight: 896,
    doorCoordinates: [
        { selector: '.area-1', top: 243, left: 30, width: 234, height: 233 }, // T-Shirts
        { selector: '.area-2', top: 403, left: 390, width: 202, height: 241 }, // Tumblers
        { selector: '.area-3', top: 271, left: 753, width: 170, height: 235 }, // Artwork
        { selector: '.area-4', top: 291, left: 1001, width: 197, height: 255 }, // Window Wraps
        { selector: '.area-5', top: 157, left: 486, width: 190, height: 230 } // Sublimation
    ],
    isInitialized: false
};

/**
 * Enter a specific room
 */
function enterRoom(roomNumber) {
    console.log('Entering room:', roomNumber);
    
    // Add loading state to the clicked door
    const doorElement = document.querySelector(`[data-room="${roomNumber}"]`);
    if (doorElement) {
        doorElement.classList.add('loading');
    }
    
    // Navigate to room
    window.location.href = `/?page=room${roomNumber}&from=main`;
}

/**
 * Position doors based on viewport and coordinates
 */
function positionDoors() {
    if (!MainRoomConfig.doorCoordinates) return;
    
    // Get viewport dimensions
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    
    // Calculate scale factors
    const viewportRatio = viewportWidth / viewportHeight;
    const imageRatio = MainRoomConfig.originalImageWidth / MainRoomConfig.originalImageHeight;
    
    let scale, offsetX, offsetY;
    
    // Calculate how the background image is displayed (cover)
    if (viewportRatio > imageRatio) {
        // Viewport is wider than image ratio, image width matches viewport width
        scale = viewportWidth / MainRoomConfig.originalImageWidth;
        offsetY = (viewportHeight - (MainRoomConfig.originalImageHeight * scale)) / 2;
        offsetX = 0;
    } else {
        // Viewport is taller than image ratio, image height matches viewport height
        scale = viewportHeight / MainRoomConfig.originalImageHeight;
        offsetX = (viewportWidth - (MainRoomConfig.originalImageWidth * scale)) / 2;
        offsetY = 0;
    }
    
    console.log('Main Room - Viewport:', viewportWidth, 'x', viewportHeight);
    console.log('Main Room - Scale:', scale, 'Offsets:', offsetX, offsetY);
    
    // Position each door
    MainRoomConfig.doorCoordinates.forEach(door => {
        const element = document.querySelector(door.selector);
        if (element) {
            // Apply scaled coordinates
            element.style.top = `${(door.top * scale) + offsetY}px`;
            element.style.left = `${(door.left * scale) + offsetX}px`;
            element.style.width = `${door.width * scale}px`;
            element.style.height = `${door.height * scale}px`;
            
            console.log(`Positioned ${door.selector}:`, element.style.top, element.style.left);
        }
    });
}

/**
 * Initialize main room functionality
 */
function initializeMainRoom() {
    if (MainRoomConfig.isInitialized) return;
    
    console.log('Initializing main room...');
    
    // Position doors
    positionDoors();
    
    // Add event listeners to doors for enhanced interaction
    document.querySelectorAll('.door-area').forEach(door => {
        // Add hover analytics or other enhanced functionality if needed
        door.addEventListener('mouseenter', function() {
            const category = this.dataset.category;
            console.log(`Hovering over ${category} door`);
        });
        
        door.addEventListener('click', function(e) {
            // Prevent double-clicks
            if (this.classList.contains('loading')) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Handle window resize with debouncing
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(() => {
            console.log('Main room - Window resized, repositioning doors');
            positionDoors();
        }, 250);
    });
    
    MainRoomConfig.isInitialized = true;
    console.log('Main room initialized successfully');
}

/**
 * Load door coordinates from database (future enhancement)
 */
async function loadDoorCoordinatesFromDatabase() {
    try {
        const response = await fetch('/api/get_room_coordinates.php?room_type=main');
        const data = await response.json();
        
        if (data.success && data.coordinates && data.coordinates.length > 0) {
            MainRoomConfig.doorCoordinates = data.coordinates;
            console.log('Main room coordinates loaded from database');
            return true;
        }
    } catch (error) {
        console.warn('Failed to load main room coordinates from database:', error);
    }
    return false;
}

/**
 * Enhanced door interaction feedback
 */
function addDoorFeedback() {
    document.querySelectorAll('.door-area').forEach(door => {
        // Add touch feedback for mobile
        door.addEventListener('touchstart', function() {
            this.style.transform = 'scale(1.02)';
        });
        
        door.addEventListener('touchend', function() {
            this.style.transform = '';
        });
        
        // Add keyboard accessibility
        door.setAttribute('tabindex', '0');
        door.setAttribute('role', 'button');
        
        door.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                const roomNumber = this.dataset.room;
                if (roomNumber) {
                    enterRoom(parseInt(roomNumber));
                }
            }
        });
    });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Load coordinates from database first, fallback to hardcoded
    loadDoorCoordinatesFromDatabase().then(() => {
        initializeMainRoom();
        addDoorFeedback();
    });
});

// Export for global access
window.MainRoom = {
    enterRoom,
    positionDoors,
    initializeMainRoom,
    config: MainRoomConfig
}; 