import { ENVIRONMENT, Environment } from '../core/constants.js';

export interface ISquareSettings {
    square_application_id: string;
    square_access_token: string;
    square_location_id: string;
    square_environment: Environment;
    square_sync_enabled: boolean;
}

// Re-export for convenience
export type { Environment } from '../core/constants.js';

// Square SDK Types
export interface ISquareCard {
    attach: (elementId: string) => Promise<void>;
    tokenize: () => Promise<{ status: string; token?: string; errors?: Array<{ message: string }> }>;
    destroy: () => Promise<void>;
}

export interface ISquarePayments {
    card: () => Promise<ISquareCard>;
}

export interface ISquare {
    payments: (appId: string, locId: string) => ISquarePayments;
}

export interface ISquareWindow extends Window {
    Square?: ISquare;
}
