import React from 'react';
import { ILogFile } from '../../../../hooks/admin/useLogs.js';

interface LogEntryViewerProps {
    selectedLog: ILogFile | null;
    logEntries: unknown[];
    isLoadingContent: boolean;
    onClearLog: (type: string) => void;
}

export const LogEntryViewer: React.FC<LogEntryViewerProps> = ({
    selectedLog,
    logEntries,
    isLoadingContent,
    onClearLog
}) => {
    return (
        <main className="p-4 pl-0 h-full">
            <div className="bg-gray-900 rounded-[3rem] shadow-2xl overflow-hidden flex flex-col border border-white/5 h-full">
                {selectedLog ? (
                    <>
                        <div className="px-8 py-6 bg-white/5 border-b border-white/5 flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className={`p-2 rounded-xl ${selectedLog.log_source === 'file' ? 'bg-[var(--brand-secondary)]/10 text-[var(--brand-secondary)]' : 'bg-[var(--brand-primary)]/10 text-[var(--brand-primary)]'}`}>
                                    <div className={`admin-action-btn btn-icon--${selectedLog.log_source === 'file' ? 'file' : 'database'}`} />
                                </div>
                                <div>
                                    <h2 className="text-lg font-black text-white tracking-tight uppercase">{selectedLog.name}</h2>
                                    <div className="flex items-center gap-3 text-[9px] font-black text-gray-500 uppercase tracking-widest mt-0.5">
                                        <span>Source: {selectedLog.log_source}</span>
                                        <span>•</span>
                                        <span>Path: {selectedLog.path}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => onClearLog(selectedLog.type)}
                                    className="admin-action-btn btn-icon--delete"
                                    data-help-id="settings-log-clear"
                                    type="button"
                                />
                                <a
                                    href={`/api/admin_file_proxy.php?path=${encodeURIComponent(selectedLog.path)}`}
                                    download
                                    className="admin-action-btn btn-icon--download"
                                    data-help-id="settings-log-download"
                                >
                                </a>
                            </div>
                        </div>

                        <div className="flex-1 overflow-y-auto p-8 font-mono text-xs">
                            {isLoadingContent ? (
                                <div className="h-full flex flex-col items-center justify-center text-gray-600 gap-4">
                                    <span className="wf-emoji-loader opacity-20">📜</span>
                                    <span className="uppercase tracking-[0.3em] font-black italic">Parsing data stream...</span>
                                </div>
                            ) : logEntries.length > 0 ? (
                                <div className="space-y-1">
                                    {logEntries.map((entry, idx) => (
                                        <div key={idx} className="group flex gap-4 p-1 rounded hover:bg-white/5 transition-colors border-l-2 border-transparent hover:border-[var(--brand-primary)]">
                                            <span className="text-gray-600 shrink-0 select-none w-20">[{idx + 1}]</span>
                                            <span className="text-[var(--brand-accent)]/80 break-all whitespace-pre-wrap">{typeof entry === 'string' ? entry : JSON.stringify(entry)}</span>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="h-full flex flex-col items-center justify-center text-gray-700 italic">
                                    No entries found in this log.
                                </div>
                            )}
                        </div>
                    </>
                ) : (
                    <div className="h-full flex flex-col items-center justify-center text-center p-12 space-y-6">
                        <div className="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center shadow-inner">
                            <span className="text-5xl opacity-20">📜</span>
                        </div>
                        <div className="space-y-2">
                            <h3 className="text-xl font-black text-white tracking-tight uppercase">Select a Log Source</h3>
                            <p className="text-sm text-gray-500 font-medium max-w-xs leading-relaxed italic">Choose a file or database stream from the registry to begin diagnostics.</p>
                        </div>
                    </div>
                )}
            </div>
        </main>
    );
};
