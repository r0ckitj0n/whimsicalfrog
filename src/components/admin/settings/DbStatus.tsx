import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useDbStatus, IDbStatus } from '../../../hooks/admin/useDbStatus.js';

interface DbStatusProps {
    onClose?: () => void;
    title?: string;
}

export const DbStatus: React.FC<DbStatusProps> = ({ onClose, title }) => {
    const {
        localStatus,
        liveStatus,
        isLoading,
        error,
        fetchStatus
    } = useDbStatus();

    const [isRefreshing, setIsRefreshing] = useState(false);

    useEffect(() => {
        fetchStatus();
    }, [fetchStatus]);

    const handleRefresh = async () => {
        setIsRefreshing(true);
        await fetchStatus();
        setIsRefreshing(false);
    };

    if (isLoading && !localStatus) {
        return (
            <div className="flex flex-col items-center justify-center p-12 text-gray-500">
                <span className="wf-emoji-loader">🗄️</span>
                <p>Probing database environments...</p>
            </div>
        );
    }

    const StatusCard = ({ title, status, env }: { title: string, status: IDbStatus | null | undefined, env: string }) => (
        <div className="bg-white border rounded-xl shadow-sm overflow-hidden flex flex-col">
            <div className="px-4 py-3 bg-gray-50 border-b flex items-center justify-between">
                <h3 className="font-bold text-gray-700 flex items-center gap-2">
                    {title}
                </h3>
                <span className={`flex items-center gap-1.5 text-[10px] font-black uppercase px-2 py-0.5 rounded-full border ${status?.online ? 'bg-[var(--brand-accent-bg)] text-[var(--brand-accent)] border-[var(--brand-accent-border)]' : 'bg-[var(--brand-error-bg)] text-[var(--brand-error)] border-[var(--brand-error-border)]'
                    }`}>
                    <span className={`w-1.5 h-1.5 rounded-full ${status?.online ? 'bg-[var(--brand-accent)]' : 'bg-[var(--brand-error)]'}`}></span>
                    {status?.online ? 'Online' : 'Offline'}
                </span>
            </div>
            <div className="p-4 space-y-3 flex-1">
                {status?.online ? (
                    <>
                        <div className="flex justify-between items-center text-sm">
                            <span className="text-gray-500 font-medium">Database</span>
                            <span className="font-mono font-bold text-gray-900">{status.database || '—'}</span>
                        </div>
                        <div className="flex justify-between items-center text-sm">
                            <span className="text-gray-500 font-medium">Engine</span>
                            <span className="font-mono font-bold text-gray-900">MySQL {status.mysql_version || ''}</span>
                        </div>
                        <div className="pt-2 mt-2 border-t text-[10px] text-gray-400 font-mono truncate opacity-60">
                            Remote: {status.host || 'Local Socket'}
                        </div>
                    </>
                ) : status ? (
                    <div className="flex-1 flex flex-col items-center justify-center py-4 px-2 bg-[var(--brand-error-bg)] rounded-xl border border-dashed border-[var(--brand-error-border)]">
                        <span className="text-2xl mb-2">🚫</span>
                        <div className="text-xs text-[var(--brand-error)] font-bold text-center leading-relaxed">
                            {status.error || 'Connection failed'}
                        </div>
                    </div>
                ) : (
                    <div className="flex-1 flex flex-col items-center justify-center py-4 px-2 bg-gray-50 rounded-xl border border-dashed border-gray-200">
                        <span className="text-2xl mb-2 opacity-40">🔒</span>
                        <div className="text-[10px] text-gray-400 font-bold text-center uppercase tracking-wider">
                            Access Restricted
                        </div>
                        <div className="text-[9px] text-gray-400 text-center mt-1">
                            Requires DevOps or SuperAdmin
                        </div>
                    </div>
                )}
            </div>
            <div className="px-4 py-3 bg-gray-50 border-t flex gap-2">
                <button
                    onClick={handleRefresh}
                    disabled={isRefreshing}
                    className="btn-text-primary flex-1 text-xs"
                >
                    {isRefreshing ? 'Testing...' : 'Test Connection'}
                </button>
            </div>
        </div>
    );

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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1200px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">📊</span> {title || 'DB Status Dashboard'}
                    </h2>
                    <div className="flex-1" />
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        data-help-id="common-close"
                        type="button"
                    />
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10 space-y-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <h2 className="text-lg font-bold text-gray-800">Database Health & Sync Status</h2>
                            </div>
                            <button
                                onClick={handleRefresh}
                                disabled={isRefreshing}
                                className="btn-text-primary text-xs"
                                data-help-id="common-refresh"
                            >
                                {isRefreshing ? 'Refreshing...' : 'Refresh Status'}
                            </button>
                        </div>

                        {error && (
                            <div className="p-4 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm rounded-lg flex items-center gap-3">
                                <span className="text-xl">⚠️</span> {error}
                            </div>
                        )}

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <StatusCard title="Local Environment" status={localStatus} env="local" />
                            <StatusCard title="Live Environment" status={liveStatus} env="live" />

                            {/* Sync Summary */}
                            <div className="bg-[var(--brand-primary)] rounded-xl shadow-lg p-5 text-white flex flex-col justify-between">
                                <div>
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="font-bold flex items-center gap-2">
                                            Sync Status
                                        </h3>
                                        <span className="text-[10px] bg-white/20 px-2 py-0.5 rounded-full uppercase font-black">
                                            Real-time
                                        </span>
                                    </div>
                                    {localStatus?.online && liveStatus?.online ? (
                                        <p className="text-sm text-white/80 leading-relaxed">
                                            Both environments are online. Use the Advanced Tools to run a full database comparison and sync audit.
                                        </p>
                                    ) : (
                                        <p className="text-sm text-white/80 italic">
                                            Both environments must be online to compare sync status.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Actions & Tools */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div className="bg-white border rounded-xl shadow-sm p-4">
                                <h3 className="font-bold text-gray-700 flex items-center gap-2 mb-4">
                                    Quick Actions
                                </h3>
                                <button
                                    onClick={() => window.open('https://www.ionos.com/', '_blank')}
                                    className="btn-text-primary text-xs w-full"
                                    data-help-id="db-web-manager"
                                >
                                    Host Login
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
