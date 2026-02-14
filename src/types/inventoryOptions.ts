export type InventoryOptionType = 'size_template' | 'color_template' | 'gender_template' | 'material';

export type InventoryOptionAppliesToType = 'category' | 'sku';

export interface IInventoryOptionLink {
    id: number;
    option_type: InventoryOptionType;
    option_id: number;
    option_label?: string | null;
    applies_to_type: InventoryOptionAppliesToType;
    category_id: number | null;
    category_name: string | null;
    item_sku: string | null;
    item_name: string | null;
    updated_at?: string | null;
    source?: 'sku' | 'category' | 'default' | null;
}

export interface IInventoryOptionLinksListResponse {
    success: boolean;
    links: IInventoryOptionLink[];
    message?: string;
    error?: string;
}

export interface IAddInventoryOptionLinkRequest {
    action: 'add';
    option_type: InventoryOptionType;
    option_id: number;
    applies_to_type: InventoryOptionAppliesToType;
    category_id?: number | null;
    item_sku?: string | null;
}

export interface IDeleteInventoryOptionLinkRequest {
    action: 'delete';
    id: number;
}

export interface IClearInventoryOptionLinksForOptionRequest {
    action: 'clear_option';
    option_type: InventoryOptionType;
    option_id: number;
}

export interface IInventoryOptionLinkActionResponse {
    success: boolean;
    message?: string;
    error?: string;
    id?: number;
}

export interface IMaterial {
    id: number;
    material_name: string;
    description?: string | null;
    sort_order: number;
    is_active: boolean;
}

export interface IMaterialsListResponse {
    success: boolean;
    materials: IMaterial[];
    message?: string;
    error?: string;
}

export interface ICreateMaterialRequest {
    action: 'create';
    material_name: string;
    description?: string | null;
}

export interface IUpdateMaterialRequest {
    action: 'update';
    id: number;
    material_name?: string;
    description?: string | null;
    sort_order?: number;
    is_active?: boolean;
}

export interface IDeleteMaterialRequest {
    action: 'delete';
    id: number;
}

export interface IOptionCascadeSettings {
    cascade_order: string[];
    enabled_dimensions: string[];
    grouping_rules: Record<string, unknown>;
}

export type OptionCascadeAppliesToType = 'category' | 'sku';

export interface ICascadeConfig {
    id: number;
    applies_to_type: OptionCascadeAppliesToType;
    category_id: number | null;
    category_name: string | null;
    item_sku: string | null;
    item_name: string | null;
    settings: IOptionCascadeSettings;
    updated_at?: string | null;
}

export interface ICascadeConfigsListResponse {
    success: boolean;
    configs: ICascadeConfig[];
    error?: string;
    message?: string;
}

export interface IUpsertCascadeConfigRequest {
    action: 'upsert';
    id?: number;
    applies_to_type: OptionCascadeAppliesToType;
    category_id?: number | null;
    item_sku?: string | null;
    settings: IOptionCascadeSettings;
}

export interface IDeleteCascadeConfigRequest {
    action: 'delete';
    id: number;
}

export interface ICascadeConfigActionResponse {
    success: boolean;
    id?: number;
    error?: string;
    message?: string;
}

export interface IEffectiveCascadeSettingsResponse {
    success: boolean;
    source: 'sku' | 'category' | 'default';
    applies_to_type?: OptionCascadeAppliesToType | null;
    category_id?: number | null;
    item_sku?: string | null;
    settings: IOptionCascadeSettings;
    error?: string;
    message?: string;
}
