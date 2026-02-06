import React from 'react';
import { IOrder, IOrderItem } from '../../../../types/admin/orders.js';
import { PAYMENT_METHOD, PAYMENT_STATUS, PaymentMethod, PaymentStatus } from '../../../../core/constants.js';

interface PaymentFinancialsSectionProps {
    order: IOrder;
    items: IOrderItem[];
    setOrder: (order: IOrder) => void;
    mode: 'view' | 'edit';
}

export const PaymentFinancialsSection: React.FC<PaymentFinancialsSectionProps> = ({ order, items, setOrder, mode }) => {
    // Subtotal calculation (items only)
    const subtotal = items?.reduce((sum: number, item: IOrderItem) => sum + (Number(item.price) * item.quantity), 0) || 0;

    // Total calculation (Subtotal + Shipping + Tax - Discount)
    const total = subtotal + (Number(order.shipping_cost) || 0) + (Number(order.tax_amount) || 0) - (Number(order.discount_amount) || 0);

    return (
        <section className="admin-section--green rounded-2xl shadow-sm overflow-hidden wf-contained-section">
            <div className="px-6 py-4">
                <h5 className="text-xs font-black uppercase tracking-widest">Payment & Financials</h5>
            </div>
            <div className="p-4 flex flex-col gap-1">
                <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Payment Method</label>
                        <select
                            value={order.payment_method}
                            onChange={e => setOrder({ ...order, payment_method: e.target.value as PaymentMethod })}
                            disabled={mode === 'view'}
                            className={`form-select w-full ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0 !appearance-none' : ''}`}
                        >
                            {Object.values(PAYMENT_METHOD).map(method => (
                                <option key={method} value={method}>{method}</option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Payment Status</label>
                        <select
                            value={order.payment_status}
                            onChange={e => setOrder({ ...order, payment_status: e.target.value as PaymentStatus })}
                            disabled={mode === 'view'}
                            className={`form-select w-full ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0 !appearance-none' : ''}`}
                        >
                            {Object.values(PAYMENT_STATUS).map(status => (
                                <option key={status} value={status}>{status}</option>
                            ))}
                        </select>
                    </div>
                </div>
                <div className="space-y-1">
                    <label className="text-xs font-bold text-gray-500 uppercase">Payment Date</label>
                    <input
                        type="datetime-local"
                        value={order.payment_at ? order.payment_at.replace(' ', 'T').slice(0, 16) : order.created_at ? order.created_at.replace(' ', 'T').slice(0, 16) : ''}
                        onChange={e => setOrder({ ...order, payment_at: e.target.value })}
                        readOnly={mode === 'view'}
                        className={`form-input w-full ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0' : ''}`}
                    />
                </div>

                <div className="grid grid-cols-2 gap-4 pt-2">
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Shipping Cost</label>
                        <input
                            type="number"
                            step="0.01"
                            value={(Number(order.shipping_cost) || 0).toFixed(2)}
                            onChange={e => setOrder({ ...order, shipping_cost: parseFloat(e.target.value) || 0 })}
                            readOnly={mode === 'view'}
                            className={`form-input w-full ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0' : ''}`}
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Tax Amount</label>
                        <input
                            type="number"
                            step="0.01"
                            value={(Number(order.tax_amount) || 0).toFixed(2)}
                            onChange={e => setOrder({ ...order, tax_amount: parseFloat(e.target.value) || 0 })}
                            readOnly={mode === 'view'}
                            className={`form-input w-full ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0' : ''}`}
                        />
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 pt-2">
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Discount Amount</label>
                        <input
                            type="number"
                            step="0.01"
                            value={(Number(order.discount_amount) || 0).toFixed(2)}
                            onChange={e => setOrder({ ...order, discount_amount: parseFloat(e.target.value) || 0 })}
                            readOnly={mode === 'view'}
                            className={`form-input w-full ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0' : ''}`}
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Coupon Code</label>
                        <input
                            type="text"
                            value={order.coupon_code || ''}
                            onChange={e => setOrder({ ...order, coupon_code: e.target.value })}
                            readOnly={mode === 'view'}
                            placeholder="None"
                            className={`form-input w-full ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0' : ''}`}
                        />
                    </div>
                </div>

                <div className="pt-2 border-t border-gray-100 space-y-2">
                    <div className="flex justify-between items-center font-medium text-gray-500">
                        <span>Subtotal</span>
                        <span>${subtotal.toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between items-center font-medium text-gray-500">
                        <span>Shipping</span>
                        <span>${(Number(order.shipping_cost) || 0).toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between items-center pb-1 font-medium text-gray-500">
                        <span>Tax</span>
                        <span>${(Number(order.tax_amount) || 0).toFixed(2)}</span>
                    </div>
                    <div className="flex justify-between items-center pb-1 text-red-500 font-medium">
                        <span>Discount</span>
                        <span>-${(Number(order.discount_amount) || 0).toFixed(2)}</span>
                    </div>
                    <div className="pt-4 text-right border-t border-gray-100 flex justify-between items-end">
                        <div className="uppercase text-lg font-black text-gray-400">Total Amount</div>
                        <div className="text-5xl font-black text-gray-900 leading-none">
                            ${total.toFixed(2)}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
};
