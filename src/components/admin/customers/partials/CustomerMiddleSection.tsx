import React, { useEffect, useState, useCallback } from 'react';
import { ICustomer, ICustomerNote } from '../../../../types/admin/customers.js';
import { useCustomers } from '../../../../hooks/admin/useCustomers.js';

interface CustomerMiddleSectionProps {
    customer: ICustomer;
    mode: 'view' | 'edit';
    onChange: (data: Partial<ICustomer>) => void;
}

export const CustomerMiddleSection: React.FC<CustomerMiddleSectionProps> = ({ customer, mode, onChange }) => {
    const { fetchCustomerNotes, addCustomerNote } = useCustomers();
    const [notes, setNotes] = useState<ICustomerNote[]>([]);
    const [newNote, setNewNote] = useState('');
    const [isSavingNote, setIsSavingNote] = useState(false);

    const loadNotes = useCallback(async () => {
        if (!customer.id) return;
        const data = await fetchCustomerNotes(customer.id);
        setNotes(data);
    }, [customer.id, fetchCustomerNotes]);

    useEffect(() => {
        loadNotes();
    }, [loadNotes]);

    const handleAddNote = async () => {
        if (!newNote.trim() || !customer.id) return;
        setIsSavingNote(true);
        const res = await addCustomerNote(customer.id, newNote);
        if (res.success) {
            setNewNote('');
            loadNotes();
        }
        setIsSavingNote(false);
    };

    const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
        const { name, value, type } = e.target;
        const val = type === 'checkbox' ? ((e.target as HTMLInputElement).checked ? '1' : '0') : value;
        onChange({ [name]: val });
    };

    const renderField = (label: string, name: keyof ICustomer, type: string = 'text', options?: { value: string, label: string }[], description?: string) => {
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
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id={String(name)}
                                name={String(name)}
                                className="w-4 h-4 text-[var(--brand-primary)] border-gray-300 rounded focus:ring-[var(--brand-primary)]"
                                checked={value === '1'}
                                onChange={handleChange}
                            />
                            {description && (
                                <span className="text-[10px] text-gray-400 italic font-medium">{description}</span>
                            )}
                        </div>
                    ) : (
                        <input
                            type={type}
                            id={String(name)}
                            name={String(name)}
                            className="form-input w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)] outline-none transition-all"
                            value={value}
                            onChange={handleChange}
                        />
                    )
                )}
                {!isReadonly && description && type !== 'checkbox' && (
                    <div className="mt-1 text-[10px] text-gray-400 italic font-medium">{description}</div>
                )}
            </div>
        );
    };

    return (
        <>
            {/* Account Flags - ORANGE (same as column) */}
            <section className="admin-section--orange rounded-2xl shadow-sm overflow-hidden wf-contained-section">
                <div className="px-6 py-4">
                    <h5 className="text-xs font-black uppercase tracking-widest">Account Flags</h5>
                </div>
                <div className="p-4 flex flex-col gap-1">
                    {renderField('Status', 'status', 'select', [
                        { value: 'active', label: 'Active' },
                        { value: 'suspended', label: 'Suspended' },
                        { value: 'closed', label: 'Closed' }
                    ])}
                    {renderField('VIP', 'vip', 'checkbox', undefined, '(Gives the customer free shipping)')}
                    {renderField('Tax Exempt', 'tax_exempt', 'checkbox')}
                </div>
            </section>

            {/* CRM - ORANGE */}
            <section className="admin-section--orange rounded-2xl shadow-sm overflow-hidden wf-contained-section">
                <div className="px-6 py-4">
                    <h5 className="text-xs font-black uppercase tracking-widest">CRM</h5>
                </div>
                <div className="p-4 flex flex-col gap-1">
                    {renderField('Referral Source', 'referral_source')}
                    {renderField('Birthdate', 'birthdate', 'date')}
                    <div>
                        {renderField('Tags (comma separated)', 'tags')}
                    </div>
                </div>
            </section>

            {/* Internal Notes - ORANGE (same as column) */}
            <section className="admin-section--orange rounded-2xl shadow-sm overflow-hidden wf-contained-section">
                <div className="px-6 py-4 flex items-center justify-between">
                    <h5 className="text-xs font-black uppercase tracking-widest">Internal Notes</h5>
                    <span className="text-[10px] text-gray-400 font-bold bg-white/50 px-2 py-0.5 rounded-full">{notes.length} log entry</span>
                </div>
                <div className="p-4 flex flex-col gap-4">
                    {/* Add Note Area */}
                    <div className="flex flex-col gap-2">
                        <textarea
                            className="form-input w-full px-3 py-2 bg-white border border-gray-100 rounded-lg text-sm focus:ring-2 focus:ring-[var(--brand-primary)]/20 focus:border-[var(--brand-primary)] outline-none transition-all placeholder:text-gray-300"
                            placeholder="Add internal note..."
                            rows={2}
                            value={newNote}
                            onChange={(e) => setNewNote(e.target.value)}
                        />
                        <button
                            onClick={handleAddNote}
                            disabled={!newNote.trim() || isSavingNote}
                            className="self-end px-3 py-1 bg-white border border-gray-200 rounded-lg text-[10px] font-black uppercase tracking-wider text-gray-500 hover:bg-gray-50 hover:border-gray-300 disabled:opacity-50 transition-all shadow-sm"
                        >
                            {isSavingNote ? 'Saving...' : 'Add Note +'}
                        </button>
                    </div>

                    {/* Notes History */}
                    <div className="flex flex-col gap-3 max-h-[300px] overflow-y-auto pr-1 customer-notes-history">
                        {notes.length === 0 ? (
                            <div className="text-center py-6 text-gray-300 italic text-xs border border-dashed border-gray-100 rounded-xl">
                                No internal notes yet.
                            </div>
                        ) : (
                            notes.map((note) => (
                                <div key={note.id} className="bg-white/60 p-3 rounded-xl border border-white/40 shadow-[0_2px_10px_-4px_rgba(0,0,0,0.05)]">
                                    <div className="flex justify-between items-center mb-1.5">
                                        <span className="text-[10px] font-black text-gray-500 uppercase tracking-tight">{note.author_username}</span>
                                        <span className="text-[9px] text-gray-400 font-medium">{new Date(note.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })}</span>
                                    </div>
                                    <p className="text-xs text-gray-700 leading-relaxed whitespace-pre-wrap">{note.note_text}</p>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </section>
        </>
    );
};
