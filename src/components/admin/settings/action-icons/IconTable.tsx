import React from 'react';

interface IconTableProps {
    filteredKeys: string[];
    localMap: Record<string, string>;
    onUpdateMapping: (key: string, emoji: string) => void;
    onRemoveMapping: (key: string) => void;
}

export const IconTable: React.FC<IconTableProps> = ({
    filteredKeys,
    localMap,
    onUpdateMapping,
    onRemoveMapping
}) => {
    return (
        <div className="flex-1 overflow-y-auto scrollbar-hide">
            <table className="w-full text-left border-collapse">
                <thead className="sticky top-0 bg-white border-b border-gray-100 z-[var(--wf-z-elevated)]">
                    <tr>
                        <th className="px-6 py-3 font-black text-gray-400 uppercase tracking-widest text-[9px]">Action Key</th>
                        <th className="px-6 py-3 font-black text-gray-400 uppercase tracking-widest text-[9px] text-center">Glyph</th>
                        <th className="px-6 py-3 font-black text-gray-400 uppercase tracking-widest text-[9px] text-center">CSS Class</th>
                        <th className="px-6 py-3 font-black text-gray-400 uppercase tracking-widest text-[9px] text-right">Action</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {filteredKeys.map((key) => (
                        <tr key={key} className="group hover:bg-gray-50/50 transition-colors">
                            <td className="px-6 py-3">
                                <input
                                    type="text"
                                    value={key}
                                    readOnly
                                    className="bg-transparent border-0 p-0 text-sm font-bold text-gray-700 w-full focus:ring-0 cursor-default"
                                />
                            </td>
                            <td className="px-6 py-3 text-center">
                                <input
                                    type="text"
                                    value={localMap[key]}
                                    onChange={e => onUpdateMapping(key, e.target.value)}
                                    className="w-12 py-1 text-center text-lg bg-white border border-gray-100 rounded-lg shadow-inner focus:ring-4 focus:ring-[var(--brand-primary)]/10 transition-all"
                                />
                            </td>
                            <td className="px-6 py-3 text-center">
                                <code className="text-[10px] font-mono text-[var(--brand-primary)] bg-[var(--brand-primary)]/5 px-2 py-1 rounded">
                                    .btn-icon--{key}
                                </code>
                            </td>
                            <td className="px-6 py-3 text-right">
                                <button
                                    onClick={() => onRemoveMapping(key)}
                                    className="admin-action-btn btn-icon--delete"
                                    type="button"
                                    data-help-id="action-icon-remove"
                                />
                            </td>
                        </tr>
                    ))}
                    {filteredKeys.length === 0 && (
                        <tr>
                            <td colSpan={4} className="py-24 text-center">
                                <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <span className="text-2xl opacity-20">🔍</span>
                                </div>
                                <p className="text-sm text-gray-400 font-medium italic">No mappings match your search</p>
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
};
