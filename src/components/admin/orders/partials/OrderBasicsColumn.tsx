import React from 'react';
import { IOrder } from '../../../../types/admin/orders.js';
import { OrderInfoSection } from './OrderInfoSection.js';
import { CustomerDetailsSection } from './CustomerDetailsSection.js';

interface OrderBasicsColumnProps {
    order: IOrder;
    setOrder: (order: IOrder) => void;
    mode: 'view' | 'edit';
}

export const OrderBasicsColumn: React.FC<OrderBasicsColumnProps> = ({
    order,
    setOrder,
    mode
}) => {
    return (
        <div className="order-modal-col order-modal-col-1 p-6 rounded-2xl flex flex-col gap-6">
            <OrderInfoSection order={order} setOrder={setOrder} mode={mode} />
            <CustomerDetailsSection order={order} />
        </div>
    );
};
