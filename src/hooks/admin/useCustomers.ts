import { useState, useCallback } from 'react';
import { ApiClient } from '../../core/ApiClient.js';
import { ICustomer, ICustomerAddress, ICustomerNote } from '../../types/admin/customers.js';
import logger from '../../core/logger.js';

export const useCustomers = () => {
    const [isLoading, setIsLoading] = useState(false);
    const [customers, setCustomers] = useState<ICustomer[]>([]);
    const [error, setError] = useState<string | null>(null);

    const fetchCustomers = useCallback(async () => {
        setIsLoading(true);
        setError(null);
        try {
            const res = await ApiClient.get('/api/users.php') as ICustomer[];
            if (Array.isArray(res)) {
                setCustomers(res);
            } else {
                setError('Failed to load customers');
            }
        } catch (err) {
            logger.error('[useCustomers] fetchCustomers failed', err);
            setError('Unable to load customers list');
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchCustomerDetails = useCallback(async (id: string | number) => {
        setIsLoading(true);
        try {
            // First try with the specific ID
            const res = await ApiClient.get('/api/users.php', { id }) as ICustomer | ICustomer[];

            if (Array.isArray(res)) {
                // If the server returns a list even with an ID, find the specific one
                const found = res.find(c => String(c.id) === String(id));
                return found || null;
            }
            return res;
        } catch (err) {
            logger.error('[useCustomers] fetchCustomerDetails failed', err);
            return null;
        } finally {
            setIsLoading(false);
        }
    }, []);

    const fetchCustomerAddresses = useCallback(async (user_id: string | number) => {
        try {
            const res = await ApiClient.get('/api/customer_addresses.php', {
                action: 'get_addresses',
                user_id: user_id
            }) as { success: boolean; addresses: ICustomerAddress[] };
            return res.success ? res.addresses : [];
        } catch (err) {
            logger.error('[useCustomers] fetchCustomerAddresses failed', err);
            return [];
        }
    }, []);

    const saveAddress = async (address: Partial<ICustomerAddress>) => {
        try {
            const action = address.id ? 'update_address' : 'add_address';
            const res = await ApiClient.post('/api/customer_addresses.php', {
                action,
                ...address
            }) as { success: boolean; error?: string };
            return res;
        } catch (err) {
            logger.error('[useCustomers] saveAddress failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const deleteAddress = async (id: string | number) => {
        try {
            const res = await ApiClient.get('/api/customer_addresses.php', {
                action: 'delete_address',
                id
            }) as { success: boolean; error?: string };
            return res;
        } catch (err) {
            logger.error('[useCustomers] deleteAddress failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    const deleteCustomer = async (id: string | number) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.delete(`/api/delete_customer.php?id=${id}`) as { success: boolean; error?: string };
            return res;
        } catch (err) {
            logger.error('[useCustomers] deleteCustomer failed', err);
            return { success: false, error: 'Network error' };
        } finally {
            setIsLoading(false);
        }
    };

    const updateCustomer = async (id: string | number, data: Partial<ICustomer>) => {
        setIsLoading(true);
        try {
            const res = await ApiClient.post('/api/update_user.php', {
                user_id: id,
                ...data
            }) as { success: boolean; error?: string };
            return res;
        } catch (err) {
            logger.error('[useCustomers] updateCustomer failed', err);
            return { success: false, error: 'Network error' };
        } finally {
            setIsLoading(false);
        }
    };

    const fetchCustomerNotes = useCallback(async (user_id: string | number) => {
        try {
            const res = await ApiClient.get('/api/customer_notes.php', { user_id }) as { success: boolean; notes: ICustomerNote[] };
            return res.success ? res.notes : [];
        } catch (err) {
            logger.error('[useCustomers] fetchCustomerNotes failed', err);
            return [];
        }
    }, []);

    const addCustomerNote = async (user_id: string | number, note_text: string) => {
        try {
            const res = await ApiClient.post('/api/customer_notes.php', {
                user_id,
                note_text,
                author_username: 'Admin' // Future: get from auth context
            }) as { success: boolean; error?: string };
            return res;
        } catch (err) {
            logger.error('[useCustomers] addCustomerNote failed', err);
            return { success: false, error: 'Network error' };
        }
    };

    return {
        isLoading,
        customers,
        error,
        fetchCustomers,
        fetchCustomerDetails,
        fetchCustomerAddresses,
        fetchCustomerNotes,
        saveAddress,
        deleteAddress,
        deleteCustomer,
        updateCustomer,
        addCustomerNote
    };
};
