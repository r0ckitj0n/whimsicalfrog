import React from 'react';
import { DISCOUNT_TYPE } from '../../../../core/constants.js';
import { IDiscount } from '../../../../hooks/admin/useDiscounts.js';

interface DiscountEditorProps {
    editingIndex: number | null;
    editBuffer: IDiscount;
    onBufferChange: (buffer: IDiscount) => void;
    onSave: () => void;
    onCancel: () => void;
    isLoading: boolean;
}

export const DiscountEditor: React.FC<DiscountEditorProps> = ({
    editingIndex,
    editBuffer,
    onBufferChange,
    onSave,
    onCancel,
    isLoading
}) => {
    // Initial state to track dirtyness
    const [initialState] = React.useState(editBuffer);
    const isDirty = JSON.stringify(initialState) !== JSON.stringify(editBuffer);

    return (
        <div className="fixed inset-0 z-[var(--wf-z-modal)] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95">
                <div className="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="font-bold text-gray-800">
                        {editingIndex === -1 ? 'New Discount Code' : 'Edit Discount'}
                    </h3>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={onSave}
                            disabled={isLoading}
                            className={`btn-icon btn-icon--save ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="modal-save"
                            type="button"
                        />
                        <button
                            onClick={onCancel}
                            className="btn-icon btn-icon--close"
                            data-help-id="modal-close"
                            type="button"
                        />
                    </div>
                </div>
                <div className="p-6 space-y-4">
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Discount Code</label>
                        <input
                            type="text"
                            value={editBuffer.code}
                            onChange={e => onBufferChange({ ...editBuffer, code: e.target.value.toUpperCase() })}
                            className="form-input w-full font-mono"
                            placeholder="SUMMER25"
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="text-xs font-bold text-gray-500 uppercase">Type</label>
                            <div className="flex p-1 bg-gray-100 rounded-lg">
                                <button
                                    onClick={() => onBufferChange({ ...editBuffer, type: DISCOUNT_TYPE.PERCENTAGE })}
                                    className={`flex-1 flex items-center justify-center gap-1 py-1.5 rounded-md text-xs font-bold transition-all bg-transparent border-0 ${editBuffer.type === DISCOUNT_TYPE.PERCENTAGE ? 'text-[var(--brand-accent)]' : 'text-gray-500 hover:text-gray-700'}`}
                                    type="button"
                                >
                                    Percent
                                </button>
                                <button
                                    onClick={() => onBufferChange({ ...editBuffer, type: DISCOUNT_TYPE.FIXED })}
                                    className={`flex-1 flex items-center justify-center gap-1 py-1.5 rounded-md text-xs font-bold transition-all bg-transparent border-0 ${editBuffer.type === DISCOUNT_TYPE.FIXED ? 'text-[var(--brand-accent)]' : 'text-gray-500 hover:text-gray-700'}`}
                                    type="button"
                                >
                                    Fixed
                                </button>
                            </div>
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-bold text-gray-500 uppercase">Value</label>
                            <input
                                type="number" step="0.01"
                                value={editBuffer.value}
                                onChange={e => onBufferChange({ ...editBuffer, value: parseFloat(e.target.value) || 0 })}
                                className="form-input w-full"
                            />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="text-xs font-bold text-gray-500 uppercase">Min. Total ($)</label>
                            <input
                                type="number" step="1"
                                value={editBuffer.minTotal}
                                onChange={e => onBufferChange({ ...editBuffer, minTotal: parseFloat(e.target.value) || 0 })}
                                className="form-input w-full"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-bold text-gray-500 uppercase">Expires</label>
                            <input
                                type="date"
                                value={editBuffer.expires}
                                onChange={e => onBufferChange({ ...editBuffer, expires: e.target.value })}
                                className="form-input w-full text-sm"
                            />
                        </div>
                    </div>
                    <label className="flex items-center gap-2 cursor-pointer pt-2">
                        <input
                            type="checkbox"
                            checked={Boolean(editBuffer.active)}
                            onChange={e => onBufferChange({ ...editBuffer, active: e.target.checked })}
                            className="rounded text-[var(--brand-accent)]"
                        />
                        <span className="text-sm font-bold text-gray-700">Code is active and usable</span>
                    </label>
                </div>
            </div>
        </div>
    );
};
