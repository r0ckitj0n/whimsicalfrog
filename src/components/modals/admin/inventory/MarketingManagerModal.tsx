import React from 'react';
import { createPortal } from 'react-dom';
import { useMarketingModal } from '../../../../hooks/admin/inventory-ai/useMarketingModal.js';
import { IntelligenceSection } from '../../../admin/inventory/marketing/IntelligenceSection.js';
import { MarketingForm } from '../../../admin/inventory/marketing/MarketingForm.js';

interface MarketingManagerModalProps {
    sku: string;
    itemName: string;
    itemDescription: string;
    category: string;
    initialMarketingData?: import('../../../../hooks/admin/inventory-ai/useMarketingManager.js').MarketingData | null;
    onClose: () => void;
    onApplyField: (field: string, value: string) => void;
}

export const MarketingManagerModal: React.FC<MarketingManagerModalProps> = (props) => {
    const {
        marketingData,
        isLoading,
        isGenerating,
        error,
        editedTitle,
        setEditedTitle,
        editedDescription,
        setEditedDescription,
        editedKeywords,
        editedAudience,
        setEditedAudience,
        newKeyword,
        setNewKeyword,
        showIntelligence,
        setShowIntelligence,
        applyStatus,
        dataSource,
        hasContent,
        handleGenerateAll,
        handleApplyTitle,
        handleApplyDescription,
        handleApplyAll,
        handleAddKeyword,
        handleRemoveKeyword,
        handleRefreshFromDb
    } = useMarketingModal(props);

    const { sku, itemName, onClose } = props;

    const modalContent = (
        <div
            className="fixed inset-0 z-[calc(var(--wf-z-modal)+10)] flex items-start justify-center bg-black/60 backdrop-blur-sm overflow-y-auto py-8"
            onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
        >
            <div
                className="bg-white rounded-2xl shadow-2xl w-full max-w-[900px] mx-4 my-auto animate-in fade-in zoom-in-95 overflow-hidden flex flex-col max-h-[85vh]"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Modal Header */}
                <div className="px-6 py-4 border-b bg-gradient-to-r from-[var(--brand-primary)]/5 to-white flex items-center justify-between sticky top-0 z-20 bg-white">
                    <div className="flex items-center gap-4">
                        <div className="w-10 h-10 bg-[var(--brand-primary)]/10 rounded-xl flex items-center justify-center text-xl">
                            📢
                        </div>
                        <div>
                            <h2 className="text-lg font-black text-gray-900">
                                Marketing Manager
                            </h2>
                            <div className="text-xs font-bold text-[var(--brand-primary)] uppercase tracking-widest">
                                {sku}
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleRefreshFromDb}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="marketing-manager-refresh"
                            aria-label="Refresh saved marketing data"
                            type="button"
                        />
                        <button
                            onClick={handleApplyAll}
                            disabled={!hasContent}
                            className="px-4 py-2 bg-[var(--brand-primary)] text-white rounded-xl font-bold text-sm hover:bg-[var(--brand-primary-dark)] transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                            data-help-id="inventory-marketing-copy-to-form"
                        >
                            <span>📋</span> Copy All to Item
                        </button>
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                {/* Scrollable Body */}
                <div className="flex-1 overflow-y-auto min-h-0">
                    {/* Status Messages */}
                    {(error || applyStatus) && (
                        <div className={`mx-6 mt-4 p-3 rounded-xl text-sm flex items-center gap-2 ${error ? 'bg-red-50 text-red-700 border border-red-200' :
                            applyStatus?.success ? 'bg-green-50 text-green-700 border border-green-200' :
                                'bg-amber-50 text-amber-700 border border-amber-200'
                            }`}>
                            <span>{error ? '⚠️' : applyStatus?.success ? '✓' : 'ℹ️'}</span>
                            {error || applyStatus?.message}
                        </div>
                    )}

                    {/* Data Source Indicator */}
                    {!isLoading && dataSource !== 'none' && !applyStatus && (
                        <div className="mx-6 mt-4 p-3 rounded-xl text-sm flex items-center justify-between gap-2 bg-blue-50 text-blue-700 border border-blue-200">
                            <div className="flex items-center gap-2">
                                <span>ℹ️</span>
                                {dataSource === 'existing'
                                    ? 'Showing previously saved marketing data. Click "Generate with AI" to create fresh content.'
                                    : 'Showing AI-generated content. Edit as needed, then click "Copy to Item" to use it.'}
                            </div>
                            <button
                                onClick={handleRefreshFromDb}
                                className="px-3 py-1 bg-white/70 border border-blue-200 rounded-lg text-[11px] font-bold uppercase tracking-widest text-blue-700 hover:bg-white transition-colors"
                                type="button"
                            >
                                Refresh
                            </button>
                        </div>
                    )}

                    {/* Generate Button */}
                    <div className="px-6 py-4 border-b bg-gray-50/50">
                        <button
                            onClick={handleGenerateAll}
                            disabled={isGenerating}
                            className="w-full py-4 bg-gradient-to-r from-amber-400 to-orange-500 text-white rounded-xl font-bold shadow-lg shadow-amber-500/20 hover:shadow-xl hover:scale-[1.01] active:scale-[0.99] transition-all flex items-center justify-center gap-3 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {isGenerating ? (
                                <>
                                    <span className="wf-emoji-loader text-xl">⏳</span>
                                    <span className="text-lg">Generating with AI... Please wait</span>
                                </>
                            ) : (
                                <>
                                    <span className="text-xl">✨</span>
                                    <span className="text-lg">Generate Marketing Content with AI</span>
                                </>
                            )}
                        </button>
                        <p className="text-xs text-gray-500 mt-2 text-center">
                            AI will analyze your item ({itemName || sku}) and generate optimized title, description, keywords, and more
                        </p>
                    </div>

                    {/* Loading State */}
                    {isLoading && (
                        <div className="p-12 flex flex-col items-center justify-center">
                            <span className="wf-emoji-loader text-4xl">📢</span>
                            <p className="mt-4 text-gray-500">Loading saved marketing data...</p>
                        </div>
                    )}

                    {/* No Content State */}
                    {!isLoading && !hasContent && dataSource === 'none' && (
                        <div className="p-12 flex flex-col items-center justify-center text-gray-500">
                            <span className="text-5xl mb-4">📝</span>
                            <p className="text-lg font-medium">No marketing content yet</p>
                            <p className="text-sm mt-2">Click the "Generate Marketing Content with AI" button above to create content</p>
                        </div>
                    )}

                    {/* Content */}
                    {!isLoading && (hasContent || dataSource !== 'none') && (
                        <>
                            <MarketingForm
                                editedTitle={editedTitle}
                                setEditedTitle={setEditedTitle}
                                editedDescription={editedDescription}
                                setEditedDescription={setEditedDescription}
                                editedKeywords={editedKeywords}
                                newKeyword={newKeyword}
                                setNewKeyword={setNewKeyword}
                                editedAudience={editedAudience}
                                setEditedAudience={setEditedAudience}
                                handleApplyTitle={handleApplyTitle}
                                handleApplyDescription={handleApplyDescription}
                                handleAddKeyword={handleAddKeyword}
                                handleRemoveKeyword={handleRemoveKeyword}
                            />

                            {/* Marketing Intelligence (Collapsible) */}
                            {marketingData && (
                                <div className="px-6 pb-6">
                                    <IntelligenceSection
                                        marketingData={marketingData}
                                        showIntelligence={showIntelligence}
                                        setShowIntelligence={setShowIntelligence}
                                    />
                                </div>
                            )}
                        </>
                    )}
                </div>

                {/* Modal Footer */}
                <div className="px-6 py-4 border-t bg-gray-50 flex justify-between items-center">
                    <div className="text-xs text-gray-400">
                        💡 Tip: After copying content, save the item to keep your changes
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={onClose}
                            className="px-6 py-2 rounded-xl font-bold text-gray-500 hover:bg-gray-100 transition-colors"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};

export default MarketingManagerModal;
