import { LOADING_MESSAGE_PAIRS } from '../data/loadingMessages.js';
import type { ILoadingMessagePair } from '../types/loadingSplash.js';

const HISTORY_KEY = 'wf_loading_msg_history';
const LAST_ID_KEY = 'wf_loading_msg_last';

const safeStorage = (): Storage | null => {
    try {
        if (typeof window === 'undefined' || !window.localStorage) return null;
        const probeKey = '__wf_loading_probe__';
        window.localStorage.setItem(probeKey, '1');
        window.localStorage.removeItem(probeKey);
        return window.localStorage;
    } catch {
        return null;
    }
};

const readNumberArray = (storage: Storage, key: string): number[] => {
    try {
        const raw = storage.getItem(key);
        if (!raw) return [];
        const parsed: unknown = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];
        return parsed
            .map((value) => Number(value))
            .filter((value) => Number.isFinite(value) && value > 0);
    } catch {
        return [];
    }
};

const readLastId = (storage: Storage): number | null => {
    try {
        const raw = storage.getItem(LAST_ID_KEY);
        if (raw == null || raw === '') return null;
        const value = Number(raw);
        return Number.isFinite(value) && value > 0 ? value : null;
    } catch {
        return null;
    }
};

const pickRandom = <T>(items: readonly T[]): T => {
    const index = Math.floor(Math.random() * items.length);
    return items[Math.max(0, Math.min(items.length - 1, index))];
};

const fallbackPair = (): ILoadingMessagePair =>
    pickRandom(LOADING_MESSAGE_PAIRS) ?? LOADING_MESSAGE_PAIRS[0];

/**
 * Selects the next unseen two-line message for this visitor.
 * Completes a full 50-message cycle before reshuffling, and prevents
 * the first message of a new cycle from matching the previous cycle's last.
 */
export const selectNextLoadingMessage = (): ILoadingMessagePair => {
    const all = LOADING_MESSAGE_PAIRS;
    if (!all.length) {
        return { id: 0, line1: 'The pond is waking up…', line2: 'Thanks for hopping by.' };
    }

    const storage = safeStorage();
    if (!storage) {
        return fallbackPair();
    }

    try {
        let history = readNumberArray(storage, HISTORY_KEY);
        const validIds = new Set(all.map((pair) => pair.id));
        history = history.filter((id) => validIds.has(id));

        let pool = all.filter((pair) => !history.includes(pair.id));
        let lastId = readLastId(storage);

        if (pool.length === 0) {
            // Start a new cycle; avoid repeating the final message of the previous cycle.
            history = [];
            pool = lastId == null ? [...all] : all.filter((pair) => pair.id !== lastId);
            if (pool.length === 0) {
                pool = [...all];
            }
        }

        const selected = pickRandom(pool);
        const nextHistory = [...history, selected.id];
        storage.setItem(HISTORY_KEY, JSON.stringify(nextHistory));
        storage.setItem(LAST_ID_KEY, String(selected.id));
        return selected;
    } catch {
        return fallbackPair();
    }
};
