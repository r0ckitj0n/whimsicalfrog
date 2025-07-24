/**
 * WhimsicalFrog Room Modal Management
 * Handles responsive modal overlays for room content.
 * Updated: 2025-07-03 (restructured for stability and clarity)
 */

console.log('Loading room-modal-manager.js...');

class RoomModalManager {
    constructor() {
        this.overlay = null;
        this.content = null;
        this.isLoading = false;
        this.currentRoomNumber = null;
        this.roomCache = new Map();

        // Always initialize immediately
        this.init();
    }

    init() {
        console.log('🚪 RoomModalManager initializing...');
        // Expose manager globally for click handlers
        window.roomModalManager = this;
        this.createModalStructure();
        this.setupEventListeners();
        this.preloadRoomContent();
    }

    createModalStructure() {
        if (document.getElementById('roomModalOverlay')) {
            console.log('🚪 Using existing modal overlay found in DOM.');
            this.overlay = document.getElementById('roomModalOverlay');
            this.content = this.overlay.querySelector('.room-modal-container');
            this.overlay.classList.add('z-room-modals');
            return;
        }

        console.log('🚪 Creating new modal overlay structure.');
        this.overlay = document.createElement('div');
        this.overlay.id = 'roomModalOverlay';
        this.overlay.className = 'room-modal-overlay z-room-modals';

        this.content = document.createElement('div');
        this.content.className = 'room-modal-container';

        const header = document.createElement('div');
        header.className = 'room-modal-header z-room-modal-header';

        const backButtonContainer = document.createElement('div');
        backButtonContainer.className = 'back-button-container';

        const backButton = document.createElement('button');
        backButton.className = 'room-modal-button z-room-buttons';
        backButton.innerHTML = '← Back';
        backButton.onclick = () => this.hide();
        backButtonContainer.appendChild(backButton);

        const titleOverlay = document.createElement('div');
        titleOverlay.className = 'room-title-overlay z-room-modal-header';
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

        // Debug: disable overlay click hiding
        // if (this.overlay) {
        //     this.overlay.addEventListener('click', (event) => {
        //         if (event.target === this.overlay) {
        //             this.hide();
        //         }
        //     });
        // }
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
        // Hide overlay completely to allow underlying interactions
        this.overlay.style.display = 'none';
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
                const htmlDoc = `<!DOCTYPE html>
<html>
<head>
  <base href="${window.location.origin}">
  <link rel="stylesheet" href="/css/bundle.css?v=${window.WF_ASSET_VERSION || Date.now()}">
  <link rel="stylesheet" href="/css/room-iframe.css?v=${window.WF_ASSET_VERSION || Date.now()}">
</head>
<body>
${cachedData.content}
</body>
</html>`;
iframe.srcdoc = htmlDoc;
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
            // Expose global popup & modal functions into iframe context for seamless interaction
            try {
                const iWin = iframe.contentWindow;

                // Inject main bundle into iframe if missing
                if (!iWin.document.getElementById('wf-bundle')) {
                    const script = iWin.document.createElement('script');
                    script.id = 'wf-bundle';
                    script.type = 'text/javascript';
                    script.src = '/js/bundle.js?v=' + (window.WF_ASSET_VERSION || Date.now());
                    iWin.document.head.appendChild(script);
                    console.log('🚪 Injected bundle.js into iframe');
                }

                // Bridge critical global functions
                const bridgeFns = [
                    'showGlobalPopup',
                    'hideGlobalPopup',
                    'showItemDetailsModal',
                    'showGlobalItemModal'
                ];
                bridgeFns.forEach(fnName => {
                    if (typeof window[fnName] === 'function') {
                        iWin[fnName] = window[fnName];
                    }
                });

                // Copy popup state utilities if they exist
                if (window.unifiedPopupSystem) {
                    iWin.unifiedPopupSystem = window.unifiedPopupSystem;
                }

                // Ensure setupPopupEventsAfterPositioning exists in iframe, then run it
                if (typeof iWin.setupPopupEventsAfterPositioning !== 'function') {
                    if (typeof window.setupPopupEventsAfterPositioning === 'function') {
                        iWin.setupPopupEventsAfterPositioning = window.setupPopupEventsAfterPositioning;
                    }
                }
                if (typeof iWin.setupPopupEventsAfterPositioning === 'function') {
                    iWin.setupPopupEventsAfterPositioning();
                }
                if (typeof iWin.attachDelegatedItemEvents === 'function') {
                    iWin.attachDelegatedItemEvents();
                }
            } catch (bridgeErr) {
                console.warn('⚠️ Unable to bridge popup functions into iframe:', bridgeErr);
            }
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
            const rooms = await apiGet('/api/get_rooms.php');

            if (Array.isArray(rooms)) {
                const preloadPromises = rooms.map(room => this.preloadSingleRoom(room.id));
                await Promise.all(preloadPromises);
                console.log('🚪 All rooms preloaded successfully.');
            } else {
                console.error('🚪 Failed to fetch rooms list or invalid format:', rooms);
            }
        } catch (error) {
            console.error('🚪 Error preloading rooms:', error);
        }
    }


    async preloadSingleRoom(roomNumber) {
        const num = parseInt(roomNumber, 10);
        if (!Number.isFinite(num) || num <= 0) {
            console.warn('🚪 Skipping preload for invalid room number:', roomNumber);
            return null;
        }
        if (this.roomCache.has(String(roomNumber))) {
            return this.roomCache.get(String(roomNumber));
        }

        try {
            const data = await apiGet(`/api/load_room_content.php?room_number=${roomNumber}&modal=1`);

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
// Instantiate RoomModalManager to set up modal overlay and event listeners
const roomModalManager = new RoomModalManager();
// Expose globally for direct access
window.roomModalManager = roomModalManager;
// Optionally register with WhimsicalFrog for module system
if (window.WhimsicalFrog && typeof WhimsicalFrog.registerModule === 'function' && typeof WhimsicalFrog.ready === 'function') {
    WhimsicalFrog.ready(wf => {
        wf.addModule('RoomModalManager', roomModalManager);
    });
}
