import React from 'react';
import { useInventoryAI, PriceSuggestion } from '../../../hooks/admin/useInventoryAI.js';
import { usePriceBreakdown } from '../../../hooks/admin/usePriceBreakdown.js';
import { PriceComponent } from '../../../hooks/admin/inventory-ai/usePriceSuggestions.js';
import { getPriceTierMultiplier } from '../../../hooks/admin/inventory-ai/usePriceSuggestions.js';
import { toastSuccess, toastError } from '../../../core/toast.js';
import { formatTime } from '../../../core/date-utils.js';
import { ageInDays } from '../../../core/date-utils.js';
import { QualityTierControl } from './QualityTierControl.js';
import { generatePriceSuggestion } from '../../../hooks/admin/inventory-ai/generateCostSuggestion.js';
import { useAICostEstimateConfirm } from '../../../hooks/admin/useAICostEstimateConfirm.js';

interface AIPricingPanelProps {
    sku: string;
    name: string;
    description: string;
    category: string;
    cost_price: number | string;
    isReadOnly?: boolean;
    onApplyPrice?: (price: number) => void;
    onApplied?: () => void;
    tier: string;
    onTierChange: (tier: string) => void;
    /** Optional cached suggestion to use instead of hook state (for orchestrated generation) */
    cachedSuggestion?: PriceSuggestion | null;
    onSuggestionUpdated?: (suggestion: PriceSuggestion) => void;
    primaryImageUrl?: string;
    imageUrls?: string[];
}

export const AIPricingPanel: React.FC<AIPricingPanelProps> = ({
    sku,
    name,
    description,
    category,
    cost_price,
    isReadOnly = false,
    onApplyPrice,
    onApplied,
    tier,
    onTierChange,
    cachedSuggestion: propCachedSuggestion,
    onSuggestionUpdated,
    primaryImageUrl,
    imageUrls = []
}) => {
    const {
        is_busy,
        cached_price_suggestion: hookCachedSuggestion,
        fetch_price_suggestion,
        fetch_stored_price_suggestion,
        retier_price_suggestion,
        setCachedPriceSuggestion
    } = useInventoryAI();

    // Prefer prop over hook state - allows parent to pass in orchestrated suggestions
    const cached_price_suggestion = propCachedSuggestion ?? hookCachedSuggestion;

    const { populateFromSuggestion, confidence: savedConfidence, appliedAt: savedAt, fetchBreakdown } = usePriceBreakdown(sku);
    const { confirmWithEstimate } = useAICostEstimateConfirm();

    const [isApplying, setIsApplying] = React.useState(false);

    const runImageFirstSuggestion = async (targetTier: string) => {
        const suggestion = await generatePriceSuggestion({
            sku,
            name,
            description,
            category,
            costPrice: cost_price,
            tier: targetTier,
            isReadOnly,
            primaryImageUrl,
            imageUrls,
            imageData: primaryImageUrl,
            fetchPriceSuggestion: fetch_price_suggestion,
            onSuggestionGenerated: (nextSuggestion) => {
                setCachedPriceSuggestion(nextSuggestion);
                onSuggestionUpdated?.(nextSuggestion);
                if (!isReadOnly && onApplyPrice) onApplyPrice(nextSuggestion.suggested_price);
            },
            onApplied
        });
        return suggestion;
    };

    const handleSuggest = async () => {
        // Fast path: if we have any stored suggestion, use it instantly and skip AI.
        // The user can later force a refresh by using other AI generation flows (Generate All / etc).
        const stored = await fetch_stored_price_suggestion(sku);
        const storedAgeDays = ageInDays(stored?.created_at ?? null);
        if (stored && stored.success && Number.isFinite(storedAgeDays ?? NaN) && (storedAgeDays as number) >= 0) {
            const updated = retier_price_suggestion(stored, tier) || stored;
            setCachedPriceSuggestion(updated);
            onSuggestionUpdated?.(updated);
            if (!isReadOnly && onApplyPrice) onApplyPrice(updated.suggested_price);
            if (window.WFToast?.info) {
                const freshness = (storedAgeDays as number) < 7 ? 'fresh' : 'stored';
                window.WFToast.info(`Using ${freshness} price suggestion.`);
            }
            return;
        }

        const confirmed = await confirmWithEstimate({
            action_key: 'inventory_generate_price',
            action_label: 'Generate price suggestion with AI',
            operations: [
                { key: 'price_estimation', label: 'Price suggestion' }
            ],
            mode: 'minimal',
            context: {
                image_count: Math.max(imageUrls.length, primaryImageUrl ? 1 : 0),
                name_length: name.length,
                description_length: description.length,
                category_length: category.length
            },
            confirmText: 'Generate'
        });
        if (!confirmed) return;

        const suggestion = await runImageFirstSuggestion(tier);
        if (!suggestion && stored) {
            // If we had something stored (even if stale) and AI refresh failed, prefer the stored result.
            const updated = retier_price_suggestion(stored, tier) || stored;
            setCachedPriceSuggestion(updated);
            onSuggestionUpdated?.(updated);
            if (!isReadOnly && onApplyPrice) onApplyPrice(updated.suggested_price);
            const when = stored.created_at ? ` (${formatTime(stored.created_at)})` : '';
            if (window.WFToast?.warning) window.WFToast.warning(`AI price refresh failed; using last stored price suggestion${when}.`);
        }
    };

    const handleTierChange = (newTier: string) => {
        onTierChange(newTier);
        if (cached_price_suggestion) {
            const updated = retier_price_suggestion(cached_price_suggestion, newTier);
            if (updated) {
                setCachedPriceSuggestion(updated);
                if (onSuggestionUpdated) onSuggestionUpdated(updated);
                if (!isReadOnly && onApplyPrice) onApplyPrice(updated.suggested_price);
            }
            return;
        }

        // No cached suggestion yet: immediately generate one for the new tier so
        // the suggested value updates without requiring an extra Generate click.
        // Fallback order: breakdown amount -> AI generation.
        void (async () => {
            if (window.WFToast) window.WFToast.info('Recalculating price for selected tier...');
            try {
                const fetched = await fetchBreakdown();
                const storedTotal = Number(fetched?.totals?.stored || 0);
                const factorsTotal = Number(fetched?.totals?.total || 0);
                const breakdownTotal = storedTotal > 0 ? storedTotal : factorsTotal;

                if (Number.isFinite(breakdownTotal) && breakdownTotal > 0) {
                    const currentMult = getPriceTierMultiplier(tier || 'standard') || 1;
                    const targetMult = getPriceTierMultiplier(newTier || 'standard') || 1;
                    const basePrice = breakdownTotal / currentMult;
                    const retieredPrice = Number((basePrice * targetMult).toFixed(2));
                    const factorScale = targetMult / currentMult;
                    const scaledComponents: PriceComponent[] = Array.isArray(fetched?.factors)
                        ? fetched.factors
                            .map((factor) => {
                                const amount = Number(factor?.amount || 0);
                                if (!Number.isFinite(amount) || amount <= 0) return null;
                                return {
                                    type: String(factor?.type || 'analysis'),
                                    label: String(factor?.label || 'Pricing Factor'),
                                    amount: Number((amount * factorScale).toFixed(2)),
                                    explanation: String(factor?.explanation || '')
                                } as PriceComponent;
                            })
                            .filter((item): item is PriceComponent => item !== null)
                        : [];

                    const synthesized: PriceSuggestion = {
                        success: true,
                        suggested_price: retieredPrice,
                        confidence: savedConfidence ?? 0,
                        reasoning: 'Derived from existing pricing breakdown and retiered.',
                        components: scaledComponents,
                        factors: {
                            requested_pricing_tier: (newTier || 'standard').toLowerCase(),
                            tier_multiplier: targetMult,
                            final_before_tier: Number(basePrice.toFixed(2))
                        },
                        analysis: {},
                        created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    };

                    setCachedPriceSuggestion(synthesized);
                    if (onSuggestionUpdated) onSuggestionUpdated(synthesized);
                    if (!isReadOnly && onApplyPrice) onApplyPrice(synthesized.suggested_price);
                    if (toastSuccess) toastSuccess('Suggested price updated from breakdown');
                    return;
                }

                const suggestion = await runImageFirstSuggestion(newTier);
                if (!suggestion) {
                    if (toastError) toastError('Failed to recalculate suggested price');
                    return;
                }
                if (toastSuccess) toastSuccess('Suggested price updated');
            } catch (_err) {
                if (toastError) toastError('Failed to recalculate suggested price');
            }
        })();
    };

    const handleApply = () => {
        if (cached_price_suggestion && onApplyPrice) {
            onApplyPrice(cached_price_suggestion.suggested_price);
            if (toastSuccess) toastSuccess('Price suggestion applied to main field');
        }
    };

    // AI Suggested Retail is read-only by design

    const handleApplyFullBreakdown = async () => {
        if (!cached_price_suggestion) return;
        setIsApplying(true);
        try {
            const success = await populateFromSuggestion(cached_price_suggestion);
            if (success) {
                if (onApplyPrice) onApplyPrice(cached_price_suggestion.suggested_price);
                if (onApplied) onApplied();
                if (toastSuccess) toastSuccess('Price breakdown applied successfully');
            } else {
                if (toastError) toastError('Failed to apply price breakdown');
            }
        } catch (err) {
            if (toastError) toastError('Error applying price breakdown');
        } finally {
            setIsApplying(false);
        }
    };

    return (
        <div className="price-suggestion-wrapper">
            <h4 className="font-semibold text-gray-700 mb-1 text-sm flex items-center gap-2">
                Price Suggestion
            </h4>

            {!isReadOnly && (
                <div className="space-y-3 mb-4">
                    <QualityTierControl value={tier} onChange={handleTierChange} />
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            className="btn btn-primary text-sm py-1.5 px-4 flex-1"
                            onClick={handleSuggest}
                            disabled={is_busy}
                            data-help-id="inventory-ai-suggest-price"
                        >
                            Generate
                        </button>
                    </div>
                </div>
            )}

            {!cached_price_suggestion && savedConfidence !== null && (
                <div className="mb-6 p-4 bg-gray-50 border border-gray-100 rounded-xl">
                    <div className="flex justify-between items-center mb-2">
                        <span className="text-[10px] font-bold text-gray-500 uppercase tracking-widest">Applied AI Metadata</span>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="text-[10px] text-gray-400">Confidence: <span className="font-bold">{Math.round(savedConfidence * 100)}%</span></span>
                        {savedAt && (
                            <span className="text-[10px] text-gray-400">Applied: <span className="font-bold">{formatTime(savedAt)}</span></span>
                        )}
                    </div>
                </div>
            )}

            {cached_price_suggestion && (
                <div className="ai-data-panel ai-data-panel--inverted mb-3">
                    <div className="flex justify-between items-center">
                        <span className="ai-data-label text-sm font-medium text-white/80">Suggested Retail:</span>
                        <span className="ai-data-value--large text-white">${cached_price_suggestion.suggested_price.toFixed(2)}</span>
                    </div>
                </div>
            )}

            {cached_price_suggestion?.components && (
                <div className="ai-data-panel overflow-hidden p-0 mb-6 bg-white border border-gray-100 rounded-xl">
                    <div className="ai-data-section-header bg-gray-50/50 p-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-100">
                        AI Reasoning Components
                    </div>
                    <div>
                        {cached_price_suggestion.components.map((comp: PriceComponent, idx: number) => (
                            <div key={idx} className="p-2 flex justify-between border-b border-gray-50 last:border-0 group hover:bg-gray-50 transition-colors">
                                <span className="text-xs text-gray-600">{comp.label}</span>
                                <span className="text-xs font-medium text-gray-900">${comp.amount.toFixed(2)}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
};
