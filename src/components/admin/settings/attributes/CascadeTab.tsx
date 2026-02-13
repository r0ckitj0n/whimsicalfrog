import React, { useMemo, useState } from 'react';
import type { ICascadeConfig, IOptionCascadeSettings, OptionCascadeAppliesToType } from '../../../../types/inventoryOptions.js';
import type { ICategoryLite } from '../../../../hooks/admin/useCategoryList.js';
import { ApiClient } from '../../../../core/ApiClient.js';
import logger from '../../../../core/logger.js';

type ItemSearchResult = { sku: string; name?: string | null; category?: string | null };
type SearchResponse = { success: boolean; results?: ItemSearchResult[] };

interface CascadeTabProps {
    configs: ICascadeConfig[];
    categories: ICategoryLite[];
    isBusy?: boolean;
    onUpsert: (payload: {
        id?: number;
        applies_to_type: OptionCascadeAppliesToType;
        category_id?: number | null;
        item_sku?: string | null;
        settings: IOptionCascadeSettings;
    }) => Promise<{ success: boolean; error?: string; id?: number }>;
    onDelete: (payload: { id: number }) => Promise<{ success: boolean; error?: string }>;
}

const DEFAULT_SETTINGS: IOptionCascadeSettings = {
    cascade_order: ['gender', 'size', 'color'],
    enabled_dimensions: ['gender', 'size', 'color'],
    grouping_rules: {},
};

type DraftById = Record<
    number,
    {
        applies_to_type: OptionCascadeAppliesToType;
        category_id: number | null;
        item_sku: string;
        settings: IOptionCascadeSettings;
        search_q?: string;
        searching?: boolean;
        search_results?: ItemSearchResult[];
    }
>;

function settingsEqual(a: IOptionCascadeSettings, b: IOptionCascadeSettings) {
    return JSON.stringify(a) === JSON.stringify(b);
}

export const CascadeTab: React.FC<CascadeTabProps> = ({ configs, categories, isBusy = false, onUpsert, onDelete }) => {
    const byId = useMemo(() => {
        const m = new Map<number, ICascadeConfig>();
        configs.forEach((c) => m.set(c.id, c));
        return m;
    }, [configs]);

    const [drafts, setDrafts] = useState<DraftById>({});
    const [createDraft, setCreateDraft] = useState<{
        applies_to_type: OptionCascadeAppliesToType;
        category_id: number | null;
        item_sku: string;
        settings: IOptionCascadeSettings;
        search_q?: string;
        searching?: boolean;
        search_results?: ItemSearchResult[];
    }>({ applies_to_type: 'category', category_id: categories[0]?.id || null, item_sku: '', settings: DEFAULT_SETTINGS });

    const ensureDraft = (id: number) => {
        if (drafts[id]) return drafts[id];
        const c = byId.get(id);
        if (!c) {
            const init = { applies_to_type: 'category' as const, category_id: categories[0]?.id || null, item_sku: '', settings: DEFAULT_SETTINGS };
            setDrafts((prev) => ({ ...prev, [id]: init }));
            return init;
        }
        const init = {
            applies_to_type: c.applies_to_type,
            category_id: c.category_id ?? null,
            item_sku: c.item_sku ?? '',
            settings: c.settings,
        };
        setDrafts((prev) => ({ ...prev, [id]: init }));
        return init;
    };

    const setDraft = (id: number, update: Partial<DraftById[number]>) => {
        setDrafts((prev) => ({ ...prev, [id]: { ...(prev[id] || ensureDraft(id)), ...update } }));
    };

    const isDirty = (id: number) => {
        const c = byId.get(id);
        const d = drafts[id];
        if (!c || !d) return false;
        if (c.applies_to_type !== d.applies_to_type) return true;
        if ((c.category_id ?? null) !== (d.category_id ?? null)) return true;
        if ((c.item_sku ?? '') !== (d.item_sku ?? '')) return true;
        return !settingsEqual(c.settings, d.settings);
    };

    const updateCascadeOrder = (s: IOptionCascadeSettings, index: number, value: string): IOptionCascadeSettings => {
        const next = [...s.cascade_order];
        next[index] = value;
        return { ...s, cascade_order: next };
    };

    const toggleDim = (s: IOptionCascadeSettings, dim: string): IOptionCascadeSettings => {
        const next = s.enabled_dimensions.includes(dim)
            ? s.enabled_dimensions.filter((d) => d !== dim)
            : [...s.enabled_dimensions, dim];
        return { ...s, enabled_dimensions: next };
    };

    const updateGrouping = (s: IOptionCascadeSettings, val: string): IOptionCascadeSettings => {
        try {
            const parsed = val.trim() ? JSON.parse(val) : {};
            return { ...s, grouping_rules: parsed };
        } catch {
            return s;
        }
    };

    const runSearch = async (q: string, setState: (u: Partial<typeof createDraft>) => void) => {
        const term = q.trim();
        if (!term) return;
        setState({ searching: true, search_results: [] });
        try {
            const res = await ApiClient.get<SearchResponse>('/api/search_items.php', { q: term });
            setState({ search_results: res?.success ? (res.results || []) : [], searching: false });
        } catch (err) {
            logger.error('[CascadeTab] search failed', err);
            setState({ search_results: [], searching: false });
        }
    };

    const save = async (id: number) => {
        const d = drafts[id] || ensureDraft(id);
        const payload = {
            id,
            applies_to_type: d.applies_to_type,
            category_id: d.applies_to_type === 'category' ? d.category_id : null,
            item_sku: d.applies_to_type === 'sku' ? (d.item_sku || '').trim() : null,
            settings: d.settings,
        };
        const res = await onUpsert(payload);
        if (res.success && window.WFToast) window.WFToast.success('Cascade saved');
        if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to save');
    };

    const create = async () => {
        const payload = {
            applies_to_type: createDraft.applies_to_type,
            category_id: createDraft.applies_to_type === 'category' ? createDraft.category_id : null,
            item_sku: createDraft.applies_to_type === 'sku' ? (createDraft.item_sku || '').trim() : null,
            settings: createDraft.settings,
        };
        const res = await onUpsert(payload);
        if (res.success && window.WFToast) window.WFToast.success('Cascade created');
        if (!res.success && window.WFToast) window.WFToast.error(res.error || 'Failed to create');
    };

    const sorted = useMemo(() => {
        const list = [...configs];
        list.sort((a, b) => {
            const aLabel = a.applies_to_type === 'category' ? (a.category_name || '') : (a.item_sku || '');
            const bLabel = b.applies_to_type === 'category' ? (b.category_name || '') : (b.item_sku || '');
            return aLabel.localeCompare(bLabel);
        });
        return list;
    }, [configs]);

    return (
        <div className="space-y-6">
            <div>
                <h4 className="text-md font-bold text-gray-800">Cascade</h4>
                <div className="text-xs text-gray-500">Configure cascade order, enabled dimensions, and grouping scoped to Category or SKU.</div>
            </div>

            <div className="border rounded-2xl bg-white shadow-sm p-4">
                <div className="flex items-center justify-between mb-3">
                    <div className="text-sm font-bold text-gray-900">Add Cascade Config</div>
                    <button type="button" className="admin-action-btn btn-icon--save" disabled={isBusy} onClick={() => { void create(); }} data-help-id="cascade-create" />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Scope</label>
                        <select
                            className="form-input w-full text-sm"
                            value={createDraft.applies_to_type}
                            disabled={isBusy}
                            onChange={(e) => {
                                const v = e.target.value as OptionCascadeAppliesToType;
                                if (v === 'category') setCreateDraft((p) => ({ ...p, applies_to_type: v, category_id: categories[0]?.id || null, item_sku: '', search_results: [] }));
                                else setCreateDraft((p) => ({ ...p, applies_to_type: v, item_sku: '', category_id: null, search_results: [] }));
                            }}
                        >
                            <option value="category">Category</option>
                            <option value="sku">SKU</option>
                        </select>
                    </div>

                    {createDraft.applies_to_type === 'category' && (
                        <div className="md:col-span-2">
                            <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Category</label>
                            <select
                                className="form-input w-full text-sm"
                                value={String(createDraft.category_id || '')}
                                disabled={isBusy}
                                onChange={(e) => setCreateDraft((p) => ({ ...p, category_id: e.target.value ? Number(e.target.value) : null }))}
                            >
                                <option value="">Select...</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={String(c.id)}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                    )}

                    {createDraft.applies_to_type === 'sku' && (
                        <div className="md:col-span-2">
                            <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">SKU</label>
                            <input
                                className="form-input w-full text-sm font-mono"
                                value={createDraft.item_sku}
                                disabled={isBusy}
                                onChange={(e) => setCreateDraft((p) => ({ ...p, item_sku: e.target.value }))}
                                placeholder="SKU"
                            />
                            <div className="mt-2 flex gap-2">
                                <input
                                    className="form-input w-full text-sm"
                                    value={createDraft.search_q || ''}
                                    disabled={isBusy}
                                    onChange={(e) => setCreateDraft((p) => ({ ...p, search_q: e.target.value }))}
                                    placeholder="Search items"
                                />
                                <button
                                    type="button"
                                    className="btn btn-secondary px-3 py-2"
                                    disabled={isBusy || !!createDraft.searching || !(createDraft.search_q || '').trim()}
                                    onClick={() => { void runSearch(createDraft.search_q || '', (u) => setCreateDraft((p) => ({ ...p, ...u }))); }}
                                >
                                    {createDraft.searching ? '...' : 'Search'}
                                </button>
                            </div>
                            {Array.isArray(createDraft.search_results) && createDraft.search_results.length > 0 && (
                                <div className="mt-2 border rounded-lg overflow-hidden">
                                    <div className="bg-gray-50 px-3 py-2 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Results</div>
                                    <div className="max-h-40 overflow-y-auto divide-y">
                                        {createDraft.search_results.map((r) => (
                                            <button
                                                key={r.sku}
                                                type="button"
                                                className="w-full text-left px-3 py-2 hover:bg-slate-50 transition-colors"
                                                disabled={isBusy}
                                                onClick={() => setCreateDraft((p) => ({ ...p, item_sku: r.sku, search_results: [] }))}
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

                <div className="mt-4 grid grid-cols-1 gap-4">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        {[0, 1, 2].map((i) => (
                            <div key={i} className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase">Priority {i + 1}</label>
                                <select
                                    value={createDraft.settings.cascade_order[i] || ''}
                                    onChange={(e) => setCreateDraft((p) => ({ ...p, settings: updateCascadeOrder(p.settings, i, e.target.value) }))}
                                    disabled={isBusy}
                                    className="form-select w-full text-sm font-bold text-gray-700 bg-gray-50 border-gray-100 focus:bg-white transition-all rounded-xl"
                                >
                                    <option value="">N/A (Skip)</option>
                                    <option value="gender">Style/Gender</option>
                                    <option value="size">Size</option>
                                    <option value="color">Color</option>
                                </select>
                            </div>
                        ))}
                    </div>

                    <div>
                        <div className="flex flex-wrap gap-3">
                            {['gender', 'size', 'color'].map((dim) => {
                                const isActive = createDraft.settings.enabled_dimensions.includes(dim);
                                return (
                                    <button
                                        key={dim}
                                        type="button"
                                        onClick={() => setCreateDraft((p) => ({ ...p, settings: toggleDim(p.settings, dim) }))}
                                        disabled={isBusy}
                                        className={`px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all bg-transparent border-0 ${isActive ? 'text-[var(--brand-primary)]' : 'text-gray-400 hover:text-[var(--brand-primary)]'}`}
                                    >
                                        {dim === 'gender' ? 'Style' : dim}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    <div>
                        <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Grouping Rules (JSON)</label>
                        <textarea
                            value={JSON.stringify(createDraft.settings.grouping_rules, null, 2)}
                            onChange={(e) => setCreateDraft((p) => ({ ...p, settings: updateGrouping(p.settings, e.target.value) }))}
                            className="form-input w-full h-28 font-mono text-xs bg-[var(--brand-dark)] text-[var(--brand-accent)] rounded-2xl p-4 shadow-inner"
                            disabled={isBusy}
                            placeholder="{}"
                        />
                    </div>
                </div>
            </div>

            <div className="grid grid-cols-1 gap-4">
                {sorted.map((c) => {
                    const d = drafts[c.id] || ensureDraft(c.id);
                    const dirty = isDirty(c.id);
                    const scopeLabel = c.applies_to_type === 'category'
                        ? `Category: ${c.category_name || `#${c.category_id ?? '?'}`}`
                        : `SKU: ${c.item_sku || '?'}${c.item_name ? ` (${c.item_name})` : ''}`;

                    return (
                        <div key={c.id} className="border rounded-2xl bg-white shadow-sm overflow-hidden">
                            <div className="px-4 py-3 bg-slate-50 border-b flex items-center justify-between">
                                <div className="min-w-0">
                                    <div className="text-sm font-bold text-gray-900 truncate">{scopeLabel}</div>
                                    <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Cascade Config</div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        className={`admin-action-btn btn-icon--save dirty-only ${dirty ? 'is-dirty' : ''}`}
                                        disabled={isBusy || !dirty}
                                        onClick={() => { void save(c.id); }}
                                        data-help-id="cascade-save"
                                    />
                                    <button
                                        type="button"
                                        className="admin-action-btn btn-icon--delete"
                                        disabled={isBusy}
                                        onClick={() => { void onDelete({ id: c.id }); }}
                                        data-help-id="cascade-delete"
                                    />
                                </div>
                            </div>

                            <div className="p-4 space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    {[0, 1, 2].map((i) => (
                                        <div key={i} className="space-y-1">
                                            <label className="text-[10px] font-bold text-gray-500 uppercase">Priority {i + 1}</label>
                                            <select
                                                value={d.settings.cascade_order[i] || ''}
                                                onChange={(e) => setDraft(c.id, { settings: updateCascadeOrder(d.settings, i, e.target.value) })}
                                                disabled={isBusy}
                                                className="form-select w-full text-sm font-bold text-gray-700 bg-gray-50 border-gray-100 focus:bg-white transition-all rounded-xl"
                                            >
                                                <option value="">N/A (Skip)</option>
                                                <option value="gender">Style/Gender</option>
                                                <option value="size">Size</option>
                                                <option value="color">Color</option>
                                            </select>
                                        </div>
                                    ))}
                                </div>

                                <div>
                                    <div className="flex flex-wrap gap-3">
                                        {['gender', 'size', 'color'].map((dim) => {
                                            const active = d.settings.enabled_dimensions.includes(dim);
                                            return (
                                                <button
                                                    key={dim}
                                                    type="button"
                                                    onClick={() => setDraft(c.id, { settings: toggleDim(d.settings, dim) })}
                                                    disabled={isBusy}
                                                    className={`px-4 py-2 rounded-xl text-xs font-black uppercase tracking-widest transition-all bg-transparent border-0 ${active ? 'text-[var(--brand-primary)]' : 'text-gray-400 hover:text-[var(--brand-primary)]'}`}
                                                >
                                                    {dim === 'gender' ? 'Style' : dim}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Grouping Rules (JSON)</label>
                                    <textarea
                                        value={JSON.stringify(d.settings.grouping_rules, null, 2)}
                                        onChange={(e) => setDraft(c.id, { settings: updateGrouping(d.settings, e.target.value) })}
                                        className="form-input w-full h-28 font-mono text-xs bg-[var(--brand-dark)] text-[var(--brand-accent)] rounded-2xl p-4 shadow-inner"
                                        disabled={isBusy}
                                        placeholder="{}"
                                    />
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
};

