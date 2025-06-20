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
                
                // Apply the background to the room wrapper
                roomWrapper.style.backgroundImage = `url('${imageUrl}?v=${Date.now()}')`;
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
                // Try to detect from URL
                const urlParams = new URLSearchParams(window.location.search);
                const currentPage = urlParams.get('page') || '';
                
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
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', autoLoadRoomBackground); 