import React from 'react';
import { useInventory, IInventoryItem } from '../../../hooks/admin/useInventory.js';
import { MainInventoryFilters } from './MainInventoryFilters.js';
import { InventoryTable } from './InventoryTable.js';
import { InventoryItemModal } from '../../modals/admin/inventory/InventoryItemModal.js';
import { useSearchParams } from 'react-router-dom';
import { useModalContext } from '../../../context/ModalContext.js';

export const InventoryManager: React.FC = () => {
    const {
        items,
        categories,
        isLoading,
        error,
        filters,
        setFilters,
        sort,
        setSort,
        deleteItem,
        updateCell,
        refresh
    } = useInventory();
    const { confirm } = useModalContext();

    const [searchParams, setSearchParams] = useSearchParams();
    const editSku = searchParams.get('edit') || searchParams.get('view') || '';
    const isAdding = searchParams.has('add');
    const modalMode = isAdding ? 'add' : (searchParams.has('edit') ? 'edit' : (searchParams.has('view') ? 'view' : '')) as 'edit' | 'view' | 'add' | '';

    const handleSort = (column: string) => {
        const direction = sort.column === column && sort.direction === 'asc' ? 'desc' : 'asc';
        setSort({ column, direction });
    };

    const handleUpdate = async (sku: string, data: Partial<IInventoryItem>) => {
        const key = Object.keys(data)[0] as keyof IInventoryItem;
        const value = Object.values(data)[0] as string | number | boolean | null;
        const res = await updateCell(sku, key, value);
        if (res.success) {
            refresh();
        } else {
            // Bubble up so the caller can show an error state/toast.
            throw new Error(res.error || 'Update failed');
        }
    };

    const handleView = (sku: string) => {
        setSearchParams(prev => {
            prev.set('view', sku);
            prev.delete('edit');
            return prev;
        });
    };

    const handleEdit = (sku: string) => {
        setSearchParams(prev => {
            prev.set('edit', sku);
            prev.delete('view');
            return prev;
        });
    };

    const handleDelete = async (sku: string) => {
        const confirmed = await confirm({
            title: 'Delete Item',
            message: `Are you sure you want to permanently delete item ${sku}?`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const res = await deleteItem(sku);
            if (res.success) {
                if (window.WFToast) window.WFToast.success(`Item ${sku} deleted.`);
            } else if ('code' in res && res.code === 'ERR_FOREIGN_KEY') {
                const shouldArchive = await confirm({
                    title: 'Cannot Delete Item',
                    message: `Item ${sku} is part of existing orders and cannot be deleted.`,
                    subtitle: 'Integrity Protection',
                    details: 'To preserve order history, this item must remain in the database.',
                    confirmText: 'Archive Instead',
                    confirmStyle: 'confirm',
                    iconKey: 'warning'
                });

                if (shouldArchive) {
                    const archiveRes = await updateCell(sku, 'is_archived', 1);
                    if (archiveRes.success) {
                        if (window.WFToast) window.WFToast.success(`Item ${sku} has been archived.`);
                        refresh();
                    } else {
                        if (window.WFToast) window.WFToast.error(archiveRes.error || 'Archive failed');
                    }
                }
            } else {
                if (window.WFToast) window.WFToast.error(res.error || 'Delete failed');
            }
        }
    };

    const handleAddNew = () => {
        setSearchParams(prev => {
            prev.set('add', '1');
            return prev;
        });
    };

    const handleCloseModal = () => {
        setSearchParams(prev => {
            prev.delete('view');
            prev.delete('edit');
            prev.delete('add');
            return prev;
        });
    };

    return (
        <div className="space-y-6 w-full flex-1 min-h-0 flex flex-col overflow-hidden">
            {error && (
                <div className="mx-4 p-4 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded-xl flex items-center gap-3">
                    <span className="text-xl">⚠️</span>
                    {error}
                </div>
            )}

            <div className="w-full flex-1 min-h-0 flex flex-col overflow-hidden">
                <MainInventoryFilters
                    filters={filters}
                    setFilters={setFilters}
                    categories={categories}
                    onRefresh={refresh}
                    onAddNew={handleAddNew}
                    isModal={false}
                />

                <div className="admin-table-section">
                    <InventoryTable
                        items={items}
                        sort={sort}
                        onSort={handleSort}
                        onView={handleView}
                        onEdit={handleEdit}
                        onDelete={handleDelete}
                        onUpdate={handleUpdate}
                        categories={categories}
                        isLoading={isLoading}
                    />
                </div>
            </div>

            {isLoading && items.length === 0 && (
                <div className="flex flex-col items-center justify-center p-24 text-gray-400 gap-4">
                    <span className="wf-emoji-loader text-6xl opacity-20">📦</span>
                    <p className="text-lg font-medium italic">Loading inventory...</p>
                </div>
            )}

            {/* Edit/View/Add Modal */}
            {(editSku || isAdding) && (
                <InventoryItemModal
                    sku={editSku}
                    mode={modalMode}
                    onClose={handleCloseModal}
                    onSaved={() => refresh()}
                    onEdit={() => handleEdit(editSku)}
                />
            )}
        </div>
    );
};
