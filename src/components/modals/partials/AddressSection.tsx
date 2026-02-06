import React from 'react';
import { ICustomerAddress } from '../../../types/admin/customers.js';

interface AddressSectionProps {
    addresses: ICustomerAddress[];
    editingAddress: Partial<ICustomerAddress> | null;
    setEditingAddress: (addr: Partial<ICustomerAddress> | null) => void;
    handleSaveAddress: (e: React.FormEvent) => void;
    handleDeleteAddress: (id: string | number) => void;
    isAddressLoading: boolean;
}

export const AddressSection: React.FC<AddressSectionProps> = ({
    addresses,
    editingAddress,
    setEditingAddress,
    handleSaveAddress,
    handleDeleteAddress,
    isAddressLoading
}) => {
    return (
        <section className="space-y-6">
            <div className="flex items-center justify-between border-b pb-2">
                <div className="flex items-center gap-2 text-xs font-black text-gray-400 uppercase tracking-widest">
                    <span className="admin-view btn-icon--globe" style={{ fontSize: '14px' }} /> Shipping Addresses
                </div>
                {!editingAddress && (
                    <button
                        onClick={() => setEditingAddress({ address_name: 'Home', is_default: false })}
                        className="btn btn-xs btn-secondary flex items-center gap-1"
                    >
                        <span className="admin-action-btn btn-icon--add" style={{ width: '16px', height: '16px', fontSize: '12px' }} /> Add
                    </button>
                )}
            </div>

            {editingAddress && (
                <div className="p-4 bg-gray-50 border border-[var(--brand-primary)]/20 rounded-2xl space-y-4 animate-in slide-in-from-top-2">
                    <div className="font-bold text-sm text-[var(--brand-primary)] flex items-center gap-2">
                        <span className="admin-view btn-icon--settings" style={{ fontSize: '16px' }} /> {editingAddress.id ? 'Edit Address' : 'New Address'}
                    </div>
                    <form onSubmit={handleSaveAddress} className="grid grid-cols-2 gap-4">
                        <div className="col-span-2 flex flex-col">
                            <label>Label</label>
                            <input
                                type="text" required
                                value={editingAddress.address_name || ''}
                                onChange={e => setEditingAddress({ ...editingAddress, address_name: e.target.value })}
                                className="form-input"
                            />
                        </div>
                        <div className="flex flex-col">
                            <label>Line 1</label>
                            <input
                                type="text" required
                                value={editingAddress.address_line_1 || ''}
                                onChange={e => setEditingAddress({ ...editingAddress, address_line_1: e.target.value })}
                                className="form-input"
                            />
                        </div>
                        <div className="flex flex-col">
                            <label>Line 2</label>
                            <input
                                type="text"
                                value={editingAddress.address_line_2 || ''}
                                onChange={e => setEditingAddress({ ...editingAddress, address_line_2: e.target.value })}
                                className="form-input"
                            />
                        </div>
                        <div className="flex flex-col">
                            <label>City</label>
                            <input
                                type="text" required
                                value={editingAddress.city || ''}
                                onChange={e => setEditingAddress({ ...editingAddress, city: e.target.value })}
                                className="form-input"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-2">
                            <div className="flex flex-col">
                                <label>State</label>
                                <input
                                    type="text" required
                                    value={editingAddress.state || ''}
                                    onChange={e => setEditingAddress({ ...editingAddress, state: e.target.value })}
                                    className="form-input"
                                />
                            </div>
                            <div className="flex flex-col">
                                <label>ZIP</label>
                                <input
                                    type="text" required
                                    value={editingAddress.zip_code || ''}
                                    onChange={e => setEditingAddress({ ...editingAddress, zip_code: e.target.value })}
                                    className="form-input"
                                />
                            </div>
                        </div>
                        <div className="col-span-2 flex items-center justify-between pt-2">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={Boolean(editingAddress.is_default)}
                                    onChange={e => setEditingAddress({ ...editingAddress, is_default: e.target.checked })}
                                    className="rounded text-[var(--brand-primary)]"
                                />
                                <span className="text-xs font-bold text-gray-700">Set Default</span>
                            </label>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => setEditingAddress(null)}
                                    className="btn btn-xs btn-secondary hover:bg-[var(--brand-secondary)] hover:text-white transition-colors"
                                    data-help-id="modal-cancel"
                                >
                                    Cancel
                                </button>
                                <button type="submit" className="btn btn-xs btn-primary bg-[var(--brand-primary)] border-[var(--brand-primary)]">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            )}

            <div className="space-y-3 max-h-[400px] overflow-y-auto pr-2">
                {addresses.map((addr) => (
                    <div key={addr.id} className={`p-4 border rounded-2xl space-y-2 transition-all ${addr.is_default ? 'bg-[var(--brand-primary)]/5 border-[var(--brand-primary)]/20' : 'bg-white hover:border-gray-300'}`}>
                        <div className="flex items-start justify-between">
                            <div className="text-sm font-black text-gray-900 uppercase tracking-tight">
                                {addr.address_name}
                                {Boolean(addr.is_default) && <span className="ml-2 text-[9px] text-[var(--brand-primary)]">(Default)</span>}
                            </div>
                            <div className="flex gap-1">
                                <button onClick={() => setEditingAddress(addr)} className="admin-action-btn btn-icon--edit p-1 text-gray-400 hover:text-[var(--brand-primary)] transition-colors" />
                                <button
                                    onClick={() => handleDeleteAddress(addr.id)}
                                    className="admin-action-btn btn-icon--delete p-1 text-gray-400 hover:bg-[var(--brand-secondary)] hover:text-white rounded-lg transition-colors"
                                    data-help-id="address-delete"
                                />
                            </div>
                        </div>
                        <div className="text-xs text-gray-600 leading-relaxed">
                            {addr.address_line_1}<br />
                            {addr.address_line_2 && <>{addr.address_line_2}<br /></>}
                            {addr.city}, {addr.state} {addr.zip_code}
                        </div>
                    </div>
                ))}
                {addresses.length === 0 && !isAddressLoading && !editingAddress && (
                    <div className="py-12 flex flex-col items-center justify-center border-2 border-dashed border-gray-100 rounded-2xl text-gray-400">
                        <span className="admin-view btn-icon--globe opacity-10 mb-2" style={{ fontSize: '32px' }} />
                        <p className="text-sm italic">No addresses saved</p>
                    </div>
                )}
            </div>
        </section>
    );
};
