import React, { useMemo, useState } from 'react';
import type { IMaterial, IInventoryOptionLink } from '../../../../types/inventoryOptions.js';
import type { ICategoryLite } from '../../../../hooks/admin/useCategoryList.js';
import type { InventoryOptionAppliesToType } from '../../../../types/inventoryOptions.js';
import { ApiClient } from '../../../../core/ApiClient.js';
import logger from '../../../../core/logger.js';

type ItemSearchResult = { sku: string; name?: string | null; category?: string | null };
type SearchResponse = { success: boolean; results?: ItemSearchResult[]; message?: string; error?: string };

interface MaterialsTabProps {
    materials: IMaterial[];
    links: IInventoryOptionLink[];
    categories: ICategoryLite[];
    isBusy?: boolean;
    onCreate: (payload: { material_name: string; description?: string | null }) => Promise<{ success: boolean; error?: string }>;
    onUpdate: (payload: { id: number; material_name?: string; description?: string | null }) => Promise<{ success: boolean; error?: string }>;
    onDelete: (payload: { id: number }) => Promise<{ success: boolean; error?: string }>;
    onAddLink: (payload: { option_type: 'material'; option_id: number; applies_to_type: InventoryOptionAppliesToType; category_id?: number | null; item_sku?: string | null }) => Promise<{ success: boolean; error?: string; id?: number }>;
    onDeleteLink: (payload: { id: number }) => Promise<{ success: boolean; error?: string }>;
    onClearOptionLinks: (payload: { option_type: 'material'; option_id: number }) => Promise<{ success: boolean; error?: string }>;
    prompt: (opts: { title: string; message: string; confirmText?: string; icon?: string; input?: { defaultValue?: string } }) => Promise<string | null>;
    confirm: (opts: { title: string; message: string; confirmText?: string; confirmStyle?: 'danger' | 'primary'; iconKey?: string }) => Promise<boolean>;
}

type DraftById = Record<number, { open?: boolean; mode: 'category' | 'sku'; category_id?: number | null; item_sku?: string; search_q?: string; search_results?: ItemSearchResult[]; searching?: boolean }>;

export const MaterialsTab: React.FC<MaterialsTabProps> = ({
    materials,
    links,
    categories,
    isBusy = false,
    onCreate,
    onUpdate,
    onDelete,
    onAddLink,
    onDeleteLink,
    onClearOptionLinks,
    prompt,
    confirm,
}) => {
    const linksByMaterialId = useMemo(() => {
        const m = new Map<number, IInventoryOptionLink[]>();
        links
            .filter((l) => l.option_type === 'material')
            .forEach((l) => {
                const list = m.get(l.option_id) || [];
                list.push(l);
                m.set(l.option_id, list);
            });
        m.forEach((list, id) => {
            list.sort((a, b) => {
                if (a.applies_to_type !== b.applies_to_type) return a.applies_to_type === 'category' ? -1 : 1;
                const aLabel = a.applies_to_type === 'category' ? (a.category_name || '') : (a.item_sku || '');
                const bLabel = b.applies_to_type === 'category' ? (b.category_name || '') : (b.item_sku || '');
                return aLabel.localeCompare(bLabel);
            });
            m.set(id, list);
        });
        return m;
    }, [links]);

    const [drafts, setDrafts] = useState<DraftById>({});

    const summarizeLink = (l: IInventoryOptionLink) => {
        if (l.applies_to_type === 'category') return `Category: ${l.category_name || `#${l.category_id ?? '?'}`}`;
        return `SKU: ${l.item_sku || '?'}${l.item_name ? ` (${l.item_name})` : ''}`;
    };

    const setDraft = (id: number, update: Partial<DraftById[number]>) => {
        setDrafts((prev) => ({ ...prev, [id]: { ...(prev[id] || { mode: 'category', category_id: categories[0]?.id || null }), ...update } }));
    };

    const ensureDraft = (id: number) => {
        if (drafts[id]) return drafts[id];
        const init = { mode: 'category' as const, category_id: categories[0]?.id || null, open: false };
        setDrafts((prev) => ({ ...prev, [id]: init }));
        return init;
    };

    const runSearch = async (id: number) => {
        const q = (drafts[id]?.search_q || '').trim();
        if (!q) return;
        setDraft(id, { searching: true, search_results: [] });
        try {
            const res = await ApiClient.get<SearchResponse>('/api/search_items.php', { q });
            if (res?.success) setDraft(id, { search_results: res.results || [] });
            else setDraft(id, { search_results: [] });
        } catch (err) {
            logger.error('[MaterialsTab] search failed', err);
            setDraft(id, { search_results: [] });
        } finally {
            setDraft(id, { searching: false });
        }
    };

    const saveLink = async (id: number) => {
        const d = drafts[id] || ensureDraft(id);
        if (d.mode === 'category') {
            const category_id = Number(d.category_id || 0);
            if (!category_id) {
                if (window.WFToast) window.WFToast.error('Select a category');
                return;
            }
            const res = await onAddLink({ option_type: 'material', option_id: id, applies_to_type: 'category', category_id, item_sku: null });
            if (res.success && window.WFToast) window.WFToast.success('Link added');
            if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to add link');
            return;
        }
        const sku = (d.item_sku || '').trim();
        if (!sku) {
            if (window.WFToast) window.WFToast.error('Enter a SKU');
            return;
        }
        const res = await onAddLink({ option_type: 'material', option_id: id, applies_to_type: 'sku', item_sku: sku, category_id: null });
        if (res.success && window.WFToast) window.WFToast.success('Link added');
        if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to add link');
    };

    const addMaterial = async () => {
        const name = await prompt({ title: 'Add Material', message: 'Material name:', confirmText: 'Next', icon: '🧵' });
        if (!name || !name.trim()) return;
        const desc = await prompt({ title: 'Add Material', message: 'Optional description:', confirmText: 'Create', icon: '🧵' });
        const res = await onCreate({ material_name: name.trim(), description: desc || null });
        if (res.success && window.WFToast) window.WFToast.success('Material created');
        if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to create material');
    };

    const editMaterial = async (m: IMaterial) => {
        const name = await prompt({ title: 'Edit Material', message: 'Material name:', confirmText: 'Next', icon: '🧵', input: { defaultValue: m.material_name } });
        if (!name || !name.trim()) return;
        const desc = await prompt({ title: 'Edit Material', message: 'Optional description:', confirmText: 'Save', icon: '🧵', input: { defaultValue: m.description || '' } });
        const res = await onUpdate({ id: m.id, material_name: name.trim(), description: desc || null });
        if (res.success && window.WFToast) window.WFToast.success('Material updated');
        if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to update material');
    };

    const removeMaterial = async (m: IMaterial) => {
        const ok = await confirm({ title: 'Delete Material', message: `Delete "${m.material_name}"?`, confirmText: 'Delete Now', confirmStyle: 'danger', iconKey: 'delete' });
        if (!ok) return;
        const res = await onDelete({ id: m.id });
        if (res.success && window.WFToast) window.WFToast.success('Material deleted');
        if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to delete material');
    };

    const sortedMaterials = useMemo(() => {
        return [...(materials || [])].sort((a, b) => (a.material_name || '').localeCompare((b.material_name || ''), undefined, { sensitivity: 'base' }));
    }, [materials]);

    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <div>
                    <h4 className="text-md font-bold text-gray-800">Materials</h4>
                    <div className="text-xs text-gray-500">Manage common materials and optionally scope them to a Category or SKU.</div>
                </div>
                <button
                    type="button"
                    className="admin-action-btn btn-icon--add"
                    onClick={() => { void addMaterial(); }}
                    disabled={isBusy}
                    data-help-id="materials-add"
                />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {sortedMaterials.map((m) => {
                    const d = drafts[m.id] || ensureDraft(m.id);
                    const current = linksByMaterialId.get(m.id) || [];
                    return (
                        <div key={m.id} className="p-4 border rounded-lg bg-white shadow-sm">
                            <div className="flex justify-between items-start gap-3">
                                <div className="min-w-0">
                                    <div className="text-sm font-bold text-gray-900 truncate">{m.material_name}</div>
                                    {m.description && <div className="text-xs text-gray-500 mt-1">{m.description}</div>}
                                    {current.length > 0 && (
                                        <div className="text-xs text-gray-600 mt-2 space-y-1">
                                            <div className="font-semibold text-gray-800">Applies to:</div>
                                            {current.map((l) => (
                                                <div key={l.id} className="flex items-center justify-between gap-2">
                                                    <div className="truncate">{summarizeLink(l)}</div>
                                                    <button
                                                        type="button"
                                                        className="admin-action-btn btn-icon--delete"
                                                        onClick={() => { void onDeleteLink({ id: l.id }); }}
                                                        disabled={isBusy}
                                                        data-help-id="materials-delete-link"
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="flex gap-2 shrink-0">
                                    <button type="button" className="admin-action-btn btn-icon--edit" onClick={() => { void editMaterial(m); }} disabled={isBusy} data-help-id="materials-edit" />
                                    <button type="button" className="admin-action-btn btn-icon--delete" onClick={() => { void removeMaterial(m); }} disabled={isBusy} data-help-id="materials-delete" />
                                    <button
                                        type="button"
                                        className="admin-action-btn btn-icon--settings"
                                        onClick={() => setDraft(m.id, { open: !d.open })}
                                        disabled={isBusy}
                                        data-help-id="materials-assign"
                                    />
                                </div>
                            </div>

                            {d.open && (
                                <div className="mt-3 border-t pt-3">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3 items-start">
                                        <div>
                                            <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Link Type</label>
                                            <select
                                                className="form-input w-full text-sm"
                                                value={d.mode}
                                                onChange={(e) => {
                                                    const mode = e.target.value as DraftById[number]['mode'];
                                                    if (mode === 'category') setDraft(m.id, { mode, category_id: categories[0]?.id || null, item_sku: '', search_results: [] });
                                                    else setDraft(m.id, { mode, item_sku: '', category_id: null, search_results: [] });
                                                }}
                                                disabled={isBusy}
                                            >
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
                                                    onChange={(e) => setDraft(m.id, { category_id: e.target.value ? Number(e.target.value) : null })}
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
                                                <input
                                                    className="form-input w-full text-sm font-mono"
                                                    value={d.item_sku || ''}
                                                    placeholder="Type SKU or search below"
                                                    onChange={(e) => setDraft(m.id, { item_sku: e.target.value })}
                                                    disabled={isBusy}
                                                />
                                                <div className="mt-2 flex gap-2">
                                                    <input
                                                        className="form-input w-full text-sm"
                                                        value={d.search_q || ''}
                                                        placeholder="Search items by name/category/sku"
                                                        onChange={(e) => setDraft(m.id, { search_q: e.target.value })}
                                                        disabled={isBusy}
                                                    />
                                                    <button
                                                        type="button"
                                                        className="btn btn-secondary px-3 py-2"
                                                        onClick={() => { void runSearch(m.id); }}
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
                                                                    onClick={() => setDraft(m.id, { item_sku: r.sku, search_results: [] })}
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

                                    <div className="mt-3 flex items-center gap-2 justify-end">
                                        <button
                                            type="button"
                                            className="btn btn-secondary px-3 py-2"
                                            onClick={() => { void onClearOptionLinks({ option_type: 'material', option_id: m.id }); }}
                                            disabled={isBusy}
                                        >
                                            Clear Link
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-primary px-3 py-2"
                                            onClick={() => { void saveLink(m.id); }}
                                            disabled={isBusy}
                                        >
                                            Save Link
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
};
