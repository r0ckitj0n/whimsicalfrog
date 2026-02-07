import { useState, useEffect } from 'react';
import { useAuthContext } from '../context/AuthContext.js';
import { useCustomers } from '../hooks/admin/useCustomers.js';
import { ICustomerAddress } from '../types/admin/customers.js';
import { useModalContext } from '../context/ModalContext.js';
import ApiClient from '../core/ApiClient.js';

/**
 * Hook for managing account settings state and actions.
 * Extracted from AccountSettingsModal.tsx
 */
export const useAccountSettings = () => {
    const { user, refresh } = useAuthContext();
    const {
        fetchCustomerAddresses,
        saveAddress,
        deleteAddress,
        isLoading: isAddressLoading
    } = useCustomers();

    const { confirm: confirmModal } = useModalContext();

    const [formData, setFormData] = useState({
        email: '',
        first_name: '',
        last_name: '',
        phone_number: '',
        currentPassword: '',
        newPassword: ''
    });

    const [addresses, setAddresses] = useState<ICustomerAddress[]>([]);
    const [editingAddress, setEditingAddress] = useState<Partial<ICustomerAddress> | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [isInitialized, setIsInitialized] = useState(false);
    const isPasswordDirty = Boolean(formData.newPassword);

    const isProfileDirty = isInitialized && user ? (
        (formData.email || '').trim() !== (user.email || '').trim() ||
        (formData.first_name || '').trim() !== (user.first_name || '').trim() ||
        (formData.last_name || '').trim() !== (user.last_name || '').trim() ||
        (formData.phone_number || '').trim() !== (user.phone_number || '').trim() ||
        isPasswordDirty
    ) : false;

    useEffect(() => {
        if (user) {
            setFormData(prev => ({
                ...prev,
                email: user.email,
                first_name: user.first_name || '',
                last_name: user.last_name || '',
                phone_number: user.phone_number || ''
            }));
            setIsInitialized(true);

            const loadAddresses = async () => {
                const addr = await fetchCustomerAddresses(user.id);
                setAddresses(addr);
            };
            loadAddresses();
        }
    }, [user, fetchCustomerAddresses]);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setFormData(prev => ({ ...prev, [e.target.name]: e.target.value }));
    };

    const handleSaveProfile = async (e?: React.FormEvent): Promise<boolean> => {
        e?.preventDefault();
        if (!user) return false;

        setIsSaving(true);

        try {
            const payload = {
                user_id: user.id,
                email: formData.email,
                first_name: formData.first_name,
                last_name: formData.last_name,
                phone_number: formData.phone_number
            };

            let res;
            if (formData.newPassword) {
                if (!formData.currentPassword) {
                    throw new Error('Current password is required to set a new one');
                }
                res = await ApiClient.post<{ success: boolean; error?: string }>('/functions/process_account_update.php', {
                    ...payload,
                    currentPassword: formData.currentPassword,
                    newPassword: formData.newPassword
                });
            } else {
                res = await ApiClient.post<{ success: boolean; error?: string }>('/api/update_user.php', payload);
            }

            if (res && res.success) {
                await refresh();
                if (window.WFToast) window.WFToast.success('Profile updated successfully');
                setIsEditing(false);
                setIsInitialized(false); // Force re-initialize after save
                setFormData(prev => ({ ...prev, currentPassword: '', newPassword: '' }));
                return true;
            } else {
                throw new Error(res?.error || 'Failed to update profile');
            }
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'Update failed';
            if (window.WFToast) window.WFToast.error(message);
            return false;
        } finally {
            setIsSaving(false);
        }
    };

    const handleSaveAddress = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingAddress || !user) return;

        // Duplicate name check
        const isDuplicate = addresses.some(addr =>
            addr.address_name.toLowerCase() === editingAddress.address_name?.toLowerCase() &&
            addr.id !== editingAddress.id
        );

        if (isDuplicate) {
            if (window.WFToast) window.WFToast.error(`Address name "${editingAddress.address_name}" is already used.`);
            return;
        }

        const res = await saveAddress({ ...editingAddress, user_id: user.id });
        if (res.success) {
            const addr = await fetchCustomerAddresses(user.id);
            setAddresses(addr);
            setEditingAddress(null);
            if (window.WFToast) window.WFToast.success('Address saved');
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Failed to save address');
        }
    };

    const handleDeleteAddress = async (id: string | number) => {
        if (!user) return;

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
                const addr = await fetchCustomerAddresses(user.id);
                setAddresses(addr);
                if (window.WFToast) window.WFToast.success('Address deleted');
            }
        }
    };

    const reset = () => {
        setIsEditing(false);
        if (user) {
            setFormData({
                email: user.email || '',
                first_name: user.first_name || '',
                last_name: user.last_name || '',
                phone_number: user.phone_number || '',
                currentPassword: '',
                newPassword: ''
            });
            setIsInitialized(true);
        } else {
            setIsInitialized(false);
        }
    };

    return {
        user,
        formData,
        addresses,
        editingAddress,
        isSaving,
        isEditing,
        isProfileDirty,
        isAddressLoading,
        setIsEditing,
        setEditingAddress,
        handleInputChange,
        handleSaveProfile,
        handleSaveAddress,
        handleDeleteAddress,
        reset
    };
};
