import React from 'react';
import { ILogFile } from '../../../../hooks/admin/useLogs.js';
import { formatTime } from '../../../../core/date-utils.js';

interface LogSidebarProps {
    logs: ILogFile[];
    searchTerm: string;
    onSearchChange: (term: string) => void;
    viewMode: 'all' | 'files' | 'database';
    onViewModeChange: (mode: 'all' | 'files' | 'database') => void;
    selectedLog: ILogFile | null;
    onLogClick: (log: ILogFile) => void;
}

export const LogSidebar: React.FC<LogSidebarProps> = ({
    logs,
    searchTerm,
    onSearchChange,
    viewMode,
    onViewModeChange,
    selectedLog,
    onLogClick
}) => {
    return (
        <aside className="col-span-4 space-y-6 p-4">
            <div className="bg-white border rounded-[2.5rem] p-6 shadow-sm flex flex-col overflow-hidden">
                <div className="space-y-4 mb-6 px-2">
                    <input
                        type="text"
                        value={searchTerm}
                        onChange={e => onSearchChange(e.target.value)}
                        placeholder="Filter logs..."
                        className="form-input form-input-search text-xs"
                    />
                </div>

                <div className="overflow-y-auto space-y-2 pr-2">
                    {logs.map(log => (
                        <button
                            key={log.type}
                            onClick={() => onLogClick(log)}
                            className={`w-full text-left p-4 rounded-2xl transition-all group border-0 bg-transparent ${selectedLog?.type === log.type
                                ? 'bg-[var(--brand-primary)]/5 text-[var(--brand-primary)]'
                                : 'hover:bg-gray-50'
                                }`}
                            type="button"
                        >
                            <div className="flex items-start gap-3">
                                <div className={`p-2 rounded-xl ${log.log_source === 'file' ? 'bg-[var(--brand-secondary)]/10 text-[var(--brand-secondary)]' : 'bg-[var(--brand-primary)]/5 text-[var(--brand-primary)]'}`}>
                                    <div className={`admin-action-btn btn-icon--${log.log_source === 'file' ? 'file' : 'database'}`} />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="text-sm font-black text-gray-900 truncate mb-0.5">{log.name}</div>
                                    <div className="text-[10px] text-gray-400 font-medium truncate mb-2">{log.description}</div>
                                    <div className="flex items-center gap-3 text-[9px] font-bold uppercase tracking-tighter">
                                        <span className="text-[var(--brand-primary)] bg-white border border-[var(--brand-primary)]/20 px-1.5 py-0.5 rounded-md">
                                            {log.size || `${log.entries} rows`}
                                        </span>
                                        <span className="text-gray-400 italic">Last: {formatTime(log.last_entry)}</span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    ))}
                </div>
            </div>
        </aside>
    );
};
