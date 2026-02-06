// src/room/room-modal-manager.ts
// Modern ES-module version of the legacy room-modal system.
// Keeps public API identical while removing direct globals and hard-coded endpoint paths.

import { ApiClient } from '../core/ApiClient.js';
import { eventBus } from '../core/event-bus.js';
import logger from '../core/logger.js';
import { createModalStructure, setupIframeListener } from './room-modal-dom.js';

/**
 * WhimsicalFrog RoomModalManager – ES module edition.
 *
 * It is designed to coexist with legacy code during migration. After import,
 * call `initRoomModal()` (or rely on the auto-run at bottom) to attach it to
 * the global `WhimsicalFrog` module registry for backwards-compatibility.
 */
interface IRoomContentResponse {
  success: boolean;
  content: string;
  metadata?: {
    room_name?: string;
    room_description?: string;
  };
}

interface IRoomListResponse {
  success: boolean;
  data: {
    itemRooms: Array<{ room_number: string | number } | string | number>;
  };
}

interface WFLegacyWindow {
  showModal?: (id: string) => void;
  hideModal?: (id: string) => void;
  WFModals?: {
    lockScroll: () => void;
    unlockScrollIfNoneOpen: () => void;
  };
  hideGlobalPopupImmediate?: () => void;
  showGlobalItemModal?: (sku: string, data?: unknown) => void;
  WhimsicalFrog?: {
    addModule: (name: string, mod: unknown) => void;
    ready: (cb: (wf: WhimsicalFrog) => void) => void;
    GlobalModal?: {
      show: (sku: string, data?: unknown) => void;
    };
  };
  roomModalManager?: RoomModalManager;
}

export class RoomModalManager {
  private overlay: HTMLElement | null = null;
  private content: HTMLElement | null = null;
  private isLoading = false;
  private currentRoomNumber: string | number | null = null;
  private roomCache = new Map<string | number, IRoomContentResponse>();

  constructor() {
    // Initialized in properties
  }

  /** Initialise once DOM is ready */
  init(): void {
    const { overlay, content } = createModalStructure();
    this.overlay = overlay;
    this.content = content;

    this.setupEventListeners();
    this.preloadRoomContent();
    logger.info('[RoomModalManager] initialised');
  }

  /* ---------- EVENTS ---------- */
  private setupEventListeners(): void {
    if (!this.overlay) return;
    this.overlay.addEventListener('click', (evt: MouseEvent) => {
      if (evt.target === this.overlay) this.hide();
    });
  }

  /* ---------- SHOW / HIDE ---------- */
  show(room_number: string | number): void {
    if (this.isLoading || !this.overlay) return;
    this.currentRoomNumber = room_number;
    this.isLoading = true;

    document.body.classList.add('room-modal-open');
    this.loadRoom(room_number);
    setTimeout(() => {
      const win = window as unknown as WFLegacyWindow;
      if (typeof win.showModal === 'function') {
        try { win.showModal('roomModalOverlay'); } catch { /* showModal failed */ }
      } else {
        this.overlay?.classList.add('show');
        if (win.WFModals && typeof win.WFModals.lockScroll === 'function') {
          win.WFModals.lockScroll();
        } else {
          document.body.classList.add('modal-open');
          document.documentElement.classList.add('modal-open');
        }
      }
      this.isLoading = false;
    }, 10);
  }

  /** Alias for show() to match legacy expectations */
  openRoom(room_number: string | number): void {
    this.show(room_number);
  }

  /** Clear cache for a specific room (call when mappings are updated) */
  invalidateRoom(room_number: string | number): void {
    this.roomCache.delete(room_number);
    // Also clear string/number variants
    this.roomCache.delete(String(room_number));
    if (!isNaN(Number(room_number))) {
      this.roomCache.delete(Number(room_number));
    }
    logger.info(`[RoomModalManager] invalidated cache for room ${room_number}`);
  }

  /** Clear all cached room data (call when bulk changes occur) */
  clearCache(): void {
    this.roomCache.clear();
    logger.info('[RoomModalManager] cleared all room cache');
  }

  hide(): void {
    if (!this.overlay) return;
    const win = window as unknown as WFLegacyWindow;
    if (typeof win.hideModal === 'function') {
      try { win.hideModal('roomModalOverlay'); } catch { /* hideModal failed */ }
      document.body.classList.remove('room-modal-open');
    } else {
      this.overlay.classList.remove('show');
      document.body.classList.remove('room-modal-open');
      // Only remove global scroll lock if no other modals are open
      if (win.WFModals && typeof win.WFModals.unlockScrollIfNoneOpen === 'function') {
        win.WFModals.unlockScrollIfNoneOpen();
      } else {
        const anyOpen = document.querySelector(
          '.room-modal-overlay.show, ' +
          '.wf-revealco-overlay.show, ' +
          '#wf-popup-dialog.show, ' +
          '.image-viewer-modal-open, ' +
          '.confirmation-modal-overlay.show, ' +
          '#searchModal.show, ' +
          '.wf-login-overlay.show'
        );
        if (!anyOpen) {
          document.body.classList.remove('modal-open');
          document.documentElement.classList.remove('modal-open');
        }
      }
    }

    setTimeout(() => {
      const iframe = document.getElementById('roomModalFrame') as HTMLIFrameElement;
      if (iframe) iframe.src = 'about:blank';
      this.currentRoomNumber = null;
    }, 250);
  }

  /* ---------- LOAD CONTENT ---------- */
  private async loadRoom(room_number: string | number): Promise<void> {
    const spinner = document.getElementById('roomModalLoading') as HTMLElement;
    const iframe = document.getElementById('roomModalFrame') as HTMLIFrameElement;
    const titleEl = document.getElementById('roomTitle') as HTMLElement;
    const descEl = document.getElementById('roomDescription') as HTMLElement;

    if (!spinner || !iframe || !titleEl || !descEl) return;

    spinner.classList.remove('hidden');
    iframe.classList.add('hidden');
    iframe.src = 'about:blank';

    try {
      const data = await this.getRoomData(room_number);
      if (!data) throw new Error('Missing room data');

      titleEl.textContent = data.metadata?.room_name || 'Room';
      descEl.textContent = data.metadata?.room_description || '';
      iframe.srcdoc = data.content || '';

      setupIframeListener(iframe, (sku, data) => {
        const win = window as unknown as WFLegacyWindow;
        try { win.hideGlobalPopupImmediate && win.hideGlobalPopupImmediate(); } catch { /* Popup hide failed */ }
        if (win.showGlobalItemModal && typeof win.showGlobalItemModal === 'function') {
          win.showGlobalItemModal(sku, data || { sku });
        } else if (win.WhimsicalFrog?.GlobalModal && typeof win.WhimsicalFrog.GlobalModal.show === 'function') {
          win.WhimsicalFrog.GlobalModal.show(sku, data || { sku });
        }
      });

      // Emit event for external hooks (e.g., analytics)
      eventBus.emit('roomModalLoaded', { room_number });
    } catch (err) {
      logger.error(`[RoomModalManager] error loading room ${room_number}:`, err);
      titleEl.textContent = 'Error';
      descEl.textContent = 'Could not load content.';
      spinner.classList.add('hidden');
    }
  }

  /* ---------- DATA ---------- */
  async getRoomData(room_number: string | number): Promise<IRoomContentResponse | null> {
    if (this.roomCache.has(room_number)) return this.roomCache.get(room_number)!;
    return this.preloadSingleRoom(room_number);
  }

  async preloadRoomContent(): Promise<void> {
    try {
      const roomData = await ApiClient.get<IRoomListResponse>('/api/get_room_data.php');
      if (!roomData.success || !Array.isArray(roomData.data?.itemRooms)) return;
      const tasks = roomData.data.itemRooms.map((room) => {
        const rn = (typeof room === 'object' && room !== null)
          ? (room as { room_number: string | number }).room_number
          : room;
        return this.preloadSingleRoom(rn);
      });
      await Promise.all(tasks);
    } catch (err) {
      logger.warn('[RoomModalManager] preload failed:', err);
    }
  }

  async preloadSingleRoom(room_number: string | number): Promise<any | null> {
    if (this.roomCache.has(room_number)) return this.roomCache.get(room_number)!;
    try {
      const data = await ApiClient.get<any>(`/api/load_room_content.php?room_number=${room_number}&modal=1`);
      if (data) {
        // ApiClient unwraps 'data' property
        this.roomCache.set(room_number, data);
        return data;
      }
    } catch (err) {
      logger.warn(`[RoomModalManager] failed to preload room ${room_number}:`, err);
    }
    return null;
  }
}

// helper to keep legacy global expectations alive during transition
export function initRoomModal(): void {
  const manager = new RoomModalManager();
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => manager.init());
  } else {
    manager.init();
  }
  // expose for legacy access
  if (typeof window !== 'undefined') {
    window.roomModalManager = manager;

    // bridge into WhimsicalFrog module system if present
    if (window.WhimsicalFrog && typeof window.WhimsicalFrog.addModule === 'function') {
      window.WhimsicalFrog.ready((wf) => wf.addModule('RoomModalManager', manager));
    }
  }
}

// auto-run when imported (dev environment).
if (typeof window !== 'undefined') {
  initRoomModal();
}
