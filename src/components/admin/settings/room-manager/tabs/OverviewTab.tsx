import React from 'react';
import { IRoomData, IRoomOverview } from '../../../../../types/index.js';
import { OverviewCategoryEditor } from '../../../categories/partials/OverviewCategoryEditor.js';
import { CreateRoomModal } from '../modals/CreateRoomModal.js';
import type { IRoomImageGenerationRequest } from '../../../../../types/room-generation.js';

interface OverviewTabProps {
    roomsData: IRoomData[];
    editingRoom: IRoomData | null;
    isCreating: boolean;
    roomForm: Partial<IRoomData>;
    setRoomForm: React.Dispatch<React.SetStateAction<Partial<IRoomData>>>;
    categoriesHook: import('../../../../../types/room.js').ICategoriesHook;
    onSaveRoom: () => Promise<void>;
    onDeleteRoom: (roomNumber: string) => Promise<void>;
    onToggleActive: (roomNumber: string, currentActive: boolean | number) => Promise<void>;
    onChangeRoomRole: (roomNumber: string, newRole: IRoomData['room_role']) => Promise<void>;
    onStartEdit: (room: IRoomData) => void;
    onCancelEdit: () => void;
    onCreateRoom: (room: Partial<IRoomData>) => Promise<{ success: boolean; error?: string; room_number?: string }>;
    onGenerateBackground: (request: IRoomImageGenerationRequest) => Promise<{ success: boolean; error?: string }>;
    isProtectedRoom: (room: IRoomData) => boolean;
}

export const OverviewTab: React.FC<OverviewTabProps> = ({
    roomsData,
    editingRoom,
    isCreating,
    roomForm,
    setRoomForm,
    categoriesHook,
    onSaveRoom,
    onDeleteRoom,
    onToggleActive,
    onChangeRoomRole,
    onStartEdit,
    onCancelEdit,
    onCreateRoom,
    onGenerateBackground,
    isProtectedRoom
}) => {
    const [isCreateModalOpen, setIsCreateModalOpen] = React.useState(false);

    return (
        <div className="h-full flex flex-col min-h-0 overflow-hidden">
            <div className="p-6 overflow-y-auto flex-1">
                <div className="flex items-center justify-between mb-6">
                    <h3 className="text-sm font-black text-slate-600 uppercase tracking-widest">All Rooms</h3>
                    {!isCreating && !editingRoom && (
                        <button
                            onClick={() => setIsCreateModalOpen(true)}
                            className="btn btn-text-primary"
                            data-help-id="room-create-btn"
                        >
                            + Create Room
                        </button>
                    )}
                </div>

                {/* Create/Edit Form */}
                {(isCreating || editingRoom) && (
                    <div className="mb-6 p-6 bg-slate-50 rounded-2xl border border-slate-100">
                        <div className="flex items-center justify-between mb-4">
                            <h4 className="text-xs font-black text-slate-500 uppercase tracking-widest">
                                {isCreating ? 'Create New Room' : `Edit Room: ${editingRoom?.room_name}`}
                            </h4>
                            <div className="flex gap-2">
                                <button
                                    onClick={onCancelEdit}
                                    className="px-3 py-1 text-[10px] font-bold text-slate-400 hover:text-slate-600 uppercase tracking-widest"
                                >
                                    Cancel
                                </button>
                                <button
                                    onClick={onSaveRoom}
                                    className="px-3 py-1 bg-blue-500 text-white text-[10px] font-bold rounded-lg hover:bg-blue-600 uppercase tracking-widest shadow-sm transition-all"
                                    data-help-id="common-save"
                                >
                                    {isCreating ? 'Create Room' : 'Save Changes'}
                                </button>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Room Number *</label>
                                <input
                                    type="text"
                                    value={roomForm.room_number || ''}
                                    onChange={e => setRoomForm(prev => ({ ...prev, room_number: e.target.value }))}
                                    disabled={!isCreating}
                                    className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:bg-slate-100 disabled:text-slate-400"
                                    placeholder="e.g., 7"
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
                                    onChange={e => setRoomForm(prev => ({ ...prev, display_order: parseInt(e.target.value) || 0 }))}
                                    className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200"
                                />
                            </div>
                        </div>
                        <div className="mt-4">
                            <label className="block text-[10px] font-bold text-slate-400 uppercase mb-1">Description</label>
                            <textarea
                                value={roomForm.description || ''}
                                onChange={e => setRoomForm(prev => ({ ...prev, description: e.target.value }))}
                                className="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-200 resize-none"
                                rows={2}
                                placeholder="Optional description..."
                            />
                        </div>

                    </div>
                )}

                {/* Rooms Table */}
                <div className="bg-white rounded-2xl border border-slate-100 overflow-hidden">
                    <table className="w-full">
                        <thead>
                            <tr className="bg-slate-50 border-b border-slate-100">
                                <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Room #</th>
                                <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Room Name</th>
                                <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Door Label</th>
                                <th className="px-4 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Role</th>
                                <th className="px-4 py-3 text-left text-[10px] font-black text-slate-400 uppercase tracking-widest">Categories</th>
                                <th className="px-4 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Order</th>
                                <th className="px-4 py-3 text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Active</th>
                                <th className="px-4 py-3 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {roomsData.map((room, idx) => {
                                const isProtected = isProtectedRoom(room);
                                const isActive = room.is_active === true || room.is_active === 1;
                                return (
                                    <tr key={room.room_number} className={`border-b border-slate-50 ${idx % 2 === 0 ? 'bg-white' : 'bg-slate-25'} hover:bg-blue-50/30 transition-colors`}>
                                        <td className="px-4 py-3">
                                            <span className={`inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-black ${isProtected ? 'bg-amber-100 text-amber-600' : 'bg-slate-100 text-slate-600'}`}>
                                                {room.room_number}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-sm font-semibold text-slate-700">{room.room_name}</td>
                                        <td className="px-4 py-3 text-sm text-slate-500">{room.door_label}</td>
                                        <td className="px-4 py-3 text-center">
                                            <select
                                                value={room.room_role || 'room'}
                                                onChange={(e) => onChangeRoomRole(room.room_number, e.target.value as IRoomData['room_role'])}
                                                className={`text-xs px-2 py-1 rounded-lg border transition-colors cursor-pointer
                                                    ${room.room_role === 'landing' ? 'bg-purple-50 border-purple-200 text-purple-700' :
                                                        room.room_role === 'main' ? 'bg-blue-50 border-blue-200 text-blue-700' :
                                                            room.room_role === 'shop' ? 'bg-green-50 border-green-200 text-green-700' :
                                                                room.room_role === 'settings' ? 'bg-orange-50 border-orange-200 text-orange-700' :
                                                                    room.room_role === 'about' ? 'bg-teal-50 border-teal-200 text-teal-700' :
                                                                        room.room_role === 'contact' ? 'bg-cyan-50 border-cyan-200 text-cyan-700' :
                                                                            'bg-slate-50 border-slate-200 text-slate-600'}`}
                                            >
                                                <option value="room">Room</option>
                                                <option value="landing">Landing Page</option>
                                                <option value="main">Main Room</option>
                                                <option value="shop">Shop</option>
                                                <option value="settings">Settings</option>
                                                <option value="about">About</option>
                                                <option value="contact">Contact</option>
                                            </select>
                                        </td>
                                        <td className="px-4 py-3">
                                            <OverviewCategoryEditor
                                                roomNumber={room.room_number}
                                                overview={categoriesHook.overview.find((o: IRoomOverview) => String(o.room_number) === String(room.room_number))}
                                                categories={categoriesHook.categories}
                                                assignments={categoriesHook.assignments}
                                                onAdd={categoriesHook.addAssignment}
                                                onDelete={categoriesHook.deleteAssignment}
                                                onUpdate={categoriesHook.updateAssignment}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-center text-sm text-slate-400">{room.display_order || 0}</td>
                                        <td className="px-4 py-3 text-center">
                                            <label className="relative inline-flex items-center cursor-pointer" data-help-id="room-active-toggle">
                                                <input
                                                    type="checkbox"
                                                    checked={isActive}
                                                    onChange={() => onToggleActive(room.room_number, room.is_active)}
                                                    disabled={isProtected}
                                                    className="sr-only peer"
                                                />
                                                <div className={`w-9 h-5 rounded-full peer-focus:ring-2 peer-focus:ring-blue-200 transition-colors ${isProtected ? 'bg-slate-200 cursor-not-allowed' : isActive ? 'bg-emerald-500' : 'bg-slate-300'} peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-transform after:shadow-sm`}></div>
                                            </label>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <button
                                                    onClick={() => onStartEdit(room)}
                                                    className="admin-action-btn btn-icon--edit"
                                                    data-help-id="room-edit-btn"
                                                ></button>
                                                {!isProtected && (
                                                    <button
                                                        onClick={() => onDeleteRoom(room.room_number)}
                                                        className="admin-action-btn btn-icon--delete"
                                                        data-help-id="room-delete-btn"
                                                    ></button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                            {roomsData.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-12 text-center text-slate-400 text-sm italic">
                                        No rooms found. Click "Create Room" to add one.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <CreateRoomModal
                isOpen={isCreateModalOpen}
                roomsData={roomsData}
                onClose={() => setIsCreateModalOpen(false)}
                onCreateRoom={onCreateRoom}
                onGenerateBackground={onGenerateBackground}
            />
        </div>
    );
};
