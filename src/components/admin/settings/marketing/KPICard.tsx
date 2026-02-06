import React from 'react';

interface KPICardProps {
    label: string;
    value: string | number;
    sub: string;
    iconKey?: string;
    emoji?: string;
    color: string;
    growthPercentage?: number;
}

export const KPICard: React.FC<KPICardProps> = ({
    label,
    value,
    sub,
    iconKey,
    emoji,
    color,
    growthPercentage
}) => (
    <div className="bg-white p-5 border rounded-xl shadow-sm space-y-2">
        <div className="flex items-center justify-between">
            <div
                className="p-2 rounded-lg text-xl"
                style={{
                    backgroundColor: `var(--brand-${color}-bg, rgba(var(--brand-${color}-rgb, 0, 0, 0), 0.1))`,
                    color: `var(--brand-${color}, currentColor)`
                }}
            >
                {iconKey ? <div className={`admin-action-btn btn-icon--${iconKey}`} data-help-id="marketing-kpi-info" /> : <span>{emoji}</span>}
            </div>
            {growthPercentage !== undefined && (
                <div className={`text-xs font-bold ${growthPercentage >= 0 ? 'text-[var(--brand-accent)]' : 'text-[var(--brand-error)]'}`}>
                    {growthPercentage >= 0 ? '+' : ''}{growthPercentage}%
                </div>
            )}
        </div>
        <div>
            <div className="text-2xl font-black text-gray-900">{value}</div>
            <div className="text-xs font-bold text-gray-400 uppercase tracking-wider">{label}</div>
        </div>
        <div className="text-[10px] text-gray-400 italic">{sub}</div>
    </div>
);
