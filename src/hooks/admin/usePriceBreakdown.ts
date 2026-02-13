import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { PriceSuggestion } from './useInventoryAI.js';
import type { IPriceFactor, IPriceBreakdown } from '../../types/ai.js';
import type { IUpdatePriceFactorsBulkRequest, IUpdatePriceFactorsBulkResponse } from '../../types/ai.js';

// Re-export for backward compatibility
export type { IPriceFactor, IPriceBreakdown } from '../../types/ai.js';

export const usePriceBreakdown = (sku: string) => {
    const [factors, setFactors] = useState<IPriceFactor[]>([]);
    const [confidence, setConfidence] = useState<number | null>(null);
    const [appliedAt, setAppliedAt] = useState<string | null>(null);
    const [is_busy, setIsBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [pendingSuggestion, setPendingSuggestion] = useState<PriceSuggestion | null>(null);
    const [breakdown, setBreakdown] = useState<IPriceBreakdown>({
        factors: [],
        totals: {
            total: 0,
            stored: 0
        }
    });

    const isDirty = Math.abs((breakdown.totals.total || 0) - (breakdown.totals.stored || 0)) > 0.001 || !!pendingSuggestion;

    const fetchBreakdown = useCallback(async () => {
        if (!sku) return;
        setIsBusy(true); // Changed from setIsLoading to setIsBusy
        setError(null);
        try {
            const data = await ApiClient.get<IPriceBreakdown & { success?: boolean; totals?: { ai_confidence?: number; ai_at?: string } }>('/api/get_price_breakdown.php', { sku }); // Adjusted type to include new fields
            if (data) {
                setFactors(data.factors || []); // Keep existing logic for factors
                setConfidence(data.totals?.ai_confidence !== undefined ? Number(data.totals.ai_confidence) : null);
                setAppliedAt(data.totals?.ai_at ?? null);
                setBreakdown(data); // Keep existing logic for breakdown
                setPendingSuggestion(null); // Keep existing logic for pending suggestion
                return data;
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('fetchBreakdown price failed', err);
            setError(message);
        } finally {
            setIsBusy(false); // Changed from setIsLoading to setIsBusy
        }
    }, [sku]);

    const applySuggestionLocally = useCallback((suggestion: PriceSuggestion) => {
        setPendingSuggestion(suggestion);

        // Map suggestion components to IPriceFactor format
        const newFactors: IPriceFactor[] = (suggestion.components || []).map((comp, idx) => ({
            id: -(idx + 1), // temp IDs
            sku,
            label: comp.label || 'AI Component',
            amount: comp.amount || 0,
            type: comp.type || 'ai',
            explanation: comp.explanation || '',
            source: 'ai',
            created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
        }));

        setFactors(newFactors); // Update factors state
        setConfidence(typeof suggestion.confidence === 'number' ? suggestion.confidence : (suggestion.confidence ? parseFloat(String(suggestion.confidence)) : null));
        setAppliedAt(null); // Clear appliedAt when applying locally

        setBreakdown({
            factors: newFactors, // Use newFactors for breakdown
            totals: {
                total: suggestion.suggested_price || 0,
                stored: breakdown.totals.stored
            }
        });
    }, [sku, breakdown.totals.stored]);

    const populateFromSuggestion = useCallback(async (suggestion?: PriceSuggestion) => {
        const suggestionToUse = suggestion || pendingSuggestion;
        if (!sku || !suggestionToUse) return false;
        setIsBusy(true); // Changed from setIsLoading to setIsBusy
        try {
            const res = await ApiClient.post<{ success: boolean }>('/api/populate_price_from_ai.php', {
                sku,
                suggestion: suggestionToUse
            });
            if (res?.success) {
                setPendingSuggestion(null);
                await fetchBreakdown();
                return true;
            }
            return false;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('populateFromSuggestion price failed', err);
            setError(message);
            return false;
        } finally {
            setIsBusy(false); // Changed from setIsLoading to setIsBusy
        }
    }, [sku, fetchBreakdown, pendingSuggestion]);

    const updateFactor = useCallback(async (factorId: number, amount: number) => {
        if (!sku) return false;
        setIsBusy(true);
        try {
            const res = await ApiClient.post<{ success: boolean }>('/api/update_price_factor.php', {
                id: factorId,
                amount
            });
            if (res?.success) {
                await fetchBreakdown();
                return true;
            }
            return false;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('updateFactor failed', err);
            setError(message);
            return false;
        } finally {
            setIsBusy(false);
        }
    }, [sku, fetchBreakdown]);

    const updateFactorsBulk = useCallback(async (updates: Array<{ id: number; amount: number }>) => {
        if (!sku) return false;
        if (!Array.isArray(updates) || updates.length === 0) return true;
        setIsBusy(true);
        try {
            const payload: IUpdatePriceFactorsBulkRequest = { sku, updates };
            const res = await ApiClient.post<IUpdatePriceFactorsBulkResponse>('/api/update_price_factors_bulk.php', payload);
            if (res?.success) {
                await fetchBreakdown();
                return true;
            }
            return false;
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Unknown error';
            logger.error('updateFactorsBulk failed', err);
            setError(message);
            return false;
        } finally {
            setIsBusy(false);
        }
    }, [sku, fetchBreakdown]);

    return {
        breakdown,
        is_busy, // Changed from isLoading to is_busy
        error,
        factors, // Added factors to return
        confidence, // Added confidence to return
        appliedAt, // Added appliedAt to return
        fetchBreakdown,
        populateFromSuggestion,
        applySuggestionLocally,
        updateFactor,
        updateFactorsBulk,
        hasPendingSuggestion: !!pendingSuggestion,
        isDirty
    };
};
