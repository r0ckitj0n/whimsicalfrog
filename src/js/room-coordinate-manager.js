/**
 * WhimsicalFrog Room Coordinate and Area Management
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
 */

// Simple and Direct Room Coordinate System
// No complex scaling, just direct pixel positioning

console.log('🎯 Loading Simple Room Coordinate System...');

// Runtime CSS class injection for coordinate-based positioning (no inline styles)
const WF_RCM = { styleEl: null, rules: new Set() };
function rcmEnsureStyleEl() {
    if (!WF_RCM.styleEl) {
        WF_RCM.styleEl = document.createElement('style');
        WF_RCM.styleEl.id = 'wf-rcm-styles';
        document.head.appendChild(WF_RCM.styleEl);
    }
    return WF_RCM.styleEl;
}
function rcmClassName(t, l, w, h) {
    return `wf-rcm-t${t}-l${l}-w${w}-h${h}`;
}
function rcmEnsureRule(cls, t, l, w, h) {
    if (WF_RCM.rules.has(cls)) return;
    const css = `.room-overlay-wrapper .${cls} { position: absolute; top: ${t}px; left: ${l}px; width: ${w}px; height: ${h}px; }`;
    rcmEnsureStyleEl().appendChild(document.createTextNode(css));
    WF_RCM.rules.add(cls);
}

// Simple coordinate system that just scales from database size to display size
function simpleCoordinateSystem(roomNumber) {
    console.log(`🎯 Initializing simple coordinate system for room ${roomNumber}...`);
    
    // Get room overlay wrapper
    const roomWrapper = document.querySelector('.room-overlay-wrapper');
    if (!roomWrapper) {
        console.error('❌ Room overlay wrapper not found');
        return;
    }
    
    console.log('✅ Room overlay wrapper found:', roomWrapper);
    
    // Fetch coordinates from database
    console.log(`📡 Fetching coordinates from /api/get_room_coordinates.php?room=${roomNumber}`);
    fetch(`/api/get_room_coordinates.php?room=${encodeURIComponent(roomNumber)}`)
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

            // Remove any previously applied coordinate class
            const prev = item.dataset.wfRcmPosClass;
            if (prev) item.classList.remove(prev);

            // Ensure and apply class-based positioning
            const cls = rcmClassName(top, left, width, height);
            rcmEnsureRule(cls, top, left, width, height);
            item.classList.add(cls);
            item.dataset.wfRcmPosClass = cls;
            
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
    // Only run on actual room detail pages; skip on room_main and other pages.
    // Modal coordinates are handled by RoomModalManager.initializeModalContent().
    const body = document.body;
    const page = (body && body.dataset && body.dataset.page) || (window.WF_PAGE_INFO && window.WF_PAGE_INFO.page) || '';
    const isRoomPage = typeof page === 'string' && /^room\d+$/.test(String(page));
    if (!isRoomPage) {
        console.log('🎯 Not a room detail page; coordinate manager idle');
        return;
    }
    console.log('🎯 DOM loaded, checking for room...');
    console.log('🎯 Available window variables:', {
        roomType: window.roomType,
        roomNumber: window.roomNumber
    });
    
    // Check if room is set (legacy globals supported)
    if (window.ROOM_TYPE) {
        console.log('🎯 Room found (legacy global)');
        simpleCoordinateSystem(window.ROOM_TYPE);
    } else if (window.roomType) {
        console.log(`🎯 Room found (fallback): ${window.roomType}`);
        simpleCoordinateSystem(window.roomType);
    } else if (window.roomNumber) {
        console.log(`🎯 Room number found: ${window.roomNumber}`);
        simpleCoordinateSystem(`room${window.roomNumber}`);
    } else {
        console.log('❌ No room found. Retrying in 500ms...');
        setTimeout(() => {
            console.log('🎯 Retrying after delay...');
            console.log('🎯 Available window variables after delay:', {
                roomType: window.roomType,
                roomNumber: window.roomNumber
            });
            if (window.ROOM_TYPE) {
                console.log('🎯 Room found after delay (legacy global)');
                simpleCoordinateSystem(window.ROOM_TYPE);
            } else if (window.roomType) {
                console.log(`🎯 Room found after delay (fallback): ${window.roomType}`);
                simpleCoordinateSystem(window.roomType);
            } else if (window.roomNumber) {
                console.log(`🎯 Room number found after delay: ${window.roomNumber}`);
                simpleCoordinateSystem(`room${window.roomNumber}`);
            } else {
                console.log('❌ Still no room found.');
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
