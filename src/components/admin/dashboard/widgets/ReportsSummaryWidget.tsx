import React from 'react';
import { IDashboardMetrics } from '../../../../types/index.js';
import { buildAdminUrl } from '../../../../core/admin-url-builder.js';

interface ReportsSummaryWidgetProps {
    metrics: IDashboardMetrics | null;
}

export const ReportsSummaryWidget: React.FC<ReportsSummaryWidgetProps> = ({ metrics }) => {
    if (!metrics || !metrics.reports) return null;

    const { last_7d, last_30d, payment_breakdown, daily_sales } = metrics.reports;
    const maxRevenue = Math.max(...daily_sales.map(s => s.revenue), 1);

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div className="bg-purple-50 rounded text-center p-3">
                    <div className="text-sm text-purple-700">Total Revenue</div>
                    <div className="text-lg font-bold text-purple-800">${Number(metrics.total_revenue || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                </div>
                <div className="bg-blue-50 rounded text-center p-3">
                    <div className="text-sm text-blue-700">Last 7d Revenue</div>
                    <div className="text-lg font-bold text-blue-800">${Number(last_7d.revenue || 0).toLocaleString(undefined, { minimumFractionDigits: 2 })}</div>
                </div>
                <div className="bg-green-50 rounded text-center p-3">
                    <div className="text-sm text-green-700">Last 7d Orders</div>
                    <div className="text-lg font-bold text-green-800">{last_7d.orders}</div>
                </div>
                <div className="bg-amber-50 rounded text-center p-3">
                    <div className="text-sm text-amber-700">Last 30d Orders</div>
                    <div className="text-lg font-bold text-amber-800">{last_30d.orders}</div>
                </div>
            </div>

            {daily_sales.length > 0 && (
                <div className="bg-white border border-gray-200 rounded p-2">
                    <h3 className="font-black text-gray-900 text-sm uppercase tracking-wider">Reports Summary</h3>
                    <div className="text-xs text-gray-600 mb-1">Recent Daily Sales (7d)</div>
                    <div className="flex gap-2 items-end">
                        {daily_sales.map((sale, idx) => {
                            const height = Math.max(6, Math.round((sale.revenue / maxRevenue) * 36));
                            return (
                                <svg key={idx} width="10" height={height} aria-hidden="true" focusable="false" role="img">
                                    <title>{`${sale.d}: $${sale.revenue.toLocaleString()}`}</title>
                                    <rect width="10" height={height} fill="#A78BFA" />
                                </svg>
                            );
                        })}
                    </div>
                </div>
            )}

            <div className="bg-white border border-gray-200 rounded overflow-hidden">
                <div className="p-2 text-xs font-medium text-gray-700 border-b">Top Payment Methods</div>
                {payment_breakdown.length > 0 ? (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-2 p-2">
                        {payment_breakdown.map((pm, idx) => (
                            <div key={idx} className="bg-gray-50 rounded p-2 text-center">
                                <div className="text-xs text-gray-600 truncate">{pm.method}</div>
                                <div className="text-sm font-semibold text-gray-800">{pm.cnt} orders</div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="p-3 text-center text-gray-500 text-sm">No payment data</div>
                )}
            </div>

            <div className="text-center pt-2">
                <a
                    href={buildAdminUrl('reports')}
                    className="admin-action-btn btn-icon--external"
                    data-help-id="dashboard-action-view-all-reports"
                />
            </div>
        </div>
    );
};
