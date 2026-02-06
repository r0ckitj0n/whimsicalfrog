import React from 'react';
import { IRedesignProposal } from '../../../../../hooks/admin/useSizeColorRedesign.js';

interface ProposedColor {
    color_name?: string;
    color_code?: string;
    stock_level?: number;
}

interface ProposedSize {
    name?: string;
    size_name?: string;
    code?: string;
    size_code?: string;
    price_adjustment?: number;
    colors?: ProposedColor[];
}

interface ProposalCardProps {
    proposal: IRedesignProposal | null;
    preserveStock: boolean;
    setPreserveStock: (val: boolean) => void;
    dryRun: boolean;
    setDryRun: (val: boolean) => void;
    onMigrate: () => void;
    isLoading: boolean;
}

export const ProposalCard: React.FC<ProposalCardProps> = ({
    proposal,
    preserveStock,
    setPreserveStock,
    dryRun,
    setDryRun,
    onMigrate,
    isLoading
}) => {
    return (
        <div className="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col gap-6">
            <div className="flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center text-lg">💡</div>
                    <h3 className="text-sm font-black text-slate-800 uppercase tracking-widest">Proposed Structure</h3>
                </div>
                <div className="flex items-center gap-4">
                    <label className="flex items-center gap-2 cursor-pointer group">
                        <input
                            type="checkbox"
                            checked={preserveStock}
                            onChange={(e) => setPreserveStock(e.target.checked)}
                            className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/20"
                        />
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest group-hover:text-slate-600 transition-colors">Preserve Stock</span>
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer group">
                        <input
                            type="checkbox"
                            checked={dryRun}
                            onChange={(e) => setDryRun(e.target.checked)}
                            className="w-4 h-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500/20"
                        />
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest group-hover:text-slate-600 transition-colors">Dry Run</span>
                    </label>
                </div>
            </div>

            {proposal ? (
                <div className="flex-1 flex flex-col gap-6">
                    <div className="p-4 bg-blue-50/50 border border-blue-100 text-blue-700 text-xs font-bold rounded-2xl">
                        {proposal.message}
                    </div>

                    <div className="flex-1 space-y-4 overflow-y-auto max-h-[400px] pr-2 custom-scrollbar">
                        {proposal.proposedSizes.map((size: ProposedSize, i: number) => (
                            <div key={i} className="p-4 bg-slate-50 rounded-2xl border border-slate-100 hover:border-blue-200 transition-colors">
                                <div className="flex items-center justify-between mb-3">
                                    <div>
                                        <span className="text-sm font-black text-slate-800">{size.name || size.size_name}</span>
                                        <span className="ml-2 px-2 py-0.5 bg-white text-[10px] font-black text-slate-400 rounded-lg">{size.code || size.size_code}</span>
                                    </div>
                                    <span className="text-[10px] font-black text-slate-400 uppercase">Δ ${Number(size.price_adjustment).toFixed(2)}</span>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {(size.colors || []).map((color: ProposedColor, ci: number) => (
                                        <div key={ci} className="flex items-center gap-2 px-3 py-1.5 bg-white rounded-xl shadow-sm border border-slate-100">
                                            <div className="w-3 h-3 rounded-full border border-slate-200" style={{ backgroundColor: color.color_code }} />
                                            <span className="text-[10px] font-bold text-slate-700">{color.color_name}</span>
                                            <span className="text-[9px] font-black text-slate-300">({color.stock_level})</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>

                    <button
                        onClick={onMigrate}
                        disabled={isLoading}
                        className="btn btn-text-primary w-full"
                    >
                        Execute Migration
                    </button>
                </div>
            ) : (
                <div className="flex-1 flex flex-col items-center justify-center p-12 text-slate-300 gap-4">
                    <span className="text-5xl opacity-20">💡</span>
                    <p className="text-[10px] font-black uppercase tracking-widest">Generate a proposal to see changes</p>
                </div>
            )}
        </div>
    );
};
