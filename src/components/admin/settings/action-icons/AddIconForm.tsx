import React from 'react';

interface AddIconFormProps {
    newKey: string;
    onNewKeyChange: (val: string) => void;
    newEmoji: string;
    onNewEmojiChange: (val: string) => void;
    onAdd: (e: React.FormEvent) => void;
}

export const AddIconForm: React.FC<AddIconFormProps> = ({
    newKey,
    onNewKeyChange,
    newEmoji,
    onNewEmojiChange,
    onAdd
}) => {
    return (
        <div className="space-y-4">
            <div className="flex items-center gap-2">
                <span className="text-sm font-black text-gray-400 uppercase tracking-widest px-1">Add New Mapping</span>
                <div className="h-px flex-1 bg-gray-100" />
            </div>

            <form onSubmit={onAdd} className="flex gap-4">
                <div className="relative flex-[3]">
                    <label className="absolute -top-2 left-3 bg-white px-1.5 text-[10px] font-black text-gray-400 uppercase tracking-tight z-10 border-x border-gray-50">Action Key</label>
                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-sm opacity-30">⌨️</span>
                    <input
                        type="text"
                        value={newKey}
                        onChange={e => onNewKeyChange(e.target.value)}
                        placeholder="e.g. 'checkout', 'apply'..."
                        className="form-input w-full pl-10 pr-4 py-3 text-sm rounded-2xl border-gray-200 bg-white focus:ring-4 focus:ring-[var(--brand-primary)]/5 focus:border-[var(--brand-primary)]/20 transition-transform font-mono font-bold text-gray-700"
                    />
                </div>

                <div className="relative flex-1 min-w-[120px]">
                    <label className="absolute -top-2 left-3 bg-white px-1.5 text-[10px] font-black text-gray-400 uppercase tracking-tight z-10 border-x border-gray-50">Symbol</label>
                    <input
                        type="text"
                        value={newEmoji}
                        onChange={e => onNewEmojiChange(e.target.value)}
                        placeholder="Emoji"
                        className="form-input w-full py-3 text-center text-xl rounded-2xl border-gray-200 bg-white focus:ring-4 focus:ring-[var(--brand-primary)]/5 focus:border-[var(--brand-primary)]/20 transition-all"
                    />
                </div>

                <button
                    type="submit"
                    disabled={!newKey.trim() || !newEmoji.trim()}
                    className={`admin-action-btn btn-icon--add !w-12 !h-12 !rounded-2xl shadow-sm transition-transform self-center ${!newKey.trim() || !newEmoji.trim() ? 'opacity-30 grayscale' : ''}`}
                    data-help-id="action-icon-add-btn"
                />
            </form>
            <p className="text-[10px] text-gray-400 italic px-1">Action keys are unique technical identifiers (e.g. <b>save-changes</b>) mapped to specific symbols.</p>
        </div>
    );
};
