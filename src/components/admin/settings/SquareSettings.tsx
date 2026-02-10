import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useSquareSettings, ISquareSettings } from '../../../hooks/admin/useSquareSettings.js';
import { isDraftDirty } from '../../../core/utils.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

import { ENVIRONMENT } from '../../../core/constants.js';

interface SquareSettingsProps {
    onClose?: () => void;
    title?: string;
}

export const SquareSettings: React.FC<SquareSettingsProps> = ({ onClose, title }) => {
    const {
        settings,
        isLoading,
        error,
        fetchSettings,
        saveSettings,
        testConnection,
        syncItems
    } = useSquareSettings();

    const [editSettings, setEditSettings] = useState<ISquareSettings | null>(null);
    const [initialSettings, setInitialSettings] = useState<ISquareSettings | null>(null); // Renamed initialState to initialSettings

    const isDirty = React.useMemo(() => {
        if (!editSettings || !initialSettings) return false;
        return isDraftDirty(editSettings, initialSettings); // Used initialSettings
    }, [editSettings, initialSettings]); // Used initialSettings

    useEffect(() => {
        fetchSettings().then(data => {
            if (data) {
                setEditSettings(data);
                setInitialSettings(data); // Used initialSettings
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        if (!settings || isLoading) return;
        if (!initialSettings) { // Used initialSettings
            setEditSettings({ ...settings });
            setInitialSettings({ ...settings }); // Used initialSettings
        }
    }, [settings, isLoading, initialSettings]); // Used initialSettings

    const handleSave = async (e?: React.FormEvent): Promise<boolean> => {
        e?.preventDefault();
        if (editSettings) {
            const success = await saveSettings(editSettings);
            if (success) {
                setInitialSettings({ ...editSettings! }); // Used initialSettings
                if (window.WFToast) window.WFToast.success('Square configuration updated');
                return true;
            }
            return false;
        }
        return false;
    };

    const handleTest = async () => {
        try {
            const res = await testConnection();
            if (window.WFToast) {
                if (res.success) window.WFToast.success(res.message || 'Connection successful!');
                else window.WFToast.error(res.message || 'Connection failed');
            }
        } catch (err) {
            console.error('[SquareSettings] handleTest error:', err);
            if (window.WFToast) window.WFToast.error('Connection test failed');
        }
    };

    const handleSync = async () => {
        try {
            const res = await syncItems();
            const msg = res.message || (res.success ? 'Sync completed!' : 'Sync failed');
            if (window.WFToast) {
                if (res.success) window.WFToast.success(msg);
                else window.WFToast.error(msg);
            }
        } catch (err) {
            console.error('[SquareSettings] handleSync error:', err);
            if (window.WFToast) window.WFToast.error('Catalog sync failed');
        }
    };

    const handleChange = <K extends keyof ISquareSettings>(field: K, value: ISquareSettings[K]) => {
        if (editSettings) {
            const next = { ...editSettings, [field]: value };

            if (field === 'square_environment') {
                const env = value as ISquareSettings['square_environment'];
                if (env === ENVIRONMENT.PRODUCTION) {
                    next.square_application_id = next.square_production_application_id || '';
                    next.square_location_id = next.square_production_location_id || '';
                    next.square_access_token = next.square_production_access_token || '';
                } else {
                    next.square_application_id = next.square_sandbox_application_id || '';
                    next.square_location_id = next.square_sandbox_location_id || '';
                    next.square_access_token = next.square_sandbox_access_token || '';
                }
            }

            if (field === 'square_application_id') {
                if (next.square_environment === ENVIRONMENT.PRODUCTION) {
                    next.square_production_application_id = String(value);
                } else {
                    next.square_sandbox_application_id = String(value);
                }
            }
            if (field === 'square_location_id') {
                if (next.square_environment === ENVIRONMENT.PRODUCTION) {
                    next.square_production_location_id = String(value);
                } else {
                    next.square_sandbox_location_id = String(value);
                }
            }
            if (field === 'square_access_token') {
                if (next.square_environment === ENVIRONMENT.PRODUCTION) {
                    next.square_production_access_token = String(value);
                } else {
                    next.square_sandbox_access_token = String(value);
                }
            }

            setEditSettings(next);
        }
    };
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty,
        isBlocked: isLoading,
        onClose,
        onSave: () => handleSave(),
        closeAfterSave: true
    });

    if (!editSettings && isLoading) {
        return createPortal(
            <div className="fixed inset-0 z-[var(--z-overlay-topmost)] flex items-center justify-center bg-black/60 backdrop-blur-sm">
                <div className="bg-white rounded-2xl p-12 flex flex-col items-center gap-4">
                    <span className="wf-emoji-loader text-4xl">💳</span>
                    <p className="text-gray-500 font-medium">Connecting to Square...</p>
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
                if (e.target === e.currentTarget) void attemptClose();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[900px] max-w-[95vw] h-[75vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-2 px-4 py-3 sticky top-0 bg-white z-20">
                    <h2 className="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <span>💳</span> {title || 'Square Configuration'}
                    </h2>
                    <div className="flex-1" />
                    <div className="flex items-center gap-2">
                        <button
                            onClick={fetchSettings}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="common-refresh"
                        />
                        <button
                            onClick={handleSave}
                            disabled={isLoading || !isDirty}
                            className={`admin-action-btn btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`}
                            data-help-id="common-save"
                        />
                        <button
                            onClick={() => { void attemptClose(); }}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-8 space-y-12">
                        {error && <div className="p-3 bg-red-50 border border-red-100 text-red-600 text-sm rounded-lg">{error}</div>}

                        <form onSubmit={handleSave} className="space-y-10">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div className="space-y-1">
                                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Application ID</label>
                                    <input
                                        type="text"
                                        value={editSettings?.square_application_id || ''}
                                        onChange={e => handleChange('square_application_id', e.target.value)}
                                        className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-mono"
                                        placeholder="sq0idp-..."
                                    />
                                </div>
                                <div className="space-y-1">
                                    <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Location ID</label>
                                    <input
                                        type="text"
                                        value={editSettings?.square_location_id || ''}
                                        onChange={e => handleChange('square_location_id', e.target.value)}
                                        className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-mono"
                                        placeholder="L..."
                                    />
                                </div>
                            </div>

                            <div className="space-y-1">
                                <label className="text-[10px] font-bold text-gray-500 uppercase ml-1">Access Token</label>
                                <input
                                    type="password"
                                    autoComplete="new-password"
                                    value={editSettings?.square_access_token || ''}
                                    onChange={e => handleChange('square_access_token', e.target.value)}
                                    className="form-input w-full p-2.5 bg-gray-50 border-transparent rounded-lg text-sm font-mono"
                                    placeholder="EAAA..."
                                />
                            </div>

                            <div className="flex items-center justify-between p-6 bg-slate-50 rounded-2xl border border-slate-100">
                                <div className="space-y-1">
                                    <h4 className="text-sm font-bold text-slate-700">Environment Mode</h4>
                                    <p className="text-xs text-slate-500 leading-relaxed max-w-sm">Use Square Sandbox for testing transactions without actual payment processing.</p>
                                </div>
                                <select
                                    value={editSettings?.square_environment || ENVIRONMENT.SANDBOX}
                                    onChange={e => handleChange('square_environment', e.target.value as ISquareSettings['square_environment'])}
                                    className="p-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-600 shadow-sm outline-none focus:ring-2 ring-emerald-100"
                                >
                                    <option value={ENVIRONMENT.SANDBOX}>Sandbox / Testing</option>
                                    <option value={ENVIRONMENT.PRODUCTION}>Production / Live</option>
                                </select>
                            </div>

                            {/* Integration Actions */}
                            <section className="pt-4 flex flex-col sm:flex-row gap-4">
                                <button
                                    type="button"
                                    onClick={handleTest}
                                    className="btn btn-text-secondary flex-1"
                                    data-help-id="square-test-connection"
                                >
                                    <i className="admin-action-btn btn-icon--refresh scale-75" />
                                    <span>{isLoading ? 'Testing...' : 'Test Connection'}</span>
                                </button>
                                <button
                                    type="button"
                                    onClick={handleSync}
                                    className="btn btn-text-primary flex-1"
                                    data-help-id="square-sync-catalog"
                                >
                                    <i className="admin-action-btn btn-icon--refresh invert brightness-0 scale-75" />
                                    <span>{isLoading ? 'Syncing...' : 'Sync Catalog'}</span>
                                </button>
                            </section>


                        </form>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
