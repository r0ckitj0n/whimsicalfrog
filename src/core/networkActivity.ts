export interface NetworkActivitySnapshot {
    activeCount: number;
    isActive: boolean;
}

type NetworkActivityListener = (snapshot: NetworkActivitySnapshot) => void;

const listeners = new Set<NetworkActivityListener>();

let activeCount = 0;
let hasUserInteracted = false;
let installed = false;
let generateIntentActive = false;
let networkSeenSinceGenerateIntent = false;
let generateIntentTimeoutId: number | null = null;
let generateIntentHardTimeoutId: number | null = null;
let generateIntentClearDelayId: number | null = null;
let originalFetch: typeof window.fetch | null = null;
let originalXhrOpen: typeof XMLHttpRequest.prototype.open | null = null;
let originalXhrSend: typeof XMLHttpRequest.prototype.send | null = null;

const publish = () => {
    const snapshot: NetworkActivitySnapshot = {
        activeCount,
        isActive: activeCount > 0 || generateIntentActive
    };

    listeners.forEach((listener) => listener(snapshot));
};

const increment = () => {
    activeCount += 1;
    if (generateIntentActive) {
        networkSeenSinceGenerateIntent = true;
    }
    publish();
};

const decrement = () => {
    activeCount = Math.max(0, activeCount - 1);

    if (generateIntentActive && networkSeenSinceGenerateIntent && activeCount === 0) {
        if (generateIntentClearDelayId) window.clearTimeout(generateIntentClearDelayId);
        generateIntentClearDelayId = window.setTimeout(() => {
            if (activeCount === 0) clearGenerateIntent();
        }, 220);
    }

    publish();
};

const clearGenerateIntent = () => {
    generateIntentActive = false;
    networkSeenSinceGenerateIntent = false;

    if (generateIntentTimeoutId) {
        window.clearTimeout(generateIntentTimeoutId);
        generateIntentTimeoutId = null;
    }

    if (generateIntentHardTimeoutId) {
        window.clearTimeout(generateIntentHardTimeoutId);
        generateIntentHardTimeoutId = null;
    }

    if (generateIntentClearDelayId) {
        window.clearTimeout(generateIntentClearDelayId);
        generateIntentClearDelayId = null;
    }
};

const isApiRequestUrl = (rawUrl: string) => {
    try {
        const parsedUrl = new URL(rawUrl, window.location.origin);
        const normalizedPath = parsedUrl.pathname.toLowerCase();
        return normalizedPath.startsWith('/api/') || normalizedPath.includes('/api/');
    } catch {
        return rawUrl.includes('/api/');
    }
};

const getRequestUrl = (input: RequestInfo | URL): string => {
    if (typeof input === 'string') return input;
    if (input instanceof URL) return input.toString();
    return input.url;
};

const shouldTrack = (url: string) => hasUserInteracted && isApiRequestUrl(url);

const markUserInteracted = () => {
    hasUserInteracted = true;
};

const getActionableElement = (target: EventTarget | null): HTMLElement | null => {
    if (!(target instanceof Element)) return null;
    const actionable = target.closest('button, input[type="button"], input[type="submit"], [role="button"]');
    return actionable instanceof HTMLElement ? actionable : null;
};

const getActionLabel = (element: HTMLElement): string => {
    if (element instanceof HTMLInputElement) {
        return (element.value || '').trim();
    }

    return (element.innerText || element.textContent || '').trim();
};

const isGenerateAction = (label: string): boolean => {
    const normalizedLabel = label.trim().toLowerCase();
    return normalizedLabel.startsWith('generate');
};

const markGenerateIntent = (event: Event) => {
    const actionable = getActionableElement(event.target);
    if (!actionable) return;

    const label = getActionLabel(actionable);
    if (!isGenerateAction(label)) return;

    generateIntentActive = true;
    networkSeenSinceGenerateIntent = false;

    if (generateIntentTimeoutId) window.clearTimeout(generateIntentTimeoutId);
    if (generateIntentHardTimeoutId) window.clearTimeout(generateIntentHardTimeoutId);
    if (generateIntentClearDelayId) window.clearTimeout(generateIntentClearDelayId);

    generateIntentTimeoutId = window.setTimeout(() => {
        if (!networkSeenSinceGenerateIntent && activeCount === 0) {
            clearGenerateIntent();
            publish();
        }
    }, 1200);

    generateIntentHardTimeoutId = window.setTimeout(() => {
        clearGenerateIntent();
        publish();
    }, 30000);

    publish();
};

const installInteractionListeners = () => {
    document.addEventListener('pointerdown', markUserInteracted, { capture: true, passive: true });
    document.addEventListener('keydown', markUserInteracted, { capture: true, passive: true });
    document.addEventListener('touchstart', markUserInteracted, { capture: true, passive: true });
    document.addEventListener('click', markGenerateIntent, { capture: true, passive: true });
};

const installFetchTracker = () => {
    originalFetch = window.fetch.bind(window);

    window.fetch = async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
        const requestUrl = getRequestUrl(input);
        const track = shouldTrack(requestUrl);

        if (track) increment();

        try {
            return await originalFetch!(input, init);
        } finally {
            if (track) decrement();
        }
    };
};

const installXhrTracker = () => {
    originalXhrOpen = XMLHttpRequest.prototype.open;
    originalXhrSend = XMLHttpRequest.prototype.send;

    XMLHttpRequest.prototype.open = function open(
        method: string,
        url: string | URL,
        async?: boolean,
        username?: string | null,
        password?: string | null
    ): void {
        const requestUrl = String(url);
        this.__wfTrackRequest = shouldTrack(requestUrl);

        if (username !== undefined || password !== undefined) {
            return originalXhrOpen!.call(this, method, requestUrl, async ?? true, username ?? null, password ?? null);
        }

        return originalXhrOpen!.call(this, method, requestUrl, async ?? true);
    };

    XMLHttpRequest.prototype.send = function send(body?: Document | XMLHttpRequestBodyInit | null): void {
        if (this.__wfTrackRequest) {
            increment();
            this.addEventListener('loadend', decrement, { once: true });
        }

        return originalXhrSend!.call(this, body);
    };
};

export const installNetworkActivityTracker = () => {
    if (installed || typeof window === 'undefined' || typeof document === 'undefined') return;

    installInteractionListeners();
    installFetchTracker();
    installXhrTracker();

    installed = true;
};

export const subscribeToNetworkActivity = (listener: NetworkActivityListener) => {
    listeners.add(listener);
    listener({ activeCount, isActive: activeCount > 0 || generateIntentActive });

    return () => {
        listeners.delete(listener);
    };
};

declare global {
    interface XMLHttpRequest {
        __wfTrackRequest?: boolean;
    }
}
