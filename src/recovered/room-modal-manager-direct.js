/**
 * WhimsicalFrog Room Modal Management - Direct Content Loading Version
 * Simplified version without iframe complexity for testing positioning and background issues
 */

console.log('Loading room-modal-manager-direct.js...');

class RoomModalManagerDirect {
    constructor() {
        this.overlay = null;
        this.content = null;
        this.contentArea = null;
        this.isLoading = false;
        this.currentRoomNumber = null;
        this.roomCache = new Map();

        // Always initialize immediately
        this.init();
    }

    init() {
        console.log('🚪 RoomModalManagerDirect initializing...');
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
            this.contentArea = this.overlay.querySelector('.room-modal-content-area');
            this.overlay.classList.add('z-room-modals');
            return;
        }

        console.log('🚪 Creating new modal overlay structure (direct content).');
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

        // Create content area instead of iframe
        this.contentArea = document.createElement('div');
        this.contentArea.id = 'roomModalContentArea';
        this.contentArea.className = 'room-modal-content-area';

        this.content.appendChild(header);
        this.content.appendChild(loadingSpinner);
        this.content.appendChild(this.contentArea);
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

        // Enable overlay click hiding for consistent modal behavior
        if (this.overlay) {
            this.overlay.addEventListener('click', (event) => {
                if (event.target === this.overlay) {
                    this.hide();
                }
            });
        }

        // ESC key support for consistent modal behavior
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && this.overlay && this.overlay.style.display !== 'none') {
                this.hide();
            }
        });
    }

    show(roomNumber) {
        if (this.isLoading) return;
        this.currentRoomNumber = roomNumber;
        this.isLoading = true;

        console.log('🚪 Showing room modal for room:', roomNumber);

        this.overlay.style.display = 'flex';
        this.overlay.style.opacity = '0';
        this.overlay.offsetHeight; // Force reflow
        this.overlay.style.opacity = '1';

        this.loadRoomContentDirect(roomNumber);
    }

    hide() {
        if (!this.overlay) return;

        console.log('🚪 Hiding room modal.');
        this.isLoading = false;

        this.overlay.style.opacity = '0';

        setTimeout(() => {
            if (this.contentArea) {
                this.contentArea.innerHTML = '';
            }
            this.currentRoomNumber = null;
            this.overlay.style.display = 'none';
        }, 300);
    }

    async loadRoomContentDirect(roomNumber) {
        const loadingSpinner = document.getElementById('roomModalLoading');
        const roomTitleEl = document.getElementById('roomTitle');
        const roomDescriptionEl = document.getElementById('roomDescription');

        if (!this.contentArea || !loadingSpinner || !roomTitleEl || !roomDescriptionEl) {
            console.error('🚪 Modal content elements not found!');
            this.isLoading = false;
            return;
        }

        loadingSpinner.style.display = 'flex';
        this.contentArea.style.opacity = '0';
        this.contentArea.innerHTML = '';

        try {
            const cachedData = await this.getRoomData(roomNumber);
            if (cachedData) {
                console.log(`🚪 Loading room ${roomNumber} content directly (no iframe).`);
                roomTitleEl.textContent = cachedData.metadata.room_name || 'Room';
                roomDescriptionEl.textContent = cachedData.metadata.room_description || '';
                
                // Load content directly into the content area
                this.contentArea.innerHTML = cachedData.content;
                
                // Content loaded successfully
                loadingSpinner.style.display = 'none';
                this.contentArea.style.opacity = '1';
                this.isLoading = false;
                
                console.log('🚪 [DEBUG] Direct content loaded, checking positioning...');
                
                // Check positioning immediately
                setTimeout(() => {
                    this.analyzeDirectContent();
                }, 500);
                
            } else {
                throw new Error('Room content not available in cache.');
            }
        } catch (error) {
            console.error(`🚪 Error loading room ${roomNumber}:`, error);
            roomTitleEl.textContent = 'Error';
            roomDescriptionEl.textContent = 'Could not load room content.';
            loadingSpinner.style.display = 'none';
            this.isLoading = false;
        }
    }

    analyzeDirectContent() {
        const productIcons = this.contentArea.querySelectorAll('.room-product-icon');
        const debugBanner = this.contentArea.querySelector('#debug-items-count');
        
        console.log('🚪 [DEBUG] Direct content analysis:');
        console.log('🚪 [DEBUG] Debug banner found:', !!debugBanner);
        console.log('🚪 [DEBUG] Product icons found:', productIcons.length);
        
        if (debugBanner) {
            const bannerRect = debugBanner.getBoundingClientRect();
            console.log('🚪 [DEBUG] Debug banner position:', bannerRect);
        }
        
        productIcons.forEach((icon, index) => {
            const rect = icon.getBoundingClientRect();
            const computedStyle = getComputedStyle(icon);
            console.log(`🚪 [DEBUG] Direct Icon ${index + 1}:`, {
                className: icon.className,
                inlineStyle: icon.getAttribute('style'),
                computedTop: computedStyle.top,
                computedLeft: computedStyle.left,
                computedPosition: computedStyle.position,
                boundingRect: {
                    top: rect.top,
                    left: rect.left,
                    width: rect.width,
                    height: rect.height
                },
                visible: rect.width > 0 && rect.height > 0,
                debugPosition: icon.dataset.debugPosition
            });
        });
        
        // Check for overlapping
        let overlapping = false;
        for (let i = 0; i < productIcons.length; i++) {
            for (let j = i + 1; j < productIcons.length; j++) {
                const rect1 = productIcons[i].getBoundingClientRect();
                const rect2 = productIcons[j].getBoundingClientRect();
                
                if (Math.abs(rect1.top - rect2.top) < 10 && Math.abs(rect1.left - rect2.left) < 10) {
                    overlapping = true;
                    console.log(`🚪 [DEBUG] ❌ Direct Icons ${i+1} and ${j+1} are overlapping!`);
                }
            }
        }
        
        if (!overlapping) {
            console.log('🚪 [DEBUG] ✅ Direct content icons are properly positioned (not overlapping)');
        }
    }

    // Reuse existing cache methods
    async getRoomData(roomNumber) {
        if (this.roomCache.has(String(roomNumber))) {
            return this.roomCache.get(String(roomNumber));
        }
        return this.preloadSingleRoom(roomNumber);
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
            const response = await fetch(`/api/load_room_content.php?room_number=${roomNumber}&modal=1&v=${Date.now()}`);
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
            console.error(`🚪 Network error preloading room ${roomNumber}:`, error);
            return null;
        }
    }

    async preloadRoomContent() {
        console.log('🚪 Preloading room content...');
        const roomNumbers = [1, 2, 3, 4, 5];
        const preloadPromises = roomNumbers.map(num => this.preloadSingleRoom(num));
        
        try {
            await Promise.allSettled(preloadPromises);
            console.log('🚪 Room content preloading completed.');
        } catch (error) {
            console.error('🚪 Error during room content preloading:', error);
        }
    }
}

// Initialize the direct content room modal manager
document.addEventListener('DOMContentLoaded', () => {
    if (!window.roomModalManager) {
        new RoomModalManagerDirect();
    }
});

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
    // DOM is still loading, wait for DOMContentLoaded
} else {
    // DOM is already loaded
    if (!window.roomModalManager) {
        new RoomModalManagerDirect();
    }
}
