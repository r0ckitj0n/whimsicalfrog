import type { IApiResponse } from './api.js';

export interface ISanmarColorsImportStats {
    extracted_base_colors: number;
    global_colors: {
        added: number;
        updated: number;
        total_sm: number;
    };
    template: {
        id: number;
        name: 'Sanmar';
        items_inserted: number;
    };
}

export type ISanmarColorsImportResponse = IApiResponse<{ stats: ISanmarColorsImportStats }>;

