/**
 * Room Coordinates Manager
 * Handles door positioning and coordinate-based functionality for room pages
 */

export class RoomCoordinateManager {
  constructor() {
    this.config = {
      originalImageWidth: 1280,
      originalImageHeight: 896,
      doorCoordinates: []
    };
    this.styleElement = null;
  }

  /**
   * Initialize coordinate manager
   */
  init() {
    this.ensureStyleElement();
    this.bindEvents();
  }

  /**
   * Ensure the coordinate style element exists
   */
  ensureStyleElement() {
    this.styleElement = document.getElementById('wf-main-room-coords');
    if (!this.styleElement) {
      this.styleElement = document.createElement('style');
      this.styleElement.id = 'wf-main-room-coords';
      document.head.appendChild(this.styleElement);
    }
  }

  /**
   * Load coordinates from database
   * @param {string} roomType - Type of room (e.g., 'room_main')
   * @returns {Promise<boolean>} Success status
   */
  async loadCoordinates(roomType = 'room_main') {
    try {
      // Don't try to load coordinates for room_main since it doesn't need them
      if (roomType === 'room_main') {
        console.log('[CoordinateManager] Skipping coordinate load for room_main - not needed');
        return true;
      }
      try { performance.mark('wf:coords:load:start'); } catch(_) {}
      const response = await fetch(`/api/get_room_coordinates.php?room=${encodeURIComponent(roomType)}`);
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      if (data.success && Array.isArray(data.coordinates) && data.coordinates.length) {
        this.config.doorCoordinates = data.coordinates.map((coord) => ({
          ...coord,
          selector: coord.selector.startsWith('.') ? coord.selector : `.${coord.selector}`,
        }));
        try {
          performance.mark('wf:coords:load:end');
          performance.measure('wf:coords:load', 'wf:coords:load:start', 'wf:coords:load:end');
          const m = performance.getEntriesByName('wf:coords:load').pop();
          if (m) console.log(`[Perf] Coordinates loaded for ${roomType} in ${m.duration.toFixed(1)}ms`);
        } catch(_) {}
        return true;
      }

      console.warn(`No active room map found in database for ${roomType}`);
      return false;
    } catch (error) {
      console.error('Error loading room coordinates from database:', error);
      return false;
    }
  }

  /**
   * Apply door coordinates to the DOM
   * @param {string} containerSelector - CSS selector for the container
   */
  applyDoorCoordinates(containerSelector = '#mainRoomPage') {
    const container = document.querySelector(containerSelector);
    if (!container) {
      console.warn('Container not found:', containerSelector);
      return;
    }

    if (!this.config.doorCoordinates || this.config.doorCoordinates.length === 0) {
      console.log('No door coordinates available - skipping application');
      return;
    }
    try { performance.mark('wf:coords:apply:start'); } catch(_) {}
    const rect = container.getBoundingClientRect();
    const scaleX = rect.width / this.config.originalImageWidth;
    const scaleY = rect.height / this.config.originalImageHeight;

    let css = '';
    this.config.doorCoordinates.forEach((coord) => {
      const door = container.querySelector(coord.selector);
      if (!door) {
        console.warn('Door element not found:', coord.selector);
        return;
      }

      const scaledTop = coord.top * scaleY;
      const scaledLeft = coord.left * scaleX;
      const scaledWidth = coord.width * scaleX;
      const scaledHeight = coord.height * scaleY;

      door.classList.add('use-door-vars');
      const selector = `${containerSelector} ${coord.selector}.use-door-vars`;
      css += `${selector}{top:${scaledTop.toFixed(2)}px;left:${scaledLeft.toFixed(2)}px;width:${scaledWidth.toFixed(2)}px;height:${scaledHeight.toFixed(2)}px;}`;
    });

    this.styleElement.textContent = css;
    try {
      performance.mark('wf:coords:apply:end');
      performance.measure('wf:coords:apply', 'wf:coords:apply:start', 'wf:coords:apply:end');
      const m = performance.getEntriesByName('wf:coords:apply').pop();
      if (m) console.log(`[Perf] Coordinates applied in ${m.duration.toFixed(1)}ms for ${this.config.doorCoordinates.length} doors`);
    } catch(_) {}
  }

  /**
   * Set up responsive coordinate updates
   * @param {string} containerSelector - CSS selector for the container
   */
  setupResponsiveCoordinates(containerSelector = '#mainRoomPage') {
    const updateCoordinates = () => {
      this.applyDoorCoordinates(containerSelector);
    };

    // Update on load
    window.addEventListener('load', () => {
      setTimeout(updateCoordinates, 100);
    });

    // Update on resize with debounce
    let resizeTimeout;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(updateCoordinates, 100);
    });

    // Update on orientation change
    window.addEventListener('orientationchange', () => {
      setTimeout(updateCoordinates, 200);
    });
  }

  /**
   * Get coordinates for a specific door
   * @param {string} selector - Door selector
   * @returns {Object|null} Coordinate data or null if not found
   */
  getDoorCoordinates(selector) {
    return this.config.doorCoordinates.find(coord =>
      coord.selector === selector || coord.selector === `.${selector}`
    ) || null;
  }

  /**
   * Add or update door coordinates
   * @param {Object} coordinateData - Coordinate data
   */
  addDoorCoordinate(coordinateData) {
    const existingIndex = this.config.doorCoordinates.findIndex(coord =>
      coord.selector === coordinateData.selector
    );

    if (existingIndex >= 0) {
      this.config.doorCoordinates[existingIndex] = coordinateData;
    } else {
      this.config.doorCoordinates.push(coordinateData);
    }
  }

  /**
   * Remove door coordinates
   * @param {string} selector - Door selector
   */
  removeDoorCoordinate(selector) {
    this.config.doorCoordinates = this.config.doorCoordinates.filter(coord =>
      coord.selector !== selector && coord.selector !== `.${selector}`
    );
  }

  /**
   * Validate coordinate data
   * @param {Object} coord - Coordinate object
   * @returns {boolean} Validation result
   */
  validateCoordinate(coord) {
    return (
      coord &&
      typeof coord.selector === 'string' &&
      typeof coord.top === 'number' &&
      typeof coord.left === 'number' &&
      typeof coord.width === 'number' &&
      typeof coord.height === 'number' &&
      coord.top >= 0 &&
      coord.left >= 0 &&
      coord.width > 0 &&
      coord.height > 0
    );
  }

  /**
   * Scale coordinates for different container sizes
   * @param {Object} coord - Original coordinates
   * @param {number} containerWidth - Container width
   * @param {number} containerHeight - Container height
   * @returns {Object} Scaled coordinates
   */
  scaleCoordinates(coord, containerWidth, containerHeight) {
    const scaleX = containerWidth / this.config.originalImageWidth;
    const scaleY = containerHeight / this.config.originalImageHeight;

    return {
      ...coord,
      top: coord.top * scaleY,
      left: coord.left * scaleX,
      width: coord.width * scaleX,
      height: coord.height * scaleY
    };
  }

  /**
   * Get all door coordinates
   * @returns {Array} Array of door coordinates
   */
  getAllCoordinates() {
    return [...this.config.doorCoordinates];
  }

  /**
   * Clear all coordinates
   */
  clearCoordinates() {
    this.config.doorCoordinates = [];
    if (this.styleElement) {
      this.styleElement.textContent = '';
    }
  }

  /**
   * Bind event handlers
   */
  bindEvents() {
    // Custom event for coordinate updates
    document.addEventListener('roomCoordinates:updated', () => {
      this.applyDoorCoordinates('#mainRoomPage');
    });

    // Custom event for coordinate reload
    document.addEventListener('roomCoordinates:reload', async (event) => {
      const roomType = event.detail?.roomType || 'room_main';
      const success = await this.loadCoordinates(roomType);
      if (success) {
        this.applyDoorCoordinates('#mainRoomPage');
      }
    });
  }

  /**
   * Export coordinates data
   * @returns {Object} Export data
   */
  exportData() {
    return {
      config: { ...this.config },
      exportDate: new Date().toISOString()
    };
  }

  /**
   * Import coordinates data
   * @param {Object} data - Data to import
   */
  importData(data) {
    if (data.config) {
      this.config = { ...data.config };
      this.ensureStyleElement();
      this.applyDoorCoordinates('#mainRoomPage');
    }
  }
}
