import React, { useState, useEffect } from 'react';
import { IColorTemplate, IColorTemplateItem } from '../../../../hooks/admin/useGlobalEntities.js';

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
                        value={category}
                        onChange={e => setCategory(e.target.value)}
                        className="form-input w-full"
                    />
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
                                        className="w-8 h-8 rounded-full border shadow-sm color-swatch-preview"
                                        style={{ '--swatch-color': item.color_code || '#cccccc' } as React.CSSProperties}
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
