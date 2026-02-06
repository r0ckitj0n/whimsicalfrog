import React from 'react';
import { createPortal } from 'react-dom';
import { useEmailTemplates } from '../../../hooks/admin/useEmailTemplates.js';
import { useEmailManagerLogic } from '../../../hooks/admin/useEmailManagerLogic.js';

import { TemplateList } from './email-templates/TemplateList.js';
import { AssignmentList } from './email-templates/AssignmentList.js';
import { TemplateEditor } from './email-templates/TemplateEditor.js';

interface EmailTemplatesManagerProps {
    onClose?: () => void;
    title?: string;
    standalone?: boolean;
}

export const EmailTemplatesManager: React.FC<EmailTemplatesManagerProps> = ({ onClose, title, standalone = true }) => {
    const api = useEmailTemplates();
    const {
        activeTab,
        setActiveTab,
        editingTemplate,
        setEditingTemplate,
        isTesting,
        pendingAssignments,
        isDirty,
        handleEdit,
        handleCreate,
        handleSaveTemplate,
        handleDelete,
        handleSendTest,
        handleAssignmentChange,
        handleSaveAssignments
    } = useEmailManagerLogic({
        templates: api.templates,
        assignments: api.assignments,
        saveTemplate: api.saveTemplate,
        deleteTemplate: api.deleteTemplate,
        saveAllAssignments: api.saveAllAssignments,
        sendTestEmail: api.sendTestEmail,
        fetchAll: api.fetchAll
    });

    const emailTypes = [
        { id: 'order_confirmation', label: 'Order Confirmation' },
        { id: 'admin_notification', label: 'Admin Notification' },
        { id: 'welcome', label: 'Welcome' },
        { id: 'password_reset', label: 'Password Reset' }
    ];

    if (api.isLoading && !api.templates.length) {
        const loadingContent = (
            <div className="bg-white rounded-2xl p-12 flex flex-col items-center gap-4">
                <span className="wf-emoji-loader text-4xl">📧</span>
                <p className="text-gray-500 font-medium">Loading email templates...</p>
            </div>
        );
        return standalone ? createPortal(<div className="admin-modal-overlay over-header show topmost" onClick={(e) => e.target === e.currentTarget && onClose?.()}>{loadingContent}</div>, document.body) : loadingContent;
    }

    const modalContent = (
        <div
            className={standalone
                ? "admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1000px] max-w-[95vw] h-[85vh] flex flex-col"
                : "flex flex-col flex-1 min-h-0 bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm"
            }
            onClick={(e) => e.stopPropagation()}
        >
            <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                <h2 className="text-xl font-black text-gray-800 flex items-center gap-3"><span className="text-2xl">📧</span> {title || 'Email Templates'}</h2>
                <div className="flex-1" />
                <div className="flex items-center gap-2">
                    <button onClick={() => api.fetchAll()} className="admin-action-btn btn-icon--refresh" data-help-id="common-refresh" type="button" />
                    {activeTab === 'templates' && <button onClick={handleCreate} className="admin-action-btn btn-icon--add" data-help-id="common-add" type="button" />}
                    <button onClick={handleSaveAssignments} disabled={api.isLoading || !isDirty} className={`admin-action-btn btn-icon--save ${isDirty ? 'is-dirty' : ''}`} data-help-id="common-save" type="button" />
                    {standalone && <button onClick={onClose} className="admin-action-btn btn-icon--close" data-help-id="common-close" type="button" />}
                </div>
            </div>

            <div className="modal-body wf-admin-modal-body flex-1 overflow-hidden p-0 flex flex-col">
                <div className="flex border-b bg-gray-50">
                    <button type="button" onClick={() => setActiveTab('templates')} className={`px-6 py-3 text-sm font-bold transition-all border-b-2 bg-transparent border-transparent ${activeTab === 'templates' ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]' : 'text-gray-500 hover:text-gray-700'}`}>Email Templates</button>
                    <button type="button" onClick={() => setActiveTab('assignments')} className={`px-6 py-3 text-sm font-bold transition-all border-b-2 bg-transparent border-transparent ${activeTab === 'assignments' ? 'border-[var(--brand-primary)] text-[var(--brand-primary)]' : 'text-gray-500 hover:text-gray-700'}`}>Active Assignments{isDirty && <span className="ml-2 w-2 h-2 rounded-full bg-orange-400 inline-block animate-pulse" />}</button>
                </div>

                <div className="p-6 flex-1 overflow-y-auto">
                    {api.error && <div className="p-3 mb-4 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm rounded-lg">{api.error}</div>}
                    {activeTab === 'templates' && <TemplateList templates={api.templates} onEdit={handleEdit} onDelete={handleDelete} onSendTest={handleSendTest} isTesting={isTesting} onCreate={handleCreate} />}
                    {activeTab === 'assignments' && <AssignmentList emailTypes={emailTypes} assignments={pendingAssignments} templates={api.templates} onSetAssignment={handleAssignmentChange} />}
                </div>
            </div>
            {editingTemplate && <TemplateEditor template={editingTemplate} onSave={handleSaveTemplate} onCancel={() => setEditingTemplate(null)} onChange={setEditingTemplate} emailTypes={emailTypes} />}
        </div>
    );

    return standalone ? createPortal(<div className="admin-modal-overlay over-header show topmost" role="dialog" aria-modal="true" onClick={(e) => e.target === e.currentTarget && onClose?.()}>{modalContent}</div>, document.body) : modalContent;
};

export default EmailTemplatesManager;
