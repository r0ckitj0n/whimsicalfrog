import React from 'react';
import { ISocialPostTemplate } from '../../../../hooks/admin/useSocialPosts.js';

interface TemplateListProps {
    templates: ISocialPostTemplate[];
    onPublish: (id: string) => void;
    onEdit: (t: ISocialPostTemplate) => void;
    onDuplicate: (t: ISocialPostTemplate) => void;
    onDelete: (id: string) => void;
}

export const TemplateList: React.FC<TemplateListProps> = ({
    templates,
    onPublish,
    onEdit,
    onDuplicate,
    onDelete
}) => {
    if (templates.length === 0) {
        return (
            <div className="p-12 text-center text-gray-500 italic">
                No templates created yet. Start by making your first one!
            </div>
        );
    }

    return (
        <table className="w-full text-sm text-left">
            <thead className="bg-gray-50 text-gray-600 font-bold border-b">
                <tr>
                    <th className="px-6 py-3">Template Name</th>
                    <th className="px-6 py-3">Platforms</th>
                    <th className="px-6 py-3 text-center">Status</th>
                    <th className="px-6 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
                {templates.map((t) => (
                    <tr key={t.id} className="hover:bg-gray-50 group">
                        <td className="px-6 py-4">
                            <div className="font-bold text-gray-900">{t.name}</div>
                            <div className="text-xs text-gray-500 line-clamp-1 mt-0.5">{t.content}</div>
                        </td>
                        <td className="px-6 py-4">
                            <div className="flex gap-1.5">
                                {t.platforms.map(p => (
                                    <span key={p} className="px-2 py-0.5 bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] text-[10px] font-black uppercase rounded border border-[var(--brand-primary)]/20">
                                        {p}
                                    </span>
                                ))}
                            </div>
                        </td>
                        <td className="px-6 py-4 text-center">
                            <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${t.is_active ? 'bg-[var(--brand-accent)]/10 text-[var(--brand-accent)] border border-[var(--brand-accent)]/20' : 'bg-gray-100 text-gray-500 border border-gray-200'
                                }`}>
                                {t.is_active ? 'Active' : 'Draft'}
                            </span>
                        </td>
                        <td className="px-6 py-4 text-right">
                            <div className="flex items-center justify-end gap-2">
                                <button
                                    onClick={() => onPublish(t.id)}
                                    className="admin-action-btn btn-icon--send"
                                    data-help-id="social-publish-now"
                                    type="button"
                                />
                                <button
                                    onClick={() => onEdit(t)}
                                    className="admin-action-btn btn-icon--edit"
                                    data-help-id="common-edit"
                                    type="button"
                                />
                                <button
                                    onClick={() => onDuplicate(t)}
                                    className="admin-action-btn btn-icon--duplicate"
                                    data-help-id="common-duplicate"
                                    type="button"
                                />
                                <button
                                    onClick={() => onDelete(t.id)}
                                    className="admin-action-btn btn-icon--delete"
                                    data-help-id="common-delete"
                                    type="button"
                                />
                            </div>
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
};
