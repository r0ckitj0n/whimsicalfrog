// src/core/dynamicBackground.js
// Dynamically loads page/room backgrounds using the unified ApiClient.
// Exported as an async function so callers can await completion if needed.

import { apiGet } from './api-client.js';

// Runtime-injected CSS classes for background images
const DYNBG_STYLE_ID = 'wf-dynbg-runtime';
function getDynBgStyleEl(){
  let el = document.getElementById(DYNBG_STYLE_ID);
  if (!el){
    el = document.createElement('style');
    el.id = DYNBG_STYLE_ID;
    document.head.appendChild(el);
  }
  return el;
}

const dynBgClassMap = new Map(); // url -> className
function ensureDynBgClass(imageUrl){
  if (!imageUrl) return null;
  if (dynBgClassMap.has(imageUrl)) return dynBgClassMap.get(imageUrl);
  const idx = dynBgClassMap.size + 1;
  const cls = `dynbg-${idx}`;
  const styleEl = getDynBgStyleEl();
  // Use both CSS var and direct background-image for robustness
  styleEl.appendChild(document.createTextNode(`.${cls}{--dynamic-bg-url:url('${imageUrl}');background-image:url('${imageUrl}');}`));
  dynBgClassMap.set(imageUrl, cls);
  return cls;
}

/**
 * Fetches room mapping from get_room_data API and returns a page->room map.
 */
async function generatePageRoomMap() {
  try {
    const data = await apiGet('/api/get_room_data.php');
    if (data.success) {
      const map = {
        // Only include explicit mappings returned by API; do not invent defaults
      };
      data.data.roomDoors.forEach(room => {
        const key = `room${room.room_number}`;
        map[key] = key;
      });
      return map;
    }
  } catch (err) {
    console.error('[DynamicBackground] Failed to generate page-room map (strict, no fallback):', err);
  }
  // Strict: return empty map; callers must handle absence explicitly
  return {};
}

export async function loadDynamicBackground() {
  try {
    const params = new URLSearchParams(window.location.search);
    const page = params.get('page') || 'landing';
    const map = await generatePageRoomMap();
    const roomNumberStr = map[page] || 'landing';

    // Only fetch backgrounds via API for real rooms (room1..room5)
    if (!/^room\d+$/.test(roomNumberStr)) {
      // Strict: do not attempt to infer backgrounds for non-room pages here
      return;
    }

    const rn = Number(String(roomNumberStr).replace(/^room/i, ''));
    const bgRes = await apiGet(`/api/get_background.php?room=${encodeURIComponent(rn)}`);
    if (!bgRes.success || !bgRes.background) return;

    const { image_filename, webp_filename } = bgRes.background;
    const supportsWebP = document.documentElement.classList.contains('webp');
    const filename = supportsWebP && webp_filename ? webp_filename : image_filename;

    let imageUrl = `/images/backgrounds/${filename}`;
    if (!imageUrl.includes('/backgrounds/') && !filename.startsWith('backgrounds/')) {
      imageUrl = `images/${filename}`;
    }

    let container = document.querySelector('.fullscreen-container') || document.getElementById('mainContent');
    if (!container) container = document.body;

    const bgCls = ensureDynBgClass(imageUrl);
    if (container.dataset.bgClass && container.dataset.bgClass !== bgCls){
      container.classList.remove(container.dataset.bgClass);
    }
    if (bgCls){
      container.classList.add(bgCls);
      container.dataset.bgClass = bgCls;
    }
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
