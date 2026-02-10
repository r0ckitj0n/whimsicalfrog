import React, { useEffect } from 'react';
import { useDashboard } from '../../../hooks/admin/useDashboard.js';
import { useHealthChecks } from '../../../hooks/admin/useHealthChecks.js';
import { IDashboardSection } from '../../../types/dashboard.js';
import { MetricsWidget } from './widgets/MetricsWidget.js';
import { RecentOrdersWidget } from './widgets/RecentOrdersWidget.js';
import { LowStockWidget } from './widgets/LowStockWidget.js';
import { OrderFulfillmentWidget } from './widgets/OrderFulfillmentWidget.js';
import { CustomerSummaryWidget } from './widgets/CustomerSummaryWidget.js';
import { InventorySummaryWidget } from './widgets/InventorySummaryWidget.js';
import { MarketingToolsWidget } from './widgets/MarketingToolsWidget.js';
import { ReportsSummaryWidget } from './widgets/ReportsSummaryWidget.js';

export const AdminDashboard: React.FC = () => {
    const {
        isLoading,
        metrics,
        recentOrders,
        lowStockItems,
        config,
        error,
        refresh
    } = useDashboard();

    const { status: healthStatus } = useHealthChecks(true);

    if (isLoading && !metrics) {
        return (
            <div className="flex flex-col items-center justify-center p-24 text-gray-400 gap-4">
                <span className="wf-emoji-loader text-6xl opacity-20">📊</span>
                <p className="text-lg font-medium italic">Gathering field intelligence...</p>
            </div>
        );
    }

    const renderWidget = (key: string) => {
        switch (key) {
            case 'metrics':
                return <MetricsWidget metrics={metrics} />;
            case 'recent_orders':
                return <RecentOrdersWidget orders={recentOrders} />;
            case 'low_stock':
                return <LowStockWidget items={lowStockItems} />;
            case 'order_fulfillment':
                return <OrderFulfillmentWidget />;
            case 'customer_summary':
                return <CustomerSummaryWidget
                    total_customers={metrics?.total_customers || 0}
                    recentCustomers={metrics?.recent_customers || []}
                />;
            case 'inventory_summary':
                return <InventorySummaryWidget
                    totalStockUnits={metrics?.total_stock_units || 0}
                    total_items={metrics?.total_items || 0}
                    lowStock={lowStockItems.length}
                    topStockItems={metrics?.top_stock_items || []}
                />;
            case 'marketing_tools':
                return <MarketingToolsWidget stats={metrics?.marketing || { email_campaigns: 0, active_discounts: 0, scheduled_posts: 0 }} />;
            case 'reports_summary':
                return <ReportsSummaryWidget metrics={metrics} />;
            default:
                return <div className="p-4 text-gray-400 italic text-xs border rounded-xl">Widget {key} coming soon</div>;
        }
    };

    const getThemeClass = (key: string) => {
        const themeMap: Record<string, string> = {
            'metrics': 'card-theme-blue',
            'recent_orders': 'card-theme-purple',
            'low_stock': 'card-theme-red',
            'inventory_summary': 'card-theme-emerald',
            'customer_summary': 'card-theme-cyan',
            'marketing_tools': 'card-theme-amber',
            'order_fulfillment': 'card-theme-blue',
            'reports_summary': 'card-theme-purple',
        };
        return themeMap[key] || 'card-theme-blue';
    };

    return (
        <div className="flex-1 min-h-0 overflow-y-auto custom-scrollbar px-6 pb-6 pt-0">
            <div className="flex flex-col">
                {healthStatus && (
                    <div className="flex items-center justify-between px-2 py-4">
                        <div className="flex items-center gap-6">
                            <div className="hidden md:flex items-center gap-4 px-4 py-2 bg-white border rounded-2xl shadow-sm">
                                <div className="flex items-center gap-2">
                                    <span className={`text-xs ${healthStatus.backgrounds.missingFiles.length > 0 || healthStatus.items.missingFiles > 0 ? 'animate-pulse' : ''}`}>
                                        {healthStatus.backgrounds.missingFiles.length > 0 || healthStatus.items.missingFiles > 0 ? '⚠️' : '✅'}
                                    </span>
                                    <span className="text-[10px] font-black uppercase tracking-widest text-gray-400">System Health</span>
                                </div>
                                <div className="h-4 w-px bg-gray-100"></div>
                                <div className="flex gap-3">
                                    <div className="flex items-center gap-1.5" data-help-id="dashboard-stat-backgrounds">
                                        <div className={`w-1.5 h-1.5 rounded-full ${healthStatus.backgrounds.missingFiles.length > 0 ? 'bg-[var(--brand-error)]' : 'bg-[var(--brand-accent)]'}`}></div>
                                        <span className="text-[9px] font-bold text-gray-600 uppercase">BGs</span>
                                    </div>
                                    <div className="flex items-center gap-1.5" data-help-id="dashboard-stat-images">
                                        <div className={`w-1.5 h-1.5 rounded-full ${healthStatus.items.missingFiles > 0 ? 'bg-[var(--brand-error)]' : 'bg-[var(--brand-accent)]'}`}></div>
                                        <span className="text-[9px] font-bold text-gray-600 uppercase">Items</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}

                {error && (
                    <div className="p-4 mb-6 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded-2xl flex items-center gap-3 animate-in slide-in-from-top-2">
                        <span className="text-xl">⚠️</span>
                        {error}
                    </div>
                )}

                <div id="dashboardGrid" className="dashboard-grid space-y-6">
                    {(config && config.length > 0 ? config : [
                        { key: 'metrics', title: 'Business Overview', width: 'full' },
                        { key: 'recent_orders', title: 'Recent Activity', width: 'half' },
                        { key: 'low_stock', title: 'Inventory Alerts', width: 'half' }
                    ] as IDashboardSection[]).map((section) => {
                        const widthClass = section.width === 'full' ? 'full-width' : 'half-width';
                        return (
                            <div
                                key={section.key}
                                className={`dashboard-section settings-section ${getThemeClass(section.key)} ${widthClass}`}
                                data-section-key={section.key}
                            >
                                <div className="section-header rounded-lg overflow-hidden">
                                    <div className="flex items-center justify-between">
                                        <div className="flex-1">
                                            <h3 className="section-title text-lg font-semibold">
                                                {section.title || section.key.replace('_', ' ')}
                                            </h3>
                                            {section.description && (
                                                <p className="section-description text-sm">
                                                    {section.description}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                <div className="section-content p-6">
                                    {renderWidget(section.key)}
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
};
