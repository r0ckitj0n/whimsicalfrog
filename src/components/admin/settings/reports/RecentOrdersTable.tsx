import React from 'react';

import { IRecentOrder } from '../../../../types/index.js';
import { formatDate } from '../../../../core/date-utils.js';

interface RecentOrdersTableProps {
    orders: IRecentOrder[];
}

export const RecentOrdersTable: React.FC<RecentOrdersTableProps> = ({ orders }) => {
    return (
        <div className="bg-white border rounded-[2rem] p-6 shadow-sm space-y-6 lg:col-span-2 overflow-hidden flex flex-col">
            <div className="flex items-center justify-between">
                <h3 className="font-black text-gray-900 text-sm uppercase tracking-wider">Recent Orders</h3>
            </div>

            <div className="flex-1 overflow-x-auto">
                <table className="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr className="border-b border-gray-100">
                            <th className="py-3 px-2 font-black text-gray-400 uppercase tracking-widest text-[9px]">ID</th>
                            <th className="py-3 px-2 font-black text-gray-400 uppercase tracking-widest text-[9px]">Customer</th>
                            <th className="py-3 px-2 font-black text-gray-400 uppercase tracking-widest text-[9px]">Date</th>
                            <th className="py-3 px-2 font-black text-gray-400 uppercase tracking-widest text-[9px]">Total</th>
                            <th className="py-3 px-2 font-black text-gray-400 uppercase tracking-widest text-[9px] text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-50 font-medium">
                        {orders.map(order => (
                            <tr key={order.id} className="hover:bg-gray-50 transition-colors">
                                <td className="py-3 px-2 font-mono text-gray-900">#{order.id}</td>
                                <td className="py-3 px-2 text-gray-600">{order.username || 'Guest'}</td>
                                <td className="py-3 px-2 text-[10px] text-gray-400">
                                    {order.created_at ? formatDate(order.created_at, { month: 'short', day: 'numeric' }) : '—'}
                                </td>
                                <td className="py-3 px-2 font-black text-gray-900">${Number(order.total).toFixed(2)}</td>
                                <td className="py-3 px-2 text-right">
                                    <span className="px-2 py-0.5 rounded-full bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] text-[9px] font-black uppercase border border-[var(--brand-primary)]/20">
                                        {order.status}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
};
