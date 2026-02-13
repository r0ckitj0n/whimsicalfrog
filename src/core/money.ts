// Small money helpers used by admin breakdown UIs.
// Keep all calculations in integer cents to avoid float drift.

export const toCents = (value: number): number => {
    const n = Number(value);
    if (!Number.isFinite(n)) return 0;
    return Math.round(n * 100);
};

export const fromCents = (cents: number): number => {
    const n = Number(cents);
    if (!Number.isFinite(n)) return 0;
    return Math.round(n) / 100;
};

/**
 * Redistribute values so they sum to targetTotalCents, splitting the delta evenly.
 * Guarantees non-negative results. If a decrease would push an entry below 0, it is clamped
 * and the remaining delta is redistributed among the remaining entries.
 */
export const distributeEvenNonNegative = (currentCents: number[], targetTotalCents: number): number[] => {
    const current = currentCents.map((v) => Math.max(0, Math.round(Number(v) || 0)));
    const target = Math.max(0, Math.round(Number(targetTotalCents) || 0));

    const sum = current.reduce((a, b) => a + b, 0);
    let delta = target - sum;
    if (delta === 0) return current;

    const result = [...current];

    // When decreasing, only entries with >0 can absorb the delta.
    let adjustable = result
        .map((_, idx) => idx)
        .filter((idx) => (delta > 0 ? true : result[idx] > 0));

    while (delta !== 0 && adjustable.length > 0) {
        const n = adjustable.length;
        const step = delta > 0
            ? Math.floor(delta / n)
            : -Math.floor((-delta) / n);

        if (step === 0) {
            const sign = delta > 0 ? 1 : -1;
            for (const idx of adjustable) {
                if (delta === 0) break;
                if (sign < 0 && result[idx] === 0) continue;
                const next = result[idx] + sign;
                if (next < 0) {
                    result[idx] = 0;
                    continue;
                }
                result[idx] = next;
                delta -= sign;
            }
        } else if (step > 0) {
            for (const idx of adjustable) {
                if (delta === 0) break;
                result[idx] += step;
                delta -= step;
            }
        } else {
            const decr = -step;
            for (const idx of adjustable) {
                if (delta === 0) break;
                const take = Math.min(result[idx], decr);
                result[idx] -= take;
                delta += take; // delta is negative, so adding moves toward 0
            }
        }

        if (delta < 0) {
            adjustable = adjustable.filter((idx) => result[idx] > 0);
        }
    }

    return result;
};

