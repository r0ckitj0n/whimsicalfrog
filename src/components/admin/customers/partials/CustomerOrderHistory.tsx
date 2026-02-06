import React from 'react';
import { IOrderHistoryItem } from '../../../../types/admin/customers.js';

interface CustomerOrderHistoryProps {
    orders: IOrderHistoryItem[];
}

export const CustomerOrderHistory: React.FC<CustomerOrderHistoryProps> = ({ orders }) => {
    const formatDate = (dateStr: string) => {
        try {
            return new Date(dateStr).toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            });
        } catch (e) {
            return dateStr;
        }
    };

    const formatCurrency = (amount: number | string) => {
        const val = typeof amount === 'string' ? parseFloat(amount) : amount;
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD'
        }).format(val || 0);
    };

    return (
        <section className="admin-section--green rounded-2xl flex flex-col overflow-hidden wf-contained-section">
            <div className="px-6 py-4 bg-white border-b border-gray-100 flex items-center justify-between">
                <h3 className="text-xs font-black text-gray-500 uppercase tracking-widest">Order History</h3>
                <span className="bg-[var(--brand-accent)]/20 text-[var(--brand-primary)] px-2 py-0.5 rounded-full text-[10px] font-bold">
                    {orders.length} Orders
                </span>
            </div>

            <div className="p-4 space-y-3">
                {orders.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-12 text-center">
                        <div className="text-3xl mb-2 opacity-20">📦</div>
                        <p className="text-sm text-gray-400 italic">No orders found for this customer.</p>
                    </div>
                ) : (
                    orders.map((order) => {
                        const statusClass = `status-${(order.status || 'pending').toLowerCase()}`;
                        return (
                            <a
                                key={order.id}
                                href={`/admin?section=orders&view=${order.id}`}
                                className="block p-4 bg-white border border-gray-100 rounded-xl hover:border-[var(--brand-primary)]/30 hover:shadow-md transition-all group"
                            >
                                <div className="flex justify-between items-start mb-2">
                                    <h5 className="text-xs font-black text-gray-900 group-hover:text-[var(--brand-primary)]">
                                        Order #{order.id}
                                    </h5>
                                    <span className={`px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider ${statusClass}`}>
                                        {order.status}
                                    </span>
                                </div>
                                <div className="grid grid-cols-2 gap-2 text-[10px]">
                                    <div className="flex flex-col">
                                        <span className="text-gray-400 font-bold uppercase">Date</span>
                                        <span className="text-gray-700 font-medium">{formatDate(order.date || order.created_at || '')}</span>
                                    </div>
                                    <div className="flex flex-col">
                                        <span className="text-gray-400 font-bold uppercase">Total</span>
                                        <span className="text-gray-700 font-bold text-xs">{formatCurrency(order.total || order.total_amount || 0)}</span>
                                    </div>
                                    <div className="col-span-2 flex flex-col pt-1 border-t border-gray-50 mt-1">
                                        <span className="text-gray-400 font-bold uppercase">Payment & Shipping</span>
                                        <span className="text-gray-600 truncate">
                                            {order.payment_method || 'N/A'} • {order.shipping_method || 'Standard'}
                                        </span>
                                    </div>
                                </div>
                            </a>
                        );
                    })
                )}
            </div>
        </section>
    );
};
