import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { ProfileForm } from './partials/ProfileForm.js';
import { AddressSection } from './partials/AddressSection.js';
import { useAccountSettings } from '../../hooks/useAccountSettings.js';
import { useUnsavedChangesCloseGuard } from '../../hooks/useUnsavedChangesCloseGuard.js';

interface AccountSettingsModalProps {
    isOpen: boolean;
    onClose: () => void;
}

/**
 * AccountSettingsModal
 * Uses React Portal to ensure topmost stacking and 95% viewport height cap.
 * Portaled to document.body to escape any parent stacking contexts.
 */
export const AccountSettingsModal: React.FC<AccountSettingsModalProps> = ({
    isOpen,
    onClose
}) => {
    const {
        user,
        formData,
        addresses,
        editingAddress,
        isSaving,
        isEditing,
        isProfileDirty,
        isAddressLoading,
        setIsEditing,
        setEditingAddress,
        handleInputChange,
        handleSaveProfile,
        handleSaveAddress,
        handleDeleteAddress,
        reset
    } = useAccountSettings();

    useEffect(() => {
        if (isOpen) reset();
    }, [isOpen]);

    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty: isProfileDirty,
        isBlocked: isSaving,
        onClose,
        onSave: () => handleSaveProfile(),
        closeAfterSave: true
    });

    if (!isOpen || !user) return null;

    const modalContent = (
        <div
            className="wf-modal-overlay show account-settings-modal"
            style={{
                position: 'fixed',
                inset: 0,
                zIndex: 'var(--wf-z-modal)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: 'rgba(0, 0, 0, 0.85)',
                backdropFilter: 'blur(8px)',
                width: '100vw',
                height: '100vh',
                padding: '2.5vh 2.5vw',
                boxSizing: 'border-box'
            }}
            onClick={(e) => e.target === e.currentTarget && void attemptClose()}
        >
            <div
                className="wf-modal-card my-auto animate-in zoom-in-95 slide-in-from-bottom-4 duration-300 overflow-hidden flex flex-col"
                style={{
                    maxWidth: '1000px',
                    width: '100%',
                    maxHeight: '100%',
                    backgroundColor: 'white',
                    borderRadius: '24px',
                    boxShadow: '0 25px 50px -12px rgba(0, 0, 0, 0.5)',
                    position: 'relative'
                }}
                onClick={e => e.stopPropagation()}
            >
                {/* Header */}
                <div className="wf-modal-header" style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '1.25rem 2rem',
                    background: 'var(--bg-gradient-brand, linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-secondary) 100%))',
                    borderBottom: '1px solid rgba(255,255,255,0.1)',
                    flexShrink: 0
                }}>
                    <div className="flex items-center gap-3">
                        <span className="admin-action-btn btn-icon--user text-white" aria-hidden="true" />
                        <h2 className="admin-card-title" style={{
                            margin: 0,
                            color: '#ffffff',
                            fontFamily: "'Merienda', cursive",
                            fontSize: '1.5rem',
                            fontWeight: 700,
                            textShadow: '0 2px 4px rgba(0,0,0,0.1)'
                        }}>
                            Account Settings
                        </h2>
                    </div>
                    <div className="flex items-center gap-3">
                        {(!isEditing && !isProfileDirty) && (
                            <button
                                onClick={() => setIsEditing(true)}
                                className="admin-action-btn btn-icon--edit"
                                aria-label="Edit Profile"
                            />
                        )}
                        {isProfileDirty && (
                            <button
                                onClick={handleSaveProfile}
                                disabled={isSaving}
                                className={`admin-action-btn btn-icon--save ${isSaving ? 'is-loading' : ''} is-dirty`}
                                aria-label="Save Profile"
                            />
                        )}
                        <button
                            onClick={() => { void attemptClose(); }}
                            className="admin-action-btn btn-icon--close"
                            aria-label="Close"
                        />
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto p-8">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
                        <ProfileForm
                            formData={formData}
                            handleInputChange={handleInputChange}
                            handleSaveProfile={handleSaveProfile}
                            isSaving={isSaving}
                            isEditing={isEditing}
                        />

                        <AddressSection
                            addresses={addresses}
                            editingAddress={editingAddress}
                            setEditingAddress={setEditingAddress}
                            handleSaveAddress={handleSaveAddress}
                            handleDeleteAddress={handleDeleteAddress}
                            isAddressLoading={isAddressLoading}
                        />
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

export default AccountSettingsModal;
