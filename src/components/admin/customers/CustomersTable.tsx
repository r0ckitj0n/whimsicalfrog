import React from 'react';
import { ICustomer } from '../../../types/admin/customers.js';
import { ROLE } from '../../../core/constants.js';

interface CustomersTableProps {
    customers: ICustomer[];
    onView: (id: string | number) => void;
    onEdit: (id: string | number) => void;
    onDelete: (id: string | number, name: string) => void;
    onUpdate: (id: string | number, data: Partial<ICustomer>) => Promise<any>;
}

export const CustomersTable: React.FC<CustomersTableProps> = ({
    customers,
    onView,
    onEdit,
    onDelete,
    onUpdate
}) => {
    const [editingCell, setEditingCell] = React.useState<{ id: string | number, field: string } | null>(null);
    const [editValue, setEditValue] = React.useState<string>('');

    const handleEditStart = (id: string | number, field: string, value: string) => {
        setEditingCell({ id, field });
        setEditValue(value);
    };

    const handleEditBlur = async (id: string | number, field: string) => {
        const customer = customers.find(c => c.id === id);
        const customerRecord = customer as unknown as Record<string, unknown>;
        if (customer && customerRecord[field] !== editValue) {
            try {
                await onUpdate(id, { [field]: editValue });
                if (window.WFToast) window.WFToast.success(`${field.replace('_', ' ')} updated successfully`);
            } catch (err) {
                if (window.WFToast) window.WFToast.error(`Failed to update ${field}`);
            }
        }
        setEditingCell(null);
    };

    const handleKeyDown = (e: React.KeyboardEvent, id: string | number, field: string) => {
        if (e.key === 'Enter') {
            handleEditBlur(id, field);
        } else if (e.key === 'Escape') {
            setEditingCell(null);
        }
    };

    const renderEditableCell = (customer: ICustomer, field: string, value: string, className: string = '') => {
        const isEditing = editingCell?.id === customer.id && editingCell?.field === field;

        if (isEditing) {
            if (field === 'role') {
                return (
                    <select
                        autoFocus
                        value={editValue}
                        onChange={(e) => setEditValue(e.target.value)}
                        onBlur={() => handleEditBlur(customer.id, field)}
                        className="text-xs border rounded px-1 py-0.5 w-full"
                    >
                        <option value={ROLE.CUSTOMER}>Customer</option>
                        <option value={ROLE.ADMIN}>Admin</option>
                    </select>
                );
            }
            return (
                <input
                    autoFocus
                    type="text"
                    value={editValue}
                    onChange={(e) => setEditValue(e.target.value)}
                    onBlur={() => handleEditBlur(customer.id, field)}
                    onKeyDown={(e) => handleKeyDown(e, customer.id, field)}
                    className={`text-sm border rounded px-2 py-1 w-full ${className}`}
                />
            );
        }

        return (
            <div
                onClick={() => handleEditStart(customer.id, field, value)}
                className={`cursor-pointer hover:bg-gray-100 px-2 -mx-2 rounded transition-colors ${className}`}
                data-help-id="customers-cell-edit"
            >
                {value || <span className="text-gray-400 italic">None</span>}
            </div>
        );
    };
    const getInitials = (first_name: string, last_name: string) => {
        if (first_name && last_name) {
            return (first_name[0] + last_name[0]).toUpperCase();
        }
        return (first_name || last_name || 'CU').slice(0, 2).toUpperCase();
    };

    return (
        <div className="bg-white border rounded-xl shadow-sm overflow-visible w-full !max-w-none !m-0 !p-0">
            <div className="overflow-visible w-full !max-w-none">
                <table className="w-full !min-w-full table-fixed border-collapse border-spacing-0">
                    <thead className="bg-gray-50 border-b-2 border-gray-300 sticky top-0 z-10">
                        <tr>
                            <th className="w-[30%] px-6 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Customer</th>
                            <th className="w-[35%] px-6 py-4 text-left text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Contact Info</th>
                            <th className="w-[10%] px-6 py-4 text-center text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Role</th>
                            <th className="w-[10%] px-6 py-4 text-center text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Orders</th>
                            <th className="w-[15%] px-6 py-4 text-right text-[11px] font-black text-gray-500 uppercase tracking-widest bg-gray-50/50">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white">
                        {customers.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="px-4 py-12 text-center text-gray-400 italic border-b-2 border-gray-300">
                                    No customers found matching the current filters.
                                </td>
                            </tr>
                        ) : (
                            customers.map((customer) => (
                                <tr key={customer.id} className="hover:bg-gray-50 group transition-colors">
                                    <td className="px-4 py-3 border-b-2 border-gray-300">
                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] rounded-full flex items-center justify-center text-sm font-black border border-[var(--brand-primary)]/20">
                                                {getInitials(customer.first_name, customer.last_name)}
                                            </div>
                                            <div>
                                                <div className="text-sm font-bold text-gray-900">
                                                    <div className="flex gap-1">
                                                        {renderEditableCell(customer, 'first_name', customer.first_name)}
                                                        {renderEditableCell(customer, 'last_name', customer.last_name)}
                                                    </div>
                                                </div>
                                                <div className="text-[10px] text-gray-400 font-mono">@{customer.username}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 border-b-2 border-gray-300">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2 text-sm text-gray-600">
                                                <span>📧</span>
                                                {renderEditableCell(customer, 'email', customer.email)}
                                            </div>
                                            {customer.phone_number && (
                                                <div className="flex items-center gap-2 text-[10px] text-gray-400">
                                                    <span>📞</span>
                                                    {renderEditableCell(customer, 'phone_number', customer.phone_number)}
                                                </div>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-center border-b-2 border-gray-300">
                                        {editingCell?.id === customer.id && editingCell?.field === 'role' ? (
                                            renderEditableCell(customer, 'role', customer.role || ROLE.CUSTOMER)
                                        ) : (
                                            <span
                                                onClick={() => handleEditStart(customer.id, 'role', customer.role || ROLE.CUSTOMER)}
                                                className={`text-[10px] font-black uppercase cursor-pointer hover:underline ${customer.role?.toLowerCase() === ROLE.ADMIN
                                                    ? 'text-[var(--brand-error)]'
                                                    : 'text-[var(--brand-primary)]'
                                                    }`}
                                            >
                                                {customer.role || ROLE.CUSTOMER}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-center border-b-2 border-gray-300">
                                        <div className="inline-flex items-center gap-1.5 px-3 py-1">
                                            <span className="font-black text-gray-700 text-xs">{customer.order_count || 0}</span>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right border-b-2 border-gray-300">
                                        <div className="flex justify-end gap-1">
                                            <button
                                                onClick={() => onView(customer.id)}
                                                className="admin-action-btn btn-icon--view"
                                                data-help-id="customers-action-view"
                                            ></button>
                                            <button
                                                onClick={() => onEdit(customer.id)}
                                                className="admin-action-btn btn-icon--edit"
                                                data-help-id="customers-action-edit"
                                            ></button>
                                            <button
                                                onClick={() => onDelete(customer.id, `${customer.first_name} ${customer.last_name}`)}
                                                className="admin-action-btn btn-icon--delete"
                                                data-help-id="customers-action-delete"
                                            ></button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
};
