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
    styleEl.appendChild(document.createTextNode(`.room-overlay-wrapper.${cls}, .room-modal-body.${cls}, .room-modal-container.${cls}{--room-bg-image:url('${imageUrl}');background-image:url('${imageUrl}') !important;background-size:cover;background-position:center;background-repeat:no-repeat;}`));
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
        // Caches for performance
        this._bgMetaCache = new Map(); // roomNumber -> background meta
        this._prefetchedRooms = new Set(); // room numbers we pre-warmed
        this._inflightContent = new Map(); // roomNumber -> Promise
        this._apiBusy = false; // deprecated: do not serialize all requests globally
        this._prewarmStopped = false; // stop flag after first open
        this._inflightByUrl = new Map(); // url -> Promise to coalesce identical requests
        
        // Diagnostics from URL params
        try {
            const p = new URLSearchParams(window.location.search || '');
            this.__diag_no_bg = p.get('wf_diag_no_bg') === '1';
            this.__diag_no_content = p.get('wf_diag_no_content') === '1';
        } catch (_) { this.__diag_no_bg = false; this.__diag_no_content = false; }

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
            } else if (this.__diag_no_content && !roomContent) {
                console.warn('[RoomModalManager][DIAG] wf_diag_no_content=1 active: skipping content API');
                roomContent = `<div class="room-modal-body-inner"><p>DIAG MODE: content fetch skipped.</p></div>`;
            }
        });

        // Escape key to close
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen()) {
                this.close();
            }
        });

        // Lightweight fetch coalescer: if same URL is already in-flight, await it
        const _coalescedFetch = async (url, opts) => {
            if (this._inflightByUrl.has(url)) {
                return this._inflightByUrl.get(url);
            }
            const p = ApiClient.request(url, opts || {}).finally(() => { this._inflightByUrl.delete(url); });
            this._inflightByUrl.set(url, p);
            return p;
        };

        const prefetchBg = async (roomNumber) => {
            try {
                if (!roomNumber) return;
                if (this._prefetchedRooms.has(String(roomNumber))) return;
                // Fetch metadata and warm the image cache
                const j = await _coalescedFetch(`/api/get_background.php?room=${encodeURIComponent(roomNumber)}`)
                  .catch(() => null);
                if (!j || !j.success || !j.background) return;
                this._bgMetaCache.set(String(roomNumber), j.background);
                let filename = j.background.webp_filename || j.background.image_filename;
                if (!filename) return;
                if (!filename.startsWith('backgrounds/')) filename = `backgrounds/${filename}`;
                const url = `/images/${filename}`;
                // Create CSS class (also warms style path)
                ensureRoomBgClass(url);
                // Warm image in memory cache
                const img = new Image();
                img.src = url;
                this._prefetchedRooms.add(String(roomNumber));
            } catch (_) {}
        };

        // Prefetch room content on hover as well (warm first API)
        const prefetchContent = async (roomNumber) => {
            try {
                if (!roomNumber) return;
                if (this.roomCache.has(String(roomNumber))) return;
                if (this._inflightContent.has(String(roomNumber))) return;
                const p = (async () => {
                    const j = await ApiClient.get('/api/load_room_content.php', { room: roomNumber, modal: 1, perf: 1 }).catch(() => null);
                    if (j && j.success && j.content) {
                        this.roomCache.set(String(roomNumber), j.content);
                    }
                })().finally(() => { this._inflightContent.delete(String(roomNumber)); });
                this._inflightContent.set(String(roomNumber), p);
                await p;
            } catch (_) {}
        };

        // Door hover prefetch gated behind flag to avoid noisy first-load network
        const PREFETCH_ON_HOVER = false;
        if (PREFETCH_ON_HOVER) {
            document.addEventListener('pointerenter', (e) => {
                const doorEl = e.target.closest && e.target.closest('[data-room], .room-door, .door-area, [data-room-number]');
                if (!doorEl) return;
                const rn = doorEl.dataset?.room || doorEl.getAttribute('data-room-number');
                if (rn) { prefetchBg(rn); prefetchContent(rn); }
            }, { passive: true, capture: true });
        }

        // Expose public prefetch method for app.js global warmup
        this.prefetchRoom = async (roomNumber) => {
            await Promise.all([
                prefetchBg(roomNumber),
                prefetchContent(roomNumber)
            ]).catch(() => {});
        };

        // Expose bulk prewarm for visible doors (limited & staggered)
        this.prewarmVisibleDoors = async () => {
            try {
                const doors = Array.from(document.querySelectorAll('[data-room], .room-door'));
                const rooms = Array.from(new Set(doors.map(d => d.dataset?.room || d.getAttribute('data-room-number')).filter(Boolean)));
                // Limit to first 2 rooms, stagger to avoid queueing
                const targets = rooms.slice(0, 2);
                for (const r of targets) {
                    if (this._prewarmStopped) break;
                    await this.prefetchRoom(r);
                    await new Promise(res => setTimeout(res, 300));
                }
            } catch(_) {}
        };

        // Room door clicks (capture-phase). Prevent triggering inside the open modal content.
        document.addEventListener('click', (e) => {
            try {
                const inOpenModal = !!(e.target.closest && e.target.closest('.room-modal-overlay.show'));
                if (inOpenModal) return; // Never treat clicks inside open modal as door clicks
                // Only treat as a door click when clicking true door elements. Exclude #modalRoomPage.
                const doorLink = e.target.closest && e.target.closest('.room-door, .door-area, .door-link, [data-room-number], a[data-room]');
                // If inside an open modal, ignore generic [data-room] containers like #modalRoomPage
                if (inOpenModal && (!doorLink || doorLink.id === 'modalRoomPage')) return;
                if (!doorLink) return;

                // Diagnostics
                try {
                    const tgt = e.target && (e.target.id || e.target.className || e.target.nodeName);
                    console.log('[RoomModalManager] Door click detected (delegated)', { target: tgt, prevented: e.defaultPrevented });
                } catch(_) {}

                e.preventDefault();
                // Stop propagation to avoid duplicate opens if other handlers exist
                if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();

                const roomNumber = doorLink.dataset?.room ||
                                   (doorLink.href && doorLink.href.match(/room=(\d+)/)?.[1]) ||
                                   doorLink.getAttribute('data-room-number');

                console.log('[RoomModalManager] Resolved room number:', roomNumber, 'from element:', doorLink);
                if (roomNumber) {
                    this.openRoom(roomNumber);
                }
            } catch(err) {
                console.warn('[RoomModalManager] Delegated door click handler error', err);
            }
        }, false); // Bubble phase to allow inner handlers to stop propagation first

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
        const key = String(roomNumber);
        this.currentRoomNumber = key;
        this.isLoading = true;

        this.show();
        this.setContent('<div class="loading">Loading room...</div>');

        try {
            try { performance.mark('wf:roomModal:open:start'); } catch(_) {}
            // If a prefetch is in flight for this room, await it first
            if (this._inflightContent.has(key)) {
                try { await this._inflightContent.get(key); } catch(_) {}
            }
            let roomContent = this.roomCache.get(key);
            if (!roomContent && !this.__diag_no_content) {
                try { performance.mark('wf:roomModal:fetch:start'); } catch(_) {}
                const tNavStart = performance.timeOrigin || (performance.timing && performance.timing.navigationStart) || 0;
                const tFetchStart = performance.now();
                const resp = await ApiClient.get('/api/load_room_content.php', { room: key, modal: 1, perf: 1 });
                // Accept both JSON and HTML string responses (defensive for prod)
                if (typeof resp === 'string') {
                    roomContent = resp;
                } else {
                    if (!resp.success) throw new Error(resp.message || 'Failed to load room content');
                    roomContent = resp.content;
                    if (resp.metadata) this.updateHeader(resp.metadata);
                    // Cache background metadata if provided to avoid extra API call
                    if (resp.background) {
                        this._bgMetaCache.set(key, resp.background);
                    }
                }
                this.roomCache.set(key, roomContent);
                try {
                    performance.mark('wf:roomModal:fetch:end');
                    performance.measure('wf:roomModal:fetch', 'wf:roomModal:fetch:start', 'wf:roomModal:fetch:end');
                    const m = performance.getEntriesByName('wf:roomModal:fetch').pop();
                    if (m) console.log(`[Perf] Room ${key} content fetched in ${m.duration.toFixed(1)}ms`);
                    // ResourceTiming breakdown if available
                    const entries = performance.getEntriesByType('resource');
                    const rt = entries && entries.find(e => (e.name && e.name.indexOf('/api/load_room_content.php') !== -1));
                    if (rt) {
                        console.log('[Perf:RT] content', {
                            name: rt.name,
                            dns: (rt.domainLookupEnd - rt.domainLookupStart).toFixed(1),
                            tcp: (rt.connectEnd - rt.connectStart).toFixed(1),
                            ssl: (rt.secureConnectionStart > 0 ? (rt.connectEnd - rt.secureConnectionStart).toFixed(1) : 0),
                            ttfb: (rt.responseStart - rt.requestStart).toFixed(1),
                            download: (rt.responseEnd - rt.responseStart).toFixed(1),
                            total: (rt.responseEnd - rt.startTime).toFixed(1)
                        });
                    } else {
                        const tFetchEnd = performance.now();
                        console.log('[Perf:Approx] content', { totalMs: (tFetchEnd - tFetchStart).toFixed(1), sinceNavMs: (tFetchEnd + tNavStart - tNavStart).toFixed(1) });
                    }
                } catch(_) {}
            }

            try { performance.mark('wf:roomModal:dom:set:start'); } catch(_) {}
            this.setContent(roomContent);
            try {
                performance.mark('wf:roomModal:dom:set:end');
                performance.measure('wf:roomModal:dom:set', 'wf:roomModal:dom:set:start', 'wf:roomModal:dom:set:end');
                const m = performance.getEntriesByName('wf:roomModal:dom:set').pop();
                if (m) console.log(`[Perf] Room ${roomNumber} DOM set in ${m.duration.toFixed(1)}ms`);
            } catch(_) {}
            this.updateHeaderFromDOM();
            if (window.WhimsicalFrog) window.WhimsicalFrog.emit('room:opened', { roomNumber, content: roomContent });
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
            try {
                performance.mark('wf:roomModal:open:end');
                performance.measure('wf:roomModal:open', 'wf:roomModal:open:start', 'wf:roomModal:open:end');
                const m = performance.getEntriesByName('wf:roomModal:open').pop();
                if (m) console.log(`[Perf] Room ${roomNumber} open total ${m.duration.toFixed(1)}ms`);
            } catch(_) {}
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

        // Delegated events for product icons (hover + click) to avoid many listeners
        const getIconData = (icon) => {
            const sku = icon?.dataset?.sku;
            if (!sku) return null;
            return {
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
        };

        const onOver = (e) => {
            const icon = e.target.closest && e.target.closest('.room-product-icon');
            if (!icon || !body.contains(icon)) return;
            const data = getIconData(icon);
            if (!data) return;
            // Cancel any scheduled hide as pointer is back over an icon
            try {
                if (typeof window.cancelHideGlobalPopup === 'function') window.cancelHideGlobalPopup();
                else if (window.parent && typeof window.parent.cancelHideGlobalPopup === 'function') window.parent.cancelHideGlobalPopup();
            } catch (_) {}
            if (typeof window.showGlobalPopup === 'function') window.showGlobalPopup(icon, data);
            else if (window.parent && typeof window.parent.showGlobalPopup === 'function') window.parent.showGlobalPopup(icon, data);
            // Keep popup visible when hovering either the icon or the popup
            try { attachPopupPersistence(icon); } catch (_) {}
        };
        const onOut = (e) => {
            const related = e.relatedTarget;
            const fromIcon = e.target.closest && e.target.closest('.room-product-icon');
            const stillInsideIcon = related && (related === fromIcon || (related.closest && related.closest('.room-product-icon') === fromIcon));
            if (stillInsideIcon) return;

            // If moving towards the popup, allow a longer grace period so popup can cancel the hide on mouseenter
            const scheduleHide = (win) => {
                if (win && typeof win.scheduleHideGlobalPopup === 'function') win.scheduleHideGlobalPopup(500);
                else if (win && typeof win.hideGlobalPopup === 'function') win.hideGlobalPopup();
            };

            if (typeof window.scheduleHideGlobalPopup === 'function' || typeof window.hideGlobalPopup === 'function') {
                scheduleHide(window);
                return;
            }
            if (window.parent && (typeof window.parent.scheduleHideGlobalPopup === 'function' || typeof window.parent.hideGlobalPopup === 'function')) {
                scheduleHide(window.parent);
                return;
            }
        };
        // Avoid double-binding hover popup handlers if the delegated system is active
        const hasDelegated = !!(document.body && document.body.hasAttribute('data-wf-room-delegated-listeners'));
        if (!hasDelegated) {
            body.addEventListener('mouseover', onOver);
            body.addEventListener('mouseout', onOut);
            body.addEventListener('focusin', onOver);
            body.addEventListener('focusout', onOut);
        } else {
            console.log('[RoomModalManager] Skipping hover popup listeners; delegated event-manager is active');
        }
        body.addEventListener('click', (e) => {
            const target = e.target;
            if (!target || typeof target.closest !== 'function') return;
            const trigger = target.closest('.room-product-icon, [data-sku], [data-product], img[data-sku], img[data-product]');
            if (!trigger) return;
            e.preventDefault();
            if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();
            // Hide hover popup before opening modal (cover both iframe/top contexts)
            try { window.hideGlobalPopupImmediate && window.hideGlobalPopupImmediate(); } catch(_) {}
            try { parent && parent.hideGlobalPopupImmediate && parent.hideGlobalPopupImmediate(); } catch(_) {}
            const icon = trigger.closest('.room-product-icon') || trigger;
            const data = getIconData(icon) || (icon.dataset?.sku ? { sku: icon.dataset.sku } : null);
            const sku = data?.sku || icon.dataset?.sku;
            if (!sku) return;
            if (typeof window.showGlobalItemModal === 'function') window.showGlobalItemModal(sku, data || { sku });
            else if (window.parent && typeof window.parent.showGlobalItemModal === 'function') window.parent.showGlobalItemModal(sku, data || { sku });
            else if (window.WhimsicalFrog) window.WhimsicalFrog.emit('product:modal-requested', { element: icon, sku });
        }, true);

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
                if (this.__diag_no_bg) { console.warn('[RoomModalManager][DIAG] wf_diag_no_bg=1 active: skipping background API'); return; }
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
                    // Also apply to the modal container to guarantee full-cover background
                    const container = this.overlay.querySelector('.room-modal-container');
                    if (container) {
                        if (container.dataset.roomBgClass && container.dataset.roomBgClass !== bgCls) container.classList.remove(container.dataset.roomBgClass);
                        if (bgCls) { container.classList.add(bgCls); container.dataset.roomBgClass = bgCls; }
                    }
                    // ResourceTiming breakdown if available
                    try {
                        const entries = performance.getEntriesByType('resource');
                        const rt = entries && entries.find(e => (e.name && e.name.indexOf('/api/get_background.php') !== -1 && e.name.indexOf(`room=${encodeURIComponent(rn)}`) !== -1));
                        if (rt) {
                            console.log('[Perf:RT] background', {
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
        const bgStart = performance.now();
        loadRoomBackground().then(() => {
            const bgDur = performance.now() - bgStart;
            console.log(`[Perf] Room ${rn} background metadata+class in ${bgDur.toFixed(1)}ms (image may still be loading by browser)`);
            setTimeout(() => {
                const s1 = performance.now();
                scaleRoomCoordinates();
                const s1d = performance.now() - s1;
                setTimeout(() => {
                    const s2 = performance.now();
                    scaleRoomCoordinates();
                    const s2d = performance.now() - s2;
                    console.log(`[Perf] Room ${rn} coordinate scale pass1 ${s1d.toFixed(1)}ms, pass2 ${s2d.toFixed(1)}ms`);
                }, 500);
            }, 300);
        });
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
