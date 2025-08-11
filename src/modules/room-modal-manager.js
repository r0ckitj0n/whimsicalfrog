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
        // Do NOT preload content or show modal during initialization
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
        // Add ARIA attributes for screen reader accessibility
        this.overlay.setAttribute('role', 'dialog');
        this.overlay.setAttribute('aria-modal', 'true');
        this.overlay.setAttribute('aria-labelledby', 'room-modal-title');
        this.overlay.setAttribute('aria-describedby', 'room-modal-content');
        // CRITICAL: Ensure modal starts completely hidden
        this.isVisible = false;
        // Let CSS handle ALL styling - do not override admin settings
        this.overlay.style.display = 'none';

        this.content = document.createElement('div');
        this.content.className = 'room-modal-container';
        // Let CSS handle ALL styling - do not override admin settings

        const header = document.createElement('div');
        header.className = 'room-modal-header';
        header.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background: transparent;
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 10000;
            pointer-events: auto;
        `;

        // Back button (left side)
        const backButton = document.createElement('button');
        backButton.className = 'room-modal-back-btn bg-primary';
        backButton.innerHTML = '← Back to Main Room';
        backButton.style.cssText = `
            align-self: flex-start;

            background-color: var(--button-bg-primary, #87ac3a);
            border: 1px solid var(--button-bg-primary, #87ac3a);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        `;

        // Add click handler to close modal
        backButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.close();
        });

        // Title container (right side)
        const titleContainer = document.createElement('div');
        titleContainer.className = 'room-modal-title-container';
        titleContainer.style.cssText = `
            text-align: right;
            margin-left: auto;
            padding: 8px 16px;
            background: transparent;
            color: white;
        `;
        
        const roomTitle = document.createElement('h2');
        roomTitle.className = 'room-modal-title';
        roomTitle.id = 'room-modal-title';
        roomTitle.textContent = 'Loading...';
        roomTitle.style.cssText = `
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
            color: white;
            text-align: right;
        `;
        titleContainer.appendChild(roomTitle);

        const body = document.createElement('div');
        body.className = 'room-modal-body';
        // Let CSS handle ALL styling - do not override admin settings

        header.appendChild(backButton);
        header.appendChild(titleContainer);
        this.content.appendChild(header);
        this.content.appendChild(body);
        this.overlay.appendChild(this.content);
        document.body.appendChild(this.overlay);
    }

    setupEventListeners() {
        console.log('[Room] Setting up event listeners...');
        
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

        // Room door clicks - with debug logging
        document.addEventListener('click', (e) => {
            console.log('[Room] Click detected on:', e.target);
            const doorLink = e.target.closest('[data-room], .door-link, .room-door');
            console.log('[Room] Door link found:', doorLink);
            
            if (doorLink && !e.defaultPrevented) {
                console.log('[Room] Processing door click...');
                e.preventDefault();
                const roomNumber = doorLink.dataset.room || 
                                 doorLink.href?.match(/room=(\d+)/)?.[1] ||
                                 doorLink.getAttribute('data-room-number');
                
                console.log('[Room] Room number extracted:', roomNumber);
                if (roomNumber) {
                    console.log('[Room] Opening room:', roomNumber);
                    this.openRoom(roomNumber);
                } else {
                    console.log('[Room] No room number found');
                }
            }
        });
        
        console.log('[Room] Event listeners registered successfully');
    }

    async openRoom(roomNumber) {
        if (this.isLoading) {
            console.log('[Room] Already loading, skipping request');
            return;
        }

        console.log(`[Room] Opening room ${roomNumber}`);
        this.currentRoomNumber = roomNumber;
        this.isLoading = true;

        // Show loading state - modal should show when user explicitly clicks a door
        this.show(); // Show modal for user-initiated door click
        this.setContent('<div class="loading">Loading room...</div>');

        try {
            let roomContent = this.roomCache.get(roomNumber);
            
            if (!roomContent) {
                // Fetch room content
                const response = await fetch(`/api/load_room_content.php?room=${roomNumber}&modal=1`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const jsonResponse = await response.json();
                
                if (!jsonResponse.success) {
                    throw new Error(jsonResponse.message || 'Failed to load room content');
                }
                
                roomContent = jsonResponse.content;
                // Update header title once metadata is available
                if (jsonResponse.metadata) {
                    this.updateHeader(jsonResponse.metadata);
                }
                this.roomCache.set(roomNumber, roomContent);
                console.log(`[Room] Cached content for room ${roomNumber}`);
                console.log(`[Room] Room metadata:`, jsonResponse.metadata);
            }

            // Set room content
            this.setContent(roomContent);
            // Remove old title overlay inside modal body if present (we show title in header now)
            const overlayEl = this.overlay.querySelector('.room-title-overlay');
            if (overlayEl) {
                overlayEl.remove();
            }
            // Update header from DOM content (fallback if metadata missing)
            this.updateHeaderFromDOM();
            
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
            // Extract scripts before setting innerHTML
            const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
            const scripts = [];
            let match;
            
            while ((match = scriptRegex.exec(html)) !== null) {
                scripts.push(match[1]); // Extract script content
            }
            
            // Set HTML content
            body.innerHTML = html;
            
            // Execute extracted scripts
            scripts.forEach((scriptContent, index) => {
                try {
                    console.log(`[Room] Executing modal script ${index + 1}`);
                    new Function(scriptContent)();
                } catch (error) {
                    console.error(`[Room] Error executing modal script ${index + 1}:`, error);
                }
            });
            
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

        // Setup hover popups and click handlers for product icons inside the room modal
        const productIcons = body.querySelectorAll('.room-product-icon');
        productIcons.forEach(icon => {
            const sku = icon.dataset.sku;
            if (!sku) return; // Skip if SKU is missing

            // Compose complete item data from dataset for popup/modal usage
            const itemData = {
                sku,
                name: icon.dataset.name,
                price: Number(icon.dataset.price || 0),
                retailPrice: Number(icon.dataset.price || 0),  // Use same as price
                stockLevel: Number(icon.dataset.stockLevel || 0),  // Read from data-stock-level attribute
                category: icon.dataset.category,
                image: icon.dataset.image,
                description: icon.dataset.description || '',
                marketingLabel: icon.dataset.marketingLabel || ''
            };

            let originalTop, originalLeft, originalWidth, originalHeight;

            if (icon.dataset.originalTop !== undefined && icon.dataset.originalTop !== '') {
                originalTop = parseFloat(icon.dataset.originalTop);
                originalLeft = parseFloat(icon.dataset.originalLeft);
                originalWidth = parseFloat(icon.dataset.originalWidth);
                originalHeight = parseFloat(icon.dataset.originalHeight);
            } else {
                // Final fallback: use explicit inline style if available
                const inlineTop = parseFloat(icon.style.top) || 0;
                const inlineLeft = parseFloat(icon.style.left) || 0;
                const inlineWidth = parseFloat(icon.style.width) || 80;
                const inlineHeight = parseFloat(icon.style.height) || 80;
                originalTop = inlineTop;
                originalLeft = inlineLeft;
                originalWidth = inlineWidth;
                originalHeight = inlineHeight;
                // Store in dataset for future
                icon.dataset.originalTop = originalTop;
                icon.dataset.originalLeft = originalLeft;
                icon.dataset.originalWidth = originalWidth;
                icon.dataset.originalHeight = originalHeight;
                console.log(`[Room Modal] Fallback inline coords for icon: top=${originalTop}, left=${originalLeft}, w=${originalWidth}, h=${originalHeight}`);
            }

            // Hover / focus – show popup
            const showPopup = () => {
                console.log('[IconHover] mouseenter on icon', icon, itemData);
                if (typeof window.showGlobalPopup === 'function') {
                    window.showGlobalPopup(icon, itemData);
                } else if (window.parent && typeof window.parent.showGlobalPopup === 'function') {
                    window.parent.showGlobalPopup(icon, itemData);
                }
            };
            const hidePopup = () => {
                console.log('[IconHover] mouseleave on icon', icon);
                if (typeof window.hideGlobalPopup === 'function') {
                    window.hideGlobalPopup();
                } else if (window.parent && typeof window.parent.hideGlobalPopup === 'function') {
                    window.parent.hideGlobalPopup();
                }
            };
            icon.addEventListener('mouseenter', showPopup);
            icon.addEventListener('focus', showPopup);
            icon.addEventListener('mouseleave', hidePopup);
            icon.addEventListener('blur', hidePopup);

            // Click – open full item modal
            icon.addEventListener('click', (e) => {
                console.log('[IconClick] Icon clicked', icon, itemData);
                e.preventDefault();
                e.stopPropagation();
                if (typeof window.showGlobalItemModal === 'function') {
                    window.showGlobalItemModal(sku, itemData);
                } else if (window.parent && typeof window.parent.showGlobalItemModal === 'function') {
                    window.parent.showGlobalItemModal(sku, itemData);
                } else if (window.WhimsicalFrog) {
                    // Fallback event for legacy systems
                    window.WhimsicalFrog.emit('product:modal-requested', {
                        element: icon,
                        sku
                    });
                }
            });
        });

        // Modal-specific background and coordinate scaling (migrated from API inline script)
        const modalPage = this.overlay.querySelector('#modalRoomPage');
        if (modalPage) {
            const rn = modalPage.getAttribute('data-room');
            if (rn) {
                // Maintain legacy globals for compatibility with existing modules
                window.ROOM_TYPE = `room${rn}`;
                window.roomType = `room${rn}`;
                window.roomNumber = rn;
                console.log(`[Room Modal] Set global room variables: ROOM_TYPE=${window.ROOM_TYPE}, roomNumber=${window.roomNumber}`);
            }

            const originalImageWidth = 1280;
            const originalImageHeight = 896;

            const roomWrapper = this.overlay.querySelector('.room-overlay-wrapper');
            const loadRoomBackground = async () => {
                if (!roomWrapper || !rn) return;
                const roomType = `room${rn}`;
                try {
                    const response = await fetch(`/api/get_background.php?room_type=${roomType}`);
                    const data = await response.json();
                    if (data.success && data.background) {
                        const bg = data.background;
                        const supportsWebP = document.documentElement.classList.contains('webp');
                        let filename = supportsWebP && bg.webp_filename ? bg.webp_filename : bg.image_filename;
                        if (!filename.startsWith('backgrounds/')) {
                            filename = `backgrounds/${filename}`;
                        }
                        const imageUrl = `/images/${filename}`;
                        // Prefer CSS variable to let stylesheet control presentation
                        roomWrapper.style.setProperty('--room-bg-image', `url('${imageUrl}')`);
                        // Fallback direct background if CSS variable not used
                        if (!getComputedStyle(roomWrapper).getPropertyValue('--room-bg-image')) {
                            roomWrapper.style.backgroundImage = `url('${imageUrl}')`;
                            roomWrapper.classList.add('dynamic-room-bg-loaded');
                        }
                        console.log(`[Room Modal] Background loaded: ${imageUrl}`);
                    }
                } catch (err) {
                    console.error('[Room Modal] Error loading background:', err);
                }
            };

            const scaleRoomCoordinates = () => {
                if (!roomWrapper) return;
                const wrapperRect = roomWrapper.getBoundingClientRect();
                if (wrapperRect.width === 0 || wrapperRect.height === 0) return;

                // Calculate scale factor using CSS background-size: cover logic
                const scaleX = wrapperRect.width / originalImageWidth;
                const scaleY = wrapperRect.height / originalImageHeight;
                const scale = Math.max(scaleX, scaleY);

                // Center offsets
                const scaledImageWidth = originalImageWidth * scale;
                const scaledImageHeight = originalImageHeight * scale;
                const offsetX = (wrapperRect.width - scaledImageWidth) / 2;
                const offsetY = (wrapperRect.height - scaledImageHeight) / 2;

                const icons = body.querySelectorAll('.room-product-icon');
                icons.forEach(icon => {
                    // Read original coords from dataset or CSS custom props
                    let oTop, oLeft, oWidth, oHeight;
                    if (icon.dataset.originalTop) {
                        oTop = parseFloat(icon.dataset.originalTop);
                        oLeft = parseFloat(icon.dataset.originalLeft);
                        oWidth = parseFloat(icon.dataset.originalWidth);
                        oHeight = parseFloat(icon.dataset.originalHeight);
                    } else {
                        const cs = getComputedStyle(icon);
                        oTop = parseFloat(cs.getPropertyValue('--icon-top')) || parseFloat(icon.style.top) || 0;
                        oLeft = parseFloat(cs.getPropertyValue('--icon-left')) || parseFloat(icon.style.left) || 0;
                        oWidth = parseFloat(cs.getPropertyValue('--icon-width')) || parseFloat(icon.style.width) || 80;
                        oHeight = parseFloat(cs.getPropertyValue('--icon-height')) || parseFloat(icon.style.height) || 80;
                        icon.dataset.originalTop = oTop;
                        icon.dataset.originalLeft = oLeft;
                        icon.dataset.originalWidth = oWidth;
                        icon.dataset.originalHeight = oHeight;
                        console.log(`[Room Modal] Fallback inline coords for icon: top=${oTop}, left=${oLeft}, w=${oWidth}, h=${oHeight}`);
                    }

                    const sTop = Math.round((oTop * scale) + offsetY);
                    const sLeft = Math.round((oLeft * scale) + offsetX);
                    const sWidth = Math.round(oWidth * scale);
                    const sHeight = Math.round(oHeight * scale);

                    // Update CSS variables instead of inline layout properties
                    icon.style.setProperty('--icon-top', sTop + 'px');
                    icon.style.setProperty('--icon-left', sLeft + 'px');
                    icon.style.setProperty('--icon-width', sWidth + 'px');
                    icon.style.setProperty('--icon-height', sHeight + 'px');
                });

                console.log(`[Room Modal] Scaled ${icons.length} product icons with scale factor: ${scale.toFixed(3)}`);
            };

            // Avoid duplicate listeners if content re-initializes
            if (this._resizeHandler) {
                window.removeEventListener('resize', this._resizeHandler);
                this._resizeHandler = null;
            }
            this._resizeHandler = () => {
                clearTimeout(this._resizeTimeout);
                this._resizeTimeout = setTimeout(scaleRoomCoordinates, 100);
            };
            window.addEventListener('resize', this._resizeHandler);

            // Load background and then scale coordinates
            loadRoomBackground().then(() => {
                setTimeout(() => {
                    scaleRoomCoordinates();
                    setTimeout(scaleRoomCoordinates, 500);
                }, 300);
            });
        }
    }

    show() {
        console.log('[Room] 🎭 show() method called');
        console.log('[Room] 🎭 overlay element:', this.overlay);
        console.log('[Room] 🎭 overlay classList before:', this.overlay?.classList.toString());
        
        // Store the previously focused element for restoration later
        this.previouslyFocusedElement = document.activeElement;
        
        this.overlay.style.display = 'flex';
        // Add the .show class required by CSS for visibility
        this.overlay.classList.add('show');
        // Force reflow
        this.overlay.offsetHeight;
        this.overlay.style.opacity = '1';
        // Global scroll lock via centralized helper
        WFModals?.lockScroll?.();
        
        // Set focus to the modal for accessibility
        setTimeout(() => {
            const closeButton = this.overlay.querySelector('.room-modal-close-btn');
            if (closeButton) {
                closeButton.focus();
                console.log('[Room] 🎭 Focus set to close button for accessibility');
            }
        }, 100);
        
        console.log('[Room] 🎭 overlay classList after:', this.overlay.classList.toString());
        console.log('[Room] 🎭 overlay opacity after:', this.overlay.style.opacity);
        console.log('[Room] 🎭 Modal should now be visible with .show class!');
    }

    close() {
        console.log('[Room] 🎭 close() method called');
        console.log('[Room] 🎭 overlay classList before close:', this.overlay?.classList.toString());
        
        // Clean up listeners created during modal content initialization
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
            this._resizeHandler = null;
        }

        this.overlay.style.opacity = '0';
        // Remove the .show class to trigger CSS transition
        this.overlay.classList.remove('show');
        
        console.log('[Room] 🎭 overlay classList after show removal:', this.overlay?.classList.toString());
        
        setTimeout(() => {
            this.overlay.style.display = 'none';
            // Remove scroll lock only if no other modals are open
            WFModals?.unlockScrollIfNoneOpen?.();
            
            // Restore focus to the previously focused element for accessibility
            if (this.previouslyFocusedElement) {
                this.previouslyFocusedElement.focus();
                console.log('[Room] 🎭 Focus restored to previously focused element for accessibility');
                this.previouslyFocusedElement = null;
            }
            
            console.log('[Room] 🎭 Modal fully closed and hidden');
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

    /**
     * Update modal header title and other metadata
     * @param {Object} metadata
     */
    updateHeader(metadata = {}) {
        // Update based on metadata
        const roomTitleEl = this.overlay.querySelector('.room-modal-title');
        if (roomTitleEl) {
            const titleText = metadata.room_name || metadata.category || `Room ${metadata.room_number || ''}`;
            if (titleText) roomTitleEl.textContent = titleText;
        }
    }

    /**
     * Fallback: extract title/description from freshly loaded DOM
     */
    updateHeaderFromDOM() {
        const roomTitleEl = this.overlay.querySelector('.room-modal-title');
        if (!roomTitleEl) return;
        // Look for h3 inside .room-title-overlay within modal body
        const modalBody = this.overlay.querySelector('.room-modal-body');
        const h3 = modalBody?.querySelector('.room-title-overlay h3');
        if (h3 && h3.textContent.trim()) {
            roomTitleEl.textContent = h3.textContent.trim();
        }
    }


}

// Export for ES6 modules
export default RoomModalManager;

// Also expose globally for compatibility
if (typeof window !== 'undefined') {
    window.RoomModalManager = RoomModalManager;
}
