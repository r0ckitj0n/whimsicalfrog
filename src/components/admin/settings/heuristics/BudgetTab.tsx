import React from 'react';
import { IIntentHeuristics } from '../../../../hooks/admin/useIntentHeuristics.js';

interface BudgetTabProps {
    editConfig: IIntentHeuristics;
    onUpdateBudget: (tier: 'low' | 'mid' | 'high', index: 0 | 1, value: string) => void;
}

// Define theme for each budget tier - alternating primary/secondary
const TIER_THEMES = {
    low: 'wf-section-primary',      // Green bg → Orange text
    mid: 'wf-section-secondary',    // Orange bg → Green text  
    high: 'wf-section-primary',     // Green bg → Orange text
} as const;

export const BudgetTab: React.FC<BudgetTabProps> = ({ editConfig, onUpdateBudget }) => {
    return (
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2">
            <div className="p-4 bg-[var(--brand-primary)]/5 border border-[var(--brand-primary)]/10 rounded-xl flex items-start gap-3">
                <span className="text-xl mt-0.5">ℹ️</span>
                <div className="text-sm text-gray-700 leading-relaxed">
                    Budget ranges define what constitutes "Low", "Mid", and "High" price points for shoppers.
                    These affect gift recommendations and budget proximity calculations.
                </div>
            </div>

            <div className="wf-sections-grid-3">
                {(['low', 'mid', 'high'] as const).map(tier => (
                    <div key={tier} className={`${TIER_THEMES[tier]} wf-section-padding wf-section-flex space-y-4`}>
                        <h4 className="wf-section-header text-center text-sm">
                            {tier.charAt(0).toUpperCase() + tier.slice(1)} Budget
                        </h4>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <label className="wf-section-label block">Min ($)</label>
                                <input
                                    type="number" step="0.1"
                                    value={editConfig.budget_ranges[tier][0]}
                                    onChange={(e) => onUpdateBudget(tier, 0, e.target.value)}
                                    className="form-input w-full text-center font-mono"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="wf-section-label block">Max ($)</label>
                                <input
                                    type="number" step="0.1"
                                    value={editConfig.budget_ranges[tier][1]}
                                    onChange={(e) => onUpdateBudget(tier, 1, e.target.value)}
                                    className="form-input w-full text-center font-mono"
                                />
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};
