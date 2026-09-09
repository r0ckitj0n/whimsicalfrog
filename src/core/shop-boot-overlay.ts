import { BRAND_ASSET } from './constants.js';
import { hrefNeedsShopLoader, locationNeedsShopData } from '../utils/pageRoute.js';
import { resolveBackgroundAssetUrl } from '../utils/background-url.js';
import '../styles/components/ui/spinning-frog-head.css';

const OVERLAY_ID = 'wf-shop-boot-overlay';
const BG_LAYER_ID = 'wf-shop-boot-bg';
const STYLE_ID = 'wf-shop-boot-overlay-style';
const BOOT_CLASS = 'wf-shop-boot';
const LOADING_CLASS = 'wf-shop-loading';
const SHOP_BG_CACHE_KEY = 'wf_shop_bg_url_v3';
const SHOP_DATA_CACHE_KEY = 'wf_shop_data_v2';

/** Known-good on-disk shop wallpaper (DB "realistic" paths often 404 as HTML). */
export const DEFAULT_SHOP_BG_URL = '/images/backgrounds/background-roomS.webp';

const SHOP_BG_FALLBACKS = [
    DEFAULT_SHOP_BG_URL,
    '/images/backgrounds/background-roomS.png',
];

let guardsInstalled = false;
let shopBgPromise: Promise<string> | null = null;
let shopCatalogPromise: Promise<unknown> | null = null;
let cachedShopBgUrl = '';
let cachedShopDataMemory: unknown = null;

/** Match --wf-z-topmost so nothing in the room tree can stack above the frog. */
const OVERLAY_Z = '2147483647';

const OVERLAY_CSS = `
:root {
  --wf-z-modal: 11010;
  --wf-shop-boot-bg-image: url("${DEFAULT_SHOP_BG_URL}");
}
/* Hide the entire React tree so fixed MainRoom/Header cannot cover the frog. */
html.wf-shop-boot #wf-root,
html.wf-shop-boot #landingPage-react,
html.wf-shop-boot #mainRoomPage-react,
html.wf-shop-boot #wf-startup-loader {
  visibility: hidden !important;
  pointer-events: none !important;
}
html.wf-shop-boot #landingPage-react,
html.wf-shop-boot #mainRoomPage-react,
html.wf-shop-boot #wf-startup-loader {
  display: none !important;
}
/* Belt-and-suspenders: paint shop wallpaper on body under the frog. */
html.wf-shop-boot body,
html.wf-shop-boot body.wf-shop-loading,
html.wf-shop-boot body.wf-shop-loading[data-bg-url],
html.wf-shop-boot body.wf-shop-loading[data-bg-url][data-bg-applied="1"] {
  background-color: #000 !important;
  background-image: var(--wf-shop-boot-bg-image) !important;
  background-size: cover !important;
  background-position: center !important;
  background-repeat: no-repeat !important;
  background-attachment: fixed !important;
}
#wf-shop-boot-overlay {
  display: none;
  position: fixed !important;
  inset: 0 !important;
  z-index: ${OVERLAY_Z} !important;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  background: transparent;
  pointer-events: auto;
  visibility: visible !important;
  opacity: 1 !important;
}
html.wf-shop-boot #wf-shop-boot-overlay {
  display: flex !important;
}
#wf-shop-boot-bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  background-color: #000;
  background-image: var(--wf-shop-boot-bg-image) !important;
  background-size: cover !important;
  background-position: center !important;
  background-repeat: no-repeat !important;
}
#wf-shop-boot-bg::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.35);
  pointer-events: none;
}
#wf-shop-boot-overlay .wf-spinning-frog-head-wrap {
  position: relative;
  z-index: 2;
}
#wf-shop-boot-overlay .wf-sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
`;

declare global {
    interface Window {
        __wfShowShopBootOverlay?: () => void;
        __wfHideShopBootOverlay?: () => void;
    }
}

function isShopWallpaperUrl(url: string): boolean {
    const u = String(url || '');
    if (!u) return false;
    // Shop room code is capital S — only accept background-roomS.* (not room0/A/5).
    return /background-roomS\.(webp|png|jpe?g)(\?|#|$)/.test(u);
}

function readCachedShopBg(): string {
    if (cachedShopBgUrl && isShopWallpaperUrl(cachedShopBgUrl)) return cachedShopBgUrl;
    if (cachedShopBgUrl && !isShopWallpaperUrl(cachedShopBgUrl)) {
        cachedShopBgUrl = '';
    }
    if (typeof sessionStorage === 'undefined') return '';
    try {
        const raw = sessionStorage.getItem(SHOP_BG_CACHE_KEY) || '';
        if (raw && !isShopWallpaperUrl(raw)) {
            sessionStorage.removeItem(SHOP_BG_CACHE_KEY);
            cachedShopBgUrl = '';
            return '';
        }
        cachedShopBgUrl = raw;
    } catch {
        cachedShopBgUrl = '';
    }
    return cachedShopBgUrl;
}

function writeCachedShopBg(url: string): void {
    if (!isShopWallpaperUrl(url)) return;
    cachedShopBgUrl = url;
    if (typeof sessionStorage === 'undefined') return;
    try {
        sessionStorage.setItem(SHOP_BG_CACHE_KEY, url);
    } catch {
        // ignore quota / private mode
    }
}

function ensureOverlayStyle(): void {
    if (typeof document === 'undefined') return;
    let style = document.getElementById(STYLE_ID) as HTMLStyleElement | null;
    if (!style) {
        style = document.createElement('style');
        style.id = STYLE_ID;
        document.head.appendChild(style);
    }
    // Always refresh so HMR / late module loads replace stale overlay CSS.
    style.textContent = OVERLAY_CSS;
}

function applyBgLayer(url: string): void {
    if (!url || typeof document === 'undefined') return;
    ensureOverlayElement();
    const layer = document.getElementById(BG_LAYER_ID) as HTMLElement | null;
    const cssUrl = `url("${url}")`;
    document.documentElement.style.setProperty('--wf-shop-boot-bg-image', cssUrl);
    if (layer) {
        layer.style.setProperty('background-image', cssUrl, 'important');
        layer.style.setProperty('background-size', 'cover', 'important');
        layer.style.setProperty('background-position', 'center', 'important');
        layer.style.setProperty('background-repeat', 'no-repeat', 'important');
        layer.setAttribute('data-bg', url);
    }
    // Paint body too — if a fixed MainRoom still stacks above the overlay, wallpaper is still shop.
    document.body.style.setProperty('background-image', cssUrl, 'important');
    document.body.style.setProperty('background-size', 'cover', 'important');
    document.body.style.setProperty('background-position', 'center', 'important');
    document.body.style.setProperty('background-repeat', 'no-repeat', 'important');
    document.body.style.setProperty('background-attachment', 'fixed', 'important');
    document.body.setAttribute('data-shop-boot-bg', url);
}

function ensureOverlayElement(): HTMLElement | null {
    if (typeof document === 'undefined' || !document.body) return null;
    let overlay = document.getElementById(OVERLAY_ID);
    if (overlay) {
        if (!document.getElementById(BG_LAYER_ID)) {
            const bg = document.createElement('div');
            bg.id = BG_LAYER_ID;
            bg.setAttribute('aria-hidden', 'true');
            overlay.insertBefore(bg, overlay.firstChild);
        }
        if (overlay.parentElement !== document.body || document.body.lastElementChild !== overlay) {
            document.body.appendChild(overlay);
        }
        overlay.style.setProperty('z-index', OVERLAY_Z, 'important');
        overlay.style.setProperty('position', 'fixed', 'important');
        overlay.style.setProperty('inset', '0', 'important');
        return overlay;
    }

    overlay = document.createElement('section');
    overlay.id = OVERLAY_ID;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.setAttribute('aria-busy', 'true');
    overlay.setAttribute('aria-label', 'Loading the shop');
    overlay.style.setProperty('z-index', OVERLAY_Z, 'important');
    overlay.style.setProperty('position', 'fixed', 'important');
    overlay.style.setProperty('inset', '0', 'important');

    const bg = document.createElement('div');
    bg.id = BG_LAYER_ID;
    bg.setAttribute('aria-hidden', 'true');

    const wrap = document.createElement('div');
    wrap.className = 'wf-spinning-frog-head-wrap';
    wrap.setAttribute('aria-hidden', 'true');

    const ring = document.createElement('span');
    ring.className = 'wf-spinning-frog-head__ring';

    const img = document.createElement('img');
    img.className = 'wf-spinning-frog-head';
    img.src = BRAND_ASSET.FROG_HEAD;
    img.alt = '';
    img.width = 160;
    img.height = 160;
    img.decoding = 'async';
    img.fetchPriority = 'high';

    const sr = document.createElement('span');
    sr.className = 'wf-sr-only';
    sr.textContent = 'Loading the shop…';

    wrap.append(ring, img);
    overlay.append(bg, wrap, sr);
    document.body.appendChild(overlay);
    return overlay;
}

function preloadImage(url: string): Promise<boolean> {
    return new Promise((resolve) => {
        const img = new Image();
        const done = (ok: boolean) => resolve(ok);
        img.onload = () => done(img.naturalWidth > 0);
        img.onerror = () => done(false);
        // Cap hung image probes (SPA HTML responses can be ambiguous).
        window.setTimeout(() => done(img.naturalWidth > 0), 1200);
        img.src = url;
    });
}

async function pickWorkingShopBgUrl(primary: string): Promise<string> {
    const candidates = [primary, ...SHOP_BG_FALLBACKS].filter(Boolean);
    for (const url of candidates) {
        const ok = await preloadImage(url);
        if (ok) return url;
    }
    return primary || SHOP_BG_FALLBACKS[0];
}

/** Fetch + cache the shop wallpaper so the boot overlay can paint it under the frog. */
export function prefetchShopBackground(): Promise<string> {
    if (typeof window === 'undefined') return Promise.resolve('');
    const existing = readCachedShopBg();
    if (existing) {
        applyBgLayer(existing);
        return Promise.resolve(existing);
    }
    if (shopBgPromise) return shopBgPromise;

    // Paint the known wallpaper immediately while the network probe runs.
    applyBgLayer(SHOP_BG_FALLBACKS[0]);

    shopBgPromise = (async () => {
        try {
            const res = await fetch('/api/get_background.php?room=S', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            });
            let primary = '';
            if (res.ok) {
                const data = await res.json() as {
                    background?: { webp_filename?: string; png_filename?: string; image_filename?: string };
                };
                const raw = data?.background?.webp_filename
                    || data?.background?.png_filename
                    || data?.background?.image_filename
                    || '';
                primary = resolveBackgroundAssetUrl(raw);
            }
            const resolved = await pickWorkingShopBgUrl(primary);
            if (!resolved) return SHOP_BG_FALLBACKS[0];
            writeCachedShopBg(resolved);
            applyBgLayer(resolved);
            return resolved;
        } catch {
            const fallback = await pickWorkingShopBgUrl('');
            if (fallback) {
                writeCachedShopBg(fallback);
                applyBgLayer(fallback);
            }
            return fallback || SHOP_BG_FALLBACKS[0];
        } finally {
            if (!readCachedShopBg()) shopBgPromise = null;
        }
    })();

    return shopBgPromise;
}

export function getCachedShopBackgroundUrl(): string {
    return readCachedShopBg();
}

export function showShopBootOverlay(): void {
    if (typeof document === 'undefined') return;
    ensureOverlayStyle();
    ensureOverlayElement();
    document.documentElement.classList.add(BOOT_CLASS);
    document.body.classList.add(LOADING_CLASS);
    const cached = readCachedShopBg();
    const paintUrl = cached || SHOP_BG_FALLBACKS[0];
    applyBgLayer(paintUrl);
    // Prefer the HTML helper so cold loads and SPA clicks share one paint path.
    const paint = (window as Window & { __wfPaintShopBootBg?: (url?: string) => void }).__wfPaintShopBootBg;
    if (typeof paint === 'function') paint(paintUrl);
    void prefetchShopBackground();
    void prefetchShopCatalog();
}

export function hideShopBootOverlay(): void {
    if (typeof document === 'undefined') return;
    document.documentElement.classList.remove(BOOT_CLASS);
    // Restore body background control to the normal room/page system.
    if (document.body.getAttribute('data-shop-boot-bg')) {
        document.body.style.removeProperty('background-image');
        document.body.style.removeProperty('background-size');
        document.body.style.removeProperty('background-position');
        document.body.style.removeProperty('background-repeat');
        document.body.style.removeProperty('background-attachment');
        document.body.removeAttribute('data-shop-boot-bg');
    }
}

export function readCachedShopData<T>(): T | null {
    if (cachedShopDataMemory) {
        return cachedShopDataMemory as T;
    }
    if (typeof sessionStorage === 'undefined') return null;
    try {
        const raw = sessionStorage.getItem(SHOP_DATA_CACHE_KEY);
        if (!raw) return null;
        cachedShopDataMemory = JSON.parse(raw) as T;
        return cachedShopDataMemory as T;
    } catch {
        return null;
    }
}

export function writeCachedShopData(data: unknown): void {
    if (!data) return;
    cachedShopDataMemory = data;
    if (typeof sessionStorage === 'undefined') return;
    try {
        sessionStorage.setItem(SHOP_DATA_CACHE_KEY, JSON.stringify(data));
    } catch {
        // Quota / private mode — memory cache still warms SPA navigations.
    }
}

/** Warm the shop catalog in parallel with the wallpaper. */
export function prefetchShopCatalog(): Promise<unknown> {
    if (typeof window === 'undefined') return Promise.resolve(null);
    const cached = readCachedShopData();
    if (cached) return Promise.resolve(cached);
    if (shopCatalogPromise) return shopCatalogPromise;

    shopCatalogPromise = (async () => {
        try {
            const res = await fetch('/api/bootstrap.php?path=/shop&include_shop=1', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' }
            });
            if (!res.ok) return null;
            const data = await res.json() as { shop_data?: unknown };
            if (data.shop_data) writeCachedShopData(data.shop_data);
            return data.shop_data ?? null;
        } catch {
            return null;
        } finally {
            if (!readCachedShopData()) shopCatalogPromise = null;
        }
    })();

    return shopCatalogPromise;
}

export function installShopBootOverlayGuards(): void {
    if (typeof document === 'undefined') return;
    // Always refresh the window hooks so HMR picks up the latest show/hide.
    window.__wfShowShopBootOverlay = showShopBootOverlay;
    window.__wfHideShopBootOverlay = hideShopBootOverlay;
    ensureOverlayStyle();

    if (guardsInstalled) {
        if (locationNeedsShopData(window.location.pathname, window.location.search)) {
            showShopBootOverlay();
        }
        return;
    }
    guardsInstalled = true;

    const warm = () => {
        void prefetchShopBackground();
        void prefetchShopCatalog();
    };
    const ric = (window as Window & {
        requestIdleCallback?: (cb: () => void, opts?: { timeout: number }) => number;
    }).requestIdleCallback;
    if (typeof ric === 'function') {
        ric(warm, { timeout: 2500 });
    } else {
        window.setTimeout(warm, 1200);
    }

    if (locationNeedsShopData(window.location.pathname, window.location.search)) {
        showShopBootOverlay();
    }

    const invokeShow = () => {
        (window.__wfShowShopBootOverlay || showShopBootOverlay)();
    };

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const anchor = target.closest('a[href]');
        if (!anchor) return;
        const href = anchor.getAttribute('href') || '';
        if (hrefNeedsShopLoader(href)) {
            invokeShow();
        }
    }, true);

    document.addEventListener('pointerover', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const anchor = target.closest('a[href]');
        if (!anchor) return;
        const href = anchor.getAttribute('href') || '';
        if (hrefNeedsShopLoader(href)) {
            void prefetchShopBackground();
            void prefetchShopCatalog();
        }
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const action = form.getAttribute('action') || '';
        if (hrefNeedsShopLoader(action)) {
            invokeShow();
        }
    }, true);
}
