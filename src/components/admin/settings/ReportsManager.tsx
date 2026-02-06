import React, { useState, useMemo, useCallback } from 'react';
import { useReports } from '../../../hooks/admin/useReports.js';
import { Line, Doughnut } from 'react-chartjs-2';
import { SummaryCard } from './reports/SummaryCard.js';
import { InventorySnapshot } from './reports/InventorySnapshot.js';
import { RecentOrdersTable } from './reports/RecentOrdersTable.js';
import { SalesPerformanceChart } from './reports/SalesPerformanceChart.js';
import { PaymentMethodsChart } from './reports/PaymentMethodsChart.js';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Title,
    Tooltip,
    Legend,
    ArcElement,
    Filler
} from 'chart.js';

// Register ChartJS components
ChartJS.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    ArcElement,
    Title,
    Tooltip,
    Legend,
    Filler
);

export const ReportsManager: React.FC = () => {
    const [timeframe, setTimeframe] = useState(7);
    const {
        isLoading,
        salesData,
        paymentData,
        summary,
        recentOrders,
        inventoryStats,
        fetchData,
        fetchInventoryReport
    } = useReports(timeframe);

    const handleGenerateInventoryReport = useCallback(async () => {
        if (window.WFToast) window.WFToast.info('Preparing inventory report download...');

        try {
            const data = await fetchInventoryReport();
            if (!data || data.length === 0) {
                if (window.WFToast) window.WFToast.error('No inventory data found.');
                return;
            }

            // Convert to CSV
            const headers = ['Item Name', 'SKU', 'Category', 'Gender', 'Size', 'Stock Level', 'Reorder Point'];
            const csvRows = [
                headers.join(','),
                ...data.map(item => [
                    `"${item.item_name}"`,
                    `"${item.item_sku}"`,
                    `"${item.category}"`,
                    `"${item.gender}"`,
                    `"${item.size_name}"`,
                    item.stock_level,
                    item.item_reorder_point
                ].join(','))
            ];

            const csvContent = csvRows.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', `inventory_report_${new Date().toISOString().split('T')[0]}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            if (window.WFToast) window.WFToast.success('Inventory report downloaded!');
        } catch (err) {
            console.error('Failed to download report:', err);
            if (window.WFToast) window.WFToast.error('Failed to download report.');
        }
    }, [fetchInventoryReport]);

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                mode: 'index' as const,
                intersect: false,
                padding: 12,
                backgroundColor: 'rgba(255, 255, 255, 0.9)',
                titleColor: '#111827',
                bodyColor: '#4b5563',
                borderColor: '#e5e7eb',
                borderWidth: 1
            }
        },
        scales: {
            y: {
                type: 'linear' as const,
                display: true,
                position: 'left' as const,
                grid: {
                    drawOnChartArea: true,
                    color: '#f3f4f6'
                }
            },
            y1: {
                type: 'linear' as const,
                display: true,
                position: 'right' as const,
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    };

    const summaryCards = useMemo(() => [
        {
            label: 'Revenue',
            value: `$${(summary?.total_revenue || 0).toLocaleString()}`,
            color: 'primary',
            data: salesData.revenue
        },
        {
            label: 'Orders',
            value: summary?.total_orders || 0,
            color: 'secondary',
            data: salesData.orders
        },
        {
            label: 'Avg Value',
            value: `$${(summary?.avg_order_value || 0).toFixed(2)}`,
            color: 'primary',
            data: salesData.revenue.map((r, i) => salesData.orders[i] > 0 ? r / salesData.orders[i] : 0)
        },
        {
            label: 'Customers',
            value: summary?.unique_customers || 0,
            color: 'accent',
            data: salesData.orders.map(o => Math.ceil(o * 0.8)) // Heuristic for sparkline
        }
    ], [summary, salesData]);

    return (
        <div className="flex-1 min-h-0 overflow-y-auto custom-scrollbar px-6 pb-6 pt-0 space-y-8">
            <div className="flex items-center justify-between px-2 pt-4">
                <div className="flex items-center gap-6">
                </div>
                <div className="flex items-center gap-2">
                    <select
                        value={timeframe}
                        onChange={(e) => setTimeframe(parseInt(e.target.value))}
                        className="form-input text-xs py-1 px-3"
                    >
                        <option value={7}>Last 7 Days</option>
                        <option value={30}>Last 30 Days</option>
                        <option value={90}>Last quarter</option>
                        <option value={365}>Last Year</option>
                    </select>
                    <button
                        onClick={fetchData}
                        className="admin-action-btn btn-icon--refresh"
                        data-help-id="reports-refresh"
                        disabled={isLoading}
                    />
                </div>
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 px-2">
                {summaryCards.map((s, i) => (
                    <SummaryCard key={i} {...s} />
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 px-2">
                <SalesPerformanceChart salesData={salesData} options={chartOptions} />
                <PaymentMethodsChart paymentData={paymentData} />
            </div>

            {/* Inventory & Orders Section */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 px-2">
                <InventorySnapshot
                    stats={inventoryStats}
                    onGenerateReport={handleGenerateInventoryReport}
                />
                <RecentOrdersTable orders={recentOrders} />
            </div>
        </div>
    );
};

