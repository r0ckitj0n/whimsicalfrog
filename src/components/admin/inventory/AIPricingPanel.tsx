import React from 'react';
import { useInventoryAI, PriceSuggestion } from '../../../hooks/admin/useInventoryAI.js';
import { usePriceBreakdown } from '../../../hooks/admin/usePriceBreakdown.js';
import { PriceComponent } from '../../../hooks/admin/inventory-ai/usePriceSuggestions.js';
import { AI_TIER } from '../../../core/constants.js';
import { toastSuccess, toastError } from '../../../core/toast.js';
import { formatTime } from '../../../core/date-utils.js';

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
    onSuggestionUpdated
}) => {
    const {
        is_busy,
        cached_price_suggestion: hookCachedSuggestion,
        fetch_price_suggestion,
        retier_price_suggestion,
        setCachedPriceSuggestion
    } = useInventoryAI();

    // Prefer prop over hook state - allows parent to pass in orchestrated suggestions
    const cached_price_suggestion = propCachedSuggestion !== undefined ? propCachedSuggestion : hookCachedSuggestion;

    const { populateFromSuggestion, confidence: savedConfidence, appliedAt: savedAt } = usePriceBreakdown(sku);

    const [isApplying, setIsApplying] = React.useState(false);

    const handleSuggest = async () => {
        const suggestion = await fetch_price_suggestion({
            sku,
            name,
            description,
            category,
            cost_price,
            tier
        });
        if (suggestion && onSuggestionUpdated) {
            onSuggestionUpdated(suggestion);
        }
        if (suggestion && !isReadOnly) {
            // Mark as dirty so save button appears
            if (onApplied) onApplied();
        }
    };

    const handleTierChange = (newTier: string) => {
        onTierChange(newTier);
        if (cached_price_suggestion) {
            const updated = retier_price_suggestion(cached_price_suggestion, newTier);
            if (updated) {
                setCachedPriceSuggestion(updated);
                if (onSuggestionUpdated) onSuggestionUpdated(updated);
            }
        }
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
                    <div>
                        <label className="text-xs text-gray-600 block mb-1 font-medium uppercase tracking-wider">Quality Positioning</label>
                        <select
                            value={tier}
                            onChange={(e) => handleTierChange(e.target.value)}
                            className="w-full text-sm p-2 border border-gray-300 rounded bg-white shadow-sm focus:ring-2 focus:ring-[var(--brand-primary)] outline-none"
                        >
                            <option value={AI_TIER.PREMIUM}>Premium (High Quality / +15%)</option>
                            <option value={AI_TIER.STANDARD}>Standard (Market Average)</option>
                            <option value={AI_TIER.CONSERVATIVE}>Conservative (Economy / -15%)</option>
                        </select>
                    </div>
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

            {cached_price_suggestion ? (
                <div className="mb-6 p-4 bg-purple-50 border border-purple-100 rounded-xl animate-in fade-in duration-300">
                    <div className="flex justify-between items-center mb-2">
                        <span className="text-[10px] font-bold text-purple-600 uppercase tracking-widest">AI Suggested Retail</span>
                        <span className="text-lg font-bold text-purple-600">
                            ${cached_price_suggestion.suggested_price.toFixed(2)}
                        </span>
                    </div>
                    <p className="text-xs text-gray-600 italic leading-relaxed mb-3">
                        "{cached_price_suggestion.reasoning}"
                    </p>
                    <div className="flex items-center gap-3">
                        <span className="text-[10px] text-gray-400">Confidence: <span className="font-bold">{Math.round((Number(cached_price_suggestion.confidence) || 0) * 100)}%</span></span>
                        <span className="text-[10px] text-gray-400">As of: <span className="font-bold">{formatTime(cached_price_suggestion.created_at || Date.now())}</span></span>
                    </div>
                </div>
            ) : savedConfidence !== null && (
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
