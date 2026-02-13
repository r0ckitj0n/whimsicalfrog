import { useState, useCallback } from 'react';
import { AiManager } from '../../../core/ai/AiManager.js';
import logger from '../../../core/logger.js';
import { ageInDays } from '../../../core/date-utils.js';

export interface PriceComponent {
    type: string;
    amount: number;
    label: string;
    explanation: string;
}

export interface PriceSuggestion {
    success: boolean;
    suggested_price: number;
    confidence: string | number | null;
    fallback_used?: boolean;
    fallback_reason?: string;
    fallback_kind?: string;
    factors: {
        requested_pricing_tier?: string;
        tier_multiplier?: number;
        final_before_tier?: number;
        [key: string]: unknown;
    };
    components: PriceComponent[];
    analysis: {
        requested_pricing_tier?: string;
        requested_tier_multiplier?: number;
        [key: string]: unknown;
    };
    reasoning: string;
    created_at: string | number | null;
    _cachedAt?: number;
}

export const getPriceTierMultiplier = (tier: string) => {
    const t = (tier || '').toLowerCase();
    switch (t) {
        case 'premium':
            return 1.15;
        case 'conservative':
        case 'budget':
        case 'economy':
            return 0.85;
        case 'standard':
        default:
            return 1.0;
    }
};

export const usePriceSuggestions = () => {
    const [is_busy, setIsBusy] = useState(false);
    const [cached_price_suggestion, setCachedPriceSuggestion] = useState<PriceSuggestion | null>(null);

    const normalizePriceSuggestionData = useCallback((raw: Record<string, unknown>): PriceSuggestion | null => {
        if (!raw || typeof raw !== 'object') return null;
        const data = { ...raw };
        const suggestedPriceVal = data.suggested_price ?? data.suggested_price ?? data.price;
        const num = parseFloat(String(suggestedPriceVal));
        const suggested_price = Number.isFinite(num) ? num : 0;

        const safe_parse = (v: unknown, fallback: unknown) => {
            if (v == null) return fallback;
            if (Array.isArray(v) || typeof v === 'object') return v;
            if (typeof v === 'string') {
                try {
                    const parsed = JSON.parse(v);
                    return parsed ?? fallback;
                } catch (_) {
                    return fallback;
                }
            }
            return fallback;
        };

        const rawConfidence = data.confidence;
        let confidence: string | number = 'N/A';
        if (typeof rawConfidence === 'number' || typeof rawConfidence === 'string') {
            confidence = rawConfidence;
        } else if (rawConfidence && typeof rawConfidence === 'object') {
            // Normalize object-based confidence (e.g. from PricingStrategyHelper)
            const vals = Object.values(rawConfidence as Record<string, number>).filter(v => typeof v === 'number');
            if (vals.length > 0) {
                const avg = vals.reduce((a, b) => a + b, 0) / vals.length;
                confidence = Number(avg.toFixed(2));
            }
        }

        const compsRaw = safe_parse(data.components, []);
        let components: PriceComponent[] = [];
        if (Array.isArray(compsRaw)) {
            components = compsRaw as PriceComponent[];
        } else if (compsRaw && typeof compsRaw === 'object') {
            components = Object.entries(compsRaw as Record<string, unknown>).map(([type, val]: [string, unknown]) => {
                if (val && typeof val === 'object' && !Array.isArray(val)) {
                    const v = val as Record<string, unknown>;
                    return {
                        type,
                        amount: Number(v.amount ?? 0),
                        label: String(v.label ?? type),
                        explanation: String(v.explanation ?? '')
                    };
                }
                return {
                    type,
                    amount: Number(val ?? 0),
                    label: type,
                    explanation: ''
                };
            });
        }

        return {
            success: Boolean(data.success),
            suggested_price,
            confidence,
            fallback_used: Boolean((data as any).fallback_used),
            fallback_reason: String(((data as any).fallback_reason || '') as string),
            fallback_kind: String(((data as any).fallback_kind || '') as string),
            factors: (safe_parse(data.factors, {}) as PriceSuggestion['factors']),
            components,
            analysis: (safe_parse(data.analysis, {}) as PriceSuggestion['analysis']),
            reasoning: String(data.reasoning || ''),
            created_at: (data.created_at || data.created_at || null) as string | number | null
        };
    }, []);

    const retier_price_suggestion = useCallback((source: PriceSuggestion, targetTier: string): PriceSuggestion | null => {
        if (!source || typeof source !== 'object') return null;

        const data = { ...source };
        const factors = { ...(data.factors || {}) };
        const analysis = { ...(data.analysis || {}) };
        const components = Array.isArray(data.components) ? [...data.components] : [];

        const currentTierMult = (data.factors.tier_multiplier as number | undefined) || 1;
        const suggestedNum = Number(data.suggested_price);

        let basePrice = (data.factors.final_before_tier as number | undefined);
        if (basePrice === undefined || !Number.isFinite(basePrice) || basePrice <= 0) {
            if (Number.isFinite(suggestedNum) && currentTierMult > 0) {
                basePrice = suggestedNum / currentTierMult;
            } else {
                basePrice = suggestedNum;
            }
        }
        if (basePrice === undefined || !Number.isFinite(basePrice) || basePrice <= 0) return null;

        const targetMult = getPriceTierMultiplier(targetTier);
        const newPrice = basePrice * targetMult;

        data.suggested_price = Number.isFinite(newPrice) ? Number(newPrice.toFixed(2)) : suggestedNum;

        factors.requested_pricing_tier = (targetTier || 'standard').toLowerCase();
        factors.tier_multiplier = targetMult;
        factors.final_before_tier = basePrice;
        analysis.requested_pricing_tier = factors.requested_pricing_tier;
        analysis.requested_tier_multiplier = targetMult;

        // Effective multiplier for components
        const effectiveMultiplier = basePrice > 0 ? (data.suggested_price / basePrice) : targetMult;

        // Distribute multiplier to components and remove any existing quality_tier adjustment
        const updatedComponents = components
            .filter(c => c && c.type !== 'quality_tier')
            .map((c) => ({
                ...c,
                amount: Number((c.amount * effectiveMultiplier).toFixed(2))
            }));

        data.factors = factors;
        data.analysis = analysis;
        data.components = updatedComponents;
        return data;
    }, []);

    const fetch_stored_price_suggestion = useCallback(async (sku: string): Promise<PriceSuggestion | null> => {
        if (!sku) return null;
        try {
            const data = await AiManager.getStoredPricingSuggestion(sku);
            if (!data || typeof data !== 'object') return null;
            const normalized = normalizePriceSuggestionData(data as unknown as Record<string, unknown>);
            if (!normalized) return null;
            normalized._cachedAt = Date.now();
            return normalized;
        } catch (err) {
            logger.error('fetch_stored_price_suggestion failed', err);
            return null;
        }
    }, [normalizePriceSuggestionData]);

    const fetch_price_suggestion = useCallback(async (params: {
        sku: string;
        name: string;
        description: string;
        category: string;
        cost_price: string | number;
        tier?: string;
        useImages?: boolean;
        forceRefresh?: boolean;
    }) => {
        const ttlDays = 7;
        let staleStored: PriceSuggestion | null = null;
        if (!params.forceRefresh) {
            const stored = await fetch_stored_price_suggestion(params.sku);
            const storedAgeDays = ageInDays(stored?.created_at ?? null);
            if (stored && stored.success && Number.isFinite(storedAgeDays ?? NaN) && (storedAgeDays as number) >= 0) {
                if ((storedAgeDays as number) < ttlDays) {
                    const requestedTier = params.tier || 'standard';
                    const updated = retier_price_suggestion(stored, requestedTier) || stored;
                    updated._cachedAt = Date.now();
                    setCachedPriceSuggestion(updated);
                    return updated;
                }
                staleStored = stored;
            }
        }

        setIsBusy(true);
        try {
            const data = await AiManager.getPricingSuggestion({
                sku: params.sku,
                name: params.name,
                description: params.description,
                category: params.category,
                cost_price: params.cost_price,
                useImages: params.useImages ?? true,
                quality_tier: params.tier || 'standard'
            });

            if (data && data.success) {
                let suggested = (data.suggested_price != null) ? Number(data.suggested_price) : 0;
                if (!Number.isFinite(suggested)) suggested = 0;
                (data as any).suggested_price = suggested;

                const normalized = normalizePriceSuggestionData(data as unknown as Record<string, unknown>);
                if (normalized) {
                    const requestedTier = params.tier || 'standard';
                    const requestedMult = getPriceTierMultiplier(requestedTier);
                    const rawFinalBeforeTier = Number(normalized.factors.final_before_tier);
                    const hasValidFinalBeforeTier = Number.isFinite(rawFinalBeforeTier) && rawFinalBeforeTier > 0;

                    // Important: API suggested_price is already tiered for the requested tier.
                    // Keep a stable untiered base so future re-tier operations do not compound.
                    const untieredBase = hasValidFinalBeforeTier
                        ? rawFinalBeforeTier
                        : (requestedMult > 0 ? Number((normalized.suggested_price / requestedMult).toFixed(2)) : normalized.suggested_price);

                    normalized.factors.final_before_tier = untieredBase;
                    normalized.factors.tier_multiplier = requestedMult;
                    normalized.factors.requested_pricing_tier = requestedTier.toLowerCase();
                    normalized._cachedAt = Date.now();
                    setCachedPriceSuggestion(normalized);
                    return normalized;
                }
            }
            return null;
        } catch (err) {
            logger.error('fetch_price_suggestion failed', err);
            return staleStored;
        } finally {
            setIsBusy(false);
        }
    }, [fetch_stored_price_suggestion, normalizePriceSuggestionData, retier_price_suggestion]);

    return {
        is_busy,
        cached_price_suggestion,
        setCachedPriceSuggestion,
        fetch_price_suggestion,
        fetch_stored_price_suggestion,
        retier_price_suggestion
    };
};
