/**
 * WhimsicalFrog Core – Asset Utilities (TypeScript)
 */

export function normalizeAssetUrl(path: string | null | undefined): string {
    if (!path || typeof path !== 'string') return '';
    try {
        const base = (typeof window !== 'undefined' && window.location?.origin) || 'http://localhost';
        const url = new URL(path, base);
        if (url.hostname === '127.0.0.1') url.hostname = 'localhost';
        if (typeof window !== 'undefined' && window.location) {
            url.protocol = window.location.protocol;
        }
        return url.toString();
    } catch (err) {
        if (window.WhimsicalFrog && typeof window.WhimsicalFrog.warn === 'function') {
            window.WhimsicalFrog.warn({ msg: '[AssetUtils] Invalid asset URL dropped', path, err });
        }
        return '';
    }
}

export function removeBrokenImage(node: HTMLImageElement | null): void {
    if (!node) return;
    try {
        node.remove();
    } catch (err) {
        if (window.WhimsicalFrog && typeof window.WhimsicalFrog.warn === 'function') {
            window.WhimsicalFrog.warn({ msg: '[AssetUtils] Failed to remove broken image node', err });
        }
    }
}

export function attachStrictImageGuards(container: HTMLElement | null, selector: string = 'img'): void {
    if (!container || container._wfStrictImageGuard) return;
    
    const handler = (event: Event) => {
        const target = event.target as HTMLElement;
        if (!target || typeof target.matches !== 'function') return;
        if (target.matches(selector)) {
            removeBrokenImage(target as HTMLImageElement);
        }
    };
    
    container.addEventListener('error', handler, true);
    container._wfStrictImageGuard = handler;
}
