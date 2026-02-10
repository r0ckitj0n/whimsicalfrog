import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import { IOrder, IOrderItem, IOrdersResponse } from '../../types/admin/orders.js';
import logger from '../../core/logger.js';

export const useOrders = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [orders, setOrders] = useState<IOrder[]>([]);
    const [status_options, setStatusOptions] = useState<string[]>([]);
    const [payment_method_options, setPaymentMethodOptions] = useState<string[]>([]);
    const [shipping_method_options, setShippingMethodOptions] = useState<string[]>([]);
    const [payment_status_options, setPaymentStatusOptions] = useState<string[]>([]);
    const [error, setError] = useState<string | null>(null);

    const normalizeOrdersResponse = (res: IOrdersResponse | (IOrdersResponse & { data?: IOrdersResponse['data'] })) => {
        const payload = (res as unknown as { data?: IOrdersResponse['data'] })?.data;
        const effective = payload && Array.isArray(payload.orders) ? payload : res;
        return {
            orders: effective.orders || [],
            status_options: effective.status_options || [],
            payment_method_options: effective.payment_method_options || [],
            shipping_method_options: effective.shipping_method_options || [],
            payment_status_options: effective.payment_status_options || []
        };
    };

    const fetchOrders = useCallback(async (filters: Record<string, string> = {}) => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get<IOrdersResponse>('/api/orders.php', filters);

            const normalized = normalizeOrdersResponse(res);

            if (normalized.orders.length === 0 && Object.values(filters).some(Boolean)) {
                const unfilteredRes = await ApiClient.get<IOrdersResponse>('/api/orders.php');
                const unfiltered = normalizeOrdersResponse(unfilteredRes);
                setOrders(unfiltered.orders);
                setStatusOptions(unfiltered.status_options);
                setPaymentMethodOptions(unfiltered.payment_method_options);
                setShippingMethodOptions(unfiltered.shipping_method_options);
                setPaymentStatusOptions(unfiltered.payment_status_options);
                setError('No orders matched active filters. Showing all orders.');
                return;
            }

            setOrders(normalized.orders);
            setStatusOptions(normalized.status_options);
            setPaymentMethodOptions(normalized.payment_method_options);
            setShippingMethodOptions(normalized.shipping_method_options);
            setPaymentStatusOptions(normalized.payment_status_options);
        } catch (err) {
            logger.error('[useOrders] fetchOrders failed', err);
            setError('Unable to load orders list');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchDropdownOptions = useCallback(async () => {
        try {
            const res = await ApiClient.get<IOrdersResponse>('/api/orders.php');
            const normalized = normalizeOrdersResponse(res);
            setStatusOptions(normalized.status_options);
            setPaymentMethodOptions(normalized.payment_method_options);
            setShippingMethodOptions(normalized.shipping_method_options);
            setPaymentStatusOptions(normalized.payment_status_options);
        } catch (err) {
            logger.error('[useOrders] fetchDropdownOptions failed', err);
        }
    }, []);

    const fetchOrderDetails = useCallback(async (id: string | number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.get('/api/get_order.php', { id }) as { success: boolean; order: IOrder; items: IOrderItem[] };
            return res;
        } catch (err) {
            logger.error('[useOrders] fetchOrderDetails failed', err);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const updateOrder = async (payload: { order_id: string | number; [key: string]: unknown }) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post<{ success: boolean; order_id?: string; error?: string }>('/api/update_order.php', payload);
            return res;
        } catch (err) {
            logger.error('[useOrders] updateOrder failed', err);
            return { success: false, error: err instanceof Error ? err.message : 'Unknown error' };
        } finally {
            setIsLoading(false);
        }
    };

    const deleteOrder = async (order_id: string | number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.delete(`/api/delete_order.php?order_id=${order_id}`) as { success: boolean; message?: string; error?: string };
            return res;
        } catch (err) {
            logger.error('[useOrders] deleteOrder failed', err);
            return { success: false, error: err instanceof Error ? err.message : 'Unknown error' };
        } finally {
            setIsLoading(false);
        }
    };

    return {
        isLoading,
        orders,
        status_options,
        payment_method_options,
        shipping_method_options,
        payment_status_options,
        error,
        fetchOrders,
        fetchDropdownOptions,
        fetchOrderDetails,
        updateOrder,
        deleteOrder
    };
};
