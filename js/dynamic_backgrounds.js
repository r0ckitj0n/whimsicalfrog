// Dynamic Background Loading for Room Pages
async function loadRoomBackground(roomType) {
    try {
        // Check if we're coming from main room - if so, use main room background
        const urlParams = new URLSearchParams(window.location.search);
        const fromMain = urlParams.get('from') === 'main';
        
        if (fromMain) {
            // When coming from main room, let CSS handle room-specific content 
            // and let main site background system handle the main room background
            console.log('Coming from main room - using CSS room background with main room body background');
            return;
        }
        
        // Normal room background loading
        const data = await apiGet(`get_background.php?room_type=${roomType}`);
        
        
        if (data.success && data.background) {
            const background = data.background;
            const roomWrapper = document.querySelector('.room-overlay-wrapper');
            
            if (roomWrapper) {
                // Determine if WebP is supported
                const supportsWebP = document.documentElement.classList.contains('webp');
                const imageUrl = supportsWebP && background.webp_filename ? 
                    `images/${background.webp_filename}` : 
                    `images/${background.image_filename}`;
                
                // Apply the background to the room wrapper using CSS custom property
                roomWrapper.style.setProperty('-dynamic-room-bg-url', `url('${imageUrl}?v=${Date.now()}')`);
                roomWrapper.classList.add('dynamic-room-bg-loaded');
                
                console.log(`Dynamic room background loaded: ${background.background_name} (${imageUrl})`);
            } else {
                console.log('Room wrapper not found, using fallback background');
            }
        } else {
            console.log('Using fallback room background - no dynamic background found');
        }
    } catch (error) {
        console.error('Error loading dynamic room background:', error);
        console.log('Using fallback room background due to error');
    }
}

// Auto-detect room type and load background
async function autoLoadRoomBackground() {
    try {
        // Get dynamic room data from API
        const roomData = await apiGet('get_room_data.php');
        
        if (!roomData.success) {
            console.error('Failed to get room data:', roomData.message);
            console.log('Background system will operate in degraded mode');
            return;
        }
        
        if (!roomData.data.roomDoors || roomData.data.roomDoors.length === 0) {
            console.log('No room doors found - background system will use default');
            return;
        }
        
        const roomTypeMapping = roomData.data.roomTypeMapping;
        const roomDoors = roomData.data.roomDoors;
        
        // Try to detect room type from the page element or URL
        const roomContainer = document.querySelector('[data-room-name]');
        if (roomContainer) {
            const roomName = roomContainer.getAttribute('data-room-name');
            let roomType = '';
            
            // Find matching room by name
            const matchingRoom = roomDoors.find(room => 
                room.room_name.toLowerCase() === roomName.toLowerCase() ||
                room.door_label.toLowerCase() === roomName.toLowerCase()
            );
            
            if (matchingRoom) {
                roomType = roomTypeMapping[matchingRoom.room_number];
            } else {
                // Try to detect from URL
                const urlParams = new URLSearchParams(window.location.search);
                const currentPage = urlParams.get('page') || '';
                
                // Check if currentPage matches any room type
                if (roomTypeMapping[currentPage.replace('room', '')]) {
                    roomType = currentPage;
                }
            }
            
            if (roomType) {
                loadRoomBackground(roomType);
            }
        }
    } catch (error) {
        console.error('Error loading dynamic room background:', error);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', autoLoadRoomBackground); 