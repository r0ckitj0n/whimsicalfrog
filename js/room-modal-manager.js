/**
<<<<<<< HEAD
 * WhimsicalFrog Room Modal and Popup Management
 * Centralized room functions to eliminate duplication across room files
 * Generated: 2025-07-01 23:22:19
=======
 * WhimsicalFrog Room Modal Management
 * Handles responsive modal overlays for room content.
 * Updated: 2025-07-03 (restructured for stability and clarity)
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
 */

console.log('Loading room-modal-manager.js...');

<<<<<<< HEAD
// Room modal management system
window.RoomModals = window.RoomModals || {};
// updateDetailedModalContent function moved to modal-functions.js for centralization


// Modal close functions (matching the detailed modal component)
function closeDetailedModal() {
    const modal = document.getElementById('detailedItemModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = ''; // Restore scrolling
    }
}


function closeDetailedModalOnOverlay(event) {
    if (event.target === event.currentTarget) {
        closeDetailedModal();
    }
}

console.log('room-modal-manager.js loaded successfully');
=======
class RoomModalManager {
    constructor() {
        this.overlay = null;
        this.content = null;
        this.isLoading = false;
        this.currentRoomNumber = null;
        this.roomCache = new Map();

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }

    init() {
        console.log('🚪 RoomModalManager initializing...');
        this.createModalStructure();
        this.setupEventListeners();
        this.preloadRoomContent();
    }

    createModalStructure() {
        if (document.getElementById('roomModalOverlay')) {
            console.log('🚪 Using existing modal overlay found in DOM.');
            this.overlay = document.getElementById('roomModalOverlay');
            this.content = this.overlay.querySelector('.room-modal-container');
            return;
        }

        console.log('🚪 Creating new modal overlay structure.');
        this.overlay = document.createElement('div');
        this.overlay.id = 'roomModalOverlay';
        this.overlay.className = 'room-modal-overlay';

        this.content = document.createElement('div');
        this.content.className = 'room-modal-container';

        const header = document.createElement('div');
        header.className = 'room-modal-header';

        const backButtonContainer = document.createElement('div');
        backButtonContainer.className = 'back-button-container';

        const backButton = document.createElement('button');
        backButton.className = 'room-modal-button';
        backButton.innerHTML = '← Back';
        backButton.onclick = () => this.hide();
        backButtonContainer.appendChild(backButton);

        const titleOverlay = document.createElement('div');
        titleOverlay.className = 'room-title-overlay';
        titleOverlay.id = 'roomTitleOverlay';

        const roomTitle = document.createElement('h1');
        roomTitle.id = 'roomTitle';
        roomTitle.textContent = 'Loading...';

        const roomDescription = document.createElement('div');
        roomDescription.className = 'room-description';
        roomDescription.id = 'roomDescription';
        roomDescription.textContent = '';

        titleOverlay.appendChild(roomTitle);
        titleOverlay.appendChild(roomDescription);
        header.appendChild(backButtonContainer);
        header.appendChild(titleOverlay);

        const loadingSpinner = document.createElement('div');
        loadingSpinner.id = 'roomModalLoading';
        loadingSpinner.className = 'room-modal-loading';
        loadingSpinner.innerHTML = `
            <div class="room-modal-spinner"></div>
            <p class="room-modal-loading-text">Loading room...</p>
        `;

        const iframe = document.createElement('iframe');
        iframe.id = 'roomModalFrame';
        iframe.className = 'room-modal-frame';
        iframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');

        this.content.appendChild(header);
        this.content.appendChild(loadingSpinner);
        this.content.appendChild(iframe);
        this.overlay.appendChild(this.content);
        document.body.appendChild(this.overlay);
    }

    setupEventListeners() {
        console.log('🚪 Setting up room modal event listeners...');
        document.body.addEventListener('click', (event) => {
            const trigger = event.target.closest('[data-room]');
            if (trigger) {
                event.preventDefault();
                const roomNumber = trigger.dataset.room;
                if (roomNumber) {
                    console.log(`🚪 Room trigger clicked for room: ${roomNumber}`);
                    this.show(roomNumber);
                }
            }
        });

        if (this.overlay) {
            this.overlay.addEventListener('click', (event) => {
                if (event.target === this.overlay) {
                    this.hide();
                }
            });
        }
    }

    show(roomNumber) {
        if (this.isLoading) return;
        this.currentRoomNumber = roomNumber;
        this.isLoading = true;

        console.log('🚪 Showing room modal for room:', roomNumber);

        this.overlay.style.display = 'flex';
        document.body.classList.add('modal-open', 'room-modal-open');
        this.hideLegacyModalElements();

        const pageHeader = document.querySelector('.room-page-header, .site-header');
        if (pageHeader) pageHeader.classList.add('modal-active');

        WhimsicalFrog.ready(wf => {
            const mainApp = wf.getModule('MainApplication');
            if (mainApp) {
                const roomData = this.roomCache.get(String(roomNumber));
                const roomType = (roomData && roomData.metadata && roomData.metadata.room_type) ? roomData.metadata.room_type : `room${roomNumber}`;
                mainApp.loadModalBackground(roomType);
            }
        });

        this.loadRoomContentFast(roomNumber);

        setTimeout(() => {
            this.overlay.classList.add('show');
            this.isLoading = false;
        }, 10);
    }

    hide() {
        if (!this.overlay) return;

        console.log('🚪 Hiding room modal.');
        this.overlay.classList.remove('show');
        document.body.classList.remove('modal-open', 'room-modal-open');
        this.restoreLegacyModalElements();

        const pageHeader = document.querySelector('.room-page-header, .site-header');
        if (pageHeader) pageHeader.classList.remove('modal-active');

        WhimsicalFrog.ready(wf => {
            const mainApp = wf.getModule('MainApplication');
            if (mainApp) {
                mainApp.resetToPageBackground();
            }
        });

        setTimeout(() => {
            const iframe = document.getElementById('roomModalFrame');
            if (iframe) {
                iframe.src = 'about:blank';
            }
            this.currentRoomNumber = null;
            this.overlay.style.display = 'none';
        }, 300);
    }

    async loadRoomContentFast(roomNumber) {
        const loadingSpinner = document.getElementById('roomModalLoading');
        const iframe = document.getElementById('roomModalFrame');
        const roomTitleEl = document.getElementById('roomTitle');
        const roomDescriptionEl = document.getElementById('roomDescription');

        if (!iframe || !loadingSpinner || !roomTitleEl || !roomDescriptionEl) {
            console.error('🚪 Modal content elements not found!');
            this.isLoading = false;
            return;
        }

        loadingSpinner.style.display = 'flex';
        iframe.style.opacity = '0';
        iframe.src = 'about:blank';

        try {
            const cachedData = await this.getRoomData(roomNumber);
            if (cachedData) {
                console.log(`🚪 Loading room ${roomNumber} from cache.`);
                roomTitleEl.textContent = cachedData.metadata.room_name || 'Room';
                roomDescriptionEl.textContent = cachedData.metadata.room_description || '';
                iframe.srcdoc = cachedData.content;
            } else {
                throw new Error('Room content not available in cache.');
            }
        } catch (error) {
            console.error(`🚪 Error loading room ${roomNumber}:`, error);
            roomTitleEl.textContent = 'Error';
            roomDescriptionEl.textContent = 'Could not load room content.';
            loadingSpinner.style.display = 'none';
        }

        iframe.onload = () => {
            // When iframe content loaded, try to initialize coordinate system inside it
            try {
                const iWin = iframe.contentWindow;
                if (iWin && typeof iWin.initializeRoomCoordinates === 'function') {
                    iWin.initializeRoomCoordinates();
                }
            } catch (coordErr) {
                console.warn('⚠️ Unable to initialize coordinates in iframe:', coordErr);
            }
            loadingSpinner.style.display = 'none';
            iframe.style.opacity = '1';
            console.log(`🚪 Room ${roomNumber} content loaded into iframe.`);
        };
    }

    async getRoomData(roomNumber) {
        if (this.roomCache.has(String(roomNumber))) {
            return this.roomCache.get(String(roomNumber));
        }
        return this.preloadSingleRoom(roomNumber);
    }

    async preloadRoomContent() {
        console.log('🚪 Preloading all room content...');
        try {
            const response = await fetch('/api/get_room_data.php');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const roomData = await response.json();

            if (roomData.success && Array.isArray(roomData.data.productRooms)) {
                const preloadPromises = roomData.data.productRooms.map(room => this.preloadSingleRoom(room.room_number));
                await Promise.all(preloadPromises);
                console.log('🚪 All rooms preloaded successfully.');
            } else {
                console.error('🚪 Failed to get room data or invalid format:', roomData.message);
            }
        } catch (error) {
            console.error('🚪 Error preloading rooms:', error);
        }
    }

    async preloadSingleRoom(roomNumber) {
        if (this.roomCache.has(String(roomNumber))) {
            return this.roomCache.get(String(roomNumber));
        }

        try {
            const response = await fetch(`/api/load_room_content.php?room_number=${roomNumber}&modal=1`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const data = await response.json();

            if (data.success) {
                this.roomCache.set(String(roomNumber), {
                    content: data.content,
                    metadata: data.metadata
                });
                console.log(`🚪 Room ${roomNumber} preloaded and cached.`);
                return data;
            } else {
                console.error(`🚪 Failed to preload room ${roomNumber}:`, data.message);
                return null;
            }
        } catch (error) {
            console.error(`🚪 Error preloading room ${roomNumber}:`, error);
            return null;
        }
    }

    hideLegacyModalElements() {
        const legacyModal = document.getElementById('room-container');
        if (legacyModal) legacyModal.style.display = 'none';
    }

    restoreLegacyModalElements() {
        const legacyModal = document.getElementById('room-container');
        if (legacyModal) legacyModal.style.display = ''; // Restore original display
    }
}

// Initialize the modal manager
WhimsicalFrog.ready(wf => {
    wf.addModule('RoomModalManager', new RoomModalManager());
});
>>>>>>> df48c881 (Codebase audit & cleanup: remove unused JS, fix ESLint to 0 errors, add ESLint config, backup removed code under backups/code_removed. Also initialized git repo.)
