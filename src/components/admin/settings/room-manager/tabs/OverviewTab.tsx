import React from 'react';
import { IRoomData, IRoomOverview } from '../../../../../types/index.js';
import { ApiClient } from '../../../../../core/ApiClient.js';
import { useAICostEstimateConfirm } from '../../../../../hooks/admin/useAICostEstimateConfirm.js';
import { OverviewCategoryEditor } from '../../../categories/partials/OverviewCategoryEditor.js';
import { CreateRoomModal } from '../modals/CreateRoomModal.js';
import { EditRoomModal } from '../modals/EditRoomModal.js';
import type { IRoomImageGenerationRequest } from '../../../../../types/room-generation.js';
import type { IRoomGenerationHistoryPromptResponse } from '../../../../../types/room-generation.js';

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
    const [isRegenerating, setIsRegenerating] = React.useState(false);
    const { confirmWithEstimate } = useAICostEstimateConfirm();

    const handleRegenerateBackground = React.useCallback(async () => {
        const roomNumber = String(editingRoom?.room_number || roomForm.room_number || '').trim();
        if (!roomNumber) {
            window.WFToast?.error?.('Room number is required');
            return;
        }

        setIsRegenerating(true);
        try {
            window.WFToast?.info?.('Step 1/3: Loading original room prompt...');
            const promptRes = await ApiClient.get<IRoomGenerationHistoryPromptResponse>('/api/room_generation_history.php', {
                room_number: roomNumber
            });
            const promptRow = promptRes?.data?.prompt || promptRes?.prompt;
            const originalPrompt = String(promptRow?.prompt_text || '').trim();
            if (!originalPrompt) {
                window.WFToast?.error?.('No original prompt found for this room');
                return;
            }
            window.WFToast?.success?.('Step 1/3 complete: Original prompt loaded');

            const confirmed = await confirmWithEstimate({
                action_key: 'create_room_generate_image',
                action_label: 'Regenerate room image with original prompt',
                operations: [
                    { key: 'room_image_generation', label: 'Room image generation', image_generations: 1 }
                ],
                context: {
                    prompt_length: originalPrompt.length
                },
                confirmText: 'Regenerate Image'
            });
            if (!confirmed) {
                window.WFToast?.info?.('Room background regeneration canceled.');
                return;
            }

            const renderContext = String(roomForm.render_context || editingRoom?.render_context || 'modal');
            const size: IRoomImageGenerationRequest['size'] = renderContext === 'fullscreen'
                ? '1536x1024'
                : renderContext === 'fixed'
                    ? '1024x1536'
                    : '1024x1024';

            const roomName = String(roomForm.room_name || editingRoom?.room_name || '').trim();
            window.WFToast?.info?.('Step 2/3: Generating background image...');
            const result = await onGenerateBackground({
                room_number: roomNumber,
                template_key: String(promptRow?.template_key || 'room_staging_empty_shelves_v1').trim(),
                provider: 'openai',
                size,
                background_name: roomName ? `${roomNumber} - ${roomName}` : roomNumber,
                prompt_override: originalPrompt
            });

            if (!result.success) {
                window.WFToast?.error?.(result.error || 'Failed to regenerate room background');
                return;
            }

            window.WFToast?.success?.('Step 2/3 complete: Background image regenerated');
            window.WFToast?.success?.('Step 3/3 complete: Room setup finished');
        } catch (err: unknown) {
            window.WFToast?.error?.(err instanceof Error ? err.message : 'Failed to regenerate room background');
        } finally {
            setIsRegenerating(false);
        }
    }, [confirmWithEstimate, editingRoom, roomForm.render_context, roomForm.room_name, roomForm.room_number, onGenerateBackground]);

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
            <EditRoomModal
                isOpen={Boolean(editingRoom)}
                editingRoom={editingRoom}
                roomForm={roomForm}
                setRoomForm={setRoomForm}
                onClose={onCancelEdit}
                onSaveRoom={onSaveRoom}
                onRegenerateBackground={handleRegenerateBackground}
                isRegenerating={isRegenerating}
            />
        </div>
    );
};
