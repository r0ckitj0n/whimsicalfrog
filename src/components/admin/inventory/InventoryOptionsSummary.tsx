import React, { useMemo } from 'react';
import { useEffectiveInventoryOptions } from '../../../hooks/admin/useEffectiveInventoryOptions.js';
import type { IInventoryOptionLink } from '../../../types/inventoryOptions.js';

interface InventoryOptionsSummaryProps {
    sku: string;
    isReadOnly?: boolean;
}

function groupLinks(links: IInventoryOptionLink[]) {
    const byType = new Map<string, IInventoryOptionLink[]>();
    links.forEach((l) => {
        const list = byType.get(l.option_type) || [];
        list.push(l);
        byType.set(l.option_type, list);
    });
    byType.forEach((list, k) => {
        list.sort((a, b) => {
            const aLabel = a.applies_to_type === 'category' ? (a.category_name || '') : (a.item_sku || '');
            const bLabel = b.applies_to_type === 'category' ? (b.category_name || '') : (b.item_sku || '');
            return (aLabel || '').localeCompare(bLabel || '');
        });
        byType.set(k, list);
    });
    return byType;
}

export const InventoryOptionsSummary: React.FC<InventoryOptionsSummaryProps> = ({ sku, isReadOnly = false }) => {
    const { cascade, links, isLoading, error, refresh } = useEffectiveInventoryOptions(sku);

    const byType = useMemo(() => groupLinks(links), [links]);

    const sourceLabel = cascade?.source === 'sku'
        ? 'SKU override'
        : cascade?.source === 'category'
            ? 'Category default'
            : 'System default';

    return (
        <div className="bg-white/80 border border-slate-200 rounded-2xl shadow-sm overflow-hidden flex flex-col">
            <div className="px-6 py-4 border-b border-slate-200 bg-slate-50/80 flex items-center justify-between">
                <div>
                    <h2 className="text-lg font-bold text-slate-900 tracking-tight">Inventory Options (Configured)</h2>
                    <p className="text-[10px] text-slate-500 font-black uppercase tracking-widest">Source: {sourceLabel}</p>
                </div>
                <button
                    type="button"
                    onClick={refresh}
                    className="admin-action-btn btn-icon--refresh"
                    disabled={isLoading}
                    data-help-id="inventory-options-refresh-effective"
                />
            </div>

            <div className="p-6 space-y-6 bg-white">
                {error && (
                    <div className="p-3 bg-red-50 border border-red-100 text-red-700 text-xs font-bold rounded-xl">
                        {error}
                    </div>
                )}

                <section className="space-y-3">
                    <div className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">
                        Cascade
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {[0, 1, 2].map((i) => (
                            <div key={i} className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase">Priority {i + 1}</label>
                                <div className="px-3 py-2 rounded-xl bg-gray-50 text-sm font-bold text-gray-800">
                                    {cascade?.settings?.cascade_order?.[i] || 'N/A'}
                                </div>
                            </div>
                        ))}
                    </div>

                    <div>
                        <label className="block text-[10px] font-bold text-gray-500 uppercase mb-1">Enabled Dimensions</label>
                        <div className="flex flex-wrap gap-2">
                            {(cascade?.settings?.enabled_dimensions || []).map((d) => (
                                <span key={d} className="px-3 py-1 rounded-full bg-slate-100 text-xs font-bold text-slate-700 uppercase tracking-widest">
                                    {d}
                                </span>
                            ))}
                            {(!cascade?.settings?.enabled_dimensions || cascade.settings.enabled_dimensions.length === 0) && (
                                <span className="text-xs text-gray-500">None</span>
                            )}
                        </div>
                    </div>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest pb-2 border-b border-gray-100">
                        Assignments
                    </div>
                    {(['color_template', 'size_template', 'material'] as const).map((t) => {
                        const list = byType.get(t) || [];
                        if (list.length === 0) return null;
                        const title = t === 'color_template' ? 'Color Templates' : t === 'size_template' ? 'Size Templates' : 'Materials';
                        return (
                            <div key={t} className="space-y-2">
                                <div className="text-xs font-bold text-slate-700">{title}</div>
                                <div className="space-y-1">
                                    {list.map((l) => (
                                        <div key={l.id} className="text-xs text-slate-700 flex items-center justify-between gap-2 border rounded-lg px-3 py-2 bg-white">
                                            <div className="min-w-0 truncate">
                                                <span className="font-semibold">{l.option_label || `${l.option_type} #${l.option_id}`}</span>
                                                <span className="mx-2 text-slate-300">•</span>
                                                <span className="font-semibold">{l.source || l.applies_to_type}</span>
                                                <span className="mx-2 text-slate-300">•</span>
                                                {l.applies_to_type === 'category'
                                                    ? (l.category_name || `Category #${l.category_id ?? '?'}`)
                                                    : (l.item_sku || 'SKU ?')}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                    {(links.length === 0) && <div className="text-xs text-gray-500">No template/material assignments found for this item.</div>}
                </section>

                {!isReadOnly && (
                    <div className="text-[11px] text-slate-500">
                        Edit these in <span className="font-semibold">Inventory Options</span> (Settings).
                    </div>
                )}
            </div>
        </div>
    );
};
