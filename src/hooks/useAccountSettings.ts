import { useState, useEffect } from 'react';
import { useAuthContext } from '../context/AuthContext.js';
import { useCustomers } from '../hooks/admin/useCustomers.js';
import { ICustomerAddress } from '../types/admin/customers.js';
import { useModalContext } from '../context/ModalContext.js';
import ApiClient from '../core/ApiClient.js';
import { IAccountSettingsFormData } from '../types/account.js';

/**
 * Hook for managing account settings state and actions.
 * Extracted from AccountSettingsModal.tsx
 */
export const useAccountSettings = () => {
    const { user, refresh } = useAuthContext();
    const {
        fetchCustomerDetails,
        fetchCustomerAddresses,
        saveAddress,
        deleteAddress,
        isLoading: isAddressLoading
    } = useCustomers();

    const { confirm: confirmModal } = useModalContext();

    const [formData, setFormData] = useState<IAccountSettingsFormData>({
        email: '',
        first_name: '',
        last_name: '',
        phone_number: '',
        company: '',
        job_title: '',
        preferred_contact: '',
        preferred_language: '',
        marketing_opt_in: false,
        currentPassword: '',
        newPassword: ''
    });
    const [initialFormData, setInitialFormData] = useState<IAccountSettingsFormData | null>(null);

    const [addresses, setAddresses] = useState<ICustomerAddress[]>([]);
    const [editingAddress, setEditingAddress] = useState<Partial<ICustomerAddress> | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [isInitialized, setIsInitialized] = useState(false);
    const isPasswordDirty = Boolean(formData.newPassword);

    const isProfileDirty = isInitialized && initialFormData ? (
        (formData.email || '').trim() !== (initialFormData.email || '').trim() ||
        (formData.first_name || '').trim() !== (initialFormData.first_name || '').trim() ||
        (formData.last_name || '').trim() !== (initialFormData.last_name || '').trim() ||
        (formData.phone_number || '').trim() !== (initialFormData.phone_number || '').trim() ||
        (formData.company || '').trim() !== (initialFormData.company || '').trim() ||
        (formData.job_title || '').trim() !== (initialFormData.job_title || '').trim() ||
        (formData.preferred_contact || '').trim() !== (initialFormData.preferred_contact || '').trim() ||
        (formData.preferred_language || '').trim() !== (initialFormData.preferred_language || '').trim() ||
        formData.marketing_opt_in !== initialFormData.marketing_opt_in ||
        isPasswordDirty
    ) : false;

    useEffect(() => {
        if (!user) return;

        const baseFormData: IAccountSettingsFormData = {
            email: user.email || '',
            first_name: user.first_name || '',
            last_name: user.last_name || '',
            phone_number: user.phone_number || '',
            company: '',
            job_title: '',
            preferred_contact: '',
            preferred_language: '',
            marketing_opt_in: false,
            currentPassword: '',
            newPassword: ''
        };

        setFormData(baseFormData);
        setInitialFormData(baseFormData);
        setIsInitialized(true);
    }, [user]);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
        const target = e.target;
        const value = target instanceof HTMLInputElement && target.type === 'checkbox'
            ? target.checked
            : target.value;
        setFormData(prev => ({ ...prev, [target.name]: value }));
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
                phone_number: formData.phone_number,
                company: formData.company,
                job_title: formData.job_title,
                preferred_contact: formData.preferred_contact,
                preferred_language: formData.preferred_language,
                marketing_opt_in: formData.marketing_opt_in ? '1' : '0'
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
                const updatedFormData = { ...formData, currentPassword: '', newPassword: '' };
                setFormData(updatedFormData);
                setInitialFormData(updatedFormData);
                return true;
            }

            throw new Error(res?.error || 'Failed to update profile');
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
            const resetFormData: IAccountSettingsFormData = {
                email: user.email || '',
                first_name: user.first_name || '',
                last_name: user.last_name || '',
                phone_number: user.phone_number || '',
                company: '',
                job_title: '',
                preferred_contact: '',
                preferred_language: '',
                marketing_opt_in: false,
                currentPassword: '',
                newPassword: ''
            };
            setFormData(resetFormData);
            setInitialFormData(resetFormData);
            setIsInitialized(true);

            void (async () => {
                const [detail, addr] = await Promise.all([
                    fetchCustomerDetails(user.id),
                    fetchCustomerAddresses(user.id)
                ]);

                if (detail) {
                    const enrichedFormData: IAccountSettingsFormData = {
                        ...resetFormData,
                        company: detail.company || '',
                        job_title: detail.job_title || '',
                        preferred_contact: detail.preferred_contact || '',
                        preferred_language: detail.preferred_language || '',
                        marketing_opt_in: String(detail.marketing_opt_in ?? '0') === '1'
                    };
                    setFormData(enrichedFormData);
                    setInitialFormData(enrichedFormData);
                } else if (window.WFToast) {
                    window.WFToast.error('Failed to load customer profile details');
                }

                setAddresses(addr);
            })();
        } else {
            setIsInitialized(false);
            setInitialFormData(null);
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
