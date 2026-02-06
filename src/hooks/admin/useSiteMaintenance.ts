import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { AUTH, BACKUP_TYPE, BACKUP_DESTINATION } from '../../core/constants.js';
import type { IBackupDetails, IDatabaseInfo, ISystemConfig, IScanResult } from '../../types/maintenance.js';

// Re-export for backward compatibility
export type { IBackupDetails, IDatabaseInfo, ISystemConfig, IScanResult } from '../../types/maintenance.js';

export const useSiteMaintenance = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [dbInfo, setDbInfo] = useState<IDatabaseInfo | null>(null);
    const [systemConfig, setSystemConfig] = useState<ISystemConfig | null>(null);

    const fetchDatabaseInfo = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IDatabaseInfo>('/api/get_database_info.php');
            if (res) {
                setDbInfo(res);
                return res;
            } else {
                throw new Error('Failed to fetch database info');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchDatabaseInfo failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchSystemConfig = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<ISystemConfig>('/api/get_system_config.php');
            if (res) {
                setSystemConfig(res);
                return res;
            } else {
                throw new Error('Failed to fetch system config');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchSystemConfig failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const executeBackup = useCallback(async (type: typeof BACKUP_TYPE[keyof typeof BACKUP_TYPE] = BACKUP_TYPE.FULL) => {
        setIsLoading(true);
        setError(null);
        try {
            const endpoint = type === BACKUP_TYPE.DATABASE ? '/api/backup_database.php' : '/api/backup_website.php';
            const res = await ApiClient.post<any>(endpoint, { destination: BACKUP_DESTINATION.CLOUD });

            if (res && res.success) {
                // Normalize response: API uses 'path', IBackupDetails expects 'filepath'
                return {
                    ...res,
                    filepath: res.path || res.filepath || 'Unknown',
                    timestamp: res.timestamp || Date.now() / 1000,
                    destinations: res.destinations || ['server']
                } as IBackupDetails;
            } else {
                throw new Error(res?.error || 'Server reported failure without a specific error message.');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'An obscure error occurred in the digital void.';
            logger.error('executeBackup failed', err);
            setError(message);
            return { success: false, error: message } as IBackupDetails;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const compactRepairDatabase = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.post<any>('/api/compact_repair_database.php', {});
            if (res) {
                return res;
            } else {
                throw new Error('Database optimization failed');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('compactRepairDatabase failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const scanConnections = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IScanResult>(`/api/convert_to_centralized_db.php?action=scan&format=json&admin_token=${AUTH.ADMIN_TOKEN}`);
            if (res) {
                return res;
            } else {
                throw new Error('Scan failed');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('scanConnections failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const convertConnections = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IScanResult>(`/api/convert_to_centralized_db.php?action=convert&format=json&admin_token=${AUTH.ADMIN_TOKEN}`);
            if (res) {
                return res;
            } else {
                throw new Error('Conversion failed');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('convertConnections failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        isLoading,
        error,
        dbInfo,
        systemConfig,
        fetchDatabaseInfo,
        fetchSystemConfig,
        executeBackup,
        compactRepairDatabase,
        scanConnections,
        convertConnections
    };
};
