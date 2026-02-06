import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { ISelfCheckMetric, ISelfCheckData, ISelfCheckResponse } from '../../types/marketing.js';

// Re-export for backward compatibility
export type { ISelfCheckMetric, ISelfCheckData, ISelfCheckResponse } from '../../types/marketing.js';

declare global {
    interface Window {
        WFToast?: {
            success: (msg: string) => void;
            error: (msg: string) => void;
            info: (msg: string) => void;
        };
    }
}

export const useMarketingSelfCheck = () => {
    const [data, setData] = useState<ISelfCheckData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const runSelfCheck = useCallback(async (isManual = false) => {
        setIsLoading(true);
        setError(null);

        if (isManual && window.WFToast) {
            window.WFToast.info('Running Marketing AI diagnostic...');
        }

        try {
            const response = await ApiClient.request<ISelfCheckResponse>('/api/marketing_selfcheck.php');
            if (response && response.success) {
                setData(response);
                if (isManual && window.WFToast) {
                    window.WFToast.success('Diagnostic complete!');
                }
            } else {
                const msg = response?.message || 'Self-check failed.';
                setError(msg);
                if (isManual && window.WFToast) {
                    window.WFToast.error(msg);
                }
            }
        } catch (err) {
            logger.error('[MarketingSelfCheck] run failed', err);
            const msg = 'Unable to run marketing self-check.';
            setError(msg);
            if (isManual && window.WFToast) {
                window.WFToast.error(msg);
            }
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        data,
        isLoading,
        error,
        runSelfCheck
    };
};
