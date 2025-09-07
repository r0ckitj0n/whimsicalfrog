// src/room/coordinateManager.js
// ES-module replacement for legacy room-coordinate-manager.js.
// Handles dynamic positioning of clickable areas over room images.

import { apiGet } from '../core/apiClient.js';

// Runtime-injected classes for positioned clickable areas
const POS_STYLE_ID = 'wf-room-pos-runtime';
function getPosStyleEl(){
  let el = document.getElementById(POS_STYLE_ID);
  if (!el){ el = document.createElement('style'); el.id = POS_STYLE_ID; document.head.appendChild(el); }
  return el;
}
const posClassCache = new Map(); // key t_l_w_h -> class
function ensurePosClass({top,left,width,height}){
  const t = Math.round(top)||0, l = Math.round(left)||0, w = Math.round(width)||0, h = Math.round(height)||0;
  const key = `${t}_${l}_${w}_${h}`;
  if (posClassCache.has(key)) return posClassCache.get(key);
  const idx = posClassCache.size + 1;
  const cls = `roompos-${idx}`;
  getPosStyleEl().appendChild(document.createTextNode(`.${cls}{position:absolute;top:${t}px;left:${l}px;width:${w}px;height:${h}px;}`));
  posClassCache.set(key, cls);
  return cls;
}

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
      const cls = ensurePosClass({
        top: a.top * sy + offY,
        left: a.left * sx + offX,
        width: a.width * sx,
        height: a.height * sy
      });
      if (el.dataset.posClass && el.dataset.posClass !== cls){
        el.classList.remove(el.dataset.posClass);
      }
      el.classList.add(cls);
      el.dataset.posClass = cls;
    }
  });
}

async function loadCoordinates(roomNumberOrType) {
  let roomParam = roomNumberOrType;
  if (/^room\d+$/i.test(String(roomNumberOrType))) {
    roomParam = String(roomNumberOrType).replace(/^room/i, '');
  }
  const res = await apiGet(`/api/get_room_coordinates.php?room=${encodeURIComponent(roomParam)}`);
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
if (window.roomNumber && window.originalImageWidth && window.originalImageHeight) {
  document.addEventListener('DOMContentLoaded', () => {
    initializeRoomCoordinates({
      roomType: `room${window.roomNumber}`,
      originalImageWidth: window.originalImageWidth,
      originalImageHeight: window.originalImageHeight
    });
  });
}

// Expose for legacy scripts inside iframe
window.initializeRoomCoordinates = (opts) => initializeRoomCoordinates(opts);
