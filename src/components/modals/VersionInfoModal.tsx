import React from 'react';
import { createPortal } from 'react-dom';
import type { IVersionInfo } from '../../types/version.js';

interface VersionInfoModalProps {
    isOpen: boolean;
    onClose: () => void;
    versionInfo: IVersionInfo | null;
    isLoading: boolean;
    error: string | null;
    onRefresh: () => void;
}

const renderTimestamp = (value: string | null) => {
    if (!value) {
        return 'Unavailable';
    }
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }
    return `${parsed.toLocaleString()} (${value})`;
};

export const VersionInfoModal: React.FC<VersionInfoModalProps> = ({
    isOpen,
    onClose,
    versionInfo,
    isLoading,
    error,
    onRefresh
}) => {
    if (!isOpen) {
        return null;
    }

    return createPortal(
        <div
            className="fixed inset-0 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
            style={{ zIndex: 'var(--wf-z-modal)' }}
            onClick={(event) => {
                if (event.target === event.currentTarget) {
                    onClose();
                }
            }}
        >
            <div className="w-full max-w-lg rounded-2xl bg-white shadow-2xl border border-gray-200">
                <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <h2 className="text-lg font-semibold text-gray-900">Build & Deploy Version</h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close admin-modal-close"
                        aria-label="Close"
                        data-help-id="modal-close"
                    />
                </div>

                <div className="space-y-3 px-5 py-4 text-sm text-gray-800">
                    {isLoading && <p>Loading version info...</p>}
                    {!isLoading && error && <p className="text-red-600">{error}</p>}

                    {!isLoading && !error && versionInfo && (
                        <>
                            <p><span className="font-semibold">Commit:</span> {versionInfo.commit_short_hash || 'Unavailable'}</p>
                            <p><span className="font-semibold">Commit Subject:</span> {versionInfo.commit_subject || 'Unavailable'}</p>
                            <p><span className="font-semibold">Built:</span> {renderTimestamp(versionInfo.built_at)}</p>
                            <p><span className="font-semibold">Deployed To Live:</span> {renderTimestamp(versionInfo.deployed_for_live_at)}</p>
                            <p><span className="font-semibold">Mode:</span> {versionInfo.mode}</p>
                            <p><span className="font-semibold">Data Source:</span> {versionInfo.source}</p>
                            <p><span className="font-semibold">Server Time:</span> {renderTimestamp(versionInfo.server_time)}</p>
                        </>
                    )}
                </div>

                <div className="flex justify-end gap-2 border-t border-gray-200 px-5 py-4">
                    <button
                        type="button"
                        className="btn btn-secondary"
                        onClick={onRefresh}
                        disabled={isLoading}
                    >
                        Refresh
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
};
