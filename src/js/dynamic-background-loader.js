// Dynamic Background Loading for Room Pages
import ApiClient from '../core/api-client.js';
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
  getBgStyleEl().appendChild(document.createTextNode(`.${cls}{--dynamic-bg-url:url('${url}');background-image:url('${url}') !important;background-size:cover;background-position:center;background-repeat:no-repeat;}`));
  bgClassCache.set(url, cls);
  return cls;
}
export async function loadRoomBackground(roomNumberStr) {
    
    try {
        // Previously, we skipped dynamic background when coming from main (?from=main),
        // which caused stale cached images (CSS-based backgrounds) to persist.
        // Always proceed with dynamic fetch/apply so we attach a cache-busted URL.
        // If no room provided, try to auto-detect similar to autoLoadRoomBackground
        if (!roomNumberStr) {
            const landingPage = document.querySelector('#landingPage');
            if (landingPage) {
                roomNumberStr = 'landing';
            } else {
                // Map certain pages explicitly
                try {
                    const body = document.body;
                    const pageSlug = (body && body.dataset && body.dataset.page) ? body.dataset.page : '';
                    if (pageSlug === 'shop') {
                        roomNumberStr = 'shop';
                    }
                } catch (e) {
                    // non-fatal
                }

                // Attempt detection via available room data
                try {
                    const roomData = await ApiClient.get('/api/get_room_data.php');
                    const roomNumberMapping = roomData?.data?.roomTypeMapping || {};
                    const roomDoors = roomData?.data?.roomDoors || [];
                    const roomContainer = document.querySelector('[data-room-name]');
                    if (roomContainer) {
                        const roomName = roomContainer.getAttribute('data-room-name');
                        const matchingRoom = roomDoors.find(room => 
                            room.room_name?.toLowerCase() === roomName?.toLowerCase() ||
                            room.door_label?.toLowerCase() === roomName?.toLowerCase()
                        );
                        if (matchingRoom) {
                            roomNumberStr = roomNumberMapping[matchingRoom.room_number];
                        }
                    }
                    if (!roomNumberStr) {
                        const currentPage = (new URLSearchParams(window.location.search)).get('page') || '';
                        if (currentPage && roomNumberMapping[currentPage.replace('room','')]) {
                            roomNumberStr = currentPage;
                        }
                    }
                } catch(e) {
                    console.warn('[DBG] Auto-detect failed to fetch room data', e);
                }
            }
        }
        if (!roomNumberStr) {
            console.log('No room detected; aborting dynamic room background.');
            return;
        }

        // Normal room background loading via new room param
        const rn = /^room\d+$/i.test(String(roomNumberStr)) ? String(roomNumberStr).replace(/^room/i, '') : String(roomNumberStr);
        if (!/^\d+$/.test(rn)) {
            console.log('[DBG] Not a numeric room; skipping background API fetch');
            return;
        }
        const data = await apiGet(`/api/get_background.php?room=${encodeURIComponent(rn)}`);
        
        
        if (data.success && data.background) {
            const background = data.background;
            // Select the appropriate wrapper for modal or main page
                const raw = (background.webp_filename || background.image_filename) || '';
                const fname = raw;
                const imageUrl = window.location.origin + `/images/backgrounds/${fname}?v=${Date.now()}`;
                const roomWrapper = document.getElementById('modalRoomPage')
    ? (document.querySelector('.room-overlay-wrapper') || document.querySelector('.room-modal-body') || document.querySelector('.room-modal-iframe-container'))
    : document.getElementById('mainRoomPage') || document.getElementById('landingPage') || document.getElementById('shopPage');
            
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
                console.error('[DynamicBG] Room wrapper not found; background will not be applied', { imageUrl });
            }
        } else {
            console.error('[DynamicBG] No active background returned for room', { room: roomNumberStr, response: data });
        }
    } catch (error) {
        console.error('Error loading dynamic room background (strict, no fallback):', error);
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

        // Don't run dynamic background loading on pages that already have backgrounds set by PHP
        const mainRoomPage = document.getElementById('mainRoomPage');
        const landingPage2 = document.getElementById('landingPage');
        const shopPage = document.getElementById('shopPage');

        if (mainRoomPage && mainRoomPage.getAttribute('data-bg-url')) {
            console.log('🚫 Main room page already has background set by PHP, skipping dynamic loading');
            return;
        }
        if (landingPage2 && landingPage2.getAttribute('data-bg-url')) {
            console.log('🚫 Landing page already has background set by PHP, skipping dynamic loading');
            return;
        }
        if (shopPage && shopPage.getAttribute('data-bg-url')) {
            console.log('🚫 Shop page already has background set by PHP, skipping dynamic loading');
            return;
        }

        // Get dynamic room data from API for non-landing pages
        const roomData = await ApiClient.get('/api/get_room_data.php');
        
        if (!roomData.success) {
            console.error('Failed to get room data:', roomData.message);
            console.log('Background system will operate in degraded mode');
            return;
        }
        
        if (!roomData.data.roomDoors || roomData.data.roomDoors.length === 0) {
            console.log('No room doors found - background system will use default');
            return;
        }
        
        const roomNumberMapping = roomData.data.roomTypeMapping;
        const roomDoors = roomData.data.roomDoors;
        
        // Try to detect room type from the page element or URL
        const roomContainer = document.querySelector('[data-room-name]');
        if (roomContainer) {
            const roomName = roomContainer.getAttribute('data-room-name');
            let roomNumberStr = '';
            
            // Find matching room by name
            const matchingRoom = roomDoors.find(room => 
                room.room_name.toLowerCase() === roomName.toLowerCase() ||
                room.door_label.toLowerCase() === roomName.toLowerCase()
            );
            
            if (matchingRoom) {
                roomNumberStr = roomNumberMapping[matchingRoom.room_number];
            } else {
                // Try to detect from URL
                const urlParams = new URLSearchParams(window.location.search);
                const currentPage = urlParams.get('page') || '';
                
                // Check if currentPage matches any room type
                if (roomNumberMapping[currentPage.replace('room', '')]) {
                    roomNumberStr = currentPage;
                }
            }
            
            if (roomNumberStr) {
                loadRoomBackground(roomNumberStr);
            }
        }
    } catch (error) {
        console.error('Error loading dynamic room background:', error);
    }
}

// Watch for modal-based room content appearing after initial load
function setupModalObserver() {
    try {
        const runDetection = async () => {
            try {
                // If a modal wrapper exists, attempt the same detection used in autoLoad
                const modalHost = document.getElementById('modalRoomPage') || document.querySelector('.room-overlay-wrapper') || document.querySelector('.room-modal-body') || document.querySelector('.room-modal-iframe-container');
                if (!modalHost) return;
                const roomData = await ApiClient.get('/api/get_room_data.php').catch(() => null);
                const roomNumberMapping = roomData?.data?.roomTypeMapping || {};
                const roomDoors = roomData?.data?.roomDoors || [];
                const roomContainer = document.querySelector('[data-room-name]');
                if (roomContainer) {
                    const roomName = roomContainer.getAttribute('data-room-name');
                    const matchingRoom = roomDoors.find(room =>
                        room.room_name?.toLowerCase() === roomName?.toLowerCase() ||
                        room.door_label?.toLowerCase() === roomName?.toLowerCase()
                    );
                    if (matchingRoom) {
                        const roomNumberStr = roomNumberMapping[matchingRoom.room_number];
                        if (roomNumberStr) {
                            loadRoomBackground(roomNumberStr);
                        }
                    }
                }
            } catch (_) { /* non-fatal */ }
        };
        // Initial attempt in case modal already present
        runDetection();
        const obs = new MutationObserver((muts) => {
            for (const m of muts) {
                if (m.type === 'childList') {
                    const added = Array.from(m.addedNodes || []);
                    if (added.some(n => (n.nodeType === 1) && (
                        n.id === 'modalRoomPage' ||
                        n.classList?.contains('room-overlay-wrapper') ||
                        n.classList?.contains('room-modal-body') ||
                        n.classList?.contains('room-modal-iframe-container')
                    ))) {
                        runDetection();
                        break;
                    }
                }
            }
        });
        obs.observe(document.body || document.documentElement, { childList: true, subtree: true });
    } catch (_) { /* non-fatal */ }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    autoLoadRoomBackground();
    setupModalObserver();
});