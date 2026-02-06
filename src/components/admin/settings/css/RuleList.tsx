import React from 'react';
import { ICssRule } from '../../../../hooks/admin/useCssRules.js';

interface RuleListProps {
    rules: ICssRule[];
    onDelete: (id: number) => void;
    isLoading: boolean;
}

export const RuleList: React.FC<RuleListProps> = ({
    rules,
    onDelete,
    isLoading
}) => {
    return (
        <div className="bg-white border rounded-[2rem] overflow-hidden shadow-sm flex flex-col min-h-[500px]">
            <div className="px-6 py-4 bg-gray-50/50 border-b border-gray-100 flex items-center justify-between">
                <h3 className="font-black text-gray-900 text-[10px] uppercase tracking-[0.2em]">Active Overrides</h3>
                <span className="px-3 py-1 bg-white border rounded-full text-[10px] font-black text-gray-400 uppercase tracking-widest shadow-sm">
                    {rules.length} Rules
                </span>
            </div>

            <div className="flex-1 overflow-y-auto">
                <table className="w-full text-left border-collapse text-sm">
                    <thead className="sticky top-0 bg-white border-b border-gray-100 z-[var(--wf-z-elevated)]">
                        <tr>
                            <th className="px-6 py-4 font-black text-gray-400 uppercase tracking-widest text-[9px]">Selector & Note</th>
                            <th className="px-6 py-4 font-black text-gray-400 uppercase tracking-widest text-[9px]">Property</th>
                            <th className="px-6 py-4 font-black text-gray-400 uppercase tracking-widest text-[9px]">Value</th>
                            <th className="px-6 py-4 font-black text-gray-400 uppercase tracking-widest text-[9px] text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50">
                        {rules.map((rule) => (
                            <tr key={rule.id} className="group hover:bg-gray-50/50 transition-colors">
                                <td className="px-6 py-4">
                                    <div className="font-mono text-xs font-bold text-[var(--brand-primary)] mb-1">{rule.selector}</div>
                                    {rule.note && (
                                        <div className="text-[10px] text-gray-400 italic max-w-xs">{rule.note}</div>
                                    )}
                                </td>
                                <td className="px-6 py-4">
                                    <span className="font-mono text-xs text-gray-500 bg-gray-50 px-2 py-1 rounded border border-gray-100">{rule.property}</span>
                                </td>
                                <td className="px-6 py-4">
                                    <div className="flex items-center gap-2">
                                        <span className="font-mono text-xs font-black text-gray-900">{rule.value}</span>
                                        {rule.important && <span className="text-[9px] font-black text-[var(--brand-error)] uppercase tracking-tighter">!important</span>}
                                    </div>
                                </td>
                                <td className="px-6 py-4 text-right">
                                    <button
                                        onClick={() => rule.id && onDelete(rule.id)}
                                        className="admin-action-btn btn-icon--delete"
                                        data-help-id="rule-delete"
                                    />
                                </td>
                            </tr>
                        ))}
                        {rules.length === 0 && !isLoading && (
                            <tr>
                                <td colSpan={4} className="px-6 py-24 text-center">
                                    <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <span className="text-3xl opacity-20">📜</span>
                                    </div>
                                    <p className="text-sm text-gray-400 font-medium italic">No custom CSS rules defined.</p>
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};
