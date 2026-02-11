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
import {
    AUTOGENERATE_LABEL,
    CUSTOM_WRITE_YOUR_OWN_LABEL,
    CUSTOM_WRITE_YOUR_OWN_VALUE
} from '../../ai/roomPromptDropdownDefaults.js';

interface VisualsTabProps {
    backgrounds: IBackgroundsHook;
    selectedRoom: string;
    selectedRoomData: IRoomData | null;
    previewImage: {
        url: string;
        name: string;
        target_type?: 'background';
        room_number?: string;
        source_background_id?: number;
    } | null;
    setPreviewImage: React.Dispatch<React.SetStateAction<{
        url: string;
        name: string;
        target_type?: 'background';
        room_number?: string;
        source_background_id?: number;
    } | null>>;
    onApplyBackground: (bgId: number) => Promise<void>;
    onDeleteBackground: (bgId: number, name: string) => Promise<void>;
    onBackgroundUpload: (e: React.ChangeEvent<HTMLInputElement>) => Promise<void>;
    onGenerateBackground: (request: IRoomImageGenerationRequest) => Promise<{ success: boolean; error?: string }>;
    getImageUrl: (bg: { webp_filename?: string; image_filename?: string }) => string;
}

const initialAestheticValues: Record<RoomImageAestheticFieldKey, string> = {
    scene_type: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.scene_type,
    subject_species: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.subject_species,
    subject_headwear: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.subject_headwear,
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
    aesthetic_statement: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.aesthetic_statement,
    critical_constraint_line: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.critical_constraint_line,
    no_props_line: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.no_props_line,
    decorative_elements_line: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.decorative_elements_line,
    open_display_zones_line: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.open_display_zones_line,
    art_style_line: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.art_style_line,
    surfaces_line: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.surfaces_line,
    text_constraint_line: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.text_constraint_line,
    lighting_line: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.lighting_line
};

const ROOM_IMAGE_FIELD_GROUPS: Array<{ title: string; fields: RoomImageAestheticFieldKey[] }> = [
    { title: 'Scene Setup', fields: ['scene_type', 'room_theme', 'location_phrase'] },
    { title: 'Subject', fields: ['subject_species', 'subject_headwear', 'frog_action', 'character_statement'] },
    { title: 'Environment', fields: ['display_furniture_style', 'thematic_accent_decorations', 'background_thematic_elements', 'aesthetic_statement'] },
    { title: 'Style & Rendering', fields: ['image_style_declaration', 'vibe_adjectives', 'color_scheme', 'art_style_line', 'surfaces_line', 'lighting_line'] },
    { title: 'Constraints', fields: ['critical_constraint_line', 'no_props_line', 'decorative_elements_line', 'open_display_zones_line', 'text_constraint_line'] }
];

const parsePlaceholderKeys = (prompt: string): string[] => {
    const out = new Set<string>();
    const regex = /\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g;
    let match = regex.exec(prompt);
    while (match) {
        out.add(match[1]);
        match = regex.exec(prompt);
    }
    return Array.from(out);
};

const resolveTemplateText = (template: string, values: Record<string, string>): string => {
    let prompt = template;
    for (let pass = 0; pass < 5; pass += 1) {
        const keys = parsePlaceholderKeys(prompt);
        if (keys.length === 0) break;
        const prev = prompt;
        for (const key of keys) {
            const value = values[key] ?? '';
            prompt = prompt.replaceAll(`{{${key}}}`, value);
        }
        if (prompt === prev) break;
    }
    const noCharacterSelected = String(values.subject_species || '').trim().toLowerCase() === 'no character (environment only)';
    if (!noCharacterSelected) return prompt;

    // Remove character-specific lines entirely when scene is environment-only.
    return prompt
        .split('\n')
        .filter((line) => {
            const lower = line.trim().toLowerCase();
            if (!lower) return true;
            if (lower.includes('character')) return false;
            if (lower.includes('subject profile')) return false;
            if (lower.includes('subject action')) return false;
            if (lower.includes('frog')) return false;
            if (lower.includes('proprietor')) return false;
            return true;
        })
        .join('\n')
        .replace(/\n{3,}/g, '\n\n');
};

const buildPriorityInstructionBlock = (values: Record<string, string>): string => {
    const resolveInline = (input: string): string =>
        input.replace(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g, (_match, token: string) => String(values[token] || '').trim());
    const get = (key: string, fallback: string): string => resolveInline(String(values[key] || fallback).trim());
    const noCharacterSelected = String(values.subject_species || '').trim().toLowerCase() === 'no character (environment only)';
    const baseLines = [
        'PRIORITY INSTRUCTIONS (MUST FOLLOW):',
        '- Treat user-provided variable content as highest priority over generic defaults.',
        '- Preserve explicit subject count/roles and concrete actions when provided.',
        `- Ensure target page/container type is: ${get('scene_type', 'general page or environment')}`,
        `- Ensure this scene direction appears clearly in composition: ${get('room_theme', 'themed room')}`,
        `- Ensure location framing includes: ${get('location_phrase', 'room setting')}`,
        `- Ensure accent decorations include: ${get('thematic_accent_decorations', 'contextual accents')}`,
        `- Ensure background thematic elements include: ${get('background_thematic_elements', 'thematic background elements')}`,
        `- Ensure final aesthetic intent is represented: ${get('aesthetic_statement', 'cohesive aesthetic statement')}`,
        '- Do not ignore these constraints unless they conflict with safety policy.',
        ''
    ];

    const subjectLines = noCharacterSelected
        ? []
        : [
            `- Ensure subject species is: ${get('subject_species', 'no character (environment only)')}`,
            `- Ensure subject headwear/wardrobe detail is: ${get('subject_headwear', 'no headwear')}`,
            `- Ensure subject action is visibly represented: ${get('frog_action', 'no characters present')}`,
            `- Ensure subject details are visibly represented: ${get('character_statement', 'no characters unless explicitly selected')}`
        ];

    return [...baseLines.slice(0, 6), ...subjectLines, ...baseLines.slice(6)].join('\n');
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
    const [selectedScaleMode, setSelectedScaleMode] = useState<'modal' | 'fullscreen' | 'fixed'>('modal');
    const [aestheticValues, setAestheticValues] = useState<Record<RoomImageAestheticFieldKey, string>>(initialAestheticValues);
    const [selectedAestheticPresets, setSelectedAestheticPresets] = useState<Record<RoomImageAestheticFieldKey, string>>(() => {
        return ROOM_IMAGE_AESTHETIC_FIELDS.reduce((acc, field) => {
            acc[field.key] = initialAestheticValues[field.key];
            return acc;
        }, {} as Record<RoomImageAestheticFieldKey, string>);
    });
    const [isGenerating, setIsGenerating] = useState(false);
    const [showPromptPreview, setShowPromptPreview] = useState(false);
    const [generationMessage, setGenerationMessage] = useState<{ type: 'error' | 'success' | 'info'; text: string } | null>(null);
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
        const genericDefault = roomTemplates.find((t) => t.template_key === 'room_staging_empty_shelves_v1');
        const userPreferred = roomTemplates.find((t) => t.template_key === settingsTemplateKey);
        setSelectedTemplateKey(genericDefault?.template_key || userPreferred?.template_key || roomTemplates[0].template_key);
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
    const inferredScaleMode = useMemo(
        () => mapRenderContextToScaleMode(String(selectedRoomData?.render_context || 'modal')),
        [selectedRoomData?.render_context]
    );

    useEffect(() => {
        setSelectedScaleMode(inferredScaleMode);
    }, [inferredScaleMode, roomNumber]);

    const resolvedVariables = useMemo(() => resolveRoomGenerationVariables({
        roomNumber,
        roomName,
        doorLabel: String(selectedRoomData?.door_label || roomName || roomNumber),
        displayOrder: Number(selectedRoomData?.display_order || 0),
        description: String(selectedRoomData?.description || ''),
        variableDefaults,
        values: aestheticValues
    }), [aestheticValues, roomName, roomNumber, selectedRoomData?.description, selectedRoomData?.display_order, selectedRoomData?.door_label, variableDefaults]);

    const imageSize = useMemo(() => imageSizeForScaleMode[selectedScaleMode], [selectedScaleMode]);
    const selectedTemplate = useMemo(
        () => roomTemplates.find((template) => template.template_key === selectedTemplateKey) || null,
        [roomTemplates, selectedTemplateKey]
    );
    const generatedPromptText = useMemo(() => {
        const basePrompt = selectedTemplate?.prompt_text || '';
        if (!basePrompt) return '';
        const promptBody = resolveTemplateText(basePrompt, resolvedVariables);
        const priorityBlock = buildPriorityInstructionBlock(resolvedVariables);
        return `${priorityBlock}${promptBody}`;
    }, [resolvedVariables, selectedTemplate?.prompt_text]);

    const handleGenerate = async () => {
        setGenerationMessage(null);
        if (!roomNumber) {
            const message = 'Select a room first';
            window.WFToast?.error?.(message);
            setGenerationMessage({ type: 'error', text: message });
            return;
        }
        if (!selectedTemplateKey) {
            const message = 'Select an AI template';
            window.WFToast?.error?.(message);
            setGenerationMessage({ type: 'error', text: message });
            return;
        }

        try {
            setIsGenerating(true);
            setGenerationMessage({ type: 'info', text: 'Generating room image...' });
            const result = await onGenerateBackground({
                room_number: roomNumber,
                template_key: selectedTemplateKey,
                variables: resolvedVariables,
                provider: 'openai',
                size: imageSize,
                background_name: roomName ? `${roomNumber} - ${roomName}` : roomNumber
            });

            if (!result.success) {
                const message = result.error || 'AI image generation failed';
                window.WFToast?.error?.(message);
                setGenerationMessage({ type: 'error', text: message });
                return;
            }
            window.WFToast?.success?.('AI room background generated and saved to library');
            setGenerationMessage({ type: 'success', text: 'AI room background generated and saved to library.' });
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : 'AI image generation failed';
            window.WFToast?.error?.(message);
            setGenerationMessage({ type: 'error', text: message });
        } finally {
            setIsGenerating(false);
        }
    };

    return (
        <div className="p-8 lg:p-10 flex-1 min-h-0 overflow-hidden">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-10 h-full min-h-0">
                {/* Active Look */}
                <div className="space-y-6 h-full min-h-0 flex flex-col">
                    <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50 pb-2">Active Look</h4>
                    {backgrounds.activeBackground ? (
                        <div className="relative group rounded-3xl overflow-hidden border-4 border-white shadow-xl bg-white">
                            <div className="aspect-video relative overflow-hidden">
                                <img
                                    src={getImageUrl(backgrounds.activeBackground)}
                                    alt="Active background"
                                    className="w-full h-full object-cover"
                                />
                                <div className="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                                    <button
                                        type="button"
                                        className="admin-action-btn btn-icon--view"
                                        data-help-id="common-view"
                                        aria-label="View active background"
                                        onClick={() => setPreviewImage({
                                            url: getImageUrl(backgrounds.activeBackground!),
                                            name: backgrounds.activeBackground?.name || 'Active background',
                                            target_type: 'background',
                                            room_number: selectedRoom,
                                            source_background_id: Number(backgrounds.activeBackground?.id || 0)
                                        })}
                                    />
                                </div>
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

                {/* Create New Background */}
                <div className="space-y-6 h-full min-h-0 flex flex-col">
                    <h4 className="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50 pb-2">Create New Background</h4>
                    <div className="space-y-6 overflow-y-auto pr-1 flex-1 min-h-0">
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 space-y-3">
                        <div className="flex items-center justify-between gap-2">
                            <div className="text-[10px] font-black text-gray-500 uppercase tracking-widest">Generate With AI (OpenAI)</div>
                            <button
                                type="button"
                                onClick={() => setShowPromptPreview(true)}
                                disabled={!selectedTemplateKey || !generatedPromptText}
                                className="btn btn-secondary px-3 py-2 text-[10px] font-black uppercase tracking-widest disabled:opacity-60"
                            >
                                Preview Prompt
                            </button>
                        </div>
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
                        <div className="space-y-1.5">
                            <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest">Scale Mode</label>
                            <select
                                value={selectedScaleMode}
                                onChange={(e) => setSelectedScaleMode(e.target.value as 'modal' | 'fullscreen' | 'fixed')}
                                className="w-full text-xs font-bold p-2.5 border border-slate-200 rounded-lg bg-white"
                                disabled={isGenerating}
                            >
                                <option value="modal">Modal (4:3)</option>
                                <option value="fullscreen">Full Page (wide)</option>
                                <option value="fixed">Fixed (portrait)</option>
                            </select>
                        </div>
                        <div className="max-h-56 overflow-auto pr-1">
                            <div className="space-y-4">
                                {ROOM_IMAGE_FIELD_GROUPS.map((group) => (
                                    <section key={group.title} className="rounded-xl border border-slate-200 p-3 bg-slate-50/40">
                                        <h5 className="text-[10px] font-black text-slate-600 uppercase tracking-widest mb-2">{group.title}</h5>
                                        <div className="grid grid-cols-1 gap-2">
                                            {group.fields.map((fieldKey) => {
                                                const field = ROOM_IMAGE_AESTHETIC_FIELDS.find((f) => f.key === fieldKey);
                                                if (!field) return null;
                                                const options = getRoomImageVariableOptions(field.key, dropdownOptionsByVariable);
                                                const currentValue = aestheticValues[field.key] || '';
                                                const selectedPreset = selectedAestheticPresets[field.key] || options[0] || '';
                                                const normalizedSelectedPreset = (selectedPreset === CUSTOM_WRITE_YOUR_OWN_VALUE || options.includes(selectedPreset))
                                                    ? selectedPreset
                                                    : (options[0] || '');
                                                const normalizedPresetValue = normalizedSelectedPreset.trim().toLowerCase();
                                                const normalizedCurrentValue = currentValue.trim().toLowerCase();
                                                const autogeneratedLabel = AUTOGENERATE_LABEL.toLowerCase();
                                                const isAutoPreset = normalizedPresetValue === autogeneratedLabel
                                                    || normalizedPresetValue === '(autogenerate)';
                                                const isAutoText = normalizedCurrentValue === autogeneratedLabel
                                                    || normalizedCurrentValue === '(autogenerate)';
                                                const showCustomInput = !isAutoPreset && !isAutoText;

                                                return (
                                                    <div key={field.key} className="space-y-1">
                                                        <label className="text-[10px] font-bold text-slate-500">{field.label}</label>
                                                        <select
                                                            value={normalizedSelectedPreset}
                                                            onChange={(e) => {
                                                                const nextPreset = e.target.value;
                                                                setSelectedAestheticPresets(prev => ({ ...prev, [field.key]: nextPreset }));
                                                                setAestheticValues(prev => ({
                                                                    ...prev,
                                                                    [field.key]: nextPreset === CUSTOM_WRITE_YOUR_OWN_VALUE ? '' : nextPreset
                                                                }));
                                                            }}
                                                            className="w-full text-[11px] p-2 border border-slate-200 rounded-lg bg-white"
                                                            disabled={isGenerating}
                                                        >
                                                            {options.map((option) => (
                                                                <option key={`visuals-room-${field.key}-${option}`} value={option}>
                                                                    {option}
                                                                </option>
                                                            ))}
                                                            <option value={CUSTOM_WRITE_YOUR_OWN_VALUE}>{CUSTOM_WRITE_YOUR_OWN_LABEL}</option>
                                                        </select>
                                                        {showCustomInput && (
                                                            <textarea
                                                                value={currentValue}
                                                                onChange={(e) => setAestheticValues(prev => ({ ...prev, [field.key]: e.target.value }))}
                                                                rows={2}
                                                                className="w-full text-[11px] p-2 border border-slate-200 rounded-lg bg-white resize-y"
                                                                disabled={isGenerating}
                                                            />
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </section>
                                ))}
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={() => void handleGenerate()}
                            disabled={isGenerating || !selectedTemplateKey || roomTemplates.length === 0}
                            className="w-full btn btn-primary px-3 py-2 text-[10px] font-black uppercase tracking-widest disabled:opacity-60"
                        >
                            {isGenerating ? 'Generating...' : 'Generate Room Image'}
                        </button>
                        {generationMessage && (
                            <p className={`text-[11px] ${
                                generationMessage.type === 'error'
                                    ? 'text-red-600'
                                    : generationMessage.type === 'success'
                                        ? 'text-emerald-700'
                                        : 'text-slate-500'
                            }`}>
                                {generationMessage.text}
                            </p>
                        )}
                    </div>
                    </div>
                </div>

                {/* Library */}
                <div className="space-y-6 h-full min-h-0 flex flex-col overflow-hidden">
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
                    <div
                        className="space-y-4 overflow-y-scroll overscroll-contain pr-1 flex-1 min-h-0 wf-scrollbar"
                        style={{ maxHeight: 'calc(var(--admin-modal-content-height, 95vh) - 13rem)' }}
                    >
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
                                            <button
                                                type="button"
                                                onClick={() => setPreviewImage({
                                                    url: getImageUrl(bg),
                                                    name: bg.name,
                                                    target_type: 'background',
                                                    room_number: selectedRoom,
                                                    source_background_id: Number(bg.id)
                                                })}
                                                className="admin-action-btn btn-icon--view"
                                                data-help-id="common-view"
                                                aria-label={`View ${bg.name}`}
                                            />
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
            {showPromptPreview && (
                <div className="fixed inset-0 z-[var(--wf-z-modal)] bg-black/45 backdrop-blur-sm p-4 flex items-center justify-center">
                    <div className="w-full max-w-3xl max-h-[85vh] bg-white rounded-2xl border border-slate-200 shadow-2xl flex flex-col">
                        <div className="px-4 py-3 border-b border-slate-200 flex items-center justify-between">
                            <h5 className="text-xs font-black uppercase tracking-widest text-slate-700">Resolved Prompt Preview</h5>
                            <button
                                type="button"
                                onClick={() => setShowPromptPreview(false)}
                                className="admin-action-btn btn-icon--close"
                                aria-label="Close prompt preview"
                            />
                        </div>
                        <div className="p-4 overflow-auto">
                            <pre className="text-[11px] leading-relaxed whitespace-pre-wrap break-words text-slate-700 font-mono">
                                {generatedPromptText || 'No prompt available. Select a template first.'}
                            </pre>
                        </div>
                        <div className="px-4 py-3 border-t border-slate-200 flex justify-end">
                            <button
                                type="button"
                                onClick={() => setShowPromptPreview(false)}
                                className="btn btn-primary px-4 py-2 text-[10px] font-black uppercase tracking-widest"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
