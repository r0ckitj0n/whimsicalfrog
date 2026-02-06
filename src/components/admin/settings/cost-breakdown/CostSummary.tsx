import React from 'react';

interface CostSummaryProps {
    totals: {
        materials: number;
        labor: number;
        energy: number;
        equipment: number;
    };
}

export const CostSummary: React.FC<CostSummaryProps> = ({ totals }) => {
    const stats = [
        { label: 'Materials', value: totals.materials, theme: 'primary' },
        { label: 'Labor', value: totals.labor, theme: 'secondary' },
        { label: 'Energy', value: totals.energy, theme: 'primary' },
        { label: 'Equipment', value: totals.equipment, theme: 'secondary' }
    ];

    return (
        <div className="flex flex-wrap justify-center gap-3 p-4 bg-slate-50 border-b border-slate-100">
            {stats.map((stat, i) => (
                <div
                    key={i}
                    className={`${stat.theme === 'primary' ? 'wf-section-primary' : 'wf-section-secondary'} 
                        px-4 py-3 shadow-sm group hover:shadow-md transition-all w-full max-w-[120px]`}
                >
                    <div className="wf-section-label leading-none mb-1">{stat.label}</div>
                    <div className="wf-section-value text-xl tracking-tight">
                        ${stat.value.toFixed(2)}
                    </div>
                </div>
            ))}
        </div>
    );
};
