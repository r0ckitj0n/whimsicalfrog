import React, { useState } from 'react';
import { INewsletterSubscriber } from '../../../../hooks/admin/useNewsletter.js';
import { formatDate } from '../../../../core/date-utils.js';

interface SubscriberTableProps {
    subscribers: INewsletterSubscriber[];
    onAdd: (email: string) => Promise<boolean>;
    onToggleStatus: (id: number, currentlyActive: boolean) => void;
    onDelete: (id: number, email: string) => void;
    isLoading: boolean;
}

export const SubscriberTable: React.FC<SubscriberTableProps> = ({
    subscribers,
    onAdd,
    onToggleStatus,
    onDelete,
    isLoading
}) => {
    const [showAddForm, setShowAddForm] = useState(false);
    const [newEmail, setNewEmail] = useState('');
    const [addError, setAddError] = useState<string | null>(null);

    const handleAddSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setAddError(null);
        if (!newEmail.trim()) return;

        const success = await onAdd(newEmail.trim());
        if (success) {
            setNewEmail('');
            setShowAddForm(false);
        } else {
            setAddError('Failed to add subscriber. Email may already exist.');
        }
    };

    return (
        <div className="space-y-4">
            {/* Header with Add Button */}
            <div className="flex items-center justify-between gap-4 w-full overflow-hidden">
                <div className="text-xs text-gray-500">
                    {subscribers.length} subscriber{subscribers.length !== 1 ? 's' : ''}
                </div>
                {!showAddForm && (
                    <button
                        onClick={() => setShowAddForm(true)}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="newsletter-add-subscriber"
                        type="button"
                        disabled={isLoading}
                    />
                )}
            </div>

            {/* Inline Add Form */}
            {showAddForm && (
                <form onSubmit={handleAddSubmit} className="flex items-center gap-3 p-4 bg-[var(--brand-primary)]/5 rounded-xl border border-[var(--brand-primary)]/20">
                    <input
                        type="email"
                        value={newEmail}
                        onChange={(e) => setNewEmail(e.target.value)}
                        placeholder="Enter email address..."
                        className="form-input flex-1 py-2"
                        autoFocus
                        required
                        disabled={isLoading}
                    />
                    <button
                        type="submit"
                        disabled={isLoading || !newEmail.trim()}
                        className="px-4 py-2 bg-[var(--brand-primary)] text-white rounded-lg 
                                   text-xs font-bold uppercase tracking-widest
                                   hover:bg-[var(--brand-primary)]/90 transition-all
                                   disabled:opacity-50"
                    >
                        {isLoading ? 'Adding...' : 'Add'}
                    </button>
                    <button
                        type="button"
                        onClick={() => { setShowAddForm(false); setNewEmail(''); setAddError(null); }}
                        className="px-4 py-2 bg-gray-200 text-gray-600 rounded-lg 
                                   text-xs font-bold uppercase tracking-widest
                                   hover:bg-gray-300 transition-all"
                    >
                        Cancel
                    </button>
                </form>
            )}
            {addError && (
                <div className="text-xs text-red-600 bg-red-50 px-4 py-2 rounded-lg border border-red-100">
                    {addError}
                </div>
            )}

            {subscribers.length === 0 ? (
                <div className="p-12 text-center text-gray-500 italic border rounded-xl bg-gray-50/50">
                    <span className="text-4xl block mb-3">📭</span>
                    No subscribers found.
                </div>
            ) : (
                <div className="overflow-x-auto border rounded-xl">
                    <table className="w-full text-sm text-left">
                        <thead className="bg-gray-50 text-gray-600 font-bold border-b">
                            <tr>
                                <th className="px-6 py-3">Email Address</th>
                                <th className="px-6 py-3">Joined On</th>
                                <th className="px-6 py-3 text-center">Status</th>
                                <th className="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {subscribers.map((sub) => (
                                <tr key={sub.id} className="hover:bg-gray-50 group">
                                    <td className="px-6 py-4 font-medium text-gray-900">{sub.email}</td>
                                    <td className="px-6 py-4 text-gray-500">{formatDate(sub.subscribed_at)}</td>
                                    <td className="px-6 py-4 text-center">
                                        <button
                                            onClick={() => onToggleStatus(sub.id, sub.is_active)}
                                            className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase cursor-pointer transition-all hover:scale-105 ${sub.is_active
                                                ? 'bg-[var(--brand-accent)]/10 text-[var(--brand-accent)] border border-[var(--brand-accent)]/20'
                                                : 'bg-gray-100 text-gray-500 border border-gray-200'
                                                }`}
                                            data-help-id={sub.is_active ? 'newsletter-unsubscribe-hint' : 'newsletter-subscribe-hint'}
                                            disabled={isLoading}
                                        >
                                            {sub.is_active ? 'Subscribed' : 'Unsubscribed'}
                                        </button>
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <button
                                            onClick={() => onDelete(sub.id, sub.email)}
                                            className="admin-action-btn btn-icon--delete"
                                            data-help-id="common-delete"
                                            type="button"
                                            disabled={isLoading}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};
