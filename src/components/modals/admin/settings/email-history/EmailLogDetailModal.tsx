import React from 'react';
import { IEmailLog } from '../../../../../hooks/admin/useEmailHistory.js';
import { EMAIL_STATUS } from '../../../../../core/constants.js';
import { formatDate, formatTime } from '../../../../../core/date-utils.js';

interface EmailLogDetailModalProps {
    log: IEmailLog;
    onClose: () => void;
}

export const EmailLogDetailModal: React.FC<EmailLogDetailModalProps> = ({ log, onClose }) => {
    return (
        <div className="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
            <div className="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95">
                <div className="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                    <h3 className="font-bold text-gray-800">Email Details</h3>
                    <button
                        onClick={onClose}
                        className="admin-action-btn btn-icon--close"
                        data-help-id="common-close"
                    />
                </div>
                <div className="flex-1 overflow-y-auto p-6 space-y-6">
                    <div className="grid grid-cols-2 gap-6 pb-6 border-b">
                        <div className="space-y-1">
                            <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Sent To</label>
                            <div className="font-bold text-gray-900">{log.to_email}</div>
                        </div>
                        <div className="space-y-1 text-right">
                            <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Sent At</label>
                            <div className="text-gray-900">{formatDate(log.sent_at)} {formatTime(log.sent_at)}</div>
                        </div>
                        <div className="space-y-1">
                            <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Subject</label>
                            <div className="text-gray-900">{log.subject}</div>
                        </div>
                        <div className="space-y-1 text-right">
                            <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Order ID</label>
                            <div className="text-gray-900">{log.order_id || 'N/A'}</div>
                        </div>
                    </div>

                    {log.status === EMAIL_STATUS.FAILED && log.error_message && (
                        <div className="p-4 bg-[var(--brand-error-bg)] border border-[var(--brand-error-border)] text-[var(--brand-error)] text-sm flex gap-3 rounded-xl">
                            <span className="text-xl">❌</span>
                            <div>
                                <div className="font-bold">Dispatch Failure</div>
                                <div className="mt-1 font-mono text-xs">{log.error_message}</div>
                            </div>
                        </div>
                    )}

                    <div className="space-y-2">
                        <label className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Message Content</label>
                        <div className="bg-gray-50 border rounded-xl p-4 font-mono text-xs overflow-x-auto whitespace-pre-wrap max-h-96">
                            {log.content || '(Content not archived)'}
                        </div>
                    </div>
                </div>
                <div className="px-6 py-4 bg-gray-50 border-t flex justify-end">
                    <button
                        onClick={onClose}
                        className="btn btn-secondary px-8 bg-transparent border-0 text-[var(--brand-secondary)] hover:bg-[var(--brand-secondary)]/5 transition-all"
                        data-help-id="common-close"
                    >
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    );
};
