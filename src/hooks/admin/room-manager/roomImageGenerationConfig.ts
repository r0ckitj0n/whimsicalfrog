import {
    AUTOGENERATE_LABEL,
    ROOM_PROMPT_DROPDOWN_DEFAULTS,
    getVariableLabel
} from '../../../components/admin/settings/ai/roomPromptDropdownDefaults.js';
import type { IAIPromptDropdownOptionsByVariable } from '../../../types/ai-prompts.js';
import type { IRoomImageGenerationRequest } from '../../../types/room-generation.js';

export type RoomImageScaleMode = 'modal' | 'fullscreen' | 'fixed';

export type RoomImagePromptVariableKey =
    | 'scene_type'
    | 'room_theme'
    | 'subject_species'
    | 'subject_headwear'
    | 'display_furniture_style'
    | 'thematic_accent_decorations'
    | 'frog_action'
    | 'vibe_adjectives'
    | 'color_scheme'
    | 'background_thematic_elements'
    | 'image_style_declaration'
    | 'location_phrase'
    | 'character_statement'
    | 'aesthetic_statement'
    | 'critical_constraint_line'
    | 'no_props_line'
    | 'decorative_elements_line'
    | 'open_display_zones_line'
    | 'art_style_line'
    | 'surfaces_line'
    | 'text_constraint_line'
    | 'lighting_line';

export type RoomImageAestheticFieldKey =
    | 'scene_type'
    | 'room_theme'
    | 'subject_species'
    | 'subject_headwear'
    | 'display_furniture_style'
    | 'thematic_accent_decorations'
    | 'frog_action'
    | 'vibe_adjectives'
    | 'color_scheme'
    | 'background_thematic_elements'
    | 'image_style_declaration'
    | 'location_phrase'
    | 'character_statement'
    | 'aesthetic_statement'
    | 'critical_constraint_line'
    | 'no_props_line'
    | 'decorative_elements_line'
    | 'open_display_zones_line'
    | 'art_style_line'
    | 'surfaces_line'
    | 'text_constraint_line'
    | 'lighting_line';

export const ROOM_IMAGE_AESTHETIC_FIELDS: Array<{ key: RoomImageAestheticFieldKey; label: string }> = [
    { key: 'scene_type', label: 'Scene Type' },
    { key: 'subject_species', label: 'Subject Species' },
    { key: 'subject_headwear', label: 'Subject Headwear' },
    { key: 'room_theme', label: 'Room Theme' },
    { key: 'display_furniture_style', label: 'Furniture Style' },
    { key: 'thematic_accent_decorations', label: 'Accent Decor' },
    { key: 'frog_action', label: 'Subject Action' },
    { key: 'vibe_adjectives', label: 'Vibe Adjectives' },
    { key: 'color_scheme', label: 'Color Scheme' },
    { key: 'background_thematic_elements', label: 'Background Elements' },
    { key: 'image_style_declaration', label: 'Image Style Declaration' },
    { key: 'location_phrase', label: 'Location Phrase' },
    { key: 'character_statement', label: 'Character Statement' },
    { key: 'aesthetic_statement', label: 'Aesthetic Statement' },
    { key: 'art_style_line', label: 'Art Style Line' },
    { key: 'surfaces_line', label: 'Surface Treatment Line' },
    { key: 'lighting_line', label: 'Lighting Line' },
    { key: 'text_constraint_line', label: 'Text Constraint Line' },
    { key: 'critical_constraint_line', label: 'Critical Constraint Line' },
    { key: 'no_props_line', label: 'No Props Line' },
    { key: 'decorative_elements_line', label: 'Decorative Elements Line' },
    { key: 'open_display_zones_line', label: 'Open Display Zones Line' }
];

export const DEFAULT_ROOM_IMAGE_VARIABLE_VALUES: Record<RoomImagePromptVariableKey, string> = {
    scene_type: AUTOGENERATE_LABEL,
    room_theme: AUTOGENERATE_LABEL,
    subject_species: AUTOGENERATE_LABEL,
    subject_headwear: AUTOGENERATE_LABEL,
    display_furniture_style: AUTOGENERATE_LABEL,
    thematic_accent_decorations: AUTOGENERATE_LABEL,
    frog_action: AUTOGENERATE_LABEL,
    vibe_adjectives: AUTOGENERATE_LABEL,
    color_scheme: AUTOGENERATE_LABEL,
    background_thematic_elements: AUTOGENERATE_LABEL,
    image_style_declaration: AUTOGENERATE_LABEL,
    location_phrase: AUTOGENERATE_LABEL,
    character_statement: AUTOGENERATE_LABEL,
    aesthetic_statement: AUTOGENERATE_LABEL,
    critical_constraint_line: AUTOGENERATE_LABEL,
    no_props_line: AUTOGENERATE_LABEL,
    decorative_elements_line: AUTOGENERATE_LABEL,
    open_display_zones_line: AUTOGENERATE_LABEL,
    art_style_line: AUTOGENERATE_LABEL,
    surfaces_line: AUTOGENERATE_LABEL,
    text_constraint_line: AUTOGENERATE_LABEL,
    lighting_line: AUTOGENERATE_LABEL
};

export const imageSizeForScaleMode: Record<RoomImageScaleMode, IRoomImageGenerationRequest['size']> = {
    modal: '1024x1024',
    fullscreen: '1536x1024',
    fixed: '1024x1536'
};

export const targetAspectRatioForScaleMode: Record<RoomImageScaleMode, number> = {
    modal: 1024 / 768,
    fullscreen: 1280 / 896,
    fixed: 1024 / 768
};

const ROOM_IMAGE_VARIABLE_FALLBACKS: Record<RoomImagePromptVariableKey, string> = {
    scene_type: 'general page or environment',
    room_theme: 'Invent a custom room theme that fits the room name and description; do not pick from preset dropdown examples',
    subject_species: 'no character (environment only)',
    subject_headwear: 'no headwear',
    display_furniture_style: 'Invent a custom display furniture style specifically for this room context; do not pick from preset dropdown examples',
    thematic_accent_decorations: 'Invent custom accent decorations that fit this room context and keep staging surfaces open; do not pick from preset dropdown examples',
    frog_action: 'no characters present',
    vibe_adjectives: 'Invent custom vibe adjectives that best fit this room concept; do not pick from preset dropdown examples',
    color_scheme: 'Invent a custom color scheme suitable for this room concept and product presentation; do not pick from preset dropdown examples',
    background_thematic_elements: 'Invent custom oversized thematic background elements for this room context; do not pick from preset dropdown examples',
    image_style_declaration: 'A high-quality render for room',
    location_phrase: 'inside a themed retail environment',
    character_statement: 'No characters should appear in this scene unless explicitly selected in subject options.',
    aesthetic_statement: "Background walls/ceiling include decorative {{background_thematic_elements}} that reinforce the room's function.",
    critical_constraint_line: 'CRITICAL CONSTRAINT: All display surfaces (shelves, racks, counters, tabletops, hooks, bins, stands) must remain completely empty and flat.',
    no_props_line: 'Do NOT place any props, decor, products, containers, signage, books, plants, objects, or accents on any display surface.',
    decorative_elements_line: 'Keep decorative elements strictly on walls, ceiling, floor edges, corners, or perimeter zones away from display surfaces.',
    open_display_zones_line: 'Maintain large uninterrupted open display zones for future item placement.',
    art_style_line: 'Art style: use the selected style direction and keep it consistent across the full scene.',
    surfaces_line: 'Surfaces: clear, well-defined materials with clean presentation and production-ready composition.',
    text_constraint_line: 'Text constraint: strictly NO TEXT anywhere in the image.',
    lighting_line: 'Lighting: balanced and production-ready, keeping empty display surfaces clearly readable for later product insertion.'
};

export const mapRenderContextToScaleMode = (renderContext?: string | null): RoomImageScaleMode => {
    if (renderContext === 'fullscreen') return 'fullscreen';
    if (renderContext === 'fixed') return 'fixed';
    return 'modal';
};

const LEGACY_AUTOGENERATE_LABEL = '(autogenerate)';

const normalizeAutoDropdownOption = (value: string): string => {
    const trimmed = value.trim();
    const key = trimmed.toLowerCase();
    if (key === AUTOGENERATE_LABEL.toLowerCase() || key === LEGACY_AUTOGENERATE_LABEL) {
        return AUTOGENERATE_LABEL;
    }
    return trimmed;
};

const normalizeRoomDropdownOptions = (rawOptions: string[], fallbackValue: string): string[] => {
    const seen = new Set<string>();
    const normalized: string[] = [];
    const allOptions = [AUTOGENERATE_LABEL, ...rawOptions, fallbackValue];

    for (const option of allOptions) {
        const candidate = normalizeAutoDropdownOption(String(option || ''));
        if (!candidate) continue;
        const key = candidate.toLowerCase();
        if (seen.has(key)) continue;
        seen.add(key);
        normalized.push(candidate);
    }

    const nonAuto = normalized
        .filter((option) => option.toLowerCase() !== AUTOGENERATE_LABEL.toLowerCase())
        .sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));

    return [AUTOGENERATE_LABEL, ...nonAuto];
};

export const getRoomImageVariableOptions = (
    variableKey: RoomImagePromptVariableKey,
    dropdownOptionsByVariable: IAIPromptDropdownOptionsByVariable,
    fallbackValue = AUTOGENERATE_LABEL
): string[] => {
    const apiOptions = dropdownOptionsByVariable[variableKey];
    if (Array.isArray(apiOptions) && apiOptions.length > 0) {
        return normalizeRoomDropdownOptions(apiOptions, fallbackValue);
    }
    return normalizeRoomDropdownOptions(ROOM_PROMPT_DROPDOWN_DEFAULTS[variableKey] || [], fallbackValue);
};

export const getRoomImageVariableLabel = (variableKey: RoomImagePromptVariableKey): string => getVariableLabel(variableKey);

interface ResolveRoomGenerationVariablesParams {
    roomNumber: string;
    roomName: string;
    doorLabel: string;
    displayOrder?: number;
    description?: string;
    variableDefaults?: Record<string, string>;
    values?: Partial<Record<RoomImagePromptVariableKey, string>>;
}

const normalizeAutoValue = (value: string, fallbackInstruction: string): string => {
    const trimmed = normalizeAutoDropdownOption(value);
    if (!trimmed || trimmed.toLowerCase() === AUTOGENERATE_LABEL.toLowerCase()) {
        return fallbackInstruction;
    }
    return trimmed;
};

export const resolveRoomGenerationVariables = ({
    roomNumber,
    roomName,
    doorLabel,
    displayOrder,
    description,
    variableDefaults,
    values
}: ResolveRoomGenerationVariablesParams): Record<string, string> => {
    const mergedValues: Record<RoomImagePromptVariableKey, string> = {
        ...DEFAULT_ROOM_IMAGE_VARIABLE_VALUES,
        ...(values || {})
    };

    return {
        ...(variableDefaults || {}),
        room_number: roomNumber.trim(),
        room_name: roomName.trim(),
        door_label: doorLabel.trim(),
        display_order: String(displayOrder || 0),
        room_description: String(description || '').trim(),
        scene_type: normalizeAutoValue(mergedValues.scene_type, ROOM_IMAGE_VARIABLE_FALLBACKS.scene_type),
        room_theme: normalizeAutoValue(mergedValues.room_theme, ROOM_IMAGE_VARIABLE_FALLBACKS.room_theme),
        subject_species: normalizeAutoValue(mergedValues.subject_species, ROOM_IMAGE_VARIABLE_FALLBACKS.subject_species),
        subject_headwear: normalizeAutoValue(mergedValues.subject_headwear, ROOM_IMAGE_VARIABLE_FALLBACKS.subject_headwear),
        display_furniture_style: normalizeAutoValue(mergedValues.display_furniture_style, ROOM_IMAGE_VARIABLE_FALLBACKS.display_furniture_style),
        thematic_accent_decorations: normalizeAutoValue(mergedValues.thematic_accent_decorations, ROOM_IMAGE_VARIABLE_FALLBACKS.thematic_accent_decorations),
        frog_action: normalizeAutoValue(mergedValues.frog_action, ROOM_IMAGE_VARIABLE_FALLBACKS.frog_action),
        vibe_adjectives: normalizeAutoValue(mergedValues.vibe_adjectives, ROOM_IMAGE_VARIABLE_FALLBACKS.vibe_adjectives),
        color_scheme: normalizeAutoValue(mergedValues.color_scheme, ROOM_IMAGE_VARIABLE_FALLBACKS.color_scheme),
        background_thematic_elements: normalizeAutoValue(mergedValues.background_thematic_elements, ROOM_IMAGE_VARIABLE_FALLBACKS.background_thematic_elements),
        image_style_declaration: normalizeAutoValue(mergedValues.image_style_declaration, ROOM_IMAGE_VARIABLE_FALLBACKS.image_style_declaration),
        location_phrase: normalizeAutoValue(mergedValues.location_phrase, ROOM_IMAGE_VARIABLE_FALLBACKS.location_phrase),
        character_statement: normalizeAutoValue(mergedValues.character_statement, ROOM_IMAGE_VARIABLE_FALLBACKS.character_statement),
        aesthetic_statement: normalizeAutoValue(mergedValues.aesthetic_statement, ROOM_IMAGE_VARIABLE_FALLBACKS.aesthetic_statement),
        critical_constraint_line: normalizeAutoValue(mergedValues.critical_constraint_line, ROOM_IMAGE_VARIABLE_FALLBACKS.critical_constraint_line),
        no_props_line: normalizeAutoValue(mergedValues.no_props_line, ROOM_IMAGE_VARIABLE_FALLBACKS.no_props_line),
        decorative_elements_line: normalizeAutoValue(mergedValues.decorative_elements_line, ROOM_IMAGE_VARIABLE_FALLBACKS.decorative_elements_line),
        open_display_zones_line: normalizeAutoValue(mergedValues.open_display_zones_line, ROOM_IMAGE_VARIABLE_FALLBACKS.open_display_zones_line),
        art_style_line: normalizeAutoValue(mergedValues.art_style_line, ROOM_IMAGE_VARIABLE_FALLBACKS.art_style_line),
        surfaces_line: normalizeAutoValue(mergedValues.surfaces_line, ROOM_IMAGE_VARIABLE_FALLBACKS.surfaces_line),
        text_constraint_line: normalizeAutoValue(mergedValues.text_constraint_line, ROOM_IMAGE_VARIABLE_FALLBACKS.text_constraint_line),
        lighting_line: normalizeAutoValue(mergedValues.lighting_line, ROOM_IMAGE_VARIABLE_FALLBACKS.lighting_line)
    };
};
