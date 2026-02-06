import React from 'react';
import { CategoryTable } from '../../../categories/partials/CategoryTable.js';
import { AssignmentsPanel } from '../../../categories/partials/AssignmentsPanel.js';
import { ICategoriesHook } from '../../../../../types/room.js';

interface CategoriesTabProps {
    categoriesHook: ICategoriesHook;
    selectedRoom: string;
}

export const CategoriesTab: React.FC<CategoriesTabProps> = ({
    categoriesHook,
    selectedRoom
}) => {
    return (
        <div className="h-full flex flex-col min-h-0 overflow-hidden">
            <div className="p-6 flex-1 min-h-0">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 h-full">
                    {/* Category Management */}
                    <div className="flex flex-col min-h-0 h-full">
                        <div className="flex items-center justify-between mb-4 flex-shrink-0">
                            <h3 className="text-sm font-black text-slate-600 uppercase tracking-widest">Master Categories</h3>
                            <button
                                onClick={() => categoriesHook.refresh()}
                                className="admin-action-btn btn-icon--refresh"
                            ></button>
                        </div>

                        {/* Create New Category Form */}
                        <div className="mb-6 bg-slate-50 border border-slate-100 rounded-2xl p-6 shadow-sm flex-shrink-0">
                            <form onSubmit={async (e) => {
                                e.preventDefault();
                                const input = (e.target as HTMLFormElement).categoryName as HTMLInputElement;
                                const name = input.value.trim();
                                if (!name) return;
                                const res = await categoriesHook.createCategory(name);
                                if (res.success) {
                                    input.value = '';
                                }
                            }} className="flex gap-4">
                                <input
                                    type="text"
                                    name="categoryName"
                                    placeholder="e.g. Outerwear"
                                    className="flex-1 px-4 py-2 bg-white border border-slate-200 rounded-xl outline-none focus:ring-2 focus:ring-blue-200 font-bold text-slate-700 text-xs"
                                />
                                <button
                                    type="submit"
                                    className="px-6 py-2 bg-blue-500 text-white rounded-xl font-black uppercase tracking-widest text-[10px] hover:bg-blue-600 transition-all font-inter"
                                >
                                    Create Label
                                </button>
                            </form>
                        </div>

                        <div className="flex-1 min-h-0 overflow-y-auto">
                            <CategoryTable
                                categories={categoriesHook.categories}
                                onRename={categoriesHook.renameCategory}
                                onDelete={categoriesHook.deleteCategory}
                            />
                        </div>
                    </div>

                    {/* Room Category Assignments */}
                    <div className="flex flex-col min-h-0 h-full">
                        <div className="mb-4 flex-shrink-0">
                            <h3 className="text-sm font-black text-slate-600 uppercase tracking-widest">Active Assignments</h3>
                            <p className="text-[10px] text-slate-400 font-bold uppercase tracking-wider mt-1">Current Room Context</p>
                        </div>
                        <div className="flex-1 min-h-0 overflow-y-auto">
                            <AssignmentsPanel
                                categories={categoriesHook.categories}
                                assignments={categoriesHook.assignments}
                                onAdd={categoriesHook.addAssignment}
                                onDelete={categoriesHook.deleteAssignment}
                                onUpdate={categoriesHook.updateAssignment}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};
