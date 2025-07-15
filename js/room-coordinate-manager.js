/**
 * WhimsicalFrog Room Coordinate and Area Management
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

console.log('Loading room-coordinate-manager.js...');

// Room coordinate management system
window.RoomCoordinates = window.RoomCoordinates || {};

// Initialize room coordinates system
function initializeRoomCoordinates() {
    // Only initialize if we have the necessary window variables
    if (!window.ROOM_TYPE || !window.originalImageWidth || !window.originalImageHeight) {
        console.warn('Room coordinate system not initialized - missing required variables');
        return;
    }
    
    // Set up DOM references
    window.roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
    
    if (!window.roomOverlayWrapper) {
        console.warn('Room overlay wrapper not found');
        return;
    }
    
    // Load coordinates from database
    loadRoomCoordinatesFromDatabase();
}

function updateAreaCoordinates() {
    if (!window.roomOverlayWrapper) {
        console.error('Room overlay wrapper not found for scaling.');
        return;
    }
    
    if (!window.baseAreas || !window.baseAreas.length) {
        console.log('No base areas to position');
        return;
    }

    const wrapperWidth = window.roomOverlayWrapper.offsetWidth;
    const wrapperHeight = window.roomOverlayWrapper.offsetHeight;

    const wrapperAspectRatio = wrapperWidth / wrapperHeight;
    const imageAspectRatio = window.originalImageWidth / window.originalImageHeight;

    let renderedImageWidth, renderedImageHeight;
    let offsetX = 0;
    let offsetY = 0;

    if (wrapperAspectRatio > imageAspectRatio) {
        renderedImageHeight = wrapperHeight;
        renderedImageWidth = renderedImageHeight * imageAspectRatio;
        offsetX = (wrapperWidth - renderedImageWidth) / 2;
    } else {
        renderedImageWidth = wrapperWidth;
        renderedImageHeight = renderedImageWidth / imageAspectRatio;
        offsetY = (wrapperHeight - renderedImageHeight) / 2;
    }

    const scaleX = renderedImageWidth / window.originalImageWidth;
    const scaleY = renderedImageHeight / window.originalImageHeight;

    window.baseAreas.forEach(areaData => {
        const areaElement = window.roomOverlayWrapper.querySelector(areaData.selector);
        if (areaElement) {
            areaElement.style.top = (areaData.top * scaleY + offsetY) + 'px';
            areaElement.style.left = (areaData.left * scaleX + offsetX) + 'px';
            areaElement.style.width = (areaData.width * scaleX) + 'px';
            areaElement.style.height = (areaData.height * scaleY) + 'px';
        }
    });
    
    console.log(`Updated ${window.baseAreas.length} room areas for ${window.ROOM_TYPE}`);
}

async function loadRoomCoordinatesFromDatabase() {
    try {
        const response = await fetch(`api/get_room_coordinates.php?room_type=${window.ROOM_TYPE}`);
        
        // Check if the response is ok (not 500 error)
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Database not available`);
        }
        
        const data = await response.json();
        
        if (data.success && data.coordinates && data.coordinates.length > 0) {
            window.baseAreas = data.coordinates;
            console.log(`Loaded ${data.coordinates.length} coordinates from database for ${window.ROOM_TYPE}`);
            
            // Initialize coordinates after loading
            updateAreaCoordinates();
            
            // Set up resize handler
            let resizeTimeout;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(updateAreaCoordinates, 100);
            });
        } else {
            console.error(`No active room map found in database for ${window.ROOM_TYPE}`);
            // Fallback to any existing baseAreas set by room helper
            if (window.baseAreas && window.baseAreas.length > 0) {
                console.log(`Using fallback coordinates for ${window.ROOM_TYPE}`);
                updateAreaCoordinates();
            }
        }
    } catch (error) {
        console.error(`Error loading ${window.ROOM_TYPE} coordinates from database:`, error);
        // Fallback to any existing baseAreas set by room helper
        if (window.baseAreas && window.baseAreas.length > 0) {
            console.log(`Using fallback coordinates for ${window.ROOM_TYPE} due to database error`);
            updateAreaCoordinates();
        }
    }
}

// Make functions available globally
window.updateAreaCoordinates = updateAreaCoordinates;
window.loadRoomCoordinatesFromDatabase = loadRoomCoordinatesFromDatabase;
window.initializeRoomCoordinates = initializeRoomCoordinates;

function waitForWrapperAndUpdate(retries = 10) {
    if (!window.roomOverlayWrapper) {
        window.roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
    }
    if (window.roomOverlayWrapper && window.roomOverlayWrapper.offsetWidth > 0 && window.roomOverlayWrapper.offsetHeight > 0) {
        updateAreaCoordinates();
    } else if (retries > 0) {
        setTimeout(() => waitForWrapperAndUpdate(retries - 1), 200);
    } else {
        console.warn('Room overlay wrapper size not ready after retries.');
    }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Add a small delay to ensure room helper variables are set
    setTimeout(initializeRoomCoordinates, 100);
});

console.log('room-coordinate-manager.js loaded successfully');
