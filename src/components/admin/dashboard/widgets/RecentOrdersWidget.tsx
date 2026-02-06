import React from 'react';
import { IRecentOrder } from '../../../../types/index.js';
import { buildAdminUrl } from '../../../../core/admin-url-builder.js';

import { ORDER_STATUS } from '../../../../core/constants.js';

interface RecentOrdersWidgetProps {
    orders: IRecentOrder[];
}

export const RecentOrdersWidget: React.FC<RecentOrdersWidgetProps> = ({ orders }) => {
    return (
        <div className="space-y-3">
            {orders.length > 0 ? (
                <>
                    <div className="space-y-2">
                        {orders.slice(0, 5).map((order) => (
                            <div key={order.id} className="flex justify-between items-center bg-gray-50 rounded p-2">
                                <div>
                                    <div className="font-medium text-sm">#{order.id}</div>
                                    <div className="text-xs text-gray-600">{order.username || 'Guest'}</div>
                                </div>
                                <div className="text-right">
                                    <div className="text-sm font-medium">${Number(order.total || order.total_amount || 0).toFixed(2)}</div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-[10px] px-2 rounded-full bg-blue-100 text-blue-800 inline-block">
                                            {order.status || 'Pending'}
                                        </span>
                                        <button
                                            onClick={() => window.openOrderDetails?.(order.id)}
                                            className="admin-action-btn btn-icon--view"
                                            data-help-id="order-action-view"
                                        ></button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                    <div className="text-center pt-2">
                        <a
                            href={buildAdminUrl('orders')}
                            className="admin-action-btn btn-icon--external"
                            data-help-id="dashboard-action-view-all-orders"
                        ></a>
                    </div>
                </>
            ) : (
                <div className="text-center py-8">
                    <p className="text-sm text-gray-500 italic">No recent orders</p>
                </div>
            )}
        </div>
    );
};
