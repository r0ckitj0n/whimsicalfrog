import React from 'react';
import { createPortal } from 'react-dom';
import { ISocialPostTemplate, ISocialImage } from '../../../../../hooks/admin/useSocialPosts.js';

import { SOCIAL_PLATFORM } from '../../../../../core/constants.js';

interface PostEditorModalProps {
    template: Partial<ISocialPostTemplate>;
    images: ISocialImage[];
    onSave: (e: React.FormEvent) => void;
    onClose: () => void;
    setTemplate: (t: Partial<ISocialPostTemplate>) => void;
    isLoading: boolean;
}

export const PostEditorModal: React.FC<PostEditorModalProps> = ({
    template,
    images,
    onSave,
    onClose,
    setTemplate,
    isLoading
}) => {
    // Initial state to track dirtyness
    const [initialState] = React.useState(template);
    const isDirty = JSON.stringify(initialState) !== JSON.stringify(template);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSave(e);
    };

    const modalContent = (
        <div
            className="wf-modal-child-overlay fixed inset-0 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose();
            }}
        >
            <div
                className="bg-white rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden animate-in fade-in zoom-in-95"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="font-bold text-gray-800">
                        {template.id ? 'Edit Social Template' : 'Create New Social Post'}
                    </h3>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleSubmit}
                            disabled={isLoading}
                            className={`btn-icon btn-icon--save ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="common-save"
                            type="button"
                        />
                        <button
                            onClick={onClose}
                            className="btn-icon btn-icon--close"
                            aria-label="Close"
                            data-help-id="common-close"
                            type="button"
                        />
                    </div>
                </div>
                <form onSubmit={handleSubmit} className="p-6 space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-4">
                            <div className="space-y-1">
                                <label className="text-xs font-bold text-gray-500 uppercase">Template Name</label>
                                <input
                                    type="text" required
                                    value={template.name || ''}
                                    onChange={e => setTemplate({ ...template, name: e.target.value })}
                                    className="form-input w-full"
                                    placeholder="e.g., Summer Sale Announcement"
                                />
                            </div>

                            <div className="space-y-1">
                                <label className="text-xs font-bold text-gray-500 uppercase">Featured Image</label>
                                <select
                                    value={template.image_url || ''}
                                    onChange={e => setTemplate({ ...template, image_url: e.target.value })}
                                    className="form-input w-full text-sm"
                                >
                                    <option value="">— No Image —</option>
                                    {images.map(img => (
                                        <option key={img.path} value={img.url}>{img.name}</option>
                                    ))}
                                </select>
                                {template.image_url && (
                                    <div className="mt-2 aspect-video rounded-lg border overflow-hidden bg-gray-50">
                                        <img
                                            src={template.image_url}
                                            alt={`Social post preview for ${template.name || 'new template'}`}
                                            className="w-full h-full object-contain"
                                            loading="lazy"
                                        />
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="space-y-1">
                                <label className="text-xs font-bold text-gray-500 uppercase">Post Content</label>
                                <textarea
                                    required
                                    value={template.content || ''}
                                    onChange={e => setTemplate({ ...template, content: e.target.value })}
                                    className="form-input w-full h-48 text-sm leading-relaxed"
                                    placeholder="What would you like to share? Use {name}, {price} for dynamic item info..."
                                />
                            </div>

                            <div className="space-y-2">
                                <label className="text-xs font-bold text-gray-500 uppercase block">Platforms</label>
                                <div className="flex flex-wrap gap-3">
                                    {[SOCIAL_PLATFORM.FACEBOOK, SOCIAL_PLATFORM.INSTAGRAM, SOCIAL_PLATFORM.TWITTER, SOCIAL_PLATFORM.PINTEREST].map(p => (
                                        <label key={p} className="flex items-center gap-2 cursor-pointer group">
                                            <input
                                                type="checkbox"
                                                checked={template.platforms?.includes(p)}
                                                onChange={e => {
                                                    const current = template.platforms || [];
                                                    const next = e.target.checked
                                                        ? [...current, p]
                                                        : current.filter(x => x !== p);
                                                    setTemplate({ ...template, platforms: next });
                                                }}
                                                className="rounded text-[var(--brand-primary)]"
                                            />
                                            <span className="text-sm font-medium text-gray-600 group-hover:text-gray-900 capitalize">{p}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <label className="flex items-center gap-2 cursor-pointer pt-2 border-t">
                                <input
                                    type="checkbox"
                                    checked={Boolean(template.is_active)}
                                    onChange={e => setTemplate({ ...template, is_active: e.target.checked })}
                                    className="rounded text-[var(--brand-accent)]"
                                />
                                <span className="text-sm font-bold text-gray-700">Active Template</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
