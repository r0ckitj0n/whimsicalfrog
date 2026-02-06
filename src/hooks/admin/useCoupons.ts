import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import { API_ACTION, DISCOUNT_TYPE, DiscountType } from '../../core/constants.js';
import logger from '../../core/logger.js';
import type { ICoupon } from '../../types/commerce.js';

// Re-export for backward compatibility
export type { ICoupon } from '../../types/commerce.js';

export const useCoupons = () => {
    const [coupons, setCoupons] = useState<ICoupon[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const fetchCoupons = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<{ success: boolean; coupons?: ICoupon[]; error?: string }>('/api/coupons.php?action=list');
            if (res?.success) {
                setCoupons(res.coupons || []);
            } else {
                setError(res?.error || 'Failed to load coupons');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchCoupons failed', err);
            setError(message);
        } finally {
            setIsLoading(false);
        }
    }, []);

    const saveCoupon = useCallback(async (couponData: Partial<ICoupon>) => {
        setIsLoading(true);
        try {
            const isEdit = !!couponData.id;
            const action = isEdit ? 'update' : 'create';
            const res = await ApiClient.post<any>(`/api/coupons.php?action=${action}`, couponData);
            if (res) {
                await fetchCoupons();
                return res;
            } else {
                throw new Error('Failed to save coupon');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('saveCoupon failed', err);
            setError(message);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, [fetchCoupons]);

    const deleteCoupon = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string; message?: string }>('/api/coupons.php?action=delete', { id });
            if (res?.success) {
                await fetchCoupons();
                return true;
            } else {
                throw new Error(res?.error || res?.message || 'Failed to delete coupon');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('deleteCoupon failed', err);
            setError(message);
            return false;
        } finally {
            setIsLoading(false);
        }
    }, [fetchCoupons]);

    return {
        coupons,
        isLoading,
        error,
        fetchCoupons,
        saveCoupon,
        deleteCoupon
    };
};
