import { useState, useEffect, useCallback } from 'react';
import { useOrders } from './useOrders.js';

export const useOrderFulfillment = () => {
    const {
        orders,
        isLoading,
        fetchOrders,
        updateOrder,
        status_options,
        payment_status_options,
        shipping_method_options,
        payment_method_options
    } = useOrders();

    const [filters, setFilters] = useState<Record<string, string>>({});
    const [showFilters, setShowFilters] = useState(false);
    const [editingCell, setEditingCell] = useState<{ id: string | number, field: string } | null>(null);
    const [editValue, setEditValue] = useState<string>('');
    const [isSaving, setIsSaving] = useState(false);

    useEffect(() => {
        fetchOrders(filters);
    }, [filters, fetchOrders]);

    const handleCellClick = useCallback((orderId: string | number, field: string, currentValue: string) => {
        setEditingCell({ id: orderId, field });
        setEditValue(currentValue || '');
    }, []);

    const handleEditCancel = useCallback(() => {
        setEditingCell(null);
        setEditValue('');
    }, []);

    const handleEditSave = useCallback(async (newValue?: string) => {
        if (!editingCell) return;

        const finalValue = newValue !== undefined ? newValue : editValue;

        const payload: { order_id: string | number;[key: string]: string | number | undefined } = {
            order_id: editingCell.id,
            [editingCell.field]: finalValue
        };

        if (editingCell.field === 'payment_status' && finalValue === 'Paid') {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            payload.payment_at = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
        }

        setIsSaving(true);
        try {
            const res = await updateOrder(payload);
            if (res.success) {
                fetchOrders(filters);
                handleEditCancel();
            } else {
                if (window.WFToast) {
                    window.WFToast.error(`Failed to update: ${res.error || 'Unknown error'}`);
                } else {
                    alert(`Failed to update: ${res.error || 'Unknown error'}`);
                }
                handleEditCancel();
            }
        } catch (err) {
            console.error('Save failed', err);
            handleEditCancel();
        } finally {
            setIsSaving(false);
        }
    }, [editingCell, editValue, updateOrder, fetchOrders, filters, handleEditCancel]);

    const handleFilterChange = useCallback((e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        const { name, value } = e.target;
        setFilters(prev => ({ ...prev, [name]: value }));
    }, []);

    const handleClearFilters = useCallback(() => {
        const clearedFilters = {};
        setFilters(clearedFilters);
        fetchOrders(clearedFilters);
    }, [fetchOrders]);

    const toggleFilters = useCallback(() => setShowFilters(prev => !prev), []);

    return {
        orders,
        isLoading,
        filters,
        showFilters,
        editingCell,
        editValue,
        setEditValue,
        isSaving,
        status_options,
        payment_status_options,
        shipping_method_options,
        payment_method_options,
        handleCellClick,
        handleEditCancel,
        handleEditSave,
        handleFilterChange,
        handleClearFilters,
        toggleFilters,
        fetchOrders
    };
};
