import React from 'react';

interface MarketingFormProps {
    editedTitle: string;
    setEditedTitle: (val: string) => void;
    editedDescription: string;
    setEditedDescription: (val: string) => void;
    editedKeywords: string[];
    newKeyword: string;
    setNewKeyword: (val: string) => void;
    editedAudience: string;
    setEditedAudience: (val: string) => void;
    handleApplyTitle: () => void;
    handleApplyDescription: () => void;
    handleAddKeyword: () => void;
    handleRemoveKeyword: (k: string) => void;
}

export const MarketingForm: React.FC<MarketingFormProps> = ({
    editedTitle,
    setEditedTitle,
    editedDescription,
    setEditedDescription,
    editedKeywords,
    newKeyword,
    setNewKeyword,
    editedAudience,
    setEditedAudience,
    handleApplyTitle,
    handleApplyDescription,
    handleAddKeyword,
    handleRemoveKeyword
}) => {
    return (
        <div className="p-6 space-y-6">
            {/* Title */}
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest">
                        Suggested Title
                    </label>
                    <button
                        onClick={handleApplyTitle}
                        disabled={!editedTitle}
                        className="px-3 py-1 bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] rounded-lg text-xs font-bold hover:bg-[var(--brand-primary)]/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                        type="button"
                    >
                        📋 Copy to Item Name
                    </button>
                </div>
                <input
                    type="text"
                    value={editedTitle}
                    onChange={(e) => setEditedTitle(e.target.value)}
                    placeholder="Click 'Generate with AI' to create a title..."
                    className="form-input w-full py-2"
                />
            </div>

            {/* Description */}
            <div className="space-y-2">
                <div className="flex items-center justify-between">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest">
                        Suggested Description
                    </label>
                    <button
                        onClick={handleApplyDescription}
                        disabled={!editedDescription}
                        className="px-3 py-1 bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] rounded-lg text-xs font-bold hover:bg-[var(--brand-primary)]/20 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
                        type="button"
                    >
                        📋 Copy to Item Description
                    </button>
                </div>
                <textarea
                    value={editedDescription}
                    onChange={(e) => setEditedDescription(e.target.value)}
                    placeholder="Click 'Generate with AI' to create a description..."
                    rows={4}
                    className="form-textarea w-full py-2 resize-none"
                />
            </div>

            {/* Two Column Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Keywords */}
                <div className="space-y-2">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest">
                        SEO Keywords
                    </label>
                    <div className="flex gap-2">
                        <input
                            type="text"
                            value={newKeyword}
                            onChange={(e) => setNewKeyword(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && handleAddKeyword()}
                            placeholder="Add keyword..."
                            className="form-input flex-1 py-1.5 text-sm"
                        />
                        <button
                            onClick={handleAddKeyword}
                            className="px-3 py-1.5 bg-gray-100 text-gray-600 rounded-lg text-sm font-bold hover:bg-gray-200 transition-colors"
                            type="button"
                        >
                            Add
                        </button>
                    </div>
                    <div className="flex flex-wrap gap-2 mt-2">
                        {editedKeywords.map((keyword, idx) => (
                            <span
                                key={idx}
                                className="px-2 py-1 bg-[var(--brand-primary)]/10 text-[var(--brand-primary)] rounded-lg text-xs font-bold flex items-center gap-1"
                            >
                                {keyword}
                                <button
                                    onClick={() => handleRemoveKeyword(keyword)}
                                    className="hover:text-red-600"
                                    type="button"
                                >
                                    ×
                                </button>
                            </span>
                        ))}
                        {editedKeywords.length === 0 && (
                            <span className="text-xs text-gray-400 italic">Keywords will appear after AI generation</span>
                        )}
                    </div>
                </div>

                {/* Target Audience */}
                <div className="space-y-2">
                    <label className="text-xs font-black text-gray-400 uppercase tracking-widest">
                        Target Audience
                    </label>
                    <input
                        type="text"
                        value={editedAudience}
                        onChange={(e) => setEditedAudience(e.target.value)}
                        placeholder="Audience info will appear after AI generation..."
                        className="form-input w-full py-2"
                    />
                </div>
            </div>
        </div>
    );
};
