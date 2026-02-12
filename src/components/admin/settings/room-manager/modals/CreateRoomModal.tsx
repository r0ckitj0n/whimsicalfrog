import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { ApiClient } from '../../../../../core/ApiClient.js';
import { useAIPromptTemplates } from '../../../../../hooks/admin/useAIPromptTemplates.js';
import { useAICostEstimateConfirm } from '../../../../../hooks/admin/useAICostEstimateConfirm.js';
import {
    DEFAULT_ROOM_IMAGE_VARIABLE_VALUES,
    getRoomImageVariableOptions,
    imageSizeForScaleMode,
    resolveRoomGenerationVariables,
    RoomImagePromptVariableKey,
    targetAspectRatioForScaleMode
} from '../../../../../hooks/admin/room-manager/roomImageGenerationConfig.js';
import type { IRoomData } from '../../../../../types/room.js';
import type { IRoomImageGenerationRequest } from '../../../../../types/room-generation.js';
import {
    AUTOGENERATE_LABEL,
    CUSTOM_WRITE_YOUR_OWN_LABEL,
    CUSTOM_WRITE_YOUR_OWN_VALUE
} from '../../ai/roomPromptDropdownDefaults.js';

interface CreateRoomModalProps {
    isOpen: boolean;
    roomsData: IRoomData[];
    onClose: () => void;
    onCreateRoom: (room: Partial<IRoomData>) => Promise<{ success: boolean; error?: string; room_number?: string }>;
    onGenerateBackground: (request: IRoomImageGenerationRequest) => Promise<{ success: boolean; error?: string }>;
}

interface CreateRoomFormState {
    room_number: string;
    room_name: string;
    door_label: string;
    display_order: number;
    description: string;
    scene_type: string;
    subject_species: string;
    subject_headwear: string;
    room_theme: string;
    display_furniture_style: string;
    thematic_accent_decorations: string;
    frog_action: string;
    vibe_adjectives: string;
    color_scheme: string;
    background_thematic_elements: string;
    image_style_declaration: string;
    location_phrase: string;
    character_statement: string;
    aesthetic_statement: string;
    critical_constraint_line: string;
    no_props_line: string;
    decorative_elements_line: string;
    open_display_zones_line: string;
    art_style_line: string;
    surfaces_line: string;
    text_constraint_line: string;
    lighting_line: string;
    scale_mode: 'modal' | 'fullscreen' | 'fixed';
    generate_image: boolean;
}

const defaultFormState: CreateRoomFormState = {
    room_number: '',
    room_name: '',
    door_label: '',
    display_order: 0,
    description: '',
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
    lighting_line: DEFAULT_ROOM_IMAGE_VARIABLE_VALUES.lighting_line,
    scale_mode: 'modal',
    generate_image: true
};

const promptVariableFields: Array<keyof CreateRoomFormState> = [
    'scene_type',
    'subject_species',
    'subject_headwear',
    'room_theme',
    'display_furniture_style',
    'thematic_accent_decorations',
    'frog_action',
    'vibe_adjectives',
    'color_scheme',
    'background_thematic_elements',
    'image_style_declaration',
    'location_phrase',
    'character_statement',
    'aesthetic_statement',
    'critical_constraint_line',
    'no_props_line',
    'decorative_elements_line',
    'open_display_zones_line',
    'art_style_line',
    'surfaces_line',
    'text_constraint_line',
    'lighting_line'
];

const buildPromptPresetMap = (formValues: CreateRoomFormState): Record<string, string> => {
    return promptVariableFields.reduce<Record<string, string>>((acc, field) => {
        acc[String(field)] = String(formValues[field] || '');
        return acc;
    }, {});
};

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

export const CreateRoomModal: React.FC<CreateRoomModalProps> = ({
    isOpen,
    roomsData,
    onClose,
    onCreateRoom,
    onGenerateBackground
}) => {
    const [form, setForm] = useState<CreateRoomFormState>(defaultFormState);
    const [selectedPromptPresets, setSelectedPromptPresets] = useState<Record<string, string>>(() => buildPromptPresetMap(defaultFormState));
    const [selectedTemplateKey, setSelectedTemplateKey] = useState('room_staging_empty_shelves_v1');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [showPromptPreview, setShowPromptPreview] = useState(false);
    const [settingsTemplateKey, setSettingsTemplateKey] = useState<string>('');
    const { confirmWithEstimate } = useAICostEstimateConfirm();
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
        () => templates.filter((t) => t.context_type === 'room_generation' && Boolean(t.is_active)),
        [templates]
    );

    const selectedTemplate = useMemo(
        () => roomTemplates.find((t) => t.template_key === selectedTemplateKey) || null,
        [roomTemplates, selectedTemplateKey]
    );

    const variableDefaults = useMemo(() => {
        const map: Record<string, string> = {};
        for (const v of variables) {
            map[v.variable_key] = String(v.sample_value || '');
        }
        return map;
    }, [variables]);

    const resolvedVariables = useMemo(() => resolveRoomGenerationVariables({
        roomNumber: form.room_number,
        roomName: form.room_name,
        doorLabel: form.door_label,
        displayOrder: form.display_order,
        description: form.description,
        variableDefaults,
        values: {
            scene_type: form.scene_type,
            subject_species: form.subject_species,
            subject_headwear: form.subject_headwear,
            room_theme: form.room_theme,
            display_furniture_style: form.display_furniture_style,
            thematic_accent_decorations: form.thematic_accent_decorations,
            frog_action: form.frog_action,
            vibe_adjectives: form.vibe_adjectives,
            color_scheme: form.color_scheme,
            background_thematic_elements: form.background_thematic_elements,
            image_style_declaration: form.image_style_declaration,
            location_phrase: form.location_phrase,
            character_statement: form.character_statement,
            aesthetic_statement: form.aesthetic_statement,
            critical_constraint_line: form.critical_constraint_line,
            no_props_line: form.no_props_line,
            decorative_elements_line: form.decorative_elements_line,
            open_display_zones_line: form.open_display_zones_line,
            art_style_line: form.art_style_line,
            surfaces_line: form.surfaces_line,
            text_constraint_line: form.text_constraint_line,
            lighting_line: form.lighting_line
        }
    }), [form, variableDefaults]);

    const generatedPromptText = useMemo(() => {
        const basePrompt = selectedTemplate?.prompt_text || '';
        if (!basePrompt) return '';
        const values: Record<string, string> = resolvedVariables;
        return resolveTemplateText(basePrompt, values);
    }, [selectedTemplate?.prompt_text, resolvedVariables]);

    useEffect(() => {
        if (!isOpen) return;
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
    }, [isOpen, fetchTemplates, fetchVariables, fetchDropdownOptions]);

    useEffect(() => {
        if (!isOpen) return;
        setForm(defaultFormState);
        setSelectedPromptPresets(buildPromptPresetMap(defaultFormState));
    }, [isOpen]);

    useEffect(() => {
        if (!isOpen) return;
        const getFirstAvailableNumber = (values: Array<string | number | null | undefined>): number => {
            const used = new Set<number>(
                values
                    .map((value) => String(value ?? '').trim())
                    .filter((value) => /^\d+$/.test(value))
                    .map((value) => parseInt(value, 10))
                    .filter((value) => value >= 1)
            );
            let candidate = 1;
            while (used.has(candidate)) {
                candidate += 1;
            }
            return candidate;
        };

        const numericRooms = roomsData
            .map((r) => String(r.room_number || '').trim())
            .filter((val) => /^\d+$/.test(val))
            .map((val) => parseInt(val, 10));
        const usedRoomNumbers = new Set<number>(numericRooms.filter((n) => n >= 1));
        let candidateRoomNumber = 1;
        while (usedRoomNumbers.has(candidateRoomNumber)) {
            candidateRoomNumber += 1;
        }
        const nextRoomNumber = String(candidateRoomNumber);
        const nextDisplayOrder = getFirstAvailableNumber(roomsData.map((room) => room.display_order));

        setForm((prev) => ({
            ...prev,
            room_number: nextRoomNumber,
            display_order: nextDisplayOrder
        }));
    }, [isOpen, roomsData]);

    useEffect(() => {
        if (!isOpen || roomTemplates.length === 0) return;
        const genericDefault = roomTemplates.find((t) => t.template_key === 'room_staging_empty_shelves_v1');
        const userPreferred = roomTemplates.find((t) => t.template_key === settingsTemplateKey);
        setSelectedTemplateKey(genericDefault?.template_key || userPreferred?.template_key || roomTemplates[0].template_key);
    }, [isOpen, roomTemplates, settingsTemplateKey]);

    const updateForm = <K extends keyof CreateRoomFormState>(key: K, value: CreateRoomFormState[K]) => {
        setForm((prev) => ({ ...prev, [key]: value }));
    };
    const hasRequiredFields = Boolean(
        form.room_number.trim() &&
        form.room_name.trim() &&
        form.door_label.trim()
    );

    const renderEditableDropdown = (
        label: string,
        field: keyof CreateRoomFormState,
        value: string
    ) => {
        const options = getOptionsForVariable(String(field));
        const selectedPreset = selectedPromptPresets[String(field)] ?? (options[0] || '');
        const normalizedSelectedPreset = (selectedPreset === CUSTOM_WRITE_YOUR_OWN_VALUE || options.includes(selectedPreset))
            ? selectedPreset
            : (options[0] || '');
        const showCustomInput = normalizedSelectedPreset !== AUTOGENERATE_LABEL;
        return (
            <div>
                <label className="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">{label}</label>
                <select
                    value={normalizedSelectedPreset}
                    onChange={(e) => {
                        const nextPreset = e.target.value;
                        setSelectedPromptPresets((prev) => ({ ...prev, [String(field)]: nextPreset }));
                        updateForm(field, (nextPreset === CUSTOM_WRITE_YOUR_OWN_VALUE ? '' : nextPreset) as CreateRoomFormState[typeof field]);
                    }}
                    className="w-full text-sm p-2.5 border border-slate-200 rounded-lg bg-white"
                >
                    {options.map((opt) => (
                        <option key={`create-room-${String(field)}-${opt}`} value={opt}>
                            {opt}
                        </option>
                    ))}
                    <option value={CUSTOM_WRITE_YOUR_OWN_VALUE}>{CUSTOM_WRITE_YOUR_OWN_LABEL}</option>
                </select>
                {showCustomInput && (
                    <textarea
                        value={value}
                        onChange={(e) => updateForm(field, e.target.value as CreateRoomFormState[typeof field])}
                        rows={2}
                        className="mt-2 w-full text-sm p-2.5 border border-slate-200 rounded-lg bg-white resize-y"
                    />
                )}
            </div>
        );
    };

    const getOptionsForVariable = (variableKey: string): string[] => {
        return getRoomImageVariableOptions(variableKey as RoomImagePromptVariableKey, dropdownOptionsByVariable, AUTOGENERATE_LABEL);
    };

    const handleCopyPrompt = async () => {
        try {
            await navigator.clipboard.writeText(generatedPromptText || '');
            window.WFToast?.success?.('Prompt copied to clipboard');
        } catch (_err) {
            window.WFToast?.error?.('Failed to copy prompt');
        }
    };

    const handleSubmit = async () => {
        if (!form.room_number.trim() || !form.room_name.trim() || !form.door_label.trim()) {
            window.WFToast?.error?.('Room Number, Room Name, and Door Label are required');
            return;
        }
        if (!selectedTemplateKey && form.generate_image) {
            window.WFToast?.error?.('Select a prompt template before generating a room image');
            return;
        }

        if (form.generate_image) {
            const confirmed = await confirmWithEstimate({
                action_key: 'create_room_generate_image',
                action_label: 'Generate initial room image with AI',
                operations: [
                    { key: 'room_image_generation', label: 'Room image generation', image_generations: 1 }
                ],
                context: {
                    prompt_length: generatedPromptText.length
                },
                confirmText: 'Generate Image'
            });
            if (!confirmed) {
                window.WFToast?.info?.('Room creation canceled.');
                return;
            }
        }

        setIsSubmitting(true);
        window.WFToast?.info?.('Step 1/3: Creating room...');
        const createRes = await onCreateRoom({
            room_number: form.room_number.trim(),
            room_name: form.room_name.trim(),
            door_label: form.door_label.trim(),
            display_order: form.display_order,
            description: form.description,
            render_context: form.scale_mode,
            target_aspect_ratio: targetAspectRatioForScaleMode[form.scale_mode],
            is_active: true
        });

        if (!createRes.success) {
            setIsSubmitting(false);
            window.WFToast?.error?.(createRes.error || 'Failed to create room');
            return;
        }
        window.WFToast?.success?.('Step 1/3 complete: Room created');

        if (form.generate_image) {
            window.WFToast?.info?.('Step 2/3: Generating background image...');
            const genRes = await onGenerateBackground({
                room_number: form.room_number.trim(),
                template_key: selectedTemplateKey,
                variables: resolvedVariables,
                provider: 'openai',
                size: imageSizeForScaleMode[form.scale_mode],
                background_name: `${form.room_number.trim()} - ${form.room_name.trim()}`
            });
            if (!genRes.success) {
                window.WFToast?.error?.(genRes.error || 'Room created, but image generation failed');
                setIsSubmitting(false);
                return;
            }
            window.WFToast?.success?.('Step 2/3 complete: Background generated');
        }

        window.WFToast?.success?.(form.generate_image ? 'Step 3/3 complete: Room setup finished' : 'Step 2/2 complete: Room setup finished');
        setIsSubmitting(false);
        setShowPromptPreview(false);
        onClose();
    };

    if (!isOpen || typeof document === 'undefined') return null;

    const modalContent = (
        <>
            <div
                className="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4"
                style={{ zIndex: 'var(--wf-z-modal)' }}
                onClick={(e) => e.target === e.currentTarget && onClose()}
            >
                <div className="relative w-full max-w-5xl bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                    <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                        <div>
                            <h3 className="text-sm font-black uppercase tracking-widest text-slate-700">Create Room</h3>
                            <p className="text-[11px] text-slate-500 mt-1">Set room details and optionally generate the initial room background.</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                className="admin-action-btn btn-icon--edit"
                                onClick={() => setShowPromptPreview(true)}
                                data-help-id="room-create-open-prompt-preview"
                            />
                            <button
                                type="button"
                                className="admin-action-btn btn-icon--close"
                                onClick={onClose}
                                data-help-id="common-close"
                            />
                        </div>
                    </div>

                    <div className="p-5 max-h-[70vh] overflow-y-auto space-y-5">
                        <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                            <h4 className="text-[10px] font-black uppercase tracking-widest text-slate-600">Required Fields</h4>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">
                                        Room Number <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        value={form.room_number}
                                        onChange={(e) => updateForm('room_number', e.target.value)}
                                        className="w-full text-sm p-2.5 border border-slate-200 rounded-lg"
                                        aria-required="true"
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">
                                        Room Name <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        value={form.room_name}
                                        onChange={(e) => updateForm('room_name', e.target.value)}
                                        className="w-full text-sm p-2.5 border border-slate-200 rounded-lg"
                                        aria-required="true"
                                    />
                                </div>
                                <div>
                                    <label className="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">
                                        Door Label <span className="text-red-500">*</span>
                                    </label>
                                    <input
                                        value={form.door_label}
                                        onChange={(e) => updateForm('door_label', e.target.value)}
                                        className="w-full text-sm p-2.5 border border-slate-200 rounded-lg"
                                        aria-required="true"
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Display Order</label>
                                <input
                                    type="number"
                                    value={form.display_order}
                                    onChange={(e) => updateForm('display_order', parseInt(e.target.value, 10) || 0)}
                                    className="w-full text-sm p-2.5 border border-slate-200 rounded-lg"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Description</label>
                            <textarea
                                value={form.description}
                                onChange={(e) => updateForm('description', e.target.value)}
                                rows={2}
                                className="w-full text-sm p-2.5 border border-slate-200 rounded-lg"
                            />
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-slate-50/70 p-4 space-y-4">
                            <h4 className="text-[10px] font-black uppercase tracking-widest text-slate-600">Prompt Template</h4>

                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div className="md:col-span-2">
                                    <label className="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Prompt Template</label>
                                    <select
                                        value={selectedTemplateKey}
                                        onChange={(e) => setSelectedTemplateKey(e.target.value)}
                                        className="w-full text-sm p-2.5 border border-slate-200 rounded-lg bg-white"
                                        disabled={templatesLoading}
                                    >
                                        <option value="">Select template...</option>
                                        {roomTemplates.map((template) => (
                                            <option key={template.id} value={template.template_key}>
                                                {template.template_name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1">Scale Mode</label>
                                    <select
                                        value={form.scale_mode}
                                        onChange={(e) => updateForm('scale_mode', e.target.value as CreateRoomFormState['scale_mode'])}
                                        className="w-full text-sm p-2.5 border border-slate-200 rounded-lg bg-white"
                                    >
                                        <option value="modal">Modal (4:3)</option>
                                        <option value="fullscreen">Full Screen (dynamic)</option>
                                        <option value="fixed">Fixed</option>
                                    </select>
                                    <p className="text-[10px] text-slate-500 mt-1">
                                        Image generation size follows scale mode:
                                        {' '}
                                        <span className="font-bold">{imageSizeForScaleMode[form.scale_mode]}</span>
                                    </p>
                                </div>
                            </div>

                            <p className="text-[11px] text-slate-500">
                                Prompt variable fields default to <span className="font-mono">{AUTOGENERATE_LABEL}</span> for new rooms.
                            </p>
                            <p className="text-[11px] text-slate-500">
                                Use the dropdown to choose a preset and edit the text box below it to customize this room generation.
                            </p>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {renderEditableDropdown('Scene Type', 'scene_type', form.scene_type)}
                                {renderEditableDropdown('Subject Species', 'subject_species', form.subject_species)}
                                {renderEditableDropdown('Subject Headwear', 'subject_headwear', form.subject_headwear)}
                                {renderEditableDropdown('Room Theme', 'room_theme', form.room_theme)}
                                {renderEditableDropdown('Furniture Style', 'display_furniture_style', form.display_furniture_style)}
                                {renderEditableDropdown('Accent Decor', 'thematic_accent_decorations', form.thematic_accent_decorations)}
                                {renderEditableDropdown('Subject Action', 'frog_action', form.frog_action)}
                                {renderEditableDropdown('Vibe Adjectives', 'vibe_adjectives', form.vibe_adjectives)}
                                {renderEditableDropdown('Color Scheme', 'color_scheme', form.color_scheme)}
                                {renderEditableDropdown('Background Elements', 'background_thematic_elements', form.background_thematic_elements)}
                                {renderEditableDropdown('Image Style Declaration', 'image_style_declaration', form.image_style_declaration)}
                                {renderEditableDropdown('Location Phrase', 'location_phrase', form.location_phrase)}
                                {renderEditableDropdown('Character Statement', 'character_statement', form.character_statement)}
                                {renderEditableDropdown('Aesthetic Statement', 'aesthetic_statement', form.aesthetic_statement)}
                                {renderEditableDropdown('Critical Constraint', 'critical_constraint_line', form.critical_constraint_line)}
                                {renderEditableDropdown('No Props Line', 'no_props_line', form.no_props_line)}
                                {renderEditableDropdown('Decorative Elements Line', 'decorative_elements_line', form.decorative_elements_line)}
                                {renderEditableDropdown('Open Display Zones Line', 'open_display_zones_line', form.open_display_zones_line)}
                                {renderEditableDropdown('Art Style Line', 'art_style_line', form.art_style_line)}
                                {renderEditableDropdown('Surfaces Line', 'surfaces_line', form.surfaces_line)}
                                {renderEditableDropdown('Text Constraint Line', 'text_constraint_line', form.text_constraint_line)}
                                {renderEditableDropdown('Lighting Line', 'lighting_line', form.lighting_line)}
                            </div>
                        </div>
                    </div>

                    <div className="px-5 py-4 border-t border-slate-100 bg-slate-50/70 flex items-center justify-between">
                        <label className="inline-flex items-center gap-2 text-xs font-bold text-slate-600">
                            <input
                                type="checkbox"
                                checked={form.generate_image}
                                onChange={(e) => updateForm('generate_image', e.target.checked)}
                                className="h-4 w-4 rounded border-slate-300"
                            />
                            Generate room image after create
                        </label>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={onClose}
                                className="px-4 py-2 text-xs font-black uppercase tracking-widest rounded-lg border border-slate-300 text-slate-600 bg-white"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                onClick={() => void handleSubmit()}
                                disabled={isSubmitting || !hasRequiredFields}
                                className={`btn btn-primary px-4 py-2 text-xs font-black uppercase tracking-widest ${(isSubmitting || !hasRequiredFields) ? 'opacity-50 cursor-not-allowed' : ''}`}
                            >
                                {isSubmitting ? 'Working...' : form.generate_image ? 'Create Room + Generate Image' : 'Create Room'}
                            </button>
                        </div>
                    </div>

                    {isSubmitting && (
                        <div className="absolute inset-0 bg-white/80 backdrop-blur-sm z-20 flex flex-col items-center justify-center">
                            <span className="wf-emoji-loader text-5xl">🐸</span>
                            <p className="mt-3 text-sm font-black uppercase tracking-widest text-slate-700">
                                Creating Room...
                            </p>
                        </div>
                    )}
                </div>
            </div>

            {showPromptPreview && (
                <div className="fixed inset-0 z-[var(--z-overlay-topmost)] bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
                    <div className="w-full max-w-4xl bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden">
                        <div className="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <h4 className="text-sm font-black uppercase tracking-widest text-slate-700">Prompt Preview</h4>
                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    className="admin-action-btn btn-icon--copy"
                                    onClick={() => void handleCopyPrompt()}
                                    data-help-id="common-copy"
                                />
                                <button
                                    type="button"
                                    className="admin-action-btn btn-icon--close"
                                    onClick={() => setShowPromptPreview(false)}
                                    data-help-id="common-close"
                                />
                            </div>
                        </div>
                        <div className="p-5 space-y-2">
                            <div className="text-[11px] text-slate-500">
                                Template: <span className="font-bold text-slate-700">{selectedTemplate?.template_name || 'None selected'}</span>
                            </div>
                            <textarea
                                value={generatedPromptText}
                                readOnly
                                rows={16}
                                className="w-full text-xs font-mono p-3 border border-slate-200 rounded-lg bg-slate-50"
                            />
                        </div>
                    </div>
                </div>
            )}
        </>
    );

    return createPortal(modalContent, document.body);
};

export default CreateRoomModal;
