import React, { useState, useEffect, useRef, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useMarketingSettings } from '../../../hooks/admin/useMarketingSettings.js';
import { useReceiptSettings, IReceiptMessage, IReceiptVerbiage } from '../../../hooks/admin/useReceiptSettings.js';
import { useModalContext } from '../../../context/ModalContext.js';
import { isDraftDirty } from '../../../core/utils.js';
import { VerbiageEditor } from './receipt/VerbiageEditor.js';
import { MessageList } from './receipt/MessageList.js';
import { MessageEditorModal } from '../../modals/admin/settings/receipt/MessageEditorModal.js';
import { PhraseManager } from './marketing/PhraseManager.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

interface MarketingSettingsProps {
    onClose?: () => void;
    title?: string;
}

type MarketingTab = 'phrases' | 'receipt' | 'default' | 'shipping' | 'items' | 'categories';

export const MarketingSettings: React.FC<MarketingSettingsProps> = ({ onClose, title }) => {
    const {
        isLoading: isMarketingLoading,
        error: marketingError,
        cartTexts,
        encouragements,
        fetchCartTexts,
        fetchEncouragements,
        addCartText,
        deleteCartText,
        updateCartText,
        addEncouragement,
        deleteEncouragement,
        updateEncouragement
    } = useMarketingSettings();

    const {
        messages,
        verbiage,
        isLoading: isReceiptLoading,
        error: receiptError,
        fetchMessages,
        saveMessage,
        deleteMessage,
        saveVerbiage
    } = useReceiptSettings();

    const { confirm: confirmModal } = useModalContext();

    const [activeTab, setActiveTab] = useState<MarketingTab>('phrases');
    const [editingMessage, setEditingMessage] = useState<Partial<IReceiptMessage> | null>(null);
    const [localVerbiage, setLocalVerbiage] = useState<IReceiptVerbiage>(verbiage);
    const hasInitiallyLoaded = useRef(false);
    const alphaCollator = useMemo(
        () => new Intl.Collator(undefined, { sensitivity: 'base', ignorePunctuation: true }),
        []
    );
    const normalizeForSort = (value?: string) =>
        (value || '')
            .trim()
            .replace(/^[^a-z0-9]+/i, '');

    const isLoading = isMarketingLoading || isReceiptLoading;
    const error = marketingError || receiptError;

    useEffect(() => {
        fetchCartTexts();
        fetchEncouragements();
        fetchMessages();
    }, [fetchCartTexts, fetchEncouragements, fetchMessages]);

    useEffect(() => {
        if (!hasInitiallyLoaded.current && !isReceiptLoading && verbiage.receipt_thank_you_message !== '') {
            hasInitiallyLoaded.current = true;
            setLocalVerbiage(verbiage);
        }
    }, [verbiage, isReceiptLoading]);

    // Track dirty state for verbiage
    const isVerbiageDirty = isDraftDirty(localVerbiage, verbiage);

    const handleCreateMessage = () => {
        if (activeTab === 'phrases' || activeTab === 'receipt') return;

        setEditingMessage({
            type: activeTab,
            title: '',
            content: '',
            is_active: true
        });
    };

    const handleSaveMessage = async (e?: React.FormEvent): Promise<boolean> => {
        e?.preventDefault();
        if (editingMessage) {
            const res = await saveMessage(editingMessage);
            if (res?.success) {
                setEditingMessage(null);
                if (window.WFToast) window.WFToast.success('Message saved successfully');
                return true;
            } else {
                if (window.WFToast) window.WFToast.error(res?.error || 'Failed to save message');
                return false;
            }
        }
        return false;
    };

    const handleSaveVerbiage = async (e?: React.FormEvent): Promise<boolean> => {
        e?.preventDefault();
        const res = await saveVerbiage(localVerbiage);
        if (res.success) {
            if (window.WFToast) window.WFToast.success('Verbiage updated successfully');
            return true;
        } else {
            if (window.WFToast) window.WFToast.error(res.error || 'Failed to save verbiage');
            return false;
        }
    };

    const handleDeleteMessage = async (id: string | number) => {
        const confirmed = await confirmModal({
            title: 'Delete Message',
            message: 'Delete message?',
            confirmText: 'Delete',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const numId = typeof id === 'string' ? parseInt(id, 10) : id;
            if (isNaN(numId)) return;
            const ok = await deleteMessage(numId);
            if (ok) {
                if (window.WFToast) window.WFToast.success('Message deleted');
            }
        }
    };

    const filteredMessages = useMemo(
        () => messages
            .filter(m => m.type === activeTab)
            .sort((a, b) => {
                const aSortText = normalizeForSort(a.title) || normalizeForSort(a.condition_value) || normalizeForSort(a.content);
                const bSortText = normalizeForSort(b.title) || normalizeForSort(b.condition_value) || normalizeForSort(b.content);
                const sortTextCompare = alphaCollator.compare(aSortText, bSortText);
                if (sortTextCompare !== 0) return sortTextCompare;
                return a.id - b.id;
            }),
        [messages, activeTab, alphaCollator]
    );
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty: activeTab === 'receipt' && isVerbiageDirty,
        isBlocked: isLoading,
        onClose,
        onSave: () => handleSaveVerbiage(),
        closeAfterSave: true
    });

    const tabs = [
        { id: 'phrases', label: 'Phrases' },
        { id: 'receipt', label: 'Receipt Messages' },
        { id: 'default', label: 'Custom Messages' },
        { id: 'shipping', label: 'Shipping Messages' },
        { id: 'items', label: 'Item Messages' },
        { id: 'categories', label: 'Category Messages' }
    ];

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-auto max-w-[95vw] h-[85vh] flex flex-col mx-auto"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header border-b border-gray-100 bg-white sticky top-0 z-20 px-8 py-5 flex items-start justify-between min-w-[800px]">
                    <div className="flex flex-col gap-5">
                        <div className="flex items-center gap-4">
                            <div className="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center text-3xl">
                                📢
                            </div>
                            <div>
                                <h2 className="text-2xl font-black text-slate-800 tracking-tight">{title || 'Sales Nudge Kit'}</h2>
                                <p className="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Global Marketing & Notification Triggers</p>
                            </div>
                        </div>

                        <div className="wf-tabs bg-slate-100/50 rounded-2xl p-1.5 border border-slate-200/50 flex items-center gap-1.5 self-start">
                            {tabs.map(tab => (
                                <button
                                    key={tab.id}
                                    onClick={() => setActiveTab(tab.id as MarketingTab)}
                                    className={`wf-tab ${activeTab === tab.id ? 'is-active' : ''}`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => {
                                fetchCartTexts();
                                fetchEncouragements();
                                fetchMessages();
                            }}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="marketing-reload-btn"
                            type="button"
                        />
                        {activeTab === 'receipt' && (
                            <button
                                onClick={handleSaveVerbiage}
                                disabled={isLoading || !isVerbiageDirty}
                                className={`admin-action-btn btn-icon--save dirty-only ${isVerbiageDirty ? 'is-dirty' : ''}`}
                                data-help-id="marketing-save-receipt-verbiage"
                                type="button"
                            />
                        )}
                        <button
                            onClick={() => { void attemptClose(); }}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="marketing-close-manager"
                            type="button"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto bg-slate-50/30">
                    <div className="p-8">
                        {error && (
                            <div className="p-4 mb-6 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3">
                                <span className="text-lg">⚠️</span>
                                {error}
                            </div>
                        )}

                        {activeTab === 'phrases' && (
                            <PhraseManager
                                cartTexts={cartTexts}
                                encouragements={encouragements}
                                isLoading={isLoading}
                                isMarketingLoading={isMarketingLoading}
                                addCartText={addCartText}
                                updateCartText={updateCartText}
                                deleteCartText={deleteCartText}
                                addEncouragement={addEncouragement}
                                updateEncouragement={updateEncouragement}
                                deleteEncouragement={deleteEncouragement}
                                confirmModal={confirmModal}
                            />
                        )}

                        {activeTab === 'receipt' && (
                            <VerbiageEditor
                                localVerbiage={localVerbiage}
                                setLocalVerbiage={setLocalVerbiage}
                                isLoading={isLoading}
                                onSave={handleSaveVerbiage}
                            />
                        )}

                        {['default', 'shipping', 'items', 'categories'].includes(activeTab) && (
                            <MessageList
                                messages={filteredMessages}
                                activeType={activeTab as IReceiptMessage['type']}
                                setActiveType={() => { }} // Disabled internal switching
                                onEdit={setEditingMessage}
                                onDelete={handleDeleteMessage}
                                onCreate={handleCreateMessage}
                                isLoading={isLoading}
                                hideTabs={true}
                            />
                        )}

                    </div>
                </div>

                {editingMessage && (
                    <MessageEditorModal
                        editingMessage={editingMessage}
                        setEditingMessage={setEditingMessage}
                        onSave={handleSaveMessage}
                        isLoading={isLoading}
                    />
                )}
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
