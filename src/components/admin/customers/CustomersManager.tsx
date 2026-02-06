import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useCustomers } from '../../../hooks/admin/useCustomers.js';
import { ICustomer } from '../../../types/admin/customers.js';
import { useModalContext } from '../../../context/ModalContext.js';
import { CustomerFilters } from './CustomerFilters.js';
import { CustomersTable } from './CustomersTable.js';
import { CustomerEditorModal } from '../../modals/admin/customers/CustomerEditorModal.js';

export const CustomersManager: React.FC = () => {
    const {
        isLoading,
        customers,
        error,
        fetchCustomers,
        deleteCustomer,
        updateCustomer
    } = useCustomers();

    const { confirm: confirmModal } = useModalContext();

    const [searchParams, setSearchParams] = useSearchParams();
    const [search, setSearch] = useState('');
    const [role, setRole] = useState('all');
    const [modalState, setModalState] = useState<{ id: string | number | null; mode: 'view' | 'edit' }>({
        id: null,
        mode: 'view'
    });

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const viewId = params.get('view');
        const editId = params.get('edit');
        const searchParam = params.get('search');
        const roleParam = params.get('role');

        if (editId) setModalState({ id: editId, mode: 'edit' });
        else if (viewId) setModalState({ id: viewId, mode: 'view' });

        if (searchParam) setSearch(searchParam);
        if (roleParam) setRole(roleParam);

        fetchCustomers();
    }, [fetchCustomers]);

    const filteredCustomers = customers.filter(c => {
        const matchesSearch = !search ||
            `${c.first_name} ${c.last_name}`.toLowerCase().includes(search.toLowerCase()) ||
            c.email.toLowerCase().includes(search.toLowerCase()) ||
            c.username.toLowerCase().includes(search.toLowerCase());

        const matchesRole = role === 'all' || c.role?.toLowerCase() === role.toLowerCase();

        return matchesSearch && matchesRole;
    });

    const updateUrl = (newSearch: string, newRole: string) => {
        setSearchParams(prev => {
            if (newSearch) prev.set('search', newSearch);
            else prev.delete('search');

            if (newRole !== 'all') prev.set('role', newRole);
            else prev.delete('role');
            return prev;
        }, { replace: true });
    };

    const handleSearchChange = (val: string) => {
        setSearch(val);
        updateUrl(val, role);
    };

    const handleRoleChange = (val: string) => {
        setRole(val);
        updateUrl(search, val);
    };

    const handleClear = () => {
        setSearch('');
        setRole('all');
        updateUrl('', 'all');
    };

    const handleView = (id: string | number) => {
        setModalState({ id, mode: 'view' });
        setSearchParams(prev => {
            prev.set('view', String(id));
            prev.delete('edit');
            return prev;
        });
    };

    const handleEdit = (id: string | number) => {
        setModalState({ id, mode: 'edit' });
        setSearchParams(prev => {
            prev.set('edit', String(id));
            prev.delete('view');
            return prev;
        });
    };

    const handleDelete = async (id: string | number, name: string) => {
        const confirmed = await confirmModal({
            title: 'Delete Customer',
            message: `Permanently delete account for "${name}"? This cannot be undone.`,
            confirmText: 'Delete',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const res = await deleteCustomer(id);
            if (res.success) {
                fetchCustomers();
            } else {
                const msg = res.error || 'Failed to delete customer';
                if (window.WFToast) window.WFToast.error(msg);
            }
        }
    };

    const handleCloseModal = () => {
        setModalState({ id: null, mode: 'view' });
        setSearchParams(prev => {
            prev.delete('view');
            prev.delete('edit');
            return prev;
        });
    };

    const handleUpdate = async (id: string | number, data: Partial<ICustomer>) => {
        const res = await updateCustomer(id, data);
        if (res.success) {
            fetchCustomers();
        } else {
            const msg = res.error || 'Failed to update customer';
            if (window.WFToast) window.WFToast.error(msg);
        }
    };

    return (
        <div className="space-y-6 w-full flex-1 min-h-0 flex flex-col overflow-hidden">
            {error && (
                <div className="mx-4 p-4 bg-[var(--brand-error)]/5 border border-[var(--brand-error)]/20 text-[var(--brand-error)] text-sm rounded-xl flex items-center gap-3">
                    <span className="text-xl">⚠️</span>
                    {error}
                </div>
            )}

            <div className="w-full flex-1 min-h-0 flex flex-col overflow-hidden">
                <CustomerFilters
                    search={search}
                    role={role}
                    onSearchChange={handleSearchChange}
                    onRoleChange={handleRoleChange}
                    onClear={handleClear}
                    onRefresh={fetchCustomers}
                    isLoading={isLoading}
                />

                <div className="admin-table-section">
                    <CustomersTable
                        customers={filteredCustomers}
                        onView={handleView}
                        onEdit={handleEdit}
                        onDelete={handleDelete}
                        onUpdate={handleUpdate}
                    />
                </div>
            </div>

            {isLoading && customers.length === 0 && (
                <div className="flex flex-col items-center justify-center p-24 text-gray-400 gap-4">
                    <span className="wf-emoji-loader text-6xl opacity-20">👥</span>
                    <p className="text-lg font-medium italic">Fetching customer database...</p>
                </div>
            )}

            {modalState.id && (
                <CustomerEditorModal
                    user_id={modalState.id}
                    all_customer_ids={customers.map(c => c.id)}
                    mode={modalState.mode}
                    onClose={handleCloseModal}
                    onSaved={() => fetchCustomers()}
                    onNavigate={(id) => {
                        const newMode = modalState.mode;
                        setModalState({ id, mode: newMode });
                        setSearchParams(prev => {
                            prev.set(newMode, String(id));
                            return prev;
                        }, { replace: true });
                    }}
                />
            )}
        </div>
    );
};
