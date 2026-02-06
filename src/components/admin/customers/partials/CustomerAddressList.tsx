import React from 'react';
import { ICustomerAddress } from '../../../../types/admin/customers.js';

interface CustomerAddressListProps {
    addresses: ICustomerAddress[];
    mode: 'view' | 'edit';
    editingAddress: Partial<ICustomerAddress> | null;
    onAddClick: () => void;
    onEditClick: (addr: ICustomerAddress) => void;
    onDeleteClick: (id: string | number) => void;
    handleSaveAddress: (e: React.FormEvent) => void;
    setEditingAddress: (addr: Partial<ICustomerAddress> | null) => void;
    isLoading: boolean;
}

export const CustomerAddressList: React.FC<CustomerAddressListProps> = ({
    addresses,
    mode,
    editingAddress,
    onAddClick,
    onEditClick,
    onDeleteClick,
    handleSaveAddress,
    setEditingAddress,
    isLoading
}) => {
    return (
        <section className="admin-section--orange rounded-2xl p-4 wf-contained-section">
            <div className="flex items-center justify-between pb-3 mb-4">
                <div className="flex items-center gap-2 text-xs font-black uppercase tracking-widest">
                    Saved Addresses ({addresses.length})
                </div>
                {mode === 'edit' && !editingAddress && (
                    <button
                        onClick={onAddClick}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="customer-action-add-address"
                    ></button>
                )}
            </div>

            {editingAddress && (
                <div className="p-4 bg-[var(--brand-primary)]/5 border border-[var(--brand-primary)]/20 rounded-2xl space-y-4 animate-in slide-in-from-top-2">
                    <div className="font-bold text-sm text-[var(--brand-primary)] flex items-center gap-2">
                        <span>🏠</span> {editingAddress.id ? 'Edit Address' : 'New Address'}
                    </div>
                    <form onSubmit={handleSaveAddress} className="flex flex-col gap-2">
                        <div className="space-y-1">
                            <label className="text-[10px] font-bold text-gray-500 uppercase">Label (e.g. Home, Office)</label>
                            <input
                                type="text" required
                                value={editingAddress.address_name || ''}
                                onChange={e => setEditingAddress({ ...editingAddress, address_name: e.target.value })}
                                className="form-input w-full py-1.5 text-sm"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-[10px] font-bold text-gray-500 uppercase">Line 1</label>
                            <input
                                type="text" required
                                value={editingAddress.address_line_1 || ''}
                                onChange={e => setEditingAddress({ ...editingAddress, address_line_1: e.target.value })}
                                className="form-input w-full py-1.5 text-sm"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-[10px] font-bold text-gray-500 uppercase">Line 2</label>
                            <input
                                type="text"
                                value={editingAddress.address_line_2 || ''}
                                onChange={e => setEditingAddress({ ...editingAddress, address_line_2: e.target.value })}
                                className="form-input w-full py-1.5 text-sm"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-[10px] font-bold text-gray-500 uppercase">City</label>
                            <input
                                type="text" required
                                value={editingAddress.city || ''}
                                onChange={e => setEditingAddress({ ...editingAddress, city: e.target.value })}
                                className="form-input w-full py-1.5 text-sm"
                            />
                        </div>
                        <div className="flex gap-2">
                            <div className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase">State</label>
                                <input
                                    type="text" required
                                    value={editingAddress.state || ''}
                                    onChange={e => setEditingAddress({ ...editingAddress, state: e.target.value })}
                                    className="form-input w-full py-1.5 text-sm"
                                />
                            </div>
                            <div className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase">ZIP</label>
                                <input
                                    type="text" required
                                    value={editingAddress.zip_code || ''}
                                    onChange={e => setEditingAddress({ ...editingAddress, zip_code: e.target.value })}
                                    className="form-input w-full py-1.5 text-sm"
                                />
                            </div>
                        </div>
                        <div className="flex items-center justify-between pt-2">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={Boolean(editingAddress.is_default)}
                                    onChange={e => setEditingAddress({ ...editingAddress, is_default: e.target.checked })}
                                    className="rounded text-[var(--brand-primary)]"
                                />
                                <span className="text-xs font-bold text-gray-700">Primary Address</span>
                            </label>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setEditingAddress(null)}
                                    className="admin-action-btn btn-icon--close"
                                    data-help-id="common-cancel"
                                ></button>
                                <button
                                    type="submit"
                                    className="admin-action-btn btn-icon--save"
                                    data-help-id="customer-action-save-address"
                                ></button>
                            </div>
                        </div>
                    </form>
                </div>
            )}

            <div className="flex flex-col gap-4">
                {addresses.map((addr) => (
                    <div key={addr.id} className={`p-4 border rounded-2xl space-y-3 transition-all ${addr.is_default ? 'bg-[var(--brand-primary)]/5 border-[var(--brand-primary)]/20' : 'bg-white hover:border-gray-300'}`}>
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-2">
                                <div className={`p-1.5 ${addr.is_default ? 'text-[var(--brand-primary)]' : 'text-gray-400'}`}>
                                    📍
                                </div>
                                <div className="text-sm font-black text-gray-900 uppercase tracking-tight">{addr.address_name}</div>
                            </div>
                            {mode === 'edit' && (
                                <div className="flex gap-1">
                                    <button
                                        onClick={() => onEditClick(addr)}
                                        className="admin-action-btn btn-icon--edit"
                                        data-help-id="customer-action-edit-address"
                                    ></button>
                                    {!addr.is_default && (
                                        <button
                                            onClick={() => onDeleteClick(addr.id)}
                                            className="admin-action-btn btn-icon--delete"
                                            data-help-id="customer-action-delete-address"
                                        ></button>
                                    )}
                                </div>
                            )}
                        </div>
                        <div className="text-xs text-gray-600 leading-relaxed">
                            {addr.address_line_1}<br />
                            {addr.address_line_2 && <>{addr.address_line_2}<br /></>}
                            {addr.city}, {addr.state} {addr.zip_code}
                        </div>
                        {Boolean(addr.is_default) && (
                            <div className="flex items-center gap-1.5 text-[9px] font-black text-[var(--brand-primary)] uppercase tracking-widest w-fit">
                                ✔️ Default Shipping
                            </div>
                        )}
                    </div>
                ))}
                {addresses.length === 0 && !isLoading && !editingAddress && (
                    <div className="col-span-full py-12 flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-2xl text-gray-400">
                        <span className="text-3xl opacity-10 mb-2">📍</span>
                        <p className="text-sm italic">No addresses registered</p>
                    </div>
                )}
            </div>
        </section>
    );
};
