import React from 'react';

interface ChartCardProps {
    title: string;
    sub?: string;
    children: React.ReactNode;
    emoji?: string;
    iconKey?: string;
    className?: string;
}

export const ChartCard: React.FC<ChartCardProps> = ({
    title,
    sub,
    children,
    emoji,
    iconKey,
    className = ""
}) => (
    <div className={`bg-white border rounded-xl shadow-sm overflow-hidden flex flex-col ${className}`}>
        <div className="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
            <div className="flex items-center gap-2">
                {iconKey ? (
                    <div className={`admin-action-btn btn-icon--${iconKey} text-sm opacity-40`} data-help-id="marketing-chart-info" />
                ) : (
                    emoji && <span className="text-sm opacity-40">{emoji}</span>
                )}
                <h3 className="text-sm font-bold text-gray-700">{title}</h3>
            </div>
            {sub && <span className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{sub}</span>}
        </div>
        <div className="p-6 flex-1 min-h-[240px] relative">
            {children}
        </div>
    </div>
);
