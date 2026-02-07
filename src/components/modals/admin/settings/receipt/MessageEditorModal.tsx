import React from 'react';
import { IReceiptMessage } from '../../../../../hooks/admin/useReceiptSettings.js';
import { useUnsavedChangesCloseGuard } from '../../../../../hooks/useUnsavedChangesCloseGuard.js';

interface MessageEditorModalProps {
    editingMessage: Partial<IReceiptMessage>;
    setEditingMessage: (msg: Partial<IReceiptMessage> | null) => void;
    onSave: () => Promise<boolean>;
    isLoading: boolean;
}

export const MessageEditorModal: React.FC<MessageEditorModalProps> = ({
    editingMessage,
    setEditingMessage,
    onSave,
    isLoading
}) => {
    // Initial state to track dirtyness
    const [initialState] = React.useState(editingMessage);
    const isDirty = JSON.stringify(initialState) !== JSON.stringify(editingMessage);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        void onSave();
    };
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty,
        isBlocked: isLoading,
        onClose: () => setEditingMessage(null),
        onSave,
        closeAfterSave: true
    });

    return (
        <div className="fixed inset-0 z-[var(--wf-z-modal)] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm overflow-y-auto animate-in fade-in duration-200">
            <div className="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-lg overflow-hidden animate-in zoom-in-95 slide-in-from-bottom-4 duration-300">
                <div className="px-8 py-6 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="text-lg font-black text-gray-900 uppercase tracking-tight">
                        {editingMessage.id ? 'Modify Trigger' : 'New Trigger Message'}
                    </h3>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleSubmit}
                            disabled={isLoading}
                            className={`btn-icon btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="common-save"
                            type="button"
                        />
                        <button
                            onClick={() => { void attemptClose(); }}
                            className="btn-icon btn-icon--close"
                            data-help-id="common-close"
                            type="button"
                        />
                    </div>
                </div>
                <form onSubmit={handleSubmit} className="p-8 space-y-6">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest pl-1">Category</label>
                            <select
                                value={editingMessage.type}
                                onChange={e => setEditingMessage({ ...editingMessage, type: e.target.value as IReceiptMessage['type'] })}
                                className="form-select w-full py-2 text-xs font-bold rounded-xl border-gray-100 bg-gray-50 focus:bg-white transition-all"
                            >
                                <option value="default">Global Default</option>
                                <option value="shipping">Shipping Related</option>
                                <option value="items">Specific Item</option>
                                <option value="categories">Whole Category</option>
                            </select>
                        </div>
                        <div className="space-y-1">
                            <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest pl-1">Condition Value</label>
                            <input
                                type="text"
                                value={editingMessage.condition_value || ''}
                                onChange={e => setEditingMessage({ ...editingMessage, condition_value: e.target.value })}
                                className="form-input w-full py-2 text-xs font-bold rounded-xl border-gray-100 bg-gray-50 focus:bg-white transition-all shadow-inner"
                                placeholder="e.g. USPS or WF-GEN-001"
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest pl-1">Message Label</label>
                        <input
                            type="text" required
                            value={editingMessage.title || ''}
                            onChange={e => setEditingMessage({ ...editingMessage, title: e.target.value })}
                            className="form-input w-full py-3 px-4 font-black text-gray-900 border-gray-100 bg-gray-50 focus:bg-white transition-all rounded-xl shadow-inner"
                            placeholder="Internal identification name"
                        />
                    </div>

                    <div className="space-y-1">
                        <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest pl-1">Receipt Content</label>
                        <textarea
                            required
                            value={editingMessage.content || ''}
                            onChange={e => setEditingMessage({ ...editingMessage, content: e.target.value })}
                            rows={4}
                            className="form-input w-full p-4 text-sm bg-gray-50 border-transparent focus:bg-white transition-all rounded-2xl shadow-inner resize-none"
                            placeholder="The text customers will see on their receipt..."
                        />
                    </div>

                    <label className="flex items-center gap-3 p-3 bg-[var(--brand-accent)]/5 rounded-xl border border-[var(--brand-accent)]/10 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={editingMessage.is_active}
                            onChange={e => setEditingMessage({ ...editingMessage, is_active: e.target.checked })}
                            className="w-4 h-4 rounded text-[var(--brand-accent)] focus:ring-[var(--brand-accent)]"
                        />
                        <span className="text-xs font-bold text-[var(--brand-accent)]/80">Active and visible on receipts</span>
                    </label>
                </form>
            </div>
        </div>
    );
};
