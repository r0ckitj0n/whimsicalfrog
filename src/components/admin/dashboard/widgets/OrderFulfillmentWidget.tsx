import React from 'react';
import { useOrderFulfillment } from '../../../../hooks/admin/useOrderFulfillment.js';
import { FulfillmentFilters } from './fulfillment/FulfillmentFilters.js';
import { FulfillmentTable } from './fulfillment/FulfillmentTable.js';

export const OrderFulfillmentWidget: React.FC = () => {
    const {
        orders,
        isLoading,
        filters,
        editingCell,
        editValue,
        setEditValue,
        isSaving,
        status_options,
        payment_status_options,
        shipping_method_options,
        payment_method_options,
        handleCellClick,
        handleEditCancel,
        handleEditSave,
        handleFilterChange,
        handleClearFilters,
        fetchOrders
    } = useOrderFulfillment();

    return (
        <div className="space-y-4">
            <FulfillmentFilters
                filters={filters}
                status_options={status_options}
                payment_method_options={payment_method_options}
                shipping_method_options={shipping_method_options}
                payment_status_options={payment_status_options}
                onFilterChange={handleFilterChange}
                onApply={(e) => { e.preventDefault(); fetchOrders(filters); }}
                onClear={handleClearFilters}
            />

            <FulfillmentTable
                orders={orders}
                isLoading={isLoading}
                editingCell={editingCell}
                editValue={editValue}
                isSaving={isSaving}
                payment_status_options={payment_status_options}
                status_options={status_options}
                payment_method_options={payment_method_options}
                shipping_method_options={shipping_method_options}
                onCellClick={handleCellClick}
                onEditValueChange={setEditValue}
                onEditSave={handleEditSave}
                onEditCancel={handleEditCancel}
            />
        </div>
    );
};
