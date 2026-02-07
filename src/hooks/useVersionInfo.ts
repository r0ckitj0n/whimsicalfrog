import { useCallback, useEffect, useState } from 'react';
import ApiClient from '../core/ApiClient.js';
import type { IVersionInfo, IVersionInfoResponse } from '../types/version.js';

export const useVersionInfo = (enabled: boolean) => {
    const [versionInfo, setVersionInfo] = useState<IVersionInfo | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchVersionInfo = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await ApiClient.get<IVersionInfoResponse>('/api/version_info.php');
            if (!response?.success || !response.data) {
                throw new Error(response?.message || 'Failed to load version info');
            }
            setVersionInfo(response.data);
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Failed to load version info';
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        if (!enabled) {
            return;
        }
        fetchVersionInfo();
    }, [enabled, fetchVersionInfo]);

    return {
        versionInfo,
        isLoading,
        error,
        refreshVersionInfo: fetchVersionInfo
    };
};
