import React, { useState, useEffect, useCallback } from 'react';
import { ICustomer, ICustomerAddress } from '../../../../types/admin/customers.js';
import { useCustomers } from '../../../../hooks/admin/useCustomers.js';
import { useModalContext } from '../../../../context/ModalContext.js';
import { CustomerProfileSection } from '../../../admin/customers/partials/CustomerProfileSection.js';
import { CustomerMiddleSection } from '../../../admin/customers/partials/CustomerMiddleSection.js';
import { isDraftDirty } from '../../../../core/utils.js';
import { CustomerAddressList } from '../../../admin/customers/partials/CustomerAddressList.js';
import { CustomerOrderHistory } from '../../../admin/customers/partials/CustomerOrderHistory.js';

interface CustomerEditorModalProps {
    user_id: string | number;
    all_customer_ids: (string | number)[];
    mode: 'view' | 'edit';
    onClose: () => void;
    onSaved: () => void;
    onNavigate: (id: string | number) => void;
}

export const CustomerEditorModal: React.FC<CustomerEditorModalProps> = ({
    user_id,
    all_customer_ids,
    mode: initialMode,
    onClose,
    onSaved,
    onNavigate
}) => {
    const { fetchCustomerDetails, fetchCustomerAddresses, saveAddress, deleteAddress, updateCustomer, isLoading } = useCustomers();
    const { confirm: confirmModal } = useModalContext();
    const [customer, setCustomer] = useState<ICustomer | null>(null);
    const [originalCustomer, setOriginalCustomer] = useState<ICustomer | null>(null);
    const [mode, setMode] = useState<'view' | 'edit'>(initialMode);
    const [addresses, setAddresses] = useState<ICustomerAddress[]>([]);
    const [editingAddress, setEditingAddress] = useState<Partial<ICustomerAddress> | null>(null);

    const loadData = useCallback(async () => {
        const data = await fetchCustomerDetails(user_id);
        if (data) {
            setCustomer(data);
            setOriginalCustomer({ ...data }); // Store a copy for dirty checking
            const addr = await fetchCustomerAddresses(user_id);
            setAddresses(addr);
        }
    }, [user_id, fetchCustomerDetails, fetchCustomerAddresses]);

    useEffect(() => {
        loadData();
    }, [loadData]);

    useEffect(() => {
        setMode(initialMode);
    }, [initialMode]);

    const handleProfileChange = (data: Partial<ICustomer>) => {
        if (!customer) return;
        setCustomer({ ...customer, ...data });
    };

    const handleSaveProfile = async (): Promise<boolean> => {
        if (!customer) return false;
        const res = await updateCustomer(user_id, customer);
        if (res.success) {
            setOriginalCustomer({ ...customer }); // Sync original on success
            onSaved();
            if (window.WFToast) window.WFToast.success('Changes saved successfully');
            return true;
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Failed to save changes');
            return false;
        }
    };

    const isDirty = React.useMemo(() => {
        if (!customer || !originalCustomer) return false;
        return isDraftDirty(customer, originalCustomer);
    }, [customer, originalCustomer]);

    const handleSaveAddress = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingAddress) return;

        // Duplicate name check
        const isDuplicate = addresses.some(addr =>
            addr.address_name.toLowerCase() === editingAddress.address_name?.toLowerCase() &&
            addr.id !== editingAddress.id
        );

        if (isDuplicate) {
            if (window.WFToast) window.WFToast.error(`Address name "${editingAddress.address_name}" is already used. Please use a different name.`);
            return;
        }

        const res = await saveAddress({ ...editingAddress, user_id: user_id });
        if (res.success) {
            const addr = await fetchCustomerAddresses(user_id);
            setAddresses(addr);
            setEditingAddress(null);
            if (window.WFToast) window.WFToast.success('Address saved successfully');
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Failed to save address');
        }
    };

    const handleDeleteAddress = async (id: string | number) => {
        const confirmed = await confirmModal({
            title: 'Delete Address',
            message: 'Delete this address?',
            confirmText: 'Delete',
            confirmStyle: 'danger',
            icon: '⚠️',
            iconType: 'danger'
        });

        if (confirmed) {
            const res = await deleteAddress(id);
            if (res.success) {
                const addr = await fetchCustomerAddresses(user_id);
                setAddresses(addr);
                if (window.WFToast) window.WFToast.success('Address deleted');
            }
        }
    };

    const handlePrev = () => {
        const idx = all_customer_ids.indexOf(user_id);
        if (idx > 0) onNavigate(all_customer_ids[idx - 1]);
        else onNavigate(all_customer_ids[all_customer_ids.length - 1]);
    };

    const handleNext = () => {
        const idx = all_customer_ids.indexOf(user_id);
        if (idx < all_customer_ids.length - 1) onNavigate(all_customer_ids[idx + 1]);
        else onNavigate(all_customer_ids[0]);
    };

    const attemptClose = async () => {
        if (isLoading) return;
        if (!isDirty) {
            onClose();
            return;
        }

        const shouldSave = await confirmModal({
            title: 'Unsaved Changes',
            message: 'Save changes before closing this modal?',
            subtitle: 'Choose Save to keep edits, or Discard to close without saving.',
            confirmText: 'Save',
            cancelText: 'Discard',
            confirmStyle: 'warning',
            iconKey: 'warning'
        });

        if (shouldSave) {
            const didSave = await handleSaveProfile();
            if (didSave) onClose();
            return;
        }

        onClose();
    };

    if (!customer) {
        return (
            <div className="fixed inset-0 z-[var(--wf-z-modal)] flex items-center justify-center bg-black/60 backdrop-blur-sm">
                <div className="bg-white rounded-2xl p-12 flex flex-col items-center gap-4">
                    <span className="wf-emoji-loader">👥</span>
                    <p className="text-gray-500 font-medium">Retrieving customer profile...</p>
                </div>
            </div>
        );
    }

    return (
        <div
            className="customer-modal admin-modal-overlay wf-overlay-viewport wf-modal--content-scroll wf-modal-single-scroll over-header topmost show"
            onClick={(e) => {
                if (e.target === e.currentTarget) void attemptClose();
            }}
        >
            <div className="wf-overlay-scrim"></div>

            {/* Navigation Arrows */}
            <button onClick={handlePrev} className="nav-arrow nav-arrow-left wf-nav-arrow wf-nav-left" data-help-id="nav-previous">
                <span className="btn-icon--previous" style={{ fontSize: '24px' }} />
            </button>
            <button onClick={handleNext} className="nav-arrow nav-arrow-right wf-nav-arrow wf-nav-right" data-help-id="nav-next">
                <span className="btn-icon--next" style={{ fontSize: '24px' }} />
            </button>

            <div className="admin-modal admin-modal-content admin-modal--responsive admin-modal--actions-in-header">
                {/* Header */}
                <div className="modal-header flex items-center justify-between gap-2 border-b bg-gray-50/50">
                    <h2 className="modal-title text-lg font-bold text-gray-800">
                        {mode === 'edit' ? 'Edit' : 'View'} Customer: {customer.first_name} {customer.last_name}
                    </h2>
                    <div className="modal-header-actions flex items-center gap-3">
                        {(mode === 'view' && !isDirty) && (
                            <button
                                onClick={() => setMode('edit')}
                                className="admin-action-btn btn-icon--edit"
                                data-help-id="customer-edit"
                            />
                        )}
                        {isDirty && (
                            <button
                                onClick={() => { void handleSaveProfile(); }}
                                className="admin-action-btn btn-icon--save is-dirty"
                                data-help-id="modal-save"
                                disabled={isLoading}
                            />
                        )}
                        <button
                            onClick={() => { void attemptClose(); }}
                            className="admin-action-btn btn-icon--close admin-modal-close"
                            data-help-id="modal-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body p-0">
                    <div className="customer-modal-grid">
                        {/* Column 1: Personal Information (GREEN) */}
                        <div className="customer-modal-col customer-modal-col-1">
                            <CustomerProfileSection
                                customer={customer}
                                mode={mode}
                                onChange={handleProfileChange}
                            />
                        </div>

                        {/* Column 2: Addresses + Account Flags + CRM + Notes (ORANGE) */}
                        <div className="customer-modal-col customer-modal-col-2">
                            <CustomerAddressList
                                addresses={addresses}
                                mode={mode}
                                editingAddress={editingAddress}
                                onAddClick={() => setEditingAddress({ address_name: 'Home', is_default: false })}
                                onEditClick={setEditingAddress}
                                onDeleteClick={handleDeleteAddress}
                                handleSaveAddress={handleSaveAddress}
                                setEditingAddress={setEditingAddress}
                                isLoading={isLoading}
                            />
                            <CustomerMiddleSection
                                customer={customer}
                                mode={mode}
                                onChange={handleProfileChange}
                            />
                        </div>

                        {/* Column 3: Order History (GREEN) */}
                        <div className="customer-modal-col customer-modal-col-3">
                            <CustomerOrderHistory orders={customer.order_history || []} />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};
