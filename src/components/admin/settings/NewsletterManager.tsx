import React, { useState, useEffect, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useModalContext } from '../../../context/ModalContext.js';
import { useNewsletter, INewsletterCampaign } from '../../../hooks/admin/useNewsletter.js';

import { SubscriberTable } from './newsletter/SubscriberTable.js';
import { CampaignList } from './newsletter/CampaignList.js';
import { CampaignEditor } from './newsletter/CampaignEditor.js';
import { isDraftDirty } from '../../../core/utils.js';

interface NewsletterManagerProps {
    onClose?: () => void;
    title?: string;
}

export const NewsletterManager: React.FC<NewsletterManagerProps> = ({ onClose, title }) => {
    const {
        subscribers,
        campaigns,
        isLoading,
        error,
        fetchSubscribers,
        addSubscriber,
        updateSubscriber,
        deleteSubscriber,
        fetchCampaigns,
        saveCampaign,
        deleteCampaign,
        sendCampaign
    } = useNewsletter();

    const [activeTab, setActiveTab] = useState<'subscribers' | 'campaigns'>('subscribers');
    const [campaignView, setCampaignView] = useState<'list' | 'editor'>('list');
    const [editingCampaign, setEditingCampaign] = useState<INewsletterCampaign | null>(null);
    const [localCampaign, setLocalCampaign] = useState<{ subject: string; content: string } | null>(null);
    const hasFetched = React.useRef(false);

    useEffect(() => {
        if (hasFetched.current) return;
        hasFetched.current = true;
        fetchSubscribers();
        fetchCampaigns();
    }, [fetchSubscribers, fetchCampaigns]);

    // Subscriber handlers
    const handleAddSubscriber = async (email: string) => {
        const success = await addSubscriber(email);
        if (success && window.WFToast) {
            window.WFToast.success('Subscriber added.');
        }
        return success;
    };

    const handleToggleSubscriberStatus = async (id: number, currentlyActive: boolean) => {
        const success = await updateSubscriber(id, { is_active: !currentlyActive });
        if (success && window.WFToast) {
            window.WFToast.success(currentlyActive ? 'Subscriber unsubscribed.' : 'Subscriber resubscribed.');
        }
    };

    const { confirm: themedConfirm } = useModalContext();

    const handleDeleteSubscriber = async (id: number, email: string) => {
        const confirmed = await themedConfirm({
            title: 'Remove Subscriber',
            message: `Remove subscriber "${email}"?`,
            confirmText: 'Remove Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteSubscriber(id);
            if (success && window.WFToast) {
                window.WFToast.success('Subscriber removed.');
            }
        }
    };


    // Campaign handlers
    const handleNewCampaign = () => {
        setEditingCampaign(null);
        setCampaignView('editor');
    };

    const handleEditCampaign = (campaign: INewsletterCampaign) => {
        setEditingCampaign(campaign);
        setCampaignView('editor');
    };

    const handleCancelEditor = () => {
        setEditingCampaign(null);
        setCampaignView('list');
    };

    const handleSaveCampaign = async () => {
        if (!localCampaign) return;
        const data = {
            id: editingCampaign?.id,
            subject: localCampaign.subject.trim(),
            content: localCampaign.content.trim(),
            status: editingCampaign?.status || 'draft'
        };
        const success = await saveCampaign(data);
        if (success) {
            if (window.WFToast) window.WFToast.success(data.id ? 'Campaign updated.' : 'Campaign saved as draft.');
            setEditingCampaign(null);
            setLocalCampaign(null);
            setCampaignView('list');
        }
    };

    const isCampaignDirty = useMemo(() => {
        if (!localCampaign) return false;
        if (!editingCampaign) {
            return localCampaign.subject.trim() !== '' || localCampaign.content.trim() !== '';
        }
        return isDraftDirty(localCampaign, {
            subject: editingCampaign.subject,
            content: editingCampaign.content
        });
    }, [localCampaign, editingCampaign]);

    const handleDeleteCampaign = async (id: number, subject: string) => {
        const confirmed = await themedConfirm({
            title: 'Delete Campaign',
            message: `Delete campaign "${subject}"?`,
            confirmText: 'Delete Now',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const success = await deleteCampaign(id);
            if (success && window.WFToast) {
                window.WFToast.success('Campaign deleted.');
            }
        }
    };


    const handleSendCampaign = async (id: number, subject: string) => {
        const confirmed = await themedConfirm({
            title: 'Send Campaign',
            message: `Send campaign "${subject}" to all subscribers?`,
            confirmText: 'Send Now',
            iconKey: 'rocket'
        });

        if (confirmed) {
            const result = await sendCampaign(id);
            if (result.success && window.WFToast) {
                window.WFToast.success(`Campaign sent to ${result.sent_count || 0} subscribers.`);
            }
        }
    };


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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[700px] max-w-[95vw] max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header border-b border-gray-100 px-6 py-4 sticky top-0 bg-white z-20 flex items-start justify-between">
                    <div className="flex flex-col gap-4">
                        <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                            <span className="text-2xl">📧</span> {title || 'Newsletters'}
                        </h2>

                        <div className="wf-tabs bg-slate-50/50 rounded-xl p-1 border border-slate-100 flex items-center gap-2 self-start">
                            <button
                                onClick={() => setActiveTab('subscribers')}
                                className={`wf-tab ${activeTab === 'subscribers' ? 'is-active' : ''}`}
                            >
                                Subscribers ({subscribers.length})
                            </button>
                            <button
                                onClick={() => { setActiveTab('campaigns'); setCampaignView('list'); }}
                                className={`wf-tab ${activeTab === 'campaigns' ? 'is-active' : ''}`}
                            >
                                Campaigns ({campaigns.length})
                            </button>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {activeTab === 'campaigns' && campaignView === 'editor' && (
                            <button
                                onClick={handleSaveCampaign}
                                disabled={isLoading || !isCampaignDirty}
                                className={`admin-action-btn btn-icon--save ${isCampaignDirty ? 'is-dirty' : ''}`}
                                data-help-id="newsletter-save-campaign"
                                type="button"
                            />
                        )}
                        <button
                            onClick={() => { fetchSubscribers(); fetchCampaigns(); }}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="newsletter-reload"
                            type="button"
                        />
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="newsletter-close"
                            type="button"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-hidden p-0 flex flex-col">

                    <div className="flex-1 overflow-y-auto overflow-x-hidden p-10">
                        {isLoading && !subscribers.length && !campaigns.length ? (
                            <div className="flex flex-col items-center justify-center p-12 text-gray-500 gap-4 uppercase tracking-widest font-black text-[10px]">
                                <span className="text-4xl animate-bounce">📧</span>
                                <p>Loading...</p>
                            </div>
                        ) : (
                            <div className="animate-in fade-in slide-in-from-bottom-2 duration-300">
                                {error && campaignView !== 'editor' && (
                                    <div className="mb-6 p-4 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3">
                                        <span className="text-lg">⚠️</span>
                                        {error}
                                    </div>
                                )}

                                {activeTab === 'subscribers' && (
                                    <SubscriberTable
                                        subscribers={subscribers}
                                        onAdd={handleAddSubscriber}
                                        onToggleStatus={handleToggleSubscriberStatus}
                                        onDelete={handleDeleteSubscriber}
                                        isLoading={isLoading}
                                    />
                                )}

                                {activeTab === 'campaigns' && campaignView === 'list' && (
                                    <CampaignList
                                        campaigns={campaigns}
                                        onNew={handleNewCampaign}
                                        onEdit={handleEditCampaign}
                                        onDelete={handleDeleteCampaign}
                                        onSend={handleSendCampaign}
                                        isLoading={isLoading}
                                    />
                                )}

                                {activeTab === 'campaigns' && campaignView === 'editor' && (
                                    <CampaignEditor
                                        campaign={editingCampaign}
                                        localCampaign={localCampaign}
                                        setLocalCampaign={setLocalCampaign}
                                        onSave={handleSaveCampaign}
                                        onCancel={handleCancelEditor}
                                        isLoading={isLoading}
                                        subscriberCount={subscribers.length}
                                    />
                                )}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
