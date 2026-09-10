/** Persist last Settings (room X) wallpaper so early boot matches the active theme. */

export const SETTINGS_BG_CACHE_KEY = 'wf-settings-bg-url';

export const SETTINGS_ORIGINAL_BG = '/images/backgrounds/background-roomX.webp';
export const SETTINGS_REALISTIC_BG = '/images/backgrounds/realistic/realistic-roomX.webp';

const ALLOWED_SETTINGS_BG = new Set([SETTINGS_ORIGINAL_BG, SETTINGS_REALISTIC_BG]);

export function isAllowedSettingsBackgroundUrl(url: string): boolean {
    return ALLOWED_SETTINGS_BG.has(url);
}

export function readCachedSettingsBackgroundUrl(): string | null {
    try {
        const cached = localStorage.getItem(SETTINGS_BG_CACHE_KEY);
        if (cached && isAllowedSettingsBackgroundUrl(cached)) {
            return cached;
        }
    } catch {
        // localStorage may be unavailable (private mode / SSR-adjacent)
    }
    return null;
}

export function writeCachedSettingsBackgroundUrl(url: string): void {
    if (!url || (!url.includes('roomX') && !url.includes('roomx'))) {
        return;
    }
    const normalized = url.startsWith('/') ? url : `/${url.replace(/^\/+/, '')}`;
    let toStore = normalized;
    if (normalized.includes('/realistic/realistic-roomX.')) {
        toStore = SETTINGS_REALISTIC_BG;
    } else if (normalized.includes('/background-roomX.')) {
        toStore = SETTINGS_ORIGINAL_BG;
    } else if (!isAllowedSettingsBackgroundUrl(normalized)) {
        return;
    }
    try {
        localStorage.setItem(SETTINGS_BG_CACHE_KEY, toStore);
    } catch {
        // ignore quota / privacy errors
    }
}
