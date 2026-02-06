import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type {
    IEmailLog,
    IPagination,
    IEmailHistoryResponse,
    IEmailLogDetailResponse
} from '../../types/email.js';

// Re-export for backward compatibility
export type {
    IEmailLog,
    IPagination,
    IEmailHistoryResponse,
    IEmailLogDetailResponse
} from '../../types/email.js';



export const useEmailHistory = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [logs, setLogs] = useState<IEmailLog[]>([]);
    const [pagination, setPagination] = useState<IPagination | null>(null);
    const [error, setError] = useState<string | null>(null);

    const fetchLogs = useCallback(async (params: {
        page?: number;
        limit?: number;
        search?: string;
        from?: string;
        to?: string;
        type?: string;
        status?: string;
        sort?: string;
    } = {}) => {
        setIsLoading(true);
        setError(null);
        try {
            const queryParams = new URLSearchParams();
            Object.entries(params).forEach(([key, value]) => {
                if (value !== undefined && value !== '') queryParams.append(key, String(value));
            });

            const res = await ApiClient.get<IEmailHistoryResponse>(`/api/email_history.php?action=list&${queryParams.toString()}`);
            if (res && res.success) {
                setLogs(res.data || []);
                setPagination(res.pagination || null);
            } else {
                setError(res?.error || 'Failed to load email history.');
            }
        } catch (err) {
            logger.error('[EmailHistory] fetch failed', err);
            setError('Unable to load email history.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const getLogDetails = async (id: number) => {
        try {
            const res = await ApiClient.get<IEmailLogDetailResponse>(`/api/email_history.php?action=get&id=${id}`);
            return res && res.success ? (res.data || null) : null;
        } catch (err) {
            logger.error('[EmailHistory] get details failed', err);
            return null;
        }
    };

    return {
        isLoading,
        logs,
        pagination,
        error,
        fetchLogs,
        getLogDetails
    };
};
