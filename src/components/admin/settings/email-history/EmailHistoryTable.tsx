import React from 'react';
import { IEmailLog } from '../../../../hooks/admin/useEmailHistory.js';
import { EMAIL_STATUS } from '../../../../core/constants.js';
import { formatDateTime } from '../../../../core/date-utils.js';

interface EmailHistoryTableProps {
    logs: IEmailLog[];
    onViewDetails: (log: IEmailLog) => void;
}

export const EmailHistoryTable: React.FC<EmailHistoryTableProps> = ({ logs, onViewDetails }) => {
    return (
        <div className="overflow-x-auto">
            {!logs.length ? (
                <div className="p-12 text-center text-gray-500 italic">
                    No matching communication logs found.
                </div>
            ) : (
                <table className="w-full text-sm text-left">
                    <thead className="bg-gray-50 text-gray-600 font-bold border-b">
                        <tr>
                            <th className="px-6 py-3">Date / Time</th>
                            <th className="px-6 py-3">Recipient</th>
                            <th className="px-6 py-3">Subject</th>
                            <th className="px-6 py-3">Type</th>
                            <th className="px-6 py-3 text-center">Status</th>
                            <th className="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                        {logs.map((log) => (
                            <tr key={log.id} className="hover:bg-gray-50 group">
                                <td className="px-6 py-4 whitespace-nowrap">
                                    {(() => {
                                        const dt = formatDateTime(log.sent_at);
                                        return (
                                            <>
                                                <div className="text-gray-900 font-medium">{dt.date}</div>
                                                <div className="text-[10px] text-gray-400">{dt.time}</div>
                                            </>
                                        );
                                    })()}
                                </td>
                                <td className="px-6 py-4">
                                    <div className="font-medium text-gray-900">{log.to_email}</div>
                                    {log.order_id && (
                                        <div className="text-[10px] text-[var(--brand-primary)] font-bold">Order #{log.order_id}</div>
                                    )}
                                </td>
                                <td className="px-6 py-4">
                                    <div className="truncate max-w-xs text-gray-600" title={log.subject}>
                                        {log.subject}
                                    </div>
                                </td>
                                <td className="px-6 py-4">
                                    <span className="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-bold uppercase rounded truncate max-w-[100px] inline-block">
                                        {log.type.replace(/_/g, ' ')}
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-center">
                                    <div className="flex justify-center text-lg">
                                        {log.status === EMAIL_STATUS.SENT ? (
                                            '✅'
                                        ) : log.status === EMAIL_STATUS.FAILED ? (
                                            '❌'
                                        ) : (
                                            'ℹ️'
                                        )}
                                    </div>
                                </td>
                                <td className="px-6 py-4 text-right">
                                    <button
                                        type="button"
                                        onClick={() => onViewDetails(log)}
                                        className="btn btn-secondary px-3 py-1 bg-transparent border-0 text-[var(--brand-secondary)] hover:bg-[var(--brand-secondary)]/5 transition-all text-xs font-bold"
                                        data-help-id="common-view"
                                    >
                                        Details
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </div>
    );
};
