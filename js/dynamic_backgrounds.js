// Dynamic Background Loading for Room Pages
async function loadRoomBackground(roomType) {
    try {
        // Fetch active background for this room
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
                roomType = 'room_artwork';
                break;
            case 't-shirts':
                roomType = 'room_tshirts';
                break;
            case 'tumblers':
                roomType = 'room_tumblers';
                break;
            case 'sublimation':
                roomType = 'room_sublimation';
                break;
            case 'window wraps':
                roomType = 'room_windowwraps';
                break;
            default:
                // Try to detect from URL
                const urlParams = new URLSearchParams(window.location.search);
                const currentPage = urlParams.get('page') || '';
                
                switch (currentPage) {
                    case 'artwork':
                        roomType = 'room_artwork';
                        break;
                    case 'tshirts':
                        roomType = 'room_tshirts';
                        break;
                    case 'tumblers':
                        roomType = 'room_tumblers';
                        break;
                    case 'sublimation':
                        roomType = 'room_sublimation';
                        break;
                    case 'windowwraps':
                        roomType = 'room_windowwraps';
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