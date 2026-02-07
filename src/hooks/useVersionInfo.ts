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
            const response = await ApiClient.get<IVersionInfoResponse & Partial<IVersionInfo>>('/api/version_info.php');
            if (!response?.success) {
                throw new Error(response?.message || 'Failed to load version info');
            }

            // ApiClient may flatten { success, data: {...} } into { success, ...data }.
            const parsed = response.data ?? {
                commit_hash: response.commit_hash ?? null,
                commit_short_hash: response.commit_short_hash ?? null,
                commit_subject: response.commit_subject ?? null,
                built_at: response.built_at ?? null,
                deployed_for_live_at: response.deployed_for_live_at ?? null,
                server_time: response.server_time ?? new Date().toISOString(),
                mode: response.mode ?? 'dev',
                source: response.source ?? 'unknown'
            };

            setVersionInfo(parsed);
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
