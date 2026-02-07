import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useEmailSettings, IEmailSettings } from '../../../hooks/admin/useEmailSettings.js';
import { EmailTemplatesManager } from './EmailTemplatesManager.js';
import { EmailHistory } from './EmailHistory.js';
import { EmailSettingsPanel } from './email-templates/EmailSettingsPanel.js';
import { isDraftDirty } from '../../../core/utils.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

interface CommunicationManagerProps {
    onClose?: () => void;
    initialTab?: 'settings' | 'templates' | 'history';
    title?: string;
}

export const CommunicationManager: React.FC<CommunicationManagerProps> = ({
    onClose,
    initialTab = 'settings',
    title
}) => {
    const [activeTab, setActiveTab] = useState(initialTab);
    const { settings, isLoading, error, fetchSettings, saveSettings } = useEmailSettings();
    const [editSettings, setEditSettings] = useState<IEmailSettings | null>(null);
    const [smtpPassword, setSmtpPassword] = useState('');
    const [initialSettings, setInitialSettings] = useState<IEmailSettings | null>(null);

    useEffect(() => {
        if (settings && !initialSettings) {
            setEditSettings({ ...settings });
            setInitialSettings({ ...settings });
        }
    }, [settings, initialSettings]);

    useEffect(() => {
        fetchSettings();
    }, [fetchSettings]);

    const isDirty = React.useMemo(() => {
        if (!editSettings || !initialSettings) return smtpPassword !== '';
        return isDraftDirty(editSettings, initialSettings) || smtpPassword !== '';
    }, [editSettings, initialSettings, smtpPassword]);

    const handleSave = async () => {
        if (editSettings) {
            const success = await saveSettings(editSettings, smtpPassword);
            if (success) {
                setInitialSettings({ ...editSettings });
                setSmtpPassword('');
                if (window.WFToast) window.WFToast.success('Email configuration updated!');
            }
        }
    };

    const handleBack = () => {
        onClose?.();
    };

    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty: activeTab === 'settings' && isDirty,
        isBlocked: isLoading,
        onClose: handleBack,
        onSave: handleSave,
        closeAfterSave: true
    });

    const tabs = [
        { id: 'settings', label: 'Email Configuration', icon: '⚙️' },
        { id: 'templates', label: 'Message Templates', icon: '📝' },
        { id: 'history', label: 'Transmissions', icon: '📨' }
    ] as const;

    if (!settings && isLoading) {
        return createPortal(
            <div className="fixed inset-0 z-[var(--z-overlay-topmost)] flex items-center justify-center bg-black/60 backdrop-blur-sm">
                <div className="bg-white rounded-2xl p-12 flex flex-col items-center gap-4">
                    <span className="wf-emoji-loader text-4xl">📨</span>
                    <p className="text-gray-500 font-medium">Blowing on the transmission tubes...</p>
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
                className="admin-modal admin-modal-content show bg-white rounded-2xl shadow-2xl w-[1100px] max-w-[95vw] h-[85vh] flex flex-col overflow-hidden"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header Section */}
                <div className="modal-header border-b border-gray-100 bg-white sticky top-0 z-30 px-6 py-4 flex items-start justify-between">
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center gap-4">
                            <div className="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center text-xl">
                                📬
                            </div>
                            <div>
                                <h2 className="text-xl font-black text-slate-800 tracking-tight">{title || 'Communication Center'}</h2>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Manage Emails, Templates & History</p>
                            </div>
                        </div>

                        <div className="wf-tabs bg-slate-50/50 rounded-xl p-1 border border-slate-100 flex items-center gap-2 self-start">
                            {tabs.map(tab => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={`wf-tab ${activeTab === tab.id ? 'is-active' : ''}`}
                                >
                                    <span className="text-sm">{tab.icon}</span>
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {activeTab === 'settings' && (
                            <button
                                onClick={handleSave}
                                disabled={isLoading || !isDirty}
                                className={`admin-action-btn btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`}
                                data-help-id="common-save"
                            />
                        )}
                        <button
                            onClick={() => { void attemptClose(); }}
                            className={`admin-action-btn btn-icon--close`}
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                {/* Content Area */}
                <div className="flex-1 overflow-y-auto w-full">
                    <div className="p-8 lg:p-12">
                        <div className="max-w-5xl mx-auto">
                            {error && (
                                <div className="mb-8 p-4 bg-red-50 border border-red-100 rounded-2xl text-red-600 text-xs font-bold animate-in shake duration-500">
                                    ⚠️ {error}
                                </div>
                            )}

                            {activeTab === 'settings' && editSettings && (
                                <EmailSettingsPanel
                                    settings={editSettings}
                                    setSettings={setEditSettings}
                                    smtpPassword={smtpPassword}
                                    setSmtpPassword={setSmtpPassword}
                                    onSave={handleSave}
                                    isLoading={isLoading}
                                />
                            )}

                            {activeTab === 'templates' && (
                                <div className="animate-in fade-in slide-in-from-bottom-2 duration-500 -mt-6">
                                    <EmailTemplatesManager standalone={false} />
                                </div>
                            )}

                            {activeTab === 'history' && (
                                <div className="animate-in fade-in slide-in-from-bottom-2 duration-500 -mt-6">
                                    <EmailHistory />
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
