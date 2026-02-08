import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { useAutomation } from '../../../hooks/admin/useAutomation.js';
import type { IAutomationPlaybook } from '../../../types/admin.js';

interface AutomationManagerProps {
    onClose?: () => void;
    title?: string;
}

export function AutomationManager({ onClose, title }: AutomationManagerProps) {
    const { playbooks, isLoading, error, fetchPlaybooks, savePlaybooks } = useAutomation();
    const [draftPlaybooks, setDraftPlaybooks] = useState<IAutomationPlaybook[]>([]);
    const [isSaving, setIsSaving] = useState(false);
    const [message, setMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    useEffect(() => {
        setDraftPlaybooks(playbooks);
    }, [playbooks]);

    const hasChanges = useMemo(() => {
        return JSON.stringify(draftPlaybooks) !== JSON.stringify(playbooks);
    }, [draftPlaybooks, playbooks]);

    const togglePlaybook = (index: number) => {
        setDraftPlaybooks((prev) => prev.map((item, itemIndex) => {
            if (itemIndex !== index) return item;
            return {
                ...item,
                active: !Boolean(item.active),
            };
        }));
        setMessage(null);
    };

    const handleRefresh = async () => {
        setMessage(null);
        await fetchPlaybooks();
    };

    const handleSave = async () => {
        setIsSaving(true);
        setMessage(null);

        const ok = await savePlaybooks(draftPlaybooks);

        if (ok) {
            setMessage({ type: 'success', text: 'Automation playbooks saved.' });
        } else {
            setMessage({ type: 'error', text: 'Unable to save automation playbooks.' });
        }

        setIsSaving(false);
    };

    const modalContent = (
        <div
            id="automationManagerModal"
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
            }}
        >
            <div
                id="automationManagerContainer"
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1100px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">⚙️</span> {title || 'Automation Manager'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={handleRefresh}
                            className="btn-text-secondary disabled:opacity-50"
                            disabled={isLoading}
                            data-help-id="common-refresh"
                        >
                            {isLoading ? 'Refreshing...' : 'Refresh'}
                        </button>
                        <button
                            type="button"
                            onClick={handleSave}
                            className="btn-text-primary disabled:opacity-50"
                            disabled={isSaving || !hasChanges}
                            data-help-id="automation-save"
                        >
                            {isSaving ? 'Saving...' : 'Save Changes'}
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div id="automationManagerContent" className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-8">
                    <div className="text-sm text-slate-600 mb-6">
                        Monitor and control the automation playbooks stored in business marketing settings.
                    </div>

                    {error && (
                        <div className="p-3 rounded mb-4 bg-red-50 border border-red-200 text-red-700">
                            {error}
                        </div>
                    )}

                    {message && (
                        <div className={`p-3 rounded mb-4 ${message.type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}`}>
                            {message.text}
                        </div>
                    )}

                    {isLoading && draftPlaybooks.length === 0 ? (
                        <div className="flex items-center justify-center py-12">
                            <span className="text-gray-500">Loading automation playbooks...</span>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {draftPlaybooks.length === 0 && (
                                <div className="admin-card text-sm text-slate-600">
                                    No automation playbooks were found.
                                </div>
                            )}

                            {draftPlaybooks.map((playbook, index) => {
                                const isActive = Boolean(playbook.active);
                                return (
                                    <article key={`${playbook.name}-${index}`} className="admin-card border border-slate-100 rounded-2xl">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <h3 className="text-base font-black text-slate-800">{playbook.name}</h3>
                                                <p className="text-xs text-slate-500 mt-1">{playbook.cadence}</p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => togglePlaybook(index)}
                                                className={`px-3 py-1.5 rounded-full text-xs font-bold border ${isActive ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200'}`}
                                                data-help-id="automation-toggle"
                                            >
                                                {isActive ? 'Active' : 'Paused'}
                                            </button>
                                        </div>

                                        <dl className="mt-4 space-y-3 text-sm">
                                            <div>
                                                <dt className="text-[10px] font-black uppercase tracking-wide text-slate-400">Trigger</dt>
                                                <dd className="text-slate-700">{playbook.trigger}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-[10px] font-black uppercase tracking-wide text-slate-400">Action</dt>
                                                <dd className="text-slate-700">{playbook.action}</dd>
                                            </div>
                                            <div>
                                                <dt className="text-[10px] font-black uppercase tracking-wide text-slate-400">Status Note</dt>
                                                <dd className="text-slate-700">{playbook.status}</dd>
                                            </div>
                                        </dl>
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
}

export default AutomationManager;
