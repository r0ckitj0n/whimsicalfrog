// src/core/Diagnostics.ts
/**
 * WhimsicalFrog Diagnostics Utility
 * Extracted from legacy app.js for better isolation and compliance.
 */

import logger from './logger.js';

let __wfFreezeLog: (msg: string, extra?: unknown) => void = () => { };

export function initDiagnostics(): void {
    if (typeof window === 'undefined') return;

    let __wfEnableFreezeDebug = false;
    try {
        const qs = new URLSearchParams(window.location.search || '');
        __wfEnableFreezeDebug = qs.get('wf_diag_freeze_debug') === '1';
    } catch { // URL parsing failed - default to disabled
        __wfEnableFreezeDebug = false;
    }

    if (__wfEnableFreezeDebug) {
        const perfStart = performance.now ? performance.now() : Date.now();
        __wfFreezeLog = (msg: string, extra?: unknown) => {
            try {
                const t = (performance.now ? Math.round(performance.now() - perfStart) : 0);
                logger.warn(`[FREEZE-DEBUG] ${msg} @${t}ms`, extra);
            } catch { /* Freeze logging itself failed - ignore */ }
        };

        __wfFreezeLog('entry script executing');
        document.addEventListener('readystatechange', () => __wfFreezeLog(`readyState=${document.readyState}`));
        window.addEventListener('DOMContentLoaded', () => __wfFreezeLog('DOMContentLoaded'));
        window.addEventListener('load', () => __wfFreezeLog('window load'));

        window.addEventListener('error', (event) => {
            __wfFreezeLog('window.onerror', {
                message: event?.message,
                filename: event?.filename,
                lineno: event?.lineno,
                colno: event?.colno,
                error: event?.error
            });
        });

        window.addEventListener('unhandledrejection', (event) => {
            __wfFreezeLog('unhandledrejection', { reason: event?.reason });
        });

        const seenResources = new Set<string>();
        const tagResource = (el: HTMLElement) => {
            let src = '';
            if (el instanceof HTMLScriptElement) src = el.src;
            else if (el instanceof HTMLLinkElement) src = el.href;

            if (!el || seenResources.has(src)) return;
            const name = src || '<inline>';
            seenResources.add(name);
            __wfFreezeLog('resource requested', { tag: el.tagName, name });

            const done = () => {
                el.dataset.freezeDebugDone = '1';
                __wfFreezeLog('resource loaded', { tag: el.tagName, name });
            };
            const fail = (error: unknown) => {
                el.dataset.freezeDebugDone = '1';
                __wfFreezeLog('resource error', { tag: el.tagName, name, error });
            };

            el.addEventListener('load', done, { once: true });
            el.addEventListener('error', fail, { once: true });
        };

        document.querySelectorAll('script[src], link[rel="stylesheet"][href]').forEach((el) => tagResource(el as HTMLElement));

        try {
            const mo = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes && mutation.addedNodes.forEach((node) => {
                        if (node && node instanceof HTMLElement) {
                            const tag = node.tagName.toUpperCase();
                            if (tag === 'SCRIPT' && (node as HTMLScriptElement).src) tagResource(node);
                            if (tag === 'LINK' && (node as HTMLLinkElement).rel === 'stylesheet' && (node as HTMLLinkElement).href) tagResource(node);
                        }
                    });
                });
            });
            mo.observe(document.documentElement, { childList: true, subtree: true });
        } catch { /* MutationObserver setup failed - diagnostics will be limited */ }

        setInterval(() => {
            try {
                const pendingScripts = Array.from(document.querySelectorAll('script[src]'))
                    .filter((el) => (el as HTMLElement).dataset.freezeDebugDone !== '1');
                const pendingLinks = Array.from(document.querySelectorAll('link[rel="stylesheet"][href]'))
                    .filter((el) => (el as HTMLElement).dataset.freezeDebugDone !== '1');
                if (pendingScripts.length || pendingLinks.length) {
                    __wfFreezeLog('pending resources', {
                        scripts: pendingScripts.map((el) => (el as HTMLScriptElement).src),
                        styles: pendingLinks.map((el) => (el as HTMLLinkElement).href)
                    });
                }
            } catch { /* Pending resource query failed - ignore */ }
        }, 2000);
    }
}

export { __wfFreezeLog };
