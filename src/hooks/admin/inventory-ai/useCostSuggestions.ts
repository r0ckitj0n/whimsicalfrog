import { useState, useCallback } from 'react';
import { AiManager } from '../../../core/ai/AiManager.js';
import logger from '../../../core/logger.js';
import { getPriceTierMultiplier } from './usePriceSuggestions.js';

export interface CostSuggestion {
    suggested_cost: number;
    confidence: string | number | null;
    created_at: string | number | null;
    breakdown: Record<string, unknown>;
    analysis: Record<string, unknown>;
    reasoning: string;
    baseline_cost?: number;
    _cachedAt?: number;
}

export const useCostSuggestions = () => {
    const [is_busy, setIsBusy] = useState(false);
    const [cached_cost_suggestion, setCachedCostSuggestion] = useState<CostSuggestion | null>(null);

    const normalizeBreakdown = (raw: Record<string, unknown> | null | undefined): Record<string, unknown> => {
        if (!raw || typeof raw !== 'object') return {};
        const normalized: Record<string, unknown> = {};
        const keyMap: Record<string, string> = {
            materials: 'materials',
            material: 'materials',
            labor: 'labor',
            labour: 'labor',
            energy: 'energy',
            utilities: 'energy',
            power: 'energy',
            equipment: 'equipment',
            machinery: 'equipment',
            tools: 'equipment'
        };

        Object.entries(raw).forEach(([key, value]) => {
            const lowered = key.toLowerCase().trim();
            const mapped = keyMap[lowered] || lowered;
            normalized[mapped] = value;
        });

        return normalized;
    };

    const fetch_cost_suggestion = useCallback(async (params: {
        sku?: string;
        name: string;
        description: string;
        category: string;
        tier?: string;
        useImages?: boolean;
    }) => {
        setIsBusy(true);
        try {
            const data = await AiManager.getCostSuggestion({
                sku: params.sku,
                name: params.name,
                description: params.description,
                category: params.category,
                quality_tier: params.tier || 'standard',
                useImages: params.useImages ?? true
            });

            if (data && data.success) {
                const res_data = data as Record<string, unknown>;
                let base_cost = Number(res_data.suggested_cost);
                if (!Number.isFinite(base_cost) || base_cost <= 0) base_cost = 0;

                const mult = getPriceTierMultiplier(params.tier || 'standard');
                const final_cost = Number((base_cost * mult).toFixed(2));

                // Normalize and scale breakdown items proportionately
                const rawBreakdown = normalizeBreakdown((res_data.breakdown || {}) as Record<string, unknown>);
                const scaledBreakdown: Record<string, unknown> = {};

                Object.entries(rawBreakdown).forEach(([key, val]) => {
                    if (typeof val === 'number') {
                        scaledBreakdown[key] = Number((val * mult).toFixed(2));
                    } else if (Array.isArray(val)) {
                        scaledBreakdown[key] = val.map(item => {
                            if (typeof item === 'object' && item !== null) {
                                const itemObj = { ...item } as Record<string, unknown>;
                                if (typeof itemObj.cost === 'number') {
                                    itemObj.cost = Number((itemObj.cost * mult).toFixed(2));
                                }
                                return itemObj;
                            }
                            return item;
                        });
                    } else {
                        scaledBreakdown[key] = val;
                    }
                });

                const suggestion: CostSuggestion = {
                    suggested_cost: final_cost,
                    baseline_cost: base_cost,
                    confidence: (res_data.confidence as string | number | null) || 'N/A',
                    created_at: (res_data.created_at || res_data.created_at || Date.now()) as string | number | null,
                    breakdown: scaledBreakdown,
                    analysis: (res_data.analysis || {}) as Record<string, unknown>,
                    reasoning: (res_data.reasoning as string) || '',
                    _cachedAt: Date.now()
                };
                setCachedCostSuggestion(suggestion);
                return suggestion;
            }
            return null;
        } catch (err) {
            logger.error('fetch_cost_suggestion failed', err);
            return null;
        } finally {
            setIsBusy(false);
        }
    }, [normalizeBreakdown]);

    return {
        is_busy,
        cached_cost_suggestion,
        setCachedCostSuggestion,
        fetch_cost_suggestion
    };
};
