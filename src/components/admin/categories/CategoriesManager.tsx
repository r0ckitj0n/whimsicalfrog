import React, { useState } from 'react';
import { useCategories } from '../../../hooks/admin/useCategories.js';
import { useModalContext } from '../../../context/ModalContext.js';
import { CategoryTable } from './partials/CategoryTable.js';
import { OverviewPanel } from './partials/OverviewPanel.js';
import { AssignmentsPanel } from './partials/AssignmentsPanel.js';

interface CategoriesManagerProps {
    onClose?: () => void;
    title?: string;
}

export const CategoriesManager: React.FC<CategoriesManagerProps> = ({ onClose, title }) => {
    const {
        categories,
        assignments,
        overview,
        isLoading,
        error,
        createCategory,
        renameCategory,
        deleteCategory,
        addAssignment,
        deleteAssignment,
        updateAssignment,
        refresh
    } = useCategories();

    const { confirm: confirmModal } = useModalContext();

    const [activeTab, setActiveTab] = useState<'overview' | 'categories' | 'assignments'>('overview');
    const [newCategoryName, setNewCategoryName] = useState('');
    const [isCreating, setIsCreating] = useState(false);

    const handleCreate = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!newCategoryName.trim()) return;

        setIsCreating(true);
        const res = await createCategory(newCategoryName.trim());
        if (res.success) {
            setNewCategoryName('');
            if (window.WFToast) window.WFToast.success('Category created successfully');
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Failed to create category');
        }
        setIsCreating(false);
    };

    const handleDelete = async (name: string) => {
        const confirmed = await confirmModal({
            title: 'Delete Category',
            message: `Delete category "${name}"? This will remove it from all items and update SKUs.`,
            confirmText: 'Delete',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const res = await deleteCategory(name);
            if (res.success) {
                if (window.WFToast) window.WFToast.success(`Category "${name}" deleted`);
            } else {
                if (window.WFToast) window.WFToast.error(res.error || 'Failed to delete category');
            }
        }
    };

    const tabs = [
        { id: 'overview', label: 'Overview' },
        { id: 'categories', label: 'Manage Labels' },
        { id: 'assignments', label: 'Item Mapping' }
    ];

    return (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[98vw] h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header border-b border-gray-100 bg-white sticky top-0 z-20 px-6 py-4 flex items-start justify-between">
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center gap-4">
                            <div className="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-xl">
                                🏷️
                            </div>
                            <div>
                                <h2 className="text-xl font-black text-slate-800 tracking-tight">{title || 'Category Management'}</h2>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Organize and Map Store Inventory</p>
                            </div>
                        </div>

                        <div className="wf-tabs bg-slate-50/50 rounded-xl p-1 border border-slate-100 flex items-center gap-2 self-start">
                            {tabs.map(tab => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id as 'overview' | 'categories' | 'assignments')}
                                    className={`wf-tab ${activeTab === tab.id ? 'is-active' : ''}`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={refresh}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="categories-refresh"
                        />
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="categories-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-hidden p-0 flex flex-col">
                    <div className="p-10 overflow-y-auto flex-1">
                        {error && (
                            <div className="p-4 mb-6 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3 animate-in fade-in">
                                <span className="text-lg">⚠️</span>
                                {error}
                            </div>
                        )}

                        <div className="animate-in fade-in slide-in-from-bottom-2 duration-300">
                            {activeTab === 'overview' && <OverviewPanel overview={overview} />}

                            {activeTab === 'categories' && (
                                <div className="grid grid-cols-1 lg:grid-cols-3 gap-10">
                                    <div className="lg:col-span-1">
                                        <div className="bg-white border border-slate-100 rounded-[2rem] p-8 shadow-sm space-y-8 sticky top-0">
                                            <div className="space-y-2">
                                                <h3 className="font-black text-slate-800 text-xs uppercase tracking-widest">New Category</h3>
                                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Create a unique taxonomy grouping</p>
                                            </div>

                                            <form onSubmit={handleCreate} className="space-y-6">
                                                <div className="space-y-3">
                                                    <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest px-1">Label Name</label>
                                                    <input
                                                        type="text"
                                                        value={newCategoryName}
                                                        onChange={(e) => setNewCategoryName(e.target.value)}
                                                        placeholder="e.g. Outerwear"
                                                        className="w-full px-5 py-4 bg-slate-50 border border-slate-100 rounded-2xl outline-none focus:ring-4 focus:ring-[var(--brand-primary)]/5 shadow-inner font-bold text-slate-700 transition-all focus:bg-white"
                                                        disabled={isCreating}
                                                    />
                                                </div>
                                                <button
                                                    type="submit"
                                                    disabled={isCreating || !newCategoryName.trim()}
                                                    className="w-full py-4 bg-[var(--brand-primary)] text-white rounded-2xl font-black uppercase tracking-widest text-[11px] hover:brightness-110 transition-all shadow-lg shadow-[var(--brand-primary)]/10 disabled:opacity-50"
                                                    data-help-id="categories-create-label"
                                                >
                                                    {isCreating ? 'Creating...' : 'Create Label'}
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <div className="lg:col-span-2">
                                        <CategoryTable
                                            categories={categories}
                                            onRename={renameCategory}
                                            onDelete={handleDelete}
                                        />
                                    </div>
                                </div>
                            )}

                            {activeTab === 'assignments' && (
                                <AssignmentsPanel
                                    assignments={assignments}
                                    categories={categories}
                                    onAdd={addAssignment}
                                    onDelete={deleteAssignment}
                                    onUpdate={updateAssignment}
                                />
                            )}
                        </div>

                        {isLoading && categories.length === 0 && (
                            <div className="flex flex-col items-center justify-center p-24 text-slate-300 gap-6">
                                <span className="text-6xl animate-bounce">🏷️</span>
                                <p className="text-[11px] font-black uppercase tracking-[0.2em] italic">Building taxonomy graph...</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};
