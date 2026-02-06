// Normalize API URLs for relative paths only.
export function ensureApiUrl(url: string): string {
    if (url.startsWith('http://') || url.startsWith('https://')) return url;
    if (url.startsWith('/')) return url;
    return `/api/${url}`;
}

export const getBackendOrigin = (): string | null => {
    if (typeof window === 'undefined') return null;
    return window.__WF_BACKEND_ORIGIN || null;
};
