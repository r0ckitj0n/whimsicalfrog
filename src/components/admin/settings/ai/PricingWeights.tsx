import React from 'react';
import { IAISettings } from '../../../../hooks/admin/useAISettings.js';

interface PricingWeightsProps {
    settings: IAISettings;
    onChange: (field: keyof IAISettings, value: unknown) => void;
}

export const PricingWeights: React.FC<PricingWeightsProps> = ({ settings, onChange }) => {
    return (
        <div className="space-y-4">
            <h4 className="text-sm font-black text-slate-800 flex items-center gap-2 tracking-tight ml-1">
                <span className="text-xl">⚖️</span> Pricing Logic Weights
            </h4>
            <div className="p-6 bg-slate-50/50 rounded-2xl border-2 border-slate-50 space-y-6">
                <div className="space-y-4">
                    <div className="flex justify-between items-center">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Cost-Plus</label>
                        <span className="text-xs font-black text-brand-primary">{(settings.ai_cost_plus_weight * 100).toFixed(0)}%</span>
                    </div>
                    <input
                        type="range" min="0" max="1" step="0.05"
                        value={settings.ai_cost_plus_weight}
                        onChange={e => onChange('ai_cost_plus_weight', parseFloat(e.target.value))}
                        className="w-full accent-brand-primary h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer"
                    />
                </div>
                <div className="space-y-4">
                    <div className="flex justify-between items-center">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Market Research</label>
                        <span className="text-xs font-black text-brand-primary">{(settings.ai_market_research_weight * 100).toFixed(0)}%</span>
                    </div>
                    <input
                        type="range" min="0" max="1" step="0.05"
                        value={settings.ai_market_research_weight}
                        onChange={e => onChange('ai_market_research_weight', parseFloat(e.target.value))}
                        className="w-full accent-brand-primary h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer"
                    />
                </div>
                <div className="space-y-4">
                    <div className="flex justify-between items-center">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Value-Based</label>
                        <span className="text-xs font-black text-brand-primary">{(settings.ai_value_based_weight * 100).toFixed(0)}%</span>
                    </div>
                    <input
                        type="range" min="0" max="1" step="0.05"
                        value={settings.ai_value_based_weight}
                        onChange={e => onChange('ai_value_based_weight', parseFloat(e.target.value))}
                        className="w-full accent-brand-primary h-1.5 bg-slate-100 rounded-lg appearance-none cursor-pointer"
                    />
                </div>
            </div>
        </div>
    );
};
