import React from 'react';
import { IAutomationPlaybook } from '../../../../../hooks/admin/useAutomation.js';

interface AutomationEditorModalProps {
    editingIndex: number;
    editBuffer: IAutomationPlaybook;
    setEditBuffer: (p: IAutomationPlaybook) => void;
    onSave: () => void;
    onClose: () => void;
    isLoading: boolean;
}

export const AutomationEditorModal: React.FC<AutomationEditorModalProps> = ({
    editingIndex,
    editBuffer,
    setEditBuffer,
    onSave,
    onClose,
    isLoading
}) => {
    // Initial state to track dirtyness
    const [initialState] = React.useState(editBuffer);
    const isDirty = JSON.stringify(initialState) !== JSON.stringify(editBuffer);

    return (
        <div className="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95">
                <div className="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="font-bold text-gray-800">
                        {editingIndex === -1 ? 'New Automation Logic' : 'Edit Automation'}
                    </h3>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={onSave}
                            disabled={isLoading}
                            className={`btn-icon btn-icon--save ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="common-save"
                            type="button"
                        />
                        <button
                            onClick={onClose}
                            className="btn-icon btn-icon--close"
                            data-help-id="common-close"
                            type="button"
                        />
                    </div>
                </div>
                <div className="p-6 space-y-4">
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Logic Name</label>
                        <input
                            type="text"
                            value={editBuffer.name}
                            onChange={e => setEditBuffer({ ...editBuffer, name: e.target.value })}
                            className="form-input w-full"
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="text-xs font-bold text-gray-500 uppercase">Run Cadence</label>
                            <input
                                type="text"
                                value={editBuffer.cadence}
                                onChange={e => setEditBuffer({ ...editBuffer, cadence: e.target.value })}
                                className="form-input w-full"
                                placeholder="e.g., Daily 2:00 AM"
                            />
                        </div>
                        <div className="flex items-end pb-2">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={Boolean(editBuffer.active)}
                                    onChange={e => setEditBuffer({ ...editBuffer, active: e.target.checked })}
                                    className="rounded text-[var(--brand-secondary)]"
                                />
                                <span className="text-sm font-bold text-gray-700">Enabled</span>
                            </label>
                        </div>
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Trigger Criteria</label>
                        <textarea
                            value={editBuffer.trigger}
                            onChange={e => setEditBuffer({ ...editBuffer, trigger: e.target.value })}
                            className="form-input w-full h-24 text-sm"
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">System Command / Action</label>
                        <textarea
                            value={editBuffer.action}
                            onChange={e => setEditBuffer({ ...editBuffer, action: e.target.value })}
                            className="form-input w-full h-24 text-sm font-mono"
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Notes / Status</label>
                        <input
                            type="text"
                            value={editBuffer.status}
                            onChange={e => setEditBuffer({ ...editBuffer, status: e.target.value })}
                            className="form-input w-full text-sm"
                        />
                    </div>
                </div>
            </div>
        </div>
    );
};
