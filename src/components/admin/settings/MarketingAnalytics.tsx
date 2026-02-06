import React, { useEffect, useState } from 'react';
import { useMarketingAnalytics } from '../../../hooks/admin/useMarketingAnalytics.js';
import { useMarketingCharts } from '../../../hooks/admin/useMarketingCharts.js';
import { KPICard } from './marketing/KPICard.js';
import { ChartCard } from './marketing/ChartCard.js';

export const MarketingAnalytics: React.FC = () => {
    const {
        data,
        isLoading,
        error,
        fetchAnalytics
    } = useMarketingAnalytics();

    const [timeframe, setTimeframe] = useState(7);
    const { refs } = useMarketingCharts(data);

    useEffect(() => {
        fetchAnalytics(timeframe);
    }, [timeframe, fetchAnalytics]);

    if (isLoading && !data) {
        return (
            <div className="flex flex-col items-center justify-center p-12 text-gray-500">
                <span className="wf-emoji-loader">📈</span>
                <p>Aggregating marketing intelligence...</p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-end">
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
                        onClick={() => fetchAnalytics(timeframe)}
                        className="admin-action-btn btn-icon--refresh"
                        disabled={isLoading}
                        data-help-id="common-refresh"
                    />
                </div>
            </div>

            {error && (
                <div className="p-4 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm rounded-lg">
                    {error}
                </div>
            )}

            {/* KPI Row */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <KPICard
                    label="Total Revenue"
                    value={`$${data?.kpis?.total_revenue?.toLocaleString() ?? '0'}`}
                    sub="Gross sales in period"
                    iconKey="trending-up"
                    color="primary"
                    growthPercentage={data?.kpis?.growth_percentage}
                />
                <KPICard
                    label="Orders"
                    value={data?.kpis?.order_count ?? 0}
                    sub="Total completed checkouts"
                    iconKey="shopping-cart"
                    color="secondary"
                    growthPercentage={data?.kpis?.growth_percentage}
                />
                <KPICard
                    label="Avg Order Value"
                    value={`$${(data?.kpis?.average_order_value ?? 0).toFixed(2)}`}
                    sub="Average spend per cart"
                    iconKey="target"
                    color="primary"
                    growthPercentage={data?.kpis?.growth_percentage}
                />
                <KPICard
                    label="Conversion Rate"
                    value={`${data?.kpis?.conversion_rate ?? 0}%`}
                    sub="Visitors to customers"
                    iconKey="users"
                    color="accent"
                    growthPercentage={data?.kpis?.growth_percentage}
                />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <ChartCard title="Revenue Trend" sub="Daily breakdown" iconKey="trending-up" className="lg:col-span-3">
                    <canvas ref={refs.salesChartRef} />
                </ChartCard>

                <ChartCard title="Payment Methods" emoji="💳">
                    <canvas ref={refs.paymentChartRef} />
                </ChartCard>

                <ChartCard title="AOV Trend" sub="Basket value" iconKey="trending-up" className="lg:col-span-3">
                    <canvas ref={refs.aovTrendChartRef} />
                </ChartCard>

                <ChartCard title="Order Status" emoji="📦">
                    <canvas ref={refs.statusChartRef} />
                </ChartCard>

                <ChartCard title="Revenue by Category" sub="Top 5" iconKey="bar-chart" className="lg:col-span-2">
                    <canvas ref={refs.categoriesChartRef} />
                </ChartCard>

                <ChartCard title="Shipping Methods" emoji="🚚">
                    <canvas ref={refs.shippingChartRef} />
                </ChartCard>

                <ChartCard title="Customer Loyalty" sub="New vs Returning" emoji="👥">
                    <canvas ref={refs.newReturningChartRef} />
                </ChartCard>
            </div>
        </div>
    );
};

