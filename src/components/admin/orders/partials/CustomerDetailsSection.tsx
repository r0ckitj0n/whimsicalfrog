import React from 'react';
import { IOrder } from '../../../../types/admin/orders.js';
import { useModalContext } from '../../../../context/ModalContext.js';

interface CustomerDetailsSectionProps {
    order: IOrder;
}

export const CustomerDetailsSection: React.FC<CustomerDetailsSectionProps> = ({ order }) => {
    const { show } = useModalContext();

    const handleViewProfile = () => {
        if (!order.user_id) return;

        // Show the customer editor modal via context
        show({
            component: 'AdminCustomerEditor',
            mode: 'component', // Explicitly set mode to component
            props: {
                user_id: order.user_id,
                mode: 'view',
                onSaved: () => { } // Refresh if needed
            }
        });
    };

    return (
        <section className="admin-section--green rounded-2xl shadow-sm overflow-hidden wf-contained-section">
            <div className="px-6 py-4">
                <h5 className="text-xs font-black uppercase tracking-widest">Customer Details</h5>
            </div>
            <div className="p-4 flex flex-col gap-1">
                <div className="p-4 bg-white/50 rounded-xl border border-[var(--brand-primary)]/10 flex items-center justify-between">
                    <div>
                        <div className="font-bold text-gray-900">{order.username || 'Guest Customer'}</div>
                        <div className="text-xs text-gray-500 font-medium">User ID: {order.user_id || 'N/A'}</div>
                    </div>
                    <button
                        type="button"
                        onClick={handleViewProfile}
                        className="admin-action-btn btn-icon--view"
                        data-help-id="orders-customer-view-profile"
                    />
                </div>
            </div>
        </section>
    );
};
