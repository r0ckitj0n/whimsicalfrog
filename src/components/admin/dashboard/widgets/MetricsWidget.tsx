import React from 'react';
import { IDashboardMetrics } from '../../../../types/index.js';

interface MetricsWidgetProps {
    metrics: IDashboardMetrics | null;
}

export const MetricsWidget: React.FC<MetricsWidgetProps> = ({ metrics }) => {
    if (!metrics) return null;

    return (
        <div className="wf-sections-grid-4">
            {/* Items - Uses Neutral theme */}
            <div className="wf-section-neutral wf-section-padding text-center">
                <div className="wf-stat-display">
                    <div className="text-3xl font-bold text-slate-700">{Number(metrics.total_items).toLocaleString()}</div>
                    <div className="text-sm text-slate-500 font-medium">Items</div>
                </div>
            </div>
            {/* Orders - Primary theme (green bg → orange text) */}
            <div className="wf-section-primary wf-section-padding text-center">
                <div className="wf-stat-display">
                    <div className="wf-stat-value text-3xl">{Number(metrics.total_orders).toLocaleString()}</div>
                    <div className="wf-stat-label text-sm font-medium">Orders</div>
                </div>
            </div>
            {/* Customers - Secondary theme (orange bg → green text) */}
            <div className="wf-section-secondary wf-section-padding text-center">
                <div className="wf-stat-display">
                    <div className="wf-stat-value text-3xl">{Number(metrics.total_customers).toLocaleString()}</div>
                    <div className="wf-stat-label text-sm font-medium">Customers</div>
                </div>
            </div>
            {/* Revenue - Primary theme (green bg → orange text) */}
            <div className="wf-section-primary wf-section-padding text-center">
                <div className="wf-stat-display">
                    <div className="wf-stat-value text-3xl">${Number(metrics.total_revenue || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                    <div className="wf-stat-label text-sm font-medium">Revenue</div>
                </div>
            </div>
        </div>
    );
};
