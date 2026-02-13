import React, { useMemo, useState } from 'react';
import { ApiClient } from '../../../../core/ApiClient.js';
import logger from '../../../../core/logger.js';
import type { ISizeTemplate, IColorTemplate } from '../../../../types/theming.js';
import type { ICategoryLite } from '../../../../hooks/admin/useCategoryList.js';
import type { IInventoryOptionLink, InventoryOptionType, InventoryOptionAppliesToType } from '../../../../types/inventoryOptions.js';

type ItemSearchResult = { sku: string; name?: string | null; category?: string | null };
type SearchResponse = { success: boolean; results?: ItemSearchResult[]; message?: string; error?: string };

type PendingAdd = {
    option_type: InventoryOptionType;
    option_id: number;
    applies_to_type: InventoryOptionAppliesToType;
    category_id?: number | null;
    item_sku?: string | null;
};

type PendingDelete = { id: number; option_type: InventoryOptionType };

interface OptionAssignmentsTabProps {
    sizeTemplates: ISizeTemplate[];
    colorTemplates: IColorTemplate[];
    links: IInventoryOptionLink[];
    categories: ICategoryLite[];
    isBusy?: boolean;
    onAddLink: (payload: PendingAdd) => Promise<{ success: boolean; error?: string; id?: number }>;
    onDeleteLink: (payload: { id: number }) => Promise<{ success: boolean; error?: string }>;
    onClearOptionLinks: (payload: { option_type: InventoryOptionType; option_id: number }) => Promise<{ success: boolean; error?: string }>;
}

type DraftByKey = Record<
    string,
    {
        mode: 'category' | 'sku';
        category_id?: number | null;
        item_sku?: string;
        search_q?: string;
        search_results?: ItemSearchResult[];
        searching?: boolean;
    }
>;

function keyOf(option_type: InventoryOptionType, option_id: number) {
    return `${option_type}:${option_id}`;
}

function summarizeLink(l: IInventoryOptionLink) {
    if (l.applies_to_type === 'category') return `Category: ${l.category_name || `#${l.category_id ?? '?'}`}`;
    return `SKU: ${l.item_sku || '?'}${l.item_name ? ` (${l.item_name})` : ''}`;
}

export const OptionAssignmentsTab: React.FC<OptionAssignmentsTabProps> = ({
    sizeTemplates,
    colorTemplates,
    links,
    categories,
    isBusy = false,
    onAddLink,
    onDeleteLink,
    onClearOptionLinks,
}) => {
    const [drafts, setDrafts] = useState<DraftByKey>({});
    const [pendingAdds, setPendingAdds] = useState<PendingAdd[]>([]);
    const [pendingDeletes, setPendingDeletes] = useState<PendingDelete[]>([]);

    const linkGroups = useMemo(() => {
        const m = new Map<string, IInventoryOptionLink[]>();
        links.forEach((l) => {
            const k = keyOf(l.option_type, l.option_id);
            const list = m.get(k) || [];
            list.push(l);
            m.set(k, list);
        });
        // Stable presentation: category first then sku; then alpha
        m.forEach((list, k) => {
            list.sort((a, b) => {
                if (a.applies_to_type !== b.applies_to_type) return a.applies_to_type === 'category' ? -1 : 1;
                const aLabel = a.applies_to_type === 'category' ? (a.category_name || '') : (a.item_sku || '');
                const bLabel = b.applies_to_type === 'category' ? (b.category_name || '') : (b.item_sku || '');
                return aLabel.localeCompare(bLabel);
            });
            m.set(k, list);
        });
        return m;
    }, [links]);

    const sections = useMemo(() => {
        const data: Array<{
            id: 'color_template' | 'size_template';
            label: string;
            option_type: InventoryOptionType;
            options: Array<{ option_id: number; title: string; subtitle?: string }>;
        }> = [
            {
                id: 'color_template',
                label: 'Color Templates',
                option_type: 'color_template',
                options: (colorTemplates || []).map((t) => ({ option_id: t.id, title: t.template_name, subtitle: t.category || 'General' })),
            },
            {
                id: 'size_template',
                label: 'Size Templates',
                option_type: 'size_template',
                options: (sizeTemplates || []).map((t) => ({ option_id: t.id, title: t.template_name, subtitle: t.category || 'General' })),
            },
        ];
        data.forEach((s) => s.options.sort((a, b) => a.title.localeCompare(b.title)));
        return data.sort((a, b) => a.label.localeCompare(b.label));
    }, [sizeTemplates, colorTemplates]);

    const setDraft = (option_type: InventoryOptionType, option_id: number, update: Partial<DraftByKey[string]>) => {
        const k = keyOf(option_type, option_id);
        setDrafts((prev) => ({ ...prev, [k]: { ...(prev[k] || { mode: 'category', category_id: categories[0]?.id || null }), ...update } }));
    };

    const getDraft = (option_type: InventoryOptionType, option_id: number) => {
        const k = keyOf(option_type, option_id);
        return drafts[k] || { mode: 'category' as const, category_id: categories[0]?.id || null };
    };

    const runSearch = async (option_type: InventoryOptionType, option_id: number) => {
        const k = keyOf(option_type, option_id);
        const q = (drafts[k]?.search_q || '').trim();
        if (!q) return;
        setDraft(option_type, option_id, { searching: true, search_results: [] });
        try {
            const res = await ApiClient.get<SearchResponse>('/api/search_items.php', { q });
            if (res?.success) setDraft(option_type, option_id, { search_results: res.results || [] });
            else setDraft(option_type, option_id, { search_results: [] });
        } catch (err) {
            logger.error('[OptionAssignmentsTab] search failed', err);
            setDraft(option_type, option_id, { search_results: [] });
        } finally {
            setDraft(option_type, option_id, { searching: false });
        }
    };

    const queueAdd = (option_type: InventoryOptionType, option_id: number) => {
        const d = getDraft(option_type, option_id);
        if (d.mode === 'category') {
            const category_id = Number(d.category_id || 0);
            if (!category_id) {
                if (window.WFToast) window.WFToast.error('Select a category');
                return;
            }
            setPendingAdds((prev) => [...prev, { option_type, option_id, applies_to_type: 'category', category_id, item_sku: null }]);
            return;
        }
        const sku = (d.item_sku || '').trim();
        if (!sku) {
            if (window.WFToast) window.WFToast.error('Enter a SKU');
            return;
        }
        setPendingAdds((prev) => [...prev, { option_type, option_id, applies_to_type: 'sku', item_sku: sku, category_id: null }]);
    };

    const queueDelete = (link: IInventoryOptionLink) => {
        setPendingDeletes((prev) => [...prev, { id: link.id, option_type: link.option_type }]);
    };

    const isPendingDelete = (id: number) => pendingDeletes.some((d) => d.id === id);

    const isPendingAddDuplicate = (p: PendingAdd) => {
        const existing = linkGroups.get(keyOf(p.option_type, p.option_id)) || [];
        const alreadyInDb = existing.some((l) => l.applies_to_type === p.applies_to_type
            && (p.applies_to_type === 'category' ? (l.category_id === (p.category_id ?? null)) : (l.item_sku === (p.item_sku ?? null))));
        const alreadyQueued = pendingAdds.some((q) => q.option_type === p.option_type
            && q.option_id === p.option_id
            && q.applies_to_type === p.applies_to_type
            && (p.applies_to_type === 'category' ? (q.category_id === p.category_id) : (q.item_sku === p.item_sku)));
        return alreadyInDb || alreadyQueued;
    };

    const sectionDirty = (option_type: InventoryOptionType) => {
        const hasAdds = pendingAdds.some((a) => a.option_type === option_type);
        const hasDeletes = pendingDeletes.some((d) => d.option_type === option_type);
        return hasAdds || hasDeletes;
    };

    const saveSection = async (option_type: InventoryOptionType) => {
        const adds = pendingAdds.filter((a) => a.option_type === option_type);
        const dels = pendingDeletes.filter((d) => d.option_type === option_type);
        if (adds.length === 0 && dels.length === 0) return;

        // Deletes first (so re-adding same target is safe).
        for (const d of dels) {
            const res = await onDeleteLink({ id: d.id });
            if (!res.success) {
                if (window.WFToast) window.WFToast.error(res.error || 'Failed to delete link');
                return;
            }
        }

        for (const a of adds) {
            if (isPendingAddDuplicate(a)) continue;
            const res = await onAddLink(a);
            if (!res.success) {
                if (window.WFToast) window.WFToast.error(res.error || 'Failed to add link');
                return;
            }
        }

        setPendingAdds((prev) => prev.filter((a) => a.option_type !== option_type));
        setPendingDeletes((prev) => prev.filter((d) => d.option_type !== option_type));
        if (window.WFToast) window.WFToast.success('Assignments saved');
    };

    const clearOption = async (option_type: InventoryOptionType, option_id: number) => {
        const res = await onClearOptionLinks({ option_type, option_id });
        if (res.success && window.WFToast) window.WFToast.success('Cleared');
        if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to clear');
    };

    return (
        <div className="space-y-6">
            <div>
                <h4 className="text-md font-bold text-gray-800">Assignments</h4>
                <div className="text-xs text-gray-500">Add multiple assignments per option. Each assignment targets either a Category or a SKU.</div>
            </div>

            {sections.map((section) => {
                const dirty = sectionDirty(section.option_type);
                return (
                    <div key={section.id} className="space-y-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <div className="text-sm font-bold text-gray-900">{section.label}</div>
                                <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Manage Links</div>
                            </div>
                            <button
                                type="button"
                                className={`admin-action-btn btn-icon--save dirty-only ${dirty ? 'is-dirty' : ''}`}
                                disabled={isBusy || !dirty}
                                onClick={() => { void saveSection(section.option_type); }}
                                data-help-id={`inventory-options-assignments-save-${section.id}`}
                            />
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {section.options.map((opt) => {
                                const k = keyOf(section.option_type, opt.option_id);
                                const assigned = linkGroups.get(k) || [];
                                const d = getDraft(section.option_type, opt.option_id);
                                const queuedAdds = pendingAdds.filter((a) => a.option_type === section.option_type && a.option_id === opt.option_id);
                                return (
                                    <div key={k} className="p-4 border rounded-lg bg-white shadow-sm">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <div className="text-sm font-bold text-gray-900 truncate">{opt.title}</div>
                                                <div className="text-xs text-gray-500">{opt.subtitle}</div>
                                            </div>
                                            <button
                                                type="button"
                                                className="admin-action-btn btn-icon--delete"
                                                disabled={isBusy}
                                                onClick={() => { void clearOption(section.option_type, opt.option_id); }}
                                                data-help-id="inventory-options-clear-option-links"
                                            />
                                        </div>

                                        <div className="mt-3 space-y-2">
                                            {assigned.length === 0 && queuedAdds.length === 0 && (
                                                <div className="text-xs text-gray-500">No assignments.</div>
                                            )}

                                            {assigned.map((l) => (
                                                <div key={l.id} className={`flex items-center justify-between gap-2 text-xs border rounded-lg px-3 py-2 ${isPendingDelete(l.id) ? 'opacity-50 line-through' : ''}`}>
                                                    <div className="min-w-0 truncate">{summarizeLink(l)}</div>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn btn-icon--delete"
                                                        disabled={isBusy || isPendingDelete(l.id)}
                                                        onClick={() => queueDelete(l)}
                                                        data-help-id="inventory-options-delete-link"
                                                    />
                                                </div>
                                            ))}

                                            {queuedAdds.map((a, idx) => (
                                                <div key={`${idx}-${a.applies_to_type}-${a.category_id ?? ''}-${a.item_sku ?? ''}`} className="flex items-center justify-between gap-2 text-xs border rounded-lg px-3 py-2 bg-emerald-50/40 border-emerald-200">
                                                    <div className="min-w-0 truncate">
                                                        {a.applies_to_type === 'category' ? `Category: #${a.category_id}` : `SKU: ${a.item_sku}`}
                                                        <span className="ml-2 text-[10px] font-bold uppercase tracking-widest text-emerald-700/70">Pending</span>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn btn-icon--close"
                                                        disabled={isBusy}
                                                        onClick={() => setPendingAdds((prev) => prev.filter((p) => p !== a))}
                                                        data-help-id="inventory-options-remove-pending-add"
                                                    />
                                                </div>
                                            ))}
                                        </div>

                                        <div className="mt-3 border-t pt-3">
                                            <div className="grid grid-cols-1 gap-2">
                                                <div className="flex gap-2">
                                                    <select
                                                        className="form-input text-sm"
                                                        value={d.mode}
                                                        disabled={isBusy}
                                                        onChange={(e) => {
                                                            const mode = e.target.value as 'category' | 'sku';
                                                            if (mode === 'category') setDraft(section.option_type, opt.option_id, { mode, category_id: categories[0]?.id || null, item_sku: '', search_results: [] });
                                                            else setDraft(section.option_type, opt.option_id, { mode, item_sku: '', category_id: null, search_results: [] });
                                                        }}
                                                    >
                                                        <option value="category">Category</option>
                                                        <option value="sku">SKU</option>
                                                    </select>

                                                    <button
                                                        type="button"
                                                        className="btn btn-primary px-3 py-2"
                                                        disabled={isBusy}
                                                        onClick={() => queueAdd(section.option_type, opt.option_id)}
                                                    >
                                                        Add
                                                    </button>
                                                </div>

                                                {d.mode === 'category' && (
                                                    <select
                                                        className="form-input w-full text-sm"
                                                        value={String(d.category_id || '')}
                                                        disabled={isBusy}
                                                        onChange={(e) => setDraft(section.option_type, opt.option_id, { category_id: e.target.value ? Number(e.target.value) : null })}
                                                    >
                                                        <option value="">Select category...</option>
                                                        {categories.map((c) => (
                                                            <option key={c.id} value={String(c.id)}>{c.name}</option>
                                                        ))}
                                                    </select>
                                                )}

                                                {d.mode === 'sku' && (
                                                    <>
                                                        <input
                                                            className="form-input w-full text-sm font-mono"
                                                            value={d.item_sku || ''}
                                                            placeholder="SKU"
                                                            disabled={isBusy}
                                                            onChange={(e) => setDraft(section.option_type, opt.option_id, { item_sku: e.target.value })}
                                                        />
                                                        <div className="flex gap-2">
                                                            <input
                                                                className="form-input w-full text-sm"
                                                                value={d.search_q || ''}
                                                                placeholder="Search items"
                                                                disabled={isBusy}
                                                                onChange={(e) => setDraft(section.option_type, opt.option_id, { search_q: e.target.value })}
                                                            />
                                                            <button
                                                                type="button"
                                                                className="btn btn-secondary px-3 py-2"
                                                                onClick={() => { void runSearch(section.option_type, opt.option_id); }}
                                                                disabled={isBusy || !!d.searching || !(d.search_q || '').trim()}
                                                            >
                                                                {d.searching ? '...' : 'Search'}
                                                            </button>
                                                        </div>
                                                        {Array.isArray(d.search_results) && d.search_results.length > 0 && (
                                                            <div className="border rounded-lg overflow-hidden">
                                                                <div className="bg-gray-50 px-3 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Results</div>
                                                                <div className="max-h-40 overflow-y-auto divide-y">
                                                                    {d.search_results.map((r) => (
                                                                        <button
                                                                            key={r.sku}
                                                                            type="button"
                                                                            className="w-full text-left px-3 py-2 hover:bg-slate-50 transition-colors"
                                                                            onClick={() => setDraft(section.option_type, opt.option_id, { item_sku: r.sku, search_results: [] })}
                                                                            disabled={isBusy}
                                                                        >
                                                                            <div className="text-sm font-mono text-gray-900">{r.sku}</div>
                                                                            <div className="text-xs text-gray-600 truncate">{r.name || ''}{r.category ? ` • ${r.category}` : ''}</div>
                                                                        </button>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
};

