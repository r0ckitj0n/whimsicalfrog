import { useState, useCallback } from 'react';
import { AiManager } from '../../../core/ai/AiManager.js';
import logger from '../../../core/logger.js';
import { getPriceTierMultiplier } from './usePriceSuggestions.js';
import { ApiClient } from '../../../core/ApiClient.js';
import { ageInDays } from '../../../core/date-utils.js';

export interface CostSuggestion {
    suggested_cost: number;
    confidence: string | number | null;
    created_at: string | number | null;
    breakdown: Record<string, unknown>;
    analysis: Record<string, unknown>;
    reasoning: string;
    baseline_cost?: number;
    fallback_used?: boolean;
    fallback_reason?: string;
    fallback_kind?: string;
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

    const fetch_stored_ai_cost_suggestion = useCallback(async (sku: string, tier: string = 'standard'): Promise<CostSuggestion | null> => {
        if (!sku) return null;
        try {
            const res = await ApiClient.get<{
                success?: boolean;
                suggested_cost?: number;
                reasoning?: string;
                confidence?: number | null;
                breakdown?: Record<string, unknown>;
                analysis?: Record<string, unknown>;
                created_at?: string | null;
            }>('/api/get_ai_cost_suggestion.php', { sku });

            if (!res?.success) return null;
            const tiered_cost = Number(res.suggested_cost || 0);
            const mult = getPriceTierMultiplier(tier || 'standard') || 1;
            const baseline_cost = mult > 0 ? Number((tiered_cost / mult).toFixed(2)) : tiered_cost;

            return {
                suggested_cost: Number(tiered_cost.toFixed(2)),
                baseline_cost,
                confidence: (res.confidence ?? 'N/A') as string | number | null,
                created_at: res.created_at ?? null,
                breakdown: normalizeBreakdown(res.breakdown || {}),
                analysis: (res.analysis || {}) as Record<string, unknown>,
                reasoning: String(res.reasoning || ''),
                fallback_used: false,
                fallback_reason: '',
                fallback_kind: '',
                _cachedAt: Date.now()
            };
        } catch (err) {
            logger.error('fetch_stored_ai_cost_suggestion failed', err);
            return null;
        }
    }, [normalizeBreakdown]);

    const fetch_cost_suggestion = useCallback(async (params: {
        sku?: string;
        name: string;
        description: string;
        category: string;
        tier?: string;
        useImages?: boolean;
        imageData?: string;
        forceRefresh?: boolean;
    }) => {
        const ttlDays = 7;
        let staleStored: CostSuggestion | null = null;
        if (!params.forceRefresh && params.sku) {
            const stored = await fetch_stored_ai_cost_suggestion(params.sku, params.tier || 'standard');
            const storedAgeDays = ageInDays(stored?.created_at ?? null);
            if (stored && Number.isFinite(storedAgeDays ?? NaN) && (storedAgeDays as number) >= 0) {
                if ((storedAgeDays as number) < ttlDays) {
                    setCachedCostSuggestion(stored);
                    return stored;
                }
                staleStored = stored;
            }
        }

        setIsBusy(true);
        try {
            const data = await AiManager.getCostSuggestion({
                sku: params.sku,
                name: params.name,
                description: params.description,
                category: params.category,
                quality_tier: params.tier || 'standard',
                useImages: params.useImages ?? true,
                imageData: params.imageData
            });

            if (data && data.success) {
                const res_data = data as unknown as Record<string, unknown>;
                let tiered_cost = Number(res_data.suggested_cost);
                if (!Number.isFinite(tiered_cost) || tiered_cost <= 0) tiered_cost = 0;

                const mult = getPriceTierMultiplier(params.tier || 'standard') || 1;
                // Backend already applies tier scaling; keep an untiered baseline for later retier operations.
                const baseline_cost = mult > 0 ? Number((tiered_cost / mult).toFixed(2)) : tiered_cost;

                // Keep breakdown as returned (already tier-scaled by backend).
                const rawBreakdown = normalizeBreakdown((res_data.breakdown || {}) as Record<string, unknown>);

                const suggestion: CostSuggestion = {
                    suggested_cost: Number(tiered_cost.toFixed(2)),
                    baseline_cost,
                    confidence: (res_data.confidence as string | number | null) || 'N/A',
                    created_at: (res_data.created_at || res_data.created_at || Date.now()) as string | number | null,
                    breakdown: rawBreakdown,
                    analysis: (res_data.analysis || {}) as Record<string, unknown>,
                    reasoning: (res_data.reasoning as string) || '',
                    fallback_used: Boolean((res_data as any).fallback_used),
                    fallback_reason: String(((res_data as any).fallback_reason || '') as string),
                    fallback_kind: String(((res_data as any).fallback_kind || '') as string),
                    _cachedAt: Date.now()
                };
                setCachedCostSuggestion(suggestion);
                return suggestion;
            }
            return null;
        } catch (err) {
            logger.error('fetch_cost_suggestion failed', err);
            return staleStored;
        } finally {
            setIsBusy(false);
        }
    }, [fetch_stored_ai_cost_suggestion, normalizeBreakdown]);

    const retier_cost_suggestion = useCallback((source: CostSuggestion, targetTier: string, currentTier: string = 'standard'): CostSuggestion | null => {
        if (!source) return null;

        const currentMult = getPriceTierMultiplier(currentTier || 'standard') || 1;
        const targetMult = getPriceTierMultiplier(targetTier || 'standard') || 1;
        const suggested = Number(source.suggested_cost);
        const baselineFromSource = Number(source.baseline_cost);
        const baseline = Number.isFinite(baselineFromSource) && baselineFromSource > 0
            ? baselineFromSource
            : (Number.isFinite(suggested) && suggested > 0 ? suggested / currentMult : 0);

        if (!Number.isFinite(baseline) || baseline <= 0) return null;

        const retiered: CostSuggestion = {
            ...source,
            baseline_cost: Number(baseline.toFixed(2)),
            suggested_cost: Number((baseline * targetMult).toFixed(2)),
            _cachedAt: Date.now()
        };

        if (retiered.breakdown && typeof retiered.breakdown === 'object') {
            const nextBreakdown: Record<string, unknown> = {};
            const ratio = targetMult / (currentMult || 1);

            Object.entries(retiered.breakdown).forEach(([key, val]) => {
                if (typeof val === 'number') {
                    nextBreakdown[key] = Number((val * ratio).toFixed(2));
                    return;
                }
                if (Array.isArray(val)) {
                    nextBreakdown[key] = val.map(item => {
                        if (item && typeof item === 'object') {
                            const nextItem = { ...item } as Record<string, unknown>;
                            if (typeof nextItem.cost === 'number') {
                                nextItem.cost = Number((nextItem.cost * ratio).toFixed(2));
                            }
                            return nextItem;
                        }
                        return item;
                    });
                    return;
                }
                nextBreakdown[key] = val;
            });

            retiered.breakdown = nextBreakdown;
        }

        setCachedCostSuggestion(retiered);
        return retiered;
    }, []);

    return {
        is_busy,
        cached_cost_suggestion,
        setCachedCostSuggestion,
        fetch_cost_suggestion,
        fetch_stored_ai_cost_suggestion,
        retier_cost_suggestion
    };
};
