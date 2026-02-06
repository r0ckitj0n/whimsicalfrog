import React from 'react';

interface AISuggestionsData {
    title: string;
    description: string;
    price?: number | null;
    priceReasoning?: string;
    cost?: number | null;
    costBreakdown?: Record<string, number>;
}

interface SuggestionsDisplayProps {
    suggestions: AISuggestionsData | null;
    onApply: (field: string, value: string | number | null) => void;
    displayMode: 'cost' | 'price';
}

export const SuggestionsDisplay: React.FC<SuggestionsDisplayProps> = ({
    suggestions,
    onApply,
    displayMode
}) => {
    if (!suggestions) {
        return (
            <div className="flex-1 flex flex-col items-center justify-center py-12 text-gray-400">
                <p className="text-sm italic text-center">
                    Select an item and generate to see {displayMode === 'cost' ? 'cost' : 'price'} suggestions
                </p>
            </div>
        );
    }

    // Cost section uses GREEN/PRIMARY background theme
    // Therefore ALL non-white text should be ORANGE/SECONDARY (inverse)
    if (displayMode === 'cost') {
        return (
            <div className="flex-1 flex flex-col space-y-4">
                {/* Main Cost Display - Uses SECONDARY (orange) text on primary-themed section */}
                <div className="text-center py-4">
                    <div style={{ color: 'var(--brand-secondary, #ea580c)' }} className="text-4xl font-black">
                        {typeof suggestions.cost === 'number' ? `$${suggestions.cost.toFixed(2)}` : 'N/A'}
                    </div>
                    <div style={{ color: 'var(--brand-secondary, #bf5700)' }} className="text-xs mt-1 opacity-80">
                        AI Recommended Cost
                    </div>
                </div>

                {/* Apply Button - Secondary button (orange with white text) */}
                {suggestions.cost && (
                    <button
                        onClick={() => onApply('cost_price', suggestions.cost!)}
                        className="btn btn-secondary w-full text-sm py-2"
                        type="button"
                    >
                        Apply to Item
                    </button>
                )}

                {/* Cost Breakdown - All text uses SECONDARY (orange) */}
                {suggestions.costBreakdown && (
                    <div className="ai-data-panel flex-1 space-y-2">
                        <div className="ai-data-section-header !bg-transparent !p-0 !border-0 mb-3">
                            Cost Breakdown
                        </div>
                        <div>
                            {Object.entries(suggestions.costBreakdown).map(([key, val]) => (
                                <div key={key} className="ai-data-row">
                                    <span className="ai-data-row-label capitalize">{key.replace(/_/g, ' ')}</span>
                                    <span className="ai-data-row-value">${Number(val).toFixed(2)}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        );
    }

    // Price section uses ORANGE/SECONDARY background theme
    // Therefore ALL non-white text should be GREEN/PRIMARY (inverse)
    return (
        <div className="flex-1 flex flex-col space-y-4">
            {/* Main Price Display - Uses PRIMARY (green) text on secondary-themed section */}
            <div className="text-center py-4">
                <div style={{ color: 'var(--brand-primary, #16a34a)' }} className="text-4xl font-black">
                    {typeof suggestions.price === 'number' ? `$${suggestions.price.toFixed(2)}` : 'N/A'}
                </div>
                <div style={{ color: 'var(--brand-primary, #87ac3a)' }} className="text-xs mt-1 opacity-80">
                    AI Recommended Price
                </div>
            </div>

            {/* Apply Button - Primary button (green with white text) */}
            {suggestions.price && (
                <button
                    onClick={() => onApply('retail_price', suggestions.price!)}
                    className="btn btn-primary w-full text-sm py-2"
                    type="button"
                >
                    Apply to Item
                </button>
            )}

            {/* Price Reasoning - All text uses PRIMARY (green) */}
            {suggestions.priceReasoning && (
                <div className="ai-data-panel ai-data-panel--inverted flex-1 space-y-2">
                    <div className="ai-data-section-header !bg-transparent !p-0 !border-0 mb-3">
                        AI Reasoning
                    </div>
                    <div>
                        {suggestions.priceReasoning.split('•').map((item, idx) => {
                            const part = item.trim();
                            if (!part) return null;
                            const lastColon = part.lastIndexOf(':');
                            const label = lastColon !== -1 ? part.substring(0, lastColon).trim() : part;
                            const value = lastColon !== -1 ? part.substring(lastColon + 1).trim() : '';

                            return (
                                <div key={idx} className="ai-data-row">
                                    <span className="ai-data-row-label">{label}</span>
                                    <span className="ai-data-row-value">{value}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
};
