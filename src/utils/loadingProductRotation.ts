import type { IFeaturedSplashProduct } from '../types/loadingSplash.js';

const PRODUCT_HISTORY_KEY = 'wf_loading_product_history';
const PRODUCT_LAST_SKU_KEY = 'wf_loading_product_last';

const safeStorage = (): Storage | null => {
    try {
        if (typeof window === 'undefined' || !window.localStorage) return null;
        const probeKey = '__wf_loading_product_probe__';
        window.localStorage.setItem(probeKey, '1');
        window.localStorage.removeItem(probeKey);
        return window.localStorage;
    } catch {
        return null;
    }
};

const readStringArray = (storage: Storage, key: string): string[] => {
    try {
        const raw = storage.getItem(key);
        if (!raw) return [];
        const parsed: unknown = JSON.parse(raw);
        if (!Array.isArray(parsed)) return [];
        return parsed.map((value) => String(value)).filter((value) => value.length > 0);
    } catch {
        return [];
    }
};

const pickRandom = <T>(items: readonly T[]): T => {
    const index = Math.floor(Math.random() * items.length);
    return items[Math.max(0, Math.min(items.length - 1, index))];
};

/**
 * Randomly selects a featured product, preferring ones this visitor has not
 * recently seen. Cycles through the current eligible set before repeating.
 */
export const selectFeaturedProduct = (
    products: readonly IFeaturedSplashProduct[]
): IFeaturedSplashProduct | null => {
    if (!products.length) return null;

    const storage = safeStorage();
    if (!storage) {
        return pickRandom(products);
    }

    try {
        const eligibleSkus = new Set(products.map((product) => product.sku));
        let history = readStringArray(storage, PRODUCT_HISTORY_KEY).filter((sku) =>
            eligibleSkus.has(sku)
        );
        let pool = products.filter((product) => !history.includes(product.sku));

        if (pool.length === 0) {
            const lastSku = storage.getItem(PRODUCT_LAST_SKU_KEY);
            history = [];
            pool =
                lastSku && products.length > 1
                    ? products.filter((product) => product.sku !== lastSku)
                    : [...products];
            if (pool.length === 0) {
                pool = [...products];
            }
        }

        const selected = pickRandom(pool);
        storage.setItem(PRODUCT_HISTORY_KEY, JSON.stringify([...history, selected.sku]));
        storage.setItem(PRODUCT_LAST_SKU_KEY, selected.sku);
        return selected;
    } catch {
        return pickRandom(products);
    }
};
