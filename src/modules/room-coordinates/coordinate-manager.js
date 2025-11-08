import { ApiClient } from '../../core/api-client.js';
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
      // Map canonical room ids. Support main navigation background as room 0.
      // Accept numbers, 'roomN', 'N', and 'room_main' => '0'
      let apiRoom = roomType;
      if (roomType === 'room_main' || roomType === 'main') apiRoom = '0';
      if (typeof apiRoom === 'number') apiRoom = String(apiRoom);
      if (typeof apiRoom === 'string' && /^room\d+$/i.test(apiRoom)) apiRoom = apiRoom.replace(/^room/i, '');
      if (apiRoom == null || apiRoom === '') apiRoom = '0';
      // Session cache (TTL: 10 minutes)
      const cacheKey = `wf_coords_room_${apiRoom}`;
      try {
        const raw = sessionStorage.getItem(cacheKey);
        if (raw) {
          const parsed = JSON.parse(raw);
          const ttlMs = 10 * 60 * 1000;
          if (parsed && Array.isArray(parsed.coordinates) && parsed.t && (Date.now() - parsed.t) < ttlMs) {
            this.config.doorCoordinates = parsed.coordinates.map((coord) => {
              const sel = String(coord.selector || '').trim();
              const normalized = (/^[.#\[]/.test(sel)) ? sel : (sel ? `.${sel}` : '');
              return { ...coord, selector: normalized };
            });
            return true;
          }
        }
      } catch(_) {}
      try { performance.mark('wf:coords:load:start'); } catch(_) {}
      const data = await ApiClient.get('/api/get_room_coordinates.php', { room: apiRoom });
      const payload = (data && (data.data || data)) || {};
      const coords = Array.isArray(payload.coordinates) ? payload.coordinates : [];
      if (data && data.success && coords.length) {
        this.config.doorCoordinates = coords.map((coord) => {
          const sel = String(coord.selector || '').trim();
          const normalized = (/^[.#\[]/.test(sel)) ? sel : (sel ? `.${sel}` : '');
          return {
            ...coord,
            selector: normalized,
          };
        });
        // Save to session cache
        try { sessionStorage.setItem(cacheKey, JSON.stringify({ t: Date.now(), coordinates: coords })); } catch(_) {}
        try {
          performance.mark('wf:coords:load:end');
          performance.measure('wf:coords:load', 'wf:coords:load:start', 'wf:coords:load:end');
          // Perf marks retained for DevTools; no console logging
        } catch(_) {}
        return true;
      }

      return false;
    } catch (error) {
      // Fallback to cache on error
      try {
        const apiRoom = (roomType === 'room_main' || roomType === 'main') ? '0' : String(roomType);
        const raw = sessionStorage.getItem(`wf_coords_room_${apiRoom}`);
        if (raw) {
          const parsed = JSON.parse(raw);
          if (parsed && Array.isArray(parsed.coordinates)) {
            this.config.doorCoordinates = parsed.coordinates.map((coord) => {
              const sel = String(coord.selector || '').trim();
              const normalized = (/^[.#\[]/.test(sel)) ? sel : (sel ? `.${sel}` : '');
              return { ...coord, selector: normalized };
            });
            return true;
          }
        }
      } catch(_) {}
      // Silent failure; caller should handle gracefully
      return false;
    }
  }

  /**
   * Apply door coordinates to the DOM
   * @param {string} containerSelector - CSS selector for the container
   */
  applyDoorCoordinates(containerSelector = '#mainRoomPage') {
    const container = document.querySelector(containerSelector);
    if (!container) return;

    if (!this.config.doorCoordinates || this.config.doorCoordinates.length === 0) return;
    try { performance.mark('wf:coords:apply:start'); } catch(_) {}
    const rect = container.getBoundingClientRect();
    // If container hasn't been laid out yet, retry on next animation frame
    if (!rect || rect.width < 4 || rect.height < 4) {
      requestAnimationFrame(() => this.applyDoorCoordinates(containerSelector));
      return;
    }
    // Use the same math as backgrounds with background-size: cover; centered
    const scaleX = rect.width / this.config.originalImageWidth;
    const scaleY = rect.height / this.config.originalImageHeight;
    const scale = Math.max(scaleX, scaleY);
    const scaledImageWidth = this.config.originalImageWidth * scale;
    const scaledImageHeight = this.config.originalImageHeight * scale;
    const offsetX = (rect.width - scaledImageWidth) / 2;
    const offsetY = (rect.height - scaledImageHeight) / 2;

    let css = '';
    this.config.doorCoordinates.forEach((coord) => {
      const door = container.querySelector(coord.selector);
      if (!door) return;

      // Coordinates are stored in pixels relative to the original image
      const scaledTop = (coord.top * scale) + offsetY;
      const scaledLeft = (coord.left * scale) + offsetX;
      const scaledWidth = coord.width * scale;
      const scaledHeight = coord.height * scale;

      // Apply inline styles to ensure immediate effect
      const st = door.style;
      st.setProperty('position', 'absolute', 'important');
      st.setProperty('top', `${scaledTop.toFixed(2)}px`, 'important');
      st.setProperty('left', `${scaledLeft.toFixed(2)}px`, 'important');
      st.setProperty('width', `${scaledWidth.toFixed(2)}px`, 'important');
      st.setProperty('height', `${scaledHeight.toFixed(2)}px`, 'important');
      if (!st.zIndex) st.setProperty('z-index', 'var(--z-room-door)');

      // Also emit CSS as a fallback
      door.classList.add('use-door-vars');
      const selector = `${containerSelector} ${coord.selector}.use-door-vars`;
      css += `${selector}{top:${scaledTop.toFixed(2)}px;left:${scaledLeft.toFixed(2)}px;width:${scaledWidth.toFixed(2)}px;height:${scaledHeight.toFixed(2)}px;}`;
    });

    this.styleElement.textContent = css;
    try {
      performance.mark('wf:coords:apply:end');
      performance.measure('wf:coords:apply', 'wf:coords:apply:start', 'wf:coords:apply:end');
      // Perf marks retained for DevTools; no console logging
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

    // Try an immediate pass in case layout is already ready
    requestAnimationFrame(updateCoordinates);

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
