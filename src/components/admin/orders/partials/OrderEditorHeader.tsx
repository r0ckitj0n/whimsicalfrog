import React from 'react';
import { IOrder } from '../../../../types/admin/orders.js';

interface OrderEditorHeaderProps {
    order: IOrder;
    mode: 'view' | 'edit';
    isSaving: boolean;
    isDirty: boolean;
    onClose: () => void;
    handleSave: (e: React.FormEvent) => void;
    handleSwitchToEdit: () => void;
}

export const OrderEditorHeader: React.FC<OrderEditorHeaderProps> = ({
    order,
    mode,
    isSaving,
    isDirty,
    onClose,
    handleSave,
    handleSwitchToEdit
}) => {
    return (
        <div className="modal-header flex items-center border-b border-gray-100 gap-2 px-4 py-3 sticky top-0 bg-white z-10">
            <h2 className="text-lg font-bold text-green-700 admin-card-title">
                {mode === 'edit' ? 'Edit Order' : 'View Order'} {order.id ? `(#${order.id})` : ''}
            </h2>
            <div className="flex-1" />
            <div className="flex items-center gap-2">
                {mode === 'view' && (
                    <button
                        type="button"
                        className="admin-action-btn btn-icon--edit"
                        data-help-id="orders-action-edit-switch"
                        onClick={handleSwitchToEdit}
                    />
                )}
                <button
                    type="button"
                    className="admin-action-btn btn-icon--print"
                    data-help-id="orders-action-print"
                    onClick={() => window.open(`/receipt?order_id=${order.id}&bare=1`, '_blank')}
                />
                {mode === 'edit' && (
                    <button
                        onClick={handleSave}
                        disabled={isSaving}
                        className={`admin-action-btn btn-icon--save ${isDirty ? 'is-dirty' : ''}`}
                        data-help-id="orders-action-save"
                    />
                )}
                <button
                    onClick={onClose}
                    className="admin-action-btn btn-icon--close"
                    data-help-id="common-close"
                />
            </div>
        </div>
    );
};
