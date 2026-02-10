import React from 'react';
import { IOrder } from '../../../types/admin/orders.js';
import { ORDER_STATUS } from '../../../core/constants.js';
import { EditableSelect } from './EditableSelect.js';
import { getStatusBadgeClass, getPaymentStatusBadgeClass, formatDate, formatDateTime } from './OrdersTableHelpers.js';
import { formatCurrency } from '../../../core/date-utils.js';

interface OrdersTableProps {
    orders: IOrder[];
    onView: (id: string | number) => void;
    onEdit: (id: string | number) => void;
    onPrint: (id: string | number) => void;
    onDelete: (id: string | number) => void;
    onUpdate?: (id: string | number, data: Partial<IOrder>) => Promise<any>;
    options?: {
        status: string[];
        payment_status: string[];
        payment_method: string[];
        shipping_method: string[];
    };
}

export const OrdersTable: React.FC<OrdersTableProps> = ({
    orders,
    onView,
    onEdit,
    onPrint,
    onDelete,
    onUpdate,
    options
}) => {
    const [editingCell, setEditingCell] = React.useState<{ id: string | number, field: string } | null>(null);
    const [editValue, setEditValue] = React.useState<string>('');

    const handleEditStart = (id: string | number, field: string, initialValue: string) => {
        setEditingCell({ id, field });
        setEditValue(initialValue || '');
    };

    const handleEditBlur = async (id: string | number, field: string, value: string) => {
        if (onUpdate) {
            try {
                await onUpdate(id, { [field]: value });
                if (window.WFToast) window.WFToast.success(`Order #${id} ${field.replace('_', ' ')} updated`);
            } catch (err) {
                console.error(`[OrdersTable] Update exception for #${id}:`, err);
                if (window.WFToast) window.WFToast.error(`Failed to update ${field}`);
            }
        }
        setEditingCell(null);
    };

    const renderEditableCell = (order: IOrder, field: string, value: string, choices: string[] | undefined) => {
        const isEditing = editingCell?.id === order.id && editingCell?.field === field;

        // Hardcoded defaults if API/Props fail
        const hardcodedDefaults: Record<string, string[]> = {
            'status': ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'],
            'payment_status': ['Pending', 'Paid', 'Failed', 'Refunded'],
            'payment_method': ['Square', 'Cash', 'Check', 'Other'],
            'shipping_method': ['USPS', 'UPS', 'FedEx', 'Customer Pickup', 'Local Delivery']
        };

        let displayChoices = Array.isArray(choices) ? [...choices] : [];
        if (displayChoices.length === 0) {
            displayChoices = hardcodedDefaults[field] || [];
        }
        if (value && !displayChoices.includes(value)) {
            displayChoices.push(value);
        }
        displayChoices = [...new Set(displayChoices)].filter(Boolean);

        if (isEditing) {
            if (field === 'tracking_number') {
                return (
                    <div className="flex items-center gap-1">
                        <input
                            autoFocus
                            type="text"
                            value={editValue}
                            onChange={(e) => setEditValue(e.target.value)}
                            onBlur={() => handleEditBlur(order.id, field, editValue)}
                            onKeyDown={(ev) => {
                                if (ev.key === 'Enter') handleEditBlur(order.id, field, editValue);
                                if (ev.key === 'Escape') setEditingCell(null);
                            }}
                            className="text-[10px] border rounded px-1 py-0.5 w-full font-mono"
                        />
                    </div>
                );
            }
            return (
                <EditableSelect
                    value={value}
                    choices={displayChoices}
                    onSave={(newVal) => handleEditBlur(order.id, field, newVal)}
                    onCancel={() => setEditingCell(null)}
                />
            );
        }

        return (
            <div
                onClick={(e) => {
                    e.stopPropagation();
                    handleEditStart(order.id, field, value);
                }}
                className="cursor-pointer hover:bg-gray-100 rounded transition-colors min-h-[24px] flex flex-col justify-center px-1"
                data-help-id="orders-cell-edit"
            >
                {field === 'payment_status' ? (
                    <div className={`inline-block text-[10px] font-bold ${getPaymentStatusBadgeClass(value)}`}>
                        {value}
                    </div>
                ) : field === 'status' ? (
                    <span className={`text-[10px] font-black uppercase ${getStatusBadgeClass(value)}`}>
                        {value}
                    </span>
                ) : field === 'tracking_number' ? (
                    <div className="text-[9px] text-[var(--brand-primary)] font-mono truncate">
                        {value ? `#${value}` : <span className="text-gray-400 italic">Add Tracking</span>}
                    </div>
                ) : (
                    <div className="text-[10px] font-bold text-gray-700 uppercase tracking-tight truncate">
                        {value || '—'}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="bg-white border rounded-xl shadow-sm w-full !max-w-none !m-0 !p-0 overflow-visible">
            <div className="overflow-visible w-full !max-w-none">
                <table className="w-full !min-w-full table-fixed border-collapse border-spacing-0">
                    <thead className="bg-gray-50 border-b-2 border-gray-300 sticky top-0 z-10">
                        <tr>
                            <th className="w-[8%] px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">ID</th>
                            <th className="w-[12%] px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Customer</th>
                            <th className="w-[10%] px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Date</th>
                            <th className="w-[8%] px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50 text-center">Items</th>
                            <th className="w-[8%] px-4 py-4 text-right text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Total</th>
                            <th className="w-[10%] px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Payment</th>
                            <th className="w-[12%] px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Method</th>
                            <th className="w-[10%] px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50 text-center">Status</th>
                            <th className="w-[12%] px-4 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50"></th>
                            <th className="w-[10%] px-4 py-4 text-right text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white">
                        {orders.length === 0 ? (
                            <tr>
                                <td colSpan={10} className="px-4 py-12 text-center text-gray-400 italic border-b-2 border-gray-300">
                                    No orders found matching the current filters.
                                </td>
                            </tr>
                        ) : (
                            orders.map((order) => {
                                const { date, time } = formatDateTime(order.created_at);
                                return (
                                    <tr key={order.id} className="hover:bg-gray-50 group transition-colors">
                                        <td className="px-4 py-3 text-sm font-mono font-bold text-gray-900 border-b-2 border-gray-300">
                                            #{order.id}
                                        </td>
                                        <td className="px-4 py-3 border-b-2 border-gray-300">
                                            <div className="text-sm text-gray-900 font-semibold truncate">
                                                {order.username || (order.user_id ? `User #${order.user_id}` : 'Guest')}
                                            </div>
                                            <div className="text-[10px] text-gray-400 font-medium">
                                                {order.user_id ? `ID ${order.user_id}` : 'No account'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 border-b-2 border-gray-300">
                                            <div className="text-sm text-gray-900">{date}</div>
                                            <div className="text-[10px] text-gray-400 font-medium">{time}</div>
                                        </td>
                                        <td className="px-4 py-3 text-center border-b-2 border-gray-300">
                                            <span className="text-xs font-bold text-gray-600">
                                                {order.total_items}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-right border-b-2 border-gray-300">
                                            <div className="text-sm font-black text-gray-900">
                                                {formatCurrency(Number(order.total || 0))}
                                            </div>
                                        </td>
                                        <td className="p-2 border-b-2 border-gray-300">
                                            {renderEditableCell(order, 'payment_status', order.payment_status, options?.payment_status || [])}
                                            <div className="text-[10px] text-gray-400">
                                                {order.payment_at ? formatDate(order.payment_at, { month: 'short', day: 'numeric' }) : '—'}
                                            </div>
                                        </td>
                                        <td className="p-2 border-b-2 border-gray-300">
                                            {renderEditableCell(order, 'payment_method', order.payment_method, options?.payment_method || [])}
                                        </td>
                                        <td className="p-2 text-center border-b-2 border-gray-300">
                                            {renderEditableCell(order, 'status', order.status, options?.status || [])}
                                        </td>
                                        <td className="p-2 border-b-2 border-gray-300">
                                            {order.status === ORDER_STATUS.SHIPPED && (
                                                <>
                                                    {renderEditableCell(order, 'shipping_method', order.shipping_method, options?.shipping_method || [])}
                                                    {renderEditableCell(order, 'tracking_number', order.tracking_number || '', [])}
                                                </>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right border-b-2 border-gray-300">
                                            <div className="flex justify-end gap-1">
                                                <button
                                                    onClick={() => onView(order.id)}
                                                    className="admin-action-btn btn-icon--view"
                                                    data-help-id="orders-action-view"
                                                />
                                                <button
                                                    onClick={() => onEdit(order.id)}
                                                    className="admin-action-btn btn-icon--edit"
                                                    data-help-id="orders-action-edit"
                                                />
                                                <button
                                                    onClick={() => onPrint(order.id)}
                                                    className="admin-action-btn btn-icon--print"
                                                    data-help-id="orders-action-print"
                                                />
                                                <button
                                                    onClick={() => onDelete(order.id)}
                                                    className="admin-action-btn btn-icon--delete"
                                                    data-help-id="orders-action-delete"
                                                />
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>
        </div >
    );
};
