import React from 'react';
import { INewsletterCampaign } from '../../../../hooks/admin/useNewsletter.js';
import { formatDate } from '../../../../core/date-utils.js';

interface CampaignListProps {
    campaigns: INewsletterCampaign[];
    onEdit: (campaign: INewsletterCampaign) => void;
    onDelete: (id: number, subject: string) => void;
    onSend: (id: number, subject: string) => void;
    onNew: () => void;
    isLoading: boolean;
}

export const CampaignList: React.FC<CampaignListProps> = ({
    campaigns,
    onEdit,
    onDelete,
    onSend,
    onNew,
    isLoading
}) => {
    const getStatusBadge = (status: string) => {
        const styles: Record<string, string> = {
            draft: 'bg-amber-100 text-amber-700 border-amber-200',
            sent: 'bg-[var(--brand-accent)]/10 text-[var(--brand-accent)] border-[var(--brand-accent)]/20',
            scheduled: 'bg-blue-100 text-blue-700 border-blue-200'
        };
        return styles[status.toLowerCase()] || 'bg-gray-100 text-gray-500 border-gray-200';
    };

    return (
        <div className="space-y-6">
            {/* Header with Add Button */}
            <div className="flex items-center justify-between gap-4 w-full overflow-hidden">
                <div className="text-xs text-gray-500">
                    {campaigns.length} campaign{campaigns.length !== 1 ? 's' : ''}
                </div>
                <button
                    onClick={onNew}
                    className="admin-action-btn btn-icon--add"
                    data-help-id="newsletter-new-campaign"
                    type="button"
                    disabled={isLoading}
                />
            </div>

            {campaigns.length === 0 ? (
                <div className="p-12 text-center text-gray-500 italic border rounded-xl bg-gray-50/50">
                    <span className="text-4xl block mb-3">📭</span>
                    No campaigns yet. Create your first campaign!
                </div>
            ) : (
                <div className="overflow-x-auto border rounded-xl">
                    <table className="w-full text-sm text-left">
                        <thead className="bg-gray-50 text-gray-600 font-bold border-b">
                            <tr>
                                <th className="px-6 py-3">Subject</th>
                                <th className="px-6 py-3">Status</th>
                                <th className="px-6 py-3">Created</th>
                                <th className="px-6 py-3">Sent</th>
                                <th className="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {campaigns.map((campaign) => (
                                <tr key={campaign.id} className="hover:bg-gray-50 group">
                                    <td className="px-6 py-4">
                                        <div className="font-medium text-gray-900">{campaign.subject}</div>
                                        {campaign.group_name && (
                                            <div className="text-[10px] text-gray-400 uppercase tracking-wider mt-1">
                                                To: {campaign.group_name}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-6 py-4">
                                        <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase border ${getStatusBadge(campaign.status)}`}>
                                            {campaign.status}
                                        </span>
                                    </td>
                                    <td className="px-6 py-4 text-gray-500">
                                        {formatDate(campaign.created_at)}
                                    </td>
                                    <td className="px-6 py-4 text-gray-500">
                                        {campaign.sent_at
                                            ? formatDate(campaign.sent_at)
                                            : <span className="text-gray-300">—</span>
                                        }
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            {campaign.status.toLowerCase() === 'draft' && (
                                                <button
                                                    onClick={() => onSend(campaign.id, campaign.subject)}
                                                    className="admin-action-btn px-3 py-1.5 text-[10px] font-bold uppercase
                                                               bg-[var(--brand-accent)]/10 text-[var(--brand-accent)]
                                                               hover:bg-[var(--brand-accent)]/20 rounded-lg transition-all"
                                                    data-help-id="newsletter-send-campaign"
                                                    type="button"
                                                    disabled={isLoading}
                                                >
                                                    Send
                                                </button>
                                            )}
                                            <button
                                                onClick={() => onEdit(campaign)}
                                                className="admin-action-btn btn-icon--edit"
                                                data-help-id="newsletter-edit-campaign"
                                                type="button"
                                                disabled={isLoading}
                                            />
                                            <button
                                                onClick={() => onDelete(campaign.id, campaign.subject)}
                                                className="admin-action-btn btn-icon--delete"
                                                data-help-id="newsletter-delete-campaign"
                                                type="button"
                                                disabled={isLoading}
                                            />
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )
            }
        </div >
    );
};
