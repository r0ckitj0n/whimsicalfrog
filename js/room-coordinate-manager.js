/**
 * WhimsicalFrog Room Coordinate and Area Management
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

console.log('Loading room-coordinate-manager.js...');

// Room coordinate management system
window.RoomCoordinates = window.RoomCoordinates || {};

// Global variables for room positioning
window.originalImageWidth = 1280;
window.originalImageHeight = 896;
window.baseAreas = [];
window.roomOverlayWrapper = null;

function updateAreaCoordinates() {
    console.log('🎯 updateAreaCoordinates called');
    
    if (!window.roomOverlayWrapper) {
        console.error('❌ Room overlay wrapper not found for scaling.');
        return;
    }

    const wrapperWidth = window.roomOverlayWrapper.offsetWidth;
    const wrapperHeight = window.roomOverlayWrapper.offsetHeight;
    
    console.log(`📐 Wrapper dimensions: ${wrapperWidth}x${wrapperHeight}`);

    const wrapperAspectRatio = wrapperWidth / wrapperHeight;
    const imageAspectRatio = window.originalImageWidth / window.originalImageHeight;
    
    console.log(`📐 Aspect ratios: wrapper=${wrapperAspectRatio}, image=${imageAspectRatio}`);

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
    
    console.log(`📐 Scaling: scaleX=${scaleX}, scaleY=${scaleY}, offsetX=${offsetX}, offsetY=${offsetY}`);

    // Position item icons based on database coordinates
    // Database coordinates use selectors like ".area-1", ".area-2", etc.
    // We need to map these to item-icon-0, item-icon-1, etc.
    if (window.baseAreas && window.baseAreas.length > 0) {
        console.log(`🎯 Processing ${window.baseAreas.length} areas...`);
        
        window.baseAreas.forEach((areaData, index) => {
            // Extract area number from selector (e.g. ".area-1" -> 1)
            const areaNumber = parseInt(areaData.selector.replace('.area-', ''));
            // Map to item-icon index (area-1 -> item-icon-0, area-2 -> item-icon-1, etc.)
            const itemIndex = areaNumber - 1;
            const itemElement = document.getElementById('item-icon-' + itemIndex);
            
            if (itemElement) {
                const newTop = (areaData.top * scaleY + offsetY);
                const newLeft = (areaData.left * scaleX + offsetX);
                const newWidth = (areaData.width * scaleX);
                const newHeight = (areaData.height * scaleY);
                
                itemElement.style.top = newTop + 'px';
                itemElement.style.left = newLeft + 'px';
                itemElement.style.width = newWidth + 'px';
                itemElement.style.height = newHeight + 'px';
                
                console.log(`✅ Positioned ${areaData.selector} -> item-icon-${itemIndex}: top=${newTop}px, left=${newLeft}px, width=${newWidth}px, height=${newHeight}px`);
            } else {
                console.warn(`⚠️ No item element found for ${areaData.selector} (item-icon-${itemIndex})`);
            }
        });
    } else {
        console.error('❌ No baseAreas available for positioning');
    }
}

async function loadRoomCoordinatesFromDatabase() {
    try {
        // Use the ROOM_TYPE variable if available, otherwise try to detect from URL
        const roomType = window.ROOM_TYPE || (window.location.href.includes('room') ? 
            window.location.href.match(/room(\d+)/)?.[0] : 'room2');
        
        console.log('Loading coordinates for room type:', roomType);
        
        const response = await fetch(`api/get_room_coordinates.php?room_type=${roomType}`);
        
        // Check if the response is ok (not 500 error)
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: Database not available`);
        }
        
        const data = await response.json();
        
        if (data.success && data.coordinates && data.coordinates.length > 0) {
            window.baseAreas = data.coordinates;
            console.log(`✅ Loaded ${data.coordinates.length} coordinates for ${roomType}:`, data.coordinates);
            
            // Initialize coordinates after loading
            setTimeout(function() {
                console.log('🔄 Applying coordinates...');
                updateAreaCoordinates();
            }, 200);
            
            // Update on window resize
            window.addEventListener('resize', function() {
                clearTimeout(window.resizeTimeout);
                window.resizeTimeout = setTimeout(function() {
                    updateAreaCoordinates();
                }, 100);
            });
        } else {
            console.error(`❌ No active room map found in database for ${roomType}`);
        }
    } catch (error) {
        console.error(`❌ Error loading room coordinates from database:`, error);
    }
}

// Initialize the coordinate system when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing room coordinate system...');
    
    // Find the room overlay wrapper
    window.roomOverlayWrapper = document.querySelector('.room-overlay-wrapper');
    
    if (window.roomOverlayWrapper) {
        // Load coordinates from database after a short delay to ensure all elements are rendered
        setTimeout(function() {
            loadRoomCoordinatesFromDatabase();
        }, 100);
    } else {
        console.error('Room overlay wrapper not found');
    }
});

// Expose functions globally
window.updateAreaCoordinates = updateAreaCoordinates;
window.loadRoomCoordinatesFromDatabase = loadRoomCoordinatesFromDatabase;

console.log('room-coordinate-manager.js loaded successfully');
