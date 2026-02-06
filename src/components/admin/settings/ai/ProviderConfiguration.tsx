import React from 'react';
import { IAISettings } from '../../../../hooks/admin/useAISettings.js';

interface AIModel {
    id: string;
    name?: string;
    supportsVision?: boolean;
}

interface ProviderConfigurationProps {
    settings: IAISettings;
    models: AIModel[] | null;
    onFetchModels: (provider: string, force?: boolean) => void;
    onChange: (field: keyof IAISettings, value: unknown) => void;
}

export const ProviderConfiguration: React.FC<ProviderConfigurationProps> = ({
    settings,
    models,
    onFetchModels,
    onChange
}) => {
    if (settings.ai_provider === 'jons_ai') {
        return (
            <div className="space-y-4">
                <label className="block text-xs font-black text-gray-400 uppercase tracking-widest leading-none mb-1.5 ml-1">2. Configuration</label>
                <div className="p-6 bg-slate-50 rounded-2xl border-2 border-dashed border-slate-100 text-center">
                    <p className="text-xs text-slate-400 font-bold italic">No configuration required for Local AI.</p>
                </div>
            </div>
        );
    }

    const provider = settings.ai_provider as 'openai' | 'anthropic' | 'google' | 'meta';
    const apiKeyField = `${provider}_api_key` as keyof IAISettings;
    const modelField = `${provider}_model` as keyof IAISettings;
    const keyPresentField = `${provider}_key_present` as keyof IAISettings;

    // Find the currently selected model to check vision support
    const selectedModelId = (settings[modelField] as string) || '';
    const selectedModel = models?.find(m => m.id === selectedModelId);
    const showVisionWarning = selectedModel && selectedModel.supportsVision === false;

    return (
        <div className="space-y-4">
            <label className="block text-xs font-black text-gray-400 uppercase tracking-widest leading-none mb-1.5 ml-1">2. Configuration</label>
            <div className="p-6 bg-slate-50 rounded-2xl border-2 border-slate-50 space-y-5 animate-in fade-in zoom-in-95 duration-500">
                <div className="flex items-center justify-between">
                    <h4 className="text-sm font-black text-slate-700 capitalize tracking-tight">{provider} Settings</h4>
                    {settings[keyPresentField] && (
                        <span className="text-[9px] bg-brand-primary text-white px-2 py-1 rounded-lg font-black uppercase tracking-widest shadow-sm">
                            Active
                        </span>
                    )}
                </div>
                <div className="space-y-4">
                    <div className="space-y-1.5">
                        <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">API Key</label>
                        <input
                            type="password"
                            value={(settings[apiKeyField] as string) || ''}
                            onChange={e => onChange(apiKeyField, e.target.value)}
                            placeholder={settings[keyPresentField] ? "••••••••••••" : "Enter API Key..."}
                            className="w-full p-3 px-4 bg-white border-2 border-white rounded-xl text-xs font-mono font-bold focus:border-blue-100 outline-none transition-all shadow-sm"
                        />
                    </div>
                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between ml-1">
                            <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Model</label>
                            <button
                                type="button"
                                onClick={() => onFetchModels(provider, true)}
                                className="text-[10px] font-black text-brand-primary hover:text-brand-secondary transition-colors uppercase tracking-widest"
                                data-help-id="ai-refresh-models"
                            >
                                Refresh
                            </button>
                        </div>
                        {models && models.length > 0 ? (
                            <select
                                value={(settings[modelField] as string) || ''}
                                onChange={e => onChange(modelField, e.target.value)}
                                className="w-full p-3 px-4 bg-white border-2 border-white rounded-xl text-xs font-black focus:border-brand-primary/30 outline-none transition-all shadow-sm appearance-none"
                            >
                                <option value="">Select a model...</option>
                                {models.map(m => (
                                    <option key={m.id} value={m.id}>{m.name || m.id}</option>
                                ))}
                            </select>
                        ) : (
                            <input
                                type="text"
                                value={(settings[modelField] as string) || ''}
                                onChange={e => onChange(modelField, e.target.value)}
                                placeholder="Enter model identifier..."
                                className="w-full p-3 px-4 bg-white border-2 border-white rounded-xl text-xs font-mono font-bold focus:border-brand-primary/30 outline-none transition-all shadow-sm"
                            />
                        )}
                        {showVisionWarning && (
                            <p className="text-[11px] font-bold text-red-500 px-1 mt-2 flex items-center gap-1.5">
                                <span>⚠️</span> This model does not support image analysis
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

