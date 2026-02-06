import React, { useState, useMemo } from 'react';
import { createPortal } from 'react-dom';
import { useLogs, ILogFile } from '../../../hooks/admin/useLogs.js';
import { useModalContext } from '../../../context/ModalContext.js';
import { LogSidebar } from './log-viewer/LogSidebar.js';
import { LogEntryViewer } from './log-viewer/LogEntryViewer.js';

interface LogViewerProps {
    onClose?: () => void;
    title?: string;
}

export const LogViewer: React.FC<LogViewerProps> = ({ onClose, title }) => {
    const {
        logs,
        isLoading,
        error,
        fetchLogList,
        fetchLogContent,
        clearLog,
        clearAllLogs
    } = useLogs();

    const { confirm: confirmModal } = useModalContext();

    const [searchTerm, setSearchTerm] = useState('');
    const [selectedLog, setSelectedLog] = useState<ILogFile | null>(null);
    const [logEntries, setLogEntries] = useState<unknown[]>([]);
    const [isLoadingContent, setIsLoadingContent] = useState(false);
    const [viewMode, setViewMode] = useState<'all' | 'files' | 'database'>('all');

    const filteredLogs = useMemo(() => {
        return logs.filter(log => {
            const matchesSearch = !searchTerm ||
                log.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                log.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
                log.type.toLowerCase().includes(searchTerm.toLowerCase());

            const matchesMode = viewMode === 'all' ||
                (viewMode === 'files' && log.log_source === 'file') ||
                (viewMode === 'database' && log.log_source === 'database');

            return matchesSearch && matchesMode;
        });
    }, [logs, searchTerm, viewMode]);

    const handleLogClick = async (log: ILogFile) => {
        setSelectedLog(log);
        setIsLoadingContent(true);
        const entries = await fetchLogContent(log.type);
        setLogEntries(entries);
        setIsLoadingContent(false);
    };

    const handleClearLog = async (type: string) => {
        const confirmed = await confirmModal({
            title: 'Clear Log',
            message: `Clear all entries for ${type}?`,
            confirmText: 'Clear',
            confirmStyle: 'danger',
            icon: '⚠️',
            iconType: 'danger'
        });

        if (confirmed) {
            const res = await clearLog(type);
            if (res?.success) {
                if (selectedLog?.type === type) {
                    setLogEntries([]);
                }
                if (window.WFToast) window.WFToast.success(`${type} log cleared`);
            }
        }
    };

    const handleClearAll = async () => {
        const confirmed = await confirmModal({
            title: 'Nuke All Logs',
            message: 'Clear ALL system logs? This includes both files and database logs.',
            confirmText: 'Clear All',
            confirmStyle: 'danger',
            icon: '⚠️',
            iconType: 'danger'
        });

        if (confirmed) {
            const success = await clearAllLogs();
            if (success) {
                setLogEntries([]);
                setSelectedLog(null);
                if (window.WFToast) window.WFToast.success('All logs cleared');
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
                <div className="modal-header border-b border-gray-100 bg-white sticky top-0 z-20 px-6 py-4 flex items-start justify-between">
                    <div className="flex flex-col gap-4">
                        <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                            <span className="text-2xl">📋</span> {title || 'Activity Logs'}
                        </h2>

                        <div className="wf-tabs bg-slate-50/50 rounded-xl p-1 border border-slate-100 flex items-center gap-2">
                            {(['all', 'files', 'database'] as const).map(mode => (
                                <button
                                    key={mode}
                                    onClick={() => setViewMode(mode)}
                                    className={`wf-tab ${viewMode === mode ? 'is-active' : ''}`}
                                >
                                    {mode.charAt(0).toUpperCase() + mode.slice(1)}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <button
                            onClick={handleClearAll}
                            className="admin-action-btn btn-icon--delete text-red-500"
                            data-help-id="logs-nuke-all"
                            type="button"
                        />
                        <button
                            onClick={fetchLogList}
                            className="admin-action-btn btn-icon--refresh"
                            data-help-id="common-refresh"
                            type="button"
                        />
                        <button
                            onClick={onClose}
                            className="admin-action-btn btn-icon--close"
                            data-help-id="common-close"
                        />
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body p-0">
                    <div className="grid grid-cols-12 items-start">
                        <LogSidebar
                            logs={filteredLogs}
                            searchTerm={searchTerm}
                            onSearchChange={setSearchTerm}
                            viewMode={viewMode}
                            onViewModeChange={setViewMode}
                            selectedLog={selectedLog}
                            onLogClick={handleLogClick}
                        />

                        <div className="col-span-8 self-stretch h-0 min-h-full">
                            <LogEntryViewer
                                selectedLog={selectedLog}
                                logEntries={logEntries}
                                isLoadingContent={isLoadingContent}
                                onClearLog={handleClearLog}
                            />
                        </div>
                    </div>
                </div>

                {error && (
                    <div className="absolute bottom-6 right-6 p-4 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-xl flex items-center gap-3 animate-in slide-in-from-bottom-2 z-50">
                        <span className="text-lg">⚠️</span>
                        {error}
                    </div>
                )}
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
