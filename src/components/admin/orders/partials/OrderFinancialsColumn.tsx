import React from 'react';
import { IOrder, IOrderItem, IOrderNote } from '../../../../types/admin/orders.js';
import { PaymentFinancialsSection } from './PaymentFinancialsSection.js';
import { NotesSection } from './NotesSection.js';

interface OrderFinancialsColumnProps {
    order: IOrder;
    items: IOrderItem[];
    notes: IOrderNote[];
    mode: 'view' | 'edit';
    setOrder: (order: IOrder) => void;
    onAddNote: (type: 'fulfillment' | 'payment', text: string) => Promise<void>;
}

export const OrderFinancialsColumn: React.FC<OrderFinancialsColumnProps> = ({
    order,
    items,
    notes,
    mode,
    setOrder,
    onAddNote
}) => {
    return (
        <div className="order-modal-col order-modal-col-3 p-6 rounded-2xl flex flex-col gap-6">
            <PaymentFinancialsSection
                order={order}
                items={items}
                setOrder={setOrder}
                mode={mode}
            />
            <NotesSection
                order={{ ...order, notes }}
                mode={mode}
                setOrder={setOrder}
                onAddNote={onAddNote}
            />
        </div>
    );
};
