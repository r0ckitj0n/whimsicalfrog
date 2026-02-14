import type { IApiResponse } from './api.js';

export interface ISanmarColorsImportStats {
    extracted_base_colors: number;
    global_colors: {
        added: number;
        updated: number;
        total_sanmar: number;
    };
    template: {
        id: number;
        name: 'Sanmar';
        items_inserted: number;
    };
    migration: {
        renamed: number;
        merged: number;
        item_color_assignments_moved: number;
        legacy_deactivated: number;
        codes_backfilled: number;
        template_items_rebuilt: number;
    };
}

export type ISanmarColorsImportResponse = IApiResponse<{ stats: ISanmarColorsImportStats }>;

export type ISanmarColorsMigrateResponse = IApiResponse<{ migration: ISanmarColorsImportStats['migration'] }>;
