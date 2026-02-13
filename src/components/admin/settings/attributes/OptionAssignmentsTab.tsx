import React, { useMemo, useState } from 'react';
import { ApiClient } from '../../../../core/ApiClient.js';
import logger from '../../../../core/logger.js';
import type { ISizeTemplate, IColorTemplate } from '../../../../types/theming.js';
import type { ICategoryLite } from '../../../../hooks/admin/useCategoryList.js';
import type { IInventoryOptionLink, InventoryOptionType, InventoryOptionAppliesToType } from '../../../../types/inventoryOptions.js';

type ItemSearchResult = { sku: string; name?: string | null; category?: string | null };
type SearchResponse = { success: boolean; results?: ItemSearchResult[]; message?: string; error?: string };

interface OptionAssignmentsTabProps {
    sizeTemplates: ISizeTemplate[];
    colorTemplates: IColorTemplate[];
    links: IInventoryOptionLink[];
    categories: ICategoryLite[];
    isBusy?: boolean;
    onUpsertLink: (payload: {
        option_type: InventoryOptionType;
        option_id: number;
        applies_to_type: InventoryOptionAppliesToType;
        category_id?: number | null;
        item_sku?: string | null;
    }) => Promise<{ success: boolean; error?: string }>;
    onClearLink: (payload: { option_type: InventoryOptionType; option_id: number }) => Promise<{ success: boolean; error?: string }>;
}

type DraftByKey = Record<string, { mode: 'unassigned' | 'category' | 'sku'; category_id?: number | null; item_sku?: string; search_q?: string; search_results?: ItemSearchResult[]; searching?: boolean }>;

function keyOf(option_type: InventoryOptionType, option_id: number) {
    return `${option_type}:${option_id}`;
}

export const OptionAssignmentsTab: React.FC<OptionAssignmentsTabProps> = ({
    sizeTemplates,
    colorTemplates,
    links,
    categories,
    isBusy = false,
    onUpsertLink,
    onClearLink,
}) => {
    const linkByKey = useMemo(() => {
        const m = new Map<string, IInventoryOptionLink>();
        links.forEach((l) => m.set(keyOf(l.option_type, l.option_id), l));
        return m;
    }, [links]);

    const [drafts, setDrafts] = useState<DraftByKey>({});

    const options = useMemo(() => {
        const out: Array<{ option_type: InventoryOptionType; option_id: number; label: string; sublabel: string }> = [];
        sizeTemplates.forEach((t) => out.push({ option_type: 'size_template', option_id: t.id, label: t.template_name, sublabel: 'Size Template' }));
        colorTemplates.forEach((t) => out.push({ option_type: 'color_template', option_id: t.id, label: t.template_name, sublabel: 'Color Template' }));
        return out.sort((a, b) => a.sublabel.localeCompare(b.sublabel) || a.label.localeCompare(b.label));
    }, [sizeTemplates, colorTemplates]);

    const summarizeLink = (l: IInventoryOptionLink | undefined) => {
        if (!l) return 'Unassigned';
        if (l.applies_to_type === 'category') return `Category: ${l.category_name || `#${l.category_id ?? '?'}`}`;
        return `SKU: ${l.item_sku || '?'}${l.item_name ? ` (${l.item_name})` : ''}`;
    };

    const ensureDraft = (option_type: InventoryOptionType, option_id: number) => {
        const k = keyOf(option_type, option_id);
        if (drafts[k]) return drafts[k];
        const existing = linkByKey.get(k);
        const init = existing
            ? (existing.applies_to_type === 'category'
                ? { mode: 'category' as const, category_id: existing.category_id ?? null }
                : { mode: 'sku' as const, item_sku: existing.item_sku ?? '' })
            : { mode: 'unassigned' as const };
        setDrafts((prev) => ({ ...prev, [k]: init }));
        return init;
    };

    const setDraft = (option_type: InventoryOptionType, option_id: number, update: Partial<DraftByKey[string]>) => {
        const k = keyOf(option_type, option_id);
        setDrafts((prev) => ({ ...prev, [k]: { ...(prev[k] || {}), ...update } }));
    };

    const runSearch = async (option_type: InventoryOptionType, option_id: number) => {
        const k = keyOf(option_type, option_id);
        const q = (drafts[k]?.search_q || '').trim();
        if (!q) return;
        setDraft(option_type, option_id, { searching: true, search_results: [] });
        try {
            const res = await ApiClient.get<SearchResponse>('/api/search_items.php', { q });
            if (res?.success) {
                setDraft(option_type, option_id, { search_results: res.results || [] });
            } else {
                setDraft(option_type, option_id, { search_results: [] });
            }
        } catch (err) {
            logger.error('[OptionAssignmentsTab] search failed', err);
            setDraft(option_type, option_id, { search_results: [] });
        } finally {
            setDraft(option_type, option_id, { searching: false });
        }
    };

    const save = async (option_type: InventoryOptionType, option_id: number) => {
        const k = keyOf(option_type, option_id);
        const d = drafts[k] || ensureDraft(option_type, option_id);
        if (d.mode === 'unassigned') {
            const res = await onClearLink({ option_type, option_id });
            if (res.success && window.WFToast) window.WFToast.success('Link cleared');
            if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to clear link');
            return;
        }
        if (d.mode === 'category') {
            const category_id = Number(d.category_id || 0);
            if (!category_id) {
                if (window.WFToast) window.WFToast.error('Select a category');
                return;
            }
            const res = await onUpsertLink({ option_type, option_id, applies_to_type: 'category', category_id, item_sku: null });
            if (res.success && window.WFToast) window.WFToast.success('Link saved');
            if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to save link');
            return;
        }
        const sku = (d.item_sku || '').trim();
        if (!sku) {
            if (window.WFToast) window.WFToast.error('Enter a SKU');
            return;
        }
        const res = await onUpsertLink({ option_type, option_id, applies_to_type: 'sku', item_sku: sku, category_id: null });
        if (res.success && window.WFToast) window.WFToast.success('Link saved');
        if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to save link');
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <div>
                    <h4 className="text-md font-bold text-gray-800">Inventory Option Assignments</h4>
                    <div className="text-xs text-gray-500">Each option can apply to either a Category or a SKU (never both).</div>
                </div>
            </div>

            <div className="space-y-3">
                {options.map((opt) => {
                    const k = keyOf(opt.option_type, opt.option_id);
                    const l = linkByKey.get(k);
                    const d = drafts[k] || ensureDraft(opt.option_type, opt.option_id);

                    return (
                        <div key={k} className="border rounded-lg bg-white shadow-sm p-4">
                            <div className="flex items-start justify-between gap-4">
                                <div className="min-w-0">
                                    <div className="text-sm font-bold text-gray-900 truncate">{opt.label}</div>
                                    <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{opt.sublabel}</div>
                                    <div className="text-xs text-gray-600 mt-1">
                                        <span className="font-semibold text-gray-800">Applies to:</span> {summarizeLink(l)}
                                    </div>
                                </div>

                                <div className="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        className="admin-action-btn btn-icon--save"
                                        disabled={isBusy}
                                        onClick={() => { void save(opt.option_type, opt.option_id); }}
                                        data-help-id="inventory-options-save-link"
                                    />
                                    <button
                                        type="button"
                                        className="admin-action-btn btn-icon--delete"
                                        disabled={isBusy}
                                        onClick={() => { setDraft(opt.option_type, opt.option_id, { mode: 'unassigned', category_id: null, item_sku: '', search_results: [] }); void save(opt.option_type, opt.option_id); }}
                                        data-help-id="inventory-options-clear-link"
                                    />
                                </div>
                            </div>

                            <div className="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
                                <div>
                                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Link Type</label>
                                    <select
                                        className="form-input w-full text-sm"
                                        value={d.mode}
                                        onChange={(e) => {
                                            const mode = e.target.value as DraftByKey[string]['mode'];
                                            if (mode === 'category') setDraft(opt.option_type, opt.option_id, { mode, category_id: categories[0]?.id || null, item_sku: '', search_results: [] });
                                            else if (mode === 'sku') setDraft(opt.option_type, opt.option_id, { mode, item_sku: '', category_id: null, search_results: [] });
                                            else setDraft(opt.option_type, opt.option_id, { mode, category_id: null, item_sku: '', search_results: [] });
                                        }}
                                        disabled={isBusy}
                                    >
                                        <option value="unassigned">Unassigned</option>
                                        <option value="category">Category</option>
                                        <option value="sku">SKU</option>
                                    </select>
                                </div>

                                {d.mode === 'category' && (
                                    <div>
                                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Category</label>
                                        <select
                                            className="form-input w-full text-sm"
                                            value={String(d.category_id || '')}
                                            onChange={(e) => setDraft(opt.option_type, opt.option_id, { category_id: e.target.value ? Number(e.target.value) : null })}
                                            disabled={isBusy}
                                        >
                                            <option value="">Select...</option>
                                            {categories.map((c) => (
                                                <option key={c.id} value={String(c.id)}>{c.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                                {d.mode === 'sku' && (
                                    <div className="md:col-span-2">
                                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">SKU</label>
                                        <div className="flex gap-2">
                                            <input
                                                className="form-input w-full text-sm font-mono"
                                                value={d.item_sku || ''}
                                                placeholder="Type SKU or search below"
                                                onChange={(e) => setDraft(opt.option_type, opt.option_id, { item_sku: e.target.value })}
                                                disabled={isBusy}
                                            />
                                        </div>

                                        <div className="mt-2 flex gap-2">
                                            <input
                                                className="form-input w-full text-sm"
                                                value={d.search_q || ''}
                                                placeholder="Search items by name/category/sku"
                                                onChange={(e) => setDraft(opt.option_type, opt.option_id, { search_q: e.target.value })}
                                                disabled={isBusy}
                                            />
                                            <button
                                                type="button"
                                                className="btn btn-secondary px-3 py-2"
                                                onClick={() => { void runSearch(opt.option_type, opt.option_id); }}
                                                disabled={isBusy || !!d.searching || !(d.search_q || '').trim()}
                                            >
                                                {d.searching ? 'Searching...' : 'Search'}
                                            </button>
                                        </div>

                                        {Array.isArray(d.search_results) && d.search_results.length > 0 && (
                                            <div className="mt-2 border rounded-lg overflow-hidden">
                                                <div className="bg-gray-50 px-3 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Results</div>
                                                <div className="max-h-40 overflow-y-auto divide-y">
                                                    {d.search_results.map((r) => (
                                                        <button
                                                            key={r.sku}
                                                            type="button"
                                                            className="w-full text-left px-3 py-2 hover:bg-slate-50 transition-colors"
                                                            onClick={() => setDraft(opt.option_type, opt.option_id, { item_sku: r.sku, search_results: [] })}
                                                            disabled={isBusy}
                                                        >
                                                            <div className="text-sm font-mono text-gray-900">{r.sku}</div>
                                                            <div className="text-xs text-gray-600 truncate">{r.name || ''}{r.category ? ` • ${r.category}` : ''}</div>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

