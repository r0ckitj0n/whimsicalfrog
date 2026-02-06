import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';

import { DISCOUNT_TYPE, DiscountType } from '../../core/constants.js';
import type { IDiscount } from '../../types/commerce.js';

// Re-export for backward compatibility
export type { IDiscount } from '../../types/commerce.js';

export const useDiscounts = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [discounts, setDiscounts] = useState<IDiscount[]>([]);
    const [error, setError] = useState<string | null>(null);

    const fetchDiscounts = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await ApiClient.get<{ settings?: Array<{ setting_key: string; setting_value: string | IDiscount[] }> }>('/api/business_settings.php?action=get_by_category&category=marketing');
            const arr = Array.isArray(data?.settings) ? data.settings : [];
            const row = arr.find((s) => s.setting_key === 'marketing_discounts');

            if (row && row.setting_value) {
                let val = row.setting_value;
                if (typeof val === 'string') {
                    try { val = JSON.parse(val) as IDiscount[]; } catch (_) { val = []; }
                }
                setDiscounts(Array.isArray(val) ? val : []);
            }
        } catch (err) {
            logger.error('[Discounts] fetch failed', err);
            setError('Unable to load discount codes.');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveDiscounts = async (updated: IDiscount[]) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<any>('/api/business_settings.php?action=upsert_settings', {
                category: 'marketing',
                settings: { marketing_discounts: updated }
            });
            if (res) {
                setDiscounts(updated);
                return true;
            }
        } catch (err) {
            logger.error('[Discounts] save failed', err);
        } finally {
            setIsLoading(false);
        }
        return false;
    };

    useEffect(() => {
        fetchDiscounts();
    }, [fetchDiscounts]);

    return {
        discounts,
        isLoading,
        error,
        fetchDiscounts,
        saveDiscounts
    };
};
