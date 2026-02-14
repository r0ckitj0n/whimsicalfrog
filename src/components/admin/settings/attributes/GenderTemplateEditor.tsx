import React, { useEffect, useMemo, useState } from 'react';
import type { IGenderTemplate, IGenderTemplateItem, IGlobalGender } from '../../../../types/theming.js';
import { ApiClient } from '../../../../core/ApiClient.js';
import { AUTH } from '../../../../core/constants.js';
import logger from '../../../../core/logger.js';
import type { ITemplateCategoriesResponse } from '../../../../types/templates.js';

interface GenderTemplateEditorProps {
    template: Partial<IGenderTemplate>;
    globalGenders?: IGlobalGender[];
    onChange?: (data: IGenderTemplate) => void;
    onSave: () => Promise<boolean>;
    onCancel: () => void;
}

export const GenderTemplateEditor: React.FC<GenderTemplateEditorProps> = ({ template, globalGenders = [], onChange, onSave, onCancel }) => {
    const [name, setName] = useState(template.template_name || '');
    const [category, setCategory] = useState(template.category || 'General');
    const [description, setDescription] = useState(template.description || '');
    const [items, setItems] = useState<IGenderTemplateItem[]>(template.genders || []);
    const [categories, setCategories] = useState<string[]>([]);
    const [categoriesError, setCategoriesError] = useState<string | null>(null);

    const categoriesId = useMemo(() => `wf-gender-template-categories-${template.id || 'new'}`, [template.id]);
    const gendersId = useMemo(() => `wf-gender-template-genders-${template.id || 'new'}`, [template.id]);

    useEffect(() => {
        if (onChange) {
            onChange({
                ...template,
                template_name: name,
                category,
                description,
                genders: items
            } as IGenderTemplate);
        }
    }, [name, category, description, items, onChange, template]);

    useEffect(() => {
        let isMounted = true;
        (async () => {
            try {
                setCategoriesError(null);
                const res = await ApiClient.get<ITemplateCategoriesResponse>(`/api/gender_templates.php?action=get_categories&admin_token=${AUTH.ADMIN_TOKEN}`);
                if (!isMounted) return;
                // gender_templates.php returns Response::success({categories: [...]}) so parser flattens into { success, categories }
                if (res?.success) {
                    const list = (res.categories || []).map(String).map(s => s.trim()).filter(Boolean);
                    if (category && !list.includes(category)) list.unshift(category);
                    setCategories(Array.from(new Set(list)));
                } else {
                    setCategoriesError(res?.error || res?.message || 'Failed to load categories');
                }
            } catch (e: unknown) {
                const msg = e instanceof Error ? e.message : 'Failed to load categories';
                logger.error('GenderTemplateEditor category load failed', e);
                if (isMounted) setCategoriesError(msg);
            }
        })();
        return () => { isMounted = false; };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleAddItem = () => {
        const nextOrder = items.length ? Math.max(...items.map(i => Number(i.display_order || 0))) + 1 : 1;
        setItems([...items, { gender_name: '', display_order: nextOrder }]);
    };

    const handleUpdateItem = (index: number, updates: Partial<IGenderTemplateItem>) => {
        const newItems = [...items];
        newItems[index] = { ...newItems[index], ...updates };
        setItems(newItems);
    };

    const handleRemoveItem = (index: number) => {
        setItems(items.filter((_, i) => i !== index));
    };

    const handleMoveItem = (index: number, direction: 'up' | 'down') => {
        if (direction === 'up' && index === 0) return;
        if (direction === 'down' && index === items.length - 1) return;

        const newItems = [...items];
        const targetIndex = direction === 'up' ? index - 1 : index + 1;
        [newItems[index], newItems[targetIndex]] = [newItems[targetIndex], newItems[index]];
        newItems.forEach((it, idx) => { it.display_order = idx + 1; });
        setItems(newItems);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        void onSave();
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-bold text-gray-700 mb-1">Template Name</label>
                    <input
                        type="text"
                        value={name}
                        onChange={e => setName(e.target.value)}
                        className="form-input w-full"
                        required
                    />
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-700 mb-1">Category</label>
                    <input
                        type="text"
                        list={categoriesId}
                        value={category}
                        onChange={e => setCategory(e.target.value)}
                        className="form-input w-full"
                    />
                    <datalist id={categoriesId}>
                        {categories.map((c) => (
                            <option key={c} value={c} />
                        ))}
                    </datalist>
                    {categoriesError && (
                        <div className="mt-1 text-[11px] font-bold text-red-600">
                            {categoriesError}
                        </div>
                    )}
                </div>
            </div>

            <div>
                <label className="block text-sm font-bold text-gray-700 mb-1">Description (Internal)</label>
                <textarea
                    value={description}
                    onChange={e => setDescription(e.target.value)}
                    className="form-input w-full h-20"
                />
            </div>

            <div className="border rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Order</th>
                            <th className="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Gender</th>
                            <th className="px-4 py-2 text-right text-xs font-bold text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {items.map((item, idx) => (
                            <tr key={idx} className="group">
                                <td className="px-4 py-2">
                                    <div className="flex items-center gap-1">
                                        <button
                                            type="button"
                                            onClick={() => handleMoveItem(idx, 'up')}
                                            className="admin-action-btn btn-icon--up"
                                            disabled={idx === 0}
                                            data-help-id="settings-gender-move-up"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => handleMoveItem(idx, 'down')}
                                            className="admin-action-btn btn-icon--down"
                                            disabled={idx === items.length - 1}
                                            data-help-id="settings-gender-move-down"
                                        />
                                    </div>
                                </td>
                                <td className="px-4 py-2">
                                    <input
                                        type="text"
                                        list={gendersId}
                                        value={item.gender_name}
                                        onChange={e => handleUpdateItem(idx, { gender_name: e.target.value, display_order: idx + 1 })}
                                        className="form-input w-full text-sm"
                                        placeholder="e.g. Unisex"
                                    />
                                </td>
                                <td className="px-4 py-2 text-right">
                                    <button
                                        type="button"
                                        onClick={() => handleRemoveItem(idx)}
                                        className="admin-action-btn btn-icon--delete"
                                        data-help-id="settings-gender-delete"
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <datalist id={gendersId}>
                    {globalGenders.map((g) => (
                        <option key={g.id} value={g.gender_name} />
                    ))}
                </datalist>
                <div className="p-3 bg-gray-50 border-t">
                    <button
                        type="button"
                        onClick={handleAddItem}
                        className="btn btn-secondary w-full py-2 bg-transparent border-0 text-[var(--brand-secondary)] hover:bg-[var(--brand-secondary)]/5"
                    >
                        Add Gender Row
                    </button>
                </div>
            </div>
        </form>
    );
};

