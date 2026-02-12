import React from 'react';
import { createPortal } from 'react-dom';
import { useUnifiedRoomManager } from '../../../hooks/admin/useUnifiedRoomManager.js';
import { TRoomManagerTab } from '../../../types/room.js';
import { getRoomManagerTabs, RoomSelectPrompt } from './room-manager/RoomManagerConstants.js';
import { OverviewTab } from './room-manager/tabs/OverviewTab.js';
import { NavigationTab } from './room-manager/tabs/NavigationTab.js';
import { ShortcutsTab } from './room-manager/tabs/ShortcutsTab.js';
import { VisualsTab } from './room-manager/tabs/VisualsTab.js';
import { CategoriesTab } from './room-manager/tabs/CategoriesTab.js';
import { BoundariesTab } from './room-manager/tabs/BoundariesTab.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';
import { useAIImageEdit } from '../../../hooks/admin/useAIImageEdit.js';

interface UnifiedRoomManagerProps {
    onClose?: () => void;
    initialTab?: TRoomManagerTab;
    title?: string;
}

export const UnifiedRoomManager: React.FC<UnifiedRoomManagerProps> = ({
    onClose,
    initialTab = 'overview',
    title
}) => {
    const {
        activeTab, setActiveTab,
        selectedRoom,
        roomsData,
        editingRoom,
        isCreating,
        roomForm,
        setRoomForm,
        newMapping, setNewMapping,
        connections, externalLinks, headerLinks,
        isDetecting,
        preview_image, setPreviewImage,
        areas, setAreas,
        selectedIds, setSelectedIds,
        activeTool, setActiveTool,
        snapSize, setSnapSize,
        renderContext, setRenderContext,
        bgUrl, setBgUrl,
        iconPanelColor, setIconPanelColor,
        isEditMode, setIsEditMode,
        currentMapId, setCurrentMapId,
        targetAspectRatio, setTargetAspectRatio,
        previewKey,
        isGlobalDirty,
        isRoomFormDirty,
        destinationOptions,
        mappings, backgrounds, boundaries, categoriesHook,
        shortcuts, boundariesTab,
        handleRoomChange,
        handleToggleActive,
        handleSaveRoom,
        handleChangeRoomRole,
        handleDeleteRoom,
        handleContentSave,
        handleContentConvert,
        handleContentUpload,
        handleContentEdit,
        handleApplyBackground,
        handleDeleteBackground,
        handleBackgroundUpload,
        handleGenerateBackground,
        handleSaveBoundaries,
        handleDeleteMap,
        handleRenameMap,
        handleLoadMap,
        handleActivateMap,
        handleSaveSettings,
        handleGlobalSave,
        handleDetectConnections,
        isProtectedRoom,
        startEditRoom,
        cancelRoomEdit,
        createRoom,
        getImageUrl
    } = useUnifiedRoomManager({ onClose, initialTab });
    const [imageTweakPrompt, setImageTweakPrompt] = React.useState('');
    const { isSubmitting: isSubmittingImageTweak, submitImageEdit } = useAIImageEdit();

    // UI Wrappers to sync local state with roomForm
    const handleBgUrlChange = (val: string) => { setBgUrl(val); if (selectedRoom) setRoomForm(prev => ({ ...prev, background_url: val })); };
    const handleRenderContextChange = (val: string) => { setRenderContext(val); if (selectedRoom) setRoomForm(prev => ({ ...prev, render_context: val })); };
    const handleIconPanelColorChange = (val: string) => { setIconPanelColor(val); if (selectedRoom) setRoomForm(prev => ({ ...prev, icon_panel_color: val })); };
    const handleTargetAspectRatioChange = (val: number) => { setTargetAspectRatio(val); if (selectedRoom) setRoomForm(prev => ({ ...prev, target_aspect_ratio: val })); };
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty: isGlobalDirty,
        onClose,
        onSave: handleGlobalSave,
        closeAfterSave: true
    });

    const handleSubmitPreviewTweak = async () => {
        if (!preview_image) return;
        const instructions = imageTweakPrompt.trim();
        if (!instructions) {
            window.WFToast?.error?.('Enter tweak instructions first');
            return;
        }

        try {
            const targetType = preview_image.target_type === 'shortcut_sign' ? 'shortcut_sign' : 'background';
            const effectiveInstructions = targetType === 'shortcut_sign'
                ? `${instructions}\nKeep this as a small isolated sign asset with transparent background and no scene/environment.`
                : instructions;
            const res = await submitImageEdit({
                target_type: targetType,
                source_image_url: String(preview_image.source_shortcut_image_url || preview_image.url),
                instructions: effectiveInstructions,
                room_number: String(preview_image.room_number || selectedRoom || ''),
                source_background_id: Number(preview_image.source_background_id || 0)
            });

            if (targetType === 'background') {
                const editedBackgroundId = Number(res?.data?.background?.id || 0);
                const roomToApply = String(preview_image.room_number || selectedRoom || '');
                if (editedBackgroundId > 0 && roomToApply) {
                    const applied = await backgrounds.applyBackground(roomToApply, editedBackgroundId);
                    if (!applied) {
                        window.WFToast?.error?.('Edited background was saved, but could not be applied automatically.');
                    } else {
                        window.WFToast?.success?.('AI edit applied to the room background.');
                    }
                } else {
                    window.WFToast?.success?.('AI-edited background saved to Room Library');
                }

                const editedImageUrl = String(res?.data?.background?.image_url || '').trim();
                if (editedImageUrl !== '') {
                    setPreviewImage(prev => prev ? ({
                        ...prev,
                        url: editedImageUrl,
                        name: String(res?.data?.background?.name || prev.name || 'Edited background'),
                        source_background_id: editedBackgroundId > 0 ? editedBackgroundId : prev.source_background_id
                    }) : prev);
                }
            } else {
                const editedSignUrl = String(res?.data?.shortcut_sign?.image_url || '').trim();
                if (editedSignUrl === '') {
                    throw new Error('AI edit did not return a sign image');
                }

                setNewMapping(prev => ({
                    ...prev,
                    content_image: editedSignUrl,
                    link_image: editedSignUrl
                }));
                setPreviewImage(prev => prev ? ({
                    ...prev,
                    url: editedSignUrl,
                    source_shortcut_image_url: editedSignUrl,
                    name: String(res?.data?.shortcut_sign?.name || prev.name || 'Edited shortcut sign')
                }) : prev);
                window.WFToast?.success?.('AI-edited shortcut sign applied');
            }

            setImageTweakPrompt('');
            if (selectedRoom) {
                await backgrounds.fetchBackgroundsForRoom(selectedRoom);
            }
        } catch (err) {
            const message = err instanceof Error ? err.message : 'Failed to submit image tweak';
            if (message === 'AI image edit canceled') return;
            window.WFToast?.error?.(message);
        }
    };

    const modalContent = (
        <div className="admin-modal-overlay over-header show topmost" onClick={(e) => e.target === e.currentTarget && void attemptClose()}>
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl overflow-hidden flex flex-col admin-modal-fullscreen wf-room-manager-modal"
                style={{ '--admin-modal-content-height': '95vh' } as React.CSSProperties}
            >
                {/* Header */}
                <div className="modal-header flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-white z-20 flex-shrink-0">
                    <div className="flex items-center gap-4 flex-1">
                        <h2 className="text-xl font-black text-gray-800 flex items-center gap-3 shrink-0">
                            <span className="text-2xl">🏬</span> {title || 'Room Manager'}
                        </h2>

                        <div className="wf-tabs bg-slate-100/50 p-1.5 rounded-full flex gap-1">
                            {getRoomManagerTabs(isRoomFormDirty, shortcuts.isContentDirty, boundariesTab.isSettingsDirty, boundariesTab.isBoundaryDirty).map(tab => (
                                <button key={tab.id} onClick={() => setActiveTab(tab.id)} className={`wf-tab px-3 py-1.5 text-xs flex items-center gap-1.5 relative ${activeTab === tab.id ? 'is-active' : ''}`}>
                                    <span className="text-base">{tab.icon}</span><span>{tab.label}</span>
                                    {tab.isDirty && <span className="w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse shadow-sm" />}
                                </button>
                            ))}
                        </div>

                        {activeTab !== 'overview' && activeTab !== 'navigation' && activeTab !== 'categories' && (
                            <select
                                value={selectedRoom}
                                onChange={e => handleRoomChange(e.target.value)}
                                className="text-xs font-bold p-2 px-3 border-2 border-slate-50 rounded-xl bg-slate-50 text-slate-600 outline-none focus:border-blue-100 transition-all cursor-pointer min-w-[140px] max-w-[200px]"
                            >
                                <option value="">Select Room...</option>
                                {mappings.roomOptions.map(r => <option key={r.val} value={r.val}>{r.label}</option>)}
                            </select>
                        )}
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        {activeTab === 'boundaries' && selectedRoom && !isGlobalDirty && !isEditMode && (
                            <button
                                onClick={() => setIsEditMode(true)}
                                className="admin-action-btn btn-icon--edit"
                                data-help-id="room-map-edit-toggle"
                            />
                        )}
                        {isGlobalDirty && (
                            <button
                                onClick={handleGlobalSave}
                                className="admin-action-btn btn-icon--save dirty-only is-dirty"
                                data-help-id="common-save"
                            />
                        )}
                        <button onClick={() => { void attemptClose(); }} className="admin-action-btn btn-icon--close shrink-0" data-help-id="common-close" />
                    </div>
                </div>

                {/* Body */}
                <div
                    className="modal-body flex-1 pointer-events-auto flex flex-col min-h-0 admin-modal-body-fullscreen"
                >
                    {activeTab === 'overview' ? (
                        <OverviewTab
                            roomsData={roomsData}
                            editingRoom={editingRoom}
                            isCreating={isCreating}
                            roomForm={roomForm}
                            setRoomForm={setRoomForm}
                            categoriesHook={categoriesHook}
                            onSaveRoom={handleSaveRoom}
                            onDeleteRoom={handleDeleteRoom}
                            onToggleActive={handleToggleActive}
                            onChangeRoomRole={handleChangeRoomRole}
                            onStartEdit={startEditRoom}
                            onCancelEdit={cancelRoomEdit}
                            onCreateRoom={createRoom}
                            onGenerateBackground={handleGenerateBackground}
                            isProtectedRoom={isProtectedRoom}
                        />
                    ) : activeTab === 'navigation' ? (
                        <NavigationTab
                            connections={connections}
                            externalLinks={externalLinks}
                            headerLinks={headerLinks}
                            isDetecting={isDetecting}
                            onDetectConnections={handleDetectConnections}
                        />
                    ) : activeTab === 'categories' ? (
                        <div className="h-full flex flex-col min-h-0">
                            <CategoriesTab
                                categoriesHook={categoriesHook}
                                selectedRoom={selectedRoom}
                            />
                        </div>
                    ) : !selectedRoom ? (
                        <RoomSelectPrompt />
                    ) : (
                        <div className="h-full flex flex-col min-h-0">
                            {activeTab === 'content' && (
                                <ShortcutsTab
                                    mappings={mappings}
                                    selectedRoom={selectedRoom}
                                    newMapping={newMapping}
                                    setNewMapping={setNewMapping}
                                    destinationOptions={destinationOptions}
                                    onContentSave={handleContentSave}
                                    onContentUpload={handleContentUpload}
                                    onGenerateContentImage={shortcuts.handleGenerateContentImage}
                                    onPreviewContentImage={(url) => setPreviewImage({
                                        url,
                                        name: 'Shortcut Sign',
                                        target_type: 'shortcut_sign',
                                        room_number: selectedRoom,
                                        source_shortcut_image_url: url
                                    })}
                                    onContentEdit={handleContentEdit}
                                    onContentConvert={handleContentConvert}
                                    onToggleMappingActive={shortcuts.handleToggleMappingActive}
                                    isGeneratingImage={shortcuts.isGeneratingImage}
                                />
                            )}

                            {activeTab === 'visuals' && (
                                <VisualsTab
                                    backgrounds={backgrounds}
                                    selectedRoom={selectedRoom}
                                    selectedRoomData={roomsData.find((room) => String(room.room_number) === String(selectedRoom)) || null}
                                    previewImage={preview_image}
                                    setPreviewImage={setPreviewImage}
                                    onApplyBackground={handleApplyBackground}
                                    onDeleteBackground={handleDeleteBackground}
                                    onBackgroundUpload={handleBackgroundUpload}
                                    onGenerateBackground={handleGenerateBackground}
                                    getImageUrl={getImageUrl}
                                />
                            )}

                            {activeTab === 'boundaries' && (
                                <BoundariesTab
                                    selectedRoom={selectedRoom}
                                    bgUrl={bgUrl}
                                    onBgUrlChange={handleBgUrlChange}
                                    iconPanelColor={iconPanelColor}
                                    onIconPanelColorChange={handleIconPanelColorChange}
                                    renderContext={renderContext}
                                    onRenderContextChange={handleRenderContextChange}
                                    targetAspectRatio={targetAspectRatio}
                                    onTargetAspectRatioChange={handleTargetAspectRatioChange}
                                    areas={areas}
                                    onAreasChange={setAreas}
                                    selectedIds={selectedIds}
                                    onSelectionChange={setSelectedIds}
                                    savedMaps={boundaries.savedMaps}
                                    currentMapId={currentMapId}
                                    onLoadActive={() => handleRoomChange(selectedRoom)}
                                    onSaveMap={handleSaveBoundaries}
                                    onToggleTool={(tool) => {
                                        setActiveTool(tool);
                                        if (tool === 'create') {
                                            setIsEditMode(true);
                                        }
                                    }}
                                    activeTool={activeTool}
                                    snapSize={snapSize}
                                    onSnapSizeChange={setSnapSize}
                                    onActivateMap={handleActivateMap}
                                    onDeleteMap={handleDeleteMap}
                                    onRefreshSaved={() => boundaries.fetchSavedMaps(selectedRoom)}
                                    onLoadMap={handleLoadMap}
                                    onRenameMap={handleRenameMap}
                                    getImageUrl={getImageUrl}
                                    activeBackground={backgrounds.activeBackground}
                                    isEditMode={isEditMode}
                                    previewKey={previewKey}
                                />
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Preview Overlay */}
            {preview_image && (
                <div
                    className="fixed inset-0 z-[var(--z-overlay-topmost)] flex items-center justify-center p-4 bg-black/90 backdrop-blur-sm"
                    onClick={() => setPreviewImage(null)}
                >
                    <div
                        className="w-full max-w-6xl max-h-[92vh] bg-white rounded-2xl overflow-hidden border border-slate-200 shadow-2xl flex flex-col"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="px-4 py-3 border-b border-slate-200 flex items-center gap-3">
                            <h3 className="text-xs font-black uppercase tracking-widest text-slate-700 truncate shrink-0 max-w-[220px]">
                                {preview_image.name || 'Image Preview'}
                            </h3>
                            <input
                                type="text"
                                value={imageTweakPrompt}
                                onChange={(e) => setImageTweakPrompt(e.target.value)}
                                placeholder="Tweak your image"
                                className="flex-1 min-w-0 text-sm p-2 border border-slate-300 rounded-lg"
                                disabled={isSubmittingImageTweak}
                            />
                            <button
                                type="button"
                                className="btn btn-primary px-3 py-2 text-[10px] font-black uppercase tracking-widest disabled:opacity-60"
                                onClick={() => void handleSubmitPreviewTweak()}
                                disabled={isSubmittingImageTweak}
                            >
                                {isSubmittingImageTweak ? 'Submitting...' : 'Submit to AI'}
                            </button>
                            <button
                                className="admin-action-btn btn-icon--close"
                                onClick={() => setPreviewImage(null)}
                                data-help-id="common-close"
                            />
                        </div>
                        <div className="flex-1 min-h-0 p-4 bg-slate-100/60">
                            <img src={preview_image.url} className="max-h-full max-w-full object-contain rounded-2xl mx-auto" />
                        </div>
                    </div>
                </div>
            )}
        </div>
    );

    return createPortal(modalContent, document.body);
};
