import React, { useState } from 'react';
import { ICategory } from '../../../../hooks/admin/useCategories.js';

interface CategoryTableProps {
    categories: ICategory[];
    onRename: (oldName: string, newName: string) => Promise<{ success: boolean; message?: string } | void>;
    onDelete: (name: string) => Promise<{ success: boolean; message?: string } | void>;
}

export const CategoryTable: React.FC<CategoryTableProps> = ({
    categories,
    onRename,
    onDelete
}) => {
    const [editingName, setEditingName] = useState<string | null>(null);
    const [editValue, setEditValue] = useState('');

    const handleStartEdit = (name: string) => {
        setEditingName(name);
        setEditValue(name);
    };

    const handleSave = async (oldName: string) => {
        if (editValue.trim() && editValue !== oldName) {
            await onRename(oldName, editValue.trim());
        }
        setEditingName(null);
    };

    return (
        <div className="bg-white border rounded-2xl shadow-sm overflow-hidden">
            <table className="w-full text-left border-collapse text-sm">
                <thead>
                    <tr className="bg-gray-50 border-b border-gray-100">
                        <th className="px-6 py-4 font-black text-gray-400 uppercase tracking-widest text-[10px]">Category Name</th>
                        <th className="px-6 py-4 font-black text-gray-400 uppercase tracking-widest text-[10px] text-center">Items</th>
                        <th className="px-6 py-4 font-black text-gray-400 uppercase tracking-widest text-[10px] text-right">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-50">
                    {categories?.map((cat) => (
                        <tr key={cat.category} className="group hover:bg-gray-50/50 transition-colors">
                            <td className="px-6 py-4">
                                {editingName === cat.category ? (
                                    <div className="flex items-center gap-2">
                                        <input
                                            type="text"
                                            value={editValue}
                                            onChange={(e) => setEditValue(e.target.value)}
                                            className="form-input py-1 text-sm rounded-lg border-gray-300 focus:ring-4 focus:ring-gray-300"
                                            autoFocus
                                            onKeyDown={(e) => e.key === 'Enter' && handleSave(cat.category)}
                                        />
                                        <button
                                            onClick={() => handleSave(cat.category)}
                                            className="admin-action-btn btn-icon--save"
                                            data-help-id="categories-action-save"
                                        ></button>
                                        <button
                                            onClick={() => setEditingName(null)}
                                            className="admin-action-btn btn-icon--close"
                                            data-help-id="categories-action-cancel"
                                        ></button>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2">
                                        <span className="font-bold text-gray-900">{cat.category}</span>
                                        <button
                                            onClick={() => handleStartEdit(cat.category)}
                                            className="admin-action-btn btn-icon--edit"
                                            data-help-id="categories-action-edit"
                                        ></button>
                                    </div>
                                )}
                            </td>
                            <td className="px-6 py-4 text-center">
                                <div className="inline-flex items-center gap-1.5 px-3 py-1 bg-white border border-gray-100 rounded-full shadow-sm">
                                    📦
                                    <span className="font-black text-gray-700 text-xs">{cat.item_count}</span>
                                </div>
                            </td>
                            <td className="px-6 py-4 text-right">
                                <button
                                    onClick={() => onDelete(cat.category)}
                                    className="admin-action-btn btn-icon--delete"
                                    data-help-id="categories-action-delete"
                                    type="button"
                                ></button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};
