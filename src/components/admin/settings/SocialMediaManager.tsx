import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { SocialAccountManager } from './SocialAccountManager.js';
import { SocialPostsPanel } from './social/SocialPostsPanel.js';
import { useSocialMedia } from '../../../hooks/admin/useSocialMedia.js';
import { useSocialPosts } from '../../../hooks/admin/useSocialPosts.js';

interface SocialMediaManagerProps {
    onClose?: () => void;
    initialTab?: 'accounts' | 'posts';
    title?: string;
}

export const SocialMediaManager: React.FC<SocialMediaManagerProps> = ({
    onClose,
    initialTab = 'accounts',
    title
}) => {
    const [activeTab, setActiveTab] = useState(initialTab);

    // Social Accounts Hook
    const socialAccounts = useSocialMedia();

    // Social Posts Hook
    const socialPosts = useSocialPosts();

    const handleRefresh = async () => {
        if (activeTab === 'accounts') {
            await socialAccounts.fetchAccounts();
        } else {
            await socialPosts.fetchTemplates();
        }
    };

    const isRefreshing = activeTab === 'accounts' ? socialAccounts.isLoading : socialPosts.isLoading;

    const tabs = [
        { id: 'accounts', label: 'Accounts', icon: '🔗' },
        { id: 'posts', label: 'Posts', icon: '📝' }
    ] as const;

    const modalContent = (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
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
                                📱
                            </div>
                            <div>
                                <h2 className="text-xl font-black text-slate-800 tracking-tight">{title || 'Social Media'}</h2>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Connect Accounts & Manage Posts</p>
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
                        <button
                            onClick={handleRefresh}
                            className={`admin-action-btn btn-icon--refresh ${isRefreshing ? 'is-loading' : ''}`}
                            data-help-id="common-refresh"
                            disabled={isRefreshing}
                        />
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                {/* Content Area */}
                <div className="flex-1 overflow-y-auto w-full">
                    <div className="p-8 lg:p-12">
                        <div className="max-w-5xl mx-auto">
                            {activeTab === 'accounts' && (
                                <div className="animate-in fade-in slide-in-from-bottom-2 duration-500">
                                    <SocialAccountManager {...socialAccounts} />
                                </div>
                            )}

                            {activeTab === 'posts' && (
                                <div className="animate-in fade-in slide-in-from-bottom-2 duration-500">
                                    <SocialPostsPanel {...socialPosts} />
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
