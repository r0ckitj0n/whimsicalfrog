import React, { useMemo, useState } from 'react';
import type { ISizeTemplate, IColorTemplate } from '../../../../types/theming.js';
import type { ICategoryLite } from '../../../../hooks/admin/useCategoryList.js';
import type { IInventoryOptionLink, InventoryOptionType, InventoryOptionAppliesToType } from '../../../../types/inventoryOptions.js';

type PendingAdd = {
    option_type: InventoryOptionType;
    option_id: number;
    applies_to_type: InventoryOptionAppliesToType;
    category_id?: number | null;
    item_sku?: string | null;
};

interface OptionAssignmentsTabProps {
    sizeTemplates: ISizeTemplate[];
    colorTemplates: IColorTemplate[];
    links: IInventoryOptionLink[];
    categories: ICategoryLite[];
    isBusy?: boolean;
    onAddLink: (payload: PendingAdd) => Promise<{ success: boolean; error?: string; id?: number }>;
    onDeleteLink: (payload: { id: number }) => Promise<{ success: boolean; error?: string }>;
    // Kept for compatibility (some other tools may still call it), but this view is category-centric now.
    onClearOptionLinks: (payload: { option_type: InventoryOptionType; option_id: number }) => Promise<{ success: boolean; error?: string }>;
}

export const OptionAssignmentsTab: React.FC<OptionAssignmentsTabProps> = ({
    sizeTemplates,
    colorTemplates,
    links,
    categories,
    isBusy = false,
    onAddLink,
    onDeleteLink,
}) => {
    const [categoryFilter, setCategoryFilter] = useState('');
    const [addColorByCategory, setAddColorByCategory] = useState<Record<number, number>>({});
    const [addSizeByCategory, setAddSizeByCategory] = useState<Record<number, number>>({});

    const sortedCategories = useMemo(() => {
        const q = categoryFilter.trim().toLowerCase();
        return [...(categories || [])]
            .filter((c) => !q || (c.name || '').toLowerCase().includes(q))
            .sort((a, b) => (a.name || '').localeCompare((b.name || ''), undefined, { sensitivity: 'base' }));
    }, [categories, categoryFilter]);

    const sortedColorTemplates = useMemo(() => {
        return [...(colorTemplates || [])].sort((a, b) => (a.template_name || '').localeCompare((b.template_name || ''), undefined, { sensitivity: 'base' }));
    }, [colorTemplates]);

    const sortedSizeTemplates = useMemo(() => {
        return [...(sizeTemplates || [])].sort((a, b) => (a.template_name || '').localeCompare((b.template_name || ''), undefined, { sensitivity: 'base' }));
    }, [sizeTemplates]);

    const categoryTemplateLinks = useMemo(() => {
        const byCat = new Map<number, { color: IInventoryOptionLink[]; size: IInventoryOptionLink[] }>();
        (links || []).forEach((l) => {
            if (l.applies_to_type !== 'category') return;
            const catId = Number(l.category_id || 0);
            if (!catId) return;
            if (l.option_type !== 'color_template' && l.option_type !== 'size_template') return;
            const cur = byCat.get(catId) || { color: [], size: [] };
            if (l.option_type === 'color_template') cur.color.push(l);
            if (l.option_type === 'size_template') cur.size.push(l);
            byCat.set(catId, cur);
        });
        byCat.forEach((v, k) => {
            v.color.sort((a, b) => (a.option_label || '').localeCompare((b.option_label || ''), undefined, { sensitivity: 'base' }));
            v.size.sort((a, b) => (a.option_label || '').localeCompare((b.option_label || ''), undefined, { sensitivity: 'base' }));
            byCat.set(k, v);
        });
        return byCat;
    }, [links]);

    const skuLinkCount = useMemo(() => {
        return (links || []).filter((l) =>
            l.applies_to_type === 'sku'
            && (l.option_type === 'color_template' || l.option_type === 'size_template')
        ).length;
    }, [links]);

    const addTemplateToCategory = async (category_id: number, option_type: 'color_template' | 'size_template', option_id: number) => {
        const existing = categoryTemplateLinks.get(category_id);
        const list = existing?.[option_type === 'color_template' ? 'color' : 'size'] || [];
        const already = list.some((l) => l.option_id === option_id);
        if (already) {
            window.WFToast?.info('Already assigned');
            return;
        }

        const res = await onAddLink({ option_type, option_id, applies_to_type: 'category', category_id, item_sku: null });
        if (res.success) window.WFToast?.success('Assigned');
        else window.WFToast?.error(res.error || 'Failed to assign');
    };

    const deleteLinkWithToast = async (id: number) => {
        const res = await onDeleteLink({ id });
        if (res.success) window.WFToast?.success('Removed');
        else window.WFToast?.error(res.error || 'Failed to remove');
    };

    return (
        <div className="space-y-6">
            <div>
                <h4 className="text-md font-bold text-gray-800">Assignments</h4>
                <div className="text-xs text-gray-500">
                    Assign templates to Categories. Items in that category inherit the options from all assigned templates.
                </div>
            </div>

            {skuLinkCount > 0 && (
                <div className="p-4 bg-amber-50 border border-amber-100 text-amber-800 text-xs font-bold rounded-2xl">
                    {skuLinkCount} SKU-specific template assignment(s) exist. This tab only edits Category assignments. If you want, I can add a small SKU override editor in the Item Information modal.
                </div>
            )}

            <input
                className="form-input w-full md:max-w-sm text-sm"
                value={categoryFilter}
                onChange={(e) => setCategoryFilter(e.target.value)}
                placeholder="Filter categories..."
                disabled={isBusy}
            />

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {sortedCategories.map((cat) => {
                    const cur = categoryTemplateLinks.get(cat.id) || { color: [], size: [] };
                    const selectedColor = addColorByCategory[cat.id] || 0;
                    const selectedSize = addSizeByCategory[cat.id] || 0;

                    return (
                        <div key={cat.id} className="p-4 border rounded-2xl bg-white shadow-sm space-y-3">
                            <div className="min-w-0">
                                <div className="text-sm font-black text-slate-900 truncate">{cat.name}</div>
                                <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Category</div>
                            </div>

                            <div className="space-y-3">
                                <div>
                                    <div className="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Color Templates</div>
                                    <div className="flex flex-wrap gap-2">
                                        {cur.color.length === 0 && <span className="text-xs text-slate-500">None</span>}
                                        {cur.color.map((l) => (
                                            <span key={l.id} className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700">
                                                <span className="truncate max-w-[220px]">{l.option_label || `#${l.option_id}`}</span>
                                                <button
                                                    type="button"
                                                    className="admin-action-btn btn-icon--close !w-6 !h-6 !min-w-6 !min-h-6 !text-xs"
                                                    disabled={isBusy}
                                                    onClick={() => { void deleteLinkWithToast(l.id); }}
                                                    data-help-id="inventory-options-delete-link"
                                                />
                                            </span>
                                        ))}
                                    </div>

                                    <div className="mt-2 flex gap-2">
                                        <select
                                            className="form-input w-full text-sm"
                                            value={String(selectedColor || '')}
                                            disabled={isBusy}
                                            onChange={(e) => setAddColorByCategory((prev) => ({ ...prev, [cat.id]: Number(e.target.value || 0) }))}
                                        >
                                            <option value="">Select template...</option>
                                            {sortedColorTemplates.map((t) => (
                                                <option key={t.id} value={String(t.id)}>{t.template_name}</option>
                                            ))}
                                        </select>
                                        <button
                                            type="button"
                                            className="btn btn-primary px-3 py-2"
                                            disabled={isBusy || !selectedColor}
                                            onClick={() => { void addTemplateToCategory(cat.id, 'color_template', selectedColor); }}
                                        >
                                            Add
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <div className="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">Size Templates</div>
                                    <div className="flex flex-wrap gap-2">
                                        {cur.size.length === 0 && <span className="text-xs text-slate-500">None</span>}
                                        {cur.size.map((l) => (
                                            <span key={l.id} className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-50 border border-slate-200 text-xs font-bold text-slate-700">
                                                <span className="truncate max-w-[220px]">{l.option_label || `#${l.option_id}`}</span>
                                                <button
                                                    type="button"
                                                    className="admin-action-btn btn-icon--close !w-6 !h-6 !min-w-6 !min-h-6 !text-xs"
                                                    disabled={isBusy}
                                                    onClick={() => { void deleteLinkWithToast(l.id); }}
                                                    data-help-id="inventory-options-delete-link"
                                                />
                                            </span>
                                        ))}
                                    </div>

                                    <div className="mt-2 flex gap-2">
                                        <select
                                            className="form-input w-full text-sm"
                                            value={String(selectedSize || '')}
                                            disabled={isBusy}
                                            onChange={(e) => setAddSizeByCategory((prev) => ({ ...prev, [cat.id]: Number(e.target.value || 0) }))}
                                        >
                                            <option value="">Select template...</option>
                                            {sortedSizeTemplates.map((t) => (
                                                <option key={t.id} value={String(t.id)}>{t.template_name}</option>
                                            ))}
                                        </select>
                                        <button
                                            type="button"
                                            className="btn btn-primary px-3 py-2"
                                            disabled={isBusy || !selectedSize}
                                            onClick={() => { void addTemplateToCategory(cat.id, 'size_template', selectedSize); }}
                                        >
                                            Add
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

