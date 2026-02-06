import React from 'react';
import { IOrder, IOrderItem } from '../../../../types/admin/orders.js';
import { OrderItemsSection } from './OrderItemsSection.js';
import { ShippingDeliverySection } from './ShippingDeliverySection.js';

interface OrderFulfillmentColumnProps {
    order: IOrder;
    items: IOrderItem[];
    mode: 'view' | 'edit';
    updateItemQty: (sku: string, qty: number) => void;
    removeItem: (sku: string) => void;
    setOrder: (order: IOrder) => void;
}

export const OrderFulfillmentColumn: React.FC<OrderFulfillmentColumnProps> = ({
    order,
    items,
    mode,
    updateItemQty,
    removeItem,
    setOrder
}) => {
    return (
        <div className="order-modal-col order-modal-col-2 p-6 rounded-2xl flex flex-col gap-6">
            <OrderItemsSection
                items={items}
                mode={mode}
                updateItemQty={updateItemQty}
                removeItem={removeItem}
            />
            <ShippingDeliverySection
                order={order}
                setOrder={setOrder}
                mode={mode}
            />
        </div>
    );
};
