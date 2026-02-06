import { useState, useCallback, useEffect } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import logger from '../../core/logger.js';
import { IRecentOrder, IInventoryStats } from '../../types/index.js';
import type {
    ISalesData,
    IPaymentData,
    IInventoryReportItem,
    IReportSummary,
    IReportsResponse
} from '../../types/dashboard.js';

// Re-export for backward compatibility
export type { ISalesData, IPaymentData, IInventoryReportItem, IReportSummary } from '../../types/dashboard.js';


export const useReports = (timeframe: number = 7) => {
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [salesData, setSalesData] = useState<ISalesData>({ labels: [], revenue: [], orders: [] });
    const [paymentData, setPaymentData] = useState<IPaymentData>({ paymentLabels: [], paymentCounts: [] });
    const [summary, setSummary] = useState<IReportSummary | null>(null);
    const [recentOrders, setRecentOrders] = useState<IRecentOrder[]>([]);
    const [inventoryStats, setInventoryStats] = useState<IInventoryStats | null>(null);

    const fetchData = useCallback(async () => {
        // 1. Try to hydrate from DOM first (Parity with PHP shell)
        const reportsDataEl = document.getElementById('reports-data');
        if (reportsDataEl) {
            try {
                const parsed = JSON.parse(reportsDataEl.textContent || '{}');
                if (parsed.labels) {
                    setSalesData({
                        labels: parsed.labels || [],
                        revenue: parsed.revenue || [],
                        orders: parsed.orders || []
                    });
                    setPaymentData({
                        paymentLabels: parsed.paymentLabels || [],
                        paymentCounts: parsed.paymentCounts || []
                    });
                    // Note: full summary and inventory stats might not be in this tag
                    // but we have the charts.
                }
            } catch (e) {
                logger.warn('[useReports] Failed to parse reports-data from shell', e);
            }
        }

        setIsLoading(true);
        setError(null);
        try {
            const days = timeframe;
            // Note: If this endpoint is missing, we might need to rely on hydration 
            // or create it. For now, we try to fetch but don't blow up if it fails 
            // if we have hydrated data.
            const res = await ApiClient.get<IReportsResponse>('/api/get_reports_data.php', { days }).catch(() => null);

            if (res && res.success) {
                setSalesData(res.sales || { labels: [], revenue: [], orders: [] });
                setPaymentData(res.payment || { paymentLabels: [], paymentCounts: [] });
                setSummary(res.summary || null);
                setRecentOrders(res.recentOrders || []);
                setInventoryStats(res.inventoryStats || null);
            } else if (!reportsDataEl) {
                setError('Failed to load report data');
            }
        } catch (err) {
            if (!reportsDataEl) {
                logger.error('[useReports] fetchData failed', err);
                setError('Unable to load reporting data');
            }
        } finally {
            setIsLoading(false);
        }
    }, [timeframe]);

    const fetchInventoryReport = useCallback(async () => {
        try {
            const res = await ApiClient.get<{ success: boolean; data: IInventoryReportItem[] }>('/api/get_inventory_report.php');
            return res.success ? res.data : [];
        } catch (err) {
            logger.error('[useReports] fetchInventoryReport failed', err);
            return [];
        }
    }, []);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    return {
        isLoading,
        error,
        salesData,
        paymentData,
        summary,
        recentOrders,
        inventoryStats,
        fetchData,
        fetchInventoryReport
    };
};
