import React, { useState, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { useModalContext } from '../../../context/ModalContext.js';
import { ApiClient } from '../../../core/ApiClient.js';


interface CronData {
    token_masked: string;
    web_cron_url: string;
    base_url: string;
}

interface CronManagerProps {
    onClose?: () => void;
    title?: string;
}

export function CronManager({ onClose, title }: CronManagerProps) {
    const [data, setData] = useState<CronData | null>(null);
    const [loading, setLoading] = useState(true);
    const [rotating, setRotating] = useState(false);
    const [running, setRunning] = useState(false);
    const [message, setMessage] = useState<{ text: string; type: 'success' | 'error' } | null>(null);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const json = await ApiClient.get<{ success: boolean; error?: string; data?: CronData }>('/api/cron_manager.php', { action: 'get' });
            if (json.success) {
                setData(json.data || null);
            } else {
                setMessage({ text: json.error || 'Failed to load', type: 'error' });
            }
        } catch (err) {
            setMessage({ text: 'Network error', type: 'error' });
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const { confirm: themedConfirm } = useModalContext();

    const handleRotateToken = async () => {
        const confirmed = await themedConfirm({
            title: 'Rotate Token',
            message: 'Rotate the maintenance token? You will need to update your cron scheduler with the new URL.',
            confirmText: 'Rotate Now',
            confirmStyle: 'danger',
            iconKey: 'rotate'
        });

        if (!confirmed) {
            return;
        }

        setRotating(true);
        setMessage(null);
        try {
            const json = await ApiClient.post<{ success: boolean; error?: string; data?: CronData }>('/api/cron_manager.php?action=rotate_token', {});
            if (json.success) {
                setData(json.data || null);
                setMessage({ text: 'Token rotated successfully', type: 'success' });
            } else {
                setMessage({ text: json.error || 'Failed to rotate', type: 'error' });
            }
        } catch (err) {
            setMessage({ text: 'Network error', type: 'error' });
        } finally {
            setRotating(false);
        }
    };

    const handleRunNow = async () => {
        setRunning(true);
        setMessage(null);
        try {
            const json = await ApiClient.post<{ success: boolean; error?: string; maintenance_result?: { deleted?: number; data?: { deleted?: number } } }>('/api/cron_manager.php?action=run_now', {});
            if (json.success) {
                const result = json.maintenance_result;
                const deleted = result?.deleted ?? result?.data?.deleted ?? 0;
                setMessage({ text: `Maintenance ran: ${deleted} sessions pruned`, type: 'success' });
            } else {
                setMessage({ text: json.error || 'Failed to run', type: 'error' });
            }
        } catch (err) {
            setMessage({ text: 'Network error', type: 'error' });
        } finally {
            setRunning(false);
        }
    };

    const handleCopyUrl = async () => {
        if (!data?.web_cron_url) return;
        try {
            await navigator.clipboard.writeText(data.web_cron_url);
            setMessage({ text: 'URL copied to clipboard', type: 'success' });
        } catch {
            setMessage({ text: 'Failed to copy', type: 'error' });
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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[900px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">⏱️</span> {title || 'Cron Job Manager'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-4">
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="cron-manager-close"
                            type="button"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-8">
                    <p className="text-gray-600 mb-6">
                        Configure and verify scheduled maintenance tasks (e.g., pruning old PHP sessions).
                    </p>

                    {message && (
                        <div className={`p-3 rounded mb-4 ${message.type === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700'}`}>
                            {message.type === 'success' ? '✅' : '⚠️'} {message.text}
                        </div>
                    )}

                    {loading ? (
                        <div className="flex items-center justify-center py-12">
                            <span className="text-gray-500">Loading system cron data...</span>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {/* Token Management */}
                            <div className="admin-card">
                                <h2 className="admin-card-title mb-2">Token Management</h2>
                                <p className="text-gray-600 mb-2 text-sm">
                                    The maintenance token is used to authenticate cron requests.
                                </p>
                                <div className="p-4 bg-slate-50 rounded-xl mb-3 flex items-center justify-between border border-slate-100">
                                    <div>
                                        <div className="text-[10px] font-black uppercase text-slate-400 mb-1">Current Masked Token</div>
                                        <code className="font-mono font-bold text-slate-700">{data?.token_masked}</code>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={handleRotateToken}
                                        disabled={rotating}
                                        className="btn-text-secondary disabled:opacity-50"
                                    >
                                        {rotating ? 'Rotating...' : 'Rotate Token'}
                                    </button>
                                </div>
                                <p className="text-[10px] text-slate-400 italic">
                                    After rotation, update your hosting scheduler with the new URL.
                                </p>
                            </div>

                            {/* Web Cron URL */}
                            <div className="admin-card">
                                <h2 className="admin-card-title mb-2">Web Cron (URL Trigger)</h2>
                                <p className="text-gray-600 mb-2 text-sm">
                                    Use this URL in your hosting provider's cron scheduler for daily session cleanup:
                                </p>
                                <div className="relative group mb-4">
                                    <pre className="bg-white text-slate-700 p-4 rounded-xl border border-slate-200 overflow-x-auto text-xs font-mono">
                                        <code>{data?.web_cron_url}</code>
                                    </pre>
                                    <button
                                        type="button"
                                        onClick={handleCopyUrl}
                                        className="absolute top-2 right-2 p-2 bg-slate-50 hover:bg-slate-100 rounded-lg text-slate-500 transition-colors border border-slate-100"
                                        data-help-id="cron-url-copy"
                                    >
                                        📋
                                    </button>
                                </div>
                                <div className="grid grid-cols-2 gap-4 mb-4">
                                    <div className="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                        <div className="text-[10px] font-black uppercase text-slate-400 mb-1">Schedule</div>
                                        <div className="text-sm font-bold text-slate-700">Daily @ 3:10 AM</div>
                                    </div>
                                    <div className="bg-slate-50 p-3 rounded-xl border border-slate-100">
                                        <div className="text-[10px] font-black uppercase text-slate-400 mb-1">Method</div>
                                        <div className="text-sm font-bold text-slate-700">HTTP GET</div>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    onClick={handleRunNow}
                                    disabled={running}
                                    className="btn-text-primary w-full py-3 mt-4"
                                >
                                    {running ? 'Executing...' : 'Run Manual Maintenance'}
                                </button>
                            </div>

                            {/* System Cron Instructions */}
                            <div className="admin-card">
                                <h2 className="admin-card-title mb-2">System Cron (SSH)</h2>
                                <p className="text-gray-600 mb-3 text-sm">
                                    If you have SSH access, install a system-level cron:
                                </p>
                                <pre className="bg-slate-50 p-3 rounded-xl border border-slate-200 overflow-x-auto text-[10px] font-mono mb-2 text-slate-600">
                                    <code>SESSION_DIR={data?.base_url}/sessions /scripts/maintenance/prune_sessions.sh 2</code>
                                </pre>
                                <div className="mt-4 p-4 border-2 border-dashed border-slate-100 rounded-xl">
                                    <div className="text-[10px] font-black uppercase text-slate-300 mb-1">Crontab Entry Example</div>
                                    <code className="text-[10px] text-slate-500">10 3 * * * SESSION_DIR={data?.base_url}/sessions /scripts/maintenance/prune_sessions.sh 2 &gt;&gt; /var/log/prune_sessions.log 2&gt;&amp;1</code>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
}

export default CronManager;
