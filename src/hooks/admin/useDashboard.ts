import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { IOrder, IOrdersResponse } from '../../types/admin/orders.js';
import { IInventoryItem, IInventoryResponse } from './useInventory.js';
import { IRecentOrder } from '../../types/index.js';
import {
    ILowStockItem,
    IDashboardSection,
    IDashboardResponse,
    IDashboardMetrics
} from '../../types/dashboard.js';

// Re-export for backward compatibility
export type { ILowStockItem, IDashboardSection } from '../../types/dashboard.js';

export const useDashboard = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [metrics, setMetrics] = useState<IDashboardMetrics | null>(null);
    const [recentOrders, setRecentOrders] = useState<IRecentOrder[]>([]);
    const [lowStockItems, setLowStockItems] = useState<ILowStockItem[]>([]);
    const [config, setConfig] = useState<IDashboardSection[]>([]);
    const [error, setError] = useState<string | null>(null);

    const fetchDashboardData = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const timestamp = Date.now();
            const [metricsRes, ordersRes, stockRes, configRes] = await Promise.all([
                ApiClient.get<IDashboardMetrics>(`/api/get_dashboard_metrics.php?_t=${timestamp}`),
                ApiClient.get<IOrdersResponse>(`/api/orders.php?limit=5&_t=${timestamp}`),
                ApiClient.get<IInventoryResponse>(`/api/inventory.php?filter=low_stock&_t=${timestamp}`),
                ApiClient.get<{ success: boolean; data?: { sections: IDashboardSection[] } }>(`/api/dashboard_sections.php?action=get_sections&_t=${timestamp}`)
            ]);

            if (metricsRes) {
                const data = 'data' in metricsRes && metricsRes.data ? metricsRes.data as IDashboardMetrics : metricsRes;
                setMetrics(data);
            }

            const ordersData = ordersRes?.orders || ordersRes?.data?.orders;
            if (ordersData) setRecentOrders(ordersData as unknown as IRecentOrder[]);

            const stockData = stockRes?.data || stockRes;
            if (stockData && Array.isArray(stockData)) setLowStockItems(stockData as unknown as ILowStockItem[]);

            interface IDashboardSectionRaw {
                section_key: string;
                display_title?: string;
                display_description?: string;
                width_class?: string;
                is_active?: string | number;
                display_order?: string | number;
            }
            const configData = (configRes?.data?.sections || []) as unknown as IDashboardSectionRaw[];
            const mappedSections: IDashboardSection[] = configData.map((s: IDashboardSectionRaw) => ({
                key: s.section_key,
                title: s.display_title || s.section_key,
                description: s.display_description,
                width: (s.width_class?.replace('-width', '') || 'half') as 'full' | 'half' | 'third',
                is_visible: !!parseInt(String(s.is_active || 1)),
                order: parseInt(String(s.display_order || 0))
            }));

            if (mappedSections.length > 0) setConfig(mappedSections);

        } catch (err) {
            logger.error('[useDashboard] Failed to fetch dashboard data', err);
            setError('Unable to load dashboard summary');
        } finally {
            setIsLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchDashboardData();
    }, [fetchDashboardData]);

    const updateSectionWidth = async (key: string, width: string) => {
        try {
            await ApiClient.post('/api/dashboard_sections.php', {
                action: 'update_width',
                section_key: key,
                width_class: width
            });
            await fetchDashboardData();
        } catch (err) {
            logger.error('[useDashboard] Failed to update section width', err);
        }
    };

    return {
        isLoading,
        metrics,
        recentOrders,
        lowStockItems,
        config,
        error,
        refresh: fetchDashboardData,
        updateSectionWidth
    };
};
