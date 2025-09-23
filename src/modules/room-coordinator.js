/**
 * Room Coordinator
 * Main coordinator that ties together all room-related modules
 */

import { RoomCoordinateManager } from './room-coordinates/coordinate-manager.js';
import { RoomSettingsManager } from './room-settings/room-settings.js';
import { RoomNavigationManager } from './room-navigation/navigation.js';

export class RoomCoordinator {
  constructor() {
    this.coordinateManager = new RoomCoordinateManager();
    this.settingsManager = new RoomSettingsManager();
    this.navigationManager = new RoomNavigationManager();
    this.notificationManager = this.createNotificationManager();
    this.initialized = false;
  }

  /**
   * Initialize the room system
   */
  async init() {
    // Only run on room pages
    if (!this.isRoomPage()) {
      return;
    }

    // Prevent multiple initializations
    if (this.initialized) {
      console.log('[RoomCoordinator] Already initialized, skipping');
      return;
    }

    console.log('[RoomCoordinator] Initializing room system...');

    // Initialize coordinate manager
    this.coordinateManager.init();

    // Load coordinates and apply them (but skip for room_main)
    const coordinatesLoaded = await this.coordinateManager.loadCoordinates('room_main');
    if (coordinatesLoaded) {
      this.coordinateManager.setupResponsiveCoordinates('#mainRoomPage');
      this.coordinateManager.applyDoorCoordinates('#mainRoomPage');
    } else {
      console.log('[RoomCoordinator] No coordinates to apply for room_main');
    }

    // Initialize navigation manager
    this.navigationManager.init();

    // Bind global functions for backward compatibility
    this.exposeGlobals();

    // Bind events
    this.bindEvents();

    this.initialized = true;
    console.log('[RoomCoordinator] Room system initialized');
    window.RoomCoordinator = this; // Expose for debugging
  }

  /**
   * Check if current page is a room page
   * @returns {boolean} True if on a room page
   */
  isRoomPage() {
    const body = document.body;
    const page = (body.dataset && body.dataset.page) || '';
    const path = (body.dataset && body.dataset.path) || window.location.pathname;

    return /room/.test(page) || /room/.test(path) || document.getElementById('mainRoomPage') !== null;
  }

  /**
   * Load main room with coordinates
   */
  async loadMainRoom() {
    // room_main doesn't need coordinates - it's just the navigation page
    console.log('[RoomCoordinator] Main room loaded (no coordinates needed)');
    return true;
  }

  /**
   * Enter a specific room
   * @param {string|number} roomNumber - Room number to enter
   */
  enterRoom(roomNumber) {
    this.navigationManager.enterRoom(roomNumber);
  }

  /**
   * Open room settings modal
   */
  openRoomSettings() {
    this.settingsManager.open();
  }

  /**
   * Close room settings modal
   */
  closeRoomSettings() {
    this.settingsManager.close();
  }

  /**
   * Get current room number
   * @returns {number|null} Current room number or null
   */
  getCurrentRoomNumber() {
    return this.navigationManager.getCurrentRoomNumber();
  }

  /**
   * Check if on main room
   * @returns {boolean} True if on main room
   */
  isOnMainRoom() {
    return this.navigationManager.isOnMainRoom();
  }

  /**
   * Reload coordinates
   * @param {string} roomType - Room type to reload coordinates for
   */
  async reloadCoordinates(roomType = 'room_main') {
    // room_main doesn't need coordinates - it's just the navigation page
    if (roomType === 'room_main') {
      console.log('[RoomCoordinator] Coordinates reload skipped for room_main - not needed');
      return true;
    }

    const success = await this.coordinateManager.loadCoordinates(roomType);
    if (success) {
      this.coordinateManager.applyDoorCoordinates('#mainRoomPage');
      this.dispatchEvent('roomCoordinates:updated');
    }
    return success;
  }

  /**
   * Update door coordinate
   * @param {string} selector - Door selector
   * @param {Object} coordinates - New coordinates
   */
  updateDoorCoordinate(selector, coordinates) {
    this.coordinateManager.addDoorCoordinate({
      selector,
      ...coordinates
    });
    if (this.coordinateManager.getAllCoordinates().length > 0) {
      this.coordinateManager.applyDoorCoordinates('#mainRoomPage');
    }
    this.dispatchEvent('roomCoordinates:updated');
  }

  /**
   * Add custom door
   * @param {Object} doorData - Door data
   */
  addDoor(doorData) {
    if (!this.coordinateManager.validateCoordinate(doorData)) {
      this.notificationManager.showError('Invalid door coordinate data');
      return;
    }

    this.coordinateManager.addDoorCoordinate(doorData);
    if (this.coordinateManager.getAllCoordinates().length > 0) {
      this.coordinateManager.applyDoorCoordinates('#mainRoomPage');
    }
    this.dispatchEvent('roomCoordinates:updated');
  }

  /**
   * Remove door
   * @param {string} selector - Door selector
   */
  removeDoor(selector) {
    this.coordinateManager.removeDoorCoordinate(selector);
    if (this.coordinateManager.getAllCoordinates().length > 0) {
      this.coordinateManager.applyDoorCoordinates('#mainRoomPage');
    }
    this.dispatchEvent('roomCoordinates:updated');
  }

  /**
   * Export room configuration
   * @returns {Object} Room configuration data
   */
  exportConfiguration() {
    return {
      coordinates: this.coordinateManager.getAllCoordinates(),
      navigation: this.navigationManager.getBreadcrumbs(),
      settings: {
        isMainRoom: this.isOnMainRoom(),
        currentRoom: this.getCurrentRoomNumber()
      },
      exportDate: new Date().toISOString()
    };
  }

  /**
   * Import room configuration
   * @param {Object} config - Configuration data
   */
  importConfiguration(config) {
    if (config.coordinates) {
      config.coordinates.forEach(coord => {
        this.coordinateManager.addDoorCoordinate(coord);
      });
      if (this.coordinateManager.getAllCoordinates().length > 0) {
        this.coordinateManager.applyDoorCoordinates('#mainRoomPage');
      }
    }
  }

  /**
   * Bind event handlers
   */
  bindEvents() {
    // Custom events for room system
    document.addEventListener('roomSystem:reload', (event) => {
      const roomType = event.detail?.roomType || 'room_main';
      this.reloadCoordinates(roomType);
    });

    document.addEventListener('roomSystem:navigate', (event) => {
      const roomNumber = event.detail?.roomNumber;
      if (roomNumber) {
        this.enterRoom(roomNumber);
      }
    });

    document.addEventListener('roomSystem:openSettings', () => {
      this.openRoomSettings();
    });

    // Window events
    window.addEventListener('resize', () => {
      if (this.isOnMainRoom() && this.coordinateManager.getAllCoordinates().length > 0) {
        this.coordinateManager.applyDoorCoordinates('#mainRoomPage');
      }
    });

    window.addEventListener('orientationchange', () => {
      setTimeout(() => {
        if (this.isOnMainRoom() && this.coordinateManager.getAllCoordinates().length > 0) {
          this.coordinateManager.applyDoorCoordinates('#mainRoomPage');
        }
      }, 200);
    });
  }

  /**
   * Dispatch custom event
   * @param {string} eventName - Event name
   * @param {Object} detail - Event detail
   */
  dispatchEvent(eventName, detail = {}) {
    const event = new CustomEvent(eventName, { detail });
    document.dispatchEvent(event);
  }

  /**
   * Create notification manager
   * @returns {Object} Notification manager
   */
  createNotificationManager() {
    return {
      showSuccess: (message) => {
        if (typeof window.showSuccess === 'function') {
          return window.showSuccess(message);
        }
        if (typeof window.showToast === 'function') {
          return window.showToast(message, 'success');
        }
        console.log('[SUCCESS]', message);
      },
      showError: (message) => {
        if (typeof window.showError === 'function') {
          return window.showError(message);
        }
        if (typeof window.showToast === 'function') {
          return window.showToast(message, 'error');
        }
        console.error('[ERROR]', message);
      }
    };
  }

  /**
   * Expose global functions for backward compatibility
   */
  exposeGlobals() {
    // Back-compat globals (not used by templates after refactor)
    window.MainRoomConfig = this.coordinateManager.config;
    window.EnhancedRoomSettings = this.settingsManager;
    window.enterRoom = (roomNumber) => this.enterRoom(roomNumber);
    window.openEnhancedRoomSettingsModal = () => this.openRoomSettings();

    // Legacy coordinate functions
    window.loadMainRoomCoordinatesFromDatabase = async () => {
      return await this.coordinateManager.loadCoordinates('room_main');
    };
    window.applyDoorCoordinates = () => {
      this.coordinateManager.applyDoorCoordinates('#mainRoomPage');
    };
    window.setupDoorClicks = () => {
      this.navigationManager.setupDoorClicks();
    };
    window.insertFullscreenLogoutLinkIfNeeded = () => {
      this.navigationManager.insertFullscreenLogoutLinkIfNeeded();
    };
  }
}

// Auto-initialize if DOM is ready
let roomCoordinatorInstance = null;

function initializeRoomCoordinator() {
  if (!roomCoordinatorInstance) {
    roomCoordinatorInstance = new RoomCoordinator();
    roomCoordinatorInstance.init();
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeRoomCoordinator, { once: true });
} else {
  initializeRoomCoordinator();
}
