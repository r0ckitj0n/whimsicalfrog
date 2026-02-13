import { useCallback, useState } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IDbMigrationsAuditResponse } from '../../types/dbMigrations.js';

export const useDbMigrationsAudit = () => {
    const [data, setData] = useState<IDbMigrationsAuditResponse | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const run = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IDbMigrationsAuditResponse>('/api/db_migrations_audit.php', { action: 'report' });
            if (res?.success) {
                setData(res);
                return res;
            }
            const msg = res?.error || res?.message || 'DB migrations audit failed';
            setError(msg);
            setData(res || null);
            return null;
        } catch (err) {
            logger.error('[useDbMigrationsAudit] run failed', err);
            const msg = err instanceof Error ? err.message : 'DB migrations audit failed';
            setError(msg);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    return { data, isLoading, error, run };
};

