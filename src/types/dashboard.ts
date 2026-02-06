import { IOrder, IRecentOrder } from './admin/orders.js';
import { IDashboardMetrics } from './admin.js';
import { IInventoryStats } from './inventory.js';

// Re-export IDashboardMetrics for convenience
export type { IDashboardMetrics } from './admin.js';

// Dashboard Widget Types
export interface ILowStockItem {
    sku: string;
    name: string;
    stock_quantity: number;
    reorder_point: number;
}

export interface IDashboardSection {
    key: string;
    title: string;
    description?: string;
    width: 'full' | 'half' | 'third';
    is_visible: boolean;
    order: number;
}

export interface IActionIcon {
    id: number;
    icon_key: string;
    label: string;
    target_action: string;
    is_active: boolean;
}

export interface IIconMapping {
    key: string;
    emoji: string;
}

export interface IMarketingAnalyticsData {
    success: boolean;
    message?: string;
    error?: string;
    timeframe: number;
    sales: {
        labels: string[];
        datasets: Array<{
            label: string;
            data: number[];
            backgroundColor?: string;
            borderColor?: string;
        }>;
    };
    kpis: {
        total_revenue: number;
        order_count: number;
        average_order_value: number;
        conversion_rate: number;
        growth_percentage: number;
    };
    payment_methods: {
        labels: string[];
        values: number[];
    };
    top_categories: {
        labels: string[];
        values: number[];
    };
    status: {
        labels: string[];
        values: number[];
    };
    new_returning: {
        labels: string[];
        values: number[];
    };
    shipping_methods: {
        labels: string[];
        values: number[];
    };
    aov_trend: {
        labels: string[];
        values: number[];
    };
}

// API Response Types
export interface IDashboardResponse {
    metrics: IDashboardMetrics;
    orders?: IOrder[];
    data?: unknown[];
    sections?: Record<string, unknown>[];
}

// Reports Types (migrated from useReports.ts)
export interface ISalesData {
    labels: string[];
    revenue: number[];
    orders: number[];
}

export interface IPaymentData {
    paymentLabels: string[];
    paymentCounts: number[];
}

export interface IInventoryReportItem {
    item_name: string;
    item_sku: string;
    category: string;
    size_name: string;
    gender: string;
    stock_level: number;
    item_reorder_point: number;
}

export interface IReportSummary {
    total_revenue: number;
    total_orders: number;
    avg_order_value: number;
    unique_customers: number;
}

export interface IReportsResponse {
    success: boolean;
    sales?: ISalesData;
    payment?: IPaymentData;
    summary?: IReportSummary;
    recentOrders?: IRecentOrder[];
    inventoryStats?: IInventoryStats;
    error?: string;
}
