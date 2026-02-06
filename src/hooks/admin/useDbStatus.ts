import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IDbStatus, ISyncStatus, IDbStatusResponse } from '../../types/maintenance.js';

// Re-export for backward compatibility
export type { IDbStatus, ISyncStatus, IDbStatusResponse } from '../../types/maintenance.js';


export const useDbStatus = () => {
    const [localStatus, setLocalStatus] = useState<IDbStatus | null>(null);
    const [liveStatus, setLiveStatus] = useState<IDbStatus | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchStatus = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await ApiClient.request<IDbStatusResponse>('/api/db_tools.php?action=status');
            if (response && response.success) {
                // JsonResponseParser unwraps the 'data' property, so we access local/live directly
                if (response.local) {
                    setLocalStatus({
                        online: response.local.online,
                        mysql_version: response.local.mysql_version,
                        database: response.local.database,
                        host: response.local.host,
                        error: response.local.error
                    });
                }
                if (response.live) {
                    setLiveStatus({
                        online: response.live.online,
                        mysql_version: response.live.mysql_version,
                        database: response.live.database,
                        host: response.live.host,
                        error: response.live.error
                    });
                }
            } else {
                setError(response?.message || 'Failed to fetch DB status.');
            }
        } catch (err) {
            logger.error('[DbStatus] fetch failed', err);
            setError('Unable to fetch database status.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const runSmokeTest = async (target: 'current' | 'local' | 'live') => {
        try {
            const response = await ApiClient.request<{ success: boolean; error?: string }>((`/api/smoke_tests.php?target=${target}`));
            return response;
        } catch (err) {
            logger.error(`[DbStatus] Smoke test failed for ${target}`, err);
            return { success: false, error: 'Smoke test failed' };
        }
    };

    return {
        localStatus,
        liveStatus,
        isLoading,
        error,
        fetchStatus,
        runSmokeTest
    };
};
