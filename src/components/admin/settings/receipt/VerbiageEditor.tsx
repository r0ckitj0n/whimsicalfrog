import React from 'react';
import { IReceiptVerbiage } from '../../../../hooks/admin/useReceiptSettings.js';

interface VerbiageEditorProps {
    localVerbiage: IReceiptVerbiage;
    setLocalVerbiage: React.Dispatch<React.SetStateAction<IReceiptVerbiage>>;
    isLoading: boolean;
    onSave: (e: React.FormEvent) => void;
}

export const VerbiageEditor: React.FC<VerbiageEditorProps> = ({
    localVerbiage,
    setLocalVerbiage,
    isLoading,
    onSave
}) => {
    return (
        <div className="bg-white border rounded-[2rem] p-8 shadow-sm space-y-8">
            <div className="space-y-1">
                <h3 className="font-black text-gray-900 text-sm uppercase tracking-wider">Standard Sales Verbiage</h3>
                <p className="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Applied to all customer receipts</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div className="space-y-2">
                    <label className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">
                        Thank You Message
                    </label>
                    <textarea
                        value={localVerbiage.receipt_thank_you_message}
                        onChange={e => setLocalVerbiage({ ...localVerbiage, receipt_thank_you_message: e.target.value })}
                        className="form-input w-full h-32 p-4 text-sm bg-gray-50 border-transparent focus:bg-white transition-all rounded-2xl shadow-inner resize-none"
                        placeholder="Thanks for hopping by!"
                    />
                </div>
                <div className="space-y-2">
                    <label className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">
                        Next Steps
                    </label>
                    <textarea
                        value={localVerbiage.receipt_next_steps}
                        onChange={e => setLocalVerbiage({ ...localVerbiage, receipt_next_steps: e.target.value })}
                        className="form-input w-full h-32 p-4 text-sm bg-gray-50 border-transparent focus:bg-white transition-all rounded-2xl shadow-inner resize-none"
                        placeholder="We'll start crafting your order immediately..."
                    />
                </div>
                <div className="space-y-2">
                    <label className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">
                        Social Sharing
                    </label>
                    <textarea
                        value={localVerbiage.receipt_social_sharing}
                        onChange={e => setLocalVerbiage({ ...localVerbiage, receipt_social_sharing: e.target.value })}
                        className="form-input w-full h-32 p-4 text-sm bg-gray-50 border-transparent focus:bg-white transition-all rounded-2xl shadow-inner resize-none"
                        placeholder="Share your new treasure with #WhimsicalFrog"
                    />
                </div>
                <div className="space-y-2">
                    <label className="flex items-center gap-2 text-[10px] font-black text-gray-400 uppercase tracking-widest px-1">
                        Returning Customers
                    </label>
                    <textarea
                        value={localVerbiage.receipt_return_customer}
                        onChange={e => setLocalVerbiage({ ...localVerbiage, receipt_return_customer: e.target.value })}
                        className="form-input w-full h-32 p-4 text-sm bg-gray-50 border-transparent focus:bg-white transition-all rounded-2xl shadow-inner resize-none"
                        placeholder="Welcome back to our habitat!"
                    />
                </div>
            </div>
        </div>
    );
};
