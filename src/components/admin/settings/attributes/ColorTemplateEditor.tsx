import React, { useState, useEffect, useMemo } from 'react';
import { IColorTemplate, IColorTemplateItem } from '../../../../hooks/admin/useGlobalEntities.js';
import { ApiClient } from '../../../../core/ApiClient.js';
import { AUTH } from '../../../../core/constants.js';
import logger from '../../../../core/logger.js';
import type { ITemplateCategoriesResponse } from '../../../../types/templates.js';

interface ColorTemplateEditorProps {
    template: Partial<IColorTemplate>;
    onChange?: (data: IColorTemplate) => void;
    onSave: () => Promise<boolean>;
    onCancel: () => void;
}

export const ColorTemplateEditor: React.FC<ColorTemplateEditorProps> = ({ template, onChange, onSave, onCancel }) => {
    const [name, setName] = useState(template.template_name || '');
    const [category, setCategory] = useState(template.category || 'General');
    const [description, setDescription] = useState(template.description || '');
    const [items, setItems] = useState<IColorTemplateItem[]>(template.colors || []);
    const [isDirty, setIsDirty] = useState(false);
    const [categories, setCategories] = useState<string[]>([]);
    const [categoriesError, setCategoriesError] = useState<string | null>(null);

    const categoriesId = useMemo(() => `wf-color-template-categories-${template.id || 'new'}`, [template.id]);

    useEffect(() => {
        setIsDirty(true);
        if (onChange) {
            onChange({
                ...template,
                template_name: name,
                category,
                description,
                colors: items
            } as IColorTemplate);
        }
    }, [name, category, description, items, onChange, template]);

    useEffect(() => {
        let isMounted = true;
        (async () => {
            try {
                setCategoriesError(null);
                const res = await ApiClient.get<ITemplateCategoriesResponse>(`/api/color_templates.php?action=get_categories&admin_token=${AUTH.ADMIN_TOKEN}`);
                if (!isMounted) return;
                if (res?.success) {
                    const list = (res.categories || []).map(String).map(s => s.trim()).filter(Boolean);
                    // Ensure current category stays selectable even if it's not in the list.
                    if (category && !list.includes(category)) list.unshift(category);
                    setCategories(Array.from(new Set(list)));
                } else {
                    setCategoriesError(res?.error || res?.message || 'Failed to load categories');
                }
            } catch (e: unknown) {
                const msg = e instanceof Error ? e.message : 'Failed to load categories';
                logger.error('ColorTemplateEditor category load failed', e);
                if (isMounted) setCategoriesError(msg);
            }
        })();
        return () => { isMounted = false; };
        // category intentionally not included; we just want to load once.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const handleAddItem = () => {
        setItems([...items, { color_name: '', color_code: '#000000', display_order: items.length + 1 }]);
    };

    const handleUpdateItem = (index: number, updates: Partial<IColorTemplateItem>) => {
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

        // Update display order (starts at 1 for colors in legacy logic)
        newItems.forEach((item, idx) => {
            item.display_order = idx + 1;
        });

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
                            <th className="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Color Name</th>
                            <th className="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Hex Code</th>
                            <th className="px-4 py-2 text-left text-xs font-bold text-gray-500 uppercase">Preview</th>
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
                                            data-help-id="settings-color-move-up"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => handleMoveItem(idx, 'down')}
                                            className="admin-action-btn btn-icon--down"
                                            disabled={idx === items.length - 1}
                                            data-help-id="settings-color-move-down"
                                        />
                                    </div>
                                </td>
                                <td className="px-4 py-2">
                                    <input
                                        type="text"
                                        value={item.color_name}
                                        onChange={e => handleUpdateItem(idx, { color_name: e.target.value })}
                                        className="form-input w-full text-sm"
                                        placeholder="e.g. Forest Green"
                                    />
                                </td>
                                <td className="px-4 py-2">
                                    <input
                                        type="text"
                                        value={item.color_code}
                                        onChange={e => handleUpdateItem(idx, { color_code: e.target.value })}
                                        className="form-input w-full text-sm font-mono"
                                        placeholder="#000000"
                                    />
                                </td>
                                <td className="px-4 py-2">
                                    <div
                                        className="w-8 h-8 rounded-full border shadow-sm"
                                        style={{ backgroundColor: (item.color_code || '#cccccc') }}
                                    />
                                </td>
                                <td className="px-4 py-2 text-right">
                                    <button
                                        type="button"
                                        onClick={() => handleRemoveItem(idx)}
                                        className="admin-action-btn btn-icon--delete"
                                        data-help-id="settings-color-delete"
                                    />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
                <div className="p-3 bg-gray-50 border-t">
                    <button
                        type="button"
                        onClick={handleAddItem}
                        className="btn btn-secondary w-full py-2 bg-transparent border-0 text-[var(--brand-secondary)] hover:bg-[var(--brand-secondary)]/5"
                    >
                        Add Color Row
                    </button>
                </div>
            </div>


        </form>
    );
};
