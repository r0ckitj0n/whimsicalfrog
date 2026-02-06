import logger from './logger.js';

/**
 * Room Icons Utility
 * Handles class-based positioning for room item icons without inline styles.
 */

const STYLE_ID = 'wf-iconpos-runtime';
const posCache = new Map<string, string>();

function getStyleEl(): HTMLStyleElement {
    let el = document.getElementById(STYLE_ID) as HTMLStyleElement;
    if (!el) {
        el = document.createElement('style');
        el.id = STYLE_ID;
        document.head.appendChild(el);
    }
    return el;
}

export function ensurePosClass(t: number | string, l: number | string, w: number | string, h: number | string): string {
    const top = Math.max(0, Math.round(Number(t) || 0));
    const left = Math.max(0, Math.round(Number(l) || 0));
    const width = Math.max(1, Math.round(Number(w) || 0));
    const height = Math.max(1, Math.round(Number(h) || 0));
    
    const key = `${top}_${left}_${width}_${height}`;
    if (posCache.has(key)) return posCache.get(key)!;
    
    const cls = `iconpos-t${top}-l${left}-w${width}-h${height}`;
    const css = `.item-icon.${cls}, .room-item-icon.${cls}{position:absolute;top:${top}px;left:${left}px;width:${width}px;height:${height}px;--icon-top:${top}px;--icon-left:${left}px;--icon-width:${width}px;--icon-height:${height}px;}`;
    
    getStyleEl().appendChild(document.createTextNode(css));
    posCache.set(key, cls);
    return cls;
}

export function applyPosFromDataset(el: HTMLElement): void {
    if (!el || !el.dataset) return;
    
    const { originalTop, originalLeft, originalWidth, originalHeight } = el.dataset;
    if (originalTop == null || originalLeft == null || originalWidth == null || originalHeight == null) return;
    
    const cls = ensurePosClass(originalTop, originalLeft, originalWidth, originalHeight);
    
    if (el.dataset.iconPosClass && el.dataset.iconPosClass !== cls) {
        el.classList.remove(el.dataset.iconPosClass);
    }
    
    el.classList.add(cls);
    el.dataset.iconPosClass = cls;
}

export function initRoomIcons(root: Document | HTMLElement = document): void {
    try {
        const icons = root.querySelectorAll('.item-icon, .room-item-icon');
        icons.forEach(icon => applyPosFromDataset(icon as HTMLElement));
    } catch (e) {
        logger.warn('[room-icons] init failed', e);
    }
}

export function startRoomIconsObserver(): MutationObserver | null {
    try {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                mutation.addedNodes.forEach(node => {
                    if (!(node instanceof Element)) return;
                    if (node.matches('.item-icon, .room-item-icon')) {
                        applyPosFromDataset(node as HTMLElement);
                    }
                    const nested = node.querySelectorAll('.item-icon, .room-item-icon');
                    nested.forEach(icon => applyPosFromDataset(icon as HTMLElement));
                });
            }
        });
        
        observer.observe(document.documentElement, { childList: true, subtree: true });
        return observer;
    } catch (e) {
        logger.warn('[room-icons] MutationObserver failed', e);
        return null;
    }
}
