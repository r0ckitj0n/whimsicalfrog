import { useEffect, useMemo, useState } from 'react';
import { ApiClient } from '../../../../../core/ApiClient.js';
import { useAIPromptTemplates } from '../../../../../hooks/admin/useAIPromptTemplates.js';
import {
    DEFAULT_ROOM_IMAGE_VARIABLE_VALUES,
    getRoomImageVariableOptions,
    imageSizeForScaleMode,
    mapRenderContextToScaleMode,
    resolveRoomGenerationVariables,
    ROOM_IMAGE_AESTHETIC_FIELDS,
    RoomImageAestheticFieldKey
} from '../../../../../hooks/admin/room-manager/roomImageGenerationConfig.js';
import type { IBackgroundsHook, IBackground } from '../../../../../types/backgrounds.js';
import type { IRoomData } from '../../../../../types/room.js';
import type { IRoomImageGenerationRequest } from '../../../../../types/room-generation.js';

interface VisualsTabProps {
    backgrounds: IBackgroundsHook;
    selectedRoom: string;
    selectedRoomData: IRoomData | null;
    previewImage: { url: string; name: string } | null;
    setPreviewImage: React.Dispatch<React.SetStateAction<{ url: string; name: string } | null>>;
    onApplyBackground: (bgId: number) => Promise<void>;
    onDeleteBackground: (bgId: number, name: string) => Promise<void>;
    onBackgroundUpload: (e: React.ChangeEvent<HTMLInputElement>) => Promise<void>;
    onGenerateBackground: (request: IRoomImageGenerationRequest) => Promise<{ success: boolean; error?: string }>;
    getImageUrl: (bg: { webp_filename?: string; image_filename?: string }) => string;
}

const initialAestheticValues: Record<RoomImageAestheticFieldKey, string> = {
    room_theme: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.room_theme,
    display_furniture_style: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.display_furniture_style,
    thematic_accent_decorations: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.thematic_accent_decorations,
    frog_action: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.frog_action,
    vibe_adjectives: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.vibe_adjectives,
    color_scheme: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.color_scheme,
    background_thematic_elements: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.background_thematic_elements,
    image_style_declaration: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.image_style_declaration,
    location_phrase: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.location_phrase,
    character_statement: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.character_statement,
    aesthetic_statement: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.aesthetic_statement
};

export const VisualsTab: React.FC<VisualsTabProps> = ({
    backgrounds,
    selectedRoom,
    selectedRoomData,
    previewImage,
    setPreviewImage,
    onApplyBackground,
    onDeleteBackground,
    onBackgroundUpload,
    onGenerateBackground,
    getImageUrl
}) => {
    const [selectedTemplateKey, setSelectedTemplateKey] = useState<string>('');
    const [aestheticValues, setAestheticValues] = useState<Record<RoomImageAestheticFieldKey, string>>(initialAestheticValues);
    const [isGenerating, setIsGenerating] = useState(false);
    const [settingsTemplateKey, setSettingsTemplateKey] = useState<string>('');
    const {
        templates,
        variables,
        dropdownOptionsByVariable,
        isLoading: templatesLoading,
        fetchTemplates,
        fetchVariables,
        fetchDropdownOptions
    } = useAIPromptTemplates();

    const roomTemplates = useMemo(
        () => templates.filter(t => t.context_type === 'room_generation' && !!t.is_active),
        [templates]
    );

    useEffect(() => {
        void fetchTemplates();
        void fetchVariables();
        void fetchDropdownOptions();
        void ApiClient.get<{ success?: boolean; settings?: { room_generation_template_key?: string } }>(
            '/api/ai_settings.php',
            { action: 'get_settings' }
        ).then((res) => {
            const key = String(res?.settings?.room_generation_template_key || '').trim();
            if (key) setSettingsTemplateKey(key);
        }).catch(() => {
            // Optional preference key; ignore if unavailable.
        });
    }, [fetchDropdownOptions, fetchTemplates, fetchVariables]);

    useEffect(() => {
        if (roomTemplates.length === 0) return;
        const preferred = settingsTemplateKey || 'room_staging_empty_shelves_v1';
        const foundPreferred = roomTemplates.find((t) => t.template_key === preferred);
        setSelectedTemplateKey(foundPreferred?.template_key || roomTemplates[0].template_key);
    }, [roomTemplates, settingsTemplateKey]);

    const variableDefaults = useMemo(() => {
        const map: Record<string, string> = {};
        for (const v of variables) {
            map[v.variable_key] = String(v.sample_value || '');
        }
        return map;
    }, [variables]);

    const roomNumber = useMemo(() => String(selectedRoomData?.room_number || selectedRoom || '').trim(), [selectedRoom, selectedRoomData?.room_number]);
    const roomName = useMemo(() => String(selectedRoomData?.room_name || '').trim(), [selectedRoomData?.room_name]);

    const resolvedVariables = useMemo(() => resolveRoomGenerationVariables({
        roomNumber,
        roomName,
        doorLabel: String(selectedRoomData?.door_label || roomName || roomNumber),
        displayOrder: Number(selectedRoomData?.display_order || 0),
        description: String(selectedRoomData?.description || ''),
        variableDefaults,
        values: aestheticValues
    }), [aestheticValues, roomName, roomNumber, selectedRoomData?.description, selectedRoomData?.display_order, selectedRoomData?.door_label, variableDefaults]);

    const imageSize = useMemo(() => {
        const scaleMode = mapRenderContextToScaleMode(String(selectedRoomData?.render_context || 'modal'));
        return imageSizeForScaleMode[scaleMode];
    }, [selectedRoomData?.render_context]);

    const handleGenerate = async () => {
        if (!roomNumber) {
            window.WFToast?.error?.('Select a room first');
            return;
        }
        if (!selectedTemplateKey) {
            window.WFToast?.error?.('Select an AI template');
            return;
        }

        setIsGenerating(true);
        const result = await onGenerateBackground({
            room_number: roomNumber,
            template_key: selectedTemplateKey,
            variables: resolvedVariables,
            provider: 'openai',
            size: imageSize,
            background_name: roomName ? `${roomNumber} - ${roomName}` : roomNumber
        });
        setIsGenerating(false);

        if (!result.success) {
            window.WFToast?.error?.(result.error || 'AI image generation failed');
            return;
        }
        window.WFToast?.success?.('AI room background generated and saved to library');
    };

    return (
        <div className="p-8 lg:p-10 flex-1 min-h-0 overflow-hidden">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 h-full min-h-0">
                {/* Create New Background */}
                <div className="space-y-6 h-full min-h-0 flex flex-col">
                    <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50 pb-2">Create New Background</h4>
                    <div className="space-y-6 overflow-y-auto pr-1 flex-1 min-h-0">
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
                        <p className="text-[11px] text-slate-500">
                            Aesthetic variables use the same room-image generation flow as room creation.
                        </p>
                        <div className="max-h-56 overflow-auto pr-1">
                            <div className="grid grid-cols-1 gap-2">
                                {ROOM_IMAGE_AESTHETIC_FIELDS.map((field) => {
                                    const options = getRoomImageVariableOptions(field.key, dropdownOptionsByVariable);
                                    const currentValue = aestheticValues[field.key] || '';
                                    const normalizedValue = options.includes(currentValue) ? currentValue : (options[0] || currentValue);
                                    return (
                                        <div key={field.key} className="space-y-1">
                                            <label className="text-[10px] font-bold text-slate-500">{field.label}</label>
                                            <select
                                                value={normalizedValue}
                                                onChange={(e) => setAestheticValues(prev => ({ ...prev, [field.key]: e.target.value }))}
                                                className="w-full text-[11px] p-2 border border-slate-200 rounded-lg bg-white"
                                                disabled={isGenerating}
                                            >
                                                {options.map((option) => (
                                                    <option key={`visuals-room-${field.key}-${option}`} value={option}>
                                                        {option}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                        <p className="text-[10px] text-slate-500">
                            Size: <span className="font-bold">{imageSize}</span>
                        </p>
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
                </div>

                {/* Library */}
                <div className="space-y-6 h-full min-h-0 flex flex-col">
                    <div className="flex items-center justify-between border-b border-gray-50 pb-2">
                        <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest">Room Library</h4>
                        <button
                            type="button"
                            onClick={() => document.getElementById('bg-upload-input')?.click()}
                            className="admin-action-btn btn-icon--add"
                            aria-label="Upload content"
                            title="Upload content"
                            data-help-id="common-upload"
                        />
                    </div>
                    <input id="bg-upload-input" type="file" className="hidden" accept="image/*" onChange={onBackgroundUpload} />
                    <div className="space-y-4 overflow-y-auto pr-1 flex-1 min-h-0">
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
