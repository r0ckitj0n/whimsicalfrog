/**
 * Legacy Room Modal Manager (from backup 2025-09-23)
 * This file is provided to allow A/B testing via the wf_legacy_modal=1 switch.
 */

// Sourced from: /Users/jongraves/Documents/Websites/WhimsicalFrog - Backups/2025-09-23/src/modules/room-modal-manager.js

/**
 * WhimsicalFrog Room Modal Management
 * Handles responsive modal overlays for room content - Vite compatible
 * Recovered and consolidated from legacy files
 */

import { ApiClient } from '../core/api-client.js';

// Runtime-injected style classes (no inline styles or element-level CSS var writes)
const ROOM_MODAL_STYLE_ID = 'room-modal-runtime-classes';
function getRoomModalStyleEl() {
    let el = document.getElementById(ROOM_MODAL_STYLE_ID);
    if (!el) {
        el = document.createElement('style');
        el.id = ROOM_MODAL_STYLE_ID;
        document.head.appendChild(el);
    }
    return el;
}

const roomBgClassMap = new Map(); // url -> className
function ensureRoomBgClass(imageUrl) {
    if (!imageUrl) return null;
    if (roomBgClassMap.has(imageUrl)) return roomBgClassMap.get(imageUrl);
    const idx = roomBgClassMap.size + 1;
    const cls = `room-bg-${idx}`;
    const styleEl = getRoomModalStyleEl();
    styleEl.appendChild(document.createTextNode(`.room-overlay-wrapper.${cls}, .room-modal-body.${cls}{--room-bg-image:url('${imageUrl}');background-image:url('${imageUrl}') !important;background-size:cover;background-position:center;background-repeat:no-repeat;}`));
    roomBgClassMap.set(imageUrl, cls);
    return cls;
}

const iconPosClassMap = new Map(); // key "t-l-w-h" -> className
function ensureIconPosClass(t, l, w, h) {
    const top = Math.max(0, Math.round(Number(t) || 0));
    const left = Math.max(0, Math.round(Number(l) || 0));
    const width = Math.max(1, Math.round(Number(w) || 1));
    const height = Math.max(1, Math.round(Number(h) || 1));
    const key = `${top}-${left}-${width}-${height}`;
    if (iconPosClassMap.has(key)) return iconPosClassMap.get(key);
    const cls = `icon-pos-t${top}-l${left}-w${width}-h${height}`;
    const styleEl = getRoomModalStyleEl();
    styleEl.appendChild(document.createTextNode(`.room-product-icon.${cls}{--icon-top:${top}px;--icon-left:${left}px;--icon-width:${width}px;--icon-height:${height}px;top:${top}px;left:${left}px;width:${width}px;height:${height}px;}`));
    iconPosClassMap.set(key, cls);
    return cls;
}

class RoomModalManager {
    constructor() {
        this.overlay = null;
        this.content = null;
        this.isLoading = false;
        this.currentRoomNumber = null;
        this.roomCache = new Map();
        this._bgMetaCache = new Map(); // roomNumber -> background meta
        
        this.init();
    }

    init() {
        console.log('[RoomModalManager] init() called');
        this.createModalStructure();
        this.setupEventListeners();
        console.log('[RoomModalManager] Modal structure created, overlay:', this.overlay);
        // Do NOT preload content or show modal during initialization
    }

    createModalStructure() {
        // Check if modal overlay already exists
        if (document.getElementById('roomModalOverlay')) {
            this.overlay = document.getElementById('roomModalOverlay');
            this.content = this.overlay.querySelector('.room-modal-container');
            return;
        }

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

        this.content = document.createElement('div');
        this.content.className = 'room-modal-container';
        // Let CSS handle ALL styling - do not override admin settings

        const header = document.createElement('div');
        header.className = 'room-modal-header';

        // Back button (left side)
        const backButton = document.createElement('button');
        backButton.className = 'room-modal-back-btn bg-primary';
        backButton.innerHTML = '← Back to Main Room';

        // Add click handler to close modal
        backButton.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.close();
        });

        // Back button container for positioning
        const backContainer = document.createElement('div');
        backContainer.className = 'back-button-container';
        backContainer.appendChild(backButton);

        // Title container (right side)
        const titleContainer = document.createElement('div');
        titleContainer.className = 'room-modal-title-container';
        // Apply brand primary background + white text
        titleContainer.classList.add('wf-brand-primary-bg');
        
        const roomTitle = document.createElement('h2');
        roomTitle.className = 'room-modal-title wf-brand-font';
        roomTitle.id = 'room-modal-title';
        roomTitle.textContent = 'Loading...';
        titleContainer.appendChild(roomTitle);

        const body = document.createElement('div');
        body.className = 'room-modal-body';
        // Let CSS handle ALL styling - do not override admin settings

        header.appendChild(backContainer);
        header.appendChild(titleContainer);
        // No close button appended
        this.content.appendChild(header);
        this.content.appendChild(body);
        this.overlay.appendChild(this.content);
        document.body.appendChild(this.overlay);
    }

    setupEventListeners() {

        // Close button removed; no listener needed

        // Back button listener already attached during creation; avoid duplicate handlers here

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
                e.stopPropagation();

                const roomNumber = doorLink.dataset.room ||
                                 doorLink.href?.match(/room=(\d+)/)?.[1] ||
                                 doorLink.getAttribute('data-room-number');

                if (roomNumber) {
                    this.openRoom(roomNumber);
                }
            }
        }, true); // Use capture phase to catch events before they bubble

        // Delegated: refresh page action for error state
        document.addEventListener('click', (e) => {
            const refreshBtn = e.target.closest('[data-action="refresh-page"]');
            if (refreshBtn) {
                e.preventDefault();
                window.location.reload();
            }
        });
    }

    async openRoom(roomNumber) {
        if (this.isLoading) return;
        this.currentRoomNumber = roomNumber;
        this.isLoading = true;

        this.show();
        this.setContent('<div class="loading">Loading room...</div>');

        try {
            try { performance.mark('wf:legacyRoom:open:start'); } catch(_) {}
            let roomContent = this.roomCache.get(roomNumber);
            if (!roomContent) {
                try { performance.mark('wf:legacyRoom:fetch:start'); } catch(_) {}
                const json = await ApiClient.get('/api/load_room_content.php', { room: roomNumber, modal: 1, perf: 1 });
                if (!json.success) throw new Error(json.message || 'Failed to load room content');
                roomContent = json.content;
                if (json.metadata) this.updateHeader(json.metadata);
                // Cache background metadata if provided so we can avoid a second API call
                if (json.background) {
                    this._bgMetaCache.set(String(roomNumber), json.background);
                }
                this.roomCache.set(roomNumber, roomContent);
                try {
                    performance.mark('wf:legacyRoom:fetch:end');
                    performance.measure('wf:legacyRoom:fetch', 'wf:legacyRoom:fetch:start', 'wf:legacyRoom:fetch:end');
                    const m = performance.getEntriesByName('wf:legacyRoom:fetch').pop();
                    if (m) console.log(`[Legacy Perf] Room ${roomNumber} content fetched in ${m.duration.toFixed(1)}ms`);
                    // ResourceTiming breakdown if available
                    const entries = performance.getEntriesByType('resource');
                    const rt = entries && entries.find(e => (e.name && e.name.indexOf('/api/load_room_content.php') !== -1));
                    if (rt) {
                        console.log('[Legacy Perf:RT] content', {
                            name: rt.name,
                            dns: (rt.domainLookupEnd - rt.domainLookupStart).toFixed(1),
                            tcp: (rt.connectEnd - rt.connectStart).toFixed(1),
                            ssl: (rt.secureConnectionStart > 0 ? (rt.connectEnd - rt.secureConnectionStart).toFixed(1) : 0),
                            ttfb: (rt.responseStart - rt.requestStart).toFixed(1),
                            download: (rt.responseEnd - rt.responseStart).toFixed(1),
                            total: (rt.responseEnd - rt.startTime).toFixed(1)
                        });
                    }
                } catch(_) {}
            }

            try { performance.mark('wf:legacyRoom:dom:set:start'); } catch(_) {}
            this.setContent(roomContent);
            try {
                performance.mark('wf:legacyRoom:dom:set:end');
                performance.measure('wf:legacyRoom:dom:set', 'wf:legacyRoom:dom:set:start', 'wf:legacyRoom:dom:set:end');
                const m2 = performance.getEntriesByName('wf:legacyRoom:dom:set').pop();
                if (m2) console.log(`[Legacy Perf] Room ${roomNumber} DOM set in ${m2.duration.toFixed(1)}ms`);
            } catch(_) {}
            this.updateHeaderFromDOM();
            if (window.WhimsicalFrog) window.WhimsicalFrog.emit('room:opened', { roomNumber, content: roomContent });
            try {
                performance.mark('wf:legacyRoom:open:end');
                performance.measure('wf:legacyRoom:open:total', 'wf:legacyRoom:open:start', 'wf:legacyRoom:open:end');
                const m3 = performance.getEntriesByName('wf:legacyRoom:open:total').pop();
                if (m3) console.log(`[Legacy Perf] Room ${roomNumber} open total ${m3.duration.toFixed(1)}ms`);
            } catch(_) {}
        } catch (err) {
            console.error(`[Room] Error loading room ${roomNumber}:`, err);
            this.setContent(`
                <div class="error">
                    <h3>Unable to load room ${roomNumber}</h3>
                    <p>Please try again later.</p>
                    <button type="button" data-action="refresh-page">Refresh Page</button>
                </div>
            `);
        } finally {
            this.isLoading = false;
        }
    }

    setContent(html) {
        const body = this.overlay.querySelector('.room-modal-body');
        if (!body) return;
        const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
        const scripts = [];
        let match;
        while ((match = scriptRegex.exec(html)) !== null) scripts.push(match[1]);
        body.innerHTML = html;
        scripts.forEach((code, i) => { try { new Function(code)(); } catch (e) { console.error(`[Room] Error executing modal script ${i + 1}:`, e); } });
        this.initializeModalContent();
    }

    initializeModalContent() {
        const body = this.overlay.querySelector('.room-modal-body');
        if (!body) return;

        // Images error handling
        body.querySelectorAll('img').forEach(img => {
            if (window.setupImageErrorHandling) {
                const sku = img.closest('[data-sku]')?.dataset.sku;
                window.setupImageErrorHandling(img, sku);
            }
        });

        // Add-to-cart buttons
        body.querySelectorAll('.add-to-cart, [data-action="add-to-cart"]').forEach(button => {
            button.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); });
        });

        // Product links -> product modal
        body.querySelectorAll('[data-product], .product-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                if (window.WhimsicalFrog) {
                    window.WhimsicalFrog.emit('product:modal-requested', { element: link, sku: link.dataset.product || link.dataset.sku });
                }
            });
        });

        // Product icons (hover + click)
        body.querySelectorAll('.room-product-icon').forEach(icon => {
            const sku = icon.dataset.sku;
            if (!sku) return;
            const itemData = {
                sku,
                name: icon.dataset.name,
                price: Number(icon.dataset.price || 0),
                retailPrice: Number(icon.dataset.price || 0),
                stockLevel: Number(icon.dataset.stockLevel || 0),
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
                const cs = getComputedStyle(icon);
                originalTop = parseFloat(cs.top) || 0;
                originalLeft = parseFloat(cs.left) || 0;
                originalWidth = parseFloat(cs.width) || 80;
                originalHeight = parseFloat(cs.height) || 80;
                icon.dataset.originalTop = originalTop;
                icon.dataset.originalLeft = originalLeft;
                icon.dataset.originalWidth = originalWidth;
                icon.dataset.originalHeight = originalHeight;
            }

            const showPopup = () => {
                if (typeof window.showGlobalPopup === 'function') window.showGlobalPopup(icon, itemData);
                else if (window.parent && typeof window.parent.showGlobalPopup === 'function') window.parent.showGlobalPopup(icon, itemData);
            };
            const hidePopup = () => {
                if (typeof window.hideGlobalPopup === 'function') window.hideGlobalPopup();
                else if (window.parent && typeof window.parent.hideGlobalPopup === 'function') window.parent.hideGlobalPopup();
            };
            icon.addEventListener('mouseenter', showPopup);
            icon.addEventListener('focus', showPopup);
            icon.addEventListener('mouseleave', hidePopup);
            icon.addEventListener('blur', hidePopup);
            icon.addEventListener('click', (e) => {
                e.preventDefault(); e.stopPropagation();
                if (typeof window.showGlobalItemModal === 'function') window.showGlobalItemModal(sku, itemData);
                else if (window.parent && typeof window.parent.showGlobalItemModal === 'function') window.parent.showGlobalItemModal(sku, itemData);
                else if (window.WhimsicalFrog) window.WhimsicalFrog.emit('product:modal-requested', { element: icon, sku });
            });
        });

        // Background + coordinate scaling
        const modalPage = this.overlay.querySelector('#modalRoomPage');
        if (!modalPage) return;
        const rn = modalPage.getAttribute('data-room');
        if (rn) {
            window.roomNumber = rn;
        }

        const originalImageWidth = 1280;
        const originalImageHeight = 896;
        const roomWrapper = this.overlay.querySelector('.room-overlay-wrapper') || this.overlay.querySelector('.room-modal-body');
        const loadRoomBackground = async () => {
            if (!roomWrapper || !rn) return;
            try {
                let bg = this._bgMetaCache.get(String(rn));
                if (!bg) {
                    const data = await ApiClient.get('/api/get_background.php', { room: rn }).catch(() => null);
                    if (!data || !data.success || !data.background) return;
                    bg = data.background;
                    this._bgMetaCache.set(String(rn), bg);
                }
                if (bg) {
                    // Prefer WebP if API provides it; fall back to PNG if WebP is missing
                    let filename = bg.webp_filename || bg.image_filename;
                    if (!filename.startsWith('backgrounds/')) filename = `backgrounds/${filename}`;
                    const imageUrl = `/images/${filename}`;
                    const bgCls = ensureRoomBgClass(imageUrl);
                    if (roomWrapper.dataset.roomBgClass && roomWrapper.dataset.roomBgClass !== bgCls) roomWrapper.classList.remove(roomWrapper.dataset.roomBgClass);
                    if (bgCls) { roomWrapper.classList.add(bgCls); roomWrapper.dataset.roomBgClass = bgCls; }
                }
            } catch (err) {
                console.error('[Room Modal] Error loading background:', err);
            }
        };

        const scaleRoomCoordinates = () => {
            if (!roomWrapper) return;
            const wrapperRect = roomWrapper.getBoundingClientRect();
            if (wrapperRect.width === 0 || wrapperRect.height === 0) return;
            const scaleX = wrapperRect.width / originalImageWidth;
            const scaleY = wrapperRect.height / originalImageHeight;
            const scale = Math.max(scaleX, scaleY);
            const scaledImageWidth = originalImageWidth * scale;
            const scaledImageHeight = originalImageHeight * scale;
            const offsetX = (wrapperRect.width - scaledImageWidth) / 2;
            const offsetY = (wrapperRect.height - scaledImageHeight) / 2;
            const icons = body.querySelectorAll('.room-product-icon');
            icons.forEach(icon => {
                let oTop, oLeft, oWidth, oHeight;
                if (icon.dataset.originalTop) {
                    oTop = parseFloat(icon.dataset.originalTop);
                    oLeft = parseFloat(icon.dataset.originalLeft);
                    oWidth = parseFloat(icon.dataset.originalWidth);
                    oHeight = parseFloat(icon.dataset.originalHeight);
                } else {
                    const cs = getComputedStyle(icon);
                    oTop = parseFloat(cs.getPropertyValue('--icon-top')) || parseFloat(cs.top) || 0;
                    oLeft = parseFloat(cs.getPropertyValue('--icon-left')) || parseFloat(cs.left) || 0;
                    oWidth = parseFloat(cs.getPropertyValue('--icon-width')) || parseFloat(cs.width) || 80;
                    oHeight = parseFloat(cs.getPropertyValue('--icon-height')) || parseFloat(cs.height) || 80;
                    icon.dataset.originalTop = oTop;
                    icon.dataset.originalLeft = oLeft;
                    icon.dataset.originalWidth = oWidth;
                    icon.dataset.originalHeight = oHeight;
                }
                const sTop = Math.round((oTop * scale) + offsetY);
                const sLeft = Math.round((oLeft * scale) + offsetX);
                const sWidth = Math.round(oWidth * scale);
                const sHeight = Math.round(oHeight * scale);
                const posCls = ensureIconPosClass(sTop, sLeft, sWidth, sHeight);
                if (icon.dataset.iconPosClass && icon.dataset.iconPosClass !== posCls) icon.classList.remove(icon.dataset.iconPosClass);
                icon.classList.add('positioned');
                icon.classList.add(posCls);
                icon.dataset.iconPosClass = posCls;
            });
        };

        if (this._resizeHandler) { window.removeEventListener('resize', this._resizeHandler); this._resizeHandler = null; }
        this._resizeHandler = () => { clearTimeout(this._resizeTimeout); this._resizeTimeout = setTimeout(scaleRoomCoordinates, 100); };
        window.addEventListener('resize', this._resizeHandler);
        loadRoomBackground().then(() => { setTimeout(() => { scaleRoomCoordinates(); setTimeout(scaleRoomCoordinates, 500); }, 300); });
    }

    show() {
        // Store the previously focused element for restoration later
        this.previouslyFocusedElement = document.activeElement;

        // Toggle CSS-driven visibility
        this.overlay.classList.add('show');
        document.body.classList.add('modal-open', 'room-modal-open');

        // Force reflow (optional)
        this.overlay.offsetHeight;
        // Global scroll lock via centralized helper (with fallback)
        if (window.WFModals && typeof window.WFModals.lockScroll === 'function') {
            window.WFModals.lockScroll();
        } else {
            // Fallback scroll lock
            document.documentElement.classList.add('modal-open');
            document.body.classList.add('modal-open');
        }

        // Set focus to the back button for accessibility (close button removed)
        setTimeout(() => {
            const backBtn = this.overlay.querySelector('.room-modal-back-btn');
            if (backBtn) backBtn.focus();
        }, 100);
    }

    close() {
        // Clean up listeners created during modal content initialization
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
            this._resizeHandler = null;
        }

        // Remove the .show class to trigger CSS transition
        this.overlay.classList.remove('show');
        document.body.classList.remove('modal-open', 'room-modal-open');

        setTimeout(() => {
            // Remove scroll lock only if no other modals are open
            if (window.WFModals && typeof window.WFModals.unlockScrollIfNoneOpen === 'function') {
                window.WFModals.unlockScrollIfNoneOpen();
            } else {
                // Fallback unlock
                document.documentElement.classList.remove('modal-open');
                document.body.classList.remove('modal-open');
                console.log('[RoomModalManager] Using fallback scroll unlock');
            }

            // Restore focus to the previously focused element for accessibility
            if (this.previouslyFocusedElement) {
                this.previouslyFocusedElement.focus();
                this.previouslyFocusedElement = null;
            }

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
        return this.overlay && this.overlay.classList.contains('show');
    }

    preloadRoomContent() {
        // Could implement room content preloading here
        // For now, just log that we're ready
    }

    // Public API methods
    getCurrentRoom() {
        return this.currentRoomNumber;
    }

    clearCache() {
        this.roomCache.clear();
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
