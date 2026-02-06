import React from 'react';
import { IEmailTemplate } from '../../../../hooks/admin/useEmailTemplates.js';

interface TemplateListProps {
    templates: IEmailTemplate[];
    onEdit: (template: IEmailTemplate) => void;
    onDelete: (id: number, name: string) => void;
    onSendTest: (templateId: number) => void;
    isTesting: number | null;
    onCreate: () => void;
}

export const TemplateList: React.FC<TemplateListProps> = ({
    templates,
    onEdit,
    onDelete,
    onSendTest,
    isTesting,
    onCreate
}) => {
    return (
        <div className="space-y-4">
            <div className="flex justify-between items-center">
                <h4 className="font-bold text-gray-800">Available Templates</h4>
                <button
                    type="button"
                    onClick={onCreate}
                    className="admin-action-btn btn-icon--add"
                    data-help-id="template-create"
                />
            </div>

            <div className="grid grid-cols-1 gap-4">
                {templates.map(tpl => (
                    <div key={tpl.id} className="p-4 border rounded-lg hover:shadow-md transition-shadow group bg-white">
                        <div className="flex justify-between items-start">
                            <div className="flex-1">
                                <div className="flex items-center gap-2 mb-1">
                                    <h5 className="font-bold text-gray-900">{tpl.template_name}</h5>
                                    <span className="px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 text-[10px] font-bold uppercase">{tpl.template_type}</span>
                                    {tpl.is_active ? (
                                        <span className="text-[var(--brand-accent)] text-[10px] font-bold uppercase">● Active</span>
                                    ) : (
                                        <span className="text-gray-400 text-[10px] font-bold uppercase">● Draft</span>
                                    )}
                                </div>
                                <p className="text-sm text-gray-600 font-medium">{tpl.subject}</p>
                                {tpl.description && <p className="text-xs text-gray-400 mt-1">{tpl.description}</p>}
                            </div>
                            <div className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => onSendTest(tpl.id)}
                                    disabled={isTesting === tpl.id}
                                    className="admin-action-btn btn-icon--send"
                                    data-help-id="template-send-test"
                                />
                                <button
                                    type="button"
                                    onClick={() => onEdit(tpl)}
                                    className="admin-action-btn btn-icon--edit"
                                    data-help-id="template-edit"
                                />
                                <button
                                    type="button"
                                    onClick={() => onDelete(tpl.id, tpl.template_name)}
                                    className="admin-action-btn btn-icon--delete"
                                    data-help-id="template-delete"
                                />
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};
