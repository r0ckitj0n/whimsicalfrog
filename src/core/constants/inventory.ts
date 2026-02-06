export const MAPPING_TYPE = {
    ITEM: 'item',
    CATEGORY: 'category',
    LINK: 'link',
    CONTENT: 'content',
    BUTTON: 'button',
    PAGE: 'page',
    MODAL: 'modal',
    ACTION: 'action'
} as const;

export type MappingType = typeof MAPPING_TYPE[keyof typeof MAPPING_TYPE];

export const ITEM_STATUS = {
    ACTIVE: 'active',
    DRAFT: 'draft',
    ARCHIVED: 'archived'
} as const;

export type ItemStatus = typeof ITEM_STATUS[keyof typeof ITEM_STATUS];

export const CATEGORY = {
    ALL: 'all'
} as const;

export const DIMENSION = {
    GENDER: 'gender',
    SIZE: 'size',
    COLOR: 'color'
} as const;

export type Dimension = typeof DIMENSION[keyof typeof DIMENSION];

export const COST_CATEGORY = {
    MATERIALS: 'materials',
    LABOR: 'labor',
    ENERGY: 'energy',
    EQUIPMENT: 'equipment'
} as const;

export type CostCategory = typeof COST_CATEGORY[keyof typeof COST_CATEGORY];
