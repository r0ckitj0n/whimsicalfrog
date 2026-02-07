import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useBranding } from '../../../hooks/admin/useBranding.js';
import { useBrandingManagerLogic } from '../../../hooks/admin/useBrandingManagerLogic.js';
import { FontPicker } from './attributes/FontPicker.js';
import { CorePalette } from './branding/CorePalette.js';
import { ExtendedPalette } from './branding/ExtendedPalette.js';
import { PublicInterfaceColors } from './branding/PublicInterfaceColors.js';
import { TypographySection } from './branding/TypographySection.js';
import { LayoutSection } from './branding/LayoutSection.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

interface BrandStylingProps {
    onClose?: () => void;
    title?: string;
}

export const BrandStyling: React.FC<BrandStylingProps> = ({ onClose, title }) => {
    const api = useBranding();
    const {
        editTokens,
        activeTab,
        setActiveTab,
        fontPickerTarget,
        setFontPickerTarget,
        handleSave,
        handleBackup,
        handleReset,
        handleAddPaletteColor,
        handleRemovePaletteColor,
        handleFontSelect,
        handleChange,
        isDirty
    } = useBrandingManagerLogic({
        tokens: api.tokens,
        isLoading: api.isLoading,
        saveTokens: api.saveTokens,
        createBackup: api.createBackup,
        resetToDefaults: api.resetToDefaults,
        fetchTokens: api.fetchTokens
    });

    useEffect(() => { api.fetchTokens(); }, [api.fetchTokens]);
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty,
        isBlocked: api.isLoading,
        onClose,
        onSave: handleSave,
        closeAfterSave: true
    });

    const modalContent = (
        <div className="admin-modal-overlay over-header show topmost" role="dialog" aria-modal="true" onClick={(e) => e.target === e.currentTarget && void attemptClose()}>
            <div className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1000px] max-w-[95vw] h-[80vh] flex flex-col" onClick={(e) => e.stopPropagation()}>
                <div className="modal-header border-b border-gray-100 bg-white sticky top-0 z-20 px-6 py-4 flex items-start justify-between">
                    <div className="flex flex-col gap-4">
                        <h2 className="text-xl font-black text-gray-800 flex items-center gap-3"><span className="text-2xl">🎨</span> {title || 'Brand Styling'}</h2>
                        <div className="wf-tabs bg-slate-50/50 rounded-xl p-1 border border-slate-100 flex items-center gap-2 self-start">
                            {[{ id: 'colors' as const, label: 'Colors' }, { id: 'fonts' as const, label: 'Typography' }, { id: 'layout' as const, label: 'UI & Layout' }].map((tab) => (
                                <button key={tab.id} onClick={() => setActiveTab(tab.id)} className={`wf-tab ${activeTab === tab.id ? 'is-active' : ''}`}>{tab.label}</button>
                            ))}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button onClick={() => api.fetchTokens()} className="admin-action-btn btn-icon--refresh" data-help-id="common-refresh" type="button" />
                        <button onClick={handleSave} disabled={api.isLoading} className={`admin-action-btn btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`} data-help-id="common-save" type="button" />
                        <button onClick={() => { void attemptClose(); }} className="admin-action-btn btn-icon--close" data-help-id="common-close" type="button" />
                    </div>
                </div>
                <div className="modal-body wf-admin-modal-body flex-1 overflow-hidden p-0 flex flex-col">
                    <div className="flex-1 overflow-y-auto p-10">
                        {api.isLoading && !api.tokens ? (
                            <div className="flex flex-col items-center justify-center p-12 text-gray-500 gap-4 uppercase tracking-widest font-black text-[10px]"><span className="text-4xl animate-bounce">🎨</span><p>Loading brand identity...</p></div>
                        ) : (
                            <div className="animate-in fade-in slide-in-from-bottom-2 duration-300">
                                {api.error && <div className="mb-6 p-4 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3"><span className="text-lg">⚠️</span>{api.error}</div>}
                                {activeTab === 'colors' && (
                                    <div className="space-y-12">
                                        <CorePalette editTokens={editTokens} onChange={handleChange} />
                                        <ExtendedPalette palette={Array.isArray(editTokens.business_brand_palette) ? editTokens.business_brand_palette : []} onAdd={handleAddPaletteColor} onRemove={handleRemovePaletteColor} />
                                        <PublicInterfaceColors editTokens={editTokens} onChange={handleChange} />
                                        <div className="pt-12 border-t border-gray-100">
                                            <div className="bg-gray-50 rounded-2xl p-8 flex items-center justify-between gap-6">
                                                <div><h3 className="text-sm font-bold text-gray-800 mb-1">Brand Maintenance</h3><p className="text-xs text-gray-500">Create backups or reset the entire system to defaults.</p></div>
                                                <div className="flex items-center gap-3">
                                                    <button onClick={handleBackup} disabled={api.isLoading} className="px-6 py-3 bg-white border border-gray-200 text-gray-700 rounded-xl text-xs font-bold hover:bg-gray-100 transition-colors shadow-sm" data-help-id="brand-backup">📦 Create Backup</button>
                                                    <button onClick={handleReset} disabled={api.isLoading} className="px-6 py-3 bg-red-50 text-red-600 rounded-xl text-xs font-bold hover:bg-red-100 transition-colors" data-help-id="common-reset">♻️ Reset Defaults</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                )}
                                {activeTab === 'fonts' && <TypographySection editTokens={editTokens} onOpenPicker={setFontPickerTarget} />}
                                {activeTab === 'layout' && <LayoutSection editTokens={editTokens} onChange={handleChange} />}
                            </div>
                        )}
                    </div>
                </div>
            </div>
            <FontPicker isOpen={!!fontPickerTarget} onClose={() => setFontPickerTarget(null)} onSelect={handleFontSelect} currentStack={fontPickerTarget === 'primary' ? editTokens.business_brand_font_primary : fontPickerTarget === 'secondary' ? editTokens.business_brand_font_secondary : fontPickerTarget === 'title-primary' ? editTokens.business_brand_font_title_primary : editTokens.business_brand_font_title_secondary} title={fontPickerTarget === 'primary' ? 'Choose Primary Font' : fontPickerTarget === 'secondary' ? 'Choose Secondary Font' : fontPickerTarget === 'title-primary' ? 'Choose Primary Title Font' : 'Choose Secondary Title Font'} />
        </div>
    );

    return createPortal(modalContent, document.body);
};
