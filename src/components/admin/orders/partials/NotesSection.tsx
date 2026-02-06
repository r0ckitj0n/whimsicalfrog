import React, { useState } from 'react';
import { IOrder, IOrderNote } from '../../../../types/admin/orders.js';
import { formatDateTime } from '../../../../core/date-utils.js';

interface NotesSectionProps {
    order: IOrder;
    setOrder: (order: IOrder) => void;
    mode: 'view' | 'edit';
    onAddNote: (type: 'fulfillment' | 'payment', text: string) => void;
}

export const NotesSection: React.FC<NotesSectionProps> = ({ order, mode, onAddNote }) => {
    const [newFulfillmentNote, setNewFulfillmentNote] = useState('');
    const [newPaymentNote, setNewPaymentNote] = useState('');
    const [localNotes, setLocalNotes] = useState<IOrderNote[]>(order.notes || []);

    // Sync local state with prop updates (e.g. initial load or parent refresh)
    React.useEffect(() => {
        if (order.notes) {
            setLocalNotes(prev => {
                // Keep locally added notes that haven't been confirmed by the server yet.
                // We identify these by their numeric IDs (Date.now() timestamps).
                const localOptimisticNotes = prev.filter(n => typeof n.id === 'number');

                // Filter out any local notes that have now appeared in the server response
                // We compare by text and type to see if the server has 'captured' our local note.
                const serverNoteKeys = new Set(order.notes?.map(n => `${n.note_type}:${n.note_text}`) || []);
                const uniqueLocalNotes = localOptimisticNotes.filter(n =>
                    !serverNoteKeys.has(`${n.note_type}:${n.note_text}`)
                );

                // Combine and ensure newest-first sorting.
                // This ensures local notes stay at the top if they are newest.
                const combined = [...uniqueLocalNotes, ...(order.notes || [])];

                // Remove duplicates by ID and content key to be absolutely sure
                const seen = new Set();
                return combined.filter(n => {
                    const key = `${n.id}:${n.note_type}:${n.note_text}`;
                    if (seen.has(key)) return false;
                    seen.add(key);
                    return true;
                }).sort((a, b) =>
                    new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
                );
            });
        }
    }, [order.notes]);

    const fulfillmentNotes = localNotes.filter(n => n.note_type === 'fulfillment');
    const paymentNotes = localNotes.filter(n => n.note_type === 'payment');

    const handleSaveFulfillment = () => {
        if (!newFulfillmentNote.trim()) return;

        // Instant local update
        const tempNote: IOrderNote = {
            id: Date.now(),
            order_id: Number(order.id),
            note_type: 'fulfillment',
            note_text: newFulfillmentNote,
            author_username: 'Admin',
            created_at: new Date().toISOString()
        };
        setLocalNotes(prev => [tempNote, ...prev]);

        onAddNote('fulfillment', newFulfillmentNote);
        setNewFulfillmentNote('');
    };

    const handleSavePayment = () => {
        if (!newPaymentNote.trim()) return;

        // Instant local update
        const tempNote: IOrderNote = {
            id: Date.now(),
            order_id: Number(order.id),
            note_type: 'payment',
            note_text: newPaymentNote,
            author_username: 'Admin',
            created_at: new Date().toISOString()
        };
        setLocalNotes(prev => [tempNote, ...prev]);

        onAddNote('payment', newPaymentNote);
        setNewPaymentNote('');
    };

    const renderNoteList = (notes: IOrderNote[]) => (
        <div className="space-y-2 mt-2 max-h-[150px] overflow-y-auto pr-1">
            {notes.length === 0 ? (
                <div className="text-[10px] text-gray-400 italic">No notes recorded</div>
            ) : (
                notes.map(note => (
                    <div key={note.id} className="p-2 bg-white border border-gray-100 rounded-lg shadow-sm">
                        <div className="text-[10px] text-gray-700 leading-relaxed break-words">{note.note_text}</div>
                        <div className="text-[9px] text-gray-400 mt-1 flex justify-between">
                            <span>{note.author_username || 'Admin'}</span>
                            <span>{(() => {
                                const dt = formatDateTime(note.created_at);
                                return `${dt.date} ${dt.time}`;
                            })()}</span>
                        </div>
                    </div>
                ))
            )}
        </div>
    );

    return (
        <section className="admin-section--orange rounded-2xl shadow-sm overflow-hidden wf-contained-section">
            <div className="px-6 py-4">
                <h5 className="text-xs font-black uppercase tracking-widest">Order Notes</h5>
            </div>
            <div className="p-4 flex flex-col gap-1">
                <div className="order-notes-grid">
                    {/* Fulfillment Column */}
                    <div className="flex flex-col gap-3 min-w-0">
                        {mode === 'edit' && (
                            <div className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Add Fulfillment Note</label>
                                <div className="relative">
                                    <textarea
                                        value={newFulfillmentNote}
                                        onChange={e => setNewFulfillmentNote(e.target.value)}
                                        placeholder="Type note here..."
                                        className="form-input w-full text-xs min-h-[80px] resize-none pr-12"
                                    />
                                    {newFulfillmentNote.trim() && (
                                        <button
                                            type="button"
                                            onClick={handleSaveFulfillment}
                                            className="absolute bottom-2 right-2 btn-note-save"
                                        >
                                            Save Note
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}
                        <div className="min-w-0">
                            <div className="text-[9px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50 pb-1">Fulfillment History</div>
                            {renderNoteList(fulfillmentNotes)}
                        </div>
                    </div>

                    {/* Payment Column */}
                    <div className="flex flex-col gap-3 min-w-0">
                        {mode === 'edit' && (
                            <div className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Add Payment Note</label>
                                <div className="relative">
                                    <textarea
                                        value={newPaymentNote}
                                        onChange={e => setNewPaymentNote(e.target.value)}
                                        placeholder="Type note here..."
                                        className="form-input w-full text-xs min-h-[80px] resize-none pr-12"
                                    />
                                    {newPaymentNote.trim() && (
                                        <button
                                            type="button"
                                            onClick={handleSavePayment}
                                            className="absolute bottom-2 right-2 btn-note-save"
                                        >
                                            Save Note
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}
                        <div className="min-w-0">
                            <div className="text-[9px] font-bold text-gray-400 uppercase tracking-widest border-b border-gray-50 pb-1">Payment History</div>
                            {renderNoteList(paymentNotes)}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
};
