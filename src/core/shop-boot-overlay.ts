import { BRAND_ASSET } from './constants.js';
import { hrefNeedsShopLoader, locationNeedsShopData } from '../utils/pageRoute.js';
import '../styles/components/ui/spinning-frog-head.css';

const OVERLAY_ID = 'wf-shop-boot-overlay';
const STYLE_ID = 'wf-shop-boot-overlay-style';
const BOOT_CLASS = 'wf-shop-boot';
const LOADING_CLASS = 'wf-shop-loading';

let guardsInstalled = false;

const OVERLAY_CSS = `
:root {
  --wf-z-modal: 11010;
}
html.wf-shop-boot,
html.wf-shop-boot body,
body.wf-shop-loading,
body.wf-shop-loading[data-bg-url],
body.wf-shop-loading[data-bg-url][data-bg-applied="1"] {
  background-image: none !important;
  background-color: #000 !important;
}
html.wf-shop-boot #landingPage-react,
html.wf-shop-boot #mainRoomPage-react,
html.wf-shop-boot #wf-startup-loader {
  display: none !important;
  pointer-events: none !important;
}
#wf-shop-boot-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: var(--wf-z-modal);
  align-items: center;
  justify-content: center;
  flex-direction: column;
  background: #000;
  pointer-events: auto;
}
html.wf-shop-boot #wf-shop-boot-overlay {
  display: flex !important;
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

function ensureOverlayStyle(): void {
    if (typeof document === 'undefined') return;
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = OVERLAY_CSS;
    document.head.appendChild(style);
}

function ensureOverlayElement(): HTMLElement | null {
    if (typeof document === 'undefined' || !document.body) return null;
    let overlay = document.getElementById(OVERLAY_ID);
    if (overlay) return overlay;

    overlay = document.createElement('section');
    overlay.id = OVERLAY_ID;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.setAttribute('aria-busy', 'true');
    overlay.setAttribute('aria-label', 'Loading the shop');

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

    const sr = document.createElement('span');
    sr.className = 'wf-sr-only';
    sr.textContent = 'Loading the shop…';

    wrap.append(ring, img);
    overlay.append(wrap, sr);
    document.body.appendChild(overlay);
    return overlay;
}

function clearBodyPhoto(): void {
    if (typeof document === 'undefined' || !document.body) return;
    document.body.style.removeProperty('background-image');
    document.body.style.removeProperty('--body-bg');
    document.body.style.removeProperty('--wf-body-bg');
}

export function showShopBootOverlay(): void {
    if (typeof document === 'undefined') return;
    ensureOverlayStyle();
    ensureOverlayElement();
    document.documentElement.classList.add(BOOT_CLASS);
    document.body.classList.add(LOADING_CLASS);
    clearBodyPhoto();
}

export function hideShopBootOverlay(): void {
    if (typeof document === 'undefined') return;
    document.documentElement.classList.remove(BOOT_CLASS);
}

export function installShopBootOverlayGuards(): void {
    if (typeof document === 'undefined' || guardsInstalled) return;
    guardsInstalled = true;
    ensureOverlayStyle();

    if (locationNeedsShopData(window.location.pathname, window.location.search)) {
        showShopBootOverlay();
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const anchor = target.closest('a[href]');
        if (!anchor) return;
        const href = anchor.getAttribute('href') || '';
        if (hrefNeedsShopLoader(href)) {
            showShopBootOverlay();
        }
    }, true);

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const action = form.getAttribute('action') || '';
        if (hrefNeedsShopLoader(action)) {
            showShopBootOverlay();
        }
    }, true);
}
