import React from 'react';
import { IIntentHeuristics } from '../../../../hooks/admin/useIntentHeuristics.js';

interface KeywordTabProps {
    editConfig: IIntentHeuristics;
    onUpdateKeywords: (type: 'positive' | 'categories', value: string) => void;
}

export const KeywordTab: React.FC<KeywordTabProps> = ({ editConfig, onUpdateKeywords }) => {
    return (
        <div className="space-y-6 animate-in fade-in slide-in-from-bottom-2">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div className="space-y-2">
                    <label className="text-xs font-bold text-gray-500 uppercase">Positive Keywords (JSON)</label>
                    <textarea 
                        value={JSON.stringify(editConfig.keywords.positive, null, 2)}
                        onChange={(e) => onUpdateKeywords('positive', e.target.value)}
                        className="form-input w-full h-64 font-mono text-xs bg-gray-900 text-[var(--brand-accent)]/80 p-4"
                    />
                </div>
                <div className="space-y-2">
                    <label className="text-xs font-bold text-gray-500 uppercase">Category Affinities (JSON)</label>
                    <textarea 
                        value={JSON.stringify(editConfig.keywords.categories, null, 2)}
                        onChange={(e) => onUpdateKeywords('categories', e.target.value)}
                        className="form-input w-full h-64 font-mono text-xs bg-gray-900 text-[var(--brand-accent)]/80 p-4"
                    />
                </div>
            </div>
        </div>
    );
};
