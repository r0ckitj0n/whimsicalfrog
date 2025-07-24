// src/room/roomModalManager.js
// Modern ES-module version of the legacy room-modal system.
// Keeps public API identical while removing direct globals and hard-coded endpoint paths.

import { apiGet } from '../core/apiClient.js';
import { eventBus } from '../core/eventBus.js';

/**
 * WhimsicalFrog RoomModalManager – ES module edition.
 *
 * It is designed to coexist with legacy code during migration. After import,
 * call `initRoomModal()` (or rely on the auto-run at bottom) to attach it to
 * the global `WhimsicalFrog` module registry for backwards-compatibility.
 */
export class RoomModalManager {
  constructor() {
    this.overlay = null;
    this.content = null;
    this.isLoading = false;
    this.currentRoomNumber = null;
    this.roomCache = new Map();
  }

  /** Initialise once DOM is ready */
  init() {
    this.createModalStructure();
    this.setupEventListeners();
    this.preloadRoomContent();
    console.log('[RoomModalManager] initialised');
  }

  /* ---------- DOM STRUCTURE ---------- */
  createModalStructure() {
    if (document.getElementById('roomModalOverlay')) {
      this.overlay = document.getElementById('roomModalOverlay');
      this.content = this.overlay.querySelector('.room-modal-container');
      return;
    }
    this.overlay = document.createElement('div');
    this.overlay.id = 'roomModalOverlay';
    this.overlay.className = 'room-modal-overlay';

    this.content = document.createElement('div');
    this.content.className = 'room-modal-container';

    // Header
    const header = document.createElement('div');
    header.className = 'room-modal-header';

    const backBtnWrap = document.createElement('div');
    backBtnWrap.className = 'back-button-container';
    const backBtn = document.createElement('button');
    backBtn.className = 'room-modal-button';
    backBtn.textContent = '← Back';
    backBtn.onclick = () => this.hide();
    backBtnWrap.appendChild(backBtn);

    const titleOverlay = document.createElement('div');
    titleOverlay.className = 'room-title-overlay';
    titleOverlay.id = 'roomTitleOverlay';

    const roomTitle = document.createElement('h1');
    roomTitle.id = 'roomTitle';
    roomTitle.textContent = 'Loading…';

    const roomDesc = document.createElement('div');
    roomDesc.className = 'room-description';
    roomDesc.id = 'roomDescription';
    titleOverlay.append(roomTitle, roomDesc);

    header.append(backBtnWrap, titleOverlay);

    // Loading spinner
    const loading = document.createElement('div');
    loading.id = 'roomModalLoading';
    loading.className = 'room-modal-loading';
    loading.innerHTML = `<div class="room-modal-spinner"></div><p class="room-modal-loading-text">Loading room…</p>`;

    // Iframe
    const iframe = document.createElement('iframe');
    iframe.id = 'roomModalFrame';
    iframe.className = 'room-modal-frame';
    iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');

    this.content.append(header, loading, iframe);
    this.overlay.appendChild(this.content);
    document.body.appendChild(this.overlay);
  }

  /* ---------- EVENTS ---------- */
  setupEventListeners() {
    document.body.addEventListener('click', evt => {
      const trigger = evt.target.closest('[data-room]');
      if (trigger) {
        evt.preventDefault();
        this.show(trigger.dataset.room);
      }
    });

    this.overlay.addEventListener('click', evt => {
      if (evt.target === this.overlay) this.hide();
    });
  }

  /* ---------- SHOW / HIDE ---------- */
  show(roomNumber) {
    if (this.isLoading) return;
    this.currentRoomNumber = roomNumber;
    this.isLoading = true;

    this.overlay.style.display = 'flex';
    document.body.classList.add('modal-open', 'room-modal-open');

    this.loadRoom(roomNumber);
    setTimeout(() => {
      this.overlay.classList.add('show');
      this.isLoading = false;
    }, 10);
  }

  hide() {
    this.overlay.classList.remove('show');
    document.body.classList.remove('modal-open', 'room-modal-open');

    setTimeout(() => {
      const iframe = document.getElementById('roomModalFrame');
      if (iframe) iframe.src = 'about:blank';
      this.overlay.style.display = 'none';
      this.currentRoomNumber = null;
    }, 250);
  }

  /* ---------- LOAD CONTENT ---------- */
  async loadRoom(roomNumber) {
    const spinner = document.getElementById('roomModalLoading');
    const iframe = document.getElementById('roomModalFrame');
    const titleEl = document.getElementById('roomTitle');
    const descEl = document.getElementById('roomDescription');

    spinner.style.display = 'flex';
    iframe.style.opacity = '0';
    iframe.src = 'about:blank';

    try {
      const data = await this.getRoomData(roomNumber);
      if (!data) throw new Error('Missing room data');

      titleEl.textContent = data.metadata.room_name || 'Room';
      descEl.textContent = data.metadata.room_description || '';
      iframe.srcdoc = data.content;
    } catch (err) {
      console.error(`[RoomModalManager] error loading room ${roomNumber}:`, err);
      titleEl.textContent = 'Error';
      descEl.textContent = 'Could not load content.';
      spinner.style.display = 'none';
      return;
    }

    iframe.onload = () => {
      spinner.style.display = 'none';
      iframe.style.opacity = '1';
      // Emit event for external hooks (e.g., analytics)
      eventBus.emit('roomModalLoaded', { roomNumber });
    };
  }

  /* ---------- DATA ---------- */
  async getRoomData(roomNumber) {
    if (this.roomCache.has(roomNumber)) return this.roomCache.get(roomNumber);
    return this.preloadSingleRoom(roomNumber);
  }

  async preloadRoomContent() {
    try {
      const roomData = await apiGet('/api/get_room_data.php');
      if (!roomData.success || !Array.isArray(roomData.data.productRooms)) return;
      const tasks = roomData.data.productRooms.map(room => this.preloadSingleRoom(room.room_number ?? room));
      await Promise.all(tasks);
    } catch (err) {
      console.warn('[RoomModalManager] preload failed:', err);
    }
  }

  async preloadSingleRoom(roomNumber) {
    if (this.roomCache.has(roomNumber)) return this.roomCache.get(roomNumber);
    try {
      const data = await apiGet(`/api/load_room_content.php?room_number=${roomNumber}&modal=1`);
      if (data.success) {
        this.roomCache.set(roomNumber, data);
        return data;
      }
    } catch (err) {
      console.warn(`[RoomModalManager] failed to preload room ${roomNumber}:`, err);
    }
    return null;
  }
}

// helper to keep legacy global expectations alive during transition
export function initRoomModal() {
  const manager = new RoomModalManager();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => manager.init());
  } else {
    manager.init();
  }
  // expose for legacy access
  window.roomModalManager = manager;

  // bridge into WhimsicalFrog module system if present
  if (window.WhimsicalFrog && typeof window.WhimsicalFrog.addModule === 'function') {
    window.WhimsicalFrog.ready(wf => wf.addModule('RoomModalManager', manager));
  }
}

// auto-run when imported (dev environment).
initRoomModal();
