import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { API_ACTION } from '../../core/constants.js';
import type { IMarketingIntelligence, ICartButtonText, IShopEncouragement } from '../../types/marketing.js';

// Re-export for backward compatibility
export type { IMarketingIntelligence, ICartButtonText, IShopEncouragement } from '../../types/marketing.js';

export const useMarketingSettings = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [cartTexts, setCartTexts] = useState<ICartButtonText[]>([]);
    const [encouragements, setEncouragements] = useState<IShopEncouragement[]>([]);

    const fetchCartTexts = useCallback(async () => {
        try {
            const res = await ApiClient.get<{ success: boolean; texts?: ICartButtonText[] }>(`/api/cart_button_texts.php?action=${API_ACTION.LIST}`);
            if (res?.success) setCartTexts(res.texts || []);
        } catch (err) { logger.error('fetchCartTexts failed', err); }
    }, []);

    const fetchEncouragements = useCallback(async () => {
        try {
            // API returns { success: true, phrases: [{ id, text }, ...] }
            const res = await ApiClient.get<{ success: boolean; phrases?: Array<{ id: number; text: string }> }>(`/api/shop_encouragement_phrases.php?action=${API_ACTION.LIST}`);
            if (res?.success && res.phrases) {
                const structured: IShopEncouragement[] = res.phrases.map((p) => ({
                    id: p.id,
                    text: p.text,
                    category: 'encouragement'
                }));
                setEncouragements(structured);
            } else {
                setEncouragements([]);
            }
        } catch (err) { logger.error('fetchEncouragements failed', err); }
    }, []);

    const addCartText = useCallback(async (text: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<any>(`/api/cart_button_texts.php?action=${API_ACTION.ADD}`, { text });
            if (res) await fetchCartTexts();
            return res;
        } finally { setIsLoading(false); }
    }, [fetchCartTexts]);

    const deleteCartText = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; message?: string }>(`/api/cart_button_texts.php?action=${API_ACTION.DELETE}`, { id });
            if (res?.success) await fetchCartTexts();
            return res;
        } finally { setIsLoading(false); }
    }, [fetchCartTexts]);

    const updateCartText = useCallback(async (id: number, text: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/cart_button_texts.php?action=${API_ACTION.UPDATE}`, { id, text });
            if (res?.success) await fetchCartTexts();
            return res;
        } finally { setIsLoading(false); }
    }, [fetchCartTexts]);

    const addEncouragement = useCallback(async (text: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; id?: number; error?: string }>(`/api/shop_encouragement_phrases.php?action=${API_ACTION.ADD}`, { text });
            if (res?.success) await fetchEncouragements();
            return res;
        } finally { setIsLoading(false); }
    }, [fetchEncouragements]);

    const deleteEncouragement = useCallback(async (id: number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/shop_encouragement_phrases.php?action=${API_ACTION.DELETE}`, { id });
            if (res?.success) await fetchEncouragements();
            return res;
        } finally { setIsLoading(false); }
    }, [fetchEncouragements]);

    const updateEncouragement = useCallback(async (id: number, text: string) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; error?: string }>(`/api/shop_encouragement_phrases.php?action=${API_ACTION.UPDATE}`, { id, text });
            if (res?.success) await fetchEncouragements();
            return res;
        } finally { setIsLoading(false); }
    }, [fetchEncouragements]);

    return {
        isLoading,
        error,
        cartTexts,
        encouragements,
        fetchCartTexts,
        fetchEncouragements,
        addCartText,
        deleteCartText,
        updateCartText,
        addEncouragement,
        deleteEncouragement,
        updateEncouragement
    };
};
