import React, { useState, useEffect } from 'react';
import { useOrders } from '../../../hooks/admin/useOrders.js';
import { useModalContext } from '../../../context/ModalContext.js';
import { IOrder } from '../../../types/admin/orders.js';
import { OrderFilters } from './OrderFilters.js';
import { OrdersTable } from './OrdersTable.js';
import { OrderEditorModal } from '../../modals/admin/orders/OrderEditorModal.js';

export const OrdersManager: React.FC = () => {
    const {
        isLoading,
        orders,
        status_options,
        payment_method_options,
        shipping_method_options,
        payment_status_options,
        error,
        fetchOrders,
        fetchDropdownOptions,
        updateOrder,
        deleteOrder
    } = useOrders();

    const { confirm: confirmModal } = useModalContext();

    const [filters, setFilters] = useState<Record<string, string>>({});
    const [modalState, setModalState] = useState<{ id: string | number | null; mode: 'view' | 'edit' }>({
        id: null,
        mode: 'view'
    });

    // Detect initial order from URL if present
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const viewId = params.get('view');
        const editId = params.get('edit');

        if (editId) {
            setModalState({ id: editId, mode: 'edit' });
        } else if (viewId) {
            setModalState({ id: viewId, mode: 'view' });
        }

        // Apply filters from URL
        const urlFilters: Record<string, string> = {};
        params.forEach((val, key) => {
            if (key.startsWith('filter_')) {
                urlFilters[key] = val;
            }
        });
        if (Object.keys(urlFilters).length > 0) {
            setFilters(urlFilters);
        }
    }, []);

    useEffect(() => {
        fetchOrders(filters);
    }, [filters, fetchOrders]);

    useEffect(() => {
        fetchDropdownOptions();
    }, [fetchDropdownOptions]);

    const handleFilterChange = (newFilters: Record<string, string>) => {
        setFilters(newFilters);

        // Update URL to reflect filters without page reload
        const url = new URL(window.location.href);
        // Clear existing filters
        Array.from(url.searchParams.keys()).forEach(key => {
            if (key.startsWith('filter_')) url.searchParams.delete(key);
        });
        // Set new filters
        Object.entries(newFilters).forEach(([k, v]) => {
            if (v) url.searchParams.set(k, v);
        });
        window.history.replaceState(null, '', url);
    };

    const handleView = (id: string | number) => {
        setModalState({ id, mode: 'view' });
        const url = new URL(window.location.href);
        url.searchParams.delete('edit');
        url.searchParams.set('view', String(id));
        window.history.pushState(null, '', url);
    };

    const handleEdit = (id: string | number) => {
        setModalState({ id, mode: 'edit' });
        const url = new URL(window.location.href);
        url.searchParams.delete('view');
        url.searchParams.set('edit', String(id));
        window.history.pushState(null, '', url);
    };

    const handlePrint = (id: string | number) => {
        window.open(`/receipt?order_id=${id}&bare=1`, '_blank');
    };

    const handleDelete = async (id: string | number) => {
        const confirmed = await confirmModal({
            title: 'Delete Order',
            message: `Permanently delete order #${id}? This cannot be undone.`,
            confirmText: 'Delete',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const res = await deleteOrder(id);
            if (res.success) {
                fetchOrders(filters);
                if (window.WFToast) window.WFToast.success(`Order #${id} deleted successfully`);
            } else {
                const msg = res.error || 'Failed to delete order';
                if (window.WFToast) {
                    window.WFToast.error(msg);
                }
            }
        }
    };

    const handleCloseModal = () => {
        setModalState({ id: null, mode: 'view' });
        const url = new URL(window.location.href);
        url.searchParams.delete('view');
        url.searchParams.delete('edit');
        window.history.pushState(null, '', url);
    };

    const handleSaved = () => {
        fetchOrders(filters);
    };

    const handleUpdate = async (id: string | number, data: Partial<IOrder>) => {
        try {
            const res = await updateOrder({ order_id: id, ...data });

            if (res.success) {
                fetchOrders(filters);
                if (window.WFToast) window.WFToast.success(`Order #${id} updated successfully`);
            } else {
                const msg = res.error || 'Failed to update order';
                console.error(`%c[OrdersManager] Update FAILURE for #${id}: ${msg}`, 'color: red; font-weight: bold', res);
                if (window.WFToast) {
                    window.WFToast.error(msg);
                }
            }
        } catch (err) {
            console.error(`%c[OrdersManager] EXCEPTION for #${id}:`, 'background: red; color: white', err);
            const errorMsg = err instanceof Error ? err.message : 'Unknown error';
            if (window.WFToast) {
                window.WFToast.error(`Critical Error: ${errorMsg}`);
            }
        }
    };

    return (
        <div className="p-0 space-y-8 admin-actions-icons w-full !max-w-none flex-1 min-h-0 flex flex-col overflow-hidden">
            {error && (
                <div className="mx-4 p-4 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded-xl flex items-center gap-3">
                    <span className="text-xl">⚠️</span>
                    {error}
                </div>
            )}

            <div className="w-full flex-1 min-h-0 flex flex-col overflow-hidden">
                <OrderFilters
                    filters={filters}
                    onFilterChange={handleFilterChange}
                    dropdownOptions={{
                        status: status_options,
                        payment_method: payment_method_options,
                        shipping_method: shipping_method_options,
                        payment_status: payment_status_options
                    }}
                    onRefresh={() => fetchOrders(filters)}
                    isLoading={isLoading}
                />

                <div className="admin-table-section">
                    <OrdersTable
                        orders={orders}
                        onView={handleView}
                        onEdit={handleEdit}
                        onPrint={handlePrint}
                        onDelete={handleDelete}
                        onUpdate={handleUpdate}
                        options={{
                            status: status_options,
                            payment_status: payment_status_options,
                            payment_method: payment_method_options,
                            shipping_method: shipping_method_options
                        }}
                    />
                </div>
            </div>

            {isLoading && orders.length === 0 && (
                <div className="flex flex-col items-center justify-center p-24 text-gray-400 gap-4">
                    <span className="wf-emoji-loader text-6xl opacity-20">📋</span>
                    <p className="text-lg font-medium italic">Scanning order logs...</p>
                </div>
            )}

            {modalState.id && (
                <OrderEditorModal
                    order_id={modalState.id}
                    mode={modalState.mode}
                    onClose={handleCloseModal}
                    onSaved={handleSaved}
                />
            )}
        </div>
    );
};
