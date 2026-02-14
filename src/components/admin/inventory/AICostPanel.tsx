import React from 'react';
import { useInventoryAI } from '../../../hooks/admin/useInventoryAI.js';
import { useCostBreakdown } from '../../../hooks/admin/useCostBreakdown.js';
import { getPriceTierMultiplier } from '../../../hooks/admin/inventory-ai/usePriceSuggestions.js';
import { toastSuccess, toastError } from '../../../core/toast.js';
import { generateCostSuggestion } from '../../../hooks/admin/inventory-ai/generateCostSuggestion.js';
import { useAICostEstimateConfirm } from '../../../hooks/admin/useAICostEstimateConfirm.js';
import { formatTime } from '../../../core/date-utils.js';
import { ageInDays } from '../../../core/date-utils.js';
import { QualityTierControl } from './QualityTierControl.js';

import { CostSuggestion } from '../../../hooks/admin/useInventoryAI.js';

interface AICostPanelProps {
    sku: string;
    name: string;
    description: string;
    category: string;
    isReadOnly?: boolean;
    onApplyCost?: (cost: number) => void;
    onApplied?: () => void;
    tier: string;
    onTierChange: (tier: string) => void;
    /** Optional cached suggestion to use instead of hook state (for orchestrated generation) */
    cachedSuggestion?: CostSuggestion | null;
    onSuggestionUpdated?: (suggestion: CostSuggestion) => void;
    showStoredMetadata?: boolean;
    primaryImageUrl?: string;
    imageUrls?: string[];
}

export const AICostPanel: React.FC<AICostPanelProps> = ({
    sku,
    name,
    description,
    category,
    isReadOnly = false,
    onApplyCost,
    onApplied,
    tier,
    onTierChange,
    cachedSuggestion: propCachedSuggestion,
    onSuggestionUpdated,
    showStoredMetadata = true,
    primaryImageUrl,
    imageUrls = []
}) => {
    const {
        is_busy,
        cached_cost_suggestion: hookCachedSuggestion,
        fetch_cost_suggestion,
        fetch_stored_ai_cost_suggestion,
        retier_cost_suggestion,
        setCachedCostSuggestion
    } = useInventoryAI();
    // Prefer prop over hook state - allows parent to pass in orchestrated suggestions
    const cached_cost_suggestion = propCachedSuggestion ?? hookCachedSuggestion;
    const { populateFromSuggestion, confidence: savedConfidence, appliedAt: savedAt, fetchBreakdown } = useCostBreakdown(sku);
    const { confirmWithEstimate } = useAICostEstimateConfirm();
    const [isApplying, setIsApplying] = React.useState(false);

    const runImageFirstSuggestion = async (targetTier: string) => {
        const suggestion = await generateCostSuggestion({
            sku,
            name,
            description,
            category,
            tier: targetTier,
            isReadOnly,
            primaryImageUrl,
            imageUrls,
            imageData: primaryImageUrl,
            fetchCostSuggestion: fetch_cost_suggestion,
            onSuggestionGenerated: (nextSuggestion) => {
                setCachedCostSuggestion(nextSuggestion);
                onSuggestionUpdated?.(nextSuggestion);
                if (!isReadOnly && onApplyCost) onApplyCost(nextSuggestion.suggested_cost);
            },
            onApplied
        });
        return suggestion;
    };

    const handleSuggest = async () => {
        // Fast path: if we have a stored AI cost suggestion newer than 7 days, use it instantly and skip AI.
        const stored = await fetch_stored_ai_cost_suggestion(sku, tier);
        const storedAgeDays = ageInDays(stored?.created_at ?? null);
        if (stored && Number.isFinite(storedAgeDays ?? NaN) && (storedAgeDays as number) >= 0 && (storedAgeDays as number) < 7) {
            setCachedCostSuggestion(stored);
            onSuggestionUpdated?.(stored);
            if (!isReadOnly && onApplyCost) onApplyCost(stored.suggested_cost);
            if (window.WFToast?.info) window.WFToast.info('Using stored cost suggestion (fresh).');
            return;
        }

        const confirmed = await confirmWithEstimate({
            action_key: 'inventory_generate_cost',
            action_label: 'Generate cost suggestion with AI',
            operations: [
                { key: 'cost_estimation', label: 'Cost suggestion' }
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
            setCachedCostSuggestion(stored);
            onSuggestionUpdated?.(stored);
            if (!isReadOnly && onApplyCost) onApplyCost(stored.suggested_cost);
            const when = stored.created_at ? ` (${formatTime(stored.created_at)})` : '';
            if (window.WFToast?.warning) window.WFToast.warning(`AI cost refresh failed; using last stored cost suggestion${when}.`);
        }
    };

    const handleTierChange = (newTier: string) => {
        onTierChange(newTier);
        if (cached_cost_suggestion) {
            const updated = retier_cost_suggestion(cached_cost_suggestion, newTier, tier);
            if (!updated) return;

            setCachedCostSuggestion(updated);
            if (onSuggestionUpdated) onSuggestionUpdated(updated);
            if (!isReadOnly && onApplyCost) onApplyCost(updated.suggested_cost);
            return;
        }

        // No cached suggestion yet: immediately generate one for the new tier so
        // the suggested value updates without requiring an extra Generate click.
        // Fallback order: breakdown amount -> AI generation.
        void (async () => {
            if (window.WFToast) window.WFToast.info('Recalculating cost for selected tier...');
            try {
                const fetched = await fetchBreakdown();
                const storedTotal = Number(fetched?.totals?.stored || 0);
                const factorsTotal = Number(fetched?.totals?.total || 0);
                const breakdownTotal = storedTotal > 0 ? storedTotal : factorsTotal;

                if (Number.isFinite(breakdownTotal) && breakdownTotal > 0) {
                    const currentMult = getPriceTierMultiplier(tier || 'standard') || 1;
                    const targetMult = getPriceTierMultiplier(newTier || 'standard') || 1;
                    const baseCost = breakdownTotal / currentMult;
                    const retieredCost = Number((baseCost * targetMult).toFixed(2));
                    const categoryTotal = (rows: Array<{ cost?: number | string }> | undefined): number => {
                        if (!Array.isArray(rows)) return 0;
                        return rows.reduce((sum, row) => sum + (Number(row?.cost || 0) || 0), 0);
                    };
                    const scaledBreakdown = {
                        materials: Number((categoryTotal(fetched?.materials) * (targetMult / currentMult)).toFixed(2)),
                        labor: Number((categoryTotal(fetched?.labor) * (targetMult / currentMult)).toFixed(2)),
                        energy: Number((categoryTotal(fetched?.energy) * (targetMult / currentMult)).toFixed(2)),
                        equipment: Number((categoryTotal(fetched?.equipment) * (targetMult / currentMult)).toFixed(2))
                    };

                    const synthesized: CostSuggestion = {
                        suggested_cost: retieredCost,
                        baseline_cost: Number(baseCost.toFixed(2)),
                        confidence: savedConfidence ?? 0,
                        created_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                        breakdown: scaledBreakdown,
                        analysis: {},
                        reasoning: 'Derived from existing cost breakdown and retiered.'
                    };

                    setCachedCostSuggestion(synthesized);
                    if (onSuggestionUpdated) onSuggestionUpdated(synthesized);
                    if (!isReadOnly && onApplyCost) onApplyCost(synthesized.suggested_cost);
                    if (toastSuccess) toastSuccess('Suggested cost updated from breakdown');
                    return;
                }

                const suggestion = await runImageFirstSuggestion(newTier);
                if (!suggestion) {
                    if (toastError) toastError('Failed to recalculate suggested cost');
                    return;
                }
                if (toastSuccess) toastSuccess('Suggested cost updated');
            } catch (_err) {
                if (toastError) toastError('Failed to recalculate suggested cost');
            }
        })();
    };

    // AI Suggested Cost is read-only by design

    const handleApplyFullBreakdown = async () => {
        if (!cached_cost_suggestion) return;
        setIsApplying(true);
        try {
            const success = await populateFromSuggestion(cached_cost_suggestion);
            if (success) {
                if (onApplyCost) onApplyCost(cached_cost_suggestion.suggested_cost);
                if (onApplied) onApplied();
                if (toastSuccess) toastSuccess('Cost breakdown applied successfully');
            } else {
                if (toastError) toastError('Failed to apply cost breakdown');
            }
        } catch (err) {
            if (toastError) toastError('Error applying cost breakdown');
        } finally {
            setIsApplying(false);
        }
    };

    return (
        <div className="cost-suggestion-wrapper">
            <h4 className="font-semibold text-gray-700 mb-1 text-sm flex items-center gap-2">
                Cost Suggestion
            </h4>

            {!isReadOnly && (
                <div className="space-y-3 mb-4">
                    <QualityTierControl value={tier} onChange={handleTierChange} />
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleSuggest}
                            disabled={is_busy}
                            className="btn btn-primary text-sm py-1.5 px-4 flex-1"
                            data-help-id="inventory-ai-suggest-cost"
                        >
                            Generate
                        </button>
                    </div>
                </div>
            )}

            {!cached_cost_suggestion && showStoredMetadata && savedConfidence !== null && (
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

            {cached_cost_suggestion && (
                <div className="ai-data-panel mb-3">
                    <div className="flex justify-between items-center">
                        <span className="ai-data-label text-sm font-medium">Suggested Cost:</span>
                        <span className="ai-data-value--large">${cached_cost_suggestion.suggested_cost.toFixed(2)}</span>
                    </div>
                </div>
            )}

            {cached_cost_suggestion?.breakdown && (
                <div className="ai-data-panel overflow-hidden p-0 mb-6 bg-white border border-gray-100 rounded-xl">
                    <div className="ai-data-section-header bg-gray-50/50 p-2 text-[10px] font-bold text-gray-500 uppercase tracking-widest border-b border-gray-100">
                        AI Reasoning Components
                    </div>
                    <div>
                        {Object.entries(cached_cost_suggestion.breakdown)
                            .filter(([key]) => ['materials', 'labor', 'energy', 'equipment'].includes(key))
                            .map(([key, value]: [string, unknown]) => (
                                <div key={key} className="p-2 flex justify-between border-b border-gray-50 last:border-0 group hover:bg-gray-50 transition-colors">
                                    <span className="text-xs text-gray-600 capitalize">{key}</span>
                                    <span className="text-xs font-medium text-gray-900">${(Number(value) || 0).toFixed(2)}</span>
                                </div>
                            ))}
                    </div>
                </div>
            )}
        </div>
    );
};
