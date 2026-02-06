import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useModalContext } from '../../../context/ModalContext.js';
import { useShippingSettings, IShippingRates } from '../../../hooks/admin/useShippingSettings.js';
import { isDraftDirty } from '../../../core/utils.js';



interface ShippingSettingsProps {
    onClose?: () => void;
    title?: string;
}

export const ShippingSettings: React.FC<ShippingSettingsProps> = ({ onClose, title }) => {
    const {
        isLoading,
        error,
        rates,
        fetchRates,
        saveRates,
        runDimensionsTool
    } = useShippingSettings();

    const [editRates, setEditRates] = useState<Partial<IShippingRates>>({});
    const [initialRates, setInitialRates] = useState<Partial<IShippingRates> | null>(null);
    const [toolResult, setToolResult] = useState<{ updated: number; skipped: number } | null>(null);
    const hasFetched = React.useRef(false);

    useEffect(() => {
        fetchRates();
    }, [fetchRates]);

    useEffect(() => {
        if (hasFetched.current || isLoading || !rates) return;
        hasFetched.current = true;
        setEditRates({ ...rates });
        setInitialRates({ ...rates });
    }, [rates, isLoading]);

    useEffect(() => {
        document.body.classList.add('wf-modal-open');
        return () => {
            document.body.classList.remove('wf-modal-open');
        };
    }, []);

    const handleSave = async (payload: Partial<IShippingRates>, message: string) => {
        const success = await saveRates(payload);
        if (success) {
            setInitialRates(prev => prev ? ({ ...prev, ...payload }) : payload);
            if (window.WFToast) window.WFToast.success(message);
        }
    };

    const handleSaveBaseRates = () => {
        handleSave({
            free_shipping_threshold: editRates.free_shipping_threshold,
            local_delivery_fee: editRates.local_delivery_fee,
            shipping_rate_usps: editRates.shipping_rate_usps,
            shipping_rate_fedex: editRates.shipping_rate_fedex,
            shipping_rate_ups: editRates.shipping_rate_ups
        }, 'Base shipping rates saved!');
    };

    const handleSavePerLbRates = () => {
        handleSave({
            shipping_rate_per_lb_usps: editRates.shipping_rate_per_lb_usps,
            shipping_rate_per_lb_fedex: editRates.shipping_rate_per_lb_fedex,
            shipping_rate_per_lb_ups: editRates.shipping_rate_per_lb_ups
        }, 'Per-pound rates saved!');
    };

    const { confirm: themedConfirm } = useModalContext();

    const handleRunBackfill = async () => {
        const confirmed = await themedConfirm({
            title: 'AI Backfill All',
            message: 'Run AI backfill for all item dimensions and weights? This uses AI credits.',
            confirmText: 'Run Now',
            iconKey: 'sparkles'
        });

        if (confirmed) {
            const res = await runDimensionsTool('run_all');
            setToolResult(res);
            if (res) {
                if (window.WFToast) {
                    window.WFToast.success(`AI Backfill complete! Updated: ${res.updated}, Skipped: ${res.skipped}`);
                }
            }
        }
    };

    const handleVerifySchema = async () => {
        const res = await runDimensionsTool('ensure_columns');
        if (res) {
            if (window.WFToast) window.WFToast.success('Database schema verified!');
        }
    };


    const handleChange = (field: keyof IShippingRates, value: string) => {
        setEditRates({ ...editRates, [field]: value });
    };

    const isDirty = React.useMemo(() => {
        if (!initialRates) return false;
        return isDraftDirty(editRates, initialRates);
    }, [editRates, initialRates]);

    if (!rates && isLoading) {
        return createPortal(
            <div className="fixed inset-0 z-[var(--z-overlay-topmost)] flex items-center justify-center bg-black/60 backdrop-blur-sm">
                <div className="bg-white rounded-2xl p-12 flex flex-col items-center gap-4">
                    <span className="wf-emoji-loader text-4xl">🚚</span>
                    <p className="text-gray-500 font-medium">Loading logistics...</p>
                </div>
            </div>,
            document.body
        );
    }

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[900px] max-w-[95vw] h-[80vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-2 px-4 py-3 sticky top-0 bg-white z-20">
                    <h2 className="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <span>🚚</span> {title || 'Shipping Rates'}
                    </h2>
                    <div className="flex-1" />
                    <div className="flex items-center gap-2">
                        <button
                            onClick={fetchRates}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="common-refresh"
                        />
                        <button
                            onClick={() => handleSave(editRates, 'All logistics saved!')}
                            disabled={isLoading || !isDirty}
                            className={`admin-action-btn btn-icon--save ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="common-save"
                        />
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-8 space-y-12">
                        {error && <div className="p-3 bg-red-50 border border-red-100 text-red-600 text-sm rounded-lg">{error}</div>}

                        {/* Base Rates */}
                        <section className="space-y-6">
                            <div className="flex justify-between items-center border-b border-gray-100 pb-2">
                                <h4 className="text-xs font-black text-gray-400 uppercase tracking-widest">Base Rates & Thresholds</h4>

                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div className="space-y-1">
                                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Free Shipping Threshold ($)</label>
                                    <input
                                        type="number" step="0.01"
                                        value={editRates.free_shipping_threshold || ''}
                                        onChange={e => handleChange('free_shipping_threshold', e.target.value)}
                                        className="w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-semibold"
                                        placeholder="0.00 (0 to disable)"
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Local Delivery Fee ($)</label>
                                    <input
                                        type="number" step="0.01"
                                        value={editRates.local_delivery_fee || ''}
                                        onChange={e => handleChange('local_delivery_fee', e.target.value)}
                                        className="w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-semibold"
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="p-4 bg-gray-50 rounded-xl border border-transparent hover:border-emerald-100 transition-colors">
                                    <label className="block text-[10px] font-black text-gray-400 uppercase mb-3 text-center">USPS Base</label>
                                    <input type="number" step="0.01" value={editRates.shipping_rate_usps || ''} onChange={e => handleChange('shipping_rate_usps', e.target.value)} className="w-full p-2 border-none bg-white rounded-lg text-sm font-bold text-center shadow-sm" />
                                </div>
                                <div className="p-4 bg-gray-50 rounded-xl border border-transparent hover:border-blue-100 transition-colors">
                                    <label className="block text-[10px] font-black text-gray-400 uppercase mb-3 text-center">FedEx Base</label>
                                    <input type="number" step="0.01" value={editRates.shipping_rate_fedex || ''} onChange={e => handleChange('shipping_rate_fedex', e.target.value)} className="w-full p-2 border-none bg-white rounded-lg text-sm font-bold text-center shadow-sm" />
                                </div>
                                <div className="p-4 bg-gray-50 rounded-xl border border-transparent hover:border-red-100 transition-colors">
                                    <label className="block text-[10px] font-black text-gray-400 uppercase mb-3 text-center">UPS Base</label>
                                    <input type="number" step="0.01" value={editRates.shipping_rate_ups || ''} onChange={e => handleChange('shipping_rate_ups', e.target.value)} className="w-full p-2 border-none bg-white rounded-lg text-sm font-bold text-center shadow-sm" />
                                </div>
                            </div>
                        </section>

                        {/* Per Pound Rates */}
                        <section className="space-y-6">
                            <div className="flex justify-between items-center border-b border-gray-100 pb-2">
                                <h4 className="text-xs font-black text-gray-400 uppercase tracking-widest">Weight-Based Adders (Per Lb)</h4>

                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 transition-colors">
                                    <label className="block text-[10px] font-bold text-gray-500 uppercase mb-3 text-center">USPS + / lb</label>
                                    <input type="number" step="0.01" value={editRates.shipping_rate_per_lb_usps || ''} onChange={e => handleChange('shipping_rate_per_lb_usps', e.target.value)} className="w-full p-2 bg-white border border-gray-100 rounded-lg text-sm text-center font-mono" />
                                </div>
                                <div className="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 transition-colors">
                                    <label className="block text-[10px] font-bold text-gray-500 uppercase mb-3 text-center">FedEx + / lb</label>
                                    <input type="number" step="0.01" value={editRates.shipping_rate_per_lb_fedex || ''} onChange={e => handleChange('shipping_rate_per_lb_fedex', e.target.value)} className="w-full p-2 bg-white border border-gray-100 rounded-lg text-sm text-center font-mono" />
                                </div>
                                <div className="p-4 border border-gray-100 rounded-xl hover:bg-gray-50 transition-colors">
                                    <label className="block text-[10px] font-bold text-gray-500 uppercase mb-3 text-center">UPS + / lb</label>
                                    <input type="number" step="0.01" value={editRates.shipping_rate_per_lb_ups || ''} onChange={e => handleChange('shipping_rate_per_lb_ups', e.target.value)} className="w-full p-2 bg-white border border-gray-100 rounded-lg text-sm text-center font-mono" />
                                </div>
                            </div>
                        </section>

                        {/* Automation Tools */}
                        <section className="p-6 bg-slate-50 border border-slate-100 rounded-2xl space-y-4">
                            <h4 className="font-bold text-slate-700 flex items-center gap-2">
                                <span className="text-xl">🤖</span> Shipping Data Automation
                            </h4>
                            <p className="text-xs text-slate-500 leading-relaxed max-w-2xl">Ensures all inventory items have correctly formatted weight and dimension columns, then uses AI to predict missing values based on item descriptions.</p>
                            <div className="flex gap-4 pt-2">
                                <button
                                    type="button"
                                    onClick={handleVerifySchema}
                                    disabled={isLoading}
                                    className="btn btn-text-primary"
                                    data-help-id="shipping-verify-schema"
                                >
                                    Verify Schema
                                </button>
                                <button
                                    type="button"
                                    onClick={handleRunBackfill}
                                    disabled={isLoading}
                                    className="btn btn-text-primary"
                                    data-help-id="shipping-ai-backfill"
                                >
                                    🤖 AI Backfill All
                                </button>
                            </div>
                            {toolResult && (
                                <div className="p-4 bg-white border border-slate-100 rounded-xl font-mono text-[10px] text-slate-600 animate-in zoom-in-95 mt-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-1">
                                            <p className="text-[8px] font-black text-slate-400 uppercase">Success Status</p>
                                            <p className="text-emerald-600 font-bold">READY</p>
                                        </div>
                                        <div className="space-y-1">
                                            <p className="text-[8px] font-black text-slate-400 uppercase">Processing Log</p>
                                            <p>Updated {toolResult.updated || 0} / Skipped {toolResult.skipped || 0}</p>
                                        </div>
                                    </div>
                                </div>
                            )}
                        </section>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
