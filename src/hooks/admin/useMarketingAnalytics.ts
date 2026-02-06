import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IMarketingAnalyticsData } from '../../types/dashboard.js';

// Re-export for backward compatibility
export type { IMarketingAnalyticsData } from '../../types/dashboard.js';

export const useMarketingAnalytics = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [data, setData] = useState<IMarketingAnalyticsData | null>(null);
    const [error, setError] = useState<string | null>(null);

    const fetchAnalytics = useCallback(async (timeframe: number) => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await ApiClient.get<IMarketingAnalyticsData>(`/api/marketing_overview.php?timeframe=${timeframe}`);
            if (data) {
                setData(data);
            } else {
                setError('Failed to load marketing analytics');
            }
        } catch (err) {
            logger.error('[useMarketingAnalytics] fetch failed', err);
            setError('Unable to load marketing analytics');
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        isLoading,
        data,
        error,
        fetchAnalytics
    };
};
