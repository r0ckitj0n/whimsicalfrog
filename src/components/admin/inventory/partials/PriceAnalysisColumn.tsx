import React from 'react';
import { AIPricingPanel } from '../AIPricingPanel.js';
import { PriceBreakdownTable } from '../PriceBreakdownTable.js';
import { FieldLockIcon } from '../FieldLockIcon.js';
import type { PriceSuggestion } from '../../../../hooks/admin/useInventoryAI.js';

interface PriceAnalysisColumnProps {
    sku: string;
    formData: {
        name: string;
        description: string;
        category: string;
        cost_price: number;
        retail_price: number;
    };
    isReadOnly: boolean;
    onApplyPrice: (price: number) => void;
    onCurrentRetailChange?: (price: number) => void;
    onBreakdownApplied: () => void;
    tier: string;
    onTierChange: (tier: string) => void;
    breakdownRefreshTrigger: number;
    /** Fields that are locked from AI overwrites */
    lockedFields?: Record<string, boolean>;
    /** Toggle lock status for a field */
    onToggleFieldLock?: (field: string) => void;
    cachedSuggestion?: PriceSuggestion | null;
    onSuggestionUpdated?: (suggestion: PriceSuggestion) => void;
}

export const PriceAnalysisColumn: React.FC<PriceAnalysisColumnProps> = ({
    sku,
    formData,
    isReadOnly,
    onApplyPrice,
    onCurrentRetailChange,
    onBreakdownApplied,
    tier,
    onTierChange,
    breakdownRefreshTrigger,
    lockedFields = {},
    onToggleFieldLock,
    cachedSuggestion = null,
    onSuggestionUpdated
}) => {
    return (
        <div className="bg-gradient-to-br from-emerald-50 via-green-50/70 to-white rounded-2xl p-5 border border-emerald-200 shadow-sm">
            <h3 className="text-[11px] font-black text-emerald-800 uppercase tracking-widest mb-4 flex items-center gap-2">
                <span className="text-lg">💵</span> Price Analysis
                {onToggleFieldLock && (
                    <FieldLockIcon
                        isLocked={!!lockedFields.retail_price}
                        onToggle={() => onToggleFieldLock('retail_price')}
                        fieldName="Retail Price"
                        disabled={isReadOnly}
                    />
                )}
            </h3>

            <div className="bg-white rounded-xl border border-emerald-200 p-4 shadow-sm">
                <AIPricingPanel
                    sku={sku}
                    name={formData.name}
                    description={formData.description}
                    category={formData.category}
                    cost_price={formData.cost_price}
                    isReadOnly={isReadOnly}
                    onApplyPrice={onApplyPrice}
                    onApplied={onBreakdownApplied}
                    tier={tier}
                    onTierChange={onTierChange}
                    cachedSuggestion={cachedSuggestion}
                    onSuggestionUpdated={onSuggestionUpdated}
                />
            </div>

            <div className="bg-white rounded-xl border border-emerald-200 overflow-hidden shadow-sm mt-4">
                <div className="px-3 py-2 bg-emerald-50/80 border-b border-emerald-200">
                    <h4 className="text-[10px] font-bold text-emerald-800 uppercase tracking-widest">Pricing Breakdown</h4>
                </div>
                <div className="p-3">
                    <PriceBreakdownTable
                        sku={sku}
                        name={formData.name}
                        description={formData.description}
                        category={formData.category}
                        isReadOnly={isReadOnly}
                        refreshTrigger={breakdownRefreshTrigger}
                        currentPrice={formData.retail_price}
                        onCurrentPriceChange={onCurrentRetailChange || onApplyPrice}
                        tier={tier}
                        cachedSuggestion={cachedSuggestion}
                    />
                </div>
            </div>
        </div>
    );
};
