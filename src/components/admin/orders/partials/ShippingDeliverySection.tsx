import React, { useState } from 'react';
import { IOrder } from '../../../../types/admin/orders.js';
import { SHIPPING_METHOD, ShippingMethod } from '../../../../core/constants.js';

interface ShippingDeliverySectionProps {
    order: IOrder;
    setOrder: (order: IOrder) => void;
    mode: 'view' | 'edit';
}

export const ShippingDeliverySection: React.FC<ShippingDeliverySectionProps> = ({ order, setOrder, mode }) => {
    const [isEditingAddress, setIsEditingAddress] = useState(false);

    const handleAddNewAddress = () => {
        setOrder({
            ...order,
            address_line_1: '',
            address_line_2: '',
            city: '',
            state: '',
            zip_code: ''
        });
        setIsEditingAddress(true);
    };

    return (
        <section className="admin-section--orange rounded-2xl shadow-sm overflow-hidden wf-contained-section">
            <div className="px-6 py-4 flex items-center justify-between">
                <h5 className="text-xs font-black uppercase tracking-widest">Shipping & Delivery</h5>
                {mode === 'edit' && (
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setIsEditingAddress(!isEditingAddress)}
                            className="admin-action-btn btn-icon--edit"
                            data-help-id="orders-action-edit-address"
                        />
                        <button
                            type="button"
                            onClick={handleAddNewAddress}
                            className="admin-action-btn btn-icon--add"
                            data-help-id="orders-action-add-address"
                        />
                    </div>
                )}
            </div>
            <div className="p-4 flex flex-col gap-1">
                <div className="p-4 bg-gray-50 rounded-xl border border-gray-100 space-y-3">
                    <div className="flex items-start gap-3">
                        <span className="text-gray-400 mt-0.5">📍</span>
                        <div className="flex-1">
                            {!isEditingAddress ? (
                                <div className="text-sm text-gray-700 font-medium leading-relaxed">
                                    {order.address_line_1}<br />
                                    {order.address_line_2 && <>{order.address_line_2}<br /></>}
                                    {order.city}, {order.state} {order.zip_code}
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <input
                                        type="text"
                                        placeholder="Address Line 1"
                                        value={order.address_line_1 || ''}
                                        onChange={e => setOrder({ ...order, address_line_1: e.target.value })}
                                        className="form-input w-full text-xs"
                                    />
                                    <input
                                        type="text"
                                        placeholder="Address Line 2"
                                        value={order.address_line_2 || ''}
                                        onChange={e => setOrder({ ...order, address_line_2: e.target.value })}
                                        className="form-input w-full text-xs"
                                    />
                                    <div className="grid grid-cols-3 gap-2">
                                        <input
                                            type="text"
                                            placeholder="City"
                                            value={order.city || ''}
                                            onChange={e => setOrder({ ...order, city: e.target.value })}
                                            className="form-input w-full text-xs"
                                        />
                                        <input
                                            type="text"
                                            placeholder="ST"
                                            value={order.state || ''}
                                            onChange={e => setOrder({ ...order, state: e.target.value })}
                                            className="form-input w-full text-xs"
                                        />
                                        <input
                                            type="text"
                                            placeholder="Zip"
                                            value={order.zip_code || ''}
                                            onChange={e => setOrder({ ...order, zip_code: e.target.value })}
                                            className="form-input w-full text-xs"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-row gap-6 w-full pt-3 border-t">
                        <div className="flex-1 space-y-1">
                            <label className="text-[10px] font-bold text-gray-400 uppercase">Method</label>
                            <select
                                value={order.shipping_method}
                                onChange={e => setOrder({ ...order, shipping_method: e.target.value as ShippingMethod })}
                                disabled={mode === 'view'}
                                className={`form-select w-full text-xs py-1 ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0 !appearance-none' : ''}`}
                            >
                                {Object.values(SHIPPING_METHOD).map(method => (
                                    <option key={method} value={method}>{method}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex-1 space-y-1">
                            <label className="text-[10px] font-bold text-gray-400 uppercase">Tracking #</label>
                            <input
                                type="text"
                                value={order.tracking_number || ''}
                                onChange={e => setOrder({ ...order, tracking_number: e.target.value })}
                                readOnly={mode === 'view'}
                                placeholder="Enter tracking ID"
                                className={`form-input w-full text-xs py-1 ${mode === 'view' ? '!bg-transparent !border-none !shadow-none !px-0' : ''}`}
                            />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
};
