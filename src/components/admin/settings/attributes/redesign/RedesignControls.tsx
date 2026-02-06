import React from 'react';

interface IRedesignItem {
    sku: string;
    name: string;
}

interface RedesignControlsProps {
    items: IRedesignItem[];
    selectedSku: string;
    onSkuChange: (sku: string) => void;
    onAnalyze: () => void;
    onPropose: () => void;
    onViewLive: () => void;
    isLoading: boolean;
    status: { msg: string; ok: boolean } | null;
}

export const RedesignControls: React.FC<RedesignControlsProps> = ({
    items,
    selectedSku,
    onSkuChange,
    onAnalyze,
    onPropose,
    onViewLive,
    isLoading,
    status
}) => {
    return (
        <div className="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex flex-wrap items-center gap-4">
            <div className="flex-1 min-w-[300px]">
                <label className="block text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 ml-1">Select Item</label>
                <select
                    value={selectedSku}
                    onChange={(e) => onSkuChange(e.target.value)}
                    className="w-full h-12 px-4 rounded-xl bg-slate-50 border-none text-sm font-bold focus:ring-2 focus:ring-blue-500/20 transition-all outline-none"
                >
                    <option value="">Select an item...</option>
                    {items.map(item => (
                        <option key={item.sku} value={item.sku}>
                            {item.sku} — {item.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="flex items-center gap-2 pt-5">
                <button
                    onClick={onAnalyze}
                    disabled={isLoading || !selectedSku}
                    className="btn btn-text-secondary"
                >
                    Analyze
                </button>
                <button
                    onClick={onPropose}
                    disabled={isLoading || !selectedSku}
                    className="btn btn-text-primary"
                >
                    Propose
                </button>
                <button
                    onClick={onViewLive}
                    disabled={isLoading || !selectedSku}
                    className="btn btn-text-secondary"
                >
                    View Live
                </button>
            </div>

            {status && (
                <div className={`ml-auto px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest ${status.ok ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-600'} animate-in fade-in slide-in-from-right-2`}>
                    {status.msg}
                </div>
            )}
        </div>
    );
};
