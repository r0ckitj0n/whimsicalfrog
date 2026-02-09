export const AUTOGENERATE_LABEL = '(autogenerate)';

export interface IRoomPromptDropdownOptionDefinition {
    variable_key: string;
    label: string;
}

export const ROOM_PROMPT_DROPDOWN_DEFINITIONS: IRoomPromptDropdownOptionDefinition[] = [
    { variable_key: 'display_furniture_style', label: 'Furniture Style' },
    { variable_key: 'thematic_accent_decorations', label: 'Accent Decor' },
    { variable_key: 'frog_action', label: 'Frog Action' },
    { variable_key: 'vibe_adjectives', label: 'Vibe' },
    { variable_key: 'color_scheme', label: 'Color Scheme' },
    { variable_key: 'background_thematic_elements', label: 'Background Elements' }
];

export const ROOM_PROMPT_DROPDOWN_DEFAULTS: Record<string, string[]> = {
    display_furniture_style: [
        AUTOGENERATE_LABEL,
        'tiered light-wood shelving units',
        'modern matte-black floating shelves',
        'rustic reclaimed wood display tables',
        'glass-front display cabinets',
        'industrial pipe-and-wood shelving',
        'curved boutique wall niches',
        'minimal white modular cubes',
        'vintage apothecary drawer wall'
    ],
    thematic_accent_decorations: [
        AUTOGENERATE_LABEL,
        'tiny potted succulents and miniature ceramic milk jugs',
        'small lanterns and mossy stones',
        'hanging ivy strands and brass trinkets',
        'vintage books and porcelain figurines',
        'woven baskets and dried florals',
        'mini chalkboards and painted pebbles',
        'glass bottles with fairy lights',
        'seasonal garlands and ribbon bundles'
    ],
    frog_action: [
        AUTOGENERATE_LABEL,
        'wiping down the empty counter with a cloth',
        'adjusting shelf spacing with a tape measure',
        'reviewing a clipboard checklist',
        'arranging decorative props near displays',
        'welcoming visitors with a cheerful wave',
        'pointing toward featured display zones',
        'inspecting lighting over the shelving',
        'sweeping the floor with a tiny broom'
    ],
    vibe_adjectives: [
        AUTOGENERATE_LABEL,
        'refreshing and bright',
        'cozy and inviting',
        'playful and energetic',
        'calm and elegant',
        'warm and nostalgic',
        'whimsical and magical',
        'modern and premium',
        'rustic and handcrafted'
    ],
    color_scheme: [
        AUTOGENERATE_LABEL,
        "robin's egg blue and soft orange",
        'sage green and cream',
        'dusty rose and antique gold',
        'navy and warm brass',
        'mint and coral',
        'charcoal and ivory',
        'lavender and pale teal',
        'terracotta and sand'
    ],
    background_thematic_elements: [
        AUTOGENERATE_LABEL,
        'giant floating fruit shapes',
        'oversized botanical motifs',
        'storybook clouds and stars',
        'subtle geometric wall inlays',
        'ornate vintage frames and arches',
        'floating paper lantern clusters',
        'soft mural waves and swirls',
        'woodland silhouettes and vines'
    ]
};
