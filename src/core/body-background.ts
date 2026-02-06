import { ApiClient } from './ApiClient.js';
import logger from './logger.js';

/**
 * Body Background Utility
 * Applies background image from body[data-bg-url] at runtime without inline style writes.
 */

const STYLE_ID = 'wf-body-bg-style';

function getStyleEl(): HTMLStyleElement {
    let el = document.getElementById(STYLE_ID) as HTMLStyleElement;
    if (!el) {
        el = document.createElement('style');
        el.id = STYLE_ID;
        document.head.appendChild(el);
    }
    return el;
}

interface IBackgroundResponse {
    success: boolean;
    background?: {
        webp_filename?: string;
        png_filename?: string;
        image_filename?: string;
    };
}

async function fetchRoomBgIfNeeded(): Promise<string | null> {
    try {
        const body = document.body;
        if (!body || body.dataset.bgUrl) return null;

        // Detect room from path or query
        const path = window.location.pathname || '';
        let roomNum: string | null = null;
        const segs = path.split('/').filter(Boolean);
        
        if (segs.length >= 2 && segs[0].toLowerCase() === 'room' && /^\d+$/.test(segs[1])) {
            roomNum = segs[1];
        } else {
            const params = new URLSearchParams(window.location.search);
            const roomParam = params.get('room') || params.get('room_number');
            if (roomParam && /^\d+$/.test(roomParam)) roomNum = roomParam;
        }

        if (!roomNum) return null;

        const res = await ApiClient.get<IBackgroundResponse>('/api/get_background.php', { room: roomNum });
        const bg = res?.background ? (res.background.webp_filename || res.background.png_filename || res.background.image_filename) : null;
        
        if (!bg) return null;

        const val = String(bg).trim();
        const buildUrl = (v: string) => {
            if (!v) return '';
            if (/^https?:\/\//i.test(v)) return v;
            if (v.startsWith('/images/')) return window.location.origin + v;
            if (v.startsWith('images/')) return window.location.origin + '/' + v;
            if (v.startsWith('backgrounds/')) return window.location.origin + '/images/' + v;
            return window.location.origin + '/images/backgrounds/' + v;
        };

        const url = `${buildUrl(val)}${val.includes('?') ? '&' : '?'}v=${Date.now()}`;
        body.setAttribute('data-bg-url', url);
        return url;
    } catch (err) {
        logger.warn('[BodyBackground] fetch failed', err);
        return null;
    }
}

export async function initBodyBackground(): Promise<void> {
    try {
        const body = document.body;
        if (!body) return;

        let url = body.dataset.bgUrl;
        if (!url) {
            url = await fetchRoomBgIfNeeded() || undefined;
            if (!url) return;
        }

        const styleEl = getStyleEl();
        styleEl.textContent = `
            body[data-bg-url][data-bg-applied="1"] {
                background-image: url(${JSON.stringify(url)});
                background-size: cover;
                background-position: center;
                background-repeat: no-repeat;
                background-attachment: fixed;
            }
            body[data-bg-url][data-bg-applied="1"]:not([data-is-admin="true"]) {
                min-height: 100vh;
            }
        `;
        body.setAttribute('data-bg-applied', '1');
        body.classList.add('wf-bg-applied');
    } catch (e) {
        logger.warn('[BodyBackground] init failed', e);
    }
}
