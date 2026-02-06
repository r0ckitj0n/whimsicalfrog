import { ApiClient } from './ApiClient.js';
import logger from './logger.js';
import { PAGE } from './constants.js';

/**
 * Dynamic Background Utility
 * Dynamically loads page/room backgrounds using the unified ApiClient.
 */

const DYNBG_STYLE_ID = 'wf-dynbg-runtime';

function getDynBgStyleEl(): HTMLStyleElement {
    let el = document.getElementById(DYNBG_STYLE_ID) as HTMLStyleElement;
    if (!el) {
        el = document.createElement('style');
        el.id = DYNBG_STYLE_ID;
        document.head.appendChild(el);
    }
    return el;
}

const dynBgClassMap = new Map<string, string>();

function ensureDynBgClass(image_url: string): string | null {
    if (!image_url) return null;
    if (dynBgClassMap.has(image_url)) return dynBgClassMap.get(image_url)!;
    
    const idx = dynBgClassMap.size + 1;
    const cls = `dynbg-${idx}`;
    const styleEl = getDynBgStyleEl();
    
    styleEl.appendChild(document.createTextNode(`.${cls}{--dynamic-bg-url:url('${image_url}');background-image:url('${image_url}');}`));
    dynBgClassMap.set(image_url, cls);
    return cls;
}

interface IRoomDataResponse {
    success: boolean;
    data: {
        roomDoors: Array<{
            room_number: string | number;
        }>;
    };
}

interface IBackgroundResponse {
    success: boolean;
    background?: {
        image_filename: string;
        webp_filename?: string;
    };
    data?: {
        background?: {
            image_filename: string;
            webp_filename?: string;
        };
    };
}

async function generatePageRoomMap(): Promise<Record<string, string>> {
    try {
        const data = await ApiClient.get<IRoomDataResponse>('/api/get_room_data.php');
        if (data.success) {
            const map: Record<string, string> = {};
            data.data.roomDoors.forEach((room) => {
                const key = `room${room.room_number}`;
                map[key] = key;
            });
            return map;
        }
    } catch (err) {
        logger.error('[DynamicBackground] Failed to generate page-room map', err);
    }
    return {};
}

export async function loadDynamicBackground(): Promise<void> {
    try {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || PAGE.LANDING;
        const map = await generatePageRoomMap();
        const roomNumberStr = map[page] || PAGE.LANDING;

        if (!/^room[0-9A-Za-z]+$/i.test(roomNumberStr)) {
            return;
        }

        const rn = String(roomNumberStr).replace(/^room/i, '');
        const bgRes = await ApiClient.get<IBackgroundResponse>(`/api/get_background.php?room=${encodeURIComponent(rn)}`);
        const bg = (bgRes && (bgRes.data?.background || bgRes.background)) || null;
        if (!bg) return;

        const { image_filename, webp_filename } = bg;
        const supportsWebP = document.documentElement.classList.contains('webp');
        const raw = (supportsWebP && webp_filename) ? webp_filename : image_filename;
        const val = String(raw || '').trim();
        
        const buildUrl = (v: string) => {
            if (!v) return '';
            if (/^https?:\/\//i.test(v)) return v;
            if (v.startsWith('/images/')) return v;
            if (v.startsWith('images/')) return '/' + v;
            if (v.startsWith('backgrounds/')) return '/images/' + v;
            return '/images/backgrounds/' + v;
        };
        
        const image_url = buildUrl(val);

        let container = document.querySelector('.fullscreen-container') || document.getElementById('mainContent');
        if (!container) container = document.body;

        const bgCls = ensureDynBgClass(image_url);
        const containerHtml = container as HTMLElement;
        
        if (containerHtml.dataset.bgClass && containerHtml.dataset.bgClass !== bgCls) {
            containerHtml.classList.remove(containerHtml.dataset.bgClass);
        }
        
        if (bgCls) {
            containerHtml.classList.add(bgCls);
            containerHtml.dataset.bgClass = bgCls;
        }
        
        containerHtml.classList.add('bg-container', 'mode-fullscreen', 'dynamic-bg-loaded');
        document.body.classList.add('dynamic-bg-active');
    } catch (err) {
        logger.error('[DynamicBackground] Error loading background', err);
    }
}

// Auto-run when DOM ready
if (typeof window !== 'undefined') {
    if (document.readyState !== 'loading') {
        loadDynamicBackground();
    } else {
        document.addEventListener('DOMContentLoaded', loadDynamicBackground);
    }
}
