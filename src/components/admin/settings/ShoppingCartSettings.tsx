import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { useShoppingCartSettings, IShoppingCartSettings } from '../../../hooks/admin/useShoppingCartSettings.js';
import { isDraftDirty } from '../../../core/utils.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

interface ShoppingCartSettingsProps {
    onClose?: () => void;
    title?: string;
}

export const ShoppingCartSettings: React.FC<ShoppingCartSettingsProps> = ({ onClose, title }) => {
    const { settings, isLoading, error, saveSettings, fetchSettings } = useShoppingCartSettings();
    const [localSettings, setLocalSettings] = useState<IShoppingCartSettings>(settings);

    useEffect(() => {
        setLocalSettings(settings);
    }, [settings]);

    const handleToggle = (field: keyof IShoppingCartSettings) => {
        setLocalSettings(prev => ({
            ...prev,
            [field]: !prev[field]
        }));
    };

    const handleNumberChange = (field: keyof IShoppingCartSettings, value: string) => {
        setLocalSettings(prev => ({
            ...prev,
            [field]: parseFloat(value) || 0
        }));
    };

    const handleSave = async (): Promise<boolean> => {
        const success = await saveSettings(localSettings);
        if (success && window.WFToast) {
            window.WFToast.success('Shopping cart settings updated!');
        }
        return success;
    };

    const isDirty = isDraftDirty(localSettings, settings);
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty,
        isBlocked: isLoading,
        onClose,
        onSave: handleSave,
        closeAfterSave: true
    });

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[800px] max-w-[95vw] h-[70vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">🛒</span> {title || 'Cart Settings'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-4">
                        <button
                            type="button"
                            onClick={handleSave}
                            disabled={isLoading || !isDirty}
                            className={`admin-action-btn btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="cart-save-changes"
                        />
                        <button
                            type="button"
                            onClick={fetchSettings}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="common-refresh"
                        />
                        <button
                            onClick={() => { void attemptClose(); }}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10">
                        {error && (
                            <div className="p-4 mb-8 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-2xl animate-in shake">
                                Error: {error}
                            </div>
                        )}

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 animate-in fade-in slide-in-from-bottom-2">
                            {/* Behavior Settings */}
                            <div className="space-y-6">
                                <h3 className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Cart Behavior</h3>

                                <label className="group flex items-start gap-4 p-4 rounded-2xl border-2 border-slate-50 hover:border-blue-50 hover:bg-blue-50/10 cursor-pointer transition-all">
                                    <input
                                        type="checkbox"
                                        checked={localSettings.open_cart_on_add}
                                        onChange={() => handleToggle('open_cart_on_add')}
                                        className="mt-1"
                                    />
                                    <div>
                                        <div className="text-sm font-black text-slate-900 mb-1">Open Cart on Add</div>
                                        <p className="text-xs text-slate-400 leading-relaxed">Automatically show the cart modal whenever a customer adds an item.</p>
                                    </div>
                                </label>

                                <label className="group flex items-start gap-4 p-4 rounded-2xl border-2 border-slate-50 hover:border-blue-50 hover:bg-blue-50/10 cursor-pointer transition-all">
                                    <input
                                        type="checkbox"
                                        checked={localSettings.merge_duplicates}
                                        onChange={() => handleToggle('merge_duplicates')}
                                        className="mt-1"
                                    />
                                    <div>
                                        <div className="text-sm font-black text-slate-900 mb-1">Merge Duplicates</div>
                                        <p className="text-xs text-slate-400 leading-relaxed">Combine identical items into a single line with increased quantity.</p>
                                    </div>
                                </label>

                                <label className="group flex items-start gap-4 p-4 rounded-2xl border-2 border-slate-50 hover:border-blue-50 hover:bg-blue-50/10 cursor-pointer transition-all">
                                    <input
                                        type="checkbox"
                                        checked={localSettings.confirm_clear_cart}
                                        onChange={() => handleToggle('confirm_clear_cart')}
                                        className="mt-1"
                                    />
                                    <div>
                                        <div className="text-sm font-black text-slate-900 mb-1">Clear Cart Confirmation</div>
                                        <p className="text-xs text-slate-400 leading-relaxed">Ask for confirmation before allowing a customer to empty their cart.</p>
                                    </div>
                                </label>
                            </div>

                            {/* Recommendation & Limits */}
                            <div className="space-y-6">
                                <h3 className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4">Upsells & Limits</h3>

                                <label className="group flex items-start gap-4 p-4 rounded-2xl border-2 border-slate-50 hover:border-blue-50 hover:bg-blue-50/10 cursor-pointer transition-all">
                                    <input
                                        type="checkbox"
                                        checked={localSettings.show_upsells}
                                        onChange={() => handleToggle('show_upsells')}
                                        className="mt-1"
                                    />
                                    <div>
                                        <div className="text-sm font-black text-slate-900 mb-1">Show Upsells</div>
                                        <p className="text-xs text-slate-400 leading-relaxed">Display AI-driven cross-sell recommendations inside the cart view.</p>
                                    </div>
                                </label>

                                <div className="p-6 bg-slate-50 border border-slate-100 rounded-2xl space-y-4">
                                    <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">Checkout Requirements</h4>
                                    <div className="space-y-2">
                                        <label className="text-xs font-bold text-slate-600">Minimum Order Total ($)</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={localSettings.minimum_checkout_total}
                                            onChange={(e) => handleNumberChange('minimum_checkout_total', e.target.value)}
                                            className="w-full p-3 bg-white border-2 border-slate-200 rounded-xl text-sm font-black outline-none focus:border-blue-200 transition-all"
                                        />
                                        <p className="text-[10px] text-slate-400 font-medium italic">Set to 0.00 to disable minimum requirement</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
