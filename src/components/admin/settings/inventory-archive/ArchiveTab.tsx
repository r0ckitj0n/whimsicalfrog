import React from 'react';

interface ArchiveItem {
    sku: string;
    name: string;
    category: string;
    stock_quantity: number;
    archived_at: string;
    archived_by?: string;
}

interface CategoryCount {
    category: string;
    count: number;
}

interface ArchiveTabProps {
    items: ArchiveItem[];
    categories: CategoryCount[];
    onRestore: (sku: string) => void;
    onNuke: (sku: string) => void;
}

export const ArchiveTab: React.FC<ArchiveTabProps> = ({
    items,
    categories,
    onRestore,
    onNuke
}) => {
    return (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-10">
            {/* Category Breakdown */}
            <div className="lg:col-span-1 bg-white border border-slate-100 rounded-[2rem] shadow-sm overflow-hidden flex flex-col">
                <div className="px-8 py-5 border-b border-slate-50 bg-slate-50/30 text-[10px] font-black uppercase tracking-widest text-slate-500">
                    Category Distribution
                </div>
                <div className="p-4 flex-1 overflow-y-auto">
                    {!categories.length ? (
                        <div className="p-8 text-center text-slate-300 italic text-[10px] font-bold uppercase">No data found</div>
                    ) : (
                        <ul className="space-y-1">
                            {categories.map((cat, i) => (
                                <li key={i} className="flex items-center justify-between px-4 py-3 hover:bg-slate-50 rounded-xl transition-colors group">
                                    <span className="text-xs font-bold text-slate-600">{cat.category || 'Uncategorized'}</span>
                                    <span className="text-[10px] font-black bg-slate-100 px-3 py-1 rounded-full text-slate-500 group-hover:bg-blue-600 group-hover:text-white transition-all">{cat.count}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </div>

            {/* Archive Table */}
            <div className="lg:col-span-2 bg-white border border-slate-100 rounded-[2rem] shadow-sm overflow-hidden flex flex-col">
                <div className="px-8 py-5 border-b border-slate-50 bg-slate-50/30 font-black text-[10px] uppercase tracking-widest text-slate-500 flex items-center justify-between">
                    Historical Unit Registry
                </div>
                <div className="flex-1 overflow-x-auto">
                    {!items.length ? (
                        <div className="p-20 text-center flex flex-col items-center gap-4">
                            <span className="text-4xl opacity-10">📦</span>
                            <p className="text-[10px] font-black text-slate-300 uppercase tracking-widest">Archive is currently empty</p>
                        </div>
                    ) : (
                        <table className="w-full text-left">
                            <thead className="bg-slate-50/50">
                                <tr>
                                    <th className="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Ident</th>
                                    <th className="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Description</th>
                                    <th className="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Vol</th>
                                    <th className="px-6 py-4 text-[9px] font-black text-slate-400 uppercase tracking-widest">Archived</th>
                                    <th className="px-6 py-4 text-right text-[9px] font-black text-slate-400 uppercase tracking-widest">Ops</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {items.slice(0, 100).map((item) => (
                                    <tr key={item.sku} className="hover:bg-slate-50/50 group transition-colors">
                                        <td className="px-6 py-4 font-mono text-[10px] font-bold text-slate-400">{item.sku}</td>
                                        <td className="px-6 py-4">
                                            <div className="text-xs font-bold text-slate-700 truncate max-w-[200px]">{item.name}</div>
                                            <div className="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">{item.category}</div>
                                        </td>
                                        <td className="px-6 py-4 font-black text-slate-500 text-xs">{item.stock_quantity}</td>
                                        <td className="px-6 py-4">
                                            <div className="text-[10px] font-bold text-slate-600">
                                                {item.archived_at ? new Date(item.archived_at).toLocaleDateString() : '—'}
                                            </div>
                                            <div className="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">by {item.archived_by || 'system'}</div>
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <div className="flex items-center justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <button
                                                    onClick={() => onRestore(item.sku)}
                                                    className="btn-wf px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all"
                                                >
                                                    Restore
                                                </button>
                                                <button
                                                    onClick={() => onNuke(item.sku)}
                                                    className="admin-action-btn btn-icon--delete"
                                                    data-help-id="common-delete"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </div>
    );
};
