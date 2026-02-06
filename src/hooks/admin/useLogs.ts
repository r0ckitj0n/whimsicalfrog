import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    ILogFile,
    ILogEntry,
    ILogListResponse,
    ILogStatusResponse,
    ILogContentResponse,
    ILogActionResponse
} from '../../types/admin.js';

// Re-export for backward compatibility
export type {
    ILogFile,
    ILogEntry,
    ILogListResponse,
    ILogStatusResponse,
    ILogContentResponse,
    ILogActionResponse
} from '../../types/admin.js';


export const useLogs = () => {
    const [logs, setLogs] = useState<ILogFile[]>([]);
    const [status, setStatus] = useState<Record<string, unknown> | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchLogList = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const [listRes, statusRes] = await Promise.all([
                ApiClient.get<ILogListResponse>('/api/website_logs.php', { action: 'list_logs' }),
                ApiClient.get<ILogStatusResponse>('/api/website_logs.php', { action: 'get_status' })
            ]);

            if (listRes?.success) setLogs(listRes.logs || []);
            if (statusRes?.success) setStatus(statusRes.status);
        } catch (err) {
            logger.error('[useLogs] fetch failed', err);
            setError('Failed to load logs');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchLogContent = async (type: string, page: number = 1, limit: number = 50) => {
        try {
            const res = await ApiClient.get<ILogContentResponse>('/api/website_logs.php', {
                action: 'get_log',
                type,
                page,
                limit
            });
            return res?.success ? res.entries : [];
        } catch (err) {
            logger.error('[useLogs] fetchLogContent failed', err);
            return [];
        }
    };

    const clearLog = async (type: string) => {
        try {
            const res = await ApiClient.post<any>('/api/website_logs.php', {
                action: 'clear_log',
                type
            });
            if (res) {
                await fetchLogList();
                return { success: true };
            }
            return { success: false, error: 'Clear failed' };
        } catch (err) {
            return { success: false, error: 'Network error' };
        }
    };

    const clearAllLogs = async () => {
        try {
            const res = await ApiClient.post<any>('/api/website_logs.php', { action: 'clear_all_logs' });
            if (res) {
                await fetchLogList();
                return { success: true };
            }
            return { success: false, error: 'Clear failed' };
        } catch (err) {
            return { success: false, error: 'Network error' };
        }
    };

    useEffect(() => {
        fetchLogList();
    }, [fetchLogList]);

    return {
        logs,
        status,
        isLoading,
        error,
        fetchLogList,
        fetchLogContent,
        clearLog,
        clearAllLogs
    };
};
