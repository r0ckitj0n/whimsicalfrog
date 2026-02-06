import React, { useState, useEffect } from 'react';
import { INewsletterCampaign } from '../../../../hooks/admin/useNewsletter.js';

interface CampaignEditorProps {
    campaign?: INewsletterCampaign | null;
    localCampaign: { subject: string; content: string } | null;
    setLocalCampaign: (data: { subject: string; content: string }) => void;
    onSave: () => void;
    onCancel: () => void;
    isLoading: boolean;
    subscriberCount: number;
}

export const CampaignEditor: React.FC<CampaignEditorProps> = ({
    campaign,
    localCampaign,
    setLocalCampaign,
    onSave,
    onCancel,
    isLoading,
    subscriberCount
}) => {
    const [saveError, setSaveError] = useState<string | null>(null);

    const isEditing = !!campaign;

    useEffect(() => {
        if (!localCampaign) {
            if (campaign) {
                setLocalCampaign({ subject: campaign.subject, content: campaign.content });
            } else {
                setLocalCampaign({ subject: '', content: '' });
            }
        }
    }, [campaign, localCampaign, setLocalCampaign]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        onSave();
    };

    return (
        <div className="max-w-3xl mx-auto animate-in fade-in slide-in-from-bottom-2">
            {/* Back Button */}
            <button
                type="button"
                onClick={onCancel}
                className="mb-6 text-xs font-bold text-gray-500 uppercase tracking-widest 
                           hover:text-gray-700 transition-all flex items-center gap-2"
                data-help-id="newsletter-back-to-list"
            >
                ← Back to Campaigns
            </button>

            <h3 className="text-lg font-bold text-gray-800 mb-6">
                {isEditing ? 'Edit Campaign' : 'New Campaign'}
            </h3>

            {saveError && (
                <div className="mb-4 text-xs text-red-600 bg-red-50 px-4 py-2 rounded-lg border border-red-100">
                    {saveError}
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-6">
                <div className="space-y-1">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest block">Subject Line</label>
                    <input
                        type="text" required
                        value={localCampaign?.subject || ''}
                        onChange={(e) => setLocalCampaign({ ...localCampaign!, subject: e.target.value })}
                        placeholder="Weekly Froggie Updates..."
                        className="form-input w-full py-3"
                        disabled={isLoading}
                    />
                </div>

                <div className="space-y-1">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest block">Email Content (HTML)</label>
                    <div className="border rounded-xl overflow-hidden focus-within:ring-2 focus-within:ring-[var(--brand-primary)] focus-within:border-[var(--brand-primary)]">
                        <div className="flex items-center gap-2 px-3 py-2 bg-gray-50 border-b border-gray-200">
                            <span className="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Editor</span>
                        </div>
                        <textarea
                            required
                            value={localCampaign?.content || ''}
                            onChange={(e) => setLocalCampaign({ ...localCampaign!, content: e.target.value })}
                            placeholder="<h1>Hello Frog!</h1><p>Here is our latest collection...</p>"
                            className="w-full h-72 p-4 font-mono text-sm border-0 focus:ring-0 resize-none"
                            disabled={isLoading}
                        />
                    </div>
                    <p className="text-[10px] text-gray-400 mt-2 italic">
                        Rich HTML templates are supported. Be sure to test display quality across different clients.
                    </p>
                </div>

                <div className="flex items-center justify-between pt-4 border-t gap-4">
                    <div className="text-xs text-gray-500">
                        Campaign will be sent to <strong>{subscriberCount}</strong> active subscribers.
                    </div>
                </div>
            </form>
        </div>
    );
};
