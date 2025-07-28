/**
 * WhimsicalFrog Room Coordinate and Area Management
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

// Simple and Direct Room Coordinate System
// No complex scaling, just direct pixel positioning

console.log('🎯 Loading Simple Room Coordinate System...');

// Simple coordinate system that just scales from database size to display size
function simpleCoordinateSystem(roomType) {
    console.log(`🎯 Initializing simple coordinate system for ${roomType}...`);
    
    // Get room overlay wrapper
    const roomWrapper = document.querySelector('.room-overlay-wrapper');
    if (!roomWrapper) {
        console.error('❌ Room overlay wrapper not found');
        return;
    }
    
    console.log('✅ Room overlay wrapper found:', roomWrapper);
    
    // Fetch coordinates from database
    console.log(`📡 Fetching coordinates from /api/get_room_coordinates.php?room_type=${roomType}`);
    fetch(`/api/get_room_coordinates.php?room_type=${roomType}`)
        .then(response => {
            console.log('📡 Response received:', response.status, response.statusText);
            return response.json();
        })
        .then(data => {
            console.log('📡 Data received:', data);
            if (data.success && data.coordinates) {
                console.log('✅ Coordinates loaded:', data.coordinates);
                applySimpleCoordinates(data.coordinates);
            } else {
                console.error('❌ Failed to load coordinates:', data.message);
            }
        })
        .catch(error => {
            console.error('❌ Error loading coordinates:', error);
        });
}

// Apply coordinates with simple scaling
function applySimpleCoordinates(coordinates) {
    console.log('🎯 Applying simple coordinates...');
    
    // Get current screen size to determine scale
    const screenWidth = window.innerWidth;
    let scaleFactor;
    
    if (screenWidth <= 480) {
        scaleFactor = 400 / 1280; // Mobile: 0.3125
    } else if (screenWidth <= 768) {
        scaleFactor = 800 / 1280; // Tablet: 0.625
    } else {
        scaleFactor = 1600 / 1280; // Desktop: 1.25
    }
    
    console.log(`📐 Screen width: ${screenWidth}px, Scale factor: ${scaleFactor}`);
    
    // Apply to each item
    coordinates.forEach((coord, index) => {
        const item = document.getElementById(`item-icon-${index}`);
        if (item) {
            const top = Math.round(coord.top * scaleFactor);
            const left = Math.round(coord.left * scaleFactor);
            const width = Math.round(coord.width * scaleFactor);
            const height = Math.round(coord.height * scaleFactor);
            
            item.style.position = 'absolute';
            item.style.top = `${top}px`;
            item.style.left = `${left}px`;
            item.style.width = `${width}px`;
            item.style.height = `${height}px`;
            
            console.log(`✅ Item ${index}: positioned at ${left},${top} (${width}x${height})`);
        } else {
            console.warn(`⚠️ Item ${index} not found`);
        }
    });
}

// Make it globally available
window.simpleCoordinateSystem = simpleCoordinateSystem;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎯 DOM loaded, checking for room type...');
    console.log('🎯 Available window variables:', {
        ROOM_TYPE: window.ROOM_TYPE,
        roomType: window.roomType,
        roomNumber: window.roomNumber
    });
    
    // Check if room type is set
    if (window.ROOM_TYPE) {
        console.log(`🎯 Room type found: ${window.ROOM_TYPE}`);
        simpleCoordinateSystem(window.ROOM_TYPE);
    } else if (window.roomType) {
        console.log(`🎯 Room type found (fallback): ${window.roomType}`);
        simpleCoordinateSystem(window.roomType);
    } else {
        console.log('⚠️ No room type found, waiting...');
        // Try again after delay
        setTimeout(() => {
            console.log('🎯 Retrying after delay...');
            console.log('🎯 Available window variables after delay:', {
                ROOM_TYPE: window.ROOM_TYPE,
                roomType: window.roomType,
                roomNumber: window.roomNumber
            });
            if (window.ROOM_TYPE) {
                console.log(`🎯 Room type found after delay: ${window.ROOM_TYPE}`);
                simpleCoordinateSystem(window.ROOM_TYPE);
            } else if (window.roomType) {
                console.log(`🎯 Room type found after delay (fallback): ${window.roomType}`);
                simpleCoordinateSystem(window.roomType);
            } else {
                console.error('❌ Still no room type found');
            }
        }, 500);
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    console.log('🎯 Window resized, reapplying coordinates...');
    if (window.ROOM_TYPE) {
        simpleCoordinateSystem(window.ROOM_TYPE);
    } else if (window.roomType) {
        simpleCoordinateSystem(window.roomType);
    }
});
