import React from 'react';
import { IOrder } from '../../../../types/admin/orders.js';
import { ORDER_STATUS, OrderStatus } from '../../../../core/constants.js';

interface OrderInfoSectionProps {
    order: IOrder;
    setOrder: (order: IOrder) => void;
    mode: 'view' | 'edit';
}

export const OrderInfoSection: React.FC<OrderInfoSectionProps> = ({ order, setOrder, mode }) => {
    return (
        <section className="admin-section--green rounded-2xl shadow-sm overflow-hidden wf-contained-section">
            <div className="px-6 py-4">
                <h5 className="text-xs font-black uppercase tracking-widest">Order Information</h5>
            </div>
            <div className="p-4 flex flex-col gap-1">
                <div className="space-y-1">
                    <label className="text-xs font-bold text-gray-500 uppercase">Status</label>
                    <select
                        value={order.status}
                        onChange={e => setOrder({ ...order, status: e.target.value as OrderStatus })}
                        disabled={mode === 'view'}
                        className={`form-select w-full ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0 !appearance-none' : ''}`}
                    >
                        {(Object.values(ORDER_STATUS) as OrderStatus[]).map(status => (
                            <option key={status} value={status}>{status}</option>
                        ))}
                    </select>
                </div>
            </div>
        </section>
    );
};
