export interface NetworkActivitySnapshot {
    activeCount: number;
    isActive: boolean;
}

type NetworkActivityListener = (snapshot: NetworkActivitySnapshot) => void;

const listeners = new Set<NetworkActivityListener>();

let activeCount = 0;
let hasUserInteracted = false;
let installed = false;
let originalFetch: typeof window.fetch | null = null;
let originalXhrOpen: typeof XMLHttpRequest.prototype.open | null = null;
let originalXhrSend: typeof XMLHttpRequest.prototype.send | null = null;

const publish = () => {
    const snapshot: NetworkActivitySnapshot = {
        activeCount,
        isActive: activeCount > 0
    };

    listeners.forEach((listener) => listener(snapshot));
};

const increment = () => {
    activeCount += 1;
    publish();
};

const decrement = () => {
    activeCount = Math.max(0, activeCount - 1);

    publish();
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

const isIgnoredForGlobalProcessing = (rawUrl: string): boolean => {
    try {
        const parsedUrl = new URL(rawUrl, window.location.origin);
        const path = parsedUrl.pathname.toLowerCase();
        // The estimate call is a quick pre-flight step for the confirm modal and
        // should never show the global processing overlay.
        return path.endsWith('/api/ai_cost_estimate.php');
    } catch {
        const u = rawUrl.toLowerCase();
        return u.includes('/api/ai_cost_estimate.php');
    }
};

const getRequestUrl = (input: RequestInfo | URL): string => {
    if (typeof input === 'string') return input;
    if (input instanceof URL) return input.toString();
    return input.url;
};

const isNonGet = (method: string | null | undefined): boolean => {
    const m = String(method || 'GET').trim().toUpperCase();
    return m !== 'GET';
};

// Only track non-GET API requests for the global processing overlay.
// Rationale: GETs may be background refreshes/polls and should not keep the user-blocking spinner alive.
const shouldTrack = (url: string, method?: string | null) =>
    hasUserInteracted && isApiRequestUrl(url) && isNonGet(method) && !isIgnoredForGlobalProcessing(url);

const markUserInteracted = () => {
    hasUserInteracted = true;
};

const installInteractionListeners = () => {
    document.addEventListener('pointerdown', markUserInteracted, { capture: true, passive: true });
    document.addEventListener('keydown', markUserInteracted, { capture: true, passive: true });
    document.addEventListener('touchstart', markUserInteracted, { capture: true, passive: true });
};

const installFetchTracker = () => {
    originalFetch = window.fetch.bind(window);

    window.fetch = async (input: RequestInfo | URL, init?: RequestInit): Promise<Response> => {
        const requestUrl = getRequestUrl(input);
        const methodFromInit = init?.method;
        const methodFromRequest = (input instanceof Request) ? input.method : undefined;
        const track = shouldTrack(requestUrl, methodFromInit ?? methodFromRequest);

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
        this.__wfTrackRequest = shouldTrack(requestUrl, method);

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
    listener({ activeCount, isActive: activeCount > 0 });

    return () => {
        listeners.delete(listener);
    };
};

declare global {
    interface XMLHttpRequest {
        __wfTrackRequest?: boolean;
    }
}
