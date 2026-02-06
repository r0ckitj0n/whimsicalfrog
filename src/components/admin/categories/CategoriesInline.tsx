import React, { useState } from 'react';
import { useCategories } from '../../../hooks/admin/useCategories.js';
import { useModalContext } from '../../../context/ModalContext.js';

export const CategoriesInline: React.FC = () => {
    const {
        categories,
        isLoading,
        createCategory,
        renameCategory,
        deleteCategory
    } = useCategories();

    const { confirm: confirmModal } = useModalContext();

    const [newName, setNewName] = useState('');
    const [editingName, setEditingName] = useState<string | null>(null);
    const [editValue, setEditValue] = useState('');

    const handleAdd = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newName.trim()) return;
        const res = await createCategory(newName.trim());
        if (res.success) setNewName('');
    };

    const handleSaveRename = async (oldName: string) => {
        if (editValue.trim() && editValue !== oldName) {
            await renameCategory(oldName, editValue.trim());
        }
        setEditingName(null);
    };

    return (
        <div className="space-y-4">
            <form onSubmit={handleAdd} className="flex gap-2">
                <input
                    type="text"
                    value={newName}
                    onChange={e => setNewName(e.target.value)}
                    placeholder="New category..."
                    className="form-input flex-1 py-1.5 text-sm rounded-xl border-gray-200"
                />
                <button
                    type="submit"
                    disabled={isLoading || !newName.trim()}
                    className="admin-action-btn btn-icon--add"
                    data-help-id="categories-action-add"
                ></button>
            </form>

            <div className="border rounded-2xl overflow-hidden divide-y divide-gray-50">
                {categories?.map(cat => (
                    <div key={cat.category || Math.random()} className="flex items-center justify-between p-3 bg-white hover:bg-gray-50 group transition-colors">
                        {editingName === cat.category ? (
                            <div className="flex items-center gap-2 flex-1">
                                <input
                                    type="text"
                                    value={editValue}
                                    onChange={e => setEditValue(e.target.value)}
                                    className="form-input flex-1 py-1 text-xs rounded-lg"
                                    autoFocus
                                    onKeyDown={e => e.key === 'Enter' && handleSaveRename(cat.category || '')}
                                />
                                <button
                                    onClick={() => handleSaveRename(cat.category || '')}
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
                            <>
                                <div className="flex items-center gap-2">
                                    <span className="text-xs font-bold text-gray-700">{cat.category}</span>
                                    <span className="text-[9px] font-black text-gray-400 uppercase tracking-widest bg-gray-100 px-1.5 py-0.5 rounded-full">
                                        {cat.item_count}
                                    </span>
                                </div>
                                <div className="flex gap-1">
                                    <button
                                        onClick={() => { setEditingName(cat.category || ''); setEditValue(cat.category || ''); }}
                                        className="admin-action-btn btn-icon--edit"
                                        data-help-id="categories-action-edit"
                                    ></button>
                                    <button
                                        onClick={async () => {
                                            const categoryName = cat.category || '';
                                            const confirmed = await confirmModal({
                                                title: 'Delete Category',
                                                message: `Delete "${categoryName}"?`,
                                                confirmText: 'Delete',
                                                confirmStyle: 'danger',
                                                icon: '⚠️',
                                                iconType: 'danger'
                                            });
                                            if (confirmed) deleteCategory(categoryName);
                                        }}
                                        className="admin-action-btn btn-icon--delete"
                                        data-help-id="categories-action-delete"
                                        type="button"
                                    ></button>
                                </div>
                            </>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
};
