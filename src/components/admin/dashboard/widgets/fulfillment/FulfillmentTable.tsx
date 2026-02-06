import React from 'react';
import { FulfillmentRow } from './FulfillmentRow.js';
import type { IFulfillmentOrder } from '../../../../../types/orders.js';

// Re-export for backward compatibility
export type { IFulfillmentOrder } from '../../../../../types/orders.js';

interface FulfillmentTableProps {
    orders: IFulfillmentOrder[];
    isLoading: boolean;
    editingCell: { id: string | number, field: string } | null;
    editValue: string;
    isSaving: boolean;
    payment_status_options: string[];
    status_options: string[];
    payment_method_options: string[];
    shipping_method_options: string[];
    onCellClick: (orderId: string | number, field: string, currentValue: string) => void;
    onEditValueChange: (val: string) => void;
    onEditSave: (newValue?: string) => void;
    onEditCancel: () => void;
}

export const FulfillmentTable: React.FC<FulfillmentTableProps> = ({
    orders,
    isLoading,
    editingCell,
    editValue,
    isSaving,
    payment_status_options,
    status_options,
    payment_method_options,
    shipping_method_options,
    onCellClick,
    onEditValueChange,
    onEditSave,
    onEditCancel
}) => {
    return (
        <div className="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div className="overflow-x-auto">
                <table className="w-full text-xs">
                    <thead className="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th className="text-left font-medium text-gray-700 p-2">Order ID</th>
                            <th className="text-left font-medium text-gray-700 p-2">Customer</th>
                            <th className="text-left font-medium text-gray-700 p-2">Date</th>
                            <th className="text-left font-medium text-gray-700 p-2">Time</th>
                            <th className="text-left font-medium text-gray-700 p-2">Items</th>
                            <th className="text-left font-medium text-gray-700 p-2">Total</th>
                            <th className="text-left font-medium text-gray-700 p-2">Payment Status</th>
                            <th className="text-left font-medium text-gray-700 p-2">Payment Date</th>
                            <th className="text-left font-medium text-gray-700 p-2">Order Status</th>
                            <th className="text-left font-medium text-gray-700 p-2">Payment Method</th>
                            <th className="text-left font-medium text-gray-700 p-2">Shipping Method</th>
                            <th className="text-right font-medium text-gray-700 p-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {orders.length === 0 ? (
                            <tr>
                                <td colSpan={12} className="p-8 text-center text-gray-400 italic">
                                    {isLoading ? 'Scanning pond...' : 'No orders found matching filters'}
                                </td>
                            </tr>
                        ) : (
                            orders.map((order) => (
                                <FulfillmentRow
                                    key={order.id}
                                    order={order}
                                    editingCell={editingCell}
                                    editValue={editValue}
                                    isSaving={isSaving}
                                    payment_status_options={payment_status_options}
                                    status_options={status_options}
                                    payment_method_options={payment_method_options}
                                    shipping_method_options={shipping_method_options}
                                    onCellClick={onCellClick}
                                    onEditValueChange={onEditValueChange}
                                    onEditSave={onEditSave}
                                    onEditCancel={onEditCancel}
                                />
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};
