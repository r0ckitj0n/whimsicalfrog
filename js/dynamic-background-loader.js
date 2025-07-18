// Ensure apiGet helper exists
if (typeof window.apiGet !== 'function') {
  window.apiGet = async function(endpoint) {
    // Prepend /api/ if not already present
    const url = endpoint.startsWith('/') ? endpoint : `/api/${endpoint}`;
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) {
      throw new Error(`Request failed (${res.status})`);
    }
    return res.json();
  };
}

async function loadDynamicBackground() {
    try {
        const urlParams = new URLSearchParams(window.location.search);
        const currentPage = urlParams.get('page') || 'landing';
        const fromMain = urlParams.get('from') === 'main';
        
        let roomType = 'landing';
        
        // Generate dynamic page room mapping
        async function generatePageRoomMap() {
            try {
                const data = await apiGet('get_room_data.php');
                
                if (data.success) {
                    const pageRoomMap = {
                        'room_main': 'room_main',
                        'shop': 'room_main', 
                        'cart': 'room_main', 
                        'login': 'room_main', 
                        'admin': 'room_main'
                    };
                    
                    // Add dynamic room mappings
                    data.data.roomDoors.forEach(room => {
                        const roomPageName = `room${room.room_number}`;
                        pageRoomMap[roomPageName] = roomPageName;
                    });
                    
                    return pageRoomMap;
                }
            } catch (error) {
                console.error('Error generating page room map:', error);
            }
            
            // Fallback to basic mapping if API fails
            return {
                'room_main': 'room_main',
                'shop': 'room_main', 
                'cart': 'room_main', 
                'login': 'room_main', 
                'admin': 'room_main'
            };
        }
        
        // Room pages no longer exist as standalone pages - they're handled by modals
        // No special handling needed for deleted room pages
        
        const pageRoomMap = await generatePageRoomMap();
        roomType = pageRoomMap[currentPage] || 'landing';
        
        // Fetch background from database
        const data = await apiGet(`get_background.php?room_type=${roomType}`);
        
        if (data.success && data.background) {
            const background = data.background;
            const supportsWebP = document.documentElement.classList.contains('webp');
            // Prefix with backgrounds/ subdirectory to match file structure
            const filename = supportsWebP && background.webp_filename
                ? background.webp_filename
                : background.image_filename;
            // Primary expected location
            let imageUrl = `images/backgrounds/${filename}`;
            // If the backgrounds directory is not used (legacy), fall back to images root
            if (!imageUrl.includes('/backgrounds/') && !filename.startsWith('backgrounds/')) {
                imageUrl = `images/${filename}`;
            }

            // Find the correct container for the background
            let backgroundContainer = document.querySelector('.fullscreen-container') || document.getElementById('mainContent');
            if (!backgroundContainer) {
                console.warn('Background container not found, falling back to body.');
                backgroundContainer = document.body;
            }

            // Set the CSS variable for the background URL on the container
            backgroundContainer.style.setProperty('--dynamic-bg-url', `url('${imageUrl}')`);

            // Add the necessary classes to the container
            backgroundContainer.classList.add('bg-container', 'mode-fullscreen', 'dynamic-bg-loaded');
            
            // Also add a class to the body to indicate a dynamic background is active
            document.body.classList.add('dynamic-bg-active');
        }
    } catch (error) {
        console.error('Error loading dynamic background:', error);
    }
}

document.addEventListener('DOMContentLoaded', loadDynamicBackground);
