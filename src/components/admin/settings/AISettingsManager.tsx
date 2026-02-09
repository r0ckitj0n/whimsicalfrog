import React, { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { useAISettings, IAISettings } from '../../../hooks/admin/useAISettings.js';
import { isDraftDirty } from '../../../core/utils.js';
import { ProviderConfiguration } from './ai/ProviderConfiguration.js';
import { BehaviorParameters } from './ai/BehaviorParameters.js';
import { PricingWeights } from './ai/PricingWeights.js';
import { SystemPromptsTab } from './ai/SystemPromptsTab.js';
import { ThemeWordsTab } from './ai/ThemeWordsTab.js';
import { useUnsavedChangesCloseGuard } from '../../../hooks/useUnsavedChangesCloseGuard.js';

interface AISettingsManagerProps {
    onClose?: () => void;
    title?: string;
}

interface TestDetails {
    text_test?: { success: boolean; message: string };
    image_test?: { success: boolean; message: string; data?: unknown };
    model?: string;
}

interface TestResult {
    success: boolean;
    message: string;
    details?: TestDetails;
}

type AISettingsTab = 'provider' | 'tuning' | 'theme_words' | 'prompts';

export const AISettingsManager: React.FC<AISettingsManagerProps> = ({ onClose, title }) => {
    const {
        settings,
        isLoading,
        error,
        models,
        fetchSettings,
        saveSettings,
        testProvider,
        fetchModels
    } = useAISettings();

    const [localSettings, setEditSettings] = useState<IAISettings | null>(null);
    const [initialState, setInitialState] = useState<IAISettings | null>(null);
    const [testResult, setTestResult] = useState<TestResult | null>(null);
    const [isTesting, setIsTesting] = useState(false);
    const [activeTab, setActiveTab] = useState<AISettingsTab>('provider');
    const hasInitialized = useRef(false);

    useEffect(() => {
        if (hasInitialized.current) return;
        hasInitialized.current = true;
        fetchSettings().then(data => {
            if (data) {
                setEditSettings(data);
                setInitialState(data);
                if (data.ai_provider && data.ai_provider !== 'jons_ai') {
                    fetchModels(data.ai_provider);
                }
            }
        });
    }, [fetchSettings, fetchModels]);

    useEffect(() => {
        if (localSettings?.ai_provider && localSettings.ai_provider !== 'jons_ai') {
            fetchModels(localSettings.ai_provider);
        }
    }, [localSettings?.ai_provider, fetchModels]);

    const handleSave = async (e?: React.FormEvent): Promise<boolean> => {
        if (e) e.preventDefault();
        if (localSettings) {
            const success = await saveSettings(localSettings);
            if (success) {
                setInitialState(localSettings);
                if (window.WFToast) window.WFToast.success('AI settings saved successfully!');
                return true;
            } else {
                if (window.WFToast) window.WFToast.error('Failed to save settings');
                return false;
            }
        }
        return false;
    };

    const handleTest = async () => {
        if (!localSettings?.ai_provider) return;
        setIsTesting(true);
        setTestResult(null);
        const res = await testProvider(localSettings.ai_provider);
        setTestResult(res);
        if (res.success) {
            if (window.WFToast) window.WFToast.success(res.message);
        } else {
            if (window.WFToast) window.WFToast.error(res.message);
        }
        setIsTesting(false);
    };

    const handleChange = (field: keyof IAISettings, value: unknown) => {
        if (localSettings) {
            setEditSettings({ ...localSettings, [field]: value });
        }
    };

    const providers = [
        { id: 'jons_ai', label: "Jon's AI (Local Fallback)" },
        { id: 'openai', label: 'OpenAI' },
        { id: 'anthropic', label: 'Anthropic' },
        { id: 'google', label: 'Google' },
        { id: 'meta', label: 'Meta' }
    ];

    const isDirty = !!localSettings && !!initialState && isDraftDirty(localSettings, initialState);
    const attemptClose = useUnsavedChangesCloseGuard({
        isDirty,
        isBlocked: isLoading || isTesting,
        onClose,
        onSave: () => handleSave(),
        closeAfterSave: true
    });

    if (!localSettings) return createPortal(
        <div className="admin-modal-overlay over-header show topmost">
            <div className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1000px] max-w-[95vw] h-[80vh] flex items-center justify-center p-12 text-center text-gray-500">
                Loading AI configuration...
            </div>
        </div>,
        document.body
    );

    const modalContent = (
        <div
            className="admin-modal-overlay over-header show topmost"
            role="dialog"
            aria-modal="true"
            onClick={(e) => {
                if (e.target === e.currentTarget) void attemptClose();
            }}
        >
            <div
                className="admin-modal admin-modal-content show bg-white rounded-lg shadow-xl w-[1000px] max-w-[95vw] h-[80vh] flex flex-col"
                onClick={(e) => e.stopPropagation()}
            >
                {/* Header */}
                <div className="modal-header flex items-center border-b border-gray-100 gap-4 px-6 py-4 sticky top-0 bg-white z-20">
                    <h2 className="text-xl font-black text-gray-800 flex items-center gap-3">
                        <span className="text-2xl">🤖</span> {title || 'AI Settings'}
                    </h2>

                    <div className="flex-1" />

                    <div className="flex items-center gap-4">
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={handleTest}
                                disabled={isTesting}
                                className="admin-action-btn btn-icon--refresh"
                                data-help-id="ai-test-provider"
                            />
                            <button
                                type="button"
                                onClick={() => handleSave()}
                                disabled={isLoading}
                                className={`admin-action-btn btn-icon--save dirty-only ${isDirty ? 'is-dirty' : ''}`}
                                data-help-id="ai-save-settings"
                            />
                            <button
                                onClick={() => { void attemptClose(); }}
                                className="admin-action-btn btn-icon--close"
                                data-help-id="ai-close-manager"
                            />
                        </div>
                    </div>
                </div>

                <div className="px-6 pt-4">
                    <div className="wf-tabs bg-slate-100/50 rounded-2xl p-1.5 border border-slate-200/50 flex items-center gap-1.5 self-start">
                        <button
                            type="button"
                            onClick={() => setActiveTab('provider')}
                            className={`wf-tab ${activeTab === 'provider' ? 'is-active' : ''}`}
                        >
                            AI Provider
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('tuning')}
                            className={`wf-tab ${activeTab === 'tuning' ? 'is-active' : ''}`}
                        >
                            AI Tuning
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('theme_words')}
                            className={`wf-tab ${activeTab === 'theme_words' ? 'is-active' : ''}`}
                        >
                            Theme Words
                        </button>
                        <button
                            type="button"
                            onClick={() => setActiveTab('prompts')}
                            className={`wf-tab ${activeTab === 'prompts' ? 'is-active' : ''}`}
                        >
                            System Prompts
                        </button>
                    </div>
                </div>

                <div className="modal-body wf-admin-modal-body flex-1 overflow-y-auto">
                    <div className="p-10">
                        {error && <div className="p-4 mb-8 bg-red-50 border border-red-100 text-red-600 text-xs font-bold rounded-2xl animate-in shake duration-500">Error: {error}</div>}

                        {testResult && (
                            <div className={`p-4 mb-8 rounded-2xl border text-xs font-bold ${testResult.success ? 'bg-emerald-50 border-emerald-100' : 'bg-amber-50 border-amber-100'} animate-in slide-in-from-top-4`}>
                                <div className={`text-sm mb-2 ${testResult.success ? 'text-emerald-700' : 'text-amber-700'}`}>
                                    {testResult.message}
                                </div>
                                {testResult.details && (
                                    <div className="space-y-2 text-[11px]">
                                        {testResult.details.model && (
                                            <div className="text-gray-500">
                                                Model: <span className="font-mono bg-white/50 px-1.5 py-0.5 rounded">{testResult.details.model}</span>
                                            </div>
                                        )}
                                        <div className="flex gap-4 mt-2">
                                            {testResult.details.text_test && (
                                                <div className={`flex items-center gap-1 ${testResult.details.text_test.success ? 'text-emerald-600' : 'text-red-500'}`}>
                                                    {testResult.details.text_test.success ? '✅' : '❌'} Text: {testResult.details.text_test.message}
                                                </div>
                                            )}
                                            {testResult.details.image_test && (
                                                <div className={`flex items-center gap-1 ${testResult.details.image_test.success ? 'text-emerald-600' : 'text-red-500'}`}>
                                                    {testResult.details.image_test.success ? '✅' : '❌'} Image: {testResult.details.image_test.message}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="p-3 mb-8 rounded-xl border border-blue-100 bg-blue-50 text-[11px] text-blue-700 font-semibold">
                            Generate requires image analysis. Use <span className="font-black">Test Provider</span> and make sure the Image test passes for the selected model.
                        </div>

                        <form onSubmit={handleSave} className="space-y-0">
                            {activeTab === 'provider' && (
                                <div className="space-y-10 max-w-3xl">
                                    <div className="space-y-4">
                                        <label className="block text-xs font-black text-gray-400 uppercase tracking-widest leading-none mb-1.5 ml-1">AI Provider</label>
                                        <div className="space-y-3">
                                            <select
                                                value={localSettings.ai_provider}
                                                onChange={e => handleChange('ai_provider', e.target.value)}
                                                className="w-full text-xs font-bold p-3 px-4 border-2 border-slate-50 rounded-xl bg-slate-50 text-slate-600 outline-none focus:border-brand-primary/30 transition-all cursor-pointer appearance-none"
                                            >
                                                {providers.map(p => (
                                                    <option key={p.id} value={p.id}>{p.label}</option>
                                                ))}
                                            </select>
                                            <p className="text-[10px] text-gray-400 font-bold px-1 leading-relaxed">Select the AI intelligence engine to use for generating content and pricing recommendations.</p>
                                        </div>
                                    </div>

                                    <ProviderConfiguration
                                        settings={localSettings}
                                        models={models}
                                        onFetchModels={fetchModels}
                                        onChange={handleChange}
                                    />
                                </div>
                            )}

                            {activeTab === 'tuning' && (
                                <div className="space-y-10 max-w-3xl">
                                    <BehaviorParameters
                                        settings={localSettings}
                                        onChange={handleChange}
                                    />

                                    <PricingWeights
                                        settings={localSettings}
                                        onChange={handleChange}
                                    />
                                </div>
                            )}

                            {activeTab === 'prompts' && (
                                <SystemPromptsTab />
                            )}

                            {activeTab === 'theme_words' && (
                                <ThemeWordsTab
                                    settings={localSettings}
                                    onChange={handleChange}
                                />
                            )}
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );

    return createPortal(modalContent, document.body);
};
