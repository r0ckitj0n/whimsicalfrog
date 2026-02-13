export type InventoryOptionType = 'size_template' | 'color_template' | 'material';

export type InventoryOptionAppliesToType = 'category' | 'sku';

export interface IInventoryOptionLink {
    id: number;
    option_type: InventoryOptionType;
    option_id: number;
    applies_to_type: InventoryOptionAppliesToType;
    category_id: number | null;
    category_name: string | null;
    item_sku: string | null;
    item_name: string | null;
    updated_at?: string | null;
}

export interface IInventoryOptionLinksListResponse {
    success: boolean;
    links: IInventoryOptionLink[];
    message?: string;
    error?: string;
}

export interface IUpsertInventoryOptionLinkRequest {
    action: 'upsert';
    option_type: InventoryOptionType;
    option_id: number;
    applies_to_type: InventoryOptionAppliesToType;
    category_id?: number | null;
    item_sku?: string | null;
}

export interface IClearInventoryOptionLinkRequest {
    action: 'clear';
    option_type: InventoryOptionType;
    option_id: number;
}

export interface IUpsertInventoryOptionLinkResponse {
    success: boolean;
    message?: string;
    error?: string;
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

