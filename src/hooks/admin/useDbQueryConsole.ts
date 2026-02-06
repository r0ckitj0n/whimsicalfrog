import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IDbQueryResults, IDbQueryResponse } from '../../types/maintenance.js';

// Re-export for backward compatibility
export type { IDbQueryResults, IDbQueryResponse } from '../../types/maintenance.js';



export const useDbQueryConsole = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [results, setResults] = useState<IDbQueryResults | null>(null);
    const [error, setError] = useState<string | null>(null);

    const executeQuery = useCallback(async (sql: string, env: string = 'local') => {
        setIsLoading(true);
        setError(null);
        try {
            const response = await ApiClient.request<any>('/api/db_tools.php?action=query&sql=' + encodeURIComponent(sql) + '&env=' + env);

            if (response && response.success) {
                const rows = response.rows || [];
                setResults({
                    rows: rows,
                    columns: rows.length > 0 ? Object.keys(rows[0]) : [],
                    rowCount: rows.length
                });
            } else {
                setError(response?.message || 'Query failed.');
            }
        } catch (err) {
            logger.error('[DbQueryConsole] execution failed', err);
            setError('Unable to execute query.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    return {
        isLoading,
        results,
        error,
        executeQuery,
        setResults
    };
};
