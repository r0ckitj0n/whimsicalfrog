import React from 'react';
import { ICustomer } from '../../../../types/admin/customers.js';

interface CustomerProfileSectionProps {
    customer: ICustomer;
    mode: 'view' | 'edit';
    onChange: (data: Partial<ICustomer>) => void;
}

export const CustomerProfileSection: React.FC<CustomerProfileSectionProps> = ({ customer, mode, onChange }) => {
    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        const { name, value, type } = e.target;
        const val = type === 'checkbox' ? ((e.target as HTMLInputElement).checked ? '1' : '0') : value;
        onChange({ [name]: val });
    };

    const renderField = (label: string, name: keyof ICustomer, type: string = 'text', options?: { value: string, label: string }[]) => {
        const value = customer[name] as string || '';
        const isReadonly = mode === 'view';

        return (
            <div className="form-group mb-4">
                <label htmlFor={String(name)} className="form-label block text-[10px] font-bold text-gray-400 uppercase mb-1">{label}</label>
                {isReadonly ? (
                    <div className="text-sm text-gray-900 px-3 py-2 bg-gray-50/50 rounded-lg border border-transparent">
                        {type === 'checkbox' ? (value === '1' ? 'Yes' : 'No') : (value || '—')}
                    </div>
                ) : (
                    type === 'select' ? (
                        <select
                            id={String(name)}
                            name={String(name)}
                            className="form-select w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)] outline-none transition-all"
                            value={value}
                            onChange={handleChange}
                            required={['first_name', 'last_name', 'email', 'username'].includes(String(name))}
                        >
                            {options?.map(opt => <option key={opt.value} value={opt.value}>{opt.label}</option>)}
                        </select>
                    ) : type === 'textarea' ? (
                        <textarea
                            id={String(name)}
                            name={String(name)}
                            className="form-input w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)] outline-none transition-all"
                            rows={3}
                            value={value}
                            onChange={handleChange}
                        />
                    ) : type === 'checkbox' ? (
                        <input
                            type="checkbox"
                            id={String(name)}
                            name={String(name)}
                            className="w-4 h-4 text-[var(--brand-primary)] border-gray-300 rounded focus:ring-[var(--brand-primary)]"
                            checked={value === '1'}
                            onChange={handleChange}
                        />
                    ) : (
                        <input
                            type={type}
                            id={String(name)}
                            name={String(name)}
                            className="form-input w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)] outline-none transition-all"
                            value={value}
                            onChange={handleChange}
                            required={['first_name', 'last_name', 'email', 'username'].includes(String(name))}
                        />
                    )
                )}
            </div>
        );
    };

    return (
        <>
            {/* Personal Information - GREEN */}
            <section className="admin-section--green rounded-2xl shadow-sm overflow-hidden wf-contained-section">
                <div className="px-6 py-4">
                    <h5 className="text-xs font-black uppercase tracking-widest">Personal Information</h5>
                </div>
                <div className="p-4 flex flex-col gap-1">
                    {renderField('First Name', 'first_name')}
                    {renderField('Last Name', 'last_name')}
                    {renderField('Username', 'username')}
                    {renderField('Password', 'password', 'password')}
                    {renderField('Email', 'email', 'email')}
                    {renderField('Role', 'role', 'select', [
                        { value: 'customer', label: 'Customer' },
                        { value: 'admin', label: 'Admin' }
                    ])}
                    {renderField('Phone Number', 'phone_number', 'tel')}
                </div>
            </section>

            {/* Customer Profile - GREEN (same as column) */}
            <section className="admin-section--green rounded-2xl shadow-sm overflow-hidden wf-contained-section">
                <div className="px-6 py-4">
                    <h5 className="text-xs font-black uppercase tracking-widest">Customer Profile</h5>
                </div>
                <div className="p-4 flex flex-col gap-1">
                    {renderField('Company', 'company')}
                    {renderField('Job Title', 'job_title')}
                </div>
            </section>

            {/* Preferences - GREEN */}
            <section className="admin-section--green rounded-2xl shadow-sm overflow-hidden wf-contained-section">
                <div className="px-6 py-4">
                    <h5 className="text-xs font-black uppercase tracking-widest">Preferences</h5>
                </div>
                <div className="p-4 flex flex-col gap-1">
                    {renderField('Preferred Contact', 'preferred_contact', 'select', [
                        { value: '', label: '—' },
                        { value: 'email', label: 'Email' },
                        { value: 'phone', label: 'Phone' },
                        { value: 'sms', label: 'SMS' }
                    ])}
                    {renderField('Preferred Language', 'preferred_language')}
                    {renderField('Marketing Opt-in', 'marketing_opt_in', 'checkbox')}
                </div>
            </section>
        </>
    );
};
