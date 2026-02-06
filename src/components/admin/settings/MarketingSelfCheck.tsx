import React, { useEffect } from 'react';
import { useMarketingSelfCheck, ISelfCheckData } from '../../../hooks/admin/useMarketingSelfCheck.js';

interface MarketingSelfCheckProps {
    onClose?: () => void;
}

export const MarketingSelfCheck: React.FC<MarketingSelfCheckProps> = ({ onClose }) => {
    const {
        data,
        isLoading,
        error,
        runSelfCheck
    } = useMarketingSelfCheck();

    const typedData = data;

    useEffect(() => {
        // Only auto-run if we haven't loaded any data yet
        if (typedData) return;
        runSelfCheck(false);
    }, [runSelfCheck, typedData]);

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'pass': return <span className="text-xl">✅</span>;
            case 'fail': return <span className="text-xl">❌</span>;
            case 'warn': return <span className="text-xl">⚠️</span>;
            default: return null;
        }
    };

    const getStatusClass = (status: string) => {
        switch (status) {
            case 'pass': return 'bg-[var(--brand-accent)]/5 border-[var(--brand-accent)]/10';
            case 'fail': return 'bg-[var(--brand-error-bg)] border-[var(--brand-error-border)]';
            case 'warn': return 'bg-[var(--brand-secondary)]/5 border-[var(--brand-secondary)]/10';
            default: return 'bg-gray-50 border-gray-100';
        }
    };

    if (isLoading && !typedData) {
        return (
            <div className="flex flex-col items-center justify-center p-12 text-gray-500">
                <span className="wf-emoji-loader">🔍</span>
                <p>Running Marketing AI diagnostic...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="p-6 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] rounded-lg">
                <p>{error}</p>
                <button
                    onClick={() => runSelfCheck(true)}
                    className="mt-2 btn btn-secondary px-4 py-2 bg-transparent border-0 text-[var(--brand-secondary)] hover:bg-[var(--brand-secondary)]/5 transition-all font-bold uppercase tracking-widest text-xs"
                >
                    Retry
                </button>
            </div>
        );
    }

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
                        <span className="text-2xl">🤖</span> Marketing AI Check
                    </h2>
                    <div className="flex-1" />
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        data-help-id="modal-close"
                        type="button"
                    />
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto p-8">
                    <div className="mb-8 p-4 bg-gray-50 border border-gray-100 rounded-lg">
                        <p className="text-sm text-gray-600 leading-relaxed">
                            This diagnostic tool verifies the health and integrity of the <strong>Whimsical Marketing AI</strong> infrastructure.
                            It audits the database schema for required AI fields, ensures marketing suggestions are being correctly
                            persisted, and validates the integration pipeline between the management interface and the intelligence engine.
                        </p>
                    </div>

                    <div className="space-y-6">
                        <div className="flex items-center justify-between px-4 py-2 bg-white border rounded-lg shadow-sm">
                            <div className="flex items-center gap-4">
                                <div className="flex items-center gap-2">
                                    <span className="text-sm font-medium text-gray-500 uppercase tracking-wider">Results:</span>
                                    <div className="flex gap-2">
                                        <span className="flex items-center gap-1 text-xs font-bold text-[var(--brand-accent)] bg-[var(--brand-accent)]/10 px-2 py-0.5 rounded-full">
                                            {typedData?.summary?.pass || 0} Pass
                                        </span>
                                        <span className="flex items-center gap-1 text-xs font-bold text-[var(--brand-secondary)] bg-[var(--brand-secondary)]/10 px-2 py-0.5 rounded-full">
                                            {typedData?.summary?.warn || 0} Warning
                                        </span>
                                        <span className="flex items-center gap-1 text-xs font-bold text-[var(--brand-error)] bg-[var(--brand-error-bg)] px-2 py-0.5 rounded-full">
                                            {typedData?.summary?.fail || 0} Fail
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button
                                onClick={() => runSelfCheck(true)}
                                disabled={isLoading}
                                className={`btn btn-secondary px-4 py-2 bg-transparent border-0 text-[var(--brand-secondary)] hover:bg-[var(--brand-secondary)]/5 transition-all flex items-center gap-2 font-bold uppercase tracking-widest text-xs ${isLoading ? 'opacity-50 cursor-not-allowed' : ''}`}
                            >
                                {isLoading ? (
                                    <>
                                        <span className="wf-emoji-loader text-base">🔍</span>
                                        Running...
                                    </>
                                ) : 'Run Diagnostic'}
                            </button>
                        </div>

                        <div className="space-y-3">
                            {typedData?.checks?.map((check) => (
                                <div key={check.id} className={`border rounded-lg p-4 ${getStatusClass(check.status)}`}>
                                    <div className="flex items-start justify-between">
                                        <div className="flex gap-3">
                                            {getStatusIcon(check.status)}
                                            <div>
                                                <h3 className="text-sm font-bold text-gray-900">{check.label}</h3>
                                                <div className="mt-2 space-y-2">
                                                    {Object.entries(check.details || {}).map(([key, value]) => (
                                                        <div key={key} className="flex items-center gap-2 text-xs">
                                                            <span className="text-[10px] text-gray-400">▶</span>
                                                            <span className="font-mono text-gray-500">{key}:</span>
                                                            <span className="font-medium text-gray-700">
                                                                {typeof value === 'boolean' ? (value ? 'Yes' : 'No') : String(value)}
                                                            </span>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        </div>
                                        <span className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded-full border ${check.status === 'pass' ? 'text-[var(--brand-accent)] border-[var(--brand-accent)]/20 bg-white' :
                                            check.status === 'fail' ? 'text-[var(--brand-error)] border-[var(--brand-error-border)] bg-white' :
                                                'text-[var(--brand-secondary)] border-[var(--brand-secondary)]/20 bg-white'
                                            }`}>
                                            {check.status}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};
