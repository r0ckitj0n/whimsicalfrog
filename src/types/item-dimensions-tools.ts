export interface ItemDimensionsBackfillResult {
    ensured?: boolean;
    scanned?: number;
    missing?: number;
    updated?: number;
    skipped?: number;
    preview?: Array<{
        sku?: string;
        weight_oz?: number;
        LxWxH_in?: number[];
    }>;
}

export type ItemDimensionsToolsApiResponse =
    { success?: boolean; data?: ItemDimensionsBackfillResult; results?: ItemDimensionsBackfillResult }
    & ItemDimensionsBackfillResult;
