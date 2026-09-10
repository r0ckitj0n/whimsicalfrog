export const BACKGROUND_URL_PREFIX = '/images/backgrounds/';

/** Basename only — use extractBackgroundRelativePath when realistic/ must be kept. */
export const extractBackgroundFilename = (value: string): string => {
    const raw = String(value || '').trim();
    if (!raw) return '';

    const withoutQuery = raw.split(/[?#]/, 1)[0].replace(/\\/g, '/');
    const parts = withoutQuery.split('/').filter(Boolean);
    return parts.length > 0 ? parts[parts.length - 1] : '';
};

/**
 * Keep backgrounds/realistic/... relative paths intact. Basename-only rewrites
 * produce /images/backgrounds/realistic-roomS.webp (missing) and force cartoons.
 */
export const extractBackgroundRelativePath = (value: string): string => {
    const raw = String(value || '').trim();
    if (!raw) return '';
    const withoutQuery = raw.split(/[?#]/, 1)[0].replace(/\\/g, '/');
    const backgroundsMatch = withoutQuery.match(/(?:^|\/)(backgrounds\/(?:realistic\/)?[^/]+)$/i);
    if (backgroundsMatch?.[1]) return backgroundsMatch[1];
    const realisticMatch = withoutQuery.match(/(?:^|\/)(realistic\/[^/]+)$/i);
    if (realisticMatch?.[1]) return `backgrounds/${realisticMatch[1]}`;
    const filename = extractBackgroundFilename(withoutQuery);
    return filename ? `backgrounds/${filename}` : '';
};

export const buildBackgroundUrl = (filenameOrRelative: string): string => {
    const relative = extractBackgroundRelativePath(filenameOrRelative);
    return relative ? `/images/${relative}` : '';
};

export const normalizeBackgroundUrlToLibrary = (value: string): string => {
    return buildBackgroundUrl(value);
};

export const resolveBackgroundAssetUrl = (value: string): string => {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.startsWith('/images/backgrounds/')) return raw;
    if (raw.startsWith('/images/')) {
        return buildBackgroundUrl(raw);
    }
    if (raw.startsWith('images/backgrounds/')) {
        return `/${raw}`;
    }
    if (raw.startsWith('backgrounds/')) {
        return `/images/${raw}`;
    }
    return buildBackgroundUrl(raw);
};
