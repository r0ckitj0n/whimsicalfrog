import React from 'react';
import { AICostPanel } from '../AICostPanel.js';
import { CostBreakdownTable } from '../CostBreakdownTable.js';
import { FieldLockIcon } from '../FieldLockIcon.js';

interface CostAnalysisColumnProps {
    sku: string;
    formData: {
        name: string;
        description: string;
        category: string;
        cost_price: number;
    };
    isReadOnly: boolean;
    onApplyCost: (cost: number) => void;
    onCurrentCostChange?: (cost: number) => void;
    onBreakdownApplied: () => void;
    tier: string;
    onTierChange: (tier: string) => void;
    breakdownRefreshTrigger: number;
    /** Fields that are locked from AI overwrites */
    lockedFields?: Record<string, boolean>;
    /** Toggle lock status for a field */
    onToggleFieldLock?: (field: string) => void;
}

export const CostAnalysisColumn: React.FC<CostAnalysisColumnProps> = ({
    sku,
    formData,
    isReadOnly,
    onApplyCost,
    onCurrentCostChange,
    onBreakdownApplied,
    tier,
    onTierChange,
    breakdownRefreshTrigger,
    lockedFields = {},
    onToggleFieldLock
}) => {
    return (
        <div className="bg-gradient-to-br from-amber-50 via-orange-50/70 to-white rounded-2xl p-5 border border-amber-200 shadow-sm">
            <h3 className="text-[11px] font-black text-amber-800 uppercase tracking-widest mb-4 flex items-center gap-2">
                <span className="text-lg">💰</span> Cost Analysis
                {onToggleFieldLock && (
                    <FieldLockIcon
                        isLocked={!!lockedFields.cost_price}
                        onToggle={() => onToggleFieldLock('cost_price')}
                        fieldName="Cost Price"
                        disabled={isReadOnly}
                    />
                )}
            </h3>

            <div className="bg-white rounded-xl border border-amber-200 p-4 shadow-sm">
                <AICostPanel
                    sku={sku}
                    name={formData.name}
                    description={formData.description}
                    category={formData.category}
                    isReadOnly={isReadOnly}
                    onApplyCost={onApplyCost}
                    onApplied={onBreakdownApplied}
                    tier={tier}
                    onTierChange={onTierChange}
                />
            </div>

            <div className="bg-white rounded-xl border border-amber-200 overflow-hidden shadow-sm mt-4">
                <div className="px-3 py-2 bg-amber-50/80 border-b border-amber-200">
                    <h4 className="text-[10px] font-bold text-amber-800 uppercase tracking-widest">Cost Breakdown</h4>
                </div>
                <div className="p-3">
                    <CostBreakdownTable
                        sku={sku}
                        name={formData.name}
                        description={formData.description}
                        category={formData.category}
                        isReadOnly={isReadOnly}
                        refreshTrigger={breakdownRefreshTrigger}
                        currentPrice={formData.cost_price}
                        onCurrentPriceChange={onCurrentCostChange || onApplyCost}
                        tier={tier}
                    />
                </div>
            </div>
        </div>
    );
};
