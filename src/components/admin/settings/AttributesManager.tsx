import React from 'react';
import { createPortal } from 'react-dom';
import { useAttributesManager, TabId, ISizeTemplate, IColorTemplate } from '../../../hooks/admin/useAttributesManager.js';

import { SizeTemplateEditor } from './attributes/SizeTemplateEditor.js';
import { ColorTemplateEditor } from './attributes/ColorTemplateEditor.js';
import { GenderTab } from './attributes/GenderTab.js';
import { GlobalColorsTab } from './attributes/GlobalColorsTab.js';
import { GlobalSizesTab } from './attributes/GlobalSizesTab.js';
import { SizeTemplatesTab } from './attributes/SizeTemplatesTab.js';
import { ColorTemplatesTab } from './attributes/ColorTemplatesTab.js';
import { SizeColorRedesign } from './attributes/SizeColorRedesign.js';
import { OptionAssignmentsTab } from './attributes/OptionAssignmentsTab.js';
import { MaterialsTab } from './attributes/MaterialsTab.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

interface AttributesManagerProps {
    onClose?: () => void;
    title?: string;
}

export const AttributesManager: React.FC<AttributesManagerProps> = ({ onClose, title }) => {
    const {
        colors,
        sizes,
        genders,
        sizeTemplates,
        colorTemplates,
        materials,
        optionLinks,
        categories,
        isLoading,
        error,
        activeTab,
        setActiveTab,
        editingSize,
        setEditingSize,
        localSize,
        setLocalSize,
        editingColor,
        setEditingColor,
        localColor,
        setLocalColor,
        isRedesignOpen,
        setIsRedesignOpen,
        isDirty,
        fetchAll,
        handleDuplicateSize,
        handleDuplicateColor,
        handleDeleteGender,
        handleDeleteSizeTemplate,
        handleDeleteColorTemplate,
        handleDeleteGlobalColor,
        handleDeleteGlobalSize,
        handleAddGlobalColor,
        handleAddGlobalSize,
        handleAddGender,
        handleEditSize,
        handleEditColor,
        handleOpenSizeColorRedesign,
        handleSaveTemplate,
        upsertLink,
        clearLink,
        materialsApi,
        themedPrompt,
        themedConfirm
    } = useAttributesManager();

    const tabs = [
        { id: 'genders', label: 'Genders' },
        { id: 'global-colors', label: 'Global Colors' },
        { id: 'global-sizes', label: 'Global Sizes' },
        { id: 'assignments', label: 'Assignments' },
        { id: 'sizes', label: 'Size Templates' },
        { id: 'colors', label: 'Color Templates' },
        { id: 'materials', label: 'Materials' },
    ];
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty,
        isBlocked: isLoading,
        onClose,
        onSave: handleSaveTemplate,
        closeAfterSave: true
    });

    const modalContent = (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) void attemptClose();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1000px] max-w-[95vw] h-[80vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header border-b border-gray-100 bg-white sticky top-0 z-20 px-6 py-4 flex items-start justify-between">
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center gap-4">
                            <div className="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-xl">
                                🧩
                            </div>
                            <div>
                                <h2 className="text-xl font-black text-slate-800 tracking-tight">{title || 'Item Attributes'}</h2>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Global Variation Management</p>
                            </div>
                        </div>

                        <div className="wf-tabs bg-slate-50/50 rounded-xl p-1 border border-slate-100 flex items-center gap-2 self-start">
                            {tabs.map(tab => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id as TabId)}
                                    className={`wf-tab ${activeTab === tab.id ? 'is-active' : ''}`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {(editingSize || editingColor) && (
                            <button
                                onClick={handleSaveTemplate}
                                disabled={isLoading || !isDirty}
                                className={`admin-action-btn btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`}
                                data-help-id="attributes-save-template"
                                type="button"
                            />
                        )}
                        <button
                            onClick={fetchAll}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="attributes-reload"
                            type="button"
                        />
                        <button
                            onClick={() => { void attemptClose(); }}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="attributes-close"
                            type="button"
                        />
                    </div>
                </div>

                {/* Body */}
                <div className="modal-body wf-admin-modal-body flex-1 overflow-hidden p-0 flex flex-col">
                    {editingSize ? (
                        <div className="flex-1 overflow-y-auto p-10 space-y-6">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-xl font-black text-slate-800 uppercase tracking-tight">
                                    {editingSize.id ? 'Edit Size Template' : 'New Size Template'}
                                </h3>
                                <button
                                    onClick={() => { setEditingSize(null); setLocalSize(null); }}
                                    className="admin-action-btn btn-icon--close"
                                    data-help-id="attributes-cancel-edit"
                                />
                            </div>
                            <SizeTemplateEditor
                                template={localSize || editingSize}
                                onChange={setLocalSize}
                                onCancel={() => { setEditingSize(null); setLocalSize(null); }}
                                onSave={handleSaveTemplate}
                            />
                        </div>
                    ) : editingColor ? (
                        <div className="flex-1 overflow-y-auto p-10 space-y-6">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-xl font-black text-slate-800 uppercase tracking-tight">
                                    {editingColor.id ? 'Edit Color Template' : 'New Color Template'}
                                </h3>
                                <button
                                    onClick={() => { setEditingColor(null); setLocalColor(null); }}
                                    className="admin-action-btn btn-icon--close"
                                    data-help-id="attributes-cancel-edit"
                                />
                            </div>
                            <ColorTemplateEditor
                                template={localColor || editingColor}
                                onChange={setLocalColor}
                                onCancel={() => { setEditingColor(null); setLocalColor(null); }}
                                onSave={handleSaveTemplate}
                            />
                        </div>
                    ) : (
                        <>
                            <div className="flex-1 overflow-y-auto p-10">
                                {error && (
                                    <div className="p-4 mb-6 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3">
                                        <span className="text-lg">⚠️</span>
                                        {error}
                                    </div>
                                )}

                                {isLoading && !genders.length && (
                                    <div className="py-20 text-center flex flex-col items-center gap-4">
                                        <div className="animate-spin text-4xl">🏷️</div>
                                        <p className="text-[10px] uppercase font-black tracking-widest text-slate-400">Syncing attributes...</p>
                                    </div>
                                )}

                                <div className="animate-in fade-in slide-in-from-bottom-2 duration-300">
                                    {activeTab === 'genders' && <GenderTab genders={genders} onAdd={handleAddGender} onDelete={handleDeleteGender} />}
                                    {activeTab === 'global-colors' && <GlobalColorsTab colors={colors} onAdd={handleAddGlobalColor} onDelete={handleDeleteGlobalColor} />}
                                    {activeTab === 'global-sizes' && <GlobalSizesTab sizes={sizes} onAdd={handleAddGlobalSize} onDelete={handleDeleteGlobalSize} />}
                                    {activeTab === 'assignments' && (
                                        <OptionAssignmentsTab
                                            sizeTemplates={sizeTemplates}
                                            colorTemplates={colorTemplates}
                                            links={optionLinks}
                                            categories={categories}
                                            isBusy={isLoading}
                                            onUpsertLink={upsertLink}
                                            onClearLink={clearLink}
                                        />
                                    )}
                                    {activeTab === 'sizes' && (
                                        <SizeTemplatesTab
                                            templates={sizeTemplates}
                                            links={optionLinks}
                                            onAdd={() => {
                                                const empty: Partial<ISizeTemplate> = { template_name: '', sizes: [] };
                                                setEditingSize(empty as ISizeTemplate);
                                                setLocalSize(empty as ISizeTemplate);
                                            }}
                                            onEdit={handleEditSize}
                                            onDuplicate={handleDuplicateSize}
                                            onDelete={handleDeleteSizeTemplate}
                                            onOpenRedesign={handleOpenSizeColorRedesign}
                                        />
                                    )}
                                    {activeTab === 'colors' && (
                                        <ColorTemplatesTab
                                            templates={colorTemplates}
                                            links={optionLinks}
                                            onAdd={() => {
                                                const empty: Partial<IColorTemplate> = { template_name: '', colors: [] };
                                                setEditingColor(empty as IColorTemplate);
                                                setLocalColor(empty as IColorTemplate);
                                            }}
                                            onEdit={handleEditColor}
                                            onDuplicate={handleDuplicateColor}
                                            onDelete={handleDeleteColorTemplate}
                                            onOpenRedesign={handleOpenSizeColorRedesign}
                                        />
                                    )}
                                    {activeTab === 'materials' && (
                                        <MaterialsTab
                                            materials={materials}
                                            links={optionLinks}
                                            categories={categories}
                                            isBusy={isLoading}
                                            onCreate={materialsApi.createMaterial}
                                            onUpdate={materialsApi.updateMaterial}
                                            onDelete={materialsApi.deleteMaterial}
                                            onUpsertLink={async (payload) => upsertLink(payload)}
                                            onClearLink={async (payload) => clearLink(payload)}
                                            prompt={themedPrompt}
                                            confirm={themedConfirm}
                                        />
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>

            {isRedesignOpen && (
                <SizeColorRedesign
                    onClose={() => setIsRedesignOpen(false)}
                />
            )}
        </div>
    );

    return createPortal(modalContent, document.body);
};
