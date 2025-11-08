/**
 * WhimsicalFrog Room Modal Management
 * Handles responsive modal overlays for room content - Vite compatible
 * Recovered and consolidated from legacy files
 */
import { ApiClient } from '../core/api-client.js';
// Module-scoped guard so we only log the disabled warning once per page load
let __WF_WARNED_NO_MODAL_SCRIPTS = false;
// Module-scoped singleton instance to avoid re-instantiation across opens
let __ROOM_MODAL_SINGLETON = null;

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
    // Apply background ONLY to the inner content wrapper to avoid duplicate layering
    styleEl.appendChild(document.createTextNode(`.room-overlay-wrapper.${cls}, .room-modal-body.${cls}, #modalRoomPage.${cls}{--room-bg-image:url('${imageUrl}');background-image:url('${imageUrl}') !important;background-size:cover;background-position:center;background-repeat:no-repeat;}`));
    roomBgClassMap.set(imageUrl, cls);
    return cls;
}

// Removed CSS class generator for per-icon positions; we now apply inline styles during scaling

// (Removed) runtime utility CSS injection for instant-open/hide; using default transitions

class RoomModalManager {
    constructor() {
        if (__ROOM_MODAL_SINGLETON) return __ROOM_MODAL_SINGLETON;
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
        this._loadedExternalScripts = new Set(); // track loaded <script src> to avoid duplicates
        // Inline scripts will execute on every render; external scripts are de-duped by URL
        this._openedRooms = new Set(); // rooms opened at least once (for instant-open UX)
        
        // Diagnostics from URL params
        try {
            const p = new URLSearchParams(window.location.search || '');
            this.__diag_no_bg = p.get('wf_diag_no_bg') === '1';
            this.__diag_no_content = p.get('wf_diag_no_content') === '1';
            this.__diag_no_scripts = p.get('wf_diag_no_modal_scripts') === '1';
            this.__diag_no_scale = p.get('wf_diag_no_scale') === '1';
            // Default: allow executing inline scripts inside modal content for proper rendering
            const enableScriptsParam = p.get('wf_enable_modal_scripts');
            this.__allow_scripts = (enableScriptsParam == null) ? true : (enableScriptsParam === '1');
            // Allow persistent opt-in/out via localStorage (overrides default and URL unless explicitly set to 0 in URL)
            try {
                const ls = localStorage.getItem('wf_enable_modal_scripts');
                if (enableScriptsParam == null) {
                    if (ls === '1') this.__allow_scripts = true;
                    if (ls === '0') this.__allow_scripts = false;
                }
            } catch(_) {}
            // Default: scaling ON unless explicitly disabled via diag flag or wf_enable_scale=0
            const enableScaleParam = p.get('wf_enable_scale');
            this.__allow_scale = (enableScaleParam == null) ? true : (enableScaleParam === '1');
        } catch (_) { this.__diag_no_bg = false; this.__diag_no_content = false; this.__allow_scripts = false; this.__allow_scale = true; }

        this._inited = false;
        this.init();
        __ROOM_MODAL_SINGLETON = this;
        try { window.__roomModalManager = this; } catch(_) {}
    }

    init() {
        if (this._inited) return;
        this._inited = true;
        this.createModalStructure();
        this.setupEventListeners();
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

                e.preventDefault();
                // Stop propagation to avoid duplicate opens if other handlers exist
                if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation(); else e.stopPropagation();

                const roomNumber = doorLink.dataset?.room ||
                                   (doorLink.href && doorLink.href.match(/room=(\d+)/)?.[1]) ||
                                   doorLink.getAttribute('data-room-number');

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
        const bodyEl = this.overlay.querySelector('.room-modal-body');
        const _cached = this.roomCache.has(key);
        const _sameRoomMounted = bodyEl && bodyEl.dataset && bodyEl.dataset.room === key && bodyEl.childElementCount > 0;
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
            // Dev/HMR-safe cache: fall back to sessionStorage if memory cache is empty
            if (!roomContent) {
                try {
                    const ss = sessionStorage.getItem(`wf_room_content_${key}`);
                    if (ss && typeof ss === 'string' && ss.length > 0) {
                        roomContent = ss;
                        this.roomCache.set(key, roomContent);
                    }
                } catch(_) {}
            }
            // Validate cached content; drop and refetch if invalid/minimal
            if (!this.isValidRoomContent(roomContent)) {
                roomContent = null;
            }
            if (!roomContent && !this.__diag_no_content) {
                try { performance.mark('wf:roomModal:fetch:start'); } catch(_) {}
                const resp = await ApiClient.get('/api/load_room_content.php', { room: key, modal: 1, perf: 1 });
                // Accept JSON or text. If text looks like JSON, parse it.
                if (typeof resp === 'string') {
                    let parsed = null;
                    const t = resp.trim();
                    if ((t.startsWith('{') && t.endsWith('}')) || (t.startsWith('[') && t.endsWith(']'))) {
                        try { parsed = JSON.parse(t); } catch(_) { parsed = null; }
                    }
                    if (parsed && typeof parsed === 'object') {
                        if (parsed.success === false) throw new Error(parsed.message || parsed.error || 'Failed to load room content');
                        roomContent = parsed.content || '';
                        if (parsed.metadata) this.updateHeader(parsed.metadata);
                        if (parsed.background) this._bgMetaCache.set(key, parsed.background);
                    } else {
                        // Raw HTML body
                        roomContent = resp;
                    }
                } else {
                    if (!resp.success) throw new Error(resp.message || 'Failed to load room content');
                    roomContent = resp.content;
                    if (resp.metadata) this.updateHeader(resp.metadata);
                    if (resp.background) this._bgMetaCache.set(key, resp.background);
                }
                // Cache only meaningful content (avoid caching empty/placeholder)
                const cacheable = typeof roomContent === 'string' && roomContent.trim().length > 50;
                if (cacheable) {
                    this.roomCache.set(key, roomContent);
                    try { sessionStorage.setItem(`wf_room_content_${key}`, roomContent); } catch(_) {}
                }
                try {
                    performance.mark('wf:roomModal:fetch:end');
                    performance.measure('wf:roomModal:fetch', 'wf:roomModal:fetch:start', 'wf:roomModal:fetch:end');
                    // Perf marks kept for DevTools, logging removed
                } catch(_) {}
            }

            try { performance.mark('wf:roomModal:dom:set:start'); } catch(_) {}
            const stillSameMounted = bodyEl && bodyEl.dataset && bodyEl.dataset.room === key && bodyEl.dataset.roomContent === '1' && bodyEl.childElementCount > 0;
            if (!stillSameMounted) {
                this.setContent(roomContent);
            } else {
                // Reuse DOM; just (re)initialize listeners and scale
                try { this.initializeModalContent(); } catch(_) {}
            }
            try {
                performance.mark('wf:roomModal:dom:set:end');
                performance.measure('wf:roomModal:dom:set', 'wf:roomModal:dom:set:start', 'wf:roomModal:dom:set:end');
                // Perf marks kept for DevTools, logging removed
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
                // Perf marks kept for DevTools, logging removed
            } catch(_) {}
        }
    }

    setContent(html) {
        const body = this.overlay.querySelector('.room-modal-body');
        if (!body) return;
        let htmlStr = html;
        try {
            // If an object with a content field was passed, extract it
            if (htmlStr && typeof htmlStr === 'object' && typeof htmlStr.content === 'string') {
                htmlStr = htmlStr.content;
            }
            if (typeof htmlStr === 'string') {
                let t = htmlStr.trim();
                // If the string looks like a JSON object (server sent JSON as text), parse and take .content
                if ((t.startsWith('{') && t.endsWith('}')) || t.includes('"content"')) {
                    try {
                        const obj = JSON.parse(t);
                        if (obj && typeof obj.content === 'string') {
                            htmlStr = obj.content;
                            t = htmlStr.trim();
                        }
                    } catch(_) { /* not JSON, continue */ }
                }
                // If still wrapped in quotes or contains escaped quotes, try unescaping once
                if ((t.startsWith('"') && t.endsWith('"')) || t.includes('\\"')) {
                    try { htmlStr = JSON.parse(t); } catch (_) {
                        htmlStr = t
                          .replace(/\\\//g, '/')
                          .replace(/\\n/g, '\n')
                          .replace(/\\t/g, '\t')
                          .replace(/\\"/g, '"')
                          .replace(/\\\\/g, '\\');
                    }
                }
            }
        } catch(_) {}
        const scriptRegex = /<script[^>]*>([\s\S]*?)<\/script>/gi;
        const scripts = [];
        let match;
        while (typeof htmlStr === 'string' && (match = scriptRegex.exec(htmlStr)) !== null) scripts.push(match[1]);
        body.innerHTML = (typeof htmlStr === 'string') ? htmlStr : String(htmlStr || '');
        // Tag which room is currently mounted to avoid re-rendering on second open
        try { body.dataset.room = String(this.currentRoomNumber || ''); } catch(_) {}
        try {
            const hasRealContent = !!(body.querySelector('.room-product-icon') || body.querySelector('img') || body.querySelector('#modalRoomPage'));
            body.dataset.roomContent = hasRealContent ? '1' : '0';
        } catch(_) {}
        // Execute external and inline scripts only when explicitly enabled
        if (!this.__diag_no_scripts && this.__allow_scripts) {
            try {
                const externalScripts = Array.from(body.querySelectorAll('script[src]'));
                externalScripts.forEach((s) => {
                    const src = s.getAttribute('src');
                    if (!src || this._loadedExternalScripts.has(src)) return;
                    const type = s.getAttribute('type') || '';
                    const asyncAttr = s.hasAttribute('async');
                    const deferAttr = s.hasAttribute('defer');
                    const newEl = document.createElement('script');
                    if (type) newEl.type = type;
                    if (asyncAttr) newEl.async = true;
                    if (deferAttr) newEl.defer = true;
                    newEl.src = src;
                    const cx = s.getAttribute('crossorigin');
                    if (cx) newEl.setAttribute('crossorigin', cx);
                    document.head.appendChild(newEl);
                    this._loadedExternalScripts.add(src);
                });
            } catch (e) { console.warn('[RoomModalManager] Failed to load external scripts from modal content', e); }
            // Run inline scripts on every render (idempotent content should guard itself)
            scripts.forEach((code, i) => { try { new Function(code)(); } catch (e) { console.error(`[Room] Error executing modal script ${i + 1}:`, e); } });
        } else {
            if (!__WF_WARNED_NO_MODAL_SCRIPTS) {
                console.info('[RoomModalManager] Modal script execution is disabled. Enable with ?wf_enable_modal_scripts=1 or localStorage.setItem(\'wf_enable_modal_scripts\',\'1\')');
                __WF_WARNED_NO_MODAL_SCRIPTS = true;
            }
        }
        this.initializeModalContent();
    }

    isValidRoomContent(html) {
        try {
            if (!html || typeof html !== 'string') return false;
            const t = html.trim();
            if (t.length < 50) return false;
            // Must include a recognizable modal wrapper or at least one product/icon/img marker
            if (t.includes('id="modalRoomPage"')) return true;
            if (t.includes('class="room-product-icon"')) return true;
            if (t.includes('<img')) return true;
            return false;
        } catch(_) { return false; }
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
        // Ensure images render even if content scripts are delayed: hydrate src from data attributes
        try {
            let backend = (typeof window !== 'undefined' && window.__WF_BACKEND_ORIGIN) ? String(window.__WF_BACKEND_ORIGIN) : '';
            if (!backend) {
                try {
                    const entries = performance.getEntriesByType('resource') || [];
                    const hit = entries.find(e => (e.name && e.name.includes('/api/')));
                    if (hit && hit.name) {
                        const u = new URL(hit.name, window.location.origin);
                        backend = `${u.protocol}//${u.host}`;
                    }
                } catch(_) {}
            }
            const absolutize = (url) => {
                try {
                    if (!url) return url;
                    if (/^(https?:)?\/\//i.test(url) || url.startsWith('data:')) return url;
                    // Normalize leading slash
                    const u = url.startsWith('/') ? url : `/${url}`;
                    if (backend && (u.startsWith('/images/') || u.startsWith('/uploads/') || u.startsWith('/media/'))) {
                        return backend + u;
                    }
                    return url;
                } catch(_) { return url; }
            };
            body.querySelectorAll('img').forEach((img) => {
                const hasSrc = !!img.getAttribute('src');
                const ds = img.dataset || {};
                const candidate = ds.src || ds.image || ds.lazySrc || ds.lazy;
                if (!hasSrc && candidate) {
                    try { img.setAttribute('src', absolutize(candidate)); } catch(_) {}
                } else if (hasSrc) {
                    try { img.setAttribute('src', absolutize(img.getAttribute('src'))); } catch(_) {}
                }
            });
            // Hydrate <source> inside <picture>
            body.querySelectorAll('picture source').forEach((source) => {
                const hasSet = !!source.getAttribute('srcset');
                const ds = source.dataset || {};
                const candidate = ds.srcset || ds.src || ds.image || ds.lazySrcset || ds.lazy;
                if (!hasSet && candidate) {
                    try { source.setAttribute('srcset', absolutize(candidate)); } catch(_) {}
                } else if (hasSet) {
                    try {
                        const val = source.getAttribute('srcset');
                        if (val && typeof val === 'string') {
                            const parts = val.split(',').map(s => s.trim()).map(part => {
                                const [u, d] = part.split(/\s+/);
                                const abs = absolutize(u);
                                return d ? `${abs} ${d}` : abs;
                            });
                            source.setAttribute('srcset', parts.join(', '));
                        }
                    } catch(_) {}
                }
            });
            // If icons only have data-image on the container, create an <img>
            body.querySelectorAll('.room-product-icon, .item-icon').forEach((icon) => {
                try {
                    const hasImgChild = !!icon.querySelector('img');
                    const ds = icon.dataset || {};
                    const url = ds.image || ds.src || ds.lazy;
                    if (!hasImgChild && url) {
                        const img = document.createElement('img');
                        img.className = 'room-product-icon-img';
                        img.setAttribute('draggable', 'false');
                        if (ds.sku) img.setAttribute('data-sku', ds.sku);
                        if (ds.image) img.setAttribute('data-image', ds.image);
                        if (ds.name) img.setAttribute('alt', ds.name);
                        try { img.src = absolutize(url); } catch(_) {}
                        icon.appendChild(img);
                    } else if (!hasImgChild && !url && ds && ds.imageUrl) {
                        // Fallback alt key
                        const img = document.createElement('img');
                        img.className = 'room-product-icon-img';
                        try { img.src = absolutize(ds.imageUrl); } catch(_) {}
                        icon.appendChild(img);
                    }
                } catch(_) {}
            });
        } catch(_) {}

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
        // Avoid double-binding hover/click handlers on the same modal body across opens
        const hasDelegated = !!(document.body && document.body.hasAttribute('data-wf-room-delegated-listeners'));
        if (!hasDelegated && !body.__wfBound) {
            body.addEventListener('mouseover', onOver);
            body.addEventListener('mouseout', onOut);
            body.addEventListener('focusin', onOver);
            body.addEventListener('focusout', onOut);
            body.__wfBound = true;
        }
        if (!body.__wfClickBound) {
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
            body.__wfClickBound = true;
        }

        // Background + coordinate scaling
        // Canonical wrapper: always use the modal body for background + scaling
        const rn = String(this.currentRoomNumber || '');

        const originalImageWidth = 1280;
        const originalImageHeight = 896;
        // Use .room-modal-body as the authoritative wrapper for both background and scaling
        const roomWrapper = this.overlay.querySelector('.room-modal-body');
        const doScale = !(this.__diag_no_scale) && !!this.__allow_scale;
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
                    const targetEl = roomWrapper;
                    if (targetEl) {
                        try {
                            const prev = targetEl.dataset?.roomBgClass;
                            if (prev && prev !== bgCls) targetEl.classList.remove(prev);
                        } catch(_) {}
                        if (bgCls) {
                            try { targetEl.classList.add(bgCls); } catch(_) {}
                            try { targetEl.dataset.roomBgClass = bgCls; targetEl.dataset.roomBgRoom = String(rn); } catch(_) {}
                        } else {
                            try { delete targetEl.dataset.roomBgClass; delete targetEl.dataset.roomBgRoom; } catch(_) {}
                        }
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

        let __wfLastWrapperW = 0, __wfLastWrapperH = 0, __wfScaleScheduled = false;
        const __wfScaleTick = 0;
        const scaleRoomCoordinates = () => {
            if (!doScale || !roomWrapper) return;
            const wrapperRect = roomWrapper.getBoundingClientRect();
            if (wrapperRect.width === 0 || wrapperRect.height === 0) return;
            // Skip if size unchanged
            if (__wfLastWrapperW === wrapperRect.width && __wfLastWrapperH === wrapperRect.height) return;
            __wfLastWrapperW = wrapperRect.width; __wfLastWrapperH = wrapperRect.height;
            const scaleX = wrapperRect.width / originalImageWidth;
            const scaleY = wrapperRect.height / originalImageHeight;
            const scale = Math.max(scaleX, scaleY);
            const scaledImageWidth = originalImageWidth * scale;
            const scaledImageHeight = originalImageHeight * scale;
            const offsetX = (wrapperRect.width - scaledImageWidth) / 2;
            const offsetY = (wrapperRect.height - scaledImageHeight) / 2;
            const icons = Array.from(body.querySelectorAll('.room-product-icon'));
            const BATCH = 60;
            let idx = 0;
            const processBatch = () => {
                const end = Math.min(idx + BATCH, icons.length);
                for (; idx < end; idx++) {
                    const icon = icons[idx];
                    if (!icon) continue;
                    let oTop, oLeft, oWidth, oHeight;
                    if (icon.dataset.originalTop) {
                        oTop = parseFloat(icon.dataset.originalTop);
                        oLeft = parseFloat(icon.dataset.originalLeft);
                        oWidth = parseFloat(icon.dataset.originalWidth);
                        oHeight = parseFloat(icon.dataset.originalHeight);
                    } else {
                        // Prefer explicit data-* attributes if provided by content
                        const attrTop = icon.getAttribute('data-top');
                        const attrLeft = icon.getAttribute('data-left');
                        const attrWidth = icon.getAttribute('data-width');
                        const attrHeight = icon.getAttribute('data-height');
                        if (attrTop != null && attrLeft != null && attrWidth != null && attrHeight != null) {
                            oTop = parseFloat(attrTop) || 0;
                            oLeft = parseFloat(attrLeft) || 0;
                            oWidth = parseFloat(attrWidth) || 80;
                            oHeight = parseFloat(attrHeight) || 80;
                        } else {
                            // Fallback: CSS custom properties, then raw computed styles
                            const cs = getComputedStyle(icon);
                            oTop = parseFloat(cs.getPropertyValue('--icon-top')) || parseFloat(cs.top) || 0;
                            oLeft = parseFloat(cs.getPropertyValue('--icon-left')) || parseFloat(cs.left) || 0;
                            oWidth = parseFloat(cs.getPropertyValue('--icon-width')) || parseFloat(cs.width) || 80;
                            oHeight = parseFloat(cs.getPropertyValue('--icon-height')) || parseFloat(cs.height) || 80;
                        }
                        icon.dataset.originalTop = String(oTop);
                        icon.dataset.originalLeft = String(oLeft);
                        icon.dataset.originalWidth = String(oWidth);
                        icon.dataset.originalHeight = String(oHeight);
                    }
                    const sTop = (oTop * scale) + offsetY;
                    const sLeft = (oLeft * scale) + offsetX;
                    const sWidth = oWidth * scale;
                    const sHeight = oHeight * scale;
                    const st = icon.style;
                    // Apply robust inline styles with !important to prevent overrides after reopen
                    st.setProperty('position', 'absolute', 'important');
                    st.setProperty('top', sTop.toFixed(2) + 'px', 'important');
                    st.setProperty('left', sLeft.toFixed(2) + 'px', 'important');
                    st.setProperty('width', sWidth.toFixed(2) + 'px', 'important');
                    st.setProperty('height', sHeight.toFixed(2) + 'px', 'important');
                    icon.classList.add('positioned');
                }
                if (idx < icons.length) {
                    requestAnimationFrame(processBatch);
                }
            };
            requestAnimationFrame(processBatch);
        };

        if (this._resizeHandler) { window.removeEventListener('resize', this._resizeHandler); this._resizeHandler = null; }
        if (this._resizeObserver) { try { this._resizeObserver.disconnect(); } catch(_) {} this._resizeObserver = null; }
        if (doScale) {
            this._resizeHandler = () => {
                if (__wfScaleScheduled) return;
                __wfScaleScheduled = true;
                requestAnimationFrame(() => { __wfScaleScheduled = false; scaleRoomCoordinates(); });
            };
            window.addEventListener('resize', this._resizeHandler);
            // Observe wrapper size changes precisely (e.g., fonts, content shifts)
            try {
                if ('ResizeObserver' in window && roomWrapper) {
                    let roScheduled = false;
                    const ro = new ResizeObserver(() => {
                        if (roScheduled) return;
                        roScheduled = true;
                        requestAnimationFrame(() => { roScheduled = false; scaleRoomCoordinates(); });
                    });
                    ro.observe(roomWrapper);
                    this._resizeObserver = ro;
                }
            } catch(_) {}
        }
        // Always resolve background for current room to avoid stale backgrounds
        loadRoomBackground().then(() => {
            // Perf marks retained; logging removed
            if (doScale) {
                // Initial scale on open
                requestAnimationFrame(() => { try { scaleRoomCoordinates(); } catch(_) {} });
                // Also schedule once after overlay transition completes (BFCache/tab focus quirks)
                try {
                    const onTe = (_e) => { try { requestAnimationFrame(scaleRoomCoordinates); } catch(_) {} };
                    this.overlay.addEventListener('transitionend', onTe, { once: true });
                } catch(_) {}
            }
            try { this._openedRooms.add(String(this.currentRoomNumber || rn)); } catch(_) {}
        }).catch(() => { /* no-op */ });
    }

    show() {
        this.previouslyFocusedElement = document.activeElement;
        try { document.body.classList.add('room-modal-open'); } catch(_) {}
        if (typeof window.showModal === 'function') {
            try { window.showModal('roomModalOverlay'); } catch(_) {}
        } else {
            this.overlay.classList.add('show');
            document.body.classList.add('modal-open');
            // Force reflow (optional)
            this.overlay.offsetHeight;
            if (window.WFModals && typeof window.WFModals.lockScroll === 'function') {
                window.WFModals.lockScroll();
            } else {
                document.documentElement.classList.add('modal-open');
                document.body.classList.add('modal-open');
            }
        }
        setTimeout(() => {
            const backBtn = this.overlay.querySelector('.room-modal-back-btn');
            if (backBtn) backBtn.focus();
        }, 100);
    }

    close() {
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
            this._resizeHandler = null;
        }
        if (this._resizeObserver) {
            try { this._resizeObserver.disconnect(); } catch(_) {}
            this._resizeObserver = null;
        }
        if (typeof window.hideModal === 'function') {
            try { window.hideModal('roomModalOverlay'); } catch(_) {}
            try { document.body.classList.remove('room-modal-open'); } catch(_) {}
            if (this.previouslyFocusedElement) {
                try { this.previouslyFocusedElement.focus(); } catch(_) {}
                this.previouslyFocusedElement = null;
            }
        } else {
            this.overlay.classList.remove('show');
            document.body.classList.remove('modal-open', 'room-modal-open');
            setTimeout(() => {
                if (window.WFModals && typeof window.WFModals.unlockScrollIfNoneOpen === 'function') {
                    window.WFModals.unlockScrollIfNoneOpen();
                } else {
                    document.documentElement.classList.remove('modal-open');
                    document.body.classList.remove('modal-open');
                }
                if (this.previouslyFocusedElement) {
                    try { this.previouslyFocusedElement.focus(); } catch(_) {}
                    this.previouslyFocusedElement = null;
                }
            }, 300);
        }
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
