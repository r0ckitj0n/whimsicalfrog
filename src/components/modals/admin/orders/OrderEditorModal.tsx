import React from 'react';
import { createPortal } from 'react-dom';
import { useOrderEditorForm } from '../../../../hooks/admin/useOrderEditorForm.js';
import { OrderBasicsColumn } from '../../../admin/orders/partials/OrderBasicsColumn.js';
import { OrderFulfillmentColumn } from '../../../admin/orders/partials/OrderFulfillmentColumn.js';
import { OrderFinancialsColumn } from '../../../admin/orders/partials/OrderFinancialsColumn.js';
import { OrderEditorHeader } from '../../../admin/orders/partials/OrderEditorHeader.js';
import { OrderLoadingOverlay } from '../../../admin/orders/partials/OrderLoadingOverlay.js';

interface OrderEditorModalProps {
    order_id: string | number;
    mode: 'view' | 'edit';
    onClose: () => void;
    onSaved: () => void;
}

/**
 * OrderEditorModal v1.2.0
 * Refactored into a 3-column "Golden Record" layout with a custom hook.
 */
export const OrderEditorModal: React.FC<OrderEditorModalProps> = ({
    order_id,
    mode,
    onClose,
    onSaved
}) => {
    const {
        order,
        items,
        notes,
        isSaving,
        isDirty,
        handleOrderChange,
        handleAddNote,
        updateItemQty,
        removeItem,
        handleSave
    } = useOrderEditorForm({
        order_id,
        onSaved,
        onClose
    });

    const handleSwitchToEdit = () => {
        const url = new URL(window.location.href);
        url.searchParams.delete('view');
        url.searchParams.set('edit', String(order_id));
        window.history.pushState(null, '', url);
        window.location.reload();
    };

    if (!order) return createPortal(<OrderLoadingOverlay />, document.body);

    const modalContent = (
        <div
            id="orderModal"
            className="admin-modal-overlay over-header show topmost order-modal"
            role="dialog"
            aria-modal="true"
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div
                className="admin-modal admin-modal-content admin-modal--order-editor show bg-white rounded-lg shadow-xl"
                onClick={(e) => e.stopPropagation()}
            >
                <OrderEditorHeader
                    order={order}
                    mode={mode}
                    isSaving={isSaving}
                    isDirty={isDirty}
                    onClose={onClose}
                    handleSave={handleSave}
                    handleSwitchToEdit={handleSwitchToEdit}
                />

                <div className="modal-body wf-admin-modal-body p-6 wf-modal--content-scroll">
                    <form onSubmit={handleSave}>
                        <div className="order-editor-grid">
                            <OrderBasicsColumn
                                order={order}
                                setOrder={handleOrderChange}
                                mode={mode}
                            />

                            <OrderFulfillmentColumn
                                order={order}
                                items={items}
                                mode={mode}
                                updateItemQty={updateItemQty}
                                removeItem={removeItem}
                                setOrder={handleOrderChange}
                            />

                            <OrderFinancialsColumn
                                order={order}
                                items={items}
                                notes={notes}
                                mode={mode}
                                setOrder={handleOrderChange}
                                onAddNote={handleAddNote}
                            />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

