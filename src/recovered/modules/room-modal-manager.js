/**
 * WhimsicalFrog Room Modal Management
 * Handles responsive modal overlays for room content - Vite compatible
 * Recovered and consolidated from legacy files
 */

class RoomModalManager {
    constructor() {
        this.overlay = null;
        this.content = null;
        this.isLoading = false;
        this.currentRoomNumber = null;
        this.roomCache = new Map();
        
        this.init();
    }

    init() {
        console.log('[Room] Modal manager initializing...');
        this.createModalStructure();
        this.setupEventListeners();
        this.preloadRoomContent();
    }

    createModalStructure() {
        // Check if modal overlay already exists
        if (document.getElementById('roomModalOverlay')) {
            console.log('[Room] Using existing modal overlay');
            this.overlay = document.getElementById('roomModalOverlay');
            this.content = this.overlay.querySelector('.room-modal-container');
            return;
        }

        console.log('[Room] Creating new modal overlay structure');
        this.overlay = document.createElement('div');
        this.overlay.id = 'roomModalOverlay';
        this.overlay.className = 'room-modal-overlay';
        this.overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 9999;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;

        this.content = document.createElement('div');
        this.content.className = 'room-modal-container';
        this.content.style.cssText = `
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 8px;
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        `;

        const header = document.createElement('div');
        header.className = 'room-modal-header';
        header.style.cssText = `
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
        `;

        const backButton = document.createElement('button');
        backButton.className = 'room-modal-back-btn';
        backButton.innerHTML = '← Back';
        backButton.style.cssText = `
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        `;

        const closeButton = document.createElement('button');
        closeButton.className = 'room-modal-close-btn';
        closeButton.innerHTML = '×';
        closeButton.style.cssText = `
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        `;

        const body = document.createElement('div');
        body.className = 'room-modal-body';
        body.style.cssText = `
            padding: 20px;
            min-height: 300px;
        `;

        header.appendChild(backButton);
        header.appendChild(closeButton);
        this.content.appendChild(header);
        this.content.appendChild(body);
        this.overlay.appendChild(this.content);
        document.body.appendChild(this.overlay);
    }

    setupEventListeners() {
        // Close button
        const closeBtn = this.overlay.querySelector('.room-modal-close-btn');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }

        // Back button
        const backBtn = this.overlay.querySelector('.room-modal-back-btn');
        if (backBtn) {
            backBtn.addEventListener('click', () => this.goBack());
        }

        // Overlay click to close
        this.overlay.addEventListener('click', (e) => {
            if (e.target === this.overlay) {
                this.close();
            }
        });

        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen()) {
                this.close();
            }
        });

        // Room door clicks
        document.addEventListener('click', (e) => {
            const doorLink = e.target.closest('[data-room], .door-link, .room-door');
            if (doorLink && !e.defaultPrevented) {
                e.preventDefault();
                const roomNumber = doorLink.dataset.room || 
                                 doorLink.href?.match(/room=(\d+)/)?.[1] ||
                                 doorLink.getAttribute('data-room-number');
                
                if (roomNumber) {
                    this.openRoom(roomNumber);
                }
            }
        });
    }

    async openRoom(roomNumber) {
        if (this.isLoading) {
            console.log('[Room] Already loading, skipping request');
            return;
        }

        console.log(`[Room] Opening room ${roomNumber}`);
        this.currentRoomNumber = roomNumber;
        this.isLoading = true;

        // Show loading state - only show modal when explicitly requested by user interaction
        // Do NOT auto-show modal on initialization
        this.setContent('<div class="loading">Loading room...</div>');

        try {
            let roomContent = this.roomCache.get(roomNumber);
            
            if (!roomContent) {
                // Fetch room content
                const response = await fetch(`/?room=${roomNumber}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                roomContent = await response.text();
                this.roomCache.set(roomNumber, roomContent);
                console.log(`[Room] Cached content for room ${roomNumber}`);
            }

            // Set room content
            this.setContent(roomContent);
            
            // Emit room opened event
            if (window.WhimsicalFrog) {
                window.WhimsicalFrog.emit('room:opened', { roomNumber, content: roomContent });
            }

        } catch (error) {
            console.error(`[Room] Error loading room ${roomNumber}:`, error);
            this.setContent(`
                <div class="error">
                    <h3>Unable to load room ${roomNumber}</h3>
                    <p>Please try again later.</p>
                    <button onclick="window.location.reload()">Refresh Page</button>
                </div>
            `);
        } finally {
            this.isLoading = false;
        }
    }

    setContent(html) {
        const body = this.overlay.querySelector('.room-modal-body');
        if (body) {
            body.innerHTML = html;
            
            // Initialize any new content
            this.initializeModalContent();
        }
    }

    initializeModalContent() {
        const body = this.overlay.querySelector('.room-modal-body');
        if (!body) return;

        // Setup image error handling for new images
        const images = body.querySelectorAll('img');
        images.forEach(img => {
            if (window.setupImageErrorHandling) {
                const sku = img.closest('[data-sku]')?.dataset.sku;
                window.setupImageErrorHandling(img, sku);
            }
        });

        // Setup any add to cart buttons in the modal
        const addToCartButtons = body.querySelectorAll('.add-to-cart, [data-action="add-to-cart"]');
        addToCartButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // Cart system will handle this via event delegation
            });
        });

        // Setup product modal triggers within room modal
        const productLinks = body.querySelectorAll('[data-product], .product-link');
        productLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                // Handle product modal opening
                if (window.WhimsicalFrog) {
                    window.WhimsicalFrog.emit('product:modal-requested', {
                        element: link,
                        sku: link.dataset.product || link.dataset.sku
                    });
                }
            });
        });
    }

    show() {
        this.overlay.style.display = 'flex';
        // Force reflow
        this.overlay.offsetHeight;
        this.overlay.style.opacity = '1';
        document.body.style.overflow = 'hidden';
    }

    close() {
        this.overlay.style.opacity = '0';
        setTimeout(() => {
            this.overlay.style.display = 'none';
            document.body.style.overflow = '';
        }, 300);

        // Emit room closed event
        if (window.WhimsicalFrog) {
            window.WhimsicalFrog.emit('room:closed', { roomNumber: this.currentRoomNumber });
        }

        this.currentRoomNumber = null;
    }

    goBack() {
        // For now, just close the modal
        // Could implement room history navigation here
        this.close();
    }

    isOpen() {
        return this.overlay && this.overlay.style.display !== 'none';
    }

    preloadRoomContent() {
        // Could implement room content preloading here
        // For now, just log that we're ready
        console.log('[Room] Ready for room content loading');
    }

    // Public API methods
    getCurrentRoom() {
        return this.currentRoomNumber;
    }

    clearCache() {
        this.roomCache.clear();
        console.log('[Room] Cache cleared');
    }

    getCachedRooms() {
        return Array.from(this.roomCache.keys());
    }
}

// Export for ES6 modules
export default RoomModalManager;

// Also expose globally for compatibility
if (typeof window !== 'undefined') {
    window.RoomModalManager = RoomModalManager;
}
