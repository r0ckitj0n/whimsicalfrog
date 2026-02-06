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
        startCreateRoom,
        cancelRoomEdit,
        getImageUrl
    } = useUnifiedRoomManager({ onClose, initialTab });

    // UI Wrappers to sync local state with roomForm
    const handleBgUrlChange = (val: string) => { setBgUrl(val); if (selectedRoom) setRoomForm(prev => ({ ...prev, background_url: val })); };
    const handleRenderContextChange = (val: string) => { setRenderContext(val); if (selectedRoom) setRoomForm(prev => ({ ...prev, render_context: val })); };
    const handleIconPanelColorChange = (val: string) => { setIconPanelColor(val); if (selectedRoom) setRoomForm(prev => ({ ...prev, icon_panel_color: val })); };
    const handleTargetAspectRatioChange = (val: number) => { setTargetAspectRatio(val); if (selectedRoom) setRoomForm(prev => ({ ...prev, target_aspect_ratio: val })); };

    const modalContent = (
        <div className="admin-modal-overlay over-header show topmost" onClick={(e) => e.target === e.currentTarget && onClose?.()}>
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl overflow-hidden flex flex-col admin-modal-fullscreen"
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
                                className="admin-action-btn btn-icon--save is-dirty"
                                data-help-id="common-save"
                            />
                        )}
                        <button onClick={onClose} className="admin-action-btn btn-icon--close shrink-0" data-help-id="common-close" />
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
                            onStartCreate={startCreateRoom}
                            onCancelEdit={cancelRoomEdit}
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
                                    onContentEdit={handleContentEdit}
                                    onContentConvert={handleContentConvert}
                                />
                            )}

                            {activeTab === 'visuals' && (
                                <VisualsTab
                                    backgrounds={backgrounds}
                                    selectedRoom={selectedRoom}
                                    previewImage={preview_image}
                                    setPreviewImage={setPreviewImage}
                                    onApplyBackground={handleApplyBackground}
                                    onDeleteBackground={handleDeleteBackground}
                                    onBackgroundUpload={handleBackgroundUpload}
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
                                    onToggleTool={setActiveTool}
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
                <div className="fixed inset-0 z-[var(--z-overlay-topmost)] flex items-center justify-center p-8 bg-black/90 backdrop-blur-sm" onClick={() => setPreviewImage(null)}>
                    <img src={preview_image.url} className="max-h-full max-w-full object-contain rounded-3xl" onClick={e => e.stopPropagation()} />
                    <button className="admin-action-btn btn-icon--close absolute top-8 right-8 text-white text-3xl shadow-lg" onClick={() => setPreviewImage(null)} data-help-id="common-close" />
                </div>
            )}
        </div>
    );

    return createPortal(modalContent, document.body);
};
