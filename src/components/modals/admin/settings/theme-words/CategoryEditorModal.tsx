import React from 'react';
import { IThemeWordCategory as ICategory } from '../../../../../types/theming.js';

interface CategoryEditorModalProps {
    editingCategory: Partial<ICategory>;
    setEditingCategory: (cat: Partial<ICategory> | null) => void;
    isSaving: boolean;
    handleSaveCategory: (e: React.FormEvent) => void;
}

export const CategoryEditorModal: React.FC<CategoryEditorModalProps> = ({
    editingCategory,
    setEditingCategory,
    isSaving,
    handleSaveCategory
}) => {
    return (
        <div className="fixed inset-0 z-[110] flex items-center justify-center p-8 bg-slate-900/40 backdrop-blur-md animate-in fade-in duration-200">
            <div
                className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md overflow-hidden animate-in zoom-in-95 duration-300 border border-white"
                onClick={e => e.stopPropagation()}
            >
                <div className="px-10 py-8 border-b border-slate-50 flex items-center justify-between bg-white">
                    <h3 className="text-lg font-black text-slate-800 uppercase tracking-tight">{editingCategory.id ? 'Category Details' : 'New Category'}</h3>
                    <button
                        type="button"
                        onClick={() => setEditingCategory(null)}
                        className="admin-action-btn btn-icon--close"
                    />
                </div>
                <form onSubmit={handleSaveCategory} className="p-10 space-y-8 bg-slate-50/50">
                    <div className="space-y-3">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Name</label>
                        <input
                            type="text" required
                            value={editingCategory.name}
                            onChange={e => setEditingCategory({
                                ...editingCategory,
                                name: e.target.value,
                                slug: e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-')
                            })}
                            className="w-full px-5 py-4 bg-white border border-slate-100 rounded-2xl outline-none focus:ring-4 focus:ring-brand-primary/5 shadow-sm font-black text-slate-800 uppercase tracking-tight transition-all"
                            placeholder="e.g., Adventure"
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-3">
                            <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Slug</label>
                            <input
                                type="text" required
                                value={editingCategory.slug}
                                onChange={e => setEditingCategory({ ...editingCategory, slug: e.target.value })}
                                className="w-full px-5 py-4 bg-white border border-slate-100 rounded-2xl outline-none text-xs font-mono"
                            />
                        </div>
                        <div className="space-y-3">
                            <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest px-1">Sort Order</label>
                            <input
                                type="number" required
                                value={editingCategory.sort_order}
                                onChange={e => setEditingCategory({ ...editingCategory, sort_order: parseInt(e.target.value) })}
                                className="w-full px-5 py-4 bg-white border border-slate-100 rounded-2xl outline-none"
                            />
                        </div>
                    </div>

                    <label className="flex items-center gap-4 cursor-pointer p-4 bg-white border border-slate-100 rounded-2xl shadow-sm hover:border-brand-primary/20 transition-all select-none group">
                        <div className={`w-10 h-6 rounded-full relative transition-all ${editingCategory.is_active ? 'bg-brand-primary' : 'bg-slate-200'}`}>
                            <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${editingCategory.is_active ? 'left-5' : 'left-1'}`} />
                        </div>
                        <input
                            type="checkbox"
                            checked={editingCategory.is_active}
                            onChange={e => setEditingCategory({ ...editingCategory, is_active: e.target.checked })}
                            className="hidden"
                        />
                        <span className="text-[11px] font-black text-slate-800 uppercase tracking-widest">Active</span>
                    </label>

                    <div className="flex gap-4 pt-4">
                        <button
                            type="button"
                            onClick={() => setEditingCategory(null)}
                            className="btn-text-secondary flex-1 !py-4"
                            data-help-id="cancel-category-edit"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={isSaving || !editingCategory.name}
                            className="btn-text-primary flex-[2] !py-4 disabled:opacity-50"
                            data-help-id="save-category-btn"
                        >
                            {isSaving ? 'Saving...' : 'Save Category'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};
