import React, { useMemo } from 'react';
import { Doughnut } from 'react-chartjs-2';
import { IPaymentData } from '../../../../hooks/admin/useReports.js';

interface PaymentMethodsChartProps {
    paymentData: IPaymentData;
}

export const PaymentMethodsChart: React.FC<PaymentMethodsChartProps> = ({ paymentData }) => {
    const data = useMemo(() => ({
        labels: paymentData.paymentLabels,
        datasets: [{
            data: paymentData.paymentCounts,
            backgroundColor: [
                '#87ac3a',
                '#bf5700',
                '#3b82f6',
                '#8b5cf6',
                '#f59e0b',
                '#ef4444'
            ],
            borderWidth: 0
        }]
    }), [paymentData]);

    return (
        <div className="bg-white border rounded-[2rem] p-6 shadow-sm space-y-6">
            <div className="flex items-center justify-between">
                <h3 className="font-black text-gray-900 text-sm uppercase tracking-wider">Payment Methods</h3>
            </div>
            <div className="h-64 flex items-center justify-center">
                <div className="w-48 h-48">
                    <Doughnut 
                        data={data} 
                        options={{
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } }
                        }} 
                    />
                </div>
            </div>
        </div>
    );
};
