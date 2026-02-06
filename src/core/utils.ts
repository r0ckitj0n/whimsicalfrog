/**
 * Utility Functions
 * Common utility functions used throughout the application.
 */

/**
 * Debounce function
 * Delays invoking a function until after `wait` milliseconds have elapsed since the last time it was invoked.
 */
export function debounce<T extends (...args: unknown[]) => unknown>(func: T, wait: number) {
    let timeout: ReturnType<typeof setTimeout> | null = null;
    return function executedFunction(...args: Parameters<T>) {
        const later = () => {
            timeout = null;
            func(...args);
        };
        if (timeout) clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function
 * Creates a throttled function that only invokes `func` at most once per every `limit` milliseconds.
 */
export function throttle<T extends (...args: unknown[]) => unknown>(func: T, limit: number) {
    let inThrottle = false;
    return function (this: unknown, ...args: Parameters<T>) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}

/**
 * Simple event emitter
 */
export function createEventEmitter() {
    const events: Record<string, Function[]> = {};
    return {
        on(event: string, callback: (data?: unknown) => void) {
            if (!events[event]) {
                events[event] = [];
            }
            events[event].push(callback);
        },
        emit(event: string, data?: unknown) {
            if (events[event]) {
                events[event].forEach(callback => callback(data));
            }
        },
        off(event: string, callback: (data?: unknown) => void) {
            if (events[event]) {
                events[event] = events[event].filter(cb => cb !== callback);
            }
        },
    };
}

/**
 * Waits for a function at a given object path to become available.
 */
export async function waitForFunction(functionPath: string, root: Record<string, unknown> = window as unknown as Record<string, unknown>, timeout: number = 5000, interval: number = 100): Promise<boolean> {
    const parts = functionPath.split('.');
    const deadline = Date.now() + timeout;

    return new Promise((resolve) => {
        function check() {
            let obj: unknown = root;
            for (const part of parts) {
                if (obj && typeof obj === 'object' && part in (obj as Record<string, unknown>)) {
                    obj = (obj as Record<string, unknown>)[part];
                } else {
                    obj = null;
                    break;
                }
            }
            if (typeof obj === 'function') {
                return resolve(true);
            }
            if (Date.now() > deadline) {
                return resolve(false);
            }
            setTimeout(check, interval);
        }
        check();
    });
}
/**
 * Deep comparison helper for draft vs base state.
 * Treats null, undefined, and empty string as equivalent for non-strict checks.
 */
export function isDraftDirty<T>(draft: T, base: T): boolean {
    if (draft === base) return false;

    // Handle null/undefined/empty string equivalence
    const isEmpty = (v: unknown) => v === null || v === undefined || v === '';
    if (isEmpty(draft) && isEmpty(base)) return false;

    if (typeof draft !== 'object' || typeof base !== 'object' || draft === null || base === null) {
        return draft !== base;
    }

    const draftKeys = Object.keys(draft);
    const baseKeys = Object.keys(base);
    const allKeys = new Set([...draftKeys, ...baseKeys]);

    for (const key of allKeys) {
        const draftRecord = draft as Record<string, unknown>;
        const baseRecord = base as Record<string, unknown>;
        const val1 = draftRecord[key];
        const val2 = baseRecord[key];

        if (typeof val1 === 'object' && typeof val2 === 'object' && val1 !== null && val2 !== null) {
            if (isDraftDirty(val1, val2)) return true;
        } else {
            // Primitive comparison with empty value normalization and trimming
            const normalizedVal1 = isEmpty(val1) ? '' : (typeof val1 === 'string' ? val1.trim() : val1);
            const normalizedVal2 = isEmpty(val2) ? '' : (typeof val2 === 'string' ? val2.trim() : val2);
            if (normalizedVal1 !== normalizedVal2) return true;
        }
    }

    return false;
}
