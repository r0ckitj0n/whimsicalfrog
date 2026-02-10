import React from 'react';
import { ORDER_STATUS } from '../../../../../core/constants.js';
import { formatCurrency, formatDate, formatTime } from '../../../../../core/date-utils.js';

import { IFulfillmentOrder } from './FulfillmentTable.js';

interface FulfillmentRowProps {
    order: IFulfillmentOrder;
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

export const FulfillmentRow: React.FC<FulfillmentRowProps> = ({
    order,
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
    const isEditing = (field: string) => editingCell?.id === order.id && editingCell?.field === field;

    const renderEditableCell = (field: string, currentValue: string | null, options?: string[], type: 'select' | 'input' | 'date' = 'select') => {
        if (isEditing(field)) {
            if (type === 'select' && options) {
                return (
                    <select
                        autoFocus
                        className="w-full text-xs p-1 border border-blue-400 rounded bg-white"
                        value={editValue}
                        onChange={(e) => {
                            const val = e.target.value;
                            onEditValueChange(val);
                            onEditSave(val);
                        }}
                        onBlur={() => !isSaving && onEditSave()}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') onEditSave();
                            if (e.key === 'Escape') onEditCancel();
                        }}
                        disabled={isSaving}
                    >
                        {field === 'payment_method' || field === 'shipping_method' ? <option value="">None</option> : null}
                        {options.map(opt => (
                            <option key={opt} value={opt}>{opt}</option>
                        ))}
                    </select>
                );
            }
            if (type === 'date') {
                return (
                    <input
                        autoFocus
                        type="date"
                        className="w-full text-xs p-1 border border-blue-400 rounded bg-white"
                        value={editValue ? editValue.split(' ')[0] : ''}
                        onChange={(e) => onEditValueChange(e.target.value)}
                        onBlur={() => !isSaving && onEditSave()}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') onEditSave();
                            if (e.key === 'Escape') onEditCancel();
                        }}
                        disabled={isSaving}
                    />
                );
            }
        }

        if (field === 'status') {
            return (
                <div className={`inline-block px-2 py-0.5 rounded-full text-[9px] font-black uppercase border ${order.status === ORDER_STATUS.PROCESSING ? 'bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] border-[var(--brand-primary)]/20' :
                    order.status === ORDER_STATUS.SHIPPED ? 'bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] border-[var(--brand-primary)]/20' :
                        'bg-gray-50 text-gray-600 border-gray-100'
                    }`}>
                    {order.status}
                </div>
            );
        }

        if (field === 'payment_at') {
            return (
                <div className="text-gray-600">
                    {order.payment_at ? formatDate(order.payment_at) : '—'}
                </div>
            );
        }

        return <div className="text-gray-700">{currentValue || '—'}</div>;
    };

    return (
        <tr className="hover:bg-gray-50 group transition-colors">
            <td className="p-2 font-mono font-medium text-gray-900">#{order.id}</td>
            <td className="p-2">
                <div className="font-bold text-gray-900">{order.username || 'N/A'}</div>
            </td>
            <td className="p-2 text-gray-600">
                {formatDate(order.created_at)}
            </td>
            <td className="p-2 text-gray-600">
                {formatTime(order.created_at)}
            </td>
            <td className="p-2 text-center">
                <button
                    onClick={() => window.openOrderDetails?.(order.id)}
                    className="btn btn-secondary px-2 py-1 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all text-xs font-bold"
                    type="button"
                >
                    {order.total_items} item{order.total_items !== 1 ? 's' : ''}
                </button>
            </td>
            <td className="p-2 font-semibold text-gray-900">{formatCurrency(Number(order.total || 0))}</td>
            <td
                className={`p-2 cursor-pointer hover:bg-blue-50/50 transition-colors ${isEditing('payment_status') ? 'bg-blue-50' : ''}`}
                onClick={() => onCellClick(order.id, 'payment_status', order.payment_status || 'Pending')}
            >
                {renderEditableCell('payment_status', order.payment_status, payment_status_options)}
            </td>
            <td
                className={`p-2 cursor-pointer hover:bg-blue-50/50 transition-colors ${isEditing('payment_at') ? 'bg-blue-50' : ''}`}
                onClick={() => onCellClick(order.id, 'payment_at', order.payment_at || '')}
            >
                {renderEditableCell('payment_at', order.payment_at || null, undefined, 'date')}
            </td>
            <td
                className={`p-2 cursor-pointer hover:bg-blue-50/50 transition-colors ${isEditing('status') ? 'bg-blue-50' : ''}`}
                onClick={() => onCellClick(order.id, 'status', order.status || '')}
            >
                {renderEditableCell('status', order.status, status_options)}
            </td>
            <td
                className={`p-2 cursor-pointer hover:bg-blue-50/50 transition-colors ${isEditing('payment_method') ? 'bg-blue-50' : ''}`}
                onClick={() => onCellClick(order.id, 'payment_method', order.payment_method || '')}
            >
                {renderEditableCell('payment_method', order.payment_method, payment_method_options)}
            </td>
            <td
                className={`p-2 cursor-pointer hover:bg-blue-50/50 transition-colors ${isEditing('shipping_method') ? 'bg-blue-50' : ''}`}
                onClick={() => onCellClick(order.id, 'shipping_method', order.shipping_method || '')}
            >
                {renderEditableCell('shipping_method', order.shipping_method, shipping_method_options)}
            </td>
            <td className="p-2 text-right">
                <div className="flex justify-end gap-1">
                    <button
                        onClick={() => window.openOrderDetails?.(order.id)}
                        className="admin-action-btn btn-icon--view"
                        data-help-id="order-action-view"
                    ></button>
                </div>
            </td>
        </tr>
    );
};
