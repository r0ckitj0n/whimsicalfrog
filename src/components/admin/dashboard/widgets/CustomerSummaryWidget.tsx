import React from 'react';
import { buildAdminUrl } from '../../../../core/admin-url-builder.js';

interface CustomerSummaryWidgetProps {
    total_customers: number;
    recentCustomers: Array<{ username: string; email: string }>;
}

export const CustomerSummaryWidget: React.FC<CustomerSummaryWidgetProps> = ({
    total_customers,
    recentCustomers
}) => {
    return (
        <div className="space-y-3">
            <div className="grid grid-cols-1 gap-3">
                <div className="bg-green-50 rounded text-center p-3">
                    <div className="text-lg font-bold text-green-600">{total_customers}</div>
                    <div className="text-xs text-green-800">Total Customers</div>
                </div>
            </div>

            {recentCustomers.length > 0 && (
                <div className="space-y-1">
                    <div className="text-xs font-medium text-gray-600">Recent Customers:</div>
                    {recentCustomers.slice(0, 3).map((c, idx) => (
                        <div key={idx} className="text-xs bg-gray-50 rounded p-2">
                            <div className="font-medium text-gray-900">{c.username}</div>
                            <div className="text-gray-500 truncate">{c.email}</div>
                        </div>
                    ))}
                </div>
            )}

            <div className="text-center pt-2">
                <a
                    href={buildAdminUrl('customers')}
                    className="admin-action-btn btn-icon--external"
                    data-help-id="dashboard-action-manage-customers"
                >🔗</a>
            </div>
        </div>
    );
};
