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
        const response = await fetch(`api/get_background.php?room_type=${roomType}`);
        const data = await response.json();
        
        if (data.success && data.background) {
            const background = data.background;
            const roomWrapper = document.querySelector('.room-overlay-wrapper');
            
            if (roomWrapper) {
                // Determine if WebP is supported
                const supportsWebP = document.documentElement.classList.contains('webp');
                const imageUrl = supportsWebP && background.webp_filename ? 
                    `images/${background.webp_filename}` : 
                    `images/${background.image_filename}`;
                
<<<<<<< HEAD
                // Apply the background to the room wrapper
                roomWrapper.style.backgroundImage = `url('${imageUrl}?v=${Date.now()}')`;
=======
                // Apply the background to the room wrapper using CSS custom property
                roomWrapper.style.setProperty('-dynamic-room-bg-url', `url('${imageUrl}?v=${Date.now()}')`);
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
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
<<<<<<< HEAD
function autoLoadRoomBackground() {
    // Try to detect room type from the page element or URL
    const roomContainer = document.querySelector('[data-room-name]');
    if (roomContainer) {
        const roomName = roomContainer.getAttribute('data-room-name');
        let roomType = '';
        
        switch (roomName.toLowerCase()) {
            case 'artwork':
                roomType = 'room4';
                break;
            case 't-shirts':
                roomType = 'room2';
                break;
            case 'tumblers':
                roomType = 'room3';
                break;
            case 'sublimation':
                roomType = 'room5';
                break;
            case 'window wraps':
                roomType = 'room6';
                break;
            default:
=======
async function autoLoadRoomBackground() {
    try {
        // Get dynamic room data from API
        const response = await fetch('/api/get_room_data.php');
        const roomData = await response.json();
        
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
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
                // Try to detect from URL
                const urlParams = new URLSearchParams(window.location.search);
                const currentPage = urlParams.get('page') || '';
                
<<<<<<< HEAD
                switch (currentPage) {
                            case 'room2':
            roomType = 'room2';
            break;
        case 'room3':
            roomType = 'room3';
            break;
        case 'room4':
            roomType = 'room4';
            break;
        case 'room5':
            roomType = 'room5';
            break;
        case 'room6':
            roomType = 'room6';
            break;
                }
        }
        
        if (roomType) {
            loadRoomBackground(roomType);
        }
=======
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
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', autoLoadRoomBackground); 