// src/room/coordinate-manager.ts
// ES-module replacement for legacy room-coordinate-manager.js.
// Handles dynamic positioning of clickable areas over room images.

import { ApiClient } from '../core/ApiClient.js';
import logger from '../core/logger.js';

interface AreaConfig {
  selector: string;
  top: number;
  left: number;
  width: number;
  height: number;
}

interface InitOptions {
  roomType?: string;
  roomNumberStr?: string;
  originalImageWidth: number;
  originalImageHeight: number;
}

// Runtime-injected classes for positioned clickable areas
const POS_STYLE_ID = 'wf-room-pos-runtime';
function getPosStyleEl(): HTMLStyleElement {
  let el = document.getElementById(POS_STYLE_ID) as HTMLStyleElement;
  if (!el) {
    el = document.createElement('style');
    el.id = POS_STYLE_ID;
    document.head.appendChild(el);
  }
  return el;
}

const posClassCache = new Map<string, string>(); // key t_l_w_h -> class
function ensurePosClass({ top, left, width, height }: { top: number; left: number; width: number; height: number }): string {
  const t = Math.round(top) || 0,
    l = Math.round(left) || 0,
    w = Math.round(width) || 0,
    h = Math.round(height) || 0;
  const key = `${t}_${l}_${w}_${h}`;
  if (posClassCache.has(key)) return posClassCache.get(key)!;
  const idx = posClassCache.size + 1;
  const cls = `roompos-${idx}`;
  getPosStyleEl().appendChild(document.createTextNode(`.${cls}{position:absolute;top:${t}px;left:${l}px;width:${w}px;height:${h}px;}`));
  posClassCache.set(key, cls);
  return cls;
}

function getWrapper(): HTMLElement | null {
  return document.querySelector('.room-overlay-wrapper');
}

function scaleAndPositionAreas(wrapper: HTMLElement, imageW: number, imageH: number, areas: AreaConfig[]): void {
  const w = wrapper.offsetWidth;
  const h = wrapper.offsetHeight;
  if (!w || !h) return;

  const wr = w / h;
  const ir = imageW / imageH;

  let renderW: number, renderH: number, offX = 0, offY = 0;
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
    const el = wrapper.querySelector(a.selector) as HTMLElement;
    if (el) {
      const cls = ensurePosClass({
        top: a.top * sy + offY,
        left: a.left * sx + offX,
        width: a.width * sx,
        height: a.height * sy
      });
      if (el.dataset.posClass && el.dataset.posClass !== cls) {
        el.classList.remove(el.dataset.posClass);
      }
      el.classList.add(cls);
      el.dataset.posClass = cls;
    }
  });
}

interface ICoordinateResponse {
  success: boolean;
  coordinates: AreaConfig[];
}

async function loadCoordinates(roomNumberOrType: string): Promise<AreaConfig[]> {
  let roomParam = roomNumberOrType;
  if (/^room\d+$/i.test(String(roomNumberOrType))) {
    roomParam = String(roomNumberOrType).replace(/^room/i, '');
  }
  try {
    const res = await ApiClient.get<ICoordinateResponse>(`/api/get_room_coordinates.php?room=${encodeURIComponent(roomParam)}`);
    if (res.success && Array.isArray(res.coordinates) && res.coordinates.length) {
      return res.coordinates;
    }
  } catch (err) {
    logger.error('[coordinate-manager] Error loading coordinates', err);
  }
  return [];
}

export async function initializeRoomCoordinates({
  roomType, // legacy: 'roomN'
  roomNumberStr, // preferred: 'roomN'
  originalImageWidth,
  originalImageHeight
}: InitOptions): Promise<void> {
  const resolvedRoom = roomNumberStr || roomType;
  if (!resolvedRoom || !originalImageWidth || !originalImageHeight) return;
  const wrapper = getWrapper();
  if (!wrapper) return;

  const areas = await loadCoordinates(resolvedRoom);
  if (!areas.length) return;

  const update = () => scaleAndPositionAreas(wrapper, originalImageWidth, originalImageHeight, areas);
  update();
  let rt: ReturnType<typeof setTimeout>;
  window.addEventListener('resize', () => {
    clearTimeout(rt);
    rt = setTimeout(update, 100);
  });
  logger.info(`[coordinate-manager] placed ${areas.length} areas for ${resolvedRoom}`);
}

// Auto-detect globals (used inside legacy room iframes)
if (typeof window !== 'undefined') {
  if (window.room_number && window.originalImageWidth && window.originalImageHeight) {
    document.addEventListener('DOMContentLoaded', () => {
      initializeRoomCoordinates({
        roomNumberStr: `room${window.room_number}`,
        originalImageWidth: window.originalImageWidth!,
        originalImageHeight: window.originalImageHeight!
      });
    });
  }

  // Expose for legacy scripts inside iframe
  window.initializeRoomCoordinates = (opts: unknown) => initializeRoomCoordinates(opts as InitOptions);
}
