import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IHealthStatus, IHealthBackgroundResponse, IHealthItemResponse } from '../../types/maintenance.js';

// Re-export for backward compatibility
export type { IHealthStatus, IHealthBackgroundResponse, IHealthItemResponse } from '../../types/maintenance.js';


export const useHealthChecks = (isAdmin: boolean = false) => {
    const [status, setStatus] = useState<IHealthStatus | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    const runChecks = useCallback(async () => {
        if (!isAdmin) return;

        setIsLoading(true);
        try {
            const [bgRes, itemRes] = await Promise.all([
                ApiClient.get<IHealthBackgroundResponse>('/api/health_backgrounds.php'),
                ApiClient.get<IHealthItemResponse>('/api/health_items.php')
            ]);

            const backgrounds = bgRes?.success ? {
                missingActive: bgRes.data?.missingActive || [],
                missingFiles: bgRes.data?.missingFiles || []
            } : { missingActive: [], missingFiles: [] };

            const items = itemRes?.success ? {
                noPrimary: itemRes.data?.counts?.noPrimary || 0,
                missingFiles: itemRes.data?.counts?.missingFiles || 0
            } : { noPrimary: 0, missingFiles: 0 };

            const page = {
                hasBackground: !!document.body.getAttribute('data-bg-url'),
                missingImagesCount: document.querySelectorAll('.wf-missing-item-image').length
            };

            const newStatus = { backgrounds, items, page };
            setStatus(newStatus);

            // Show notifications for critical issues
            if (backgrounds.missingFiles.length > 0) {
                window.showError?.(`Missing background files for: ${backgrounds.missingFiles.join(', ')}`, { title: 'Health Alert' });
            }
            if (items.missingFiles > 0) {
                window.showError?.(`${items.missingFiles} items have missing image files on disk.`, { title: 'Health Alert' });
            }

            return newStatus;

        } catch (err) {
            logger.warn('[useHealthChecks] Failed to run health checks', err);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [isAdmin]);

    useEffect(() => {
        if (isAdmin) {
            runChecks();
        }
    }, [isAdmin, runChecks]);

    return {
        status,
        isLoading,
        runChecks
    };
};
