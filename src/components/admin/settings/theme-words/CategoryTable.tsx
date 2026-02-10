import React from 'react';
import { IThemeWordCategory as ICategory } from '../../../../types/theming.js';
import { useModalContext } from '../../../../context/ModalContext.js';

interface CategoryTableProps {
    sortedCategories: ICategory[];
    inlineEditingCategoryId: number | null;
    tempValue: string;
    setTempValue: (val: string) => void;
    handleInlineCategoryBlur: (cat: ICategory) => void;
    startInlineCategoryEdit: (cat: ICategory) => void;
    setEditingCategory: (cat: ICategory) => void;
    deleteCategory: (id: number) => Promise<boolean>;
    toggleCategoryActive: (cat: ICategory) => Promise<void>;
}

export const CategoryTable: React.FC<CategoryTableProps> = ({
    sortedCategories,
    inlineEditingCategoryId,
    tempValue,
    setTempValue,
    handleInlineCategoryBlur,
    startInlineCategoryEdit,
    setEditingCategory,
    deleteCategory,
    toggleCategoryActive
}) => {
    const { confirm: themedConfirm } = useModalContext();

    return (
        <div className="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden animate-in fade-in slide-in-from-bottom-2 duration-300">
            <table className="w-full text-left">
                <thead>
                    <tr className="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                        <th className="px-6 py-4">Category Name</th>
                        <th className="px-6 py-4">Slug</th>
                        <th className="px-6 py-4">Display Order</th>
                        <th className="px-6 py-4 text-center">Active</th>
                        <th className="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-50 font-bold text-sm text-slate-700">
                    {sortedCategories.map(cat => (
                        <tr key={cat.id} className="group hover:bg-slate-50 transition-all">
                            <td className="px-6 py-4">
                                {inlineEditingCategoryId === cat.id ? (
                                    <input
                                        autoFocus
                                        className="w-full bg-white border border-brand-primary/20 px-3 py-1.5 rounded-lg outline-none ring-2 ring-brand-primary/10 font-bold"
                                        value={tempValue}
                                        onChange={e => setTempValue(e.target.value)}
                                        onBlur={() => handleInlineCategoryBlur(cat)}
                                        onKeyDown={e => e.key === 'Enter' && handleInlineCategoryBlur(cat)}
                                    />
                                ) : (
                                    <span
                                        className="cursor-pointer hover:text-brand-primary flex items-center gap-2 group/text"
                                        onClick={() => startInlineCategoryEdit(cat)}
                                        data-help-id="edit-category-inline"
                                    >
                                        {cat.name}
                                        <span className="opacity-0 group-hover/text:opacity-40 text-[10px]">✎</span>
                                    </span>
                                )}
                            </td>
                            <td className="px-6 py-4 text-[10px] text-slate-400 font-mono uppercase">{cat.slug}</td>
                            <td className="px-6 py-4">{cat.sort_order}</td>
                            <td className="px-6 py-4 text-center">
                                <label className="relative inline-flex items-center cursor-pointer" data-help-id="theme-word-category-active-toggle">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(cat.is_active)}
                                        onChange={() => { void toggleCategoryActive(cat); }}
                                        className="sr-only peer"
                                    />
                                    <div className={`w-9 h-5 rounded-full peer-focus:ring-2 peer-focus:ring-blue-200 transition-colors ${cat.is_active ? 'bg-emerald-500' : 'bg-slate-300'} peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-transform after:shadow-sm`}></div>
                                </label>
                            </td>
                            <td className="px-6 py-4 text-right">
                                <div className="flex justify-end gap-1">
                                    <button
                                        type="button"
                                        onClick={() => setEditingCategory(cat)}
                                        className="admin-action-btn btn-icon--edit scale-75"
                                        data-help-id="edit-category-detail"
                                    />
                                    <button
                                        type="button"
                                        onClick={async () => {
                                            const confirmed = await themedConfirm({
                                                title: 'Delete Category',
                                                message: `Delete category "${cat.name}"? This will not delete words, but they will become uncategorized.`,
                                                confirmText: 'Delete Now',
                                                confirmStyle: 'danger',
                                                iconKey: 'delete'
                                            });

                                            if (confirmed) {
                                                const success = await deleteCategory(cat.id);
                                                if (success && window.WFToast) window.WFToast.success('Category deleted');
                                            }
                                        }}
                                        className="admin-action-btn btn-icon--delete scale-75"
                                        data-help-id="delete-category"
                                    />
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};
