import React from 'react';
import { IEmailTemplate } from '../../../../hooks/admin/useEmailTemplates.js';

interface TemplateEditorProps {
    template: Partial<IEmailTemplate>;
    onSave: (e: React.FormEvent) => void;
    onCancel: () => void;
    onChange: (template: Partial<IEmailTemplate>) => void;
    emailTypes: { id: string; label: string }[];
}

export const TemplateEditor: React.FC<TemplateEditorProps> = ({
    template,
    onSave,
    onCancel,
    onChange,
    emailTypes
}) => {
    // Initial state to track dirtyness
    const [initialState] = React.useState(template);
    const isDirty = JSON.stringify(initialState) !== JSON.stringify(template);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSave(e);
    };

    return (
        <div className="fixed inset-0 z-[var(--wf-z-modal)] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95">
                <div className="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="font-bold text-gray-800">{template.id ? 'Edit Template' : 'Create New Template'}</h3>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleSubmit}
                            className={`btn-icon btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="modal-save"
                            type="button"
                        />
                        <button
                            type="button"
                            onClick={onCancel}
                            className="btn-icon btn-icon--close"
                            data-help-id="modal-close"
                        />
                    </div>
                </div>
                <form onSubmit={handleSubmit} className="flex-1 overflow-y-auto p-6 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <label className="text-xs font-bold text-gray-500 uppercase">Template Name</label>
                            <input
                                type="text"
                                required
                                value={template.template_name || ''}
                                onChange={e => onChange({ ...template, template_name: e.target.value })}
                                className="w-full p-2 border rounded-lg outline-none focus:ring-2 focus:ring-[var(--brand-primary)]"
                            />
                        </div>
                        <div className="space-y-1">
                            <label className="text-xs font-bold text-gray-500 uppercase">Type</label>
                            <select
                                value={template.template_type || 'custom'}
                                onChange={e => onChange({ ...template, template_type: e.target.value })}
                                className="w-full p-2 border rounded-lg outline-none focus:ring-2 focus:ring-[var(--brand-primary)]"
                            >
                                {emailTypes.map(t => <option key={t.id} value={t.id}>{t.label}</option>)}
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">Email Subject</label>
                        <input
                            type="text"
                            required
                            value={template.subject || ''}
                            onChange={e => onChange({ ...template, subject: e.target.value })}
                            className="w-full p-2 border rounded-lg outline-none focus:ring-2 focus:ring-[var(--brand-primary)]"
                        />
                    </div>
                    <div className="space-y-1">
                        <label className="text-xs font-bold text-gray-500 uppercase">HTML Content (Use variables like {'{customer_name}'}, {'{order_id}'})</label>
                        <textarea
                            required
                            value={template.html_content || ''}
                            onChange={e => onChange({ ...template, html_content: e.target.value })}
                            className="w-full h-64 p-3 border rounded-lg font-mono text-sm outline-none focus:ring-2 focus:ring-[var(--brand-primary)]"
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="tplActive"
                            checked={!!template.is_active}
                            onChange={e => onChange({ ...template, is_active: e.target.checked })}
                        />
                        <label htmlFor="tplActive" className="text-sm font-bold text-gray-700">Active Template</label>
                    </div>
                </form>
            </div>
        </div>
    );
};
