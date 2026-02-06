import { useRef, useEffect, useCallback } from 'react';
import { Chart, ChartOptions, ChartEvent, ActiveElement } from 'chart.js';
import { IMarketingAnalyticsData } from './useMarketingAnalytics.js';
import { buildAdminUrl } from '../../core/admin-url-builder.js';
import {
    getCommonOptions,
    getDonutOptions,
    createSalesChartConfig,
    createDonutChartConfig,
    createBarChartConfig
} from './marketing/chartConfigs.js';

export const useMarketingCharts = (data: IMarketingAnalyticsData | null) => {
    const salesChartRef = useRef<HTMLCanvasElement>(null);
    const paymentChartRef = useRef<HTMLCanvasElement>(null);
    const categoriesChartRef = useRef<HTMLCanvasElement>(null);
    const statusChartRef = useRef<HTMLCanvasElement>(null);
    const newReturningChartRef = useRef<HTMLCanvasElement>(null);
    const shippingChartRef = useRef<HTMLCanvasElement>(null);
    const aovTrendChartRef = useRef<HTMLCanvasElement>(null);

    const chartInstances = useRef<Record<string, Chart>>({});

    const getClickedLabel = useCallback((chart: Chart, evt: ChartEvent) => {
        try {
            const points = chart.getElementsAtEventForMode(evt as unknown as Event, 'nearest', { intersect: true }, true);
            if (points && points.length) {
                const { index } = points[0];
                return chart.data.labels?.[index] as string;
            }
        } catch { /* Chart element lookup failed */ }
        return null;
    }, []);

    const goToOrdersWithQuery = useCallback((q: Record<string, string>) => {
        try {
            const url = new URL(buildAdminUrl('orders'), window.location.origin);
            Object.entries(q || {}).forEach(([k, v]) => {
                if (v != null && v !== '') url.searchParams.set(k, v);
            });
            window.location.href = url.toString();
        } catch { /* URL navigation failed */ }
    }, []);

    useEffect(() => {
        if (!data) return;

        // Cleanup previous charts
        Object.values(chartInstances.current).forEach(chart => chart?.destroy());

        // 1. Sales Trend
        if (salesChartRef.current && data?.sales) {
            chartInstances.current.sales = new Chart(
                salesChartRef.current,
                createSalesChartConfig(data.sales, getCommonOptions((evt, _els, chart) => {
                    const label = getClickedLabel(chart, evt);
                    if (label) goToOrdersWithQuery({ filter_created_at: label });
                }))
            );
        }


        // 3. Payment Methods
        if (paymentChartRef.current && data?.payment_methods?.labels && Array.isArray(data.payment_methods.labels)) {
            chartInstances.current.payment = new Chart(
                paymentChartRef.current,
                createDonutChartConfig(
                    'doughnut',
                    data.payment_methods.labels,
                    data.payment_methods.values || [],
                    ['#3b82f6', '#10b981', '#f59e0b', '#6366f1', '#ec4899'],
                    getDonutOptions((evt, _els, chart) => {
                        const label = getClickedLabel(chart, evt);
                        if (label) goToOrdersWithQuery({ filter_payment_method: label });
                    })
                )
            );
        }

        // 4. Top Categories
        if (categoriesChartRef.current && data?.top_categories?.labels && Array.isArray(data.top_categories.labels)) {
            chartInstances.current.categories = new Chart(
                categoriesChartRef.current,
                createBarChartConfig(
                    data.top_categories.labels,
                    data.top_categories.values || [],
                    getCommonOptions((evt, _els, chart) => {
                        const label = getClickedLabel(chart, evt);
                        if (label) goToOrdersWithQuery({ filter_items: label });
                    })
                )
            );
        }

        // 5. Order Status
        if (statusChartRef.current && data?.status?.labels && Array.isArray(data.status.labels)) {
            chartInstances.current.status = new Chart(
                statusChartRef.current,
                createDonutChartConfig(
                    'doughnut',
                    data.status.labels,
                    data.status.values || [],
                    ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#6b7280'],
                    getDonutOptions((evt, _els, chart) => {
                        const label = getClickedLabel(chart, evt);
                        if (label) goToOrdersWithQuery({ filter_status: label });
                    })
                )
            );
        }

        // 6. New vs Returning
        if (newReturningChartRef.current && data?.new_returning?.labels && Array.isArray(data.new_returning.labels)) {
            chartInstances.current.newReturning = new Chart(
                newReturningChartRef.current,
                createDonutChartConfig(
                    'pie',
                    data.new_returning.labels,
                    data.new_returning.values || [],
                    ['#3b82f6', '#6366f1'],
                    getDonutOptions()
                )
            );
        }

        // 7. Shipping Methods
        if (shippingChartRef.current && data?.shipping_methods?.labels && Array.isArray(data.shipping_methods.labels)) {
            chartInstances.current.shipping = new Chart(
                shippingChartRef.current,
                createDonutChartConfig(
                    'doughnut',
                    data.shipping_methods.labels,
                    data.shipping_methods.values || [],
                    ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e'],
                    getDonutOptions((evt, _els, chart) => {
                        const label = getClickedLabel(chart, evt);
                        if (label) goToOrdersWithQuery({ filter_shipping_method: label });
                    })
                )
            );
        }

        // 8. AOV Trend
        if (aovTrendChartRef.current && data?.aov_trend?.labels && Array.isArray(data.aov_trend.labels)) {
            chartInstances.current.aovTrend = new Chart(
                aovTrendChartRef.current,
                {
                    type: 'line',
                    data: {
                        labels: data.aov_trend.labels,
                        datasets: [{
                            label: 'Avg Order Value',
                            data: data.aov_trend.values,
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: getCommonOptions((evt, _els, chart) => {
                        const label = getClickedLabel(chart, evt);
                        if (label) goToOrdersWithQuery({ filter_created_at: label });
                    })
                }
            );
        }

        return () => {
            Object.values(chartInstances.current).forEach(chart => chart?.destroy());
        };
    }, [data, getClickedLabel, goToOrdersWithQuery]);

    return {
        refs: {
            salesChartRef,
            paymentChartRef,
            categoriesChartRef,
            statusChartRef,
            newReturningChartRef,
            shippingChartRef,
            aovTrendChartRef
        }
    };
};
