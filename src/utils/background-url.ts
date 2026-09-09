export const BACKGROUND_URL_PREFIX = '/images/backgrounds/';

const sanitizeRelativeParts = (parts: string[]): string[] =>
    parts.filter((part) => part !== '' && part !== '.' && part !== '..');

/**
 * Extract a safe backgrounds-library relative path, preserving a single subdirectory
 * such as `realistic/file.webp`.
 */
export const extractBackgroundRelativePath = (value: string): string => {
    const raw = String(value || '').trim();
    if (!raw) return '';

    const withoutQuery = raw.split(/[?#]/, 1)[0].replace(/\\/g, '/');
    let path = withoutQuery.replace(/^\/+/, '');

    if (/^images\/backgrounds\//i.test(path)) {
        path = path.replace(/^images\/backgrounds\//i, '');
    } else if (/^backgrounds\//i.test(path)) {
        path = path.replace(/^backgrounds\//i, '');
    } else if (/^images\//i.test(path)) {
        path = path.replace(/^images\//i, '');
        if (/^backgrounds\//i.test(path)) {
            path = path.replace(/^backgrounds\//i, '');
        }
    }

    const parts = sanitizeRelativeParts(path.split('/'));
    if (parts.length === 0) return '';
    if (parts.length === 1) return parts[0];
    if (parts.length === 2 && /^[A-Za-z0-9_-]+$/.test(parts[0])) {
        return `${parts[0]}/${parts[1]}`;
    }
    return parts[parts.length - 1];
};

/** @deprecated Prefer extractBackgroundRelativePath to preserve subdirectories. */
export const extractBackgroundFilename = (value: string): string => {
    const relative = extractBackgroundRelativePath(value);
    if (!relative) return '';
    const parts = relative.split('/');
    return parts[parts.length - 1] || '';
};

export const buildBackgroundUrl = (filename: string): string => {
    const clean = extractBackgroundRelativePath(filename);
    return clean ? `${BACKGROUND_URL_PREFIX}${clean}` : '';
};

export const normalizeBackgroundUrlToLibrary = (value: string): string => {
    return buildBackgroundUrl(value);
};

export const resolveBackgroundAssetUrl = (value: string): string => {
    const raw = String(value || '').trim();
    if (!raw) return '';
    if (/^https?:\/\//i.test(raw)) return raw;
    if (raw.startsWith('/images/backgrounds/')) return raw;
    if (raw.startsWith('images/backgrounds/')) {
        return `/${raw}`;
    }
    if (raw.startsWith('backgrounds/')) {
        return `/images/${raw}`;
    }
    if (raw.startsWith('/images/')) {
        return buildBackgroundUrl(raw);
    }
    return buildBackgroundUrl(raw);
};
