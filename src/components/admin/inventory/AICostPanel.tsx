import React from 'react';
import { useInventoryAI } from '../../../hooks/admin/useInventoryAI.js';
import { useCostBreakdown } from '../../../hooks/admin/useCostBreakdown.js';
import { AI_TIER } from '../../../core/constants.js';
import { toastSuccess, toastError } from '../../../core/toast.js';
import { formatTime } from '../../../core/date-utils.js';

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
    showStoredMetadata = true
}) => {
    const { is_busy, cached_cost_suggestion: hookCachedSuggestion, fetch_cost_suggestion } = useInventoryAI();
    // Prefer prop over hook state - allows parent to pass in orchestrated suggestions
    const cached_cost_suggestion = propCachedSuggestion !== undefined ? propCachedSuggestion : hookCachedSuggestion;
    const { populateFromSuggestion, confidence: savedConfidence, appliedAt: savedAt } = useCostBreakdown(sku);
    const [isApplying, setIsApplying] = React.useState(false);

    const handleSuggest = async () => {
        const suggestion = await fetch_cost_suggestion({
            sku,
            name,
            description,
            category,
            tier
        });
        if (suggestion && onSuggestionUpdated) {
            onSuggestionUpdated(suggestion);
        }
        if (suggestion && !isReadOnly) {
            // Success - mark form as dirty so save button appears
            if (onApplied) onApplied();
        }
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
                    <div>
                        <label className="text-xs text-gray-600 block mb-1 font-medium uppercase tracking-wider">Quality Tier</label>
                        <select
                            value={tier}
                            onChange={(e) => onTierChange(e.target.value)}
                            className="w-full text-sm p-2 border border-gray-300 rounded bg-white shadow-sm focus:ring-2 focus:ring-[var(--brand-primary)]/20 outline-none"
                        >
                            <option value={AI_TIER.PREMIUM}>Premium (High Quality / +15%)</option>
                            <option value={AI_TIER.STANDARD}>Standard (Market Average)</option>
                            <option value={AI_TIER.CONSERVATIVE}>Conservative (Economy / -15%)</option>
                        </select>
                    </div>
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

            {cached_cost_suggestion ? (
                <div className="mb-6 p-4 bg-[var(--brand-primary)]/5 border border-[var(--brand-primary)]/10 rounded-xl animate-in fade-in duration-300">
                    <div className="flex justify-between items-center mb-2">
                        <span className="text-[10px] font-bold text-[var(--brand-primary)] uppercase tracking-widest">AI Suggested Cost</span>
                        <span className="text-lg font-bold text-[var(--brand-primary)]">
                            ${cached_cost_suggestion.suggested_cost.toFixed(2)}
                        </span>
                    </div>
                    <p className="text-xs text-gray-600 italic leading-relaxed mb-3">
                        "{cached_cost_suggestion.reasoning}"
                    </p>
                    <div className="flex items-center gap-3">
                        <span className="text-[10px] text-gray-400">Confidence: <span className="font-bold">{Math.round((Number(cached_cost_suggestion.confidence) || 0) * 100)}%</span></span>
                        <span className="text-[10px] text-gray-400">As of: <span className="font-bold">{formatTime(cached_cost_suggestion.created_at || Date.now())}</span></span>
                    </div>
                </div>
            ) : showStoredMetadata && savedConfidence !== null && (
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
