import React from 'react';
import { ICustomerAddress } from '../../../types/admin/customers.js';

interface AddressSelectionProps {
    addresses: ICustomerAddress[];
    selected_address_id: string | number | null;
    onSelect: (id: string | number) => void;
}

export const AddressSelection: React.FC<AddressSelectionProps> = ({
    addresses,
    selected_address_id,
    onSelect
}) => {
    const selectedAddress = addresses.find(a => a.id === selected_address_id);
    const defaultAddress = addresses.find(a => a.is_default) || addresses[0];
    const displayAddress = selectedAddress || defaultAddress;

    return (
        <section className="bg-white rounded-2xl p-4 border border-gray-200">
            <div className="flex items-center justify-between mb-3">
                <h3 style={{
                    margin: 0,
                    color: 'var(--brand-secondary)',
                    fontFamily: "'Merienda', cursive",
                    fontSize: '1.25rem',
                    fontWeight: 700,
                    fontStyle: 'italic'
                }}>
                    Shipping address
                </h3>
                <button
                    type="button"
                    style={{
                        width: '32px',
                        height: '32px',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        border: '1px solid #d1d5db',
                        borderRadius: '8px',
                        background: '#ffffff',
                        cursor: 'pointer',
                        color: '#6b7280'
                    }}
                    data-help-id="address-add-new"
                    aria-label="Add new address"
                >
                    <span className="btn-icon--add" style={{ fontSize: '14px' }} aria-hidden="true" />
                </button>
            </div>

            {addresses.length > 0 ? (
                <div className="space-y-4">
                    {/* Display current address info */}
                    <div className="p-4 bg-gray-50 rounded-xl border border-gray-100">
                        <div style={{ color: 'var(--brand-secondary)', fontSize: '0.875rem', fontWeight: 600, marginBottom: '6px' }}>
                            Primary Address
                        </div>
                        {displayAddress && (
                            <div className="space-y-1">
                                <div className="text-base text-gray-800 font-medium">{displayAddress.address_line_1}</div>
                                <div className="text-sm text-gray-600">
                                    {displayAddress.city}, {displayAddress.state} {displayAddress.zip_code}
                                </div>
                                {Boolean(displayAddress.is_default) && (
                                    <div className="inline-block px-2 py-0.5 bg-brand-primary-bg text-brand-primary text-[10px] font-bold rounded uppercase mt-2 border border-brand-primary-border">
                                        Default
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Dropdown selection */}
                    <div className="space-y-2">
                        <div style={{ color: 'var(--brand-secondary)', fontSize: '0.875rem', fontWeight: 600 }}>
                            Choose address
                        </div>
                        <select
                            value={selected_address_id || ''}
                            onChange={(e) => onSelect(e.target.value)}
                            style={{
                                width: '100%',
                                minHeight: '52px',
                                padding: '12px 16px',
                                border: '1px solid #d1d5db',
                                borderRadius: '12px',
                                fontSize: '1rem',
                                color: '#374151',
                                background: '#ffffff',
                                cursor: 'pointer',
                                boxShadow: '0 2px 4px rgba(0,0,0,0.05)',
                                appearance: 'none',
                                backgroundImage: `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E")`,
                                backgroundRepeat: 'no-repeat',
                                backgroundPosition: 'right 16px center',
                                backgroundSize: '16px'
                            }}
                        >
                            {addresses.map((addr) => (
                                <option key={addr.id} value={addr.id}>
                                    {addr.address_name} – {addr.city}, {addr.state}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            ) : (
                <div className="p-4 bg-brand-warning-bg border border-brand-warning-border rounded-xl text-center">
                    <p className="text-sm font-semibold text-brand-warning">No Address Found</p>
                    <p className="text-xs text-brand-warning mt-1">Please add a shipping address in your account settings.</p>
                </div>
            )}
        </section>
    );
};
