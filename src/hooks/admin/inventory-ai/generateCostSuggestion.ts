import { toastSuccess, toastError } from '../../../core/toast.js';
import { subscribeToNetworkActivity } from '../../../core/networkActivity.js';
import type { CostSuggestion } from './useCostSuggestions.js';

interface GenerateCostSuggestionParams {
    sku: string;
    name: string;
    description: string;
    category: string;
    tier: string;
    isReadOnly?: boolean;
    showApplyingToast?: boolean;
    imageData?: string;
    fetchCostSuggestion: (params: {
        sku?: string;
        name: string;
        description: string;
        category: string;
        tier?: string;
        useImages?: boolean;
        imageData?: string;
    }) => Promise<CostSuggestion | null>;
    onSuggestionGenerated?: (suggestion: CostSuggestion) => void;
    onApplied?: () => void;
}

const waitForGlobalNetworkIdle = (timeoutMs = 15000, idleWindowMs = 250): Promise<boolean> =>
    new Promise((resolve) => {
        let settled = false;
        let idleTimer: number | null = null;
        let timeoutTimer: number | null = null;

        const cleanup = () => {
            if (idleTimer) window.clearTimeout(idleTimer);
            if (timeoutTimer) window.clearTimeout(timeoutTimer);
            unsubscribe();
        };

        const done = (result: boolean) => {
            if (settled) return;
            settled = true;
            cleanup();
            resolve(result);
        };

        const unsubscribe = subscribeToNetworkActivity(({ isActive }) => {
            if (!isActive) {
                if (!idleTimer) {
                    idleTimer = window.setTimeout(() => done(true), idleWindowMs);
                }
                return;
            }

            if (idleTimer) {
                window.clearTimeout(idleTimer);
                idleTimer = null;
            }
        });

        timeoutTimer = window.setTimeout(() => done(false), timeoutMs);
    });

export const generateCostSuggestion = async ({
    sku,
    name,
    description,
    category,
    tier,
    isReadOnly = false,
    showApplyingToast = false,
    imageData,
    fetchCostSuggestion,
    onSuggestionGenerated,
    onApplied
}: GenerateCostSuggestionParams): Promise<CostSuggestion | null> => {
    toastSuccess('Starting AI cost analysis...');
    try {
        const suggestion = await fetchCostSuggestion({
            sku,
            name,
            description,
            category,
            tier,
            useImages: true,
            imageData
        });

        if (!suggestion) {
            toastError('Failed to generate cost suggestion');
            return null;
        }

        toastSuccess(showApplyingToast ? 'AI analysis complete. Building preview...' : 'AI analysis complete.');

        onSuggestionGenerated?.(suggestion);
        if (!isReadOnly) {
            onApplied?.();
        }

        toastSuccess(showApplyingToast ? 'Cost preview ready (unsaved).' : 'Cost suggestion generated');
        if (showApplyingToast) {
            const settled = await waitForGlobalNetworkIdle();
            if (settled) {
                toastSuccess('All cost generation tasks finished.');
            }
        }
        return suggestion;
    } catch (_err) {
        toastError('Failed to generate cost suggestion');
        return null;
    }
};
