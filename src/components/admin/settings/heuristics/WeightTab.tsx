import React from 'react';
import { IIntentHeuristics } from '../../../../hooks/admin/useIntentHeuristics.js';

interface WeightTabProps {
    editConfig: IIntentHeuristics;
    onUpdateWeight: (key: keyof IIntentHeuristics['weights'], value: string) => void;
}

const WeightInput = ({ label, field, value, onChange, step = 0.1 }: { 
    label: string, 
    field: keyof IIntentHeuristics['weights'], 
    value: number,
    onChange: (key: keyof IIntentHeuristics['weights'], val: string) => void,
    step?: number 
}) => (
    <div className="space-y-1">
        <label className="text-[10px] font-bold text-gray-500 uppercase block">{label}</label>
        <input 
            type="number" step={step}
            value={value}
            onChange={(e) => onChange(field, e.target.value)}
            className="form-input w-full text-sm py-1 font-mono"
        />
    </div>
);

export const WeightTab: React.FC<WeightTabProps> = ({ editConfig, onUpdateWeight }) => {
    return (
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2">
            <section className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div className="col-span-full">
                    <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        Core Influence Factors
                    </h3>
                </div>
                <WeightInput label="Popularity Cap" field="popularity_cap" value={editConfig.weights?.popularity_cap ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Keyword Match (+)" field="kw_positive" value={editConfig.weights?.kw_positive ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Category Match (+)" field="cat_positive" value={editConfig.weights?.cat_positive ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Seasonal Boost" field="seasonal" value={editConfig.weights?.seasonal ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Same Category" field="same_category" value={editConfig.weights?.same_category ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Negative Match (-)" field="neg_keyword_penalty" value={editConfig.weights?.neg_keyword_penalty ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Badge Threshold" field="intent_badge_threshold" value={editConfig.weights?.intent_badge_threshold ?? 0} onChange={onUpdateWeight} />
            </section>

            <section className="pt-8 border-t grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div className="col-span-full">
                    <h3 className="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-2">
                        specialized logic
                    </h3>
                </div>
                <WeightInput label="Upgrade Price Ratio" field="upgrade_price_ratio_threshold" value={editConfig.weights?.upgrade_price_ratio_threshold ?? 0} onChange={onUpdateWeight} step={0.01} />
                <WeightInput label="Upgrade Price Boost" field="upgrade_price_boost" value={editConfig.weights?.upgrade_price_boost ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Upgrade Label Boost" field="upgrade_label_boost" value={editConfig.weights?.upgrade_label_boost ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Replacement Label" field="replacement_label_boost" value={editConfig.weights?.replacement_label_boost ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Gift Set Boost" field="gift_set_boost" value={editConfig.weights?.gift_set_boost ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Gift Price Boost" field="gift_price_boost" value={editConfig.weights?.gift_price_boost ?? 0} onChange={onUpdateWeight} />
                <WeightInput label="Teacher Ceiling ($)" field="teacher_price_ceiling" value={editConfig.weights?.teacher_price_ceiling ?? 0} onChange={onUpdateWeight} step={1} />
                <WeightInput label="Teacher Price Boost" field="teacher_price_boost" value={editConfig.weights?.teacher_price_boost ?? 0} onChange={onUpdateWeight} />
            </section>
        </div>
    );
};
