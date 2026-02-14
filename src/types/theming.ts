/**
 * Theming Types
 * Centralized type definitions for theme words, variants, fonts, and styling
 */

export interface IThemeWordVariant {
    id: number;
    theme_word_id: number;
    variant_text: string;
    is_active: boolean;
}

export interface IThemeWord {
    id: number;
    word: string;
    category: string;
    category_id: number;
    is_active: boolean;
    variants: (IThemeWordVariant | string)[];
}

export interface IThemeWordCategory {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    is_active: boolean;
}

export interface IFontMeta {
    id: string;
    name: string;
    detail: string;
    stack: string;
    category: string;
    sample: string;
    importFamily?: string;
    normalizedStack?: string;
}

export interface ICssCatalogData {
    generatedAt: string;
    allClasses: string[];
    sources: Array<{
        file: string;
        classes: string[];
    }>;
}

export interface ICssRule {
    id?: number;
    selector: string;
    property: string;
    value: string;
    important: boolean;
    note?: string;
}

export interface IThemeWordsResponse {
    success: boolean;
    words?: IThemeWord[];
    categories?: IThemeWordCategory[];
    error?: string;
}

export interface IThemeWordsHook {
    words: IThemeWord[];
    categories: IThemeWordCategory[];
    isLoading: boolean;
    error: string | null;
    fetchWords: () => Promise<void>;
    fetchCategories: () => Promise<void>;
    saveWord: (wordData: Partial<IThemeWord>) => Promise<IThemeWordsResponse | null>;
    deleteWord: (id: number) => Promise<boolean>;
    saveCategory: (catData: Partial<IThemeWordCategory>) => Promise<IThemeWordsResponse | null>;
    deleteCategory: (id: number) => Promise<boolean>;
}

// Branding Types (migrated from useBranding.ts)
export interface IPaletteColor {
    name: string;
    hex: string;
}

export interface IBrandingTokens {
    business_brand_primary: string;
    business_brand_secondary: string;
    business_brand_accent: string;
    business_brand_background: string;
    business_brand_text: string;
    business_toast_text: string;
    business_brand_font_primary: string;
    business_brand_font_secondary: string;
    business_brand_font_title_primary: string;
    business_brand_font_title_secondary: string;
    business_public_header_bg: string;
    business_public_header_text: string;
    business_public_modal_bg: string;
    business_public_modal_text: string;
    business_public_page_bg: string;
    business_public_page_text: string;
    business_button_primary_bg: string;
    business_button_primary_hover: string;
    business_button_secondary_bg: string;
    business_button_secondary_hover: string;
    business_button_primary_text: string;
    business_button_secondary_text: string;
    business_button_height: string;
    business_admin_modal_radius: string;
    business_admin_modal_body_padding: string;
    business_admin_modal_shadow: string;
    business_brand_palette: string | IPaletteColor[];
    business_css_vars: string;
    business_transition_fast: string;
    business_transition_normal: string;
    business_transition_smooth: string;
    business_shadow_sm: string;
    business_shadow_md: string;
    business_shadow_lg: string;
    business_scrollbar_thumb: string;
    business_scrollbar_track: string;
    business_scrollbar_width: string;
    business_hover_lift: string;
    business_hover_lift_lg: string;
}

// Global Entity Types (migrated from global-entities hooks)
export interface IGlobalColor {
    id: number;
    color_name: string;
    color_code?: string;
    category?: string;
    is_active: boolean;
}

export interface IGlobalSize {
    id: number;
    size_name: string;
    size_code: string;
    category?: string;
    is_active: boolean;
    display_order: number;
}

export interface IGlobalGender {
    id: number;
    gender_name: string;
    is_active: boolean;
    display_order: number;
}

// Gender Template Types
export interface IGenderTemplateItem {
    id?: number;
    template_id?: number;
    gender_name: string;
    display_order: number;
    is_active?: boolean;
}

export interface IGenderTemplate {
    id: number;
    template_name: string;
    description?: string;
    category?: string;
    is_active: boolean;
    gender_count?: number;
    genders?: IGenderTemplateItem[];
}

// Color Template Types (migrated from useColorTemplates.ts)
export interface IColorTemplateItem {
    id?: number;
    template_id?: number;
    color_name: string;
    color_code: string;
    display_order: number;
    is_active?: boolean;
}

export interface IColorTemplate {
    id: number;
    template_name: string;
    description?: string;
    category?: string;
    is_active: boolean;
    color_count?: number;
    colors?: IColorTemplateItem[];
}

// Size Template Types (migrated from useSizeTemplates.ts)
export interface ISizeTemplateItem {
    id?: number;
    template_id?: number;
    size_name: string;
    size_code: string;
    price_adjustment: number;
    display_order: number;
    is_active?: boolean;
}

export interface ISizeTemplate {
    id: number;
    template_name: string;
    description?: string;
    category?: string;
    is_active: boolean;
    size_count?: number;
    sizes?: ISizeTemplateItem[];
}

// ============================================================================
// API Response Interfaces
// ============================================================================

/** Response for branding tokens endpoint */
export interface IBrandingResponse {
    success: boolean;
    tokens: IBrandingTokens;
    message?: string;
}

/** Response for branding action endpoints */
export interface IBrandingActionResponse {
    success: boolean;
    tokens?: IBrandingTokens;
    message?: string;
}

export interface IGlobalEntitiesResponse<T> {
    success: boolean;
    genders?: T;
    sizes?: T;
    colors?: T;
    templates?: T;
    error?: string;
}
