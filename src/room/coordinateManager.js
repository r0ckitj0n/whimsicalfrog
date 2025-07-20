// src/room/coordinateManager.js
// ES-module replacement for legacy room-coordinate-manager.js.
// Handles dynamic positioning of clickable areas over room images.

import { apiGet } from '../core/apiClient.js';

function getWrapper() {
  return document.querySelector('.room-overlay-wrapper');
}

function scaleAndPositionAreas(wrapper, imageW, imageH, areas) {
  if (!wrapper) return;
  const w = wrapper.offsetWidth;
  const h = wrapper.offsetHeight;
  if (!w || !h) return;

  const wr = w / h;
  const ir = imageW / imageH;

  let renderW, renderH, offX = 0, offY = 0;
  if (wr > ir) {
    renderH = h;
    renderW = renderH * ir;
    offX = (w - renderW) / 2;
  } else {
    renderW = w;
    renderH = renderW / ir;
    offY = (h - renderH) / 2;
  }

  const sx = renderW / imageW;
  const sy = renderH / imageH;

  areas.forEach(a => {
    const el = wrapper.querySelector(a.selector);
    if (el) {
      el.style.top = `${a.top * sy + offY}px`;
      el.style.left = `${a.left * sx + offX}px`;
      el.style.width = `${a.width * sx}px`;
      el.style.height = `${a.height * sy}px`;
    }
  });
}

async function loadCoordinates(roomType) {
  const res = await apiGet(`/api/get_room_coordinates.php?room_type=${roomType}`);
  if (res.success && Array.isArray(res.coordinates) && res.coordinates.length) {
    return res.coordinates;
  }
  return [];
}

export async function initializeRoomCoordinates({
  roomType,
  originalImageWidth,
  originalImageHeight
}) {
  if (!roomType || !originalImageWidth || !originalImageHeight) return;
  const wrapper = getWrapper();
  if (!wrapper) return;

  const areas = await loadCoordinates(roomType);
  if (!areas.length) return;

  const update = () => scaleAndPositionAreas(wrapper, originalImageWidth, originalImageHeight, areas);
  update();
  let rt;
  window.addEventListener('resize', () => {
    clearTimeout(rt);
    rt = setTimeout(update, 100);
  });
  console.log(`[coordinateManager] placed ${areas.length} areas for ${roomType}`);
}

// Auto-detect globals (used inside legacy room iframes)
if (window.ROOM_TYPE && window.originalImageWidth && window.originalImageHeight) {
  document.addEventListener('DOMContentLoaded', () => {
    initializeRoomCoordinates({
      roomType: window.ROOM_TYPE,
      originalImageWidth: window.originalImageWidth,
      originalImageHeight: window.originalImageHeight
    });
  });
}

// Expose for legacy scripts inside iframe
window.initializeRoomCoordinates = (opts) => initializeRoomCoordinates(opts);
