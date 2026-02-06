import React, { useState } from 'react';
import { useModalContext } from '../../../../context/ModalContext.js';
import type { ISocialPostTemplate, ISocialImage } from '../../../../hooks/admin/useSocialPosts.js';

import { TemplateList } from './TemplateList.js';
import { PostEditorModal } from '../../../modals/admin/settings/social/PostEditorModal.js';

interface SocialPostsPanelProps {
    templates: ISocialPostTemplate[];
    images: ISocialImage[];
    isLoading: boolean;
    fetchTemplates: () => Promise<void>;
    saveTemplate: (template: Partial<ISocialPostTemplate>) => Promise<boolean>;
    deleteTemplate: (id: string) => Promise<boolean>;
    publishTemplate: (id: string) => Promise<boolean>;
}

export const SocialPostsPanel: React.FC<SocialPostsPanelProps> = ({
    templates,
    images,
    isLoading,
    fetchTemplates,
    saveTemplate,
    deleteTemplate,
    publishTemplate
}) => {
    const [editingTemplate, setEditingTemplate] = useState<Partial<ISocialPostTemplate> | null>(null);

    const handleNew = () => {
        setEditingTemplate({
            name: '',
            content: '',
            image_url: '',
            platforms: ['facebook', 'instagram'],
            is_active: true
        });
    };

    const handleEdit = (t: ISocialPostTemplate) => {
        setEditingTemplate({ ...t });
    };

    const handleDuplicate = (t: ISocialPostTemplate) => {
        setEditingTemplate({
            ...t,
            id: undefined,
            name: `${t.name} (Copy)`,
            is_active: true
        });
    };

    const { confirm: themedConfirm } = useModalContext();

    const handleDelete = async (id: string) => {
        const confirmed = await themedConfirm({
            title: 'Delete Template',
            message: 'Delete this template? This cannot be undone.',
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteTemplate(id);
            if (success && window.WFToast) {
                window.WFToast.success('Template deleted.');
            }
        }
    };


    const handlePublish = async (id: string) => {
        const success = await publishTemplate(id);
        if (success && window.WFToast) {
            window.WFToast.success('Published to all platforms!');
        }
    };

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault();
        if (editingTemplate) {
            const success = await saveTemplate(editingTemplate);
            if (success) {
                setEditingTemplate(null);
                if (window.WFToast) {
                    window.WFToast.success('Template saved.');
                }
            }
        }
    };

    return (
        <div className="social-posts-panel">
            <div className="flex items-center justify-between mb-8">
                <div className="flex flex-col">
                    <h3 className="text-lg font-bold text-slate-800">Post Templates</h3>
                    <p className="text-xs text-slate-500 font-medium">Create and manage your reusable social media content.</p>
                </div>
                <div className="flex items-center gap-3">
                    <button
                        onClick={handleNew}
                        className="px-5 py-2.5 text-sm font-bold text-white bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-hover)] rounded-xl transition-colors flex items-center gap-2 shadow-sm"
                        type="button"
                    >
                        <span>➕</span> Create Template
                    </button>
                </div>
            </div>

            {templates.length === 0 && isLoading ? (
                <div className="flex flex-col items-center justify-center py-20 text-slate-400 uppercase tracking-widest font-black text-[10px]">
                    <span className="text-4xl animate-bounce mb-4">📱</span>
                    <p>Loading templates...</p>
                </div>
            ) : (
                <div className="animate-in fade-in slide-in-from-bottom-2 duration-300">
                    <TemplateList
                        templates={templates}
                        onPublish={handlePublish}
                        onEdit={handleEdit}
                        onDuplicate={handleDuplicate}
                        onDelete={handleDelete}
                    />
                </div>
            )}

            {editingTemplate && (
                <PostEditorModal
                    template={editingTemplate}
                    images={images}
                    onSave={handleSave}
                    onClose={() => setEditingTemplate(null)}
                    setTemplate={setEditingTemplate}
                    isLoading={isLoading}
                />
            )}
        </div>
    );
};
