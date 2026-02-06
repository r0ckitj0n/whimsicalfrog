import React from 'react';
import { IReceiptMessage } from '../../../../hooks/admin/useReceiptSettings.js';

interface MessageListProps {
    messages: IReceiptMessage[];
    activeType: 'shipping' | 'items' | 'categories' | 'default';
    setActiveType: (type: 'shipping' | 'items' | 'categories' | 'default') => void;
    onEdit: (msg: IReceiptMessage) => void;
    onDelete: (id: string | number) => void;
    onCreate: () => void;
    isLoading: boolean;
    hideTabs?: boolean;
}

export const MessageList: React.FC<MessageListProps> = ({
    messages,
    activeType,
    setActiveType,
    onEdit,
    onDelete,
    onCreate,
    isLoading,
    hideTabs
}) => {
    return (
        <div className="bg-white border rounded-[2.5rem] shadow-sm overflow-hidden flex flex-col min-h-[500px]">
            {!hideTabs && (
                <div className="px-6 py-4 border-b bg-gray-50 flex items-center justify-between">
                    <div className="flex p-1 bg-gray-200 rounded-xl">
                        {(['default', 'shipping', 'items', 'categories'] as const).map(type => (
                            <button
                                key={type}
                                onClick={() => setActiveType(type)}
                                className={`px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all ${activeType === type ? 'bg-white text-[var(--brand-primary)] shadow-sm' : 'text-gray-500 hover:text-gray-700'
                                    }`}
                            >
                                {type}
                            </button>
                        ))}
                    </div>
                    <button
                        onClick={onCreate}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="common-add"
                    />
                </div>
            )}

            {hideTabs && (
                <div className="px-6 py-4 border-b bg-gray-50 flex items-center justify-end">
                    <button
                        onClick={onCreate}
                        className="admin-action-btn btn-icon--add"
                        data-help-id="common-add"
                    />
                </div>
            )}

            <div className="flex-1 p-6 overflow-y-auto scrollbar-hide">
                <div className="space-y-4">
                    {messages.map(msg => (
                        <div key={msg.id} className="p-6 border rounded-3xl bg-white hover:border-[var(--brand-primary)]/30 transition-all group shadow-sm">
                            <div className="flex justify-between items-start mb-4">
                                <div className="space-y-1">
                                    <h4 className="text-sm font-black text-gray-900 uppercase tracking-tight">{msg.title}</h4>
                                    {msg.condition_value && (
                                        <div className="inline-flex items-center gap-1.5 px-2 py-0.5 bg-[var(--brand-primary)]/5 text-[var(--brand-primary)] rounded-lg text-[9px] font-black uppercase border border-[var(--brand-primary)]/10">
                                            Trigger: {msg.condition_value}
                                        </div>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        onClick={() => onEdit(msg)}
                                        className="admin-action-btn btn-icon--edit"
                                        data-help-id="common-edit"
                                    />
                                    <button
                                        onClick={() => onDelete(msg.id)}
                                        className="admin-action-btn btn-icon--delete"
                                        data-help-id="common-delete"
                                    />
                                </div>
                            </div>
                            <p className="text-xs text-gray-500 italic whitespace-pre-wrap leading-relaxed">
                                {msg.content}
                            </p>
                            {!msg.is_active && (
                                <div className="mt-4 inline-flex items-center gap-1.5 text-[9px] font-black text-gray-400 uppercase">
                                    <div className="w-1.5 h-1.5 rounded-full bg-gray-300"></div>
                                    Draft / Inactive
                                </div>
                            )}
                        </div>
                    ))}
                    {messages.length === 0 && !isLoading && (
                        <div className="py-24 text-center space-y-4 border-2 border-dashed border-gray-100 rounded-[2rem]">
                            <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto">
                                <span className="text-3xl opacity-20">💬</span>
                            </div>
                            <p className="text-sm text-gray-400 font-medium italic">No {activeType} triggers defined yet.</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};
