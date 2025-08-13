// Dynamic Background Loading for Room Pages
import { apiGet } from '../core/apiClient.js';
console.log('🚪 [DBG] dynamic-background-loader.js loaded');

// Runtime-injected background classes
const RBG_STYLE_ID = 'wf-room-dynbg-runtime';
function getBgStyleEl(){
  let el = document.getElementById(RBG_STYLE_ID);
  if (!el){ el = document.createElement('style'); el.id = RBG_STYLE_ID; document.head.appendChild(el); }
  return el;
}
const bgClassCache = new Map(); // url -> class
function ensureBgClass(url){
  if (!url) return null;
  if (bgClassCache.has(url)) return bgClassCache.get(url);
  const idx = bgClassCache.size + 1;
  const cls = `roombg-${idx}`;
  getBgStyleEl().appendChild(document.createTextNode(`.${cls}{--dynamic-bg-url:url('${url}');background-image:url('${url}');}`));
  bgClassCache.set(url, cls);
  return cls;
}
export async function loadRoomBackground(roomType) {
    
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
        // If no roomType provided, try to auto-detect similar to autoLoadRoomBackground
        if (!roomType) {
            const landingPage = document.querySelector('#landingPage');
            if (landingPage) {
                roomType = 'landing';
            } else {
                // Attempt detection via available room data
                try {
                    const roomData = await apiGet('/api/get_room_coordinates.php');
                    const roomTypeMapping = roomData?.data?.roomTypeMapping || {};
                    const roomDoors = roomData?.data?.roomDoors || [];
                    const roomContainer = document.querySelector('[data-room-name]');
                    if (roomContainer) {
                        const roomName = roomContainer.getAttribute('data-room-name');
                        const matchingRoom = roomDoors.find(room => 
                            room.room_name?.toLowerCase() === roomName?.toLowerCase() ||
                            room.door_label?.toLowerCase() === roomName?.toLowerCase()
                        );
                        if (matchingRoom) {
                            roomType = roomTypeMapping[matchingRoom.room_number];
                        }
                    }
                    if (!roomType) {
                        const currentPage = (new URLSearchParams(window.location.search)).get('page') || '';
                        if (currentPage && roomTypeMapping[currentPage.replace('room','')]) {
                            roomType = currentPage;
                        }
                    }
                } catch(e) {
                    console.warn('[DBG] Auto-detect failed to fetch room data', e);
                }
            }
        }
        if (!roomType) {
            console.log('No roomType detected; aborting dynamic room background.');
            return;
        }

        // Normal room background loading
        const data = await apiGet(`/api/get_background.php?room_type=${roomType}`);
        
        
        if (data.success && data.background) {
            const background = data.background;
            // Select the appropriate wrapper for modal or main page
                const filename = (background.webp_filename || background.image_filename);
                const prefixedFilename = filename.startsWith('background_') ? filename : `background_${filename}`;
                const imageUrl = window.location.origin + `/images/backgrounds/${prefixedFilename}?v=${Date.now()}`;
                const roomWrapper = document.getElementById('modalRoomPage')
    ? document.querySelector('.room-modal-iframe-container')
    : document.getElementById('mainRoomPage') || document.getElementById('landingPage');
            
            if (roomWrapper) {
                // Apply computed background image via class
                const bgCls = ensureBgClass(imageUrl);
                if (roomWrapper.dataset.bgClass && roomWrapper.dataset.bgClass !== bgCls){
                    roomWrapper.classList.remove(roomWrapper.dataset.bgClass);
                }
                if (bgCls){
                    roomWrapper.classList.add(bgCls);
                    roomWrapper.dataset.bgClass = bgCls;
                }
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
        // Check if this is the landing page FIRST, before any API calls
        console.log('🔍 Looking for #landingPage element...');
        const landingPage = document.querySelector('#landingPage');
        console.log('🔍 landingPage element found:', landingPage);
        if (landingPage) {
            console.log('🎯 Detected landing page, loading landing background');
            loadRoomBackground('landing');
            return;
        } else {
            console.log('❌ No #landingPage element found, continuing to room detection...');
        }
        
        // Get dynamic room data from API for non-landing pages
        // Use available room coordinates API instead of missing room data API
        const roomData = await apiGet('/api/get_room_coordinates.php');
        
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