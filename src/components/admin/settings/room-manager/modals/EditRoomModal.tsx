import React from 'react';
import { createPortal } from 'react-dom';
import type { IRoomData } from '../../../../../types/room.js';

interface EditRoomModalProps {
    isOpen: boolean;
    editingRoom: IRoomData | null;
    roomForm: Partial<IRoomData>;
    setRoomForm: React.Dispatch<React.SetStateAction<Partial<IRoomData>>>;
    onClose: () => void;
    onSaveRoom: () => Promise<void>;
    onRegenerateBackground: () => Promise<void>;
    isRegenerating: boolean;
}

export const EditRoomModal: React.FC<EditRoomModalProps> = ({
    isOpen,
    editingRoom,
    roomForm,
    setRoomForm,
    onClose,
    onSaveRoom,
    onRegenerateBackground,
    isRegenerating
}) => {
    if (!isOpen || !editingRoom || typeof document === 'undefined') return null;

    const modalContent = (
        <div
            className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
            style={{ zIndex: 'var(--wf-z-modal)' }}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div className="relative w-full max-w-4xl bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 className="text-sm font-black uppercase tracking-widest text-slate-700">Edit Room</h3>
                        <p className="text-[11px] text-slate-500 mt-1">Update room settings and regenerate a background from the original AI prompt.</p>
                    </div>
                    <button
                        type="button"
                        className="admin-action-btn btn-icon--close"
                        onClick={onClose}
                        data-help-id="common-close"
                    />
                </div>

                <div className="p-5 space-y-5 max-h-[70vh] overflow-y-auto">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Room Number *</label>
                            <input
                                type="text"
                                value={roomForm.room_number || ''}
                                disabled
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg bg-slate-100 text-slate-400"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Room Name *</label>
                            <input
                                type="text"
                                value={roomForm.room_name || ''}
                                onChange={e => setRoomForm(prev => ({ ...prev, room_name: e.target.value }))}
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="e.g., Holiday Collection"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Door Label *</label>
                            <input
                                type="text"
                                value={roomForm.door_label || ''}
                                onChange={e => setRoomForm(prev => ({ ...prev, door_label: e.target.value }))}
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200"
                                placeholder="e.g., Holidays"
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Display Order</label>
                            <input
                                type="number"
                                value={roomForm.display_order || 0}
                                onChange={e => setRoomForm(prev => ({ ...prev, display_order: parseInt(e.target.value, 10) || 0 }))}
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Description</label>
                        <textarea
                            value={roomForm.description || ''}
                            onChange={e => setRoomForm(prev => ({ ...prev, description: e.target.value }))}
                            className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 resize-none"
                            rows={3}
                            placeholder="Optional description..."
                        />
                    </div>
                </div>

                <div className="px-5 py-4 border-t border-slate-100 bg-slate-50/70 flex items-center justify-between">
                    <button
                        type="button"
                        onClick={() => void onRegenerateBackground()}
                        disabled={isRegenerating}
                        className={`btn btn-secondary px-4 py-2 text-xs font-black uppercase tracking-widest ${isRegenerating ? 'opacity-60 cursor-not-allowed' : ''}`}
                    >
                        {isRegenerating ? 'Regenerating...' : 'Regenerate Background Image'}
                    </button>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-xs font-black uppercase tracking-widest rounded-lg border border-slate-300 text-slate-600 bg-white"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            onClick={() => void onSaveRoom()}
                            className="btn btn-primary px-4 py-2 text-xs font-black uppercase tracking-widest"
                            data-help-id="common-save"
                        >
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

export default EditRoomModal;
