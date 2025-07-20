// src/core/dynamicBackground.js
// Dynamically loads page/room backgrounds using the unified ApiClient.
// Exported as an async function so callers can await completion if needed.

import { apiGet } from './apiClient.js';

/**
 * Fetches room mapping from get_room_data API and returns a page->room map.
 */
async function generatePageRoomMap() {
  try {
    const data = await apiGet('/api/get_room_data.php');
    if (data.success) {
      const map = {
        room_main: 'room_main',
        shop: 'room_main',
        cart: 'room_main',
        login: 'room_main',
        admin: 'room_main'
      };
      data.data.roomDoors.forEach(room => {
        const key = `room${room.room_number}`;
        map[key] = key;
      });
      return map;
    }
  } catch (err) {
    console.warn('[DynamicBackground] Failed to generate page-room map:', err);
  }
  // fallback basic map
  return {
    room_main: 'room_main',
    shop: 'room_main',
    cart: 'room_main',
    login: 'room_main',
    admin: 'room_main'
  };
}

export async function loadDynamicBackground() {
  try {
    const params = new URLSearchParams(window.location.search);
    const page = params.get('page') || 'landing';
    const map = await generatePageRoomMap();
    const roomType = map[page] || 'landing';

    const bgRes = await apiGet(`/api/get_background.php?room_type=${roomType}`);
    if (!bgRes.success || !bgRes.background) return;

    const { image_filename, webp_filename } = bgRes.background;
    const supportsWebP = document.documentElement.classList.contains('webp');
    const filename = supportsWebP && webp_filename ? webp_filename : image_filename;

    let imageUrl = `images/backgrounds/${filename}`;
    if (!imageUrl.includes('/backgrounds/') && !filename.startsWith('backgrounds/')) {
      imageUrl = `images/${filename}`;
    }

    let container = document.querySelector('.fullscreen-container') || document.getElementById('mainContent');
    if (!container) container = document.body;

    container.style.setProperty('--dynamic-bg-url', `url('${imageUrl}')`);
    container.classList.add('bg-container', 'mode-fullscreen', 'dynamic-bg-loaded');
    document.body.classList.add('dynamic-bg-active');
  } catch (err) {
    console.error('[DynamicBackground] Error loading background:', err);
  }
}

// Auto-run when DOM ready in dev / Vite context
if (document.readyState !== 'loading') {
  loadDynamicBackground();
} else {
  document.addEventListener('DOMContentLoaded', loadDynamicBackground);
}
