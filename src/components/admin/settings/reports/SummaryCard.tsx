import React from 'react';
import { Line } from 'react-chartjs-2';

interface SummaryCardProps {
    label: string;
    value: string | number;
    color: string;
    data: number[];
}

const sparklineOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
    scales: { x: { display: false }, y: { display: false } },
    elements: { point: { radius: 0 }, line: { borderWidth: 2, tension: 0.4 } }
};

export const SummaryCard: React.FC<SummaryCardProps> = ({ 
    label, 
    value, 
    color, 
    data 
}) => {
    const borderColor = color === 'accent' ? 'var(--brand-accent)' : 
                      color === 'secondary' ? 'var(--brand-secondary)' : 
                      color === 'primary' ? 'var(--brand-primary)' : 'var(--brand-secondary)';

    return (
        <div className="bg-white p-5 border rounded-3xl shadow-sm space-y-4 group hover:shadow-md transition-all relative overflow-hidden">
            <div className="flex justify-between items-start">
                <div 
                    className="w-10 h-10 rounded-xl flex items-center justify-center text-xl"
                    style={{ 
                        backgroundColor: `var(--brand-${color}-bg, rgba(var(--brand-${color}-rgb, 0, 0, 0), 0.1))`,
                        color: `var(--brand-${color}, currentColor)` 
                    }}
                >
                    {label === 'Revenue' ? '💰' : label === 'Orders' ? '🛒' : label === 'Avg Value' ? '📈' : '👥'}
                </div>
                <div className="w-24 h-10">
                    <Line 
                        data={{
                            labels: data.map((_, i) => i),
                            datasets: [{
                                data: data,
                                borderColor,
                                fill: false
                            }]
                        }}
                        options={sparklineOptions}
                    />
                </div>
            </div>
            <div>
                <div className="text-2xl font-black text-gray-900 tracking-tight">{value}</div>
                <div className="text-[9px] font-black text-gray-400 uppercase tracking-widest">{label}</div>
            </div>
        </div>
    );
};
