import React from 'react';
import type { IAISettings } from '../../../../types/ai.js';

interface ThemeWordsTabProps {
    settings: IAISettings;
    onChange: (field: keyof IAISettings, value: unknown) => void;
}

interface ToggleRow {
    key: keyof IAISettings;
    label: string;
    description: string;
}

const fieldToggles: ToggleRow[] = [
    {
        key: 'ai_theme_words_enabled_name',
        label: 'Item Name / Title',
        description: 'Allow whimsical words in generated names and titles.'
    },
    {
        key: 'ai_theme_words_enabled_description',
        label: 'Description',
        description: 'Allow whimsical words in generated descriptions.'
    },
    {
        key: 'ai_theme_words_enabled_keywords',
        label: 'SEO Keywords',
        description: 'Allow whimsical words in generated keyword lists.'
    },
    {
        key: 'ai_theme_words_enabled_selling_points',
        label: 'Selling Points',
        description: 'Allow whimsical words in generated selling-point bullets.'
    },
    {
        key: 'ai_theme_words_enabled_call_to_action',
        label: 'Calls To Action',
        description: 'Allow whimsical words in generated CTA suggestions.'
    }
];

export const ThemeWordsTab: React.FC<ThemeWordsTabProps> = ({ settings, onChange }) => {
    const masterEnabled = settings.ai_theme_words_enabled ?? true;

    return (
        <div className="space-y-8 max-w-none">
            <div className="rounded-2xl border border-slate-200 bg-slate-50/60 p-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h3 className="text-sm font-black text-slate-800 uppercase tracking-widest">Theme Words</h3>
                        <p className="text-xs text-slate-500 mt-2 leading-relaxed">
                            Control where whimsical/frog-inspired words are allowed when AI generates marketing content.
                        </p>
                    </div>
                    <label className="inline-flex items-center gap-2 text-xs font-bold text-slate-700">
                        <input
                            type="checkbox"
                            checked={masterEnabled}
                            onChange={(e) => onChange('ai_theme_words_enabled', e.target.checked)}
                            className="h-4 w-4 rounded border-slate-300 text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]"
                        />
                        Enable Theme Words
                    </label>
                </div>
            </div>

            <div className={`rounded-2xl border p-5 ${masterEnabled ? 'border-emerald-100 bg-emerald-50/30' : 'border-slate-200 bg-slate-50/50'}`}>
                <h4 className="text-xs font-black uppercase tracking-widest text-slate-700 mb-4">Field Controls</h4>
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-3">
                    {fieldToggles.map((toggle) => (
                        <label
                            key={toggle.key}
                            className={`flex items-start justify-between gap-4 rounded-xl border px-4 py-3 ${
                                masterEnabled ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-100'
                            }`}
                        >
                            <div>
                                <div className="text-sm font-bold text-slate-800">{toggle.label}</div>
                                <div className="text-xs text-slate-500 mt-1">{toggle.description}</div>
                            </div>
                            <input
                                type="checkbox"
                                checked={Boolean(settings[toggle.key])}
                                onChange={(e) => onChange(toggle.key, e.target.checked)}
                                disabled={!masterEnabled}
                                className="mt-1 h-4 w-4 rounded border-slate-300 text-[var(--brand-primary)] focus:ring-[var(--brand-primary)] disabled:cursor-not-allowed disabled:opacity-50"
                            />
                        </label>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default ThemeWordsTab;
