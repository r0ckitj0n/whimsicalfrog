import { useEffect, useMemo, useState } from 'react';
import { IBackgroundsHook, IBackground } from '../../../../../types/backgrounds.js';
import { useAIPromptTemplates } from '../../../../../hooks/admin/useAIPromptTemplates.js';
import type { IRoomImageGenerationRequest } from '../../../../../types/room-generation.js';

interface VisualsTabProps {
    backgrounds: IBackgroundsHook;
    selectedRoom: string;
    previewImage: { url: string; name: string } | null;
    setPreviewImage: React.Dispatch<React.SetStateAction<{ url: string; name: string } | null>>;
    onApplyBackground: (bgId: number) => Promise<void>;
    onDeleteBackground: (bgId: number, name: string) => Promise<void>;
    onBackgroundUpload: (e: React.ChangeEvent<HTMLInputElement>) => Promise<void>;
    onGenerateBackground: (request: IRoomImageGenerationRequest) => Promise<{ success: boolean; error?: string }>;
    getImageUrl: (bg: { webp_filename?: string; image_filename?: string }) => string;
}

export const VisualsTab: React.FC<VisualsTabProps> = ({
    backgrounds,
    selectedRoom,
    previewImage,
    setPreviewImage,
    onApplyBackground,
    onDeleteBackground,
    onBackgroundUpload,
    onGenerateBackground,
    getImageUrl
}) => {
    const [selectedTemplateKey, setSelectedTemplateKey] = useState<string>('');
    const [variableValues, setVariableValues] = useState<Record<string, string>>({});
    const [isGenerating, setIsGenerating] = useState(false);
    const {
        templates,
        variables,
        isLoading: templatesLoading,
        fetchTemplates,
        fetchVariables
    } = useAIPromptTemplates();

    const roomTemplates = useMemo(
        () => templates.filter(t => t.context_type === 'room_generation' && !!t.is_active),
        [templates]
    );

    useEffect(() => {
        void fetchTemplates();
        void fetchVariables();
    }, [fetchTemplates, fetchVariables]);

    useEffect(() => {
        if (!selectedTemplateKey && roomTemplates.length > 0) {
            setSelectedTemplateKey(roomTemplates[0].template_key);
        }
    }, [roomTemplates, selectedTemplateKey]);

    useEffect(() => {
        if (variables.length === 0) return;
        setVariableValues(prev => {
            const next = { ...prev };
            for (const v of variables) {
                if (!(v.variable_key in next) && v.sample_value) {
                    next[v.variable_key] = v.sample_value;
                }
            }
            return next;
        });
    }, [variables]);

    const handleGenerate = async () => {
        if (!selectedRoom) {
            window.WFToast?.error?.('Select a room first');
            return;
        }
        if (!selectedTemplateKey) {
            window.WFToast?.error?.('Select an AI template');
            return;
        }

        setIsGenerating(true);
        const result = await onGenerateBackground({
            room_number: selectedRoom,
            template_key: selectedTemplateKey,
            variables: variableValues,
            provider: 'openai'
        });
        setIsGenerating(false);

        if (!result.success) {
            window.WFToast?.error?.(result.error || 'AI image generation failed');
            return;
        }
        window.WFToast?.success?.('AI room background generated and saved to library');
    };

    return (
        <div className="p-8 lg:p-10 overflow-y-auto flex-1">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-10">
                {/* Active Look */}
                <div className="space-y-6">
                    <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50 pb-2">Active Look</h4>
                    {backgrounds.activeBackground ? (
                        <div className="relative group rounded-3xl overflow-hidden border-4 border-white shadow-xl bg-white">
                            <div className="aspect-video relative overflow-hidden">
                                <img
                                    src={getImageUrl(backgrounds.activeBackground)}
                                    alt="Active background"
                                    className="w-full h-full object-cover"
                                />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent pointer-events-none" />
                                <div className="absolute bottom-4 left-4 right-4 text-white">
                                    <p className="text-sm font-black truncate">{backgrounds.activeBackground.name}</p>
                                    <p className="text-[9px] font-bold uppercase tracking-widest opacity-60">Deployed</p>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="aspect-video rounded-3xl border-2 border-dashed border-gray-100 flex flex-col items-center justify-center text-center p-6 bg-slate-50/50 italic text-slate-400 text-xs">
                            No active background
                        </div>
                    )}
                </div>

                {/* Upload */}
                <div className="space-y-6">
                    <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50 pb-2">Add Variation</h4>
                    <button
                        type="button"
                        onClick={() => document.getElementById('bg-upload-input')?.click()}
                        className="w-full h-40 border-2 border-dashed border-gray-200 rounded-3xl flex flex-col items-center justify-center text-center p-8 hover:border-blue-200 hover:bg-blue-50/30 transition-all group bg-white"
                        data-help-id="common-upload"
                    >
                        <div className="w-12 h-12 bg-slate-50 rounded-2xl flex items-center justify-center text-2xl mb-3 group-hover:bg-white transition-all">📁</div>
                        <p className="text-xs font-black text-slate-600 uppercase tracking-widest">Upload Content</p>
                    </button>
                    <input id="bg-upload-input" type="file" className="hidden" accept="image/*" onChange={onBackgroundUpload} />

                    <div className="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
                        <div className="text-[10px] font-black text-gray-500 uppercase tracking-widest">Generate With AI (OpenAI)</div>
                        <div className="space-y-1.5">
                            <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest">Template</label>
                            <select
                                value={selectedTemplateKey}
                                onChange={(e) => setSelectedTemplateKey(e.target.value)}
                                className="w-full text-xs font-bold p-2.5 border border-slate-200 rounded-lg bg-white"
                                disabled={templatesLoading || isGenerating}
                            >
                                <option value="">Select template...</option>
                                {roomTemplates.map(template => (
                                    <option key={template.id} value={template.template_key}>
                                        {template.template_name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="max-h-40 overflow-auto space-y-2 pr-1">
                            {variables.map(v => (
                                <div key={`room-var-${v.id}`} className="space-y-1">
                                    <label className="text-[10px] font-bold text-slate-500">{v.display_name}</label>
                                    <input
                                        type="text"
                                        value={variableValues[v.variable_key] ?? ''}
                                        onChange={(e) => setVariableValues(prev => ({ ...prev, [v.variable_key]: e.target.value }))}
                                        className="w-full text-[11px] p-2 border border-slate-200 rounded-lg bg-white"
                                        disabled={isGenerating}
                                    />
                                </div>
                            ))}
                        </div>
                        <button
                            type="button"
                            onClick={() => void handleGenerate()}
                            disabled={isGenerating || !selectedTemplateKey || roomTemplates.length === 0}
                            className="w-full btn btn-primary px-3 py-2 text-[10px] font-black uppercase tracking-widest disabled:opacity-60"
                        >
                            {isGenerating ? 'Generating...' : 'Generate Room Image'}
                        </button>
                    </div>
                </div>

                {/* Library */}
                <div className="space-y-6">
                    <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50 pb-2">Room Library</h4>
                    <div className="space-y-4">
                        {backgrounds.backgrounds.map((bg: IBackground) => {
                            const is_active = backgrounds.activeBackground?.id === bg.id;
                            return (
                                <div key={bg.id} className={`group relative rounded-2xl overflow-hidden border-2 ${is_active ? 'border-emerald-400 shadow-md shadow-emerald-50' : 'border-slate-50'}`}>
                                    <div className="aspect-video relative overflow-hidden bg-slate-100">
                                        <img
                                            src={getImageUrl(bg)}
                                            className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000"
                                        />
                                        <div className="absolute inset-0 bg-slate-900/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                            <button onClick={() => setPreviewImage({ url: getImageUrl(bg), name: bg.name })} className="px-3 py-1.5 bg-white text-slate-900 text-[9px] font-black uppercase tracking-widest rounded-lg" data-help-id="common-inspect">Inspect</button>
                                            {!is_active && <button onClick={() => onApplyBackground(bg.id)} className="px-3 py-1.5 bg-emerald-500 text-white text-[9px] font-black uppercase tracking-widest rounded-lg" data-help-id="common-deploy">Deploy</button>}
                                        </div>
                                    </div>
                                    <div className="p-3 bg-white flex justify-between items-center">
                                        <span className="text-[10px] font-black truncate text-slate-700">{bg.name}</span>
                                        <button onClick={() => onDeleteBackground(bg.id, bg.name)} className="admin-action-btn btn-icon--delete" data-help-id="common-delete"></button>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>
        </div>
    );
};
