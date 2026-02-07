import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useBusinessInfo, IBusinessInfo } from '../../../hooks/admin/useBusinessInfo.js';
import { isDraftDirty } from '../../../core/utils.js';
import { IdentitySection } from './business/IdentitySection.js';
import { ContactSection } from './business/ContactSection.js';
import { AddressSection } from './business/AddressSection.js';
import { LegalSection } from './business/LegalSection.js';
import { FooterSection } from './business/FooterSection.js';
import { AboutSection } from './business/AboutSection.js';
import { PoliciesSection } from './business/PoliciesSection.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

interface BusinessInfoManagerProps {
    onClose?: () => void;
    title?: string;
}

export const BusinessInfoManager: React.FC<BusinessInfoManagerProps> = ({ onClose, title }) => {
    const {
        info,
        isLoading,
        error,
        saveInfo,
        refresh
    } = useBusinessInfo();

    const [editInfo, setEditTokens] = useState<IBusinessInfo>(info);
    const [initialState, setInitialState] = useState<IBusinessInfo | null>(null);
    const [hasUserEdited, setHasUserEdited] = useState(false);

    useEffect(() => {
        if (isLoading || !info.business_name) return;
        if (!initialState) {
            setEditTokens({ ...info });
            setInitialState({ ...info });
            setHasUserEdited(false);
        }
    }, [info, isLoading, initialState]);

    const handleSave = async (e?: React.FormEvent): Promise<boolean> => {
        e?.preventDefault();
        const res = await saveInfo(editInfo);
        if (res.success) {
            setInitialState({ ...editInfo });
            setHasUserEdited(false);
            if (window.WFToast) window.WFToast.success('Business information saved successfully');
            return true;
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Failed to save');
            return false;
        }
    };

    const handleChange = (key: keyof IBusinessInfo, value: string) => {
        setHasUserEdited(true);
        setEditTokens(prev => ({ ...prev, [key]: value }));
    };

    const isDirty = React.useMemo(() => {
        if (!initialState || !hasUserEdited) return false;
        return isDraftDirty(editInfo, initialState);
    }, [editInfo, initialState, hasUserEdited]);
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty,
        isBlocked: isLoading,
        onClose,
        onSave: handleSave,
        closeAfterSave: true
    });

    if (isLoading && !info.business_name) {
        return createPortal(
            <div className="fixed inset-0 z-[var(--z-overlay-topmost)] flex items-center justify-center bg-black/60 backdrop-blur-sm">
                <div className="bg-white rounded-2xl p-12 flex flex-col items-center gap-4">
                    <span className="wf-emoji-loader text-4xl">🏢</span>
                    <p className="text-gray-500 font-medium">Retrieving identity...</p>
                </div>
            </div>,
            document.body
        );
    }

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-2 px-4 py-3 sticky top-0 bg-white z-20">
                    <h2 className="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <span>🏢</span> {title || 'Business Information'}
                    </h2>
                    <div className="flex-1" />
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={refresh}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="business-info-refresh"
                        />
                        {isDirty && (
                            <button
                                type="button"
                                onClick={() => handleSave()}
                                disabled={isLoading}
                                className="admin-action-btn btn-icon--save is-dirty"
                                data-help-id="business-info-save"
                            />
                        )}
                        <button
                            type="button"
                            onClick={() => { void attemptClose(); }}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="business-info-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-0">
                    <form onSubmit={handleSave} className="p-8 space-y-12">
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            <IdentitySection info={editInfo} onChange={handleChange} />
                            <ContactSection info={editInfo} onChange={handleChange} />
                            <AddressSection info={editInfo} onChange={handleChange} />
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                            <LegalSection info={editInfo} onChange={handleChange} />
                            <FooterSection info={editInfo} onChange={handleChange} />
                            <AboutSection info={editInfo} onChange={handleChange} />
                        </div>

                        <PoliciesSection info={editInfo} onChange={handleChange} />
                    </form>
                </div>
                <div className="pb-8" />
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
