export const BACKGROUND_URL_PREFIX = '/images/backgrounds/';

export const extractBackgroundFilename = (value: string): string => {
    const raw = String(value || '').trim();
    if (!raw) return '';

    const withoutQuery = raw.split(/[?#]/, 1)[0].replace(/\\/g, '/');
    const parts = withoutQuery.split('/').filter(Boolean);
    return parts.length > 0 ? parts[parts.length - 1] : '';
};

export const buildBackgroundUrl = (filename: string): string => {
    const clean = extractBackgroundFilename(filename);
    return clean ? `${BACKGROUND_URL_PREFIX}${clean}` : '';
};

export const normalizeBackgroundUrlToLibrary = (value: string): string => {
    return buildBackgroundUrl(extractBackgroundFilename(value));
};

export const resolveBackgroundAssetUrl = (value: string): string => {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.startsWith('/images/backgrounds/')) return raw;
    if (raw.startsWith('/images/')) {
        return buildBackgroundUrl(extractBackgroundFilename(raw));
    }
    if (raw.startsWith('images/backgrounds/')) {
        return `/${raw}`;
    }
    if (raw.startsWith('backgrounds/')) {
        return `/images/${raw}`;
    }
    return buildBackgroundUrl(raw);
};
