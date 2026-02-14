import React from 'react';
import { createPortal } from 'react-dom';
import { useAttributesManager, TabId, ISizeTemplate, IColorTemplate, IGenderTemplate } from '../../../hooks/admin/useAttributesManager.js';
import { ApiClient } from '../../../core/ApiClient.js';
import { AUTH } from '../../../core/constants.js';
import type { ISanmarColorsImportResponse } from '../../../types/sanmar.js';

import { SizeTemplateEditor } from './attributes/SizeTemplateEditor.js';
import { ColorTemplateEditor } from './attributes/ColorTemplateEditor.js';
import { GenderTab } from './attributes/GenderTab.js';
import { GlobalColorsTab } from './attributes/GlobalColorsTab.js';
import { GlobalSizesTab } from './attributes/GlobalSizesTab.js';
import { SizeTemplatesTab } from './attributes/SizeTemplatesTab.js';
import { ColorTemplatesTab } from './attributes/ColorTemplatesTab.js';
import { GenderTemplatesTab } from './attributes/GenderTemplatesTab.js';
import { SizeColorRedesign } from './attributes/SizeColorRedesign.js';
import { OptionAssignmentsTab } from './attributes/OptionAssignmentsTab.js';
import { MaterialsTab } from './attributes/MaterialsTab.js';
import { CascadeTab } from './attributes/CascadeTab.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';
import { GenderTemplateEditor } from './attributes/GenderTemplateEditor.js';

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
        genderTemplates,
        cascadeConfigs,
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
        editingGenderTemplate,
        setEditingGenderTemplate,
        localGenderTemplate,
        setLocalGenderTemplate,
        isRedesignOpen,
        setIsRedesignOpen,
        isDirty,
        fetchAll,
        handleDuplicateSize,
        handleDuplicateColor,
        handleDuplicateGenderTemplate,
        handleDeleteGender,
        handleDeleteSizeTemplate,
        handleDeleteColorTemplate,
        handleDeleteGenderTemplate,
        handleDeleteGlobalColor,
        handleDeleteGlobalSize,
        handleAddGlobalColor,
        handleUpdateGlobalColor,
        handleAddGlobalSize,
        handleAddGender,
        handleEditSize,
        handleEditColor,
        handleEditGenderTemplate,
        handleOpenSizeColorRedesign,
        handleSaveTemplate,
        addLink,
        deleteLink,
        clearOptionLinks,
        materialsApi,
        cascadeApi,
        themedPrompt,
        themedConfirm
    } = useAttributesManager();

    const runSanmarImport = async () => {
        const ok = await themedConfirm({
            title: 'Import SanMar Colors',
            message: 'This will fetch SanMar Digital Color Guide PDFs, sync SanMar colors, and refresh the "Sanmar" color template. Continue?',
            confirmText: 'Import Now',
            confirmStyle: 'primary',
            iconKey: 'download'
        });
        if (!ok) return;

        try {
            const res = await ApiClient.post<ISanmarColorsImportResponse>(`/api/sanmar_import.php?action=import_colors&admin_token=${AUTH.ADMIN_TOKEN}`, {});
            if (res?.success) {
                const extracted = res?.data?.stats?.extracted_base_colors ?? 0;
                const added = res?.data?.stats?.global_colors?.added ?? 0;
                if (window.WFToast) window.WFToast.success(`SanMar import complete (${extracted} colors, +${added} new)`);
                await fetchAll();
            } else {
                if (window.WFToast) window.WFToast.error(res?.error || res?.message || 'SanMar import failed');
            }
        } catch (e: unknown) {
            const msg = e instanceof Error ? e.message : 'SanMar import failed';
            if (window.WFToast) window.WFToast.error(msg);
        }
    };

    const tabs = ([
        { id: 'assignments', label: 'Assignments' },
        { id: 'cascade', label: 'Cascade' },
        { id: 'colors', label: 'Color Templates' },
        { id: 'gender-templates', label: 'Gender Templates' },
        { id: 'global-colors', label: 'Colors' },
        { id: 'genders', label: 'Genders' },
        { id: 'materials', label: 'Materials' },
        { id: 'sizes', label: 'Size Templates' },
        { id: 'global-sizes', label: 'Sizes' },
    ] as Array<{ id: TabId; label: string }>).sort((a, b) => a.label.localeCompare(b.label));
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
                        {activeTab === 'global-colors' && !editingColor && !editingSize && !editingGenderTemplate && (
                            <button
                                type="button"
                                onClick={() => { void runSanmarImport(); }}
                                className="btn btn-primary whitespace-nowrap"
                                data-help-id="attributes-import-sanmar"
                            >
                                Import Sanmar
                            </button>
                        )}
                        {(editingSize || editingColor || editingGenderTemplate) && (
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
                    ) : editingGenderTemplate ? (
                        <div className="flex-1 overflow-y-auto p-10 space-y-6">
                            <div className="flex justify-between items-center mb-6">
                                <h3 className="text-xl font-black text-slate-800 uppercase tracking-tight">
                                    {editingGenderTemplate.id ? 'Edit Gender Template' : 'New Gender Template'}
                                </h3>
                                <button
                                    onClick={() => { setEditingGenderTemplate(null); setLocalGenderTemplate(null); }}
                                    className="admin-action-btn btn-icon--close"
                                    data-help-id="attributes-cancel-edit"
                                />
                            </div>
                            <GenderTemplateEditor
                                template={localGenderTemplate || editingGenderTemplate}
                                globalGenders={genders}
                                onChange={(t) => setLocalGenderTemplate(t)}
                                onCancel={() => { setEditingGenderTemplate(null); setLocalGenderTemplate(null); }}
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
                                    {activeTab === 'global-colors' && (
                                        <GlobalColorsTab
                                            colors={colors}
                                            onAdd={handleAddGlobalColor}
                                            onDelete={handleDeleteGlobalColor}
                                            onUpdate={handleUpdateGlobalColor}
                                        />
                                    )}
                                    {activeTab === 'global-sizes' && <GlobalSizesTab sizes={sizes} onAdd={handleAddGlobalSize} onDelete={handleDeleteGlobalSize} />}
                                    {activeTab === 'assignments' && (
                                        <OptionAssignmentsTab
                                            sizeTemplates={sizeTemplates}
                                            colorTemplates={colorTemplates}
                                            genderTemplates={genderTemplates}
                                            links={optionLinks}
                                            categories={categories}
                                            isBusy={isLoading}
                                            onAddLink={addLink}
                                            onDeleteLink={deleteLink}
                                            onClearOptionLinks={clearOptionLinks}
                                        />
                                    )}
                                    {activeTab === 'cascade' && (
                                        <CascadeTab
                                            configs={cascadeConfigs}
                                            categories={categories}
                                            isBusy={isLoading}
                                            onUpsert={cascadeApi.upsertConfig}
                                            onDelete={cascadeApi.deleteConfig}
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
                                    {activeTab === 'gender-templates' && (
                                        <GenderTemplatesTab
                                            templates={genderTemplates}
                                            links={optionLinks}
                                            onAdd={() => {
                                                const empty: Partial<IGenderTemplate> = { template_name: '', genders: [] };
                                                setEditingGenderTemplate(empty as IGenderTemplate);
                                                setLocalGenderTemplate(empty as IGenderTemplate);
                                            }}
                                            onEdit={handleEditGenderTemplate}
                                            onDuplicate={handleDuplicateGenderTemplate}
                                            onDelete={handleDeleteGenderTemplate}
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
                                            onAddLink={async (payload) => addLink(payload)}
                                            onDeleteLink={async (payload) => deleteLink(payload)}
                                            onClearOptionLinks={async (payload) => clearOptionLinks(payload)}
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
