/**
 * Room Navigation Manager
 * Handles room navigation and door click functionality
 */

export class RoomNavigationManager {
  constructor() {
    this.doorClickHandlers = new Map();
  }

  /**
   * Initialize room navigation
   */
  init() {
    this.setupDoorClicks();
    this.insertFullscreenLogoutLinkIfNeeded();
    this.bindEvents();
  }

  /**
   * Set up door click handlers
   */
  setupDoorClicks() {
    const doorAreas = document.querySelectorAll('#mainRoomPage .door-area[data-room]');
    doorAreas.forEach((doorElement) => {
      const roomNumber = doorElement.getAttribute('data-room');
      if (roomNumber) {
        doorElement.addEventListener('click', () => {
          this.enterRoom(roomNumber);
        });
        this.doorClickHandlers.set(doorElement, roomNumber);
      }
    });
  }

  /**
   * Navigate to a specific room
   * @param {string|number} roomNumber - Room number to navigate to
   */
  enterRoom(roomNumber) {
    const roomNum = String(roomNumber);
    const url = new URL(window.location.href);

    // Update URL to navigate to room
    url.pathname = '/';
    url.searchParams.set('page', `room${roomNum}`);

    window.location.href = url.toString();
  }

  /**
   * Insert fullscreen logout link if needed
   */
  insertFullscreenLogoutLinkIfNeeded() {
    const container = document.getElementById('mainRoomPage');
    if (!container || !container.classList.contains('fullscreen')) {
      return;
    }

    const existingLogout = document.querySelector('.logout-fullscreen-link');
    if (existingLogout) {
      return; // Already exists
    }

    const logoutLink = document.createElement('a');
    logoutLink.textContent = 'Logout';
    logoutLink.href = '/logout.php';
    logoutLink.className = 'logout-fullscreen-link';
    logoutLink.title = 'Logout from fullscreen mode';

    document.body.appendChild(logoutLink);
  }

  /**
   * Navigate to main room
   */
  goToMainRoom() {
    const url = new URL(window.location.href);
    url.pathname = '/';
    url.searchParams.delete('page');
    window.location.href = url.toString();
  }

  /**
   * Navigate to a specific room by number
   * @param {number} roomNumber - Room number (1-5)
   */
  goToRoom(roomNumber) {
    if (roomNumber < 1 || roomNumber > 5) {
      console.warn('Invalid room number:', roomNumber);
      return;
    }
    this.enterRoom(roomNumber);
  }

  /**
   * Navigate to next room
   * @param {number} currentRoom - Current room number
   */
  goToNextRoom(currentRoom) {
    const nextRoom = currentRoom + 1;
    if (nextRoom > 5) {
      console.log('Already at last room');
      return;
    }
    this.enterRoom(nextRoom);
  }

  /**
   * Navigate to previous room
   * @param {number} currentRoom - Current room number
   */
  goToPreviousRoom(currentRoom) {
    const prevRoom = currentRoom - 1;
    if (prevRoom < 1) {
      this.goToMainRoom();
      return;
    }
    this.enterRoom(prevRoom);
  }

  /**
   * Get current room number from URL
   * @returns {number|null} Current room number or null if not on a room page
   */
  getCurrentRoomNumber() {
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page');

    if (page && /^room\d+$/.test(page)) {
      const roomMatch = page.match(/^room(\d+)$/);
      return roomMatch ? parseInt(roomMatch[1], 10) : null;
    }

    return null;
  }

  /**
   * Check if currently on main room page
   * @returns {boolean} True if on main room page
   */
  isOnMainRoom() {
    const urlParams = new URLSearchParams(window.location.search);
    return !urlParams.has('page') || urlParams.get('page') === '';
  }

  /**
   * Add custom door click handler
   * @param {string} doorSelector - CSS selector for door element
   * @param {Function} handler - Click handler function
   */
  addDoorClickHandler(doorSelector, handler) {
    const doorElement = document.querySelector(doorSelector);
    if (!doorElement) {
      console.warn('Door element not found:', doorSelector);
      return;
    }

    const existingHandler = this.doorClickHandlers.get(doorElement);
    if (existingHandler) {
      doorElement.removeEventListener('click', existingHandler);
    }

    doorElement.addEventListener('click', handler);
    this.doorClickHandlers.set(doorElement, handler);
  }

  /**
   * Remove door click handler
   * @param {string} doorSelector - CSS selector for door element
   */
  removeDoorClickHandler(doorSelector) {
    const doorElement = document.querySelector(doorSelector);
    if (!doorElement) return;

    const handler = this.doorClickHandlers.get(doorElement);
    if (handler) {
      doorElement.removeEventListener('click', handler);
      this.doorClickHandlers.delete(doorElement);
    }
  }

  /**
   * Enable door interaction
   * @param {string} doorSelector - CSS selector for door element
   */
  enableDoor(doorSelector) {
    const doorElement = document.querySelector(doorSelector);
    if (doorElement) {
      doorElement.classList.remove('disabled');
    }
  }

  /**
   * Disable door interaction
   * @param {string} doorSelector - CSS selector for door element
   */
  disableDoor(doorSelector) {
    const doorElement = document.querySelector(doorSelector);
    if (doorElement) {
      doorElement.classList.add('disabled');
    }
  }

  /**
   * Highlight door
   * @param {string} doorSelector - CSS selector for door element
   */
  highlightDoor(doorSelector) {
    const doorElement = document.querySelector(doorSelector);
    if (doorElement) {
      doorElement.classList.add('highlighted');
    }
  }

  /**
   * Remove door highlight
   * @param {string} doorSelector - CSS selector for door element
   */
  removeDoorHighlight(doorSelector) {
    const doorElement = document.querySelector(doorSelector);
    if (doorElement) {
      doorElement.classList.remove('highlighted');
    }
  }

  /**
   * Add hover effects to doors
   * @param {string} doorSelector - CSS selector for door element
   * @param {Object} options - Hover effect options
   */
  addHoverEffect(doorSelector, options = {}) {
    const doorElement = document.querySelector(doorSelector);
    if (!doorElement) return;

    const {
      _scale = 1.05,
      _transition = 'transform 0.2s ease',
      _cursor = 'pointer'
    } = options;

    doorElement.addEventListener('mouseenter', () => {
      doorElement.classList.add('hovered');
    });

    doorElement.addEventListener('mouseleave', () => {
      doorElement.classList.remove('hovered');
    });
  }

  /**
   * Bind event handlers
   */
  bindEvents() {
    // Custom event for room navigation
    document.addEventListener('roomNavigation:navigate', (event) => {
      const roomNumber = event.detail?.roomNumber;
      if (roomNumber) {
        this.enterRoom(roomNumber);
      }
    });

    // Custom event for main room navigation
    document.addEventListener('roomNavigation:goToMain', () => {
      this.goToMainRoom();
    });

    // Keyboard navigation
    document.addEventListener('keydown', (event) => {
      const currentRoom = this.getCurrentRoomNumber();
      if (!currentRoom) return;

      switch (event.key) {
        case 'ArrowLeft':
          event.preventDefault();
          this.goToPreviousRoom(currentRoom);
          break;
        case 'ArrowRight':
          event.preventDefault();
          this.goToNextRoom(currentRoom);
          break;
        case 'Escape':
          event.preventDefault();
          this.goToMainRoom();
          break;
      }
    });
  }

  /**
   * Get navigation breadcrumbs
   * @returns {Array} Navigation breadcrumb data
   */
  getBreadcrumbs() {
    const breadcrumbs = [
      { name: 'Main Room', url: '/', active: this.isOnMainRoom() }
    ];

    const currentRoom = this.getCurrentRoomNumber();
    if (currentRoom) {
      breadcrumbs.push({
        name: `Room ${currentRoom}`,
        url: `/?page=room${currentRoom}`,
        active: true
      });
    }

    return breadcrumbs;
  }

  /**
   * Render navigation breadcrumbs
   * @param {HTMLElement} container - Container element for breadcrumbs
   */
  renderBreadcrumbs(container) {
    if (!container) return;

    const breadcrumbs = this.getBreadcrumbs();
    container.innerHTML = breadcrumbs
      .map((crumb, index) => {
        const isLast = index === breadcrumbs.length - 1;
        const classes = `breadcrumb-item ${isLast ? 'active' : ''}`;

        if (isLast) {
          return `<span class="${classes}">${crumb.name}</span>`;
        } else {
          return `<a href="${crumb.url}" class="${classes}">${crumb.name}</a>`;
        }
      })
      .join(' > ');
  }
}
