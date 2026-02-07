import React, { useMemo } from 'react';
import { Line } from 'react-chartjs-2';
import { ChartOptions } from 'chart.js';
import { ISalesData } from '../../../../hooks/admin/useReports.js';

interface SalesPerformanceChartProps {
    salesData: ISalesData;
    options: ChartOptions<'line'>;
}

export const SalesPerformanceChart: React.FC<SalesPerformanceChartProps> = ({ salesData, options }) => {
    const data = useMemo(() => ({
        labels: salesData.labels,
        datasets: [
            {
                label: 'Revenue',
                data: salesData.revenue,
                borderColor: '#87ac3a',
                backgroundColor: 'rgba(135, 172, 58, 0.1)',
                fill: true,
                tension: 0.4,
                yAxisID: 'y'
            },
            {
                label: 'Orders',
                data: salesData.orders,
                borderColor: '#bf5700',
                backgroundColor: 'rgba(191, 87, 0, 0.1)',
                fill: false,
                tension: 0.4,
                yAxisID: 'y1'
            }
        ]
    }), [salesData]);

    return (
        <div className="bg-white border rounded-[2rem] p-6 shadow-sm space-y-6">
            <div className="flex items-center justify-between">
                <h3 className="font-black text-gray-900 text-sm uppercase tracking-wider">Sales Performance</h3>
            </div>
            <div className="h-64">
                <Line data={data} options={options} />
            </div>
        </div>
    );
};
