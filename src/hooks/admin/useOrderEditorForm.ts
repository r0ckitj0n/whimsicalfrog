import { useState, useEffect, useCallback } from 'react';
import { IOrder, IOrderItem, IOrderNote } from '../../types/admin/orders.js';
import { useOrders } from '../../hooks/admin/useOrders.js';

interface UseOrderEditorFormProps {
    order_id: string | number;
    onSaved: () => void;
    onClose: () => void;
}

export const useOrderEditorForm = ({
    order_id,
    onSaved,
    onClose
}: UseOrderEditorFormProps) => {
    const { fetchOrderDetails, updateOrder } = useOrders();
    const [order, setOrder] = useState<IOrder | null>(null);
    const [originalOrder, setOriginalOrder] = useState<IOrder | null>(null);
    const [items, setItems] = useState<IOrderItem[]>([]);
    const [notes, setNotes] = useState<IOrderNote[]>([]);
    const [isSaving, setIsSaving] = useState(false);
    const [isDirty, setIsDirty] = useState(false);

    const loadData = useCallback(async () => {
        try {
            const res = await fetchOrderDetails(order_id);
            const resTyped = res as {
                success?: boolean | string;
                order?: IOrder;
                data?: { order?: IOrder; items?: IOrderItem[]; notes?: IOrderNote[] };
                items?: IOrderItem[];
                notes?: IOrderNote[]
            };

            const isSuccess = resTyped && (
                resTyped.success === true ||
                resTyped.success === 'true' ||
                (resTyped.order && resTyped.order.id)
            );

            if (isSuccess) {
                const orderData = resTyped.data?.order || resTyped.order;
                const itemsData = resTyped.data?.items || resTyped.items || [];
                const notesData = resTyped.data?.notes || resTyped.notes || [];

                if (orderData) {
                    const normalizedOrder = {
                        ...orderData,
                        shipping_cost: Number(orderData.shipping_cost) || 0,
                        tax_amount: Number(orderData.tax_amount) || 0,
                        discount_amount: Number(orderData.discount_amount) || 0,
                        coupon_code: orderData.coupon_code || '',
                        total_amount: Number(orderData.total_amount) || 0
                    };
                    setOrder(normalizedOrder);
                    setOriginalOrder(normalizedOrder);
                    setNotes(notesData);
                    setItems(itemsData.map((it) => ({
                        ...it,
                        price: Number(it.price) || 0,
                        quantity: Number(it.quantity) || 0
                    })));
                }
            }
        } catch (err) {
            console.error('[useOrderEditorForm] Exception during load:', err);
        }
    }, [order_id, fetchOrderDetails]);

    useEffect(() => {
        loadData();
    }, [loadData]);

    const handleOrderChange = (newOrder: IOrder) => {
        setOrder(newOrder);
        setIsDirty(true);
    };

    const handleAddNote = async (type: 'fulfillment' | 'payment', text: string) => {
        if (!order) return;
        const optimisticId = Date.now();
        const newNote: IOrderNote = {
            id: optimisticId,
            order_id: Number(order.id),
            note_type: type,
            note_text: text,
            author_username: 'Admin',
            created_at: new Date().toISOString()
        };

        setNotes([newNote, ...(notes || [])]);

        try {
            const res = await updateOrder({
                order_id: order.id,
                [`new_${type}_note`]: text,
                author_username: 'Admin'
            });
            if (!res.success) {
                setNotes(prev => prev.filter(n => n.id !== optimisticId));
                if (window.WFToast) window.WFToast.error('Failed to add note');
            }
        } catch (err) {
            setNotes(prev => prev.filter(n => n.id !== optimisticId));
            if (window.WFToast) window.WFToast.error('Network error while adding note');
        }
    };

    const updateItemQty = (sku: string, qty: number) => {
        setItems(prev => prev.map(it =>
            it.sku === sku ? { ...it, quantity: Math.max(0, qty) } : it
        ).filter(it => it.quantity > 0));
        setIsDirty(true);
    };

    const removeItem = (sku: string) => {
        setItems(prev => prev.filter(it => it.sku !== sku));
        setIsDirty(true);
    };

    const handleSave = async (e?: React.FormEvent) => {
        if (e) e.preventDefault();
        if (!order) return;

        setIsSaving(true);
        const payload = {
            order_id: order.id,
            status: order.status,
            tracking_number: order.tracking_number,
            payment_method: order.payment_method,
            shipping_method: order.shipping_method,
            payment_status: order.payment_status,
            payment_at: order.payment_at,
            address_line_1: order.address_line_1,
            address_line_2: order.address_line_2,
            city: order.city,
            state: order.state,
            zip_code: order.zip_code,
            shipping_cost: order.shipping_cost,
            tax_amount: order.tax_amount,
            discount_amount: order.discount_amount,
            coupon_code: order.coupon_code,
            items: items.map(it => ({ sku: it.sku, quantity: it.quantity }))
        };

        try {
            const res = await updateOrder(payload);
            if (res.success) {
                setIsDirty(false);
                onSaved();
                onClose();
                if (window.WFToast) window.WFToast.success('Order updated successfully');
            } else {
                if (window.WFToast) window.WFToast.error(res.error || 'Failed to update order');
            }
        } catch (err) {
            if (window.WFToast) window.WFToast.error('Network error during save');
        } finally {
            setIsSaving(false);
        }
    };

    return {
        order,
        items,
        notes,
        isSaving,
        isDirty,
        handleOrderChange,
        handleAddNote,
        updateItemQty,
        removeItem,
        handleSave,
        refresh: loadData
    };
};
