import React, { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useModalContext } from '../../../context/ModalContext.js';
import { useInventoryArchive } from '../../../hooks/admin/useInventoryArchive.js';
import { AuditTab } from './inventory-archive/AuditTab.js';
import { ArchiveTab } from './inventory-archive/ArchiveTab.js';

interface InventoryArchiveProps {
    onClose?: () => void;
    title?: string;
}

type Tab = 'audit' | 'archive';

export const InventoryArchive: React.FC<InventoryArchiveProps> = ({ onClose, title }) => {
    const {
        metrics,
        categories,
        items,
        audit,
        isLoading,
        error,
        fetchArchive,
        restoreItem,
        nukeItem
    } = useInventoryArchive();

    const [activeTab, setActiveTab] = useState<Tab>('audit');
    const hasFetched = useRef(false);

    useEffect(() => {
        if (hasFetched.current) return;
        fetchArchive();
        hasFetched.current = true;
    }, [fetchArchive]);

    const { confirm: themedConfirm } = useModalContext();

    const handleRestore = async (sku: string) => {
        const confirmed = await themedConfirm({
            title: 'Restore Item',
            message: 'Restore this item to active inventory?',
            confirmText: 'Restore Now',
            icon: '📦',
            iconType: 'warning'
        });

        if (confirmed) {
            const success = await restoreItem(sku);
            if (success && window.WFToast) {
                window.WFToast.success('Item restored successfully.');
            }
        }
    };


    const handleNuke = async (sku: string) => {
        const confirmed = await themedConfirm({
            title: 'Permanently Delete',
            message: 'This will permanently delete the item. Continue?',
            confirmText: 'Delete Forever',
            confirmStyle: 'danger',
            iconKey: 'delete'
        });

        if (confirmed) {
            const result = await nukeItem(sku);
            if (result.success && window.WFToast) {
                window.WFToast.success('Item permanently deleted.');
            } else if (window.WFToast) {
                window.WFToast.error(result.error || 'Failed to permanently delete item.');
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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1400px] max-w-[98vw] h-[95vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header border-b border-gray-100 px-6 py-4 sticky top-0 bg-white z-20 flex items-start justify-between">
                    <div className="flex flex-col gap-4">
                        <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                            <span className="text-2xl">📦</span> {title || 'Inventory Audit'}
                        </h2>

                        <div className="wf-tabs bg-slate-50/50 rounded-xl p-1 border border-slate-100 flex items-center gap-2 self-start">
                            <button
                                onClick={() => setActiveTab('audit')}
                                className={`wf-tab ${activeTab === 'audit' ? 'is-active' : ''}`}
                            >
                                Audit Report
                            </button>
                            <button
                                onClick={() => setActiveTab('archive')}
                                className={`wf-tab ${activeTab === 'archive' ? 'is-active' : ''}`}
                            >
                                Archived Units
                            </button>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={fetchArchive}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="inventory-sync-archives"
                            type="button"
                        />
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                            type="button"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10">
                        {error && (
                            <div className="p-4 mb-6 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3 animate-in fade-in">
                                <span className="text-lg">⚠️</span>
                                {error}
                            </div>
                        )}

                        {isLoading && !metrics ? (
                            <div className="flex flex-col items-center justify-center py-24 text-slate-300 gap-6">
                                <span className="text-6xl animate-pulse">📦</span>
                                <p className="text-[11px] font-black uppercase tracking-[0.2em] italic">Indexing catalog data...</p>
                            </div>
                        ) : (
                            <div className="space-y-10 animate-in fade-in slide-in-from-bottom-2 duration-300">
                                {/* Metrics Grid */}
                                <div className="grid gap-4 grid-cols-2 md:grid-cols-4 lg:grid-cols-8">
                                    {[
                                        { label: 'Archived', value: metrics?.total_archived, sub: 'Total units', color: 'slate' },
                                        { label: 'Tied Up', value: metrics?.total_stock, sub: 'Archived stock', color: 'slate' },
                                        { label: 'Missing Image', value: metrics?.missing_images_count, sub: 'Needs visual', color: metrics?.missing_images_count ? 'orange' : 'slate' },
                                        { label: 'Pricing Alert', value: metrics?.pricing_alerts_count, sub: 'Cost > Retail', color: metrics?.pricing_alerts_count ? 'red' : 'slate' },
                                        { label: 'Out of Stock', value: metrics?.stock_issues_count, sub: 'Zero/Negative', color: metrics?.stock_issues_count ? 'amber' : 'slate' },
                                        { label: 'Content Gaps', value: metrics?.content_issues_count, sub: 'Desc/Category', color: metrics?.content_issues_count ? 'blue' : 'slate' },
                                        { label: 'Avg Aging', value: metrics?.avg_days_archived, sub: 'Days archived', color: 'slate' },
                                        { label: 'Registry Size', value: items.length, sub: 'Tracked units', color: 'slate' }
                                    ].map((card, i) => (
                                        <div key={i} className={`bg-white p-5 border border-slate-100 rounded-[1.5rem] shadow-sm flex flex-col items-center text-center ${card.color === 'red' ? 'ring-2 ring-red-500/10 bg-red-50/10' : ''}`}>
                                            <div className="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 whitespace-nowrap">{card.label}</div>
                                            <div className={`text-2xl font-black ${card.color === 'red' ? 'text-red-600' : card.color === 'orange' ? 'text-orange-500' : card.color === 'amber' ? 'text-amber-500' : card.color === 'blue' ? 'text-blue-500' : 'text-slate-800'}`}>
                                                {card.value ?? '0'}
                                            </div>
                                            <div className="text-[8px] font-bold text-slate-300 uppercase tracking-tight mt-1">{card.sub}</div>
                                        </div>
                                    ))}
                                </div>

                                {activeTab === 'audit' && (
                                    <AuditTab audit={audit} />
                                )}

                                {activeTab === 'archive' && (
                                    <ArchiveTab
                                        items={items}
                                        categories={categories}
                                        onRestore={handleRestore}
                                        onNuke={handleNuke}
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
