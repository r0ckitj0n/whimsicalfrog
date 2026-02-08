import React, { useState, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { ApiClient } from '../../../core/ApiClient.js';
import type { SessionViewerData, SessionViewerResponse } from '../../../types/admin/sessionViewer.js';

interface SessionViewerProps {
    onClose?: () => void;
    title?: string;
}

export function SessionViewer({ onClose, title }: SessionViewerProps) {
    const [data, setData] = useState<SessionViewerData | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [expandedSections, setExpandedSections] = useState<Record<string, boolean>>({
        session: true,
        cookies: true,
        server: false,
    });

    const fetchData = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const json = await ApiClient.get<SessionViewerResponse>('/api/session_diagnostics.php', { action: 'get' });
            if (json.success) {
                setData(json.data || null);
            } else {
                setError(json.error || 'Failed to load session data');
            }
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Network error');
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const toggleSection = (section: string) => {
        setExpandedSections(prev => ({
            ...prev,
            [section]: !prev[section],
        }));
    };

    const formatValue = (value: unknown): string => {
        if (value === null) return 'null';
        if (value === undefined) return 'undefined';
        if (typeof value === 'object') {
            return JSON.stringify(value, null, 2);
        }
        return String(value);
    };

    const renderDataSection = (
        title: string,
        sectionKey: string,
        dataObj: Record<string, unknown> | undefined
    ) => {
        const isExpanded = expandedSections[sectionKey];
        const entries = Object.entries(dataObj || {});

        return (
            <div className="admin-card mb-4">
                <button
                    type="button"
                    className="w-full flex items-center justify-between admin-card-title mb-2 cursor-pointer hover:text-brand-primary"
                    onClick={() => toggleSection(sectionKey)}
                >
                    <span>{title}</span>
                    <span className="text-sm text-gray-500">
                        {entries.length} items {isExpanded ? '▼' : '▶'}
                    </span>
                </button>

                {isExpanded && (
                    <div className="bg-gray-50 p-4 rounded overflow-auto max-h-[400px] font-mono text-sm border border-gray-200">
                        {entries.length === 0 ? (
                            <span className="text-gray-500 italic">Empty</span>
                        ) : (
                            <table className="w-full text-left">
                                <tbody>
                                    {entries.map(([key, value]) => (
                                        <tr key={key} className="border-b border-gray-200 last:border-0">
                                            <td className="py-1 pr-4 font-semibold text-gray-700 align-top whitespace-nowrap">
                                                {key}
                                            </td>
                                            <td className="py-1 text-gray-600 break-all">
                                                <pre className="whitespace-pre-wrap m-0">{formatValue(value)}</pre>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                )}
            </div>
        );
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
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1100px] max-w-[95vw] h-auto max-h-[90vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">👤</span> {title || 'Session Viewer'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-4">
                        <button
                            type="button"
                            onClick={fetchData}
                            className="btn-text-secondary disabled:opacity-50"
                            data-help-id="common-refresh"
                            disabled={loading}
                        >
                            {loading ? 'Loading...' : 'Refresh'}
                        </button>
                        {onClose && (
                            <button
                                type="button"
                                onClick={onClose}
                                className="admin-action-btn btn-icon--close"
                                data-help-id="session-viewer-close"
                            />
                        )}
                    </div>
                </div>

                <div className="modal-body p-6 overflow-y-auto wf-admin-modal-body flex-1">
                    {error && (
                        <div className="bg-red-50 border border-red-200 text-red-700 p-3 rounded mb-4">
                            {error}
                        </div>
                    )}

                    {data && (
                        <>
                            <div className="text-sm text-gray-600 mb-4">
                                <span className="mr-4">Session ID: <code>{data.session_id || 'None'}</code></span>
                                <span>PHP: <code>{data.php_version}</code></span>
                            </div>

                            <div className="admin-card mb-4">
                                <div className="admin-card-title mb-2">Active PHP Sessions</div>
                                <div className="text-xs text-gray-500 mb-2">
                                    Save Path: <code>{data.php_session_save_path || '(unknown)'}</code>
                                </div>
                                {data.php_session_scan_error && (
                                    <div className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mb-2">
                                        {data.php_session_scan_error}
                                    </div>
                                )}
                                <div className="bg-gray-50 p-4 rounded overflow-auto max-h-[360px] border border-gray-200">
                                    {data.php_sessions.length === 0 ? (
                                        <span className="text-gray-500 italic">No PHP session files found.</span>
                                    ) : (
                                        <table className="w-full text-left text-sm">
                                            <thead>
                                                <tr className="border-b border-gray-300">
                                                    <th className="py-2 pr-3">Session</th>
                                                    <th className="py-2 pr-3">Last Modified</th>
                                                    <th className="py-2 pr-3">Bytes</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {data.php_sessions.map((item) => (
                                                    <tr key={`${item.session_id}-${item.last_modified}`} className={`border-b border-gray-200 last:border-0 align-top ${item.is_current ? 'bg-green-50' : ''}`}>
                                                        <td className="py-2 pr-3 font-mono text-xs break-all">
                                                            {item.session_id || '(empty)'}{item.is_current ? ' (current)' : ''}
                                                        </td>
                                                        <td className="py-2 pr-3 whitespace-nowrap">{item.last_modified}</td>
                                                        <td className="py-2 pr-3">{item.bytes}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </div>
                            </div>

                            <div className="admin-card mb-4">
                                <div className="admin-card-title mb-2">Recent Analytics Sessions</div>
                                <div className="bg-gray-50 p-4 rounded overflow-auto max-h-[360px] border border-gray-200">
                                    {data.recent_sessions.length === 0 ? (
                                        <span className="text-gray-500 italic">No analytics sessions found.</span>
                                    ) : (
                                        <table className="w-full text-left text-sm">
                                            <thead>
                                                <tr className="border-b border-gray-300">
                                                    <th className="py-2 pr-3">Session</th>
                                                    <th className="py-2 pr-3">Last Activity</th>
                                                    <th className="py-2 pr-3">Pages</th>
                                                    <th className="py-2 pr-3">Landing Page</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {data.recent_sessions.map((item) => (
                                                    <tr key={`${item.session_id}-${item.last_activity}`} className="border-b border-gray-200 last:border-0 align-top">
                                                        <td className="py-2 pr-3 font-mono text-xs break-all">{item.session_id || '(empty)'}</td>
                                                        <td className="py-2 pr-3 whitespace-nowrap">{item.last_activity}</td>
                                                        <td className="py-2 pr-3">{item.total_page_views}</td>
                                                        <td className="py-2 pr-3 break-all">{item.landing_page || '-'}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    )}
                                </div>
                            </div>

                            {renderDataSection('$_SESSION', 'session', data.session)}
                            {renderDataSection('$_COOKIE', 'cookies', data.cookies)}
                            {renderDataSection('$_SERVER', 'server', data.server)}
                        </>
                    )}

                    {loading && !data && (
                        <div className="flex items-center justify-center py-12">
                            <span className="text-gray-500">Loading session data...</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
}

export default SessionViewer;
