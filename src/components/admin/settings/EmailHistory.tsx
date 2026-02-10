import React, { useEffect, useState, useRef } from 'react';
import { useEmailHistory, IEmailLog } from '../../../hooks/admin/useEmailHistory.js';
import { EMAIL_STATUS } from '../../../core/constants.js';

import { EmailHistoryTable } from './email-history/EmailHistoryTable.js';
import { EmailLogDetailModal } from '../../modals/admin/settings/email-history/EmailLogDetailModal.js';

export const EmailHistory: React.FC = () => {
    const {
        isLoading,
        logs,
        pagination,
        fetchLogs,
        getLogDetails
    } = useEmailHistory();

    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('');
    const [selectedLog, setSelectedLog] = useState<IEmailLog | null>(null);
    const hasFetched = useRef(false);

    useEffect(() => {
        if (hasFetched.current) return;
        hasFetched.current = true;
        fetchLogs({ search, status });
    }, [fetchLogs, search, status]);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        fetchLogs({ search, status, page: 1 });
    };

    const handlePageChange = (page: number) => {
        fetchLogs({ search, status, page });
    };

    const handleViewDetails = async (log: IEmailLog) => {
        const details = await getLogDetails(log.id);
        if (details) {
            setSelectedLog(details);
            return;
        }
        setSelectedLog(log);
    };

    if (isLoading && !logs.length) {
        return (
            <div className="flex flex-col items-center justify-center p-12 text-gray-500">
                <span className="wf-emoji-loader">📧</span>
                <p>Loading email logs...</p>
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <div className="bg-white border rounded-xl shadow-sm overflow-hidden">
                <div className="px-4 py-3 border-b bg-gray-50 flex flex-col lg:flex-row lg:items-center justify-between gap-2">
                    <div className="flex items-center gap-3">
                        <h2 className="text-base font-bold text-gray-800">Email Communication History</h2>
                    </div>

                    <form onSubmit={handleSearch} className="flex flex-wrap lg:flex-nowrap items-center gap-2">
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search emails, orders..."
                            className="form-input form-input-search text-xs w-full sm:w-72"
                        />
                        <select
                            value={status}
                            onChange={(e) => setStatus(e.target.value)}
                            className="form-input py-1.5 text-xs w-full sm:w-36"
                        >
                            <option value="">All Status</option>
                            <option value={EMAIL_STATUS.SENT}>Sent</option>
                            <option value={EMAIL_STATUS.FAILED}>Failed</option>
                            <option value={EMAIL_STATUS.PENDING}>Pending</option>
                        </select>
                        <button
                            type="submit"
                            className="btn btn-primary px-4 py-1.5 bg-transparent border-0 text-[var(--brand-primary)] hover:bg-[var(--brand-primary)]/5 transition-all text-xs font-bold uppercase tracking-widest"
                        >
                            Filter
                        </button>
                    </form>
                </div>

                <EmailHistoryTable logs={logs} onViewDetails={handleViewDetails} />

                {pagination && pagination.total_pages > 1 && (
                    <div className="px-4 py-3 border-t bg-gray-50 flex items-center justify-between">
                        <div className="text-xs text-gray-500">
                            Showing {logs.length} of {pagination.total} logs
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={() => handlePageChange(pagination.current_page - 1)}
                                disabled={pagination.current_page === 1}
                                className="admin-action-btn btn-icon--back"
                                data-help-id="common-back"
                            />
                            <div className="flex items-center px-4 text-xs font-bold text-gray-700">
                                Page {pagination.current_page} of {pagination.total_pages}
                            </div>
                            <button
                                onClick={() => handlePageChange(pagination.current_page + 1)}
                                disabled={pagination.current_page === pagination.total_pages}
                                className="admin-action-btn btn-icon--preview-inline"
                                data-help-id="common-next"
                            />
                        </div>
                    </div>
                )}
            </div>

            {/* Details Modal */}
            {selectedLog && (
                <EmailLogDetailModal log={selectedLog} onClose={() => setSelectedLog(null)} />
            )}
        </div>
    );
};
