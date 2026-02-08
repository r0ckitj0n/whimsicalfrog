import React, { useState } from 'react';
import { useModalContext } from '../../../context/ModalContext.js';
import { ApiClient } from '../../../core/ApiClient.js';
import type { ItemDimensionsBackfillResult, ItemDimensionsToolsApiResponse } from '../../../types/item-dimensions-tools.js';

interface ItemDimensionsToolsProps {
    onClose?: () => void;
}

export function ItemDimensionsTools({ onClose }: ItemDimensionsToolsProps) {
    const [status, setStatus] = useState<{ text: string; type: 'success' | 'error' | 'info' } | null>(null);
    const [result, setResult] = useState<ItemDimensionsBackfillResult | null>(null);
    const [loading, setLoading] = useState(false);

    const ensureColumns = async () => {
        setLoading(true);
        setStatus({ text: 'Ensuring columns...', type: 'info' });
        setResult(null);

        try {
            const json = await ApiClient.get<ItemDimensionsToolsApiResponse>('/api/item_dimensions_tools.php', { action: 'ensure_columns' });
            const data = json.data ?? json.results ?? json;
            setResult(data);
            setStatus({ text: 'Columns ensured', type: 'success' });
        } catch (err) {
            setStatus({ text: 'Failed to ensure columns', type: 'error' });
        } finally {
            setLoading(false);
        }
    };

    const { confirm: themedConfirm } = useModalContext();

    const runBackfill = async () => {
        const confirmed = await themedConfirm({
            title: 'AI Backfill',
            message: 'Run AI backfill for item shipping attributes?',
            confirmText: 'Run Backfill',
            icon: '🤖',
            iconType: 'warning'
        });

        if (!confirmed) {
            return;
        }


        setLoading(true);
        setStatus({ text: 'Scanning items and generating missing dimensions...', type: 'info' });
        setResult(null);

        try {
            const json = await ApiClient.post<ItemDimensionsToolsApiResponse>('/api/item_dimensions_tools.php', { action: 'run_all', use_ai: 1 });
            const data = json.data ?? json.results ?? json;
            setResult(data);
            setStatus({ text: 'Backfill complete', type: 'success' });
        } catch (err) {
            setStatus({ text: 'Backfill failed', type: 'error' });
        } finally {
            setLoading(false);
        }
    };

    const formatPreview = () => {
        if (!result?.preview?.length) return null;

        return result.preview.slice(0, 8).map((item, i) => {
            const dims = item.LxWxH_in?.join('×') || '';
            return (
                <div key={i} className="text-sm py-1 border-b border-gray-200 last:border-0">
                    <span className="font-semibold">{item.sku || 'N/A'}</span>
                    {item.weight_oz !== undefined && <span className="ml-2">{item.weight_oz} oz</span>}
                    {dims && <span className="ml-2">{dims} in</span>}
                </div>
            );
        });
    };

    return (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) onClose?.();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">📐</span> Sizing Tools
                    </h2>
                    <div className="flex-1" />
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        data-help-id="modal-close"
                        type="button"
                    />
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-6 bg-white min-h-[400px]">
                    <p className="text-gray-600 mb-6">
                        Scan all inventory for blank shipping dimensions, then run the same AI dimensions-generation flow used by Item Information Generate.
                    </p>

                    {/* Actions */}
                    <div className="admin-card mb-4">
                        <h2 className="admin-card-title mb-2">Actions</h2>
                        <div className="flex gap-4">
                            <button
                                type="button"
                                onClick={ensureColumns}
                                disabled={loading}
                                className="btn-text-secondary disabled:opacity-50"
                            >
                                Find Empty Shipping Dimensions
                            </button>
                            <button
                                type="button"
                                onClick={runBackfill}
                                disabled={loading}
                                className="btn-text-primary disabled:opacity-50"
                            >
                                Fix Missing Shipping Dimensions
                            </button>
                        </div>

                        {status && (
                            <div className={`mt-3 text-sm font-medium ${status.type === 'success' ? 'text-green-700' :
                                status.type === 'error' ? 'text-red-700' : 'text-gray-600'
                                }`}>
                                {status.text}
                            </div>
                        )}
                    </div>

                    {/* Results */}
                    <div className="admin-card">
                        <h2 className="admin-card-title mb-2">Results Preview</h2>
                        <div className="bg-gray-50 p-4 rounded border border-gray-200 min-h-[100px]">
                            {result ? (
                                <>
                                    <div className="text-sm mb-2">
                                        <span className="mr-4">Ensured: {result.ensured ? 'Yes' : 'No'}</span>
                                        <span className="mr-4">Scanned: {result.scanned ?? 0}</span>
                                        <span className="mr-4">Missing: {result.missing ?? 0}</span>
                                        <span className="mr-4">Updated: {result.updated ?? 0}</span>
                                        <span>Skipped: {result.skipped ?? 0}</span>
                                    </div>
                                    {result.preview?.length ? (
                                        <div className="mt-3">
                                            <strong className="text-sm">Examples:</strong>
                                            {formatPreview()}
                                        </div>
                                    ) : null}
                                </>
                            ) : (
                                <span className="text-gray-500">No results yet.</span>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default ItemDimensionsTools;
