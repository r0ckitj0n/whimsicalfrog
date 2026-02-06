import React from 'react';
import { IRedesignAnalysis } from '../../../../../hooks/admin/useSizeColorRedesign.js';

interface AnalysisCardProps {
    analysis: IRedesignAnalysis | null;
}

export const AnalysisCard: React.FC<AnalysisCardProps> = ({ analysis }) => {
    return (
        <div className="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 flex flex-col gap-6">
            <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center text-lg">📊</div>
                <h3 className="text-sm font-black text-slate-800 uppercase tracking-widest">Analysis</h3>
            </div>

            {analysis ? (
                <div className="space-y-6">
                    <div className="grid grid-cols-3 gap-4">
                        <div className="p-4 bg-slate-50 rounded-2xl">
                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Colors</p>
                            <p className="text-2xl font-black text-slate-800">{analysis.total_colors}</p>
                        </div>
                        <div className="p-4 bg-slate-50 rounded-2xl">
                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Sizes</p>
                            <p className="text-2xl font-black text-slate-800">{analysis.total_sizes}</p>
                        </div>
                        <div className="p-4 bg-slate-50 rounded-2xl">
                            <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Backwards</p>
                            <p className={`text-sm font-black uppercase ${analysis.is_backwards ? 'text-red-500' : 'text-green-500'}`}>
                                {analysis.is_backwards ? 'Yes' : 'No'}
                            </p>
                        </div>
                    </div>

                    <div>
                        <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Potential Issues</p>
                        <ul className="space-y-2">
                            {analysis.structure_issues.length > 0 ? analysis.structure_issues.map((issue: string, i: number) => (
                                <li key={i} className="flex gap-3 text-xs text-slate-600 bg-red-50/30 p-3 rounded-xl border border-red-100/50">
                                    <span className="text-red-400">●</span> {issue}
                                </li>
                            )) : (
                                <li className="text-xs text-slate-400 italic bg-green-50/30 p-3 rounded-xl border border-green-100/50">No critical issues found.</li>
                            )}
                        </ul>
                    </div>

                    <div>
                        <p className="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Recommendations</p>
                        <ul className="space-y-2">
                            {analysis.recommendations.map((rec: string, i: number) => (
                                <li key={i} className="flex gap-3 text-xs text-slate-600 bg-blue-50/30 p-3 rounded-xl border border-blue-100/50">
                                    <span className="text-blue-400">◇</span> {rec}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
            ) : (
                <div className="flex-1 flex flex-col items-center justify-center p-12 text-slate-300 gap-4">
                    <span className="text-5xl opacity-20">📊</span>
                    <p className="text-[10px] font-black uppercase tracking-widest">Select an item and run analysis</p>
                </div>
            )}
        </div>
    );
};
