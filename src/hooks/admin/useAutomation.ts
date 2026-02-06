import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import type { IAutomationPlaybook, IAutomationPlaybookResponse } from '../../types/admin.js';

// Re-export for backward compatibility
export type { IAutomationPlaybook, IAutomationPlaybookResponse } from '../../types/admin.js';



export const useAutomation = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [playbooks, setPlaybooks] = useState<IAutomationPlaybook[]>([]);
    const [error, setError] = useState<string | null>(null);

    const fetchPlaybooks = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<any>('/api/business_settings.php?action=get_by_category&category=marketing');

            // The API may return the root object or a 'data' wrapper (handled by ApiClient)
            const settings = res?.settings || res;
            const val = settings?.marketing_automations;

            if (val) {
                let parsed = val;
                if (typeof val === 'string') {
                    try { parsed = JSON.parse(val) as IAutomationPlaybook[]; } catch (_) { parsed = []; }
                }
                setPlaybooks(Array.isArray(parsed) ? parsed : []);
            } else {
                // Default playbooks if none found
                setPlaybooks([
                    {
                        name: 'Nightly Shipping Attribute Audit',
                        trigger: 'Items missing weight (oz) or L×W×H before fulfillment runs overnight.',
                        action: 'Call /api/item_dimensions_tools.php?action=ensure_columns then run_all with use_ai=1 to backfill missing data.',
                        cadence: 'Daily · 2:00 AM Eastern',
                        status: 'Verify AI backfill accuracy before enabling.',
                        active: false
                    },
                    {
                        name: 'Weekly Draft Cleanup',
                        trigger: 'Items in Draft status for 30+ days or never published.',
                        action: 'Send reminder email to merchandising, then archive SKUs via /api/marketing_manager.php?action=flag_draft.',
                        cadence: 'Weekly · Mondays 06:00 AM',
                        status: 'Good hygiene automation to keep the catalog lean.',
                        active: false
                    }
                ]);
            }
        } catch (err) {
            logger.error('[Automation] fetch failed', err);
            setError('Unable to load automation playbooks.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const savePlaybooks = async (updated: IAutomationPlaybook[]) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<any>('/api/business_settings.php?action=upsert_settings', {
                category: 'marketing',
                settings: { marketing_automations: updated }
            });
            if (res) {
                setPlaybooks(updated);
                return true;
            }
        } catch (err) {
            logger.error('[Automation] save failed', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    };

    useEffect(() => {
        fetchPlaybooks();
    }, [fetchPlaybooks]);

    return {
        playbooks,
        isLoading,
        error,
        fetchPlaybooks,
        savePlaybooks
    };
};
